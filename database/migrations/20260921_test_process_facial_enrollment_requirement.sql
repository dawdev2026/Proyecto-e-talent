-- Habilita a cada proceso para exigir enrolamiento facial.
-- Los procesos existentes quedan sin el requisito por el DEFAULT 0.
USE `e_talent_tests`;

ALTER TABLE `test_processes`
    ADD COLUMN `require_facial_enrollment` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `admin_assignment_mode`;
