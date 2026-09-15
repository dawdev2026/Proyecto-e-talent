<?php
declare(strict_types=1);

final class TestMediaProcessingService
{
    private Database $db;
    private string $storageRoot;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('tests');
        $this->storageRoot = BASE_PATH . '/storage/test-evidence';
    }

    public function enqueue(int $evidenceId): bool
    {
        return (bool) $this->db->transaction(function (Database $db) use ($evidenceId): bool {
            $evidence = $db->fetch('SELECT id, status FROM test_media_evidence WHERE id=? LIMIT 1', [$evidenceId]);
            if (!$evidence || !in_array((string) ($evidence['status'] ?? ''), ['processing', 'saved'], true)) return false;
            $db->execute('INSERT INTO test_media_processing_jobs (evidence_id,status,attempts,last_error) VALUES (?,"queued",0,NULL) ON DUPLICATE KEY UPDATE status="queued",attempts=0,locked_at=NULL,completed_at=NULL,last_error=NULL,updated_at=CURRENT_TIMESTAMP', [$evidenceId]);
            $db->execute('UPDATE test_media_evidence SET processing_status="queued",processing_error=NULL,processed_at=NULL,updated_at=NOW() WHERE id=?', [$evidenceId]);
            return true;
        });
    }

    public function processNext(int $maxAttempts = 3): bool
    {
        $job = $this->claimNext($maxAttempts);
        if (!$job) return false;
        try {
            $result = $this->consolidate($job);
            $summary = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->db->transaction(function (Database $db) use ($job, $result, $summary): void {
                $db->execute('UPDATE test_media_processing_jobs SET status="completed",completed_at=NOW(),last_error=NULL,updated_at=NOW() WHERE id=? AND status="processing"', [(int) $job['id']]);
                $db->execute('UPDATE test_media_evidence SET status="saved",file_size=?,sha256=?,processing_status="completed",processing_error=NULL,processing_summary_json=?,processed_at=NOW(),updated_at=NOW() WHERE id=?', [(int) $result['file_size'], $result['sha256'], $summary, (int) $job['evidence_id']]);
            });
        } catch (Throwable $exception) {
            $message = mb_substr($exception->getMessage(), 0, 500);
            $retry = (int) ($job['attempts'] ?? 0) < $maxAttempts;
            $this->db->transaction(function (Database $db) use ($job, $message, $retry): void {
                $db->execute('UPDATE test_media_processing_jobs SET status=?,locked_at=NULL,last_error=?,updated_at=NOW() WHERE id=? AND status="processing"', [$retry ? 'queued' : 'failed', $message, (int) $job['id']]);
                $db->execute('UPDATE test_media_evidence SET processing_status=?,processing_error=?,updated_at=NOW() WHERE id=?', [$retry ? 'queued' : 'failed', $message, (int) $job['evidence_id']]);
            });
        }
        return true;
    }

    private function claimNext(int $maxAttempts): ?array
    {
        return $this->db->transaction(function (Database $db) use ($maxAttempts): ?array {
            $db->execute('UPDATE test_media_processing_jobs SET status="queued",locked_at=NULL WHERE status="processing" AND locked_at < DATE_SUB(NOW(),INTERVAL 30 MINUTE)');
            $job = $db->fetch('SELECT j.*,e.storage_key,e.mime_type,e.status AS evidence_status FROM test_media_processing_jobs j JOIN test_media_evidence e ON e.id=j.evidence_id WHERE j.status="queued" AND j.attempts < ? ORDER BY j.id ASC LIMIT 1 FOR UPDATE', [$maxAttempts]);
            if (!$job) return null;
            $changed = $db->execute('UPDATE test_media_processing_jobs SET status="processing",attempts=attempts+1,locked_at=NOW(),started_at=COALESCE(started_at,NOW()),updated_at=NOW() WHERE id=? AND status="queued" AND attempts < ?', [(int) $job['id'], $maxAttempts]);
            return $changed > 0 ? $job : null;
        });
    }

    private function consolidate(array $job): array
    {
        if (!in_array((string) ($job['evidence_status'] ?? ''), ['processing', 'saved'], true)) throw new RuntimeException('La evidencia no está disponible para procesar.');
        $relative = ltrim(str_replace('..', '', (string) ($job['storage_key'] ?? '')), '/');
        if ($relative === '') throw new RuntimeException('La evidencia no tiene una ruta válida.');
        $path = $this->storageRoot . '/' . $relative;
        if (!is_file($path)) {
            $chunks = $this->db->fetchAll('SELECT chunk_number,storage_key FROM test_media_chunks WHERE evidence_id=? ORDER BY chunk_number ASC', [(int) $job['evidence_id']]);
            if (!$chunks) throw new RuntimeException('No se encontraron fragmentos para consolidar.');
            $this->ensureDirectory(dirname($path));
            $partial = $path . '.part'; $output = fopen($partial, 'wb'); if ($output === false) throw new RuntimeException('No se pudo abrir el archivo temporal.');
            $expected = 0;
            try {
                foreach ($chunks as $chunk) {
                    if ((int) $chunk['chunk_number'] !== $expected) throw new RuntimeException('Falta un fragmento audiovisual.');
                    $input = fopen($this->absolutePath((string) $chunk['storage_key']), 'rb'); if ($input === false) throw new RuntimeException('No se pudo leer un fragmento audiovisual.');
                    stream_copy_to_stream($input, $output); fclose($input); $expected++;
                }
            } finally { fclose($output); }
            if (!rename($partial, $path)) { @unlink($partial); throw new RuntimeException('No se pudo publicar la evidencia consolidada.'); }
        }
        $size = (int) filesize($path); $sha256 = hash_file('sha256', $path) ?: '';
        if ($size <= 0 || !$this->hasExpectedContainer($path, (string) ($job['mime_type'] ?? ''))) throw new RuntimeException('La evidencia audiovisual no tiene un contenedor válido.');
        return ['processor' => 'mediarecorder_metadata_v1', 'provider' => 'browser_media_recorder', 'file_size' => $size, 'sha256' => $sha256, 'transcription' => ['status' => 'not_applicable', 'provider' => 'evaluation_mediarecorder']];
    }

    private function hasExpectedContainer(string $path, string $mime): bool
    {
        $handle = fopen($path, 'rb'); if ($handle === false) return false; $header = fread($handle, 16); fclose($handle);
        return is_string($header) && ((strpos($mime, 'webm') !== false && substr($header, 0, 4) === "\x1A\x45\xDF\xA3") || (strpos($mime, 'mp4') !== false && strpos($header, 'ftyp') !== false));
    }

    private function ensureDirectory(string $path): void { if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) throw new RuntimeException('No se pudo preparar el almacenamiento.'); }
    private function absolutePath(string $relative): string { return $this->storageRoot . '/' . ltrim(str_replace('..', '', $relative), '/'); }
}
