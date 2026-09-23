<?php
declare(strict_types=1);

final class TestMediaEvidenceModel
{
    private Database $db;
    private string $storageRoot;
    private string $coreSchema;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('tests');
        $this->storageRoot = BASE_PATH . '/storage/test-evidence';
        $this->coreSchema = database_identifier('core');
    }

    public function session(int $sessionId, int $userId): ?array
    {
        return $this->db->fetch('
            SELECT ts.id, ts.instrument_id, ts.user_id, ts.status,
                   COALESCE(ts.control_mode, i.control_mode) AS control_mode,
                   i.control_mode AS instrument_control_mode,
                   ts.audio_visual_policy, ts.audio_visual_upload_failure_policy, i.name AS instrument_name
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN test_processes p ON p.id = ts.process_id
            JOIN ' . $this->coreSchema . '.users target_user ON target_user.id = ts.user_id
            WHERE ts.id = ? AND ts.user_id = ?
              AND p.company_id = target_user.company_id
            LIMIT 1
        ', [$sessionId, $userId]);
    }

    public function evidenceForSession(int $sessionId): ?array
    {
        return $this->db->fetch('SELECT * FROM test_media_evidence WHERE session_id = ? ORDER BY segment_number DESC, id DESC LIMIT 1', [$sessionId]);
    }

    public function evidencesForSession(int $sessionId): array
    {
        return $this->db->fetchAll('SELECT * FROM test_media_evidence WHERE session_id = ? ORDER BY segment_number ASC, id ASC', [$sessionId]);
    }

    /**
     * Removes audiovisual files and their database rows for a test session.
     * The session itself is intentionally kept so this can also be used by
     * "Eliminar respuestas" before resetting the session.
     */
    public function deleteForSession(int $sessionId): void
    {
        if ($sessionId <= 0) {
            return;
        }

        $evidenceRows = $this->db->fetchAll(
            'SELECT id, storage_key FROM test_media_evidence WHERE session_id = ?',
            [$sessionId]
        );
        $captureRows = $this->db->fetchAll(
            'SELECT storage_key FROM test_screen_captures WHERE session_id = ?',
            [$sessionId]
        );
        $keys = [];
        foreach ($evidenceRows as $row) {
            $keys[] = (string) ($row['storage_key'] ?? '');
            $chunks = $this->db->fetchAll(
                'SELECT storage_key FROM test_media_chunks WHERE evidence_id = ?',
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
        $this->db->execute('DELETE FROM test_screen_captures WHERE session_id = ?', [$sessionId]);
        $this->db->execute('DELETE FROM test_media_evidence WHERE session_id = ?', [$sessionId]);
    }

    public function risksForSession(int $sessionId): array
    {
        return $this->db->fetchAll('
            SELECT *
            FROM test_audio_visual_risk_events
            WHERE session_id = ?
            ORDER BY created_at ASC, id ASC
        ', [$sessionId]);
    }

    public function screenCapturesForSession(int $sessionId): array
    {
        return $this->db->fetchAll('SELECT * FROM test_screen_captures WHERE session_id=? ORDER BY evidence_id ASC, capture_number ASC', [$sessionId]);
    }

    public function uploadScreenCapture(int $sessionId, int $userId, int $evidenceId, string $source, int $captureNumber, array $file, ?int $itemId, ?int $blockNumber, string $eventType): array
    {
        $session = $this->session($sessionId, $userId); $evidence = $this->evidenceForSession($sessionId);
        $recordingStarted = !empty($evidence['recording_started_at']) || (string) ($evidence['status'] ?? '') === 'recording';
        if (!$session || !$evidence || (int) $evidence['id'] !== $evidenceId || !$this->isAudioVisualMode((string) ($session['control_mode'] ?? '')) || !$recordingStarted || $captureNumber < 0 || !in_array($source, ['screen', 'canvas'], true)) return ['ok' => false, 'reason' => 'recording_not_started'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) return ['ok' => false, 'reason' => 'invalid_screen_capture'];
        $mime = strtolower(substr(trim((string) ($file['type'] ?? '')), 0, 120));
        if (!in_array($mime, ['image/jpeg', 'image/webp'], true)) return ['ok' => false, 'reason' => 'unsupported_screen_capture_type'];
        $size = (int) filesize((string) $file['tmp_name']); $imageInfo = @getimagesize((string) $file['tmp_name']);
        if ($size <= 0 || $size > (1 * 1024 * 1024) || !is_array($imageInfo) || (string) ($imageInfo['mime'] ?? '') !== $mime) return ['ok' => false, 'reason' => 'invalid_screen_capture'];
        $relative = 'sessions/' . $sessionId . '/evidence-' . $evidenceId . '/screens/capture-' . $captureNumber . '.' . ($mime === 'image/webp' ? 'webp' : 'jpg'); $absolute = $this->absolutePath($relative); $this->ensureDirectory(dirname($absolute));
        if (!move_uploaded_file((string) $file['tmp_name'], $absolute)) return ['ok' => false, 'reason' => 'screen_capture_storage_failed'];
        $sha256 = hash_file('sha256', $absolute) ?: '';
        try {
            $this->db->execute('INSERT INTO test_screen_captures (session_id,evidence_id,capture_source,capture_number,item_id,block_number,event_type,mime_type,storage_key,file_size,sha256,captured_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE evidence_id=VALUES(evidence_id),capture_source=VALUES(capture_source),item_id=VALUES(item_id),block_number=VALUES(block_number),event_type=VALUES(event_type),mime_type=VALUES(mime_type),storage_key=VALUES(storage_key),file_size=VALUES(file_size),sha256=VALUES(sha256),captured_at=NOW()', [$sessionId, $evidenceId ?: null, $source, $captureNumber, $itemId ?: null, $blockNumber ?: null, mb_substr(preg_replace('/[^a-z0-9_]/', '', strtolower($eventType)) ?: 'periodic', 0, 80), $mime, $relative, $size, $sha256]);
        } catch (Throwable $error) { if (is_file($absolute)) unlink($absolute); return ['ok' => false, 'reason' => 'screen_capture_record_failed']; }
        return ['ok' => true, 'capture_number' => $captureNumber, 'sha256' => $sha256, 'source' => $source];
    }

    public function screenCaptureFile(int $sessionId, int $captureId): ?array
    {
        $row = $this->db->fetch('SELECT * FROM test_screen_captures WHERE id=? AND session_id=? LIMIT 1', [$captureId, $sessionId]);
        if (!$row || !is_file($this->absolutePath((string) $row['storage_key']))) return null;
        return ['path' => $this->absolutePath((string) $row['storage_key']), 'mime_type' => (string) $row['mime_type'], 'size' => (int) $row['file_size']];
    }

    public function purgeExpiredIfDue(int $retentionDays, int $intervalSeconds = 900): int
    {
        $lockPath = TMP_PATH . '/test-media-purge.lock';
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) { if (is_resource($handle)) fclose($handle); return 0; }
        rewind($handle); $lastRun = (int) trim((string) stream_get_contents($handle));
        if ($lastRun > 0 && (time() - $lastRun) < max(60, $intervalSeconds)) { flock($handle, LOCK_UN); fclose($handle); return 0; }
        ftruncate($handle, 0); rewind($handle); fwrite($handle, (string) time()); fflush($handle);
        $retentionDays = max(1, min(3650, $retentionDays));
        $cutoff = date('Y-m-d H:i:s', time() - ($retentionDays * 86400));
        $rows = $this->db->fetchAll('SELECT id,session_id,storage_key FROM test_media_evidence WHERE updated_at < ?', [$cutoff]);
        $ids = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['storage_key'] ?? ''));
            if ($key !== '') { $path = $this->absolutePath($key); if (is_file($path)) unlink($path); }
            $captures = $this->db->fetchAll('SELECT storage_key FROM test_screen_captures WHERE evidence_id=?', [(int) $row['id']]);
            foreach ($captures as $capture) { $capturePath = $this->absolutePath((string) ($capture['storage_key'] ?? '')); if (is_file($capturePath)) unlink($capturePath); }
            $this->db->execute('DELETE FROM test_screen_captures WHERE evidence_id=?', [(int) $row['id']]);
            $ids[] = (int) $row['id'];
        }
        if ($ids) { $this->db->execute('DELETE FROM test_media_evidence WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids); }
        flock($handle, LOCK_UN); fclose($handle);
        return count($rows);
    }

    public function setPolicy(int $sessionId, int $userId, string $policy, bool $consented): array
    {
        $session = $this->session($sessionId, $userId);
        if (!$session || !$this->isAudioVisualMode((string) ($session['control_mode'] ?? ''))) {
            return ['ok' => false, 'reason' => 'mode_not_enabled'];
        }
        if (!in_array($policy, ['pause', 'continue', 'block'], true)) {
            return ['ok' => false, 'reason' => 'invalid_policy'];
        }
        if (!$consented) {
            $this->upsertEvidence($session, 'not_consented', 'consent_required', 'El usuario no acepto la captura audiovisual.');
            return ['ok' => false, 'reason' => 'consent_required'];
        }

        $this->db->execute('UPDATE test_sessions SET audio_visual_policy = ? WHERE id = ? AND user_id = ?', [$policy, $sessionId, $userId]);
        $existing = $this->evidenceForSession($sessionId);
        $evidence = ($existing && $this->reopenedAfterEvidence($sessionId, $existing))
            ? $this->createEvidenceSegment($session, (int) ($existing['segment_number'] ?? 1) + 1)
            : $this->upsertEvidence($session, 'recording', null, null);

        return ['ok' => true, 'evidence_id' => (int) $evidence['id'], 'policy' => $policy];
    }

    public function uploadChunk(int $sessionId, int $userId, int $evidenceId, int $chunkNumber, array $file, ?string $mimeType): array
    {
        $session = $this->session($sessionId, $userId);
        $evidence = $this->evidenceForSession($sessionId);
        if (!$session || !$evidence || (int) $evidence['id'] !== $evidenceId || (int) $evidence['session_id'] !== $sessionId) {
            return ['ok' => false, 'reason' => 'evidence_not_found'];
        }
        if (!$this->isAudioVisualMode((string) ($session['control_mode'] ?? '')) || $chunkNumber < 0) {
            return ['ok' => false, 'reason' => 'invalid_media_session'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['ok' => false, 'reason' => 'invalid_chunk'];
        }

        $mimeType = substr(trim((string) ($mimeType ?: ($file['type'] ?? 'application/octet-stream'))), 0, 120);
        if (!$this->allowedMime($mimeType)) {
            return ['ok' => false, 'reason' => 'unsupported_media_type'];
        }

        $relative = 'sessions/' . $sessionId . '/evidence-' . $evidenceId . '/chunks/chunk-' . $chunkNumber . '.bin';
        $absolute = $this->absolutePath($relative);
        $this->ensureDirectory(dirname($absolute));
        if (!move_uploaded_file((string) $file['tmp_name'], $absolute)) {
            return ['ok' => false, 'reason' => 'chunk_storage_failed'];
        }

        $operational = (new PlatformSettingsModel())->operationalSettings();
        $chunkLimit = (int) $operational['test_evidence_chunk_size_mb'] * 1024 * 1024;
        $maxEvidenceSize = (int) $operational['test_evidence_max_size_mb'] * 1024 * 1024;
        $size = (int) filesize($absolute);
        if ($size > $chunkLimit) {
            @unlink($absolute);
            return ['ok' => false, 'reason' => 'chunk_too_large'];
        }
        $sha256 = hash_file('sha256', $absolute) ?: null;
        $accepted = $this->db->transaction(function (Database $db) use ($evidenceId, $chunkNumber, $relative, $size, $sha256, $mimeType, $maxEvidenceSize): bool {
            $evidence = $db->fetch('SELECT uploaded_bytes FROM test_media_evidence WHERE id = ? FOR UPDATE', [$evidenceId]);
            if (!$evidence) {
                return false;
            }
            $previous = $db->fetch('SELECT size_bytes FROM test_media_chunks WHERE evidence_id = ? AND chunk_number = ? FOR UPDATE', [$evidenceId, $chunkNumber]);
            $previousSize = $previous ? (int) $previous['size_bytes'] : 0;
            if ((int) $evidence['uploaded_bytes'] - $previousSize + $size > $maxEvidenceSize) {
                return false;
            }
            $delta = $size - $previousSize;
            $db->execute('
                INSERT INTO test_media_chunks (evidence_id, chunk_number, storage_key, size_bytes, sha256)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE storage_key = VALUES(storage_key), size_bytes = VALUES(size_bytes), sha256 = VALUES(sha256), uploaded_at = CURRENT_TIMESTAMP
            ', [$evidenceId, $chunkNumber, $relative, $size, $sha256]);
            $db->execute('UPDATE test_media_evidence SET uploaded_bytes = GREATEST(0, uploaded_bytes + ?), status = "uploading", mime_type = ?, recording_started_at = COALESCE(recording_started_at, NOW()), upload_started_at = COALESCE(upload_started_at, NOW()), updated_at = NOW() WHERE id = ?', [$delta, $mimeType, $evidenceId]);
            return true;
        });

        if (!$accepted) {
            $stored = $this->db->fetch('SELECT storage_key FROM test_media_chunks WHERE evidence_id=? AND chunk_number=? LIMIT 1', [$evidenceId, $chunkNumber]);
            if (!$stored && is_file($absolute)) @unlink($absolute);
            return ['ok' => false, 'reason' => 'evidence_too_large'];
        }

        return ['ok' => true, 'chunk_number' => $chunkNumber, 'size_bytes' => $size, 'sha256' => $sha256];
    }

    public function finalize(int $sessionId, int $userId, int $evidenceId, ?int $durationSeconds = null): array
    {
        $session = $this->session($sessionId, $userId);
        $evidence = $this->evidenceForSession($sessionId);
        if (!$session || !$evidence || (int) $evidence['id'] !== $evidenceId) {
            return ['ok' => false, 'reason' => 'evidence_not_found'];
        }
        if ((string) ($evidence['status'] ?? '') === 'failed') {
            return ['ok' => false, 'reason' => 'evidence_failed'];
        }

        $chunks = $this->db->fetchAll('SELECT chunk_number, storage_key FROM test_media_chunks WHERE evidence_id = ? ORDER BY chunk_number ASC', [$evidenceId]);
        if (!$chunks) {
            $this->markFailure($evidenceId, 'no_chunks', 'No se recibieron fragmentos de video.');
            return ['ok' => false, 'reason' => 'no_chunks'];
        }

        $expectedChunk = 0;
        foreach ($chunks as $chunk) {
            if ((int) $chunk['chunk_number'] !== $expectedChunk || !is_file($this->absolutePath((string) $chunk['storage_key']))) {
                $this->markFailure($evidenceId, 'missing_chunk', 'Falta un fragmento de la evidencia.');
                return ['ok' => false, 'reason' => 'missing_chunk'];
            }
            $expectedChunk++;
        }
        $finalCapture = $this->db->fetch(
            'SELECT storage_key FROM test_screen_captures WHERE session_id=? AND evidence_id=? AND event_type=? ORDER BY capture_number DESC LIMIT 1',
            [$sessionId, $evidenceId, 'assessment_finished']
        );
        if (!$finalCapture || !is_file($this->absolutePath((string) ($finalCapture['storage_key'] ?? '')))) {
            return ['ok' => false, 'reason' => 'final_screen_capture_missing'];
        }
        $mimeType = strtolower((string) ($evidence['mime_type'] ?? 'video/webm'));
        $extension = strpos($mimeType, 'video/mp4') === 0 ? 'mp4' : 'webm';
        $relative = 'sessions/' . $sessionId . '/evidence-' . $evidenceId . '.' . $extension;
        $this->db->execute('UPDATE test_media_evidence SET status="processing", storage_key=?, duration_seconds=?, recording_finished_at=COALESCE(recording_finished_at,NOW()), upload_finished_at=NOW(), processing_status="queued", processing_error=NULL, processed_at=NULL, updated_at=NOW() WHERE id=? AND status IN ("uploading","processing")', [$relative, $durationSeconds !== null ? max(0, $durationSeconds) : null, $evidenceId]);
        (new TestMediaProcessingService($this->db))->enqueue($evidenceId);
        return ['ok' => true, 'status' => 'processing', 'storage_key' => $relative, 'chunks_total' => count($chunks)];
    }

    public function markChunkUploadFailure(int $sessionId, int $userId, int $evidenceId, int $chunkNumber, string $reason): bool
    {
        $session = $this->session($sessionId, $userId);
        $evidence = $this->evidenceForSession($sessionId);
        if (!$session || !$evidence || (int) $evidence['id'] !== $evidenceId || $chunkNumber < 0) return false;
        $reason = mb_substr(trim($reason) !== '' ? trim($reason) : 'No se pudo enviar el fragmento audiovisual.', 0, 500);
        $this->db->execute('UPDATE test_media_evidence SET status="failed", processing_status="failed", failure_code="chunk_upload_failed", failure_reason=?, processing_error=?, updated_at=NOW() WHERE id=? AND status IN ("recording","uploading","processing")', [$reason, $reason, $evidenceId]);
        return true;
    }

    public function recordRisk(int $sessionId, int $userId, string $eventType, string $severity, ?float $confidence, array $metadata = [], ?int $evidenceId = null): bool
    {
        $session = $this->session($sessionId, $userId);
        if (!$session || !$this->isAudioVisualMode((string) ($session['control_mode'] ?? ''))) {
            return false;
        }
        $eventType = preg_replace('/[^a-z0-9_]/', '', strtolower($eventType));
        if ($eventType === '' || strlen($eventType) > 80 || !in_array($severity, ['info', 'attention', 'risk'], true)) {
            return false;
        }
        if ($eventType === 'multiple_voice_possible') {
            $severity = 'attention';
        }
        $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if ($metadataJson !== null && strlen($metadataJson) > 5000) {
            $metadataJson = substr($metadataJson, 0, 5000);
        }
        if ($evidenceId !== null) {
            $evidence = $this->evidenceForSession($sessionId);
            if (!$evidence || (int) $evidence['id'] !== $evidenceId) {
                $evidenceId = null;
            }
        }
        $this->db->execute('
            INSERT INTO test_audio_visual_risk_events (session_id, evidence_id, event_type, severity, confidence, started_at, metadata)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ', [$sessionId, $evidenceId, $eventType, $severity, $confidence !== null ? max(0, min(1, $confidence)) : null, $metadataJson]);

        return true;
    }

    public function evidenceFileForAdmin(int $sessionId): ?array
    {
        $evidence = $this->evidenceForSession($sessionId);
        return $this->evidenceFileForRow($evidence);
    }

    public function evidenceFileForEvidence(int $sessionId, int $evidenceId): ?array
    {
        return $this->evidenceFileForRow($this->db->fetch('SELECT * FROM test_media_evidence WHERE session_id=? AND id=? LIMIT 1', [$sessionId, $evidenceId]));
    }

    private function evidenceFileForRow(?array $evidence): ?array
    {
        if (!$evidence || !in_array((string) ($evidence['status'] ?? ''), ['saved', 'partial'], true) || trim((string) ($evidence['storage_key'] ?? '')) === '') {
            return null;
        }
        $path = $this->absolutePath((string) $evidence['storage_key']);
        if (!is_file($path)) {
            return null;
        }
        return ['path' => $path, 'mime_type' => (string) ($evidence['mime_type'] ?? 'video/webm'), 'size' => filesize($path) ?: 0];
    }

    public function assemblePartial(int $sessionId, int $actorUserId): array
    {
        $evidence = $this->evidenceForSession($sessionId);
        if (!$evidence || !in_array((string) ($evidence['status'] ?? ''), ['uploading', 'failed', 'partial'], true)) return ['ok' => false, 'reason' => 'partial_not_available'];
        $chunks = $this->db->fetchAll('SELECT chunk_number,storage_key FROM test_media_chunks WHERE evidence_id=? ORDER BY chunk_number ASC', [(int) $evidence['id']]);
        if (!$chunks) return ['ok' => false, 'reason' => 'no_chunks'];
        $mime = strtolower((string) ($evidence['mime_type'] ?? 'video/webm')); $extension = strpos($mime, 'video/mp4') === 0 ? 'mp4' : 'webm';
        $relative = 'sessions/' . $sessionId . '/evidence-' . (int) $evidence['id'] . '-partial.' . $extension; $absolute = $this->absolutePath($relative); $this->ensureDirectory(dirname($absolute));
        $output = fopen($absolute . '.part', 'wb'); if ($output === false) return ['ok' => false, 'reason' => 'partial_storage_failed'];
        $expected = 0; $joined = 0;
        try { foreach ($chunks as $chunk) { if ((int) $chunk['chunk_number'] !== $expected) break; $input = fopen($this->absolutePath((string) $chunk['storage_key']), 'rb'); if ($input === false) break; $joined += (int) stream_copy_to_stream($input, $output); fclose($input); $expected++; } } finally { fclose($output); }
        if ($expected === 0 || $joined <= 0 || !rename($absolute . '.part', $absolute)) { @unlink($absolute . '.part'); return ['ok' => false, 'reason' => 'partial_storage_failed']; }
        $this->db->execute('UPDATE test_media_evidence SET status="partial",storage_key=?,file_size=?,processing_status="completed",processing_error=NULL,processing_summary_json=?,processed_at=NOW(),updated_at=NOW() WHERE id=?', [$relative, $joined, json_encode(['processor' => 'manual_partial_assembly_v1', 'chunks_joined' => $expected, 'partial' => true], JSON_UNESCAPED_UNICODE), (int) $evidence['id']]);
        $this->auditAdminAccess($sessionId, $actorUserId, 'video_viewed');
        return ['ok' => true, 'chunks_joined' => $expected, 'file_size' => $joined];
    }

    public function auditAdminAccess(int $sessionId, int $actorUserId, string $action): bool
    {
        if (!in_array($action, ['result_viewed', 'video_viewed', 'video_downloaded', 'screen_capture_viewed'], true) || $sessionId <= 0 || $actorUserId <= 0) {
            return false;
        }

        $evidence = $this->evidenceForSession($sessionId);
        $this->db->execute('
            INSERT INTO test_media_access_audit (session_id, evidence_id, actor_user_id, action, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ', [
            $sessionId,
            $evidence ? (int) $evidence['id'] : null,
            $actorUserId,
            $action,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        ]);

        return true;
    }

    public function auditScreenCaptureAccess(int $sessionId, int $captureId, int $actorUserId): bool
    {
        if ($sessionId <= 0 || $captureId <= 0 || $actorUserId <= 0) return false;
        $capture = $this->db->fetch('SELECT evidence_id FROM test_screen_captures WHERE id=? AND session_id=? LIMIT 1', [$captureId, $sessionId]);
        if (!$capture) return false;
        return $this->auditAdminAccess($sessionId, $actorUserId, 'screen_capture_viewed');
    }

    private function upsertEvidence(array $session, string $status, ?string $failureCode, ?string $failureReason): array
    {
        $this->db->execute('
            INSERT INTO test_media_evidence (session_id, instrument_id, user_id, status, failure_code, failure_reason)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), failure_code = VALUES(failure_code), failure_reason = VALUES(failure_reason), updated_at = NOW()
        ', [(int) $session['id'], (int) $session['instrument_id'], (int) $session['user_id'], $status, $failureCode, $failureReason]);
        return $this->evidenceForSession((int) $session['id']) ?: [];
    }

    private function createEvidenceSegment(array $session, int $segmentNumber): array
    {
        $this->db->execute('INSERT INTO test_media_evidence (session_id,segment_number,instrument_id,user_id,status) VALUES (?,?,?,?,"recording")', [(int) $session['id'], max(1, $segmentNumber), (int) $session['instrument_id'], (int) $session['user_id']]);
        return $this->evidenceForSession((int) $session['id']) ?: [];
    }

    private function reopenedAfterEvidence(int $sessionId, array $evidence): bool
    {
        if (!in_array((string) ($evidence['status'] ?? ''), ['saved', 'partial', 'failed'], true)) return false;
        $event = $this->db->fetch('SELECT created_at FROM test_activity_events WHERE session_id=? AND event_type="evaluation_reopened_by_admin" ORDER BY id DESC LIMIT 1', [$sessionId]);
        return $event && strtotime((string) ($event['created_at'] ?? '')) >= strtotime((string) ($evidence['updated_at'] ?? ''));
    }

    private function markFailure(int $evidenceId, string $code, string $reason): void
    {
        $this->db->execute('UPDATE test_media_evidence SET status = "failed", failure_code = ?, failure_reason = ?, updated_at = NOW() WHERE id = ?', [$code, $reason, $evidenceId]);
    }

    private function transcodeAndValidate(string $input, string $output, string $extension, ?int $durationSeconds): array
    {
        if (!is_file($input) || (int) filesize($input) <= 0 || !$this->hasExpectedContainer($input, $extension) || !rename($input, $output)) {
            return ['ok' => false, 'reason' => 'media_validation_failed', 'message' => 'No se pudo guardar la evidencia audiovisual recibida.'];
        }
        $size = (int) filesize($output);
        if ($size <= 0) {
            return ['ok' => false, 'reason' => 'media_validation_failed', 'message' => 'La evidencia audiovisual quedó vacía.'];
        }
        return ['ok' => true, 'size' => $size, 'duration' => $durationSeconds];
    }

    private function hasExpectedContainer(string $path, string $extension): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 12);
        fclose($handle);
        if (!is_string($header) || strlen($header) < 8) {
            return false;
        }
        if ($extension === 'mp4') {
            return substr($header, 4, 4) === 'ftyp';
        }
        return substr($header, 0, 4) === "\x1A\x45\xDF\xA3";
    }

    private function isAudioVisualMode(string $mode): bool
    {
        return $mode === 'supervised_audio_visual';
    }

    private function allowedMime(string $mimeType): bool
    {
        return in_array(strtolower($mimeType), ['video/webm', 'video/webm;codecs=vp8,opus', 'video/webm;codecs=vp9,opus', 'video/mp4', 'video/mp4;codecs=avc1,mp4a.40.2'], true)
            || strpos(strtolower($mimeType), 'video/webm') === 0
            || strpos(strtolower($mimeType), 'video/mp4') === 0;
    }

    private function absolutePath(string $relative): string
    {
        $relative = ltrim(str_replace('..', '', $relative), '/');
        return $this->storageRoot . '/' . $relative;
    }

    private function deleteStorageFiles(array $keys): void
    {
        $root = realpath($this->storageRoot);
        if ($root === false) {
            return;
        }

        foreach (array_unique($keys) as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $path = $this->absolutePath($key);
            $parent = realpath(dirname($path));
            if ($parent === false || ($parent !== $root && strpos($parent, $root . DIRECTORY_SEPARATOR) !== 0)) {
                continue;
            }
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0700, true);
        }
    }
}
