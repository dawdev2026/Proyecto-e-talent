<?php
declare(strict_types=1);

final class UserFieldModel
{
    private Database $db;

    public const TYPES = [
        'text' => 'Texto',
        'email' => 'Correo',
        'phone' => 'Telefono',
        'number' => 'Numero',
        'date' => 'Fecha',
        'select' => 'Lista desplegable',
        'textarea' => 'Texto largo',
        'checkbox' => 'Si / No',
    ];

    public const VALIDATION_RULES = [
        'by_type' => 'Segun tipo de campo',
        'none' => 'Sin validacion de formato',
        'email' => 'Correo valido',
        'phone' => 'Telefono',
        'integer' => 'Numero entero',
        'decimal' => 'Numero decimal',
        'date' => 'Fecha',
        'options' => 'Debe estar en opciones',
        'regex' => 'Patron personalizado',
    ];

    public const SCOPES = [
        'core:core' => 'Core de usuarios',
        'platform:tests' => 'Evaluaciones',
    ];

    private const RESERVED_CORE_KEYS = [
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

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database();
    }

    public function all(): array
    {
        return $this->db->fetchAll('
            SELECT *
            FROM user_field_definitions
            WHERE ' . $this->notReservedSql() . '
            ORDER BY scope_type ASC, scope_key ASC, sort_order ASC, label ASC
        ');
    }

    public function active(string $scopeType = 'core', string $scopeKey = 'core'): array
    {
        return $this->db->fetchAll('
            SELECT *
            FROM user_field_definitions
            WHERE is_active = 1
              AND scope_type = ?
              AND scope_key = ?
              AND ' . $this->notReservedSql() . '
            ORDER BY sort_order ASC, label ASC
        ', [$scopeType, $scopeKey]);
    }

    public function activeForUsers(): array
    {
        return $this->db->fetchAll('
            SELECT *
            FROM user_field_definitions
            WHERE is_active = 1
              AND ' . $this->notReservedSql() . '
            ORDER BY sort_order ASC, label ASC, scope_type ASC, scope_key ASC
        ');
    }

    public function listable(string $scopeType = 'core', string $scopeKey = 'core'): array
    {
        return $this->db->fetchAll('
            SELECT *
            FROM user_field_definitions
            WHERE is_active = 1
              AND show_in_list = 1
              AND scope_type = ?
              AND scope_key = ?
              AND ' . $this->notReservedSql() . '
            ORDER BY sort_order ASC, label ASC
        ', [$scopeType, $scopeKey]);
    }

    public function listableForUsers(): array
    {
        return $this->db->fetchAll('
            SELECT *
            FROM user_field_definitions
            WHERE is_active = 1
              AND show_in_list = 1
              AND ' . $this->notReservedSql() . '
            ORDER BY sort_order ASC, label ASC, scope_type ASC, scope_key ASC
        ');
    }

    private function notReservedSql(): string
    {
        return "field_key NOT IN ('" . implode("','", self::RESERVED_CORE_KEYS) . "')";
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM user_field_definitions WHERE id = ? LIMIT 1', [$id]);
    }

    public function fieldKeyExists(string $fieldKey, ?int $excludeId = null): bool
    {
        $params = [$fieldKey];
        $sql = 'SELECT id FROM user_field_definitions WHERE field_key = ?';
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        return (bool) $this->db->fetch($sql . ' LIMIT 1', $params);
    }

    public function create(array $data): int
    {
        return $this->db->insert('
            INSERT INTO user_field_definitions (scope_type, scope_key, field_key, label, field_type, validation_rule, validation_pattern, validation_message, options, help_text, is_required, show_in_list, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ', [
            $data['scope_type'],
            $data['scope_key'],
            $data['field_key'],
            $data['label'],
            $data['field_type'],
            $data['validation_rule'],
            $data['validation_pattern'] ?: null,
            $data['validation_message'] ?: null,
            $data['options'] ?: null,
            $data['help_text'] ?: null,
            (int) $data['is_required'],
            (int) $data['show_in_list'],
            (int) $data['sort_order'],
            (int) $data['is_active'],
        ]);
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute('
            UPDATE user_field_definitions
            SET scope_type = ?, scope_key = ?, field_key = ?, label = ?, field_type = ?, validation_rule = ?, validation_pattern = ?, validation_message = ?, options = ?, help_text = ?, is_required = ?, show_in_list = ?, sort_order = ?, is_active = ?
            WHERE id = ?
        ', [
            $data['scope_type'],
            $data['scope_key'],
            $data['field_key'],
            $data['label'],
            $data['field_type'],
            $data['validation_rule'],
            $data['validation_pattern'] ?: null,
            $data['validation_message'] ?: null,
            $data['options'] ?: null,
            $data['help_text'] ?: null,
            (int) $data['is_required'],
            (int) $data['show_in_list'],
            (int) $data['sort_order'],
            (int) $data['is_active'],
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM user_field_definitions WHERE id = ?', [$id]);
    }

    public function valuesForUser(int $userId): array
    {
        $rows = $this->db->fetchAll('SELECT field_id, value FROM user_field_values WHERE user_id = ?', [$userId]);
        $values = [];
        foreach ($rows as $row) {
            $values[(int) $row['field_id']] = (string) $row['value'];
        }

        return $values;
    }

    public function valuesForUsers(array $userIds, array $fields): array
    {
        if (!$userIds || !$fields) {
            return [];
        }

        $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $fieldIds = array_map(static fn(array $field): int => (int) $field['id'], $fields);
        $fieldPlaceholders = implode(',', array_fill(0, count($fieldIds), '?'));

        $rows = $this->db->fetchAll("
            SELECT user_id, field_id, value
            FROM user_field_values
            WHERE user_id IN ({$userPlaceholders}) AND field_id IN ({$fieldPlaceholders})
        ", array_merge($userIds, $fieldIds));

        $values = [];
        foreach ($rows as $row) {
            $values[(int) $row['user_id']][(int) $row['field_id']] = (string) $row['value'];
        }

        return $values;
    }

    public function validateValues(array $fields, array $values): array
    {
        $errors = [];
        foreach ($fields as $field) {
            $value = trim((string) ($values[(int) $field['id']] ?? ''));
            if ((int) $field['is_required'] === 1 && $value === '') {
                $errors[] = 'El campo ' . $field['label'] . ' es obligatorio.';
                continue;
            }

            if ($value === '') {
                continue;
            }

            $formatError = $this->validateFieldValue($field, $value);
            if ($formatError) {
                $errors[] = $formatError;
            }
        }

        return $errors;
    }

    public function validateFieldValue(array $field, string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $rule = $this->effectiveValidationRule($field);
        if ($rule === 'none') {
            return null;
        }

        $valid = true;
        if ($rule === 'email') {
            $valid = (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
        } elseif ($rule === 'phone') {
            $valid = (bool) preg_match('/^\+?[0-9][0-9\s().-]{5,24}$/', $value);
        } elseif ($rule === 'integer') {
            $valid = (bool) preg_match('/^-?\d+$/', $value);
        } elseif ($rule === 'decimal') {
            $valid = is_numeric(str_replace(',', '.', $value));
        } elseif ($rule === 'date') {
            $valid = $this->isValidDate($value);
        } elseif ($rule === 'options') {
            $valid = in_array($value, $this->optionList($field['options'] ?? ''), true);
        } elseif ($rule === 'regex') {
            $pattern = trim((string) ($field['validation_pattern'] ?? ''));
            $valid = $pattern !== '' && @preg_match($pattern, '') !== false && (bool) preg_match($pattern, $value);
        }

        if ($valid) {
            return null;
        }

        $customMessage = trim((string) ($field['validation_message'] ?? ''));
        if ($customMessage !== '') {
            return $customMessage;
        }

        return $this->defaultValidationMessage($field, $rule);
    }

    public function effectiveValidationRule(array $field): string
    {
        $rule = $field['validation_rule'] ?? 'by_type';
        if ($rule !== 'by_type') {
            return isset(self::VALIDATION_RULES[$rule]) ? $rule : 'none';
        }

        if ($field['field_type'] === 'email') {
            return 'email';
        }
        if ($field['field_type'] === 'phone') {
            return 'phone';
        }
        if ($field['field_type'] === 'number') {
            return 'decimal';
        }
        if ($field['field_type'] === 'date') {
            return 'date';
        }
        if ($field['field_type'] === 'select') {
            return 'options';
        }

        return 'none';
    }

    private function defaultValidationMessage(array $field, string $rule): string
    {
        $label = (string) $field['label'];
        $messages = [
            'email' => 'El campo ' . $label . ' debe ser un correo valido. Formato esperado: nombre@dominio.cl.',
            'phone' => 'El campo ' . $label . ' debe ser un telefono valido. Ejemplo: +56912345678.',
            'integer' => 'El campo ' . $label . ' debe ser un numero entero. Ejemplo: 35.',
            'decimal' => 'El campo ' . $label . ' debe ser numerico. Ejemplo: 35 o 35.5.',
            'date' => 'El campo ' . $label . ' debe ser una fecha valida. Formato esperado: AAAA-MM-DD.',
            'options' => 'El valor seleccionado para ' . $label . ' no es valido. Debe coincidir con una de las opciones configuradas.',
            'regex' => 'El campo ' . $label . ' no cumple la regla avanzada configurada. Revisa el formato indicado por administracion.',
        ];

        return $messages[$rule] ?? 'El campo ' . $label . ' no tiene un formato valido.';
    }

    private function isValidDate(string $value): bool
    {
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    public function saveValues(int $userId, array $fields, array $values): void
    {
        foreach ($fields as $field) {
            $fieldId = (int) $field['id'];
            $value = $field['field_type'] === 'checkbox'
                ? (in_array($values[$fieldId] ?? null, ['1', 1, true], true) ? '1' : '0')
                : trim((string) ($values[$fieldId] ?? ''));

            $this->db->execute('
                INSERT INTO user_field_values (user_id, field_id, value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ', [$userId, $fieldId, $value]);
        }
    }

    public function optionList(?string $options): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R+/', (string) $options))));
    }
}
