ALTER TABLE report_generation_batch_items
    MODIFY status ENUM('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
    ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN locked_at TIMESTAMP NULL AFTER attempts,
    ADD COLUMN started_at TIMESTAMP NULL AFTER locked_at,
    ADD COLUMN storage_key VARCHAR(255) NULL AFTER execution_id,
    ADD COLUMN mime_type VARCHAR(120) NULL AFTER storage_key,
    ADD COLUMN file_size BIGINT UNSIGNED NULL AFTER mime_type;

ALTER TABLE report_generation_batches
    ADD COLUMN storage_key VARCHAR(255) NULL AFTER failed_items,
    ADD COLUMN zip_filename VARCHAR(180) NULL AFTER storage_key,
    ADD COLUMN download_token CHAR(64) NULL AFTER zip_filename,
    ADD COLUMN expires_at TIMESTAMP NULL AFTER download_token;

CREATE UNIQUE INDEX uq_report_batch_download_token ON report_generation_batches (download_token);
CREATE INDEX idx_report_batch_items_queue ON report_generation_batch_items (status, locked_at, id);
