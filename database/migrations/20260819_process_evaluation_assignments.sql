CREATE TABLE IF NOT EXISTS e_talent_tests.test_process_evaluation_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    process_id INT UNSIGNED NOT NULL,
    form_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('assigned','cancelled') NOT NULL DEFAULT 'assigned',
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_process_evaluation_assignment (process_id, form_id, user_id),
    KEY idx_process_evaluation_assignment_user (user_id, status),
    KEY idx_process_evaluation_assignment_process (process_id, status),
    CONSTRAINT fk_process_evaluation_assignment_process FOREIGN KEY (process_id) REFERENCES e_talent_tests.test_processes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
