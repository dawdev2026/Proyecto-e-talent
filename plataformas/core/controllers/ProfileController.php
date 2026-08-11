<?php
declare(strict_types=1);

final class ProfileController extends Controller
{
    private ProfileModel $profiles;

    public function __construct(?Template $view = null, ?ProfileModel $profiles = null)
    {
        parent::__construct($view);
        $this->profiles = $profiles ?: new ProfileModel();
    }

    public function index(): void
    {
        require_permission('manage_profiles');

        $this->render('profiles/index', [
            'title' => 'Perfiles | Metricatest',
            'currentPage' => 'profiles',
            'profiles' => $this->profiles->all(),
            'permissionLabels' => ProfileModel::PERMISSIONS,
            'permissionGroups' => $this->profiles->groupedPermissions(),
        ]);
    }

    public function form(): void
    {
        require_permission('manage_profiles');

        $id = request_secure_id('profile');
        $profile = $id ? $this->profiles->find($id) : null;

        if ($id && !$profile) {
            platform_error(404, 'Perfil no encontrado.', [
                'chips' => ['Perfil'],
            ]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save($id);
        }

        $this->render('profiles/form', [
            'title' => ($id ? 'Editar perfil' : 'Nuevo perfil') . ' | Metricatest',
            'currentPage' => 'profiles',
            'id' => $id,
            'permissionLabels' => ProfileModel::PERMISSIONS,
            'permissionGroups' => $this->profiles->groupedPermissions(),
            'homeRoutes' => ProfileModel::HOME_ROUTES,
            'homeRouteOptions' => ProfileModel::homeRouteOptionsForPermissions(json_decode($profile['permissions'] ?? '[]', true) ?: []),
            'scopes' => ProfileModel::SCOPES,
            'selectedScopes' => $id ? $this->profiles->scopesForProfile($id) : ['core:core' => []],
            'values' => $profile ?: [
                'name' => '',
                'role_key' => 'usuario',
                'scope_type' => 'core',
                'scope_key' => 'core',
                'description' => '',
                'permissions' => '[]',
                'home_route' => 'my-tests',
                'is_system' => 0,
                'is_default_requester' => 0,
                'is_active' => 1,
            ],
        ]);
    }

    private function save(int $id): void
    {
        verify_csrf();

        $enabledScopes = array_values(array_intersect(array_keys(ProfileModel::SCOPES), $_POST['scopes'] ?? []));
        $postedPermissions = $_POST['permissions'] ?? [];
        $scopePermissions = [];
        $permissions = [];

        foreach ($enabledScopes as $scope) {
            [$scopeType, $scopeKey] = $this->scopeParts($scope);
            $allowedPermissions = array_keys($this->profiles->permissionsForScope($scopeType, $scopeKey));
            $scopePermissions[$scope] = array_values(array_intersect($allowedPermissions, $postedPermissions[$scope] ?? []));
            $permissions = array_merge($permissions, $scopePermissions[$scope]);
        }

        $permissions = array_values(array_unique($permissions));
        $primaryScope = $enabledScopes[0] ?? '';
        [$primaryScopeType, $primaryScopeKey] = $this->scopeParts($primaryScope);
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'role_key' => preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['role_key'] ?? 'usuario'))),
            'primary_scope_type' => $primaryScopeType,
            'primary_scope_key' => $primaryScopeKey,
            'description' => trim($_POST['description'] ?? ''),
            'permissions' => $permissions,
            'home_route' => trim((string) ($_POST['home_route'] ?? 'dashboard')),
            'is_default_requester' => isset($_POST['is_default_requester']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($data['name'] === '' || $data['role_key'] === '' || !$enabledScopes || (!$permissions && $data['role_key'] !== 'usuario')) {
            flash('danger', 'Completa nombre, clave del perfil, al menos un alcance y al menos un permiso cuando corresponda.');
            return;
        }

        if (!ProfileModel::homeRouteIsAllowed($data['home_route'], $permissions)) {
            flash('danger', 'La pagina inicial debe pertenecer a los permisos configurados para este perfil.');
            return;
        }

        if ($data['is_default_requester'] === 1 && ($data['role_key'] !== 'usuario' || $data['is_active'] !== 1)) {
            flash('danger', 'El perfil por defecto para cargas masivas debe estar activo y tener clave usuario.');
            return;
        }

        try {
            if ($id) {
                $this->profiles->update($id, $data);
                $this->profiles->syncScopes($id, $scopePermissions);
                flash('success', 'Perfil actualizado correctamente.');
            } else {
                $id = $this->profiles->create($data);
                $this->profiles->syncScopes($id, $scopePermissions);
                flash('success', 'Perfil creado correctamente.');
            }

            redirect(route_url('profiles'));
        } catch (PDOException $exception) {
            flash('danger', 'No se pudo guardar el perfil. Revisa si el nombre o clave ya existe.');
        }
    }

    private function scopeParts(string $scope): array
    {
        if (!isset(ProfileModel::SCOPES[$scope])) {
            return ['core', 'core'];
        }

        return explode(':', $scope, 2);
    }
}
