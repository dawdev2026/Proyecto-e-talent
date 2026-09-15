<section class="page-header">
    <div>
        <p class="text-uppercase text-warning fw-bold small mb-1">Administración central</p>
        <h1 class="fw-bold mb-1">Limpiar usuarios de prueba</h1>
        <p class="text-muted mb-0">Empresa: <strong><?= e($preview['company']['name']) ?></strong> · prefijo <code><?= e($preview['company']['url_prefix']) ?></code></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(route_url('companies')) ?>">Volver</a>
</section>

<section class="card content-panel">
    <div class="alert alert-warning">
        Esta operación es permanente. Eliminará solo los usuarios con rol <code>usuario</code> de esta empresa y toda la información relacionada con ellos: pruebas, respuestas, resultados, sesiones, evidencias, intentos de evaluación, asignaciones, entrevistas, citas, reportes asociados, campos personalizados y códigos de acceso. No elimina administradores ni la empresa.
    </div>
    <div class="row g-3 mb-4">
        <?php foreach ([
            'users' => 'Usuarios',
            'active_users' => 'Activos',
            'test_sessions' => 'Sesiones de pruebas',
            'test_assignments' => 'Asignaciones',
            'evaluation_attempts' => 'Intentos de evaluación',
            'interview_appointments' => 'Citas de entrevista',
        ] as $key => $label): ?>
            <div class="col-6 col-lg-2"><div class="border rounded p-3 h-100"><div class="small text-muted"><?= e($label) ?></div><div class="fs-4 fw-bold"><?= (int) $preview[$key] ?></div></div></div>
        <?php endforeach; ?>
    </div>
    <?php if ((int) $preview['users'] > 0): ?>
        <form method="post" action="<?= e(route_url('company.cleanup-users', (int) $preview['company']['id'])) ?>" class="border border-danger rounded p-3" data-confirm-submit="CONFIRMACIÓN DE BORRADO PERMANENTE\n\nSe eliminarán los usuarios de prueba y toda su información relacionada: pruebas, respuestas, resultados, sesiones, evidencias, evaluaciones, entrevistas, citas, reportes asociados, campos personalizados y códigos de acceso.\n\nNo se puede deshacer. ¿Deseas continuar para esta empresa?">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label class="form-label fw-semibold" for="confirmation_prefix">Segunda confirmación: escribe exactamente el prefijo <code><?= e($preview['company']['url_prefix']) ?></code></label>
            <input class="form-control mb-3" id="confirmation_prefix" name="confirmation_prefix" autocomplete="off" required>
            <button type="submit" class="btn btn-danger"><i class="bi bi-trash3 me-1"></i> Confirmar borrado permanente</button>
        </form>
    <?php else: ?>
        <div class="alert alert-success mb-0">No hay usuarios de prueba para eliminar en esta empresa.</div>
    <?php endif; ?>
</section>
