ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_media_access_audit
    MODIFY COLUMN action ENUM('result_viewed','video_viewed','video_downloaded','screen_capture_viewed') NOT NULL;
ALTER TABLE e_talent_tests.test_media_access_audit
    MODIFY COLUMN action ENUM('result_viewed','video_viewed','video_downloaded','screen_capture_viewed') NOT NULL;
