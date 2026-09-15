<?php
declare(strict_types=1);

final class EvaluationSurveyMediaEvidenceModel
{
    private Database $db;
    private string $storageRoot;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('evaluaciones_encuestas');
        $this->storageRoot = BASE_PATH . '/storage/evaluation-evidence';
    }

    public function attempt(int $attemptId, int $userId): ?array
    {
        return $this->db->fetch('SELECT a.*, f.control_mode, f.audio_visual_upload_failure_policy, f.audio_visual_interruption_policy, f.audio_visual_voice_policy, f.audio_visual_permission_policy, f.audio_visual_quality_profile FROM evaluation_survey_attempts a JOIN evaluation_survey_forms f ON f.id=a.form_id WHERE a.id=? AND a.user_id=? LIMIT 1', [$attemptId, $userId]);
    }

    public function evidenceForAttempt(int $attemptId): ?array
    {
        return $this->db->fetch('SELECT * FROM evaluation_survey_media_evidence WHERE attempt_id=? ORDER BY segment_number DESC, id DESC LIMIT 1', [$attemptId]);
    }

    public function evidencesForAttempt(int $attemptId): array
    {
        return $this->db->fetchAll('SELECT * FROM evaluation_survey_media_evidence WHERE attempt_id=? ORDER BY segment_number ASC, id ASC', [$attemptId]);
    }

    /** Removes audiovisual files and database rows for an evaluation attempt. */
    public function deleteForAttempt(int $attemptId): void
    {
        if ($attemptId <= 0) {
            return;
        }

        $evidenceRows = $this->db->fetchAll(
            'SELECT id, storage_key FROM evaluation_survey_media_evidence WHERE attempt_id = ?',
            [$attemptId]
        );
        $captureRows = $this->db->fetchAll(
            'SELECT storage_key FROM evaluation_survey_screen_captures WHERE attempt_id = ?',
            [$attemptId]
        );
        $keys = [];
        foreach ($evidenceRows as $row) {
            $keys[] = (string) ($row['storage_key'] ?? '');
            $chunks = $this->db->fetchAll(
                'SELECT storage_key FROM evaluation_survey_media_chunks WHERE evidence_id = ?',
                [(int) ($row['id'] ?? 0)]
            );
            foreach ($chunks as $chunk) {
                $keys[] = (string) ($chunk['storage_key'] ?? '');
            }
        }
        foreach ($captureRows as $row) {
            $keys[] = (string) ($row['storage_key'] ?? '');
        }

        $this->deleteStorageFiles($keys);
        $this->db->execute('DELETE FROM evaluation_survey_screen_captures WHERE attempt_id = ?', [$attemptId]);
        $this->db->execute('DELETE FROM evaluation_survey_media_evidence WHERE attempt_id = ?', [$attemptId]);
    }

    public function setConsent(int $attemptId, int $userId, bool $consented): array
    {
        $attempt = $this->attempt($attemptId, $userId);
        if (!$attempt || !$this->isAudioVisualMode((string) ($attempt['control_mode'] ?? ''))) return ['ok' => false, 'reason' => 'mode_not_enabled'];
        if (!$consented) {
            $evidence = $this->upsertEvidence($attempt, 'not_consented', 'consent_required', 'El usuario no aceptó la captura audiovisual.');
            return ['ok' => false, 'reason' => 'consent_required', 'evidence_id' => (int) ($evidence['id'] ?? 0)];
        }
        $existing = $this->evidenceForAttempt((int) $attempt['id']);
        $evidence = ($existing && $this->reopenedAfterEvidence((int) $attempt['id'], $existing))
            ? $this->createEvidenceSegment($attempt, (int) ($existing['segment_number'] ?? 1) + 1)
            : $this->upsertEvidence($attempt, 'recording', null, null);
        $this->db->execute('UPDATE evaluation_survey_media_evidence SET consented_at=COALESCE(consented_at,NOW()), updated_at=NOW() WHERE id=?', [(int) $evidence['id']]);
        return ['ok' => true, 'evidence_id' => (int) $evidence['id'], 'upload_failure_policy' => (string) ($attempt['audio_visual_upload_failure_policy'] ?? 'continue'), 'interruption_policy' => (string) ($attempt['audio_visual_interruption_policy'] ?? 'pause'), 'voice_policy' => (string) ($attempt['audio_visual_voice_policy'] ?? 'warn'), 'permission_policy' => (string) ($attempt['audio_visual_permission_policy'] ?? 'pause'), 'quality_profile' => (string) ($attempt['audio_visual_quality_profile'] ?? 'economical')];
    }

    public function markRecordingStarted(int $attemptId, int $userId, int $evidenceId): bool
    {
        $context = $this->mediaContext($attemptId, $userId, $evidenceId);
        if (!$context || !$this->isAudioVisualMode((string) ($context['control_mode'] ?? '')) || (string) ($context['evidence_status'] ?? '') === 'failed') {
            return false;
        }
        $this->db->execute('UPDATE evaluation_survey_media_evidence SET recording_started_at=COALESCE(recording_started_at,NOW()), updated_at=NOW() WHERE id=? AND attempt_id=?', [$evidenceId, $attemptId]);
        return true;
    }

    public function uploadChunk(int $attemptId, int $userId, int $evidenceId, int $chunkNumber, array $file, ?string $mimeType): array
    {
        $context = $this->mediaContext($attemptId, $userId, $evidenceId);
        if (!$context || !$this->isAudioVisualMode((string) ($context['control_mode'] ?? '')) || $chunkNumber < 0 || (string) ($context['evidence_status'] ?? '') === 'failed') return ['ok' => false, 'reason' => 'invalid_media_session'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) return ['ok' => false, 'reason' => 'invalid_chunk'];
        $mimeType = substr(trim((string) ($mimeType ?: ($file['type'] ?? 'application/octet-stream'))), 0, 120);
        if (!$this->allowedMime($mimeType)) return ['ok' => false, 'reason' => 'unsupported_media_type'];
        $operational = $this->operationalSettingsForEvidence($evidenceId);
        $chunkLimit = max(1, (int) ($operational['test_evidence_chunk_size_mb'] ?? 8)) * 1024 * 1024;
        $maxSize = max(1, (int) ($operational['test_evidence_max_size_mb'] ?? 250)) * 1024 * 1024;
        $size = (int) filesize((string) $file['tmp_name']);
        if ($size <= 0 || $size > $chunkLimit) return ['ok' => false, 'reason' => 'chunk_too_large'];
        $relative = 'attempts/' . $attemptId . '/chunks/chunk-' . $chunkNumber . '.bin'; $absolute = $this->absolutePath($relative); $this->ensureDirectory(dirname($absolute));
        if (!move_uploaded_file((string) $file['tmp_name'], $absolute)) return ['ok' => false, 'reason' => 'chunk_storage_failed'];
        $sha256 = hash_file('sha256', $absolute) ?: null;
        $accepted = $this->db->transaction(function (Database $db) use ($evidenceId, $chunkNumber, $relative, $size, $sha256, $mimeType, $maxSize): bool {
            $evidence = $db->fetch('SELECT uploaded_bytes FROM evaluation_survey_media_evidence WHERE id=? FOR UPDATE', [$evidenceId]);
            if (!$evidence) return false;
            $previous = $db->fetch('SELECT size_bytes FROM evaluation_survey_media_chunks WHERE evidence_id=? AND chunk_number=? FOR UPDATE', [$evidenceId, $chunkNumber]);
            $previousSize = $previous ? (int) $previous['size_bytes'] : 0;
            if ((int) $evidence['uploaded_bytes'] - $previousSize + $size > $maxSize) return false;
            $delta = $size - $previousSize;
            $db->execute('INSERT INTO evaluation_survey_media_chunks (evidence_id,chunk_number,storage_key,size_bytes,sha256) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE storage_key=VALUES(storage_key),size_bytes=VALUES(size_bytes),sha256=VALUES(sha256),uploaded_at=CURRENT_TIMESTAMP', [$evidenceId, $chunkNumber, $relative, $size, $sha256]);
            $db->execute('UPDATE evaluation_survey_media_evidence SET uploaded_bytes=GREATEST(0,uploaded_bytes+?),status="uploading",mime_type=?,recording_started_at=COALESCE(recording_started_at,NOW()),upload_started_at=COALESCE(upload_started_at,NOW()),updated_at=NOW() WHERE id=?', [$delta, $mimeType, $evidenceId]);
            return true;
        });
        if (!$accepted) {
            $stored = $this->db->fetch('SELECT storage_key FROM evaluation_survey_media_chunks WHERE evidence_id=? AND chunk_number=? LIMIT 1', [$evidenceId, $chunkNumber]);
            if (!$stored && is_file($absolute)) unlink($absolute);
            return ['ok' => false, 'reason' => 'evidence_too_large'];
        }
        return ['ok' => true, 'chunk_number' => $chunkNumber, 'sha256' => $sha256];
    }

    public function finalize(int $attemptId, int $userId, int $evidenceId, ?int $durationSeconds = null): array
    {
        $attempt = $this->attempt($attemptId, $userId); $evidence = $this->evidenceForAttempt($attemptId);
        if (!$attempt || !$evidence || (int) $evidence['id'] !== $evidenceId) return ['ok' => false, 'reason' => 'evidence_not_found'];
        $chunks = $this->db->fetchAll('SELECT chunk_number, storage_key, size_bytes FROM evaluation_survey_media_chunks WHERE evidence_id=? ORDER BY chunk_number ASC', [$evidenceId]);
        if (!$chunks) { $this->markFailure($evidenceId, 'no_chunks', 'No se recibieron fragmentos audiovisuales.'); return ['ok' => false, 'reason' => 'no_chunks']; }
        $expected = 0;
        foreach ($chunks as $chunk) {
            if ((int) $chunk['chunk_number'] !== $expected || !is_file($this->absolutePath((string) $chunk['storage_key']))) {
                $this->markFailure($evidenceId, 'missing_chunk', 'Falta un fragmento audiovisual.');
                return ['ok' => false, 'reason' => 'missing_chunk'];
            }
            $expected++;
        }
        $mime = strtolower((string) ($evidence['mime_type'] ?? 'video/webm'));
        $extension = strpos($mime, 'video/mp4') === 0 ? 'mp4' : 'webm';
        $relative = 'attempts/' . $attemptId . '/evidence-' . $evidenceId . '.' . $extension;
        $this->db->execute('UPDATE evaluation_survey_media_evidence SET status="processing", storage_key=?, duration_seconds=?, recording_finished_at=COALESCE(recording_finished_at,NOW()), upload_finished_at=NOW(), processing_status="queued", processing_error=NULL, processed_at=NULL, updated_at=NOW() WHERE id=? AND status IN ("uploading","processing")', [$relative, $durationSeconds !== null ? max(0, $durationSeconds) : null, $evidenceId]);
        (new EvaluationSurveyMediaProcessingService($this->db))->enqueue($evidenceId);
        return ['ok' => true, 'status' => 'processing', 'storage_key' => $relative, 'chunks_total' => count($chunks)];
    }

    public function markChunkUploadFailure(int $attemptId, int $userId, int $evidenceId, int $chunkNumber, string $reason): bool
    {
        $context = $this->mediaContext($attemptId, $userId, $evidenceId);
        if (!$context || $chunkNumber < 0) {
            return false;
        }
        $reason = mb_substr(trim($reason) !== '' ? trim($reason) : 'No se pudo enviar el fragmento audiovisual.', 0, 500);
        $this->db->execute('UPDATE evaluation_survey_media_evidence SET status="failed", processing_status="failed", failure_code="chunk_upload_failed", failure_reason=?, processing_error=?, updated_at=NOW() WHERE id=? AND status IN ("recording","uploading","processing")', [$reason, $reason, $evidenceId]);
        return true;
    }

    public function recordRisk(int $attemptId, int $userId, string $eventType, string $severity, ?float $confidence, array $metadata = [], ?int $evidenceId = null): bool
    {
        $attempt = $this->attempt($attemptId, $userId); if (!$attempt || !$this->isAudioVisualMode((string) ($attempt['control_mode'] ?? ''))) return false;
        $eventType = preg_replace('/[^a-z0-9_]/', '', strtolower($eventType)); if ($eventType === '' || strlen($eventType) > 80 || !in_array($severity, ['info','attention','risk'], true)) return false;
        if ($eventType === 'multiple_voice_possible') $severity = 'attention';
        $json = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null; if ($json !== null) $json = substr($json, 0, 5000);
        if ($evidenceId !== null && (!$this->evidenceForAttempt($attemptId) || (int) ($this->evidenceForAttempt($attemptId)['id'] ?? 0) !== $evidenceId)) $evidenceId = null;
        $this->db->execute('INSERT INTO evaluation_survey_media_risk_events (attempt_id,evidence_id,event_type,severity,confidence,metadata) VALUES (?,?,?,?,?,?)', [$attemptId, $evidenceId, $eventType, $severity, $confidence !== null ? max(0, min(1, $confidence)) : null, $json]); return true;
    }

    public function evidenceFile(int $attemptId): ?array
    {
        return $this->evidenceFileForEvidence($this->evidenceForAttempt($attemptId));
    }

    public function evidenceByIdForAttempt(int $attemptId, int $evidenceId): ?array
    {
        return $this->db->fetch('SELECT * FROM evaluation_survey_media_evidence WHERE id=? AND attempt_id=? LIMIT 1', [$evidenceId, $attemptId]);
    }

    public function evidenceFileForEvidence(?array $evidence): ?array
    {
        if (!$evidence || !in_array((string) ($evidence['status'] ?? ''), ['saved', 'partial'], true) || !$evidence['storage_key']) return null; $path = $this->absolutePath((string) $evidence['storage_key']);
        return is_file($path) ? ['path' => $path, 'mime_type' => (string) ($evidence['mime_type'] ?? 'video/webm'), 'size' => (int) filesize($path)] : null;
    }

    public function assemblePartial(int $attemptId, int $actorUserId): array
    {
        $attempt = $this->db->fetch('SELECT id FROM evaluation_survey_attempts WHERE id=? LIMIT 1', [$attemptId]);
        $evidence = $this->evidenceForAttempt($attemptId);
        if (!$attempt || !$evidence || !in_array((string) ($evidence['status'] ?? ''), ['recording', 'uploading', 'failed', 'partial'], true)) return ['ok' => false, 'reason' => 'partial_not_available'];
        $chunks = $this->db->fetchAll('SELECT chunk_number,storage_key FROM evaluation_survey_media_chunks WHERE evidence_id=? ORDER BY chunk_number ASC', [(int) $evidence['id']]);
        if (!$chunks) return ['ok' => false, 'reason' => 'no_chunks'];
        $mime = strtolower((string) ($evidence['mime_type'] ?? 'video/webm')); $extension = strpos($mime, 'video/mp4') === 0 ? 'mp4' : 'webm';
        $relative = 'attempts/' . $attemptId . '/evidence-' . (int) $evidence['id'] . '-partial.' . $extension; $absolute = $this->absolutePath($relative); $this->ensureDirectory(dirname($absolute));
        $output = fopen($absolute . '.part', 'wb'); if ($output === false) return ['ok' => false, 'reason' => 'partial_storage_failed'];
        $expected = 0; $joined = 0;
        try {
            foreach ($chunks as $chunk) {
                if ((int) $chunk['chunk_number'] !== $expected) break;
                $input = fopen($this->absolutePath((string) $chunk['storage_key']), 'rb'); if ($input === false) break;
                $joined += (int) stream_copy_to_stream($input, $output); fclose($input); $expected++;
            }
        } finally { fclose($output); }
        if ($expected === 0 || $joined <= 0 || !rename($absolute . '.part', $absolute)) { @unlink($absolute . '.part'); return ['ok' => false, 'reason' => 'partial_storage_failed']; }
        $this->db->execute('UPDATE evaluation_survey_media_evidence SET status="partial", storage_key=?, file_size=?, processing_status="completed", processing_error=NULL, processing_summary_json=?, processed_at=NOW(), updated_at=NOW() WHERE id=?', [$relative, $joined, json_encode(['processor' => 'manual_partial_assembly_v1', 'chunks_joined' => $expected, 'partial' => true], JSON_UNESCAPED_UNICODE), (int) $evidence['id']]);
        $this->auditAccess($attemptId, (int) $evidence['id'], $actorUserId, 'video_viewed');
        return ['ok' => true, 'chunks_joined' => $expected, 'file_size' => $joined];
    }

    public function recoverForAttempts(array $attemptIds, int $actorUserId): array
    {
        $candidates = $this->recoveryCandidatesForAttempts($attemptIds);
        $result = ['recovered' => [], 'failed' => [], 'skipped' => []];
        foreach (array_values(array_unique(array_map('intval', $attemptIds))) as $attemptId) {
            if ($attemptId <= 0 || !isset($candidates[$attemptId])) {
                if ($attemptId > 0) $result['skipped'][] = $attemptId;
                continue;
            }
            $recovery = $this->assemblePartial($attemptId, $actorUserId);
            if (!empty($recovery['ok'])) $result['recovered'][$attemptId] = $recovery;
            else $result['failed'][$attemptId] = $recovery['reason'] ?? 'recovery_failed';
        }
        return $result;
    }

    public function auditAccess(int $attemptId, int $evidenceId, int $actorUserId, string $action): void
    {
        if (!in_array($action, ['result_viewed','video_viewed','video_downloaded','screen_capture_viewed'], true)) return;
        $this->db->execute('INSERT INTO evaluation_survey_media_access_audit (attempt_id,evidence_id,actor_user_id,action,ip_address,user_agent) VALUES (?,?,?,?,?,?)', [$attemptId, $evidenceId ?: null, $actorUserId, $action, substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null]);
    }

    public function auditScreenCaptureAccess(int $attemptId, int $captureId, int $actorUserId): void
    {
        $capture = $this->db->fetch('SELECT evidence_id FROM evaluation_survey_screen_captures WHERE id=? AND attempt_id=? LIMIT 1', [$captureId, $attemptId]);
        if (!$capture) return;
        $this->auditAccess($attemptId, (int) ($capture['evidence_id'] ?? 0), $actorUserId, 'screen_capture_viewed');
    }

    public function risksForAttempt(int $attemptId): array { return $this->db->fetchAll('SELECT * FROM evaluation_survey_media_risk_events WHERE attempt_id=? ORDER BY created_at ASC,id ASC', [$attemptId]); }
    public function screenCapturesForAttempt(int $attemptId): array
    {
        return $this->db->fetchAll('SELECT * FROM evaluation_survey_screen_captures WHERE attempt_id=? ORDER BY capture_number ASC', [$attemptId]);
    }

    public function recoveryCandidatesForAttempts(array $attemptIds): array
    {
        $attemptIds = array_values(array_unique(array_filter(array_map('intval', $attemptIds), static fn(int $id): bool => $id > 0)));
        if (!$attemptIds) return [];
        $placeholders = implode(',', array_fill(0, count($attemptIds), '?'));
        $rows = $this->db->fetchAll(
            'SELECT e.attempt_id, e.id AS evidence_id, e.status, e.processing_status,
                    e.storage_key, COUNT(DISTINCT c.id) AS chunk_count,
                    COUNT(DISTINCT s.id) AS capture_count
             FROM evaluation_survey_media_evidence e
             LEFT JOIN evaluation_survey_media_chunks c ON c.evidence_id=e.id
             LEFT JOIN evaluation_survey_screen_captures s ON s.attempt_id=e.attempt_id
             WHERE e.attempt_id IN (' . $placeholders . ')
               AND e.status IN ("recording", "uploading", "failed", "partial")
             GROUP BY e.attempt_id,e.id,e.status,e.processing_status,e.storage_key
             HAVING COUNT(DISTINCT c.id) > 0
             ORDER BY e.attempt_id ASC',
            $attemptIds
        );
        $result = [];
        foreach ($rows as $row) $result[(int) $row['attempt_id']] = $row;
        return $result;
    }

    public function uploadScreenCapture(int $attemptId, int $userId, int $evidenceId, string $source, int $captureNumber, array $file, ?int $questionId, string $eventType): array
    {
        $context = $this->mediaContext($attemptId, $userId, $evidenceId);
        if (!$context || !$this->isAudioVisualMode((string) ($context['control_mode'] ?? '')) || empty($context['recording_started_at']) || $captureNumber < 0 || !in_array($source, ['screen', 'canvas'], true)) return ['ok' => false, 'reason' => 'recording_not_started'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) return ['ok' => false, 'reason' => 'invalid_screen_capture'];
        $mime = strtolower(substr(trim((string) ($file['type'] ?? '')), 0, 120));
        if (!in_array($mime, ['image/jpeg', 'image/webp'], true)) return ['ok' => false, 'reason' => 'unsupported_screen_capture_type'];
        $size = (int) filesize((string) $file['tmp_name']); $imageInfo = @getimagesize((string) $file['tmp_name']);
        if ($size <= 0 || $size > (1 * 1024 * 1024) || !is_array($imageInfo) || (string) ($imageInfo['mime'] ?? '') !== $mime) return ['ok' => false, 'reason' => 'invalid_screen_capture'];
        $relative = 'attempts/' . $attemptId . '/screens/capture-' . $captureNumber . '.' . ($mime === 'image/webp' ? 'webp' : 'jpg');
        $absolute = $this->absolutePath($relative); $this->ensureDirectory(dirname($absolute));
        if (!move_uploaded_file((string) $file['tmp_name'], $absolute)) return ['ok' => false, 'reason' => 'screen_capture_storage_failed'];
        $sha256 = hash_file('sha256', $absolute) ?: '';
        try {
            $this->db->execute('INSERT INTO evaluation_survey_screen_captures (attempt_id,evidence_id,capture_source,capture_number,question_id,event_type,mime_type,storage_key,file_size,sha256,captured_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE evidence_id=VALUES(evidence_id),capture_source=VALUES(capture_source),question_id=VALUES(question_id),event_type=VALUES(event_type),mime_type=VALUES(mime_type),storage_key=VALUES(storage_key),file_size=VALUES(file_size),sha256=VALUES(sha256),captured_at=NOW()', [$attemptId, $evidenceId ?: null, $source, $captureNumber, $questionId ?: null, mb_substr(preg_replace('/[^a-z0-9_]/', '', strtolower($eventType)) ?: 'periodic', 0, 80), $mime, $relative, $size, $sha256]);
        } catch (Throwable $error) { if (is_file($absolute)) unlink($absolute); return ['ok' => false, 'reason' => 'screen_capture_record_failed']; }
        return ['ok' => true, 'capture_number' => $captureNumber, 'sha256' => $sha256, 'source' => $source];
    }

    public function screenCaptureFile(int $attemptId, int $captureId): ?array
    {
        $row = $this->db->fetch('SELECT * FROM evaluation_survey_screen_captures WHERE id=? AND attempt_id=? LIMIT 1', [$captureId, $attemptId]);
        if (!$row || !is_file($this->absolutePath((string) $row['storage_key']))) return null;
        return ['path' => $this->absolutePath((string) $row['storage_key']), 'mime_type' => (string) $row['mime_type'], 'size' => (int) $row['file_size']];
    }
    public function purgeExpired(int $retentionDays): int
    {
        $retentionDays = max(1, min(3650, $retentionDays));
        $cutoff = date('Y-m-d H:i:s', time() - ($retentionDays * 86400));
        $rows = $this->db->fetchAll('SELECT id,attempt_id,storage_key FROM evaluation_survey_media_evidence WHERE updated_at < ?', [$cutoff]);
        $ids = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['storage_key'] ?? ''));
            if ($key !== '') {
                $path = $this->absolutePath($key);
                if (is_file($path)) unlink($path);
            }
            $captures = $this->db->fetchAll('SELECT storage_key FROM evaluation_survey_screen_captures WHERE evidence_id=?', [(int) $row['id']]);
            foreach ($captures as $capture) { $capturePath = $this->absolutePath((string) ($capture['storage_key'] ?? '')); if (is_file($capturePath)) unlink($capturePath); }
            $this->db->execute('DELETE FROM evaluation_survey_screen_captures WHERE evidence_id=?', [(int) $row['id']]);
            $ids[] = (int) $row['id'];
        }
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $this->db->execute('DELETE FROM evaluation_survey_media_evidence WHERE id IN (' . $placeholders . ')', $ids);
        }
        return count($rows);
    }
    public function purgeExpiredIfDue(int $retentionDays, int $intervalSeconds = 900): int
    {
        $lockPath = TMP_PATH . '/evaluation-media-purge.lock';
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) { if (is_resource($handle)) fclose($handle); return 0; }
        rewind($handle); $lastRun = (int) trim((string) stream_get_contents($handle));
        if ($lastRun > 0 && (time() - $lastRun) < max(60, $intervalSeconds)) { flock($handle, LOCK_UN); fclose($handle); return 0; }
        ftruncate($handle, 0); rewind($handle); fwrite($handle, (string) time()); fflush($handle);
        $deleted = $this->purgeExpired($retentionDays);
        flock($handle, LOCK_UN); fclose($handle);
        return $deleted;
    }
    private function upsertEvidence(array $attempt, string $status, ?string $code, ?string $reason): array
    {
        $existing = $this->evidenceForAttempt((int) $attempt['id']);
        if ($existing) {
            $this->db->execute('UPDATE evaluation_survey_media_evidence SET status=?,failure_code=?,failure_reason=?,updated_at=NOW() WHERE id=?', [$status, $code, $reason, (int) $existing['id']]);
        } else {
            $this->db->execute('INSERT INTO evaluation_survey_media_evidence (attempt_id,segment_number,form_id,user_id,status,failure_code,failure_reason) VALUES (?,?,?,?,?,?,?)', [(int) $attempt['id'], 1, (int) $attempt['form_id'], (int) $attempt['user_id'], $status, $code, $reason]);
        }
        return $this->evidenceForAttempt((int) $attempt['id']) ?: [];
    }
    private function createEvidenceSegment(array $attempt, int $segmentNumber): array { $this->db->execute('INSERT INTO evaluation_survey_media_evidence (attempt_id,segment_number,form_id,user_id,status) VALUES (?,?,?,?,"recording")', [(int) $attempt['id'], max(1, $segmentNumber), (int) $attempt['form_id'], (int) $attempt['user_id']]); return $this->evidenceForAttempt((int) $attempt['id']) ?: []; }
    private function reopenedAfterEvidence(int $attemptId, array $evidence): bool { if (!in_array((string) ($evidence['status'] ?? ''), ['saved', 'partial', 'failed'], true)) return false; $event = $this->db->fetch('SELECT created_at FROM evaluation_survey_activity_events WHERE attempt_id=? AND event_type="evaluation_reopened_by_admin" ORDER BY id DESC LIMIT 1', [$attemptId]); return $event && strtotime((string) ($event['created_at'] ?? '')) >= strtotime((string) ($evidence['updated_at'] ?? '')); }
    private function markFailure(int $id, string $code, string $reason): void { $this->db->execute('UPDATE evaluation_survey_media_evidence SET status="failed",failure_code=?,failure_reason=?,updated_at=NOW() WHERE id=?', [$code, $reason, $id]); }
    private function mediaContext(int $attemptId, int $userId, int $evidenceId): ?array { return $this->db->fetch('SELECT a.*, e.id AS evidence_id, e.status AS evidence_status, e.recording_started_at, e.uploaded_bytes FROM evaluation_survey_attempts a JOIN evaluation_survey_forms f ON f.id=a.form_id JOIN evaluation_survey_media_evidence e ON e.attempt_id=a.id WHERE a.id=? AND a.user_id=? AND e.id=? LIMIT 1', [$attemptId, $userId, $evidenceId]); }
    private function operationalSettingsForEvidence(int $evidenceId): array { $cached = $_SESSION['evaluation_survey_media_operational'][(string) $evidenceId] ?? null; return is_array($cached) ? $cached : (new PlatformSettingsModel())->operationalSettings(); }
    private function isAudioVisualMode(string $mode): bool { return $mode === 'supervised_audio_visual'; }
    private function allowedMime(string $mime): bool { $mime = strtolower($mime); return strpos($mime, 'video/webm') === 0 || strpos($mime, 'video/mp4') === 0; }
    private function looksLikeMedia(string $path, string $mime): bool { $handle = fopen($path, 'rb'); if (!$handle) return false; $header = fread($handle, 16); fclose($handle); return $mime && ((strpos($mime, 'webm') !== false && substr($header, 0, 4) === "\x1A\x45\xDF\xA3") || (strpos($mime, 'mp4') !== false && strpos($header, 'ftyp') !== false)); }
    private function absolutePath(string $relative): string { return $this->storageRoot . '/' . ltrim(str_replace('..', '', $relative), '/'); }
    private function deleteStorageFiles(array $keys): void
    {
        $root = realpath($this->storageRoot);
        if ($root === false) return;
        foreach (array_unique($keys) as $key) {
            $key = trim((string) $key);
            if ($key === '') continue;
            $path = $this->absolutePath($key);
            $parent = realpath(dirname($path));
            if ($parent === false || ($parent !== $root && strpos($parent, $root . DIRECTORY_SEPARATOR) !== 0)) continue;
            if (is_file($path)) @unlink($path);
        }
    }
    private function ensureDirectory(string $path): void { if (!is_dir($path)) mkdir($path, 0700, true); }
}
