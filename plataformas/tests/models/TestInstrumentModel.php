<?php
declare(strict_types=1);

final class TestInstrumentModel
{
    private Database $db;
    private ?bool $hasUserResultVisibilityColumn = null;
    private ?bool $hasQuestionOrderModeColumn = null;
    private ?bool $hasActivityTrackingColumn = null;
    private ?bool $hasSupervisedModeColumn = null;
    private ?bool $hasControlModeColumn = null;
    private ?bool $hasAudioVisualUploadFailurePolicyColumn = null;
    private ?bool $hasAudioVisualRulesColumns = null;
    private ?bool $hasShowQuestionNumbersColumn = null;
    private ?bool $hasAutoStartColumns = null;

    public const CATEGORIES = [
        'personality' => 'Personalidad',
        'behavior' => 'Conductual laboral',
        'cognitive' => 'Aptitud cognitiva',
        'verbal' => 'Comprension e interpretacion',
    ];

    public const STATUSES = [
        'draft' => 'Borrador',
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ];

    public const ITEM_TYPES = [
        'likert' => 'Likert',
        'single_choice' => 'Seleccion unica',
        'multiple_choice' => 'Seleccion multiple',
        'open_text' => 'Texto abierto',
        'numeric' => 'Numerico',
    ];

    public const QUESTION_ORDER_MODES = [
        'ordered' => 'Ordenadas',
        'random' => 'Aleatorias',
    ];

    public const CONTROL_MODES = [
        'off' => 'Sin registro',
        'activity' => 'Registro de actividad',
        'supervised' => 'Rendicion supervisada',
        'supervised_audio_visual' => 'Rendicion supervisada + control audiovisual',
    ];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('tests');
    }

    public function all(): array
    {
        return $this->db->fetchAll('
            SELECT i.*, COUNT(DISTINCT s.id) AS scales_count, COUNT(DISTINCT it.id) AS items_count
            FROM test_instruments i
            LEFT JOIN test_scales s ON s.instrument_id = i.id
            LEFT JOIN test_items it ON it.instrument_id = i.id
            GROUP BY i.id
            ORDER BY FIELD(i.status, "active", "draft", "inactive"), i.name
        ');
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM test_instruments WHERE id = ? LIMIT 1', [$id]);
    }

    public function findByCode(string $code): ?array
    {
        return $this->db->fetch('SELECT * FROM test_instruments WHERE code = ? LIMIT 1', [$code]);
    }

    public function scalesForInstrument(int $instrumentId): array
    {
        return $this->db->fetchAll('
            SELECT *
            FROM test_scales
            WHERE instrument_id = ?
            ORDER BY sort_order ASC, id ASC
        ', [$instrumentId]);
    }

    public function itemsForInstrument(int $instrumentId): array
    {
        return $this->db->fetchAll('
            SELECT it.*, s.scale_key
            FROM test_items it
            LEFT JOIN test_scales s ON s.id = it.scale_id
            WHERE it.instrument_id = ?
            ORDER BY it.sort_order ASC, it.id ASC
        ', [$instrumentId]);
    }

    public function contentStats(int $instrumentId): array
    {
        $row = $this->db->fetch('
            SELECT
                (SELECT COUNT(*) FROM test_scales WHERE instrument_id = ?) AS scales_count,
                (SELECT COUNT(*) FROM test_items WHERE instrument_id = ?) AS items_count,
                (SELECT COUNT(*) FROM test_item_score_rules WHERE instrument_id = ?) AS score_rules_count,
                (SELECT COUNT(*) FROM test_norms WHERE instrument_id = ?) AS norms_count,
                (SELECT COUNT(*) FROM test_scale_formula_terms WHERE instrument_id = ?) AS formula_terms_count
        ', [$instrumentId, $instrumentId, $instrumentId, $instrumentId, $instrumentId]);

        return [
            'scales_count' => (int) ($row['scales_count'] ?? 0),
            'items_count' => (int) ($row['items_count'] ?? 0),
            'score_rules_count' => (int) ($row['score_rules_count'] ?? 0),
            'norms_count' => (int) ($row['norms_count'] ?? 0),
            'formula_terms_count' => (int) ($row['formula_terms_count'] ?? 0),
        ];
    }

    public function hasAdvancedContent(int $instrumentId): bool
    {
        $stats = $this->contentStats($instrumentId);
        return $stats['score_rules_count'] > 0
            || $stats['norms_count'] > 0
            || $stats['formula_terms_count'] > 0;
    }

    public function itemsPageForInstrument(int $instrumentId, int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        return $this->db->fetchAll('
            SELECT it.*, s.scale_key
            FROM test_items it
            LEFT JOIN test_scales s ON s.id = it.scale_id
            WHERE it.instrument_id = ?
            ORDER BY it.sort_order ASC, it.id ASC
            LIMIT ' . $perPage . ' OFFSET ' . $offset . '
        ', [$instrumentId]);
    }

    public function scoreRulesForItems(int $instrumentId, array $itemIds): array
    {
        $itemIds = array_values(array_filter(array_map('intval', $itemIds), static fn(int $id): bool => $id > 0));
        if (!$itemIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $rows = $this->db->fetchAll('
            SELECT r.*, s.scale_key, s.name AS scale_name
            FROM test_item_score_rules r
            JOIN test_scales s ON s.id = r.scale_id
            WHERE r.instrument_id = ? AND r.item_id IN (' . $placeholders . ')
            ORDER BY r.item_id ASC, r.sort_order ASC, r.id ASC
        ', array_merge([$instrumentId], $itemIds));

        $rules = [];
        foreach ($rows as $row) {
            $rules[(int) $row['item_id']][] = $row;
        }

        return $rules;
    }

    public function scaleForInstrument(int $instrumentId, int $scaleId): ?array
    {
        return $this->db->fetch('
            SELECT *
            FROM test_scales
            WHERE instrument_id = ? AND id = ?
            LIMIT 1
        ', [$instrumentId, $scaleId]);
    }

    public function itemForInstrument(int $instrumentId, int $itemId): ?array
    {
        return $this->db->fetch('
            SELECT it.*, s.scale_key
            FROM test_items it
            LEFT JOIN test_scales s ON s.id = it.scale_id
            WHERE it.instrument_id = ? AND it.id = ?
            LIMIT 1
        ', [$instrumentId, $itemId]);
    }

    public function saveScale(int $instrumentId, int $scaleId, array $data): int
    {
        $scaleKey = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($data['scale_key'] ?? '')));
        $name = trim($data['name'] ?? '');
        if ($scaleKey === '' || $name === '') {
            throw new InvalidArgumentException('Clave y nombre de escala son obligatorios.');
        }

        $scaleType = in_array(($data['scale_type'] ?? 'primary'), ['primary', 'validity', 'derived', 'global'], true) ? $data['scale_type'] : 'primary';
        $scoringMethod = in_array(($data['scoring_method'] ?? 'sum'), ['sum', 'formula', 'manual'], true) ? $data['scoring_method'] : 'sum';
        $params = [
            $scaleKey,
            $name,
            trim($data['description'] ?? '') ?: null,
            $scaleType,
            $scoringMethod,
            (int) ($data['sort_order'] ?? 100),
        ];

        if ($scaleId > 0) {
            $this->db->execute('
                UPDATE test_scales
                SET scale_key = ?, name = ?, description = ?, scale_type = ?, scoring_method = ?, sort_order = ?
                WHERE instrument_id = ? AND id = ?
            ', array_merge($params, [$instrumentId, $scaleId]));
            return $scaleId;
        }

        return $this->db->insert('
            INSERT INTO test_scales
                (instrument_id, scale_key, name, description, scale_type, scoring_method, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ', array_merge([$instrumentId], $params));
    }

    public function deleteScale(int $instrumentId, int $scaleId): void
    {
        if ($scaleId <= 0) {
            return;
        }

        $this->db->execute('DELETE FROM test_scales WHERE instrument_id = ? AND id = ?', [$instrumentId, $scaleId]);
    }

    public function saveItemWithRules(int $instrumentId, int $itemId, array $payload): int
    {
        $item = $payload['item'] ?? [];
        $rules = $payload['rules'] ?? [];
        $newRules = $payload['new_rules'] ?? [];
        $scales = $this->scalesForInstrument($instrumentId);
        $scaleIds = array_map(static fn(array $scale): int => (int) $scale['id'], $scales);

        return (int) $this->db->transaction(function (Database $db) use ($instrumentId, $itemId, $item, $rules, $newRules, $scaleIds): int {
            $itemKey = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($item['item_key'] ?? '')));
            $prompt = trim($item['prompt'] ?? '');
            if ($itemKey === '' || $prompt === '') {
                throw new InvalidArgumentException('Clave y enunciado son obligatorios.');
            }

            $itemType = $item['item_type'] ?? 'single_choice';
            if (!isset(self::ITEM_TYPES[$itemType])) {
                $itemType = 'single_choice';
            }

            $scaleId = (int) ($item['scale_id'] ?? 0);
            if ($scaleId <= 0 || !in_array($scaleId, $scaleIds, true)) {
                $scaleId = null;
            }

            $params = [
                $scaleId,
                $itemKey,
                $prompt,
                $itemType,
                $this->normalizeOptionLines((string) ($item['options'] ?? '')) ?: null,
                (int) ($item['reverse_scored'] ?? 0),
                (int) ($item['sort_order'] ?? 100),
                isset($item['is_active']) ? 1 : 0,
            ];

            if ($itemId > 0) {
                $db->execute('
                    UPDATE test_items
                    SET scale_id = ?, item_key = ?, prompt = ?, item_type = ?, options = ?, reverse_scored = ?, sort_order = ?, is_active = ?
                    WHERE instrument_id = ? AND id = ?
                ', array_merge($params, [$instrumentId, $itemId]));
            } else {
                $itemId = $db->insert('
                    INSERT INTO test_items
                        (instrument_id, scale_id, item_key, prompt, item_type, options, reverse_scored, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ', array_merge([$instrumentId], $params));
            }

            foreach ($rules as $ruleId => $rule) {
                $ruleId = (int) $ruleId;
                if ($ruleId <= 0) {
                    continue;
                }

                if (isset($rule['delete'])) {
                    $db->execute('DELETE FROM test_item_score_rules WHERE id = ? AND instrument_id = ?', [$ruleId, $instrumentId]);
                    continue;
                }

                $this->upsertScoreRule($db, $instrumentId, null, $rule, $ruleId);
            }

            foreach ($newRules as $rule) {
                if (!$this->newRuleHasContent($rule)) {
                    continue;
                }
                $this->upsertScoreRule($db, $instrumentId, $itemId, $rule, null);
            }

            return $itemId;
        });
    }

    public function deleteItem(int $instrumentId, int $itemId): void
    {
        if ($itemId <= 0) {
            return;
        }

        $this->db->transaction(function (Database $db) use ($instrumentId, $itemId): void {
            $db->execute('DELETE FROM test_answers WHERE item_id = ?', [$itemId]);
            $db->execute('DELETE FROM test_items WHERE instrument_id = ? AND id = ?', [$instrumentId, $itemId]);
        });
    }

    public function updateAdvancedContentPage(int $instrumentId, array $payload): void
    {
        $scales = $payload['scales'] ?? [];
        $items = $payload['items'] ?? [];
        $rules = $payload['rules'] ?? [];
        $newRules = $payload['new_rules'] ?? [];

        $this->db->transaction(function (Database $db) use ($instrumentId, $scales, $items, $rules, $newRules): void {
            foreach ($scales as $scaleId => $scale) {
                $scaleId = (int) $scaleId;
                if ($scaleId <= 0) {
                    continue;
                }

                $db->execute('
                    UPDATE test_scales
                    SET scale_key = ?, name = ?, description = ?, scale_type = ?, scoring_method = ?, sort_order = ?
                    WHERE id = ? AND instrument_id = ?
                ', [
                    preg_replace('/[^a-z0-9_]/', '', strtolower(trim($scale['scale_key'] ?? ''))),
                    trim($scale['name'] ?? ''),
                    trim($scale['description'] ?? '') ?: null,
                    in_array(($scale['scale_type'] ?? 'primary'), ['primary', 'validity', 'derived', 'global'], true) ? $scale['scale_type'] : 'primary',
                    in_array(($scale['scoring_method'] ?? 'sum'), ['sum', 'formula', 'manual'], true) ? $scale['scoring_method'] : 'sum',
                    (int) ($scale['sort_order'] ?? 100),
                    $scaleId,
                    $instrumentId,
                ]);
            }

            foreach ($items as $itemId => $item) {
                $itemId = (int) $itemId;
                if ($itemId <= 0) {
                    continue;
                }

                $itemType = $item['item_type'] ?? 'single_choice';
                if (!isset(self::ITEM_TYPES[$itemType])) {
                    $itemType = 'single_choice';
                }

                $db->execute('
                    UPDATE test_items
                    SET item_key = ?, prompt = ?, item_type = ?, options = ?, sort_order = ?, is_active = ?
                    WHERE id = ? AND instrument_id = ?
                ', [
                    preg_replace('/[^a-z0-9_]/', '', strtolower(trim($item['item_key'] ?? ''))),
                    trim($item['prompt'] ?? ''),
                    $itemType,
                    $this->normalizeOptionLines((string) ($item['options'] ?? '')) ?: null,
                    (int) ($item['sort_order'] ?? 100),
                    isset($item['is_active']) ? 1 : 0,
                    $itemId,
                    $instrumentId,
                ]);
            }

            foreach ($rules as $ruleId => $rule) {
                $ruleId = (int) $ruleId;
                if ($ruleId <= 0) {
                    continue;
                }

                if (isset($rule['delete'])) {
                    $db->execute('DELETE FROM test_item_score_rules WHERE id = ? AND instrument_id = ?', [$ruleId, $instrumentId]);
                    continue;
                }

                $this->upsertScoreRule($db, $instrumentId, null, $rule, $ruleId);
            }

            foreach ($newRules as $itemId => $rows) {
                $itemId = (int) $itemId;
                if ($itemId <= 0 || !is_array($rows)) {
                    continue;
                }

                foreach ($rows as $rule) {
                    if (!$this->newRuleHasContent($rule)) {
                        continue;
                    }
                    $this->upsertScoreRule($db, $instrumentId, $itemId, $rule, null);
                }
            }
        });
    }

    private function upsertScoreRule(Database $db, int $instrumentId, ?int $itemId, array $rule, ?int $ruleId): void
    {
        $scaleId = (int) ($rule['scale_id'] ?? 0);
        if ($scaleId <= 0) {
            return;
        }

        $ruleType = $rule['rule_type'] ?? 'mapped';
        if (!in_array($ruleType, ['direct', 'reverse', 'keyed', 'mapped'], true)) {
            $ruleType = 'mapped';
        }

        $answerValue = trim((string) ($rule['answer_value'] ?? ''));
        $scoreValue = trim((string) ($rule['score_value'] ?? ''));
        $ruleConfig = trim((string) ($rule['rule_config'] ?? ''));
        $weight = trim((string) ($rule['weight'] ?? '1'));
        $sortOrder = (int) ($rule['sort_order'] ?? 100);
        $isActive = isset($rule['is_active']) ? 1 : 0;

        if ($ruleId) {
            $db->execute('
                UPDATE test_item_score_rules
                SET scale_id = ?, rule_type = ?, answer_value = ?, score_value = ?, weight = ?, rule_config = ?, sort_order = ?, is_active = ?
                WHERE id = ? AND instrument_id = ?
            ', [
                $scaleId,
                $ruleType,
                $answerValue !== '' ? $answerValue : null,
                $scoreValue !== '' && is_numeric($scoreValue) ? (float) $scoreValue : null,
                is_numeric($weight) ? (float) $weight : 1,
                $ruleConfig !== '' ? $ruleConfig : null,
                $sortOrder,
                $isActive,
                $ruleId,
                $instrumentId,
            ]);
            return;
        }

        if ($itemId === null) {
            return;
        }

        $db->execute('
            INSERT INTO test_item_score_rules
                (instrument_id, item_id, scale_id, rule_type, answer_value, score_value, weight, rule_config, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ', [
            $instrumentId,
            $itemId,
            $scaleId,
            $ruleType,
            $answerValue !== '' ? $answerValue : null,
            $scoreValue !== '' && is_numeric($scoreValue) ? (float) $scoreValue : null,
            is_numeric($weight) ? (float) $weight : 1,
            $ruleConfig !== '' ? $ruleConfig : null,
            $sortOrder,
            $isActive,
        ]);
    }

    private function newRuleHasContent(array $rule): bool
    {
        return (int) ($rule['scale_id'] ?? 0) > 0
            || trim((string) ($rule['answer_value'] ?? '')) !== ''
            || trim((string) ($rule['score_value'] ?? '')) !== ''
            || trim((string) ($rule['rule_config'] ?? '')) !== '';
    }

    private function normalizeOptionLines(string $raw): string
    {
        $lines = [];
        foreach (preg_split('/\R/', trim($raw)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return implode(';', $lines);
    }

    public function create(array $data): int
    {
        $columns = [
            'code',
            'name',
            'category',
            'description',
            'source_reference',
            'duration_minutes',
            'instructions',
            'use_blocks',
            'block_size',
            'require_block_completion',
        ];
        $params = [
            $data['code'],
            $data['name'],
            $data['category'],
            $data['description'] ?: null,
            $data['source_reference'] ?: null,
            (int) $data['duration_minutes'],
            $data['instructions'] ?: null,
            (int) $data['use_blocks'],
            (int) $data['block_size'],
            (int) $data['require_block_completion'],
        ];

        if ($this->hasQuestionOrderModeColumn(true)) {
            $columns[] = 'question_order_mode';
            $params[] = $data['question_order_mode'];
        }
        if ($this->hasActivityTrackingColumn(true)) {
            $columns[] = 'track_activity_enabled';
            $params[] = (int) $data['track_activity_enabled'];
        }
        if ($this->hasSupervisedModeColumn(true)) {
            $columns[] = 'supervised_mode_enabled';
            $params[] = (int) $data['supervised_mode_enabled'];
        }
        if ($this->hasControlModeColumn(true)) {
            $columns[] = 'control_mode';
            $params[] = $data['control_mode'];
        }
        if ($this->hasAudioVisualUploadFailurePolicyColumn()) {
            $columns[] = 'audio_visual_upload_failure_policy';
            $params[] = $data['audio_visual_upload_failure_policy'];
        }
        if ($this->hasAudioVisualRulesColumns()) {
            foreach (['audio_visual_interruption_policy', 'audio_visual_voice_policy', 'audio_visual_permission_policy', 'audio_visual_quality_profile'] as $column) {
                $columns[] = $column;
                $params[] = $data[$column];
            }
        }
        if ($this->hasShowQuestionNumbersColumn(true)) {
            $columns[] = 'show_question_numbers';
            $params[] = (int) $data['show_question_numbers'];
        }
        if ($this->hasAutoStartColumns(true)) {
            $columns[] = 'auto_start_enabled';
            $columns[] = 'auto_start_order';
            $params[] = (int) $data['auto_start_enabled'];
            $params[] = (int) $data['auto_start_order'];
        }
        if ($this->hasUserResultVisibilityColumn(true)) {
            $columns[] = 'user_can_view_results';
            $params[] = (int) $data['user_can_view_results'];
        }

        $columns[] = 'status';
        $columns[] = 'requires_manual_review';
        $params[] = $data['status'];
        $params[] = (int) $data['requires_manual_review'];

        return $this->db->insert('
            INSERT INTO test_instruments
                (' . implode(', ', $columns) . ')
            VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')
        ', $params);
    }

    public function update(int $id, array $data): void
    {
        $sets = [
            'code = ?',
            'name = ?',
            'category = ?',
            'description = ?',
            'source_reference = ?',
            'duration_minutes = ?',
            'instructions = ?',
            'use_blocks = ?',
            'block_size = ?',
            'require_block_completion = ?',
        ];
        $params = [
            $data['code'],
            $data['name'],
            $data['category'],
            $data['description'] ?: null,
            $data['source_reference'] ?: null,
            (int) $data['duration_minutes'],
            $data['instructions'] ?: null,
            (int) $data['use_blocks'],
            (int) $data['block_size'],
            (int) $data['require_block_completion'],
        ];

        if ($this->hasQuestionOrderModeColumn(true)) {
            $sets[] = 'question_order_mode = ?';
            $params[] = $data['question_order_mode'];
        }
        if ($this->hasActivityTrackingColumn(true)) {
            $sets[] = 'track_activity_enabled = ?';
            $params[] = (int) $data['track_activity_enabled'];
        }
        if ($this->hasSupervisedModeColumn(true)) {
            $sets[] = 'supervised_mode_enabled = ?';
            $params[] = (int) $data['supervised_mode_enabled'];
        }
        if ($this->hasControlModeColumn(true)) {
            $sets[] = 'control_mode = ?';
            $params[] = $data['control_mode'];
        }
        if ($this->hasAudioVisualUploadFailurePolicyColumn()) {
            $sets[] = 'audio_visual_upload_failure_policy = ?';
            $params[] = $data['audio_visual_upload_failure_policy'];
        }
        if ($this->hasAudioVisualRulesColumns()) {
            foreach (['audio_visual_interruption_policy', 'audio_visual_voice_policy', 'audio_visual_permission_policy', 'audio_visual_quality_profile'] as $column) {
                $sets[] = $column . ' = ?';
                $params[] = $data[$column];
            }
        }
        if ($this->hasShowQuestionNumbersColumn(true)) {
            $sets[] = 'show_question_numbers = ?';
            $params[] = (int) $data['show_question_numbers'];
        }
        if ($this->hasAutoStartColumns(true)) {
            $sets[] = 'auto_start_enabled = ?';
            $sets[] = 'auto_start_order = ?';
            $params[] = (int) $data['auto_start_enabled'];
            $params[] = (int) $data['auto_start_order'];
        }
        if ($this->hasUserResultVisibilityColumn(true)) {
            $sets[] = 'user_can_view_results = ?';
            $params[] = (int) $data['user_can_view_results'];
        }

        $sets[] = 'status = ?';
        $sets[] = 'requires_manual_review = ?';
        $params[] = $data['status'];
        $params[] = (int) $data['requires_manual_review'];
        $params[] = $id;

        $this->db->execute('
            UPDATE test_instruments
            SET ' . implode(', ', $sets) . '
            WHERE id = ?
        ', $params);
    }

    private function hasUserResultVisibilityColumn(bool $createIfMissing = false): bool
    {
        if ($this->hasUserResultVisibilityColumn !== null) {
            return $this->hasUserResultVisibilityColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME = 'user_can_view_results'
            ");

            $this->hasUserResultVisibilityColumn = (int) ($row['total'] ?? 0) > 0;
            if (!$this->hasUserResultVisibilityColumn && $createIfMissing) {
                $this->db->execute('
                    ALTER TABLE test_instruments
                    ADD COLUMN user_can_view_results TINYINT(1) NOT NULL DEFAULT 1
                    AFTER require_block_completion
                ');
                $this->hasUserResultVisibilityColumn = true;
            }
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasUserResultVisibilityColumn = false;
        }

        return $this->hasUserResultVisibilityColumn;
    }

    private function hasQuestionOrderModeColumn(bool $createIfMissing = false): bool
    {
        if ($this->hasQuestionOrderModeColumn !== null) {
            return $this->hasQuestionOrderModeColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME = 'question_order_mode'
            ");

            $this->hasQuestionOrderModeColumn = (int) ($row['total'] ?? 0) > 0;
            if (!$this->hasQuestionOrderModeColumn && $createIfMissing) {
                $this->db->execute("
                    ALTER TABLE test_instruments
                    ADD COLUMN question_order_mode ENUM('ordered', 'random') NOT NULL DEFAULT 'ordered'
                    AFTER require_block_completion
                ");
                $this->hasQuestionOrderModeColumn = true;
            }
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasQuestionOrderModeColumn = false;
        }

        return $this->hasQuestionOrderModeColumn;
    }

    private function hasActivityTrackingColumn(bool $createIfMissing = false): bool
    {
        if ($this->hasActivityTrackingColumn !== null) {
            return $this->hasActivityTrackingColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME = 'track_activity_enabled'
            ");

            $this->hasActivityTrackingColumn = (int) ($row['total'] ?? 0) > 0;
            if (!$this->hasActivityTrackingColumn && $createIfMissing) {
                $this->db->execute('
                    ALTER TABLE test_instruments
                    ADD COLUMN track_activity_enabled TINYINT(1) NOT NULL DEFAULT 0
                    AFTER question_order_mode
                ');
                $this->hasActivityTrackingColumn = true;
            }
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasActivityTrackingColumn = false;
        }

        return $this->hasActivityTrackingColumn;
    }

    private function hasSupervisedModeColumn(bool $createIfMissing = false): bool
    {
        if ($this->hasSupervisedModeColumn !== null) {
            return $this->hasSupervisedModeColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME = 'supervised_mode_enabled'
            ");

            $this->hasSupervisedModeColumn = (int) ($row['total'] ?? 0) > 0;
            if (!$this->hasSupervisedModeColumn && $createIfMissing) {
                $this->db->execute('
                    ALTER TABLE test_instruments
                    ADD COLUMN supervised_mode_enabled TINYINT(1) NOT NULL DEFAULT 0
                    AFTER track_activity_enabled
                ');
                $this->hasSupervisedModeColumn = true;
            }
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasSupervisedModeColumn = false;
        }

        return $this->hasSupervisedModeColumn;
    }

    private function hasControlModeColumn(bool $createIfMissing = false): bool
    {
        if ($this->hasControlModeColumn !== null) {
            return $this->hasControlModeColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME = 'control_mode'
            ");

            $this->hasControlModeColumn = (int) ($row['total'] ?? 0) > 0;
            if (!$this->hasControlModeColumn && $createIfMissing) {
                $this->db->execute('
                    ALTER TABLE test_instruments
                    ADD COLUMN control_mode ENUM("off", "activity", "supervised") NOT NULL DEFAULT "off"
                    AFTER supervised_mode_enabled
                ');
                $this->hasControlModeColumn = true;
            }
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasControlModeColumn = false;
        }

        return $this->hasControlModeColumn;
    }

    private function hasAudioVisualUploadFailurePolicyColumn(bool $createIfMissing = false): bool
    {
        if ($this->hasAudioVisualUploadFailurePolicyColumn !== null) {
            return $this->hasAudioVisualUploadFailurePolicyColumn;
        }

        try {
            $row = $this->db->fetch("SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'test_instruments' AND COLUMN_NAME = 'audio_visual_upload_failure_policy'");
            $this->hasAudioVisualUploadFailurePolicyColumn = (int) ($row['total'] ?? 0) > 0;
            if (!$this->hasAudioVisualUploadFailurePolicyColumn && $createIfMissing) {
                $this->db->execute('ALTER TABLE test_instruments ADD COLUMN audio_visual_upload_failure_policy ENUM("continue", "retry_once", "block") NOT NULL DEFAULT "continue" AFTER control_mode');
                $this->hasAudioVisualUploadFailurePolicyColumn = true;
            }
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasAudioVisualUploadFailurePolicyColumn = false;
        }

        return $this->hasAudioVisualUploadFailurePolicyColumn;
    }

    private function hasAudioVisualRulesColumns(): bool
    {
        if ($this->hasAudioVisualRulesColumns !== null) return $this->hasAudioVisualRulesColumns;
        try {
            $row = $this->db->fetch("SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'test_instruments' AND COLUMN_NAME IN ('audio_visual_interruption_policy', 'audio_visual_voice_policy', 'audio_visual_permission_policy', 'audio_visual_quality_profile')");
            $this->hasAudioVisualRulesColumns = (int) ($row['total'] ?? 0) === 4;
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasAudioVisualRulesColumns = false;
        }
        return $this->hasAudioVisualRulesColumns;
    }

    private function hasShowQuestionNumbersColumn(bool $createIfMissing = false): bool
    {
        if ($this->hasShowQuestionNumbersColumn !== null) {
            return $this->hasShowQuestionNumbersColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME = 'show_question_numbers'
            ");

            $this->hasShowQuestionNumbersColumn = (int) ($row['total'] ?? 0) > 0;
            if (!$this->hasShowQuestionNumbersColumn && $createIfMissing) {
                $this->db->execute('
                    ALTER TABLE test_instruments
                    ADD COLUMN show_question_numbers TINYINT(1) NOT NULL DEFAULT 1
                    AFTER question_order_mode
                ');
                $this->hasShowQuestionNumbersColumn = true;
            }
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasShowQuestionNumbersColumn = false;
        }

        return $this->hasShowQuestionNumbersColumn;
    }

    private function hasAutoStartColumns(bool $createIfMissing = false): bool
    {
        if ($this->hasAutoStartColumns !== null) {
            return $this->hasAutoStartColumns;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME IN ('auto_start_enabled', 'auto_start_order')
            ");

            $this->hasAutoStartColumns = (int) ($row['total'] ?? 0) === 2;
            if (!$this->hasAutoStartColumns && $createIfMissing) {
                $this->db->execute('
                    ALTER TABLE test_instruments
                    ADD COLUMN IF NOT EXISTS auto_start_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER supervised_mode_enabled,
                    ADD COLUMN IF NOT EXISTS auto_start_order INT UNSIGNED NOT NULL DEFAULT 100 AFTER auto_start_enabled
                ');
                $this->hasAutoStartColumns = true;
            }
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasAutoStartColumns = false;
        }

        return $this->hasAutoStartColumns;
    }

    public function replaceScales(int $instrumentId, string $rawScales): void
    {
        $scales = $this->parseScaleLines($rawScales);

        $this->db->transaction(function (Database $db) use ($instrumentId, $scales): void {
            $db->execute('DELETE FROM test_scales WHERE instrument_id = ?', [$instrumentId]);

            foreach ($scales as $index => $scale) {
                $db->execute('
                    INSERT INTO test_scales (instrument_id, scale_key, name, description, sort_order)
                    VALUES (?, ?, ?, ?, ?)
                ', [
                    $instrumentId,
                    $scale['scale_key'],
                    $scale['name'],
                    $scale['description'],
                    ($index + 1) * 10,
                ]);
            }
        });
    }

    public function replaceItems(int $instrumentId, string $rawItems): void
    {
        $items = $this->parseItemLines($rawItems);
        $scaleRows = $this->scalesForInstrument($instrumentId);
        $scaleIdsByKey = [];
        foreach ($scaleRows as $scale) {
            $scaleIdsByKey[$scale['scale_key']] = (int) $scale['id'];
        }

        $this->db->transaction(function (Database $db) use ($instrumentId, $items, $scaleIdsByKey): void {
            $db->execute('DELETE FROM test_items WHERE instrument_id = ?', [$instrumentId]);

            foreach ($items as $index => $item) {
                $db->execute('
                    INSERT INTO test_items
                        (instrument_id, scale_id, item_key, prompt, image_url, item_type, options, scoring_key, reverse_scored, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ', [
                    $instrumentId,
                    $item['scale_key'] !== '' ? ($scaleIdsByKey[$item['scale_key']] ?? null) : null,
                    $item['item_key'],
                    $item['prompt'],
                    $item['image_url'] ?: null,
                    $item['item_type'],
                    $item['options'] ?: null,
                    $item['scoring_key'] ?: null,
                    (int) $item['reverse_scored'],
                    ($index + 1) * 10,
                    1,
                ]);
            }
        });
    }

    public function formatScales(array $scales): string
    {
        $lines = [];
        foreach ($scales as $scale) {
            $line = $scale['scale_key'] . '|' . $scale['name'];
            if (!empty($scale['description'])) {
                $line .= '|' . $scale['description'];
            }
            $lines[] = $line;
        }

        return implode(PHP_EOL, $lines);
    }

    public function formatItems(array $items): string
    {
        $lines = [];
        foreach ($items as $item) {
            $lines[] = implode('|', [
                $item['item_key'],
                $item['scale_key'] ?? '',
                $item['item_type'],
                (int) $item['reverse_scored'],
                $item['prompt'],
                $item['options'] ?? '',
                $item['scoring_key'] ?? '',
                $item['image_url'] ?? '',
            ]);
        }

        return implode(PHP_EOL, $lines);
    }

    public function parseScaleLines(string $rawScales): array
    {
        $scales = [];
        $lines = preg_split('/\R/', trim($rawScales));

        foreach ($lines ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 3));
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower($parts[0] ?? ''));
            $name = $parts[1] ?? '';

            if ($key === '' || $name === '') {
                continue;
            }

            $scales[] = [
                'scale_key' => $key,
                'name' => $name,
                'description' => $parts[2] ?? null,
            ];
        }

        return $scales;
    }

    public function parseItemLines(string $rawItems): array
    {
        $items = [];
        $lines = preg_split('/\R/', trim($rawItems));

        foreach ($lines ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 8));
            $itemKey = preg_replace('/[^a-z0-9_]/', '', strtolower($parts[0] ?? ''));
            $scaleKey = preg_replace('/[^a-z0-9_]/', '', strtolower($parts[1] ?? ''));
            $itemType = $parts[2] ?? 'likert';
            $reverseScored = (int) (($parts[3] ?? '0') === '1');
            $prompt = $parts[4] ?? '';

            if ($itemKey === '' || $prompt === '' || !isset(self::ITEM_TYPES[$itemType])) {
                continue;
            }

            $items[] = [
                'item_key' => $itemKey,
                'scale_key' => $scaleKey,
                'item_type' => $itemType,
                'reverse_scored' => $reverseScored,
                'prompt' => $prompt,
                'options' => $parts[5] ?? '',
                'scoring_key' => $parts[6] ?? '',
                'image_url' => $parts[7] ?? '',
            ];
        }

        return $items;
    }
}
