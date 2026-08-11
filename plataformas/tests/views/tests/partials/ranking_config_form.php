<?php
$rankingConfig = is_array($rankingConfig ?? null) ? $rankingConfig : [];
$rankingUsesOfficialConfig = (bool) ($rankingUsesOfficialConfig ?? false);
$rankingFormAction = (string) ($rankingFormAction ?? ($_SERVER['REQUEST_URI'] ?? ''));
$rankingPresetFormAction = (string) ($rankingPresetFormAction ?? route_url('tests.ranking-presets'));
$rankingPresetRedirectTo = (string) ($rankingPresetRedirectTo ?? ($_SERVER['REQUEST_URI'] ?? $rankingFormAction));
$rankingPresets = is_array($rankingPresets ?? null) ? $rankingPresets : [];
$rankingSelectedPresetId = (int) ($rankingSelectedPresetId ?? -1);
$canManageRankingPresets = (bool) ($canManageRankingPresets ?? false);
$rankingPresetsStorageReady = (bool) ($rankingPresetsStorageReady ?? false);
$rankingSelectedPresetName = '';
foreach ($rankingPresets as $rankingPresetOption) {
    if ((int) ($rankingPresetOption['id'] ?? -1) === $rankingSelectedPresetId) {
        $rankingSelectedPresetName = (string) ($rankingPresetOption['name'] ?? '');
        break;
    }
}
$classification = is_array($rankingConfig['classification'] ?? null) ? $rankingConfig['classification'] : [];
$dimensions = is_array($rankingConfig['dimensions'] ?? null) ? $rankingConfig['dimensions'] : [];
$knockouts = is_array($rankingConfig['knockouts'] ?? null) ? $rankingConfig['knockouts'] : [];
?>

<section class="content-panel mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Configuracion de criterios</h2>
            <p class="text-muted mb-0">Ajusta pesos, umbrales y criterios considerados. La matriz oficial queda protegida para restaurar la base.</p>
        </div>
        <span class="badge <?= $rankingUsesOfficialConfig ? 'text-bg-success' : 'text-bg-warning' ?>">
            <?= $rankingUsesOfficialConfig ? 'Matriz oficial' : 'Personalizada' ?>
        </span>
    </div>

    <div class="border rounded p-3 mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-5">
                <form method="get" action="<?= e($rankingFormAction) ?>">
                    <label class="form-label" for="ranking_preset_id">Configuracion guardada</label>
                    <div class="input-group">
                        <select id="ranking_preset_id" class="form-select" name="ranking_preset_id">
                            <?php foreach ($rankingPresets as $preset): ?>
                                <?php $presetId = (int) ($preset['id'] ?? 0); ?>
                                <option value="<?= $presetId ?>" <?= $rankingSelectedPresetId === $presetId ? 'selected' : '' ?>>
                                    <?= e((string) ($preset['name'] ?? 'Configuracion')) ?><?= !empty($preset['is_official']) ? ' · Protegida' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary" type="submit" name="ranking_load_preset" value="1"><i class="bi bi-box-arrow-in-down me-1"></i> Cargar</button>
                    </div>
                </form>
            </div>
            <div class="col-12 col-lg-7">
                <?php if ($canManageRankingPresets): ?>
                    <?php if (!$rankingPresetsStorageReady): ?>
                        <div class="alert alert-warning mb-0">Para guardar configuraciones, primero aplica la migracion de presets de ranking.</div>
                    <?php else: ?>
                        <label class="form-label" for="ranking_preset_name">Nombre para guardar o actualizar</label>
                        <input id="ranking_preset_name" class="form-control" form="ranking_config_form" name="ranking_preset_name" value="<?= e($rankingSelectedPresetId > 0 ? $rankingSelectedPresetName : '') ?>" maxlength="150" placeholder="Ej: Escenario CAG <= 7 sin impulsividad">
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-light border mb-0">Tu perfil puede cargar configuraciones, pero no guardarlas ni eliminarlas.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <form id="ranking_config_form" method="post" action="<?= e($rankingFormAction) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="ranking_action" value="save">
        <input type="hidden" name="redirect_to" value="<?= e($rankingPresetRedirectTo) ?>">
        <input type="hidden" name="ranking_preset_id" value="<?= max(0, $rankingSelectedPresetId) ?>">

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="ranking_recommended_min">Umbral Recomendado</label>
                <input id="ranking_recommended_min" class="form-control" type="number" step="0.01" min="0" max="100" name="ranking_config[classification][recommended_min]" value="<?= e((string) ($classification['recommended_min'] ?? 70)) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="ranking_observation_min">Umbral Observacion</label>
                <input id="ranking_observation_min" class="form-control" type="number" step="0.01" min="0" max="100" name="ranking_config[classification][observation_min]" value="<?= e((string) ($classification['observation_min'] ?? 40)) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="ranking_knockout_cap">Tope con knockout</label>
                <input id="ranking_knockout_cap" class="form-control" type="number" step="0.01" min="0" max="100" name="ranking_config[classification][knockout_score_cap]" value="<?= e((string) ($classification['knockout_score_cap'] ?? 64)) ?>">
            </div>
            <div class="col-12 col-md-6">
                <div class="form-check form-switch">
                    <input id="ranking_knockout_force" class="form-check-input" type="checkbox" name="ranking_config[classification][knockout_forces_not_recommended]" value="1" <?= !empty($classification['knockout_forces_not_recommended']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ranking_knockout_force">Knockout clasifica automaticamente como NR</label>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="form-check form-switch">
                    <input id="ranking_knockout_cap_enabled" class="form-check-input" type="checkbox" name="ranking_config[classification][knockout_score_cap_enabled]" value="1" <?= !empty($classification['knockout_score_cap_enabled']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ranking_knockout_cap_enabled">Aplicar tope de puntaje si hay knockout</label>
                </div>
            </div>
        </div>

        <div class="accordion mb-3" id="rankingConfigAccordion">
            <?php foreach ($dimensions as $dimensionKey => $dimension): ?>
                <?php $components = is_array($dimension['components'] ?? null) ? $dimension['components'] : []; ?>
                <div class="accordion-item">
                    <h3 class="accordion-header" id="ranking_heading_<?= e((string) $dimensionKey) ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ranking_collapse_<?= e((string) $dimensionKey) ?>" aria-expanded="false" aria-controls="ranking_collapse_<?= e((string) $dimensionKey) ?>">
                            <?= e((string) $dimensionKey) ?> · <?= e((string) ($dimension['label'] ?? 'Dimension')) ?>
                        </button>
                    </h3>
                    <div id="ranking_collapse_<?= e((string) $dimensionKey) ?>" class="accordion-collapse collapse" aria-labelledby="ranking_heading_<?= e((string) $dimensionKey) ?>" data-bs-parent="#rankingConfigAccordion">
                        <div class="accordion-body">
                            <div class="row g-3 align-items-end mb-3">
                                <div class="col-12 col-md-4">
                                    <div class="form-check form-switch">
                                        <input id="ranking_dimension_<?= e((string) $dimensionKey) ?>" class="form-check-input" type="checkbox" name="ranking_config[dimensions][<?= e((string) $dimensionKey) ?>][enabled]" value="1" <?= !empty($dimension['enabled']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="ranking_dimension_<?= e((string) $dimensionKey) ?>">Considerar dimension</label>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="ranking_dimension_weight_<?= e((string) $dimensionKey) ?>">Peso dimension</label>
                                    <input id="ranking_dimension_weight_<?= e((string) $dimensionKey) ?>" class="form-control" type="number" step="0.01" min="0" max="100" name="ranking_config[dimensions][<?= e((string) $dimensionKey) ?>][weight]" value="<?= e((string) ($dimension['weight'] ?? 0)) ?>">
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Criterio</th>
                                            <th>Sentido</th>
                                            <th style="width: 160px;">Peso interno</th>
                                            <th class="text-center" style="width: 140px;">Considerar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($components as $componentIndex => $component): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= e((string) ($component['label'] ?? $component['key'] ?? 'Criterio')) ?></div>
                                                    <div class="text-muted small"><?= e((string) ($component['key'] ?? '')) ?></div>
                                                </td>
                                                <td><?= e((string) ($component['transform'] ?? 'direct')) ?></td>
                                                <td>
                                                    <input class="form-control form-control-sm" type="number" step="0.01" min="0" max="100" name="ranking_config[dimensions][<?= e((string) $dimensionKey) ?>][components][<?= (int) $componentIndex ?>][weight]" value="<?= e((string) ($component['weight'] ?? 0)) ?>">
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="checkbox" name="ranking_config[dimensions][<?= e((string) $dimensionKey) ?>][components][<?= (int) $componentIndex ?>][enabled]" value="1" <?= !empty($component['enabled']) ? 'checked' : '' ?> aria-label="Considerar <?= e((string) ($component['label'] ?? 'criterio')) ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="border rounded p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Filtros knockout</h3>
            <div class="row g-3">
                <?php foreach ($knockouts as $knockoutKey => $knockout): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="border rounded p-3 h-100">
                            <div class="form-check form-switch mb-2">
                                <input id="ranking_knockout_<?= e((string) $knockoutKey) ?>" class="form-check-input" type="checkbox" name="ranking_config[knockouts][<?= e((string) $knockoutKey) ?>][enabled]" value="1" <?= !empty($knockout['enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="ranking_knockout_<?= e((string) $knockoutKey) ?>"><?= e((string) ($knockout['label'] ?? $knockoutKey)) ?></label>
                            </div>
                            <label class="form-label small" for="ranking_knockout_threshold_<?= e((string) $knockoutKey) ?>">
                                <?= e((string) ($knockout['metric'] ?? 'Metrica')) ?>
                            </label>
                            <div class="input-group input-group-sm">
                                <select class="form-select" name="ranking_config[knockouts][<?= e((string) $knockoutKey) ?>][operator]" aria-label="Operador <?= e((string) ($knockout['label'] ?? $knockoutKey)) ?>">
                                    <?php foreach (['<', '<=', '>', '>='] as $operatorOption): ?>
                                        <option value="<?= e($operatorOption) ?>" <?= (string) ($knockout['operator'] ?? '<') === $operatorOption ? 'selected' : '' ?>><?= e($operatorOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input id="ranking_knockout_threshold_<?= e((string) $knockoutKey) ?>" class="form-control" type="number" step="0.01" name="ranking_config[knockouts][<?= e((string) $knockoutKey) ?>][threshold]" value="<?= e((string) ($knockout['threshold'] ?? 0)) ?>">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($canManageRankingPresets): ?>
                <button class="btn btn-primary" type="submit"><i class="bi bi-sliders me-1"></i> Actualizar ranking</button>
                <button class="btn btn-outline-primary" type="submit" formmethod="post" formaction="<?= e($rankingPresetFormAction) ?>" name="ranking_preset_action" value="create" <?= $rankingPresetsStorageReady ? '' : 'disabled' ?>>
                    <i class="bi bi-plus-circle me-1"></i> Guardar como nueva
                </button>
                <button class="btn btn-outline-secondary" type="submit" formmethod="post" formaction="<?= e($rankingPresetFormAction) ?>" name="ranking_preset_action" value="update" <?= $rankingPresetsStorageReady && $rankingSelectedPresetId > 0 ? '' : 'disabled' ?>>
                    <i class="bi bi-save me-1"></i> Actualizar guardada
                </button>
            <?php endif; ?>
        </div>
    </form>

    <div class="d-flex flex-wrap gap-2 mt-2">
        <?php if ($canManageRankingPresets): ?>
            <form method="post" action="<?= e($rankingFormAction) ?>" data-confirm-submit="Esta accion restaurara los pesos, umbrales y criterios de la matriz oficial protegida. Deseas continuar?">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="ranking_action" value="reset">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar matriz oficial</button>
            </form>
            <form method="post" action="<?= e($rankingPresetFormAction) ?>" data-confirm-submit="Esta accion eliminara la configuracion personalizada seleccionada. La matriz oficial no se puede eliminar. Deseas continuar?">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="redirect_to" value="<?= e($rankingPresetRedirectTo) ?>">
                <input type="hidden" name="ranking_preset_action" value="delete">
                <input type="hidden" name="ranking_preset_id" value="<?= max(0, $rankingSelectedPresetId) ?>">
                <button class="btn btn-outline-danger" type="submit" <?= $rankingPresetsStorageReady && $rankingSelectedPresetId > 0 ? '' : 'disabled' ?>><i class="bi bi-trash me-1"></i> Eliminar personalizada</button>
            </form>
        <?php endif; ?>
    </div>
</section>
