-- Índices para acotar primero los eventos del reporte de incidencias por fecha
-- y conservar el acceso por sesión existente para las comprobaciones EXISTS.
ALTER TABLE e_talent_tests.test_activity_events
    ADD INDEX idx_test_activity_created_session (created_at, session_id);

ALTER TABLE e_talent_tests.test_audio_visual_risk_events
    ADD INDEX idx_test_av_risk_created_session (created_at, session_id);
