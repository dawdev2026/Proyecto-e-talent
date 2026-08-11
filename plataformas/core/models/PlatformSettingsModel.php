<?php
declare(strict_types=1);

final class PlatformSettingsModel
{
    private Database $db;
    private static ?array $settingsCache = null;

    public const LOGIN_DEFAULTS = [
        'login_title' => 'Metricatest',
        'login_title_font_size' => '32',
        'login_subtitle' => 'Evaluaciones y gestion institucional.',
        'login_subtitle_font_size' => '16',
        'login_form_position' => 'right',
        'login_background_color' => '#00283C',
        'login_form_background_color' => '#FFFFFF',
        'login_title_color' => '#2E2E2E',
        'login_subtitle_color' => '#6E6E6E',
        'login_button_color' => '#92BE2E',
        'login_logo_path' => 'uploads/branding/metricatest_logo_clean_20260708.png',
        'login_background_path' => 'uploads/branding/metricatest_login_bg_20260708.png',
        'login_identifier' => 'email',
    ];

    public const DESIGN_DEFAULTS = [
        'html_title' => 'Metricatest',
        'html_favicon_path' => 'uploads/branding/metricatest_icon_20260708.png',
        'html_meta_description' => 'Plataforma Metricatest para evaluaciones, procesos y reportes de seleccion.',
        'app_font_url' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Poppins:wght@400;500;600;700&display=swap',
        'app_font_family' => 'Poppins, Inter, Roboto, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'app_font_size' => '16',
        'font_heading_family' => 'Montserrat, Poppins, Inter, Roboto, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'font_body_family' => 'Poppins, Inter, Roboto, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'font_size_h1' => '40',
        'font_size_h2' => '24',
        'font_size_h3' => '20',
        'font_size_subtitle' => '18',
        'font_size_body' => '16',
        'font_size_small' => '13',
        'font_size_nav' => '15',
        'font_size_button' => '16',
        'font_size_table' => '14',
        'app_primary_color' => '#00283C',
        'portal_text_color' => '#2E2E2E',
        'topbar_icon_path' => 'uploads/branding/metricatest_icon_20260708.png',
        'topbar_name' => 'Metricatest',
        'topbar_background_color' => '#ffffff',
        'topbar_menu_background_color' => '#ffffff',
        'topbar_menu_button_color' => '#92BE2E',
        'topbar_height' => '68',
        'topbar_logo_position' => 'start',
        'topbar_logo_width' => '42',
        'layout_background_color' => '#F6F8F4',
        'card_header_background_color' => '#F6F8F4',
        'card_content_background_color' => '#ffffff',
        'card_text_color' => '#2E2E2E',
        'button_background_color' => '#92BE2E',
        'button_text_color' => '#2E2E2E',
        'table_border_color' => '#D9D9D9',
        'table_header_background_color' => '#F6F8F4',
        'table_header_text_color' => '#00283C',
        'table_body_background_color' => '#ffffff',
        'table_body_text_color' => '#2E2E2E',
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

    public function loginSettings(): array
    {
        return array_merge(self::LOGIN_DEFAULTS, array_intersect_key($this->all(), self::LOGIN_DEFAULTS));
    }

    public function designSettings(): array
    {
        return array_merge(self::DESIGN_DEFAULTS, array_intersect_key($this->all(), self::DESIGN_DEFAULTS));
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
}
