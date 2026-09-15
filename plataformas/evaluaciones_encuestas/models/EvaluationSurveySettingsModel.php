<?php
declare(strict_types=1);

final class EvaluationSurveySettingsModel
{
    public const AI_PDF_PRESETS = [
        60 => ['max_pdf_text_chars' => 60000, 'label' => '60 páginas — recomendado'],
        100 => ['max_pdf_text_chars' => 100000, 'label' => '100 páginas — equilibrio'],
        150 => ['max_pdf_text_chars' => 150000, 'label' => '150 páginas — cobertura amplia'],
    ];

    public const AI_DEFAULTS = [
        'enabled' => '1', 'import_enabled' => '1', 'generation_enabled' => '1',
        'require_review' => '1', 'max_pdf_mb' => '20', 'max_pdf_pages' => '60',
        'max_pdf_text_chars' => '60000', 'max_questions' => '30', 'default_difficulty' => 'medium',
    ];
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('evaluaciones_encuestas');
    }

    public function get(string $key, string $default = ''): string
    {
        $row = $this->db->fetch('SELECT setting_value FROM evaluation_survey_settings WHERE setting_key = ? LIMIT 1', [$key]);
        return $row ? (string) ($row['setting_value'] ?? $default) : $default;
    }

    public function set(string $key, string $value): void
    {
        $this->db->execute('
            INSERT INTO evaluation_survey_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ', [$key, $value]);
    }

    public function aiSettings(): array
    {
        $settings = self::AI_DEFAULTS;
        $keys = array_map(static fn(string $key): string => 'ai_' . $key, array_keys($settings));
        if ($keys) {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            foreach ($this->db->fetchAll('SELECT setting_key, setting_value FROM evaluation_survey_settings WHERE setting_key IN (' . $placeholders . ')', $keys) as $row) {
                $key = substr((string) $row['setting_key'], 3);
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = (string) ($row['setting_value'] ?? $settings[$key]);
                }
            }
        }
        $settings['enabled'] = $this->boolValue($settings['enabled']);
        $settings['import_enabled'] = $this->boolValue($settings['import_enabled']);
        $settings['generation_enabled'] = $this->boolValue($settings['generation_enabled']);
        $settings['require_review'] = $this->boolValue($settings['require_review']);
        $settings['max_pdf_mb'] = max(1, min(200, (int) $settings['max_pdf_mb']));
        $settings['max_pdf_pages'] = $this->normalizePdfPages((int) $settings['max_pdf_pages']);
        $settings['max_pdf_text_chars'] = self::AI_PDF_PRESETS[$settings['max_pdf_pages']]['max_pdf_text_chars'];
        $settings['max_questions'] = max(1, min(100, (int) $settings['max_questions']));
        $settings['default_difficulty'] = in_array($settings['default_difficulty'], ['easy', 'medium', 'hard'], true) ? $settings['default_difficulty'] : 'medium';
        return $settings;
    }

    public function setAiSettings(array $settings): void
    {
        $pages = $this->normalizePdfPages((int) ($settings['max_pdf_pages'] ?? 60));
        $this->setMany([
            'ai_enabled' => !empty($settings['enabled']) ? '1' : '0',
            'ai_import_enabled' => !empty($settings['import_enabled']) ? '1' : '0',
            'ai_generation_enabled' => !empty($settings['generation_enabled']) ? '1' : '0',
            'ai_require_review' => !empty($settings['require_review']) ? '1' : '0',
            'ai_max_pdf_mb' => (string) max(1, min(200, (int) ($settings['max_pdf_mb'] ?? 20))),
            'ai_max_pdf_pages' => (string) $pages,
            'ai_max_pdf_text_chars' => (string) self::AI_PDF_PRESETS[$pages]['max_pdf_text_chars'],
            'ai_max_questions' => (string) max(1, min(100, (int) ($settings['max_questions'] ?? 30))),
            'ai_default_difficulty' => in_array(($settings['default_difficulty'] ?? 'medium'), ['easy', 'medium', 'hard'], true) ? (string) $settings['default_difficulty'] : 'medium',
        ]);
    }

    private function setMany(array $values): void
    {
        if (!$values) return;
        $params = [];
        foreach ($values as $key => $value) array_push($params, (string) $key, (string) $value);
        $this->db->execute(
            'INSERT INTO evaluation_survey_settings (setting_key, setting_value) VALUES ' . implode(', ', array_fill(0, count($values), '(?, ?)')) . ' ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            $params
        );
    }

    private function boolValue(string $value): string
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    }

    private function normalizePdfPages(int $pages): int
    {
        return array_key_exists($pages, self::AI_PDF_PRESETS) ? $pages : 60;
    }
}
