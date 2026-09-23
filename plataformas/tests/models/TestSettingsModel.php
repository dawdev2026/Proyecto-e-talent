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
        'incomplete_confirm_title' => 'Evaluacion incompleta',
        'incomplete_confirm_message' => 'Aun quedan preguntas sin responder. Deseas contestarlas antes de finalizar?',
        'incomplete_confirm_button' => 'Si, contestar pendientes',
        'incomplete_cancel_button' => 'No, guardar y finalizar',
        'expired_message' => 'El tiempo finalizo. Se guardaron las respuestas registradas hasta este momento.',
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
    private ?bool $hasCompanyRankingAssignmentsTable = null;

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
            if (!$allowedSettings) {
                return;
            }
            $params = [];
            foreach ($allowedSettings as $key => $value) array_push($params, (string) $key, (string) $value);
            $db->execute(
                'INSERT INTO test_settings (setting_key, setting_value) VALUES ' . implode(', ', array_fill(0, count($allowedSettings), '(?, ?)')) . ' ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                $params
            );
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

    /**
     * Returns the ranking configuration assigned to a company, falling back
     * to the supplied configuration when the company has no assignment.
     */
    public function rankingConfigForCompany(int $companyId, array $fallbackConfig): array
    {
        $fallbackConfig = is_array($fallbackConfig) ? $fallbackConfig : [];
        if ($companyId <= 0 || !$this->hasCompanyRankingAssignmentsTable() || !$this->hasRankingPresetsTable()) {
            return $fallbackConfig;
        }

        $row = $this->db->fetch('
            SELECT p.config_json
            FROM test_ranking_company_assignments a
            INNER JOIN test_ranking_presets p ON p.id = a.preset_id
            WHERE a.company_id = ? AND a.is_active = 1
            LIMIT 1
        ', [$companyId]);
        if (!$row) {
            return $fallbackConfig;
        }

        $config = json_decode((string) ($row['config_json'] ?? ''), true);
        return is_array($config) ? $config : $fallbackConfig;
    }

    public function rankingCompanyAssignment(int $companyId): ?array
    {
        if ($companyId <= 0 || !$this->hasCompanyRankingAssignmentsTable()) {
            return null;
        }

        $row = $this->db->fetch('
            SELECT a.company_id, a.preset_id, a.assigned_at, p.preset_key, p.name AS preset_name,
                   p.is_official
            FROM test_ranking_company_assignments a
            LEFT JOIN test_ranking_presets p ON p.id = a.preset_id
            WHERE a.company_id = ? AND a.is_active = 1
            LIMIT 1
        ', [$companyId]);

        if (!$row) {
            return null;
        }

        return [
            'company_id' => (int) ($row['company_id'] ?? 0),
            'preset_id' => (int) ($row['preset_id'] ?? 0),
            'preset_key' => (string) ($row['preset_key'] ?? ''),
            'preset_name' => (string) ($row['preset_name'] ?? ''),
            'is_official' => (int) ($row['is_official'] ?? 0) === 1,
            'assigned_at' => (string) ($row['assigned_at'] ?? ''),
        ];
    }

    public function rankingCompanyAssignments(): array
    {
        if (!$this->hasCompanyRankingAssignmentsTable()) {
            return [];
        }

        return $this->db->fetchAll('
            SELECT a.company_id, a.preset_id, a.assigned_at, p.preset_key, p.name AS preset_name,
                   p.is_official
            FROM test_ranking_company_assignments a
            LEFT JOIN test_ranking_presets p ON p.id = a.preset_id
            WHERE a.is_active = 1
            ORDER BY a.company_id ASC
        ');
    }

    public function saveRankingCompanyAssignment(int $companyId, ?int $presetId, int $userId): void
    {
        if ($companyId <= 0 || !$this->hasCompanyRankingAssignmentsTable()) {
            throw new InvalidArgumentException('No se pudo identificar la empresa para asignar la matriz.');
        }

        if ($presetId !== null && $presetId > 0) {
            $preset = $this->db->fetch('SELECT id FROM test_ranking_presets WHERE id = ? LIMIT 1', [$presetId]);
            if (!$preset) {
                throw new InvalidArgumentException('La configuracion de ranking seleccionada no existe.');
            }
        }

        if ($presetId === null || $presetId <= 0) {
            $this->db->execute('DELETE FROM test_ranking_company_assignments WHERE company_id = ?', [$companyId]);
            return;
        }

        $this->db->execute('
            INSERT INTO test_ranking_company_assignments (company_id, preset_id, assigned_by, is_active)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE preset_id = VALUES(preset_id), assigned_by = VALUES(assigned_by),
                                    is_active = 1, assigned_at = CURRENT_TIMESTAMP
        ', [$companyId, $presetId, $userId > 0 ? $userId : null]);
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

    private function hasCompanyRankingAssignmentsTable(): bool
    {
        if ($this->hasCompanyRankingAssignmentsTable !== null) {
            return $this->hasCompanyRankingAssignmentsTable;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_ranking_company_assignments'
            ");
            $this->hasCompanyRankingAssignmentsTable = (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Company ranking assignment schema check error: ' . $exception->getMessage());
            $this->hasCompanyRankingAssignmentsTable = false;
        }

        return $this->hasCompanyRankingAssignmentsTable;
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
