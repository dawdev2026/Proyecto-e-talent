<?php
declare(strict_types=1);

final class EvaluationSurveyMediaProcessingService
{
    private Database $db;
    private string $storageRoot;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('evaluaciones_encuestas');
        $this->storageRoot = BASE_PATH . '/storage/evaluation-evidence';
    }

    public function enqueue(int $evidenceId): bool
    {
        if ($evidenceId <= 0) {
            return false;
        }

        return (bool) $this->db->transaction(function (Database $db) use ($evidenceId): bool {
            $evidence = $db->fetch('SELECT id, status FROM evaluation_survey_media_evidence WHERE id = ? LIMIT 1', [$evidenceId]);
            if (!$evidence || !in_array((string) ($evidence['status'] ?? ''), ['processing', 'saved'], true)) {
                return false;
            }
            $db->execute('INSERT INTO evaluation_survey_media_processing_jobs (evidence_id, status, attempts, last_error) VALUES (?, "queued", 0, NULL) ON DUPLICATE KEY UPDATE status="queued", attempts=0, locked_at=NULL, completed_at=NULL, last_error=NULL, updated_at=CURRENT_TIMESTAMP', [$evidenceId]);
            $db->execute('UPDATE evaluation_survey_media_evidence SET processing_status="queued", processing_error=NULL, processed_at=NULL, updated_at=NOW() WHERE id=?', [$evidenceId]);
            return true;
        });
    }

    public function processNext(int $maxAttempts = 3): bool
    {
        $maxAttempts = max(1, min(10, $maxAttempts));
        $job = $this->claimNext($maxAttempts);
        if (!$job) {
            return false;
        }

        try {
            $result = $this->inspectEvidence($job);
            $summary = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->db->transaction(function (Database $db) use ($job, $summary, $result): void {
                $db->execute('UPDATE evaluation_survey_media_processing_jobs SET status="completed", completed_at=NOW(), last_error=NULL, updated_at=NOW() WHERE id=? AND status="processing"', [(int) $job['id']]);
                $db->execute('UPDATE evaluation_survey_media_evidence SET status="saved", file_size=?, sha256=?, processing_status="completed", processing_error=NULL, processing_summary_json=?, processed_at=NOW(), updated_at=NOW() WHERE id=?', [(int) ($result['file_size'] ?? 0), (string) ($result['sha256'] ?? ''), $summary, (int) $job['evidence_id']]);
            });
            return true;
        } catch (Throwable $exception) {
            $message = mb_substr($exception->getMessage(), 0, 500);
            $retry = (int) ($job['attempts'] ?? 0) < $maxAttempts;
            $this->db->transaction(function (Database $db) use ($job, $message, $retry): void {
                $db->execute('UPDATE evaluation_survey_media_processing_jobs SET status=?, locked_at=NULL, last_error=?, updated_at=NOW() WHERE id=? AND status="processing"', [$retry ? 'queued' : 'failed', $message, (int) $job['id']]);
                $db->execute('UPDATE evaluation_survey_media_evidence SET processing_status=?, processing_error=?, updated_at=NOW() WHERE id=?', [$retry ? 'queued' : 'failed', $message, (int) $job['evidence_id']]);
            });
            return true;
        }
    }

    private function claimNext(int $maxAttempts): ?array
    {
        return $this->db->transaction(function (Database $db) use ($maxAttempts): ?array {
            $db->execute('UPDATE evaluation_survey_media_processing_jobs SET status="queued", locked_at=NULL WHERE status="processing" AND locked_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)');
            $job = $db->fetch('SELECT j.*, e.storage_key, e.file_size, e.sha256, e.mime_type, e.status AS evidence_status FROM evaluation_survey_media_processing_jobs j JOIN evaluation_survey_media_evidence e ON e.id=j.evidence_id WHERE j.status="queued" AND j.attempts < ? ORDER BY j.id ASC LIMIT 1 FOR UPDATE', [$maxAttempts]);
            if (!$job) {
                return null;
            }
            $changed = $db->execute('UPDATE evaluation_survey_media_processing_jobs SET status="processing", attempts=attempts+1, locked_at=NOW(), started_at=COALESCE(started_at,NOW()), updated_at=NOW() WHERE id=? AND status="queued" AND attempts < ?', [(int) $job['id'], $maxAttempts]);
            return $changed > 0 ? $job : null;
        });
    }

    private function inspectEvidence(array $job): array
    {
        if (!in_array((string) ($job['evidence_status'] ?? ''), ['processing', 'saved'], true)) {
            throw new RuntimeException('La evidencia no está en estado guardado.');
        }
        $relative = ltrim(str_replace('..', '', (string) ($job['storage_key'] ?? '')), '/');
        $path = $this->storageRoot . '/' . $relative;
        if ($relative === '') {
            throw new RuntimeException('La evidencia no tiene una ruta de almacenamiento válida.');
        }
        if (!is_file($path)) {
            $this->consolidateChunks((int) $job['evidence_id'], $path);
        }
        $size = (int) filesize($path);
        $sha256 = hash_file('sha256', $path) ?: '';
        if ($size <= 0 || ($job['file_size'] !== null && $size !== (int) $job['file_size']) || ($job['sha256'] && !hash_equals((string) $job['sha256'], $sha256))) {
            throw new RuntimeException('La integridad del archivo audiovisual no coincide con sus metadatos.');
        }
        if (!$this->looksLikeMedia($path, (string) ($job['mime_type'] ?? ''))) {
            throw new RuntimeException('La evidencia no tiene un contenedor audiovisual válido.');
        }
        $chunks = $this->db->fetch('SELECT COUNT(*) AS total, COALESCE(SUM(size_bytes), 0) AS bytes FROM evaluation_survey_media_chunks WHERE evidence_id=?', [(int) $job['evidence_id']]) ?: [];
        if ((int) ($chunks['bytes'] ?? 0) !== $size) {
            throw new RuntimeException('El tamaño consolidado no coincide con los fragmentos recibidos.');
        }
        $risks = $this->db->fetch('SELECT COUNT(*) AS total, SUM(severity="risk") AS risk_total, SUM(severity="attention") AS attention_total, SUM(severity="info") AS info_total FROM evaluation_survey_media_risk_events WHERE evidence_id=?', [(int) $job['evidence_id']]) ?: [];
        return [
            'processor' => 'mediarecorder_metadata_risk_v1',
            'provider' => 'browser_media_recorder',
            'mime_type' => (string) ($job['mime_type'] ?? ''),
            'file_size' => $size,
            'sha256' => $sha256,
            'chunks_total' => (int) ($chunks['total'] ?? 0),
            'chunks_bytes' => (int) ($chunks['bytes'] ?? 0),
            'risk_events_total' => (int) ($risks['total'] ?? 0),
            'risk_events' => [
                'risk' => (int) ($risks['risk_total'] ?? 0),
                'attention' => (int) ($risks['attention_total'] ?? 0),
                'info' => (int) ($risks['info_total'] ?? 0),
            ],
            // Daily pertenece exclusivamente al modulo de entrevistas. Este
            // worker procesa archivos MediaRecorder de evaluaciones y no llama
            // APIs de entrevistas ni intenta convertir el archivo mediante el
            // sistema operativo.
            'transcription' => ['status' => 'not_applicable', 'provider' => 'evaluation_mediarecorder'],
        ];
    }

    private function consolidateChunks(int $evidenceId, string $path): void
    {
        $chunks = $this->db->fetchAll('SELECT chunk_number, storage_key FROM evaluation_survey_media_chunks WHERE evidence_id=? ORDER BY chunk_number ASC', [$evidenceId]);
        if (!$chunks) {
            throw new RuntimeException('No se encontraron fragmentos para consolidar.');
        }
        $this->ensureDirectory(dirname($path));
        $partial = $path . '.part';
        $output = fopen($partial, 'wb');
        if ($output === false) {
            throw new RuntimeException('No se pudo abrir el archivo temporal audiovisual.');
        }
        $expected = 0;
        try {
            foreach ($chunks as $chunk) {
                if ((int) $chunk['chunk_number'] !== $expected) {
                    throw new RuntimeException('Falta un fragmento audiovisual.');
                }
                $input = fopen($this->absolutePath((string) $chunk['storage_key']), 'rb');
                if ($input === false) {
                    throw new RuntimeException('No se pudo leer un fragmento audiovisual.');
                }
                while (!feof($input)) {
                    $buffer = fread($input, 1024 * 1024);
                    if ($buffer === false) {
                        fclose($input);
                        throw new RuntimeException('No se pudo leer un fragmento audiovisual.');
                    }
                    if ($buffer === '') {
                        break;
                    }
                    $written = fwrite($output, $buffer);
                    if ($written !== strlen($buffer)) {
                        fclose($input);
                        throw new RuntimeException('No se pudo consolidar la evidencia audiovisual.');
                    }
                }
                fclose($input);
                $expected++;
            }
        } finally {
            fclose($output);
        }
        if (!rename($partial, $path)) {
            @unlink($partial);
            throw new RuntimeException('No se pudo publicar la evidencia audiovisual consolidada.');
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('No se pudo preparar el almacenamiento audiovisual.');
        }
    }

    private function looksLikeMedia(string $path, string $mime): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 16);
        fclose($handle);
        return is_string($header) && ((strpos($mime, 'webm') !== false && substr($header, 0, 4) === "\x1A\x45\xDF\xA3") || (strpos($mime, 'mp4') !== false && strpos($header, 'ftyp') !== false));
    }

    private function absolutePath(string $relative): string
    {
        return $this->storageRoot . '/' . ltrim(str_replace('..', '', $relative), '/');
    }
}
