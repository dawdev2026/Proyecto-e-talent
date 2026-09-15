USE e_talent_tests;

CREATE TABLE IF NOT EXISTS company_test_instruments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    instrument_id INT UNSIGNED NOT NULL,
    duration_minutes INT UNSIGNED NULL,
    question_order_mode ENUM('ordered', 'random') NULL,
    use_blocks TINYINT(1) NULL,
    block_size INT UNSIGNED NULL,
    require_block_completion TINYINT(1) NULL,
    user_can_view_results TINYINT(1) NULL,
    show_question_numbers TINYINT(1) NULL,
    auto_start_enabled TINYINT(1) NULL,
    auto_start_order INT UNSIGNED NULL,
    control_mode ENUM('off', 'activity', 'supervised', 'supervised_audio_visual') NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_company_test_instrument (company_id, instrument_id),
    KEY idx_company_test_instrument_company (company_id),
    KEY idx_company_test_instrument_instrument (instrument_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
