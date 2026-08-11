<?php
declare(strict_types=1);

final class PlatformSettingsController extends Controller
{
    private PlatformSettingsModel $settings;
    private VisualPresetAnalyzer $visualAnalyzer;

    public function __construct(?Template $view = null, ?PlatformSettingsModel $settings = null, ?VisualPresetAnalyzer $visualAnalyzer = null)
    {
        parent::__construct($view);
        $this->settings = $settings ?: new PlatformSettingsModel();
        $this->visualAnalyzer = $visualAnalyzer ?: new VisualPresetAnalyzer();
    }

    public function index(): void
    {
        require_permission('manage_platform_settings');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save();
        }

        $this->render('settings/index', [
            'title' => 'Configuracion | Metricatest',
            'currentPage' => 'settings',
            'databaseConfig' => load_config('database'),
            'integrationsConfig' => load_config('integrations'),
            'loginSettings' => $this->settings->loginSettings(),
            'designSettings' => $this->settings->designSettings(),
            'operationalSettings' => $this->settings->operationalSettings(),
        ]);
    }

    private function save(): void
    {
        verify_csrf();
        $section = $_POST['section'] ?? '';

        if ($section === 'database') {
            $this->saveDatabase();
            return;
        }

        if ($section === 'mail') {
            $this->saveMail();
            return;
        }

        if ($section === 'operational') {
            $this->saveOperational();
            return;
        }

        if ($section === 'login') {
            $this->saveLogin();
            return;
        }

        if ($section === 'login_preset') {
            $this->applyLoginPreset();
            return;
        }

        if ($section === 'design') {
            $this->saveDesign();
            return;
        }

        if ($section === 'design_preset') {
            $this->applyDesignPreset();
            return;
        }

        flash('danger', 'Seccion de configuracion no valida.');
    }

    private function saveOperational(): void
    {
        $retentionDays = max(1, min(3650, (int) ($_POST['test_evidence_retention_days'] ?? 365)));
        $maxSizeMb = max(50, min(5000, (int) ($_POST['test_evidence_max_size_mb'] ?? 500)));
        $chunkSizeMb = max(1, min(50, (int) ($_POST['test_evidence_chunk_size_mb'] ?? 10)));
        $this->settings->setMany([
            'test_evidence_retention_days' => (string) $retentionDays,
            'test_evidence_max_size_mb' => (string) $maxSizeMb,
            'test_evidence_chunk_size_mb' => (string) $chunkSizeMb,
        ]);
        flash('success', 'Politica audiovisual guardada correctamente.');
    }

    private function saveDatabase(): void
    {
        $current = load_config('database');
        $currentConnections = is_array($current['connections'] ?? null) ? $current['connections'] : [];
        $postedConnections = $_POST['connections'] ?? [];
        $connections = [];

        foreach (['core', 'tests'] as $key) {
            $posted = is_array($postedConnections[$key] ?? null) ? $postedConnections[$key] : [];
            $connections[$key] = [
                'database' => trim((string) ($posted['database'] ?? ($currentConnections[$key]['database'] ?? ''))),
            ];
        }

        $config = [
            'host' => trim($_POST['host'] ?? '127.0.0.1'),
            'port' => trim($_POST['port'] ?? '3306'),
            'username' => trim($_POST['username'] ?? ''),
            'password' => ($_POST['password'] ?? '') !== '' ? (string) $_POST['password'] : ($current['password'] ?? ''),
            'charset' => trim($_POST['charset'] ?? 'utf8mb4'),
            'socket' => trim($_POST['socket'] ?? ''),
            'connections' => $connections,
        ];

        if ($config['username'] === '' || $connections['core']['database'] === '' || $connections['tests']['database'] === '') {
            flash('danger', 'Usuario, base Core y base Tests son obligatorios.');
            return;
        }

        write_secure_config('database', $config);
        flash('success', 'Configuracion de base de datos cifrada y guardada.');
    }

    private function saveMail(): void
    {
        $current = load_config('integrations');
        $password = $_POST['mail_password'] ?? '';
        $current['mail'] = [
            'enabled' => isset($_POST['mail_enabled']),
            'host' => trim($_POST['mail_host'] ?? ''),
            'port' => (int) ($_POST['mail_port'] ?? 587),
            'username' => trim($_POST['mail_username'] ?? ''),
            'password' => $password !== '' ? $password : ($current['mail']['password'] ?? ''),
            'from_email' => trim($_POST['from_email'] ?? ''),
            'from_name' => trim($_POST['from_name'] ?? ''),
        ];

        write_secure_config('integrations', $current);
        flash('success', 'Configuracion de correo cifrada y guardada.');
    }

    private function saveLogin(): void
    {
        $settings = $this->settings->loginSettings();
        $data = [
            'login_title' => trim($_POST['login_title'] ?? $settings['login_title']),
            'login_title_font_size' => (string) $this->number($_POST['login_title_font_size'] ?? $settings['login_title_font_size'], 18, 64),
            'login_subtitle' => trim($_POST['login_subtitle'] ?? $settings['login_subtitle']),
            'login_subtitle_font_size' => (string) $this->number($_POST['login_subtitle_font_size'] ?? $settings['login_subtitle_font_size'], 12, 32),
            'login_form_position' => in_array($_POST['login_form_position'] ?? 'center', ['left', 'center', 'right'], true) ? $_POST['login_form_position'] : 'center',
            'login_background_color' => $this->color($_POST['login_background_color'] ?? $settings['login_background_color']),
            'login_form_background_color' => $this->color($_POST['login_form_background_color'] ?? $settings['login_form_background_color']),
            'login_title_color' => $this->color($_POST['login_title_color'] ?? $settings['login_title_color']),
            'login_subtitle_color' => $this->color($_POST['login_subtitle_color'] ?? $settings['login_subtitle_color']),
            'login_button_color' => $this->color($_POST['login_button_color'] ?? $settings['login_button_color']),
            'login_logo_path' => $settings['login_logo_path'],
            'login_background_path' => $settings['login_background_path'],
            'login_identifier' => in_array($_POST['login_identifier'] ?? $settings['login_identifier'], ['email', 'rut'], true)
                ? (string) ($_POST['login_identifier'] ?? $settings['login_identifier'])
                : 'email',
        ];

        $logo = $this->storeBrandAsset($_FILES['login_logo'] ?? [], 'logo');
        if ($logo !== '') {
            $data['login_logo_path'] = $logo;
        }

        $background = $this->storeBrandAsset($_FILES['login_background'] ?? [], 'background');
        if ($background !== '') {
            $data['login_background_path'] = $background;
        }

        $this->settings->setMany($data);
        flash('success', 'Configuracion visual del login guardada.');
    }

    private function saveDesign(): void
    {
        $settings = $this->settings->designSettings();
        $appFontUrl = $this->googleFontUrl(trim((string) ($_POST['app_font_url'] ?? $settings['app_font_url'])));
        $appFontFamily = $this->fontFamily($_POST['app_font_family'] ?? $settings['app_font_family'], $settings['app_font_family']);
        $bodyFontFamily = $this->fontFamily($_POST['font_body_family'] ?? $settings['font_body_family'], $settings['font_body_family']);
        $data = [
            'app_font_url' => $appFontUrl !== '' ? $appFontUrl : $settings['app_font_url'],
            'app_font_family' => $appFontFamily,
            'app_font_size' => (string) $this->number($_POST['app_font_size'] ?? $settings['app_font_size'], 12, 22),
            'font_heading_family' => $this->fontFamily($_POST['font_heading_family'] ?? $settings['font_heading_family'], $settings['font_heading_family']),
            'font_body_family' => $bodyFontFamily,
            'font_size_h1' => (string) $this->number($_POST['font_size_h1'] ?? $settings['font_size_h1'], 24, 72),
            'font_size_h2' => (string) $this->number($_POST['font_size_h2'] ?? $settings['font_size_h2'], 18, 48),
            'font_size_h3' => (string) $this->number($_POST['font_size_h3'] ?? $settings['font_size_h3'], 16, 36),
            'font_size_subtitle' => (string) $this->number($_POST['font_size_subtitle'] ?? $settings['font_size_subtitle'], 12, 30),
            'font_size_body' => (string) $this->number($_POST['font_size_body'] ?? $settings['font_size_body'], 12, 22),
            'font_size_small' => (string) $this->number($_POST['font_size_small'] ?? $settings['font_size_small'], 10, 16),
            'font_size_nav' => (string) $this->number($_POST['font_size_nav'] ?? $settings['font_size_nav'], 12, 20),
            'font_size_button' => (string) $this->number($_POST['font_size_button'] ?? $settings['font_size_button'], 12, 22),
            'font_size_table' => (string) $this->number($_POST['font_size_table'] ?? $settings['font_size_table'], 11, 18),
            'app_primary_color' => $this->color($_POST['app_primary_color'] ?? $settings['app_primary_color']),
            'portal_text_color' => $this->color($_POST['portal_text_color'] ?? $settings['portal_text_color']),
            'topbar_icon_path' => $settings['topbar_icon_path'],
            'topbar_name' => trim((string) ($_POST['topbar_name'] ?? $settings['topbar_name'])) !== ''
                ? mb_substr(trim((string) ($_POST['topbar_name'] ?? $settings['topbar_name'])), 0, 80)
                : $settings['topbar_name'],
            'topbar_background_color' => $this->color($_POST['topbar_background_color'] ?? $settings['topbar_background_color']),
            'topbar_menu_background_color' => $this->color($_POST['topbar_menu_background_color'] ?? $settings['topbar_menu_background_color']),
            'topbar_menu_button_color' => $this->color($_POST['topbar_menu_button_color'] ?? $settings['topbar_menu_button_color']),
            'topbar_logo_position' => 'start',
            'layout_background_color' => $this->color($_POST['layout_background_color'] ?? $settings['layout_background_color']),
            'card_header_background_color' => $this->color($_POST['card_header_background_color'] ?? $settings['card_header_background_color']),
            'card_content_background_color' => $this->color($_POST['card_content_background_color'] ?? $settings['card_content_background_color']),
            'card_text_color' => $this->color($_POST['card_text_color'] ?? $settings['card_text_color']),
            'button_background_color' => $this->color($_POST['button_background_color'] ?? $settings['button_background_color']),
            'button_text_color' => $this->color($_POST['button_text_color'] ?? $settings['button_text_color']),
            'table_border_color' => $this->color($_POST['table_border_color'] ?? $settings['table_border_color']),
            'table_header_background_color' => $this->color($_POST['table_header_background_color'] ?? $settings['table_header_background_color']),
            'table_header_text_color' => $this->color($_POST['table_header_text_color'] ?? $settings['table_header_text_color']),
            'table_body_background_color' => $this->color($_POST['table_body_background_color'] ?? $settings['table_body_background_color']),
            'table_body_text_color' => $this->color($_POST['table_body_text_color'] ?? $settings['table_body_text_color']),
        ];

        $topbarIcon = $this->storeBrandAsset($_FILES['topbar_icon'] ?? [], 'topbar');
        if ($topbarIcon !== '') {
            $data['topbar_icon_path'] = $topbarIcon;
        }

        $this->settings->setMany($data);
        flash('success', 'Diseno general de plataforma guardado.');
    }

    private function applyLoginPreset(): void
    {
        $asset = $this->storeBrandAsset($_FILES['login_reference'] ?? [], 'login_reference');
        if ($asset === '') {
            return;
        }

        try {
            $preset = $this->visualAnalyzer->analyzeLogin(PUBLIC_PATH . '/' . $asset, $asset);
            $this->settings->setMany($preset);
            flash('success', 'Imagen de login analizada y configuracion aplicada.');
        } catch (Throwable $exception) {
            flash('danger', 'No se pudo analizar la imagen de login: ' . $exception->getMessage());
        }
    }

    private function applyDesignPreset(): void
    {
        $asset = $this->storeBrandAsset($_FILES['design_reference'] ?? [], 'design_reference');
        if ($asset === '') {
            return;
        }

        try {
            $preset = $this->visualAnalyzer->analyzeDesign(PUBLIC_PATH . '/' . $asset);
            $preset['design_reference_path'] = $asset;
            $this->settings->setMany($preset);
            flash('success', 'Imagen de plataforma analizada y configuracion aplicada.');
        } catch (Throwable $exception) {
            flash('danger', 'No se pudo analizar la imagen de plataforma: ' . $exception->getMessage());
        }
    }

    private function color(string $value): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '#1a73e8';
    }

    private function number($value, int $min, int $max): int
    {
        $number = is_numeric($value) ? (int) $value : $min;
        return max($min, min($max, $number));
    }

    private function googleFontUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'fonts.googleapis.com') {
            return '';
        }

        return $url;
    }

    private function fontFamily($value, string $fallback): string
    {
        $family = trim((string) $value);
        if ($family === '') {
            return $fallback;
        }

        $family = preg_replace('/[\r\n;{}<>]/', '', $family) ?? '';
        $family = trim($family);

        return $family !== '' ? mb_substr($family, 0, 255) : $fallback;
    }

    private function storeBrandAsset(array $file, string $prefix): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            flash('danger', 'No se pudo cargar una imagen de marca.');
            return '';
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset($allowed[$mime]) || (int) ($file['size'] ?? 0) > 3 * 1024 * 1024) {
            flash('danger', 'Las imagenes de marca deben ser JPG, PNG o WEBP y pesar maximo 3 MB.');
            return '';
        }

        $dir = PUBLIC_PATH . '/uploads/branding';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            flash('danger', 'No se pudo guardar la imagen de marca.');
            return '';
        }

        return 'uploads/branding/' . $filename;
    }
}
