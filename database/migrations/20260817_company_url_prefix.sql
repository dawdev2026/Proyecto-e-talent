SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = 'e_talent_core'
       AND table_name = 'companies'
       AND column_name = 'url_prefix') = 0,
    'ALTER TABLE e_talent_core.companies ADD COLUMN url_prefix VARCHAR(80) NULL AFTER tax_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE e_talent_core.companies
SET url_prefix = CONCAT('empresa-', id)
WHERE url_prefix IS NULL OR TRIM(url_prefix) = '';

ALTER TABLE e_talent_core.companies
    MODIFY COLUMN url_prefix VARCHAR(80) NOT NULL;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = 'e_talent_core'
       AND table_name = 'companies'
       AND index_name = 'uq_companies_url_prefix') = 0,
    'ALTER TABLE e_talent_core.companies ADD UNIQUE KEY uq_companies_url_prefix (url_prefix)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
