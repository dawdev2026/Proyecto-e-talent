<?php
declare(strict_types=1);

final class ProfileModel
{
    private Database $db;

    public const PERMISSIONS = [
        'manage_profiles' => 'Administrar perfiles',
        'manage_platform_settings' => 'Administrar configuracion de plataforma',
        'manage_company_branding' => 'Administrar identidad visual de la empresa',
        'manage_user_fields' => 'Administrar campos de usuario',
        'manage_company_user_fields' => 'Administrar campos de usuario de la empresa',
        'manage_users' => 'Administrar usuarios',
        'manage_companies' => 'Administrar empresas',
        'manage_tests' => 'Administrar evaluaciones',
        'manage_ranking_presets' => 'Administrar configuraciones de ranking',
        'assign_tests' => 'Asignar evaluaciones',
        'manage_test_processes' => 'Administrar procesos de evaluacion',
        'view_test_process_progress' => 'Ver avance de procesos',
        'view_test_process_dashboard' => 'Ver Dashboard Avance',
        'view_test_process_results' => 'Ver resultados de procesos',
        'manage_process_session_actions' => 'Administrar acciones de sesiones de procesos',
        'take_tests' => 'Responder evaluaciones',
        'view_test_results' => 'Ver resultados de evaluaciones',
        'manage_interview_processes' => 'Administrar procesos de entrevistas',
        'conduct_selection_interviews' => 'Conducir entrevistas de seleccion',
        'view_interview_reports' => 'Ver reportes de entrevistas',
        'manage_interview_settings' => 'Administrar configuracion de entrevistas',
        'manage_evaluation_surveys' => 'Administrar encuestas y evaluaciones',
        'view_evaluation_dashboard' => 'Ver dashboard de evaluaciones',
        'manage_reports' => 'Administrar informes',
        'assign_reports_by_company' => 'Asignar informes por empresa',
        'approve_reports' => 'Aprobar y publicar informes',
        'generate_reports' => 'Generar informes',
        'download_reports' => 'Descargar informes',
        'view_report_history' => 'Ver historial de informes',
        'run_report_batches' => 'Ejecutar informes masivos',
        'manage_company_users' => 'Administrar usuarios de la empresa',
        'manage_company_processes' => 'Administrar procesos de la empresa',
        'manage_company_interviews' => 'Administrar entrevistas de la empresa',
        'view_company_results' => 'Ver resultados de la empresa',
        'manage_facial_recognition' => 'Enrolar identidades faciales',
        'view_company_client_portal' => 'Consultar panel de avance de empresa',
        'validate_facial_identity' => 'Validar identidad facial',
    ];

    public const CORE_PERMISSIONS = [
        'manage_profiles',
        'manage_platform_settings',
        'manage_company_branding',
        'manage_user_fields',
        'manage_company_user_fields',
        'manage_users',
        'manage_companies',
        'manage_facial_recognition',
        'validate_facial_identity',
        'view_company_client_portal',
    ];

    public const PLATFORM_PERMISSIONS = [
        'evaluaciones_encuestas' => ['manage_evaluation_surveys'],
    ];
    public const INTERVIEWS_PERMISSIONS = [
        'manage_interview_processes',
        'conduct_selection_interviews',
        'view_interview_reports',
        'manage_interview_settings',
    ];
    public const EVALUATION_SURVEYS_PERMISSIONS = [
        'manage_evaluation_surveys',
        'view_evaluation_dashboard',
    ];
    public const REPORTS_PERMISSIONS = [
        'manage_reports',
        'assign_reports_by_company',
        'approve_reports',
        'generate_reports',
        'download_reports',
        'view_report_history',
        'run_report_batches',
    ];
    public const TESTS_PERMISSIONS = [
        'manage_tests',
        'manage_ranking_presets',
        'assign_tests',
        'manage_test_processes',
        'view_test_process_progress',
        'view_test_process_dashboard',
        'view_test_process_results',
        'manage_process_session_actions',
        'take_tests',
        'view_test_results',
    ];

    public const SCOPES = [
        'core:core' => 'Core de plataforma',
        'platform:tests' => 'Sub-plataforma Evaluaciones',
        'platform:interviews' => 'Sub-plataforma Entrevistas seleccion',
        'platform:evaluaciones_encuestas' => 'Sub-plataforma Encuestas y Evaluaciones',
        'platform:reports' => 'Sub-plataforma Informes',
    ];

    public const PERMISSION_GROUPS = [
        'core:core' => [
            'label' => 'Core de plataforma',
            'type' => 'Core',
            'permissions' => self::CORE_PERMISSIONS,
        ],
        'platform:tests' => [
            'label' => 'Sub-plataforma Evaluaciones',
            'type' => 'Sub-plataforma',
            'permissions' => self::TESTS_PERMISSIONS,
        ],
        'platform:interviews' => [
            'label' => 'Sub-plataforma Entrevistas seleccion',
            'type' => 'Sub-plataforma',
            'permissions' => self::INTERVIEWS_PERMISSIONS,
        ],
        'platform:evaluaciones_encuestas' => [
            'label' => 'Sub-plataforma Encuestas y Evaluaciones',
            'type' => 'Sub-plataforma',
            'permissions' => self::EVALUATION_SURVEYS_PERMISSIONS,
        ],
        'platform:reports' => [
            'label' => 'Sub-plataforma Informes',
            'type' => 'Sub-plataforma',
            'permissions' => self::REPORTS_PERMISSIONS,
        ],
    ];

    public const HOME_ROUTES = [
        'dashboard' => [
            'label' => 'Inicio',
            'permissions' => [],
        ],
        'client-admin.dashboard' => [
            'label' => 'Inicio Administrador Cliente',
            'permissions' => ['view_company_client_portal'],
        ],
        'companies' => [
            'label' => 'Empresas',
            'permissions' => ['manage_companies'],
        ],
        'users' => [
            'label' => 'Usuarios',
            'permissions' => ['manage_users'],
        ],
        'user-fields' => [
            'label' => 'Campos usuario',
            'permissions' => ['manage_user_fields', 'manage_company_user_fields'],
            'match' => 'any',
        ],
        'profiles' => [
            'label' => 'Perfiles',
            'permissions' => ['manage_profiles'],
        ],
        'settings' => [
            'label' => 'Configuracion',
            'permissions' => ['manage_platform_settings'],
        ],
        'tests.settings' => [
            'label' => 'Configuracion Evaluaciones',
            'permissions' => ['manage_platform_settings'],
        ],
        'my-tests' => [
            'label' => 'Mis evaluaciones',
            'permissions' => [],
        ],
        'tests' => [
            'label' => 'Evaluaciones',
            'permissions' => ['manage_tests'],
        ],
        'tests.progress' => [
            'label' => 'Estado Avance',
            'permissions' => ['manage_tests'],
        ],
        'tests.progress-ranking' => [
            'label' => 'Configurar ranking',
            'permissions' => ['manage_ranking_presets'],
        ],
        'tests.ranking-company-assignments' => [
            'label' => 'Asignar ranking por empresa',
            'permissions' => ['manage_ranking_presets'],
        ],
        'test-processes' => [
            'label' => 'Procesos',
            'permissions' => ['manage_tests', 'manage_test_processes', 'manage_company_processes', 'view_test_process_progress', 'view_test_process_dashboard', 'view_test_process_results'],
            'match' => 'any',
        ],
        'test-process.dashboard' => [
            'label' => 'Dashboard Avance',
            'permissions' => ['view_test_process_dashboard'],
            'match' => 'any',
        ],
        'tests.assign' => [
            'label' => 'Asignar evaluaciones',
            'permissions' => ['assign_tests'],
        ],
        'reports.generate' => [
            'label' => 'Generar Informes',
            'permissions' => ['manage_reports', 'generate_reports', 'manage_tests'],
            'match' => 'any',
        ],
        'interviews' => [
            'label' => 'Entrevistas seleccion',
            'permissions' => ['manage_interview_processes', 'conduct_selection_interviews', 'view_interview_reports'],
            'match' => 'any',
        ],
        'interviews.settings' => [
            'label' => 'Configuracion Entrevistas',
            'permissions' => ['manage_interview_settings'],
        ],
    ];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database();
    }

    public function all(): array
    {
        return $this->db->fetchAll('
            SELECT p.*, COUNT(DISTINCT u.id) AS users_count,
                   GROUP_CONCAT(DISTINCT CONCAT(s.scope_type, ":", s.scope_key) ORDER BY s.scope_type, s.scope_key SEPARATOR ",") AS scopes
            FROM role_profiles p
            LEFT JOIN users u ON u.profile_id = p.id
            LEFT JOIN role_profile_scopes s ON s.profile_id = p.id
            GROUP BY p.id
            ORDER BY p.scope_type ASC, p.scope_key ASC, p.is_system DESC, p.name ASC
        ');
    }

    public function active(): array
    {
        return $this->db->fetchAll('SELECT * FROM role_profiles WHERE is_active = 1 ORDER BY name');
    }

    public static function baseRoleForProfile(array $profile): string
    {
        $roleKey = (string) ($profile['role_key'] ?? '');
        if (in_array($roleKey, ['admin', 'agente', 'usuario', 'company_admin'], true)) {
            return $roleKey;
        }

        $permissions = json_decode((string) ($profile['permissions'] ?? '[]'), true);
        $permissions = is_array($permissions) ? $permissions : [];

        if (array_intersect($permissions, ['manage_profiles', 'manage_platform_settings'])) {
            return 'admin';
        }

        if (array_intersect($permissions, [
            'manage_users',
            'manage_companies',
            'manage_tests',
            'manage_ranking_presets',
            'assign_tests',
            'manage_test_processes',
            'view_test_process_progress',
            'view_test_process_dashboard',
            'view_test_process_results',
            'manage_interview_processes',
            'conduct_selection_interviews',
            'view_interview_reports',
            'manage_interview_settings',
        ])) {
            return 'agente';
        }

        return 'usuario';
    }

    public function requestProfiles(): array
    {
        return $this->db->fetchAll("
            SELECT *
            FROM role_profiles
            WHERE is_active = 1
              AND role_key = 'usuario'
            ORDER BY is_default_requester DESC, name
        ");
    }

    public function findActiveByRole(string $roleKey): ?array
    {
        return $this->db->fetch('
            SELECT *
            FROM role_profiles
            WHERE is_active = 1
              AND role_key = ?
            LIMIT 1
        ', [$roleKey]);
    }

    public function defaultRequesterProfile(): ?array
    {
        return $this->db->fetch("
            SELECT *
            FROM role_profiles
            WHERE is_active = 1
              AND role_key = 'usuario'
              AND is_default_requester = 1
            LIMIT 1
        ");
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM role_profiles WHERE id = ? LIMIT 1', [$id]);
    }

    public function scopesForProfile(int $id): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM role_profile_scopes WHERE profile_id = ? ORDER BY scope_type, scope_key', [$id]);
        $scopes = [];
        foreach ($rows as $row) {
            $scope = $row['scope_type'] . ':' . $row['scope_key'];
            $scopes[$scope] = json_decode($row['permissions'] ?? '[]', true) ?: [];
        }

        return $scopes;
    }

    public function create(array $data): int
    {
        return (int) $this->db->transaction(function () use ($data): int {
            $id = $this->db->insert('
                INSERT INTO role_profiles (name, role_key, scope_type, scope_key, description, permissions, home_route, is_default_requester, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ', [
                $data['name'],
                $data['role_key'],
                $data['primary_scope_type'],
                $data['primary_scope_key'],
                $data['description'] ?: null,
                json_encode($data['permissions'], JSON_UNESCAPED_UNICODE),
                $data['home_route'],
                (int) $data['is_default_requester'],
                (int) $data['is_active'],
            ]);

            if ((int) $data['is_default_requester'] === 1) {
                $this->setDefaultRequesterProfile($id);
            }

            return $id;
        });
    }

    public function update(int $id, array $data): void
    {
        $profile = $this->find($id);
        $roleKey = ((int) ($profile['is_system'] ?? 0) === 1) ? $profile['role_key'] : $data['role_key'];
        $scopeType = $data['primary_scope_type'];
        $scopeKey = $data['primary_scope_key'];

        $this->db->transaction(function () use ($id, $data, $roleKey, $scopeType, $scopeKey): void {
            $this->db->execute('
                UPDATE role_profiles
                SET name = ?, role_key = ?, scope_type = ?, scope_key = ?, description = ?, permissions = ?, home_route = ?, is_default_requester = ?, is_active = ?
                WHERE id = ?
            ', [
                $data['name'],
                $roleKey,
                $scopeType,
                $scopeKey,
                $data['description'] ?: null,
                json_encode($data['permissions'], JSON_UNESCAPED_UNICODE),
                $data['home_route'],
                (int) $data['is_default_requester'],
                (int) $data['is_active'],
                $id,
            ]);

            if ((int) $data['is_default_requester'] === 1) {
                $this->setDefaultRequesterProfile($id);
            }
        });
    }

    public function setDefaultRequesterProfile(int $id): void
    {
        $profile = $this->find($id);
        if (!$profile || $profile['role_key'] !== 'usuario' || (int) $profile['is_active'] !== 1) {
            return;
        }

        $this->db->transaction(function () use ($id): void {
            $this->db->execute('UPDATE role_profiles SET is_default_requester = 0 WHERE role_key = \'usuario\' AND id <> ?', [$id]);
            $this->db->execute('UPDATE role_profiles SET is_default_requester = 1 WHERE id = ? AND role_key = \'usuario\' AND is_active = 1', [$id]);
        });
    }

    public function permissionsForScope(string $scopeType, string $scopeKey): array
    {
        $keys = $scopeType === 'core'
            ? self::CORE_PERMISSIONS
            : ($scopeKey === 'tests'
                ? self::TESTS_PERMISSIONS
                : ($scopeKey === 'interviews'
                    ? self::INTERVIEWS_PERMISSIONS
                    : ($scopeKey === 'reports' ? self::REPORTS_PERMISSIONS : (self::PLATFORM_PERMISSIONS[$scopeKey] ?? []))));

        return array_intersect_key(self::PERMISSIONS, array_flip($keys));
    }

    public static function homeRouteOptionsForPermissions(array $permissions): array
    {
        $permissions = array_values(array_unique(array_map('strval', $permissions)));
        $options = [];

        foreach (self::HOME_ROUTES as $route => $meta) {
            $required = $meta['permissions'] ?? [];
            $match = $meta['match'] ?? 'all';
            $allowed = !$required;

            if ($required && $match === 'any') {
                $allowed = (bool) array_intersect($required, $permissions);
            } elseif ($required) {
                $allowed = !array_diff($required, $permissions);
            }

            if ($allowed) {
                $options[$route] = $meta['label'];
            }
        }

        return $options;
    }

    public static function homeRouteIsAllowed(string $route, array $permissions): bool
    {
        return array_key_exists($route, self::homeRouteOptionsForPermissions($permissions));
    }

    public function syncScopes(int $profileId, array $scopePermissions): void
    {
        $this->db->transaction(function () use ($profileId, $scopePermissions): void {
            $this->db->execute('DELETE FROM role_profile_scopes WHERE profile_id = ?', [$profileId]);

            foreach ($scopePermissions as $scope => $permissions) {
                if (!$permissions || !isset(self::SCOPES[$scope])) {
                    continue;
                }

                [$scopeType, $scopeKey] = explode(':', $scope, 2);
                $this->db->execute('
                    INSERT INTO role_profile_scopes (profile_id, scope_type, scope_key, permissions)
                    VALUES (?, ?, ?, ?)
                ', [
                    $profileId,
                    $scopeType,
                    $scopeKey,
                    json_encode(array_values($permissions), JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    public function groupedPermissions(): array
    {
        $groups = [];
        foreach (self::PERMISSION_GROUPS as $scope => $group) {
            $groups[$scope] = [
                'label' => $group['label'],
                'type' => $group['type'],
                'permissions' => array_intersect_key(self::PERMISSIONS, array_flip($group['permissions'])),
            ];
        }

        return $groups;
    }
}
