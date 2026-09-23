<?php
$old = $_POST ?? [];
$errors = (array) ($errors ?? []);
$result = is_array($result ?? null) ? $result : null;
?>
<section class="page-header" data-page-back-url="<?= e(route_url('evaluation-surveys.assessments')) ?>">
    <div><p class="dashboard-kicker mb-2">Encuestas / Evaluaciones</p><h1 class="fw-bold mb-1">Importar evaluación desde Moodle</h1><p class="text-muted mb-0">Sincroniza una batería y conviértela en una evaluación calificable de e-talent.</p></div>
    <div class="page-header-actions"><a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('evaluation-surveys.assessments')) ?>"><i class="bi bi-arrow-left me-1"></i>Volver</a></div>
</section>
<?php foreach ($errors as $error): ?><div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-1"></i><?= e((string) $error) ?></div><?php endforeach; ?>
<section class="card content-panel">
    <div class="alert alert-info small"><strong>Importación segura:</strong> la plataforma realiza la consulta antes de publicar la evaluación, guarda la batería completa como borrador y configura por defecto 7 preguntas aleatorias por intento. El token solo se usa durante esta solicitud y no se muestra ni se persiste.</div>
    <form method="post" class="row g-4 needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="col-12"><div class="evaluation-form-section-heading"><span class="evaluation-form-section-number">1</span><div><h2>Conexión a Moodle</h2><p>Usa una cuenta técnica con permisos mínimos de lectura.</p></div></div></div>
        <div class="col-12 col-lg-8"><label class="form-label" for="moodle_base_url">URL base de Moodle</label><input id="moodle_base_url" class="form-control" type="url" name="moodle_base_url" value="<?= e((string) ($old['moodle_base_url'] ?? '')) ?>" placeholder="https://moodle.ejemplo.cl" required><div class="form-text">No incluyas <code>/webservice/rest/server.php</code>; la plataforma lo agrega.</div></div>
        <div class="col-12 col-lg-4"><label class="form-label" for="moodle_token">Token Web Service</label><input id="moodle_token" class="form-control" type="password" name="moodle_token" autocomplete="new-password" required></div>
        <div class="col-12 col-md-4"><label class="form-label" for="moodle_course_id">ID del curso <span class="text-muted">(opcional)</span></label><input id="moodle_course_id" class="form-control" type="number" min="0" name="moodle_course_id" value="<?= e((string) ($old['moodle_course_id'] ?? '')) ?>"></div>
        <div class="col-12 col-md-4"><label class="form-label" for="moodle_quiz_id">ID del cuestionario</label><input id="moodle_quiz_id" class="form-control" type="number" min="1" name="moodle_quiz_id" value="<?= e((string) ($old['moodle_quiz_id'] ?? '')) ?>" required></div>
        <div class="col-12 col-md-4"><label class="form-label" for="moodle_user_id">ID usuario técnico <span class="text-muted">(opcional)</span></label><input id="moodle_user_id" class="form-control" type="number" min="0" name="moodle_user_id" value="<?= e((string) ($old['moodle_user_id'] ?? '')) ?>"><div class="form-text">Se usa para consultar explícitamente el umbral de aprobación.</div></div>
        <div class="col-12 col-md-4"><label class="form-label" for="question_display_limit">Preguntas por intento</label><input id="question_display_limit" class="form-control" type="number" min="1" max="100" name="question_display_limit" value="<?= e((string) ($old['question_display_limit'] ?? '7')) ?>" required><div class="form-text">La batería completa se importa, pero cada intento mostrará esta cantidad aleatoria.</div></div>
        <div class="col-12"><div class="evaluation-form-section-heading"><span class="evaluation-form-section-number">2</span><div><h2>Servicios a invocar</h2><p>El primer servicio es estándar; el servicio de definición debe existir en Moodle para entregar preguntas y alternativas en formato estructurado.</p></div></div></div>
        <div class="col-12 col-lg-4"><label class="form-label" for="moodle_quiz_function">Servicio resumen del cuestionario</label><input id="moodle_quiz_function" class="form-control" name="moodle_quiz_function" value="<?= e((string) ($old['moodle_quiz_function'] ?? 'mod_quiz_get_quizzes_by_courses')) ?>"></div>
        <div class="col-12 col-lg-4"><label class="form-label" for="moodle_definition_function">Servicio definición/preguntas</label><input id="moodle_definition_function" class="form-control" name="moodle_definition_function" value="<?= e((string) ($old['moodle_definition_function'] ?? 'local_etalent_get_quiz_definition')) ?>" required></div>
        <div class="col-12 col-lg-4"><label class="form-label" for="moodle_grade_function">Servicio nota/aprobación</label><input id="moodle_grade_function" class="form-control" name="moodle_grade_function" value="<?= e((string) ($old['moodle_grade_function'] ?? 'mod_quiz_get_user_best_grade')) ?>"></div>
        <div class="col-12"><div class="form-text">El servicio personalizado recomendado debe devolver <code>quiz</code>, <code>questions</code>, <code>options</code> y la fracción/puntaje de cada alternativa correcta. Moodle estándar no entrega toda la clave de respuestas como un único servicio administrativo.</div></div>
        <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary px-4" type="submit"><i class="bi bi-cloud-arrow-down me-1"></i>Consultar e importar</button><a class="btn btn-outline-secondary" href="<?= e(route_url('evaluation-surveys.assessments')) ?>">Cancelar</a></div>
    </form>
</section>
