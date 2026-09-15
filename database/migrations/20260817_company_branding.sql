-- Branding por empresa. Preparar, revisar y respaldar antes de ejecutar.
-- No modifica platform_settings: el branding global permanece como fallback.

CREATE TABLE IF NOT EXISTS e_talent_core.company_branding (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_company_branding_setting (company_id, setting_key),
    KEY idx_company_branding_company (company_id),
    CONSTRAINT fk_company_branding_company
        FOREIGN KEY (company_id) REFERENCES e_talent_core.companies(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
