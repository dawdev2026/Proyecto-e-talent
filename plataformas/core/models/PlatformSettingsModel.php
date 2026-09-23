<?php
declare(strict_types=1);

final class PlatformSettingsModel
{
    private Database $db;
    private static ?array $settingsCache = null;

    public const LOGIN_DEFAULTS = [
        'login_title' => 'e-talent',
        'login_title_font_size' => '40',
        'login_subtitle' => 'Evaluaciones y gestion de talento.',
        'login_subtitle_font_size' => '16',
        'login_form_position' => 'right',
        'login_background_color' => '#F6F7F9',
        'login_form_background_color' => '#FFFFFF',
        'login_title_color' => '#111827',
        'login_subtitle_color' => '#667085',
        'login_button_color' => '#2563EB',
        'login_logo_path' => '',
        'login_background_path' => '',
        'login_identifier' => 'email',
        'login_password_recovery_enabled' => '1',
        'login_two_step_enabled' => '0',
        'login_two_step_expiration_minutes' => '5',
        'login_two_step_max_attempts' => '5',
    ];

    public const DESIGN_DEFAULTS = [
        'html_title' => 'e-talent',
        'html_favicon_path' => '',
        'html_meta_description' => 'Plataforma e-talent para evaluaciones, procesos y reportes de seleccion.',
        'app_font_url' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
        'app_font_family' => 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'app_font_size' => '16',
        'font_heading_family' => 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'font_body_family' => 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'font_size_h1' => '40',
        'font_size_h2' => '24',
        'font_size_h3' => '20',
        'font_size_subtitle' => '18',
        'font_size_body' => '16',
        'font_size_small' => '13',
        'font_size_nav' => '15',
        'font_size_button' => '16',
        'font_size_table' => '14',
        'app_primary_color' => '#2563EB',
        'portal_text_color' => '#111827',
        'topbar_icon_path' => '',
        'topbar_name' => 'e-talent',
        'topbar_background_color' => '#ffffff',
        'topbar_menu_background_color' => '#ffffff',
        'topbar_menu_button_color' => '#2563EB',
        'topbar_height' => '68',
        'topbar_logo_position' => 'start',
        'topbar_logo_width' => '42',
        'sidebar_background_color' => '#FFFFFF',
        'sidebar_text_color' => '#344054',
        'sidebar_icon_color' => '#98A2B3',
        'sidebar_active_background_color' => '#EEF2FF',
        'sidebar_active_text_color' => '#2146D0',
        'sidebar_border_color' => '#E4E7EC',
        'sidebar_width' => '280',
        'layout_background_color' => '#F6F7F9',
        'card_header_background_color' => '#F2F4F7',
        'card_content_background_color' => '#ffffff',
        'card_text_color' => '#111827',
        'button_background_color' => '#2563EB',
        'button_text_color' => '#ffffff',
        'table_border_color' => '#D0D5DD',
        'table_header_background_color' => '#F2F4F7',
        'table_header_text_color' => '#111827',
        'table_body_background_color' => '#ffffff',
        'table_body_text_color' => '#111827',
    ];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database();
    }

    public function all(): array
    {
        if (self::$settingsCache !== null) {
            return self::$settingsCache;
        }

        $rows = $this->db->fetchAll('SELECT setting_key, setting_value FROM platform_settings');
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = (string) $row['setting_value'];
        }

        self::$settingsCache = $settings;
        return self::$settingsCache;
    }

    public function loginSettings(?int $companyId = null): array
    {
        return $this->resolve(self::LOGIN_DEFAULTS, $companyId);
    }

    public function designSettings(?int $companyId = null): array
    {
        return $this->resolve(self::DESIGN_DEFAULTS, $companyId);
    }

    public function operationalSettings(): array
    {
        $settings = $this->all();
        return [
            'test_evidence_retention_days' => max(1, min(3650, (int) ($settings['test_evidence_retention_days'] ?? 365))),
            'test_evidence_max_size_mb' => max(50, min(5000, (int) ($settings['test_evidence_max_size_mb'] ?? 500))),
            'test_evidence_chunk_size_mb' => max(1, min(50, (int) ($settings['test_evidence_chunk_size_mb'] ?? 10))),
        ];
    }

    /**
     * Resolves the shared AI integration without exposing its credential to views.
     * The secret is returned only to backend services that explicitly request it.
     */
    public function aiSettings(?array $integrations = null, bool $includeSecret = false): array
    {
        $integrations = $integrations ?? load_config('integrations');
        $configured = $integrations['interviews_ai'] ?? ($integrations['ai'] ?? []);
        $configured = is_array($configured) ? $configured : [];

        $apiKey = trim((string) ($configured['api_key'] ?? ''));
        $baseUrl = rtrim(trim((string) ($configured['base_url'] ?? 'https://api.openai.com/v1')), '/');
        if (!preg_match('/^https:\/\//i', $baseUrl)) {
            $baseUrl = 'https://api.openai.com/v1';
        }

        return [
            'enabled' => $this->settingBool($configured['enabled'] ?? false),
            'provider' => trim((string) ($configured['provider'] ?? 'openai_compatible')) ?: 'openai_compatible',
            'base_url' => $baseUrl,
            'api_key' => $includeSecret ? $apiKey : '',
            'has_api_key' => $apiKey !== '',
            'model' => trim((string) ($configured['model'] ?? '')),
            'timeout_seconds' => max(10, min(180, (int) ($configured['timeout_seconds'] ?? 45))),
            'prompt_version' => trim((string) ($configured['prompt_version'] ?? 'e_talent-ai-v1')) ?: 'e_talent-ai-v1',
        ];
    }

    public function setMany(array $settings): void
    {
        $this->db->transaction(function (Database $db) use ($settings): void {
            foreach ($settings as $key => $value) {
                $db->execute('
                    INSERT INTO platform_settings (setting_key, setting_value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ', [$key, $value]);
            }
        });

        self::$settingsCache = null;
    }

    public function setManyForCompany(int $companyId, array $settings): void
    {
        (new CompanyBrandingModel($this->db))->setMany($companyId, $settings);
    }

    private function resolve(array $defaults, ?int $companyId): array
    {
        $settings = array_merge($defaults, array_intersect_key($this->all(), $defaults));
        if (!$companyId || $companyId <= 0) {
            return $settings;
        }

        $companySettings = (new CompanyBrandingModel($this->db))->allForCompany($companyId);
        return array_merge($settings, array_intersect_key($companySettings, $defaults));
    }

    private function settingBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }
}
