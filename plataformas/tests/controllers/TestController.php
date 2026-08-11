<?php
declare(strict_types=1);

final class TestController extends Controller
{
    private TestInstrumentModel $tests;
    private TestSessionModel $sessions;
    private TestSettingsModel $settings;
    private RiasecRecommendationService $riasecRecommendations;
    private ProgressRankingSummaryService $progressRankingSummary;
    private InterviewProcessModel $interviews;
    private TestMediaEvidenceModel $mediaEvidence;

    public function __construct(?Template $view = null, ?TestInstrumentModel $tests = null, ?TestSessionModel $sessions = null, ?TestSettingsModel $settings = null, ?RiasecRecommendationService $riasecRecommendations = null, ?ProgressRankingSummaryService $progressRankingSummary = null, ?InterviewProcessModel $interviews = null, ?TestMediaEvidenceModel $mediaEvidence = null)
    {
        parent::__construct($view);
        $this->tests = $tests ?: new TestInstrumentModel();
        $this->sessions = $sessions ?: new TestSessionModel();
        $this->settings = $settings ?: new TestSettingsModel();
        $this->riasecRecommendations = $riasecRecommendations ?: new RiasecRecommendationService();
        $this->progressRankingSummary = $progressRankingSummary ?: new ProgressRankingSummaryService();
        $this->interviews = $interviews ?: new InterviewProcessModel();
        $this->mediaEvidence = $mediaEvidence ?: new TestMediaEvidenceModel();
    }

    public function index(): void
    {
        require_permission('manage_tests');

        $this->render('tests/index', [
            'title' => 'Evaluaciones | Metricatest',
            'currentPage' => 'tests',
            'tests' => $this->tests->all(),
            'categories' => TestInstrumentModel::CATEGORIES,
            'statuses' => TestInstrumentModel::STATUSES,
            'itemTypes' => TestInstrumentModel::ITEM_TYPES,
            'sessions' => $this->sessions->allSessions(),
            'hasFinishedSessions' => $this->sessions->hasFinishedActiveSessions(),
        ]);
    }

    public function progress(): void
    {
        require_permission('manage_tests');

        $dashboardFields = array_values(array_filter(
            $this->sessions->dashboardUserFields(),
            static fn(array $field): bool => (string) ($field['field_key'] ?? '') !== 'edad'
        ));
        $dashboardFilters = [
            'fields' => is_array($_GET['fields'] ?? null) ? $_GET['fields'] : [],
        ];
        $activeTests = $this->sessions->activeInstruments();
        $activeTestIds = array_flip(array_map(static fn(array $test): int => (int) $test['id'], $activeTests));
        $dashboardSessions = array_values(array_filter(
            $this->sessions->dashboardSessions($dashboardFilters, $dashboardFields),
            static fn(array $session): bool => isset($activeTestIds[(int) ($session['instrument_id'] ?? 0)])
        ));

        $this->render('tests/progress', [
            'title' => 'Estado Avance | Metricatest',
            'currentPage' => 'tests.progress',
            'tests' => $activeTests,
            'sessions' => $dashboardSessions,
            'dashboardFields' => $dashboardFields,
            'dashboardFilters' => $dashboardFilters,
            'dashboardOptions' => $this->sessions->dashboardFilterOptions($dashboardFields),
            'dashboardSummary' => $this->sessions->dashboardSummary($dashboardSessions),
            'hasFinishedSessions' => $this->sessions->hasFinishedActiveSessions(),
        ]);
    }

    public function progressExport(): void
    {
        require_permission('manage_tests');

        $rankingConfig = $this->progressRankingSummary->activeConfig($this->settings->rankingConfig());
        $dashboardFields = array_values(array_filter(
            $this->sessions->dashboardUserFields(),
            static fn(array $field): bool => (string) ($field['field_key'] ?? '') !== 'edad'
        ));
        $dashboardFilters = [
            'fields' => is_array($_GET['fields'] ?? null) ? $_GET['fields'] : [],
        ];
        $activeTests = $this->sessions->activeInstruments();
        $activeTestIds = array_flip(array_map(static fn(array $test): int => (int) $test['id'], $activeTests));
        $finishedSessions = array_values(array_filter(
            $this->sessions->dashboardSessions($dashboardFilters, $dashboardFields),
            static fn(array $session): bool => isset($activeTestIds[(int) ($session['instrument_id'] ?? 0)])
                && in_array((string) ($session['status'] ?? ''), ['completed', 'expired'], true)
        ));

        if (!$finishedSessions) {
            flash('warning', 'No hay evaluaciones terminadas para exportar.');
            redirect(route_url('tests.progress'));
        }

        $this->downloadProgressResultsXlsx($finishedSessions, $rankingConfig);
    }

    public function instrumentResultExport(): void
    {
        require_permission('manage_tests');

        $instrumentId = request_secure_id('test');
        $instrument = $this->tests->find($instrumentId);
        if (!$instrument) {
            platform_error(404, 'Instrumento no encontrado.', [
                'chips' => ['Evaluacion', 'Excel'],
            ]);
        }

        $finishedSessions = $this->sessions->finishedDashboardSessionsForInstrument($instrumentId);

        if (!$finishedSessions) {
            flash('warning', 'No hay resultados terminados para exportar en esta evaluacion.');
            redirect(route_url('tests'));
        }

        $this->downloadInstrumentResultsXlsx($instrument, $finishedSessions);
    }

    public function instrumentAnswersExport(): void
    {
        require_permission('manage_tests');

        $instrumentId = request_secure_id('test');
        $instrument = $this->tests->find($instrumentId);
        if (!$instrument) {
            platform_error(404, 'Instrumento no encontrado.', [
                'chips' => ['Evaluacion', 'Excel'],
            ]);
        }

        $dashboardFields = $this->sessions->dashboardUserFields();
        $finishedSessions = $this->sessions->finishedDashboardSessionsForInstrument($instrumentId, $dashboardFields);

        if (!$finishedSessions) {
            flash('warning', 'No hay respuestas terminadas para exportar en esta evaluacion.');
            redirect(route_url('tests'));
        }

        $this->downloadInstrumentAnswersXlsx($instrument, $finishedSessions, $dashboardFields);
    }

    public function progressRanking(): void
    {
        require_permission('manage_tests');

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
            redirect(route_url('tests.progress-ranking'));
        }

        $officialRankingConfig = $this->progressRankingSummary->officialConfig();
        $rankingPresets = $this->settings->rankingPresetOptions($officialRankingConfig);
        $selectedRankingPreset = $this->rankingPresetFromRequest($officialRankingConfig);
        $rankingConfig = $selectedRankingPreset
            ? $this->progressRankingSummary->activeConfig($selectedRankingPreset['config'])
            : $this->progressRankingSummary->activeConfig($this->settings->rankingConfig());
        $dashboardFields = array_values(array_filter(
            $this->sessions->dashboardUserFields(),
            static fn(array $field): bool => (string) ($field['field_key'] ?? '') !== 'edad'
        ));
        $dashboardFilters = [
            'fields' => is_array($_GET['fields'] ?? null) ? $_GET['fields'] : [],
        ];
        $activeTests = $this->sessions->activeInstruments();
        $activeTestIds = array_flip(array_map(static fn(array $test): int => (int) $test['id'], $activeTests));
        $finishedSessions = array_values(array_filter(
            $this->sessions->dashboardSessions($dashboardFilters, $dashboardFields),
            static fn(array $session): bool => isset($activeTestIds[(int) ($session['instrument_id'] ?? 0)])
                && in_array((string) ($session['status'] ?? ''), ['completed', 'expired'], true)
        ));

        $ranking = $this->progressRankingSummary->build(
            $finishedSessions,
            function (int $sessionId, array $session): array {
                $summary = $this->sessions->summaryForSession($sessionId);
                if (!$summary && (string) ($session['status'] ?? '') === 'expired') {
                    $summary = $this->sessions->complete($sessionId, [], 'expired');
                }

                return $summary;
            },
            $rankingConfig
        );

        $this->render('tests/progress_ranking', [
            'title' => 'Ranking Resumen | Metricatest',
            'currentPage' => 'tests.progress',
            'ranking' => $ranking,
            'dashboardFields' => $dashboardFields,
            'dashboardFilters' => $dashboardFilters,
            'dashboardOptions' => $this->sessions->dashboardFilterOptions($dashboardFields),
            'hasFinishedSessions' => $finishedSessions !== [],
            'rankingConfig' => $rankingConfig,
            'rankingOfficialConfig' => $officialRankingConfig,
            'rankingUsesOfficialConfig' => $this->progressRankingSummary->isOfficialConfig($rankingConfig),
            'rankingPresets' => $rankingPresets,
            'rankingSelectedPresetId' => (int) ($selectedRankingPreset['id'] ?? -1),
            'canManageRankingPresets' => has_permission('manage_ranking_presets'),
            'rankingPresetsStorageReady' => $this->settings->hasRankingPresetsStorage(),
            'rankingSummary' => $this->progressRankingSummary->dashboardSummary($ranking),
            'classificationCounts' => $this->progressRankingSummary->classificationCounts($ranking),
        ]);
    }

    public function rankingPresetAction(): void
    {
        require_auth();
        require_permission('manage_ranking_presets');
        verify_csrf();

        $redirectTo = $this->safeRedirectPath((string) ($_POST['redirect_to'] ?? route_url('tests.progress-ranking')));
        if (!$this->settings->hasRankingPresetsStorage()) {
            flash('danger', 'La tabla de configuraciones de ranking aun no existe. Aplica la migracion antes de guardar presets.');
            redirect($redirectTo);
        }

        $action = (string) ($_POST['ranking_preset_action'] ?? '');
        $presetId = max(0, (int) ($_POST['ranking_preset_id'] ?? 0));
        $name = (string) ($_POST['ranking_preset_name'] ?? '');
        $userId = (int) (current_user()['id'] ?? 0);

        try {
            if ($action === 'create') {
                $config = $this->progressRankingSummary->configFromRequest($_POST['ranking_config'] ?? []);
                $newPresetId = $this->settings->createRankingPreset($name, $config, $userId);
                $this->settings->setRankingConfig($config);
                flash('success', 'Configuracion guardada correctamente.');
                $redirectTo = $this->redirectWithPreset($redirectTo, (int) $newPresetId);
            } elseif ($action === 'update') {
                $config = $this->progressRankingSummary->configFromRequest($_POST['ranking_config'] ?? []);
                $this->settings->updateRankingPreset($presetId, $name, $config, $userId);
                $this->settings->setRankingConfig($config);
                flash('success', 'Configuracion actualizada correctamente.');
                $redirectTo = $this->redirectWithPreset($redirectTo, $presetId);
            } elseif ($action === 'delete') {
                $this->settings->deleteRankingPreset($presetId);
                flash('success', 'Configuracion eliminada correctamente.');
                $redirectTo = $this->redirectWithPreset($redirectTo, 0);
            } else {
                flash('warning', 'Accion de configuracion no reconocida.');
            }
        } catch (Throwable $exception) {
            flash('danger', $exception->getMessage());
        }

        redirect($redirectTo);
    }

    public function assign(): void
    {
        require_permission('assign_tests');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $instrumentIds = $_POST['instrument_ids'] ?? [];
            $userIds = $_POST['user_ids'] ?? [];

            if (!is_array($instrumentIds)) {
                $instrumentIds = [];
            }
            if (!is_array($userIds)) {
                $userIds = [];
            }

            if (!$instrumentIds || !$userIds) {
                flash('danger', 'Selecciona al menos una evaluacion activa y un usuario.');
            } else {
                $result = $this->sessions->assignMany($instrumentIds, $userIds, (int) current_user()['id']);
                if ($result['created'] > 0) {
                    $message = sprintf(
                        'Asignacion masiva completada: %d nuevas y %d ya existentes.',
                        (int) $result['created'],
                        (int) $result['existing']
                    );
                    flash('success', $message);
                } elseif ($result['existing'] > 0) {
                    flash('warning', 'No se crearon nuevas asignaciones porque ya existian sesiones activas para la seleccion.');
                } else {
                    flash('danger', 'No se pudo asignar. Verifica que las evaluaciones y usuarios seleccionados esten activos.');
                }
                redirect($this->testsIndexUrl());
            }
        }

        $this->render('tests/assign', [
            'title' => 'Asignar evaluacion | Metricatest',
            'currentPage' => 'tests.assign',
            'instruments' => $this->sessions->activeInstruments(),
            'users' => $this->sessions->assignableUsersForDemo(),
            'userFields' => $this->sessions->assignableUserFields(),
        ]);
    }

    public function mine(): void
    {
        require_auth();
        $sessions = $this->sessions->sessionsForUser((int) current_user()['id']);
        $pendingAutoStartSession = $this->pendingAutoStartSession($sessions);
        $assignedTestsFinalized = $this->assignedTestsFinalized($sessions);
        $interviewAppointments = $this->interviews->appointmentsForCandidate((int) current_user()['id']);

        $this->render('tests/my', [
            'title' => 'Mis evaluaciones | Metricatest',
            'currentPage' => 'my-tests',
            'sessions' => $sessions,
            'interviewAppointments' => $interviewAppointments,
            'pendingAutoStartSession' => $pendingAutoStartSession,
            'assignedTestsFinalized' => $assignedTestsFinalized,
            'evaluationMessages' => $this->settings->evaluationMessages(),
            'serverNow' => time(),
        ]);
    }

    public function mineStatus(): void
    {
        require_auth();
        $sessions = $this->sessions->sessionsForUser((int) current_user()['id']);

        $this->jsonResponse([
            'ok' => true,
            'assigned_tests_finalized' => $this->assignedTestsFinalized($sessions),
            'server_now' => time(),
        ]);
    }

    public function settings(): void
    {
        require_permission('manage_platform_settings');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $defaults = TestSettingsModel::DEFAULTS;
            $data = [];

            foreach (array_keys($defaults) as $key) {
                $value = trim((string) ($_POST[$key] ?? $defaults[$key]));
                $data[$key] = $value !== '' ? $value : $defaults[$key];
            }

            $this->settings->setMany($data);
            flash('success', 'Configuracion de Evaluaciones guardada.');
            redirect(route_url('tests.settings'));
        }

        $this->render('tests/settings', [
            'title' => 'Configuracion Evaluaciones | Metricatest',
            'currentPage' => 'tests.settings',
            'settings' => $this->settings->evaluationMessages(),
            'defaults' => TestSettingsModel::DEFAULTS,
        ]);
    }

    public function cancelSession(): void
    {
        require_permission('assign_tests');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect($this->testsIndexUrl());
        }

        verify_csrf();
        $id = request_secure_id('test_session');

        if ($this->sessions->cancelAssignment($id)) {
            flash('success', 'Asignacion cancelada correctamente.');
        } else {
            flash('warning', 'La asignacion no se pudo cancelar porque ya fue completada, cancelada o no existe.');
        }

        redirect($this->testsIndexUrl());
    }

    public function cancelAllSessions(): void
    {
        require_permission('assign_tests');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect($this->testsIndexUrl());
        }

        verify_csrf();
        $cancelled = $this->sessions->cancelAllAssignments();

        if ($cancelled > 0) {
            flash('success', sprintf('Se cancelaron %d asignaciones activas.', $cancelled));
        } else {
            flash('warning', 'No habia asignaciones activas para cancelar.');
        }

        redirect($this->testsIndexUrl());
    }

    public function clearAllResults(): void
    {
        require_permission('manage_tests');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(route_url('tests'));
        }

        verify_csrf();
        $deleted = $this->sessions->clearAllResultsAndAssignments();

        if ($deleted > 0) {
            flash('success', sprintf('Se limpiaron %d evaluaciones realizadas/asignadas. Los instrumentos y preguntas se conservaron.', $deleted));
        } else {
            flash('warning', 'No habia resultados ni asignaciones para limpiar.');
        }

        redirect(route_url('tests'));
    }

    public function take(): void
    {
        require_auth();

        $id = request_secure_id('test_session');
        $userId = (int) current_user()['id'];
        $session = $this->sessions->findForUser($id, (int) current_user()['id']);

        if (!$session) {
            platform_error(404, 'Evaluacion no encontrada.', [
                'chips' => ['Evaluacion', 'Sesion'],
            ]);
        }

        if ($session['status'] === 'completed') {
            redirect(route_url('my-tests'));
        }

        if (in_array($session['status'], ['cancelled', 'expired'], true)) {
            flash('warning', 'Esta evaluacion ya no esta disponible para responder.');
            redirect(route_url('my-tests'));
        }

        $pendingAutoStartSession = $this->pendingAutoStartSession($this->sessions->sessionsForUser($userId));
        if ($pendingAutoStartSession && (int) ($pendingAutoStartSession['id'] ?? 0) !== $id) {
            flash('warning', sprintf(
                'Debes continuar con la evaluacion "%s" antes de responder otras evaluaciones.',
                (string) ($pendingAutoStartSession['instrument_name'] ?? 'obligatoria')
            ));
            redirect(route_url('test-session.take', (int) $pendingAutoStartSession['id']));
        }

        $availability = $session['process_availability'] ?? $this->sessions->availabilityForSession($session);
        if (empty($availability['allowed'])) {
            $this->handleUnavailableProcessSession($id, $session, $availability);
            return;
        }

        $originalStatus = (string) $session['status'];
        $this->sessions->start($id, (int) ($session['duration_minutes'] ?? 0));
        $session = $this->sessions->findForUser($id, $userId);
        if (!$session) {
            platform_error(404, 'Evaluacion no encontrada.', [
                'chips' => ['Evaluacion', 'Sesion'],
            ]);
        }
        $availability = $session['process_availability'] ?? $this->sessions->availabilityForSession($session);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sessions->logActivity($id, $userId, 'evaluation_opened', [
                'url_path' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '',
            ]);
            $this->sessions->logActivity(
                $id,
                $userId,
                $originalStatus === 'assigned' ? 'evaluation_started' : 'evaluation_reopened'
            );
        }

        $hasTimeLimit = (int) ($session['duration_minutes'] ?? 0) > 0;
        $remainingSeconds = null;
        if ($hasTimeLimit && !empty($session['expires_at'])) {
            $remainingSeconds = max(0, strtotime((string) $session['expires_at']) - time());
            if ($remainingSeconds <= 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sessions->complete($id, [], 'expired');
                flash('warning', 'El tiempo de esta evaluacion ha finalizado.');
                redirect(route_url('my-tests'));
            }
        }

        $items = $this->sessions->itemsForSession($id);
        $totalItems = count($items);
        $useBlocks = (int) ($session['use_blocks'] ?? 0) === 1 && (int) ($session['block_size'] ?? 0) > 0;
        $blockSize = $useBlocks ? max(1, (int) $session['block_size']) : max(1, $totalItems);
        $totalBlocks = $useBlocks ? max(1, (int) ceil($totalItems / $blockSize)) : 1;
        $requestedBlock = $_GET['block'] ?? $_POST['block'] ?? null;
        $currentBlock = $useBlocks ? max(1, min($totalBlocks, (int) ($requestedBlock ?? 1))) : 1;
        if ($useBlocks && $requestedBlock === null) {
            $firstMissingBlock = 1;
            $hasMissingAnswer = false;
            foreach ($items as $index => $item) {
                if (trim((string) ($item['answer_value'] ?? '')) === '') {
                    $firstMissingBlock = (int) floor($index / $blockSize) + 1;
                    $hasMissingAnswer = true;
                    break;
                }
            }
            $currentBlock = $hasMissingAnswer ? max(1, min($totalBlocks, $firstMissingBlock)) : $totalBlocks;
        }
        $blockOffset = ($currentBlock - 1) * $blockSize;
        $blockItems = array_slice($items, $blockOffset, $blockSize);
        $requireBlockCompletion = !$useBlocks || (int) ($session['require_block_completion'] ?? 1) === 1;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $answers = $_POST['answers'] ?? [];
            $rawPostAnswers = is_array($answers) ? $answers : [];
            $answerTrace = $this->answerSnapshotTrace($rawPostAnswers, $_POST['answers_snapshot_json'] ?? null);
            $answers = $this->mergeAnswerSnapshot($rawPostAnswers, $_POST['answers_snapshot_json'] ?? null);
            $action = $_POST['test_action'] ?? 'complete';

            $availability = $this->sessions->availabilityForSession($session);
            $isExitPauseAction = in_array((string) $action, ['save_exit', 'complete_expired'], true);
            if (empty($availability['allowed']) && !$isExitPauseAction) {
                if (in_array((string) ($availability['reason'] ?? ''), ['process_ended', 'closed_now'], true)) {
                    $this->expireProcessSessionWithAnswers(
                        $id,
                        $userId,
                        $session,
                        is_array($answers) ? $answers : [],
                        $useBlocks ? $blockItems : $items,
                        $currentBlock,
                        $remainingSeconds,
                        $availability,
                        $answerTrace,
                        (string) $action
                    );
                    return;
                }

                $this->handleUnavailableProcessSession($id, $session, $availability);
                return;
            }

            if ($hasTimeLimit && ($action === 'time_expired' || ($remainingSeconds !== null && $remainingSeconds <= 0))) {
                $items = $this->sessions->itemsForSession($id);
                $this->logAnswerActivity($id, $userId, $answers, $items, $currentBlock, $remainingSeconds);
                $saveResult = $this->sessions->saveAnswersPayloadForSession($id, $answers);
                $this->sessions->logActivity($id, $userId, 'evaluation_expired', [
                    'block' => $currentBlock,
                    'remaining_seconds' => (int) ($remainingSeconds ?? 0),
                    'received_answer_item_ids' => $saveResult['received_item_ids'] ?? [],
                    'saved_answer_item_ids' => $saveResult['saved_item_ids'] ?? [],
                    'answer_trace' => $this->buildAnswerTrace($answerTrace, $answers, $saveResult, (string) $action, $currentBlock),
                ], null, $useBlocks ? $currentBlock : null);
                $this->sessions->complete($id, [], 'expired');
                flash('warning', 'El tiempo de esta evaluacion ha finalizado. Se guardaron las respuestas registradas hasta ese momento.');
                redirect(route_url('my-tests'));
            }

            if ($useBlocks && in_array($action, ['save_block', 'save_block_next', 'save_block_prev', 'save_block_finish'], true)) {
                $this->logAnswerActivity($id, $userId, $answers, $blockItems, $currentBlock, $remainingSeconds);
                $this->sessions->saveAnswersForItems($id, $answers, $blockItems);
                $this->sessions->logActivity($id, $userId, 'block_saved', [
                    'block' => $currentBlock,
                    'remaining_seconds' => $remainingSeconds,
                ], null, $currentBlock);

                if ($action === 'save_block_next' && $requireBlockCompletion) {
                    $missingInBlock = $this->sessions->missingRequiredAnswers($blockItems, is_array($answers) ? $answers : []);
                    if ($missingInBlock) {
                        $message = 'Debes responder todas las preguntas de este bloque antes de avanzar.';
                        if ($this->isAjaxRequest()) {
                            $this->jsonResponse([
                                'ok' => false,
                                'reason' => 'block_incomplete',
                                'message' => $message,
                                'missing_count' => count($missingInBlock),
                                'block' => $currentBlock,
                            ], 422);
                            return;
                        }

                        flash('warning', $message);
                        redirect(route_url('test-session.take', $id) . '?block=' . $currentBlock);
                    }
                }

                $items = $this->sessions->itemsForSession($id);

                if ($action === 'save_block_finish') {
                    if ($missing = $this->sessions->missingRequiredAnswers($items, [])) {
                        $missingBlock = $this->blockForFirstMissingItem($items, $missing, $blockSize);
                        if ($this->isAjaxRequest()) {
                            $missingOffset = ($missingBlock - 1) * $blockSize;
                            $missingItems = array_slice($items, $missingOffset, $blockSize);
                            $html = $this->view->render('tests/take', [
                                'title' => 'Responder evaluacion | Metricatest',
                                'currentPage' => 'my-tests',
                                'session' => $session,
                                'items' => $missingItems,
                                'progressItems' => $items,
                                'useBlocks' => $useBlocks,
                                'currentBlock' => $missingBlock,
                                'totalBlocks' => $totalBlocks,
                                'blockStart' => $missingOffset,
                                'requireBlockCompletion' => $requireBlockCompletion,
                                'remainingSeconds' => $remainingSeconds,
                                'sessionModel' => $this->sessions,
                                'evaluationMessages' => $this->settings->evaluationMessages(),
                            ], null);

                            $this->jsonResponse([
                                'ok' => false,
                                'reason' => 'missing_answers_before_finish',
                                'message' => 'Aun te quedan preguntas sin contestar en la evaluacion. Deseas finalizarla de todas formas?',
                                'missing_count' => count($missing),
                                'missing_block' => $missingBlock,
                                'next_block' => $missingBlock,
                                'next_url' => route_url('test-session.take', $id) . '?block=' . $missingBlock,
                                'redirect_url' => route_url('test-session.take', $id) . '?block=' . $missingBlock,
                                'html' => $html,
                            ], 422);
                            return;
                        }

                        flash('danger', 'Aun quedan preguntas sin responder. Te llevamos al primer bloque pendiente antes de finalizar.');
                        redirect(route_url('test-session.take', $id) . '?block=' . $missingBlock);
                    }

                    $this->sessions->logActivity($id, $userId, 'evaluation_submitted', [
                        'answered_count' => count(array_filter($answers, static fn($answer): bool => is_array($answer) ? count(array_filter($answer)) > 0 : trim((string) $answer) !== '')),
                        'remaining_seconds' => $remainingSeconds,
                    ]);
                    $this->sessions->complete($id, []);

                    if ($this->isAjaxRequest()) {
                        $this->jsonResponse([
                            'ok' => true,
                            'finished' => true,
                            'message' => 'Evaluacion enviada correctamente.',
                            'redirect_url' => route_url('my-tests'),
                        ]);
                        return;
                    }

                    flash('success', 'Evaluacion enviada correctamente.');
                    redirect(route_url('my-tests'));
                }

                $targetBlock = $action === 'save_block_prev'
                    ? max(1, $currentBlock - 1)
                    : min($totalBlocks, $currentBlock + 1);

                if ($this->isAjaxRequest()) {
                    $nextOffset = ($targetBlock - 1) * $blockSize;
                    $nextItems = array_slice($items, $nextOffset, $blockSize);

                    $html = $this->view->render('tests/take', [
                        'title' => 'Responder evaluacion | Metricatest',
                        'currentPage' => 'my-tests',
                        'session' => $session,
                        'items' => $nextItems,
                        'progressItems' => $items,
                        'useBlocks' => $useBlocks,
                        'currentBlock' => $targetBlock,
                        'totalBlocks' => $totalBlocks,
                        'blockStart' => $nextOffset,
                        'requireBlockCompletion' => $requireBlockCompletion,
                        'remainingSeconds' => $remainingSeconds,
                        'sessionModel' => $this->sessions,
                        'evaluationMessages' => $this->settings->evaluationMessages(),
                    ], null);

                    $this->jsonResponse([
                        'ok' => true,
                        'message' => 'Bloque guardado. Puedes volver a responder preguntas pendientes antes de finalizar.',
                        'next_block' => $targetBlock,
                        'next_url' => route_url('test-session.take', $id) . '?block=' . $targetBlock,
                        'html' => $html,
                    ]);
                    return;
                }

                flash('success', 'Bloque guardado. Puedes volver a responder preguntas pendientes antes de finalizar.');
                redirect(route_url('test-session.take', $id) . '?block=' . $targetBlock);
            }

            if ($action === 'complete_expired') {
                $items = $this->sessions->itemsForSession($id);
                $this->logAnswerActivity($id, $userId, $answers, $items, $currentBlock, $remainingSeconds);
                $saveResult = $this->sessions->saveAnswersPayloadForSession($id, $answers);
                $this->sessions->logActivity($id, $userId, 'evaluation_submitted_incomplete', [
                    'block' => $currentBlock,
                    'remaining_seconds' => $remainingSeconds,
                    'source' => 'legacy_incomplete_finish',
                    'auto_start_enabled' => (int) ($session['auto_start_enabled'] ?? 0),
                    'received_answer_item_ids' => $saveResult['received_item_ids'] ?? [],
                    'saved_answer_item_ids' => $saveResult['saved_item_ids'] ?? [],
                    'answer_trace' => $this->buildAnswerTrace($answerTrace, $answers, $saveResult, (string) $action, $currentBlock),
                ], null, $useBlocks ? $currentBlock : null);
                $this->sessions->complete($id, [], 'completed');

                if ($this->isAjaxRequest()) {
                    $this->jsonResponse([
                        'ok' => true,
                        'finished' => true,
                        'final_status' => 'completed',
                        'message' => 'Evaluacion finalizada con las respuestas registradas.',
                        'redirect_url' => route_url('my-tests'),
                    ]);
                    return;
                }

                flash('success', 'Evaluacion finalizada con las respuestas registradas.');
                redirect(route_url('my-tests'));
            }

            if ($action === 'complete_incomplete') {
                $items = $this->sessions->itemsForSession($id);
                $this->logAnswerActivity($id, $userId, $answers, $items, $currentBlock, $remainingSeconds);
                $saveResult = $this->sessions->saveAnswersPayloadForSession($id, $answers);
                $this->sessions->logActivity($id, $userId, 'evaluation_submitted_incomplete', [
                    'block' => $currentBlock,
                    'remaining_seconds' => $remainingSeconds,
                    'source' => 'user_confirmed_incomplete_finish',
                    'auto_start_enabled' => (int) ($session['auto_start_enabled'] ?? 0),
                    'received_answer_item_ids' => $saveResult['received_item_ids'] ?? [],
                    'saved_answer_item_ids' => $saveResult['saved_item_ids'] ?? [],
                    'answer_trace' => $this->buildAnswerTrace($answerTrace, $answers, $saveResult, (string) $action, $currentBlock),
                ], null, $useBlocks ? $currentBlock : null);
                $this->sessions->complete($id, [], 'completed');

                if ($this->isAjaxRequest()) {
                    $this->jsonResponse([
                        'ok' => true,
                        'finished' => true,
                        'final_status' => 'completed',
                        'message' => 'Evaluacion finalizada con las respuestas registradas.',
                        'redirect_url' => route_url('my-tests'),
                    ]);
                    return;
                }

                flash('success', 'Evaluacion finalizada con las respuestas registradas.');
                redirect(route_url('my-tests'));
            }

            if ($action === 'save_exit') {
                $items = $this->sessions->itemsForSession($id);
                $this->logAnswerActivity($id, $userId, $answers, $items, $currentBlock, $remainingSeconds);
                $saveResult = $this->sessions->saveAnswersPayloadForSession($id, $answers);
                if ($this->isAjaxRequest() && $useBlocks && $currentBlock === $totalBlocks) {
                    $this->sessions->logActivity($id, $userId, 'evaluation_submitted_incomplete', [
                        'block' => $currentBlock,
                        'remaining_seconds' => $remainingSeconds,
                        'source' => 'legacy_ajax_save_exit_from_final_block',
                        'auto_start_enabled' => (int) ($session['auto_start_enabled'] ?? 0),
                        'received_answer_item_ids' => $saveResult['received_item_ids'] ?? [],
                        'saved_answer_item_ids' => $saveResult['saved_item_ids'] ?? [],
                        'answer_trace' => $this->buildAnswerTrace($answerTrace, $answers, $saveResult, (string) $action, $currentBlock),
                    ], null, $currentBlock);
                    $this->sessions->complete($id, [], 'completed');
                    $this->jsonResponse([
                        'ok' => true,
                        'finished' => true,
                        'final_status' => 'completed',
                        'message' => 'Evaluacion finalizada con las respuestas registradas.',
                        'redirect_url' => route_url('my-tests'),
                    ]);
                    return;
                }

                $this->sessions->pause($id, $userId, $remainingSeconds);
                $this->sessions->logActivity($id, $userId, 'evaluation_paused', [
                    'block' => $currentBlock,
                    'remaining_seconds' => $remainingSeconds,
                    'source' => 'user_exit',
                    'received_answer_item_ids' => $saveResult['received_item_ids'] ?? [],
                    'saved_answer_item_ids' => $saveResult['saved_item_ids'] ?? [],
                    'answer_trace' => $this->buildAnswerTrace($answerTrace, $answers, $saveResult, (string) $action, $currentBlock),
                ], null, $useBlocks ? $currentBlock : null);
                if ($this->isAjaxRequest()) {
                    $this->jsonResponse([
                        'ok' => true,
                        'finished' => true,
                        'paused' => true,
                        'final_status' => 'paused',
                        'message' => 'Evaluacion guardada. Podras continuarla con el tiempo restante.',
                        'redirect_url' => route_url('my-tests'),
                    ]);
                    return;
                }

                flash('success', 'Evaluacion guardada. Podras continuarla con el tiempo restante.');
                redirect(route_url('my-tests'));
            }

            if ($useBlocks) {
                $this->sessions->saveAnswersForItems($id, $answers, $blockItems);
                $items = $this->sessions->itemsForSession($id);
            }

            if ($missing = $this->sessions->missingRequiredAnswers($items, $answers)) {
                $firstMissing = (int) reset($missing);
                $missingIndex = 0;
                foreach ($items as $index => $item) {
                    if ((int) $item['id'] === $firstMissing) {
                        $missingIndex = $index;
                        break;
                    }
                }
                $missingBlock = $useBlocks ? $this->blockForFirstMissingItem($items, $missing, $blockSize) : 1;
                flash('danger', 'Aun quedan preguntas sin responder. Te llevamos al primer bloque pendiente antes de finalizar.');
                redirect(route_url('test-session.take', $id) . ($useBlocks ? '?block=' . $missingBlock : ''));
            }

            if (!$useBlocks) {
                $this->logAnswerActivity($id, $userId, $answers, $items, $currentBlock, $remainingSeconds);
            }
            $this->sessions->logActivity($id, $userId, 'evaluation_submitted', [
                'answered_count' => count(array_filter($answers, static fn($answer): bool => is_array($answer) ? count(array_filter($answer)) > 0 : trim((string) $answer) !== '')),
                'remaining_seconds' => $remainingSeconds,
            ]);
            $this->sessions->complete($id, $answers);
            flash('success', 'Evaluacion enviada correctamente.');
            redirect(route_url('my-tests'));
        }

        $this->render('tests/take', [
            'title' => 'Responder evaluacion | Metricatest',
            'currentPage' => 'my-tests',
            'session' => $session,
            'items' => $useBlocks ? $blockItems : $items,
            'progressItems' => $items,
            'useBlocks' => $useBlocks,
            'currentBlock' => $currentBlock,
            'totalBlocks' => $totalBlocks,
            'blockStart' => $blockOffset,
            'requireBlockCompletion' => $requireBlockCompletion,
            'remainingSeconds' => $remainingSeconds,
            'sessionModel' => $this->sessions,
            'evaluationMessages' => $this->settings->evaluationMessages(),
        ]);
    }

    public function activity(): void
    {
        require_auth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            platform_error(405, 'Metodo no permitido.');
        }

        verify_csrf();
        $id = request_secure_id('test_session');
        $userId = (int) current_user()['id'];
        $eventType = (string) ($_POST['event_type'] ?? '');
        if ($eventType === 'heartbeat') {
            $saved = $this->sessions->touchPresence($id, $userId);
            header('Content-Type: application/json');
            echo json_encode(['ok' => $saved]);
            return;
        }

        $metadata = [];
        $allowedMetadata = ['visible', 'hidden_seconds', 'inactive_seconds', 'remaining_seconds', 'reason', 'source', 'url_path', 'key', 'combo', 'supported', 'attempt', 'device_type', 'os', 'browser', 'browser_language', 'timezone', 'screen', 'viewport', 'pixel_ratio', 'touch_points', 'hardware_concurrency', 'device_memory', 'connection'];
        foreach ($allowedMetadata as $key) {
            if (array_key_exists($key, $_POST)) {
                $metadata[$key] = $_POST[$key];
            }
        }

        $saved = $this->sessions->logActivity(
            $id,
            $userId,
            $eventType,
            $metadata,
            max(0, (int) ($_POST['item_id'] ?? 0)) ?: null,
            max(0, (int) ($_POST['block_number'] ?? 0)) ?: null
        );

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $saved], JSON_UNESCAPED_UNICODE);
    }

    public function mediaInit(): void
    {
        require_auth();
        verify_csrf();
        $id = request_secure_id('test_session');
        $userId = (int) current_user()['id'];
        $policy = (string) ($_POST['policy'] ?? 'pause');
        $consented = (string) ($_POST['consented'] ?? '0') === '1';
        $result = $this->mediaEvidence->setPolicy($id, $userId, $policy, $consented);
        if (!empty($result['ok'])) {
            $this->sessions->logActivity($id, $userId, 'audio_visual_recording_started', ['policy' => $policy]);
        }
        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    public function mediaStatus(): void
    {
        require_auth();
        $id = request_secure_id('test_session');
        $userId = (int) current_user()['id'];
        $session = $this->mediaEvidence->session($id, $userId);
        if (!$session) {
            $this->jsonResponse(['ok' => false, 'reason' => 'session_not_found'], 404);
            return;
        }
        $this->jsonResponse([
            'ok' => true,
            'evidence' => $this->mediaEvidence->evidenceForSession($id),
            'risks' => $this->mediaEvidence->risksForSession($id),
        ]);
    }

    public function mediaChunk(): void
    {
        require_auth();
        verify_csrf();
        $id = request_secure_id('test_session');
        $userId = (int) current_user()['id'];
        $evidenceId = max(0, (int) ($_POST['evidence_id'] ?? 0));
        $chunkNumber = max(0, (int) ($_POST['chunk_number'] ?? -1));
        $result = $this->mediaEvidence->uploadChunk($id, $userId, $evidenceId, $chunkNumber, $_FILES['chunk'] ?? [], $_POST['mime_type'] ?? null);
        if (!empty($result['ok'])) {
            $this->sessions->logActivity($id, $userId, 'audio_visual_upload_started', ['chunk_number' => $chunkNumber]);
        }
        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    public function mediaFinalize(): void
    {
        require_auth();
        verify_csrf();
        $id = request_secure_id('test_session');
        $userId = (int) current_user()['id'];
        $evidenceId = max(0, (int) ($_POST['evidence_id'] ?? 0));
        $duration = isset($_POST['duration_seconds']) ? max(0, (int) $_POST['duration_seconds']) : null;
        $result = $this->mediaEvidence->finalize($id, $userId, $evidenceId, $duration);
        $event = !empty($result['ok']) ? 'audio_visual_upload_completed' : 'audio_visual_upload_failed';
        $this->sessions->logActivity($id, $userId, $event, ['reason' => $result['reason'] ?? null, 'evidence_id' => $evidenceId]);
        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    public function mediaRisk(): void
    {
        require_auth();
        verify_csrf();
        $id = request_secure_id('test_session');
        $userId = (int) current_user()['id'];
        $eventType = (string) ($_POST['event_type'] ?? '');
        $severity = (string) ($_POST['severity'] ?? 'attention');
        $confidence = isset($_POST['confidence']) ? (float) $_POST['confidence'] : null;
        $metadata = is_array($_POST['metadata'] ?? null) ? $_POST['metadata'] : [];
        $evidenceId = max(0, (int) ($_POST['evidence_id'] ?? 0)) ?: null;
        $saved = $this->mediaEvidence->recordRisk($id, $userId, $eventType, $severity, $confidence, $metadata, $evidenceId);
        if ($saved) {
            $this->sessions->logActivity($id, $userId, 'audio_visual_risk', [
                'event_type' => $eventType,
                'severity' => $severity,
                'confidence' => $confidence,
                'evidence_id' => $evidenceId,
            ]);
        }
        $this->jsonResponse(['ok' => $saved], $saved ? 200 : 422);
    }

    public function mediaEvidence(): void
    {
        require_result_access();
        $id = request_secure_id('test_session');
        if (!$this->sessions->findForAdmin($id)) {
            platform_error(404, 'Evidencia audiovisual no disponible.');
        }
        $this->mediaEvidence->auditAdminAccess($id, (int) (current_user()['id'] ?? 0), 'video_viewed');
        $file = $this->mediaEvidence->evidenceFileForAdmin($id);
        if (!$file) {
            platform_error(404, 'Evidencia audiovisual no disponible.');
        }
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . (string) $file['size']);
        header('Content-Disposition: inline; filename="evidencia-' . $id . '.media"');
        header('Cache-Control: private, no-store');
        readfile($file['path']);
        exit;
    }

    public function draft(): void
    {
        require_auth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            platform_error(405, 'Metodo no permitido.');
        }

        verify_csrf();
        $id = request_secure_id('test_session');
        $userId = (int) current_user()['id'];

        if (!$this->sessions->canSaveDraftForUser($id, $userId)) {
            $this->jsonResponse([
                'ok' => false,
                'message' => 'La evaluacion ya no esta disponible para guardar borrador.',
            ], 409);
            return;
        }

        $rawPostAnswers = is_array($_POST['answers'] ?? null) ? $_POST['answers'] : [];
        $answerTrace = $this->answerSnapshotTrace($rawPostAnswers, $_POST['answers_snapshot_json'] ?? null);
        $answers = $this->mergeAnswerSnapshot($rawPostAnswers, $_POST['answers_snapshot_json'] ?? null);
        $currentBlock = max(1, (int) ($_POST['block'] ?? 1));
        $remainingSeconds = isset($_POST['remaining_seconds']) && $_POST['remaining_seconds'] !== ''
            ? max(0, (int) $_POST['remaining_seconds'])
            : null;
        $draftReason = (string) ($_POST['reason'] ?? '');
        $saveResult = $this->sessions->saveAnswersPayloadForSession($id, $answers);

        if ($draftReason === 'page_exit') {
            $this->sessions->pause($id, $userId, $remainingSeconds);
        }

        if (in_array($draftReason, ['tab_hidden', 'window_blurred', 'process_expired', 'page_exit'], true)) {
            $this->sessions->logActivity($id, $userId, 'draft_saved', [
                'block' => $currentBlock,
                'remaining_seconds' => $remainingSeconds,
                'reason' => $draftReason,
                'received_answer_item_ids' => $saveResult['received_item_ids'] ?? [],
                'saved_answer_item_ids' => $saveResult['saved_item_ids'] ?? [],
                'answer_trace' => $this->buildAnswerTrace($answerTrace, $answers, $saveResult, 'draft', $currentBlock),
            ], null, $currentBlock);
        }

        $this->jsonResponse([
            'ok' => true,
            'saved_answer_item_ids' => $saveResult['saved_item_ids'] ?? [],
        ]);
    }

    public function availability(): void
    {
        require_auth();

        $id = request_secure_id('test_session');
        $session = $this->sessions->availabilitySessionForUser($id, (int) current_user()['id']);
        if (!$session) {
            $this->jsonResponse([
                'ok' => false,
                'message' => 'Evaluacion no encontrada.',
            ], 404);
            return;
        }

        $this->jsonResponse([
            'ok' => true,
            'server_now' => time(),
            'availability' => $this->sessions->availabilityForSession($session),
            'redirect_url' => route_url('my-tests'),
        ]);
    }

    public function result(): void
    {
        require_auth();

        $id = request_secure_id('test_session');
        $isOwnSession = false;
        $session = $this->sessions->findForUser($id, (int) current_user()['id']);
        if ($session) {
            $isOwnSession = true;
        }

        if (!$session && has_result_access()) {
            $session = $this->sessions->findForAdmin($id);
        }

        if (!$session) {
            platform_error(404, 'Resultado no encontrado.', [
                'chips' => ['Resultado', 'Evaluacion'],
            ]);
        }

        if ($isOwnSession && (int) ($session['user_can_view_results'] ?? 1) !== 1) {
            flash('warning', 'El resultado de esta evaluacion no esta disponible para el usuario.');
            redirect(route_url('dashboard'));
        }

        if (!$isOwnSession && has_result_access()) {
            $this->mediaEvidence->auditAdminAccess($id, (int) (current_user()['id'] ?? 0), 'result_viewed');
        }

        $summary = $this->sessions->summaryForSession($id);
        if (!$summary && $session['status'] === 'expired') {
            $summary = $this->sessions->complete($id, [], 'expired');
            $session = $this->sessions->findForUser($id, (int) current_user()['id']);
            if (!$session && has_result_access()) {
                $session = $this->sessions->findForAdmin($id);
            }
        }

        $this->render('tests/result', [
            'title' => 'Resultado evaluacion | Metricatest',
            'currentPage' => has_result_access() ? 'tests' : 'my-tests',
            'session' => $session,
            'summary' => $summary,
            'riasecResult' => $this->riasecRecommendations->buildForSession($session, $summary),
            'resultContext' => $this->sessions->resultContext($id),
            'answeredItems' => $this->sessions->resultAnswerItemsForSession($id),
            'activityEvents' => has_result_access() ? $this->sessions->activityForSession($id) : [],
            'mediaEvidence' => has_result_access() && !$isOwnSession ? $this->mediaEvidence->evidenceForSession($id) : null,
            'audioVisualRisks' => has_result_access() && !$isOwnSession ? $this->mediaEvidence->risksForSession($id) : [],
        ]);
    }

    public function userResults(): void
    {
        require_result_access();

        $userId = request_secure_id('user');
        $sessions = $this->sessions->finishedSessionsForUserResults($userId);
        if (!$sessions) {
            platform_error(404, 'No hay resultados terminados para este usuario.', [
                'chips' => ['Resultados', 'Usuario'],
            ]);
        }

        $results = [];
        foreach ($sessions as $session) {
            $sessionId = (int) $session['id'];
            $summary = $this->sessions->summaryForSession($sessionId);
            if (!$summary && $session['status'] === 'expired') {
                $summary = $this->sessions->complete($sessionId, [], 'expired');
            }

            $results[] = [
                'session' => $session,
                'summary' => $summary,
                'resultContext' => $this->sessions->resultContext($sessionId),
                'answeredItems' => $this->sessions->resultAnswerItemsForSession($sessionId),
                'activityEvents' => $this->sessions->activityForSession($sessionId),
                'mediaEvidence' => $this->mediaEvidence->evidenceForSession($sessionId),
                'audioVisualRisks' => $this->mediaEvidence->risksForSession($sessionId),
            ];
        }

        $user = $sessions[0];
        $this->render('tests/user_results', [
            'title' => 'Resultados usuario | Metricatest',
            'currentPage' => 'tests',
            'userResult' => [
                'id' => $userId,
                'name' => $user['user_name'] ?? '',
                'email' => $user['user_email'] ?? '',
                'company_name' => $user['company_name'] ?? '',
                'age' => $user['age'] ?? '',
            ],
            'results' => $results,
        ]);
    }

    public function resultExport(): void
    {
        require_result_access();

        $sessionId = request_secure_id('test_session');
        $session = $this->sessions->findForAdmin($sessionId);
        if (!$session || !in_array((string) ($session['status'] ?? ''), ['completed', 'expired'], true)) {
            platform_error(404, 'Resultado no encontrado.', [
                'chips' => ['Resultado', 'Excel'],
            ]);
        }

        $summary = $this->sessions->summaryForSession($sessionId);
        if (!$summary && (string) ($session['status'] ?? '') === 'expired') {
            $summary = $this->sessions->complete($sessionId, [], 'expired');
        }

        $this->downloadSingleResultXlsx($session, $summary, $this->sessions->resultContext($sessionId));
    }

    public function form(): void
    {
        require_permission('manage_tests');

        $id = request_secure_id('test');
        $instrument = $id ? $this->tests->find($id) : null;

        if ($id && !$instrument) {
            platform_error(404, 'Instrumento no encontrado.', [
                'chips' => ['Instrumento', 'Evaluacion'],
            ]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save($id);
        }

        $contentStats = $id ? $this->tests->contentStats($id) : [
            'scales_count' => 0,
            'items_count' => 0,
            'score_rules_count' => 0,
            'norms_count' => 0,
            'formula_terms_count' => 0,
        ];

        $this->render('tests/form', [
            'title' => ($id ? 'Editar evaluacion' : 'Nueva evaluacion') . ' | Metricatest',
            'currentPage' => 'tests',
            'id' => $id,
            'categories' => TestInstrumentModel::CATEGORIES,
            'statuses' => TestInstrumentModel::STATUSES,
            'itemTypes' => TestInstrumentModel::ITEM_TYPES,
            'questionOrderModes' => TestInstrumentModel::QUESTION_ORDER_MODES,
            'contentStats' => $contentStats,
            'values' => $instrument ?: [
                'code' => '',
                'name' => '',
                'category' => 'personality',
                'description' => '',
                'source_reference' => '',
                'duration_minutes' => 0,
                'instructions' => '',
                'use_blocks' => 0,
                'block_size' => 10,
                'require_block_completion' => 1,
                'question_order_mode' => 'ordered',
                'show_question_numbers' => 1,
                'track_activity_enabled' => 0,
                'supervised_mode_enabled' => 0,
                'control_mode' => 'off',
                'audio_visual_upload_failure_policy' => 'continue',
                'audio_visual_interruption_policy' => 'pause',
                'audio_visual_voice_policy' => 'warn',
                'audio_visual_permission_policy' => 'pause',
                'audio_visual_quality_profile' => 'standard',
                'auto_start_enabled' => 0,
                'auto_start_order' => 100,
                'user_can_view_results' => 1,
                'status' => 'draft',
                'requires_manual_review' => 1,
            ],
        ]);
    }

    public function content(): void
    {
        require_permission('manage_tests');

        $id = request_secure_id('test');
        $instrument = $this->tests->find($id);
        if (!$instrument) {
            platform_error(404, 'Instrumento no encontrado.', [
                'chips' => ['Instrumento', 'Contenido'],
            ]);
        }

        $stats = $this->tests->contentStats($id);
        $items = $this->tests->itemsForInstrument($id);
        $rulesByItem = $this->tests->scoreRulesForItems($id, array_column($items, 'id'));

        $this->render('tests/content', [
            'title' => 'Contenido evaluacion | Metricatest',
            'currentPage' => 'tests',
            'instrument' => $instrument,
            'scales' => $this->tests->scalesForInstrument($id),
            'items' => $items,
            'rulesByItem' => $rulesByItem,
            'itemTypes' => TestInstrumentModel::ITEM_TYPES,
            'stats' => $stats,
        ]);
    }

    public function contentHelp(): void
    {
        require_permission('manage_tests');

        $id = request_secure_id('test');
        $instrument = $this->tests->find($id);
        if (!$instrument) {
            platform_error(404, 'Instrumento no encontrado.', [
                'chips' => ['Instrumento', 'Ayuda'],
            ]);
        }

        $this->render('tests/partials/content_help', [
            'instrument' => $instrument,
        ], ($_GET['partial'] ?? '') !== '' ? null : 'app');
    }

    public function contentScaleForm(): void
    {
        require_permission('manage_tests');

        $id = request_secure_id('test');
        $instrument = $this->tests->find($id);
        if (!$instrument) {
            platform_error(404, 'Instrumento no encontrado.', [
                'chips' => ['Instrumento', 'Escala'],
            ]);
        }

        $scaleId = max(0, (int) ($_GET['scale_id'] ?? $_POST['scale_id'] ?? 0));
        $scale = $scaleId > 0 ? $this->tests->scaleForInstrument($id, $scaleId) : null;
        if ($scaleId > 0 && !$scale) {
            platform_error(404, 'Escala no encontrada.', [
                'chips' => ['Escala', 'Instrumento'],
            ]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $this->tests->saveScale($id, $scaleId, $_POST['scale'] ?? []);
            flash('success', 'Escala guardada correctamente.');
            redirect(route_url('test.content', $id));
        }

        $this->render('tests/partials/scale_form', [
            'instrument' => $instrument,
            'scale' => $scale,
        ], ($_GET['partial'] ?? '') !== '' ? null : 'app');
    }

    public function contentScaleDelete(): void
    {
        require_permission('manage_tests');
        verify_csrf();

        $id = request_secure_id('test');
        $scaleId = (int) ($_POST['scale_id'] ?? 0);
        $this->tests->deleteScale($id, $scaleId);
        flash('success', 'Escala eliminada correctamente.');
        redirect(route_url('test.content', $id));
    }

    public function contentItemForm(): void
    {
        require_permission('manage_tests');

        $id = request_secure_id('test');
        $instrument = $this->tests->find($id);
        if (!$instrument) {
            platform_error(404, 'Instrumento no encontrado.', [
                'chips' => ['Instrumento', 'Pregunta'],
            ]);
        }

        $itemId = max(0, (int) ($_GET['item_id'] ?? $_POST['item_id'] ?? 0));
        $item = $itemId > 0 ? $this->tests->itemForInstrument($id, $itemId) : null;
        if ($itemId > 0 && !$item) {
            platform_error(404, 'Pregunta no encontrada.', [
                'chips' => ['Pregunta', 'Instrumento'],
            ]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $this->tests->saveItemWithRules($id, $itemId, $_POST);
            flash('success', 'Pregunta guardada correctamente.');
            redirect(route_url('test.content', $id));
        }

        $rulesByItem = $item ? $this->tests->scoreRulesForItems($id, [(int) $item['id']]) : [];

        $this->render('tests/partials/item_form', [
            'instrument' => $instrument,
            'item' => $item,
            'rules' => $item ? ($rulesByItem[(int) $item['id']] ?? []) : [],
            'scales' => $this->tests->scalesForInstrument($id),
            'itemTypes' => TestInstrumentModel::ITEM_TYPES,
        ], ($_GET['partial'] ?? '') !== '' ? null : 'app');
    }

    public function contentItemDelete(): void
    {
        require_permission('manage_tests');
        verify_csrf();

        $id = request_secure_id('test');
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $this->tests->deleteItem($id, $itemId);
        flash('success', 'Pregunta eliminada correctamente.');
        redirect(route_url('test.content', $id));
    }

    private function save(int $id): void
    {
        verify_csrf();

        $data = [
            'code' => preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['code'] ?? ''))),
            'name' => trim($_POST['name'] ?? ''),
            'category' => $_POST['category'] ?? 'personality',
            'description' => trim($_POST['description'] ?? ''),
            'source_reference' => trim($_POST['source_reference'] ?? ''),
            'duration_minutes' => max(0, (int) ($_POST['duration_minutes'] ?? 0)),
            'instructions' => trim($_POST['instructions'] ?? ''),
            'use_blocks' => isset($_POST['use_blocks']) ? 1 : 0,
            'block_size' => max(0, (int) ($_POST['block_size'] ?? 0)),
            'require_block_completion' => (string) ($_POST['require_block_completion'] ?? '1') === '1' ? 1 : 0,
            'question_order_mode' => $_POST['question_order_mode'] ?? 'ordered',
            'show_question_numbers' => isset($_POST['show_question_numbers']) ? 1 : 0,
            'track_activity_enabled' => isset($_POST['track_activity_enabled']) ? 1 : 0,
            'supervised_mode_enabled' => isset($_POST['supervised_mode_enabled']) ? 1 : 0,
            'control_mode' => $_POST['control_mode'] ?? '',
            'audio_visual_upload_failure_policy' => $_POST['audio_visual_upload_failure_policy'] ?? 'continue',
            'audio_visual_interruption_policy' => $_POST['audio_visual_interruption_policy'] ?? 'pause',
            'audio_visual_voice_policy' => $_POST['audio_visual_voice_policy'] ?? 'warn',
            'audio_visual_permission_policy' => $_POST['audio_visual_permission_policy'] ?? 'pause',
            'audio_visual_quality_profile' => $_POST['audio_visual_quality_profile'] ?? 'standard',
            'auto_start_enabled' => isset($_POST['auto_start_enabled']) ? 1 : 0,
            'auto_start_order' => max(1, (int) ($_POST['auto_start_order'] ?? 100)),
            'user_can_view_results' => isset($_POST['user_can_view_results']) ? 1 : 0,
            'status' => $_POST['status'] ?? 'draft',
            'requires_manual_review' => isset($_POST['requires_manual_review']) ? 1 : 0,
        ];
        if (!isset(TestInstrumentModel::CONTROL_MODES[$data['control_mode']])) {
            $data['control_mode'] = $data['supervised_mode_enabled'] === 1
                ? 'supervised'
                : ($data['track_activity_enabled'] === 1 ? 'activity' : 'off');
        }
        if (!in_array($data['audio_visual_upload_failure_policy'], ['continue', 'retry_once', 'block'], true)) {
            $data['audio_visual_upload_failure_policy'] = 'continue';
        }
        if (!in_array($data['audio_visual_interruption_policy'], ['continue', 'pause', 'block'], true)) $data['audio_visual_interruption_policy'] = 'pause';
        if (!in_array($data['audio_visual_voice_policy'], ['log', 'warn', 'pause'], true)) $data['audio_visual_voice_policy'] = 'warn';
        if (!in_array($data['audio_visual_permission_policy'], ['continue', 'pause', 'block'], true)) $data['audio_visual_permission_policy'] = 'pause';
        if (!in_array($data['audio_visual_quality_profile'], ['economical', 'standard', 'high'], true)) $data['audio_visual_quality_profile'] = 'standard';
        $data['track_activity_enabled'] = $data['control_mode'] === 'off' ? 0 : 1;
        $data['supervised_mode_enabled'] = in_array($data['control_mode'], ['supervised', 'supervised_audio_visual'], true) ? 1 : 0;
        if ($data['supervised_mode_enabled'] === 1) {
            $data['track_activity_enabled'] = 1;
        }
        if (!isset(TestInstrumentModel::QUESTION_ORDER_MODES[$data['question_order_mode']])) {
            $data['question_order_mode'] = 'ordered';
        }
        if ($data['use_blocks'] === 1 && $data['block_size'] <= 0) {
            $data['block_size'] = 10;
        }
        if ($data['code'] === '' && $data['name'] !== '') {
            $data['code'] = $this->uniqueInstrumentCodeFromName($data['name'], $id);
        }

        if (
            $data['code'] === ''
            || $data['name'] === ''
            || !isset(TestInstrumentModel::CATEGORIES[$data['category']])
            || !isset(TestInstrumentModel::STATUSES[$data['status']])
        ) {
            flash('danger', 'Completa codigo, nombre, categoria y estado valido.');
            return;
        }

        try {
            $redirectUrl = route_url('tests');
            if ($id) {
                $this->tests->update($id, $data);
                flash('success', 'Evaluacion actualizada correctamente. El contenido se administra desde el mantenedor por bloques.');
            } else {
                $id = $this->tests->create($data);
                flash('success', 'Evaluacion creada correctamente. Ahora puedes cargar escalas y preguntas desde el mantenedor de contenido.');
                $redirectUrl = route_url('test.content', $id);
            }

            redirect($redirectUrl);
        } catch (PDOException $exception) {
            error_log('Test save error: ' . $exception->getMessage());
            flash('danger', 'No se pudo guardar la evaluacion. Revisa codigo, items o tamano de las imagenes.');
        }
    }

    private function uniqueInstrumentCodeFromName(string $name, int $ignoreId = 0): string
    {
        $base = preg_replace('/[^a-z0-9_]+/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($name)) ?: trim($name))) ?? '';
        $base = trim(preg_replace('/_+/', '_', $base) ?? '', '_');
        $base = substr($base !== '' ? $base : 'evaluacion', 0, 52);
        $code = $base;
        $suffix = 2;

        while (true) {
            $existing = $this->tests->findByCode($code);
            if (!$existing || (int) ($existing['id'] ?? 0) === $ignoreId) {
                return $code;
            }

            $code = substr($base, 0, 52) . '_' . $suffix++;
        }
    }

    private function testsIndexUrl(): string
    {
        return has_permission('manage_tests') ? route_url('tests') : route_url('dashboard');
    }

    private function downloadProgressResultsXlsx(array $sessions, array $rankingConfig): void
    {
        $sheets = [];
        $summariesBySessionId = [];
        foreach ($sessions as $session) {
            $sessionId = (int) $session['id'];
            $instrumentId = (int) $session['instrument_id'];
            $summary = $this->sessions->summaryForSession($sessionId);
            if (!$summary && (string) ($session['status'] ?? '') === 'expired') {
                $summary = $this->sessions->complete($sessionId, [], 'expired');
            }
            $summariesBySessionId[$sessionId] = $summary;

            if (!isset($sheets[$instrumentId])) {
                $sheets[$instrumentId] = [
                    'title' => (string) ($session['instrument_name'] ?? 'Evaluacion'),
                    'headers' => ['RUT', 'Nombre completo'],
                    'rows' => [],
                ];
            }

            $row = [
                'RUT' => (string) ($session['user_rut'] ?? ''),
                'Nombre completo' => (string) ($session['user_name'] ?? ''),
            ];
            foreach ($this->progressExportResultColumns($session, $summary, $this->sessions->resultContext($sessionId)) as $header => $value) {
                if (!in_array($header, $sheets[$instrumentId]['headers'], true)) {
                    $sheets[$instrumentId]['headers'][] = $header;
                }
                $row[$header] = $value;
            }

            $sheets[$instrumentId]['rows'][] = $row;
        }

        $workbookSheets = [];
        foreach ($sheets as $sheetData) {
            $headers = $sheetData['headers'];
            $rows = [$headers];
            foreach ($sheetData['rows'] as $row) {
                $rows[] = array_map(static fn(string $header): string => (string) ($row[$header] ?? ''), $headers);
            }

            $workbookSheets[] = [
                'title' => (string) $sheetData['title'],
                'rows' => $rows,
            ];
        }

        $ranking = $this->progressRankingSummary->build($sessions, static function (int $sessionId, array $session) use ($summariesBySessionId): array {
            return $summariesBySessionId[$sessionId] ?? [];
        }, $rankingConfig);
        if (!empty($ranking['rows']) || !empty($ranking['warnings'])) {
            $workbookSheets[] = [
                'title' => ProgressRankingSummaryService::SHEET_TITLE,
                'rows' => $this->progressRankingSummary->sheetRows($ranking),
            ];
            $workbookSheets[] = [
                'title' => 'Base Reglas',
                'rows' => $this->progressRankingSummary->baseRulesRows(),
            ];
        }

        $filename = 'estado_avance_resultados_' . date('Ymd_His') . '.xlsx';
        download_xlsx_workbook($filename, $workbookSheets, 'Estado Avance resultados');
    }

    private function downloadInstrumentResultsXlsx(array $instrument, array $sessions): void
    {
        $headers = [
            'Proceso',
            'Fecha y hora inicio',
            'Fecha y hora termino',
            'RUT usuario',
            'Nombre usuario',
        ];
        $rows = [];
        foreach ($sessions as $session) {
            $sessionId = (int) ($session['id'] ?? 0);
            $summary = $sessionId > 0 ? $this->sessions->summaryForSession($sessionId) : [];
            if (!$summary && (string) ($session['status'] ?? '') === 'expired' && $sessionId > 0) {
                $summary = $this->sessions->complete($sessionId, [], 'expired');
            }

            $row = [
                'Proceso' => (string) ($session['process_name'] ?? ''),
                'Fecha y hora inicio' => (string) ($session['started_at'] ?? ''),
                'Fecha y hora termino' => (string) ($session['completed_at'] ?? ''),
                'RUT usuario' => (string) ($session['user_rut'] ?? ''),
                'Nombre usuario' => (string) ($session['user_name'] ?? ''),
            ];
            foreach ($this->progressExportResultColumns($session, $summary, $sessionId > 0 ? $this->sessions->resultContext($sessionId) : []) as $header => $value) {
                if (!in_array($header, $headers, true)) {
                    $headers[] = $header;
                }
                $row[$header] = $value;
            }
            $rows[] = $row;
        }

        $sheetRows = [$headers];
        foreach ($rows as $row) {
            $sheetRows[] = array_map(static fn(string $header): string => (string) ($row[$header] ?? ''), $headers);
        }

        $filename = sprintf(
            'resultados_%s_%s.xlsx',
            $this->resultExportFilenamePart((string) ($instrument['code'] ?? $instrument['name'] ?? 'evaluacion')),
            date('Ymd_His')
        );

        download_xlsx_workbook($filename, [[
            'title' => (string) ($instrument['name'] ?? 'Resultados'),
            'rows' => $sheetRows,
        ]], 'Resultados evaluacion');
    }

    private function downloadInstrumentAnswersXlsx(array $instrument, array $sessions, array $dashboardFields): void
    {
        $instrumentId = (int) ($instrument['id'] ?? 0);
        $items = array_values(array_filter(
            $this->tests->itemsForInstrument($instrumentId),
            static fn(array $item): bool => (int) ($item['is_active'] ?? 1) === 1
        ));

        if (!$items) {
            flash('warning', 'La evaluacion no tiene preguntas activas para exportar.');
            redirect(route_url('tests'));
        }

        $baseHeaders = [
            'RUT',
            'Nombre',
            'Proceso',
            'Fecha y hora inicio',
            'Fecha y hora termino',
            'Sede',
        ];
        $headers = $baseHeaders;
        $itemHeaders = [];
        foreach ($items as $index => $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $header = $this->answerExportQuestionHeader($item, $index + 1);
            while (in_array($header, $headers, true)) {
                $header .= ' ';
            }

            $headers[] = $header;
            $itemHeaders[$itemId] = $header;
        }

        $answersBySession = $this->sessions->answersForSessions(array_column($sessions, 'id'));
        $rows = [$headers];
        foreach ($sessions as $session) {
            $sessionId = (int) ($session['id'] ?? 0);
            $row = [
                'RUT' => (string) ($session['user_rut'] ?? ''),
                'Nombre' => (string) ($session['user_name'] ?? ''),
                'Proceso' => (string) ($session['process_name'] ?? ''),
                'Fecha y hora inicio' => (string) ($session['started_at'] ?? ''),
                'Fecha y hora termino' => (string) ($session['completed_at'] ?? ''),
                'Sede' => $this->answerExportSite($session, $dashboardFields),
            ];

            foreach ($items as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                $row[$itemHeaders[$itemId]] = $this->answerExportDisplayValue(
                    (string) ($answersBySession[$sessionId][$itemId] ?? ''),
                    $item
                );
            }

            $rows[] = array_map(static fn(string $header): string => (string) ($row[$header] ?? ''), $headers);
        }

        $filename = sprintf(
            'preguntas_respuestas_%s_%s.xlsx',
            $this->resultExportFilenamePart((string) ($instrument['code'] ?? $instrument['name'] ?? 'evaluacion')),
            date('Ymd_His')
        );

        download_xlsx_workbook($filename, [[
            'title' => (string) ($instrument['name'] ?? 'Preguntas y respuestas'),
            'rows' => $rows,
        ]], 'Preguntas y respuestas evaluacion');
    }

    private function downloadSingleResultXlsx(array $session, array $summary, array $resultContext): void
    {
        $rows = [
            ['Dato', 'Valor'],
            ['Proceso', (string) ($session['process_name'] ?? '')],
            ['Fecha y hora inicio', (string) ($session['started_at'] ?? '')],
            ['Fecha y hora termino', (string) ($session['completed_at'] ?? '')],
            ['RUT usuario', (string) ($session['user_rut'] ?? '')],
            ['Nombre usuario', (string) ($session['user_name'] ?? '')],
            ['Evaluacion', (string) ($session['instrument_name'] ?? '')],
            ['Codigo evaluacion', (string) ($session['instrument_code'] ?? '')],
            ['Estado', $this->resultExportStatusLabel((string) ($session['status'] ?? ''))],
            [],
        ];

        if (!$summary) {
            $rows[] = ['Resultado'];
            $rows[] = ['No hay resumen calculado para esta evaluacion.'];
        } elseif ($this->progressExportUsesScaleColumns($session)) {
            $headers = [];
            $values = [];
            foreach ($summary as $row) {
                $header = $this->progressExportHeader((string) ($row['name'] ?? $row['scale'] ?? 'Escala'));
                if ((string) ($session['instrument_code'] ?? '') === 'ticl_barratt') {
                    $header .= ' - Puntaje bruto';
                }
                $headers[] = $header;
                $values[] = $this->progressExportScaleValue($session, $row);
            }
            $rows[] = $headers;
            $rows[] = $values;
        } else {
            $rows[] = [
                'Escala',
                'Codigo escala',
                'Respuestas Correctas',
                'Respondidos',
                'Puntaje bruto',
                'Puntaje ajustado',
                'Transformado',
                'Percentil',
                'Clasificacion',
            ];
            $scaleMetrics = $resultContext['scale_metrics'] ?? [];
            $supportsCorrectAnswers = !empty($resultContext['supports_correct_answers']);
            foreach ($summary as $row) {
                $metricKey = !empty($row['scale_id']) ? 'id:' . (int) $row['scale_id'] : 'scale:' . (string) ($row['scale'] ?? 'general');
                $rowMetrics = $scaleMetrics[$metricKey] ?? $scaleMetrics['scale:' . (string) ($row['scale'] ?? 'general')] ?? [];
                $answeredItems = (int) ($rowMetrics['answered_items'] ?? $row['answered'] ?? 0);
                $totalItems = (int) ($rowMetrics['total_items'] ?? $answeredItems);
                $correctItems = $rowMetrics['correct_items'] ?? null;
                $rows[] = [
                    (string) ($row['name'] ?? $row['scale'] ?? 'Escala'),
                    (string) ($row['scale'] ?? 'general'),
                    $supportsCorrectAnswers && $correctItems !== null ? (string) (int) $correctItems : '-',
                    $answeredItems . ' / ' . $totalItems,
                    $this->progressExportNumber($row['raw_score'] ?? $row['score'] ?? 0),
                    array_key_exists('adjusted_score', $row) && $row['adjusted_score'] !== null ? $this->progressExportNumber($row['adjusted_score']) : '-',
                    (string) ($row['transformed_score'] ?? '-'),
                    (string) ($row['percentile'] ?? '-'),
                    (string) ($row['classification'] ?? '-'),
                ];
            }
        }

        $filename = sprintf(
            'resultado_%s_%s_%s.xlsx',
            $this->resultExportFilenamePart((string) ($session['instrument_code'] ?? 'evaluacion')),
            $this->resultExportFilenamePart((string) ($session['user_rut'] ?? $session['user_name'] ?? 'usuario')),
            date('Ymd_His')
        );

        download_xlsx_workbook($filename, [[
            'title' => (string) ($session['instrument_name'] ?? 'Resultado'),
            'rows' => $rows,
        ]], 'Resultado evaluacion');
    }

    private function progressExportResultColumns(array $session, array $summary, array $resultContext): array
    {
        $columns = [];
        if ($this->progressExportUsesScaleColumns($session)) {
            foreach ($summary as $row) {
                $header = $this->progressExportHeader((string) ($row['name'] ?? $row['scale'] ?? 'Escala'));
                if ((string) ($session['instrument_code'] ?? '') === 'ticl_barratt') {
                    $header .= ' - Puntaje bruto';
                }
                $columns[$header] = $this->progressExportScaleValue($session, $row);
            }

            return $columns;
        }

        $scaleMetrics = $resultContext['scale_metrics'] ?? [];
        $supportsCorrectAnswers = !empty($resultContext['supports_correct_answers']);
        foreach ($summary as $row) {
            $scaleName = $this->progressExportHeader((string) ($row['name'] ?? $row['scale'] ?? 'Escala'));
            $metricKey = !empty($row['scale_id']) ? 'id:' . (int) $row['scale_id'] : 'scale:' . (string) ($row['scale'] ?? 'general');
            $rowMetrics = $scaleMetrics[$metricKey] ?? $scaleMetrics['scale:' . (string) ($row['scale'] ?? 'general')] ?? [];
            $answeredItems = (int) ($rowMetrics['answered_items'] ?? $row['answered'] ?? 0);
            $totalItems = (int) ($rowMetrics['total_items'] ?? $answeredItems);
            $correctItems = $rowMetrics['correct_items'] ?? null;

            $columns[$scaleName . ' - Respuestas Correctas'] = $supportsCorrectAnswers && $correctItems !== null ? (string) (int) $correctItems : '-';
            $columns[$scaleName . ' - Respondidos'] = $answeredItems . ' / ' . $totalItems;
            $columns[$scaleName . ' - Puntaje bruto'] = $this->progressExportNumber($row['raw_score'] ?? $row['score'] ?? 0);
            $columns[$scaleName . ' - Puntaje ajustado'] = array_key_exists('adjusted_score', $row) && $row['adjusted_score'] !== null
                ? $this->progressExportNumber($row['adjusted_score'])
                : '-';
            $columns[$scaleName . ' - Transformado'] = (string) ($row['transformed_score'] ?? '-');
            $columns[$scaleName . ' - Percentil'] = (string) ($row['percentile'] ?? '-');
            $columns[$scaleName . ' - Clasificacion'] = (string) ($row['classification'] ?? '-');
        }

        return $columns;
    }

    private function progressExportUsesScaleColumns(array $session): bool
    {
        return in_array((string) ($session['instrument_code'] ?? ''), ['ticl_barratt', 'ipip_16pf', 'riasec'], true);
    }

    private function progressExportScaleValue(array $session, array $row): string
    {
        $fields = (string) ($session['instrument_code'] ?? '') === 'ticl_barratt'
            ? ['raw_score', 'score', 'adjusted_score', 'transformed_score']
            : ['transformed_score', 'adjusted_score', 'raw_score', 'score'];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                continue;
            }

            return $this->progressExportNumber($row[$field]);
        }

        return '-';
    }

    private function progressExportNumber($value): string
    {
        return is_numeric($value) ? (string) round((float) $value, 2) : (string) $value;
    }

    private function progressExportHeader(string $header): string
    {
        $header = trim(preg_replace('/\s+/', ' ', $header) ?? '');
        return $header !== '' ? $header : 'Resultado';
    }

    private function resultExportStatusLabel(string $status): string
    {
        return [
            'assigned' => 'Asignada',
            'in_progress' => 'En curso',
            'completed' => 'Completada',
            'expired' => 'Expirada',
            'cancelled' => 'Cancelada',
        ][$status] ?? labelize($status);
    }

    private function resultExportFilenamePart(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '_', $value) ?? '';
        $value = trim($value, '_');
        return $value !== '' ? substr($value, 0, 40) : 'resultado';
    }

    private function answerExportQuestionHeader(array $item, int $questionNumber): string
    {
        $prompt = $this->answerExportPlainText((string) ($item['prompt'] ?? ''));
        if ($prompt === '') {
            $prompt = (string) ($item['item_key'] ?? '');
        }

        $prompt = trim(preg_replace('/\s+/', ' ', $prompt) ?? '');
        if ($prompt === '') {
            $prompt = 'Pregunta ' . $questionNumber;
        }

        return mb_substr($prompt, 0, 240);
    }

    private function answerExportPlainText(string $value): string
    {
        $htmlPrefix = '__html64__:';
        $blocksPrefix = '__blocks64__:';
        if (strpos($value, $htmlPrefix) === 0) {
            $decoded = base64_decode(substr($value, strlen($htmlPrefix)), true);
            $value = $decoded !== false ? $decoded : '';
        } elseif (strpos($value, $blocksPrefix) === 0) {
            $decoded = base64_decode(substr($value, strlen($blocksPrefix)), true);
            $blocks = $decoded !== false ? json_decode($decoded, true) : null;
            if (is_array($blocks)) {
                $parts = [];
                foreach ($blocks as $block) {
                    if (($block['type'] ?? '') === 'text' && trim((string) ($block['text'] ?? '')) !== '') {
                        $parts[] = trim((string) $block['text']);
                    }
                }
                $value = implode(' ', $parts);
            }
        }

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8'));
    }

    private function answerExportDisplayValue(string $answer, array $item): string
    {
        $answer = trim($answer);
        if ($answer === '') {
            return '';
        }

        $itemType = (string) ($item['item_type'] ?? '');
        if (!in_array($itemType, ['likert', 'single_choice', 'multiple_choice'], true)) {
            return $answer;
        }

        $options = $this->answerExportOptionPairs((string) ($item['options'] ?? ''));
        if (!$options) {
            return $answer;
        }

        $values = $itemType === 'multiple_choice'
            ? array_values(array_filter(array_map('trim', explode(',', $answer)), static fn(string $value): bool => $value !== ''))
            : [$answer];

        $labels = [];
        foreach ($values as $value) {
            $labels[] = $options[$value] ?? $value;
        }

        return implode(', ', $labels);
    }

    private function answerExportOptionPairs(string $options): array
    {
        $pairs = [];
        foreach (explode(';', $options) as $option) {
            [$value, $label] = array_pad(array_map('trim', explode('=', $option, 2)), 2, null);
            if ($value !== null && $value !== '') {
                $pairs[$value] = $label ?: $value;
            }
        }

        return $pairs;
    }

    private function answerExportSite(array $session, array $dashboardFields): string
    {
        $dynamicFields = $session['dynamic_fields'] ?? [];
        if (!is_array($dynamicFields)) {
            return '';
        }

        foreach (['sede', 'site', 'sucursal', 'base', 'ubicacion', 'lugar', 'recinto'] as $key) {
            $value = trim((string) ($dynamicFields[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        foreach ($dashboardFields as $field) {
            $fieldKey = (string) ($field['field_key'] ?? '');
            $label = mb_strtolower(trim((string) ($field['label'] ?? '')));
            if ($fieldKey === '' || !in_array($label, ['sede', 'site', 'sucursal'], true)) {
                continue;
            }

            $value = trim((string) ($dynamicFields[$fieldKey] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function logAnswerActivity(int $sessionId, int $userId, array $answers, array $items, int $currentBlock, ?int $remainingSeconds): void
    {
        if (!$items) {
            return;
        }

        $answeredItemIds = [];
        foreach ($items as $item) {
            $itemId = (int) $item['id'];
            if (!array_key_exists($itemId, $answers)) {
                continue;
            }
            $raw = $answers[$itemId];
            $hasAnswer = is_array($raw)
                ? count(array_filter($raw, static fn($value): bool => trim((string) $value) !== '')) > 0
                : trim((string) $raw) !== '';
            if ($hasAnswer) {
                $answeredItemIds[] = $itemId;
            }
        }

        if (!$answeredItemIds) {
            return;
        }

        $changedItemIds = $this->sessions->changedAnswerItemIds($sessionId, $answers, $items);
        $metadata = [
            'block' => $currentBlock,
            'answered_count' => count($answeredItemIds),
            'item_ids' => $answeredItemIds,
            'changed_count' => count($changedItemIds),
            'changed_item_ids' => $changedItemIds,
            'remaining_seconds' => $remainingSeconds,
        ];

        $this->sessions->logActivity(
            $sessionId,
            $userId,
            $changedItemIds ? 'answer_changed' : 'answer_saved',
            $metadata,
            count($answeredItemIds) === 1 ? (int) $answeredItemIds[0] : null,
            $currentBlock
        );
    }

    private function isAjaxRequest(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || strpos($accept, 'application/json') !== false;
    }

    private function blockForFirstMissingItem(array $items, array $missing, int $blockSize): int
    {
        $firstMissing = (int) reset($missing);
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) === $firstMissing) {
                return (int) floor($index / max(1, $blockSize)) + 1;
            }
        }

        return 1;
    }

    private function handleUnavailableProcessSession(int $sessionId, array $session, array $availability): void
    {
        $reason = (string) ($availability['reason'] ?? '');
        $message = (string) ($availability['message'] ?? $availability['label'] ?? 'La evaluacion no esta disponible en este momento.');

        if ($this->isAjaxRequest()) {
            $this->jsonResponse([
                'ok' => false,
                'message' => $message,
                'reason' => $reason,
                'redirect_url' => route_url('my-tests'),
            ], 403);
            return;
        }

        flash('warning', $message);
        redirect(route_url('my-tests'));
    }

    private function expireProcessSessionWithAnswers(int $sessionId, int $userId, array $session, array $answers, array $itemsToSave, int $currentBlock, ?int $remainingSeconds, array $availability, array $answerTrace = [], string $action = 'process_expired'): void
    {
        $reason = (string) ($availability['reason'] ?? 'process_ended');
        $message = (string) ($availability['message'] ?? 'Ya termino el tiempo para el proceso completo');
        $items = $this->sessions->itemsForSession($sessionId);

        $this->logAnswerActivity($sessionId, $userId, $answers, $items, $currentBlock, $remainingSeconds);
        $saveResult = $this->sessions->saveAnswersPayloadForSession($sessionId, $answers);
        $this->sessions->logActivity($sessionId, $userId, 'evaluation_expired', [
            'block' => $currentBlock,
            'remaining_seconds' => (int) ($remainingSeconds ?? 0),
            'process_remaining_seconds' => (int) ($availability['remaining_seconds'] ?? 0),
            'reason' => $reason,
            'source' => 'process_deadline',
            'received_answer_item_ids' => $saveResult['received_item_ids'] ?? [],
            'saved_answer_item_ids' => $saveResult['saved_item_ids'] ?? [],
            'answer_trace' => $this->buildAnswerTrace($answerTrace, $answers, $saveResult, $action, $currentBlock),
        ], null, $currentBlock);
        $this->sessions->complete($sessionId, [], 'expired');

        if ($this->isAjaxRequest()) {
            $this->jsonResponse([
                'ok' => false,
                'saved' => true,
                'final_status' => 'expired',
                'message' => $message,
                'reason' => $reason,
                'received_answer_item_ids' => $saveResult['received_item_ids'] ?? [],
                'saved_answer_item_ids' => $saveResult['saved_item_ids'] ?? [],
                'redirect_url' => route_url('my-tests'),
            ], 403);
            return;
        }

        flash('warning', $message);
        redirect(route_url('my-tests'));
    }

    private function mergeAnswerSnapshot(array $answers, $snapshotJson): array
    {
        if (!is_string($snapshotJson) || trim($snapshotJson) === '') {
            return $answers;
        }

        $snapshot = json_decode($snapshotJson, true);
        if (!is_array($snapshot)) {
            return $answers;
        }

        foreach ($snapshot as $itemId => $value) {
            $key = (int) $itemId;
            if ($key <= 0) {
                continue;
            }

            if (is_array($value)) {
                $cleanValues = array_values(array_filter(array_map('strval', $value), static fn(string $entry): bool => trim($entry) !== ''));
                if ($cleanValues !== []) {
                    $answers[$key] = $cleanValues;
                }
                continue;
            }

            $cleanValue = trim((string) $value);
            if ($cleanValue !== '') {
                $answers[$key] = $cleanValue;
            }
        }

        return $answers;
    }

    private function answerSnapshotTrace(array $postAnswers, $snapshotJson): array
    {
        $snapshot = is_string($snapshotJson) && trim($snapshotJson) !== ''
            ? json_decode($snapshotJson, true)
            : null;
        $snapshot = is_array($snapshot) ? $snapshot : [];

        return [
            'enabled' => true,
            'logged_at' => date('Y-m-d H:i:s'),
            'post_item_ids' => $this->answerItemIds($postAnswers),
            'snapshot_item_ids' => $this->answerItemIds($snapshot),
            'snapshot_json_length' => is_string($snapshotJson) ? strlen($snapshotJson) : 0,
        ];
    }

    private function buildAnswerTrace(array $trace, array $mergedAnswers, array $saveResult, string $action, int $currentBlock): array
    {
        $mergedItemIds = $this->answerItemIds($mergedAnswers);
        $savedItemIds = array_map('intval', $saveResult['saved_item_ids'] ?? []);

        return array_merge($trace, [
            'action' => $action,
            'block' => $currentBlock,
            'merged_item_ids' => $mergedItemIds,
            'saved_item_ids' => $savedItemIds,
            'post_count' => count($trace['post_item_ids'] ?? []),
            'snapshot_count' => count($trace['snapshot_item_ids'] ?? []),
            'merged_count' => count($mergedItemIds),
            'saved_count' => count($savedItemIds),
        ]);
    }

    private function answerItemIds(array $answers): array
    {
        $itemIds = [];
        foreach ($answers as $itemId => $value) {
            $itemId = (int) $itemId;
            if ($itemId <= 0) {
                continue;
            }

            $hasAnswer = is_array($value)
                ? count(array_filter($value, static fn($entry): bool => trim((string) $entry) !== '')) > 0
                : trim((string) $value) !== '';
            if ($hasAnswer) {
                $itemIds[] = $itemId;
            }
        }

        sort($itemIds);

        return array_values(array_unique($itemIds));
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    private function safeRedirectPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || strpos($path, '//') === 0 || strpos($path, '://') !== false) {
            return route_url('tests.progress-ranking');
        }

        return $path[0] === '/' ? $path : '/' . ltrim($path, '/');
    }

    private function redirectWithPreset(string $path, int $presetId): string
    {
        $parts = parse_url($path);
        $routePath = (string) ($parts['path'] ?? route_url('tests.progress-ranking'));
        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query['ranking_config'], $query['ranking_preset']);
        $query['ranking_preset_id'] = $presetId;
        $query['ranking_load_preset'] = '1';
        $queryString = http_build_query($query);

        return $routePath . ($queryString !== '' ? '?' . $queryString : '');
    }

    private function pendingAutoStartSession(array $sessions): ?array
    {
        $pending = array_values(array_filter($sessions, static function (array $session): bool {
            if ((int) ($session['auto_start_enabled'] ?? 0) !== 1) {
                return false;
            }
            if (!in_array((string) ($session['status'] ?? ''), ['assigned', 'in_progress'], true)) {
                return false;
            }

            $availability = $session['process_availability'] ?? ['allowed' => true];
            return !empty($availability['allowed']);
        }));

        if (!$pending) {
            return null;
        }

        usort($pending, static function (array $left, array $right): int {
            $orderCompare = ((int) ($left['auto_start_order'] ?? 100)) <=> ((int) ($right['auto_start_order'] ?? 100));
            if ($orderCompare !== 0) {
                return $orderCompare;
            }

            $leftStarted = (string) ($left['started_at'] ?? '') !== '' ? 0 : 1;
            $rightStarted = (string) ($right['started_at'] ?? '') !== '' ? 0 : 1;
            if ($leftStarted !== $rightStarted) {
                return $leftStarted <=> $rightStarted;
            }

            return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
        });

        return $pending[0];
    }

    private function assignedTestsFinalized(array $sessions): bool
    {
        $hasFinishedAssignedTest = false;

        foreach ($sessions as $session) {
            $status = (string) ($session['status'] ?? '');
            if (in_array($status, ['completed', 'expired'], true)) {
                $hasFinishedAssignedTest = true;
                continue;
            }

            if ($status === 'cancelled') {
                continue;
            }

            if ($status !== '') {
                return false;
            }
        }

        return $hasFinishedAssignedTest;
    }
}
