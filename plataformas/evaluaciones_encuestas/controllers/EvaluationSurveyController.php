<?php
declare(strict_types=1);

final class EvaluationSurveyController extends Controller
{
    private EvaluationSurveyFormModel $forms;
    private EvaluationSurveyAttemptModel $attempts;
    private EvaluationSurveySettingsModel $settings;
    private EvaluationSurveyControlModel $control;
    private EvaluationSurveyMediaEvidenceModel $mediaEvidence;
    private TestSettingsModel $testSettings;
    private EvaluationSurveyDashboardModel $dashboard;
    private TestSessionModel $testSessions;
    private EvaluationSurveyIntegrityReportPdfService $integrityPdf;
    private MoodleEvaluationImportService $moodleImport;

    public function __construct(?Template $view = null, ?EvaluationSurveyFormModel $forms = null, ?EvaluationSurveyAttemptModel $attempts = null)
    {
        parent::__construct($view);
        $this->forms = $forms ?: new EvaluationSurveyFormModel();
        $this->attempts = $attempts ?: new EvaluationSurveyAttemptModel();
        $this->settings = new EvaluationSurveySettingsModel();
        $this->control = new EvaluationSurveyControlModel();
        $this->mediaEvidence = new EvaluationSurveyMediaEvidenceModel();
        $this->testSettings = new TestSettingsModel();
        $this->dashboard = new EvaluationSurveyDashboardModel();
        $this->testSessions = new TestSessionModel();
        $this->integrityPdf = new EvaluationSurveyIntegrityReportPdfService();
        $this->moodleImport = new MoodleEvaluationImportService();
    }

    public function dashboard(): void
    {
        $this->requireDashboardAccess();
        $companyId = $this->isGlobalEvaluationAdmin() ? null : (int) (current_user()['company_id'] ?? 0);
        $data = $this->dashboard->dashboard($companyId > 0 ? $companyId : null);
        $this->render('evaluaciones_encuestas/dashboard', [
            'title' => 'Dashboard de evaluaciones | e-talent',
            'currentPage' => 'evaluation-surveys.dashboard',
            'summary' => $data['summary'],
            'rows' => $data['rows'],
            'isCompanyScope' => $companyId !== null,
        ]);
    }

    public function dashboardSummaryExport(): void
    {
        $this->requireDashboardAccess();
        $companyId = $this->isGlobalEvaluationAdmin() ? null : (int) (current_user()['company_id'] ?? 0);
        $rows = $this->attempts->dashboardSummaryExportData($companyId > 0 ? $companyId : null);
        $exportRows = [['Evaluación', 'Proceso', 'RUT', 'Nombre completo', 'Correo', 'Estado del intento', 'Preguntas', 'Buenas', 'Malas', 'Omitidas', 'Nota', 'Estado de aprobación']];
        foreach ($rows as $row) {
            $finalScore = $row['final_score'] === null ? null : (float) $row['final_score'];
            $passingScore = $row['passing_score'] === null ? null : (float) $row['passing_score'];
            $approval = $finalScore === null || $passingScore === null
                ? 'Sin umbral'
                : ($finalScore >= $passingScore ? 'Aprobada' : 'Reprobada');
            $exportRows[] = [
                (string) ($row['evaluation_name'] ?? 'Evaluación'),
                (string) ($row['process_name'] ?? 'Sin proceso identificado'),
                (string) ($row['rut'] ?? ''),
                (string) ($row['user_name'] ?? 'Persona'),
                (string) ($row['user_email'] ?? ''),
                $this->evaluationExportAttemptStatusLabel($row),
                (int) ($row['total_questions'] ?? 0),
                (int) ($row['correct_answers'] ?? 0),
                (int) ($row['incorrect_answers'] ?? 0),
                max(0, (int) ($row['total_questions'] ?? 0) - (int) ($row['answered_answers'] ?? 0)),
                $finalScore === null ? '' : $finalScore,
                $approval,
            ];
        }

        $headerStyle = ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF123B4D']], 'alignment' => ['vertical' => 'center', 'wrapText' => true]];
        download_xlsx_workbook('evaluaciones-resumen-dashboard.xlsx', [[
            'title' => 'Resumen',
            'rows' => $exportRows,
            'wrapText' => true,
            'columnWidths' => [34, 38, 18, 34, 36, 18, 12, 10, 10, 10, 12, 20],
            'cellStyles' => ['A1' => $headerStyle],
        ]], 'Resumen de evaluaciones | e-talent');
    }

    public function dashboardIntegrityReport(): void
    {
        $this->requireDashboardAccess();
        $companyId = $this->isGlobalEvaluationAdmin() ? null : (int) (current_user()['company_id'] ?? 0);
        $domain = (string) ($_GET['domain'] ?? 'evaluations') === 'tests' ? 'tests' : 'evaluations';
        $processIds = $this->requestPositiveIntList('process_id');
        $formIds = $this->requestPositiveIntList('form_id');
        $testIds = $this->requestPositiveIntList('test_id');
        $dateRange = $this->integrityDateRange();
        $data = $this->dashboard->integrityReport($companyId > 0 ? $companyId : null, $processIds ?: null, $formIds ?: null, $testIds ?: null, $domain, $dateRange['from'], $dateRange['until']);
        $filterOptions = $this->dashboard->integrityFilterOptions($companyId > 0 ? $companyId : null, $domain);
        $this->render('evaluaciones_encuestas/dashboard_integrity', [
            'title' => ($domain === 'tests' ? 'Incidencias de tests' : 'Incidencias de evaluaciones') . ' | e-talent',
            'currentPage' => $domain === 'tests'
                ? 'client-admin.test-incidents'
                : ($this->isGlobalEvaluationAdmin() ? 'evaluation-surveys.dashboard' : 'client-admin.evaluation-incidents'),
            'reportDomain' => $domain,
            'dateFrom' => $dateRange['from'], 'dateTo' => $dateRange['through'],
            'summary' => $data['summary'],
            'rows' => $data['rows'],
            'processIds' => $processIds,
            'formIds' => $formIds,
            'testIds' => $testIds,
            'isCompanyScope' => $companyId !== null,
            'filterOptions' => $filterOptions,
            'useSelect2' => true,
        ]);
    }

    public function dashboardIntegrityReportPdf(): void
    {
        $this->requireDashboardAccess();
        $companyId = $this->isGlobalEvaluationAdmin() ? null : (int) (current_user()['company_id'] ?? 0);
        $processIds = $this->requestPositiveIntList('process_id');
        $formIds = $this->requestPositiveIntList('form_id');
        $testIds = $this->requestPositiveIntList('test_id');
        $domain = (string) ($_GET['domain'] ?? 'evaluations') === 'tests' ? 'tests' : 'evaluations';
        $dateRange = $this->integrityDateRange();
        $data = $this->dashboard->integrityReport($companyId > 0 ? $companyId : null, $processIds ?: null, $formIds ?: null, $testIds ?: null, $domain, $dateRange['from'], $dateRange['until']);
        $pdf = $this->integrityPdf->render($data);
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        $filename = $domain === 'tests' ? 'reporte-incidencias-tests-psicolaborales.pdf' : $this->integrityPdf->filename();
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, no-store');
        echo $pdf;
        exit;
    }

    public function dashboardIntegrityReportExcel(): void
    {
        $this->requireDashboardAccess();
        $companyId = $this->isGlobalEvaluationAdmin() ? null : (int) (current_user()['company_id'] ?? 0);
        $processIds = $this->requestPositiveIntList('process_id');
        $formIds = $this->requestPositiveIntList('form_id');
        $testIds = $this->requestPositiveIntList('test_id');
        $domain = (string) ($_GET['domain'] ?? 'evaluations') === 'tests' ? 'tests' : 'evaluations';
        $dateRange = $this->integrityDateRange();
        $data = $this->dashboard->integrityReport($companyId > 0 ? $companyId : null, $processIds ?: null, $formIds ?: null, $testIds ?: null, $domain, $dateRange['from'], $dateRange['until']);
        $workbook = $this->buildIntegrityExportWorkbook($data);
        $filename = $domain === 'tests' ? 'reporte-ejecutivo-incidencias-tests.xlsx' : 'reporte-ejecutivo-incidencias-evaluaciones.xlsx';
        $title = $domain === 'tests' ? 'Incidencias tests psicolaborales | e-talent' : 'Incidencias evaluaciones | e-talent';
        download_xlsx_workbook($filename, $workbook, $title);
    }

    private function buildIntegrityExportWorkbook(array $data): array
    {
        $summary = (array) ($data['summary'] ?? []);
        $rows = array_values(array_filter((array) ($data['rows'] ?? []), static fn(array $row): bool => ($row['alert_level'] ?? 'none') !== 'none'));
        $groups = [];
        foreach ($rows as $row) {
            $key = (string) ($row['process_id'] ?? '') . ':' . (string) ($row['form_id'] ?? '');
            if (!isset($groups[$key])) {
                $groups[$key] = ['form_title' => (string) ($row['form_title'] ?? 'Evaluación'), 'process_name' => (string) ($row['process_name'] ?? 'Proceso'), 'people' => 0, 'high' => 0, 'review' => 0, 'incidents' => 0, 'types' => []];
            }
            $groups[$key]['people']++;
            $groups[$key]['high'] += ($row['alert_level'] ?? '') === 'high' ? 1 : 0;
            $groups[$key]['review'] += ($row['alert_level'] ?? '') === 'review' ? 1 : 0;
            $groups[$key]['incidents'] += (int) ($row['incident_total'] ?? 0);
            foreach ((array) ($row['signal_types'] ?? []) as $type) {
                $label = $this->integritySignalLabel((string) $type);
                if ($label !== '') $groups[$key]['types'][$label] = ($groups[$key]['types'][$label] ?? 0) + 1;
            }
        }

        $summaryRows = [
            ['REPORTE EJECUTIVO DE INCIDENCIAS | E_TALENT'],
            ['Se analizan únicamente asignaciones activas de evaluaciones con proceso asignado. El reporte orienta una revisión autorizada y no determina por sí solo una conducta.'],
            [],
            ['RESUMEN GENERAL'],
            ['Indicador', 'Valor'],
            ['Asignaciones activas', (string) ((int) ($summary['assigned_people'] ?? 0))],
            ['Personas distintas asignadas', (string) ((int) ($summary['distinct_assigned_people'] ?? 0))],
            ['Asignaciones con intento', (string) ((int) ($summary['attempted_people'] ?? 0))],
            ['Asignaciones con incidencias clasificadas', (string) ((int) ($summary['people_with_incidents'] ?? 0))],
            ['Casos que requieren revisión', (string) ((int) ($summary['review_cases'] ?? 0))],
            ['Alertas altas', (string) ((int) ($summary['high_alert_cases'] ?? 0))],
            ['Eventos de actividad', (string) ((int) ($summary['activity_events'] ?? 0))],
            ['Eventos audiovisuales', (string) ((int) ($summary['media_events'] ?? 0))],
            [],
            ['CÓMO LEER LA TIPIFICACIÓN'],
            ['ALERTA ALTA', 'Existe al menos una señal clasificada como riesgo. Es el primer bloque que debe priorizarse.'],
            ['REVISIÓN', 'Existe una señal aislada o técnica que debe validarse en contexto.'],
            ['Criterio', 'La clasificación orienta la revisión humana; no constituye por sí sola una conclusión de fraude.'],
        ];
        $groupRows = [['EVALUACIÓN', 'PROCESO', 'PERSONAS AFECTADAS', 'ALERTAS ALTAS', 'REQUIEREN REVISIÓN', 'SEÑALES REGISTRADAS', 'INCIDENCIAS CLASIFICADAS']];
        foreach ($groups as $group) {
            arsort($group['types']);
            $types = implode(', ', array_map(static fn(string $label, int $count): string => $label . ' (' . $count . ')', array_keys($group['types']), $group['types']));
            $groupRows[] = [$group['form_title'], $group['process_name'], (string) $group['people'], (string) $group['high'], (string) $group['review'], (string) $group['incidents'], $types ?: 'Sin tipificación'];
        }
        $detailRows = [['NIVEL', 'RUT', 'PERSONA', 'CORREO', 'ID INTENTO', 'INICIO', 'FINALIZACIÓN', 'EVALUACIÓN', 'PROCESO', 'PONER ATENCIÓN EN', 'INCIDENCIAS CLASIFICADAS', 'SEÑALES', 'ESTADO DEL INTENTO']];
        foreach ($rows as $row) {
            $labels = [];
            foreach ((array) ($row['signal_types'] ?? []) as $type) {
                $label = $this->integritySignalLabel((string) $type);
                if ($label !== '') $labels[] = $label;
            }
            $labels = array_values(array_unique($labels));
            $isHigh = ($row['alert_level'] ?? '') === 'high';
            $detailRows[] = [$isHigh ? 'ALERTA ALTA' : 'REVISIÓN', (string) ($row['rut'] ?? ''), (string) ($row['user_name'] ?? 'Persona'), (string) ($row['user_email'] ?? ''), (string) ($row['attempt_id'] ?? ''), (string) ($row['attempt_started_at'] ?? ''), (string) ($row['completed_at'] ?? ''), (string) ($row['form_title'] ?? 'Evaluación'), (string) ($row['process_name'] ?? 'Proceso'), $isHigh ? 'Presenta señales clasificadas como riesgo: ' . implode(', ', $labels) : 'Presenta señales aisladas o técnicas que requieren validación: ' . implode(', ', $labels), (string) ((int) ($row['incident_total'] ?? 0)), implode(', ', $labels), (string) ($row['attempt_status'] ?? 'Sin intento')];
        }

        $headerStyle = ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF123B4D']], 'alignment' => ['vertical' => 'center', 'wrapText' => true]];
        $titleStyle = ['font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF123B4D']]];
        $sectionStyle = ['font' => ['bold' => true, 'color' => ['argb' => 'FF123B4D']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFEAF3F5']]];
        return [
            ['title' => 'Resumen ejecutivo', 'rows' => $summaryRows, 'wrapText' => true, 'columnWidths' => [38, 100], 'cellStyles' => ['A1' => $titleStyle, 'A4' => $sectionStyle, 'A5' => $headerStyle, 'A15' => $sectionStyle]],
            ['title' => 'Evaluación - proceso', 'rows' => $groupRows, 'wrapText' => true, 'columnWidths' => [34, 42, 18, 16, 20, 18, 75], 'cellStyles' => ['A1' => $headerStyle]],
            ['title' => 'Detalle focalizado', 'rows' => $detailRows, 'wrapText' => true, 'columnWidths' => [16, 18, 34, 36, 12, 20, 20, 36, 42, 75, 55, 12, 18], 'cellStyles' => ['A1' => $headerStyle]],
        ];
    }

    private function integritySignalLabel(string $type): string
    {
        $labels = ['tab_hidden' => 'Cambio de pestaña', 'window_blurred' => 'Pérdida de foco', 'fullscreen_exited' => 'Salida de pantalla completa', 'fullscreen_denied' => 'Pantalla completa denegada', 'fullscreen_failed' => 'Falla de pantalla completa', 'suspicious_key_printscreen' => 'Captura de pantalla', 'suspicious_key_print' => 'Intento de impresión', 'suspicious_key_copy' => 'Intento de copia', 'suspicious_key_save' => 'Intento de guardado', 'suspicious_key_devtools' => 'Herramientas de desarrollo', 'multiple_voice_possible' => 'Posibles voces múltiples', 'audio_visual_risk' => 'Riesgo audiovisual', 'audio_visual_capture_failed' => 'Falla de captura audiovisual', 'audio_visual_upload_failed' => 'Falla de carga audiovisual', 'audio_visual_screen_failed' => 'Falla de captura de pantalla', 'audio_visual_recording_interrupted' => 'Interrupción de grabación', 'screen_capture_upload_failed' => 'Falla de carga de pantalla', 'recording_upload_failed' => 'Falla de carga de grabación', 'finalize_failed' => 'Falla de finalización'];
        $labels += ['fullscreen_unavailable' => 'Pantalla completa no disponible', 'inactive_detected' => 'Inactividad detectada', 'copy_blocked' => 'Copia bloqueada', 'cut_blocked' => 'Corte bloqueado', 'paste_blocked' => 'Pegado bloqueado', 'print_blocked' => 'Impresión bloqueada', 'context_menu_blocked' => 'Menú contextual bloqueado', 'drag_blocked' => 'Arrastre bloqueado'];
        if (substr($type, 0, 9) === 'evidence_') return 'Evidencia ' . str_replace('_', ' ', substr($type, 9));
        return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    public function dashboardResults(): void
    {
        $this->requireDashboardAccess();
        $formId = request_secure_id('evaluation_survey_form');
        $processId = max(0, (int) ($_GET['process_id'] ?? 0));
        $form = $this->forms->findForm($formId);
        if (!$form || (string) ($form['form_type'] ?? '') !== 'assessment') {
            platform_error(404, 'Evaluación no encontrada.', ['chips' => ['Dashboard', 'Evaluaciones']]);
        }
        $companyId = $this->isGlobalEvaluationAdmin() ? null : (int) (current_user()['company_id'] ?? 0);
        $attempts = $this->attempts->attemptsForForm($formId, $companyId > 0 ? $companyId : null, $processId > 0 ? $processId : null);
        $this->render('evaluaciones_encuestas/dashboard_results', [
            'title' => 'Resultados de evaluación | e-talent',
            'currentPage' => 'evaluation-surveys.dashboard',
            'form' => $form,
            'attempts' => $attempts,
            'mediaRecoveryCandidates' => $this->mediaEvidence->recoveryCandidatesForAttempts(array_column($attempts, 'id')),
        ], (string) ($_GET['drawer'] ?? '') === '1' ? null : 'app');
    }

    public function dashboardResultsMediaRecovery(): void
    {
        $this->requireDashboardAccess();
        require_permission('manage_evaluation_surveys');
        verify_csrf();
        $formId = request_secure_id('evaluation_survey_form');
        $processId = max(0, (int) ($_POST['process_id'] ?? 0));
        $form = $this->forms->findForm($formId);
        if (!$form || (string) ($form['form_type'] ?? '') !== 'assessment') {
            platform_error(404, 'Evaluación no encontrada.', ['chips' => ['Dashboard', 'Evaluaciones']]);
        }
        $companyId = $this->isGlobalEvaluationAdmin() ? null : (int) (current_user()['company_id'] ?? 0);
        $attempts = $this->attempts->attemptsForForm($formId, $companyId > 0 ? $companyId : null, $processId > 0 ? $processId : null);
        $candidates = $this->mediaEvidence->recoveryCandidatesForAttempts(array_column($attempts, 'id'));
        $requested = [];
        $attemptToken = trim((string) ($_POST['attempt_sid'] ?? ''));
        if ($attemptToken !== '') {
            $attemptId = secure_url_id($attemptToken, 'evaluation_survey_attempt');
            if ($attemptId > 0) $requested[] = $attemptId;
        } else {
            $requested = array_keys($candidates);
        }
        $requested = array_values(array_filter(array_map('intval', $requested), static fn(int $id): bool => isset($candidates[$id])));
        $result = $this->mediaEvidence->recoverForAttempts($requested, (int) current_user()['id']);
        $message = 'Evidencias recuperadas: ' . count($result['recovered']) . '.';
        if ($result['failed']) $message .= ' Fallidas: ' . count($result['failed']) . '.';
        flash($result['failed'] ? 'warning' : 'success', $message);
        $resultsUrl = route_url('evaluation-surveys.dashboard.results', $formId);
        if ($processId > 0) $resultsUrl .= '?process_id=' . $processId;
        redirect($resultsUrl);
    }

    public function dashboardResultsReprocess(): void
    {
        $this->requireDashboardAccess();
        require_permission('manage_evaluation_surveys');
        $formId = request_secure_id('evaluation_survey_form');
        $processId = max(0, (int) ($_GET['process_id'] ?? $_POST['process_id'] ?? 0));
        $form = $this->forms->findForm($formId);
        if (!$form || (string) ($form['form_type'] ?? '') !== 'assessment') {
            platform_error(404, 'Evaluación no encontrada.', ['chips' => ['Dashboard', 'Evaluaciones']]);
        }
        $companyId = $this->isGlobalEvaluationAdmin() ? null : (int) (current_user()['company_id'] ?? 0);
        $attemptToken = trim((string) ($_POST['attempt_sid'] ?? $_GET['attempt_sid'] ?? ''));
        $attemptId = $attemptToken !== '' ? secure_url_id($attemptToken, 'evaluation_survey_attempt') : 0;
        if ($attemptId <= 0) {
            platform_error(400, 'Intento de evaluación no válido.', ['chips' => ['Reprocesamiento']]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['confirm_reprocess'] ?? '') === '1') {
            verify_csrf();
            try {
                $attempt = $this->attempts->reprocessAttempt($form, $attemptId, $companyId > 0 ? $companyId : null, $processId > 0 ? $processId : null);
                flash('success', 'Evaluación reprocesada. Nota: ' . number_format((float) ($attempt['final_score'] ?? 0), 2, ',', '.') . '.');
            } catch (Throwable $exception) {
                flash('danger', $exception->getMessage());
            }
            $resultsUrl = route_url('evaluation-surveys.dashboard.results', $formId);
            if ($processId > 0) $resultsUrl .= '?process_id=' . $processId;
            redirect($resultsUrl);
        }

        $attempt = $this->attempts->reprocessableAttempt($attemptId, $formId, $companyId > 0 ? $companyId : null, $processId > 0 ? $processId : null);
        if (!$attempt) {
            platform_error(409, 'Este intento no cumple las condiciones de reprocesamiento: debe tener respuestas, no tener nota ni aprobación y continuar en curso.', ['chips' => ['Reprocesamiento']]);
        }
        $questions = (new EvaluationSurveyFormModel())->questionsForForm($formId, false);
        $attempt['question_count'] = count($questions);
        $attempt['pending_count'] = max(0, (int) $attempt['question_count'] - (int) ($attempt['answered_count'] ?? 0));
        $attempt['attempt_sid'] = secure_url_token($attemptId, 'evaluation_survey_attempt');
        $this->render('evaluaciones_encuestas/dashboard_reprocess', [
            'title' => 'Reprocesar evaluación | e-talent',
            'currentPage' => 'evaluation-surveys.dashboard',
            'form' => $form,
            'attempt' => $attempt,
            'formSid' => secure_url_token($formId, 'evaluation_survey_form'),
            'processId' => $processId,
        ]);
    }

    public function dashboardResultsExport(): void
    {
        $this->requireDashboardAccess();
        $formId = request_secure_id('evaluation_survey_form');
        $processId = max(0, (int) ($_GET['process_id'] ?? 0));
        $exportType = (string) ($_GET['type'] ?? 'detail');
        if (!in_array($exportType, ['detail', 'incorrect', 'summary'], true)) {
            platform_error(400, 'Tipo de exportación no válido.', ['chips' => ['Excel', 'Evaluaciones']]);
        }

        $form = $this->forms->findForm($formId);
        if (!$form || (string) ($form['form_type'] ?? '') !== 'assessment') {
            platform_error(404, 'Evaluación no encontrada.', ['chips' => ['Dashboard', 'Evaluaciones']]);
        }
        $companyId = $this->isGlobalEvaluationAdmin() ? null : (int) (current_user()['company_id'] ?? 0);
        $data = $this->attempts->exportDataForForm($form, $companyId > 0 ? $companyId : null, $processId > 0 ? $processId : null);
        $workbook = $this->buildEvaluationExportWorkbook($form, $data, $exportType);
        $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', (string) ($form['title'] ?? 'evaluacion')), '-');
        $typeLabel = ['detail' => 'respuestas', 'incorrect' => 'errores', 'summary' => 'resumen'][$exportType];
        download_xlsx_workbook('evaluacion-' . ($slug ?: 'reporte') . '-' . $typeLabel . '.xlsx', $workbook, 'Reporte de evaluación');
    }

    private function buildEvaluationExportWorkbook(array $form, array $data, string $exportType): array
    {
        $questions = array_values(array_filter($data['questions'] ?? [], static fn(array $question): bool => (int) ($question['is_active'] ?? 1) === 1));
        $answersByAttempt = $data['answers'] ?? [];
        $questionMap = [];
        foreach ($questions as $index => $question) {
            $questionMap[(int) $question['id']] = ['question' => $question, 'number' => $index + 1];
        }
        $headers = ['Persona', 'Proceso', 'Estado del intento'];
        if ($exportType === 'detail') {
            foreach ($questions as $index => $question) $headers[] = 'Pregunta ' . ($index + 1) . ': ' . trim((string) $question['question_text']);
            array_push($headers, 'Preguntas', 'Buenas', 'Malas', 'Nota', 'Estado de aprobación');
        } elseif ($exportType === 'incorrect') {
            $headers = ['Persona', 'Proceso', 'Estado del intento', 'N° pregunta', 'Pregunta', 'Respuesta seleccionada', 'Respuesta correcta'];
        } else {
            array_push($headers, 'Preguntas', 'Buenas', 'Malas', 'Nota', 'Estado de aprobación');
        }

        $rows = [$headers];
        $cellStyles = [];
        $green = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9EAD3']]];
        $red = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4CCCC']]];
        $yellow = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2CC']]];
        foreach (($data['attempts'] ?? []) as $attempt) {
            $attemptId = (int) ($attempt['id'] ?? 0);
            $answerRows = $answersByAttempt[$attemptId] ?? [];
            $good = 0; $bad = 0;
            foreach ($questions as $question) {
                $answer = $answerRows[(int) $question['id']] ?? [];
                if (($answer['answer_value'] ?? '') === '') continue;
                (float) ($answer['score_value'] ?? 0) > 0 ? $good++ : $bad++;
            }
            $status = $this->evaluationExportApprovalLabel($attempt, $form);
            $base = [(string) ($attempt['user_name'] ?? 'Persona'), (string) ($attempt['process_name'] ?? 'Sin proceso identificado'), $this->evaluationExportAttemptStatusLabel($attempt)];
            if ($exportType === 'incorrect') {
                foreach ($questions as $question) {
                    $answer = $answerRows[(int) $question['id']] ?? [];
                    if (($answer['answer_value'] ?? '') === '' || (float) ($answer['score_value'] ?? 0) > 0) continue;
                    $rows[] = array_merge($base, [
                        (int) ($questionMap[(int) $question['id']]['number'] ?? 0),
                        trim((string) $question['question_text']),
                        $this->evaluationExportAnswerLabel($question, $answer['answer_value'] ?? ''),
                        $this->evaluationExportCorrectLabel($question),
                    ]);
                }
                continue;
            }
            $row = $base;
            if ($exportType === 'detail') {
                foreach ($questions as $question) {
                    $answer = $answerRows[(int) $question['id']] ?? [];
                    $row[] = $this->evaluationExportAnswerLabel($question, $answer['answer_value'] ?? '');
                    $coordinate = $this->evaluationExportColumnName(count($row)) . (count($rows) + 1);
                    $cellStyles[$coordinate] = (($answer['answer_value'] ?? '') === '') ? $yellow : ((float) ($answer['score_value'] ?? 0) > 0 ? $green : $red);
                }
            }
            array_push($row, count($questions), $good, $bad, $attempt['final_score'] === null ? '' : (float) $attempt['final_score'], $status);
            $rows[] = $row;
        }
        return [['title' => $exportType === 'detail' ? 'Respuestas por persona' : ($exportType === 'incorrect' ? 'Respuestas erróneas' : 'Resumen'), 'rows' => $rows, 'cellStyles' => $cellStyles]];
    }

    private function evaluationExportApprovalLabel(array $attempt, array $form): string
    {
        if (($attempt['final_score'] ?? null) === null || ($form['passing_score'] ?? null) === null) return 'Sin umbral';
        return (float) $attempt['final_score'] >= (float) $form['passing_score'] ? 'Aprobada' : 'Reprobada';
    }

    private function evaluationExportAttemptStatusLabel(array $attempt): string
    {
        return [
            'assigned' => 'Asignada',
            'in_progress' => 'En curso',
            'completed' => 'Completada',
            'expired' => 'Expirada',
            'cancelled' => 'Cancelada',
        ][(string) ($attempt['status'] ?? '')] ?? 'No disponible';
    }

    private function evaluationExportAnswerLabel(array $question, $answer): string
    {
        $answer = trim((string) $answer);
        if ($answer === '') return 'Sin respuesta';
        if ((string) ($question['question_type'] ?? '') === 'matrix') {
            $values = json_decode($answer, true);
            if (is_array($values)) return implode(' | ', array_map(static fn($key, $value): string => $key . ': ' . $value, array_keys($values), $values));
        }
        $selected = array_filter(array_map('trim', explode(',', $answer)), static fn(string $value): bool => $value !== '');
        $labels = [];
        foreach (($question['options'] ?? []) as $option) if (in_array((string) $option['option_value'], $selected, true)) $labels[] = (string) $option['option_label'];
        return $labels ? implode(', ', $labels) : $answer;
    }

    private function evaluationExportCorrectLabel(array $question): string
    {
        $labels = [];
        foreach (($question['options'] ?? []) as $option) if ((float) ($option['score_value'] ?? 0) > 0) $labels[] = (string) $option['option_label'];
        return $labels ? implode(', ', $labels) : 'No disponible';
    }

    private function evaluationExportColumnName(int $number): string
    {
        $name = '';
        while ($number > 0) { $number--; $name = chr(65 + ($number % 26)) . $name; $number = intdiv($number, 26); }
        return $name;
    }

    public function aiSettings(): void
    {
        require_permission('manage_evaluation_surveys');
        $settings = $this->settings->aiSettings();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $this->settings->setAiSettings([
                'enabled' => isset($_POST['ai_enabled']), 'import_enabled' => isset($_POST['ai_import_enabled']),
                'generation_enabled' => isset($_POST['ai_generation_enabled']), 'require_review' => isset($_POST['ai_require_review']),
                'max_pdf_mb' => $_POST['max_pdf_mb'] ?? 20, 'max_pdf_pages' => $_POST['max_pdf_pages'] ?? 60,
                'max_pdf_text_chars' => $_POST['max_pdf_text_chars'] ?? 60000, 'max_questions' => $_POST['max_questions'] ?? 30,
                'default_difficulty' => $_POST['default_difficulty'] ?? 'medium',
            ]);
            flash('success', 'Configuración de IA para Encuestas / Evaluaciones guardada.');
            redirect(route_url('evaluation-surveys.ai.settings'));
        }
        $globalAi = (new PlatformSettingsModel())->aiSettings(load_config('integrations'));
        $this->render('evaluaciones_encuestas/ai_settings_form', ['title' => 'IA para Encuestas / Evaluaciones | e-talent', 'currentPage' => 'evaluation-surveys.ai.settings', 'backUrl' => route_url('evaluation-surveys.assessments'), 'settings' => $settings, 'globalAi' => $globalAi]);
    }

    public function aiGenerate(): void
    {
        require_permission('manage_evaluation_surveys');
        $settings = $this->settings->aiSettings();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $contextType = (string) ($_SESSION['evaluation_surveys_ai_form_type'] ?? 'assessment');
            if (!isset(EvaluationSurveyFormModel::FORM_TYPES[$contextType])) $contextType = 'assessment';
            $aiRequest = [
                'form_type' => $contextType,
                'operation' => in_array((string) ($_POST['operation'] ?? 'import'), ['import', 'generate'], true) ? (string) $_POST['operation'] : 'import',
                'question_count' => max(1, min((int) $settings['max_questions'], (int) ($_POST['question_count'] ?? 10))),
                'difficulty' => in_array((string) ($_POST['difficulty'] ?? $settings['default_difficulty']), ['easy', 'medium', 'hard'], true) ? (string) $_POST['difficulty'] : $settings['default_difficulty'],
            ];
            $file = $_FILES['pdf'] ?? null;
            $uploadError = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            $uploadMessages = [
                UPLOAD_ERR_INI_SIZE => 'El PDF supera el límite de carga configurado por el servidor.',
                UPLOAD_ERR_FORM_SIZE => 'El PDF supera el límite permitido por el formulario.',
                UPLOAD_ERR_PARTIAL => 'La carga del PDF quedó incompleta. Inténtalo nuevamente.',
                UPLOAD_ERR_NO_FILE => 'Selecciona un archivo PDF antes de generar el borrador.',
                UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene disponible el directorio temporal de carga.',
                UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar temporalmente el PDF.',
                UPLOAD_ERR_EXTENSION => 'Una extensión del servidor detuvo la carga del PDF.',
            ];
            if (!is_array($file) || $uploadError !== UPLOAD_ERR_OK) { flash('danger', $uploadMessages[$uploadError] ?? 'No fue posible cargar el PDF.'); redirect($this->aiGenerateUrl($aiRequest)); }
            $tmp = (string) ($file['tmp_name'] ?? '');
            $signature = is_file($tmp) ? (string) file_get_contents($tmp, false, null, 0, 5) : '';
            $mime = function_exists('finfo_open') && is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
            if ($signature !== '%PDF-' || ($mime !== '' && $mime !== 'application/pdf' && $mime !== 'application/octet-stream')) { flash('danger', 'El archivo cargado no parece ser un PDF válido.'); redirect($this->aiGenerateUrl($aiRequest)); }
            $result = (new EvaluationSurveyAiService())->generateFromPdf($tmp, $aiRequest['operation'], $aiRequest['form_type'], ['question_count' => $aiRequest['question_count'], 'difficulty' => $aiRequest['difficulty'], 'original_filename' => (string) ($file['name'] ?? '')], (int) current_user()['id']);
            if (!$result['ok']) { flash(($result['code'] ?? '') === 'pdf_import_error' ? 'pdf_import_error' : 'danger', (string) $result['message']); redirect($this->aiGenerateUrl($aiRequest)); }
            $message = 'Borrador generado con ' . (int) $result['questions'] . ' pregunta(s). Revísalo antes de publicarlo.';
            if (!empty($result['warnings'])) $message .= ' Algunas preguntas requieren revisión adicional.';
            flash('success', $message);
            redirect(route_url('evaluation-surveys.form.edit', (int) $result['form_id']));
        }
        $formType = in_array((string) ($_GET['form_type'] ?? 'assessment'), ['assessment', 'survey'], true) ? (string) $_GET['form_type'] : 'assessment';
        $_SESSION['evaluation_surveys_ai_form_type'] = $formType;
        $formValues = [
            'form_type' => $formType,
            'operation' => in_array((string) ($_GET['operation'] ?? 'import'), ['import', 'generate'], true) ? (string) $_GET['operation'] : 'import',
            'question_count' => max(1, min((int) $settings['max_questions'], (int) ($_GET['question_count'] ?? 10))),
            'difficulty' => in_array((string) ($_GET['difficulty'] ?? $settings['default_difficulty']), ['easy', 'medium', 'hard'], true) ? (string) $_GET['difficulty'] : $settings['default_difficulty'],
        ];
        $this->render('evaluaciones_encuestas/ai_generate_form', ['title' => 'Crear con IA | e-talent', 'currentPage' => 'evaluation-surveys.ai.generate', 'backUrl' => route_url('evaluation-surveys.assessments'), 'settings' => $settings, 'formValues' => $formValues]);
    }

    private function aiGenerateUrl(array $values = []): string
    {
        $params = [];
        foreach (['form_type', 'question_count', 'difficulty'] as $key) {
            if (isset($values[$key]) && (string) $values[$key] !== '') $params[$key] = (string) $values[$key];
        }
        if (isset($values['operation']) && in_array((string) $values['operation'], ['import', 'generate'], true)) $params['operation'] = (string) $values['operation'];
        return route_url('evaluation-surveys.ai.generate') . ($params ? '?' . http_build_query($params) : '');
    }

    private function requireDashboardAccess(): void
    {
        require_auth();
        $user = current_user() ?: [];
        $allowed = $this->isGlobalEvaluationAdmin()
            || ((string) ($user['role'] ?? '') === 'company_admin'
                && (int) ($user['company_id'] ?? 0) > 0);
        if (!$allowed) {
            platform_error(403, 'No tienes permisos para consultar el dashboard de evaluaciones.', [
                'detailRows' => ['Permiso requerido' => 'Administrador general o Administrador Cliente'],
            ]);
        }
    }

    private function isGlobalEvaluationAdmin(): bool
    {
        $user = current_user() ?: [];
        return (string) ($user['role'] ?? '') !== 'company_admin' && is_general_admin($user);
    }

    private function requestPositiveIntList(string $key): array
    {
        $raw = $_GET[$key] ?? [];
        $raw = is_array($raw) ? $raw : [$raw];
        $values = [];
        foreach ($raw as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) $values[(int) $id] = (int) $id;
        }
        return array_values($values);
    }

    private function integrityDateRange(): array
    {
        $isValidDate = static function (string $value): bool {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
        };
        $from = trim((string) ($_GET['date_from'] ?? ''));
        $through = trim((string) ($_GET['date_to'] ?? ''));
        if (!$isValidDate($from)) $from = date('Y-m-d', strtotime('-90 days'));
        if (!$isValidDate($through)) $through = date('Y-m-d');
        if ($through < $from) $from = date('Y-m-d', strtotime($through . ' -90 days'));
        $until = (new DateTimeImmutable($through))->modify('+1 day')->format('Y-m-d');
        return ['from' => $from, 'through' => $through, 'until' => $until];
    }

    public function index(): void
    {
        require_permission('manage_evaluation_surveys');

        $type = $this->requestedFormType();
        if ($type === 'survey' && !$this->isGlobalEvaluationAdmin()) {
            platform_error(403, 'Las encuestas de satisfacción solo están disponibles para el administrador general.');
        }
        $this->render('evaluaciones_encuestas/index', [
            'title' => ($type === 'survey' ? 'Encuestas de satisfacción' : 'Evaluaciones con nota') . ' | e-talent',
            'currentPage' => $type === 'survey' ? 'evaluation-surveys.surveys' : 'evaluation-surveys.assessments',
            'backUrl' => route_url($type === 'survey' ? 'evaluation-surveys.surveys' : 'evaluation-surveys.assessments'),
            'type' => $type,
            'forms' => $this->forms->formsByType($type),
            'formTypes' => EvaluationSurveyFormModel::FORM_TYPES,
            'statuses' => EvaluationSurveyFormModel::FORM_STATUSES,
        ]);
    }

    public function moodleImport(): void
    {
        require_permission('manage_evaluation_surveys');
        $result = null;
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            try {
                $result = $this->moodleImport->import($_POST);
                $importLimit = max(1, (int) ($_POST['question_display_limit'] ?? 7));
                $questionCount = count((array) ($result['payload']['questions'] ?? []));
                if ($importLimit > $questionCount) {
                    throw new InvalidArgumentException('La cantidad de preguntas por intento no puede superar la batería importada (' . $questionCount . ').');
                }
                $result['payload']['question_display_limit'] = $importLimit;
                $formId = $this->forms->createFromMoodle($result['payload'], (int) current_user()['id']);
                flash('success', 'Evaluación importada correctamente como borrador. Revisa las preguntas antes de activarla.');
                redirect(route_url('evaluation-surveys.form.edit', $formId));
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        $this->render('evaluaciones_encuestas/moodle_import', [
            'title' => 'Importar evaluación desde Moodle | e-talent',
            'currentPage' => 'evaluation-surveys.assessments',
            'backUrl' => route_url('evaluation-surveys.assessments'),
            'errors' => $errors,
            'result' => $result,
        ]);
    }

    public function form(): void
    {
        require_permission('manage_evaluation_surveys');

        $formId = !empty($_GET['sid']) ? request_secure_id('evaluation_survey_form') : 0;
        $requestedType = $this->requestedFormType();
        $postedType = (string) ($_POST['form_type'] ?? $requestedType);
        if ($postedType === 'survey' && !$this->isGlobalEvaluationAdmin()) {
            platform_error(403, 'Las encuestas de satisfacción solo están disponibles para el administrador general.');
        }
        $form = $formId > 0 ? $this->forms->findForm($formId) : null;
        if ($formId > 0 && !$form) {
            platform_error(404, 'Formulario no encontrado.', ['chips' => ['Evaluaciones']]);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            try {
                $payload = $_POST;
                if (is_company_admin_user()) {
                    $payload['control_mode'] = 'off';
                    $payload['audio_visual_upload_failure_policy'] = 'continue';
                    $payload['audio_visual_interruption_policy'] = 'pause';
                    $payload['audio_visual_voice_policy'] = 'warn';
                    $payload['audio_visual_permission_policy'] = 'pause';
                    $payload['audio_visual_quality_profile'] = 'economical';
                    $payload['show_result_to_user'] = '0';
                    $payload['show_correction_to_user'] = '0';
                    $payload['result_display_mode'] = 'best_only';
                }
                $savedId = $this->forms->saveForm($formId, $payload, (int) current_user()['id']);
                flash('success', 'Formulario guardado correctamente.');
                redirect(route_url('evaluation-surveys.form.edit', $savedId));
            } catch (Throwable $exception) {
                flash('danger', $exception->getMessage());
                $form = array_merge($form ?: [], $_POST);
            }
        }

        $activeType = ($form['form_type'] ?? $requestedType) === 'survey' ? 'survey' : 'assessment';
        $this->render('evaluaciones_encuestas/form_form', [
            'title' => ($formId > 0 ? 'Editar formulario' : 'Nuevo formulario') . ' | e-talent',
            'currentPage' => $activeType === 'survey' ? 'evaluation-surveys.surveys' : 'evaluation-surveys.assessments',
            'backUrl' => route_url($activeType === 'survey' ? 'evaluation-surveys.surveys' : 'evaluation-surveys.assessments'),
            'formId' => $formId,
            'form' => $form ?: $this->defaultForm($requestedType),
            'lockedFormType' => $formId > 0 ? (string) $form['form_type'] : $requestedType,
            'questions' => $formId > 0 ? $this->forms->questionsForForm($formId) : [],
            'formTypes' => EvaluationSurveyFormModel::FORM_TYPES,
            'statuses' => EvaluationSurveyFormModel::FORM_STATUSES,
            'questionOrderModes' => EvaluationSurveyFormModel::QUESTION_ORDER_MODES,
            'questionTypes' => EvaluationSurveyFormModel::QUESTION_TYPES,
            'isCompanyAdmin' => is_company_admin_user(),
        ]);
    }

    public function deleteForm(): void
    {
        require_permission('manage_evaluation_surveys');
        verify_csrf();

        $formId = request_secure_id('evaluation_survey_form');
        $form = $this->forms->findForm($formId);
        if (!$form) {
            platform_error(404, 'Formulario no encontrado.', ['chips' => ['Evaluaciones']]);
        }

        $this->forms->deleteForm($formId);
        flash('success', 'Formulario eliminado.');
        redirect($form['form_type'] === 'survey' ? route_url('evaluation-surveys.surveys') : route_url('evaluation-surveys.assessments'));
    }

    public function duplicateForm(): void
    {
        require_permission('manage_evaluation_surveys');
        verify_csrf();

        $formId = request_secure_id('evaluation_survey_form');
        $form = $this->forms->findForm($formId);
        if (!$form) {
            platform_error(404, 'Formulario no encontrado.', ['chips' => ['Evaluaciones']]);
        }

        try {
            $newFormId = $this->forms->duplicateForm($formId, (int) current_user()['id']);
            flash('success', ((string) ($form['form_type'] ?? '') === 'survey' ? 'Encuesta' : 'Evaluación') . ' duplicada correctamente.');
            redirect(route_url('evaluation-surveys.form.edit', $newFormId));
        } catch (Throwable $exception) {
            flash('danger', $exception->getMessage());
            redirect((string) ($form['form_type'] ?? '') === 'survey' ? route_url('evaluation-surveys.surveys') : route_url('evaluation-surveys.assessments'));
        }
    }

    public function preview(): void
    {
        require_permission('manage_evaluation_surveys');

        $formId = request_secure_id('evaluation_survey_form');
        $form = $this->forms->findForm($formId);
        if (!$form) {
            platform_error(404, 'Formulario no encontrado.', ['chips' => ['Evaluaciones']]);
        }

        $questions = $this->forms->questionsForForm($formId, true);
        $displayLimit = max(0, (int) ($form['question_display_limit'] ?? 0));
        if (($form['question_order_mode'] ?? 'ordered') === 'random') {
            shuffle($questions);
        }
        if ($displayLimit > 0 && $displayLimit < count($questions)) {
            $questions = array_slice($questions, 0, $displayLimit);
        }

        foreach ($questions as &$question) {
            $question['answer_value'] = '';
        }
        unset($question);

        $isSurvey = (string) ($form['form_type'] ?? '') === 'survey';
        $this->render('evaluaciones_encuestas/take', [
            'title' => ($isSurvey ? 'Vista previa encuesta' : 'Vista previa evaluación') . ' | e-talent',
            'currentPage' => $isSurvey ? 'evaluation-surveys.surveys' : 'evaluation-surveys.assessments',
            'backUrl' => route_url('evaluation-surveys.form.edit', $formId),
            'form' => $form,
            'attempt' => ['attempt_number' => 1],
            'questions' => $questions,
            'remainingSeconds' => max(0, (int) ($form['duration_minutes'] ?? 0)) > 0 ? ((int) $form['duration_minutes'] * 60) : null,
            'evaluationMessages' => $this->testSettings->evaluationMessages(),
            'previewMode' => true,
        ]);
    }

    public function questionForm(): void
    {
        require_permission('manage_evaluation_surveys');
        $isDrawer = $this->isDrawerRequest();

        $formId = !empty($_GET['form_sid']) ? secure_url_id((string) $_GET['form_sid'], 'evaluation_survey_form') : 0;
        $questionId = !empty($_GET['sid']) && empty($_GET['form_sid']) ? request_secure_id('evaluation_survey_question') : 0;
        $question = $questionId > 0 ? $this->forms->findQuestion($questionId) : null;
        if ($questionId > 0 && !$question) {
            platform_error(404, 'Pregunta no encontrada.', ['chips' => ['Evaluaciones']]);
        }
        if ($question) {
            $formId = (int) $question['form_id'];
        }

        $form = $this->forms->findForm($formId);
        if (!$form) {
            platform_error(404, 'Formulario no encontrado.', ['chips' => ['Evaluaciones']]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            try {
                $this->forms->saveQuestion($formId, $questionId, $_POST);
                if ($this->isAjaxRequest()) {
                    $this->jsonResponse([
                        'ok' => true,
                        'message' => 'Pregunta guardada correctamente.',
                        'reload' => true,
                    ]);
                    return;
                }
                flash('success', 'Pregunta guardada correctamente.');
                redirect(route_url('evaluation-surveys.form.edit', $formId));
            } catch (Throwable $exception) {
                if ($this->isAjaxRequest()) {
                    $this->jsonResponse([
                        'ok' => false,
                        'message' => $exception->getMessage(),
                    ], 422);
                    return;
                }
                flash('danger', $exception->getMessage());
                $question = array_merge($question ?: ['form_id' => $formId], $_POST);
            }
        }

        $this->render('evaluaciones_encuestas/question_form', [
            'title' => ($questionId > 0 ? 'Editar pregunta' : 'Nueva pregunta') . ' | e-talent',
            'currentPage' => ($form['form_type'] ?? 'assessment') === 'survey' ? 'evaluation-surveys.surveys' : 'evaluation-surveys.assessments',
            'backUrl' => route_url('evaluation-surveys.form.edit', (int) $form['id']),
            'form' => $form,
            'questionId' => $questionId,
            'isDrawer' => $isDrawer,
            'question' => $question ?: $this->defaultQuestion($formId),
            'questionTypes' => EvaluationSurveyFormModel::QUESTION_TYPES,
        ], $isDrawer ? null : 'app');
    }

    public function deleteQuestion(): void
    {
        require_permission('manage_evaluation_surveys');
        verify_csrf();

        $questionId = request_secure_id('evaluation_survey_question');
        $question = $this->forms->findQuestion($questionId);
        if (!$question) {
            platform_error(404, 'Pregunta no encontrada.', ['chips' => ['Evaluaciones']]);
        }

        $this->forms->deleteQuestion($questionId);
        flash('success', 'Pregunta eliminada.');
        redirect(route_url('evaluation-surveys.form.edit', (int) $question['form_id']));
    }

    public function reorderQuestions(): void
    {
        require_permission('manage_evaluation_surveys');
        verify_csrf();

        $formId = request_secure_id('evaluation_survey_form');
        $form = $this->forms->findForm($formId);
        if (!$form) {
            $this->jsonResponse([
                'ok' => false,
                'message' => 'Formulario no encontrado.',
            ], 404);
            return;
        }

        try {
            $order = is_array($_POST['order'] ?? null) ? $_POST['order'] : [];
            $this->forms->reorderQuestions($formId, $order);
            $this->jsonResponse([
                'ok' => true,
                'message' => 'Orden actualizado.',
            ]);
        } catch (Throwable $exception) {
            $this->jsonResponse([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function take(): void
    {
        require_auth();

        $formId = request_secure_id('evaluation_survey_form');
        $processId = max(0, (int) ($_GET['process_id'] ?? 0));
        $qrMode = (string) ($_GET['qr'] ?? '') === '1';
        $qrResultQuery = $qrMode ? '?qr=1' : '';
        $form = $this->forms->findForm($formId);
        if (!$form || (string) $form['status'] !== 'active') {
            platform_error(404, 'Formulario no disponible.', ['chips' => ['Evaluaciones']]);
        }
        $userId = (int) current_user()['id'];
        $clientAssignment = null;
        $isParticipant = !has_permission('manage_evaluation_surveys');
        if ($isParticipant) {
            $companyId = (int) (current_user()['company_id'] ?? 0);
            $clientAssignment = (new TestProcessModel())->evaluationAssignmentForUser(
                $formId,
                $userId,
                $companyId,
                $processId > 0 ? $processId : null
            );
            if (!$clientAssignment) {
                platform_error(403, 'No tienes esta evaluación asignada a un proceso válido.', ['chips' => ['Evaluación', 'Asignación']]);
            }
            $processId = (int) $clientAssignment['process_id'];
            $effectiveAssignment = !empty($clientAssignment['process_policy_snapshot_at'])
                ? $clientAssignment
                : ProcessPrerequisiteService::resolve(array_merge($form, $clientAssignment));
            $form = array_merge($form, $effectiveAssignment);
        } elseif (!$this->attempts->isAssignedToUser($formId, $userId, $processId > 0 ? $processId : null)) {
            platform_error(403, 'No tienes esta evaluación asignada.', ['chips' => ['Evaluación', 'Asignación']]);
        }

        // Resolve the active attempt before validating the face proof. The proof
        // is intentionally consumed when supervised_started succeeds; it must
        // not be required again for answer, draft, or expiry POSTs belonging to
        // that already-authorized in-progress attempt.
        $activeAttempt = $isParticipant
            ? $this->attempts->activeAttemptForUser($formId, $userId, $processId)
            : null;

        if ($isParticipant) {
            $requestPath = route_url('evaluation-surveys.form.take', $formId) . '?process_id=' . $processId . ($qrMode ? '&qr=1' : '');
            $faceAuthorizationKey = 'evaluation:' . $formId . ':' . $processId;
            $proof = $_SESSION['assessment_face_authorizations'][$faceAuthorizationKey] ?? null;
            $validProof = is_array($proof)
                && (int) ($proof['user_id'] ?? 0) === $userId
                && (int) ($proof['company_id'] ?? 0) === (int) (current_user()['company_id'] ?? 0)
                && (string) ($proof['activity_type'] ?? '') === 'evaluation'
                && (int) ($proof['activity_id'] ?? 0) === $formId
                && (int) ($proof['process_id'] ?? 0) === $processId
                && (int) ($proof['issued_at'] ?? 0) <= time()
                && (time() - (int) ($proof['issued_at'] ?? 0)) <= 900;
            $attemptAlreadyAuthorized = is_array($activeAttempt)
                && (string) ($activeAttempt['status'] ?? '') === 'in_progress';
            $assessmentIdentityVerified = $validProof || $attemptAlreadyAuthorized;
        } else {
            $assessmentIdentityVerified = true;
        }

        if ($isParticipant && !$activeAttempt) {
            $companyId = (int) (current_user()['company_id'] ?? 0);
            $componentReview = $companyId > 0 ? (new ComponentValidationModel())->latestForUser($userId, $companyId) : null;
            $facialStatus = $companyId > 0 ? (new FacialRecognitionModel())->enrollmentStatusForUser($userId, $companyId) : null;
            if (!ProcessPrerequisiteService::isReady(
                $clientAssignment,
                (string) ($componentReview['outcome'] ?? '') === 'passed',
                $facialStatus === 'active'
            )) {
                $missing = ProcessPrerequisiteService::missingLabels(
                    $clientAssignment,
                    (string) ($componentReview['outcome'] ?? '') === 'passed',
                    $facialStatus === 'active'
                );
                flash('warning', 'Antes de iniciar esta actividad debes ' . implode(' y ', $missing) . '.');
                redirect(route_url('my-tests'));
            }
        }
        $processAvailability = $processId > 0
            ? $this->testSessions->availabilityForProcess($processId)
            : $this->testSessions->availabilityForProcess(0);
        if (!has_permission('manage_evaluation_surveys') && $_SERVER['REQUEST_METHOD'] !== 'POST' && $processId > 0) {
            if (empty($processAvailability['allowed'])) {
                flash('warning', (string) ($processAvailability['message'] ?? $processAvailability['label'] ?? 'La evaluación no está disponible en este momento.'));
                redirect(route_url('my-tests'));
            }
        }
        // Las respuestas y resultados de una evaluación son de consulta administrativa.
        // La configuración histórica show_result_to_user no debe exponerlos al participante.
        $ownerResultHidden = !$this->isAdministrativeEvaluationViewer();

        try {
            // Los participantes siempre pasan por `preparing`, incluso si la
            // actividad no usa supervisión audiovisual. Solo el evento
            // `supervised_started`, emitido al confirmar el último paso del
            // wizard, puede activar el intento y su temporizador.
            $attempt = $this->attempts->startAttempt($form, $userId, $processId > 0 ? $processId : null, $_SERVER['REQUEST_METHOD'] !== 'POST', $isParticipant);
            if ($isParticipant && (string) ($attempt['status'] ?? '') === 'in_progress') unset($_SESSION['assessment_face_authorizations'][$faceAuthorizationKey]);
        } catch (Throwable $exception) {
            $message = $exception instanceof PDOException
                ? 'No se pudo iniciar la evaluación. Inténtalo nuevamente.'
                : $exception->getMessage();
            flash('warning', $message);
            redirect(route_url('evaluation-surveys.assessments'));
        }
        if ((string) ($attempt['status'] ?? '') === 'in_progress'
            && (string) ($attempt['control_mode'] ?? '') !== 'supervised_audio_visual') {
            $this->control->record($attempt, 'attempt_opened');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $attemptStatusBeforeExpiry = (string) ($attempt['status'] ?? '');
            $attempt = $this->attempts->expireIfNeeded($attempt);
            if ($attemptStatusBeforeExpiry === 'in_progress' && ($attempt['status'] ?? '') === 'expired') {
                $this->control->record($attempt, 'attempt_expired');
            }
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !in_array((string) ($attempt['status'] ?? ''), ['in_progress', 'preparing'], true)) {
            $resultUrl = $ownerResultHidden
                ? route_url('my-tests')
                : route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . $qrResultQuery;
            redirect(!$ownerResultHidden && $this->isDrawerRequest() ? $resultUrl . '?drawer=1' : $resultUrl);
        }

        // El conjunto de preguntas del intento es servidor-autoritativo. El
        // campo visible_question_ids solo sirve para la interfaz y no participa
        // en validación ni puntuación.
        $questions = $this->attempts->questionsForAttempt($form, (int) $attempt['id']);
        $remainingSeconds = $this->attempts->remainingSeconds($attempt);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            if ((string) ($attempt['status'] ?? '') !== 'in_progress') {
                if (in_array((string) ($attempt['status'] ?? ''), ['completed', 'expired'], true) && $this->isAjaxRequest()) {
                    $retryAnswers = is_array($_POST['answers'] ?? null) ? $_POST['answers'] : [];
                    $retryVerification = $this->attempts->verifyAnswersForAttempt((int) $attempt['id'], $questions, $retryAnswers);
                    $retryEvent = (string) $attempt['status'] === 'expired' ? 'attempt_expired' : 'attempt_completed';
                    $retryActivityRecorded = $this->control->hasEvent((int) $attempt['id'], $retryEvent);
                    if ($retryVerification['verified'] && $retryActivityRecorded) {
                        $resultUrl = $ownerResultHidden ? route_url('my-tests') : route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . $qrResultQuery;
                        $payload = ['ok' => true, 'answers_saved' => true, 'answers_verified' => true, 'answers_saved_count' => $retryVerification['answered_count'], 'activity_recorded' => true, 'final_status' => $attempt['status'], 'redirect_url' => $resultUrl];
                        if (!$ownerResultHidden) { $payload['drawer_url'] = route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . '?drawer=1' . ($qrMode ? '&qr=1' : ''); $payload['drawer_title'] = $form['form_type'] === 'survey' ? 'Encuesta enviada' : 'Resultado evaluación'; $payload['drawer_size'] = 'lg'; }
                        $this->jsonResponse($payload);
                        return;
                    }
                }
                $this->jsonResponse(['ok' => false, 'message' => 'Completa la validación previa antes de responder.'], 409);
                return;
            }
            $answers = is_array($_POST['answers'] ?? null) ? $_POST['answers'] : [];
            $action = (string) ($_POST['evaluation_survey_action'] ?? 'complete');

            // El plazo del proceso es independiente del tiempo particular del
            // intento. Si termina mientras el usuario responde, este POST debe
            // ser el mismo punto de entrada para guardar respuestas y permitir
            // que el flujo audiovisual finalice video y capturas.
            $processEnded = $processId > 0
                && empty($processAvailability['allowed'])
                && in_array((string) ($processAvailability['reason'] ?? ''), ['process_ended', 'closed_now', 'process_not_active'], true);
            if ($processEnded || $action === 'process_expired') {
                $this->attempts->saveDraft((int) $attempt['id'], $questions, $answers);
                $answerVerification = $this->attempts->verifyAnswersForAttempt((int) $attempt['id'], $questions, $answers);
                $activityRecorded = $this->control->record($attempt, 'attempt_expired', [
                    'reason' => 'process_deadline',
                    'process_reason' => (string) ($processAvailability['reason'] ?? 'process_ended'),
                    'answered_count' => $answerVerification['answered_count'],
                ]);
                if (($attempt['control_mode'] ?? 'off') === 'supervised_audio_visual' && (!$answerVerification['verified'] || !$activityRecorded)) {
                    $this->jsonResponse(['ok' => false, 'reason' => !$answerVerification['verified'] ? 'answer_persistence_unverified' : 'activity_persistence_unavailable', 'message' => 'No se pudo confirmar el guardado de todas las respuestas y la actividad de cierre.'], 503);
                    return;
                }
                $attempt = $this->attempts->completeAttempt($form, $attempt, $questions, $answers, 'expired');
                $resultUrl = $ownerResultHidden ? route_url('my-tests') : route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . $qrResultQuery;
                $message = 'El proceso ha finalizado. Se guardarán tus respuestas y la evidencia audiovisual antes de cerrar.';
                if ($this->isAjaxRequest()) {
                    $payload = ['ok' => true, 'process_expired' => true, 'answers_saved' => true, 'answers_verified' => true, 'answers_saved_count' => $answerVerification['answered_count'], 'activity_recorded' => $activityRecorded, 'final_status' => 'expired', 'message' => $message, 'redirect_url' => $resultUrl];
                    if (!$ownerResultHidden) {
                        $payload['drawer_url'] = route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . '?drawer=1' . ($qrMode ? '&qr=1' : '');
                        $payload['drawer_title'] = $form['form_type'] === 'survey' ? 'Encuesta enviada' : 'Resultado evaluación';
                        $payload['drawer_size'] = 'lg';
                    }
                    $this->jsonResponse($payload);
                    return;
                }
                flash('warning', $message);
                redirect(!$ownerResultHidden && $this->isDrawerRequest() ? $resultUrl : ($ownerResultHidden ? route_url('my-tests') : route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . $qrResultQuery));
            }

            if ($action === 'abandon') {
                $this->attempts->saveDraft((int) $attempt['id'], $questions, $answers);
                $answerVerification = $this->attempts->verifyAnswersForAttempt((int) $attempt['id'], $questions, $answers);
                $activityRecorded = $this->control->record($attempt, 'attempt_expired', ['reason' => 'user_left_before_completion', 'answered_count' => $answerVerification['answered_count']]);
                if (($attempt['control_mode'] ?? 'off') === 'supervised_audio_visual' && (!$answerVerification['verified'] || !$activityRecorded)) {
                    $this->jsonResponse(['ok' => false, 'reason' => !$answerVerification['verified'] ? 'answer_persistence_unverified' : 'activity_persistence_unavailable', 'message' => 'No se pudo confirmar el guardado de todas las respuestas y la actividad.'], 503);
                    return;
                }
                $attempt = $this->attempts->completeAttempt($form, $attempt, $questions, $answers, 'expired');
                $this->jsonResponse(['ok' => true, 'answers_saved' => true, 'answers_verified' => true, 'answers_saved_count' => $answerVerification['answered_count'], 'activity_recorded' => $activityRecorded, 'final_status' => 'expired', 'message' => 'La evaluación fue cerrada.']);
                return;
            }

            if ($remainingSeconds !== null && $remainingSeconds <= 0) {
                $this->attempts->saveDraft((int) $attempt['id'], $questions, $answers);
                $answerVerification = $this->attempts->verifyAnswersForAttempt((int) $attempt['id'], $questions, $answers);
                $activityRecorded = $this->control->record($attempt, 'attempt_expired', ['reason' => 'time_limit', 'answered_count' => $answerVerification['answered_count']]);
                if (($attempt['control_mode'] ?? 'off') === 'supervised_audio_visual' && (!$answerVerification['verified'] || !$activityRecorded)) {
                    $this->jsonResponse(['ok' => false, 'reason' => !$answerVerification['verified'] ? 'answer_persistence_unverified' : 'activity_persistence_unavailable', 'message' => 'No se pudo confirmar el guardado de todas las respuestas y la actividad de vencimiento.'], 503);
                    return;
                }
                $attempt = $this->attempts->completeAttempt($form, $attempt, $questions, $answers, 'expired');
                if ($this->isAjaxRequest()) {
                    $resultUrl = $ownerResultHidden ? route_url('my-tests') : route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . $qrResultQuery;
                    $payload = [
                        'ok' => true,
                        'answers_saved' => true,
                        'answers_verified' => true,
                        'answers_saved_count' => $answerVerification['answered_count'],
                        'activity_recorded' => $activityRecorded,
                        'final_status' => 'expired',
                        'message' => (string) $this->testSettings->evaluationMessages()['expired_message'],
                        'redirect_url' => $resultUrl,
                    ];
                    if (!$ownerResultHidden) {
                        $payload['drawer_url'] = route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . '?drawer=1' . ($qrMode ? '&qr=1' : '');
                        $payload['drawer_title'] = $form['form_type'] === 'survey' ? 'Encuesta enviada' : 'Resultado evaluación';
                        $payload['drawer_size'] = 'lg';
                    }
                    $this->jsonResponse($payload);
                    return;
                }
                flash('warning', (string) $this->testSettings->evaluationMessages()['expired_message']);
                $resultUrl = $ownerResultHidden ? route_url('my-tests') : route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . $qrResultQuery;
                redirect(!$ownerResultHidden && $this->isDrawerRequest() ? $resultUrl . '?drawer=1' : $resultUrl);
            }

            if ($action === 'draft') {
                $this->attempts->saveDraft((int) $attempt['id'], $questions, $answers);
                $this->control->record($attempt, 'draft_saved', ['answered_count' => count(array_filter($answers, static fn($answer): bool => is_array($answer) ? (bool) array_filter($answer) : trim((string) $answer) !== ''))]);
                if ($this->isAjaxRequest()) {
                    $this->jsonResponse([
                        'ok' => true,
                        'message' => 'Avance guardado.',
                        'reload' => true,
                    ]);
                    return;
                }
                flash('success', 'Avance guardado.');
                redirect(route_url('evaluation-surveys.assessments'));
            }

            if ($action === 'complete_incomplete') {
                $this->attempts->saveDraft((int) $attempt['id'], $questions, $answers);
                $answerVerification = $this->attempts->verifyAnswersForAttempt((int) $attempt['id'], $questions, $answers);
                $activityRecorded = $this->control->record($attempt, 'attempt_completed', ['status' => 'completed', 'incomplete' => true, 'answered_count' => $answerVerification['answered_count']]);
                if (($attempt['control_mode'] ?? 'off') === 'supervised_audio_visual' && (!$answerVerification['verified'] || !$activityRecorded)) {
                    $this->jsonResponse(['ok' => false, 'reason' => !$answerVerification['verified'] ? 'answer_persistence_unverified' : 'activity_persistence_unavailable', 'message' => 'No se pudo confirmar el guardado de todas las respuestas y la actividad. La evidencia no se finalizará; solicita asistencia.'], 503);
                    return;
                }
                $attempt = $this->attempts->completeAttempt($form, $attempt, $questions, $answers, 'completed');
                if ($this->isAjaxRequest()) {
                    $resultUrl = $ownerResultHidden ? route_url('my-tests') : route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . $qrResultQuery;
                    $payload = ['ok' => true, 'answers_saved' => true, 'answers_verified' => true, 'answers_saved_count' => $answerVerification['answered_count'], 'activity_recorded' => $activityRecorded, 'message' => 'Sus respuestas y el registro de actividad han sido guardados con éxito.', 'redirect_url' => $resultUrl];
                    if (!$ownerResultHidden) { $payload['drawer_url'] = route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . '?drawer=1' . ($qrMode ? '&qr=1' : ''); $payload['drawer_title'] = $form['form_type'] === 'survey' ? 'Encuesta enviada' : 'Resultado evaluacion'; $payload['drawer_size'] = 'lg'; }
                    $this->jsonResponse($payload);
                    return;
                }
                flash('success', 'Sus respuestas han sido guardadas con éxito.');
                redirect($ownerResultHidden ? route_url('my-tests') : route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . $qrResultQuery);
            }
            $this->attempts->saveDraft((int) $attempt['id'], $questions, $answers);
            $answerVerification = $this->attempts->verifyAnswersForAttempt((int) $attempt['id'], $questions, $answers);
            $activityRecorded = $this->control->record($attempt, 'attempt_completed', ['status' => $attempt['status'] ?? 'completed', 'answered_count' => $answerVerification['answered_count']]);
            if (($attempt['control_mode'] ?? 'off') === 'supervised_audio_visual' && (!$answerVerification['verified'] || !$activityRecorded)) {
                $this->jsonResponse(['ok' => false, 'reason' => !$answerVerification['verified'] ? 'answer_persistence_unverified' : 'activity_persistence_unavailable', 'message' => 'No se pudo confirmar el guardado de todas las respuestas y la actividad. La evidencia no se finalizará; solicita asistencia.'], 503);
                return;
            }
            $attempt = $this->attempts->completeAttempt($form, $attempt, $questions, $answers);
            if ($this->isAjaxRequest()) {
                    $resultUrl = $ownerResultHidden
                        ? route_url('my-tests')
                        : route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . $qrResultQuery;
                    $payload = [
                        'ok' => true,
                        'answers_saved' => true,
                        'answers_verified' => true,
                        'answers_saved_count' => $answerVerification['answered_count'],
                        'activity_recorded' => $activityRecorded,
                        'message' => 'Sus respuestas y el registro de actividad han sido guardados con éxito.',
                        'redirect_url' => $resultUrl,
                    ];
                    if (!$ownerResultHidden) {
                        $payload['drawer_url'] = route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . '?drawer=1' . ($qrMode ? '&qr=1' : '');
                        $payload['drawer_title'] = $form['form_type'] === 'survey' ? 'Encuesta enviada' : 'Resultado evaluación';
                        $payload['drawer_size'] = 'lg';
                    }
                    $this->jsonResponse($payload);
                    return;
            }
            flash('success', 'Sus respuestas han sido guardadas con éxito.');
            $resultUrl = $ownerResultHidden ? route_url('my-tests') : route_url('evaluation-surveys.attempt.result', (int) $attempt['id']) . $qrResultQuery;
            redirect(!$ownerResultHidden && $this->isDrawerRequest() ? $resultUrl . '?drawer=1' : $resultUrl);
        }

        $takeData = [
            'title' => 'Responder formulario | e-talent',
            'currentPage' => 'evaluation-surveys.assessments',
            'backUrl' => route_url('evaluation-surveys.assessments'),
            'form' => $form,
            'processId' => $processId,
            'attempt' => $attempt,
            'questions' => $questions,
            'remainingSeconds' => $remainingSeconds,
            'processAvailability' => $processAvailability,
            'evaluationMessages' => $this->testSettings->evaluationMessages(),
            'controlMode' => (string) ($attempt['control_mode'] ?? $form['control_mode'] ?? 'off'),
            'activityUrl' => route_url('evaluation-surveys.attempt.activity', (int) $attempt['id']),
            'mediaInitUrl' => route_url('evaluation-surveys.attempt.media.init', (int) $attempt['id']),
            'mediaStatusUrl' => route_url('evaluation-surveys.attempt.media.status', (int) $attempt['id']),
            'mediaChunkUrl' => route_url('evaluation-surveys.attempt.media.chunk', (int) $attempt['id']),
            'mediaFinalizeUrl' => route_url('evaluation-surveys.attempt.media.finalize', (int) $attempt['id']),
            'mediaRiskUrl' => route_url('evaluation-surveys.attempt.media.risk', (int) $attempt['id']),
            'mediaFailureUrl' => route_url('evaluation-surveys.attempt.media.failure', (int) $attempt['id']),
            'assessmentIdentityVerified' => $assessmentIdentityVerified,
            'assessmentEntryFlow' => $isParticipant && (string) ($attempt['status'] ?? '') === 'preparing',
            'assessmentUserId' => $userId,
            'assessmentReturnTo' => $requestPath,
            'assessmentFaceSettings' => (new FacialRecognitionService())->settings(),
        ];
        $this->render('evaluaciones_encuestas/take', $takeData, 'app');
    }

    public function result(): void
    {
        require_auth();
        $isDrawer = (string) ($_GET['drawer'] ?? '') === '1' || (string) ($_GET['partial'] ?? '') === 'drawer';
        $attemptId = request_secure_id('evaluation_survey_attempt');
        $userId = (int) current_user()['id'];
        $isCompanyAdmin = is_company_admin_user();
        $companyId = (int) (current_user()['company_id'] ?? 0);
        $attempt = $isCompanyAdmin
            ? $this->attempts->findAttemptForCompany($attemptId, $companyId)
            : $this->attempts->findAttempt($attemptId);

        if (!$attempt) {
            platform_error(404, 'Resultado no encontrado.', ['chips' => ['Evaluaciones']]);
        }
        $form = $isCompanyAdmin
            ? $this->forms->findFormForCompany((int) $attempt['form_id'], $companyId)
            : $this->forms->findForm((int) $attempt['form_id']);
        if (!$form) {
            platform_error(404, 'Formulario del resultado no encontrado.', ['chips' => ['Evaluaciones']]);
        }
        $isAdministrativeViewer = $this->isAdministrativeEvaluationViewer();
        $isOwner = (int) ($attempt['user_id'] ?? 0) === $userId;
        if (!$isAdministrativeViewer && !$isOwner) {
            platform_error(403, 'No tienes permiso para ver este resultado.', ['chips' => ['Evaluación', 'Resultado']]);
        }
        $questions = $form ? $this->attempts->questionsForAnsweredAttempt($form, $attemptId) : [];
        $answers = $this->attempts->answersForAttempt($attemptId);
        $resultAttemptDetails = [];
        $resultDisplayMode = (string) ($form['result_display_mode'] ?? $attempt['result_display_mode'] ?? 'best_only');
        $controlEvents = $this->control->eventsForAttempt($attemptId);
        $mediaEvidence = $this->mediaEvidence->evidencesForAttempt($attemptId);
        $audioVisualRisks = $this->mediaEvidence->risksForAttempt($attemptId);
        $screenCaptures = $this->mediaEvidence->screenCapturesForAttempt($attemptId);

        if ($form && $resultDisplayMode === 'collapsible_attempts') {
            $finishedAttempts = $this->attempts->finishedAttemptsForFormUser(
                (int) $form['id'],
                (int) $attempt['user_id'],
                (int) ($attempt['process_id'] ?? 0),
                $isCompanyAdmin ? $companyId : null
            );
            foreach ($finishedAttempts as $finishedAttempt) {
                $finishedAttemptId = (int) $finishedAttempt['id'];
                $resultAttemptDetails[] = [
                    'attempt' => $finishedAttempt,
                    'questions' => $this->attempts->questionsForAnsweredAttempt($form, $finishedAttemptId),
                    'answers' => $this->attempts->answersForAttempt($finishedAttemptId),
                ];
            }
        }

        $resultData = [
            'title' => 'Resultado | e-talent',
            'currentPage' => 'evaluation-surveys.assessments',
            'backUrl' => route_url('evaluation-surveys.assessments'),
            'attempt' => $attempt,
            'form' => $form,
            'questions' => $questions,
            'answers' => $answers,
            'resultAttemptDetails' => $resultAttemptDetails,
            'controlEvents' => $controlEvents,
            'mediaEvidence' => $mediaEvidence,
            'audioVisualRisks' => $audioVisualRisks,
            'screenCaptures' => $screenCaptures,
            'isAdministrativeViewer' => $isAdministrativeViewer,
            'resultsVisible' => $isAdministrativeViewer,
        ];
        $this->render('evaluaciones_encuestas/result', $resultData, $isDrawer ? null : 'app');
    }

    private function defaultForm(string $type = 'assessment'): array
    {
        if (!isset(EvaluationSurveyFormModel::FORM_TYPES[$type])) {
            $type = 'assessment';
        }

        return [
            'form_type' => $type,
            'title' => '',
            'description' => '',
            'instructions' => '',
            'status' => 'draft',
            'duration_minutes' => 0,
            'question_order_mode' => 'ordered',
            'question_display_limit' => 0,
            'max_attempts' => 1,
            'max_score' => 100,
            'passing_score' => $type === 'survey' ? null : 70,
            'show_result_to_user' => 1,
            'result_display_mode' => 'best_only',
            'show_correction_to_user' => 0,
            'is_required' => 1,
            'sort_order' => 100,
            'control_mode' => 'off',
            'audio_visual_upload_failure_policy' => 'continue',
            'audio_visual_interruption_policy' => 'pause',
            'audio_visual_voice_policy' => 'warn',
            'audio_visual_permission_policy' => 'pause',
            'audio_visual_quality_profile' => 'economical',
        ];
    }

    private function requestedFormType(): string
    {
        $type = (string) ($_GET['type'] ?? 'assessment');
        return isset(EvaluationSurveyFormModel::FORM_TYPES[$type]) ? $type : 'assessment';
    }

    private function defaultQuestion(int $formId): array
    {
        return [
            'form_id' => $formId,
            'question_text' => '',
            'question_type' => '',
            'is_required' => 1,
            'points' => 1,
            'sort_order' => 100,
            'is_active' => 1,
            'options' => [],
        ];
    }

    private function isDrawerRequest(): bool
    {
        return (string) ($_GET['drawer'] ?? '') === '1'
            || (string) ($_GET['partial'] ?? '') === 'drawer';
    }

    private function isAdministrativeEvaluationViewer(): bool
    {
        return has_permission('manage_evaluation_surveys')
            || has_permission('manage_company_processes')
            || has_permission('view_company_results')
            || has_permission('view_process_results')
            || $this->isGlobalEvaluationAdmin();
    }

    private function isAjaxRequest(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
            || $this->isDrawerRequest();
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function activity(): void
    {
        require_auth();
        verify_csrf();
        $attemptId = request_secure_id('evaluation_survey_attempt');
        $attempt = $this->attempts->findAttemptForUser($attemptId, (int) current_user()['id']);
        if (!$attempt) {
            $this->jsonResponse(['ok' => false, 'message' => 'Intento no encontrado.'], 404);
            return;
        }
        $eventType = trim((string) ($_POST['event_type'] ?? ''));
        if ($eventType === 'heartbeat') {
            $saved = $this->attempts->touchPresence($attemptId, (int) current_user()['id']);
            $this->jsonResponse(['ok' => $saved]);
            return;
        }
        $metadata = is_array($_POST['metadata'] ?? null) ? $_POST['metadata'] : (is_string($_POST['metadata'] ?? null) ? (json_decode((string) $_POST['metadata'], true) ?: []) : []);
        if (!$this->control->isAllowedEvent($eventType)) {
            $this->jsonResponse(['ok' => false, 'message' => 'Evento no permitido.'], 422);
            return;
        }
        if ((string) ($attempt['control_mode'] ?? '') === 'supervised_audio_visual' && !in_array($eventType, ['supervised_started', 'audio_visual_consent_accepted'], true)) {
            $started = false;
            foreach ($this->control->eventsForAttempt($attemptId) as $event) {
                if ((string) ($event['event_type'] ?? '') === 'supervised_started') { $started = true; break; }
            }
            $evidence = $this->mediaEvidence->evidenceForAttempt($attemptId);
            if (!$started && empty($evidence['recording_started_at'])) {
                $this->jsonResponse(['ok' => false, 'reason' => 'not_started']);
                return;
            }
        }
        if ($eventType === 'supervised_started') {
            if ((string) ($attempt['status'] ?? '') === 'preparing') {
                $proofKey = 'evaluation:' . (int) ($attempt['form_id'] ?? 0) . ':' . (int) ($attempt['process_id'] ?? 0);
                $proof = $_SESSION['assessment_face_authorizations'][$proofKey] ?? null;
                if (!is_array($proof) || (int) ($proof['user_id'] ?? 0) !== (int) current_user()['id']
                    || (int) ($proof['company_id'] ?? 0) !== (int) (current_user()['company_id'] ?? 0)
                    || (string) ($proof['activity_type'] ?? '') !== 'evaluation'
                    || (int) ($proof['activity_id'] ?? 0) !== (int) ($attempt['form_id'] ?? 0)
                    || (int) ($proof['process_id'] ?? 0) !== (int) ($attempt['process_id'] ?? 0)
                    || (int) ($proof['issued_at'] ?? 0) > time()
                    || (time() - (int) ($proof['issued_at'] ?? 0)) > 900) {
                    $this->jsonResponse(['ok' => false, 'message' => 'Debes verificar tu identidad facial antes de iniciar.'], 403); return;
                }
                $processId = max(0, (int) ($attempt['process_id'] ?? 0));
                if ($processId > 0) {
                    $availability = $this->testSessions->availabilityForProcess($processId);
                    if (empty($availability['allowed'])) {
                        $this->jsonResponse(['ok' => false, 'message' => (string) ($availability['message'] ?? 'El proceso ya no está disponible.')], 409);
                        return;
                    }
                }
                $companyId = (int) (current_user()['company_id'] ?? 0);
                $componentReview = $companyId > 0 ? (new ComponentValidationModel())->latestForUser((int) current_user()['id'], $companyId) : null;
                $facialStatus = $companyId > 0 ? (new FacialRecognitionModel())->enrollmentStatusForUser((int) current_user()['id'], $companyId) : null;
                $prerequisiteSnapshot = [
                    'component_validation_required' => (int) ($attempt['process_component_validation_required'] ?? 0),
                    'require_facial_enrollment' => (int) ($attempt['process_facial_enrollment_required'] ?? 0),
                ];
                if (!ProcessPrerequisiteService::isReady($prerequisiteSnapshot, (string) ($componentReview['outcome'] ?? '') === 'passed', $facialStatus === 'active')) {
                    $this->jsonResponse(['ok' => false, 'message' => 'No se cumplen los requisitos de componentes o enrolamiento del proceso.'], 409);
                    return;
                }
                if ((string) ($attempt['control_mode'] ?? '') === 'supervised_audio_visual') {
                    $evidenceId = max(0, (int) ($metadata['evidence_id'] ?? 0));
                    $evidence = $this->mediaEvidence->evidenceForAttempt($attemptId);
                    if ($evidenceId <= 0 || !$evidence || (int) ($evidence['id'] ?? 0) !== $evidenceId
                        || empty($evidence['consented_at']) || (string) ($evidence['status'] ?? '') !== 'recording') {
                        $this->jsonResponse(['ok' => false, 'message' => 'Acepta y autoriza los componentes audiovisuales antes de iniciar.'], 409);
                        return;
                    }
                }
            }
            $form = $this->forms->findForm((int) ($attempt['form_id'] ?? 0));
            if ($form) {
                $attempt = $this->attempts->activateTimer($attempt, $form);
            }
            if ((string) ($attempt['status'] ?? '') !== 'in_progress') {
                $this->jsonResponse(['ok' => false, 'message' => 'No se pudo activar el intento supervisado.'], 409);
                return;
            }
            if (!empty($proofKey)) unset($_SESSION['assessment_face_authorizations'][$proofKey]);
        }
        if ($eventType === 'audio_visual_recording_started') {
            $evidenceId = max(0, (int) ($metadata['evidence_id'] ?? 0));
            if ($evidenceId <= 0 || !$this->mediaEvidence->markRecordingStarted($attemptId, (int) current_user()['id'], $evidenceId)) {
                $this->jsonResponse(['ok' => false, 'message' => 'No se pudo registrar el inicio de la grabación.'], 409);
                return;
            }
        }
        $this->control->record($attempt, $eventType, $metadata, (int) ($_POST['question_id'] ?? 0));
        $this->jsonResponse(['ok' => true]);
    }

    public function mediaInit(): void
    {
        require_auth(); verify_csrf();
        $operationalSettings = (new PlatformSettingsModel())->operationalSettings();
        $this->mediaEvidence->purgeExpiredIfDue($operationalSettings['test_evidence_retention_days'] ?? 365);
        $attemptId = request_secure_id('evaluation_survey_attempt');
        $result = $this->mediaEvidence->setConsent($attemptId, (int) current_user()['id'], (string) ($_POST['consented'] ?? '0') === '1');
        if (!empty($result['ok'])) {
            $attempt = $this->mediaEvidence->attempt($attemptId, (int) current_user()['id']);
            if ($attempt) {
                $this->control->record($attempt, 'audio_visual_consent_accepted', [
                    'source' => 'media_init',
                    'video_and_microphone' => true,
                    'screen_capture' => true,
                ]);
            }
            $_SESSION['evaluation_survey_media_operational'][(string) ($result['evidence_id'] ?? 0)] = $operationalSettings;
        }
        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    public function mediaStatus(): void
    {
        require_auth();
        $attemptId = request_secure_id('evaluation_survey_attempt'); $attempt = $this->mediaEvidence->attempt($attemptId, (int) current_user()['id']);
        if (!$attempt) { $this->jsonResponse(['ok' => false, 'reason' => 'attempt_not_found'], 404); return; }
        $this->jsonResponse(['ok' => true, 'evidence' => $this->mediaEvidence->evidenceForAttempt($attemptId), 'risks' => $this->mediaEvidence->risksForAttempt($attemptId)]);
    }

    public function mediaChunk(): void
    {
        require_auth(); verify_csrf();
        $attemptId = request_secure_id('evaluation_survey_attempt'); $userId = (int) current_user()['id'];
        $result = $this->mediaEvidence->uploadChunk($attemptId, $userId, max(0, (int) ($_POST['evidence_id'] ?? 0)), max(0, (int) ($_POST['chunk_number'] ?? -1)), $_FILES['chunk'] ?? [], $_POST['mime_type'] ?? null);
        if (!empty($result['ok'])) $this->control->record($this->mediaEvidence->attempt($attemptId, $userId) ?: [], 'audio_visual_upload_completed', ['chunk_number' => (int) ($_POST['chunk_number'] ?? 0)]);
        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    public function mediaFinalize(): void
    {
        require_auth(); verify_csrf();
        $attemptId = request_secure_id('evaluation_survey_attempt'); $userId = (int) current_user()['id']; $evidenceId = max(0, (int) ($_POST['evidence_id'] ?? 0));
        $result = $this->mediaEvidence->finalize($attemptId, $userId, $evidenceId, isset($_POST['duration_seconds']) ? max(0, (int) $_POST['duration_seconds']) : null);
        unset($_SESSION['evaluation_survey_media_operational'][(string) $evidenceId]);
        $this->control->record($this->mediaEvidence->attempt($attemptId, $userId) ?: [], !empty($result['ok']) ? 'audio_visual_upload_completed' : 'audio_visual_upload_failed', ['reason' => $result['reason'] ?? null]);
        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    public function mediaRisk(): void
    {
        require_auth(); verify_csrf();
        $attemptId = request_secure_id('evaluation_survey_attempt'); $userId = (int) current_user()['id'];
        $attempt = $this->mediaEvidence->attempt($attemptId, $userId);
        if (!$attempt) { $this->jsonResponse(['ok' => false, 'reason' => 'attempt_not_found'], 404); return; }
        $eventType = (string) ($_POST['event_type'] ?? '');
        $diagnosticEvents = [
            'audio_visual_browser_compatibility', 'audio_visual_canvas_requested', 'audio_visual_canvas_ready',
            'audio_visual_canvas_failed', 'audio_visual_screen_requested', 'audio_visual_screen_failed',
            'audio_visual_screen_capture_cancelled',
            'audio_visual_capture_succeeded', 'audio_visual_capture_failed', 'audio_visual_recorder_format_selected',
            'screen_capture_upload_failed',
            'audio_visual_recorder_created', 'audio_visual_recorder_failed', 'audio_visual_recorder_error',
            'audio_visual_recorder_started', 'audio_visual_start_failed', 'screen_capture_fallback_canvas',
            'permission_or_recording_failed',
        ];
        $isDiagnosticEvent = in_array($eventType, $diagnosticEvents, true);
        if ((string) ($attempt['control_mode'] ?? '') === 'supervised_audio_visual') {
            $started = false;
            foreach ($this->control->eventsForAttempt($attemptId) as $event) {
                if ((string) ($event['event_type'] ?? '') === 'supervised_started') { $started = true; break; }
            }
            $evidence = $this->mediaEvidence->evidenceForAttempt($attemptId);
            if (!$started && empty($evidence['recording_started_at']) && !$isDiagnosticEvent) {
                $this->jsonResponse(['ok' => false, 'reason' => 'not_started'], 409);
                return;
            }
        }
        $metadata = is_array($_POST['metadata'] ?? null) ? $_POST['metadata'] : (is_string($_POST['metadata'] ?? null) ? (json_decode((string) $_POST['metadata'], true) ?: []) : []);
        $metadata['trace_user_id'] = $userId;
        $metadata['trace_company_id'] = (int) (current_user()['company_id'] ?? 0);
        $metadata['trace_recorded_at'] = date('Y-m-d H:i:s');
        $saved = $this->mediaEvidence->recordRisk($attemptId, $userId, $eventType, (string) ($_POST['severity'] ?? 'attention'), isset($_POST['confidence']) ? (float) $_POST['confidence'] : null, $metadata, max(0, (int) ($_POST['evidence_id'] ?? 0)) ?: null);
        if ($saved && in_array($eventType, ['screen_capture_upload_failed', 'recording_upload_failed'], true)) {
            $this->control->record($attempt, 'audio_visual_upload_failed', ['media_type' => $eventType === 'recording_upload_failed' ? 'video_chunk' : 'screen_capture', 'metadata' => $metadata]);
        }
        if ($saved && $eventType === 'multiple_voice_possible') {
            $this->control->record($attempt, 'multiple_voice_possible', ['source' => $metadata['source'] ?? 'browser_preliminary', 'severity' => 'attention']);
        }
        $this->jsonResponse(['ok' => $saved], $saved ? 200 : 422);
    }

    public function mediaScreenshot(): void
    {
        require_auth(); verify_csrf();
        $attemptId = request_secure_id('evaluation_survey_attempt'); $userId = (int) current_user()['id'];
        $result = $this->mediaEvidence->uploadScreenCapture($attemptId, $userId, max(0, (int) ($_POST['evidence_id'] ?? 0)), (string) ($_POST['capture_source'] ?? ''), max(0, (int) ($_POST['capture_number'] ?? -1)), $_FILES['capture'] ?? [], max(0, (int) ($_POST['question_id'] ?? 0)) ?: null, (string) ($_POST['event_type'] ?? 'periodic'));
        $captureAttempt = $this->mediaEvidence->attempt($attemptId, $userId) ?: [];
        if (!empty($result['ok'])) {
            $this->control->record($captureAttempt, 'audio_visual_screen_capture_completed', ['capture_number' => $result['capture_number'], 'source' => $result['source']]);
        } else {
            $this->control->record($captureAttempt, 'audio_visual_upload_failed', ['media_type' => 'screen_capture', 'capture_number' => (int) ($_POST['capture_number'] ?? -1), 'source' => (string) ($_POST['capture_source'] ?? ''), 'reason' => $result['reason'] ?? 'screen_capture_upload_failed']);
        }
        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    public function mediaScreenshotFile(): void
    {
        require_auth();
        if (!$this->isAdministrativeEvaluationViewer()) {
            platform_error(403, 'No tienes permiso para consultar la evidencia audiovisual.');
        }
        $attemptId = request_secure_id('evaluation_survey_attempt');
        $viewer = current_user();
        $attempt = $this->isGlobalEvaluationAdmin()
            ? $this->attempts->findAttempt($attemptId)
            : $this->attempts->findAttemptForCompany($attemptId, (int) ($viewer['company_id'] ?? 0));
        if (!$attempt) {
            platform_error(404, 'Captura no disponible.');
        }
        $captureId = max(0, (int) ($_GET['capture_id'] ?? 0));
        $file = $this->mediaEvidence->screenCaptureFile($attemptId, $captureId); if (!$file) { platform_error(404, 'Captura no disponible.'); }
        $this->mediaEvidence->auditScreenCaptureAccess($attemptId, $captureId, (int) $viewer['id']);
        header('Content-Type: ' . $file['mime_type']); header('Content-Length: ' . (string) $file['size']); header('Content-Disposition: inline; filename="captura-' . $captureId . '.jpg"'); header('Cache-Control: private, no-store'); readfile($file['path']); exit;
    }

    public function mediaFailure(): void
    {
        require_auth(); verify_csrf();
        $attemptId = request_secure_id('evaluation_survey_attempt'); $userId = (int) current_user()['id'];
        $saved = $this->mediaEvidence->markChunkUploadFailure($attemptId, $userId, max(0, (int) ($_POST['evidence_id'] ?? 0)), max(0, (int) ($_POST['chunk_number'] ?? -1)), (string) ($_POST['reason'] ?? ''));
        $this->jsonResponse(['ok' => $saved, 'status' => $saved ? 'failed' : 'invalid_media_session'], $saved ? 200 : 422);
    }

    public function mediaEvidence(): void
    {
        require_permission('manage_evaluation_surveys');
        $attemptId = request_secure_id('evaluation_survey_attempt'); $attempt = $this->attempts->findAttempt($attemptId);
        if (!$attempt) { platform_error(404, 'Evidencia audiovisual no disponible.'); }
        $evidenceId = max(0, (int) ($_GET['evidence_id'] ?? 0));
        $evidence = $evidenceId > 0 ? $this->mediaEvidence->evidenceByIdForAttempt($attemptId, $evidenceId) : $this->mediaEvidence->evidenceForAttempt($attemptId);
        $file = $this->mediaEvidence->evidenceFileForEvidence($evidence); if (!$file) { platform_error(404, 'Evidencia audiovisual no disponible.'); }
        $this->mediaEvidence->auditAccess($attemptId, (int) ($evidence['id'] ?? 0), (int) current_user()['id'], 'video_viewed');
        header('Content-Type: ' . $file['mime_type']); header('Content-Length: ' . (string) $file['size']); header('Content-Disposition: inline; filename="evaluacion-' . $attemptId . '.media"'); header('Cache-Control: private, no-store'); readfile($file['path']); exit;
    }

    public function mediaProcess(): void
    {
        require_permission('manage_evaluation_surveys'); verify_csrf();
        $attemptId = request_secure_id('evaluation_survey_attempt');
        $attempt = $this->attempts->findAttempt($attemptId);
        $evidenceId = max(0, (int) ($_POST['evidence_id'] ?? 0));
        $evidence = $attempt && $evidenceId > 0 ? $this->mediaEvidence->evidenceByIdForAttempt($attemptId, $evidenceId) : null;
        if (!$attempt || !$evidence) {
            flash('warning', 'No se encontró la evidencia audiovisual solicitada.');
        } else {
            $result = (new EvaluationSurveyMediaProcessingService(database('evaluaciones_encuestas')))->processEvidenceNow($evidenceId);
            if (!empty($result['ok'])) {
                flash('success', 'La evidencia audiovisual fue procesada correctamente y ya está disponible.');
            } else {
                flash('warning', (string) ($result['message'] ?? 'El procesamiento manual no pudo completarse. Revisa el estado del job y los fragmentos recibidos.'));
            }
        }
        $returnUrl = trim((string) ($_POST['return_url'] ?? ''));
        if ($returnUrl === '' || $returnUrl[0] !== '/' || str_starts_with($returnUrl, '//')) {
            $returnUrl = route_url('evaluation-surveys.attempt.result', $attemptId);
        }
        redirect($returnUrl);
    }

    public function mediaPartial(): void
    {
        require_permission('manage_evaluation_surveys'); verify_csrf();
        $attemptId = request_secure_id('evaluation_survey_attempt');
        $this->mediaEvidence->assemblePartial($attemptId, (int) current_user()['id']);
        redirect(route_url('evaluation-surveys.attempt.result', $attemptId));
    }

    public function questionMedia(): void
    {
        require_auth();
        $mediaId = request_secure_id('evaluation_survey_question_media');
        $db = database('evaluaciones_encuestas');
        $media = $db->fetch('SELECT m.*, q.form_id FROM evaluation_survey_question_media m JOIN evaluation_survey_questions q ON q.id=m.question_id WHERE m.id=? LIMIT 1', [$mediaId]);
        if (!$media) platform_error(404, 'Audio no disponible.');
        $form = $this->forms->findForm((int) $media['form_id']);
        if (!$form) platform_error(404, 'Audio no disponible.');
        $relative = ltrim(str_replace('..', '', (string) $media['storage_key']), '/');
        $root = realpath(BASE_PATH . '/storage/evaluation-question-media');
        $file = $root ? realpath($root . '/' . $relative) : false;
        if ($root === false || $file === false || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) platform_error(404, 'Audio no disponible.');
        header('Content-Type: ' . (string) $media['mime_type']);
        header('Content-Length: ' . (string) filesize($file));
        header('Content-Disposition: inline; filename="' . rawurlencode((string) $media['original_name']) . '"');
        header('Cache-Control: private, no-store');
        readfile($file);
        exit;
    }
}
