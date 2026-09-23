<?php
declare(strict_types=1);

final class ReportBatchService
{
    private Database $db;
    private Database $testsDb;
    private string $coreSchema;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('core');
        $this->testsDb = database('tests');
        $this->coreSchema = database_identifier('core');
    }

    public function queue(int $reportId, int $companyId, ?int $processId, string $format, int $requestedBy, array $userIds): int
    {
        if ($format !== 'pdf') {
            throw new InvalidArgumentException('Los lotes masivos solo están disponibles en formato PDF.');
        }
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if (!$userIds) {
            throw new InvalidArgumentException('El lote debe contener al menos un usuario.');
        }
        $this->assertUsersBelongToCompany($companyId, $userIds, $processId);
        return $this->db->transaction(function () use ($reportId, $companyId, $processId, $format, $requestedBy, $userIds): int {
            $batchId = $this->db->insert(
                'INSERT INTO report_generation_batches (report_id, company_id, process_id, requested_by, format, total_items) VALUES (?, ?, ?, ?, ?, ?)',
                [$reportId, $companyId, $processId ?: null, $requestedBy, $format, count($userIds)]
            );
            foreach (array_chunk($userIds, 500) as $chunk) {
                $params = [];
                foreach ($chunk as $userId) {
                    $params[] = $batchId;
                    $params[] = $userId;
                }
                $this->db->execute(
                    'INSERT INTO report_generation_batch_items (batch_id, user_id) VALUES ' . implode(', ', array_fill(0, count($chunk), '(?, ?)')),
                    $params
                );
            }
            return $batchId;
        });
    }

    public function status(int $batchId): ?array
    {
        return $this->db->fetch(
            'SELECT b.*, r.name AS report_name, c.name AS company_name,
                    SUM(i.status = \'completed\') AS completed_items,
                    SUM(i.status = \'failed\') AS failed_items,
                    SUM(i.status = \'processing\') AS processing_items
             FROM report_generation_batches b
             INNER JOIN report_definitions r ON r.id = b.report_id
             INNER JOIN companies c ON c.id = b.company_id
             LEFT JOIN report_generation_batch_items i ON i.batch_id = b.id
             WHERE b.id = ?
             GROUP BY b.id
             LIMIT 1',
            [$batchId]
        );
    }

    public function processNext(int $maxAttempts = 3): bool
    {
        $item = $this->claimNext($maxAttempts);
        if (!$item) {
            return false;
        }

        try {
            if ((string) $item['format'] !== 'pdf') {
                throw new RuntimeException('El worker masivo actualmente procesa PDF.');
            }

            $report = (new ReportDefinitionModel())->find((int) $item['report_id']);
            if (!$report || (string) ($report['status'] ?? '') !== 'active') {
                throw new RuntimeException('El informe no está disponible.');
            }

            $execution = (new ReportMarkdownInterpreter())->execute(
                (string) ($report['markdown_content'] ?? ''),
                (int) $item['process_id'],
                (int) $item['user_id'],
                (int) $item['company_id']
            );
            if (empty($execution['available'])) {
                throw new RuntimeException((string) ($execution['message'] ?? 'El informe no está disponible para este postulante.'));
            }

            $pdf = (new ReportDocumentRenderer())->renderPdf($execution);
            $batchDir = BASE_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'report_batches' . DIRECTORY_SEPARATOR . (string) $item['batch_id'];
            $pdfDir = $batchDir . DIRECTORY_SEPARATOR . 'pdf';
            if (!is_dir($pdfDir) && !mkdir($pdfDir, 0775, true)) {
                throw new RuntimeException('No se pudo crear el almacenamiento temporal del lote.');
            }

            $filename = $this->safeFilename((string) ($execution['payload']['candidate']['rut'] ?? 'postulante')) . '_' . (int) $item['user_id'] . '.pdf';
            $absolutePath = $pdfDir . DIRECTORY_SEPARATOR . $filename;
            if (file_put_contents($absolutePath, $pdf) === false) {
                throw new RuntimeException('No se pudo guardar el PDF temporal.');
            }

            $relativeKey = 'report_batches/' . (int) $item['batch_id'] . '/pdf/' . $filename;
            $this->db->execute(
                'UPDATE report_generation_batch_items SET status = "completed", storage_key = ?, mime_type = ?, file_size = ?, completed_at = CURRENT_TIMESTAMP, locked_at = NULL WHERE id = ? AND status = "processing"',
                [$relativeKey, 'application/pdf', filesize($absolutePath), (int) $item['id']]
            );
            $this->refreshBatch((int) $item['batch_id']);
            return true;
        } catch (Throwable $exception) {
            $this->failItem((int) $item['id'], (string) $exception->getMessage(), (int) $item['attempts'] < $maxAttempts);
            return true;
        }
    }

    private function claimNext(int $maxAttempts): ?array
    {
        $this->db->execute('UPDATE report_generation_batch_items SET status = "queued", locked_at = NULL WHERE status = "processing" AND locked_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)');
        $item = $this->db->fetch(
            'SELECT i.*, b.report_id, b.company_id, b.process_id, b.format
             FROM report_generation_batch_items i
             INNER JOIN report_generation_batches b ON b.id = i.batch_id
             WHERE i.status = "queued" AND i.attempts < ? AND b.status IN ("queued", "running")
             ORDER BY i.created_at ASC, i.id ASC LIMIT 1',
            [$maxAttempts]
        );
        if (!$item) {
            return null;
        }

        $changed = $this->db->execute(
            'UPDATE report_generation_batch_items SET status = "processing", attempts = attempts + 1, locked_at = NOW(), started_at = COALESCE(started_at, NOW()) WHERE id = ? AND status = "queued" AND attempts < ?',
            [(int) $item['id'], $maxAttempts]
        );
        if ($changed !== 1) {
            return null;
        }

        $this->db->execute('UPDATE report_generation_batches SET status = "running", started_at = COALESCE(started_at, CURRENT_TIMESTAMP) WHERE id = ? AND status = "queued"', [(int) $item['batch_id']]);
        $item['attempts'] = (int) $item['attempts'] + 1;
        return $item;
    }

    private function failItem(int $itemId, string $error, bool $retry): void
    {
        $this->db->execute(
            'UPDATE report_generation_batch_items SET status = ?, error_message = ?, locked_at = NULL WHERE id = ? AND status = "processing"',
            [$retry ? 'queued' : 'failed', mb_substr($error, 0, 500), $itemId]
        );
        $item = $this->db->fetch('SELECT batch_id FROM report_generation_batch_items WHERE id = ? LIMIT 1', [$itemId]);
        if (!$retry && $item) {
            $this->refreshBatch((int) $item['batch_id']);
        }
    }

    private function refreshBatch(int $batchId): void
    {
        $counts = $this->db->fetch('SELECT SUM(status = "queued") AS queued_items, SUM(status = "processing") AS processing_items, SUM(status = "completed") AS completed_items, SUM(status = "failed") AS failed_items FROM report_generation_batch_items WHERE batch_id = ?', [$batchId]) ?: [];
        $queued = (int) ($counts['queued_items'] ?? 0);
        $processing = (int) ($counts['processing_items'] ?? 0);
        $completed = (int) ($counts['completed_items'] ?? 0);
        $failed = (int) ($counts['failed_items'] ?? 0);
        $batch = $this->db->fetch('SELECT total_items, status FROM report_generation_batches WHERE id = ? LIMIT 1', [$batchId]);
        if (!$batch || $queued > 0 || $processing > 0) {
            $this->db->execute('UPDATE report_generation_batches SET completed_items = ?, failed_items = ? WHERE id = ?', [$completed, $failed, $batchId]);
            return;
        }

        if ($completed === (int) $batch['total_items']) {
            $this->createZip($batchId);
            return;
        }
        $this->db->execute('UPDATE report_generation_batches SET status = "failed", completed_items = ?, failed_items = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?', [$completed, $failed, $batchId]);
    }

    private function createZip(int $batchId): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive no está disponible.');
        }
        $batchDir = BASE_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'report_batches' . DIRECTORY_SEPARATOR . $batchId;
        $zipFilename = 'reportes_' . $batchId . '.zip';
        $zipPath = $batchDir . DIRECTORY_SEPARATOR . $zipFilename;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP del lote.');
        }
        foreach ($this->db->fetchAll('SELECT storage_key FROM report_generation_batch_items WHERE batch_id = ? AND status = "completed"', [$batchId]) as $item) {
            $absolute = BASE_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . (string) $item['storage_key'];
            if (is_file($absolute)) {
                $zip->addFile($absolute, basename($absolute));
            }
        }
        $zip->close();
        $token = bin2hex(random_bytes(32));
        $this->db->execute('UPDATE report_generation_batches SET status = "completed", completed_items = total_items, storage_key = ?, zip_filename = ?, download_token = ?, expires_at = DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY), completed_at = CURRENT_TIMESTAMP WHERE id = ?', ['report_batches/' . $batchId . '/' . $zipFilename, $zipFilename, $token, $batchId]);
    }

    private function safeFilename(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9_-]+/', '_', $value) ?: 'POSTULANTE';
        return trim($value, '_-') ?: 'POSTULANTE';
    }

    private function assertUsersBelongToCompany(int $companyId, array $userIds, ?int $processId): void
    {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $params = array_merge([$companyId], $userIds);
        $sql = "SELECT COUNT(*) AS total FROM users WHERE company_id = ? AND is_active = 1 AND id IN ({$placeholders})";
        if ($processId) {
            $sql = "SELECT COUNT(DISTINCT pu.user_id) AS total
                    FROM test_process_users pu
                    INNER JOIN {$this->coreSchema}.users u ON u.id = pu.user_id AND u.company_id = ? AND u.is_active = 1
                    WHERE pu.process_id = ? AND pu.user_id IN ({$placeholders})";
            $params = array_merge([$companyId, $processId], $userIds);
        }
        $row = $processId ? $this->testsDb->fetch($sql, $params) : $this->db->fetch($sql, $params);
        if ((int) ($row['total'] ?? 0) !== count($userIds)) {
            throw new InvalidArgumentException('Todos los usuarios del lote deben pertenecer a la empresa y proceso indicados.');
        }
    }
}
