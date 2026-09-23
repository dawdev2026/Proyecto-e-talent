<?php
declare(strict_types=1);

final class ClientAdminController extends Controller
{
    private ClientAdminInsightsModel $insights;
    private EvaluationSurveyDashboardModel $evaluationDashboard;

    public function __construct(?Template $view = null, ?ClientAdminInsightsModel $insights = null)
    {
        parent::__construct($view);
        $this->insights = $insights ?: new ClientAdminInsightsModel();
        $this->evaluationDashboard = new EvaluationSurveyDashboardModel();
    }

    public function dashboard(): void
    {
        $companyId = $this->requireClientAdmin();
        header('Cache-Control: private, no-store');
        $this->render('client_admin/dashboard', [
            'title' => 'Inicio Administrador Cliente | e-talent',
            'currentPage' => 'client-admin.dashboard',
            'summary' => $this->insights->homeSummary($companyId),
            'companyName' => (string) ((current_user() ?: [])['company_name'] ?? 'Empresa'),
        ]);
    }

    public function evaluationProgress(): void
    {
        $companyId = $this->requireClientAdmin();
        $this->render('client_admin/progress', [
            'title' => 'Avance de evaluaciones | e-talent', 'currentPage' => 'client-admin.evaluation-progress',
            'heading' => 'Dashboard de avance Evaluaciones', 'kind' => 'Evaluaciones',
            'rows' => $this->insights->evaluationProgress($companyId),
        ]);
    }

    public function testProgress(): void
    {
        $companyId = $this->requireClientAdmin();
        $this->render('client_admin/progress', [
            'title' => 'Avance de tests | e-talent', 'currentPage' => 'client-admin.test-progress',
            'heading' => 'Dashboard de avance Test', 'kind' => 'Tests psicolaborales',
            'rows' => $this->insights->testProgress($companyId),
        ]);
    }

    public function testIncidents(): void
    {
        $companyId = $this->requireClientAdmin();
        $processIds = $this->requestPositiveIntList('process_id');
        $testIds = $this->requestPositiveIntList('test_id');
        $dateRange = $this->incidentDateRange();
        $data = $this->evaluationDashboard->integrityReport($companyId, $processIds ?: null, null, $testIds ?: null, 'tests', $dateRange['from'], $dateRange['until']);
        $this->render('evaluaciones_encuestas/dashboard_integrity', [
            'title' => 'Incidencias de tests psicolaborales | e-talent',
            'currentPage' => 'client-admin.test-incidents',
            'reportDomain' => 'tests',
            'dateFrom' => $dateRange['from'], 'dateTo' => $dateRange['through'],
            'summary' => $data['summary'], 'rows' => $data['rows'],
            'processIds' => $processIds, 'formIds' => [], 'testIds' => $testIds,
            'filterOptions' => $this->evaluationDashboard->integrityFilterOptions($companyId, 'tests'),
            'isCompanyScope' => true, 'useSelect2' => true,
        ]);
    }

    public function userLookup(): void
    {
        $companyId = $this->requireClientAdmin();
        $rut = UserModel::formatRut(trim((string) ($_GET['rut'] ?? '')));
        $user = null;
        $assignments = [];
        $selectedAnswers = [];
        $selectedActivityName = '';
        $selectedActivityId = max(0, (int) ($_GET['activity_id'] ?? 0));
        $selectedActivityType = (string) ($_GET['activity_type'] ?? '');
        $error = '';
        if ($rut !== '') {
            if (!UserModel::isValidRut($rut)) {
                $error = 'Ingresa un RUT válido.';
            } else {
                $user = $this->insights->userByRut($rut, $companyId);
                if ($user) {
                    $assignments = $this->insights->userAssignments((int) $user['id'], $companyId);
                    if ($selectedActivityId > 0 && in_array($selectedActivityType, ['test', 'evaluation'], true)) {
                        foreach ($assignments as $assignment) {
                            if ((int) ($assignment['record_id'] ?? 0) === $selectedActivityId
                                && (string) ($assignment['activity_type'] ?? '') === $selectedActivityType) {
                                $selectedActivityName = (string) ($assignment['activity_name'] ?? 'Actividad');
                                $selectedAnswers = $this->insights->answersForActivity((int) $user['id'], $companyId, $selectedActivityId, $selectedActivityType);
                                break;
                            }
                        }
                    }
                }
            }
        }
        header('Cache-Control: private, no-store');
        $this->render('client_admin/user_lookup', [
            'title' => 'Consultar usuario | e-talent', 'currentPage' => 'client-admin.user-lookup',
            'rut' => $rut, 'user' => $user, 'assignments' => $assignments, 'error' => $error,
            'selectedActivityId' => $selectedActivityId, 'selectedActivityType' => $selectedActivityType,
            'selectedActivityName' => $selectedActivityName, 'selectedAnswers' => $selectedAnswers,
        ]);
    }

    public function componentReviews(): void
    {
        $companyId = $this->requireClientAdmin();
        $validationModel = new ComponentValidationModel();
        $pageSize = 50;
        $totalRows = $validationModel->countForCompany($companyId);
        $totalPages = max(1, (int) ceil($totalRows / $pageSize));
        $pageValue = $_GET['page'] ?? 1;
        $pageValue = is_scalar($pageValue) ? filter_var($pageValue, FILTER_VALIDATE_INT) : false;
        $page = max(1, min($totalPages, $pageValue === false ? 1 : (int) $pageValue));
        $rows = $validationModel->recentForCompany($companyId, $pageSize, ($page - 1) * $pageSize);
        $attemptCounts = $validationModel->attemptCountsForUsers(
            $companyId,
            array_column($rows, 'user_id')
        );
        foreach ($rows as &$row) {
            $row['attempt_count'] = $attemptCounts[(int) $row['user_id']] ?? 0;
        }
        unset($row);

        header('Cache-Control: private, no-store');
        $this->render('client_admin/component_reviews', [
            'title' => 'Historial de Validaciones | e-talent',
            'currentPage' => 'client-admin.component-reviews',
            'companyName' => (string) ((current_user() ?: [])['company_name'] ?? 'Empresa'),
            'rows' => $rows,
            'totalRows' => $totalRows,
            'page' => $page,
            'totalPages' => $totalPages,
            'pageSize' => $pageSize,
        ]);
    }

    public function userVerifications(): void
    {
        $companyId = $this->requireClientAdmin();
        $verificationModel = new UserVerificationModel();
        $pageSize = 50;
        $totalRows = $verificationModel->countChecksForCompany($companyId);
        $totalPages = max(1, (int) ceil($totalRows / $pageSize));
        $pageValue = $_GET['page'] ?? 1;
        $pageValue = is_scalar($pageValue) ? filter_var($pageValue, FILTER_VALIDATE_INT) : false;
        $page = max(1, min($totalPages, $pageValue === false ? 1 : (int) $pageValue));

        $this->render('client_admin/user_verifications', [
            'title' => 'Verificación de usuarios | e-talent',
            'currentPage' => 'client-admin.user-verifications',
            'companyName' => (string) ((current_user() ?: [])['company_name'] ?? 'Empresa'),
            'rows' => $verificationModel->recentChecksForCompany($companyId, $pageSize, ($page - 1) * $pageSize),
            'totalRows' => $totalRows,
            'page' => $page,
            'totalPages' => $totalPages,
            'pageSize' => $pageSize,
        ]);
    }

    private function requestPositiveIntList(string $key): array
    {
        $values = $_GET[$key] ?? [];
        $values = is_array($values) ? $values : [$values];
        $valid = [];
        foreach ($values as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) $valid[(int) $id] = (int) $id;
        }
        return array_values($valid);
    }

    private function incidentDateRange(): array
    {
        $validDate = static function (string $value): bool {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
        };
        $from = trim((string) ($_GET['date_from'] ?? ''));
        $through = trim((string) ($_GET['date_to'] ?? ''));
        if (!$validDate($from)) $from = date('Y-m-d', strtotime('-90 days'));
        if (!$validDate($through)) $through = date('Y-m-d');
        if ($through < $from) $from = date('Y-m-d', strtotime($through . ' -90 days'));
        return ['from' => $from, 'through' => $through, 'until' => (new DateTimeImmutable($through))->modify('+1 day')->format('Y-m-d')];
    }

    private function requireClientAdmin(): int
    {
        require_auth();
        require_permission('view_company_client_portal');
        $user = current_user() ?: [];
        if ((string) ($user['role'] ?? '') !== 'company_admin' || (int) ($user['company_id'] ?? 0) <= 0) {
            platform_error(403, 'Este acceso requiere un Administrador Cliente asociado a una empresa.');
        }
        header('Cache-Control: private, no-store');
        return (int) $user['company_id'];
    }
}
