-- Migration 003: Add indexes for performance + security fixes
-- Date: 2026-02-26
-- Idempotent: safe to run multiple times

-- ============================================
-- Add missing columns to lavoratori FIRST (before indexes that depend on them)
-- ============================================

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND COLUMN_NAME = 'archiviato');
SET @sql = IF(@col = 0, 'ALTER TABLE lavoratori ADD COLUMN archiviato TINYINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND COLUMN_NAME = 'sede_id');
SET @sql = IF(@col = 0, 'ALTER TABLE lavoratori ADD COLUMN sede_id INT DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Indexes on lavoratori (most queried table)
-- ============================================

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND INDEX_NAME = 'idx_lav_azienda');
SET @sql = IF(@idx = 0, 'ALTER TABLE lavoratori ADD INDEX idx_lav_azienda (azienda_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND INDEX_NAME = 'idx_lav_sede');
SET @sql = IF(@idx = 0, 'ALTER TABLE lavoratori ADD INDEX idx_lav_sede (sede_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND COLUMN_NAME = 'unita_operativa_id');
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND INDEX_NAME = 'idx_lav_unita');
SET @sql = IF(@col > 0 AND @idx = 0, 'ALTER TABLE lavoratori ADD INDEX idx_lav_unita (unita_operativa_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND INDEX_NAME = 'idx_lav_archiviato');
SET @sql = IF(@idx = 0, 'ALTER TABLE lavoratori ADD INDEX idx_lav_archiviato (archiviato)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND INDEX_NAME = 'idx_lav_iscritto');
SET @sql = IF(@idx = 0, 'ALTER TABLE lavoratori ADD INDEX idx_lav_iscritto (iscritto)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND INDEX_NAME = 'idx_lav_cognome_nome');
SET @sql = IF(@idx = 0, 'ALTER TABLE lavoratori ADD INDEX idx_lav_cognome_nome (cognome, nome)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND INDEX_NAME = 'idx_lav_settore');
SET @sql = IF(@idx = 0, 'ALTER TABLE lavoratori ADD INDEX idx_lav_settore (settore)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND COLUMN_NAME = 'data_iscrizione');
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND INDEX_NAME = 'idx_lav_data_iscrizione');
SET @sql = IF(@col > 0 AND @idx = 0, 'ALTER TABLE lavoratori ADD INDEX idx_lav_data_iscrizione (data_iscrizione)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND INDEX_NAME = 'idx_lav_arch_sede');
SET @sql = IF(@idx = 0, 'ALTER TABLE lavoratori ADD INDEX idx_lav_arch_sede (archiviato, sede_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND INDEX_NAME = 'idx_lav_arch_azienda');
SET @sql = IF(@idx = 0, 'ALTER TABLE lavoratori ADD INDEX idx_lav_arch_azienda (archiviato, azienda_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Indexes on iscrizioni
-- ============================================

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iscrizioni' AND INDEX_NAME = 'idx_isc_lavoratore');
SET @sql = IF(@idx = 0, 'ALTER TABLE iscrizioni ADD INDEX idx_isc_lavoratore (lavoratore_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iscrizioni' AND INDEX_NAME = 'idx_isc_lav_fine');
SET @sql = IF(@idx = 0, 'ALTER TABLE iscrizioni ADD INDEX idx_isc_lav_fine (lavoratore_id, data_fine)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Indexes on documenti
-- ============================================

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documenti_lavoratori' AND INDEX_NAME = 'idx_doc_lav');
SET @sql = IF(@idx = 0, 'ALTER TABLE documenti_lavoratori ADD INDEX idx_doc_lav (lavoratore_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documenti_aziende' AND INDEX_NAME = 'idx_doc_az');
SET @sql = IF(@idx = 0, 'ALTER TABLE documenti_aziende ADD INDEX idx_doc_az (azienda_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Indexes on storico_aziende_lavoratori
-- ============================================

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'storico_aziende_lavoratori' AND INDEX_NAME = 'idx_storico_lav');
SET @sql = IF(@idx = 0, 'ALTER TABLE storico_aziende_lavoratori ADD INDEX idx_storico_lav (lavoratore_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'storico_aziende_lavoratori' AND INDEX_NAME = 'idx_storico_az');
SET @sql = IF(@idx = 0, 'ALTER TABLE storico_aziende_lavoratori ADD INDEX idx_storico_az (azienda_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Indexes on pagamenti_quote
-- ============================================

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagamenti_quote' AND INDEX_NAME = 'idx_pag_lav');
SET @sql = IF(@idx = 0, 'ALTER TABLE pagamenti_quote ADD INDEX idx_pag_lav (lavoratore_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Indexes on locks
-- ============================================

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'locks' AND INDEX_NAME = 'idx_lock_table_record');
SET @sql = IF(@idx = 0, 'ALTER TABLE locks ADD INDEX idx_lock_table_record (table_name, record_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Fix tipo_tessera ENUM to include 'sepa'
-- Map legacy values before modifying ENUM
-- ============================================

SET @has_tipo_tessera = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'lavoratori'
    AND COLUMN_NAME = 'tipo_tessera'
);

SET @sql = IF(@has_tipo_tessera = 1,
  'UPDATE lavoratori SET tipo_tessera = CASE tipo_tessera WHEN ''tipo1'' THEN ''trattenuta in busta paga'' WHEN ''tipo2'' THEN ''rinnovo annuale'' WHEN ''tipo3'' THEN ''sepa'' ELSE tipo_tessera END WHERE tipo_tessera IN (''tipo1'',''tipo2'',''tipo3'')',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @enum = (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lavoratori' AND COLUMN_NAME = 'tipo_tessera');
SET @sql = IF(@has_tipo_tessera = 1 AND @enum NOT LIKE '%sepa%', 'ALTER TABLE lavoratori MODIFY COLUMN tipo_tessera ENUM(''trattenuta in busta paga'', ''rinnovo annuale'', ''sepa'') DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
