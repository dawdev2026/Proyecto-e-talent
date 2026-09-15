-- Resumen incremental por sesión para dashboards y consolidación de procesos.
-- Aplicar por ambiente mediante el procedimiento autorizado; no ejecutar desde PHP.

CREATE TABLE IF NOT EXISTS test_session_rollups (
    session_id INT UNSIGNED NOT NULL,
    process_id INT UNSIGNED NULL,
    instrument_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    answers_count INT UNSIGNED NOT NULL DEFAULT 0,
    answered_count INT UNSIGNED NOT NULL DEFAULT 0,
    activity_events_total INT UNSIGNED NOT NULL DEFAULT 0,
    activity_attention_total INT UNSIGNED NOT NULL DEFAULT 0,
    activity_risk_total INT UNSIGNED NOT NULL DEFAULT 0,
    last_activity_event_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id),
    KEY idx_test_session_rollups_process (process_id, session_id),
    KEY idx_test_session_rollups_user (user_id, session_id),
    CONSTRAINT fk_test_session_rollups_session FOREIGN KEY (session_id) REFERENCES test_sessions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO test_session_rollups (session_id, process_id, instrument_id, user_id, answers_count, answered_count, activity_events_total, activity_attention_total, activity_risk_total, last_activity_event_at)
SELECT s.id, s.process_id, s.instrument_id, s.user_id,
       COALESCE(a.answers_count, 0), COALESCE(a.answered_count, 0),
       COALESCE(e.events_total, 0), COALESCE(e.attention_total, 0), COALESCE(e.risk_total, 0), e.last_event_at
FROM test_sessions s
LEFT JOIN (
    SELECT session_id, COUNT(*) answers_count,
           SUM(CASE WHEN answer_value IS NOT NULL AND TRIM(answer_value) <> '' THEN 1 ELSE 0 END) answered_count
    FROM test_answers GROUP BY session_id
) a ON a.session_id = s.id
LEFT JOIN (
    SELECT session_id, COUNT(*) events_total,
           SUM(CASE WHEN event_type IN ('tab_hidden','window_blurred','inactive_detected','fullscreen_exited') THEN 1 ELSE 0 END) attention_total,
           SUM(CASE WHEN event_type IN ('fullscreen_denied','fullscreen_failed','fullscreen_unavailable','suspicious_key_printscreen','suspicious_key_print','suspicious_key_save','suspicious_key_copy','suspicious_key_devtools','context_menu_blocked','copy_blocked','cut_blocked','paste_blocked','drag_blocked','print_blocked') THEN 1 ELSE 0 END) risk_total,
           MAX(created_at) last_event_at
    FROM test_activity_events GROUP BY session_id
) e ON e.session_id = s.id
ON DUPLICATE KEY UPDATE process_id = VALUES(process_id), instrument_id = VALUES(instrument_id), user_id = VALUES(user_id), answers_count = VALUES(answers_count), answered_count = VALUES(answered_count), activity_events_total = VALUES(activity_events_total), activity_attention_total = VALUES(activity_attention_total), activity_risk_total = VALUES(activity_risk_total), last_activity_event_at = VALUES(last_activity_event_at);

DELIMITER $$
CREATE TRIGGER trg_test_sessions_rollup_ai AFTER INSERT ON test_sessions FOR EACH ROW
BEGIN
    INSERT INTO test_session_rollups (session_id, process_id, instrument_id, user_id)
    VALUES (NEW.id, NEW.process_id, NEW.instrument_id, NEW.user_id)
    ON DUPLICATE KEY UPDATE process_id = VALUES(process_id), instrument_id = VALUES(instrument_id), user_id = VALUES(user_id);
END$$

CREATE TRIGGER trg_test_sessions_rollup_au AFTER UPDATE ON test_sessions FOR EACH ROW
BEGIN
    UPDATE test_session_rollups SET process_id = NEW.process_id, instrument_id = NEW.instrument_id, user_id = NEW.user_id WHERE session_id = NEW.id;
END$$

CREATE TRIGGER trg_test_answers_rollup_ai AFTER INSERT ON test_answers FOR EACH ROW
BEGIN
    INSERT INTO test_session_rollups (session_id, process_id, instrument_id, user_id, answers_count, answered_count)
    SELECT id, process_id, instrument_id, user_id, 1, IF(NEW.answer_value IS NOT NULL AND TRIM(NEW.answer_value) <> '', 1, 0)
    FROM test_sessions WHERE id = NEW.session_id
    ON DUPLICATE KEY UPDATE answers_count = answers_count + 1, answered_count = answered_count + IF(NEW.answer_value IS NOT NULL AND TRIM(NEW.answer_value) <> '', 1, 0);
END$$

CREATE TRIGGER trg_test_answers_rollup_au AFTER UPDATE ON test_answers FOR EACH ROW
BEGIN
    UPDATE test_session_rollups
    SET answered_count = GREATEST(0, answered_count - IF(OLD.answer_value IS NOT NULL AND TRIM(OLD.answer_value) <> '', 1, 0) + IF(NEW.answer_value IS NOT NULL AND TRIM(NEW.answer_value) <> '', 1, 0))
    WHERE session_id = NEW.session_id;
END$$

CREATE TRIGGER trg_test_answers_rollup_ad AFTER DELETE ON test_answers FOR EACH ROW
BEGIN
    UPDATE test_session_rollups SET answers_count = GREATEST(0, answers_count - 1), answered_count = GREATEST(0, answered_count - IF(OLD.answer_value IS NOT NULL AND TRIM(OLD.answer_value) <> '', 1, 0)) WHERE session_id = OLD.session_id;
END$$

CREATE TRIGGER trg_test_activity_rollup_ai AFTER INSERT ON test_activity_events FOR EACH ROW
BEGIN
    INSERT INTO test_session_rollups (session_id, process_id, instrument_id, user_id, activity_events_total, activity_attention_total, activity_risk_total, last_activity_event_at)
    SELECT id, process_id, instrument_id, user_id, 1, IF(NEW.event_type IN ('tab_hidden','window_blurred','inactive_detected','fullscreen_exited'), 1, 0), IF(NEW.event_type IN ('fullscreen_denied','fullscreen_failed','fullscreen_unavailable','suspicious_key_printscreen','suspicious_key_print','suspicious_key_save','suspicious_key_copy','suspicious_key_devtools','context_menu_blocked','copy_blocked','cut_blocked','paste_blocked','drag_blocked','print_blocked'), 1, 0), NEW.created_at
    FROM test_sessions WHERE id = NEW.session_id
    ON DUPLICATE KEY UPDATE activity_events_total = activity_events_total + 1, activity_attention_total = activity_attention_total + IF(NEW.event_type IN ('tab_hidden','window_blurred','inactive_detected','fullscreen_exited'), 1, 0), activity_risk_total = activity_risk_total + IF(NEW.event_type IN ('fullscreen_denied','fullscreen_failed','fullscreen_unavailable','suspicious_key_printscreen','suspicious_key_print','suspicious_key_save','suspicious_key_copy','suspicious_key_devtools','context_menu_blocked','copy_blocked','cut_blocked','paste_blocked','drag_blocked','print_blocked'), 1, 0), last_activity_event_at = GREATEST(COALESCE(last_activity_event_at, NEW.created_at), NEW.created_at);
END$$
DELIMITER ;
