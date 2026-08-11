<?php
declare(strict_types=1);

final class TestMediaEvidenceModel
{
    private Database $db;
    private string $storageRoot;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('tests');
        $this->storageRoot = BASE_PATH . '/storage/test-evidence';
    }

    public function session(int $sessionId, int $userId): ?array
    {
        return $this->db->fetch('
            SELECT ts.id, ts.instrument_id, ts.user_id, ts.status, i.control_mode AS control_mode,
                   ts.audio_visual_policy, ts.audio_visual_upload_failure_policy, i.name AS instrument_name
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            WHERE ts.id = ? AND ts.user_id = ?
            LIMIT 1
        ', [$sessionId, $userId]);
    }

    public function evidenceForSession(int $sessionId): ?array
    {
        return $this->db->fetch('SELECT * FROM test_media_evidence WHERE session_id = ? LIMIT 1', [$sessionId]);
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
        $evidence = $this->upsertEvidence($session, 'recording', null, null);
        $this->db->execute('UPDATE test_media_evidence SET recording_started_at = COALESCE(recording_started_at, NOW()), updated_at = NOW() WHERE id = ?', [(int) $evidence['id']]);

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

        $relative = 'sessions/' . $sessionId . '/chunks/chunk-' . $chunkNumber . '.bin';
        $absolute = $this->absolutePath($relative);
        $this->ensureDirectory(dirname($absolute));
        if (!move_uploaded_file((string) $file['tmp_name'], $absolute)) {
            return ['ok' => false, 'reason' => 'chunk_storage_failed'];
        }

        $size = (int) filesize($absolute);
        $operational = (new PlatformSettingsModel())->operationalSettings();
        $chunkLimit = (int) $operational['test_evidence_chunk_size_mb'] * 1024 * 1024;
        $maxEvidenceSize = (int) $operational['test_evidence_max_size_mb'] * 1024 * 1024;
        $currentSizeRow = $this->db->fetch('SELECT COALESCE(SUM(size_bytes), 0) AS total FROM test_media_chunks WHERE evidence_id = ?', [$evidenceId]);
        $currentSize = (int) ($currentSizeRow['total'] ?? 0);
        if ($size > $chunkLimit) {
            @unlink($absolute);
            return ['ok' => false, 'reason' => 'chunk_too_large'];
        }
        if ($currentSize + $size > $maxEvidenceSize) {
            @unlink($absolute);
            return ['ok' => false, 'reason' => 'evidence_too_large'];
        }
        $sha256 = hash_file('sha256', $absolute) ?: null;
        $this->db->execute('
            INSERT INTO test_media_chunks (evidence_id, chunk_number, storage_key, size_bytes, sha256)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE storage_key = VALUES(storage_key), size_bytes = VALUES(size_bytes), sha256 = VALUES(sha256), uploaded_at = CURRENT_TIMESTAMP
        ', [$evidenceId, $chunkNumber, $relative, $size, $sha256]);
        $this->db->execute('UPDATE test_media_evidence SET status = "uploading", mime_type = ?, upload_started_at = COALESCE(upload_started_at, NOW()), updated_at = NOW() WHERE id = ?', [$mimeType, $evidenceId]);

        return ['ok' => true, 'chunk_number' => $chunkNumber, 'size_bytes' => $size, 'sha256' => $sha256];
    }

    public function finalize(int $sessionId, int $userId, int $evidenceId, ?int $durationSeconds = null): array
    {
        $session = $this->session($sessionId, $userId);
        $evidence = $this->evidenceForSession($sessionId);
        if (!$session || !$evidence || (int) $evidence['id'] !== $evidenceId) {
            return ['ok' => false, 'reason' => 'evidence_not_found'];
        }

        $chunks = $this->db->fetchAll('SELECT * FROM test_media_chunks WHERE evidence_id = ? ORDER BY chunk_number ASC', [$evidenceId]);
        if (!$chunks) {
            $this->markFailure($evidenceId, 'no_chunks', 'No se recibieron fragmentos de video.');
            return ['ok' => false, 'reason' => 'no_chunks'];
        }

        $mimeType = strtolower((string) ($evidence['mime_type'] ?? 'video/webm'));
        $extension = strpos($mimeType, 'video/mp4') === 0 ? 'mp4' : 'webm';
        $rawRelative = 'sessions/' . $sessionId . '/evidence-' . $evidenceId . '.raw';
        $relative = 'sessions/' . $sessionId . '/evidence-' . $evidenceId . '.' . $extension;
        $rawAbsolute = $this->absolutePath($rawRelative);
        $absolute = $this->absolutePath($relative);
        $this->ensureDirectory(dirname($rawAbsolute));
        $output = fopen($rawAbsolute, 'wb');
        if ($output === false) {
            $this->markFailure($evidenceId, 'evidence_storage_failed', 'No se pudo crear el archivo de evidencia.');
            return ['ok' => false, 'reason' => 'evidence_storage_failed'];
        }

        $totalSize = 0;
        $expectedChunk = 0;
        foreach ($chunks as $chunk) {
            if ((int) $chunk['chunk_number'] !== $expectedChunk) {
                fclose($output);
                $this->markFailure($evidenceId, 'missing_chunk', 'Falta un fragmento de la evidencia.');
                return ['ok' => false, 'reason' => 'missing_chunk'];
            }
            $chunkPath = $this->absolutePath((string) $chunk['storage_key']);
            $input = fopen($chunkPath, 'rb');
            if ($input === false) {
                fclose($output);
                $this->markFailure($evidenceId, 'missing_chunk', 'Falta un fragmento de la evidencia.');
                return ['ok' => false, 'reason' => 'missing_chunk'];
            }
            stream_copy_to_stream($input, $output);
            fclose($input);
            $totalSize += (int) $chunk['size_bytes'];
            $expectedChunk++;
        }
        fclose($output);

        if ($totalSize <= 0 || !is_file($rawAbsolute)) {
            $this->markFailure($evidenceId, 'empty_evidence', 'La evidencia recibida no contiene datos válidos.');
            return ['ok' => false, 'reason' => 'empty_evidence'];
        }

        $transcode = $this->transcodeAndValidate($rawAbsolute, $absolute, $extension);
        if (!$transcode['ok']) {
            $this->markFailure($evidenceId, (string) $transcode['reason'], (string) $transcode['message']);
            return ['ok' => false, 'reason' => (string) $transcode['reason']];
        }

        $fileSize = (int) ($transcode['size'] ?? 0);
        $sha256 = hash_file('sha256', $absolute) ?: null;
        $validatedDuration = isset($transcode['duration']) ? (int) round((float) $transcode['duration']) : null;
        $storedDuration = $validatedDuration !== null && $validatedDuration > 0
            ? $validatedDuration
            : ($durationSeconds !== null ? max(0, $durationSeconds) : null);
        $this->db->execute('
            UPDATE test_media_evidence
            SET status = "saved", storage_key = ?, file_size = ?, sha256 = ?, duration_seconds = ?,
                recording_finished_at = COALESCE(recording_finished_at, NOW()), upload_finished_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ', [$relative, $fileSize, $sha256, $storedDuration, $evidenceId]);

        // El archivo crudo sólo sirve para el ensamblado; los fragmentos se conservan
        // para permitir auditoría y reintentos controlados si el producto los requiere.
        if (is_file($rawAbsolute)) {
            unlink($rawAbsolute);
        }

        return ['ok' => true, 'status' => 'saved', 'storage_key' => $relative, 'file_size' => $fileSize, 'sha256' => $sha256, 'duration_seconds' => $storedDuration];
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
        if (!$evidence || (string) ($evidence['status'] ?? '') !== 'saved' || trim((string) ($evidence['storage_key'] ?? '')) === '') {
            return null;
        }
        $path = $this->absolutePath((string) $evidence['storage_key']);
        if (!is_file($path)) {
            return null;
        }
        return ['path' => $path, 'mime_type' => (string) ($evidence['mime_type'] ?? 'video/webm'), 'size' => filesize($path) ?: 0];
    }

    public function auditAdminAccess(int $sessionId, int $actorUserId, string $action): bool
    {
        if (!in_array($action, ['result_viewed', 'video_viewed', 'video_downloaded'], true) || $sessionId <= 0 || $actorUserId <= 0) {
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

    private function upsertEvidence(array $session, string $status, ?string $failureCode, ?string $failureReason): array
    {
        $this->db->execute('
            INSERT INTO test_media_evidence (session_id, instrument_id, user_id, status, failure_code, failure_reason)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), failure_code = VALUES(failure_code), failure_reason = VALUES(failure_reason), updated_at = NOW()
        ', [(int) $session['id'], (int) $session['instrument_id'], (int) $session['user_id'], $status, $failureCode, $failureReason]);
        return $this->evidenceForSession((int) $session['id']) ?: [];
    }

    private function markFailure(int $evidenceId, string $code, string $reason): void
    {
        $this->db->execute('UPDATE test_media_evidence SET status = "failed", failure_code = ?, failure_reason = ?, updated_at = NOW() WHERE id = ?', [$code, $reason, $evidenceId]);
    }

    private function transcodeAndValidate(string $input, string $output, string $extension): array
    {
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
        $ffprobe = trim((string) shell_exec('command -v ffprobe 2>/dev/null'));
        if ($ffmpeg === '' || $ffprobe === '') {
            return ['ok' => false, 'reason' => 'transcoder_unavailable', 'message' => 'No está disponible el validador audiovisual del servidor.'];
        }

        $codecArgs = $extension === 'mp4'
            ? '-c copy -movflags +faststart'
            : '-c copy';
        $command = escapeshellarg($ffmpeg) . ' -nostdin -hide_banner -loglevel error -y -i '
            . escapeshellarg($input) . ' ' . $codecArgs . ' ' . escapeshellarg($output) . ' 2>&1';
        $result = $this->runProcess($command);
        if ($result['exit_code'] !== 0 || !is_file($output) || (int) filesize($output) <= 0) {
            return ['ok' => false, 'reason' => 'transcode_failed', 'message' => 'No se pudo ensamblar o validar el video recibido.'];
        }

        $probeCommand = escapeshellarg($ffprobe) . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
            . escapeshellarg($output) . ' 2>&1';
        $probe = $this->runProcess($probeCommand);
        $duration = (float) trim($probe['stdout']);
        if ($probe['exit_code'] !== 0 || !is_finite($duration) || $duration <= 0) {
            return ['ok' => false, 'reason' => 'media_validation_failed', 'message' => 'El video generado no superó la validación de reproducción.'];
        }

        return ['ok' => true, 'size' => (int) filesize($output), 'duration' => $duration];
    }

    private function runProcess(string $command): array
    {
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return ['exit_code' => 1, 'stdout' => '', 'stderr' => ''];
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        return ['exit_code' => $exitCode, 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
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

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0700, true);
        }
    }
}
