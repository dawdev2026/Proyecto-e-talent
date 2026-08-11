<?php
declare(strict_types=1);

final class CompanyController extends Controller
{
    private CompanyModel $companies;

    public function __construct(?Template $view = null, ?CompanyModel $companies = null)
    {
        parent::__construct($view);
        $this->companies = $companies ?: new CompanyModel();
    }

    public function index(): void
    {
        require_permission('manage_companies');

        $this->render('companies/index', [
            'title' => 'Empresas | Metricatest',
            'currentPage' => 'companies',
            'companies' => $this->companies->all(),
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
            'title' => ($id ? 'Editar empresa' : 'Nueva empresa') . ' | Metricatest',
            'currentPage' => 'companies',
            'id' => $id,
            'values' => $company ?: [
                'name' => '',
                'tax_id' => '',
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
            flash('danger', 'No se pudo guardar la empresa. Revisa si el nombre o RUT ya existe.');
        }
    }
}
