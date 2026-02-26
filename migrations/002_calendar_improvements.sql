-- Migration 002: Calendar system improvements
-- Adds location field, audit timestamps, indexes, and FK constraints

-- Add location column
ALTER TABLE calendario_lavoratori
    ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER descrizione;

-- Add audit timestamps
ALTER TABLE calendario_lavoratori
    ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER all_day,
    ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add indexes for performance
ALTER TABLE calendario_lavoratori
    ADD INDEX idx_cal_lavoratore (lavoratore_id),
    ADD INDEX idx_cal_azienda (azienda_id),
    ADD INDEX idx_cal_start (start),
    ADD INDEX idx_cal_start_date (start, all_day);

-- Add index on event_exceptions
ALTER TABLE event_exceptions
    ADD INDEX idx_exc_event (event_id),
    ADD INDEX idx_exc_lavoratore (lavoratore_id);

-- Add foreign key constraints (ignore errors if FK already exist)
ALTER TABLE calendario_lavoratori
    ADD CONSTRAINT fk_cal_lavoratore FOREIGN KEY (lavoratore_id) REFERENCES lavoratori(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cal_azienda FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE SET NULL;

ALTER TABLE event_exceptions
    ADD CONSTRAINT fk_exc_event FOREIGN KEY (event_id) REFERENCES calendario_lavoratori(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_exc_lavoratore FOREIGN KEY (lavoratore_id) REFERENCES lavoratori(id) ON DELETE CASCADE;
