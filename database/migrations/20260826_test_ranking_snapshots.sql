CREATE TABLE IF NOT EXISTS test_ranking_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    process_id INT UNSIGNED NOT NULL,
    config_hash CHAR(64) NOT NULL,
    source_fingerprint CHAR(64) NOT NULL,
    rows_json LONGTEXT NOT NULL,
    warnings_json LONGTEXT NOT NULL,
    users_total INT UNSIGNED NOT NULL DEFAULT 0,
    sessions_total INT UNSIGNED NOT NULL DEFAULT 0,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_test_ranking_snapshot (process_id, config_hash),
    KEY idx_test_ranking_snapshot_fingerprint (process_id, source_fingerprint),
    CONSTRAINT fk_test_ranking_snapshot_process FOREIGN KEY (process_id) REFERENCES test_processes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
