<?php
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class UserController extends Controller
{
    private const IMPORT_TEMPLATE_KEY = 'user_import_xlsx';
    private const IMPORT_TEMPLATE_VERSION = '5';
    private const CORE_IMPORT_HEADERS = ['rut', 'nombres', 'apellidos', 'correo', 'sexo', 'fecha_nacimiento', 'edad', 'empresa', 'contrasena', 'activo'];

    private UserModel $users;
    private ProfileModel $profiles;
    private UserFieldModel $fields;

    public function __construct(?Template $view = null, ?UserModel $users = null, ?ProfileModel $profiles = null, ?UserFieldModel $fields = null)
    {
        parent::__construct($view);
        $this->users = $users ?: new UserModel();
        $this->profiles = $profiles ?: new ProfileModel();
        $this->fields = $fields ?: new UserFieldModel();
    }

    public function index(): void
    {
        require_company_user_management();

        $this->render('users/index', [
            'title' => 'Usuarios | e-talent',
            'currentPage' => 'users',
        ]);
    }

    public function data(): void
    {
        require_company_user_management();

        $draw = max(0, (int) ($_GET['draw'] ?? 0));
        $start = max(0, (int) ($_GET['start'] ?? 0));
        $length = (int) ($_GET['length'] ?? 25);
        $searchParam = $_GET['search'] ?? [];
        $search = trim(is_array($searchParam) ? (string) ($searchParam['value'] ?? '') : '');
        $order = is_array($_GET['order'][0] ?? null) ? $_GET['order'][0] : [];
        $orderColumn = (int) ($order['column'] ?? 0);
        $orderDir = (string) ($order['dir'] ?? 'asc');
        $page = $this->users->usersDataPage($search, $start, $length, $orderColumn, $orderDir);

        $this->jsonResponse([
            'draw' => $draw,
            'recordsTotal' => (int) $page['total'],
            'recordsFiltered' => (int) $page['filtered'],
            'data' => array_map(fn(array $user): array => $this->userTableRow($user), $page['rows']),
        ]);
    }

    public function form(): void
    {
        require_company_user_management();

        $id = request_secure_id('user');
        $requester = $id ? $this->users->findUser($id) : null;
        $isDrawer = (string) ($_GET['drawer'] ?? '') === '1';
        $isCompanyAdmin = is_company_admin_user() || (has_permission('manage_company_users') && !has_permission('manage_users'));
        $defaultProfile = $this->profiles->defaultRequesterProfile();

        if ($id && !$requester) {
            platform_error(404, 'Usuario no encontrado.', [
                'chips' => ['Usuario'],
            ]);
        }
        if (is_array($requester)) {
            $requester['sex'] = $this->normalizeSex(trim((string) ($requester['sex'] ?? '')));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->save($id);
            if ($this->isAjaxRequest()) {
                $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
                return;
            }
        }

        $fieldDefinitions = $this->fields->activeForUsers();

        $this->render('users/form', [
            'title' => ($id ? 'Editar usuario' : 'Nuevo usuario') . ' | e-talent',
            'currentPage' => 'users',
            'companies' => $this->users->companies(),
            'profiles' => $this->activeProfilesForUserForm(),
            'fieldDefinitions' => $fieldDefinitions,
            'fieldValues' => $id ? $this->fields->valuesForUser($id) : [],
            'id' => $id,
            'isDrawer' => $isDrawer,
            'formAction' => $id ? route_url('user.edit', $id) . '?drawer=1' : route_url('user.new'),
            'values' => $requester ?: [
                'rut' => '',
                'first_names' => '',
                'last_names' => '',
                'email' => '',
                'sex' => 'no_informado',
                'birth_date' => '',
                'age' => '',
                'role' => 'usuario',
                'profile_id' => $isCompanyAdmin ? (int) ($defaultProfile['id'] ?? 0) : '',
                'company_id' => $isCompanyAdmin ? (int) (current_user()['company_id'] ?? 0) : '',
                'is_active' => 1,
            ],
        ], $isDrawer ? null : 'app');
    }

    public function import(): void
    {
        require_company_user_management();

        $fieldDefinitions = $this->fields->activeForUsers();
        $defaultProfile = $this->profiles->defaultRequesterProfile();
        $preview = $_SESSION['user_import_preview'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $action = $_POST['import_action'] ?? 'validate';

            if ($action === 'apply') {
                $this->applyImport($fieldDefinitions);
                return;
            }

            if ($action === 'recalculate_age') {
                $preview = $this->recalculateImportAges($fieldDefinitions, $defaultProfile);
            } else {
                $preview = $action === 'correct'
                    ? $this->validateCorrectedRows($fieldDefinitions, $defaultProfile)
                    : $this->validateImportFile($fieldDefinitions, $defaultProfile);
            }
            $_SESSION['user_import_preview'] = $preview;
        } else {
            unset($_SESSION['user_import_preview']);
            $preview = null;
        }

        $this->render('users/import', [
            'title' => 'Carga masiva de usuarios | e-talent',
            'currentPage' => 'users',
            'fieldDefinitions' => $fieldDefinitions,
            'defaultProfile' => $defaultProfile,
            'preview' => $preview,
        ]);
    }

    public function importPreviewData(): void
    {
        require_company_user_management();

        $preview = $_SESSION['user_import_preview'] ?? null;
        $rows = is_array($preview['rows'] ?? null) ? $preview['rows'] : [];
        $draw = max(0, (int) ($_GET['draw'] ?? 0));
        $start = max(0, (int) ($_GET['start'] ?? 0));
        $length = (int) ($_GET['length'] ?? 25);
        $length = $length > 0 ? min($length, 100) : 25;
        $searchParam = $_GET['search'] ?? [];
        $search = trim(is_array($searchParam) ? (string) ($searchParam['value'] ?? '') : '');
        $fieldDefinitions = $this->fields->activeForUsers();
        $indexedRows = [];

        foreach ($rows as $index => $row) {
            if ($search !== '' && !$this->importRowMatchesSearch($row, $search, $fieldDefinitions)) {
                continue;
            }

            $indexedRows[] = [
                'index' => (int) $index,
                'row' => $row,
            ];
        }

        $pageRows = array_slice($indexedRows, $start, $length);

        $this->jsonResponse([
            'draw' => $draw,
            'recordsTotal' => count($rows),
            'recordsFiltered' => count($indexedRows),
            'data' => array_map(function (array $entry) use ($fieldDefinitions): array {
                return $this->importPreviewTableRow((int) $entry['index'], $entry['row'], $fieldDefinitions);
            }, $pageRows),
        ]);
    }

    public function importRowForm(): void
    {
        require_company_user_management();

        $preview = $_SESSION['user_import_preview'] ?? null;
        $index = max(0, (int) ($_GET['index'] ?? -1));
        $row = is_array($preview['rows'][$index] ?? null) ? $preview['rows'][$index] : null;

        if (!$row) {
            http_response_code(404);
            echo '<div class="drawer-error"><i class="bi bi-exclamation-triangle"></i><p>No se encontro la fila solicitada.</p></div>';
            return;
        }

        echo $this->view->render('users/partials/import_row_form', [
            'fieldDefinitions' => $this->fields->activeForUsers(),
            'row' => $row,
            'index' => $index,
            'saveUrl' => app_url('users/import/row-save'),
        ], null);
    }

    public function importRowSave(): void
    {
        require_company_user_management();
        verify_csrf();

        $preview = $_SESSION['user_import_preview'] ?? null;
        $index = max(0, (int) ($_POST['row_index'] ?? -1));

        if (!$preview || !is_array($preview['rows'][$index] ?? null)) {
            $this->jsonResponse([
                'ok' => false,
                'message' => 'No se encontro la previsualizacion o la fila solicitada.',
            ], 404);
            return;
        }

        $fieldDefinitions = $this->fields->activeForUsers();
        $defaultProfile = $this->profiles->defaultRequesterProfile();
        $rows = $preview['rows'];
        $rows[$index] = $this->importRowFromPostedData($_POST, (int) ($rows[$index]['row_number'] ?? ($index + 2)), $fieldDefinitions, $defaultProfile);

        $updatedPreview = $this->validateImportRows(
            $rows,
            $fieldDefinitions,
            $defaultProfile,
            $this->importPreviewBase((string) ($preview['uploaded_name'] ?? 'Correcciones en pantalla'))
        );
        $_SESSION['user_import_preview'] = $updatedPreview;

        $this->jsonResponse([
            'ok' => true,
            'message' => empty($updatedPreview['valid']) ? 'Fila guardada. Aun quedan problemas por resolver.' : 'Fila guardada. La carga quedo lista para aplicar.',
            'valid' => !empty($updatedPreview['valid']),
            'summary' => $updatedPreview['summary'] ?? $this->emptyImportSummary(),
            'error_summary' => $updatedPreview['error_summary'] ?? $this->emptyImportErrorSummary(),
            'created' => (int) ($updatedPreview['created'] ?? 0),
            'updated' => (int) ($updatedPreview['updated'] ?? 0),
        ]);
    }

    public function importTemplate(): void
    {
        require_company_user_management();

        $headers = array_merge(self::CORE_IMPORT_HEADERS, array_map(
            static fn(array $field): string => $field['field_key'],
            $this->fields->activeForUsers()
        ));

        $users = $this->users->users();
        $fields = $this->fields->activeForUsers();
        $fieldValuesByUser = $this->fields->valuesForUsers(array_map(static fn(array $user): int => (int) $user['id'], $users), $fields);
        $rows = [$headers];

        foreach ($users as $user) {
            $row = [
                $user['rut'] ?? '',
                $user['first_names'] ?? '',
                $user['last_names'] ?? '',
                $user['email'],
                $user['sex'] ?? '',
                $user['birth_date'] ?? '',
                $user['age'] ?? '',
                $user['company_name'] ?? '',
                '',
                (int) $user['is_active'] === 1 ? '1' : '0',
            ];

            foreach ($fields as $field) {
                $value = $fieldValuesByUser[(int) $user['id']][(int) $field['id']] ?? '';
                $row[] = $field['field_type'] === 'checkbox' ? ($value === '1' ? '1' : '0') : $value;
            }

            $rows[] = $row;
        }

        if (count($rows) === 1) {
            $rows[] = array_fill(0, count($headers), '');
        }

        $this->downloadXlsx('usuarios_carga_masiva.xlsx', $rows);
        exit;
    }

    private function save(int $id): array
    {
        verify_csrf();
        $isAjax = $this->isAjaxRequest();

        try {

        $data = [
            'rut' => UserModel::formatRut(trim($_POST['rut'] ?? '')),
            'role' => 'usuario',
            'first_names' => trim($_POST['first_names'] ?? ''),
            'last_names' => trim($_POST['last_names'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'sex' => $this->normalizeSex(trim((string) ($_POST['sex'] ?? ''))),
            'birth_date' => $this->normalizeBirthDateInput(trim($_POST['birth_date'] ?? '')),
            'age' => trim((string) ($_POST['age'] ?? '')),
            'password' => $_POST['password'] ?? '',
            'profile_id' => (int) ($_POST['profile_id'] ?? 0),
            'company_id' => (int) ($_POST['company_id'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        $isCompanyAdmin = is_company_admin_user() || (has_permission('manage_company_users') && !has_permission('manage_users'));
        if ($isCompanyAdmin) {
            $data['role'] = 'usuario';
            $data['company_id'] = (int) (current_user()['company_id'] ?? 0);
        }
        $selectedProfile = $data['profile_id'] > 0 ? $this->profiles->find($data['profile_id']) : null;
        if (!$isCompanyAdmin && $selectedProfile && (int) ($selectedProfile['is_active'] ?? 0) === 1) {
            $data['role'] = ProfileModel::baseRoleForProfile($selectedProfile);
        }
        $fieldDefinitions = $this->fields->activeForUsers();
        $fieldValues = $_POST['field_values'] ?? [];

        foreach ($this->validateCoreUserData($data, $id ?: null) as $error) {
            if (!$isAjax) {
                flash('danger', $error);
            }
            return ['ok' => false, 'message' => $error];
        }
        $data['age'] = (int) UserModel::calculateAge($data['birth_date']);
        if ($data['password'] === '') {
            $data['password'] = UserModel::rutDefaultPassword($data['rut']);
        }

        foreach ($this->fields->validateValues($fieldDefinitions, $fieldValues) as $error) {
            if (!$isAjax) {
                flash('danger', $error);
            }
            return ['ok' => false, 'message' => $error];
        }

            if ($id) {
                $currentUser = $this->users->findUser($id);
                if ($currentUser && (string) ($currentUser['role'] ?? '') === 'company_admin' && (int) ($currentUser['is_active'] ?? 0) === 1 && (int) $data['is_active'] === 0) {
                    $activeAdmins = (new CompanyModel())->activeAdminCount((int) ($currentUser['company_id'] ?? 0));
                    if ($activeAdmins <= 1) {
                        $error = 'La empresa debe conservar al menos un administrador activo.';
                        if (!$isAjax) {
                            flash('danger', $error);
                        }
                        return ['ok' => false, 'message' => $error];
                    }
                }
                $this->users->updateUser($id, $data);
                $this->fields->saveValues($id, $fieldDefinitions, $fieldValues);
                if ($isAjax) {
                    return ['ok' => true, 'message' => 'Usuario actualizado correctamente.'];
                }
                flash('success', 'Usuario actualizado correctamente.');
            } else {
                $id = $this->users->createUser($data);
                $this->fields->saveValues($id, $fieldDefinitions, $fieldValues);
                if ($isAjax) {
                    return ['ok' => true, 'message' => 'Usuario creado correctamente.'];
                }
                flash('success', 'Usuario creado correctamente.');
            }
            redirect(route_url('users'));
        } catch (Throwable $exception) {
            $reference = 'USER-SAVE-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            error_log(sprintf(
                'User save failed [%s]: %s: %s',
                $reference,
                get_class($exception),
                $exception->getMessage()
            ));

            $message = $this->userSaveExceptionMessage($exception, $reference);

            if (!$isAjax) {
                flash('danger', $message);
            }
            return ['ok' => false, 'message' => $message, 'reference' => $reference];
        }

        return ['ok' => true, 'message' => 'Usuario guardado correctamente.'];
    }

    private function userSaveExceptionMessage(Throwable $exception, string $reference): string
    {
        $exceptionMessage = $exception->getMessage();
        if (str_contains($exceptionMessage, 'uq_users_rut')) {
            return 'Ya existe un usuario con ese RUT.';
        }
        if (str_contains($exceptionMessage, 'uq_users_email')) {
            return 'Ya existe un usuario con ese correo.';
        }

        // El detalle técnico queda registrado mediante error_log() arriba,
        // pero nunca debe exponerse en la interfaz, tampoco en Desarrollo.
        return 'No se pudo guardar el usuario [' . $reference . ']. Revisa los datos e inténtalo nuevamente.';
    }

    private function userTableRow(array $user): array
    {
        $row = [
            '<span class="fw-semibold">' . e((string) ($user['rut'] ?? '')) . '</span>',
            e((string) ($user['first_names'] ?? '')),
            e((string) ($user['last_names'] ?? '')),
            e((string) ($user['email'] ?? '')),
            e(labelize((string) ($user['sex'] ?? ''))),
            e((string) ($user['birth_date'] ?? '')),
            e((string) ($user['age'] ?? '')),
            e((string) ($user['profile_name'] ?? 'Sin perfil')),
            e((string) ($user['company_name'] ?? 'Sin empresa')),
        ];

        $row[] = '<span class="badge ' . ((int) ($user['is_active'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary') . '">' . ((int) ($user['is_active'] ?? 0) === 1 ? 'Activo' : 'Inactivo') . '</span>';
        $editUrl = route_url('user.edit', (int) $user['id']);
        $row[] = '<a class="btn btn-sm btn-outline-primary" href="' . e($editUrl) . '" data-drawer-url="' . e($editUrl . '?drawer=1') . '" data-drawer-title="Editar usuario" data-drawer-size="lg"><i class="bi bi-pencil me-1"></i> Editar</a>';

        return $row;
    }

    private function isAjaxRequest(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    private function importPreviewTableRow(int $index, array $importRow, array $fieldDefinitions): array
    {
        $fieldErrors = $importRow['field_errors'] ?? [];
        $data = $importRow['data'] ?? [];
        $row = [
            '<span class="import-row-number">' . (int) ($importRow['row_number'] ?? ($index + 2)) . '</span>',
            '<span class="badge ' . (($importRow['action'] ?? '') === 'update' ? 'text-bg-info' : 'text-bg-success') . '">' . (($importRow['action'] ?? '') === 'update' ? 'Actualizar' : 'Crear') . '</span>',
            $this->importPreviewCell((string) ($data['rut'] ?? ''), isset($fieldErrors['rut'])),
            $this->importPreviewCell((string) ($data['first_names'] ?? ''), isset($fieldErrors['first_names'])),
            $this->importPreviewCell((string) ($data['last_names'] ?? ''), isset($fieldErrors['last_names'])),
            $this->importPreviewCell((string) ($data['email'] ?? ''), isset($fieldErrors['email'])),
            $this->importPreviewCell(labelize((string) ($data['sex'] ?? '')), isset($fieldErrors['sex'])),
            $this->importPreviewCell((string) ($data['birth_date'] ?? ''), isset($fieldErrors['birth_date'])),
            $this->importPreviewCell((string) ($data['age'] ?? ''), isset($fieldErrors['age'])),
            $this->importPreviewCell((string) ($data['company_name'] ?? ''), isset($fieldErrors['company_name'])),
            !empty($importRow['password_unchanged'])
                ? 'Sin cambio'
                : (!empty($importRow['password_from_rut']) ? 'Desde RUT' : (((string) ($data['password'] ?? '')) !== '' ? 'Definida' : 'Desde RUT')),
            (int) ($data['is_active'] ?? 1) === 1 ? 'Si' : 'No',
        ];

        foreach ($fieldDefinitions as $field) {
            $fieldId = (int) $field['id'];
            $errorKey = 'field_' . $fieldId;
            $value = $importRow['field_values'][$fieldId] ?? '';
            $row[] = $this->importPreviewCell(
                (string) ($field['field_type'] === 'checkbox' ? ($value === '1' ? 'Si' : 'No') : $value),
                isset($fieldErrors[$errorKey])
            );
        }

        $row[] = $this->importProblemCell($index, $importRow);

        return $row;
    }

    private function importPreviewCell(string $value, bool $hasError): string
    {
        return '<span class="' . ($hasError ? 'import-preview-error-value' : '') . '">' . e($value) . '</span>';
    }

    private function importProblemCell(int $index, array $importRow): string
    {
        $errors = $importRow['errors'] ?? [];
        if (!$errors) {
            return '<span class="import-ok"><i class="bi bi-check2-circle"></i> Sin problemas. La fila esta lista para cargar.</span>';
        }

        $items = array_map(static fn(string $error): string => '<li>' . e($error) . '</li>', $errors);
        $rowNumber = (int) ($importRow['row_number'] ?? ($index + 2));

        return '<div class="import-problem-cell"><ul>' . implode('', $items) . '</ul>'
            . '<button class="btn btn-sm btn-outline-primary mt-2" type="button" data-drawer-url="' . e(app_url('users/import/row') . '?index=' . $index) . '" data-drawer-title="Corregir fila ' . $rowNumber . '" data-drawer-size="lg">'
            . '<i class="bi bi-pencil-square me-1"></i> Corregir fila</button></div>';
    }

    private function importRowMatchesSearch(array $row, string $search, array $fieldDefinitions): bool
    {
        $data = $row['data'] ?? [];
        $haystack = [
            (string) ($row['row_number'] ?? ''),
            (string) ($row['action'] ?? ''),
            (string) ($data['rut'] ?? ''),
            (string) ($data['first_names'] ?? ''),
            (string) ($data['last_names'] ?? ''),
            (string) ($data['email'] ?? ''),
            (string) ($data['sex'] ?? ''),
            (string) ($data['birth_date'] ?? ''),
            (string) ($data['age'] ?? ''),
            (string) ($data['company_name'] ?? ''),
            (int) ($data['is_active'] ?? 1) === 1 ? 'activo si' : 'inactivo no',
        ];

        foreach ($fieldDefinitions as $field) {
            $fieldId = (int) $field['id'];
            $haystack[] = (string) ($row['field_values'][$fieldId] ?? '');
        }

        foreach (($row['errors'] ?? []) as $error) {
            $haystack[] = (string) $error;
        }

        return stripos(implode(' ', $haystack), $search) !== false;
    }

    private function importPreviewBase(string $uploadedName): array
    {
        return [
            'rows' => [],
            'global_errors' => [],
            'created' => 0,
            'updated' => 0,
            'valid' => false,
            'uploaded_name' => $uploadedName,
            'summary' => $this->emptyImportSummary(),
            'error_summary' => $this->emptyImportErrorSummary(),
        ];
    }

    private function importRowFromPostedData(array $postedRow, int $rowNumber, array $fieldDefinitions, ?array $defaultProfile): array
    {
        $fieldValues = [];
        foreach ($fieldDefinitions as $field) {
            $fieldId = (int) $field['id'];
            $fieldValues[$fieldId] = trim((string) (($postedRow['field_values'] ?? [])[$fieldId] ?? ''));
        }

        return [
            'row_number' => $rowNumber,
            'action' => 'create',
            'data' => [
                'rut' => UserModel::formatRut(trim((string) (($postedRow['data'] ?? [])['rut'] ?? ''))),
                'role' => 'usuario',
                'first_names' => trim((string) (($postedRow['data'] ?? [])['first_names'] ?? '')),
                'last_names' => trim((string) (($postedRow['data'] ?? [])['last_names'] ?? '')),
                'email' => trim((string) (($postedRow['data'] ?? [])['email'] ?? '')),
                'sex' => $this->normalizeSex(trim((string) (($postedRow['data'] ?? [])['sex'] ?? ''))),
                'birth_date' => $this->normalizeBirthDateInput(trim((string) (($postedRow['data'] ?? [])['birth_date'] ?? ''))),
                'age' => trim((string) (($postedRow['data'] ?? [])['age'] ?? '')),
                'password' => trim((string) (($postedRow['data'] ?? [])['password'] ?? '')),
                'profile_id' => (int) ($defaultProfile['id'] ?? 0),
                'company_id' => 0,
                'company_name' => trim((string) (($postedRow['data'] ?? [])['company_name'] ?? '')),
                'is_active' => $this->parseActive((string) (($postedRow['data'] ?? [])['is_active'] ?? '1')),
            ],
            'password_from_rut' => false,
            'password_unchanged' => false,
            'field_values' => $fieldValues,
            'errors' => [],
            'field_errors' => [],
        ];
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function validateImportFile(array $fieldDefinitions, ?array $defaultProfile): array
    {
        $preview = [
            'rows' => [],
            'global_errors' => [],
            'created' => 0,
            'updated' => 0,
            'valid' => false,
            'uploaded_name' => $_FILES['import_file']['name'] ?? '',
            'summary' => $this->emptyImportSummary(),
            'error_summary' => $this->emptyImportErrorSummary(),
        ];

        if (!$defaultProfile) {
            $preview['global_errors'][] = 'Configura un perfil por defecto activo con clave usuario antes de cargar usuarios.';
        }

        $file = $_FILES['import_file'] ?? null;
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $preview['global_errors'][] = 'Sube el archivo Excel XLSX descargado desde esta pantalla.';
            return $preview;
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            $preview['global_errors'][] = 'Solo se permiten archivos Excel .xlsx. Descarga la data actual y trabaja sobre ese archivo.';
            return $preview;
        }

        $workbook = $this->readXlsxWorkbook((string) $file['tmp_name']);
        $sheetRows = $workbook['rows'];

        if (!$sheetRows) {
            $preview['global_errors'][] = 'El archivo no contiene filas legibles.';
            return $preview;
        }

        $headers = array_shift($sheetRows);
        if (!$headers || !$this->rowHasData($headers)) {
            $preview['global_errors'][] = 'El archivo no contiene encabezados.';
            return $preview;
        }

        $normalizedHeaders = [];
        foreach ($headers as $index => $header) {
            $normalizedHeaders[$this->normalizeHeader((string) $header)] = $index;
        }

        $requiredHeaders = array_merge(self::CORE_IMPORT_HEADERS, array_map(
            static fn(array $field): string => $field['field_key'],
            $fieldDefinitions
        ));

        $meta = $workbook['meta'];
        if (($meta['template'] ?? '') !== self::IMPORT_TEMPLATE_KEY || ($meta['version'] ?? '') !== self::IMPORT_TEMPLATE_VERSION) {
            $preview['global_errors'][] = 'El archivo no corresponde a la plantilla oficial de carga masiva. Descarga la data existente y trabaja sobre ese archivo.';
        } elseif (($meta['headers_signature'] ?? '') !== $this->headersSignature($requiredHeaders)) {
            $preview['global_errors'][] = 'La plantilla no coincide con la configuracion actual de campos de usuario. Descarga nuevamente la data existente.';
        }

        foreach ($requiredHeaders as $header) {
            if (!array_key_exists($this->normalizeHeader($header), $normalizedHeaders)) {
                $preview['global_errors'][] = 'Falta la columna ' . $header . ' en el archivo.';
            }
        }

        $rowNumber = 1;
        $rows = [];

        foreach ($sheetRows as $values) {
            $rowNumber++;
            if (!$this->rowHasData($values)) {
                continue;
            }

            $rows[] = $this->buildImportRowFromCells($rowNumber, $values, $normalizedHeaders, $fieldDefinitions, $defaultProfile);
        }

        return $this->validateImportRows($rows, $fieldDefinitions, $defaultProfile, $preview);
    }

    private function validateCorrectedRows(array $fieldDefinitions, ?array $defaultProfile): array
    {
        $preview = [
            'rows' => [],
            'global_errors' => [],
            'created' => 0,
            'updated' => 0,
            'valid' => false,
            'uploaded_name' => $_SESSION['user_import_preview']['uploaded_name'] ?? 'Correcciones en pantalla',
            'summary' => $this->emptyImportSummary(),
            'error_summary' => $this->emptyImportErrorSummary(),
        ];

        if (!$defaultProfile) {
            $preview['global_errors'][] = 'Configura un perfil por defecto activo con clave usuario antes de cargar usuarios.';
        }

        $rows = [];
        foreach (($_POST['rows'] ?? []) as $index => $postedRow) {
            $fieldValues = [];
            foreach ($fieldDefinitions as $field) {
                $fieldId = (int) $field['id'];
                $fieldValues[$fieldId] = trim((string) (($postedRow['field_values'] ?? [])[$fieldId] ?? ''));
            }

            $rows[] = [
                'row_number' => (int) ($postedRow['row_number'] ?? ((int) $index + 2)),
                'action' => 'create',
                'data' => [
                    'rut' => UserModel::formatRut(trim((string) (($postedRow['data'] ?? [])['rut'] ?? ''))),
                    'role' => 'usuario',
                    'first_names' => trim((string) (($postedRow['data'] ?? [])['first_names'] ?? '')),
                    'last_names' => trim((string) (($postedRow['data'] ?? [])['last_names'] ?? '')),
                    'email' => trim((string) (($postedRow['data'] ?? [])['email'] ?? '')),
                    'sex' => $this->normalizeSex(trim((string) (($postedRow['data'] ?? [])['sex'] ?? ''))),
                    'birth_date' => $this->normalizeBirthDateInput(trim((string) (($postedRow['data'] ?? [])['birth_date'] ?? ''))),
                    'age' => trim((string) (($postedRow['data'] ?? [])['age'] ?? '')),
                    'password' => trim((string) (($postedRow['data'] ?? [])['password'] ?? '')),
                    'profile_id' => (int) ($defaultProfile['id'] ?? 0),
                    'company_id' => 0,
                    'company_name' => trim((string) (($postedRow['data'] ?? [])['company_name'] ?? '')),
                    'is_active' => $this->parseActive((string) (($postedRow['data'] ?? [])['is_active'] ?? '1')),
                ],
                'password_from_rut' => false,
                'password_unchanged' => false,
                'field_values' => $fieldValues,
                'errors' => [],
                'field_errors' => [],
            ];
        }

        return $this->validateImportRows($rows, $fieldDefinitions, $defaultProfile, $preview);
    }

    private function recalculateImportAges(array $fieldDefinitions, ?array $defaultProfile): array
    {
        $previousPreview = $_SESSION['user_import_preview'] ?? null;
        if (!$previousPreview || !is_array($previousPreview['rows'] ?? null)) {
            $preview = $this->importPreviewBase('Recalculo de edades');
            $preview['global_errors'][] = 'Primero valida un archivo para poder recalcular las edades.';
            return $this->appendImportErrorSummary($preview, $fieldDefinitions);
        }

        $rows = $previousPreview['rows'];
        $changed = 0;

        foreach ($rows as &$row) {
            $birthDate = $this->normalizeBirthDateInput((string) ($row['data']['birth_date'] ?? ''));
            if (!$this->isValidBirthDate($birthDate)) {
                continue;
            }

            $expectedAge = UserModel::calculateAge($birthDate);
            if ($this->isValidAge((string) ($row['data']['age'] ?? ''), $expectedAge)) {
                continue;
            }

            $row['data']['birth_date'] = $birthDate;
            $row['data']['age'] = (string) $expectedAge;
            $changed++;
        }
        unset($row);

        $preview = $this->validateImportRows(
            $rows,
            $fieldDefinitions,
            $defaultProfile,
            $this->importPreviewBase((string) ($previousPreview['uploaded_name'] ?? 'Archivo validado'))
        );
        $preview['age_recalculated'] = $changed;

        if ($changed > 0) {
            flash('success', 'Edades recalculadas: ' . $changed . ' fila(s). Se revalido la previsualizacion completa.');
        } else {
            flash('warning', 'No se encontraron edades recalculables con fecha de nacimiento valida.');
        }

        return $preview;
    }

    private function applyImport(array $fieldDefinitions): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $preview = $_SESSION['user_import_preview'] ?? null;
        if (!$preview || empty($preview['valid'])) {
            flash('danger', 'Primero valida un archivo sin errores.');
            redirect(route_url('user.import'));
        }

        try {
            $rows = $preview['rows'];
            if (is_company_admin_user() || (has_permission('manage_company_users') && !has_permission('manage_users'))) {
                $companyId = (int) (current_user()['company_id'] ?? 0);
                foreach ($rows as &$row) {
                    $row['data']['company_id'] = $companyId;
                    $row['data']['role'] = 'usuario';
                }
                unset($row);
            }
            $result = $this->users->importUsers($rows, $fieldDefinitions);
            unset($_SESSION['user_import_preview']);
            flash('success', 'Carga aplicada: ' . (int) $result['created'] . ' usuarios creados y ' . (int) $result['updated'] . ' actualizados.');
            redirect(route_url('users'));
        } catch (Throwable $exception) {
            flash('danger', 'No se pudo aplicar la carga. Revisa el archivo y vuelve a validar.');
            redirect(route_url('user.import'));
        }
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim(str_replace("\xEF\xBB\xBF", '', $header));
        $header = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header) ?: $header);
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $header);
        return $normalized === null ? '' : $normalized;
    }

    private function validateCoreUserData(array $data, ?int $existingId = null): array
    {
        $errors = [];
        $isCompanyAdmin = is_company_admin_user() || (has_permission('manage_company_users') && !has_permission('manage_users'));
        if (!in_array($data['role'] ?? '', UserModel::ALLOWED_ROLES, true)) {
            $errors[] = 'Selecciona un tipo de usuario valido.';
        }

        if (!UserModel::isValidRut($data['rut'] ?? '')) {
            $errors[] = 'Ingresa un RUT valido.';
        } elseif ($this->users->rutExists($data['rut'], $existingId)) {
            $errors[] = 'Ya existe un usuario con ese RUT.';
        }

        if (trim((string) ($data['first_names'] ?? '')) === '') {
            $errors[] = 'Ingresa los nombres.';
        }

        if (trim((string) ($data['last_names'] ?? '')) === '') {
            $errors[] = 'Ingresa los apellidos.';
        }

        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ingresa un correo valido.';
        } elseif ($this->users->emailExists($data['email'], $existingId)) {
            $errors[] = 'Ya existe un usuario con ese correo.';
        }

        if (!in_array($data['sex'] ?? '', UserModel::ALLOWED_SEXES, true)) {
            $errors[] = 'Selecciona una opcion valida para sexo.';
        }

        if (!$this->isValidBirthDate((string) ($data['birth_date'] ?? ''))) {
            $errors[] = 'Ingresa una fecha de nacimiento valida, no mayor a hoy.';
        } else {
            $expectedAge = UserModel::calculateAge((string) $data['birth_date']);
            if (!$this->isValidAge((string) ($data['age'] ?? ''), $expectedAge)) {
                $errors[] = 'La edad debe coincidir con la fecha de nacimiento.';
            } else {
                $data['age'] = $expectedAge;
            }
        }

        if ((int) ($data['profile_id'] ?? 0) <= 0) {
            $errors[] = 'Selecciona un perfil.';
        } else {
            $profile = $this->profiles->find((int) $data['profile_id']);
            $profileRole = $profile ? ProfileModel::baseRoleForProfile($profile) : '';
            if (!$profile || (int) ($profile['is_active'] ?? 0) !== 1 || ($isCompanyAdmin && $profileRole !== 'usuario')) {
                $errors[] = 'Selecciona un perfil activo.';
            }
        }

        if (($data['role'] ?? '') === 'usuario' && (int) ($data['company_id'] ?? 0) <= 0) {
            $errors[] = 'Selecciona una empresa.';
        }

        return $errors;
    }

    private function activeProfilesForUserForm(): array
    {
        $profiles = array_map(static function (array $profile): array {
            $profile['base_role'] = ProfileModel::baseRoleForProfile($profile);
            return $profile;
        }, $this->profiles->active());

        if (is_company_admin_user() || (has_permission('manage_company_users') && !has_permission('manage_users'))) {
            $profiles = array_values(array_filter($profiles, static function (array $profile): bool {
                return (string) ($profile['role_key'] ?? '') === 'usuario';
            }));
        }

        return $profiles;
    }

    private function normalizeRole(string $value): string
    {
        $role = $this->normalizeHeader($value);
        if ($role === '') {
            return 'usuario';
        }
        if (in_array($role, ['administrador', 'administrator'], true)) {
            return 'admin';
        }
        if (in_array($role, ['operador', 'agent'], true)) {
            return 'agente';
        }

        return in_array($role, UserModel::ALLOWED_ROLES, true) ? $role : '';
    }

    private function isValidBirthDate(string $value): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return false;
        }

        $date->setTime(0, 0);
        $today = new DateTime('today');
        return $date <= $today;
    }

    private function normalizeBirthDateInput(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $value) === 1) {
            return str_replace('/', '-', $value);
        }

        return $value;
    }

    private function isValidAge(string $value, ?int $expectedAge): bool
    {
        $value = trim($value);
        if ($expectedAge === null || $value === '' || !ctype_digit($value)) {
            return false;
        }

        return (int) $value === $expectedAge;
    }

    private function normalizeSex(string $value): string
    {
        $normalized = $this->normalizeHeader($value);
        if ($normalized === '') {
            return 'no_informado';
        }
        if (in_array($normalized, ['m', 'masculino', 'hombre'], true)) {
            return 'masculino';
        }
        if (in_array($normalized, ['f', 'femenino', 'mujer'], true)) {
            return 'femenino';
        }
        if (in_array($normalized, ['no_ingresado', 'no ingresado', 'no_informado', 'no informado', 'sin informar'], true)) {
            return 'no_informado';
        }

        return $normalized;
    }

    private function buildImportRowFromCells(int $rowNumber, array $values, array $headers, array $fieldDefinitions, ?array $defaultProfile): array
    {
        $row = [
            'row_number' => $rowNumber,
            'action' => 'create',
            'data' => [
                'rut' => UserModel::formatRut($this->cell($values, $headers, 'rut')),
                'role' => 'usuario',
                'first_names' => $this->cell($values, $headers, 'nombres'),
                'last_names' => $this->cell($values, $headers, 'apellidos'),
                'email' => $this->cell($values, $headers, 'correo'),
                'sex' => $this->normalizeSex($this->cell($values, $headers, 'sexo')),
                'birth_date' => $this->normalizeBirthDateInput($this->cell($values, $headers, 'fecha_nacimiento')),
                'age' => $this->cell($values, $headers, 'edad'),
                'password' => $this->cell($values, $headers, 'contrasena') ?: $this->cell($values, $headers, 'password'),
                'profile_id' => (int) ($defaultProfile['id'] ?? 0),
                'company_id' => 0,
                'company_name' => $this->cell($values, $headers, 'empresa'),
                'is_active' => $this->parseActive($this->cell($values, $headers, 'activo')),
            ],
            'password_from_rut' => false,
            'field_values' => [],
            'errors' => [],
            'field_errors' => [],
        ];

        foreach ($fieldDefinitions as $field) {
            $fieldId = (int) $field['id'];
            $rawValue = $this->cell($values, $headers, (string) $field['field_key']);
            $row['field_values'][$fieldId] = $field['field_type'] === 'checkbox'
                ? (string) $this->parseActive($rawValue)
                : $rawValue;
        }

        return $row;
    }

    private function validateImportRows(array $rows, array $fieldDefinitions, ?array $defaultProfile, array $preview): array
    {
        $companiesByName = $this->users->companiesByName();
        $ruts = [];
        $emails = [];
        foreach ($rows as $candidateRow) {
            $rut = $candidateRow['data']['rut'] ?? '';
            $email = $candidateRow['data']['email'] ?? '';
            if (UserModel::isValidRut($rut)) {
                $ruts[] = UserModel::formatRut($rut);
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = mb_strtolower(trim((string) $email));
            }
        }
        $existingByRut = [];
        $existingByEmail = [];
        foreach ($this->users->findUsersByRutsAndEmails($ruts, $emails) as $existingUser) {
            $existingByRut[UserModel::formatRut((string) ($existingUser['rut'] ?? ''))] = $existingUser;
            $existingByEmail[mb_strtolower(trim((string) ($existingUser['email'] ?? '')))] = $existingUser;
        }
        $seenEmails = [];
        $seenRuts = [];
        foreach ($rows as $row) {
            $row['errors'] = [];
            $row['field_errors'] = [];
            $rutKey = UserModel::formatRut($row['data']['rut'] ?? '');
            $emailKey = mb_strtolower(trim((string) ($row['data']['email'] ?? '')));
            $existing = UserModel::isValidRut($rutKey) ? ($existingByRut[$rutKey] ?? null) : null;
            $existing = $existing ?: (filter_var($row['data']['email'] ?? '', FILTER_VALIDATE_EMAIL) ? ($existingByEmail[$emailKey] ?? null) : null);
            $row['action'] = $existing ? 'update' : 'create';
            $row['existing_id'] = $existing ? (int) $existing['id'] : null;
            $role = 'usuario';
            $profileId = (int) ($defaultProfile['id'] ?? 0);

            if ($existing) {
                $role = (string) ($existing['role'] ?? 'usuario');
                $profileId = (int) ($existing['profile_id'] ?? 0);
            }

            $row['data']['role'] = $role;
            $row['data']['profile_id'] = $profileId;
            $row['data']['company_id'] = 0;

            if ($profileId <= 0) {
                $this->addImportError($row, 'profile_id', 'No existe un perfil por defecto activo para usuarios. Configura un perfil de usuario antes de cargar.');
            }

            $rutKey = UserModel::formatRut($row['data']['rut'] ?? '');
            if (!UserModel::isValidRut($rutKey)) {
                $this->addImportError($row, 'rut', 'RUT no valido. Formato esperado: 12.345.678-5, con digito verificador correcto.');
            } elseif (isset($seenRuts[$rutKey])) {
                $this->addImportError($row, 'rut', 'RUT duplicado dentro del archivo. Deja solo una fila por persona.');
            } else {
                $seenRuts[$rutKey] = true;
            }

            if (trim((string) ($row['data']['first_names'] ?? '')) === '') {
                $this->addImportError($row, 'first_names', 'Nombres obligatorios. Completa uno o mas nombres de la persona.');
            }
            if (trim((string) ($row['data']['last_names'] ?? '')) === '') {
                $this->addImportError($row, 'last_names', 'Apellidos obligatorios. Completa los apellidos de la persona.');
            }

            $emailKey = mb_strtolower($row['data']['email']);
            if (!filter_var($row['data']['email'], FILTER_VALIDATE_EMAIL)) {
                $this->addImportError($row, 'email', 'Correo no valido. Formato esperado: nombre@dominio.cl.');
            } elseif (isset($seenEmails[$emailKey])) {
                $this->addImportError($row, 'email', 'Correo duplicado dentro del archivo. Usa un correo unico por persona.');
            } else {
                $seenEmails[$emailKey] = true;
            }

            if (!in_array($row['data']['sex'] ?? '', UserModel::ALLOWED_SEXES, true)) {
                $this->addImportError($row, 'sex', 'Sexo no valido. Valores permitidos: masculino, femenino o no informado.');
            }

            $row['data']['birth_date'] = $this->normalizeBirthDateInput((string) ($row['data']['birth_date'] ?? ''));
            if (!$this->isValidBirthDate((string) ($row['data']['birth_date'] ?? ''))) {
                $this->addImportError($row, 'birth_date', 'Fecha de nacimiento no valida. Formato esperado: AAAA-MM-DD, y no puede ser mayor a hoy.');
            } else {
                $expectedAge = UserModel::calculateAge((string) $row['data']['birth_date']);
                if (!$this->isValidAge((string) ($row['data']['age'] ?? ''), $expectedAge)) {
                    $this->addImportError($row, 'age', 'Edad no coincide con la fecha de nacimiento. Debe ser ' . (string) $expectedAge . ' para la fecha indicada.');
                } else {
                    $row['data']['age'] = $expectedAge;
                }
            }

            $companyKey = mb_strtolower(trim((string) ($row['data']['company_name'] ?? '')));
            $isCompanyAdmin = is_company_admin_user() || (has_permission('manage_company_users') && !has_permission('manage_users'));
            if ($isCompanyAdmin) {
                $companyId = (int) (current_user()['company_id'] ?? 0);
                if ($companyId <= 0) {
                    $this->addImportError($row, 'company_name', 'No se pudo determinar la empresa del Administrador Clientes.');
                } else {
                    // La empresa del administrador es la autoridad del contexto; nunca se toma del Excel.
                    $row['data']['company_id'] = $companyId;
                }
            } elseif ($role === 'usuario') {
                if ($companyKey === '' || !isset($companiesByName[$companyKey])) {
                    $this->addImportError($row, 'company_name', 'Empresa no existe o esta inactiva. Debe coincidir exactamente con una empresa activa de la plataforma.');
                } else {
                    $row['data']['company_id'] = (int) $companiesByName[$companyKey]['id'];
                }
            } elseif ($companyKey !== '' && isset($companiesByName[$companyKey])) {
                $row['data']['company_id'] = (int) $companiesByName[$companyKey]['id'];
            }

            $existingId = $existing ? (int) $existing['id'] : null;
            if (UserModel::isValidRut($row['data']['rut'] ?? '') && $this->users->rutExists($row['data']['rut'], $existingId)) {
                $this->addImportError($row, 'rut', 'Ya existe otro usuario con ese RUT. Revisa si corresponde actualizar esa persona o corregir el RUT.');
            }
            if (filter_var($row['data']['email'], FILTER_VALIDATE_EMAIL) && $this->users->emailExists($row['data']['email'], $existingId)) {
                $this->addImportError($row, 'email', 'Ya existe otro usuario con ese correo. Corrige el correo o usa el registro existente.');
            }

            if ($existing && $row['data']['password'] === '') {
                $row['password_unchanged'] = true;
            } elseif ($row['data']['password'] === '' && UserModel::isValidRut($row['data']['rut'] ?? '')) {
                $row['data']['password'] = UserModel::rutDefaultPassword($row['data']['rut']);
                $row['password_from_rut'] = true;
            }

            foreach ($fieldDefinitions as $field) {
                $fieldId = (int) $field['id'];
                $value = trim((string) ($row['field_values'][$fieldId] ?? ''));
                if ($field['field_type'] === 'checkbox') {
                    $row['field_values'][$fieldId] = (string) $this->parseActive($value);
                    continue;
                }

                if ((int) $field['is_required'] === 1 && $value === '') {
                    $this->addImportError($row, 'field_' . $fieldId, 'El campo ' . $field['label'] . ' es obligatorio. Completa este dato antes de cargar.');
                    continue;
                }

                if ($value === '') {
                    continue;
                }

                $formatError = $this->fields->validateFieldValue($field, $value);
                if ($formatError) {
                    $this->addImportError($row, 'field_' . $fieldId, $formatError);
                }
            }

            $row['errors'] = array_values(array_unique($row['errors']));
            $row['action'] === 'update' ? $preview['updated']++ : $preview['created']++;
            $preview['summary']['processed']++;
            (int) $row['data']['is_active'] === 1 ? $preview['summary']['enabled']++ : $preview['summary']['disabled']++;
            $row['errors'] ? $preview['summary']['with_errors']++ : $preview['summary']['without_errors']++;
            $preview['rows'][] = $row;
        }

        if (!$preview['rows']) {
            $preview['global_errors'][] = 'El archivo no contiene filas de usuarios.';
        }

        $hasRowErrors = array_reduce($preview['rows'], static fn(bool $carry, array $row): bool => $carry || (bool) $row['errors'], false);
        $preview['valid'] = !$preview['global_errors'] && !$hasRowErrors;

        return $this->appendImportErrorSummary($preview, $fieldDefinitions);
    }

    private function emptyImportSummary(): array
    {
        return [
            'processed' => 0,
            'enabled' => 0,
            'disabled' => 0,
            'with_errors' => 0,
            'without_errors' => 0,
            'total_errors' => 0,
            'age_errors' => 0,
        ];
    }

    private function emptyImportErrorSummary(): array
    {
        return [
            'total_errors' => 0,
            'age_errors' => 0,
            'by_field' => [],
            'by_message' => [],
        ];
    }

    private function appendImportErrorSummary(array $preview, array $fieldDefinitions): array
    {
        $errorSummary = $this->summarizeImportErrors(is_array($preview['rows'] ?? null) ? $preview['rows'] : [], $fieldDefinitions);
        $preview['error_summary'] = $errorSummary;
        $preview['summary']['total_errors'] = $errorSummary['total_errors'];
        $preview['summary']['age_errors'] = $errorSummary['age_errors'];

        return $preview;
    }

    private function summarizeImportErrors(array $rows, array $fieldDefinitions): array
    {
        $labels = [
            'profile_id' => 'Perfil',
            'rut' => 'RUT',
            'first_names' => 'Nombres',
            'last_names' => 'Apellidos',
            'email' => 'Correo',
            'sex' => 'Sexo',
            'birth_date' => 'Fecha nacimiento',
            'age' => 'Edad',
            'company_name' => 'Empresa',
            'password' => 'Contrasena',
            'is_active' => 'Activo',
        ];

        foreach ($fieldDefinitions as $field) {
            $labels['field_' . (int) $field['id']] = (string) ($field['label'] ?? $field['field_key'] ?? 'Campo adicional');
        }

        $byField = [];
        $byMessage = [];
        $totalErrors = 0;
        $ageErrors = 0;

        foreach ($rows as $row) {
            $rowHadAgeError = false;
            foreach (($row['field_errors'] ?? []) as $field => $messages) {
                $count = count((array) $messages);
                if ($count <= 0) {
                    continue;
                }

                $label = $labels[$field] ?? labelize((string) $field);
                $byField[$label] = ($byField[$label] ?? 0) + $count;
                if ($field === 'age') {
                    $rowHadAgeError = true;
                }
            }

            foreach (($row['errors'] ?? []) as $message) {
                $message = (string) $message;
                if ($message === '') {
                    continue;
                }

                $totalErrors++;
                $byMessage[$message] = ($byMessage[$message] ?? 0) + 1;
            }

            if ($rowHadAgeError) {
                $ageErrors++;
            }
        }

        arsort($byField);
        arsort($byMessage);

        $fieldRows = [];
        foreach ($byField as $label => $count) {
            $fieldRows[] = ['label' => $label, 'count' => (int) $count];
        }

        $messageRows = [];
        foreach ($byMessage as $message => $count) {
            $messageRows[] = ['message' => $message, 'count' => (int) $count];
        }

        return [
            'total_errors' => $totalErrors,
            'age_errors' => $ageErrors,
            'by_field' => $fieldRows,
            'by_message' => array_slice($messageRows, 0, 6),
        ];
    }

    private function addImportError(array &$row, string $field, string $message): void
    {
        $row['errors'][] = $message;
        $row['field_errors'][$field][] = $message;
    }

    private function readXlsxWorkbook(string $path): array
    {
        $empty = ['rows' => [], 'meta' => []];
        if (!class_exists(IOFactory::class)) {
            return $empty;
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $exception) {
            return $empty;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        if ($highestRow < 1 || ($highestColumn === 'A' && trim((string) $sheet->getCell('A1')->getFormattedValue()) === '')) {
            $spreadsheet->disconnectWorksheets();
            return $empty;
        }

        $rows = [];
        foreach ($sheet->rangeToArray('A1:' . $highestColumn . $highestRow, '', true, true, false) as $row) {
            $rows[] = array_map(static fn($value): string => trim((string) $value), $row);
        }

        $meta = [];
        $metaSheet = $spreadsheet->getSheetByName('_import_meta');
        if ($metaSheet) {
            $metaRows = $metaSheet->rangeToArray('A1:B10', '', true, true, false);
            foreach ($metaRows as $metaRow) {
                $key = trim((string) ($metaRow[0] ?? ''));
                if ($key !== '') {
                    $meta[$key] = trim((string) ($metaRow[1] ?? ''));
                }
            }
        }

        $spreadsheet->disconnectWorksheets();
        return ['rows' => $rows, 'meta' => $meta];
    }

    private function downloadXlsx(string $filename, array $rows): void
    {
        if (!class_exists(Spreadsheet::class)) {
            platform_error(500, 'PhpSpreadsheet es requerido para generar Excel.', [
                'log' => true,
                'chips' => ['Excel', 'Dependencia'],
            ]);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'usuarios_xlsx_');
        if ($tmpPath === false) {
            platform_error(500, 'No se pudo generar el archivo Excel.', [
                'log' => true,
                'chips' => ['Excel', 'Archivo temporal'],
            ]);
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('e-talent')
            ->setLastModifiedBy('e-talent')
            ->setTitle('Usuarios carga masiva');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Usuarios');
        $headers = $rows[0] ?? [];

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueExplicitByColumnAndRow(
                    $columnIndex + 1,
                    $rowIndex + 1,
                    (string) $value,
                    DataType::TYPE_STRING
                );
            }
        }

        if ($rows) {
            $lastColumn = count($rows[0]);
            $lastRow = count($rows);
            $sheet->getStyleByColumnAndRow(1, 1, $lastColumn, 1)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 1, $lastColumn, 1)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE8F0FE');
            $sheet->setAutoFilterByColumnAndRow(1, 1, $lastColumn, max(1, $lastRow));
            for ($column = 1; $column <= $lastColumn; $column++) {
                $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
            }
            $sheet->freezePane('A2');
        }

        $metaSheet = $spreadsheet->createSheet();
        $metaSheet->setTitle('_import_meta');
        $metaSheet->setCellValueExplicit('A1', 'template', DataType::TYPE_STRING);
        $metaSheet->setCellValueExplicit('B1', self::IMPORT_TEMPLATE_KEY, DataType::TYPE_STRING);
        $metaSheet->setCellValueExplicit('A2', 'version', DataType::TYPE_STRING);
        $metaSheet->setCellValueExplicit('B2', self::IMPORT_TEMPLATE_VERSION, DataType::TYPE_STRING);
        $metaSheet->setCellValueExplicit('A3', 'headers_signature', DataType::TYPE_STRING);
        $metaSheet->setCellValueExplicit('B3', $this->headersSignature($headers), DataType::TYPE_STRING);
        $metaSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($tmpPath);
        $spreadsheet->disconnectWorksheets();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpPath));
        readfile($tmpPath);
        @unlink($tmpPath);
    }

    private function cell(array $values, array $headers, string $header): string
    {
        $index = $headers[$this->normalizeHeader($header)] ?? null;
        return $index === null ? '' : trim((string) ($values[$index] ?? ''));
    }

    private function headersSignature(array $headers): string
    {
        return hash('sha256', implode('|', array_map(fn($header): string => $this->normalizeHeader((string) $header), $headers)));
    }

    private function rowHasData(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function parseActive(string $value): int
    {
        $normalized = $this->normalizeHeader($value);
        if ($normalized === '') {
            return 1;
        }

        return in_array($normalized, ['1', 'si', 's', 'yes', 'y', 'true', 'activo', 'activa'], true) ? 1 : 0;
    }
}
