<?php
declare(strict_types=1);

final class TestProcessController extends Controller
{
    private TestProcessModel $processes;
    private TestSessionModel $sessions;
    private TestSettingsModel $settings;
    private ProgressRankingSummaryService $progressRankingSummary;
    private PostulantRankingReportPdfService $rankingReportPdf;

    public function __construct(?Template $view = null, ?TestProcessModel $processes = null, ?TestSessionModel $sessions = null, ?TestSettingsModel $settings = null, ?ProgressRankingSummaryService $progressRankingSummary = null, ?PostulantRankingReportPdfService $rankingReportPdf = null)
    {
        parent::__construct($view);
        $this->processes = $processes ?: new TestProcessModel();
        $this->sessions = $sessions ?: new TestSessionModel();
        $this->settings = $settings ?: new TestSettingsModel();
        $this->progressRankingSummary = $progressRankingSummary ?: new ProgressRankingSummaryService();
        $this->rankingReportPdf = $rankingReportPdf ?: new PostulantRankingReportPdfService();
    }

    public function index(): void
    {
        require_auth();
        $this->requireAnyProcessAccess();

        $processes = $this->processes->allForUser(current_user() ?: []);
        $dateGroups = $this->processDateGroups($processes);
        $selectedDateGroup = trim((string) ($_GET['date_group'] ?? ''));
        if ($selectedDateGroup !== '' && !isset($dateGroups[$selectedDateGroup])) {
            $selectedDateGroup = '';
        }
        $visibleProcesses = $selectedDateGroup !== ''
            ? array_values(array_filter($processes, fn(array $process): bool => $this->processDateGroupKey($process) === $selectedDateGroup))
            : $processes;
        $hasDashboardPermission = has_permission('view_test_process_dashboard') && !$this->isCompanyAdminOrSupervisor();
        $canViewDashboard = false;
        foreach ($processes as $process) {
            $processId = (int) ($process['id'] ?? 0);
            if ($processId > 0 && $hasDashboardPermission && $this->processes->can(current_user() ?: [], $processId, 'view_process_dashboard')) {
                $canViewDashboard = true;
                break;
            }
        }
        foreach ($visibleProcesses as &$process) {
            $processId = (int) ($process['id'] ?? 0);
            $process['can_view_process'] = $this->processes->can(current_user() ?: [], (int) ($process['id'] ?? 0), 'view_process');
            $process['can_view_dashboard'] = $hasDashboardPermission && $this->processes->can(current_user() ?: [], (int) ($process['id'] ?? 0), 'view_process_dashboard');
            $process['can_view_results'] = $this->processes->can(current_user() ?: [], (int) ($process['id'] ?? 0), 'view_process_results');
            $process['can_view_ranking'] = $this->processes->can(current_user() ?: [], (int) ($process['id'] ?? 0), 'view_process_ranking');
            $process['can_manage_settings'] = $this->processes->can(current_user() ?: [], (int) ($process['id'] ?? 0), 'manage_process_settings');
            $process['has_results'] = $processId > 0 ? $this->processes->processHasResults($processId) : false;
        }
        unset($process);

        $this->render('tests/processes/index', [
            'title' => 'Procesos | e-talent',
            'currentPage' => 'test-processes',
            'processes' => $visibleProcesses,
            'statuses' => TestProcessModel::STATUSES,
            'canViewDashboard' => $canViewDashboard,
            'dateGroups' => $dateGroups,
            'selectedDateGroup' => $selectedDateGroup,
            'totalProcesses' => count($processes),
        ]);
    }

    public function dashboard(): void
    {
        require_auth();
        require_permission('view_test_process_dashboard');
        $this->requireAnyProcessDashboardAccess();
        session_write_close();

        $dashboardFilters = $this->dashboardFilterOptions();

        $this->render('tests/processes/dashboard', [
            'title' => 'Dashboard Avance | e-talent',
            'currentPage' => 'test-process.dashboard',
            'dashboardAsyncShell' => true,
            'dashboardDataUrl' => route_url('test-process.dashboard') . '-data',
            'dashboardIsGlobalAdmin' => $dashboardFilters['is_global_admin'],
            'dashboardCompanies' => $dashboardFilters['companies'],
            'dashboardAvailableProcesses' => $dashboardFilters['processes'],
            'dashboardCompanyId' => $dashboardFilters['company_id'],
            'dashboardSelectedProcessIds' => $dashboardFilters['process_ids'],
        ]);
    }

    public function dashboardData(): void
    {
        require_auth();
        require_permission('view_test_process_dashboard');
        $this->requireAnyProcessDashboardAccess();
        session_write_close();

        try {
            $data = $this->dashboardViewData();
            $html = $this->view->render('tests/processes/dashboard', array_merge($data, [
                'dashboardContentOnly' => true,
                'dashboardSkipScripts' => true,
            ]), null);

            $this->jsonResponse([
                'ok' => true,
                'html' => $html,
                'chartData' => $data['chartData'] ?? [],
            ]);
        } catch (Throwable $exception) {
            error_log('Process dashboard data error: ' . $exception->getMessage());
            $this->jsonResponse([
                'ok' => false,
                'message' => 'No se pudo cargar la informacion del dashboard.',
            ], 500);
        }
    }

    public function dashboardWarnings(): void
    {
        require_auth();
        require_permission('view_test_process_dashboard');
        $this->requireAnyProcessDashboardAccess();
        session_write_close();

        $data = $this->dashboardViewData();
        $this->render('tests/processes/dashboard_warnings', array_merge($data, [
            'title' => 'Advertencias de ranking | e-talent',
            'currentPage' => 'test-process.dashboard',
            'warningRows' => $data['rankingWarningRows'] ?? [],
            'dashboardQueryString' => (string) ($_SERVER['QUERY_STRING'] ?? ''),
        ]));
    }

    private function dashboardViewData(): array
    {
        $canConfigureRanking = has_permission('manage_ranking_presets');
        $dashboardFilters = $this->dashboardFilterOptions();
        $processFilter = $canConfigureRanking ? max(0, (int) ($_GET['process_id'] ?? 0)) : 0;
        $dashboardFields = array_values(array_filter(
            $this->processes->userFields(),
            static fn(array $field): bool => (string) ($field['field_key'] ?? '') !== 'edad'
        ));
        $fieldFilters = $canConfigureRanking && is_array($_GET['fields'] ?? null) ? $_GET['fields'] : [];
        $processes = $dashboardFilters['processes'];
        $dashboardProcesses = [];
        $visibleProcesses = [];
        foreach ($processes as $process) {
            $processId = (int) ($process['id'] ?? 0);
            if (!$this->processes->can(current_user() ?: [], $processId, 'view_process_dashboard')) {
                continue;
            }
            $dashboardProcesses[] = $process;
            if ($dashboardFilters['is_global_admin'] && !in_array($processId, $dashboardFilters['process_ids'], true)) {
                continue;
            }
            if ($processFilter > 0 && $processId !== $processFilter) {
                continue;
            }
            $visibleProcesses[] = $process;
        }

        $activeInstruments = $this->processes->activeInstruments();
        $officialRankingConfig = $this->progressRankingSummary->officialConfig();
        $storedRankingConfig = $this->progressRankingSummary->activeConfig($this->settings->rankingConfig());
        $rankingPresets = $canConfigureRanking ? $this->settings->rankingPresetOptions($officialRankingConfig) : [];
        $selectedRankingPreset = $canConfigureRanking ? $this->rankingPresetFromRequest($officialRankingConfig) : null;
        $rankingConfig = $canConfigureRanking
            ? $this->dashboardRankingConfig($storedRankingConfig)
            : $this->progressRankingSummary->activeConfig($this->settings->rankingConfigForCompany(
                (int) (current_user()['company_id'] ?? 0),
                $officialRankingConfig
            ));
        if ($canConfigureRanking && $selectedRankingPreset) {
            $rankingConfig = $this->progressRankingSummary->activeConfig($selectedRankingPreset['config']);
        }
        $rankingScope = $canConfigureRanking ? $this->dashboardRankingScope() : 'process';
        $overall = [
            'users_total' => 0,
            'evaluated' => 0,
            'in_progress' => 0,
            'pending' => 0,
            'sessions_total' => 0,
            'sessions_finished' => 0,
            'activity_events_total' => 0,
            'activity_attention_total' => 0,
            'activity_risk_total' => 0,
            'ranking_ranked' => 0,
            'ranking_warnings' => 0,
            'ranking_recommended' => 0,
            'ranking_observation' => 0,
            'ranking_not_recommended' => 0,
            'ranking_knockouts' => 0,
        ];
        $processRows = [];
        $trend = $this->emptyTrendDays(8);
        $dashboardFieldOptions = [];
        $uniqueUserProgress = [];
        $activeProcessesByUser = [];
        $rankingWarningRows = [];
        $rankingWarningInstrumentColumns = [];

        foreach ($visibleProcesses as $process) {
            $processId = (int) ($process['id'] ?? 0);
            $users = $this->processes->processUsers($processId);
            $dashboardFieldOptions = $this->mergeProcessDashboardFieldOptions($dashboardFieldOptions, $users, $dashboardFields);
            $rankingUsers = $rankingScope === 'filtered'
                ? $this->filterProcessUsersByFields($users, $fieldFilters)
                : $users;
            $users = $this->filterProcessUsersByFields($users, $fieldFilters);
            $this->collectActiveProcessAssignments($activeProcessesByUser, $users, $process);
            $sessions = $this->processes->processDashboardSessions($processId);
            $selectedInstrumentIds = $this->processes->selectedInstrumentIds($processId);
            $stats = $this->processProgressStats($users, $sessions, $activeInstruments, $selectedInstrumentIds);
            $rankingSummaryCache = [];
            $rankingInstrumentStatus = $this->rankingInstrumentStatusByRut($rankingUsers, $sessions, $activeInstruments, $selectedInstrumentIds);
            foreach ($rankingInstrumentStatus['columns'] as $columnKey => $column) {
                $rankingWarningInstrumentColumns[$columnKey] = $column;
            }
            $ranking = $this->processRankingForDashboard($rankingUsers, $sessions, $activeInstruments, $selectedInstrumentIds, $rankingConfig, $rankingSummaryCache);
            $rankingSummary = $this->progressRankingSummary->dashboardSummary($ranking);
            foreach (is_array($ranking['warnings'] ?? null) ? $ranking['warnings'] : [] as $warning) {
                $warningRut = (string) ($warning['rut'] ?? '');
                $rankingWarningRows[] = [
                    'process_id' => $processId,
                    'process_name' => (string) ($process['name'] ?? 'Proceso'),
                    'process_code' => (string) ($process['code'] ?? ''),
                    'rut' => $warningRut,
                    'name' => (string) ($warning['name'] ?? ''),
                    'warning' => (string) ($warning['warning'] ?? 'Datos insuficientes.'),
                    'instrument_statuses' => $rankingInstrumentStatus['statuses_by_rut'][$warningRut] ?? [],
                ];
            }
            $this->collectUniqueProcessProgress($uniqueUserProgress, $users, $sessions, $activeInstruments, $selectedInstrumentIds);

            foreach (['sessions_total', 'sessions_finished', 'activity_events_total', 'activity_attention_total', 'activity_risk_total'] as $key) {
                $overall[$key] += (int) ($stats[$key] ?? 0);
            }
            $overall['ranking_ranked'] += (int) ($rankingSummary['ranked'] ?? 0);
            $overall['ranking_warnings'] += (int) ($rankingSummary['warnings'] ?? 0);
            $overall['ranking_recommended'] += (int) ($rankingSummary['recommended'] ?? 0);
            $overall['ranking_observation'] += (int) ($rankingSummary['observation'] ?? 0);
            $overall['ranking_not_recommended'] += (int) ($rankingSummary['not_recommended'] ?? 0);
            $overall['ranking_knockouts'] += (int) ($rankingSummary['knockouts'] ?? 0);

            foreach ($this->processCompletedTrend($sessions, $selectedInstrumentIds, array_keys($trend)) as $date => $count) {
                $trend[$date]['count'] += $count;
            }

            $processRows[] = array_merge($process, $stats, [
                'progress_percent' => (int) ($stats['users_total'] > 0 ? round(((int) $stats['evaluated'] / (int) $stats['users_total']) * 100) : 0),
                'ranking_summary' => $rankingSummary,
                'can_view_process' => $this->processes->can(current_user() ?: [], $processId, 'view_process'),
                'can_view_ranking' => $this->processes->can(current_user() ?: [], $processId, 'view_process_ranking'),
            ]);
        }
        $overall = array_merge($overall, $this->uniqueDashboardProgressStats($uniqueUserProgress));

        usort($processRows, static function (array $a, array $b): int {
            $pending = ((int) ($b['pending'] ?? 0)) <=> ((int) ($a['pending'] ?? 0));
            return $pending !== 0 ? $pending : strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return [
            'title' => 'Dashboard Avance | e-talent',
            'currentPage' => 'test-process.dashboard',
            'processes' => $dashboardProcesses,
            'processRows' => $processRows,
            'overall' => $overall,
            'duplicateAssignments' => $this->duplicateProcessAssignmentsSummary($activeProcessesByUser),
            'rankingWarningRows' => $rankingWarningRows,
            'rankingWarningInstrumentColumns' => array_values($rankingWarningInstrumentColumns),
            'trend' => array_values($trend),
            'rankingConfig' => $rankingConfig,
            'rankingStoredConfig' => $storedRankingConfig,
            'rankingUsesOfficialConfig' => $this->progressRankingSummary->isOfficialConfig($rankingConfig),
            'rankingIsPreviewConfig' => $this->dashboardRankingHasPreview(),
            'rankingPresets' => $rankingPresets,
            'rankingSelectedPresetId' => (int) ($selectedRankingPreset['id'] ?? -1),
            'canManageRankingPresets' => $canConfigureRanking,
            'canConfigureRanking' => $canConfigureRanking,
            'rankingCompanyAssignment' => $canConfigureRanking ? null : $this->settings->rankingCompanyAssignment((int) (current_user()['company_id'] ?? 0)),
            'rankingPresetsStorageReady' => $this->settings->hasRankingPresetsStorage(),
            'rankingScope' => $rankingScope,
            'canViewCompleteRanking' => $this->hasAnyProcessRankingAccess($dashboardProcesses),
            'dashboardFields' => $dashboardFields,
            'dashboardFieldOptions' => $dashboardFieldOptions,
            'filters' => [
                'process_id' => $processFilter,
                'company_id' => $dashboardFilters['company_id'],
                'process_ids' => $dashboardFilters['process_ids'],
                'fields' => $fieldFilters,
            ],
            'chartData' => $this->processDashboardChartData($processRows, array_values($trend), $overall),
        ];
    }

    private function dashboardFilterOptions(): array
    {
        $user = current_user() ?: [];
        $isGlobalAdmin = $this->hasGlobalProcessManagement();
        $companies = $isGlobalAdmin ? (new CompanyModel())->active() : [];
        $companyId = $isGlobalAdmin
            ? max(0, (int) ($_GET['company_id'] ?? 0))
            : max(0, (int) ($user['company_id'] ?? 0));
        $requestedProcessIds = $_GET['process_ids'] ?? [];
        if (!is_array($requestedProcessIds)) {
            $requestedProcessIds = [$requestedProcessIds];
        }
        $processIds = array_values(array_unique(array_filter(array_map('intval', $requestedProcessIds), static fn(int $id): bool => $id > 0)));
        $processes = $this->processes->allForUser($user);
        if ($isGlobalAdmin) {
            if ($companyId > 0) {
                $processes = array_values(array_filter($processes, static fn(array $process): bool => (int) ($process['company_id'] ?? 0) === $companyId));
            } else {
                $processIds = [];
            }
        }
        if (!$isGlobalAdmin && !$processIds) {
            $processIds = array_values(array_filter(array_map(static fn(array $process): int => (int) ($process['id'] ?? 0), $processes)));
        }

        return [
            'is_global_admin' => $isGlobalAdmin,
            'companies' => $companies,
            'processes' => $processes,
            'company_id' => $companyId,
            'process_ids' => $processIds,
        ];
    }

    public function rankingAll(): void
    {
        require_auth();
        $rankingProcesses = $this->rankingProcessesForCurrentUser();

        if (!$rankingProcesses) {
            platform_error(403, 'No tienes permisos para ver ranking de procesos.', [
                'chips' => ['Procesos', 'Ranking'],
                'detailRows' => [
                    'Permiso requerido' => 'view_process_ranking',
                ],
            ]);
        }

        $canConfigureRanking = has_permission('manage_ranking_presets');
        $fieldFilters = $canConfigureRanking && is_array($_GET['fields'] ?? null) ? $_GET['fields'] : [];
        $officialRankingConfig = $this->progressRankingSummary->officialConfig();
        $storedRankingConfig = $this->progressRankingSummary->activeConfig($this->settings->rankingConfig());
        $rankingPresets = $canConfigureRanking ? $this->settings->rankingPresetOptions($officialRankingConfig) : [];
        $selectedRankingPreset = $canConfigureRanking ? $this->rankingPresetFromRequest($officialRankingConfig) : null;
        $rankingConfig = $canConfigureRanking
            ? $this->dashboardRankingConfig($storedRankingConfig)
            : $this->progressRankingSummary->activeConfig($this->settings->rankingConfigForCompany(
                (int) (current_user()['company_id'] ?? 0),
                $officialRankingConfig
            ));
        if ($canConfigureRanking && $selectedRankingPreset) {
            $rankingConfig = $this->progressRankingSummary->activeConfig($selectedRankingPreset['config']);
        }

        $ranking = $this->completeProcessRanking($rankingProcesses, $fieldFilters, $rankingConfig);

        $rankingReportsByCompany = $this->rankingReportsByCompany($ranking['rows'] ?? [], $rankingProcesses);

        $this->render('tests/processes/ranking_all', [
            'title' => 'Ranking Completo | e-talent',
            'currentPage' => 'test-process.dashboard',
            'ranking' => $ranking,
            'processesTotal' => count($rankingProcesses),
            'usersTotal' => (int) ($ranking['meta']['users_total'] ?? 0),
            'sessionsTotal' => (int) ($ranking['meta']['sessions_total'] ?? 0),
            'rankingConfig' => $rankingConfig,
            'rankingUsesOfficialConfig' => $this->progressRankingSummary->isOfficialConfig($rankingConfig),
            'rankingPresets' => $rankingPresets,
            'rankingSelectedPresetId' => (int) ($selectedRankingPreset['id'] ?? -1),
            'canManageRankingPresets' => $canConfigureRanking,
            'canConfigureRanking' => $canConfigureRanking,
            'rankingCompanyAssignment' => $canConfigureRanking ? null : $this->settings->rankingCompanyAssignment((int) (current_user()['company_id'] ?? 0)),
            'rankingPresetsStorageReady' => $this->settings->hasRankingPresetsStorage(),
            'rankingSummary' => $this->progressRankingSummary->dashboardSummary($ranking),
            'classificationCounts' => $this->progressRankingSummary->classificationCounts($ranking),
            'dashboardQueryString' => $this->rankingAllBackQueryString(),
            'rankingReportsByCompany' => $rankingReportsByCompany,
        ]);
    }

    private function rankingProcessesForCurrentUser(): array
    {
        $user = current_user() ?: [];
        $processes = $this->processes->allForUser($user);
        $rankingProcesses = [];
        foreach ($processes as $process) {
            $processId = (int) ($process['id'] ?? 0);
            if ($processId > 0 && $this->processes->can($user, $processId, 'view_process_ranking')) {
                $rankingProcesses[] = $process;
            }
        }

        return $rankingProcesses;
    }

    private function dashboardRankingConfig(array $storedRankingConfig): array
    {
        $preset = (string) ($_GET['ranking_preset'] ?? '');
        if ($preset === 'official') {
            return $this->progressRankingSummary->officialConfig();
        }

        if ($preset === 'saved') {
            return $storedRankingConfig;
        }

        if (is_array($_GET['ranking_config'] ?? null)) {
            return $this->progressRankingSummary->configFromRequest($_GET['ranking_config']);
        }

        return $storedRankingConfig;
    }

    private function rankingPresetFromRequest(array $officialConfig): ?array
    {
        if (!array_key_exists('ranking_preset_id', $_GET)) {
            return null;
        }
        if (is_array($_GET['ranking_config'] ?? null) && !isset($_GET['ranking_load_preset'])) {
            return null;
        }

        return $this->settings->rankingPresetById(max(0, (int) $_GET['ranking_preset_id']), $officialConfig);
    }

    private function dashboardRankingHasPreview(): bool
    {
        if (!has_permission('manage_ranking_presets')) {
            return false;
        }

        if (array_key_exists('ranking_preset_id', $_GET) && !is_array($_GET['ranking_config'] ?? null)) {
            return false;
        }

        $preset = (string) ($_GET['ranking_preset'] ?? '');
        if (in_array($preset, ['official', 'saved'], true)) {
            return false;
        }

        return is_array($_GET['ranking_config'] ?? null);
    }

    private function dashboardRankingScope(): string
    {
        return (string) ($_GET['ranking_scope'] ?? 'filtered') === 'process'
            ? 'process'
            : 'filtered';
    }

    public function reviewAssignment(): void
    {
        require_auth();
        if (!$this->hasProcessManagement()) {
            platform_error(403, 'No tienes permisos para revisar o reasignar usuarios de procesos.', [
                'pageTitle' => 'Acceso restringido',
                'pageLead' => 'Esta herramienta requiere permisos de administracion de procesos.',
            ]);
        }

        $currentUser = current_user() ?: [];
        $companyId = (int) ($currentUser['company_id'] ?? 0);
        $companyScoped = $this->isCompanyScopedProcessActor($currentUser);
        if ($companyScoped && $companyId <= 0) {
            platform_error(403, 'Tu perfil de Administrador Cliente no tiene una empresa asociada.', [
                'pageTitle' => 'Configuracion incompleta',
                'pageLead' => 'No es posible revisar o reasignar usuarios sin una empresa vinculada.',
            ]);
        }

        $rut = UserModel::formatRut(trim((string) ($_GET['rut'] ?? $_POST['rut'] ?? '')));
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            if (!UserModel::isValidRut($rut)) {
                flash('danger', 'Ingresa un RUT valido.');
                redirect(route_url('test-process.review-assignment'));
            }

            $result = $this->processes->reassignUserProcess(
                $rut,
                max(0, (int) ($_POST['source_process_id'] ?? 0)),
                max(0, (int) ($_POST['target_process_id'] ?? 0)),
                (int) ($currentUser['id'] ?? 0),
                (string) ($_POST['confirm_delete_answers'] ?? '') === '1',
                $companyScoped ? $companyId : 0
            );

            if (!empty($result['ok'])) {
                flash('success', sprintf(
                    'Usuario reasignado correctamente desde "%s" hacia "%s". Sesiones nuevas: %d, existentes: %d, respuestas eliminadas: %d.',
                    (string) ($result['source_process'] ?? 'proceso origen'),
                    (string) ($result['target_process'] ?? 'proceso destino'),
                    (int) ($result['sessions_created'] ?? 0),
                    (int) ($result['sessions_existing'] ?? 0),
                    (int) ($result['answers_deleted'] ?? 0)
                ));
            } elseif (!empty($result['requires_confirmation'])) {
                flash('warning', 'El usuario tiene respuestas guardadas. Confirma la reasignacion desde la pantalla para eliminar esas respuestas.');
            } else {
                flash('danger', (string) ($result['message'] ?? 'No se pudo reasignar el usuario.'));
            }

            redirect(route_url('test-process.review-assignment') . '?rut=' . urlencode($rut));
        }

        $review = null;
        if ($rut !== '') {
            if (UserModel::isValidRut($rut)) {
                $review = $this->processes->assignmentReviewByRut($rut, $companyScoped ? $companyId : 0);
            } else {
                $error = 'Ingresa un RUT valido.';
            }
        }

        $this->render('tests/processes/review_assignment', [
            'title' => 'Revisar asignacion | e-talent',
            'currentPage' => 'test-process.review-assignment',
            'rut' => $rut,
            'error' => $error,
            'review' => $review,
            'companyScoped' => $companyScoped,
            'companyName' => (string) ($currentUser['company_name'] ?? ''),
        ]);
    }

    public function form(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $process = $id ? $this->requireProcess($id, 'manage_process_settings') : null;

        if (!$id && !($this->hasProcessManagement())) {
            platform_error(403, 'No tienes permisos para crear procesos.', [
                'chips' => ['Procesos', 'Permisos'],
            ]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            try {
                $processId = $this->processes->save($_POST, $id, (int) current_user()['id'], (int) (current_user()['company_id'] ?? 0));
                $this->processes->syncInstruments($processId, is_array($_POST['instrument_ids'] ?? null) ? $_POST['instrument_ids'] : []);
                $this->processes->syncEvaluationForms($processId, is_array($_POST['evaluation_form_ids'] ?? null) ? $_POST['evaluation_form_ids'] : []);
                $this->processes->syncAssignableProfiles($processId, $this->processes->selectedAssignableProfileIds($processId));
                $this->processes->syncUserAdmins($processId, is_array($_POST['user_permissions'] ?? null) ? $_POST['user_permissions'] : []);
                $this->processes->clearProfileAdmins($processId);
                flash('success', 'Proceso guardado correctamente.');
                redirect(route_url('test-process.show', $processId));
            } catch (Throwable $exception) {
                flash('danger', $exception->getMessage());
                $process = array_merge($process ?: [], $_POST);
            }
        }

        $selectedProfiles = $id ? $this->processes->selectedProfileAdmins($id) : [];
        $selectedAssignableProfileIds = $id ? $this->processes->selectedAssignableProfileIds($id) : [];
        $selectedUserAdmins = $id ? $this->processes->selectedUserAdmins($id) : [];

        $this->render('tests/processes/form', [
            'title' => ($id ? 'Editar proceso' : 'Nuevo proceso') . ' | e-talent',
            'currentPage' => 'test-processes',
            'process' => $process,
            'statuses' => TestProcessModel::STATUSES,
            'instruments' => $this->processes->activeInstruments(),
            'selectedInstrumentIds' => $id ? $this->processes->selectedInstrumentIds($id) : [],
            'evaluationForms' => $this->processes->activeEvaluationForms(),
            'selectedEvaluationFormIds' => $id ? $this->processes->selectedEvaluationFormIds($id) : [],
            'fields' => $this->processes->userFields(),
            'selectedFields' => $id ? $this->processes->selectedFields($id) : [],
            'profiles' => $this->processes->activeProfiles(),
            'adminUsers' => $this->processes->activeProcessAdminUsers(),
            'selectedAssignableProfileIds' => $selectedAssignableProfileIds,
            'selectedProfiles' => $selectedProfiles,
            'selectedUserAdmins' => $selectedUserAdmins,
            'processPermissions' => TestProcessModel::PROCESS_PERMISSIONS,
        ]);
    }

    public function show(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $process = $this->requireProcess($id, 'view_process');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            if (!$this->processes->can(current_user() ?: [], $id, 'manage_process_users')) {
                platform_error(403, 'No tienes permisos para gestionar usuarios de este proceso.', [
                    'chips' => ['Proceso', 'Usuarios'],
                    'detailRows' => [
                        'Permiso requerido' => 'manage_process_users',
                    ],
                ]);
            }

            $result = $this->processes->assignUsers($id, $this->selectedUserIdsFromRequest(), (int) current_user()['id']);
            $message = sprintf(
                'Usuarios agregados: %d nuevos, %d ya existentes/reactivados. Evaluaciones asignadas: %d nuevas, %d ya existentes. Formularios asignados: %d nuevos, %d ya existentes.',
                (int) $result['created'],
                (int) $result['existing'],
                (int) $result['sessions_created'],
                (int) $result['sessions_existing'],
                (int) ($result['evaluations_created'] ?? 0),
                (int) ($result['evaluations_existing'] ?? 0)
            );
            flash('success', $message);
            redirect(route_url('test-process.show', $id));
        }

        $this->render('tests/processes/show', [
            'title' => 'Proceso | e-talent',
            'currentPage' => 'test-processes',
            'process' => $process,
            'summary' => $this->processes->summary($id),
            'processUsers' => $this->processes->processUsers($id),
            'sessions' => $this->processes->processSessions($id),
            'instruments' => $this->processes->activeInstruments(),
            'selectedInstrumentIds' => $this->processes->selectedInstrumentIds($id),
            'evaluationForms' => $this->processes->selectedEvaluationForms($id),
            'evaluationAssignments' => $this->processes->processEvaluationAssignments($id),
            'fields' => $this->visibleProcessFields($id),
            'availableUserFilters' => $this->processes->can(current_user() ?: [], $id, 'manage_process_users')
                ? $this->processes->availableUserFilterOptions($id, $this->visibleProcessFields($id))
                : ['companies' => [], 'fields' => []],
            'canManageUsers' => $this->processes->can(current_user() ?: [], $id, 'manage_process_users'),
            'canManageSessionActions' => $this->processes->can(current_user() ?: [], $id, TestProcessModel::SUPERVISOR_SESSION_PERMISSION),
            'canEditProcessUserData' => $this->processes->can(current_user() ?: [], $id, 'manage_process_user_data'),
            'canManageAssignments' => $this->processes->can(current_user() ?: [], $id, 'manage_process_assignments'),
            'canManageSettings' => $this->processes->can(current_user() ?: [], $id, 'manage_process_settings'),
            'canViewResults' => $this->processes->can(current_user() ?: [], $id, 'view_process_results'),
            'canViewRanking' => $this->processes->can(current_user() ?: [], $id, 'view_process_ranking'),
            'processHasResults' => $this->processes->processHasResults($id),
        ]);
    }

    public function delete(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $process = $this->requireProcess($id, 'manage_process_settings');
        verify_csrf();

        if ($this->processes->deleteIfNoResults($id)) {
            flash('success', 'Proceso eliminado correctamente. Tambien se quitaron sus asignaciones pendientes y configuracion asociada.');
            redirect(route_url('test-processes'));
        }

        flash('warning', 'No se puede eliminar el proceso "' . (string) ($process['name'] ?? '') . '" porque existen evaluaciones de usuarios asignados con resultados.');
        redirect(route_url('test-process.show', $id));
    }

    public function availableUsers(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $this->requireProcess($id, 'manage_process_users');

        $filters = $this->availableUserFiltersFromRequest();
        if ((string) ($_GET['mode'] ?? '') === 'ids') {
            $this->jsonResponse([
                'ok' => true,
                'ids' => $this->processes->availableUserIds($id, $filters),
            ]);
            return;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(10, min(100, (int) ($_GET['per_page'] ?? 25)));
        $result = $this->processes->availableUsersPage($id, $filters, $page, $perPage);

        $this->jsonResponse([
            'ok' => true,
            'users' => array_map([$this, 'availableUserPayload'], $result['rows']),
            'total' => (int) $result['total'],
            'page' => (int) $result['page'],
            'per_page' => (int) $result['per_page'],
            'total_pages' => (int) $result['total_pages'],
        ]);
    }

    public function ranking(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $process = $this->requireProcess($id, 'view_process_ranking');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_permission('manage_ranking_presets');
            verify_csrf();
            $action = (string) ($_POST['ranking_action'] ?? 'save');
            if ($action === 'reset') {
                $this->settings->resetRankingConfig();
                flash('success', 'Configuracion de ranking restaurada a la matriz oficial.');
            } else {
                $this->settings->setRankingConfig($this->progressRankingSummary->configFromRequest($_POST['ranking_config'] ?? []));
                flash('success', 'Configuracion de ranking actualizada.');
            }
            redirect(route_url('test-process.ranking', $id));
        }

        $officialRankingConfig = $this->progressRankingSummary->officialConfig();
        $canConfigureRanking = has_permission('manage_ranking_presets');
        $rankingPresets = $canConfigureRanking ? $this->settings->rankingPresetOptions($officialRankingConfig) : [];
        $selectedRankingPreset = $canConfigureRanking ? $this->rankingPresetFromRequest($officialRankingConfig) : null;
        $rankingConfig = $canConfigureRanking
            ? ($selectedRankingPreset
                ? $this->progressRankingSummary->activeConfig($selectedRankingPreset['config'])
                : $this->progressRankingSummary->activeConfig($this->settings->rankingConfig()))
            : $this->progressRankingSummary->activeConfig($this->settings->rankingConfigForCompany(
                (int) ($process['company_id'] ?? (current_user()['company_id'] ?? 0)),
                $officialRankingConfig
            ));
        $processUsers = $this->processes->processUsers($id);
        $sessions = $this->processes->processSessions($id);
        $instruments = $this->processes->activeInstruments();
        $selectedInstrumentIds = $this->processes->selectedInstrumentIds($id);
        $rankingSessions = $this->processRankingSessions($processUsers, $sessions, $instruments, $selectedInstrumentIds);

        $rankingSummaryCache = $this->sessions->summariesForSessions(array_column($rankingSessions, 'id'));
        $ranking = $this->progressRankingSummary->build(
            $rankingSessions,
            function (int $sessionId, array $session) use (&$rankingSummaryCache): array {
                return $this->rankingSessionSummary($sessionId, $session, $rankingSummaryCache);
            },
            $rankingConfig
        );

        $rankingReports = $this->rankingReportsForCompany((int) ($process['company_id'] ?? (current_user()['company_id'] ?? 0)));

        $this->render('tests/processes/ranking', [
            'title' => 'Ranking Resumen | e-talent',
            'currentPage' => 'test-processes',
            'process' => $process,
            'ranking' => $ranking,
            'usersTotal' => count(array_filter($processUsers, static fn(array $user): bool => (string) ($user['status'] ?? '') !== 'cancelled')),
            'sessionsTotal' => count(array_filter($rankingSessions, static fn(array $session): bool => (int) ($session['id'] ?? 0) > 0)),
            'rankingConfig' => $rankingConfig,
            'rankingOfficialConfig' => $officialRankingConfig,
            'rankingUsesOfficialConfig' => $this->progressRankingSummary->isOfficialConfig($rankingConfig),
            'rankingPresets' => $rankingPresets,
            'rankingSelectedPresetId' => (int) ($selectedRankingPreset['id'] ?? -1),
            'canManageRankingPresets' => $canConfigureRanking,
            'canConfigureRanking' => $canConfigureRanking,
            'rankingCompanyAssignment' => $canConfigureRanking ? null : $this->settings->rankingCompanyAssignment((int) ($process['company_id'] ?? (current_user()['company_id'] ?? 0))),
            'rankingPresetsStorageReady' => $this->settings->hasRankingPresetsStorage(),
            'rankingSummary' => $this->progressRankingSummary->dashboardSummary($ranking),
            'classificationCounts' => $this->progressRankingSummary->classificationCounts($ranking),
            'rankingReports' => $rankingReports,
        ]);
    }

    /**
     * Devuelve los informes registrados que pueden ejecutarse desde un
     * ranking de proceso. La funcionalidad se declara al registrar el
     * informe y queda versionada; source.ranking se mantiene como respaldo
     * de compatibilidad para informes antiguos.
     */
    private function rankingReportsForCompany(int $companyId): array
    {
        if ($companyId <= 0 || !class_exists('ReportDefinitionModel')) {
            return [];
        }

        $reportModel = new ReportDefinitionModel();
        $reports = $reportModel->publishedAssignmentsForCompanyAndFunctionality($companyId, 'ranking');
        if (!$reports) {
            return [];
        }
        $interpreter = new ReportMarkdownInterpreter();
        $compatible = [];

        foreach ($reports as $report) {
            try {
                $spec = $interpreter->parse((string) ($report['markdown_content'] ?? ''));
                $metadata = $spec['metadata'] ?? [];
                if ((string) ($metadata['entity'] ?? '') !== 'process_user') {
                    continue;
                }

                $compatible[] = [
                    'id' => (int) ($report['id'] ?? 0),
                    'name' => (string) ($report['name'] ?? 'Informe'),
                    'version' => (int) ($report['version'] ?? 1),
                ];
            } catch (Throwable $exception) {
                error_log('Ranking report compatibility check failed: ' . $exception->getMessage());
            }
        }

        return $compatible;
    }

    private function rankingReportsByCompany(array $rows, array $processes): array
    {
        $processesById = [];
        foreach ($processes as $process) {
            $processesById[(int) ($process['id'] ?? 0)] = $process;
        }

        $reportsByCompany = [];
        foreach ($rows as $row) {
            $processId = (int) ($row['_process_id'] ?? 0);
            $companyId = (int) ($processesById[$processId]['company_id'] ?? 0);
            if ($companyId > 0 && !array_key_exists($companyId, $reportsByCompany)) {
                $reportsByCompany[$companyId] = $this->rankingReportsForCompany($companyId);
            }
        }

        return $reportsByCompany;
    }

    public function exportResults(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $process = $this->requireProcess($id, 'view_process_results');

        $payload = $this->processes->exportResults($id);
        $filename = sprintf(
            'resultados_%s_%s.json.gz',
            preg_replace('/[^a-z0-9_-]+/i', '_', (string) ($process['code'] ?? 'proceso')),
            date('Ymd_His')
        );
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            platform_error(500, 'No se pudo preparar la exportacion de resultados.');
        }
        $compressed = gzencode($json, 6);
        if ($compressed === false) {
            platform_error(500, 'No se pudo comprimir la exportacion de resultados.');
        }

        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        echo $compressed;
    }

    public function downloadRankingReport(): void
    {
        require_auth();
        $processId = request_secure_id('test_process');
        $process = $this->requireProcess($processId, 'view_process_ranking');
        $sessionId = $this->rankingReportSessionIdFromRequest();
        $session = $this->processes->processSessionForReport($processId, $sessionId);
        if (!$session) {
            platform_error(404, 'No se encontro la sesion solicitada para este proceso.');
        }

        $payload = $this->rankingReportPayload($process, $session);
        $pdf = $this->rankingReportPdf->render($payload);
        $filename = $this->rankingReportPdf->filename($payload);
        $disposition = (string) ($_GET['view'] ?? '') === '1' ? 'inline' : 'attachment';

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        echo $pdf;
    }

    public function downloadRankingAllReportsZip(): void
    {
        require_auth();
        session_write_close();
        @set_time_limit(0);

        $baseDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'ranking_reports';
        $downloadToken = trim((string) ($_GET['download'] ?? ''));
        if ($downloadToken !== '') {
            if (!preg_match('/^[0-9]{8}_[0-9]{6}_[a-f0-9]{8}$/', $downloadToken)) {
                platform_error(404, 'El archivo temporal no es válido o ya fue eliminado.');
            }
            $tempDir = $baseDir . DIRECTORY_SEPARATOR . $downloadToken;
            $manifestPath = $tempDir . DIRECTORY_SEPARATOR . 'manifest.json';
            $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;
            $zipPath = is_array($manifest) ? $tempDir . DIRECTORY_SEPARATOR . basename((string) ($manifest['zip_filename'] ?? '')) : '';
            if (!is_array($manifest) || (int) ($manifest['user_id'] ?? 0) !== (int) (current_user()['id'] ?? 0) || $zipPath === '' || !is_file($zipPath)) {
                platform_error(404, 'El archivo temporal no está disponible.');
            }

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
            header('Content-Length: ' . filesize($zipPath));
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            readfile($zipPath);
            return;
        }

        if (!class_exists('ZipArchive')) {
            platform_error(500, 'El servidor no tiene habilitada la extension ZipArchive para crear el archivo ZIP.');
        }

        $rankingProcesses = $this->rankingProcessesForCurrentUser();
        if (!$rankingProcesses) {
            platform_error(403, 'No tienes permisos para ver ranking de procesos.', [
                'chips' => ['Procesos', 'Ranking'],
                'detailRows' => [
                    'Permiso requerido' => 'view_process_ranking',
                ],
            ]);
        }

        $canConfigureRanking = has_permission('manage_ranking_presets');
        $fieldFilters = $canConfigureRanking && is_array($_GET['fields'] ?? null) ? $_GET['fields'] : [];
        $officialRankingConfig = $this->progressRankingSummary->officialConfig();
        $storedRankingConfig = $this->progressRankingSummary->activeConfig($this->settings->rankingConfig());
        $selectedRankingPreset = $canConfigureRanking ? $this->rankingPresetFromRequest($officialRankingConfig) : null;
        $rankingConfig = $canConfigureRanking
            ? $this->dashboardRankingConfig($storedRankingConfig)
            : $this->progressRankingSummary->activeConfig($this->settings->rankingConfigForCompany(
                (int) (current_user()['company_id'] ?? 0),
                $officialRankingConfig
            ));
        if ($canConfigureRanking && $selectedRankingPreset) {
            $rankingConfig = $this->progressRankingSummary->activeConfig($selectedRankingPreset['config']);
        }

        $ranking = $this->completeProcessRanking($rankingProcesses, $fieldFilters, $rankingConfig);
        $rows = is_array($ranking['rows'] ?? null) ? $ranking['rows'] : [];
        if (!$rows) {
            platform_error(422, 'No hay postulantes con informe disponible para generar el ZIP.');
        }

        $timestamp = date('Ymd_His');
        $tempDir = $this->createRankingReportsTempDir($timestamp);
        $pdfDir = $tempDir . DIRECTORY_SEPARATOR . 'pdf';
        if (!is_dir($pdfDir) && !mkdir($pdfDir, 0775, true)) {
            $this->removeRankingReportsTempDir($tempDir);
            platform_error(500, 'No se pudo crear la carpeta temporal para los informes.');
        }

        $zipPath = $tempDir . DIRECTORY_SEPARATOR . $timestamp . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->removeRankingReportsTempDir($tempDir);
            platform_error(500, 'No se pudo crear el archivo ZIP temporal.');
        }

        $processesById = [];
        foreach ($rankingProcesses as $process) {
            $processesById[(int) ($process['id'] ?? 0)] = $process;
        }

        $usedNames = [];
        $created = 0;
        try {
            foreach ($rows as $row) {
                $processId = (int) ($row['_process_id'] ?? 0);
                $sessionId = (int) ($row['_report_session_id'] ?? 0);
                if ($processId <= 0 || $sessionId <= 0 || !isset($processesById[$processId])) {
                    continue;
                }

                $session = $this->processes->processSessionForReport($processId, $sessionId);
                if (!$session) {
                    continue;
                }

                $payload = $this->rankingReportPayload($processesById[$processId], $session, $rankingConfig);
                $pdf = $this->rankingReportPdf->render($payload);
                $filename = $this->rankingReportRutFilename((string) ($payload['candidate']['rut'] ?? ''), $usedNames);
                $pdfPath = $pdfDir . DIRECTORY_SEPARATOR . $filename;
                if (file_put_contents($pdfPath, $pdf) === false) {
                    throw new RuntimeException('No se pudo escribir uno de los informes PDF temporales.');
                }

                if (!$zip->addFile($pdfPath, $filename)) {
                    throw new RuntimeException('No se pudo agregar uno de los informes al ZIP.');
                }
                $created++;
                unset($pdf, $payload);
            }

            $zip->close();
        } catch (Throwable $exception) {
            $zip->close();
            $this->removeRankingReportsTempDir($tempDir);
            platform_error(500, 'No se pudo generar el ZIP de informes: ' . $exception->getMessage());
        }

        if ($created === 0 || !is_file($zipPath)) {
            $this->removeRankingReportsTempDir($tempDir);
            platform_error(422, 'No se encontraron informes validos para incluir en el ZIP.');
        }

        $zipFilename = $timestamp . '.zip';
        $token = basename($tempDir);
        $manifest = [
            'user_id' => (int) (current_user()['id'] ?? 0),
            'zip_filename' => $zipFilename,
            'created_at' => date('c'),
            'expires_at' => date('Y-m-d 00:00:00', strtotime('+1 day')),
            'items' => $created,
        ];
        if (file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
            $this->removeRankingReportsTempDir($tempDir);
            platform_error(500, 'No se pudo registrar el archivo temporal generado.');
        }

        $this->jsonResponse([
            'ok' => true,
            'message' => 'Proceso finalizado correctamente. Se generaron ' . $created . ' informes PDF.',
            'detail' => 'El ZIP y sus PDFs temporales se eliminarán automáticamente a medianoche.',
            'download_url' => route_url('test-process.ranking-all-reports-zip') . '?download=' . rawurlencode($token),
            'download_filename' => $zipFilename,
            'expires_at' => $manifest['expires_at'],
            'items' => $created,
        ]);
    }

    public function importResults(): void
    {
        require_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(route_url('test-processes'));
        }

        $id = request_secure_id('test_process');
        $process = $this->requireProcess($id, 'manage_process_assignments');
        if ($this->requestExceedsPostLimit()) {
            flash('danger', sprintf(
                'El archivo supera el limite de carga configurado en PHP (%s). Exporta/importa el respaldo comprimido .json.gz o aumenta post_max_size y upload_max_filesize.',
                ini_get('post_max_size') ?: 'desconocido'
            ));
            redirect(route_url('test-process.show', $id));
        }
        verify_csrf();

        $file = $_FILES['results_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('warning', 'Selecciona un archivo JSON de resultados para importar.');
            redirect(route_url('test-process.show', $id));
        }

        $contents = $this->readResultsImportFile($file);
        $payload = json_decode((string) $contents, true);
        if (!is_array($payload)) {
            flash('danger', 'El archivo no tiene un formato JSON valido o no se pudo descomprimir.');
            redirect(route_url('test-process.show', $id));
        }

        $exportProcessCode = (string) ($payload['process']['code'] ?? '');
        $currentProcessCode = (string) ($process['code'] ?? '');
        if ($exportProcessCode === '' || $currentProcessCode === '' || $exportProcessCode !== $currentProcessCode) {
            flash('danger', 'El archivo corresponde a otro proceso o no incluye el codigo de proceso esperado.');
            redirect(route_url('test-process.show', $id));
        }

        try {
            $result = $this->processes->importResults($id, $payload, (int) (current_user()['id'] ?? 0));
            $message = sprintf(
                'Importacion finalizada. Sesiones creadas: %d. Reemplazadas por mejor resultado: %d. Omitidas: %d.',
                (int) ($result['created_sessions'] ?? 0),
                (int) ($result['replaced_sessions'] ?? 0),
                count($result['skipped'] ?? [])
            );
            flash(empty($result['skipped']) ? 'success' : 'warning', $message);
        } catch (Throwable $exception) {
            flash('danger', 'No se pudo importar el archivo: ' . $exception->getMessage());
        }

        redirect(route_url('test-process.show', $id));
    }

    private function readResultsImportFile(array $file): string
    {
        $path = (string) ($file['tmp_name'] ?? '');
        $contents = $path !== '' ? file_get_contents($path) : false;
        if ($contents === false) {
            return '';
        }

        $name = strtolower((string) ($file['name'] ?? ''));
        if (substr($name, -3) === '.gz' || substr($contents, 0, 2) === "\x1f\x8b") {
            $decoded = gzdecode($contents);
            return $decoded === false ? '' : $decoded;
        }

        return $contents;
    }

    private function rankingReportSessionIdFromRequest(): int
    {
        $token = (string) ($_GET['session'] ?? '');
        if ($token !== '') {
            try {
                return secure_url_id($token, 'test_session');
            } catch (RuntimeException $exception) {
                platform_error(410, 'El enlace seguro del informe expiro o no es valido.');
            }
        }

        return max(0, (int) ($_GET['session_id'] ?? 0));
    }

    private function createRankingReportsTempDir(string $timestamp): string
    {
        $baseDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'ranking_reports';
        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true)) {
            platform_error(500, 'No se pudo crear el directorio temporal de informes.');
        }

        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (Throwable $exception) {
            $suffix = substr(str_replace('.', '', uniqid('', true)), -8);
        }

        $tempDir = $baseDir . DIRECTORY_SEPARATOR . $timestamp . '_' . $suffix;
        if (!mkdir($tempDir, 0775, true)) {
            platform_error(500, 'No se pudo crear la carpeta temporal del proceso ZIP.');
        }

        return $tempDir;
    }

    private function rankingReportRutFilename(string $rut, array &$usedNames): string
    {
        $rut = strtoupper(trim($rut));
        $parts = explode('-', $rut, 2);
        $number = preg_replace('/\D+/', '', (string) ($parts[0] ?? '')) ?: '';
        $dv = '';
        if (isset($parts[1])) {
            $dv = preg_replace('/[^0-9K]+/', '', (string) $parts[1]) ?: '';
        } else {
            $compactRut = preg_replace('/[^0-9K]+/', '', $rut) ?: '';
            if (strlen($compactRut) > 1) {
                $number = substr($compactRut, 0, -1);
                $dv = substr($compactRut, -1);
            }
        }

        $base = $number !== '' && $dv !== ''
            ? $number . '-' . $dv
            : $number;
        if (!$base) {
            $base = 'sin_rut';
        }

        $candidate = $base . '.pdf';
        $counter = 2;
        while (isset($usedNames[$candidate])) {
            $candidate = $base . '_' . $counter . '.pdf';
            $counter++;
        }

        $usedNames[$candidate] = true;
        return $candidate;
    }

    private function removeRankingReportsTempDir(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeRankingReportsTempDir($path);
                continue;
            }

            if (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private function rankingReportPayload(array $process, array $session, ?array $rankingConfig = null): array
    {
        $processId = (int) ($session['process_id'] ?? $process['id'] ?? 0);
        $userId = (int) ($session['user_id'] ?? 0);
        $processUser = $this->processes->processUser($processId, $userId);
        if (!$processUser || (string) ($processUser['status'] ?? '') === 'cancelled') {
            platform_error(404, 'No se encontro el postulante activo en este proceso.');
        }

        $sessions = $this->processes->processDashboardSessionsForUser($processId, $userId);
        $selectedInstrumentIds = $this->processes->selectedInstrumentIds($processId);
        $rankingConfig = $rankingConfig === null
            ? $this->progressRankingSummary->activeConfig($this->settings->rankingConfigForCompany(
                (int) ($process['company_id'] ?? ($processUser['company_id'] ?? 0)),
                $this->progressRankingSummary->officialConfig()
            ))
            : $this->progressRankingSummary->activeConfig($rankingConfig);
        $summaryCache = [];
        $ranking = $this->processRankingForDashboard([$processUser], $sessions, $this->processes->activeInstruments(), $selectedInstrumentIds, $rankingConfig, $summaryCache, false);
        $row = (is_array($ranking['rows'] ?? null) && isset($ranking['rows'][0])) ? $ranking['rows'][0] : null;
        if (!$row) {
            platform_error(422, 'El postulante no tiene los datos suficientes para generar el informe de ranking.');
        }

        $summariesByCode = $this->rankingReportSummariesByCode($sessions);
        $scaleMap = new PostulantReportScaleMapService();

        return [
            'generated_at' => date('d-m-Y H:i'),
            'process' => [
                'id' => $processId,
                'name' => (string) ($process['name'] ?? $session['process_name'] ?? 'Proceso'),
                'code' => (string) ($process['code'] ?? $session['process_code'] ?? ''),
            ],
            'candidate' => [
                'id' => $userId,
                'name' => (string) ($processUser['name'] ?? $session['user_name'] ?? ''),
                'rut' => (string) ($processUser['rut'] ?? $session['user_rut'] ?? ''),
                'email' => (string) ($processUser['email'] ?? $session['user_email'] ?? ''),
                'age' => (string) ($processUser['age'] ?? ''),
                'company' => (string) ($processUser['company_name'] ?? ''),
            ],
            'ranking_row' => $row,
            'ipip_factors' => $scaleMap->mappedSummary($summariesByCode['ipip_16pf'] ?? []),
            'riasec_result' => $this->rankingReportRiasecResult($sessions, $summariesByCode),
            'summaries' => $summariesByCode,
        ];
    }

    private function rankingReportRiasecResult(array $sessions, array $summariesByCode): ?array
    {
        if (empty($summariesByCode['riasec'])) {
            return null;
        }

        foreach ($sessions as $session) {
            if ((string) ($session['instrument_code'] ?? '') !== 'riasec') {
                continue;
            }

            return (new RiasecRecommendationService())->buildForSession($session, $summariesByCode['riasec']);
        }

        return null;
    }

    private function rankingReportSummariesByCode(array $sessions): array
    {
        $summaries = [];
        $sessionIds = [];
        $sessionCodes = [];
        foreach ($sessions as $session) {
            $sessionId = (int) ($session['id'] ?? 0);
            $code = (string) ($session['instrument_code'] ?? '');
            if ($sessionId <= 0 || $code === '' || (string) ($session['status'] ?? '') === 'cancelled') {
                continue;
            }

            if (!in_array($code, ['ipip_16pf', 'cag_wonderlic', 'cag', 'ticl_barratt', 'riasec'], true)) {
                continue;
            }

            $sessionIds[] = $sessionId;
            $sessionCodes[$sessionId] = $code;
        }

        $summaryRows = $this->sessions->summariesForSessions($sessionIds);
        foreach ($sessionCodes as $sessionId => $code) {
            $summary = $summaryRows[$sessionId] ?? [];
            if ($summary) {
                $summaries[$code] = $summary;
            }
        }

        return $summaries;
    }

    private function requestExceedsPostLimit(): bool
    {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength <= 0 || $_POST || $_FILES) {
            return false;
        }

        $limit = $this->iniBytes((string) ini_get('post_max_size'));
        return $limit > 0 && $contentLength > $limit;
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        if ($unit === 'g') {
            $number *= 1024;
        }
        if ($unit === 'm' || $unit === 'g') {
            $number *= 1024;
        }
        if ($unit === 'k' || $unit === 'm' || $unit === 'g') {
            $number *= 1024;
        }

        return (int) $number;
    }

    public function assignSessions(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $this->requireProcess($id, 'manage_process_assignments');
        verify_csrf();

        $result = $this->processes->createMissingSessions($id, (int) current_user()['id']);
        flash('success', sprintf('Asignaciones generadas: %d nuevas, %d ya existentes.', (int) $result['created'], (int) $result['existing']));
        redirect(route_url('test-process.show', $id));
    }

    public function availability(): void
    {
        require_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(route_url('test-processes'));
        }

        $id = request_secure_id('test_process');
        $process = $this->requireProcess($id, 'manage_process_settings');
        verify_csrf();

        $status = (string) ($_POST['availability_status'] ?? 'scheduled');
        $now = time();
        $startsAt = $this->processTimestampOrNull($process['starts_at'] ?? null);
        $endsAt = $this->processTimestampOrNull($process['ends_at'] ?? null);

        if ($status === 'open_now' && $endsAt !== null && $now > $endsAt) {
            flash('warning', 'No se puede abrir ahora: el proceso ya finalizo por calendario. Para habilitarlo debes editar la fecha y hora de termino.');
            redirect(route_url('test-process.show', $id));
        }

        if ($status === 'closed_now' && $endsAt !== null && $now > $endsAt) {
            flash('warning', 'No se puede cerrar ahora: el proceso ya finalizo por calendario.');
            redirect(route_url('test-process.show', $id));
        }

        if ($status === 'closed_now' && $startsAt !== null && $now < $startsAt) {
            flash('warning', 'No se puede cerrar ahora: el proceso aun no comienza segun el calendario configurado.');
            redirect(route_url('test-process.show', $id));
        }

        $labels = [
            'scheduled' => 'El proceso volvera a respetar el calendario configurado.',
            'open_now' => 'El proceso fue abierto anticipadamente.',
            'closed_now' => 'El proceso fue cerrado anticipadamente.',
        ];

        if ($this->processes->updateAvailabilityStatus($id, $status, (int) current_user()['id'])) {
            flash('success', $labels[$status] ?? 'Disponibilidad actualizada.');
        } else {
            flash('danger', 'No se pudo actualizar la disponibilidad del proceso.');
        }

        redirect(route_url('test-process.show', $id));
    }

    private function processTimestampOrNull($value): ?int
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $timestamp = strtotime($text);

        return $timestamp !== false ? $timestamp : null;
    }

    public function editUser(): void
    {
        require_auth();
        $processId = request_secure_id('test_process');
        $process = $this->requireProcess($processId, 'manage_process_user_data');
        try {
            $userId = secure_url_id((string) ($_GET['user_sid'] ?? ''), 'user');
        } catch (RuntimeException $exception) {
            platform_error(410, 'El enlace seguro expiro o no es valido.');
        }
        $processUser = $this->processes->processUser($processId, $userId);

        if (!$processUser || (string) ($processUser['status'] ?? '') === 'cancelled') {
            platform_error(404, 'Usuario no encontrado en este proceso.', [
                'chips' => ['Proceso', 'Usuario'],
            ]);
        }
        if (trim((string) ($processUser['sex'] ?? '')) === '' || (string) ($processUser['sex'] ?? '') === 'no_ingresado') {
            $processUser['sex'] = 'no_informado';
        }

        $users = new UserModel();
        $fields = new UserFieldModel();
        $fieldDefinitions = $fields->activeForUsers();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->saveProcessUserData($users, $fields, $fieldDefinitions, $processUser, $userId);
            $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
            return;
        }

        $processToken = secure_url_token($processId, 'test_process');
        $userToken = secure_url_token($userId, 'user');
        $this->render('tests/processes/user_form', [
            'title' => 'Editar datos usuario | e-talent',
            'process' => $process,
            'processUser' => $processUser,
            'fieldDefinitions' => $fieldDefinitions,
            'fieldValues' => $fields->valuesForUser($userId),
            'formAction' => app_url('tests/processes/' . $processToken . '/edit-user/' . $userToken . '?drawer=1'),
        ], null);
    }

    public function removeUser(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $this->requireProcess($id, TestProcessModel::SUPERVISOR_SESSION_PERMISSION);
        verify_csrf();

        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $this->processes->removeUser($id, $userId);
            flash('success', 'Usuario removido del proceso. Sus asignaciones, respuestas y resultados asociados a este proceso fueron eliminados.');
        }

        redirect(route_url('test-process.show', $id));
    }

    public function removeAllUsers(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $this->requireProcess($id, TestProcessModel::SUPERVISOR_SESSION_PERMISSION);
        verify_csrf();

        $removed = $this->processes->removeAllUsers($id);
        flash('success', sprintf('Se quitaron %d usuarios del proceso junto con sus asignaciones, respuestas y resultados asociados.', $removed));
        redirect(route_url('test-process.show', $id));
    }

    public function cancelSession(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $this->requireProcess($id, TestProcessModel::SUPERVISOR_SESSION_PERMISSION);
        verify_csrf();

        $sessionId = (int) ($_POST['session_id'] ?? 0);
        if ($sessionId > 0 && $this->processes->cancelSession($id, $sessionId)) {
            flash('success', 'Asignacion cancelada.');
        } else {
            flash('warning', 'No se pudo cancelar la asignacion solicitada.');
        }

        redirect(route_url('test-process.show', $id));
    }

    public function cancelEvaluationAssignment(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $this->requireProcess($id, TestProcessModel::SUPERVISOR_SESSION_PERMISSION);
        verify_csrf();

        $formId = max(0, (int) ($_POST['form_id'] ?? 0));
        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        if ($formId > 0 && $userId > 0 && $this->processes->cancelEvaluationAssignment($id, $formId, $userId)) {
            flash('success', 'Asignación de evaluación cancelada.');
        } else {
            flash('warning', 'No se pudo cancelar la asignación solicitada.');
        }

        redirect(route_url('test-process.show', $id));
    }

    public function resetSession(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $this->requireProcess($id, TestProcessModel::SUPERVISOR_SESSION_PERMISSION);
        verify_csrf();

        $sessionId = (int) ($_POST['session_id'] ?? 0);
        if ($sessionId > 0 && $this->processes->resetSession($id, $sessionId)) {
            flash('success', 'Respuestas eliminadas. La evaluacion quedo disponible para responder nuevamente.');
        } else {
            flash('warning', 'No se pudo reiniciar la evaluacion solicitada.');
        }

        redirect(route_url('test-process.show', $id));
    }

    public function resetEvaluationAssignment(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $this->requireProcess($id, TestProcessModel::SUPERVISOR_SESSION_PERMISSION);
        verify_csrf();

        $formId = max(0, (int) ($_POST['form_id'] ?? 0));
        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        if ($this->processes->resetEvaluationAssignment($id, $formId, $userId)) {
            flash('success', 'Respuestas de la evaluación eliminadas. Quedó disponible para responder nuevamente.');
        } else {
            flash('warning', 'No se pudo reiniciar la evaluación solicitada.');
        }

        redirect(route_url('test-process.show', $id));
    }

    public function reopenExpiredEvaluationAssignment(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $process = $this->requireProcess($id, TestProcessModel::SUPERVISOR_SESSION_PERMISSION);
        verify_csrf();

        $formId = max(0, (int) ($_POST['form_id'] ?? 0));
        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        $durationMinutes = max(0, (int) ($_POST['duration_minutes'] ?? 0));
        $success = (int) ($process['allow_expired_reopen'] ?? 0) === 1
            && $durationMinutes > 0
            && $this->processes->reopenEvaluationAssignment($id, $formId, $userId, $durationMinutes, current_user() ?: []);

        if ($success) {
            flash('success', sprintf('Evaluación reabierta. El usuario tendrá %d minutos nuevos y conservará sus respuestas.', max(1, min(1440, $durationMinutes))));
        } else {
            flash('warning', 'No se pudo reabrir la evaluación. Verifica que esté completada, en curso o expirada, que el proceso esté activo y que permita reaperturas.');
        }

        redirect(route_url('test-process.show', $id));
    }

    public function reopenSession(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $process = $this->requireProcess($id, TestProcessModel::SUPERVISOR_SESSION_PERMISSION);
        verify_csrf();

        if ((int) ($process['allow_expired_reopen'] ?? 0) !== 1) {
            flash('warning', 'La reapertura de evaluaciones expiradas no esta habilitada en la configuracion del proceso.');
            redirect(route_url('test-process.show', $id));
        }

        $sessionId = (int) ($_POST['session_id'] ?? 0);
        $durationMinutes = (int) ($_POST['duration_minutes'] ?? 0);
        if ($sessionId <= 0 || $durationMinutes <= 0) {
            flash('warning', 'Indica un tiempo valido para reabrir la evaluacion.');
            redirect(route_url('test-process.show', $id));
        }

        if ($this->processes->reopenSession($id, $sessionId, $durationMinutes, current_user() ?: [])) {
            flash('success', sprintf('Evaluacion reabierta. El usuario tendra %d minutos nuevos para responder y conservara sus respuestas.', max(1, min(1440, $durationMinutes))));
        } else {
            flash('warning', 'No se pudo reabrir la evaluacion solicitada. Verifica que este completada, en curso o expirada y que el proceso lo permita.');
        }

        redirect(route_url('test-process.show', $id));
    }

    public function reopenSavedSession(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $process = $this->requireProcess($id, TestProcessModel::SUPERVISOR_SESSION_PERMISSION);
        verify_csrf();

        if ((int) ($process['allow_expired_reopen'] ?? 0) !== 1) {
            flash('warning', 'La reapertura de evaluaciones no esta habilitada en la configuracion del proceso.');
            redirect(route_url('test-process.show', $id));
        }

        $sessionId = (int) ($_POST['session_id'] ?? 0);
        $durationMinutes = (int) ($_POST['duration_minutes'] ?? 0);
        if ($sessionId <= 0 || $durationMinutes <= 0) {
            flash('warning', 'Indica un tiempo valido para reabrir la evaluacion.');
            redirect(route_url('test-process.show', $id));
        }

        if ($this->processes->reopenSession($id, $sessionId, $durationMinutes, current_user() ?: [])) {
            flash('success', sprintf('Evaluacion reabierta. El usuario tendra %d minutos nuevos para responder y conservara sus respuestas.', max(1, min(1440, $durationMinutes))));
        } else {
            flash('warning', 'No se pudo reabrir la evaluacion solicitada. Verifica que este completada, en curso o expirada y que el proceso lo permita.');
        }

        redirect(route_url('test-process.show', $id));
    }

    public function reopenExpiredInstrumentSessions(): void
    {
        require_auth();
        $id = request_secure_id('test_process');
        $process = $this->requireProcess($id, TestProcessModel::SUPERVISOR_SESSION_PERMISSION);
        verify_csrf();

        if ((int) ($process['allow_expired_reopen'] ?? 0) !== 1) {
            flash('warning', 'La reapertura de evaluaciones expiradas no esta habilitada en la configuracion del proceso.');
            redirect(route_url('test-process.show', $id));
        }

        $instrumentId = (int) ($_POST['instrument_id'] ?? 0);
        $durationMinutes = (int) ($_POST['duration_minutes'] ?? 0);
        if ($instrumentId <= 0 || $durationMinutes <= 0) {
            flash('warning', 'Selecciona un test del proceso e indica un tiempo valido para reabrir.');
            redirect(route_url('test-process.show', $id));
        }

        $selectedInstrumentIds = $this->processes->selectedInstrumentIds($id);
        if (!in_array($instrumentId, $selectedInstrumentIds, true)) {
            flash('warning', 'El test seleccionado no pertenece a este proceso.');
            redirect(route_url('test-process.show', $id));
        }

        $result = $this->processes->reopenExpiredSessionsForInstrument($id, $instrumentId, $durationMinutes, current_user() ?: []);
        if (empty($result['allowed'])) {
            flash('warning', 'La reapertura de evaluaciones expiradas no esta habilitada en la configuracion del proceso.');
            redirect(route_url('test-process.show', $id));
        }

        if (empty($result['instrument_found'])) {
            flash('warning', 'El test seleccionado no pertenece a este proceso.');
            redirect(route_url('test-process.show', $id));
        }

        $reopened = (int) ($result['reopened'] ?? 0);
        $eligible = (int) ($result['eligible'] ?? 0);
        if ($reopened <= 0) {
            flash('info', 'No hay evaluaciones expiradas para reabrir en el test seleccionado.');
        } else {
            flash('success', sprintf(
                'Se reabrieron %d evaluaciones expiradas del test seleccionado para este proceso. Elegibles encontradas: %d.',
                $reopened,
                $eligible
            ));
        }

        redirect(route_url('test-process.show', $id));
    }

    private function requireProcess(int $id, string $permission): array
    {
        if ($id <= 0) {
            platform_error(404, 'Proceso no encontrado.', [
                'chips' => ['Proceso'],
            ]);
        }

        $process = $this->processes->find($id);
        if (!$process) {
            platform_error(404, 'Proceso no encontrado.', [
                'chips' => ['Proceso'],
            ]);
        }

        if (!$this->processes->can(current_user() ?: [], $id, $permission)) {
            platform_error(403, 'No tienes permisos para acceder a este proceso.', [
                'chips' => ['Proceso', 'Permisos'],
                'detailRows' => [
                    'Permiso requerido' => $permission,
                ],
            ]);
        }

        return $process;
    }

    private function requireProcessAny(int $id, array $permissions): array
    {
        if ($id <= 0) {
            platform_error(404, 'Proceso no encontrado.', [
                'chips' => ['Proceso'],
            ]);
        }

        $process = $this->processes->find($id);
        if (!$process) {
            platform_error(404, 'Proceso no encontrado.', [
                'chips' => ['Proceso'],
            ]);
        }

        foreach ($permissions as $permission) {
            if ($this->processes->can(current_user() ?: [], $id, (string) $permission)) {
                return $process;
            }
        }

        platform_error(403, 'No tienes permisos para acceder a este proceso.', [
            'chips' => ['Proceso', 'Permisos'],
            'detailRows' => [
                'Permisos requeridos' => implode(', ', $permissions),
            ],
        ]);
    }

    private function requireAnyProcessAccess(): void
    {
        if ($this->hasGlobalProcessManagement() || has_permission('view_test_process_progress') || has_permission('view_test_process_dashboard') || has_permission('view_test_process_results')) {
            return;
        }

        $rows = $this->processes->allForUser(current_user() ?: []);
        if ($rows) {
            return;
        }

        platform_error(403, 'No tienes permisos para acceder a procesos.', [
            'chips' => ['Procesos', 'Permisos'],
        ]);
    }

    private function requireAnyProcessDashboardAccess(): void
    {
        if ($this->isCompanyAdminOrSupervisor()) {
            platform_error(403, 'No tienes permisos para acceder al Dashboard Avance.', [
                'chips' => ['Procesos', 'Dashboard'],
            ]);
        }

        if (!has_permission('view_test_process_dashboard')) {
            platform_error(403, 'No tienes permisos para acceder al Dashboard Avance.', [
                'chips' => ['Procesos', 'Dashboard'],
                'detailRows' => [
                    'Permiso requerido' => 'view_test_process_dashboard',
                ],
            ]);
        }

        $processes = $this->processes->allForUser(current_user() ?: []);
        foreach ($processes as $process) {
            if ($this->processes->can(current_user() ?: [], (int) ($process['id'] ?? 0), 'view_process_dashboard')) {
                return;
            }
        }

        platform_error(403, 'No tienes permisos para acceder al Dashboard Avance.', [
            'chips' => ['Procesos', 'Dashboard'],
            'detailRows' => [
                'Permiso requerido' => 'view_process_dashboard',
            ],
        ]);
    }

    private function isCompanyAdminOrSupervisor(): bool
    {
        $user = current_user() ?: [];
        return in_array((string) ($user['role'] ?? ''), ['company_admin', 'supervisor_sede'], true)
            || in_array((string) ($user['profile_key'] ?? ''), ['company_admin', 'supervisor_sede'], true);
    }

    private function hasAnyProcessRankingAccess(array $processes): bool
    {
        foreach ($processes as $process) {
            if ($this->processes->can(current_user() ?: [], (int) ($process['id'] ?? 0), 'view_process_ranking')) {
                return true;
            }
        }

        return false;
    }

    private function availableUserFiltersFromRequest(): array
    {
        return [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'company' => trim((string) ($_GET['company'] ?? '')),
            'fields' => is_array($_GET['fields'] ?? null) ? $_GET['fields'] : [],
        ];
    }

    private function selectedUserIdsFromRequest(): array
    {
        $json = trim((string) ($_POST['selected_user_ids_json'] ?? ''));
        if ($json !== '') {
            $ids = json_decode($json, true);
            if (is_array($ids)) {
                return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
            }
        }

        return is_array($_POST['user_ids'] ?? null) ? $_POST['user_ids'] : [];
    }

    private function availableUserPayload(array $user): array
    {
        $fields = $user['dynamic_fields'] ?? [];
        return [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'rut' => (string) ($user['rut'] ?? ''),
            'company_name' => (string) ($user['company_name'] ?? ''),
            'fields' => is_array($fields) ? $fields : [],
        ];
    }

    private function saveProcessUserData(UserModel $users, UserFieldModel $fields, array $fieldDefinitions, array $existingUser, int $userId): array
    {
        verify_csrf();

        $data = [
            'rut' => UserModel::formatRut(trim((string) ($_POST['rut'] ?? ''))),
            'role' => (string) ($existingUser['role'] ?? 'usuario'),
            'first_names' => trim((string) ($_POST['first_names'] ?? '')),
            'last_names' => trim((string) ($_POST['last_names'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'sex' => (string) ($_POST['sex'] ?? ''),
            'birth_date' => trim((string) ($_POST['birth_date'] ?? '')),
            'age' => trim((string) ($_POST['age'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'profile_id' => (int) ($existingUser['profile_id'] ?? 0),
            'company_id' => (int) ($existingUser['company_id'] ?? 0),
            'is_active' => (int) ($existingUser['is_active'] ?? 1),
        ];

        foreach ($this->validateProcessUserData($users, $data, $userId) as $error) {
            return ['ok' => false, 'message' => $error];
        }

        $expectedAge = UserModel::calculateAge($data['birth_date']);
        $data['age'] = (int) $expectedAge;
        $fieldValues = is_array($_POST['field_values'] ?? null) ? $_POST['field_values'] : [];

        foreach ($fields->validateValues($fieldDefinitions, $fieldValues) as $error) {
            return ['ok' => false, 'message' => $error];
        }

        try {
            $users->updateUser($userId, $data);
            $fields->saveValues($userId, $fieldDefinitions, $fieldValues);
        } catch (PDOException $exception) {
            return ['ok' => false, 'message' => 'No se pudo guardar el usuario. Revisa si el correo o RUT ya existe.'];
        }

        return ['ok' => true, 'message' => 'Datos del usuario actualizados correctamente.', 'reload' => true];
    }

    private function validateProcessUserData(UserModel $users, array $data, int $existingId): array
    {
        $errors = [];
        if (!UserModel::isValidRut($data['rut'] ?? '')) {
            $errors[] = 'Ingresa un RUT valido.';
        } elseif ($users->rutExists($data['rut'], $existingId)) {
            $errors[] = 'Ya existe un usuario con ese RUT.';
        }

        if (trim((string) ($data['first_names'] ?? '')) === '') {
            $errors[] = 'Ingresa los nombres.';
        }

        if (trim((string) ($data['last_names'] ?? '')) === '') {
            $errors[] = 'Ingresa los apellidos.';
        }

        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ingresa un correo valido.';
        } elseif ($users->emailExists($data['email'], $existingId)) {
            $errors[] = 'Ya existe un usuario con ese correo.';
        }

        if (!in_array($data['sex'] ?? '', UserModel::ALLOWED_SEXES, true)) {
            $errors[] = 'Selecciona una opcion valida para sexo.';
        }

        $birthDate = (string) ($data['birth_date'] ?? '');
        $date = DateTime::createFromFormat('Y-m-d', $birthDate);
        if (!$date || $date->format('Y-m-d') !== $birthDate || $date > new DateTimeImmutable('today')) {
            $errors[] = 'Ingresa una fecha de nacimiento valida, no mayor a hoy.';
        } else {
            $expectedAge = UserModel::calculateAge($birthDate);
            if (!ctype_digit((string) ($data['age'] ?? '')) || (int) $data['age'] !== (int) $expectedAge) {
                $errors[] = 'La edad debe coincidir con la fecha de nacimiento.';
            }
        }

        return $errors;
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function processDateGroups(array $processes): array
    {
        $groups = [];
        foreach ($processes as $process) {
            $key = $this->processDateGroupKey($process);
            if (!isset($groups[$key])) {
                [$startDateTime, $endDateTime] = explode('__', $key, 2);
                $groups[$key] = [
                    'key' => $key,
                    'label' => $this->processDateGroupLabel($startDateTime, $endDateTime),
                    'count' => 0,
                    'sort' => $startDateTime !== 'none' ? $startDateTime : ($endDateTime !== 'none' ? $endDateTime : '0000-00-00 00:00:00'),
                ];
            }

            $groups[$key]['count']++;
        }

        uasort($groups, static function (array $left, array $right): int {
            $dateCompare = strcmp((string) $right['sort'], (string) $left['sort']);
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return strcmp((string) $left['label'], (string) $right['label']);
        });

        return $groups;
    }

    private function processDateGroupKey(array $process): string
    {
        $startDateTime = $this->processDateTime($process['starts_at'] ?? null);
        $endDateTime = $this->processDateTime($process['ends_at'] ?? null);

        return ($startDateTime ?: 'none') . '__' . ($endDateTime ?: 'none');
    }

    private function processDateTime($value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($raw))->format('Y-m-d H:i:s');
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function processDateGroupLabel(string $startDateTime, string $endDateTime): string
    {
        $startLabel = $startDateTime !== 'none' ? $this->formatProcessGroupDateTime($startDateTime) : '';
        $endLabel = $endDateTime !== 'none' ? $this->formatProcessGroupDateTime($endDateTime) : '';

        if ($startLabel !== '' && $endLabel !== '') {
            return $startDateTime === $endDateTime ? $startLabel : $startLabel . ' al ' . $endLabel;
        }

        if ($startLabel !== '') {
            return 'Desde ' . $startLabel;
        }

        if ($endLabel !== '') {
            return 'Hasta ' . $endLabel;
        }

        return 'Sin fechas';
    }

    private function formatProcessGroupDateTime(string $dateTime): string
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime);

        return $parsed ? $parsed->format('d/m/Y H:i') : $dateTime;
    }

    private function hasGlobalProcessManagement(): bool
    {
        return has_permission('manage_tests') || has_permission('manage_test_processes');
    }

    private function hasProcessManagement(): bool
    {
        return $this->hasGlobalProcessManagement() || has_permission('manage_company_processes');
    }

    private function isCompanyScopedProcessActor(array $user): bool
    {
        if ($this->hasGlobalProcessManagement()) {
            return false;
        }

        return (string) ($user['role'] ?? '') === 'company_admin'
            || has_permission('manage_company_processes');
    }

    private function visibleProcessFields(int $processId): array
    {
        return $this->processes->userFields();
    }

    private function processRankingSessions(array $processUsers, array $sessions, array $instruments, array $selectedInstrumentIds): array
    {
        $selectedInstrumentSet = array_flip(array_map('intval', $selectedInstrumentIds));
        $selectedInstruments = [];
        foreach ($instruments as $instrument) {
            $instrumentId = (int) ($instrument['id'] ?? 0);
            if (isset($selectedInstrumentSet[$instrumentId])) {
                $selectedInstruments[$instrumentId] = $instrument;
            }
        }

        $sessionsByUserInstrument = [];
        foreach ($sessions as $session) {
            $userId = (int) ($session['user_id'] ?? 0);
            $instrumentId = (int) ($session['instrument_id'] ?? 0);
            $instrumentCode = (string) ($session['instrument_code'] ?? '');
            $current = $sessionsByUserInstrument[$userId][$instrumentId] ?? null;

            if ($current === null || $instrumentCode !== 'ticl_barratt' || $this->isPreferredTiclRankingSession($session, $current)) {
                $sessionsByUserInstrument[$userId][$instrumentId] = $session;
            }
        }

        $rankingSessions = [];
        foreach ($processUsers as $user) {
            if ((string) ($user['status'] ?? '') === 'cancelled') {
                continue;
            }

            foreach ($selectedInstruments as $instrumentId => $instrument) {
                $session = $sessionsByUserInstrument[(int) ($user['user_id'] ?? 0)][$instrumentId] ?? null;
                if ($session && (string) ($session['status'] ?? '') !== 'cancelled') {
                    $session['user_rut'] = (string) ($session['user_rut'] ?? $user['rut'] ?? '');
                    $session['user_name'] = (string) ($session['user_name'] ?? $user['name'] ?? '');
                    $rankingSessions[] = $session;
                    continue;
                }

                $rankingSessions[] = [
                    'id' => 0,
                    'user_id' => (int) ($user['user_id'] ?? 0),
                    'user_rut' => (string) ($user['rut'] ?? ''),
                    'user_name' => (string) ($user['name'] ?? ''),
                    'instrument_id' => $instrumentId,
                    'instrument_name' => (string) ($instrument['name'] ?? ''),
                    'instrument_code' => (string) ($instrument['code'] ?? ''),
                    'status' => 'missing',
                ];
            }
        }

        return $rankingSessions;
    }

    private function isPreferredTiclRankingSession(array $candidate, array $current): bool
    {
        $candidateScore = $this->ticlRankingSessionPriority($candidate);
        $currentScore = $this->ticlRankingSessionPriority($current);

        foreach ($candidateScore as $index => $value) {
            if ($value === $currentScore[$index]) {
                continue;
            }

            return $value > $currentScore[$index];
        }

        return false;
    }

    private function ticlRankingSessionPriority(array $session): array
    {
        $status = (string) ($session['status'] ?? '');
        $answersCount = max(0, (int) ($session['answers_count'] ?? 0));
        $completed = $status === 'completed' ? 1 : 0;
        $validCompleted = $completed === 1 && $answersCount >= 30 ? 1 : 0;
        $timestamp = strtotime((string) ($session['completed_at'] ?? $session['updated_at'] ?? $session['created_at'] ?? '')) ?: 0;

        return [$validCompleted, $completed, $answersCount, $timestamp, (int) ($session['id'] ?? 0)];
    }

    private function processRankingForDashboard(array $processUsers, array $sessions, array $instruments, array $selectedInstrumentIds, array $rankingConfig, array &$summaryCache, bool $preloadSummaries = true): array
    {
        $rankingSessions = $this->processRankingSessions($processUsers, $sessions, $instruments, $selectedInstrumentIds);
        if ($preloadSummaries) {
            $missingSummaryIds = [];
            foreach ($rankingSessions as $session) {
                $sessionId = (int) ($session['id'] ?? 0);
                $instrumentCode = (string) ($session['instrument_code'] ?? '');
                if (
                    $sessionId > 0
                    && in_array((string) ($session['status'] ?? ''), ['completed', 'expired'], true)
                    && in_array($instrumentCode, ['ipip_16pf', 'cag_wonderlic', 'cag', 'ticl_barratt'], true)
                    && !array_key_exists($sessionId, $summaryCache)
                ) {
                    $missingSummaryIds[] = $sessionId;
                }
            }
            if ($missingSummaryIds) {
                $summaryCache += $this->sessions->summariesForSessions($missingSummaryIds);
            }
        }

        return $this->progressRankingSummary->build(
            $rankingSessions,
            function (int $sessionId, array $session) use (&$summaryCache, $preloadSummaries): array {
                return $preloadSummaries
                    ? $this->rankingSessionSummary($sessionId, $session, $summaryCache)
                    : $this->rankingSessionSummaryLazy($sessionId, $session);
            },
            $rankingConfig
        );
    }

    private function completeProcessRanking(array $processes, array $fieldFilters, array $rankingConfig): array
    {
        $rows = [];
        $warnings = [];
        $usersTotal = 0;
        $sessionsTotal = 0;
        $instruments = $this->processes->activeInstruments();

        foreach ($processes as $process) {
            $processId = (int) ($process['id'] ?? 0);
            if ($processId <= 0) {
                continue;
            }

            $processUsers = $this->filterProcessUsersByFields($this->processes->processUsers($processId), $fieldFilters);
            $configHash = hash('sha256', json_encode($rankingConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (!$fieldFilters) {
                $snapshot = $this->processes->rankingSnapshot($processId, $configHash);
                if ($snapshot) {
                    foreach ($snapshot['rows'] as $row) {
                        $rows[] = ['Proceso' => trim((string) ($process['name'] ?? 'Proceso')), 'Codigo proceso' => trim((string) ($process['code'] ?? ''))] + $row;
                    }
                    foreach ($snapshot['warnings'] as $warning) {
                        $warnings[] = ['Proceso' => trim((string) ($process['name'] ?? 'Proceso')), 'Codigo proceso' => trim((string) ($process['code'] ?? ''))] + $warning;
                    }
                    $usersTotal += (int) $snapshot['users_total'];
                    $sessionsTotal += (int) $snapshot['sessions_total'];
                    continue;
                }
            }
            $sessions = $this->processes->processDashboardSessions($processId);
            $selectedInstrumentIds = $this->processes->selectedInstrumentIds($processId);
            $summaryCache = [];
            $ranking = $this->processRankingForDashboard($processUsers, $sessions, $instruments, $selectedInstrumentIds, $rankingConfig, $summaryCache, true);
            $rankingSessions = $this->processRankingSessions($processUsers, $sessions, $instruments, $selectedInstrumentIds);
            $processLabel = trim((string) ($process['name'] ?? 'Proceso'));
            $processCode = trim((string) ($process['code'] ?? ''));

            foreach (is_array($ranking['rows'] ?? null) ? $ranking['rows'] : [] as $row) {
                unset($row['Ranking']);
                $rows[] = ['Proceso' => $processLabel, 'Codigo proceso' => $processCode] + $row;
            }

            foreach (is_array($ranking['warnings'] ?? null) ? $ranking['warnings'] : [] as $warning) {
                $warnings[] = [
                    'Proceso' => $processLabel,
                    'Codigo proceso' => $processCode,
                    'rut' => (string) ($warning['rut'] ?? ''),
                    'name' => (string) ($warning['name'] ?? ''),
                    'warning' => (string) ($warning['warning'] ?? 'Datos insuficientes.'),
                ];
            }
            if (!$fieldFilters) {
                $this->processes->saveRankingSnapshot(
                    $processId,
                    $configHash,
                    $ranking,
                    count(array_filter($processUsers, static fn(array $user): bool => (string) ($user['status'] ?? '') !== 'cancelled')),
                    count(array_filter($rankingSessions, static fn(array $session): bool => (int) ($session['id'] ?? 0) > 0))
                );
            }

            $usersTotal += count(array_filter($processUsers, static fn(array $user): bool => (string) ($user['status'] ?? '') !== 'cancelled'));
            $sessionsTotal += count(array_filter($rankingSessions, static fn(array $session): bool => (int) ($session['id'] ?? 0) > 0));
            unset($summaryCache, $ranking, $rankingSessions, $sessions, $processUsers);
        }

        usort($rows, static function (array $a, array $b): int {
            $score = ((float) ($b['Puntaje Final'] ?? 0)) <=> ((float) ($a['Puntaje Final'] ?? 0));
            if ($score !== 0) {
                return $score;
            }

            $process = strcmp((string) ($a['Proceso'] ?? ''), (string) ($b['Proceso'] ?? ''));
            return $process !== 0 ? $process : strcmp((string) ($a['Nombre completo'] ?? ''), (string) ($b['Nombre completo'] ?? ''));
        });

        $rankedRows = [];
        $rank = 0;
        $previousScore = null;
        foreach ($rows as $index => $row) {
            $finalScore = (float) ($row['Puntaje Final'] ?? 0);
            if ($previousScore === null || $finalScore !== $previousScore) {
                $rank = $index + 1;
                $previousScore = $finalScore;
            }
            $rankedRows[] = ['Ranking' => $rank] + $row;
        }

        return [
            'rows' => $rankedRows,
            'warnings' => $warnings,
            'config' => $rankingConfig,
            'summary' => $this->progressRankingSummary->dashboardSummary(['rows' => $rankedRows, 'warnings' => $warnings]),
            'meta' => [
                'users_total' => $usersTotal,
                'sessions_total' => $sessionsTotal,
            ],
        ];
    }

    private function rankingAllBackQueryString(): string
    {
        $query = $_GET;
        unset($query['process_id']);

        return http_build_query($query);
    }

    private function rankingInstrumentStatusByRut(array $processUsers, array $sessions, array $instruments, array $selectedInstrumentIds): array
    {
        $selectedInstrumentSet = array_flip(array_map('intval', $selectedInstrumentIds));
        $columns = [];
        $selectedRankingInstruments = [];
        foreach ($instruments as $instrument) {
            $instrumentId = (int) ($instrument['id'] ?? 0);
            if (!isset($selectedInstrumentSet[$instrumentId])) {
                continue;
            }

            $column = $this->rankingInstrumentColumn((string) ($instrument['code'] ?? ''));
            if ($column === null) {
                continue;
            }

            $columns[$column['key']] = $column;
            $selectedRankingInstruments[$instrumentId] = $column['key'];
        }

        $sessionStatusByUserInstrument = [];
        foreach ($sessions as $session) {
            $instrumentId = (int) ($session['instrument_id'] ?? 0);
            if (!isset($selectedRankingInstruments[$instrumentId])) {
                continue;
            }

            $userId = (int) ($session['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $columnKey = $selectedRankingInstruments[$instrumentId];
            $current = $sessionStatusByUserInstrument[$userId][$columnKey] ?? null;
            $next = $this->rankingInstrumentStatusPayload((string) ($session['status'] ?? 'missing'));
            if ($current === null || (int) $next['priority'] > (int) $current['priority']) {
                $sessionStatusByUserInstrument[$userId][$columnKey] = $next;
            }
        }

        $statusesByRut = [];
        foreach ($processUsers as $user) {
            if ((string) ($user['status'] ?? '') === 'cancelled') {
                continue;
            }

            $rut = (string) ($user['rut'] ?? '');
            if ($rut === '') {
                continue;
            }

            $userId = (int) ($user['user_id'] ?? 0);
            foreach ($columns as $columnKey => $column) {
                $statusesByRut[$rut][$columnKey] = $sessionStatusByUserInstrument[$userId][$columnKey]
                    ?? $this->rankingInstrumentStatusPayload('missing');
            }
        }

        return [
            'columns' => $columns,
            'statuses_by_rut' => $statusesByRut,
        ];
    }

    private function rankingInstrumentColumn(string $instrumentCode): ?array
    {
        if ($instrumentCode === 'ipip_16pf') {
            return ['key' => 'ipip_16pf', 'label' => 'IPIP-16PF'];
        }

        if (in_array($instrumentCode, ['cag_wonderlic', 'cag'], true)) {
            return ['key' => 'cag_wonderlic', 'label' => 'CAG / Wonderlic'];
        }

        if ($instrumentCode === 'ticl_barratt') {
            return ['key' => 'ticl_barratt', 'label' => 'TICL'];
        }

        return null;
    }

    private function rankingInstrumentStatusPayload(string $status): array
    {
        if (in_array($status, ['completed', 'expired'], true)) {
            return ['label' => 'Realizado', 'class' => 'text-bg-success', 'priority' => 4];
        }

        if ($status === 'in_progress') {
            return ['label' => 'En curso', 'class' => 'text-bg-info', 'priority' => 3];
        }

        if ($status === 'cancelled') {
            return ['label' => 'Cancelado', 'class' => 'text-bg-secondary', 'priority' => 1];
        }

        return ['label' => 'Pendiente', 'class' => 'text-bg-warning', 'priority' => 2];
    }

    private function rankingSessionSummary(int $sessionId, array $session, array &$summaryCache): array
    {
        if ($sessionId <= 0 || !in_array((string) ($session['status'] ?? ''), ['completed', 'expired'], true)) {
            return [];
        }

        $summary = $summaryCache[$sessionId] ?? [];
        if (!$summary && (string) ($session['status'] ?? '') === 'expired') {
            $summary = $this->sessions->complete($sessionId, [], 'expired');
            $summaryCache[$sessionId] = $summary;
        }

        return $summary;
    }

    private function rankingSessionSummaryLazy(int $sessionId, array $session): array
    {
        if ($sessionId <= 0 || !in_array((string) ($session['status'] ?? ''), ['completed', 'expired'], true)) {
            return [];
        }

        $summary = $this->sessions->summaryForSession($sessionId);
        if (!$summary && (string) ($session['status'] ?? '') === 'expired') {
            $summary = $this->sessions->complete($sessionId, [], 'expired');
        }

        return $summary;
    }

    private function processProgressStats(array $processUsers, array $sessions, array $instruments, array $selectedInstrumentIds): array
    {
        $selectedInstrumentSet = array_flip(array_map('intval', $selectedInstrumentIds));
        $requiredInstrumentIds = [];
        foreach ($instruments as $instrument) {
            $instrumentId = (int) ($instrument['id'] ?? 0);
            if (isset($selectedInstrumentSet[$instrumentId])) {
                $requiredInstrumentIds[] = $instrumentId;
            }
        }

        $requiredCount = count($requiredInstrumentIds);
        $sessionsByUserInstrument = [];
        $activityTotals = [
            'activity_events_total' => 0,
            'activity_attention_total' => 0,
            'activity_risk_total' => 0,
        ];
        foreach ($sessions as $session) {
            $instrumentId = (int) ($session['instrument_id'] ?? 0);
            if (!isset($selectedInstrumentSet[$instrumentId]) || (string) ($session['status'] ?? '') === 'cancelled') {
                continue;
            }
            $sessionsByUserInstrument[(int) ($session['user_id'] ?? 0)][$instrumentId] = $session;
            $activityTotals['activity_events_total'] += (int) ($session['activity_events_total'] ?? 0);
            $activityTotals['activity_attention_total'] += (int) ($session['activity_attention_total'] ?? 0);
            $activityTotals['activity_risk_total'] += (int) ($session['activity_risk_total'] ?? 0);
        }

        $stats = [
            'users_total' => 0,
            'evaluated' => 0,
            'in_progress' => 0,
            'pending' => 0,
            'sessions_total' => 0,
            'sessions_finished' => 0,
            'activity_events_total' => $activityTotals['activity_events_total'],
            'activity_attention_total' => $activityTotals['activity_attention_total'],
            'activity_risk_total' => $activityTotals['activity_risk_total'],
        ];

        foreach ($processUsers as $user) {
            if ((string) ($user['status'] ?? '') === 'cancelled') {
                continue;
            }

            $stats['users_total']++;
            $stats['sessions_total'] += $requiredCount;

            $finished = 0;
            $hasProgress = false;
            foreach ($requiredInstrumentIds as $instrumentId) {
                $session = $sessionsByUserInstrument[(int) ($user['user_id'] ?? 0)][$instrumentId] ?? null;
                $status = (string) ($session['status'] ?? 'missing');
                if (in_array($status, ['completed', 'expired'], true)) {
                    $finished++;
                    $hasProgress = true;
                    continue;
                }
                if ($status === 'in_progress') {
                    $hasProgress = true;
                }
            }

            $stats['sessions_finished'] += $finished;
            if ($requiredCount > 0 && $finished >= $requiredCount) {
                $stats['evaluated']++;
            } elseif ($hasProgress) {
                $stats['in_progress']++;
            } else {
                $stats['pending']++;
            }
        }

        return $stats;
    }

    private function processDashboardChartData(array $processRows, array $trend, array $overall): array
    {
        $usersTotal = max(0, (int) ($overall['users_total'] ?? 0));
        $evaluated = max(0, (int) ($overall['evaluated'] ?? 0));
        $inProgress = max(0, (int) ($overall['in_progress'] ?? 0));
        $pending = max(0, (int) ($overall['pending'] ?? 0));
        $chartRows = array_slice($processRows, 0, 8);

        return [
            'processLabels' => array_map(static fn(array $row): string => (string) ($row['name'] ?? 'Proceso'), $chartRows),
            'evaluated' => array_map(static fn(array $row): int => (int) ($row['evaluated'] ?? 0), $chartRows),
            'inProgress' => array_map(static fn(array $row): int => (int) ($row['in_progress'] ?? 0), $chartRows),
            'pending' => array_map(static fn(array $row): int => (int) ($row['pending'] ?? 0), $chartRows),
            'trendLabels' => array_map(static fn(array $point): string => (string) ($point['label'] ?? ''), $trend),
            'trendCounts' => array_map(static fn(array $point): int => (int) ($point['count'] ?? 0), $trend),
            'distribution' => [
                'evaluated' => $usersTotal > 0 ? $evaluated : 0,
                'inProgress' => $usersTotal > 0 ? $inProgress : 0,
                'pending' => $usersTotal > 0 ? $pending : 0,
            ],
        ];
    }

    private function collectActiveProcessAssignments(array &$activeProcessesByUser, array $processUsers, array $process): void
    {
        $processId = (int) ($process['id'] ?? 0);
        if ($processId <= 0) {
            return;
        }

        foreach ($processUsers as $user) {
            if ((string) ($user['status'] ?? '') === 'cancelled') {
                continue;
            }

            $userId = (int) ($user['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $activeProcessesByUser[$userId]['user'] = [
                'name' => (string) ($user['name'] ?? 'Usuario'),
                'rut' => (string) ($user['rut'] ?? ''),
            ];
            $activeProcessesByUser[$userId]['processes'][$processId] = [
                'name' => (string) ($process['name'] ?? 'Proceso'),
                'code' => (string) ($process['code'] ?? ''),
            ];
        }
    }

    private function duplicateProcessAssignmentsSummary(array $activeProcessesByUser): array
    {
        $duplicates = [];
        foreach ($activeProcessesByUser as $userId => $data) {
            $processes = $data['processes'] ?? [];
            if (count($processes) <= 1) {
                continue;
            }

            $duplicates[] = [
                'user_id' => (int) $userId,
                'user' => $data['user'] ?? ['name' => 'Usuario', 'rut' => ''],
                'processes' => array_values($processes),
            ];
        }

        return [
            'total' => count($duplicates),
            'examples' => array_slice($duplicates, 0, 5),
        ];
    }

    private function collectUniqueProcessProgress(array &$uniqueUserProgress, array $processUsers, array $sessions, array $instruments, array $selectedInstrumentIds): void
    {
        $selectedInstrumentSet = array_flip(array_map('intval', $selectedInstrumentIds));
        $requiredInstrumentIds = [];
        foreach ($instruments as $instrument) {
            $instrumentId = (int) ($instrument['id'] ?? 0);
            if (isset($selectedInstrumentSet[$instrumentId])) {
                $requiredInstrumentIds[] = $instrumentId;
            }
        }

        $sessionsByUserInstrument = [];
        foreach ($sessions as $session) {
            $instrumentId = (int) ($session['instrument_id'] ?? 0);
            if (!isset($selectedInstrumentSet[$instrumentId]) || (string) ($session['status'] ?? '') === 'cancelled') {
                continue;
            }

            $sessionsByUserInstrument[(int) ($session['user_id'] ?? 0)][$instrumentId] = $session;
        }

        foreach ($processUsers as $user) {
            if ((string) ($user['status'] ?? '') === 'cancelled') {
                continue;
            }

            $userId = (int) ($user['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            if (!isset($uniqueUserProgress[$userId])) {
                $uniqueUserProgress[$userId] = [
                    'required' => 0,
                    'finished' => 0,
                    'has_progress' => false,
                ];
            }

            $uniqueUserProgress[$userId]['required'] += count($requiredInstrumentIds);
            foreach ($requiredInstrumentIds as $instrumentId) {
                $session = $sessionsByUserInstrument[$userId][$instrumentId] ?? null;
                $status = (string) ($session['status'] ?? 'missing');
                if (in_array($status, ['completed', 'expired'], true)) {
                    $uniqueUserProgress[$userId]['finished']++;
                    $uniqueUserProgress[$userId]['has_progress'] = true;
                    continue;
                }

                if ($status === 'in_progress') {
                    $uniqueUserProgress[$userId]['has_progress'] = true;
                }
            }
        }
    }

    private function uniqueDashboardProgressStats(array $uniqueUserProgress): array
    {
        $stats = [
            'users_total' => count($uniqueUserProgress),
            'evaluated' => 0,
            'in_progress' => 0,
            'pending' => 0,
        ];

        foreach ($uniqueUserProgress as $progress) {
            $required = (int) ($progress['required'] ?? 0);
            $finished = (int) ($progress['finished'] ?? 0);
            $hasProgress = !empty($progress['has_progress']);

            if ($required > 0 && $finished >= $required) {
                $stats['evaluated']++;
            } elseif ($hasProgress) {
                $stats['in_progress']++;
            } else {
                $stats['pending']++;
            }
        }

        return $stats;
    }

    private function emptyTrendDays(int $days): array
    {
        $trend = [];
        $start = new DateTimeImmutable('-' . max(0, $days - 1) . ' days');
        for ($index = 0; $index < $days; $index++) {
            $date = $start->modify('+' . $index . ' days')->format('Y-m-d');
            $trend[$date] = [
                'date' => $date,
                'label' => $start->modify('+' . $index . ' days')->format('d/m'),
                'count' => 0,
            ];
        }

        return $trend;
    }

    private function processCompletedTrend(array $sessions, array $selectedInstrumentIds, array $dates): array
    {
        $selectedInstrumentSet = array_flip(array_map('intval', $selectedInstrumentIds));
        $dateSet = array_flip($dates);
        $trend = array_fill_keys($dates, 0);

        foreach ($sessions as $session) {
            if (!isset($selectedInstrumentSet[(int) ($session['instrument_id'] ?? 0)])) {
                continue;
            }
            if (!in_array((string) ($session['status'] ?? ''), ['completed', 'expired'], true)) {
                continue;
            }

            $date = substr((string) ($session['completed_at'] ?? $session['updated_at'] ?? ''), 0, 10);
            if (isset($dateSet[$date])) {
                $trend[$date]++;
            }
        }

        return $trend;
    }

    private function mergeProcessDashboardFieldOptions(array $options, array $users, array $fields): array
    {
        $allowed = [];
        foreach ($fields as $field) {
            $allowed[(string) ($field['field_key'] ?? '')] = true;
            $allowed[(string) ($field['id'] ?? '')] = true;
        }

        foreach ($users as $user) {
            foreach (($user['dynamic_fields'] ?? []) as $key => $value) {
                $fieldKey = (string) $key;
                $fieldValue = trim((string) $value);
                if ($fieldValue === '' || !isset($allowed[$fieldKey])) {
                    continue;
                }
                $options[$fieldKey][$fieldValue] = $fieldValue;
            }
        }

        foreach ($options as &$values) {
            ksort($values);
        }
        unset($values);

        return $options;
    }

    private function filterProcessUsersByFields(array $users, array $fieldFilters): array
    {
        $cleanFilters = [];
        foreach ($fieldFilters as $key => $value) {
            $fieldValue = trim((string) $value);
            if ($fieldValue !== '') {
                $cleanFilters[(string) $key] = $fieldValue;
            }
        }

        if (!$cleanFilters) {
            return $users;
        }

        return array_values(array_filter($users, static function (array $user) use ($cleanFilters): bool {
            $dynamicFields = $user['dynamic_fields'] ?? [];
            foreach ($cleanFilters as $key => $value) {
                if (trim((string) ($dynamicFields[$key] ?? '')) !== $value) {
                    return false;
                }
            }

            return true;
        }));
    }
}
