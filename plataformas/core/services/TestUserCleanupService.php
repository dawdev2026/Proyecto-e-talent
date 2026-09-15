<?php
declare(strict_types=1);

final class TestUserCleanupService
{
    public function preview(int $companyId): array
    {
        [$company, $userIds] = $this->context($companyId);
        $tests = database('tests');
        $evaluations = database('evaluaciones_encuestas');

        return [
            'company' => $company,
            'users' => count($userIds),
            'active_users' => $this->countByIds(database('core'), 'users', 'id', $userIds, 'is_active = 1'),
            'test_sessions' => $this->countByIds($tests, 'test_sessions', 'user_id', $userIds),
            'test_assignments' => $this->countByIds($tests, 'test_process_evaluation_assignments', 'user_id', $userIds),
            'evaluation_attempts' => $this->countByIds($evaluations, 'evaluation_survey_attempts', 'user_id', $userIds),
            'interview_appointments' => $this->countByIds(database('interviews'), 'interview_appointments', 'candidate_user_id', $userIds),
        ];
    }

    public function delete(int $companyId, string $confirmationPrefix): array
    {
        [$company, $userIds] = $this->context($companyId);
        $prefix = trim((string) ($company['url_prefix'] ?? ''));
        if ($prefix === '' || !hash_equals($prefix, trim($confirmationPrefix))) {
            throw new InvalidArgumentException('El prefijo de confirmacion no coincide.');
        }

        if ($userIds === []) {
            return ['company' => $company, 'users' => 0];
        }

        $tests = database('tests');
        $evaluations = database('evaluaciones_encuestas');
        $interviews = database('interviews');
        $core = database('core');

        $sessionIds = $this->ids($tests->fetchAll(
            'SELECT id FROM test_sessions WHERE ' . $this->inCondition('user_id', $userIds),
            $userIds
        ));

        $tests->transaction(function (Database $db) use ($userIds, $sessionIds): void {
            $this->deleteIfTable($db, 'test_process_evaluation_assignments', 'user_id', $userIds);
            $this->deleteIfTable($db, 'test_activity_events', 'user_id', $userIds);
            // Las tablas de respuestas y evidencias dependen de la sesion y se eliminan por FK CASCADE.
            $this->deleteByIds($db, 'test_sessions', 'id', $sessionIds);
            $this->deleteIfTable($db, 'test_session_rollups', 'user_id', $userIds);
        });

        $evaluations->transaction(function (Database $db) use ($userIds): void {
            $this->deleteIfTable($db, 'evaluation_survey_attempts', 'user_id', $userIds);
            $this->deleteIfTable($db, 'evaluation_survey_media_access_audit', 'actor_user_id', $userIds);
        });

        $interviews->transaction(function (Database $db) use ($userIds): void {
            $this->deleteIfTable($db, 'interview_appointments', 'candidate_user_id', $userIds);
        });

        $core->transaction(function (Database $db) use ($userIds): void {
            // Estas tablas tienen FK RESTRICT hacia users y deben limpiarse antes del usuario.
            $this->deleteIfTable($db, 'report_generation_batch_items', 'user_id', $userIds);
            $this->deleteIfTable($db, 'report_execution_logs', 'user_id', $userIds);
            $this->deleteIfTable($db, 'user_field_values', 'user_id', $userIds);
            $this->deleteIfTable($db, 'password_reset_tokens', 'user_id', $userIds);
            $this->deleteIfTable($db, 'login_verification_codes', 'user_id', $userIds);
            $this->deleteByIds($db, 'users', 'id', $userIds);
        });

        security_log(sprintf(
            'Limpieza de usuarios de prueba: company_id=%d, users_deleted=%d, actor_user_id=%d',
            $companyId,
            count($userIds),
            (int) (current_user()['id'] ?? 0)
        ));

        foreach ($sessionIds as $sessionId) {
            $this->removeDirectory(BASE_PATH . '/storage/test-evidence/sessions/' . $sessionId, BASE_PATH . '/storage/test-evidence/sessions');
        }

        return ['company' => $company, 'users' => count($userIds)];
    }

    /** @return array{0: array<string,mixed>, 1: list<int>} */
    private function context(int $companyId): array
    {
        if ($companyId <= 0) {
            throw new InvalidArgumentException('Empresa invalida.');
        }
        $company = database('core')->fetch('SELECT id, name, url_prefix FROM companies WHERE id = ? LIMIT 1', [$companyId]);
        if (!$company) {
            throw new RuntimeException('La empresa no existe.');
        }
        $rows = database('core')->fetchAll("SELECT id FROM users WHERE company_id = ? AND role = 'usuario'", [$companyId]);
        return [$company, $this->ids($rows)];
    }

    /** @param list<int> $ids */
    private function countByIds(Database $db, string $table, string $column, array $ids, string $extra = ''): int
    {
        if ($ids === [] || !$this->tableExists($db, $table)) {
            return 0;
        }
        $where = $this->inCondition($column, $ids) . ($extra !== '' ? ' AND ' . $extra : '');
        $row = $db->fetch('SELECT COUNT(*) AS total FROM ' . $table . ' WHERE ' . $where, $ids);
        return (int) ($row['total'] ?? 0);
    }

    /** @param list<int> $ids */
    private function deleteIfTable(Database $db, string $table, string $column, array $ids): void
    {
        if ($this->tableExists($db, $table)) {
            $this->deleteByIds($db, $table, $column, $ids);
        }
    }

    /** @param list<int> $ids */
    private function deleteByIds(Database $db, string $table, string $column, array $ids): void
    {
        if ($ids !== []) {
            $db->execute('DELETE FROM ' . $table . ' WHERE ' . $this->inCondition($column, $ids), $ids);
        }
    }

    private function tableExists(Database $db, string $table): bool
    {
        return $db->fetch('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1', [$table]) !== null;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private function ids(array $rows): array
    {
        return array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows)));
    }

    /** @param list<int> $ids */
    private function inCondition(string $column, array $ids): string
    {
        return $column . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    }

    private function removeDirectory(string $path, string $allowedRoot): void
    {
        $base = realpath($allowedRoot);
        $target = realpath($path);
        if ($target === false || $base === false || !str_starts_with($target . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($target);
    }
}
