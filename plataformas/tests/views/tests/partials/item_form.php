<?php
if (!function_exists('test_prompt_for_editor')) {
    function test_prompt_for_editor(string $prompt): string
    {
        $prefix = '__html64__:';
        if (strpos($prompt, $prefix) === 0) {
            $decoded = base64_decode(substr($prompt, strlen($prefix)), true);
            return $decoded !== false ? $decoded : '';
        }

        if (preg_match('/<(p|br|img|strong|b|em|i|u|ul|ol|li)\b/i', $prompt)) {
            return html_entity_decode($prompt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '<p>' . nl2br(e($prompt)) . '</p>';
    }
}

$item = $item ?: [
    'id' => 0,
    'item_key' => '',
    'scale_id' => null,
    'prompt' => '',
    'item_type' => 'single_choice',
    'options' => '',
    'reverse_scored' => 0,
    'sort_order' => 100,
    'is_active' => 1,
];
?>

<form method="post" action="<?= e(route_url('test.content', (int) $instrument['id']) . '/item') ?>" class="drawer-form" data-drawer-form data-advanced-item-form>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">

    <div class="drawer-detail-heading">
        <p class="text-muted mb-0">La pregunta define el enunciado, sus alternativas visibles y las reglas que convierten respuestas en puntaje.</p>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <label class="form-label">Clave</label>
            <input class="form-control" name="item[item_key]" value="<?= e($item['item_key']) ?>" required>
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label">Escala principal</label>
            <select class="form-select" name="item[scale_id]">
                <option value="">Sin escala directa</option>
                <?php foreach ($scales as $scale): ?>
                    <option value="<?= (int) $scale['id'] ?>" <?= (int) ($item['scale_id'] ?? 0) === (int) $scale['id'] ? 'selected' : '' ?>><?= e($scale['scale_key']) ?> · <?= e($scale['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label">Tipo</label>
            <select class="form-select" name="item[item_type]">
                <?php foreach ($itemTypes as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $item['item_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-lg-4">
            <label class="form-label">Orden</label>
            <input class="form-control" type="number" name="item[sort_order]" value="<?= (int) $item['sort_order'] ?>">
        </div>
        <div class="col-6 col-lg-4 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="item[is_active]" <?= (int) $item['is_active'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label">Activo</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Enunciado</label>
            <textarea class="form-control test-prompt-editor" name="item[prompt]" data-item-prompt-editor rows="7" required><?= e(test_prompt_for_editor((string) $item['prompt'])) ?></textarea>
        </div>
        <div class="col-12">
            <div class="test-option-builder">
                <div class="test-option-builder-head">
                    <div><label class="form-label mb-1">Alternativas</label><div class="form-text mb-0">Define valor y texto visible. El puntaje se maneja en reglas.</div></div>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-add-advanced-option><i class="bi bi-plus-lg me-1"></i> Agregar alternativa</button>
                </div>
                <div class="test-option-list" data-advanced-option-list>
                    <?php foreach (array_filter(explode(';', (string) $item['options'])) ?: ['='] as $option): ?>
                        <?php [$value, $label] = array_pad(array_map('trim', explode('=', $option, 2)), 2, ''); ?>
                        <div class="test-option-row" data-advanced-option-row>
                            <div><label class="form-label">Valor</label><input class="form-control" data-advanced-option-value value="<?= e($value) ?>"></div>
                            <div><label class="form-label">Texto visible</label><input class="form-control" data-advanced-option-label value="<?= e($label) ?>"></div>
                            <div class="test-option-row-action"><button class="btn btn-sm btn-outline-danger" type="button" data-remove-advanced-option><i class="bi bi-trash"></i></button></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="item[options]" data-advanced-options-value value="<?= e($item['options'] ?? '') ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Reglas de valorizacion</label>
            <div class="table-responsive">
                <table class="table table-sm align-middle app-table">
                    <thead>
                        <tr>
                            <th>Escala</th>
                            <th>Tipo</th>
                            <th>Respuesta</th>
                            <th>Puntaje</th>
                            <th>Peso</th>
                            <th>Config</th>
                            <th>Activo</th>
                            <th>Eliminar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $rule): ?>
                            <tr>
                                <td><select class="form-select form-select-sm" name="rules[<?= (int) $rule['id'] ?>][scale_id]"><?php foreach ($scales as $scale): ?><option value="<?= (int) $scale['id'] ?>" <?= (int) $rule['scale_id'] === (int) $scale['id'] ? 'selected' : '' ?>><?= e($scale['scale_key']) ?></option><?php endforeach; ?></select></td>
                                <td><select class="form-select form-select-sm" name="rules[<?= (int) $rule['id'] ?>][rule_type]"><?php foreach (['direct' => 'Directa', 'reverse' => 'Inversa', 'keyed' => 'Clave', 'mapped' => 'Mapa'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $rule['rule_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
                                <td><input class="form-control form-control-sm" name="rules[<?= (int) $rule['id'] ?>][answer_value]" value="<?= e($rule['answer_value'] ?? '') ?>"></td>
                                <td><input class="form-control form-control-sm" name="rules[<?= (int) $rule['id'] ?>][score_value]" value="<?= e((string) ($rule['score_value'] ?? '')) ?>"></td>
                                <td><input class="form-control form-control-sm" name="rules[<?= (int) $rule['id'] ?>][weight]" value="<?= e((string) ($rule['weight'] ?? '1')) ?>"></td>
                                <td><input class="form-control form-control-sm" name="rules[<?= (int) $rule['id'] ?>][rule_config]" value="<?= e($rule['rule_config'] ?? '') ?>"></td>
                                <td><input class="form-check-input" type="checkbox" name="rules[<?= (int) $rule['id'] ?>][is_active]" <?= (int) $rule['is_active'] === 1 ? 'checked' : '' ?>></td>
                                <td><input class="form-check-input" type="checkbox" name="rules[<?= (int) $rule['id'] ?>][delete]"></td>
                                <input type="hidden" name="rules[<?= (int) $rule['id'] ?>][sort_order]" value="<?= (int) $rule['sort_order'] ?>">
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><select class="form-select form-select-sm" name="new_rules[0][scale_id]"><option value="">Nueva</option><?php foreach ($scales as $scale): ?><option value="<?= (int) $scale['id'] ?>"><?= e($scale['scale_key']) ?></option><?php endforeach; ?></select></td>
                            <td><select class="form-select form-select-sm" name="new_rules[0][rule_type]"><option value="mapped">Mapa</option><option value="direct">Directa</option><option value="reverse">Inversa</option><option value="keyed">Clave</option></select></td>
                            <td><input class="form-control form-control-sm" name="new_rules[0][answer_value]"></td>
                            <td><input class="form-control form-control-sm" name="new_rules[0][score_value]"></td>
                            <td><input class="form-control form-control-sm" name="new_rules[0][weight]" value="1"></td>
                            <td><input class="form-control form-control-sm" name="new_rules[0][rule_config]"></td>
                            <td><input class="form-check-input" type="checkbox" name="new_rules[0][is_active]" checked></td>
                            <td></td>
                            <input type="hidden" name="new_rules[0][sort_order]" value="<?= (count($rules) + 1) * 10 ?>">
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-end gap-2">
        <button class="btn btn-outline-secondary" type="button" data-app-drawer-close>Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar pregunta</button>
    </div>
</form>
