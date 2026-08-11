<?php
$scale = $scale ?: [
    'id' => 0,
    'scale_key' => '',
    'name' => '',
    'description' => '',
    'scale_type' => 'primary',
    'scoring_method' => 'sum',
    'sort_order' => 100,
];
?>

<form method="post" action="<?= e(route_url('test.content', (int) $instrument['id']) . '/scale') ?>" class="drawer-form" data-drawer-form>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="scale_id" value="<?= (int) $scale['id'] ?>">
    <div class="drawer-detail-heading">
        <p class="text-muted mb-0">Una escala agrupa puntajes de una o mas preguntas. Usa claves cortas y unicas dentro del test.</p>
    </div>
    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <label class="form-label">Clave</label>
            <input class="form-control" name="scale[scale_key]" value="<?= e($scale['scale_key']) ?>" required>
        </div>
        <div class="col-12 col-lg-8">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="scale[name]" value="<?= e($scale['name']) ?>" required>
        </div>
        <div class="col-12">
            <label class="form-label">Descripcion</label>
            <textarea class="form-control" name="scale[description]" rows="3"><?= e($scale['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label">Tipo</label>
            <select class="form-select" name="scale[scale_type]">
                <?php foreach (['primary' => 'Primaria', 'validity' => 'Control', 'derived' => 'Derivada', 'global' => 'Global'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($scale['scale_type'] ?? 'primary') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label">Metodo</label>
            <select class="form-select" name="scale[scoring_method]">
                <?php foreach (['sum' => 'Suma', 'formula' => 'Formula', 'manual' => 'Manual'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($scale['scoring_method'] ?? 'sum') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label">Orden</label>
            <input class="form-control" type="number" name="scale[sort_order]" value="<?= (int) $scale['sort_order'] ?>">
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-end gap-2">
        <button class="btn btn-outline-secondary" type="button" data-app-drawer-close>Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar escala</button>
    </div>
</form>
