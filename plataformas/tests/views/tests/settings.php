<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Administracion / Configuracion</p>
        <h1 class="fw-bold mb-1">Evaluaciones</h1>
        <p class="text-muted mb-0">Parametros propios de la sub-plataforma Evaluaciones.</p>
    </div>
</section>

<form method="post" class="card content-panel">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="row g-4">
        <div class="col-12">
            <div class="alert alert-info mb-0" data-inline-alert>
                Puedes usar <code>{test_name}</code> para mostrar el nombre de la evaluacion y <code>{activity_tracking_notice}</code> para insertar el texto de control de actividad correspondiente.
            </div>
        </div>

        <div class="col-12">
            <h2 class="h5 fw-bold mb-1">Mensaje antes de responder</h2>
            <p class="text-muted mb-0">Se muestra al presionar el boton Responder antes de ingresar a la evaluacion.</p>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="entry_confirm_title">Titulo</label>
            <input id="entry_confirm_title" class="form-control" type="text" name="entry_confirm_title" value="<?= e($settings['entry_confirm_title']) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="entry_confirm_button">Boton confirmar</label>
            <input id="entry_confirm_button" class="form-control" type="text" name="entry_confirm_button" value="<?= e($settings['entry_confirm_button']) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="entry_cancel_button">Boton cancelar</label>
            <input id="entry_cancel_button" class="form-control" type="text" name="entry_cancel_button" value="<?= e($settings['entry_cancel_button']) ?>" required>
        </div>
        <div class="col-12">
            <label class="form-label" for="entry_confirm_message">Mensaje</label>
            <textarea id="entry_confirm_message" class="form-control" name="entry_confirm_message" rows="8" required><?= e($settings['entry_confirm_message']) ?></textarea>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="activity_tracking_notice">Texto con control de actividad</label>
            <textarea id="activity_tracking_notice" class="form-control" name="activity_tracking_notice" rows="4" required><?= e($settings['activity_tracking_notice']) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="activity_tracking_disabled_notice">Texto sin control de actividad</label>
            <textarea id="activity_tracking_disabled_notice" class="form-control" name="activity_tracking_disabled_notice" rows="4" required><?= e($settings['activity_tracking_disabled_notice']) ?></textarea>
        </div>

        <div class="col-12">
            <hr>
            <h2 class="h5 fw-bold mb-1">Mensaje al intentar salir</h2>
            <p class="text-muted mb-0">Se muestra al volver atras o navegar fuera de una evaluacion en curso.</p>
        </div>

        <div class="col-12">
            <hr>
            <h2 class="h5 fw-bold mb-1">Preguntas pendientes y tiempo agotado</h2>
            <p class="text-muted mb-0">Estos mensajes se usan en evaluaciones psicometricas, encuestas y evaluaciones.</p>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="incomplete_confirm_title">Titulo de preguntas pendientes</label>
            <input id="incomplete_confirm_title" class="form-control" type="text" name="incomplete_confirm_title" value="<?= e($settings['incomplete_confirm_title']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="expired_message">Mensaje al agotarse el tiempo</label>
            <textarea id="expired_message" class="form-control" name="expired_message" rows="3" required><?= e($settings['expired_message']) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="incomplete_confirm_message">Mensaje de confirmacion</label>
            <textarea id="incomplete_confirm_message" class="form-control" name="incomplete_confirm_message" rows="3" required><?= e($settings['incomplete_confirm_message']) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="incomplete_confirm_button">Boton para contestar pendientes</label>
            <input id="incomplete_confirm_button" class="form-control" type="text" name="incomplete_confirm_button" value="<?= e($settings['incomplete_confirm_button']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="incomplete_cancel_button">Boton para guardar y finalizar</label>
            <input id="incomplete_cancel_button" class="form-control" type="text" name="incomplete_cancel_button" value="<?= e($settings['incomplete_cancel_button']) ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="exit_confirm_title">Titulo</label>
            <input id="exit_confirm_title" class="form-control" type="text" name="exit_confirm_title" value="<?= e($settings['exit_confirm_title']) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="exit_continue_button">Boton continuar</label>
            <input id="exit_continue_button" class="form-control" type="text" name="exit_continue_button" value="<?= e($settings['exit_continue_button']) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="exit_save_exit_button">Boton guardar y salir</label>
            <input id="exit_save_exit_button" class="form-control" type="text" name="exit_save_exit_button" value="<?= e($settings['exit_save_exit_button']) ?>" required>
        </div>
        <div class="col-12">
            <label class="form-label" for="exit_confirm_message">Mensaje</label>
            <textarea id="exit_confirm_message" class="form-control" name="exit_confirm_message" rows="5" required><?= e($settings['exit_confirm_message']) ?></textarea>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="<?= e(route_url('settings')) ?>">Volver</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Guardar configuracion</button>
    </div>
</form>
