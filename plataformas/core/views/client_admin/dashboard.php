<?php
$summary = is_array($summary ?? null) ? $summary : [];
$companyName = trim((string) ($companyName ?? '')) ?: 'Empresa';
$count = static fn(string $key): string => number_format(max(0, (int) ($summary[$key] ?? 0)), 0, ',', '.');
$processIndicators = [
    ['Procesos generados', 'processes_generated', 'bi-diagram-3', 'Todos los procesos de la empresa, salvo los cancelados.', 'primary'],
    ['Finalizados', 'processes_finished', 'bi-check2-circle', 'Cerrados o con el plazo de ejecución cumplido.', 'success'],
    ['En curso', 'processes_in_progress', 'bi-play-circle', 'Activos, iniciados y aún dentro de su período.', 'info'],
    ['Aún no iniciados', 'processes_not_started', 'bi-hourglass', 'En borrador o programados para una fecha futura.', 'warning'],
];
$userIndicators = [
    ['Usuarios registrados', 'users_registered', 'bi-people', 'Cuentas activas de participantes de la empresa.', 'primary'],
    ['Usuarios enrolados', 'users_enrolled', 'bi-person-check', 'Participantes de procesos con enrolamiento facial activo.', 'success'],
    ['Por enrolar', 'users_pending_enrollment', 'bi-person-plus', 'Participantes asignados a procesos que aún no tienen enrolamiento facial activo.', 'warning'],
];
$renderIndicators = static function (array $indicators) use ($count): void { ?>
    <div class="client-admin-metrics-grid">
        <?php foreach ($indicators as [$label, $key, $icon, $description, $tone]): ?>
            <article class="client-admin-metric" data-tone="<?= e($tone) ?>" aria-labelledby="indicator-<?= e($key) ?>">
                <div class="client-admin-metric-heading">
                    <h3 id="indicator-<?= e($key) ?>"><?= e($label) ?></h3>
                    <span class="client-admin-metric-icon" aria-hidden="true"><i class="bi <?= e($icon) ?>"></i></span>
                </div>
                <p class="client-admin-metric-value"><?= e($count($key)) ?></p>
                <p class="client-admin-metric-description"><?= e($description) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php }; ?>

<section class="client-admin-dashboard" aria-labelledby="client-admin-home-title">
    <div class="client-admin-dashboard-shell">
        <header class="client-admin-dashboard-header">
            <div class="client-admin-dashboard-heading">
                <p class="client-admin-dashboard-eyebrow"><span aria-hidden="true"></span>Inicio · Administrador Cliente</p>
                <h1 id="client-admin-home-title">Resumen de gestión</h1>
                <p class="client-admin-dashboard-subtitle"><?= e($companyName) ?> <span aria-hidden="true">·</span> Procesos, participantes y enrolamiento facial</p>
            </div>
            <nav class="client-admin-dashboard-actions" aria-label="Accesos principales">
                <a class="btn btn-outline-primary" href="<?= e(route_url('test-processes')) ?>"><i class="bi bi-kanban me-1" aria-hidden="true"></i>Ver procesos</a>
                <a class="btn btn-outline-primary" href="<?= e(route_url('users')) ?>"><i class="bi bi-people me-1" aria-hidden="true"></i>Usuarios</a>
                <a class="btn btn-primary" href="<?= e(route_url('facial-recognition.enrolled')) ?>"><i class="bi bi-person-check me-1" aria-hidden="true"></i>Reconocimientos enrolados</a>
            </nav>
        </header>

        <div class="client-admin-dashboard-divider" aria-hidden="true"></div>

        <div class="client-admin-dashboard-content">
            <section class="client-admin-metric-section" aria-labelledby="client-admin-processes-title">
                <div class="client-admin-section-heading">
                    <div>
                        <h2 id="client-admin-processes-title">Procesos</h2>
                        <p>Estado de los procesos asociados a tu empresa.</p>
                    </div>
                </div>
                <?= status_help_button('Estados e indicadores de procesos', "• Activos: procesos habilitados para operar, sujetos a su calendario.\n• Aún no iniciados: procesos en borrador o programados para una fecha futura.\n• Finalizados: procesos cerrados o cuyo plazo de ejecución ya terminó.\n• Cancelados: procesos retirados; no admiten nueva actividad y se excluyen de los totales activos.") ?>
                <?php $renderIndicators($processIndicators); ?>
            </section>

            <section class="client-admin-metric-section client-admin-users-section" aria-labelledby="client-admin-users-title">
                <div class="client-admin-section-heading">
                    <div>
                        <h2 id="client-admin-users-title">Usuarios y enrolamiento</h2>
                        <p>Personas registradas y avance de enrolamiento entre quienes participan en procesos.</p>
                    </div>
                </div>
                <?php $renderIndicators($userIndicators); ?>
            </section>
        </div>
    </div>
</section>
