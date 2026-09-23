<?php
declare(strict_types=1);

final class UserFieldController extends Controller
{
    private const RESERVED_CORE_FIELDS = [
        'rut',
        'nombres',
        'apellidos',
        'correo',
        'email',
        'sexo',
        'fecha_nacimiento',
        'edad',
        'age',
        'password',
        'contrasena',
        'clave',
    ];

    private UserFieldModel $fields;

    public function __construct(?Template $view = null, ?UserFieldModel $fields = null)
    {
        parent::__construct($view);
        $this->fields = $fields ?: new UserFieldModel();
    }

    public function index(): void
    {
        require_company_user_field_management();

        $this->render('user_fields/index', [
            'title' => 'Campos de usuario | e-talent',
            'currentPage' => 'user-fields',
            'fields' => $this->fields->all(),
            'companyName' => (string) (current_user()['company_name'] ?? ''),
            'types' => UserFieldModel::TYPES,
            'validationRules' => UserFieldModel::VALIDATION_RULES,
            'scopes' => UserFieldModel::SCOPES,
        ]);
    }

    public function form(): void
    {
        require_company_user_field_management();

        $id = request_secure_id('user_field');
        $field = $id ? $this->fields->find($id) : null;

        if ($id && !$field) {
            platform_error(404, 'Campo no encontrado.', [
                'chips' => ['Campo de usuario'],
            ]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save($id);
        }

        $this->render('user_fields/form', [
            'title' => ($id ? 'Editar campo' : 'Nuevo campo') . ' | e-talent',
            'currentPage' => 'user-fields',
            'id' => $id,
            'types' => UserFieldModel::TYPES,
            'validationRules' => UserFieldModel::VALIDATION_RULES,
            'scopes' => UserFieldModel::SCOPES,
            'values' => $field ?: [
                'scope_type' => 'core',
                'scope_key' => 'core',
                'field_key' => '',
                'label' => '',
                'field_type' => 'text',
                'validation_rule' => 'by_type',
                'validation_pattern' => '',
                'validation_message' => '',
                'options' => '',
                'help_text' => '',
                'is_required' => 0,
                'show_in_list' => 0,
                'sort_order' => 100,
                'is_active' => 1,
            ],
        ]);
    }

    public function delete(): void
    {
        require_company_user_field_management();
        verify_csrf();

        $id = request_secure_id('user_field');
        $field = $id ? $this->fields->find($id) : null;

        if (!$field || in_array((string) ($field['field_key'] ?? ''), self::RESERVED_CORE_FIELDS, true)) {
            flash('danger', 'No se pudo eliminar el campo solicitado.');
            redirect(route_url('user-fields'));
        }

        try {
            $this->fields->delete($id);
            flash('success', 'Campo eliminado correctamente.');
        } catch (PDOException $exception) {
            flash('danger', 'No se pudo eliminar el campo. Intenta dejarlo inactivo si tiene dependencias.');
        }

        redirect(route_url('user-fields'));
    }

    private function save(int $id): void
    {
        verify_csrf();

        $data = [
            'company_id' => is_company_admin_user()
                ? (int) (current_user()['company_id'] ?? 0)
                : (has_permission('manage_company_user_fields') && !has_permission('manage_user_fields') ? (int) (current_user()['company_id'] ?? 0) : null),
            'scope' => $_POST['scope'] ?? 'core:core',
            'label' => trim($_POST['label'] ?? ''),
            'field_type' => $_POST['field_type'] ?? 'text',
            'validation_rule' => $_POST['validation_rule'] ?? 'by_type',
            'validation_pattern' => trim($_POST['validation_pattern'] ?? ''),
            'validation_message' => trim($_POST['validation_message'] ?? ''),
            'options' => trim($_POST['options'] ?? ''),
            'help_text' => trim($_POST['help_text'] ?? ''),
            'is_required' => isset($_POST['is_required']) ? 1 : 0,
            'show_in_list' => isset($_POST['show_in_list']) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 100),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        [$scopeType, $scopeKey] = $this->normalizeScope((string) $data['scope']);
        $data['scope_type'] = $scopeType;
        $data['scope_key'] = $scopeKey;
        unset($data['scope']);

        $postedFieldKey = trim($_POST['field_key'] ?? '');
        $data['field_key'] = $postedFieldKey !== ''
            ? $this->normalizeFieldKey($postedFieldKey)
            : $this->uniqueFieldKey($data['label'], $id ?: null, $data['scope_type'], $data['scope_key']);

        if (
            $data['field_key'] === ''
            || $data['label'] === ''
            || !isset(UserFieldModel::TYPES[$data['field_type']])
            || !isset(UserFieldModel::VALIDATION_RULES[$data['validation_rule']])
            || !isset(UserFieldModel::SCOPES[$data['scope_type'] . ':' . $data['scope_key']])
        ) {
            flash('danger', 'Indica el nombre del dato, el tipo de respuesta y como se debe revisar.');
            return;
        }

        if (in_array($data['field_key'], self::RESERVED_CORE_FIELDS, true)) {
            flash('danger', 'Ese dato ya pertenece al core del usuario y no se debe crear como campo extra.');
            return;
        }

        if ($this->fields->fieldKeyExists($data['field_key'], $id ?: null)) {
            flash('danger', 'Ya existe un campo extra con esa clave. Usa un nombre distinto para evitar columnas duplicadas en Excel.');
            return;
        }

        if (($data['field_type'] === 'select' || $data['validation_rule'] === 'options') && !$this->fields->optionList($data['options'])) {
            flash('danger', 'Agrega las opciones permitidas, una por linea.');
            return;
        }

        if ($data['validation_rule'] === 'regex' && ($data['validation_pattern'] === '' || @preg_match($data['validation_pattern'], '') === false)) {
            flash('danger', 'La regla avanzada no es valida. Revisa el patron configurado.');
            return;
        }

        try {
            if ($id) {
                $this->fields->update($id, $data);
                flash('success', 'Campo actualizado correctamente.');
            } else {
                $this->fields->create($data);
                flash('success', 'Campo creado correctamente.');
            }

            redirect(route_url('user-fields'));
        } catch (PDOException $exception) {
            flash('danger', 'No se pudo guardar el campo. Revisa si la clave ya existe.');
        }
    }

    private function uniqueFieldKey(string $label, ?int $excludeId, string $scopeType = 'core', string $scopeKey = 'core'): string
    {
        $base = $this->normalizeFieldKey($label);
        if ($base === '') {
            return '';
        }

        $candidate = $base;
        $suffix = 2;
        while ($this->fields->fieldKeyExists($candidate, $excludeId)) {
            $candidate = substr($base, 0, 55) . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function normalizeFieldKey(string $value): string
    {
        $value = trim($value);
        $value = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = trim((string) $value, '_');

        return substr($value, 0, 60);
    }

    private function normalizeScope(string $scope): array
    {
        if (!isset(UserFieldModel::SCOPES[$scope])) {
            return ['core', 'core'];
        }

        return explode(':', $scope, 2);
    }
}
