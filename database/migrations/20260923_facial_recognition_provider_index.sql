-- Alinea el índice del proveedor facial con el esquema canónico.
-- Puede omitirse de forma segura si el índice ya existe con la misma definición.
USE `e_talent_core`;

ALTER TABLE `facial_recognition_enrollments`
    ADD KEY `idx_facial_enrollment_provider` (`provider`);
