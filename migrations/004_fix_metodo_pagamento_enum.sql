-- Aggiorna ENUM metodo_pagamento per includere 'sepa'
-- Idempotent: safe to run multiple times

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'iscrizioni'
    AND COLUMN_NAME = 'metodo_pagamento'
);

SET @sepa_exists = IF(@col_exists = 0, 1, (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'iscrizioni'
    AND COLUMN_NAME = 'metodo_pagamento'
    AND COLUMN_TYPE LIKE '%sepa%'
));

SET @legacy_count = IF(@col_exists = 0, 0, (
    SELECT COUNT(*) FROM iscrizioni
    WHERE metodo_pagamento IS NOT NULL
    AND metodo_pagamento NOT IN ('trattenuta in busta paga','rinnovo annuale','sepa')
));

SET @sql = IF(@col_exists = 1 AND @sepa_exists = 0 AND @legacy_count = 0,
    'ALTER TABLE iscrizioni MODIFY metodo_pagamento ENUM(''trattenuta in busta paga'',''rinnovo annuale'',''sepa'') NOT NULL',
    IF(@legacy_count > 0,
       'SELECT ''Legacy metodo_pagamento values found: migration skipped'' AS info',
       'SELECT ''ENUM already contains sepa or column missing'' AS info')
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
