CREATE DATABASE IF NOT EXISTS e_talent_evaluaciones_encuestas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE e_talent_evaluaciones_encuestas;

CREATE TABLE IF NOT EXISTS evaluation_survey_forms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_type ENUM('assessment','survey') NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    instructions TEXT NULL,
    status ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
    duration_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    control_mode ENUM('off','activity','supervised','supervised_audio_visual') NOT NULL DEFAULT 'off',
    audio_visual_upload_failure_policy ENUM('continue','retry_once','block') NOT NULL DEFAULT 'continue',
    audio_visual_interruption_policy ENUM('continue','pause','block') NOT NULL DEFAULT 'pause',
    audio_visual_voice_policy ENUM('log','warn','pause') NOT NULL DEFAULT 'warn',
    audio_visual_permission_policy ENUM('continue','pause','block') NOT NULL DEFAULT 'pause',
    audio_visual_quality_profile ENUM('economical','standard','high') NOT NULL DEFAULT 'standard',
    question_order_mode ENUM('ordered','random') NOT NULL DEFAULT 'ordered',
    question_display_limit INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 1,
    max_score DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    passing_score DECIMAL(10,2) NULL,
    show_result_to_user TINYINT(1) NOT NULL DEFAULT 1,
    result_display_mode ENUM('best_only','collapsible_attempts') NOT NULL DEFAULT 'best_only',
    show_correction_to_user TINYINT(1) NOT NULL DEFAULT 0,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_esf_type_status (form_type, status),
    KEY idx_esf_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_survey_questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    question_type VARCHAR(40) NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    points DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sort_order INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_esq_form_order (form_id, is_active, sort_order),
    CONSTRAINT fk_esq_form FOREIGN KEY (form_id) REFERENCES evaluation_survey_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_survey_question_options (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_id INT UNSIGNED NOT NULL,
    option_label VARCHAR(500) NOT NULL,
    option_value VARCHAR(500) NOT NULL,
    score_value DECIMAL(10,2) NULL,
    sort_order INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_esqo_question_order (question_id, is_active, sort_order),
    CONSTRAINT fk_esqo_question FOREIGN KEY (question_id) REFERENCES evaluation_survey_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_survey_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    control_mode ENUM('off','activity','supervised','supervised_audio_visual') NOT NULL DEFAULT 'off',
    audio_visual_upload_failure_policy ENUM('continue','retry_once','block') NOT NULL DEFAULT 'continue',
    audio_visual_interruption_policy ENUM('continue','pause','block') NOT NULL DEFAULT 'pause',
    audio_visual_voice_policy ENUM('log','warn','pause') NOT NULL DEFAULT 'warn',
    audio_visual_permission_policy ENUM('continue','pause','block') NOT NULL DEFAULT 'pause',
    audio_visual_quality_profile ENUM('economical','standard','high') NOT NULL DEFAULT 'standard',
    attempt_number INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('in_progress','completed','expired') NOT NULL DEFAULT 'in_progress',
    expires_at DATETIME NULL,
    completed_at DATETIME NULL,
    max_score DECIMAL(10,2) NULL,
    raw_score DECIMAL(10,2) NULL,
    final_score DECIMAL(10,2) NULL,
    passed TINYINT(1) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_esa_form_user_attempt (form_id, user_id, attempt_number),
    KEY idx_esa_form_status (form_id, status),
    KEY idx_esa_user_status (user_id, status),
    CONSTRAINT fk_esa_form FOREIGN KEY (form_id) REFERENCES evaluation_survey_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_survey_answers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    answer_value LONGTEXT NULL,
    score_value DECIMAL(10,2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_esa_answer (attempt_id, question_id),
    KEY idx_esans_question (question_id),
    CONSTRAINT fk_esans_attempt FOREIGN KEY (attempt_id) REFERENCES evaluation_survey_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_esans_question FOREIGN KEY (question_id) REFERENCES evaluation_survey_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_survey_settings (
    setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
