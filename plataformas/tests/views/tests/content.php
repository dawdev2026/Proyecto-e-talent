<?php
$contentUrl = route_url('test.content', (int) $instrument['id']);
$helpUrl = $contentUrl . '/help?partial=1';
$scaleFormUrl = $contentUrl . '/scale?partial=1';
$itemFormUrl = $contentUrl . '/item?partial=1';
if (!function_exists('test_short_text')) {
    function test_short_text(string $value, int $limit = 120): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, max(0, $limit - 3)) . '...';
    }
}
?>

<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Contenido: <?= e($instrument['name']) ?></h1>
        <p class="text-muted mb-0">Administra escalas, preguntas, alternativas y valorizaciones sin cargar todo el test a la vez.</p>
    </div>
    <a class="btn btn-outline-secondary" data-page-back="1" href="<?= e(route_url('tests')) ?>">Volver</a>
</section>

<section class="card content-panel">
    <div class="test-config-summary">
        <div>
            <h2 class="h5 fw-bold mb-2">Guia de configuracion del test</h2>
            <p class="text-muted mb-0">
                Revisa que significa cada campo, donde se registra y como se espera que impacte el resultado antes de editar escalas, preguntas o reglas.
            </p>
        </div>
        <button class="btn btn-outline-primary justify-self-start" type="button" data-drawer-url="<?= e($helpUrl) ?>" data-drawer-title="Guia de configuracion" data-drawer-size="lg">
            <i class="bi bi-info-circle me-1"></i> Ver guia y ejemplo
        </button>
    </div>
</section>

<section class="card content-panel mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Escalas</h2>
            <p class="text-muted mb-0"><?= (int) $stats['scales_count'] ?> escalas configuradas.</p>
        </div>
        <button class="btn btn-primary" type="button" data-drawer-url="<?= e($scaleFormUrl) ?>" data-drawer-title="Nueva escala" data-drawer-size="md">
            <i class="bi bi-plus-lg me-1"></i> Agregar escala
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle app-table app-data-table" data-export-title="Escalas <?= e($instrument['name']) ?>" data-page-length="10">
            <thead>
                <tr>
                    <th>Clave</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Metodo</th>
                    <th>Orden</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scales as $scale): ?>
                    <tr>
                        <td><code><?= e($scale['scale_key']) ?></code></td>
                        <td>
                            <div class="fw-semibold"><?= e($scale['name']) ?></div>
                            <?php if (!empty($scale['description'])): ?>
                                <div class="text-muted small"><?= e($scale['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e(labelize($scale['scale_type'] ?? 'primary')) ?></td>
                        <td><?= e(labelize($scale['scoring_method'] ?? 'sum')) ?></td>
                        <td><?= (int) $scale['sort_order'] ?></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-drawer-url="<?= e($scaleFormUrl . '&scale_id=' . (int) $scale['id']) ?>" data-drawer-title="Editar escala" data-drawer-size="md">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" action="<?= e($contentUrl . '/scale-delete') ?>" data-confirm-submit="Eliminar esta escala tambien puede eliminar reglas asociadas. Deseas continuar?">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="scale_id" value="<?= (int) $scale['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card content-panel mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Preguntas y valorizaciones</h2>
            <p class="text-muted mb-0"><?= (int) $stats['items_count'] ?> preguntas configuradas. Usa busqueda, orden y paginacion de la tabla.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="button" data-drawer-url="<?= e($itemFormUrl) ?>" data-drawer-title="Nueva pregunta" data-drawer-size="lg">
                <i class="bi bi-plus-lg me-1"></i> Agregar pregunta
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle app-table app-data-table" data-export-title="Preguntas <?= e($instrument['name']) ?>" data-page-length="25">
            <thead>
                <tr>
                    <th>Pregunta</th>
                    <th>Enunciado</th>
                    <th>Alternativas</th>
                    <th>Reglas</th>
                    <th>Estado</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php $itemRules = $rulesByItem[(int) $item['id']] ?? []; ?>
                    <tr>
                        <td>
                            <code><?= e($item['item_key']) ?></code>
                            <div class="text-muted small">Orden <?= (int) $item['sort_order'] ?></div>
                        </td>
                        <td class="test-content-prompt-cell"><?= e(test_short_text((string) $item['prompt'])) ?></td>
                        <td><?= count(array_filter(explode(';', (string) $item['options']))) ?></td>
                        <td><?= count($itemRules) ?></td>
                        <td><span class="badge <?= (int) $item['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $item['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-drawer-url="<?= e($itemFormUrl . '&item_id=' . (int) $item['id']) ?>" data-drawer-title="Editar pregunta" data-drawer-size="lg">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" action="<?= e($contentUrl . '/item-delete') ?>" data-confirm-submit="Eliminar esta pregunta eliminara sus alternativas, reglas y respuestas guardadas. Deseas continuar?">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
