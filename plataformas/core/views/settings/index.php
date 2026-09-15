<?php
$companyBrandingMode = !empty($companyBrandingMode);
$mail = !empty($companyBrandingMode) ? ($companyMailSettings ?? []) : ($integrationsConfig['mail'] ?? []);
$dbConnections = is_array($databaseConfig['connections'] ?? null) ? $databaseConfig['connections'] : [];
$dbConnectionLabels = [
    'core' => ['label' => 'Core', 'description' => 'Usuarios, perfiles, empresas, campos de usuario y configuracion transversal.', 'default' => 'e_talent_core'],
    'tests' => ['label' => 'Tests', 'description' => 'Instrumentos, preguntas, sesiones, respuestas, scoring, baremos y reportes.', 'default' => 'e_talent_tests'],
];
$settingsTab = $settingsTab ?? '';
$verificationActive = $settingsTab === 'verification';
?>
<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Administracion</p>
        <h1 class="fw-bold mb-1"><?= $companyBrandingMode ? 'Identidad visual de la empresa' : 'Configuracion de plataforma' ?></h1>
        <p class="text-muted mb-0"><?= $companyBrandingMode ? 'Personaliza el login y la experiencia visual de tu empresa.' : 'Administra conexion, correo del sistema y experiencia visual del login.' ?></p>
    </div>
</section>

<ul class="nav nav-tabs settings-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation"><button id="settings-login-tab" class="nav-link <?= $verificationActive ? '' : 'active' ?>" role="tab" aria-controls="settings-login" aria-selected="<?= $verificationActive ? 'false' : 'true' ?>" data-bs-toggle="tab" data-bs-target="#settings-login" type="button">Login</button></li>
    <li class="nav-item" role="presentation"><button id="settings-design-tab" class="nav-link" role="tab" aria-controls="settings-design" aria-selected="false" data-bs-toggle="tab" data-bs-target="#settings-design" type="button">Diseño plataforma</button></li>
    <li class="nav-item" role="presentation"><button id="settings-mail-tab" class="nav-link" role="tab" aria-controls="settings-mail" aria-selected="false" data-bs-toggle="tab" data-bs-target="#settings-mail" type="button">Correo<?= !empty($companyBrandingMode) ? ' de la empresa' : '' ?></button></li>
    <?php if (!$companyBrandingMode): ?>
        <li class="nav-item" role="presentation"><button id="settings-operational-tab" class="nav-link" role="tab" aria-controls="settings-operational" aria-selected="false" data-bs-toggle="tab" data-bs-target="#settings-operational" type="button">Evidencia audiovisual</button></li>
        <li class="nav-item" role="presentation"><button id="settings-db-tab" class="nav-link" role="tab" aria-controls="settings-db" aria-selected="false" data-bs-toggle="tab" data-bs-target="#settings-db" type="button">Base de datos</button></li>
        <li class="nav-item" role="presentation"><button id="settings-platforms-tab" class="nav-link" role="tab" aria-controls="settings-platforms" aria-selected="false" data-bs-toggle="tab" data-bs-target="#settings-platforms" type="button">Sub-plataformas</button></li>
    <?php endif; ?>
    <?php if ($companyBrandingMode): ?><li class="nav-item" role="presentation"><button id="settings-verification-tab" class="nav-link <?= $verificationActive ? 'active' : '' ?>" role="tab" aria-controls="settings-verification" aria-selected="<?= $verificationActive ? 'true' : 'false' ?>" data-bs-toggle="tab" data-bs-target="#settings-verification" type="button">Verificación de usuarios</button></li><?php endif; ?>
</ul>

<div class="tab-content">
    <section id="settings-login" role="tabpanel" aria-labelledby="settings-login-tab" tabindex="0" class="tab-pane fade <?= $verificationActive ? '' : 'show active' ?> content-panel">
        <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end mb-4 pb-4 border-bottom">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="section" value="login_preset">
            <div class="col-12">
                <h2 class="h5 fw-bold mb-1">Analizar imagen de login</h2>
                <p class="text-muted mb-0">Carga una referencia visual para generar un fondo limpio, un logo limpio y completar solo posicion, colores y tamanos de texto.</p>
            </div>
            <div class="col-12 col-lg-8">
                <label class="form-label" for="login_reference">Imagen de referencia</label>
                <input id="login_reference" class="form-control" type="file" name="login_reference" accept="image/png,image/jpeg,image/webp" required>
            </div>
            <div class="col-12 col-lg-4">
                <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-magic me-1"></i> Analizar y aplicar</button>
            </div>
        </form>
        <form method="post" enctype="multipart/form-data" class="row g-4 needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="section" value="login">
            <div class="col-12 col-xl-7">
                <div class="row g-4">
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="login_title">Titulo</label>
                        <input id="login_title" class="form-control form-control-lg" name="login_title" value="<?= e($loginSettings['login_title']) ?>">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="login_title_font_size">Tamano titulo</label>
                        <input id="login_title_font_size" class="form-control form-control-lg" type="number" min="18" max="64" name="login_title_font_size" value="<?= e($loginSettings['login_title_font_size']) ?>">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="login_form_position">Posicion formulario</label>
                        <select id="login_form_position" class="form-select form-select-lg" name="login_form_position">
                            <?php foreach (['left' => 'Izquierda', 'center' => 'Centro', 'right' => 'Derecha'] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $loginSettings['login_form_position'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="login_identifier">Usuario de acceso</label>
                        <select id="login_identifier" class="form-select form-select-lg" name="login_identifier">
                            <?php foreach (['email' => 'Correo', 'rut' => 'RUT'] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= ($loginSettings['login_identifier'] ?? 'email') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Define que dato se pedira como usuario en el login.</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="login_password_recovery_enabled" name="login_password_recovery_enabled" value="1" <?= !empty($loginSettings['login_password_recovery_enabled']) && in_array((string) $loginSettings['login_password_recovery_enabled'], ['1', 'true', 'on', 'yes'], true) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="login_password_recovery_enabled">Permitir olvidar contraseña</label>
                        </div>
                        <div class="form-text">Controla si los usuarios de esta empresa pueden solicitar un enlace para recuperar su clave.</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="login_two_step_enabled" name="login_two_step_enabled" value="1" <?= !empty($loginSettings['login_two_step_enabled']) && in_array((string) $loginSettings['login_two_step_enabled'], ['1', 'true', 'on', 'yes'], true) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="login_two_step_enabled">Activar verificación de dos pasos por correo</label>
                        </div>
                        <div class="form-text">Después de validar usuario y clave, se enviará un código de 6 dígitos. El código anterior queda invalidado al generar uno nuevo.</div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="login_two_step_expiration_minutes">Vigencia del código (minutos)</label>
                        <input id="login_two_step_expiration_minutes" class="form-control form-control-lg" type="number" min="2" max="15" name="login_two_step_expiration_minutes" value="<?= e($loginSettings['login_two_step_expiration_minutes']) ?>">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="login_two_step_max_attempts">Intentos máximos por código</label>
                        <input id="login_two_step_max_attempts" class="form-control form-control-lg" type="number" min="3" max="10" name="login_two_step_max_attempts" value="<?= e($loginSettings['login_two_step_max_attempts']) ?>">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="login_subtitle_font_size">Tamano bajada</label>
                        <input id="login_subtitle_font_size" class="form-control form-control-lg" type="number" min="12" max="32" name="login_subtitle_font_size" value="<?= e($loginSettings['login_subtitle_font_size']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="login_subtitle">Texto bajada</label>
                        <input id="login_subtitle" class="form-control" name="login_subtitle" value="<?= e($loginSettings['login_subtitle']) ?>">
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label" for="login_background_color">Color fondo pagina</label>
                        <input id="login_background_color" class="form-control form-control-color w-100" type="color" name="login_background_color" value="<?= e($loginSettings['login_background_color']) ?>">
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label" for="login_form_background_color">Color fondo formulario</label>
                        <input id="login_form_background_color" class="form-control form-control-color w-100" type="color" name="login_form_background_color" value="<?= e($loginSettings['login_form_background_color']) ?>">
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label" for="login_title_color">Color titulo</label>
                        <input id="login_title_color" class="form-control form-control-color w-100" type="color" name="login_title_color" value="<?= e($loginSettings['login_title_color']) ?>">
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label" for="login_subtitle_color">Color bajada</label>
                        <input id="login_subtitle_color" class="form-control form-control-color w-100" type="color" name="login_subtitle_color" value="<?= e($loginSettings['login_subtitle_color']) ?>">
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label" for="login_button_color">Color boton</label>
                        <input id="login_button_color" class="form-control form-control-color w-100" type="color" name="login_button_color" value="<?= e($loginSettings['login_button_color']) ?>">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="login_logo">Logo</label>
                        <input id="login_logo" class="form-control" type="file" name="login_logo" accept="image/png,image/jpeg,image/webp">
                        <?php if ($loginSettings['login_logo_path']): ?><div class="form-text"><?= e($loginSettings['login_logo_path']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="login_background">Imagen de fondo</label>
                        <input id="login_background" class="form-control" type="file" name="login_background" accept="image/png,image/jpeg,image/webp">
                        <?php if ($loginSettings['login_background_path']): ?><div class="form-text"><?= e($loginSettings['login_background_path']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-5">
                <div class="login-preview login-preview-<?= e($loginSettings['login_form_position']) ?>" style="--preview-bg: <?= e($loginSettings['login_background_color']) ?>; --preview-form-bg: <?= e($loginSettings['login_form_background_color']) ?>; --preview-primary: <?= e($loginSettings['login_button_color']) ?>; --preview-button-color: <?= e($loginSettings['login_button_color']) ?>; <?= $loginSettings['login_background_path'] ? '--preview-bg-image:url(' . e(url($loginSettings['login_background_path'])) . ');' : '' ?>">
                    <div class="login-preview-card">
                        <?php if ($loginSettings['login_logo_path']): ?>
                            <img src="<?= e(url($loginSettings['login_logo_path'])) ?>" alt="Logo" class="login-preview-logo">
                        <?php else: ?>
                            <span class="login-preview-logo"><i class="bi bi-shield-check"></i></span>
                        <?php endif; ?>
                        <?php if (trim((string) $loginSettings['login_title']) !== ''): ?>
                            <strong style="font-size: <?= (int) $loginSettings['login_title_font_size'] ?>px; color: <?= e($loginSettings['login_title_color']) ?>;"><?= e($loginSettings['login_title']) ?></strong>
                        <?php endif; ?>
                        <small style="font-size: <?= (int) $loginSettings['login_subtitle_font_size'] ?>px; color: <?= e($loginSettings['login_subtitle_color']) ?>;"><?= e($loginSettings['login_subtitle']) ?></small>
                        <span class="login-preview-button">Ingresar</span>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i> Guardar login</button>
            </div>
        </form>
    </section>

    <?php if ($companyBrandingMode && $company): ?>
        <section id="settings-verification" role="tabpanel" aria-labelledby="settings-verification-tab" tabindex="0" class="tab-pane fade <?= $verificationActive ? 'show active' : '' ?> content-panel">
            <form method="post" class="row g-4 needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="section" value="user_verification">
                <div class="col-12">
                    <h2 class="h5 fw-bold mb-1">Verificación pública de usuarios</h2>
                    <p class="text-muted mb-0">Permite consultar, sin iniciar sesión, si un usuario está activo y en qué procesos participa dentro de tu empresa.</p>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="verification_enabled" name="verification_enabled" value="1" <?= (int) ($company['verification_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="verification_enabled">Activar pantalla de verificación</label>
                    </div>
                </div>
                <div class="col-12 col-lg-8">
                    <label class="form-label" for="verification_url">URL de acceso</label>
                    <input id="verification_url" class="form-control" type="text" value="<?= e(app_absolute_url('/' . $company['url_prefix'] . '/verificar-usuario')) ?>" readonly>
                    <div class="form-text">Comparte este enlace con las personas autorizadas para realizar consultas.</div>
                </div>
                <div class="col-12 col-lg-4 d-flex align-items-end"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('/' . $company['url_prefix'] . '/verificar-usuario')) ?>" target="_blank" rel="noopener">Abrir pantalla</a></div>
                <div class="col-12"><button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i> Guardar configuración</button></div>
            </form>
        </section>
    <?php endif; ?>

    <?php if (!$companyBrandingMode): ?><section id="settings-operational" role="tabpanel" aria-labelledby="settings-operational-tab" tabindex="0" class="tab-pane fade content-panel">
        <form method="post" class="row g-4 needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="section" value="operational">
            <div class="col-12">
                <h2 class="h5 fw-bold mb-1">Retención y almacenamiento audiovisual</h2>
                <p class="text-muted mb-0">Define los límites operativos de las evidencias de evaluaciones supervisadas.</p>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="test_evidence_retention_days">Retención (días)</label>
                <input id="test_evidence_retention_days" class="form-control" type="number" min="1" max="3650" name="test_evidence_retention_days" value="<?= (int) ($operationalSettings['test_evidence_retention_days'] ?? 365) ?>" required>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="test_evidence_max_size_mb">Tamaño máximo por evidencia (MB)</label>
                <input id="test_evidence_max_size_mb" class="form-control" type="number" min="50" max="5000" name="test_evidence_max_size_mb" value="<?= (int) ($operationalSettings['test_evidence_max_size_mb'] ?? 500) ?>" required>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="test_evidence_chunk_size_mb">Tamaño de fragmento (MB)</label>
                <input id="test_evidence_chunk_size_mb" class="form-control" type="number" min="1" max="50" name="test_evidence_chunk_size_mb" value="<?= (int) ($operationalSettings['test_evidence_chunk_size_mb'] ?? 10) ?>" required>
            </div>
            <div class="col-12"><button class="btn btn-primary px-4" type="submit"><i class="bi bi-hdd-stack me-1"></i> Guardar política</button></div>
        </form>
    </section><?php endif; ?>

    <section id="settings-design" role="tabpanel" aria-labelledby="settings-design-tab" tabindex="0" class="tab-pane fade content-panel">
        <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end mb-4 pb-4 border-bottom">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="section" value="design_preset">
            <div class="col-12">
                <h2 class="h5 fw-bold mb-1">Analizar imagen de plataforma</h2>
                <p class="text-muted mb-0">Carga una referencia para detectar solo menu superior, fondo general, cards, botones y tablas.</p>
            </div>
            <div class="col-12 col-lg-8">
                <label class="form-label" for="design_reference">Imagen de referencia</label>
                <input id="design_reference" class="form-control" type="file" name="design_reference" accept="image/png,image/jpeg,image/webp" required>
            </div>
            <div class="col-12 col-lg-4">
                <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-magic me-1"></i> Analizar y aplicar</button>
            </div>
        </form>
        <form method="post" enctype="multipart/form-data" class="row g-4 needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="section" value="design">

            <div class="col-12"><h2 class="h5 fw-bold mb-1">Menu superior</h2></div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="topbar_name">Nombre barra superior</label>
                <input id="topbar_name" class="form-control" name="topbar_name" value="<?= e($designSettings['topbar_name']) ?>" maxlength="80" required>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="topbar_icon">Logo barra superior</label>
                <input id="topbar_icon" class="form-control" type="file" name="topbar_icon" accept="image/png,image/jpeg,image/webp">
                <?php if ($designSettings['topbar_icon_path']): ?><div class="form-text"><?= e($designSettings['topbar_icon_path']) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="topbar_background_color">Color fondo barra superior</label>
                <input id="topbar_background_color" class="form-control form-control-color w-100" type="color" name="topbar_background_color" value="<?= e($designSettings['topbar_background_color']) ?>">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="topbar_menu_background_color">Color fondo menu superior</label>
                <input id="topbar_menu_background_color" class="form-control form-control-color w-100" type="color" name="topbar_menu_background_color" value="<?= e($designSettings['topbar_menu_background_color']) ?>">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="topbar_menu_button_color">Color botones menu superior</label>
                <input id="topbar_menu_button_color" class="form-control form-control-color w-100" type="color" name="topbar_menu_button_color" value="<?= e($designSettings['topbar_menu_button_color']) ?>">
            </div>

            <div class="col-12"><hr><h2 class="h5 fw-bold mb-1">General</h2></div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="layout_background_color">Fondo general</label>
                <input id="layout_background_color" class="form-control form-control-color w-100" type="color" name="layout_background_color" value="<?= e($designSettings['layout_background_color']) ?>">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="portal_text_color">Color texto</label>
                <input id="portal_text_color" class="form-control form-control-color w-100" type="color" name="portal_text_color" value="<?= e($designSettings['portal_text_color']) ?>">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="app_primary_color">Color primario botones</label>
                <input id="app_primary_color" class="form-control form-control-color w-100" type="color" name="app_primary_color" value="<?= e($designSettings['app_primary_color']) ?>">
            </div>

            <div class="col-12"><hr><h2 class="h5 fw-bold mb-1">Tipografia</h2></div>
            <div class="col-12">
                <label class="form-label" for="app_font_url">URL Google Fonts</label>
                <input id="app_font_url" class="form-control" type="url" name="app_font_url" value="<?= e($designSettings['app_font_url']) ?>" placeholder="https://fonts.googleapis.com/...">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="app_font_family">Fuente base heredada</label>
                <input id="app_font_family" class="form-control" name="app_font_family" value="<?= e($designSettings['app_font_family']) ?>" maxlength="255">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="font_heading_family">Fuente titulos</label>
                <input id="font_heading_family" class="form-control" name="font_heading_family" value="<?= e($designSettings['font_heading_family']) ?>" maxlength="255">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="font_body_family">Fuente cuerpo/UI</label>
                <input id="font_body_family" class="form-control" name="font_body_family" value="<?= e($designSettings['font_body_family']) ?>" maxlength="255">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="app_font_size">Base heredada</label>
                <input id="app_font_size" class="form-control" type="number" min="12" max="22" name="app_font_size" value="<?= e($designSettings['app_font_size']) ?>">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="font_size_h1">H1</label>
                <input id="font_size_h1" class="form-control" type="number" min="24" max="72" name="font_size_h1" value="<?= e($designSettings['font_size_h1']) ?>">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="font_size_h2">H2</label>
                <input id="font_size_h2" class="form-control" type="number" min="18" max="48" name="font_size_h2" value="<?= e($designSettings['font_size_h2']) ?>">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="font_size_h3">H3</label>
                <input id="font_size_h3" class="form-control" type="number" min="16" max="36" name="font_size_h3" value="<?= e($designSettings['font_size_h3']) ?>">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="font_size_subtitle">Bajada</label>
                <input id="font_size_subtitle" class="form-control" type="number" min="12" max="30" name="font_size_subtitle" value="<?= e($designSettings['font_size_subtitle']) ?>">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="font_size_body">Cuerpo</label>
                <input id="font_size_body" class="form-control" type="number" min="12" max="22" name="font_size_body" value="<?= e($designSettings['font_size_body']) ?>">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="font_size_small">Pequeno</label>
                <input id="font_size_small" class="form-control" type="number" min="10" max="16" name="font_size_small" value="<?= e($designSettings['font_size_small']) ?>">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="font_size_nav">Menu</label>
                <input id="font_size_nav" class="form-control" type="number" min="12" max="20" name="font_size_nav" value="<?= e($designSettings['font_size_nav']) ?>">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="font_size_button">Botones</label>
                <input id="font_size_button" class="form-control" type="number" min="12" max="22" name="font_size_button" value="<?= e($designSettings['font_size_button']) ?>">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="font_size_table">Tablas</label>
                <input id="font_size_table" class="form-control" type="number" min="11" max="18" name="font_size_table" value="<?= e($designSettings['font_size_table']) ?>">
            </div>

            <div class="col-12"><hr><h2 class="h5 fw-bold mb-1">Cards o cajas contenedoras</h2></div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="card_header_background_color">Fondo card-header</label>
                <input id="card_header_background_color" class="form-control form-control-color w-100" type="color" name="card_header_background_color" value="<?= e($designSettings['card_header_background_color']) ?>">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="card_content_background_color">Fondo card-content</label>
                <input id="card_content_background_color" class="form-control form-control-color w-100" type="color" name="card_content_background_color" value="<?= e($designSettings['card_content_background_color']) ?>">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="card_text_color">Color texto</label>
                <input id="card_text_color" class="form-control form-control-color w-100" type="color" name="card_text_color" value="<?= e($designSettings['card_text_color']) ?>">
            </div>

            <div class="col-12"><hr><h2 class="h5 fw-bold mb-1">Botones</h2></div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="button_background_color">Color fondo</label>
                <input id="button_background_color" class="form-control form-control-color w-100" type="color" name="button_background_color" value="<?= e($designSettings['button_background_color']) ?>">
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="button_text_color">Color texto</label>
                <input id="button_text_color" class="form-control form-control-color w-100" type="color" name="button_text_color" value="<?= e($designSettings['button_text_color']) ?>">
            </div>

            <div class="col-12"><hr><h2 class="h5 fw-bold mb-1">Tablas</h2></div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="table_border_color">Color bordes</label>
                <input id="table_border_color" class="form-control form-control-color w-100" type="color" name="table_border_color" value="<?= e($designSettings['table_border_color']) ?>">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="table_header_background_color">Fondo thead</label>
                <input id="table_header_background_color" class="form-control form-control-color w-100" type="color" name="table_header_background_color" value="<?= e($designSettings['table_header_background_color']) ?>">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="table_header_text_color">Texto thead</label>
                <input id="table_header_text_color" class="form-control form-control-color w-100" type="color" name="table_header_text_color" value="<?= e($designSettings['table_header_text_color']) ?>">
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="table_body_background_color">Fondo tbody</label>
                <input id="table_body_background_color" class="form-control form-control-color w-100" type="color" name="table_body_background_color" value="<?= e($designSettings['table_body_background_color']) ?>">
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="table_body_text_color">Texto tbody</label>
                <input id="table_body_text_color" class="form-control form-control-color w-100" type="color" name="table_body_text_color" value="<?= e($designSettings['table_body_text_color']) ?>">
            </div>

            <div class="col-12">
                <button class="btn btn-primary px-4" type="submit"><i class="bi bi-palette me-1"></i> Guardar diseño</button>
            </div>
        </form>
    </section>

    <section id="settings-mail" role="tabpanel" aria-labelledby="settings-mail-tab" tabindex="0" class="tab-pane fade content-panel">
        <form method="post" class="row g-4 needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="section" value="mail">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input id="mail_enabled" class="form-check-input" type="checkbox" name="mail_enabled" <?= !empty($mail['enabled']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mail_enabled">Correo <?= !empty($companyBrandingMode) ? 'de la empresa' : 'global' ?> activo</label>
                </div>
                <?php if (!empty($companyBrandingMode)): ?>
                    <div class="form-text">Si el correo de la empresa no está configurado o queda desactivado, se utilizará el correo SMTP general.</div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-lg-6"><label class="form-label" for="mail_host">Servidor SMTP</label><input id="mail_host" class="form-control" name="mail_host" value="<?= e($mail['host'] ?? '') ?>"></div>
            <div class="col-12 col-lg-2"><label class="form-label" for="mail_port">Puerto</label><input id="mail_port" class="form-control" type="number" name="mail_port" value="<?= (int) ($mail['port'] ?? 587) ?>"></div>
            <div class="col-12 col-lg-4"><label class="form-label" for="mail_encryption">Seguridad SMTP</label><select id="mail_encryption" class="form-select" name="mail_encryption"><option value="none" <?= ($mail['encryption'] ?? 'starttls') === 'none' ? 'selected' : '' ?>>Sin cifrado</option><option value="starttls" <?= ($mail['encryption'] ?? 'starttls') === 'starttls' ? 'selected' : '' ?>>STARTTLS</option><option value="smtps" <?= ($mail['encryption'] ?? 'starttls') === 'smtps' ? 'selected' : '' ?>>SMTPS / SSL</option></select></div>
            <div class="col-12 col-lg-4"><label class="form-label" for="mail_username">Usuario SMTP</label><input id="mail_username" class="form-control" name="mail_username" value="<?= e($mail['username'] ?? '') ?>"></div>
            <div class="col-12 col-lg-4"><label class="form-label" for="mail_auth_type">Método de autenticación</label><select id="mail_auth_type" class="form-select" name="mail_auth_type"><option value="">Automático</option><option value="LOGIN">LOGIN</option><option value="PLAIN">PLAIN</option><option value="CRAM-MD5">CRAM-MD5</option></select></div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="mail_password">Clave SMTP</label>
                <input id="mail_password" class="form-control" type="password" name="mail_password" placeholder="Dejar vacía para mantener">
                <div class="form-text"><?= !empty($mail['password_configured']) ? 'Clave SMTP configurada y cifrada.' : 'La clave se almacenará cifrada.' ?></div>
                <?php if (!empty($mail['password_configured'])): ?>
                    <div class="form-check mt-2">
                        <input id="mail_clear_password" class="form-check-input" type="checkbox" name="mail_clear_password" value="1">
                        <label class="form-check-label" for="mail_clear_password">Eliminar la clave SMTP guardada</label>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-lg-3"><label class="form-label" for="mail_timeout">Tiempo de espera (segundos)</label><input id="mail_timeout" class="form-control" type="number" min="5" max="120" name="mail_timeout" value="<?= (int) ($mail['timeout'] ?? 30) ?>"></div>
            <div class="col-12 col-lg-3"><div class="form-check form-switch mt-lg-4"><input id="mail_smtp_auth" class="form-check-input" type="checkbox" name="mail_smtp_auth" <?= !empty($mail['smtp_auth']) ? 'checked' : '' ?>><label class="form-check-label" for="mail_smtp_auth">Autenticación SMTP</label></div></div>
            <div class="col-12 col-lg-3"><label class="form-label" for="from_email">Correo remitente</label><input id="from_email" class="form-control" type="email" name="from_email" value="<?= e($mail['from_email'] ?? '') ?>"></div>
            <div class="col-12 col-lg-3"><label class="form-label" for="from_name">Nombre remitente</label><input id="from_name" class="form-control" name="from_name" value="<?= e($mail['from_name'] ?? '') ?>"></div>
            <div class="col-12"><button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i> Guardar correo</button></div>
        </form>
    </section>

    <?php if (!$companyBrandingMode): ?><section id="settings-db" role="tabpanel" aria-labelledby="settings-db-tab" tabindex="0" class="tab-pane fade content-panel">
        <form method="post" class="row g-4 needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="section" value="database">
            <div class="col-12">
                <h2 class="h5 fw-bold mb-1">Servidor compartido</h2>
                <p class="text-muted mb-0">Las sub-plataformas comparten credenciales de servidor, pero cada una usa su propia base de datos aislada.</p>
            </div>
            <div class="col-12 col-lg-4"><label class="form-label" for="host">Host</label><input id="host" class="form-control" name="host" value="<?= e($databaseConfig['host'] ?? '') ?>"></div>
            <div class="col-12 col-lg-2"><label class="form-label" for="port">Puerto</label><input id="port" class="form-control" name="port" value="<?= e($databaseConfig['port'] ?? '3306') ?>"></div>
            <div class="col-12 col-lg-3"><label class="form-label" for="charset">Charset</label><input id="charset" class="form-control" name="charset" value="<?= e($databaseConfig['charset'] ?? 'utf8mb4') ?>"></div>
            <div class="col-12 col-lg-4"><label class="form-label" for="username">Usuario</label><input id="username" class="form-control" name="username" value="<?= e($databaseConfig['username'] ?? '') ?>" required></div>
            <div class="col-12 col-lg-4"><label class="form-label" for="password">Password</label><input id="password" class="form-control" type="password" name="password" placeholder="Dejar vacia para mantener"></div>
            <div class="col-12 col-lg-4"><label class="form-label" for="socket">Socket</label><input id="socket" class="form-control" name="socket" value="<?= e($databaseConfig['socket'] ?? '') ?>"></div>
            <div class="col-12">
                <hr>
                <h2 class="h5 fw-bold mb-1">Bases por sub-plataforma</h2>
                <p class="text-muted mb-0">Cada nueva sub-plataforma debe registrar aqui su propia base. El prefijo del proyecto es <code>pm_</code>.</p>
            </div>
            <?php foreach ($dbConnectionLabels as $key => $meta): ?>
                <?php $connection = is_array($dbConnections[$key] ?? null) ? $dbConnections[$key] : []; ?>
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="db_<?= e($key) ?>"><?= e($meta['label']) ?></label>
                    <input id="db_<?= e($key) ?>" class="form-control" name="connections[<?= e($key) ?>][database]" value="<?= e($connection['database'] ?? $meta['default']) ?>" required>
                    <div class="form-text"><?= e($meta['description']) ?></div>
                </div>
            <?php endforeach; ?>
            <div class="col-12"><button class="btn btn-primary px-4" type="submit"><i class="bi bi-shield-lock me-1"></i> Guardar cifrado</button></div>
        </form>
    </section><?php endif; ?>

    <?php if (!$companyBrandingMode): ?><section id="settings-platforms" role="tabpanel" aria-labelledby="settings-platforms-tab" tabindex="0" class="tab-pane fade content-panel">
        <div class="row g-3">
            <div class="col-12">
                <h2 class="h5 fw-bold mb-1">Configuracion por sub-plataforma</h2>
                <p class="text-muted mb-0">Cada sub-plataforma mantiene sus parametros en su propio modulo y base de datos.</p>
            </div>
            <div class="col-md-6 col-xl-4">
                <div class="metric-card h-100">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <span class="metric-icon mb-3"><i class="bi bi-clipboard2-pulse"></i></span>
                            <h3 class="h6 fw-bold mb-1">Evaluaciones</h3>
                            <p class="text-muted mb-3">Mensajes de ingreso, salida y control de actividad de evaluaciones.</p>
                            <a class="btn btn-sm btn-primary" href="<?= e(route_url('tests.settings')) ?>">Configurar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section><?php endif; ?>
</div>
