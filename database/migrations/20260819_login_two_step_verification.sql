-- Verificación de dos pasos para el acceso al sistema.
-- Revisar y respaldar antes de ejecutar en Docker.

CREATE TABLE IF NOT EXISTS e_talent_core.login_verification_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    code_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_verification_user_latest (user_id, id),
    KEY idx_login_verification_expiry (expires_at),
    CONSTRAINT fk_login_verification_user
        FOREIGN KEY (user_id) REFERENCES e_talent_core.users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
