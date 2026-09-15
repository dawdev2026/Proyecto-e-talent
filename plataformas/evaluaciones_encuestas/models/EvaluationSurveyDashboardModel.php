<?php
declare(strict_types=1);

final class EvaluationSurveyDashboardModel
{
    private Database $db;
    private string $coreSchema;
    private string $testsSchema;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('evaluaciones_encuestas');
        $this->coreSchema = database_identifier('core');
        $this->testsSchema = database_identifier('tests');
    }

    public function dashboard(?int $companyId = null): array
    {
        $formScopeSql = '';
        $processScopeSql = '';
        $processParams = [];
        $unassignedParams = [];
        if ($companyId && $companyId > 0) {
            $formScopeSql = ' AND (f.company_id IS NULL OR f.company_id = ?)';
            $processScopeSql = ' AND (p.company_id = ? OR u.company_id = ?)';
            $processParams = [$companyId, $companyId, $companyId];
            $unassignedParams = [$companyId, $companyId];
        }

        // El universo nace en las asignaciones proceso-evaluacion-persona.
        // Los intentos historicos sin process_id se resuelven solo cuando la
        // persona tiene una unica asignacion activa para esa evaluacion.
        $rows = $this->db->fetchAll(
            'SELECT f.id, f.title, f.status, f.max_score, f.passing_score,
                    p.id AS process_id, p.name AS process_name, p.code AS process_code,
                    COUNT(DISTINCT pea.user_id) AS assigned_people,
                    COUNT(DISTINCT CASE WHEN a.status IN ("in_progress", "completed", "expired") THEN pea.user_id END) AS answered_people,
                    COUNT(DISTINCT CASE WHEN a.status IN ("completed", "expired") THEN pea.user_id END) AS finished_people,
                    COUNT(DISTINCT CASE WHEN a.status IN ("completed", "expired") AND a.final_score IS NOT NULL THEN pea.user_id END) AS graded_people,
                    COUNT(DISTINCT CASE WHEN a.status IN ("completed", "expired") AND a.final_score >= COALESCE(f.passing_score, f.max_score + 1) THEN pea.user_id END) AS approved_people,
                    COUNT(DISTINCT CASE WHEN a.status IN ("completed", "expired") AND a.final_score IS NOT NULL AND a.final_score < COALESCE(f.passing_score, f.max_score + 1) THEN pea.user_id END) AS failed_people,
                    COALESCE(AVG(CASE WHEN a.status IN ("completed", "expired") THEN a.final_score END), 0) AS average_score,
                    COALESCE(SUM(CASE WHEN a.status IN ("completed", "expired") THEN COALESCE(ast.correct_answers, 0) ELSE 0 END), 0) AS correct_answers,
                    COALESCE(SUM(CASE WHEN a.status IN ("completed", "expired") THEN COALESCE(ast.incorrect_answers, 0) ELSE 0 END), 0) AS incorrect_answers,
                    COALESCE(SUM(CASE WHEN a.status IN ("completed", "expired") THEN COALESCE(q.total_questions, 0) - COALESCE(ast.answered_answers, 0) ELSE 0 END), 0) AS unanswered_answers
             FROM ' . $this->testsSchema . '.test_process_evaluation_forms pef
             JOIN ' . $this->testsSchema . '.test_processes p ON p.id = pef.process_id
             JOIN evaluation_survey_forms f ON f.id = pef.form_id AND f.form_type = "assessment"' . $formScopeSql . '
             JOIN ' . $this->testsSchema . '.test_process_evaluation_assignments pea
               ON pea.process_id = pef.process_id AND pea.form_id = pef.form_id AND pea.status <> "cancelled"
             JOIN ' . $this->coreSchema . '.users u ON u.id = pea.user_id' . $processScopeSql . '
             LEFT JOIN evaluation_survey_attempts a
               ON a.form_id = pea.form_id
              AND a.user_id = pea.user_id
              AND a.attempt_number = (
                  SELECT MAX(a2.attempt_number)
                  FROM evaluation_survey_attempts a2
                  WHERE a2.form_id = pea.form_id
                    AND a2.user_id = pea.user_id
                    AND (
                        a2.process_id = pea.process_id
                        OR (
                            a2.process_id IS NULL
                            AND NOT EXISTS (
                                SELECT 1
                                FROM ' . $this->testsSchema . '.test_process_evaluation_assignments other_pea
                                WHERE other_pea.form_id = pea.form_id
                                  AND other_pea.user_id = pea.user_id
                                  AND other_pea.status <> "cancelled"
                                  AND other_pea.process_id <> pea.process_id
                            )
                        )
                    )
              )
              AND (
                  a.process_id = pea.process_id
                  OR (
                      a.process_id IS NULL
                      AND NOT EXISTS (
                          SELECT 1
                          FROM ' . $this->testsSchema . '.test_process_evaluation_assignments other_pea2
                          WHERE other_pea2.form_id = pea.form_id
                            AND other_pea2.user_id = pea.user_id
                            AND other_pea2.status <> "cancelled"
                            AND other_pea2.process_id <> pea.process_id
                      )
                  )
              )
             LEFT JOIN (
                 SELECT ea.attempt_id,
                        SUM(CASE WHEN ea.answer_value IS NOT NULL AND ea.answer_value <> "" AND ea.score_value > 0 THEN 1 ELSE 0 END) AS correct_answers,
                        SUM(CASE WHEN ea.answer_value IS NOT NULL AND ea.answer_value <> "" AND COALESCE(ea.score_value, 0) <= 0 THEN 1 ELSE 0 END) AS incorrect_answers,
                        COUNT(*) AS answered_answers
                 FROM evaluation_survey_answers ea
                 GROUP BY ea.attempt_id
             ) ast ON ast.attempt_id = a.id
             LEFT JOIN (
                 SELECT form_id, COUNT(*) AS total_questions
                 FROM evaluation_survey_questions
                 WHERE is_active = 1
                 GROUP BY form_id
             ) q ON q.form_id = f.id
             GROUP BY f.id, f.title, f.status, f.max_score, f.passing_score, p.id, p.name, p.code
             ORDER BY f.updated_at DESC, f.id DESC, p.name ASC',
            $processParams
        );

        // Conserva visibles las evaluaciones rendidas fuera de un proceso y
        // los casos ambiguos, sin inventar una pertenencia.
        $unassignedRows = $this->db->fetchAll(
            'SELECT f.id, f.title, f.status, f.max_score, f.passing_score,
                    NULL AS process_id, NULL AS process_name, NULL AS process_code,
                    COUNT(DISTINCT a.user_id) AS assigned_people,
                    COUNT(DISTINCT CASE WHEN a.status IN ("in_progress", "completed", "expired") THEN a.user_id END) AS answered_people,
                    COUNT(DISTINCT CASE WHEN a.status IN ("completed", "expired") THEN a.user_id END) AS finished_people,
                    COUNT(DISTINCT CASE WHEN a.status IN ("completed", "expired") AND a.final_score IS NOT NULL THEN a.user_id END) AS graded_people,
                    COUNT(DISTINCT CASE WHEN a.status IN ("completed", "expired") AND a.final_score >= COALESCE(f.passing_score, f.max_score + 1) THEN a.user_id END) AS approved_people,
                    COUNT(DISTINCT CASE WHEN a.status IN ("completed", "expired") AND a.final_score IS NOT NULL AND a.final_score < COALESCE(f.passing_score, f.max_score + 1) THEN a.user_id END) AS failed_people,
                    COALESCE(AVG(CASE WHEN a.status IN ("completed", "expired") THEN a.final_score END), 0) AS average_score,
                    COALESCE(SUM(CASE WHEN a.status IN ("completed", "expired") THEN COALESCE(ast.correct_answers, 0) ELSE 0 END), 0) AS correct_answers,
                    COALESCE(SUM(CASE WHEN a.status IN ("completed", "expired") THEN COALESCE(ast.incorrect_answers, 0) ELSE 0 END), 0) AS incorrect_answers,
                    COALESCE(SUM(CASE WHEN a.status IN ("completed", "expired") THEN COALESCE(q.total_questions, 0) - COALESCE(ast.answered_answers, 0) ELSE 0 END), 0) AS unanswered_answers
             FROM evaluation_survey_forms f
             JOIN evaluation_survey_attempts a ON a.form_id = f.id
             LEFT JOIN ' . $this->coreSchema . '.users u ON u.id = a.user_id
             LEFT JOIN (
                 SELECT ea.attempt_id,
                        SUM(CASE WHEN ea.answer_value IS NOT NULL AND ea.answer_value <> "" AND ea.score_value > 0 THEN 1 ELSE 0 END) AS correct_answers,
                        SUM(CASE WHEN ea.answer_value IS NOT NULL AND ea.answer_value <> "" AND COALESCE(ea.score_value, 0) <= 0 THEN 1 ELSE 0 END) AS incorrect_answers,
                        COUNT(*) AS answered_answers
                 FROM evaluation_survey_answers ea
                 GROUP BY ea.attempt_id
             ) ast ON ast.attempt_id = a.id
             LEFT JOIN (
                 SELECT form_id, COUNT(*) AS total_questions
                 FROM evaluation_survey_questions
                 WHERE is_active = 1
                 GROUP BY form_id
             ) q ON q.form_id = f.id
             WHERE f.form_type = "assessment"
               AND a.process_id IS NULL' . $formScopeSql . '
               AND (' . ($companyId && $companyId > 0 ? 'u.company_id = ? OR ' : '') . 'NOT EXISTS (
                    SELECT 1
                    FROM ' . $this->testsSchema . '.test_process_evaluation_assignments pea
                    WHERE pea.form_id = a.form_id AND pea.user_id = a.user_id AND pea.status <> "cancelled"
               ) OR 2 <= (
                    SELECT COUNT(DISTINCT pea2.process_id)
                    FROM ' . $this->testsSchema . '.test_process_evaluation_assignments pea2
                    WHERE pea2.form_id = a.form_id AND pea2.user_id = a.user_id AND pea2.status <> "cancelled"
               ))
             GROUP BY f.id, f.title, f.status, f.max_score, f.passing_score
             ORDER BY f.updated_at DESC, f.id DESC',
            $unassignedParams
        );
        $rows = array_merge($rows, $unassignedRows);

        $summary = ['forms' => count($rows), 'assigned_people' => 0, 'answered_people' => 0, 'finished' => 0, 'approved' => 0, 'failed' => 0, 'average_score' => 0.0];
        $weightedScore = 0.0;
        $gradedTotal = 0;
        foreach ($rows as &$row) {
            foreach (['assigned_people', 'answered_people', 'finished_people', 'graded_people', 'approved_people', 'failed_people'] as $key) {
                $row[$key] = (int) ($row[$key] ?? 0);
            }
            $row['average_score'] = (float) ($row['average_score'] ?? 0);
            $row['correct_answers'] = (int) ($row['correct_answers'] ?? 0);
            $row['incorrect_answers'] = (int) ($row['incorrect_answers'] ?? 0);
            $row['unanswered_answers'] = (int) ($row['unanswered_answers'] ?? 0);
            $row['passing_percentage'] = $row['max_score'] > 0 && $row['passing_score'] !== null
                ? ((float) $row['passing_score'] / (float) $row['max_score']) * 100
                : null;
            $summary['assigned_people'] += $row['assigned_people'];
            $summary['answered_people'] += $row['answered_people'];
            $summary['finished'] += $row['finished_people'];
            $summary['approved'] += $row['approved_people'];
            $summary['failed'] += $row['failed_people'];
            $weightedScore += $row['average_score'] * $row['graded_people'];
            $gradedTotal += $row['graded_people'];
        }
        unset($row);
        $summary['average_score'] = $gradedTotal > 0 ? $weightedScore / $gradedTotal : 0.0;
        $summary['approval_rate'] = $gradedTotal > 0 ? ($summary['approved'] / $gradedTotal) * 100 : 0.0;
        return ['summary' => $summary, 'rows' => $rows];
    }

    /**
     * Consolidates integrity signals only for process-evaluation assignments.
     * Signals are observations for manual review, not findings of misconduct.
     */
    public function integrityReport(?int $companyId = null, ?array $processIds = null, ?array $formIds = null, ?array $testIds = null): array
    {
        $conditions = ['pea.status <> "cancelled"', 'f.form_type = "assessment"'];
        $params = [];
        $activityAttentionEvents = ['tab_hidden', 'window_blurred', 'fullscreen_exited', 'fullscreen_failed', 'fullscreen_denied', 'fullscreen_unavailable', 'inactive_detected', 'copy_blocked', 'cut_blocked', 'paste_blocked', 'print_blocked', 'context_menu_blocked', 'drag_blocked', 'audio_visual_recording_interrupted', 'audio_visual_upload_failed', 'multiple_voice_possible'];
        $activityRiskEvents = ['suspicious_key_printscreen', 'suspicious_key_print', 'suspicious_key_save', 'suspicious_key_copy', 'suspicious_key_devtools', 'audio_visual_risk'];
        $activityAttentionSql = '"' . implode('","', $activityAttentionEvents) . '"';
        $activityRiskSql = '"' . implode('","', $activityRiskEvents) . '"';
        $mediaSignalEvents = ['multiple_voice_possible', 'audio_visual_risk', 'audio_visual_capture_failed', 'audio_visual_screen_failed', 'screen_capture_upload_failed', 'recording_upload_failed', 'audio_visual_recorder_failed', 'audio_visual_recorder_error', 'audio_visual_start_failed', 'permission_or_recording_failed', 'audio_visual_upload_failed', 'finalize_failed'];
        $reportSignalTypes = array_flip(array_merge($activityAttentionEvents, $activityRiskEvents, $mediaSignalEvents));
        if ($companyId && $companyId > 0) {
            $conditions[] = 'p.company_id = ? AND u.company_id = ? AND (f.company_id IS NULL OR f.company_id = ?)';
            $params[] = $companyId;
            $params[] = $companyId;
            $params[] = $companyId;
        }
        $processIds = $this->positiveIds($processIds);
        $formIds = $this->positiveIds($formIds);
        $testIds = $this->positiveIds($testIds);
        $includeEvaluations = !$testIds || $formIds;
        $includeTests = !$formIds || $testIds;
        if (!$includeEvaluations) $conditions[] = '1 = 0';
        if ($processIds) {
            $conditions[] = 'pea.process_id IN (' . implode(',', array_fill(0, count($processIds), '?')) . ')';
            array_push($params, ...$processIds);
        }
        if ($formIds) {
            $conditions[] = 'pea.form_id IN (' . implode(',', array_fill(0, count($formIds), '?')) . ')';
            array_push($params, ...$formIds);
        }

        $rows = $this->db->fetchAll(
            'SELECT pea.process_id, p.name AS process_name, p.code AS process_code,
                    pea.form_id, f.title AS form_title, pea.user_id, u.rut, u.name AS user_name,
                    u.email AS user_email, a.id AS attempt_id, a.status AS attempt_status,
                    a.created_at AS attempt_started_at, a.final_score, a.completed_at, a.updated_at,
                    COALESCE(act.total_events, 0) AS activity_events_total,
                    COALESCE(act.attention_events, 0) AS activity_attention_total,
                    COALESCE(act.risk_events, 0) AS activity_risk_total,
                    act.event_types, COALESCE(rsk.total_events, 0) AS media_risk_events_total,
                    COALESCE(rsk.attention_events, 0) AS media_attention_total,
                    COALESCE(rsk.risk_events, 0) AS media_risk_total,
                    rsk.risk_types, e.status AS evidence_status,
                    e.failure_code AS evidence_failure_code
             FROM ' . $this->testsSchema . '.test_process_evaluation_assignments pea
             JOIN ' . $this->testsSchema . '.test_processes p ON p.id = pea.process_id
             JOIN evaluation_survey_forms f ON f.id = pea.form_id
             JOIN ' . $this->coreSchema . '.users u ON u.id = pea.user_id
             LEFT JOIN evaluation_survey_attempts a
               ON a.id = (SELECT a2.id FROM evaluation_survey_attempts a2
                          WHERE a2.form_id = pea.form_id AND a2.user_id = pea.user_id
                            AND (a2.process_id = pea.process_id OR a2.process_id IS NULL)
                          ORDER BY (a2.process_id = pea.process_id) DESC, a2.attempt_number DESC, a2.id DESC LIMIT 1)
             LEFT JOIN (
                 SELECT attempt_id, COUNT(*) AS total_events,
                        SUM(event_type IN (' . $activityAttentionSql . ')) AS attention_events,
                        SUM(event_type IN (' . $activityRiskSql . ')) AS risk_events,
                        GROUP_CONCAT(DISTINCT CASE WHEN event_type IN (' . $activityAttentionSql . ',' . $activityRiskSql . ') THEN event_type END ORDER BY event_type SEPARATOR ",") AS event_types
                 FROM evaluation_survey_activity_events GROUP BY attempt_id
             ) act ON act.attempt_id = a.id
             LEFT JOIN (
                 SELECT attempt_id, COUNT(*) AS total_events,
                        SUM(CASE WHEN event_type IN ("multiple_voice_possible","audio_visual_risk","audio_visual_capture_failed","audio_visual_screen_failed","screen_capture_upload_failed","recording_upload_failed","audio_visual_recorder_failed","audio_visual_recorder_error","audio_visual_start_failed","permission_or_recording_failed","audio_visual_upload_failed","finalize_failed") AND severity = "attention" THEN 1 ELSE 0 END) AS attention_events,
                        SUM(CASE WHEN event_type IN ("multiple_voice_possible","audio_visual_risk","audio_visual_capture_failed","audio_visual_screen_failed","screen_capture_upload_failed","recording_upload_failed","audio_visual_recorder_failed","audio_visual_recorder_error","audio_visual_start_failed","permission_or_recording_failed","audio_visual_upload_failed","finalize_failed") AND severity = "risk" THEN 1 ELSE 0 END) AS risk_events,
                        GROUP_CONCAT(DISTINCT CASE WHEN event_type IN ("multiple_voice_possible","audio_visual_risk","audio_visual_capture_failed","audio_visual_screen_failed","screen_capture_upload_failed","recording_upload_failed","audio_visual_recorder_failed","audio_visual_recorder_error","audio_visual_start_failed","permission_or_recording_failed","audio_visual_upload_failed","finalize_failed") THEN event_type END ORDER BY event_type SEPARATOR ",") AS risk_types
                 FROM evaluation_survey_media_risk_events GROUP BY attempt_id
             ) rsk ON rsk.attempt_id = a.id
             LEFT JOIN evaluation_survey_media_evidence e ON e.attempt_id = a.id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY (COALESCE(act.risk_events, 0) + COALESCE(rsk.risk_events, 0)) DESC,
                      (COALESCE(act.attention_events, 0) + COALESCE(rsk.attention_events, 0)) DESC,
                      p.name ASC, u.name ASC',
            $params
        );

        // Los tests psicométricos no usan evaluation_survey_attempts: su unidad
        // de seguimiento es la sesión del test y sus eventos viven en tests.
        // Se normalizan aquí al mismo contrato del reporte para consolidarlos
        // con las evaluaciones sin perder el aislamiento por empresa.
        $testConditions = ['ts.process_id IS NOT NULL', 'ts.status <> "cancelled"'];
        $testParams = [];
        if ($companyId && $companyId > 0) {
            $testConditions[] = 'p.company_id = ? AND u.company_id = ?';
            $testParams[] = $companyId;
            $testParams[] = $companyId;
        }
        if ($processIds) {
            $testConditions[] = 'ts.process_id IN (' . implode(',', array_fill(0, count($processIds), '?')) . ')';
            array_push($testParams, ...$processIds);
        }
        if ($testIds) {
            $testConditions[] = 'ts.instrument_id IN (' . implode(',', array_fill(0, count($testIds), '?')) . ')';
            array_push($testParams, ...$testIds);
        }
        if (!$includeTests) $testConditions[] = '1 = 0';
        $testScopeConditions = ['ts0.process_id IS NOT NULL', 'ts0.status <> "cancelled"'];
        $testScopeParams = [];
        if ($companyId && $companyId > 0) {
            $testScopeConditions[] = 'p0.company_id = ? AND u0.company_id = ?';
            $testScopeParams[] = $companyId;
            $testScopeParams[] = $companyId;
        }
        if ($processIds) {
            $testScopeConditions[] = 'ts0.process_id IN (' . implode(',', array_fill(0, count($processIds), '?')) . ')';
            array_push($testScopeParams, ...$processIds);
        }
        if ($testIds) {
            $testScopeConditions[] = 'ts0.instrument_id IN (' . implode(',', array_fill(0, count($testIds), '?')) . ')';
            array_push($testScopeParams, ...$testIds);
        }
        if (!$includeTests) $testScopeConditions[] = '1 = 0';
        $testSessionScopeSql = 'SELECT ts0.id FROM ' . $this->testsSchema . '.test_sessions ts0 JOIN ' . $this->testsSchema . '.test_processes p0 ON p0.id = ts0.process_id JOIN ' . $this->coreSchema . '.users u0 ON u0.id = ts0.user_id WHERE ' . implode(' AND ', $testScopeConditions);
        $testActivityAttentionSql = '"tab_hidden","window_blurred","fullscreen_exited","fullscreen_failed","fullscreen_denied","fullscreen_unavailable","inactive_detected","copy_blocked","cut_blocked","paste_blocked","print_blocked","context_menu_blocked","drag_blocked"';
        $testActivityRiskSql = '"suspicious_key_printscreen","suspicious_key_print","suspicious_key_save","suspicious_key_copy","suspicious_key_devtools"';
        $testMediaSignalSql = '"multiple_voice_possible","audio_visual_risk","audio_visual_capture_failed","audio_visual_screen_failed","screen_capture_upload_failed","recording_upload_failed","audio_visual_recorder_failed","audio_visual_recorder_error","audio_visual_start_failed","permission_or_recording_failed","audio_visual_upload_failed","finalize_failed"';
        $testRows = $this->db->fetchAll(
            'SELECT ts.process_id, p.name AS process_name, p.code AS process_code,
                    0 AS form_id, i.name AS form_title, ts.user_id, u.rut,
                    u.name AS user_name, u.email AS user_email, NULL AS attempt_id,
                    ts.status AS attempt_status, ts.started_at AS attempt_started_at,
                    NULL AS final_score, ts.completed_at, ts.updated_at,
                    COALESCE(act.total_events, 0) AS activity_events_total,
                    COALESCE(act.attention_events, 0) AS activity_attention_total,
                    COALESCE(act.risk_events, 0) AS activity_risk_total,
                    act.event_types, COALESCE(rsk.total_events, 0) AS media_risk_events_total,
                    COALESCE(rsk.attention_events, 0) AS media_attention_total,
                    COALESCE(rsk.risk_events, 0) AS media_risk_total,
                    rsk.risk_types, ev.status AS evidence_status,
                    ev.failure_code AS evidence_failure_code
             FROM ' . $this->testsSchema . '.test_sessions ts
             JOIN ' . $this->testsSchema . '.test_processes p ON p.id = ts.process_id
             JOIN ' . $this->testsSchema . '.test_instruments i ON i.id = ts.instrument_id
             JOIN ' . $this->coreSchema . '.users u ON u.id = ts.user_id
             LEFT JOIN (
                 SELECT session_id, COUNT(*) AS total_events,
                        SUM(event_type IN (' . $testActivityAttentionSql . ')) AS attention_events,
                        SUM(event_type IN (' . $testActivityRiskSql . ')) AS risk_events,
                        GROUP_CONCAT(DISTINCT CASE WHEN event_type IN (' . $testActivityAttentionSql . ',' . $testActivityRiskSql . ') THEN event_type END ORDER BY event_type SEPARATOR ",") AS event_types
                 FROM ' . $this->testsSchema . '.test_activity_events tae
                 JOIN (' . $testSessionScopeSql . ') scoped_sessions ON scoped_sessions.id = tae.session_id
                 GROUP BY session_id
             ) act ON act.session_id = ts.id
             LEFT JOIN (
                 SELECT session_id, COUNT(*) AS total_events,
                        SUM(CASE WHEN event_type IN (' . $testMediaSignalSql . ') AND severity = "attention" THEN 1 ELSE 0 END) AS attention_events,
                        SUM(CASE WHEN event_type IN (' . $testMediaSignalSql . ') AND severity = "risk" THEN 1 ELSE 0 END) AS risk_events,
                        GROUP_CONCAT(DISTINCT CASE WHEN event_type IN (' . $testMediaSignalSql . ') THEN event_type END ORDER BY event_type SEPARATOR ",") AS risk_types
                 FROM ' . $this->testsSchema . '.test_audio_visual_risk_events tar
                 JOIN (' . $testSessionScopeSql . ') scoped_sessions ON scoped_sessions.id = tar.session_id
                 GROUP BY session_id
             ) rsk ON rsk.session_id = ts.id
             LEFT JOIN (
                 SELECT session_id, MAX(id) AS evidence_id
                 FROM ' . $this->testsSchema . '.test_media_evidence GROUP BY session_id
             ) latest_ev ON latest_ev.session_id = ts.id
             LEFT JOIN ' . $this->testsSchema . '.test_media_evidence ev ON ev.id = latest_ev.evidence_id
             WHERE ' . implode(' AND ', $testConditions) . '
             ORDER BY (COALESCE(act.risk_events, 0) + COALESCE(rsk.risk_events, 0)) DESC,
                      (COALESCE(act.attention_events, 0) + COALESCE(rsk.attention_events, 0)) DESC,
                      p.name ASC, u.name ASC',
            array_merge($testScopeParams, $testScopeParams, $testParams)
        );
        $rows = array_merge($rows, $testRows);

        $eventCounts = $this->integrityEventCounts(array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int) ($row['attempt_id'] ?? 0), $rows)))));

        $summary = ['assigned_people' => count($rows), 'distinct_assigned_people' => count(array_unique(array_filter(array_column($rows, 'user_id')))), 'attempted_people' => 0, 'finished_people' => 0,
            'people_with_incidents' => 0, 'review_cases' => 0, 'high_alert_cases' => 0,
            'activity_events' => 0, 'media_events' => 0];
        foreach ($rows as &$row) {
            $activityRisk = (int) ($row['activity_risk_total'] ?? 0);
            $mediaRisk = (int) ($row['media_risk_total'] ?? 0);
            $attention = (int) ($row['activity_attention_total'] ?? 0) + (int) ($row['media_attention_total'] ?? 0);
            $technicalFailure = in_array((string) ($row['evidence_status'] ?? ''), ['failed', 'partial', 'not_supported', 'not_consented'], true)
                || (string) ($row['evidence_failure_code'] ?? '') !== '';
            $total = (int) ($row['activity_attention_total'] ?? 0) + $activityRisk
                + (int) ($row['media_attention_total'] ?? 0) + $mediaRisk + ($technicalFailure ? 1 : 0);
            $row['incident_total'] = $total;
            $row['alert_level'] = ($activityRisk + $mediaRisk) > 0 ? 'high' : (($attention > 0 || $technicalFailure) ? 'review' : 'none');
            $row['signal_types'] = array_values(array_unique(array_filter(array_merge(
                explode(',', (string) ($row['event_types'] ?? '')),
                explode(',', (string) ($row['risk_types'] ?? '')),
                $technicalFailure ? ['evidence_' . (string) ($row['evidence_status'] ?? 'failure')] : []
            ))));
            $row['signal_counts'] = $eventCounts[(int) ($row['attempt_id'] ?? 0)] ?? [];
            if ($technicalFailure) $row['signal_counts']['evidence_' . (string) ($row['evidence_status'] ?? 'failure')] = 1;
            if ($row['signal_counts']) $row['signal_types'] = array_values(array_unique(array_merge(array_keys(array_intersect_key($row['signal_counts'], $reportSignalTypes)), $row['signal_types'])));
            $summary['attempted_people'] += !empty($row['attempt_id']) || in_array((string) ($row['attempt_status'] ?? ''), ['in_progress', 'completed', 'expired'], true) ? 1 : 0;
            $summary['finished_people'] += in_array((string) ($row['attempt_status'] ?? ''), ['completed', 'expired'], true) ? 1 : 0;
            $summary['people_with_incidents'] += $total > 0 || $technicalFailure ? 1 : 0;
            $summary['activity_events'] += (int) ($row['activity_events_total'] ?? 0);
            $summary['media_events'] += (int) ($row['media_risk_events_total'] ?? 0);
        }
        unset($row);
        $analysis = $this->buildIntegrityAnalysis($rows, $summary);
        $summary['review_cases'] = 0;
        $summary['high_alert_cases'] = 0;
        foreach ($rows as &$row) {
            $personLevel = (string) ($analysis['person_levels'][(string) ($row['user_id'] ?? '')] ?? 'technical');
            $row['person_level'] = $personLevel;
            $row['alert_level'] = ($row['incident_total'] ?? 0) <= 0 ? 'none' : ($personLevel === 'high' ? 'high' : 'review');
            $summary['review_cases'] += $row['alert_level'] === 'review' ? 1 : 0;
            $summary['high_alert_cases'] += $row['alert_level'] === 'high' ? 1 : 0;
        }
        unset($row);
        $analysis['person_levels'] = $analysis['person_levels'] ?? [];
        return ['summary' => $summary, 'rows' => $rows, 'analysis' => $analysis];
    }

    public function integrityFilterOptions(?int $companyId = null): array
    {
        $conditions = ['pea.status <> "cancelled"', 'f.form_type = "assessment"'];
        $params = [];
        if ($companyId && $companyId > 0) {
            $conditions[] = 'p.company_id = ? AND u.company_id = ? AND (f.company_id IS NULL OR f.company_id = ?)';
            $params = [$companyId, $companyId, $companyId];
        }
        $rows = $this->db->fetchAll('SELECT DISTINCT f.id AS form_id, f.title AS form_title, 0 AS test_id, "" AS test_name, p.id AS process_id, p.name AS process_name FROM ' . $this->testsSchema . '.test_process_evaluation_assignments pea JOIN ' . $this->testsSchema . '.test_processes p ON p.id = pea.process_id JOIN evaluation_survey_forms f ON f.id = pea.form_id JOIN ' . $this->coreSchema . '.users u ON u.id = pea.user_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY f.title ASC, p.name ASC', $params);
        $testConditions = ['ts.process_id IS NOT NULL', 'ts.status <> "cancelled"'];
        $testParams = [];
        if ($companyId && $companyId > 0) {
            $testConditions[] = 'p.company_id = ? AND u.company_id = ?';
            $testParams[] = $companyId;
            $testParams[] = $companyId;
        }
        $testProcessRows = $this->db->fetchAll('SELECT DISTINCT 0 AS form_id, i.name AS form_title, i.id AS test_id, i.name AS test_name, p.id AS process_id, p.name AS process_name FROM ' . $this->testsSchema . '.test_sessions ts JOIN ' . $this->testsSchema . '.test_processes p ON p.id = ts.process_id JOIN ' . $this->testsSchema . '.test_instruments i ON i.id = ts.instrument_id JOIN ' . $this->coreSchema . '.users u ON u.id = ts.user_id WHERE ' . implode(' AND ', $testConditions) . ' ORDER BY p.name ASC, i.name ASC', $testParams);
        $rows = array_merge($rows, $testProcessRows);
        $forms = []; $tests = []; $processes = [];
        foreach ($rows as $row) {
            if ((int) ($row['form_id'] ?? 0) > 0) $forms[(int) $row['form_id']] = (string) $row['form_title'];
            if ((int) ($row['test_id'] ?? 0) > 0) $tests[(int) $row['test_id']] = (string) $row['test_name'];
            $processes[(int) $row['process_id']] = (string) $row['process_name'];
        }
        return ['forms' => $forms, 'tests' => $tests, 'processes' => $processes];
    }

    private function integrityEventCounts(array $attemptIds): array
    {
        if (!$attemptIds) return [];
        $placeholders = implode(',', array_fill(0, count($attemptIds), '?'));
        $counts = [];
        foreach ($this->db->fetchAll('SELECT attempt_id, event_type, COUNT(*) AS total FROM evaluation_survey_activity_events WHERE attempt_id IN (' . $placeholders . ') GROUP BY attempt_id, event_type', $attemptIds) as $event) $counts[(int) $event['attempt_id']][(string) $event['event_type']] = (int) $event['total'];
        foreach ($this->db->fetchAll('SELECT attempt_id, event_type, COUNT(*) AS total FROM evaluation_survey_media_risk_events WHERE attempt_id IN (' . $placeholders . ') GROUP BY attempt_id, event_type', $attemptIds) as $event) $counts[(int) $event['attempt_id']][(string) $event['event_type']] = ($counts[(int) $event['attempt_id']][(string) $event['event_type']] ?? 0) + (int) $event['total'];
        return $counts;
    }

    private function buildIntegrityAnalysis(array $rows, array $summary): array
    {
        $conductual = ['multiple_voice_possible', 'tab_hidden', 'window_blurred', 'fullscreen_exited', 'fullscreen_denied', 'fullscreen_failed', 'suspicious_key_printscreen', 'suspicious_key_print', 'suspicious_key_copy', 'suspicious_key_save', 'suspicious_key_devtools'];
        $technical = ['audio_visual_capture_failed', 'audio_visual_upload_failed', 'audio_visual_screen_failed', 'screen_capture_upload_failed', 'recording_upload_failed', 'finalize_failed', 'audio_visual_recording_interrupted', 'fullscreen_failed', 'fullscreen_denied', 'fullscreen_unavailable', 'inactive_detected', 'copy_blocked', 'cut_blocked', 'paste_blocked', 'print_blocked', 'context_menu_blocked', 'drag_blocked'];
        $people = []; $signals = []; $processes = []; $co = [];
        foreach ($rows as $row) {
            $total = (int) ($row['incident_total'] ?? 0); if ($total <= 0) continue;
            $userKey = (string) ($row['user_id'] ?? $row['user_name'] ?? 'unknown');
            if (!isset($people[$userKey])) $people[$userKey] = ['events' => 0, 'attempts' => 0, 'conduct' => [], 'incident_attempts' => 0];
            $people[$userKey]['events'] += $total; $people[$userKey]['attempts']++; $people[$userKey]['incident_attempts']++;
            $types = (array) ($row['signal_counts'] ?? []); if (!$types) foreach ((array) ($row['signal_types'] ?? []) as $type) $types[(string) $type] = 1;
            foreach ($types as $type => $count) { $type = (string) $type; $count = (int) $count; if ($count <= 0 || (!in_array($type, $conductual, true) && !in_array($type, $technical, true))) continue; $signals[$type]['attempts'] = ($signals[$type]['attempts'] ?? 0) + 1; $signals[$type]['people'][$userKey] = true; if (in_array($type, $conductual, true)) $people[$userKey]['conduct'][$type] = true; }
            $processKey = (string) ($row['process_id'] ?? '') . ':' . (string) ($row['form_id'] ?? '');
            if (!isset($processes[$processKey])) $processes[$processKey] = ['name' => (string) ($row['process_name'] ?? 'Proceso'), 'people' => [], 'high' => 0, 'medium' => 0, 'low' => 0];
            $processes[$processKey]['people'][$userKey] = true;
        }
        foreach ($people as $key => &$person) { $person['conduct_count'] = count($person['conduct']); $person['score'] = ($person['conduct_count'] * 3) + (2 * log(1 + $person['events'])) + (1.5 * max(0, $person['incident_attempts'] - 1)); $person['technical_only'] = $person['conduct_count'] === 0; }
        unset($person);
        $behavioral = array_filter($people, static fn(array $person): bool => !$person['technical_only']); usort($behavioral, static fn(array $a, array $b): int => $b['score'] <=> $a['score']); $technicalPeople = array_filter($people, static fn(array $person): bool => $person['technical_only']);
        $levels = ['high' => ['label' => 'ALTO', 'people' => 0, 'pct' => '0%', 'rule' => '3 o más señales conductuales distintas o score >=16'], 'medium' => ['label' => 'MEDIO', 'people' => 0, 'pct' => '0%', 'rule' => '2 señales conductuales distintas'], 'low' => ['label' => 'BAJO', 'people' => 0, 'pct' => '0%', 'rule' => '1 señal conductual sin acumulación suficiente'], 'technical' => ['label' => 'SOLO TÉCNICO', 'people' => count($technicalPeople), 'pct' => '0%', 'rule' => 'Sin señales conductuales; solo fallas de plataforma o equipo']];
        $personLevels = [];
        foreach ($behavioral as $key => $person) { $level = $person['conduct_count'] >= 3 || $person['score'] >= 16 ? 'high' : ($person['conduct_count'] >= 2 ? 'medium' : 'low'); $levels[$level]['people']++; $personLevels[(string) $key] = $level; }
        foreach ($technicalPeople as $key => $_person) $personLevels[(string) $key] = 'technical';
        foreach ($processes as &$process) { foreach (array_keys($process['people']) as $personKey) { $level = $personLevels[(string) $personKey] ?? 'low'; if ($level === 'high') $process['high']++; elseif ($level === 'medium') $process['medium']++; else $process['low']++; } } unset($process);
        $distinct = max(1, count($people)); foreach ($levels as &$level) $level['pct'] = number_format(($level['people'] / $distinct) * 100, 0) . '%'; unset($level);
        foreach ($signals as $type => &$signal) { $signal['people'] = count($signal['people']); $signal['label'] = $type; } unset($signal); uasort($signals, static fn(array $a, array $b): int => $b['attempts'] <=> $a['attempts']);
        foreach ($processes as &$process) { $process['people'] = count($process['people']); } unset($process);
        $conductMatrix = ['multiple_voice_possible', 'window_blurred', 'tab_hidden', 'fullscreen_exited']; $cooccurrence = [['', 'Voces múltiples', 'Pérdida de foco', 'Cambio de pestaña', 'Salida pant. completa']]; foreach ($conductMatrix as $rowType) { $row = [$rowType === 'multiple_voice_possible' ? 'Voces múltiples' : ($rowType === 'window_blurred' ? 'Pérdida de foco' : ($rowType === 'tab_hidden' ? 'Cambio de pestaña' : 'Salida pant. completa'))]; foreach ($conductMatrix as $columnType) { $row[] = (string) count(array_filter($people, static fn(array $person): bool => isset($person['conduct'][$rowType], $person['conduct'][$columnType]))); } $cooccurrence[] = $row; }
        $recurrence = [['Pruebas con incidencia', 'Personas']]; foreach ([1, 2, 3] as $attemptCount) $recurrence[] = [(string) $attemptCount, (string) count(array_filter($people, static fn(array $person): bool => (int) $person['incident_attempts'] === $attemptCount))]; $conductDistribution = []; foreach ([0, 1, 2, 3, 4] as $conductCount) $conductDistribution[] = [(string) $conductCount, (string) count(array_filter($people, static fn(array $person): bool => count($person['conduct']) === $conductCount))];
        $events = array_column($people, 'events'); rsort($events); $totalEvents = max(1, array_sum($events)); $top10 = max(1, (int) ceil(count($events) * .10)); $top25 = max(1, (int) ceil(count($events) * .25));
        $classifiedEvents = array_sum(array_map(static fn(array $signal): int => (int) $signal['attempts'], $signals)); $technicalEvents = array_sum(array_map(static fn(array $signal): int => in_array($signal['label'], $technical, true) ? (int) $signal['attempts'] : 0, $signals));
        return ['universe' => ['assigned' => (int) ($summary['assigned_people'] ?? count($rows)), 'distinct_assigned' => (int) ($summary['distinct_assigned_people'] ?? 0), 'attempted' => (int) ($summary['attempted_people'] ?? 0), 'incident_assignments' => count(array_filter($rows, static fn(array $row): bool => (int) ($row['incident_total'] ?? 0) > 0)), 'distinct_incident_people' => count($people), 'activity' => (int) ($summary['activity_events'] ?? 0), 'media' => (int) ($summary['media_events'] ?? 0)], 'totals' => ['classified_events' => $classifiedEvents, 'technical_events' => $technicalEvents, 'conductual_events' => max(0, $classifiedEvents - $technicalEvents)], 'levels' => $levels, 'person_levels' => $personLevels, 'signals' => array_map(static fn(array $signal): array => [$signal['label'], $signal['attempts'], $signal['people'], ''], $signals), 'concentration' => [['10% de las personas con más eventos', number_format(array_sum(array_slice($events, 0, $top10)) / $totalEvents * 100, 0) . '% del total'], ['25% de las personas con más eventos', number_format(array_sum(array_slice($events, 0, $top25)) / $totalEvents * 100, 0) . '% del total']], 'event_distribution' => $events, 'cooccurrence' => $cooccurrence, 'conduct_distribution' => $conductDistribution, 'recurrence' => $recurrence, 'processes' => array_values($processes)];
    }

    private function scope(?int $companyId): array
    {
        if (!$companyId || $companyId <= 0) {
            return ['formsSql' => '', 'params' => []];
        }
        return [
            'formsSql' => ' AND (f.company_id IS NULL OR f.company_id = ?)',
            'params' => [$companyId, $companyId, $companyId],
        ];
    }

    private function positiveIds(?array $values): array
    {
        $ids = [];
        foreach ((array) $values as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) $ids[(int) $id] = (int) $id;
        }
        return array_values($ids);
    }
}
