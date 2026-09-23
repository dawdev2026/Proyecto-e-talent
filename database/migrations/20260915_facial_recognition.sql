USE `e_talent_core`;

CREATE TABLE IF NOT EXISTS facial_recognition_enrollments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'facex',
    face_embedding MEDIUMTEXT NULL,
    model_version VARCHAR(80) NOT NULL DEFAULT 'facex-wasm-1.0',
    status ENUM('active', 'revoked', 'failed') NOT NULL DEFAULT 'active',
    consent_version VARCHAR(40) NOT NULL,
    consented_at DATETIME NOT NULL,
    enrolled_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_facial_enrollment_user_company (company_id, user_id),
    KEY idx_facial_enrollment_provider (provider),
    KEY idx_facial_enrollment_status (status),
    CONSTRAINT fk_facial_enrollment_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_facial_enrollment_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_facial_enrollment_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS facial_recognition_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enrollment_id INT UNSIGNED NULL,
    company_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    context VARCHAR(80) NOT NULL DEFAULT 'identity_validation',
    result ENUM('verified', 'not_verified', 'review', 'unavailable', 'error') NOT NULL,
    similarity DECIMAL(7,5) NULL,
    liveness DECIMAL(7,5) NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'facex',
    reason VARCHAR(120) NULL,
    request_id VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_facial_attempt_company_date (company_id, created_at),
    KEY idx_facial_attempt_user_date (user_id, created_at),
    CONSTRAINT fk_facial_attempt_enrollment FOREIGN KEY (enrollment_id) REFERENCES facial_recognition_enrollments (id) ON DELETE SET NULL,
    CONSTRAINT fk_facial_attempt_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL,
    CONSTRAINT fk_facial_attempt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
