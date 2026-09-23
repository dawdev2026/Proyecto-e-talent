<?php
declare(strict_types=1);

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
defined('CONFIG_PATH') || define('CONFIG_PATH', BASE_PATH . '/config');
defined('SISTEMA_PATH') || define('SISTEMA_PATH', BASE_PATH . '/sistema');
defined('UTILS_PATH') || define('UTILS_PATH', BASE_PATH . '/utils');
defined('PLATAFORMAS_PATH') || define('PLATAFORMAS_PATH', BASE_PATH . '/plataformas');
defined('PUBLIC_PATH') || define('PUBLIC_PATH', BASE_PATH . '/public');
defined('TMP_PATH') || define('TMP_PATH', BASE_PATH . '/tmp');

if (is_file(SISTEMA_PATH . '/vendor/autoload.php')) {
    require_once SISTEMA_PATH . '/vendor/autoload.php';
}

require_once SISTEMA_PATH . '/core/security.php';

$appConfig = load_config('app');
enforce_https_policy($appConfig);
configure_secure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
send_security_headers();

date_default_timezone_set($appConfig['timezone'] ?? 'America/Santiago');

require_once SISTEMA_PATH . '/mvc/models/Database.php';
require_once PLATAFORMAS_PATH . '/core/models/UserModel.php';
require_once PLATAFORMAS_PATH . '/core/models/CompanyModel.php';
require_once PLATAFORMAS_PATH . '/core/models/CompanyBrandingModel.php';
require_once PLATAFORMAS_PATH . '/core/models/ProfileModel.php';
require_once PLATAFORMAS_PATH . '/core/models/UserFieldModel.php';
require_once PLATAFORMAS_PATH . '/core/models/PlatformSettingsModel.php';
require_once PLATAFORMAS_PATH . '/core/models/PasswordResetModel.php';
require_once PLATAFORMAS_PATH . '/core/models/LoginVerificationModel.php';
require_once PLATAFORMAS_PATH . '/core/models/CompanyMailSettingsModel.php';
require_once PLATAFORMAS_PATH . '/core/models/UserVerificationModel.php';
require_once PLATAFORMAS_PATH . '/core/models/FacialRecognitionModel.php';
require_once PLATAFORMAS_PATH . '/core/models/ComponentValidationModel.php';
require_once PLATAFORMAS_PATH . '/core/models/ClientAdminInsightsModel.php';
require_once PLATAFORMAS_PATH . '/core/services/VisualPresetAnalyzer.php';
require_once PLATAFORMAS_PATH . '/core/services/FacialRecognitionService.php';
require_once PLATAFORMAS_PATH . '/core/services/ProcessPrerequisiteService.php';
require_once PLATAFORMAS_PATH . '/core/services/MailService.php';
require_once PLATAFORMAS_PATH . '/core/services/CompanyDeletionService.php';
require_once PLATAFORMAS_PATH . '/core/services/TestUserCleanupService.php';
require_once PLATAFORMAS_PATH . '/tests/models/TestInstrumentModel.php';
require_once PLATAFORMAS_PATH . '/tests/models/CompanyTestAssignmentModel.php';
require_once PLATAFORMAS_PATH . '/tests/models/TestSessionModel.php';
require_once PLATAFORMAS_PATH . '/tests/models/TestMediaEvidenceModel.php';
require_once PLATAFORMAS_PATH . '/tests/models/TestSettingsModel.php';
require_once PLATAFORMAS_PATH . '/tests/models/TestProcessModel.php';
require_once PLATAFORMAS_PATH . '/tests/services/RiasecRecommendationService.php';
require_once PLATAFORMAS_PATH . '/tests/services/ProgressRankingSummaryService.php';
require_once PLATAFORMAS_PATH . '/tests/services/PostulantReportScaleMapService.php';
require_once PLATAFORMAS_PATH . '/tests/services/PostulantRankingReportPdfService.php';
require_once PLATAFORMAS_PATH . '/tests/services/PostulantReportDataService.php';
require_once PLATAFORMAS_PATH . '/tests/services/TestMediaProcessingService.php';
require_once BASE_PATH . '/plug-and-play/daily-video-kit/backend/DailySettings.php';
require_once BASE_PATH . '/plug-and-play/daily-video-kit/backend/DailyMeetingService.php';
require_once PLATAFORMAS_PATH . '/interviews/models/InterviewProcessModel.php';
require_once PLATAFORMAS_PATH . '/interviews/services/InterviewSettings.php';
require_once PLATAFORMAS_PATH . '/interviews/services/InterviewAiService.php';
require_once PLATAFORMAS_PATH . '/interviews/services/InterviewDailyPoolService.php';
require_once PLATAFORMAS_PATH . '/interviews/services/InterviewFinalReportPdfService.php';
require_once PLATAFORMAS_PATH . '/interviews/services/InterviewDocumentTextExtractor.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/models/EvaluationSurveyFormModel.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/models/EvaluationSurveyDashboardModel.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/models/EvaluationSurveyAttemptModel.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/models/EvaluationSurveySettingsModel.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/models/EvaluationSurveyControlModel.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/models/EvaluationSurveyMediaEvidenceModel.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/services/EvaluationSurveyMediaProcessingService.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/services/EvaluationSurveyIntegrityReportPdfService.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/services/EvaluationSurveyAiService.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/services/MoodleEvaluationImportService.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/services/MoodleXmlEvaluationImportService.php';
require_once SISTEMA_PATH . '/mvc/views/Template.php';
require_once SISTEMA_PATH . '/mvc/controllers/Controller.php';
require_once PLATAFORMAS_PATH . '/core/controllers/AuthController.php';
require_once PLATAFORMAS_PATH . '/core/controllers/UserController.php';
require_once PLATAFORMAS_PATH . '/core/controllers/CompanyController.php';
require_once PLATAFORMAS_PATH . '/core/controllers/UserVerificationController.php';
require_once PLATAFORMAS_PATH . '/core/controllers/FacialRecognitionController.php';
require_once PLATAFORMAS_PATH . '/core/controllers/ComponentValidationController.php';
require_once PLATAFORMAS_PATH . '/core/controllers/ClientAdminController.php';
require_once PLATAFORMAS_PATH . '/core/controllers/ProfileController.php';
require_once PLATAFORMAS_PATH . '/core/controllers/UserFieldController.php';
require_once PLATAFORMAS_PATH . '/core/controllers/PlatformSettingsController.php';
require_once PLATAFORMAS_PATH . '/core/controllers/DashboardController.php';
require_once PLATAFORMAS_PATH . '/tests/controllers/TestController.php';
require_once PLATAFORMAS_PATH . '/tests/controllers/TestProcessController.php';
require_once PLATAFORMAS_PATH . '/reports/services/ReportTemplateCatalog.php';
require_once PLATAFORMAS_PATH . '/reports/services/ReportMarkdownInterpreter.php';
require_once PLATAFORMAS_PATH . '/reports/services/ReportDesignInterpreter.php';
require_once PLATAFORMAS_PATH . '/reports/services/ReportDocumentRenderer.php';
require_once PLATAFORMAS_PATH . '/reports/models/ReportAuditModel.php';
require_once PLATAFORMAS_PATH . '/reports/services/ReportBatchService.php';
require_once PLATAFORMAS_PATH . '/reports/controllers/ReportController.php';
require_once PLATAFORMAS_PATH . '/reports/models/ReportDefinitionModel.php';
require_once PLATAFORMAS_PATH . '/interviews/controllers/InterviewController.php';
require_once PLATAFORMAS_PATH . '/evaluaciones_encuestas/controllers/EvaluationSurveyController.php';
require_once SISTEMA_PATH . '/core/database.php';
require_once UTILS_PATH . '/helpers.php';
require_once UTILS_PATH . '/excel.php';
require_once UTILS_PATH . '/uploads.php';
require_once SISTEMA_PATH . '/core/auth.php';

remember_navigation();
