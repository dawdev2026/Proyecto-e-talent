-- Configuracion SMTP cifrada por empresa.
-- Revisar y respaldar antes de ejecutar en Docker.

CREATE TABLE IF NOT EXISTS e_talent_core.company_mail_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    host VARCHAR(255) NOT NULL DEFAULT '',
    port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    encryption VARCHAR(16) NOT NULL DEFAULT 'starttls',
    smtp_auth TINYINT(1) NOT NULL DEFAULT 1,
    auth_type VARCHAR(16) NULL,
    username VARCHAR(255) NOT NULL DEFAULT '',
    password_ciphertext TEXT NULL,
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    from_name VARCHAR(160) NOT NULL DEFAULT 'e-talent',
    reply_to_email VARCHAR(255) NULL,
    reply_to_name VARCHAR(160) NULL,
    timeout SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_company_mail_settings_company (company_id),
    CONSTRAINT fk_company_mail_settings_company
        FOREIGN KEY (company_id) REFERENCES e_talent_core.companies(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
