<?php
declare(strict_types=1);

final class CompanyTestAssignmentModel
{
    private Database $db;

    private const CONFIG_FIELDS = [
        'duration_minutes', 'question_order_mode', 'use_blocks', 'block_size',
        'require_block_completion', 'user_can_view_results', 'show_question_numbers',
        'auto_start_enabled', 'auto_start_order', 'control_mode',
    ];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('tests');
    }

    public function assignmentsForCompany(int $companyId): array
    {
        return $this->db->fetchAll('
            SELECT i.id, i.code, i.name, i.category, i.duration_minutes AS default_duration_minutes,
                   i.question_order_mode AS default_question_order_mode, i.use_blocks AS default_use_blocks,
                   i.block_size AS default_block_size, i.require_block_completion AS default_require_block_completion,
                   i.user_can_view_results AS default_user_can_view_results,
                   i.show_question_numbers AS default_show_question_numbers,
                   i.auto_start_enabled AS default_auto_start_enabled,
                   i.auto_start_order AS default_auto_start_order,
                   i.control_mode AS default_control_mode,
                   a.id AS assignment_id, a.duration_minutes, a.question_order_mode, a.use_blocks,
                   a.block_size, a.require_block_completion, a.user_can_view_results,
                   a.show_question_numbers, a.auto_start_enabled, a.auto_start_order, a.control_mode
            FROM test_instruments i
            LEFT JOIN company_test_instruments a ON a.instrument_id = i.id AND a.company_id = ?
            WHERE i.status = "active"
            ORDER BY i.name ASC
        ', [$companyId]);
    }

    public function saveCompanyAssignments(int $companyId, array $posted): void
    {
        if ($companyId <= 0) {
            throw new InvalidArgumentException('Selecciona una empresa válida.');
        }

        $this->db->transaction(function (Database $db) use ($companyId, $posted): void {
            $selectedIds = [];
            $activeRows = $db->fetchAll('SELECT id FROM test_instruments WHERE status = "active"');
            $activeInstrumentIds = array_fill_keys(array_map(static fn(array $row): int => (int) $row['id'], $activeRows), true);
            $upsertRows = [];
            foreach ($posted as $instrumentId => $config) {
                $instrumentId = (int) $instrumentId;
                if ($instrumentId <= 0 || !is_array($config) || empty($config['enabled'])) {
                    continue;
                }
                if (!isset($activeInstrumentIds[$instrumentId])) {
                    continue;
                }
                $selectedIds[] = $instrumentId;
                $values = $this->normalizeConfig($config);
                $upsertRows[] = array_merge([$companyId, $instrumentId], array_values($values));
            }
            if ($upsertRows) {
                $fields = implode(', ', array_merge(['company_id', 'instrument_id'], self::CONFIG_FIELDS));
                $rowPlaceholders = '(' . implode(', ', array_fill(0, count(self::CONFIG_FIELDS) + 2, '?')) . ')';
                $sets = implode(', ', array_map(static fn(string $field): string => $field . ' = VALUES(' . $field . ')', self::CONFIG_FIELDS));
                $db->execute(
                    'INSERT INTO company_test_instruments (' . $fields . ') VALUES ' . implode(', ', array_fill(0, count($upsertRows), $rowPlaceholders)) . ' ON DUPLICATE KEY UPDATE ' . $sets,
                    array_merge(...$upsertRows)
                );
            }

            if ($selectedIds) {
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $db->execute('DELETE FROM company_test_instruments WHERE company_id = ? AND instrument_id NOT IN (' . $placeholders . ')', array_merge([$companyId], $selectedIds));
            } else {
                $db->execute('DELETE FROM company_test_instruments WHERE company_id = ?', [$companyId]);
            }
        });
    }

    private function normalizeConfig(array $config): array
    {
        $nullableIntegers = ['duration_minutes', 'block_size', 'auto_start_order'];
        $nullableBooleans = ['use_blocks', 'require_block_completion', 'user_can_view_results', 'show_question_numbers', 'auto_start_enabled'];
        $values = [];
        foreach (self::CONFIG_FIELDS as $field) {
            $value = $config[$field] ?? null;
            if (in_array($field, $nullableIntegers, true)) {
                $values[$field] = $value === '' || $value === null ? null : max(0, (int) $value);
            } elseif (in_array($field, $nullableBooleans, true)) {
                $values[$field] = in_array((string) $value, ['0', '1'], true) ? (int) $value : null;
            } elseif ($field === 'question_order_mode') {
                $values[$field] = in_array((string) $value, ['ordered', 'random'], true) ? (string) $value : null;
            } elseif ($field === 'control_mode') {
                $values[$field] = in_array((string) $value, ['off', 'activity', 'supervised', 'supervised_audio_visual'], true) ? (string) $value : null;
            }
        }
        return $values;
    }
}
