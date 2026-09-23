<?php
declare(strict_types=1);

final class InterviewProcessModel
{
    private Database $db;
    private string $coreSchema;
    private string $testsSchema;
    private PostulantReportDataService $postulantReports;

    public function __construct(?Database $db = null, ?PostulantReportDataService $postulantReports = null)
    {
        $this->db = $db ?: database('interviews');
        $this->coreSchema = database_identifier('core');
        $this->testsSchema = database_identifier('tests');
        $this->postulantReports = $postulantReports ?: new PostulantReportDataService();
    }

    public function all(): array
    {
        return $this->db->fetchAll("
            SELECT p.*,
                   moderator.name AS moderator_name,
                   COUNT(a.id) AS appointments_count,
                   SUM(CASE WHEN a.meeting_status = 'finished' THEN 1 ELSE 0 END) AS finished_count
            FROM interview_processes p
            LEFT JOIN {$this->coreSchema}.users moderator ON moderator.id = p.moderator_user_id
            LEFT JOIN interview_appointments a ON a.process_id = p.id
            WHERE " . $this->companyScopeSql('p') . "
            GROUP BY p.id
            ORDER BY p.interview_date DESC, p.starts_at DESC, p.id DESC
        ", $this->companyScopeParams());
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch("
            SELECT p.*, moderator.name AS moderator_name
            FROM interview_processes p
            LEFT JOIN {$this->coreSchema}.users moderator ON moderator.id = p.moderator_user_id
            WHERE p.id = ? AND " . $this->companyScopeSql('p') . "
            LIMIT 1
        ", array_merge([$id], $this->companyScopeParams()));
    }

    public function appointments(int $processId): array
    {
        return $this->db->fetchAll("
            SELECT a.*,
                   candidate.name AS candidate_name,
                   candidate.email AS candidate_email,
                   candidate.rut AS candidate_rut,
                   moderator.name AS moderator_name,
                   tp.name AS test_process_name,
                   ts.status AS test_session_status
            FROM interview_appointments a
            LEFT JOIN {$this->coreSchema}.users candidate ON candidate.id = a.candidate_user_id
            LEFT JOIN {$this->coreSchema}.users moderator ON moderator.id = a.moderator_user_id
            LEFT JOIN {$this->testsSchema}.test_processes tp ON tp.id = a.test_process_id
            LEFT JOIN {$this->testsSchema}.test_sessions ts ON ts.id = a.test_session_id
            WHERE a.process_id = ? AND " . $this->companyScopeSql('p') . "
            ORDER BY a.scheduled_start_at ASC, a.id ASC
        ", array_merge([$processId], $this->companyScopeParams()));
    }

    public function findAppointment(int $id, bool $includeParticipant = false): ?array
    {
        $scopeSql = $this->companyScopeSql('p');
        $scopeParams = $this->companyScopeParams();
        if ($includeParticipant) {
            $userId = (int) (current_user()['id'] ?? 0);
            if ($userId > 0) {
                $scopeSql = '(' . $scopeSql . ' OR a.candidate_user_id = ? OR a.moderator_user_id = ?)';
                $scopeParams = array_merge($scopeParams, [$userId, $userId]);
            }
        }

        return $this->db->fetch("
            SELECT a.*,
                   p.name AS process_name,
                   p.interview_date,
                   candidate.name AS candidate_name,
                   candidate.email AS candidate_email,
                   candidate.rut AS candidate_rut,
                   moderator.name AS moderator_name,
                   tp.name AS test_process_name,
                   tp.code AS test_process_code
            FROM interview_appointments a
            JOIN interview_processes p ON p.id = a.process_id
            LEFT JOIN {$this->coreSchema}.users candidate ON candidate.id = a.candidate_user_id
            LEFT JOIN {$this->coreSchema}.users moderator ON moderator.id = a.moderator_user_id
            LEFT JOIN {$this->testsSchema}.test_processes tp ON tp.id = a.test_process_id
            WHERE a.id = ? AND {$scopeSql}
            LIMIT 1
        ", array_merge([$id], $scopeParams));
    }

    public function moderators(): array
    {
        return $this->db->fetchAll("
            SELECT u.id, u.name, u.email, p.name AS profile_name
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.role_profiles p ON p.id = u.profile_id
            WHERE u.is_active = 1 AND " . $this->companyScopeSql('u') . "
              AND (
                p.role_key <> 'usuario'
                OR p.permissions LIKE '%conduct_selection_interviews%'
                OR p.permissions LIKE '%manage_interview_processes%'
              )
            ORDER BY u.name
        ", $this->companyScopeParams());
    }

    public function candidates(): array
    {
        return $this->db->fetchAll("
            SELECT u.id, u.name, u.email, u.rut, c.name AS company_name
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            LEFT JOIN {$this->coreSchema}.role_profiles p ON p.id = u.profile_id
            WHERE u.is_active = 1 AND " . $this->companyScopeSql('u') . "
              AND COALESCE(p.role_key, u.role) = 'usuario'
            ORDER BY u.name
        ", $this->companyScopeParams());
    }

    public function testProcesses(): array
    {
        return $this->db->fetchAll("
            SELECT id, code, name, status
            FROM {$this->testsSchema}.test_processes tp
            WHERE " . $this->companyScopeSql('tp') . "
            ORDER BY tp.created_at DESC, tp.id DESC
        ", $this->companyScopeParams());
    }

    public function futureDailySubEvents(): array
    {
        $rows = $this->db->fetchAll("
            SELECT a.id,
                   p.name AS process_name,
                   a.scheduled_start_at,
                   TIMESTAMPDIFF(MINUTE, a.scheduled_start_at, a.scheduled_end_at) AS duration_minutes
            FROM interview_appointments a
            JOIN interview_processes p ON p.id = a.process_id
            WHERE a.meeting_status NOT IN ('cancelled', 'finished')
              AND a.scheduled_start_at >= NOW()
              AND " . $this->companyScopeSql('p') . "
            ORDER BY a.scheduled_start_at ASC, a.id ASC
        ", $this->companyScopeParams());

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'name' => (string) ($row['process_name'] ?? 'Entrevista'),
                'starts_at' => (string) ($row['scheduled_start_at'] ?? ''),
                'duration_minutes' => max(0, (int) ($row['duration_minutes'] ?? 0)),
                'participant_count' => 2,
            ];
        }

        return $items;
    }

    public function agendaForUser(int $userId, bool $canSeeAll): array
    {
        $dayStart = date('Y-m-d 00:00:00');
        $dayEnd = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $where = $canSeeAll ? 'AND ' . $this->companyScopeSql('p') : 'AND (a.moderator_user_id = ? OR a.candidate_user_id = ?)';
        $params = array_merge([$dayStart, $dayEnd], $canSeeAll ? $this->companyScopeParams() : [$userId, $userId]);
        return $this->db->fetchAll("\n            SELECT a.id, a.scheduled_start_at, a.scheduled_end_at, a.meeting_status,\n                   p.name AS process_name, candidate.name AS candidate_name,\n                   moderator.name AS moderator_name\n            FROM interview_appointments a\n            JOIN interview_processes p ON p.id = a.process_id\n            LEFT JOIN {$this->coreSchema}.users candidate ON candidate.id = a.candidate_user_id\n            LEFT JOIN {$this->coreSchema}.users moderator ON moderator.id = a.moderator_user_id\n            WHERE a.scheduled_start_at >= ? AND a.scheduled_start_at < ?\n              AND a.meeting_status <> 'cancelled'\n              AND p.status <> 'cancelled'\n              {$where}\n            ORDER BY a.scheduled_start_at ASC, a.id ASC\n        ", $params);
    }

    public function appointmentsForCandidate(int $candidateId): array
    {
        if ($candidateId <= 0) {
            return [];
        }

        return $this->db->fetchAll("
            SELECT a.*,
                   p.name AS process_name,
                   p.interview_date,
                   moderator.name AS moderator_name
            FROM interview_appointments a
            JOIN interview_processes p ON p.id = a.process_id
            LEFT JOIN {$this->coreSchema}.users moderator ON moderator.id = a.moderator_user_id
            WHERE a.candidate_user_id = ?
              AND p.status <> 'cancelled'
              AND a.meeting_status NOT IN ('cancelled', 'finished')
            ORDER BY a.scheduled_start_at ASC, a.id ASC
        ", [$candidateId]);
    }

    public function create(array $data, array $candidateIds, int $createdBy): int
    {
        return (int) $this->db->transaction(function () use ($data, $candidateIds, $createdBy): int {
            $id = $this->db->insert('
                INSERT INTO interview_processes (name, interview_date, starts_at, slot_duration_minutes, break_minutes, moderator_user_id, status, created_by, company_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ', [
                $data['name'],
                $data['interview_date'],
                $data['starts_at'],
                $data['slot_duration_minutes'],
                $data['break_minutes'],
                $data['moderator_user_id'],
                $data['status'],
                $createdBy ?: null,
                $this->currentCompanyId(),
            ]);

            $this->syncAppointments($id, $data, $candidateIds, $createdBy);

            return $id;
        });
    }

    public function update(int $id, array $data, array $candidateIds, int $updatedBy): void
    {
        $this->db->transaction(function () use ($id, $data, $candidateIds, $updatedBy): void {
            $this->db->execute("
                UPDATE interview_processes
                SET name = ?, interview_date = ?, starts_at = ?, slot_duration_minutes = ?, break_minutes = ?, moderator_user_id = ?, status = ?
                WHERE id = ? AND " . $this->companyScopeSql('interview_processes') . "
            ", [
                $data['name'],
                $data['interview_date'],
                $data['starts_at'],
                $data['slot_duration_minutes'],
                $data['break_minutes'],
                $data['moderator_user_id'],
                $data['status'],
                $id,
                ...$this->companyScopeParams(),
            ]);

            $this->syncAppointments($id, $data, $candidateIds, $updatedBy);
        });
    }

    public function updateAppointmentSchedule(int $appointmentId, string $startAt, string $endAt, int $moderatorId): void
    {
        $appointment = $this->findAppointment($appointmentId);
        if (!$appointment) {
            throw new RuntimeException('Entrevista no encontrada.');
        }

        $this->assertProcessSlotsAvailable([[
            'candidate_id' => (int) $appointment['candidate_user_id'],
            'moderator_id' => $moderatorId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'ignore_id' => $appointmentId,
        ]]);

        $this->db->execute('
            UPDATE interview_appointments
            SET scheduled_start_at = ?, scheduled_end_at = ?, moderator_user_id = ?, manual_override = 1
            WHERE id = ?
        ', [$startAt, $endAt, $moderatorId, $appointmentId]);
    }

    public function noteFor(int $appointmentId, int $userId): ?array
    {
        return $this->db->fetch('
            SELECT * FROM interview_notes WHERE appointment_id = ? AND author_user_id = ? LIMIT 1
        ', [$appointmentId, $userId]);
    }

    public function documents(int $appointmentId): array
    {
        return $this->db->fetchAll('
            SELECT id, appointment_id, document_type, original_name, mime_type, file_size, sha256,
                   processing_status, processing_error, uploaded_by, created_at, updated_at
            FROM interview_documents
            WHERE appointment_id = ?
            ORDER BY created_at DESC, id DESC
        ', [$appointmentId]);
    }

    public function document(int $documentId): ?array
    {
        return $this->db->fetch('SELECT * FROM interview_documents WHERE id = ? LIMIT 1', [$documentId]);
    }

    public function addDocument(int $appointmentId, array $document, int $uploadedBy): int
    {
        return (int) $this->db->insert('
            INSERT INTO interview_documents
                (appointment_id, document_type, original_name, stored_name, storage_path, mime_type, file_size, sha256, extracted_text, processing_status, processing_error, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ', [
            $appointmentId, $document['document_type'], $document['original_name'], $document['stored_name'],
            $document['storage_path'], $document['mime_type'], $document['file_size'], $document['sha256'],
            $document['extracted_text'] ?: null, $document['processing_status'], $document['processing_error'] ?: null,
            $uploadedBy ?: null,
        ]);
    }

    public function saveNotes(int $appointmentId, int $userId, string $notes): void
    {
        $this->db->execute('
            INSERT INTO interview_notes (appointment_id, author_user_id, notes)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE notes = VALUES(notes), updated_at = CURRENT_TIMESTAMP
        ', [$appointmentId, $userId, $notes]);
    }

    public function recordTranscriptionEvent(int $appointmentId, int $userId, string $action, string $status, string $transcript, string $message = ''): void
    {
        $allowedActions = ['start', 'snapshot', 'stop', 'failed'];
        $allowedStatuses = ['idle', 'recording', 'processing', 'available', 'failed'];
        if (!in_array($action, $allowedActions, true) || !in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('Estado de transcripcion invalido.');
        }

        $this->db->transaction(function () use ($appointmentId, $userId, $action, $status, $transcript, $message): void {
            $this->db->insert('
                INSERT INTO interview_transcription_events (appointment_id, action, status, transcript_text, message, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ', [$appointmentId, $action, $status, $transcript !== '' ? $transcript : null, $message !== '' ? $message : null, $userId ?: null]);

            $this->db->execute('
                UPDATE interview_appointments
                SET transcript_status = ?,
                    transcript_text = CASE WHEN ? <> "" THEN ? ELSE transcript_text END,
                    transcript_last_snapshot_at = CASE WHEN ? IN ("snapshot", "stop", "failed") THEN NOW() ELSE transcript_last_snapshot_at END
                WHERE id = ?
            ', [$status, $transcript, $transcript, $action, $appointmentId]);
        });
    }

    public function markMeetingStarted(int $appointmentId, string $roomName, string $roomUrl): void
    {
        $this->db->execute('
            UPDATE interview_appointments
            SET meeting_status = "in_progress", daily_room_name = ?, daily_room_url = ?
            WHERE id = ?
        ', [$roomName, $roomUrl, $appointmentId]);
    }

    public function finishAppointment(int $appointmentId): void
    {
        $this->db->execute('
            UPDATE interview_appointments
            SET meeting_status = "finished", finished_at = COALESCE(finished_at, NOW()), final_report_status = "pending"
            WHERE id = ?
        ', [$appointmentId]);
        $this->queueJob($appointmentId, 'final_report');
    }

    public function queueJob(int $appointmentId, string $type): void
    {
        $existing = $this->db->fetch('
            SELECT id
            FROM interview_report_jobs
            WHERE appointment_id = ? AND job_type = ? AND status IN ("pending", "processing")
            LIMIT 1
        ', [$appointmentId, $type]);
        if ($existing) {
            return;
        }

        $this->db->insert('
            INSERT INTO interview_report_jobs (appointment_id, job_type, status)
            VALUES (?, ?, "pending")
        ', [$appointmentId, $type]);
    }

    public function pendingJob(string $type): ?array
    {
        return $this->db->fetch('
            SELECT * FROM interview_report_jobs
            WHERE job_type = ? AND status = "pending"
            ORDER BY created_at ASC, id ASC
            LIMIT 1
        ', [$type]);
    }

    public function claimPendingJob(string $type, int $maxAttempts = 3): ?array
    {
        $this->db->execute('
            UPDATE interview_report_jobs
            SET status = "pending", locked_at = NULL
            WHERE status = "processing" AND locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ');

        $job = $this->db->fetch('
            SELECT * FROM interview_report_jobs
            WHERE job_type = ? AND status = "pending" AND attempts < ?
            ORDER BY created_at ASC, id ASC
            LIMIT 1
        ', [$type, $maxAttempts]);
        if (!$job) {
            return null;
        }

        $changed = $this->db->execute('
            UPDATE interview_report_jobs
            SET status = "processing", attempts = attempts + 1, locked_at = NOW()
            WHERE id = ? AND status = "pending" AND attempts < ?
        ', [(int) $job['id'], $maxAttempts]);
        if ($changed !== 1) {
            return null;
        }

        $job['status'] = 'processing';
        $job['attempts'] = (int) $job['attempts'] + 1;
        return $job;
    }

    public function markJobProcessing(int $jobId): void
    {
        $this->db->execute('
            UPDATE interview_report_jobs
            SET status = "processing", attempts = attempts + 1, locked_at = NOW()
            WHERE id = ?
        ', [$jobId]);
    }

    public function completeBrief(int $appointmentId, int $jobId, array $brief): void
    {
        $this->db->execute('
            UPDATE interview_appointments
            SET moderator_brief_status = "ready", moderator_brief_json = ?, moderator_brief_error = NULL
            WHERE id = ?
        ', [json_encode($brief, JSON_UNESCAPED_UNICODE), $appointmentId]);
        $this->completeJob($jobId);
    }

    public function failBrief(int $appointmentId, int $jobId, string $error, bool $pending): void
    {
        $this->db->execute('
            UPDATE interview_appointments
            SET moderator_brief_status = ?, moderator_brief_error = ?
            WHERE id = ?
        ', [$pending ? 'pending' : 'failed', $error, $appointmentId]);
        $pending ? $this->resetJob($jobId, $error) : $this->failJob($jobId, $error);
    }

    public function completeFinalReport(int $appointmentId, int $jobId, array $report, string $html): void
    {
        $this->db->execute('
            UPDATE interview_appointments
            SET final_report_status = "ready", final_report_json = ?, final_report_html = ?, final_report_error = NULL, final_report_generated_at = NOW()
            WHERE id = ?
        ', [json_encode($report, JSON_UNESCAPED_UNICODE), $html, $appointmentId]);
        $this->completeJob($jobId);
    }

    public function failFinalReport(int $appointmentId, int $jobId, string $error, bool $pending): void
    {
        $this->db->execute('
            UPDATE interview_appointments
            SET final_report_status = ?, final_report_error = ?
            WHERE id = ?
        ', [$pending ? 'pending' : 'failed', $error, $appointmentId]);
        $pending ? $this->resetJob($jobId, $error) : $this->failJob($jobId, $error);
    }

    public function reportContext(array $appointment): array
    {
        $note = $this->db->fetch('
            SELECT notes FROM interview_notes WHERE appointment_id = ? ORDER BY updated_at DESC LIMIT 1
        ', [(int) $appointment['id']]);

        $documents = $this->db->fetchAll('
            SELECT id, document_type, original_name, mime_type, file_size, processing_status, extracted_text, created_at
            FROM interview_documents
            WHERE appointment_id = ?
            ORDER BY created_at ASC, id ASC
        ', [(int) $appointment['id']]);

        $documentContext = [];
        foreach ($documents as $document) {
            $documentContext[] = [
                'id' => (int) $document['id'],
                'type' => (string) $document['document_type'],
                'name' => (string) $document['original_name'],
                'mime_type' => (string) $document['mime_type'],
                'status' => (string) $document['processing_status'],
                'text' => mb_substr((string) ($document['extracted_text'] ?? ''), 0, 18000),
                'created_at' => (string) $document['created_at'],
            ];
        }

        return [
            'candidate' => [
                'name' => $appointment['candidate_name'] ?? '',
                'email' => $appointment['candidate_email'] ?? '',
                'rut' => $appointment['candidate_rut'] ?? '',
            ],
            'process' => [
                'name' => $appointment['process_name'] ?? '',
                'interview_date' => $appointment['interview_date'] ?? '',
                'test_process' => $appointment['test_process_name'] ?? '',
            ],
            'report' => $this->candidateReportSummary($appointment),
            'transcript' => (string) ($appointment['transcript_text'] ?? ''),
            'notes' => (string) ($note['notes'] ?? ''),
            'documents' => $documentContext,
        ];
    }

    public function moderatorPreflight(array $appointment): array
    {
        $checks = [];
        $hasAssociation = !empty($appointment['test_process_id']) && !empty($appointment['test_session_id']);
        $checks[] = [
            'label' => 'Proceso y sesión de evaluación asociados',
            'status' => $hasAssociation ? 'ok' : 'error',
            'detail' => $hasAssociation ? 'La entrevista tiene una evaluación vinculada.' : 'Asocia un proceso y una sesión de evaluación antes de ingresar.',
            'critical' => true,
        ];

        $report = $hasAssociation ? $this->candidateReportSummary($appointment) : ['available' => false];
        $checks[] = [
            'label' => 'Informe psicométrico disponible',
            'status' => !empty($report['available']) ? 'ok' : 'error',
            'detail' => !empty($report['available']) ? 'El informe está disponible para consulta.' : (string) ($report['message'] ?? 'El informe no está disponible.'),
            'critical' => true,
        ];

        $briefReady = (string) ($appointment['moderator_brief_status'] ?? '') === 'ready';
        $checks[] = [
            'label' => 'Resumen y preguntas del moderador',
            'status' => $briefReady ? 'ok' : 'warning',
            'detail' => $briefReady ? 'El brief está preparado.' : 'El brief se generará al reintentar el ingreso.',
            'critical' => false,
        ];

        $checks[] = [
            'label' => 'Transcripción',
            'status' => 'ok',
            'detail' => 'La transcripción se habilitará al iniciar la sala.',
            'critical' => false,
        ];

        $hasCriticalError = false;
        foreach ($checks as $check) {
            if (!empty($check['critical']) && $check['status'] === 'error') {
                $hasCriticalError = true;
                break;
            }
        }

        return [
            'allowed' => !$hasCriticalError,
            'checks' => $checks,
            'report' => $report,
        ];
    }

    public function invalidateModeratorBrief(int $appointmentId): void
    {
        $this->db->execute('
            UPDATE interview_appointments
            SET moderator_brief_status = "pending", moderator_brief_json = NULL, moderator_brief_error = NULL
            WHERE id = ?
        ', [$appointmentId]);
    }

    public function moderatorBriefNeedsRefresh(array $appointment, array $preflight): bool
    {
        if (empty($preflight['report']['available']) || (string) ($appointment['moderator_brief_status'] ?? '') !== 'ready') {
            return false;
        }

        $brief = json_decode((string) ($appointment['moderator_brief_json'] ?? ''), true);
        $summary = mb_strtolower(trim((string) ($brief['summary'] ?? '')));
        return $summary === '' || strpos($summary, 'no hay informe') !== false;
    }

    private function candidateReportSummary(array $appointment): array
    {
        if (empty($appointment['test_process_id']) || empty($appointment['candidate_user_id'])) {
            return ['available' => false, 'message' => 'No hay informe de evaluacion asociado.'];
        }

        $payload = $this->postulantReports->payloadForProcessUser(
            (int) $appointment['test_process_id'],
            (int) $appointment['candidate_user_id']
        );

        if (empty($payload['available'])) {
            return [
                'available' => false,
                'test_process_id' => (int) $appointment['test_process_id'],
                'test_session_id' => (int) ($appointment['test_session_id'] ?? 0),
                'message' => (string) ($payload['message'] ?? 'Informe de evaluacion no disponible.'),
                'warnings' => $payload['warnings'] ?? [],
            ];
        }

        return [
            'available' => true,
            'test_process_id' => (int) $appointment['test_process_id'],
            'test_session_id' => (int) ($appointment['test_session_id'] ?? 0),
            'message' => 'Informe psicometrico estructurado disponible.',
            'candidate' => $payload['candidate'] ?? [],
            'process' => $payload['process'] ?? [],
            'ranking_row' => $payload['ranking_row'] ?? [],
            'structured_summary' => $payload['structured_summary'] ?? [],
        ];
    }

    private function syncAppointments(int $processId, array $data, array $candidateIds, int $userId): void
    {
        $candidateIds = array_values(array_unique(array_filter(array_map('intval', $candidateIds))));
        $existing = $this->appointments($processId);
        $existingByCandidate = [];
        foreach ($existing as $row) {
            $existingByCandidate[(int) $row['candidate_user_id']] = $row;
        }

        if ($candidateIds) {
            $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
            $params = array_merge([$processId], $candidateIds);
            $this->db->execute("
                UPDATE interview_appointments
                SET meeting_status = 'cancelled'
                WHERE process_id = ? AND candidate_user_id NOT IN ({$placeholders}) AND meeting_status <> 'finished'
            ", $params);
        }

        $start = new DateTimeImmutable($data['interview_date'] . ' ' . $data['starts_at']);
        $duration = max(5, (int) $data['slot_duration_minutes']);
        $break = max(0, (int) $data['break_minutes']);
        $testProcessId = (int) ($data['test_process_id'] ?? 0);
        $candidateSlots = [];

        $latestSessionIds = $this->latestSessionIds($candidateIds, $testProcessId);
        foreach ($candidateIds as $index => $candidateId) {
            $slotStart = $start->modify('+' . (($duration + $break) * $index) . ' minutes');
            $slotEnd = $slotStart->modify('+' . $duration . ' minutes');
            $startAt = $slotStart->format('Y-m-d H:i:s');
            $endAt = $slotEnd->format('Y-m-d H:i:s');
            $appointment = $existingByCandidate[$candidateId] ?? null;

            if ($appointment && (int) $appointment['manual_override'] === 1) {
                continue;
            }

            $candidateSlots[] = [
                'candidate_id' => $candidateId,
                'moderator_id' => (int) $data['moderator_user_id'],
                'start_at' => $startAt,
                'end_at' => $endAt,
                'ignore_id' => $appointment ? (int) $appointment['id'] : null,
            ];
        }

        $this->assertProcessSlotsAvailable($candidateSlots);

        foreach ($candidateIds as $index => $candidateId) {
            $slotStart = $start->modify('+' . (($duration + $break) * $index) . ' minutes');
            $slotEnd = $slotStart->modify('+' . $duration . ' minutes');
            $startAt = $slotStart->format('Y-m-d H:i:s');
            $endAt = $slotEnd->format('Y-m-d H:i:s');
            $appointment = $existingByCandidate[$candidateId] ?? null;
            $testSessionId = $latestSessionIds[$candidateId] ?? null;

            if ($appointment && (int) $appointment['manual_override'] === 1) {
                $associationChanged = (int) ($appointment['test_process_id'] ?? 0) !== $testProcessId
                    || (int) ($appointment['test_session_id'] ?? 0) !== (int) ($testSessionId ?? 0);
                $this->db->execute('
                    UPDATE interview_appointments
                    SET moderator_user_id = ?, test_process_id = ?, test_session_id = ?,
                        moderator_brief_status = IF(?, "pending", moderator_brief_status),
                        moderator_brief_json = IF(?, NULL, moderator_brief_json),
                        moderator_brief_error = IF(?, NULL, moderator_brief_error),
                        meeting_status = IF(meeting_status = "cancelled", "scheduled", meeting_status)
                    WHERE id = ?
                ', [
                    (int) $data['moderator_user_id'],
                    $testProcessId ?: null,
                    $testSessionId ?: null,
                    $associationChanged ? 1 : 0,
                    $associationChanged ? 1 : 0,
                    $associationChanged ? 1 : 0,
                    (int) $appointment['id'],
                ]);
                continue;
            }

            if ($appointment) {
                $associationChanged = (int) ($appointment['test_process_id'] ?? 0) !== $testProcessId
                    || (int) ($appointment['test_session_id'] ?? 0) !== (int) ($testSessionId ?? 0);
                $this->db->execute('
                    UPDATE interview_appointments
                    SET moderator_user_id = ?, test_process_id = ?, test_session_id = ?, scheduled_start_at = ?, scheduled_end_at = ?,
                        moderator_brief_status = IF(?, "pending", moderator_brief_status),
                        moderator_brief_json = IF(?, NULL, moderator_brief_json),
                        moderator_brief_error = IF(?, NULL, moderator_brief_error),
                        meeting_status = IF(meeting_status = "cancelled", "scheduled", meeting_status)
                    WHERE id = ?
                ', [
                    (int) $data['moderator_user_id'],
                    $testProcessId ?: null,
                    $testSessionId ?: null,
                    $startAt,
                    $endAt,
                    $associationChanged ? 1 : 0,
                    $associationChanged ? 1 : 0,
                    $associationChanged ? 1 : 0,
                    (int) $appointment['id'],
                ]);
                continue;
            }

            $this->db->insert('
                INSERT INTO interview_appointments (process_id, candidate_user_id, moderator_user_id, test_process_id, test_session_id, scheduled_start_at, scheduled_end_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ', [$processId, $candidateId, (int) $data['moderator_user_id'], $testProcessId ?: null, $testSessionId ?: null, $startAt, $endAt, $userId ?: null]);
        }
    }

    /** @return array<int, int> */
    private function latestSessionIds(array $candidateIds, int $testProcessId): array
    {
        if ($testProcessId <= 0 || !$candidateIds) {
            return [];
        }
        $candidateIds = array_values(array_unique(array_map('intval', $candidateIds)));
        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $rows = $this->db->fetchAll("
            SELECT user_id, id
            FROM {$this->testsSchema}.test_sessions
            WHERE process_id = ? AND user_id IN ({$placeholders})
            ORDER BY user_id, status = 'completed' DESC, completed_at DESC, created_at DESC, id DESC
        ", array_merge([$testProcessId], $candidateIds));
        $latest = [];
        foreach ($rows as $row) {
            $userId = (int) $row['user_id'];
            if (!isset($latest[$userId])) {
                $latest[$userId] = (int) $row['id'];
            }
        }
        return $latest;
    }

    private function assertProcessSlotsAvailable(array $slots): void
    {
        $conflicts = [];
        foreach ($slots as $slot) {
            $this->assertSlotChronology((string) $slot['start_at'], (string) $slot['end_at']);
            foreach ($this->slotConflicts($slot) as $conflict) {
                $name = trim((string) ($conflict['candidate_name'] ?? 'Postulante'));
                $process = trim((string) ($conflict['process_name'] ?? 'otro proceso'));
                $when = $this->formatDateTime((string) ($conflict['scheduled_start_at'] ?? ''));
                $conflicts[$name . '|' . $process . '|' . $when] = $name . ' (' . $process . ', ' . $when . ')';
            }
        }

        if ($conflicts) {
            throw new RuntimeException('No se pudo guardar. Estas personas ya tienen entrevista en otro proceso u horario: ' . implode('; ', array_values($conflicts)) . '.');
        }
    }

    private function assertSlotChronology(string $startAt, string $endAt): void
    {
        if ($startAt >= $endAt) {
            throw new RuntimeException('La hora de termino debe ser posterior a la hora de inicio.');
        }
    }

    private function slotConflicts(array $slot): array
    {
        $ignoreSql = '';
        $dayStart = date('Y-m-d 00:00:00', strtotime((string) $slot['start_at']));
        $dayEnd = date('Y-m-d 00:00:00', strtotime((string) $slot['start_at'] . ' +1 day'));
        $params = [
            (int) $slot['moderator_id'],
            (string) $slot['start_at'],
            (string) $slot['end_at'],
            (int) $slot['candidate_id'],
            $dayStart,
            $dayEnd,
        ];
        if (!empty($slot['ignore_id'])) {
            $ignoreSql = 'AND a.id <> ?';
            $params[] = (int) $slot['ignore_id'];
        }

        return $this->db->fetchAll("
            SELECT a.id,
                   a.scheduled_start_at,
                   p.name AS process_name,
                   candidate.name AS candidate_name
            FROM interview_appointments a
            JOIN interview_processes p ON p.id = a.process_id
            LEFT JOIN {$this->coreSchema}.users candidate ON candidate.id = a.candidate_user_id
            WHERE a.meeting_status NOT IN ('cancelled', 'finished')
              AND p.status <> 'cancelled'
              AND (
                  (a.moderator_user_id = ? AND a.scheduled_start_at < ? AND a.scheduled_end_at > ?)
                  OR (a.candidate_user_id = ? AND a.scheduled_start_at >= ? AND a.scheduled_start_at < ?)
              )
              {$ignoreSql}
            ORDER BY a.scheduled_start_at ASC, a.id ASC
        ", $params);
    }

    private function formatDateTime(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
    }

    private function completeJob(int $jobId): void
    {
        $this->db->execute('UPDATE interview_report_jobs SET status = "done", last_error = NULL WHERE id = ?', [$jobId]);
    }

    private function resetJob(int $jobId, string $error): void
    {
        $this->db->execute('UPDATE interview_report_jobs SET status = "pending", locked_at = NULL, last_error = ? WHERE id = ?', [$error, $jobId]);
    }

    private function failJob(int $jobId, string $error): void
    {
        $this->db->execute('UPDATE interview_report_jobs SET status = "failed", last_error = ? WHERE id = ?', [$error, $jobId]);
    }

    private function companyScopeSql(string $alias): string
    {
        $user = current_user();
        if (!$user || has_permission('manage_interview_processes')) {
            return '1 = 1';
        }

        if (has_permission('manage_company_interviews') && (int) ($user['company_id'] ?? 0) > 0) {
            return $alias . '.company_id = ?';
        }

        return '1 = 0';
    }

    private function companyScopeParams(): array
    {
        $user = current_user();
        if ($user && !has_permission('manage_interview_processes') && has_permission('manage_company_interviews') && (int) ($user['company_id'] ?? 0) > 0) {
            return [(int) $user['company_id']];
        }

        return [];
    }

    private function currentCompanyId(): ?int
    {
        $user = current_user();
        if ($user && has_permission('manage_company_interviews') && !has_permission('manage_interview_processes')) {
            $companyId = (int) ($user['company_id'] ?? 0);
            return $companyId > 0 ? $companyId : null;
        }

        return null;
    }
}
