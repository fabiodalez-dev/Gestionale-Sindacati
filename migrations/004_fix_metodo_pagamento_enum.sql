-- Aggiorna ENUM metodo_pagamento per includere 'sepa'
-- Idempotent: safe to run multiple times

SET @sepa_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'iscrizioni'
    AND COLUMN_NAME = 'metodo_pagamento'
    AND COLUMN_TYPE LIKE '%sepa%'
);

SET @sql = IF(@sepa_exists = 0,
    'ALTER TABLE iscrizioni MODIFY metodo_pagamento ENUM(''trattenuta in busta paga'',''rinnovo annuale'',''sepa'') NOT NULL',
    'SELECT ''ENUM already contains sepa'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
