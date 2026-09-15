<?php
declare(strict_types=1);

final class InterviewController extends Controller
{
    private InterviewProcessModel $interviews;
    private InterviewAiService $ai;
    private InterviewFinalReportPdfService $pdf;
    private InterviewDailyPoolService $dailyPool;

    public function __construct(?Template $view = null, ?InterviewProcessModel $interviews = null, ?InterviewAiService $ai = null, ?InterviewFinalReportPdfService $pdf = null, ?InterviewDailyPoolService $dailyPool = null)
    {
        parent::__construct($view);
        $this->interviews = $interviews ?: new InterviewProcessModel();
        $this->ai = $ai ?: new InterviewAiService();
        $this->pdf = $pdf ?: new InterviewFinalReportPdfService();
        $this->dailyPool = $dailyPool ?: new InterviewDailyPoolService($this->interviews);
    }

    public function index(): void
    {
        $this->requireAnyPermission(['manage_interview_processes', 'manage_company_interviews', 'conduct_selection_interviews', 'view_interview_reports']);

        $this->render('interviews/index', [
            'title' => 'Entrevistas seleccion | e-talent',
            'currentPage' => 'interviews',
            'processes' => $this->interviews->all(),
        ]);
    }

    public function form(): void
    {
        require_company_interview_management();

        $id = request_secure_id('interview_process');
        $process = $id ? $this->interviews->find($id) : null;
        if ($id && !$process) {
            platform_error(404, 'Proceso de entrevistas no encontrado.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->saveProcess($id)) {
                return;
            }
        }

        $appointments = $id ? $this->interviews->appointments($id) : [];
        $selectedCandidates = array_map('intval', array_column($appointments, 'candidate_user_id'));
        $associatedTestProcessId = 0;
        foreach ($appointments as $appointment) {
            if ((int) ($appointment['test_process_id'] ?? 0) > 0) {
                $associatedTestProcessId = (int) $appointment['test_process_id'];
                break;
            }
        }
        $values = $_SERVER['REQUEST_METHOD'] === 'POST'
            ? array_merge($process ?: [], $_POST)
            : array_merge($process ?: [], [
                'name' => '',
                'interview_date' => date('Y-m-d'),
                'starts_at' => '09:00:00',
                'slot_duration_minutes' => 45,
                'break_minutes' => 10,
                'moderator_user_id' => (int) (current_user()['id'] ?? 0),
                'status' => 'draft',
                'test_process_id' => $associatedTestProcessId,
            ]);

        $this->render('interviews/processes/form', [
            'title' => ($id ? 'Editar proceso' : 'Nuevo proceso') . ' | e-talent',
            'currentPage' => 'interviews',
            'id' => $id,
            'values' => $values,
            'moderators' => $this->interviews->moderators(),
            'candidates' => $this->interviews->candidates(),
            'testProcesses' => $this->interviews->testProcesses(),
            'selectedCandidates' => $selectedCandidates,
        ]);
    }

    public function show(): void
    {
        $this->requireAnyPermission(['manage_interview_processes', 'manage_company_interviews', 'conduct_selection_interviews', 'view_interview_reports']);

        $id = request_secure_id('interview_process');
        $process = $this->interviews->find($id);
        if (!$process) {
            platform_error(404, 'Proceso de entrevistas no encontrado.');
        }

        $this->render('interviews/processes/show', [
            'title' => 'Agenda entrevistas | e-talent',
            'currentPage' => 'interviews',
            'process' => $process,
            'appointments' => $this->interviews->appointments($id),
            'moderators' => $this->interviews->moderators(),
        ]);
    }

    public function appointmentSchedule(): void
    {
        require_company_interview_management();
        verify_csrf();

        $id = request_secure_id('interview_appointment');
        $startAt = trim((string) ($_POST['scheduled_start_at'] ?? ''));
        $endAt = trim((string) ($_POST['scheduled_end_at'] ?? ''));
        $moderatorId = (int) ($_POST['moderator_user_id'] ?? 0);

        try {
            $this->interviews->updateAppointmentSchedule($id, $startAt, $endAt, $moderatorId);
            flash('success', 'Horario actualizado correctamente.');
        } catch (Throwable $exception) {
            flash('danger', $exception->getMessage());
        }

        redirect((string) ($_SERVER['HTTP_REFERER'] ?? route_url('interviews')));
    }

    public function room(): void
    {
        require_auth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['upload_document'])) {
            $this->uploadDocument();
            return;
        }

        $id = request_secure_id('interview_appointment');
        $appointment = $this->interviews->findAppointment($id, true);
        if (!$appointment) {
            platform_error(404, 'Entrevista no encontrada.');
        }

        $user = current_user() ?: [];
        $isCandidate = (int) ($user['id'] ?? 0) === (int) $appointment['candidate_user_id'];
        $isAssignedModerator = (int) ($user['id'] ?? 0) === (int) $appointment['moderator_user_id'];
        $isModerator = $isAssignedModerator || (has_permission('conduct_selection_interviews') && !$isCandidate);
        if (!$isModerator && !$isCandidate) {
            platform_error(403, 'No tienes permisos para acceder a esta entrevista.');
        }

        if ($isCandidate && !$isModerator && (string) ($appointment['meeting_status'] ?? '') !== 'in_progress') {
            flash('warning', 'La sala aun no esta disponible. Espera a que el moderador ingrese para habilitar la videollamada.');
            redirect(route_url('my-tests'));
        }

        $preflight = ['allowed' => true, 'checks' => [], 'report' => []];
        if ($isModerator) {
            $preflight = $this->interviews->moderatorPreflight($appointment);
            if ($this->interviews->moderatorBriefNeedsRefresh($appointment, $preflight)) {
                $this->interviews->invalidateModeratorBrief($id);
                $appointment = $this->interviews->findAppointment($id, true) ?: $appointment;
            }

            if (($appointment['moderator_brief_status'] ?? '') === 'pending') {
                try {
                    $this->interviews->queueJob($id, 'moderator_brief');
                    $this->processOneJob('moderator_brief');
                    $appointment = $this->interviews->findAppointment($id, true) ?: $appointment;
                } catch (Throwable $exception) {
                    security_log('Interview moderator brief failed for appointment ' . $id . ': ' . $exception->getMessage());
                    $appointment['moderator_brief_error'] = 'No se pudo preparar el brief automaticamente.';
                }
            }

            $preflight = $this->interviews->moderatorPreflight($appointment);
        }

        $roomBlocked = $isModerator && empty($preflight['allowed']);
        $payload = ['ok' => false, 'error' => 'La sala requiere validaciones previas.'];
        if (!$roomBlocked) {
            $dailySettings = InterviewSettings::daily();
            $daily = new DailyMeetingService($dailySettings);
            try {
                $payload = $daily->meetingPayload([
                    'room_slug' => 'e_talent-interview-' . $id,
                    'user_name' => (string) ($user['name'] ?? ($isModerator ? $appointment['moderator_name'] : $appointment['candidate_name'])),
                    'user_id' => 'user-' . (int) ($user['id'] ?? 0),
                    'is_owner' => $isModerator,
                    'transcription_enabled' => true,
                    'transcription_auto_start' => $isModerator,
                    'transcription_url' => route_url('interview-appointment.transcription', $id),
                    'transcription_snapshot_interval_seconds' => $dailySettings['transcription_snapshot_interval_seconds'] ?? 20,
                    'csrf_token' => csrf_token(),
                ]);
            } catch (Throwable $exception) {
                security_log('Interview Daily payload failed for appointment ' . $id . ': ' . $exception->getMessage());
                $payload = [
                    'ok' => false,
                    'error' => 'No se pudo preparar la videollamada. Revisa la configuracion Daily antes de iniciar la entrevista.',
                ];
            }
        }

        if ($isModerator && !$roomBlocked && !empty($payload['ok'])) {
            try {
                $this->interviews->markMeetingStarted($id, (string) ($payload['room_name'] ?? ''), (string) ($payload['url'] ?? ''));
                $appointment['meeting_status'] = 'in_progress';
            } catch (Throwable $exception) {
                security_log('Interview meeting status update failed for appointment ' . $id . ': ' . $exception->getMessage());
            }
        }

        $note = $this->interviews->noteFor($id, (int) ($user['id'] ?? 0));
        $brief = json_decode((string) ($appointment['moderator_brief_json'] ?? ''), true);
        $brief = is_array($brief) ? $brief : [];
        $brief = $this->ai->normalizeModeratorBrief($brief);

        $reportUrl = '';
        if (!empty($appointment['test_process_id']) && !empty($appointment['test_session_id'])) {
            $reportUrl = route_url('test-process.ranking-report', (int) $appointment['test_process_id'])
                . '?session=' . rawurlencode(secure_url_token((int) $appointment['test_session_id'], 'test_session'));
        }
        $reportViewUrl = $reportUrl !== '' ? $reportUrl . '&view=1' : '';
        $finalReportUrl = (string) ($appointment['final_report_status'] ?? '') === 'ready'
            ? route_url('interview-appointment.report', (int) $appointment['id'])
            : '';
        $finalReportViewUrl = $finalReportUrl !== '' ? $finalReportUrl . '?view=1' : '';
        $finalReportHtml = $this->displayFinalReportHtml($appointment);

        $this->render('interviews/appointments/room', [
            'title' => 'Sala entrevista | e-talent',
            'currentPage' => 'interviews',
            'appointment' => $appointment,
            'dailyPayload' => $payload,
            'preflight' => $preflight,
            'roomBlocked' => $roomBlocked,
            'isModerator' => $isModerator,
            'notes' => (string) ($note['notes'] ?? ''),
            'brief' => $brief,
            'reportUrl' => $reportUrl,
            'reportViewUrl' => $reportViewUrl,
            'finalReportUrl' => $finalReportUrl,
            'finalReportViewUrl' => $finalReportViewUrl,
            'finalReportHtml' => $finalReportHtml,
            'documents' => $this->interviews->documents($id),
        ]);
    }

    public function uploadDocument(): void
    {
        require_permission('conduct_selection_interviews');
        verify_csrf();
        $appointmentId = request_secure_id('interview_appointment');
        $appointment = $this->interviews->findAppointment($appointmentId);
        if (!$appointment) {
            platform_error(404, 'Entrevista no encontrada.');
        }

        $file = $_FILES['interview_document'] ?? [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            flash('danger', 'Selecciona un documento valido para cargar.');
            redirect(route_url('interview-appointment.room', $appointmentId));
        }

        $maxBytes = 10 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxBytes) {
            flash('danger', 'El documento debe pesar entre 1 byte y 10 MB.');
            redirect(route_url('interview-appointment.room', $appointmentId));
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $allowed = [
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv', 'text/markdown', 'application/json',
        ];
        if (!in_array($mime, $allowed, true)) {
            flash('danger', 'Formato no permitido. Usa PDF, Word, Excel, CSV, TXT, MD o JSON.');
            redirect(route_url('interview-appointment.room', $appointmentId));
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $storedName = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . preg_replace('/[^a-z0-9]+/i', '', $extension) : '');
        $relativePath = 'storage/interviews/' . date('Y/m') . '/' . $storedName;
        $absoluteDir = BASE_PATH . '/storage/interviews/' . date('Y/m');
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0700, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
        }
        $absolutePath = $absoluteDir . '/' . $storedName;
        if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
            throw new RuntimeException('No se pudo guardar el documento.');
        }

        $extraction = (new InterviewDocumentTextExtractor())->extract($absolutePath, $mime);
        $this->interviews->addDocument($appointmentId, [
            'document_type' => in_array((string) ($_POST['document_type'] ?? ''), ['performance', 'psychological', 'job_profile', 'resume', 'reference', 'other'], true) ? (string) $_POST['document_type'] : 'other',
            'original_name' => mb_substr(basename((string) $file['name']), 0, 255),
            'stored_name' => $storedName,
            'storage_path' => $relativePath,
            'mime_type' => $mime,
            'file_size' => (int) $file['size'],
            'sha256' => hash_file('sha256', $absolutePath),
            'extracted_text' => $extraction['text'],
            'processing_status' => $extraction['status'],
            'processing_error' => $extraction['error'],
        ], (int) (current_user()['id'] ?? 0));

        flash('success', 'Documento asociado a la entrevista.');
        redirect(route_url('interview-appointment.room', $appointmentId));
    }

    public function downloadDocument(): void
    {
        require_permission('conduct_selection_interviews');
        $documentId = request_secure_id('interview_document');
        $document = $this->interviews->document($documentId);
        if (!$document) {
            platform_error(404, 'Documento no encontrado.');
        }
        $path = BASE_PATH . '/' . ltrim((string) $document['storage_path'], '/');
        if (!is_file($path)) {
            platform_error(404, 'Archivo no disponible.');
        }
        header('Content-Type: ' . $document['mime_type']);
        header('Content-Disposition: inline; filename="' . str_replace('"', '', basename((string) $document['original_name'])) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        readfile($path);
    }

    public function saveNotes(): void
    {
        require_permission('conduct_selection_interviews');
        verify_csrf();

        $id = request_secure_id('interview_appointment');
        $this->interviews->saveNotes($id, (int) (current_user()['id'] ?? 0), trim((string) ($_POST['notes'] ?? '')));
        $this->jsonResponse(['ok' => true, 'message' => 'Apuntes guardados.']);
    }

    public function transcription(): void
    {
        require_permission('conduct_selection_interviews');
        verify_csrf();

        $id = request_secure_id('interview_appointment');
        try {
            $this->interviews->recordTranscriptionEvent(
                $id,
                (int) (current_user()['id'] ?? 0),
                (string) ($_POST['transcription_action'] ?? ''),
                (string) ($_POST['transcription_status'] ?? 'recording'),
                (string) ($_POST['transcript_text'] ?? ''),
                (string) ($_POST['message'] ?? '')
            );
            $this->jsonResponse(['ok' => true, 'message' => 'Transcripcion registrada.']);
        } catch (Throwable $exception) {
            $this->jsonResponse(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function finish(): void
    {
        require_permission('conduct_selection_interviews');
        verify_csrf();

        $id = request_secure_id('interview_appointment');
        $this->interviews->finishAppointment($id);
        $this->processOneJob('final_report');

        $this->jsonResponse(['ok' => true, 'message' => 'Entrevista finalizada. El reporte quedo en proceso.']);
    }

    public function processJob(): void
    {
        require_permission('view_interview_reports');
        verify_csrf();

        $type = (string) ($_POST['job_type'] ?? 'final_report');
        $this->processOneJob($type === 'moderator_brief' ? 'moderator_brief' : 'final_report');
        $this->jsonResponse(['ok' => true, 'message' => 'Proceso de generacion revisado.']);
    }

    public function downloadReport(): void
    {
        require_permission('view_interview_reports');

        $id = request_secure_id('interview_appointment');
        $appointment = $this->interviews->findAppointment($id);
        if (!$appointment || (string) ($appointment['final_report_status'] ?? '') !== 'ready') {
            platform_error(404, 'Reporte final no disponible.');
        }

        $pdf = $this->pdf->render($appointment, $this->displayFinalReportHtml($appointment));
        $filename = $this->pdf->filename($appointment);
        $disposition = (string) ($_GET['view'] ?? '') === '1' ? 'inline' : 'attachment';

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        echo $pdf;
    }

    public function settings(): void
    {
        require_permission('manage_interview_settings');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $daily = DailySettings::fromPost($_POST, InterviewSettings::daily());
            $ai = [
                'enabled' => isset($_POST['ai_enabled']),
                'api_key' => trim((string) ($_POST['ai_api_key'] ?? '')) ?: InterviewSettings::ai()['api_key'],
                'model' => trim((string) ($_POST['ai_model'] ?? '')),
            ];
            $errors = InterviewSettings::validationErrors($daily, $ai);
            if ($errors) {
                flash('danger', implode(' ', $errors));
            } else {
                InterviewSettings::save($_POST);
                flash('success', 'Configuracion de entrevistas guardada.');
                redirect(route_url('interviews.settings'));
            }
        }

        $this->render('interviews/settings/index', [
            'title' => 'Configuracion Entrevistas | e-talent',
            'currentPage' => 'interviews.settings',
            'daily' => InterviewSettings::daily(),
            'ai' => InterviewSettings::ai(),
            'dailyPool' => $this->dailyPool->summary(InterviewSettings::daily()),
        ]);
    }

    public function testAiConnection(): void
    {
        require_permission('manage_interview_settings');
        verify_csrf();

        $current = InterviewSettings::ai();
        $settings = [
            'enabled' => true,
            'provider' => trim((string) ($_POST['ai_provider'] ?? $current['provider'])) ?: $current['provider'],
            'base_url' => trim((string) ($_POST['ai_base_url'] ?? $current['base_url'])) ?: $current['base_url'],
            'api_key' => trim((string) ($_POST['ai_api_key'] ?? '')) !== '' ? trim((string) $_POST['ai_api_key']) : $current['api_key'],
            'model' => trim((string) ($_POST['ai_model'] ?? $current['model'])),
            'timeout_seconds' => max(10, min(180, (int) ($_POST['ai_timeout_seconds'] ?? $current['timeout_seconds']))),
            'prompt_version' => trim((string) ($_POST['ai_prompt_version'] ?? $current['prompt_version'])) ?: $current['prompt_version'],
        ];

        $result = (new InterviewAiService($settings))->testConnection();
        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    private function saveProcess(int $id): bool
    {
        verify_csrf();
        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'interview_date' => trim((string) ($_POST['interview_date'] ?? '')),
            'starts_at' => trim((string) ($_POST['starts_at'] ?? '09:00')),
            'slot_duration_minutes' => max(5, (int) ($_POST['slot_duration_minutes'] ?? 45)),
            'break_minutes' => max(0, (int) ($_POST['break_minutes'] ?? 10)),
            'moderator_user_id' => (int) ($_POST['moderator_user_id'] ?? 0),
            'status' => in_array((string) ($_POST['status'] ?? 'draft'), ['draft', 'scheduled', 'in_progress', 'closed', 'cancelled'], true) ? (string) $_POST['status'] : 'draft',
            'test_process_id' => (int) ($_POST['test_process_id'] ?? 0),
        ];
        if (strlen($data['starts_at']) === 5) {
            $data['starts_at'] .= ':00';
        }
        $candidateIds = is_array($_POST['candidate_user_ids'] ?? null) ? $_POST['candidate_user_ids'] : [];

        if ($data['name'] === '' || $data['interview_date'] === '' || $data['moderator_user_id'] <= 0) {
            flash('danger', 'Completa nombre, fecha y moderador.');
            return false;
        }

        try {
            if ($id) {
                $this->interviews->update($id, $data, $candidateIds, (int) (current_user()['id'] ?? 0));
                flash('success', 'Proceso actualizado correctamente.');
            } else {
                $id = $this->interviews->create($data, $candidateIds, (int) (current_user()['id'] ?? 0));
                flash('success', 'Proceso creado correctamente.');
            }
            redirect(route_url('interview-process.show', $id));
        } catch (Throwable $exception) {
            flash('danger', $exception->getMessage());
        }

        return false;
    }

    public function processOneJob(string $type): bool
    {
        $job = $this->interviews->claimPendingJob($type);
        if (!$job) {
            return false;
        }

        $appointment = $this->interviews->findAppointment((int) $job['appointment_id']);
        if (!$appointment) {
            $this->interviews->failFinalReport((int) $job['appointment_id'], (int) $job['id'], 'La cita asociada al trabajo no existe.', false);
            return true;
        }

        $context = $this->interviews->reportContext($appointment);
        if ($type === 'moderator_brief') {
            $result = $this->ai->moderatorBrief($context);
            if (!empty($result['ok'])) {
                $this->interviews->completeBrief((int) $appointment['id'], (int) $job['id'], $result['data']);
            } else {
                $this->interviews->failBrief((int) $appointment['id'], (int) $job['id'], (string) $result['error'], !empty($result['pending']));
            }
            return true;
        }

        $result = $this->ai->finalReport($context);
        if (!empty($result['ok'])) {
            $html = $this->finalReportHtml($result['data']);
            $this->interviews->completeFinalReport((int) $appointment['id'], (int) $job['id'], $result['data'], $html);
        } else {
            $this->interviews->failFinalReport((int) $appointment['id'], (int) $job['id'], (string) $result['error'], !empty($result['pending']));
        }

        return true;
    }

    private function finalReportHtml(array $report): string
    {
        $html = '<h2>' . e((string) ($report['title'] ?? 'Reporte final de entrevista')) . '</h2>';
        $html .= $this->reportTextSectionHtml('Resumen', (string) ($report['summary'] ?? ''));
        $html .= $this->reportEvidenceHtml($report['evidence'] ?? []);
        $html .= $this->reportListSectionHtml('Riesgos o puntos a profundizar', $report['risks'] ?? []);
        $html .= $this->reportTextSectionHtml('Recomendacion responsable', (string) ($report['recommendation'] ?? ''));
        $html .= $this->reportListSectionHtml('Siguientes pasos', $report['next_steps'] ?? []);

        return $html;
    }

    private function displayFinalReportHtml(array $appointment): string
    {
        if ((string) ($appointment['final_report_status'] ?? '') !== 'ready') {
            return '';
        }

        $report = json_decode((string) ($appointment['final_report_json'] ?? ''), true);
        if (is_array($report)) {
            return $this->finalReportHtml($report);
        }

        return (string) ($appointment['final_report_html'] ?? '');
    }

    private function reportTextSectionHtml(string $title, string $content): string
    {
        $content = trim($content);

        return '<section class="interview-report-section"><h3>' . e($title) . '</h3><p>' . nl2br(e($content ?: 'Sin informacion registrada.')) . '</p></section>';
    }

    private function reportListSectionHtml(string $title, $value): string
    {
        $items = $this->normalizeTextItems($value);
        $html = '<section class="interview-report-section"><h3>' . e($title) . '</h3>';
        if (!$items) {
            return $html . '<p>Sin informacion registrada.</p></section>';
        }

        $html .= '<ul class="interview-report-list">';
        foreach ($items as $item) {
            $html .= '<li>' . e($item) . '</li>';
        }

        return $html . '</ul></section>';
    }

    private function reportEvidenceHtml($value): string
    {
        $items = $this->normalizeEvidenceItems($value);
        $html = '<section class="interview-report-section"><h3>Evidencia observada</h3>';
        if (!$items) {
            return $html . '<p>Sin informacion registrada.</p></section>';
        }

        $html .= '<div class="interview-evidence-list">';
        foreach ($items as $item) {
            $html .= '<article class="interview-evidence-card">';
            $html .= '<div class="interview-evidence-head">';
            $html .= '<span class="interview-evidence-source">' . e($item['source']) . '</span>';
            $html .= '<h4>' . e($item['finding']) . '</h4>';
            $html .= '</div>';
            $html .= '<dl class="interview-evidence-detail">';
            $html .= '<div><dt>Soporte</dt><dd>' . e($item['support']) . '</dd></div>';
            $html .= '<div><dt>Interpretacion prudente</dt><dd>' . e($item['interpretation']) . '</dd></div>';
            $html .= '<div><dt>Que profundizar</dt><dd>' . e($item['follow_up']) . '</dd></div>';
            $html .= '</dl>';
            $html .= '</article>';
        }

        return $html . '</div></section>';
    }

    private function normalizeEvidenceItems($value): array
    {
        if (!is_array($value)) {
            $text = trim((string) $value);
            return $text === '' ? [] : [[
                'source' => 'General',
                'finding' => $text,
                'support' => 'Reporte final',
                'interpretation' => 'Debe contrastarse con entrevista, apuntes y antecedentes del proceso.',
                'follow_up' => 'Profundizar con ejemplos laborales concretos antes de tomar decisiones.',
            ]];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                if (!$item) {
                    continue;
                }
                $labelValueText = $this->labelValueText($item);
                if ($labelValueText !== '') {
                    $items[] = [
                        'source' => 'Psicometria',
                        'finding' => 'Indicadores del informe psicometrico',
                        'support' => $labelValueText,
                        'interpretation' => 'Estos datos son indicadores contextuales del reporte; no deben leerse como conclusion absoluta ni como diagnostico.',
                        'follow_up' => 'Contrastar los indicadores con respuestas, ejemplos laborales y observaciones de la entrevista.',
                    ];
                    continue;
                }

                $finding = $this->firstText($item, ['finding', 'hallazgo', 'title', 'titulo', 'summary', 'resumen']);
                $support = $this->firstText($item, ['support', 'soporte', 'evidence', 'detalle', 'detail', 'quote']);
                $interpretation = $this->firstText($item, ['interpretation', 'interpretacion', 'meaning', 'lectura']);
                $followUp = $this->firstText($item, ['follow_up', 'followup', 'profundizar', 'next_step', 'question']);
                $source = $this->sourceLabel($this->firstText($item, ['source', 'origen', 'tipo']));
                $fallback = $this->compactArrayText($item);
                if ($finding === '' && $support === '' && $interpretation === '' && $followUp === '' && $fallback === '') {
                    continue;
                }

                $items[] = [
                    'source' => $source,
                    'finding' => $finding !== '' ? $finding : ($fallback ?: 'Evidencia registrada'),
                    'support' => $support !== '' ? $support : 'Informacion disponible en el reporte.',
                    'interpretation' => $interpretation !== '' ? $interpretation : 'Indicador contextual; no debe leerse como conclusion absoluta.',
                    'follow_up' => $followUp !== '' ? $followUp : 'Contrastar con ejemplos observables y antecedentes del proceso.',
                ];
                continue;
            }

            $text = trim((string) $item);
            if ($text !== '') {
                $items[] = [
                    'source' => 'General',
                    'finding' => $text,
                    'support' => 'Reporte final',
                    'interpretation' => 'Indicador contextual; requiere contraste con evidencia observable.',
                    'follow_up' => 'Solicitar un ejemplo concreto asociado a este punto.',
                ];
            }
        }

        return $items;
    }

    private function normalizeTextItems($value): array
    {
        if (!is_array($value)) {
            $text = trim((string) $value);
            return $text === '' ? [] : [$text];
        }

        $items = [];
        foreach ($value as $item) {
            $text = is_array($item) ? $this->compactArrayText($item) : trim((string) $item);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        return $items;
    }

    private function firstText(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }
            $value = $item[$key];
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function compactArrayText(array $item): string
    {
        $parts = [];
        foreach ($item as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $parts[] = trim((string) $value);
                continue;
            }
            if (is_array($value)) {
                $text = $this->labelValueText([$value]) ?: $this->compactArrayText($value);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return implode(' ', array_values(array_unique($parts)));
    }

    private function labelValueText(array $items): string
    {
        $parts = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $value = $item['value'] ?? null;
            if ($label === '' || (!is_scalar($value) && $value !== null)) {
                continue;
            }
            $valueText = trim((string) $value);
            if ($valueText === '') {
                continue;
            }
            $parts[] = $label . ': ' . $valueText;
        }

        return implode('; ', $parts);
    }

    private function sourceLabel(string $source): string
    {
        $source = strtolower(trim($source));
        $labels = [
            'entrevista' => 'Entrevista',
            'apuntes' => 'Apuntes',
            'transcripcion' => 'Transcripcion',
            'psicometria' => 'Psicometria',
            'mixto' => 'Mixto',
        ];

        return $labels[$source] ?? 'General';
    }

    private function listText($value): string
    {
        if (is_array($value)) {
            return implode("\n", array_map(static fn($item): string => '- ' . (is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE)), $value));
        }

        return (string) $value;
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private function requireAnyPermission(array $permissions): void
    {
        require_auth();
        foreach ($permissions as $permission) {
            if (has_permission((string) $permission)) {
                return;
            }
        }

        platform_error(403, 'No tienes permisos para acceder a esta seccion.', [
            'detailRows' => [
                'Permisos requeridos' => implode(', ', $permissions),
            ],
        ]);
    }
}
