<link href="<?= e(url('assets/css/interviews/interviews.css')) ?>" rel="stylesheet">

<?php
$fmt = static fn(int $value): string => number_format($value, 0, ',', '.');
$poolStatusClass = (string) ($dailyPool['status_class'] ?? 'text-bg-secondary');
$poolStatusLabel = (string) ($dailyPool['status_label'] ?? 'Sin dato');
$progressWidth = max(0, min(100, (int) ($dailyPool['committed_percent'] ?? 0)));
?>

<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Entrevistas seleccion</p>
        <h1 class="fw-bold mb-1">Videollamada interna</h1>
        <p class="text-muted mb-0">Configura Daily.co como proveedor usado para entrevistas 1 a 1, transcripcion y control de bolsa.</p>
    </div>
</section>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <section class="card content-panel mb-4">
        <p class="text-muted mb-4">Daily requiere una cuenta activa con metodo de pago para habilitar salas embebidas y transcripcion. Las claves se guardan cifradas y solo se usan desde backend.</p>

        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="interview-setting-toggle">
                    <input class="form-check-input" type="checkbox" name="daily_enabled" <?= !empty($daily['enabled']) ? 'checked' : '' ?>>
                    <span>Integracion interna activa</span>
                </label>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="daily_provider">Proveedor</label>
                <select id="daily_provider" class="form-select" disabled>
                    <option selected>Daily.co</option>
                </select>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="daily_domain">Dominio Daily</label>
                <input id="daily_domain" class="form-control" name="daily_domain" value="<?= e($daily['domain']) ?>" placeholder="dominio.daily.co">
                <div class="form-text">Dominio asignado por Daily para las salas embebidas.</div>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="daily_api_key">API Key Daily</label>
                <input id="daily_api_key" class="form-control" type="password" name="daily_api_key" value="" placeholder="<?= $daily['api_key'] !== '' ? 'Dejar vacio para mantener la clave guardada' : 'Daily API Key' ?>">
                <div class="form-text"><?= $daily['api_key'] !== '' ? 'Hay una API Key guardada. Este dato se guarda cifrado y no se muestra nuevamente.' : 'La API Key se guardara cifrada y no se mostrara nuevamente.' ?></div>
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="daily_webhook_secret">Webhook secret Daily</label>
                <input id="daily_webhook_secret" class="form-control" type="password" name="daily_webhook_secret" value="" placeholder="<?= $daily['webhook_secret'] !== '' ? 'Dejar vacio para mantener el secreto guardado' : 'Opcional' ?>">
                <div class="form-text">Se usara cuando activemos callbacks persistentes de transcripcion.</div>
            </div>

            <div class="col-12 col-lg-6">
                <label class="interview-setting-toggle">
                    <input class="form-check-input" type="checkbox" name="daily_transcription_enabled" <?= !empty($daily['transcription_enabled']) ? 'checked' : '' ?>>
                    <span>Habilitar controles de transcripcion Daily</span>
                </label>
                <div class="form-text">El moderador podra iniciar y detener transcripcion desde el aula remota.</div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="interview-setting-toggle">
                    <input class="form-check-input" type="checkbox" name="daily_transcription_auto_start_default" <?= !empty($daily['transcription_auto_start_default']) ? 'checked' : '' ?>>
                    <span>Auto iniciar transcripcion</span>
                </label>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label fw-semibold" for="daily_transcription_snapshot_interval_seconds">Guardado incremental</label>
                <div class="input-group">
                    <input id="daily_transcription_snapshot_interval_seconds" class="form-control" type="number" min="5" max="300" name="daily_transcription_snapshot_interval_seconds" value="<?= (int) $daily['transcription_snapshot_interval_seconds'] ?>">
                    <span class="input-group-text">segundos</span>
                </div>
                <div class="form-text">Cada cuantos segundos se guarda un respaldo parcial.</div>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="daily_token_ttl_seconds">Duracion token sala</label>
                <div class="input-group">
                    <input id="daily_token_ttl_seconds" class="form-control" type="number" min="300" max="86400" name="daily_token_ttl_seconds" value="<?= (int) $daily['token_ttl_seconds'] ?>">
                    <span class="input-group-text">segundos</span>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="daily_default_lang">Idioma Daily</label>
                <input id="daily_default_lang" class="form-control" name="daily_default_lang" value="<?= e($daily['default_lang']) ?>" maxlength="2">
            </div>
        </div>
    </section>

    <section class="card content-panel mb-4">
        <div class="border-bottom pb-3 mb-4">
            <h2 class="h4 fw-bold mb-1">Control de bolsa Daily</h2>
            <p class="text-muted mb-0">Compara minutos contratados, consumo real consultado a Daily y entrevistas futuras ya agendadas.</p>
        </div>

        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="interview-setting-toggle">
                    <input class="form-check-input" type="checkbox" name="daily_minutes_pool_enabled" <?= !empty($daily['minutes_pool_enabled']) ? 'checked' : '' ?>>
                    <span>Monitorear bolsa Daily</span>
                </label>
                <div class="form-text">Usa minutos participante como unidad de control.</div>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="daily_minutes_pool_total">Minutos contratados</label>
                <input id="daily_minutes_pool_total" class="form-control" type="number" min="0" step="1" name="daily_minutes_pool_total" value="<?= (int) $daily['minutes_pool_total'] ?>">
                <div class="form-text">Bolsa total disponible en Daily.</div>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="daily_minutes_pool_warning_percent">Avisar desde</label>
                <div class="input-group">
                    <input id="daily_minutes_pool_warning_percent" class="form-control" type="number" min="1" max="100" step="1" name="daily_minutes_pool_warning_percent" value="<?= (int) $daily['minutes_pool_warning_percent'] ?>">
                    <span class="input-group-text">%</span>
                </div>
            </div>
        </div>

        <div class="interview-pool-card mt-4">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                <div>
                    <p class="text-uppercase text-primary fw-bold small mb-1">Consulta <?= e((string) ($dailyPool['queried_at'] ?? '')) ?></p>
                    <h3 class="h4 fw-bold mb-1">Estado de bolsa Daily</h3>
                    <p class="text-muted mb-0">El consumo real se consulta desde <?= e((string) ($dailyPool['period_label'] ?? '')) ?>. Los requeridos futuros consideran entrevistas activas con dos participantes.</p>
                </div>
                <div class="text-end">
                    <span class="badge <?= e($poolStatusClass) ?>"><?= e($poolStatusLabel) ?></span>
                    <div class="fs-4 fw-bold mt-2"><?= $fmt((int) ($dailyPool['remaining_projected_minutes'] ?? 0)) ?></div>
                    <div class="text-muted small">minutos proyectados restantes</div>
                </div>
            </div>

            <?php if (!empty($dailyPool['error'])): ?>
                <p class="text-muted mb-3"><?= e((string) $dailyPool['error']) ?></p>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <span class="text-uppercase text-primary small">Contratados</span>
                    <strong class="d-block fs-5"><?= $fmt((int) ($dailyPool['contracted_minutes'] ?? 0)) ?></strong>
                </div>
                <div class="col-6 col-lg-3">
                    <span class="text-uppercase text-primary small">Consumidos</span>
                    <strong class="d-block fs-5"><?= $fmt((int) ($dailyPool['consumed_minutes'] ?? 0)) ?></strong>
                </div>
                <div class="col-6 col-lg-3">
                    <span class="text-uppercase text-primary small">Restantes reales</span>
                    <strong class="d-block fs-5"><?= $fmt((int) ($dailyPool['remaining_real_minutes'] ?? 0)) ?></strong>
                </div>
                <div class="col-6 col-lg-3">
                    <span class="text-uppercase text-primary small">Requeridos futuros</span>
                    <strong class="d-block fs-5"><?= $fmt((int) ($dailyPool['future_required_minutes'] ?? 0)) ?></strong>
                </div>
            </div>

            <div class="interview-pool-meter mt-3" role="progressbar" aria-label="Uso proyectado de bolsa Daily" aria-valuenow="<?= $progressWidth ?>" aria-valuemin="0" aria-valuemax="100">
                <span style="width: <?= $progressWidth ?>%"></span>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-2">
                <span><?= (int) ($dailyPool['consumed_percent'] ?? 0) ?>% consumido actualmente.</span>
                <span><?= (int) ($dailyPool['committed_percent'] ?? 0) ?>% comprometido considerando entrevistas futuras.</span>
            </div>
        </div>
    </section>

    <section class="card content-panel mb-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 fw-bold mb-1">IA entrevistas</h2>
                <p class="text-muted mb-0">Valida la conexion antes de usar reportes reales de entrevistas.</p>
            </div>
            <button
                class="btn btn-outline-primary"
                type="button"
                data-ai-test-connection
                data-url="<?= e(route_url('interviews.ai-test')) ?>">
                <i class="bi bi-plug me-1"></i> Test connection
            </button>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="interview-setting-toggle">
                    <input class="form-check-input" type="checkbox" name="ai_enabled" <?= !empty($ai['enabled']) ? 'checked' : '' ?>>
                    <span>Generacion IA activa</span>
                </label>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="ai_provider">Proveedor</label>
                <input id="ai_provider" class="form-control" name="ai_provider" value="<?= e($ai['provider']) ?>" placeholder="Proveedor">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="ai_model">Modelo</label>
                <input id="ai_model" class="form-control" name="ai_model" value="<?= e($ai['model']) ?>" placeholder="Modelo">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="ai_base_url">Base URL compatible</label>
                <input id="ai_base_url" class="form-control" name="ai_base_url" value="<?= e($ai['base_url']) ?>" placeholder="Base URL">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="ai_api_key">API Key IA</label>
                <div class="input-group">
                    <input id="ai_api_key" class="form-control" type="password" name="ai_api_key" value="<?= e($ai['api_key']) ?>" placeholder="API key" autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" data-api-key-toggle="ai_api_key" aria-label="Mostrar API Key IA" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                        <span class="visually-hidden">Mostrar API Key IA</span>
                    </button>
                </div>
                <div class="form-text">La clave se muestra oculta. Usa el boton del ojo para visualizarla.</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="ai_timeout_seconds">Timeout segundos</label>
                <input id="ai_timeout_seconds" class="form-control" type="number" name="ai_timeout_seconds" value="<?= (int) $ai['timeout_seconds'] ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="ai_prompt_version">Version prompt</label>
                <input id="ai_prompt_version" class="form-control" name="ai_prompt_version" value="<?= e($ai['prompt_version']) ?>">
            </div>
        </div>
        <div class="mt-3" data-ai-test-result hidden></div>
    </section>

    <div class="d-flex gap-2 mb-4">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i> Guardar configuracion</button>
    </div>
</form>
<script src="<?= e(url('assets/js/interviews/interviews.js')) ?>"></script>
