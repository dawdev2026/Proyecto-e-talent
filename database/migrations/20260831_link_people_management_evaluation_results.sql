-- Vincula resultados historicos de las evaluaciones seleccionadas en el proceso
-- de Gestion de Personas cuando existe una unica asignacion activa inequívoca.
-- No modifica respuestas, puntajes, estados ni fechas del intento.

START TRANSACTION;

UPDATE e_talent_evaluaciones_encuestas.evaluation_survey_attempts ea
JOIN (
    SELECT candidate_id, process_id
    FROM (
        SELECT ea0.id AS candidate_id, pea0.process_id
        FROM e_talent_evaluaciones_encuestas.evaluation_survey_attempts ea0
        JOIN e_talent_tests.test_process_evaluation_assignments pea0
          ON pea0.form_id = ea0.form_id
         AND pea0.user_id = ea0.user_id
         AND pea0.status <> 'cancelled'
        JOIN e_talent_tests.test_processes p0 ON p0.id = pea0.process_id
        JOIN e_talent_tests.test_process_evaluation_forms pef0
          ON pef0.process_id = pea0.process_id
         AND pef0.form_id = pea0.form_id
        LEFT JOIN e_talent_tests.test_process_evaluation_assignments other_pea
          ON other_pea.form_id = ea0.form_id
         AND other_pea.user_id = ea0.user_id
         AND other_pea.status <> 'cancelled'
         AND other_pea.process_id <> pea0.process_id
        LEFT JOIN e_talent_evaluaciones_encuestas.evaluation_survey_attempts existing_ea
          ON existing_ea.process_id = pea0.process_id
         AND existing_ea.form_id = ea0.form_id
         AND existing_ea.user_id = ea0.user_id
         AND existing_ea.attempt_number = ea0.attempt_number
        WHERE ea0.process_id IS NULL
          AND p0.code = 'p_a_coordinador_a_de_gesti_n_de_personas'
        GROUP BY ea0.id, pea0.process_id
        HAVING COUNT(DISTINCT other_pea.process_id) = 0
           AND COUNT(DISTINCT existing_ea.id) = 0
    ) eligible_rows
) eligible ON eligible.candidate_id = ea.id
SET ea.process_id = eligible.process_id
WHERE ea.process_id IS NULL;

COMMIT;
