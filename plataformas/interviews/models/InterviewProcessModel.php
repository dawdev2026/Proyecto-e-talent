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

    public function jobProfile(int $processId): ?array
    {
        return $this->db->fetch('
            SELECT jp.*
            FROM interview_job_profiles jp
            JOIN interview_processes p ON p.id = jp.process_id
            WHERE jp.process_id = ? AND ' . $this->companyScopeSql('p') . '
            LIMIT 1
        ', array_merge([$processId], $this->companyScopeParams()));
    }

    public function candidateBelongsToProcess(int $processId, int $candidateId): bool
    {
        if (!$this->find($processId) || $candidateId <= 0) {
            return false;
        }

        return (bool) $this->db->fetch('
            SELECT id FROM interview_appointments
            WHERE process_id = ? AND candidate_user_id = ?
            LIMIT 1
        ', [$processId, $candidateId]);
    }

    public function saveJobProfile(int $processId, array $data, int $userId): void
    {
        if (!$this->find($processId)) {
            throw new RuntimeException('Proceso de entrevistas no encontrado.');
        }

        $this->db->execute('
            INSERT INTO interview_job_profiles
                (process_id, title, description, technical_requirements, behavioral_requirements, evaluation_criteria_json, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title), description = VALUES(description),
                technical_requirements = VALUES(technical_requirements),
                behavioral_requirements = VALUES(behavioral_requirements),
                evaluation_criteria_json = VALUES(evaluation_criteria_json),
                updated_at = CURRENT_TIMESTAMP
        ', [
            $processId,
            trim((string) ($data['title'] ?? '')),
            trim((string) ($data['description'] ?? '')) ?: null,
            trim((string) ($data['technical_requirements'] ?? '')) ?: null,
            trim((string) ($data['behavioral_requirements'] ?? '')) ?: null,
            json_encode($data['evaluation_criteria'] ?? [], JSON_UNESCAPED_UNICODE),
            $userId ?: null,
        ]);
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
                   ts.status AS test_session_status,
                   ((SELECT COUNT(*) FROM interview_documents d WHERE d.appointment_id = a.id)
                    + (SELECT COUNT(*) FROM interview_documents d WHERE d.process_id = a.process_id AND d.candidate_user_id IS NULL)
                    + (SELECT COUNT(*) FROM interview_documents d WHERE d.process_id = a.process_id AND d.candidate_user_id = a.candidate_user_id)) AS document_count,
                   (SELECT COUNT(*) FROM interview_notes n WHERE n.appointment_id = a.id) AS notes_count,
                   (SELECT COUNT(*) FROM interview_evaluations e WHERE e.appointment_id = a.id AND e.status = 'submitted') AS evaluations_submitted
            FROM interview_appointments a
            JOIN interview_processes p ON p.id = a.process_id
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
                   p.company_id AS interview_company_id,
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

    public function candidateById(int $candidateId): ?array
    {
        if ($candidateId <= 0) {
            return null;
        }

        return $this->db->fetch("SELECT u.id, u.name, u.email, u.rut, c.name AS company_name
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            LEFT JOIN {$this->coreSchema}.role_profiles p ON p.id = u.profile_id
            WHERE u.id = ? AND u.is_active = 1 AND " . $this->companyScopeSql('u') . "
              AND COALESCE(p.role_key, u.role) = 'usuario'
            LIMIT 1", array_merge([$candidateId], $this->companyScopeParams()));
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

    public function assessmentPreview(int $testProcessId, int $candidateId): array
    {
        if ($candidateId <= 0) {
            return ['available' => false, 'message' => 'Selecciona un postulante.'];
        }

        $candidate = $this->db->fetch("SELECT u.id FROM {$this->coreSchema}.users u WHERE u.id = ? AND " . $this->companyScopeSql('u') . " LIMIT 1", array_merge([$candidateId], $this->companyScopeParams()));
        if (!$candidate) {
            return ['available' => false, 'message' => 'El postulante no pertenece a esta empresa.'];
        }
        if ($testProcessId <= 0) {
            $processes = $this->assessmentProcessesForCandidate($candidateId);
            $recommended = 0;
            foreach ($processes as $process) {
                if ((int) ($process['completed_count'] ?? 0) > 0) {
                    $recommended = (int) $process['id'];
                    break;
                }
            }
            return [
                'available' => false,
                'message' => $recommended > 0
                    ? 'Se encontró automáticamente la evaluación más reciente con resultados.'
                    : 'Este postulante no tiene evaluaciones finalizadas disponibles.',
                'processes' => $processes,
                'recommended_process_id' => $recommended,
            ];
        }

        $testProcess = $this->db->fetch("SELECT tp.id FROM {$this->testsSchema}.test_processes tp WHERE tp.id = ? AND " . $this->companyScopeSql('tp') . " LIMIT 1", array_merge([$testProcessId], $this->companyScopeParams()));
        if (!$testProcess) {
            return ['available' => false, 'message' => 'Ese proceso no pertenece a las evaluaciones del postulante.'];
        }

        $payload = $this->postulantReports->payloadForProcessUser($testProcessId, $candidateId);
        return [
            'available' => !empty($payload['available']),
            'message' => (string) ($payload['message'] ?? 'Informe no disponible.'),
            'candidate' => $payload['candidate'] ?? [],
            'structured_summary' => $payload['structured_summary'] ?? [],
            'warnings' => $payload['warnings'] ?? [],
        ];
    }

    public function assessmentProcessesForCandidate(int $candidateId): array
    {
        if ($candidateId <= 0) {
            return [];
        }
        return $this->db->fetchAll("SELECT tp.id, tp.name, COUNT(ts.id) AS sessions_count, SUM(CASE WHEN ts.status = 'completed' THEN 1 ELSE 0 END) AS completed_count, MAX(ts.completed_at) AS last_completed_at
            FROM {$this->testsSchema}.test_process_users pu
            JOIN {$this->testsSchema}.test_processes tp ON tp.id = pu.process_id
            JOIN {$this->testsSchema}.test_sessions ts ON ts.process_id = pu.process_id AND ts.user_id = pu.user_id AND ts.status <> 'cancelled'
            WHERE pu.user_id = ? AND pu.status <> 'cancelled' AND " . $this->companyScopeSql('tp') . "
            GROUP BY tp.id, tp.name
            ORDER BY MAX(ts.completed_at) IS NULL ASC, MAX(ts.completed_at) DESC, tp.name ASC", array_merge([$candidateId], $this->companyScopeParams()));
    }

    public function recommendedAssessmentProcessId(int $candidateId): int
    {
        foreach ($this->assessmentProcessesForCandidate($candidateId) as $process) {
            if ((int) ($process['completed_count'] ?? 0) > 0) {
                return (int) ($process['id'] ?? 0);
            }
        }

        return 0;
    }

    public function appointmentForCandidate(int $processId, int $candidateId): ?array
    {
        if ($processId <= 0 || $candidateId <= 0) {
            return null;
        }

        return $this->db->fetch('
            SELECT a.*
            FROM interview_appointments a
            JOIN interview_processes p ON p.id = a.process_id
            WHERE a.process_id = ? AND a.candidate_user_id = ? AND ' . $this->companyScopeSql('p') . '
            LIMIT 1
        ', array_merge([$processId, $candidateId], $this->companyScopeParams()));
    }

    public function latestTestSessionId(int $testProcessId, int $candidateId): int
    {
        $latest = $this->latestSessionIds([$candidateId], $testProcessId);
        return (int) ($latest[$candidateId] ?? 0);
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

    public function deleteProcess(int $processId): void
    {
        $process = $this->find($processId);
        if (!$process) {
            throw new RuntimeException('Proceso de entrevistas no encontrado.');
        }

        $active = $this->db->fetch('
            SELECT COUNT(*) AS total
            FROM interview_appointments
            WHERE process_id = ? AND meeting_status IN ("in_progress", "finished")
        ', [$processId]);
        if ((int) ($active['total'] ?? 0) > 0) {
            throw new RuntimeException('No se puede eliminar una entrevista que ya comenzó o finalizó.');
        }

        $documents = $this->db->fetchAll('
            SELECT d.storage_path
            FROM interview_documents d
            WHERE d.process_id = ?
        ', [$processId]);

        $this->db->transaction(function () use ($processId): void {
            // Los documentos nuevos tienen process_id; los documentos
            // históricos vinculados por appointment_id se eliminan por
            // cascada al borrar el proceso y sus citas.
            $this->db->execute('DELETE FROM interview_documents WHERE process_id = ?', [$processId]);
            $deleted = $this->db->execute('DELETE FROM interview_processes WHERE id = ?', [$processId]);
            if ($deleted !== 1) {
                throw new RuntimeException('No se pudo eliminar el proceso de entrevistas.');
            }
        });

        foreach ($documents as $document) {
            $relativePath = ltrim((string) ($document['storage_path'] ?? ''), '/');
            if (strpos($relativePath, 'storage/interviews/') !== 0) {
                continue;
            }
            $absolutePath = BASE_PATH . '/' . $relativePath;
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
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
            SELECT d.id, d.process_id, d.candidate_user_id, d.appointment_id, d.document_type,
                   d.original_name, d.mime_type, d.file_size, d.sha256, d.extracted_text, d.processing_status,
                   d.processing_error, d.uploaded_by, d.created_at, d.updated_at
            FROM interview_documents d
            JOIN interview_appointments a ON a.id = ?
            JOIN interview_processes p ON p.id = a.process_id
            WHERE ' . $this->companyScopeSql('p') . '
              AND (d.appointment_id = a.id OR (d.process_id = p.id AND (d.candidate_user_id IS NULL OR d.candidate_user_id = a.candidate_user_id)))
            ORDER BY d.created_at DESC, d.id DESC
        ', array_merge([$appointmentId], $this->companyScopeParams()));
    }

    public function processDocuments(int $processId): array
    {
        $scopedParams = $this->companyScopeParams();
        $processDocuments = $this->db->fetchAll('
            SELECT d.id, d.process_id, d.candidate_user_id, d.document_type, d.original_name,
                   d.mime_type, d.file_size, d.processing_status, d.processing_error,
                   d.created_at, u.name AS candidate_name
            FROM interview_documents d
            JOIN interview_processes p ON p.id = d.process_id
            LEFT JOIN ' . $this->coreSchema . '.users u ON u.id = d.candidate_user_id
            WHERE d.process_id = ? AND ' . $this->companyScopeSql('p') . '
            ORDER BY d.created_at DESC, d.id DESC
        ', array_merge([$processId], $scopedParams));

        $legacyDocuments = $this->db->fetchAll('
            SELECT d.id, COALESCE(d.process_id, a.process_id) AS process_id,
                   COALESCE(d.candidate_user_id, a.candidate_user_id) AS candidate_user_id, d.document_type, d.original_name,
                   d.mime_type, d.file_size, d.processing_status, d.processing_error,
                   d.created_at, u.name AS candidate_name
            FROM interview_documents d
            JOIN interview_appointments a ON a.id = d.appointment_id
            JOIN interview_processes p ON p.id = a.process_id
            LEFT JOIN ' . $this->coreSchema . '.users u ON u.id = a.candidate_user_id
            WHERE d.process_id IS NULL AND a.process_id = ? AND ' . $this->companyScopeSql('p') . '
            ORDER BY d.created_at DESC, d.id DESC
        ', array_merge([$processId], $scopedParams));

        $documents = array_merge($processDocuments, $legacyDocuments);
        usort($documents, static fn(array $left, array $right): int => strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? '')) ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0)));
        return $documents;
    }

    public function document(int $documentId): ?array
    {
        return $this->db->fetch('
            SELECT d.*, p.company_id, p.id AS scoped_process_id
            FROM interview_documents d
            LEFT JOIN interview_appointments a ON a.id = d.appointment_id
            LEFT JOIN interview_processes p ON p.id = COALESCE(d.process_id, a.process_id)
            WHERE d.id = ? AND ' . $this->companyScopeSql('p') . '
            LIMIT 1
        ', array_merge([$documentId], $this->companyScopeParams()));
    }

    public function deleteDocument(int $documentId): array
    {
        $document = $this->document($documentId);
        if (!$document) {
            throw new RuntimeException('Documento no encontrado.');
        }

        $this->db->execute('DELETE FROM interview_documents WHERE id = ?', [$documentId]);

        $storagePath = ltrim((string) ($document['storage_path'] ?? ''), '/');
        if (str_starts_with($storagePath, 'storage/interviews/')) {
            $absolutePath = BASE_PATH . '/' . $storagePath;
            if (is_file($absolutePath) && !unlink($absolutePath)) {
                throw new RuntimeException('El registro fue eliminado, pero no se pudo retirar el archivo físico.');
            }
        }

        return $document;
    }

    public function addDocument(?int $appointmentId, array $document, int $uploadedBy): int
    {
        $processId = (int) ($document['process_id'] ?? 0);
        $candidateUserId = (int) ($document['candidate_user_id'] ?? 0);
        if ($appointmentId !== null) {
            $appointment = $this->findAppointment($appointmentId);
            if (!$appointment) {
                throw new RuntimeException('Entrevista no encontrada.');
            }
            $processId = (int) $appointment['process_id'];
            $candidateUserId = (int) $appointment['candidate_user_id'];
        } elseif ($processId <= 0 || !$this->find($processId)) {
            throw new RuntimeException('Proceso de entrevistas no encontrado.');
        }

        return (int) $this->db->insert('
            INSERT INTO interview_documents
                (process_id, candidate_user_id, appointment_id, document_type, original_name, stored_name, storage_path, mime_type, file_size, sha256, extracted_text, processing_status, processing_error, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ', [
            $processId ?: null, $candidateUserId ?: null, $appointmentId, $document['document_type'], $document['original_name'], $document['stored_name'],
            $document['storage_path'], $document['mime_type'], $document['file_size'], $document['sha256'],
            $document['extracted_text'] ?: null, $document['processing_status'], $document['processing_error'] ?: null,
            $uploadedBy ?: null,
        ]);
    }

    public function evaluationFor(int $appointmentId, int $evaluatorUserId): ?array
    {
        // La sala permite acceder al participante mediante su vínculo seguro.
        // Mantener ese mismo alcance al cargar su evaluación evita que la
        // segunda consulta rechace la cita y convierta la sala en un 500.
        $this->assertScopedAppointment($appointmentId, true);
        return $this->db->fetch('
            SELECT * FROM interview_evaluations
            WHERE appointment_id = ? AND evaluator_user_id = ?
            LIMIT 1
        ', [$appointmentId, $evaluatorUserId]);
    }

    public function evaluations(int $appointmentId): array
    {
        $this->assertScopedAppointment($appointmentId);
        return $this->db->fetchAll('
            SELECT e.*, u.name AS evaluator_name
            FROM interview_evaluations e
            LEFT JOIN ' . $this->coreSchema . '.users u ON u.id = e.evaluator_user_id
            WHERE e.appointment_id = ?
            ORDER BY e.updated_at DESC, e.id DESC
        ', [$appointmentId]);
    }

    public function saveEvaluation(int $appointmentId, int $evaluatorUserId, array $data): void
    {
        $this->assertScopedAppointment($appointmentId);
        $status = ($data['status'] ?? 'draft') === 'submitted' ? 'submitted' : 'draft';
        $recommendation = in_array(($data['recommendation'] ?? 'pending'), ['pending', 'recommended', 'not_recommended', 'hold'], true)
            ? (string) $data['recommendation'] : 'pending';
        $submittedAt = $status === 'submitted' ? date('Y-m-d H:i:s') : null;

        $this->db->execute('
            INSERT INTO interview_evaluations
                (appointment_id, evaluator_user_id, status, technical_score, behavioral_score, overall_score, recommendation, strengths, risks, comments, criteria_json, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status), technical_score = VALUES(technical_score),
                behavioral_score = VALUES(behavioral_score), overall_score = VALUES(overall_score),
                recommendation = VALUES(recommendation), strengths = VALUES(strengths),
                risks = VALUES(risks), comments = VALUES(comments), criteria_json = VALUES(criteria_json),
                submitted_at = VALUES(submitted_at), updated_at = CURRENT_TIMESTAMP
        ', [
            $appointmentId, $evaluatorUserId, $status,
            $this->score($data['technical_score'] ?? null),
            $this->score($data['behavioral_score'] ?? null),
            $this->score($data['overall_score'] ?? null),
            $recommendation,
            trim((string) ($data['strengths'] ?? '')) ?: null,
            trim((string) ($data['risks'] ?? '')) ?: null,
            trim((string) ($data['comments'] ?? '')) ?: null,
            json_encode($data['criteria'] ?? [], JSON_UNESCAPED_UNICODE),
            $submittedAt,
        ]);
    }

    public function saveNotes(int $appointmentId, int $userId, string $notes): void
    {
        $this->assertScopedAppointment($appointmentId);
        $this->db->execute('
            INSERT INTO interview_notes (appointment_id, author_user_id, notes)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE notes = VALUES(notes), updated_at = CURRENT_TIMESTAMP
        ', [$appointmentId, $userId, $notes]);
    }

    public function recordTranscriptionEvent(int $appointmentId, int $userId, string $action, string $status, string $transcript, string $message = ''): void
    {
        $this->assertScopedAppointment($appointmentId);
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
        $this->assertScopedAppointment($appointmentId);
        $this->db->execute('
            UPDATE interview_appointments
            SET meeting_status = "in_progress", daily_room_name = ?, daily_room_url = ?
            WHERE id = ?
        ', [$roomName, $roomUrl, $appointmentId]);
    }

    public function finishAppointment(int $appointmentId): void
    {
        $this->assertScopedAppointment($appointmentId);
        $this->db->execute('
            UPDATE interview_appointments
            SET meeting_status = "finished", finished_at = COALESCE(finished_at, NOW()), final_report_status = "pending"
            WHERE id = ?
        ', [$appointmentId]);
        $this->queueJob($appointmentId, 'final_report');
    }

    public function queueJob(int $appointmentId, string $type): void
    {
        $this->assertScopedAppointment($appointmentId);
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

    public function retryJob(int $appointmentId, string $type): void
    {
        $this->assertScopedAppointment($appointmentId);
        $this->db->execute('
            UPDATE interview_report_jobs
            SET status = "pending", attempts = 0, locked_at = NULL, last_error = NULL
            WHERE appointment_id = ? AND job_type = ? AND status IN ("pending", "failed")
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
        $this->assertScopedAppointment($appointmentId);
        $this->db->execute('
            UPDATE interview_appointments
            SET moderator_brief_status = "ready", moderator_brief_json = ?, moderator_brief_error = NULL
            WHERE id = ?
        ', [json_encode($brief, JSON_UNESCAPED_UNICODE), $appointmentId]);
        $this->completeJob($jobId);
    }

    public function failBrief(int $appointmentId, int $jobId, string $error, bool $pending): void
    {
        $this->assertScopedAppointment($appointmentId);
        $this->db->execute('
            UPDATE interview_appointments
            SET moderator_brief_status = ?, moderator_brief_error = ?
            WHERE id = ?
        ', [$pending ? 'pending' : 'failed', $error, $appointmentId]);
        $pending ? $this->resetJob($jobId, $error) : $this->failJob($jobId, $error);
    }

    public function completeFinalReport(int $appointmentId, int $jobId, array $report, string $html): void
    {
        $this->assertScopedAppointment($appointmentId);
        $this->db->execute('
            UPDATE interview_appointments
            SET final_report_status = "ready", final_report_json = ?, final_report_html = ?, final_report_error = NULL, final_report_generated_at = NOW()
            WHERE id = ?
        ', [json_encode($report, JSON_UNESCAPED_UNICODE), $html, $appointmentId]);
        $this->completeJob($jobId);
    }

    public function failFinalReport(int $appointmentId, int $jobId, string $error, bool $pending): void
    {
        $this->assertScopedAppointment($appointmentId);
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
            SELECT n.notes, n.author_user_id, n.created_at, n.updated_at, u.name AS author_name
            FROM interview_notes n
            LEFT JOIN ' . $this->coreSchema . '.users u ON u.id = n.author_user_id
            WHERE n.appointment_id = ? ORDER BY n.updated_at DESC LIMIT 1
        ', [(int) $appointment['id']]);

        $jobProfile = $this->jobProfile((int) ($appointment['process_id'] ?? 0));
        $documents = $this->documents((int) $appointment['id']);

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
                'id' => (int) ($appointment['candidate_user_id'] ?? 0),
                'name' => $appointment['candidate_name'] ?? '',
                'email' => $appointment['candidate_email'] ?? '',
                'rut' => $appointment['candidate_rut'] ?? '',
            ],
            'process' => [
                'id' => (int) ($appointment['process_id'] ?? 0),
                'company_id' => (int) ($appointment['interview_company_id'] ?? 0),
                'name' => $appointment['process_name'] ?? '',
                'interview_date' => $appointment['interview_date'] ?? '',
                'test_process' => $appointment['test_process_name'] ?? '',
                'job_profile' => $jobProfile ? [
                    'title' => (string) ($jobProfile['title'] ?? ''),
                    'description' => (string) ($jobProfile['description'] ?? ''),
                    'technical_requirements' => (string) ($jobProfile['technical_requirements'] ?? ''),
                    'behavioral_requirements' => (string) ($jobProfile['behavioral_requirements'] ?? ''),
                    'evaluation_criteria' => json_decode((string) ($jobProfile['evaluation_criteria_json'] ?? '[]'), true) ?: [],
                ] : [],
            ],
            'appointment' => [
                'id' => (int) ($appointment['id'] ?? 0),
                'process_id' => (int) ($appointment['process_id'] ?? 0),
                'scheduled_start_at' => (string) ($appointment['scheduled_start_at'] ?? ''),
                'scheduled_end_at' => (string) ($appointment['scheduled_end_at'] ?? ''),
                'meeting_status' => (string) ($appointment['meeting_status'] ?? ''),
                'transcript_status' => (string) ($appointment['transcript_status'] ?? ''),
                'final_report_status' => (string) ($appointment['final_report_status'] ?? ''),
                'finished_at' => (string) ($appointment['finished_at'] ?? ''),
            ],
            'report' => $this->candidateReportSummary($appointment),
            'transcript' => (string) ($appointment['transcript_text'] ?? ''),
            'transcript_metadata' => [
                'status' => (string) ($appointment['transcript_status'] ?? ''),
                'last_snapshot_at' => (string) ($appointment['transcript_last_snapshot_at'] ?? ''),
            ],
            'notes' => (string) ($note['notes'] ?? ''),
            'note_metadata' => [
                'author_user_id' => (int) ($note['author_user_id'] ?? 0),
                'author_name' => (string) ($note['author_name'] ?? ''),
                'created_at' => (string) ($note['created_at'] ?? ''),
                'updated_at' => (string) ($note['updated_at'] ?? ''),
            ],
            'documents' => $documentContext,
            'evaluations' => array_map(static function (array $evaluation): array {
                return [
                    'id' => (int) ($evaluation['id'] ?? 0),
                    'evaluator_user_id' => (int) ($evaluation['evaluator_user_id'] ?? 0),
                    'evaluator' => (string) ($evaluation['evaluator_name'] ?? ''),
                    'status' => (string) ($evaluation['status'] ?? ''),
                    'technical_score' => $evaluation['technical_score'] === null ? null : (int) $evaluation['technical_score'],
                    'behavioral_score' => $evaluation['behavioral_score'] === null ? null : (int) $evaluation['behavioral_score'],
                    'overall_score' => $evaluation['overall_score'] === null ? null : (int) $evaluation['overall_score'],
                    'recommendation' => (string) ($evaluation['recommendation'] ?? ''),
                    'strengths' => (string) ($evaluation['strengths'] ?? ''),
                    'risks' => (string) ($evaluation['risks'] ?? ''),
                    'comments' => (string) ($evaluation['comments'] ?? ''),
                    'criteria' => json_decode((string) ($evaluation['criteria_json'] ?? '[]'), true) ?: [],
                    'submitted_at' => (string) ($evaluation['submitted_at'] ?? ''),
                    'updated_at' => (string) ($evaluation['updated_at'] ?? ''),
                ];
            }, $this->evaluations((int) $appointment['id'])),
            'source_metadata' => [
                'origin' => 'registered_interview',
                'is_simulated' => false,
            ],
        ];
    }

    /**
     * Identifies the inputs used to build the moderator brief without
     * including interview-only data such as notes, transcript, or scores.
     * This lets callers reuse a ready brief when an edit did not change its
     * source material.
     */
    public function moderatorBriefSourceFingerprint(array $appointment): string
    {
        $context = $this->reportContext($appointment);
        $source = [
            'candidate_id' => (int) ($appointment['candidate_user_id'] ?? 0),
            'test_process_id' => (int) ($appointment['test_process_id'] ?? 0),
            'test_session_id' => (int) ($appointment['test_session_id'] ?? 0),
            'process_name' => (string) ($context['process']['name'] ?? $appointment['process_name'] ?? ''),
            'job_profile' => $context['process']['job_profile'] ?? [],
            'report' => $context['report'] ?? [],
            'documents' => $context['documents'] ?? [],
        ];

        return hash('sha256', (string) json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
        $this->assertAssignableUsers((int) $data['moderator_user_id'], $candidateIds);
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

    private function assertAssignableUsers(int $moderatorId, array $candidateIds): void
    {
        $userScope = $this->companyScopeSql('u');
        $scopeParams = $this->companyScopeParams();
        $moderator = $this->db->fetch('
            SELECT u.id FROM ' . $this->coreSchema . '.users u
            WHERE u.id = ? AND u.is_active = 1 AND ' . $userScope . '
            LIMIT 1
        ', array_merge([$moderatorId], $scopeParams));
        if (!$moderator) {
            throw new RuntimeException('El moderador no pertenece al alcance de la empresa activa.');
        }

        if (!$candidateIds) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $rows = $this->db->fetchAll('
            SELECT u.id FROM ' . $this->coreSchema . '.users u
            LEFT JOIN ' . $this->coreSchema . '.role_profiles rp ON rp.id = u.profile_id
            WHERE u.is_active = 1 AND u.id IN (' . $placeholders . ')
              AND COALESCE(rp.role_key, u.role) = "usuario"
              AND ' . $userScope . '
        ', array_merge($candidateIds, $scopeParams));
        if (count($rows) !== count($candidateIds)) {
            throw new RuntimeException('Uno o más postulantes no pertenecen al alcance de la empresa activa.');
        }
    }

    private function score($value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return max(0, min(100, (int) $value));
    }

    private function assertScopedAppointment(int $appointmentId, bool $includeParticipant = false): array
    {
        $appointment = $this->findAppointment($appointmentId, $includeParticipant);
        if (!$appointment) {
            throw new RuntimeException('Entrevista no encontrada.');
        }

        return $appointment;
    }

    private function companyScopeSql(string $alias): string
    {
        $user = current_user();
        if (!$user || has_permission('manage_interview_processes')) {
            return '1 = 1';
        }

        if ((has_permission('manage_company_interviews') || is_company_admin_user($user)) && (int) ($user['company_id'] ?? 0) > 0) {
            return $alias . '.company_id = ?';
        }

        return '1 = 0';
    }

    private function companyScopeParams(): array
    {
        $user = current_user();
        if ($user && !has_permission('manage_interview_processes') && (has_permission('manage_company_interviews') || is_company_admin_user($user)) && (int) ($user['company_id'] ?? 0) > 0) {
            return [(int) $user['company_id']];
        }

        return [];
    }

    private function currentCompanyId(): ?int
    {
        $user = current_user();
        if ($user && (has_permission('manage_company_interviews') || is_company_admin_user($user)) && !has_permission('manage_interview_processes')) {
            $companyId = (int) ($user['company_id'] ?? 0);
            return $companyId > 0 ? $companyId : null;
        }

        return null;
    }
}
