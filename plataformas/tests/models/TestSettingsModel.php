<?php
declare(strict_types=1);

final class TestSettingsModel
{
    private Database $db;
    private static ?array $settingsCache = null;
    private ?bool $hasSettingsTable = null;

    public const DEFAULTS = [
        'entry_confirm_title' => 'Antes de comenzar',
        'entry_confirm_message' => "Entraras a contestar {test_name}.\n\nNo podras salir sin contestar todas las preguntas o hasta que se acabe el tiempo.\nNo podras mantener inactividad durante la evaluacion.\nNo debes recibir ayuda de otras paginas, aplicaciones o ventanas.\n{activity_tracking_notice}\n\nConfirma si deseas ingresar a la evaluacion.",
        'entry_confirm_button' => 'Ingresar',
        'entry_cancel_button' => 'Cancelar',
        'activity_tracking_notice' => 'La plataforma registrara senales de atencion, como inactividad, cambio de pestana, perdida de foco o salida de pantalla completa.',
        'activity_tracking_disabled_notice' => 'La plataforma puede controlar el avance y el tiempo disponible durante la evaluacion.',
        'exit_confirm_title' => 'Evaluacion en curso',
        'exit_confirm_message' => "Si sales ahora, guardaremos tus respuestas y conservaras el tiempo restante para continuar despues.\n\nDeseas guardar y salir?",
        'exit_continue_button' => 'Continuar evaluacion',
        'exit_save_exit_button' => 'Guardar y salir',
    ];

    public const RANKING_CONFIG_KEY = 'ranking_config';
    public const OFFICIAL_RANKING_PRESET_KEY = 'official_matrix';

    private const LEGACY_VALUES = [
        'exit_confirm_message' => [
            "No puedes realizar esta accion mientras estas contestando la evaluacion.\n\nDeseas continuar en la evaluacion?",
        ],
        'exit_continue_button' => [
            'Si, continuar',
        ],
        'exit_save_exit_button' => [
            'No, finalizar y salir',
            'No, guardar y salir',
        ],
    ];

    private ?bool $hasRankingPresetsTable = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('tests');
    }

    public function all(): array
    {
        if (self::$settingsCache !== null) {
            return self::$settingsCache;
        }

        $settings = [];
        if ($this->hasSettingsTable(true)) {
            $rows = $this->db->fetchAll('SELECT setting_key, setting_value FROM test_settings');
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = (string) $row['setting_value'];
            }
        }

        self::$settingsCache = $settings;
        return self::$settingsCache;
    }

    public function evaluationMessages(): array
    {
        $messages = array_merge(self::DEFAULTS, array_intersect_key($this->all(), self::DEFAULTS));

        foreach (self::LEGACY_VALUES as $key => $legacyValues) {
            if (!isset($messages[$key])) {
                continue;
            }

            $currentValue = $this->normalizeText((string) $messages[$key]);
            foreach ($legacyValues as $legacyValue) {
                if ($currentValue === $this->normalizeText($legacyValue)) {
                    $messages[$key] = self::DEFAULTS[$key];
                    break;
                }
            }
        }

        return $messages;
    }

    public function setMany(array $settings): void
    {
        if (!$this->hasSettingsTable(true)) {
            return;
        }

        $allowedSettings = array_intersect_key($settings, self::DEFAULTS);
        $this->db->transaction(function (Database $db) use ($allowedSettings): void {
            foreach ($allowedSettings as $key => $value) {
                $db->execute('
                    INSERT INTO test_settings (setting_key, setting_value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ', [$key, $value]);
            }
        });

        self::$settingsCache = null;
    }

    public function rankingConfig(): array
    {
        $settings = $this->all();
        $json = (string) ($settings[self::RANKING_CONFIG_KEY] ?? '');
        if ($json === '') {
            return [];
        }

        $config = json_decode($json, true);
        return is_array($config) ? $config : [];
    }

    public function setRankingConfig(array $config): void
    {
        if (!$this->hasSettingsTable(true)) {
            return;
        }

        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        $this->db->execute('
            INSERT INTO test_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ', [self::RANKING_CONFIG_KEY, $json]);

        self::$settingsCache = null;
    }

    public function resetRankingConfig(): void
    {
        if (!$this->hasSettingsTable(true)) {
            return;
        }

        $this->db->execute('DELETE FROM test_settings WHERE setting_key = ?', [self::RANKING_CONFIG_KEY]);
        self::$settingsCache = null;
    }

    public function rankingPresetOptions(array $officialConfig): array
    {
        $official = $this->ensureOfficialRankingPreset($officialConfig);
        $presets = [$official];
        if (!$this->hasRankingPresetsTable()) {
            return $presets;
        }

        $rows = $this->db->fetchAll('
            SELECT *
            FROM test_ranking_presets
            WHERE is_official = 0
            ORDER BY name ASC, id ASC
        ');

        foreach ($rows as $row) {
            $presets[] = $this->rankingPresetPayload($row);
        }

        return $presets;
    }

    public function rankingPresetById(int $id, array $officialConfig): ?array
    {
        if ($id <= 0) {
            return $this->ensureOfficialRankingPreset($officialConfig);
        }

        if (!$this->hasRankingPresetsTable()) {
            return null;
        }

        $row = $this->db->fetch('SELECT * FROM test_ranking_presets WHERE id = ? LIMIT 1', [$id]);
        return $row ? $this->rankingPresetPayload($row) : null;
    }

    public function rankingPresetByKey(string $presetKey, array $officialConfig): ?array
    {
        if ($presetKey === self::OFFICIAL_RANKING_PRESET_KEY) {
            return $this->ensureOfficialRankingPreset($officialConfig);
        }

        if (!$this->hasRankingPresetsTable()) {
            return null;
        }

        $row = $this->db->fetch('SELECT * FROM test_ranking_presets WHERE preset_key = ? LIMIT 1', [$presetKey]);
        return $row ? $this->rankingPresetPayload($row) : null;
    }

    public function createRankingPreset(string $name, array $config, int $userId): ?int
    {
        if (!$this->hasRankingPresetsTable()) {
            return null;
        }

        $name = $this->cleanPresetName($name);
        if ($name === '') {
            throw new InvalidArgumentException('Ingresa un nombre para guardar la configuracion.');
        }

        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo preparar la configuracion.');
        }

        return $this->db->insert('
            INSERT INTO test_ranking_presets (preset_key, name, config_json, is_official, created_by, updated_by)
            VALUES (?, ?, ?, 0, ?, ?)
        ', [
            $this->rankingPresetKey($name),
            $name,
            $json,
            $userId > 0 ? $userId : null,
            $userId > 0 ? $userId : null,
        ]);
    }

    public function updateRankingPreset(int $id, string $name, array $config, int $userId): void
    {
        if (!$this->hasRankingPresetsTable()) {
            return;
        }

        $preset = $this->rankingPresetById($id, []);
        if (!$preset || !empty($preset['is_official'])) {
            throw new InvalidArgumentException('La matriz oficial no se puede editar.');
        }

        $name = $this->cleanPresetName($name);
        if ($name === '') {
            throw new InvalidArgumentException('Ingresa un nombre para actualizar la configuracion.');
        }

        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo preparar la configuracion.');
        }

        $this->db->execute('
            UPDATE test_ranking_presets
            SET name = ?, config_json = ?, updated_by = ?
            WHERE id = ? AND is_official = 0
        ', [$name, $json, $userId > 0 ? $userId : null, $id]);
    }

    public function deleteRankingPreset(int $id): void
    {
        if (!$this->hasRankingPresetsTable()) {
            return;
        }

        $preset = $this->rankingPresetById($id, []);
        if (!$preset || !empty($preset['is_official'])) {
            throw new InvalidArgumentException('La matriz oficial no se puede eliminar.');
        }

        $this->db->execute('DELETE FROM test_ranking_presets WHERE id = ? AND is_official = 0', [$id]);
    }

    public function hasRankingPresetsStorage(): bool
    {
        return $this->hasRankingPresetsTable();
    }

    private function hasSettingsTable(bool $createIfMissing = false): bool
    {
        if ($this->hasSettingsTable !== null) {
            return $this->hasSettingsTable;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_settings'
            ");
            $this->hasSettingsTable = (int) ($row['total'] ?? 0) > 0;
            if (!$this->hasSettingsTable && $createIfMissing) {
                $this->db->execute('
                    CREATE TABLE IF NOT EXISTS test_settings (
                        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
                        setting_value TEXT NULL,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ');
                $this->hasSettingsTable = true;
            }
        } catch (Throwable $exception) {
            error_log('Test settings schema check error: ' . $exception->getMessage());
            $this->hasSettingsTable = false;
        }

        return $this->hasSettingsTable;
    }

    private function hasRankingPresetsTable(): bool
    {
        if ($this->hasRankingPresetsTable !== null) {
            return $this->hasRankingPresetsTable;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_ranking_presets'
            ");
            $this->hasRankingPresetsTable = (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Ranking presets schema check error: ' . $exception->getMessage());
            $this->hasRankingPresetsTable = false;
        }

        return $this->hasRankingPresetsTable;
    }

    private function ensureOfficialRankingPreset(array $officialConfig): array
    {
        $payload = [
            'id' => 0,
            'preset_key' => self::OFFICIAL_RANKING_PRESET_KEY,
            'name' => 'Matriz oficial',
            'config' => $officialConfig,
            'is_official' => true,
        ];

        if (!$this->hasRankingPresetsTable()) {
            return $payload;
        }

        $json = json_encode($officialConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return $payload;
        }

        $this->db->execute('
            INSERT INTO test_ranking_presets (preset_key, name, config_json, is_official)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE name = VALUES(name), config_json = VALUES(config_json), is_official = 1
        ', [self::OFFICIAL_RANKING_PRESET_KEY, 'Matriz oficial', $json]);

        $row = $this->db->fetch('SELECT * FROM test_ranking_presets WHERE preset_key = ? LIMIT 1', [self::OFFICIAL_RANKING_PRESET_KEY]);
        return $row ? $this->rankingPresetPayload($row) : $payload;
    }

    private function rankingPresetPayload(array $row): array
    {
        $config = json_decode((string) ($row['config_json'] ?? ''), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'preset_key' => (string) ($row['preset_key'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'config' => is_array($config) ? $config : [],
            'is_official' => (int) ($row['is_official'] ?? 0) === 1,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function cleanPresetName(string $name): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $name)), 0, 150);
    }

    private function rankingPresetKey(string $name): string
    {
        $base = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name);
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '_', $base), '_');
        $base = $base !== '' ? mb_substr($base, 0, 48) : 'configuracion';

        return $base . '_' . bin2hex(random_bytes(4));
    }

    private function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
