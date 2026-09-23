<?php
declare(strict_types=1);

final class CompanyDeletionService
{
    public function delete(int $companyId): void
    {
        if ($companyId <= 0) {
            throw new InvalidArgumentException('Empresa invalida.');
        }

        $company = database('core')->fetch('SELECT id FROM companies WHERE id = ?', [$companyId]);
        if (!$company) {
            throw new RuntimeException('La empresa no existe.');
        }

        $core = database('core');
        $tests = database('tests');
        $evaluations = database('evaluaciones_encuestas');
        $interviews = database('interviews');

        $userIds = $this->ids($core->fetchAll('SELECT id FROM users WHERE company_id = ?', [$companyId]));
        $processIds = $this->ids($tests->fetchAll('SELECT id FROM test_processes WHERE company_id = ?', [$companyId]));
        $formIds = $this->ids($evaluations->fetchAll('SELECT id FROM evaluation_survey_forms WHERE company_id = ?', [$companyId]));
        $interviewProcessIds = $this->ids($interviews->fetchAll('SELECT id FROM interview_processes WHERE company_id = ?', [$companyId]));
        $batchIds = $this->ids($core->fetchAll('SELECT id FROM report_generation_batches WHERE company_id = ?', [$companyId]));
        $sessionIds = $this->ids($tests->fetchAll(
            'SELECT id FROM test_sessions WHERE ' . $this->inCondition('process_id', $processIds) . ' OR ' . $this->inCondition('user_id', $userIds),
            array_merge($processIds, $userIds)
        ));

        // Cada conexión se confirma solo después de borrar sus dependencias locales.
        // La eliminación de la empresa se mantiene al final en la conexión core.
        $tests->transaction(function (Database $db) use ($companyId, $processIds, $userIds, $sessionIds): void {
            $this->deleteByIds($db, 'test_sessions', 'id', $sessionIds);
            $this->deleteByIds($db, 'test_activity_events', 'user_id', $userIds);
            $this->deleteByIds($db, 'test_processes', 'id', $processIds);
            $db->execute('DELETE FROM company_test_instruments WHERE company_id = ?', [$companyId]);
            $db->execute('DELETE FROM test_ranking_company_assignments WHERE company_id = ?', [$companyId]);
        });

        $evaluations->transaction(function (Database $db) use ($companyId, $formIds, $userIds): void {
            $this->deleteByIds($db, 'evaluation_survey_attempts', 'user_id', $userIds);
            $this->deleteByIds($db, 'evaluation_survey_forms', 'id', $formIds);
            // Las preguntas y respuestas dependientes se eliminan por sus FK CASCADE.
            $db->execute('DELETE FROM evaluation_survey_questions WHERE ' . $this->inCondition('form_id', $formIds), $formIds);
            $db->execute('DELETE FROM evaluation_survey_forms WHERE company_id = ?', [$companyId]);
        });

        $interviews->transaction(function (Database $db) use ($companyId, $interviewProcessIds): void {
            $this->deleteByIds($db, 'interview_processes', 'id', $interviewProcessIds);
            $db->execute('DELETE FROM interview_processes WHERE company_id = ?', [$companyId]);
        });

        $core->transaction(function (Database $db) use ($companyId, $userIds, $batchIds): void {
            $this->deleteByIds($db, 'report_generation_batch_items', 'batch_id', $batchIds);
            $this->deleteByIds($db, 'report_generation_batch_items', 'user_id', $userIds);
            $db->execute('DELETE FROM report_generation_batches WHERE company_id = ?', [$companyId]);
            $db->execute('DELETE FROM report_execution_logs WHERE company_id = ?', [$companyId]);
            $db->execute('DELETE FROM report_company_assignments WHERE company_id = ?', [$companyId]);
            $this->deleteByIds($db, 'password_reset_tokens', 'user_id', $userIds);
            $db->execute('DELETE FROM user_field_definitions WHERE company_id = ?', [$companyId]);
            $this->deleteByIds($db, 'users', 'id', $userIds);
            $db->execute('DELETE FROM company_branding WHERE company_id = ?', [$companyId]);
            $db->execute('DELETE FROM company_mail_settings WHERE company_id = ?', [$companyId]);
            $db->execute('DELETE FROM companies WHERE id = ?', [$companyId]);
        });

        $this->removeDirectory(BASE_PATH . '/public/uploads/branding/company/' . $companyId, BASE_PATH . '/public/uploads/branding/company');
        foreach ($sessionIds as $sessionId) {
            $this->removeDirectory(BASE_PATH . '/storage/test-evidence/sessions/' . $sessionId, BASE_PATH . '/storage/test-evidence/sessions');
        }
    }

    /** @param list<array<string, mixed>> $rows @return list<int> */
    private function ids(array $rows): array
    {
        return array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows)));
    }

    /** @param list<int> $ids */
    private function deleteByIds(Database $db, string $table, string $column, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $db->execute('DELETE FROM ' . $table . ' WHERE ' . $this->inCondition($column, $ids), $ids);
    }

    /** @param list<int> $ids */
    private function inCondition(string $column, array $ids): string
    {
        if ($ids === []) {
            return '1 = 0';
        }

        return $column . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    }

    private function removeDirectory(string $path, string $allowedRoot): void
    {
        $base = realpath($allowedRoot);
        $target = realpath($path);
        if ($target === false || $base === false || !str_starts_with($target . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($target);
    }
}
