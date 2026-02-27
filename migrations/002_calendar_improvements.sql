-- Migration 002: Calendar system improvements
-- Adds location field, audit timestamps, indexes, and FK constraints
-- Idempotent: safe to run multiple times

-- Add location column (if not exists)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendario_lavoratori' AND COLUMN_NAME = 'location');
SET @sql = IF(@col = 0, 'ALTER TABLE calendario_lavoratori ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER descrizione', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add created_at column (if not exists)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendario_lavoratori' AND COLUMN_NAME = 'created_at');
SET @sql = IF(@col = 0, 'ALTER TABLE calendario_lavoratori ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER all_day', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add updated_at column (if not exists)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendario_lavoratori' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col = 0, 'ALTER TABLE calendario_lavoratori ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index idx_cal_lavoratore (if not exists)
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendario_lavoratori' AND INDEX_NAME = 'idx_cal_lavoratore');
SET @sql = IF(@idx = 0, 'ALTER TABLE calendario_lavoratori ADD INDEX idx_cal_lavoratore (lavoratore_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index idx_cal_azienda (if not exists)
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendario_lavoratori' AND INDEX_NAME = 'idx_cal_azienda');
SET @sql = IF(@idx = 0, 'ALTER TABLE calendario_lavoratori ADD INDEX idx_cal_azienda (azienda_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index idx_cal_start (if not exists)
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendario_lavoratori' AND INDEX_NAME = 'idx_cal_start');
SET @sql = IF(@idx = 0, 'ALTER TABLE calendario_lavoratori ADD INDEX idx_cal_start (start)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index idx_cal_start_date (if not exists)
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendario_lavoratori' AND INDEX_NAME = 'idx_cal_start_date');
SET @sql = IF(@idx = 0, 'ALTER TABLE calendario_lavoratori ADD INDEX idx_cal_start_date (start, all_day)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index idx_exc_event on event_exceptions (if not exists)
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_exceptions' AND INDEX_NAME = 'idx_exc_event');
SET @sql = IF(@idx = 0, 'ALTER TABLE event_exceptions ADD INDEX idx_exc_event (event_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index idx_exc_lavoratore on event_exceptions (if not exists)
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_exceptions' AND INDEX_NAME = 'idx_exc_lavoratore');
SET @sql = IF(@idx = 0, 'ALTER TABLE event_exceptions ADD INDEX idx_exc_lavoratore (lavoratore_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add FK fk_cal_lavoratore (if not exists)
SET @fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendario_lavoratori' AND CONSTRAINT_NAME = 'fk_cal_lavoratore');
SET @sql = IF(@fk = 0, 'ALTER TABLE calendario_lavoratori ADD CONSTRAINT fk_cal_lavoratore FOREIGN KEY (lavoratore_id) REFERENCES lavoratori(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add FK fk_cal_azienda (if not exists)
SET @fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendario_lavoratori' AND CONSTRAINT_NAME = 'fk_cal_azienda');
SET @sql = IF(@fk = 0, 'ALTER TABLE calendario_lavoratori ADD CONSTRAINT fk_cal_azienda FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add FK fk_exc_event (if not exists)
SET @fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_exceptions' AND CONSTRAINT_NAME = 'fk_exc_event');
SET @sql = IF(@fk = 0, 'ALTER TABLE event_exceptions ADD CONSTRAINT fk_exc_event FOREIGN KEY (event_id) REFERENCES calendario_lavoratori(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add FK fk_exc_lavoratore (if not exists)
SET @fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_exceptions' AND CONSTRAINT_NAME = 'fk_exc_lavoratore');
SET @sql = IF(@fk = 0, 'ALTER TABLE event_exceptions ADD CONSTRAINT fk_exc_lavoratore FOREIGN KEY (lavoratore_id) REFERENCES lavoratori(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
