<?php
declare(strict_types=1);

final class EvaluationSurveyAttemptModel
{
    private Database $db;
    private string $coreSchema;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('evaluaciones_encuestas');
        $this->coreSchema = database_identifier('core');
    }

    public function latestAttemptsForUser(int $userId): array
    {
        $rows = $this->db->fetchAll('
            SELECT a.*
            FROM evaluation_survey_attempts a
            JOIN (
                SELECT form_id, MAX(attempt_number) AS attempt_number
                FROM evaluation_survey_attempts
                WHERE user_id = ?
                GROUP BY form_id
            ) latest ON latest.form_id = a.form_id AND latest.attempt_number = a.attempt_number
            WHERE a.user_id = ?
        ', [$userId, $userId]);

        $attempts = [];
        foreach ($rows as $row) {
            $attempts[(int) $row['form_id']] = $row;
        }

        return $attempts;
    }

    public function isAssignedToUser(int $formId, int $userId, ?int $processId = null): bool
    {
        if ($formId <= 0 || $userId <= 0) {
            return false;
        }

        $tests = database('tests');
        $row = $tests->fetch('
            SELECT a.id
            FROM test_process_evaluation_assignments a
            JOIN test_processes p ON p.id = a.process_id
            WHERE a.form_id = ? AND a.user_id = ? AND a.status = "assigned" AND p.status = "active"' . ($processId && $processId > 0 ? ' AND a.process_id = ?' : '') . '
            LIMIT 1
        ', $processId && $processId > 0 ? [$formId, $userId, $processId] : [$formId, $userId]);

        return (bool) $row;
    }

    public function attemptSummariesForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->db->fetchAll('
            SELECT *
            FROM evaluation_survey_attempts
            WHERE user_id = ?
            ORDER BY form_id ASC, attempt_number DESC, id DESC
        ', [$userId]);

        $summaries = [];
        foreach ($rows as $row) {
            $formId = (int) $row['form_id'];
            if (!isset($summaries[$formId])) {
                $summaries[$formId] = [
                    'attempt_count' => 0,
                    'latest_attempt' => null,
                    'active_attempt' => null,
                    'best_attempt' => null,
                ];
            }

            $summaries[$formId]['attempt_count']++;
            $summaries[$formId]['latest_attempt'] ??= $row;

            if (($row['status'] ?? '') === 'in_progress' && !$summaries[$formId]['active_attempt']) {
                $summaries[$formId]['active_attempt'] = $this->expireIfNeeded($row);
            }

            if (in_array((string) ($row['status'] ?? ''), ['completed', 'expired'], true)) {
                $currentBest = $summaries[$formId]['best_attempt'];
                if (!$currentBest || $this->attemptRanksHigher($row, $currentBest)) {
                    $summaries[$formId]['best_attempt'] = $row;
                }
            }
        }

        return $summaries;
    }

    public function attemptsForForm(int $formId, ?int $companyId = null, ?int $processId = null): array
    {
        return $this->db->fetchAll('
            SELECT a.*,
                   COALESCE(p.name, assigned_process.name) AS process_name,
                   COALESCE(p.code, assigned_process.code) AS process_code,
                   u.name AS user_name, u.email AS user_email, u.company_id AS user_company_id,
                   (SELECT COUNT(DISTINCT ea.question_id) FROM evaluation_survey_answers ea
                    WHERE ea.attempt_id = a.id AND ea.answer_value IS NOT NULL AND TRIM(ea.answer_value) <> "") AS answer_count
            FROM evaluation_survey_attempts a
            JOIN ' . $this->coreSchema . '.users u ON u.id = a.user_id
            LEFT JOIN ' . database_identifier('tests') . '.test_processes p ON p.id = a.process_id
            LEFT JOIN ' . database_identifier('tests') . '.test_processes assigned_process
              ON assigned_process.id = ?
            WHERE a.form_id = ?' . ($processId && $processId > 0 ? ' AND (a.process_id = ? OR (a.process_id IS NULL AND EXISTS (
                SELECT 1
                FROM ' . database_identifier('tests') . '.test_process_evaluation_assignments pea
                WHERE pea.process_id = ? AND pea.form_id = a.form_id AND pea.user_id = a.user_id AND pea.status <> "cancelled"
            ) AND NOT EXISTS (
                SELECT 1
                FROM ' . database_identifier('tests') . '.test_process_evaluation_assignments other_pea
                WHERE other_pea.form_id = a.form_id AND other_pea.user_id = a.user_id AND other_pea.status <> "cancelled" AND other_pea.process_id <> ?
            )))' : '') . ($companyId && $companyId > 0 ? ' AND u.company_id = ?' : '') . '
            ORDER BY a.completed_at DESC, a.updated_at DESC, a.id DESC
        ', array_values(array_filter([$processId && $processId > 0 ? $processId : null, $formId, $processId && $processId > 0 ? $processId : null, $processId && $processId > 0 ? $processId : null, $processId && $processId > 0 ? $processId : null, $companyId && $companyId > 0 ? $companyId : null], static fn($value): bool => $value !== null)));
    }

    public function reprocessableAttempt(int $attemptId, int $formId, ?int $companyId = null, ?int $processId = null): ?array
    {
        if ($attemptId <= 0 || $formId <= 0) {
            return null;
        }

        $conditions = [
            'a.id = ?',
            'a.form_id = ?',
            'a.status = "in_progress"',
            'a.final_score IS NULL',
            'a.passed IS NULL',
            'f.form_type = "assessment"',
            'EXISTS (SELECT 1 FROM evaluation_survey_answers ea WHERE ea.attempt_id = a.id AND ea.answer_value IS NOT NULL AND TRIM(ea.answer_value) <> "")',
        ];
        $params = [$attemptId, $formId];
        if ($processId && $processId > 0) {
            $conditions[] = 'a.process_id = ?';
            $params[] = $processId;
        }
        if ($companyId && $companyId > 0) {
            $conditions[] = 'u.company_id = ?';
            $params[] = $companyId;
        }

        return $this->db->fetch('
            SELECT a.*, f.title AS form_title, f.form_type, f.max_score AS form_max_score,
                   f.passing_score, u.name AS user_name, u.email AS user_email,
                   p.name AS process_name,
                   (SELECT COUNT(DISTINCT ea.question_id) FROM evaluation_survey_answers ea
                    WHERE ea.attempt_id = a.id AND ea.answer_value IS NOT NULL AND TRIM(ea.answer_value) <> "") AS answered_count,
                   (SELECT COUNT(*) FROM evaluation_survey_answers ea WHERE ea.attempt_id = a.id) AS answer_rows
            FROM evaluation_survey_attempts a
            JOIN evaluation_survey_forms f ON f.id = a.form_id
            JOIN ' . $this->coreSchema . '.users u ON u.id = a.user_id
            LEFT JOIN ' . database_identifier('tests') . '.test_processes p ON p.id = a.process_id
            WHERE ' . implode(' AND ', $conditions) . '
            LIMIT 1
        ', $params);
    }

    public function reprocessAttempt(array $form, int $attemptId, ?int $companyId = null, ?int $processId = null): array
    {
        $attempt = $this->reprocessableAttempt($attemptId, (int) ($form['id'] ?? 0), $companyId, $processId);
        if (!$attempt) {
            throw new RuntimeException('El intento ya tiene nota/aprobación, no tiene respuestas o dejó de estar en curso.');
        }

        $questions = (new EvaluationSurveyFormModel($this->db))->questionsForForm((int) $form['id'], false);
        $answerRows = $this->answersForAttempt($attemptId);
        $answers = [];
        foreach ($answerRows as $questionId => $answer) {
            $answers[(int) $questionId] = $answer['answer_value'] ?? '';
        }

        return $this->completeAttempt($form, $attempt, $questions, $answers, 'completed');
    }

    public function exportDataForForm(array $form, ?int $companyId = null, ?int $processId = null): array
    {
        $attempts = $this->attemptsForForm((int) ($form['id'] ?? 0), $companyId, $processId);
        $questions = (new EvaluationSurveyFormModel($this->db))->questionsForForm((int) ($form['id'] ?? 0), false);
        if (!$attempts || !$questions) {
            return ['attempts' => $attempts, 'questions' => $questions, 'answers' => []];
        }

        $attemptIds = array_values(array_filter(array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $attempts), static fn(int $id): bool => $id > 0));
        $placeholders = implode(',', array_fill(0, count($attemptIds), '?'));
        $answersByAttempt = [];
        foreach ($this->db->fetchAll(
            'SELECT attempt_id, question_id, answer_value, score_value
             FROM evaluation_survey_answers
             WHERE attempt_id IN (' . $placeholders . ')
             ORDER BY attempt_id, id',
            $attemptIds
        ) as $answer) {
            $answersByAttempt[(int) $answer['attempt_id']][(int) $answer['question_id']] = $answer;
        }

        return ['attempts' => $attempts, 'questions' => $questions, 'answers' => $answersByAttempt];
    }

    public function dashboardSummaryExportData(?int $companyId = null): array
    {
        $testsSchema = database_identifier('tests');
        $companySql = $companyId && $companyId > 0
            ? ' AND (f.company_id IS NULL OR f.company_id = ?) AND u.company_id = ?'
            : '';
        $params = $companyId && $companyId > 0 ? [$companyId, $companyId] : [];

        return $this->db->fetchAll(
            'SELECT a.status, a.final_score, f.title AS evaluation_name, f.passing_score,
                    f.max_score, u.rut, u.name AS user_name, u.email AS user_email,
                    COALESCE(p.name, CASE WHEN (
                        SELECT COUNT(DISTINCT pea.process_id)
                        FROM ' . $testsSchema . '.test_process_evaluation_assignments pea
                        WHERE pea.form_id = a.form_id AND pea.user_id = a.user_id AND pea.status <> "cancelled"
                    ) = 1 THEN (
                        SELECT MAX(p2.name)
                        FROM ' . $testsSchema . '.test_process_evaluation_assignments pea2
                        JOIN ' . $testsSchema . '.test_processes p2 ON p2.id = pea2.process_id
                        WHERE pea2.form_id = a.form_id AND pea2.user_id = a.user_id AND pea2.status <> "cancelled"
                    ) END) AS process_name,
                    COALESCE(ast.correct_answers, 0) AS correct_answers,
                    COALESCE(ast.incorrect_answers, 0) AS incorrect_answers,
                    COALESCE(ast.answered_answers, 0) AS answered_answers,
                    COALESCE(q.total_questions, 0) AS total_questions
             FROM evaluation_survey_attempts a
             JOIN evaluation_survey_forms f ON f.id = a.form_id AND f.form_type = "assessment"
             JOIN ' . $this->coreSchema . '.users u ON u.id = a.user_id
             LEFT JOIN ' . database_identifier('tests') . '.test_processes p ON p.id = a.process_id
             LEFT JOIN (
                 SELECT ea.attempt_id,
                        SUM(CASE WHEN ea.answer_value IS NOT NULL AND TRIM(ea.answer_value) <> "" AND ea.score_value > 0 THEN 1 ELSE 0 END) AS correct_answers,
                        SUM(CASE WHEN ea.answer_value IS NOT NULL AND TRIM(ea.answer_value) <> "" AND COALESCE(ea.score_value, 0) <= 0 THEN 1 ELSE 0 END) AS incorrect_answers,
                        SUM(CASE WHEN ea.answer_value IS NOT NULL AND TRIM(ea.answer_value) <> "" THEN 1 ELSE 0 END) AS answered_answers
                 FROM evaluation_survey_answers ea
                 GROUP BY ea.attempt_id
             ) ast ON ast.attempt_id = a.id
             LEFT JOIN (
                 SELECT form_id, COUNT(*) AS total_questions
                 FROM evaluation_survey_questions
                 WHERE is_active = 1
                 GROUP BY form_id
             ) q ON q.form_id = a.form_id
             WHERE a.id = (
                 SELECT MAX(a2.id)
                 FROM evaluation_survey_attempts a2
                 WHERE a2.form_id = a.form_id
                   AND a2.user_id = a.user_id
                   AND (a2.process_id = a.process_id OR (a2.process_id IS NULL AND a.process_id IS NULL))
             )
               AND (a.process_id IS NULL OR EXISTS (
                   SELECT 1
                   FROM ' . $testsSchema . '.test_process_evaluation_assignments active_pea
                   WHERE active_pea.process_id = a.process_id
                     AND active_pea.form_id = a.form_id
                     AND active_pea.user_id = a.user_id
                     AND active_pea.status <> "cancelled"
               ))' . $companySql . '
             ORDER BY f.title ASC, process_name ASC, u.name ASC, a.id DESC',
            $params
        );
    }

    public function findAttempt(int $attemptId): ?array
    {
        return $this->db->fetch('
            SELECT a.*, f.title AS form_title, f.form_type, f.show_result_to_user, f.result_display_mode,
                   f.show_correction_to_user, f.passing_score,
                   u.name AS user_name, u.email AS user_email,
                   p.name AS process_name, p.code AS process_code
            FROM evaluation_survey_attempts a
            JOIN evaluation_survey_forms f ON f.id = a.form_id
            JOIN ' . $this->coreSchema . '.users u ON u.id = a.user_id
            LEFT JOIN ' . database_identifier('tests') . '.test_processes p ON p.id = a.process_id
            WHERE a.id = ?
            LIMIT 1
        ', [$attemptId]);
    }

    public function findAttemptForUser(int $attemptId, int $userId): ?array
    {
        $attempt = $this->findAttempt($attemptId);
        if (!$attempt || (int) $attempt['user_id'] !== $userId) {
            return null;
        }

        return $attempt;
    }

    public function finishedAttemptsForFormUser(int $formId, int $userId, ?int $processId = null): array
    {
        if ($formId <= 0 || $userId <= 0) {
            return [];
        }

        return $this->db->fetchAll('
            SELECT *
            FROM evaluation_survey_attempts
            WHERE form_id = ? AND user_id = ? AND status IN ("completed", "expired")' . ($processId && $processId > 0 ? ' AND process_id = ?' : '') . '
            ORDER BY attempt_number DESC, id DESC
        ', $processId && $processId > 0 ? [$formId, $userId, $processId] : [$formId, $userId]);
    }

    public function startAttempt(array $form, int $userId, ?int $processId = null, bool $expireActive = true): array
    {
        $formId = (int) $form['id'];
        $maxAttempts = max(1, (int) ($form['max_attempts'] ?? 1));

        $active = $this->db->fetch('
            SELECT *
            FROM evaluation_survey_attempts
            WHERE form_id = ? AND user_id = ? AND status = "in_progress"' . ($processId && $processId > 0 ? ' AND process_id = ?' : ' AND process_id IS NULL') . '
            ORDER BY id DESC
            LIMIT 1
        ', $processId && $processId > 0 ? [$formId, $userId, $processId] : [$formId, $userId]);
        if ($active) {
            return $expireActive ? $this->expireIfNeeded($active) : $active;
        }

        $row = $this->db->fetch('
            SELECT COALESCE(MAX(attempt_number), 0) AS last_attempt
            FROM evaluation_survey_attempts
            WHERE form_id = ? AND user_id = ?' . ($processId && $processId > 0 ? ' AND process_id = ?' : ' AND process_id IS NULL') . '
        ', $processId && $processId > 0 ? [$formId, $userId, $processId] : [$formId, $userId]);
        $nextAttempt = (int) ($row['last_attempt'] ?? 0) + 1;
        if ($nextAttempt > $maxAttempts) {
            throw new RuntimeException((string) ($form['form_type'] ?? '') === 'survey' ? 'Ya enviaste esta encuesta.' : 'Ya alcanzaste el máximo de intentos disponibles.');
        }

        $durationMinutes = max(0, (int) ($form['duration_minutes'] ?? 0));
        // En el modo audiovisual el tiempo comienza cuando el cliente termina
        // la validación y registra supervised_started, no al abrir la pantalla.
        $expiresAt = $durationMinutes > 0 && (string) ($form['control_mode'] ?? 'off') !== 'supervised_audio_visual'
            ? date('Y-m-d H:i:s', time() + ($durationMinutes * 60))
            : null;
        try {
            $attemptId = $this->db->insert('
                INSERT INTO evaluation_survey_attempts (form_id, user_id, process_id, control_mode, audio_visual_upload_failure_policy, audio_visual_interruption_policy, audio_visual_voice_policy, audio_visual_permission_policy, audio_visual_quality_profile, attempt_number, expires_at, max_score, last_seen_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ', [$formId, $userId, $processId ?: null, (string) ($form['control_mode'] ?? 'off'), (string) ($form['audio_visual_upload_failure_policy'] ?? 'continue'), (string) ($form['audio_visual_interruption_policy'] ?? 'pause'), (string) ($form['audio_visual_voice_policy'] ?? 'warn'), (string) ($form['audio_visual_permission_policy'] ?? 'pause'), (string) ($form['audio_visual_quality_profile'] ?? 'economical'), $nextAttempt, $expiresAt, (float) ($form['max_score'] ?? 100)]);
        } catch (Throwable $exception) {
            // Two requests can start the same QR evaluation at the same time
            // (for example, a double click or a pending-assessment prompt).
            // The unique key is intentional; reuse the row created by the
            // concurrent request instead of exposing a SQL error to the user.
            $existing = $this->db->fetch('
                SELECT *
                FROM evaluation_survey_attempts
                WHERE form_id = ? AND user_id = ? AND attempt_number = ?' . ($processId && $processId > 0 ? ' AND process_id = ?' : ' AND process_id IS NULL') . '
                LIMIT 1
            ', $processId && $processId > 0 ? [$formId, $userId, $nextAttempt, $processId] : [$formId, $userId, $nextAttempt]);
            if ($existing) {
                return $expireActive ? $this->expireIfNeeded($existing) : $existing;
            }

            throw $exception;
        }

        $attempt = $this->findAttempt($attemptId) ?: [];
        $this->ensureQuestionSet($form, $attempt);
        return $this->findAttempt($attemptId) ?: $attempt;
    }

    public function activateTimer(array $attempt, array $form): array
    {
        $attemptId = (int) ($attempt['id'] ?? 0);
        $durationMinutes = max(0, (int) ($form['duration_minutes'] ?? 0));
        if ($attemptId <= 0 || ($attempt['status'] ?? '') !== 'in_progress' || $durationMinutes <= 0 || !empty($attempt['expires_at'])) {
            return $attempt;
        }

        $this->db->execute(
            'UPDATE evaluation_survey_attempts SET expires_at = ? WHERE id = ? AND status = "in_progress" AND expires_at IS NULL',
            [date('Y-m-d H:i:s', time() + ($durationMinutes * 60)), $attemptId]
        );

        return $this->findAttempt($attemptId) ?: $attempt;
    }

    public function touchPresence(int $attemptId, int $userId): bool
    {
        return $this->db->execute('
            UPDATE evaluation_survey_attempts
            SET last_seen_at = NOW()
            WHERE id = ? AND user_id = ? AND status = "in_progress"
        ', [$attemptId, $userId]) > 0;
    }

    public function questionsForAttempt(array $form, int $attemptId, bool $reshuffleRandom = false, array $selectedQuestionIds = []): array
    {
        $attempt = $this->findAttempt($attemptId) ?: [];
        $this->ensureQuestionSet($form, $attempt);
        $questionIds = $this->questionSetForAttempt($attemptId);
        // Keep the snapshot stable even if an administrator deactivates a
        // question after the attempt started; it still counts in the total.
        $questions = (new EvaluationSurveyFormModel($this->db))->questionsForForm((int) $form['id'], false);
        $answers = $this->answersForAttempt($attemptId);
        $questionsById = [];
        foreach ($questions as $question) {
            $questionsById[(int) $question['id']] = $question;
        }
        $orderedQuestions = [];
        foreach ($questionIds as $questionId) {
            if (isset($questionsById[$questionId])) {
                $orderedQuestions[] = $questionsById[$questionId];
            }
        }
        $questions = $orderedQuestions;

        foreach ($questions as &$question) {
            $question['answer_value'] = $answers[(int) $question['id']]['answer_value'] ?? '';
        }
        unset($question);

        return $questions;
    }

    private function ensureQuestionSet(array $form, array $attempt): void
    {
        $attemptId = (int) ($attempt['id'] ?? 0);
        if ($attemptId <= 0 || $this->questionSetForAttempt($attemptId)) {
            return;
        }

        $questions = (new EvaluationSurveyFormModel($this->db))->questionsForForm((int) $form['id'], true);
        if (($form['question_order_mode'] ?? 'ordered') === 'random') {
            shuffle($questions);
        }
        $displayLimit = max(0, (int) ($form['question_display_limit'] ?? 0));
        if ($displayLimit > 0 && $displayLimit < count($questions)) {
            $questions = array_slice($questions, 0, $displayLimit);
        }
        $questionIds = array_values(array_filter(array_map(static fn(array $question): int => (int) ($question['id'] ?? 0), $questions), static fn(int $id): bool => $id > 0));
        $this->db->execute(
            'UPDATE evaluation_survey_attempts SET question_set_json = ? WHERE id = ? AND (question_set_json IS NULL OR question_set_json = "")',
            [json_encode($questionIds, JSON_UNESCAPED_UNICODE), $attemptId]
        );
    }

    private function questionSetForAttempt(int $attemptId): array
    {
        if ($attemptId <= 0) {
            return [];
        }
        $row = $this->db->fetch('SELECT question_set_json FROM evaluation_survey_attempts WHERE id = ?', [$attemptId]);
        $decoded = json_decode((string) ($row['question_set_json'] ?? ''), true);
        return is_array($decoded) ? array_values(array_filter(array_map('intval', $decoded), static fn(int $id): bool => $id > 0)) : [];
    }

    public function questionsForAnsweredAttempt(array $form, int $attemptId): array
    {
        // El detalle debe mostrar todo el conjunto de preguntas de la
        // evaluación, incluyendo las que todavía no tienen respuesta.
        return $this->questionsForAttempt($form, $attemptId);
    }

    public function saveDraft(int $attemptId, array $questions, array $answers): void
    {
        $this->db->transaction(function (Database $db) use ($attemptId, $questions, $answers): void {
            // Serialize drafts with final submission for this attempt only. A
            // stale draft must never overwrite a completed attempt.
            $attempt = $db->fetch(
                'SELECT status FROM evaluation_survey_attempts WHERE id = ? FOR UPDATE',
                [$attemptId]
            );
            if (!$attempt || (string) ($attempt['status'] ?? '') !== 'in_progress') {
                return;
            }

            $rows = [];
            foreach ($questions as $question) {
                $questionId = (int) $question['id'];
                if (!array_key_exists($questionId, $answers)) {
                    continue;
                }

                $answer = $this->answerValue($answers[$questionId]);
                $rows[] = [$attemptId, $questionId, $answer !== '' ? $answer : null];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                $params = [];
                foreach ($chunk as $row) array_push($params, ...$row);
                $db->execute(
                    'INSERT INTO evaluation_survey_answers (attempt_id, question_id, answer_value, score_value) VALUES ' . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, NULL)')) . ' ON DUPLICATE KEY UPDATE answer_value = VALUES(answer_value), score_value = NULL',
                    $params
                );
            }
        });
    }

    public function completeAttempt(array $form, array $attempt, array $questions, array $answers, string $status = 'completed'): array
    {
        $status = in_array($status, ['completed', 'expired'], true) ? $status : 'completed';
        $formType = (string) ($form['form_type'] ?? 'assessment');
        $maxScore = max(1, (float) ($form['max_score'] ?? 100));
        $earned = 0.0;
        $possible = 0.0;

        return $this->db->transaction(function (Database $db) use ($form, $attempt, $questions, $answers, $status, $formType, $maxScore, &$earned, &$possible): array {
            // The same row lock used by saveDraft() makes the final submission
            // the serialization point for all writes belonging to this attempt.
            $lockedAttempt = $db->fetch(
                'SELECT status, final_score, passed FROM evaluation_survey_attempts WHERE id = ? FOR UPDATE',
                [(int) $attempt['id']]
            );
            $lockedStatus = (string) ($lockedAttempt['status'] ?? '');
            $canCalculateExpired = $lockedStatus === 'expired'
                && $lockedAttempt !== null
                && $lockedAttempt['final_score'] === null
                && $lockedAttempt['passed'] === null;
            if (!$lockedAttempt || ($lockedStatus !== 'in_progress' && !$canCalculateExpired)) {
                return $this->findAttempt((int) $attempt['id']) ?: $attempt;
            }

            $answerRows = [];
            foreach ($questions as $question) {
                $questionId = (int) $question['id'];
                $answer = array_key_exists($questionId, $answers)
                    ? $this->answerValue($answers[$questionId])
                    : trim((string) ($question['answer_value'] ?? ''));
                $score = null;

                if ($formType === 'assessment') {
                    // Evaluaciones: cada pregunta asignada vale una unidad.
                    // Una respuesta vacía o incorrecta aporta cero, pero nunca
                    // reduce el denominador.
                    $score = $this->scoreQuestion($question, $answer) ?? 0.0;
                    $possible += 1.0;
                    $earned += $score > 0 ? 1.0 : 0.0;
                }

                $answerRows[] = [(int) $attempt['id'], $questionId, $answer !== '' ? $answer : null, $score];
            }
            foreach (array_chunk($answerRows, 500) as $chunk) {
                $params = [];
                foreach ($chunk as $row) array_push($params, ...$row);
                $db->execute(
                    'INSERT INTO evaluation_survey_answers (attempt_id, question_id, answer_value, score_value) VALUES ' . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?)')) . ' ON DUPLICATE KEY UPDATE answer_value = VALUES(answer_value), score_value = VALUES(score_value)',
                    $params
                );
            }

            $finalScore = null;
            $passed = null;
            if ($formType === 'assessment') {
                $finalScore = $possible > 0 ? round(($earned / $possible) * $maxScore, 2) : 0.0;
                $passingScore = $form['passing_score'] !== null ? (float) $form['passing_score'] : null;
                $passed = $passingScore !== null ? ($finalScore >= $passingScore ? 1 : 0) : null;
            }

            $db->execute('
                UPDATE evaluation_survey_attempts
                SET status = ?, completed_at = COALESCE(completed_at, NOW()), raw_score = ?,
                    final_score = ?, max_score = ?, passed = ?
                WHERE id = ? AND final_score IS NULL AND passed IS NULL
            ', [$status, $formType === 'assessment' ? $earned : null, $finalScore, $formType === 'assessment' ? $maxScore : null, $passed, (int) $attempt['id']]);

            return $this->findAttempt((int) $attempt['id']) ?: [];
        });
    }

    public function answersForAttempt(int $attemptId): array
    {
        $rows = $this->db->fetchAll('
            SELECT *
            FROM evaluation_survey_answers
            WHERE attempt_id = ?
            ORDER BY id ASC
        ', [$attemptId]);

        $answers = [];
        foreach ($rows as $row) {
            $answers[(int) $row['question_id']] = $row;
        }

        return $answers;
    }

    public function expireIfNeeded(array $attempt): array
    {
        if (($attempt['status'] ?? '') === 'expired' && ($attempt['final_score'] ?? null) === null && ($attempt['passed'] ?? null) === null) {
            $form = (new EvaluationSurveyFormModel($this->db))->findForm((int) ($attempt['form_id'] ?? 0));
            if ($form) {
                $questions = $this->questionsForAttempt($form, (int) $attempt['id']);
                $savedAnswers = [];
                foreach ($this->answersForAttempt((int) $attempt['id']) as $questionId => $answer) {
                    $savedAnswers[(int) $questionId] = $answer['answer_value'] ?? '';
                }

                return $this->completeAttempt($form, $attempt, $questions, $savedAnswers, 'expired');
            }
        }

        if (($attempt['status'] ?? '') !== 'in_progress' || empty($attempt['expires_at'])) {
            return $attempt;
        }

        if (strtotime((string) $attempt['expires_at']) > time()) {
            return $attempt;
        }

        $form = (new EvaluationSurveyFormModel($this->db))->findForm((int) ($attempt['form_id'] ?? 0));
        if ($form) {
            $questions = $this->questionsForAttempt($form, (int) $attempt['id']);
            $savedAnswers = [];
            foreach ($this->answersForAttempt((int) $attempt['id']) as $questionId => $answer) {
                $savedAnswers[(int) $questionId] = $answer['answer_value'] ?? '';
            }

            return $this->completeAttempt($form, $attempt, $questions, $savedAnswers, 'expired');
        }

        // Fallback defensivo si el formulario fue eliminado: al menos se
        // conserva el cierre del intento, sin inventar una nota.
        $this->db->execute('
            UPDATE evaluation_survey_attempts
            SET status = "expired", completed_at = COALESCE(completed_at, NOW())
            WHERE id = ? AND status = "in_progress"
        ', [(int) $attempt['id']]);

        return $this->findAttempt((int) $attempt['id']) ?: $attempt;
    }

    public function remainingSeconds(array $attempt): ?int
    {
        if (empty($attempt['expires_at'])) {
            return null;
        }

        return max(0, strtotime((string) $attempt['expires_at']) - time());
    }

    private function answerValue($raw): string
    {
        if (is_array($raw) && $this->isAssociativeAnswer($raw)) {
            $clean = [];
            foreach ($raw as $key => $value) {
                $key = trim((string) $key);
                $value = trim((string) $value);
                if ($key !== '' && $value !== '') {
                    $clean[$key] = $value;
                }
            }

            return $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        }

        return is_array($raw)
            ? implode(',', array_values(array_filter(array_map('trim', array_map('strval', $raw)), static fn(string $value): bool => $value !== '')))
            : trim((string) $raw);
    }

    private function isAssociativeAnswer(array $raw): bool
    {
        $index = 0;
        foreach ($raw as $key => $value) {
            if ($key !== $index || is_array($value)) {
                return true;
            }
            $index++;
        }

        return false;
    }

    private function attemptRanksHigher(array $candidate, array $current): bool
    {
        $candidateScore = $candidate['final_score'] !== null ? (float) $candidate['final_score'] : -1.0;
        $currentScore = $current['final_score'] !== null ? (float) $current['final_score'] : -1.0;
        if ($candidateScore !== $currentScore) {
            return $candidateScore > $currentScore;
        }

        $candidateCompleted = strtotime((string) ($candidate['completed_at'] ?? $candidate['updated_at'] ?? '')) ?: 0;
        $currentCompleted = strtotime((string) ($current['completed_at'] ?? $current['updated_at'] ?? '')) ?: 0;
        if ($candidateCompleted !== $currentCompleted) {
            return $candidateCompleted > $currentCompleted;
        }

        return (int) ($candidate['id'] ?? 0) > (int) ($current['id'] ?? 0);
    }

    private function scoreQuestion(array $question, string $answer): ?float
    {
        if ($answer === '') {
            return 0.0;
        }

        if ((string) $question['question_type'] === 'multiple_choice') {
            $selected = array_filter(array_map('trim', explode(',', $answer)), static fn(string $value): bool => $value !== '');
            sort($selected);
            $correct = [];
            foreach (($question['options'] ?? []) as $option) {
                if ((float) ($option['score_value'] ?? 0) > 0) {
                    $correct[] = (string) $option['option_value'];
                }
            }
            sort($correct);

            return $selected === $correct ? 1.0 : 0.0;
        }

        if (in_array((string) $question['question_type'], ['single_choice', 'true_false'], true)) {
            foreach (($question['options'] ?? []) as $option) {
                if ((string) $option['option_value'] === $answer && (float) ($option['score_value'] ?? 0) > 0) {
                    return 1.0;
                }
            }
            return 0.0;
        }

        return 0.0;
    }

    private function questionHasScore(array $question): bool
    {
        foreach (($question['options'] ?? []) as $option) {
            if ((float) ($option['score_value'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function possibleQuestionScore(array $question): float
    {
        $scores = array_map(static fn(array $option): float => (float) ($option['score_value'] ?? 0), $question['options'] ?? []);
        return max(1.0, (float) ($question['points'] ?? 1), $scores ? max($scores) : 0.0);
    }
}
