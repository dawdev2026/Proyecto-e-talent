USE e_talent_tests;

CREATE TABLE IF NOT EXISTS test_ranking_company_assignments (
    company_id INT UNSIGNED NOT NULL,
    preset_id BIGINT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (company_id),
    KEY idx_trca_preset (preset_id),
    KEY idx_trca_active_company (is_active, company_id),
    CONSTRAINT fk_trca_preset FOREIGN KEY (preset_id)
        REFERENCES test_ranking_presets (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
