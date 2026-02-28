-- schema.sql

-- Creazione delle tabelle

CREATE TABLE IF NOT EXISTS aziende (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_azienda VARCHAR(100) NOT NULL,
    partita_iva VARCHAR(11),
    indirizzo_via VARCHAR(255),
    indirizzo_numero_civico VARCHAR(50),
    indirizzo_cap VARCHAR(5),
    indirizzo_citta VARCHAR(255),
    indirizzo_provincia VARCHAR(2),
    telefono VARCHAR(20),
    email VARCHAR(255),
    settore VARCHAR(255),
    note TEXT
);

CREATE TABLE IF NOT EXISTS calendario_lavoratori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lavoratore_id INT,
    azienda_id INT,
    is_company_event TINYINT NOT NULL DEFAULT 0,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT,
    start DATETIME NOT NULL,
    end DATETIME,
    all_day TINYINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS documenti_aziende (
    id INT AUTO_INCREMENT PRIMARY KEY,
    azienda_id INT NOT NULL,
    descrizione_documento VARCHAR(255),
    percorso_documento VARCHAR(255),
    data_caricamento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
);

CREATE TABLE IF NOT EXISTS documenti_lavoratori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lavoratore_id INT NOT NULL,
    descrizione_documento VARCHAR(255),
    percorso_documento VARCHAR(255),
    data_caricamento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
);

CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
);

CREATE TABLE IF NOT EXISTS event_exceptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    lavoratore_id INT NOT NULL
);

CREATE TABLE IF NOT EXISTS iscrizioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lavoratore_id INT NOT NULL,
    numero_tessera VARCHAR(50),
    metodo_pagamento ENUM('trattenuta in busta paga','rinnovo annuale','sepa') NOT NULL,
    nota_pagamento TEXT,
    data_inizio DATE NOT NULL,
    data_fine DATE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
);

CREATE TABLE IF NOT EXISTS lavoratori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cognome VARCHAR(255),
    nome VARCHAR(255),
    codice_fiscale VARCHAR(16),
    data_nascita DATE,
    indirizzo_via VARCHAR(100),
    indirizzo_numero_civico VARCHAR(10),
    indirizzo_cap VARCHAR(5),
    indirizzo_citta VARCHAR(100),
    indirizzo_provincia VARCHAR(2),
    telefono VARCHAR(20),
    email VARCHAR(100),
    nazionalita VARCHAR(50),
    foto VARCHAR(255),
    azienda_id INT,
    ruolo VARCHAR(100),
    contratto ENUM('tempo_determinato', 'tempo_indeterminato', 'apprendistato'),
    data_assunzione DATE,
    data_fine_contratto DATE,
    ore_settimanali INT,
    ral DECIMAL(10,2),
    note TEXT,
    iscritto TINYINT,
    settore ENUM('privato', 'pubblico'),
    genere VARCHAR(10),
    data_iscrizione DATE,
    paese_nascita VARCHAR(100),
    ccnl VARCHAR(100),
    tipo_tessera ENUM('trattenuta in busta paga', 'rinnovo annuale', 'sepa'),
    vertenze TINYINT DEFAULT 0,
    orario_contratto VARCHAR(20) NOT NULL,
    unita_operativa_id INT,
    archiviato TINYINT NOT NULL DEFAULT 0,
    sede_id INT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    user_id INT NOT NULL,
    locked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
);

CREATE TABLE IF NOT EXISTS pagamenti_quote (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lavoratore_id INT NOT NULL,
    anno INT NOT NULL,
    importo_pagato DECIMAL(10,2) NOT NULL,
    data_pagamento DATE NOT NULL
);

CREATE TABLE IF NOT EXISTS sedi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    indirizzo VARCHAR(255),
    citta VARCHAR(100),
    provincia VARCHAR(2),
    cap VARCHAR(5),
    telefono VARCHAR(20),
    email VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
);

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS smtp_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host VARCHAR(255) NOT NULL,
    port INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    encryption ENUM('ssl', 'tls', 'none') NOT NULL,
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
);

CREATE TABLE IF NOT EXISTS storico_aziende_lavoratori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lavoratore_id INT NOT NULL,
    azienda_id INT NOT NULL,
    data_inizio DATE NOT NULL,
    data_fine DATE
);

CREATE TABLE IF NOT EXISTS unita_operativa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    azienda_id INT NOT NULL,
    nome_unita_operativa VARCHAR(255) NOT NULL,
    descrizione TEXT
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'operatore') NOT NULL DEFAULT 'operatore',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
);
