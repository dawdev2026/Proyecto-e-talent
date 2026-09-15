ALTER TABLE e_talent_tests.test_media_evidence
    MODIFY COLUMN status ENUM('pending','recording','uploading','processing','saved','partial','failed','not_supported','not_consented') NOT NULL DEFAULT 'pending',
    ADD COLUMN processing_status ENUM('not_queued','queued','processing','completed','failed') NOT NULL DEFAULT 'not_queued' AFTER status,
    ADD COLUMN processing_error VARCHAR(500) NULL AFTER failure_reason,
    ADD COLUMN processing_summary_json LONGTEXT NULL AFTER processing_error,
    ADD COLUMN processed_at DATETIME NULL AFTER processing_summary_json;

CREATE TABLE IF NOT EXISTS e_talent_tests.test_media_processing_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evidence_id BIGINT UNSIGNED NOT NULL,
    status ENUM('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_test_media_processing_evidence (evidence_id),
    KEY idx_test_media_processing_queue (status, attempts, locked_at, id),
    CONSTRAINT fk_test_media_processing_evidence FOREIGN KEY (evidence_id) REFERENCES e_talent_tests.test_media_evidence(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
