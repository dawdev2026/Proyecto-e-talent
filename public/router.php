<?php
declare(strict_types=1);

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
defined('CONFIG_PATH') || define('CONFIG_PATH', BASE_PATH . '/config');
defined('SISTEMA_PATH') || define('SISTEMA_PATH', BASE_PATH . '/sistema');
defined('UTILS_PATH') || define('UTILS_PATH', BASE_PATH . '/utils');
defined('PLATAFORMAS_PATH') || define('PLATAFORMAS_PATH', BASE_PATH . '/plataformas');
defined('PUBLIC_PATH') || define('PUBLIC_PATH', BASE_PATH . '/public');
defined('TMP_PATH') || define('TMP_PATH', BASE_PATH . '/tmp');

require_once SISTEMA_PATH . '/core/security.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptName !== '' ? rtrim(str_replace('\\', '/', dirname($scriptName)), '/') : '';
$hasPhysicalBasePath = $basePath !== ''
    && $basePath !== '.'
    && $basePath !== '/';
$pathUsesPhysicalBase = $hasPhysicalBasePath
    && ($path === $basePath || strpos($path, $basePath . '/') === 0);

if ($pathUsesPhysicalBase) {
    $cleanPath = substr($path, strlen($basePath)) ?: '/';
    $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
    $location = $cleanPath . ($queryString !== '' ? '?' . $queryString : '');

    // Canonicaliza solicitudes que llegaron con la ruta física expuesta.
    // Se conserva íntegramente lo que siga al directorio public, incluido
    // cualquier prefijo de empresa utilizado por la plataforma.
    header('Location: ' . $location, true, 302);
    exit;
}

// Cuando el proyecto está detrás de una reescritura desde public_html,
// SCRIPT_NAME puede contener la ruta física del servidor. La plataforma debe
// generar sus URLs desde la raíz pública del dominio, no desde ese directorio.
$_SERVER['SCRIPT_NAME'] = '/router.php';
enforce_https_policy(load_config('app'));

function configure_router_timezone(): void
{
    $configPath = dirname(__DIR__) . '/config/app.php';
    $config = is_file($configPath) ? require $configPath : [];
    $timezone = is_array($config) ? ($config['timezone'] ?? 'America/Santiago') : 'America/Santiago';

    date_default_timezone_set((string) $timezone);
}

function deny_public_file_access(): void
{
    platform_error(403, 'Archivo protegido por la plataforma.', [
        'heading' => 'Archivo protegido por la plataforma.',
        'message' => 'Este recurso solo puede cargarse desde una sesion valida de la plataforma.',
        'chips' => ['Archivo protegido', 'Acceso restringido'],
    ]);
}

function deny_protected_asset_access(string $file): void
{
    http_response_code(403);
    header('Content-Type: ' . protected_file_mime_type($file));
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    exit;
}

function is_same_origin_platform_request(): bool
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer === '') {
        return false;
    }

    $refererHost = parse_url($referer, PHP_URL_HOST);
    $refererPort = parse_url($referer, PHP_URL_PORT);
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    $refererAuthority = is_string($refererHost) ? $refererHost . ($refererPort ? ':' . $refererPort : '') : '';

    return $refererAuthority !== '' && $currentHost !== '' && strcasecmp($refererAuthority, $currentHost) === 0;
}

function router_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function router_master_key(): string
{
    $path = dirname(__DIR__) . '/config/master.key';

    if (!is_file($path)) {
        file_put_contents($path, base64_encode(random_bytes(32)) . PHP_EOL, LOCK_EX);
        @chmod($path, 0600);
    }

    $key = base64_decode(trim((string) file_get_contents($path)), true);
    if ($key === false || strlen($key) !== 32) {
        deny_public_file_access();
    }

    return $key;
}

function is_valid_daily_asset_signature(string $path): bool
{
    $token = $_GET['_asset'] ?? '';
    if (!is_string($token) || $token === '') {
        return false;
    }

    $cleanPath = ltrim((string) parse_url($path, PHP_URL_PATH), '/');
    $expected = router_base64url_encode(hash_hmac('sha256', $cleanPath . '|' . date('Y-m-d'), router_master_key(), true));

    return hash_equals($expected, $token);
}

function protected_file_mime_type(string $file): string
{
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'pdf' => 'application/pdf',
        'wasm' => 'application/wasm',
    ];

    return $mimeTypes[$extension] ?? 'application/octet-stream';
}

function serve_protected_public_file(string $path, string $file): void
{
    $publicRoot = realpath(__DIR__);
    $realFile = realpath($file);

    if ($publicRoot === false || $realFile === false || strpos($realFile, $publicRoot . DIRECTORY_SEPARATOR) !== 0) {
        deny_public_file_access();
    }

    if (!is_valid_daily_asset_signature($path)) {
        deny_protected_asset_access($realFile);
    }

    header('Content-Type: ' . protected_file_mime_type($realFile));
    header('Content-Length: ' . filesize($realFile));
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($realFile);
    exit;
}

configure_router_timezone();
require_once dirname(__DIR__) . '/sistema/bootstrap.php';

$companyContext = company_url_context_for_path($path);
set_company_url_context($companyContext);
remember_navigation();
if ($companyContext) {
    $prefix = trim((string) ($companyContext['url_prefix'] ?? ''), '/');
    $prefixPath = '/' . $prefix;
    $path = $path === $prefixPath ? '/' : substr($path, strlen($prefixPath)) ?: '/';
}

$file = __DIR__ . $path;
$isSharedProtectedAsset = is_protected_asset_path($path) && is_file($file);

if (current_user() && (int) (current_user()['company_id'] ?? 0) > 0) {
    $userCompanyId = (int) current_user()['company_id'];
    if (!$companyContext && !$isSharedProtectedAsset) {
        $redirectPath = trim($path, '/');
        redirect(company_route_path($redirectPath));
    }
    if ($companyContext && (int) ($companyContext['id'] ?? 0) !== $userCompanyId) {
        platform_error(403, 'El usuario no pertenece a la empresa indicada en la URL.');
    }
}

if ($path === '/favicon.ico' && !is_file($file)) {
    http_response_code(204);
    exit;
}

if ($path !== '/' && is_file($file)) {
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
        if (basename($file) === 'router.php') {
            platform_error(404, 'Pagina no encontrada.');
        }

        require $file;
        return;
    }

    serve_protected_public_file($path, $file);
}

if ($path !== '/' && is_dir($file)) {
    deny_public_file_access();
}

$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($segments === []) {
        if (current_user() && profile_home_route() !== 'dashboard') {
            redirect(profile_home_url());
        }
        (new DashboardController())->index();
        return;
    }

    if ($segments === ['verificar-usuario']) {
        (new UserVerificationController())->index();
        return;
    }

    if ($segments === ['reconocimiento-facial', 'enrolar']) {
        (new FacialRecognitionController())->enroll();
        return;
    }

    if ($segments === ['reconocimiento-facial', 'enrolados']) {
        (new FacialRecognitionController())->enrolledUsers();
        return;
    }

    if ($segments === ['administrador-cliente', 'reportes', 'incidencias-tests']) {
        (new ClientAdminController())->testIncidents();
        return;
    }

    if ($segments === ['administrador-cliente', 'inicio']) {
        (new ClientAdminController())->dashboard();
        return;
    }

    if ($segments === ['administrador-cliente', 'reportes', 'incidencias-evaluaciones']) {
        (new EvaluationSurveyController())->dashboardIntegrityReport();
        return;
    }

    if ($segments === ['administrador-cliente', 'avance', 'evaluaciones']) {
        (new ClientAdminController())->evaluationProgress();
        return;
    }

    if ($segments === ['administrador-cliente', 'avance', 'tests']) {
        (new ClientAdminController())->testProgress();
        return;
    }

    if ($segments === ['administrador-cliente', 'avance', 'consultar-usuario']) {
        (new ClientAdminController())->userLookup();
        return;
    }

    if ($segments === ['administrador-cliente', 'avance', 'revision-componentes']) {
        (new ClientAdminController())->componentReviews();
        return;
    }

    if ($segments === ['administrador-cliente', 'procesos', 'verificacion-usuarios']) {
        (new ClientAdminController())->userVerifications();
        return;
    }

    if ($segments === ['componentes', 'revision']) {
        (new ComponentValidationController())->review();
        return;
    }

    if ($segments === ['reconocimiento-facial', 'desafio']) {
        (new FacialRecognitionController())->startChallenge();
        return;
    }

    if ($segments === ['reconocimiento-facial', 'validar-identidad']) {
        (new FacialRecognitionController())->validateIdentity();
        return;
    }

    if ($segments === ['reconocimiento-facial', 'ingreso-evaluacion']) {
        (new FacialRecognitionController())->assessmentEntry();
        return;
    }

    if ($segments === ['login']) {
        (new AuthController())->login();
        return;
    }

    if ($segments === ['password', 'forgot']) {
        (new AuthController())->forgotPassword();
        return;
    }

    if ($segments === ['password', 'reset']) {
        (new AuthController())->resetPassword();
        return;
    }

    if ($segments === ['logout']) {
        (new AuthController())->logout();
        return;
    }

    if ($segments === ['my-tests']) {
        (new TestController())->mine();
        return;
    }

    if ($segments === ['my-tests', 'realizadas']) {
        (new TestController())->mineCompleted();
        return;
    }

    if ($segments === ['my-tests', 'status']) {
        (new TestController())->mineStatus();
        return;
    }

    if ($segments === ['tests']) {
        (new TestController())->index();
        return;
    }

    if ($segments === ['tests', 'company-assignments']) {
        (new TestController())->companyAssignments();
        return;
    }

    if ($segments === ['tests', 'progress']) {
        (new TestController())->progress();
        return;
    }

    if ($segments === ['tests', 'progress', 'ranking']) {
        (new TestController())->progressRanking();
        return;
    }

    if ($segments === ['tests', 'ranking-presets']) {
        (new TestController())->rankingPresetAction();
        return;
    }

    if ($segments === ['tests', 'ranking-company-assignments']) {
        (new TestController())->rankingCompanyAssignments();
        return;
    }

    if ($segments === ['tests', 'progress', 'export']) {
        (new TestController())->progressExport();
        return;
    }

    if ($segments === ['settings', 'tests'] || $segments === ['tests', 'settings']) {
        (new TestController())->settings();
        return;
    }

    if ($segments === ['tests', 'assign']) {
        (new TestController())->assign();
        return;
    }

    if ($segments === ['reports', 'generate']) {
        (new ReportController())->generate();
        return;
    }

    if ($segments === ['reports', 'generate', 'new']) {
        (new ReportController())->form();
        return;
    }

    if ($segments === ['reports', 'company-assignments']) {
        (new ReportController())->companyAssignments();
        return;
    }

    if ($segments === ['reports', 'history']) {
        (new ReportController())->history();
        return;
    }

    if ($segments === ['reports', 'batches']) {
        (new ReportController())->batches();
        return;
    }

    if ($segments === ['reports', 'batches', 'status']) {
        (new ReportController())->batchStatus();
        return;
    }

    if ($segments === ['reports', 'batches', 'download']) {
        (new ReportController())->batchDownload();
        return;
    }

    if ($segments === ['reports', 'batches', 'status']) {
        (new ReportController())->batchStatus();
        return;
    }

    if ($segments === ['reports', 'batches', 'download']) {
        (new ReportController())->batchDownload();
        return;
    }

    if (($segments[0] ?? '') === 'reports' && ($segments[1] ?? '') === 'generate' && count($segments) >= 4 && ($segments[3] ?? '') === 'edit') {
        $_GET['sid'] = $segments[2];
        (new ReportController())->form();
        return;
    }

    if (($segments[0] ?? '') === 'reports' && ($segments[1] ?? '') === 'generate' && count($segments) >= 4 && ($segments[3] ?? '') === 'run') {
        $_GET['sid'] = $segments[2];
        (new ReportController())->run();
      return;
    }

    if (($segments[0] ?? '') === 'reports' && ($segments[1] ?? '') === 'generate' && count($segments) >= 4 && ($segments[3] ?? '') === 'approve') {
        $_GET['sid'] = $segments[2];
        (new ReportController())->approve();
        return;
    }

    if (($segments[0] ?? '') === 'reports' && ($segments[1] ?? '') === 'generate' && count($segments) >= 4 && ($segments[3] ?? '') === 'delete') {
        $_GET['sid'] = $segments[2];
        (new ReportController())->delete();
        return;
    }

    if (($segments[0] ?? '') === 'reports' && ($segments[1] ?? '') === 'generate' && count($segments) >= 4 && ($segments[3] ?? '') === 'batch') {
        $_GET['sid'] = $segments[2];
        (new ReportController())->batchRun();
        return;
    }

    if ($segments === ['tests', 'processes']) {
        (new TestProcessController())->index();
        return;
    }

    if ($segments === ['tests', 'processes', 'new']) {
        (new TestProcessController())->form();
        return;
    }

    if ($segments === ['tests', 'processes', 'dashboard']) {
        (new TestProcessController())->dashboard();
        return;
    }

    if ($segments === ['tests', 'processes', 'dashboard-data']) {
        (new TestProcessController())->dashboardData();
        return;
    }

    if ($segments === ['tests', 'processes', 'dashboard-warnings']) {
        (new TestProcessController())->dashboardWarnings();
        return;
    }

    if ($segments === ['tests', 'processes', 'ranking']) {
        (new TestProcessController())->rankingAll();
        return;
    }

    if ($segments === ['tests', 'processes', 'ranking', 'reports-zip']) {
        (new TestProcessController())->downloadRankingAllReportsZip();
        return;
    }

    if ($segments === ['tests', 'processes', 'review-assignment']) {
        (new TestProcessController())->reviewAssignment();
        return;
    }

    if (($segments[0] ?? '') === 'tests' && ($segments[1] ?? '') === 'processes' && count($segments) >= 3) {
        $_GET['sid'] = $segments[2];
        if (($segments[3] ?? '') === 'edit') {
            (new TestProcessController())->form();
            return;
        }

        if (($segments[3] ?? '') === 'delete') {
            (new TestProcessController())->delete();
            return;
        }

        if (($segments[3] ?? '') === 'ranking') {
            (new TestProcessController())->ranking();
            return;
        }

        if (($segments[3] ?? '') === 'ranking-report') {
            (new TestProcessController())->downloadRankingReport();
            return;
        }

        if (($segments[3] ?? '') === 'results-export') {
            (new TestProcessController())->exportResults();
            return;
        }

        if (($segments[3] ?? '') === 'results-import') {
            (new TestProcessController())->importResults();
            return;
        }

        if (($segments[3] ?? '') === 'available-users') {
            (new TestProcessController())->availableUsers();
            return;
        }

        if (($segments[3] ?? '') === 'assign-sessions') {
            (new TestProcessController())->assignSessions();
            return;
        }

        if (($segments[3] ?? '') === 'availability') {
            (new TestProcessController())->availability();
            return;
        }

        if (($segments[3] ?? '') === 'remove-user') {
            (new TestProcessController())->removeUser();
            return;
        }

        if (($segments[3] ?? '') === 'edit-user' && count($segments) >= 5) {
            $_GET['user_sid'] = $segments[4];
            (new TestProcessController())->editUser();
            return;
        }

        if (($segments[3] ?? '') === 'remove-all-users') {
            (new TestProcessController())->removeAllUsers();
            return;
        }

        if (($segments[3] ?? '') === 'cancel-session') {
            (new TestProcessController())->cancelSession();
            return;
        }

        if (($segments[3] ?? '') === 'cancel-evaluation-assignment') {
            (new TestProcessController())->cancelEvaluationAssignment();
            return;
        }

        if (($segments[3] ?? '') === 'reset-session') {
            (new TestProcessController())->resetSession();
            return;
        }

        if (($segments[3] ?? '') === 'reset-evaluation-assignment') {
            (new TestProcessController())->resetEvaluationAssignment();
            return;
        }

        if (($segments[3] ?? '') === 'reopen-expired-evaluation-assignment') {
            (new TestProcessController())->reopenExpiredEvaluationAssignment();
            return;
        }

        if (($segments[3] ?? '') === 'reopen-session') {
            (new TestProcessController())->reopenSession();
            return;
        }

        if (($segments[3] ?? '') === 'reopen-saved-session') {
            (new TestProcessController())->reopenSavedSession();
            return;
        }

        if (($segments[3] ?? '') === 'reopen-expired-instrument') {
            (new TestProcessController())->reopenExpiredInstrumentSessions();
            return;
        }

        (new TestProcessController())->show();
        return;
    }

    if ($segments === ['tests', 'assignments', 'cancel']) {
        (new TestController())->cancelAllSessions();
        return;
    }

    if ($segments === ['interviews']) {
        (new InterviewController())->index();
        return;
    }

    if ($segments === ['interviews', 'processes', 'new']) {
        (new InterviewController())->form();
        return;
    }

    if ($segments === ['interviews', 'settings']) {
        (new InterviewController())->settings();
        return;
    }

    if ($segments === ['interviews', 'settings', 'ai-test']) {
        (new InterviewController())->testAiConnection();
        return;
    }

    if ($segments === ['interviews', 'jobs', 'process']) {
        (new InterviewController())->processJob();
        return;
    }

    if (($segments[0] ?? '') === 'interviews' && ($segments[1] ?? '') === 'processes' && count($segments) >= 3) {
        $_GET['sid'] = $segments[2];
        if (($segments[3] ?? '') === 'edit') {
            (new InterviewController())->form();
            return;
        }

        (new InterviewController())->show();
        return;
    }

    if (($segments[0] ?? '') === 'interviews' && ($segments[1] ?? '') === 'appointments' && count($segments) >= 4) {
        $_GET['sid'] = $segments[2];
        if (($segments[3] ?? '') === 'room') {
            (new InterviewController())->room();
            return;
        }
        if (($segments[3] ?? '') === 'schedule') {
            (new InterviewController())->appointmentSchedule();
            return;
        }
        if (($segments[3] ?? '') === 'notes') {
            (new InterviewController())->saveNotes();
            return;
        }
        if (($segments[3] ?? '') === 'transcription') {
            (new InterviewController())->transcription();
            return;
        }
        if (($segments[3] ?? '') === 'finish') {
            (new InterviewController())->finish();
            return;
        }
        if (($segments[3] ?? '') === 'report') {
            (new InterviewController())->downloadReport();
            return;
        }
    }

    if (($segments[0] ?? '') === 'interviews' && ($segments[1] ?? '') === 'documents' && count($segments) === 3 && ($segments[2] ?? '') !== '') {
        $_GET['sid'] = $segments[2];
        (new InterviewController())->downloadDocument();
        return;
    }

    if ($segments === ['evaluaciones-encuestas', 'evaluaciones'] || $segments === ['evaluaciones-encuestas', 'encuestas']) {
        $_GET['type'] = $segments[1] === 'encuestas' ? 'survey' : 'assessment';
        (new EvaluationSurveyController())->index();
        return;
    }

    if ($segments === ['evaluaciones-encuestas', 'dashboard']) {
        (new EvaluationSurveyController())->dashboard();
        return;
    }

    if ($segments === ['evaluaciones-encuestas', 'dashboard', 'resumen.xlsx']) {
        (new EvaluationSurveyController())->dashboardSummaryExport();
        return;
    }

    if ($segments === ['evaluaciones-encuestas', 'dashboard', 'incidencias']) {
        (new EvaluationSurveyController())->dashboardIntegrityReport();
        return;
    }

    if ($segments === ['evaluaciones-encuestas', 'dashboard', 'incidencias', 'pdf']) {
        (new EvaluationSurveyController())->dashboardIntegrityReportPdf();
        return;
    }

    if ($segments === ['evaluaciones-encuestas', 'dashboard', 'incidencias', 'xlsx']) {
        (new EvaluationSurveyController())->dashboardIntegrityReportExcel();
        return;
    }

    if (($segments[0] ?? '') === 'evaluaciones-encuestas' && ($segments[1] ?? '') === 'dashboard' && ($segments[2] ?? '') === 'resultados' && count($segments) === 4) {
        $_GET['sid'] = $segments[3];
        (new EvaluationSurveyController())->dashboardResults();
        return;
    }

    if (($segments[0] ?? '') === 'evaluaciones-encuestas' && ($segments[1] ?? '') === 'dashboard' && ($segments[2] ?? '') === 'resultados' && count($segments) === 5 && ($segments[4] ?? '') === 'export') {
        $_GET['sid'] = $segments[3];
        (new EvaluationSurveyController())->dashboardResultsExport();
        return;
    }

    if (($segments[0] ?? '') === 'evaluaciones-encuestas' && ($segments[1] ?? '') === 'dashboard' && ($segments[2] ?? '') === 'resultados' && count($segments) === 5 && ($segments[4] ?? '') === 'reprocesar') {
        $_GET['sid'] = $segments[3];
        (new EvaluationSurveyController())->dashboardResultsReprocess();
        return;
    }

    if (($segments[0] ?? '') === 'evaluaciones-encuestas' && ($segments[1] ?? '') === 'dashboard' && ($segments[2] ?? '') === 'resultados' && count($segments) === 5 && ($segments[4] ?? '') === 'reprocesar-media') {
        $_GET['sid'] = $segments[3];
        (new EvaluationSurveyController())->dashboardResultsMediaRecovery();
        return;
    }

    if ($segments === ['evaluaciones-encuestas', 'formularios', 'new']) {
        (new EvaluationSurveyController())->form();
        return;
    }

    if ($segments === ['evaluaciones-encuestas', 'formularios', 'moodle-import']) {
        (new EvaluationSurveyController())->moodleImport();
        return;
    }

    if ($segments === ['evaluaciones-encuestas', 'ai-settings']) {
        (new EvaluationSurveyController())->aiSettings();
        return;
    }

    if ($segments === ['evaluaciones-encuestas', 'ai-generate']) {
        (new EvaluationSurveyController())->aiGenerate();
        return;
    }

    if (($segments[0] ?? '') === 'evaluaciones-encuestas' && ($segments[1] ?? '') === 'formularios' && count($segments) >= 4) {
        $_GET['sid'] = $segments[2];
        $action = $segments[3];
        if ($action === 'edit') { (new EvaluationSurveyController())->form(); return; }
        if ($action === 'preview') { (new EvaluationSurveyController())->preview(); return; }
        if ($action === 'take') { (new EvaluationSurveyController())->take(); return; }
        if ($action === 'delete') { (new EvaluationSurveyController())->deleteForm(); return; }
        if ($action === 'duplicate') { (new EvaluationSurveyController())->duplicateForm(); return; }
        if ($action === 'questions' && ($segments[4] ?? '') === 'new') { $_GET['form_sid'] = $segments[2]; (new EvaluationSurveyController())->questionForm(); return; }
        if ($action === 'questions' && ($segments[4] ?? '') === 'reorder') { (new EvaluationSurveyController())->reorderQuestions(); return; }
    }

    if (($segments[0] ?? '') === 'evaluaciones-encuestas' && ($segments[1] ?? '') === 'preguntas' && count($segments) >= 4) {
        $_GET['sid'] = $segments[2];
        if (($segments[3] ?? '') === 'edit') { (new EvaluationSurveyController())->questionForm(); return; }
        if (($segments[3] ?? '') === 'delete') { (new EvaluationSurveyController())->deleteQuestion(); return; }
    }

    if (($segments[0] ?? '') === 'evaluaciones-encuestas' && ($segments[1] ?? '') === 'preguntas-media' && count($segments) === 3) {
        $_GET['sid'] = $segments[2];
        (new EvaluationSurveyController())->questionMedia();
        return;
    }

    if (($segments[0] ?? '') === 'evaluaciones-encuestas' && ($segments[1] ?? '') === 'intentos' && count($segments) === 4 && ($segments[3] ?? '') === 'result') {
        $_GET['sid'] = $segments[2];
        (new EvaluationSurveyController())->result();
        return;
    }

    if (($segments[0] ?? '') === 'evaluaciones-encuestas' && ($segments[1] ?? '') === 'intentos' && count($segments) === 4 && ($segments[3] ?? '') === 'activity') {
        $_GET['sid'] = $segments[2];
        (new EvaluationSurveyController())->activity();
        return;
    }

    if (($segments[0] ?? '') === 'evaluaciones-encuestas' && ($segments[1] ?? '') === 'intentos' && count($segments) === 5 && ($segments[3] ?? '') === 'media') {
        $_GET['sid'] = $segments[2];
        $mediaAction = $segments[4] ?? '';
        if ($mediaAction === 'init') { (new EvaluationSurveyController())->mediaInit(); return; }
        if ($mediaAction === 'status') { (new EvaluationSurveyController())->mediaStatus(); return; }
        if ($mediaAction === 'chunk') { (new EvaluationSurveyController())->mediaChunk(); return; }
        if ($mediaAction === 'finalize') { (new EvaluationSurveyController())->mediaFinalize(); return; }
        if ($mediaAction === 'risk') { (new EvaluationSurveyController())->mediaRisk(); return; }
        if ($mediaAction === 'failure') { (new EvaluationSurveyController())->mediaFailure(); return; }
        if ($mediaAction === 'screenshot') { (new EvaluationSurveyController())->mediaScreenshot(); return; }
        if ($mediaAction === 'screenshot-file') { (new EvaluationSurveyController())->mediaScreenshotFile(); return; }
        if ($mediaAction === 'evidence') { (new EvaluationSurveyController())->mediaEvidence(); return; }
        if ($mediaAction === 'partial') { (new EvaluationSurveyController())->mediaPartial(); return; }
    }

    if ($segments === ['tests', 'results', 'clear']) {
        (new TestController())->clearAllResults();
        return;
    }

    if (($segments[0] ?? '') === 'tests' && ($segments[1] ?? '') === 'results' && ($segments[2] ?? '') === 'user' && count($segments) === 4) {
        $_GET['sid'] = $segments[3];
        (new TestController())->userResults();
        return;
    }

    if ($segments === ['tests', 'new']) {
        (new TestController())->form();
        return;
    }

    if (($segments[0] ?? '') === 'tests' && ($segments[1] ?? '') === 'session' && count($segments) >= 3) {
        $_GET['sid'] = $segments[2];
        if (($segments[3] ?? '') === 'cancel') {
            (new TestController())->cancelSession();
            return;
        }
        if (($segments[3] ?? '') === 'activity') {
            (new TestController())->activity();
            return;
        }
        if (($segments[3] ?? '') === 'media' && ($segments[4] ?? '') === 'init') {
            (new TestController())->mediaInit();
            return;
        }
        if (($segments[3] ?? '') === 'media' && ($segments[4] ?? '') === 'status') {
            (new TestController())->mediaStatus();
            return;
        }
        if (($segments[3] ?? '') === 'media' && ($segments[4] ?? '') === 'chunk') {
            (new TestController())->mediaChunk();
            return;
        }
        if (($segments[3] ?? '') === 'media' && ($segments[4] ?? '') === 'finalize') {
            (new TestController())->mediaFinalize();
            return;
        }
        if (($segments[3] ?? '') === 'media' && ($segments[4] ?? '') === 'risk') {
            (new TestController())->mediaRisk();
            return;
        }
        if (($segments[3] ?? '') === 'media' && ($segments[4] ?? '') === 'failure') {
            (new TestController())->mediaFailure();
            return;
        }
        if (($segments[3] ?? '') === 'media' && ($segments[4] ?? '') === 'screenshot') {
            (new TestController())->mediaScreenshot();
            return;
        }
        if (($segments[3] ?? '') === 'media' && ($segments[4] ?? '') === 'screenshot-file') {
            (new TestController())->mediaScreenshotFile();
            return;
        }
        if (($segments[3] ?? '') === 'media' && ($segments[4] ?? '') === 'evidence') {
            (new TestController())->mediaEvidence();
            return;
        }
        if (($segments[3] ?? '') === 'media' && ($segments[4] ?? '') === 'partial') {
            (new TestController())->mediaPartial();
            return;
        }
        if (($segments[3] ?? '') === 'draft') {
            (new TestController())->draft();
            return;
        }
        if (($segments[3] ?? '') === 'availability') {
            (new TestController())->availability();
            return;
        }
        if (($segments[3] ?? '') === 'result-export') {
            (new TestController())->resultExport();
            return;
        }
        if (($segments[3] ?? '') === 'result') {
            (new TestController())->result();
            return;
        }
        (new TestController())->take();
        return;
    }

    if (($segments[0] ?? '') === 'tests' && count($segments) === 3 && $segments[2] === 'edit') {
        $_GET['sid'] = $segments[1];
        (new TestController())->form();
        return;
    }

    if (($segments[0] ?? '') === 'tests' && count($segments) === 3 && $segments[2] === 'content') {
        $_GET['sid'] = $segments[1];
        (new TestController())->content();
        return;
    }

    if (($segments[0] ?? '') === 'tests' && count($segments) === 3 && $segments[2] === 'result-export') {
        $_GET['sid'] = $segments[1];
        (new TestController())->instrumentResultExport();
        return;
    }

    if (($segments[0] ?? '') === 'tests' && count($segments) === 3 && $segments[2] === 'answers-export') {
        $_GET['sid'] = $segments[1];
        (new TestController())->instrumentAnswersExport();
        return;
    }

    if (($segments[0] ?? '') === 'tests' && count($segments) >= 4 && $segments[2] === 'content') {
        $_GET['sid'] = $segments[1];
        $action = $segments[3] ?? '';
        if ($action === 'help') {
            (new TestController())->contentHelp();
            return;
        }
        if ($action === 'scale') {
            (new TestController())->contentScaleForm();
            return;
        }
        if ($action === 'scale-delete') {
            (new TestController())->contentScaleDelete();
            return;
        }
        if ($action === 'item') {
            (new TestController())->contentItemForm();
            return;
        }
        if ($action === 'item-delete') {
            (new TestController())->contentItemDelete();
            return;
        }
    }

    if ($segments === ['users']) {
        (new UserController())->index();
        return;
    }

    if ($segments === ['users', 'data']) {
        (new UserController())->data();
        return;
    }

    if ($segments === ['users', 'import', 'preview-data']) {
        (new UserController())->importPreviewData();
        return;
    }

    if ($segments === ['users', 'import', 'row']) {
        (new UserController())->importRowForm();
        return;
    }

    if ($segments === ['users', 'import', 'row-save']) {
        (new UserController())->importRowSave();
        return;
    }

    if ($segments === ['users', 'new']) {
        (new UserController())->form();
        return;
    }

    if ($segments === ['users', 'import']) {
        (new UserController())->import();
        return;
    }

    if ($segments === ['users', 'import', 'template']) {
        (new UserController())->importTemplate();
        return;
    }

    if (($segments[0] ?? '') === 'users' && count($segments) === 3 && $segments[2] === 'edit') {
        $_GET['sid'] = $segments[1];
        (new UserController())->form();
        return;
    }

    if ($segments === ['companies']) {
        (new CompanyController())->index();
        return;
    }

    if ($segments === ['companies', 'new']) {
        (new CompanyController())->form();
        return;
    }

    if (($segments[0] ?? '') === 'companies' && count($segments) === 3 && $segments[2] === 'edit') {
        $_GET['sid'] = $segments[1];
        (new CompanyController())->form();
        return;
    }

    if (($segments[0] ?? '') === 'companies' && count($segments) === 3 && $segments[2] === 'delete') {
        $_GET['sid'] = $segments[1];
        (new CompanyController())->delete();
        return;
    }

    if (($segments[0] ?? '') === 'companies' && count($segments) === 3 && $segments[2] === 'cleanup-users') {
        $_GET['sid'] = $segments[1];
        (new CompanyController())->cleanupUsers();
        return;
    }

    if ($segments === ['profiles']) {
        (new ProfileController())->index();
        return;
    }

    if ($segments === ['profiles', 'new']) {
        (new ProfileController())->form();
        return;
    }

    if (($segments[0] ?? '') === 'profiles' && count($segments) === 3 && $segments[2] === 'edit') {
        $_GET['sid'] = $segments[1];
        (new ProfileController())->form();
        return;
    }

    if ($segments === ['user-fields']) {
        (new UserFieldController())->index();
        return;
    }

    if ($segments === ['user-fields', 'new']) {
        (new UserFieldController())->form();
        return;
    }

    if (($segments[0] ?? '') === 'user-fields' && count($segments) === 3 && $segments[2] === 'edit') {
        $_GET['sid'] = $segments[1];
        (new UserFieldController())->form();
        return;
    }

    if (($segments[0] ?? '') === 'user-fields' && count($segments) === 3 && $segments[2] === 'delete') {
        $_GET['sid'] = $segments[1];
        (new UserFieldController())->delete();
        return;
    }

    if ($segments === ['settings']) {
        (new PlatformSettingsController())->index();
        return;
    }
} catch (Throwable $exception) {
    security_log(sprintf(
        'Unhandled exception %s at %s:%d :: %s',
        get_class($exception),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getMessage()
    ));
    platform_error(500, 'Ocurrio un error interno.', [
        'log' => true,
        'detailRows' => [
            'Metodo' => $method,
        ],
    ]);
}

platform_error(404, 'Pagina no encontrada.');
