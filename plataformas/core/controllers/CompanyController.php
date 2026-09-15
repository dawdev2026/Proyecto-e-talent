<?php
declare(strict_types=1);

final class CompanyController extends Controller
{
    private CompanyModel $companies;
    private CompanyDeletionService $deletion;
    private TestUserCleanupService $cleanup;

    public function __construct(?Template $view = null, ?CompanyModel $companies = null, ?CompanyDeletionService $deletion = null, ?TestUserCleanupService $cleanup = null)
    {
        parent::__construct($view);
        $this->companies = $companies ?: new CompanyModel();
        $this->deletion = $deletion ?: new CompanyDeletionService();
        $this->cleanup = $cleanup ?: new TestUserCleanupService();
    }

    public function delete(): void
    {
        require_permission('manage_companies');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            platform_error(405, 'La eliminacion de una empresa requiere una solicitud POST.');
        }

        verify_csrf();
        $id = request_secure_id('company');
        if ($id <= 0) {
            platform_error(404, 'Empresa no encontrada.');
        }

        try {
            $this->deletion->delete($id);
            flash('success', 'La empresa y sus datos relacionados fueron eliminados correctamente.');
        } catch (Throwable $exception) {
            error_log('Company deletion failed: ' . $exception->getMessage());
            flash('danger', 'No se pudo completar la eliminacion. Revisa el registro tecnico antes de volver a intentarlo.');
        }

        redirect('companies');
    }

    public function index(): void
    {
        require_permission('manage_companies');

        $this->render('companies/index', [
            'title' => 'Empresas | e-talent',
            'currentPage' => 'companies',
            'companies' => $this->companies->all(),
        ]);
    }

    public function cleanupUsers(): void
    {
        require_permission('manage_companies');
        $id = request_secure_id('company');
        if ($id <= 0) {
            platform_error(404, 'Empresa no encontrada.');
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                verify_csrf();
                $result = $this->cleanup->delete($id, (string) ($_POST['confirmation_prefix'] ?? ''));
                flash('success', sprintf('Se eliminaron %d usuarios de prueba de %s.', $result['users'], $result['company']['name']));
                redirect('companies');
            }
            $preview = $this->cleanup->preview($id);
        } catch (Throwable $exception) {
            error_log('Test user cleanup failed: ' . $exception->getMessage());
            flash('danger', 'No se pudo completar la limpieza. Verifica el prefijo y revisa el registro técnico.');
            redirect('companies');
        }

        $this->render('companies/cleanup-users', [
            'title' => 'Limpiar usuarios de prueba | e-talent',
            'currentPage' => 'companies',
            'preview' => $preview,
        ]);
    }

    public function form(): void
    {
        require_permission('manage_companies');

        $id = request_secure_id('company');
        $company = $id ? $this->companies->find($id) : null;
        $companyAdmins = $id ? $this->companies->activeAdmins($id) : [];

        if ($id && !$company) {
            platform_error(404, 'Empresa no encontrada.', [
                'chips' => ['Empresa'],
            ]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save($id);
        }

        $this->render('companies/form', [
            'title' => ($id ? 'Editar empresa' : 'Nueva empresa') . ' | e-talent',
            'currentPage' => 'companies',
            'id' => $id,
            'values' => $company ?: [
                'name' => '',
                'tax_id' => '',
                'url_prefix' => '',
                'is_active' => 1,
            ],
            'companyAdmins' => $companyAdmins,
        ]);
    }

    private function save(int $id): void
    {
        verify_csrf();

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'tax_id' => trim($_POST['tax_id'] ?? ''),
            'url_prefix' => mb_strtolower(trim($_POST['url_prefix'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'admin_first_names' => trim($_POST['admin_first_names'] ?? ''),
            'admin_last_names' => trim($_POST['admin_last_names'] ?? ''),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
            'admin_password' => (string) ($_POST['admin_password'] ?? ''),
        ];

        if ($data['name'] === '') {
            flash('danger', 'El nombre de la empresa es obligatorio.');
            return;
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['url_prefix'])) {
            flash('danger', 'El prefijo de URL solo puede contener letras, números y guiones, sin espacios.');
            return;
        }
        if (in_array($data['url_prefix'], company_reserved_url_prefixes(), true)) {
            flash('danger', 'Ese prefijo de URL está reservado por la plataforma.');
            return;
        }
        $existingPrefix = $this->companies->findByUrlPrefix($data['url_prefix']);
        if ($existingPrefix && (int) $existingPrefix['id'] !== $id) {
            flash('danger', 'El prefijo de URL ya está asignado a otra empresa.');
            return;
        }
        $needsAdmin = !$id || !$this->companies->activeAdmins($id);
        if ($needsAdmin && ($data['admin_first_names'] === '' || $data['admin_last_names'] === '')) {
            flash('danger', 'Ingresa el nombre y apellido del administrador inicial.');
            return;
        }
        if ($needsAdmin && !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Ingresa un correo valido para el administrador inicial.');
            return;
        }
        if ($needsAdmin && strlen($data['admin_password']) < 8) {
            flash('danger', 'La contraseña del administrador inicial debe tener al menos 8 caracteres.');
            return;
        }

        try {
            if ($id) {
                $this->companies->update($id, $data);
                if (!$this->companies->activeAdmins($id)) {
                    $this->companies->provisionAdmin($id, $data);
                    flash('success', 'Empresa actualizada y administrador inicial creado correctamente.');
                } else {
                    flash('success', 'Empresa actualizada correctamente.');
                }
            } else {
                $this->companies->create($data);
                flash('success', 'Empresa creada correctamente.');
            }

            redirect('companies');
        } catch (PDOException $exception) {
            flash('danger', 'No se pudo guardar la empresa. Revisa si el nombre, RUT o prefijo de URL ya existe.');
        }
    }
}
