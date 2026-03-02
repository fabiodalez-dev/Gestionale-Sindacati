<?php
// Installer legacy - usa config.php per le credenziali DB
require_once 'config.php';

// Inizio transazione
$mysqli->begin_transaction();

// Funzione per eseguire query SQL in modo sicuro e mostrare errori in caso di fallimento
function executeQuery($query) {
    global $mysqli;
    if ($mysqli->query($query) === TRUE) {
        // Query eseguita con successo
    } else {
        error_log("Errore nella query di installazione: " . $mysqli->error);
        echo "Errore durante l'installazione. Controlla i log per dettagli.<br>";
        $mysqli->rollback();
        die("Installazione interrotta a causa di un errore.");
    }
}

// Controllo se l'applicazione è già installata (verifica se esiste la tabella users)
$result = $mysqli->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    die("L'applicazione è già installata.");
}

// Creazione della tabella utenti (users)
$query = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'operator', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
executeQuery($query);

// Creazione della tabella aziende (aziende)
$query = "
CREATE TABLE IF NOT EXISTS aziende (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_azienda VARCHAR(100) NOT NULL,
    partita_iva VARCHAR(11) NOT NULL UNIQUE,
    indirizzo_via VARCHAR(100) NOT NULL,
    indirizzo_numero_civico VARCHAR(10) NOT NULL,
    indirizzo_cap VARCHAR(5) NOT NULL,
    indirizzo_citta VARCHAR(100) NOT NULL,
    indirizzo_provincia VARCHAR(2) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100),
    settore VARCHAR(100)
)";
executeQuery($query);

// Creazione della tabella lavoratori (lavoratori)
$query = "
CREATE TABLE IF NOT EXISTS lavoratori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    codice_fiscale VARCHAR(16) NOT NULL UNIQUE,
    data_nascita DATE NOT NULL,
    indirizzo_via VARCHAR(100) NOT NULL,
    indirizzo_numero_civico VARCHAR(10) NOT NULL,
    indirizzo_cap VARCHAR(5) NOT NULL,
    indirizzo_citta VARCHAR(100) NOT NULL,
    indirizzo_provincia VARCHAR(2) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100),
    nazionalita VARCHAR(50),
    foto VARCHAR(255),
    azienda_id INT,
    ruolo VARCHAR(100),
    contratto ENUM('indeterminato', 'determinato', 'progetto', 'apprendistato', 'part-time', 'full-time') DEFAULT 'indeterminato',
    data_assunzione DATE,
    data_fine_contratto DATE DEFAULT NULL,
    ore_settimanali INT DEFAULT 40,
    ral DECIMAL(10, 2),
    note TEXT,
    iscritto TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE SET NULL
)";
executeQuery($query);


// Creazione della tabella pagamenti quote (pagamenti_quote)
$query = "
CREATE TABLE IF NOT EXISTS pagamenti_quote (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lavoratore_id INT NOT NULL,
    anno INT NOT NULL,
    importo_pagato DECIMAL(10, 2) NOT NULL,
    data_pagamento DATE NOT NULL,
    FOREIGN KEY (lavoratore_id) REFERENCES lavoratori(id) ON DELETE CASCADE
)";
executeQuery($query);

// Creazione della tabella documenti lavoratori (documenti_lavoratori)
$query = "
CREATE TABLE IF NOT EXISTS documenti_lavoratori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lavoratore_id INT NOT NULL,
    descrizione_documento VARCHAR(255),
    percorso_documento VARCHAR(255),
    data_caricamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lavoratore_id) REFERENCES lavoratori(id) ON DELETE CASCADE
)";
executeQuery($query);

// Creazione della tabella storico aziende lavoratori (storico_aziende_lavoratori)
$query = "
CREATE TABLE IF NOT EXISTS storico_aziende_lavoratori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lavoratore_id INT NOT NULL,
    azienda_id INT NOT NULL,
    data_inizio DATE NOT NULL,
    data_fine DATE DEFAULT NULL,
    FOREIGN KEY (lavoratore_id) REFERENCES lavoratori(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
)";
executeQuery($query);

// Creazione della tabella documenti aziende (documenti_aziende)
$query = "
CREATE TABLE IF NOT EXISTS documenti_aziende (
    id INT AUTO_INCREMENT PRIMARY KEY,
    azienda_id INT NOT NULL,
    descrizione_documento VARCHAR(255),
    percorso_documento VARCHAR(255),
    data_caricamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
)";
executeQuery($query);

// Creazione della tabella impostazioni generali (settings)
$query = "
CREATE TABLE IF NOT EXISTS settings (
   id INT AUTO_INCREMENT PRIMARY KEY,
   setting_key VARCHAR(50) UNIQUE NOT NULL,
   setting_value TEXT NOT NULL
)";
executeQuery($query);

// Inserimento delle impostazioni di default nel sistema
$query = "
INSERT INTO settings (setting_key, setting_value)
VALUES 
('accepted_file_formats', 'jpg,jpeg,png,doc,xlsx,txt,pdf,zip,webm,xls'),
('date_format', 'd-m-Y')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
";
executeQuery($query);

// Aggiunta dell'utente admin predefinito
// La password DEVE essere fornita come variabile d'ambiente o parametro POST durante l'installazione
$admin_username_raw = $_POST['admin_username'] ?? ($_ENV['ADMIN_USERNAME'] ?? getenv('ADMIN_USERNAME') ?: '');
$admin_email_raw = $_POST['admin_email'] ?? ($_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: '');
$admin_password_plain = $_POST['admin_password'] ?? ($_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: '');

$admin_username = trim($admin_username_raw);
$admin_email = trim($admin_email_raw);

if (empty($admin_username) || empty($admin_email) || empty($admin_password_plain)) {
    $mysqli->rollback();
    die("Errore: username, email e password dell'admin sono obbligatori. Fornirli via POST o variabili d'ambiente (ADMIN_USERNAME, ADMIN_EMAIL, ADMIN_PASSWORD).");
}

if (strlen($admin_password_plain) < 8) {
    $mysqli->rollback();
    die("Errore: la password dell'admin deve essere di almeno 8 caratteri.");
}

if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
    $mysqli->rollback();
    die("Errore: l'email admin non è valida.");
}

$admin_password = password_hash($admin_password_plain, PASSWORD_DEFAULT);

// Verifica che l'utente admin non esista già
$stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $admin_username, $admin_email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
   $stmt = $mysqli->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
   $stmt->bind_param("sss", $admin_username, $admin_email, $admin_password);
   if ($stmt->execute()) {
       echo "Utente admin creato con successo.<br>";
   } else {
       error_log("Errore nella creazione dell'utente admin: " . $stmt->error);
       echo "Errore nella creazione dell'utente admin. Controlla i log per dettagli.<br>";
       $mysqli->rollback();
       die("Installazione interrotta a causa di un errore.");
   }
} else {
   echo "L'utente admin esiste già.<br>";
}

// Commit delle modifiche
$mysqli->commit();

// Chiusura della connessione
$mysqli->close();

// Fine installazione
echo "Installazione completata con successo!";
?>
