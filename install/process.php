<?php
// install/process.php
session_start();

// Controlla se l'applicazione è già stata installata
if (file_exists('../config.php')) {
    die('L\'applicazione è già stata installata. Se desideri reinstallarla, elimina il file <code>config.php</code> e le tabelle del database.');
}

// Funzione per mostrare errori e reindirizzare indietro
function show_error($message) {
    $_SESSION['error'] = $message;
    header('Location: index.php');
    exit;
}

// Funzione per mostrare successi e reindirizzare
function show_success($message) {
    $_SESSION['success'] = $message;
    header('Location: index.php');
    exit;
}

// Verifica che il modulo sia stato inviato
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    show_error('Richiesta non valida.');
}

// Raccogli i dati del modulo con validazione tipo
foreach (['db_host', 'db_name', 'db_user', 'db_pass', 'admin_username', 'admin_email', 'admin_password', 'base_url', 'accepted_file_formats'] as $_field) {
    if (isset($_POST[$_field]) && !is_string($_POST[$_field])) {
        show_error("Il campo $_field deve essere una stringa.");
    }
}
unset($_field);

$db_host = trim($_POST['db_host'] ?? '');
$db_name = trim($_POST['db_name'] ?? '');
$db_user = trim($_POST['db_user'] ?? '');
$db_pass = trim($_POST['db_pass'] ?? '');

$admin_username = trim($_POST['admin_username'] ?? '');
$admin_email = trim($_POST['admin_email'] ?? '');
$admin_password = trim($_POST['admin_password'] ?? '');

$base_url = trim($_POST['base_url'] ?? '');
$accepted_file_formats = trim($_POST['accepted_file_formats'] ?? '');

// Validazione dei dati
if (empty($db_host) || empty($db_name) || empty($db_user) || empty($admin_username) || empty($admin_email) || empty($admin_password) || empty($base_url) || empty($accepted_file_formats)) {
    show_error('Tutti i campi contrassegnati sono obbligatori.');
}

// Validazione dell'email admin
if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
    show_error('L\'email dell\'amministratore non è valida.');
}

// Validazione del base_url
if (!filter_var($base_url, FILTER_VALIDATE_URL)) {
    show_error('La Base URL non è valida.');
}

// Gestione del caricamento del logo
if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    show_error('Errore nel caricamento del logo.');
}

$allowed_image_types = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($_FILES['logo']['type'], $allowed_image_types)) {
    show_error('Il logo deve essere un\'immagine (jpg, jpeg, png, gif).');
}

// Carica il logo nella cartella uploads
$uploads_dir = '../uploads/';
if (!is_dir($uploads_dir)) {
    if (!mkdir($uploads_dir, 0755, true)) {
        show_error('Impossibile creare la cartella uploads.');
    }
}

// Verifica i permessi della cartella uploads
if (!is_writable($uploads_dir)) {
    show_error('La cartella uploads non ha i permessi di scrittura.');
}

$logo_filename = 'logo_' . time() . '_' . basename($_FILES['logo']['name']);
$logo_path = $uploads_dir . $logo_filename;

if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
    show_error('Errore nel salvataggio del logo.');
}

// Connessione al database
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    show_error('Connessione al database fallita: ' . $mysqli->connect_error);
}

// Imposta la codifica dei caratteri
if (!$mysqli->set_charset("utf8mb4")) {
    show_error("Errore durante il caricamento del set di caratteri utf8mb4: " . $mysqli->error);
}

// Esecuzione dello script SQL per creare le tabelle
$sql_file = 'sql/schema.sql';
if (!file_exists($sql_file)) {
    show_error('Il file schema.sql non esiste.');
}

$sql_commands = file_get_contents($sql_file);
if ($sql_commands === false) {
    show_error('Impossibile leggere il file schema.sql.');
}

// Suddivide i comandi SQL per eseguire uno alla volta
$commands = array_filter(array_map('trim', explode(';', $sql_commands)));
foreach ($commands as $command) {
    if (!empty($command)) {
        if (!$mysqli->query($command)) {
            show_error('Errore nell\'esecuzione del comando SQL: ' . $mysqli->error);
        }
    }
}

// Creazione dell'utente admin
$hashed_password = password_hash($admin_password, PASSWORD_BCRYPT);

$stmt = $mysqli->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
if (!$stmt) {
    show_error('Errore nella preparazione della query per l\'utente admin: ' . $mysqli->error);
}

$stmt->bind_param('sss', $admin_username, $admin_email, $hashed_password);

if (!$stmt->execute()) {
    show_error('Errore nell\'inserimento dell\'utente admin: ' . $stmt->error);
}

$stmt->close();

// Salvataggio delle impostazioni di base
$accepted_formats_array = array_map('trim', explode(',', $accepted_file_formats));
$accepted_formats_string = implode(',', $accepted_formats_array);

// Inserimento delle impostazioni nel database
$settings = [
    'accepted_file_formats' => $accepted_formats_string,
    'base_url' => rtrim($base_url, '/') . '/',
    'logo' => 'uploads/' . $logo_filename
];

// Inserisce le impostazioni
$stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
if (!$stmt) {
    show_error('Errore nella preparazione della query per le impostazioni: ' . $mysqli->error);
}

foreach ($settings as $key => $value) {
    $stmt->bind_param('ss', $key, $value);
    if (!$stmt->execute()) {
        show_error('Errore nell\'inserimento dell\'impostazione ' . $key . ': ' . $stmt->error);
    }
}

$stmt->close();

// Creazione del file config.php
$config_content = "<?php
// config.php

/**
 * Funzione per sanitizzare output destinati a HTML
 *
 * @param mixed \$data L'input da sanitizzare
 * @return string L'input sanitizzato
 */
function sanitizeForHTML(\$data) {
    return htmlspecialchars(\$data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Funzione per generare un token CSRF
 *
 * @return string Il token CSRF generato
 */
function generateCsrfToken() {
    if (empty(\$_SESSION['csrf_token'])) {
        \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return \$_SESSION['csrf_token'];
}

/**
 * Funzione per includere il token CSRF nei form
 */
function csrfInputField() {
    \$token = generateCsrfToken();
    echo '<input type=\"hidden\" name=\"csrf_token\" value=\"' . sanitizeForHTML(\$token) . '\">';
}

// Funzioni aggiuntive e configurazioni
require_once 'user_functions.php'; // Assicurati che le funzioni siano incluse qui

/**
 * Funzione per eseguire query SQL in modo sicuro (prepared statements)
 *
 * @param string \$query La query SQL con placeholder
 * @param array \$params I parametri da legare alla query
 * @param string \$types I tipi dei parametri (es. 's', 'i', 'd', 'b')
 * @return mysqli_stmt|false La dichiarazione preparata o false in caso di errore
 */
function executeQuery(\$query, \$params = [], \$types = '') {
    global \$mysqli;

    // Prepara la query
    \$stmt = \$mysqli->prepare(\$query);
    if (\$stmt === false) {
        error_log(\"Errore nella preparazione della query: \" . \$mysqli->error);
        return false;
    }

    // Lega i parametri se presenti
    if (!empty(\$params) && !empty(\$types)) {
        // Assicurati che il numero di tipi corrisponda al numero di parametri
        if (strlen(\$types) !== count(\$params)) {
            error_log(\"Il numero di tipi non corrisponde al numero di parametri.\");
            return false;
        }

        // Gestione dei valori NULL
        \$refs = [];
        \$new_types = '';
        for (\$i = 0; \$i < count(\$params); \$i++) {
            if (is_null(\$params[\$i])) {
                // Usa 's' come tipo per i valori NULL
                \$new_types .= 's';
            } else {
                \$new_types .= \$types[\$i];
            }
            \$refs[\$i] = &\$params[\$i];
        }

        // Bind parameters
        array_unshift(\$refs, \$new_types);
        call_user_func_array([\$stmt, 'bind_param'], \$refs);
    }

    // Esegui la query
    if (!\$stmt->execute()) {
        error_log(\"Errore nell'esecuzione della query: \" . \$stmt->error);
        return false;
    }

    return \$stmt;
}

// Altre funzioni e configurazioni...

// Definizione del percorso base
\$base_url = '" . rtrim($base_url, '/') . "/';

// Assicurati che il percorso termini con una slash
if (substr(\$base_url, -1) !== '/') {
    \$base_url .= '/';
}

// Rimozione di eventuali backslash su Windows
\$base_url = str_replace('\\\\', '/', \$base_url);

// Imposta il fuso orario (opzionale)
date_default_timezone_set('Europe/Rome');

// Gestione della durata della sessione basata sul cookie \"remember_me\"
\$remember_me = isset(\$_COOKIE['remember_me']) && \$_COOKIE['remember_me'] === '1';

// Impostazione dei parametri del cookie di sessione prima di avviare la sessione
if (\$remember_me) {
    // Sessione che dura 30 giorni
    \$cookie_lifetime = 30 * 24 * 60 * 60; // 30 giorni in secondi
} else {
    // Sessione che dura 30 minuti
    \$cookie_lifetime = 30 * 60; // 30 minuti in secondi
}

// Configurazione della Garbage Collection di PHP
ini_set('session.gc_maxlifetime', \$cookie_lifetime);
ini_set('session.cookie_lifetime', \$cookie_lifetime);

// Imposta i parametri del cookie di sessione
session_set_cookie_params([
    'lifetime' => \$cookie_lifetime,
    'path' => '/',
    'domain' => '', // Imposta il tuo dominio se necessario
    'secure' => isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on', // Usa solo HTTPS se disponibile
    'httponly' => true,
    'samesite' => 'Lax' // Può essere 'Strict', 'Lax' o 'None'
]);

// Avvio della sessione dopo aver impostato i parametri
session_start();

// Verifica la connessione al database
\$host = '$db_host';
\$db = '$db_name';               // Nome del database
\$user = '$db_user';             // Nome utente del database
\$pass = '$db_pass';             // Password del database

\$mysqli = new mysqli(\$host, \$user, \$pass, \$db);
mysqli_set_charset(\$mysqli, \"utf8mb4\");

// Verifica la connessione
if (\$mysqli->connect_error) {
    die(\"Connessione fallita: \" . \$mysqli->connect_error);
}

// Imposta la codifica dei caratteri per la connessione al database
if (!\$mysqli->set_charset(\"utf8mb4\")) {
    printf(\"Errore durante il caricamento del set di caratteri utf8mb4: %s\\n\", \$mysqli->error);
    exit();
}

/**
 * Funzione per ottenere un'impostazione dal database
 *
 * @param string \$key La chiave dell'impostazione
 * @return string|null Il valore dell'impostazione o null se non trovato
 */
function getSetting(\$key) {
    \$stmt = executeQuery(\"SELECT setting_value FROM settings WHERE setting_key = ?\", [\$key], 's');
    if (\$stmt === false) {
        return null;
    }
    \$result = \$stmt->get_result();
    if (\$result->num_rows > 0) {
        return \$result->fetch_assoc()['setting_value'];
    }
    return null;
}

/**
 * Funzione per impostare un valore di configurazione nel database
 *
 * @param string \$key La chiave dell'impostazione
 * @param string \$value Il valore dell'impostazione
 * @return bool True se l'operazione ha avuto successo, False altrimenti
 */
function setSetting(\$key, \$value) {
    // Verifica se l'impostazione esiste già
    \$stmt = executeQuery(\"SELECT id FROM settings WHERE setting_key = ?\", [\$key], 's');
    if (\$stmt === false) {
        return false;
    }
    \$result = \$stmt->get_result();
    if (\$result->num_rows > 0) {
        // Aggiorna l'impostazione esistente
        \$query = \"UPDATE settings SET setting_value = ? WHERE setting_key = ?\";
        \$params = [\$value, \$key];
        \$types = 'ss';
    } else {
        // Inserisce una nuova impostazione
        \$query = \"INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)\";
        \$params = [\$key, \$value];
        \$types = 'ss';
    }
    \$stmt = executeQuery(\$query, \$params, \$types);
    return \$stmt !== false;
}

/**
 * Funzione per verificare il ruolo dell'utente
 *
 * @param string \$required_role Il ruolo richiesto
 */
function checkUserRole(\$required_role) {
    if (!isset(\$_SESSION['user_role']) || \$_SESSION['user_role'] !== \$required_role) {
        // L'utente non ha il ruolo richiesto
        header(\"Location: unauthorized.php\");
        exit;
    }
}

/**
 * Funzione per verificare il token CSRF
 *
 * @param string \$token Il token CSRF da verificare
 * @return bool True se il token è valido, False altrimenti
 */
function verifyCsrfToken(\$token) {
    if (!isset(\$_SESSION['csrf_token']) || !hash_equals(\$_SESSION['csrf_token'], \$token)) {
        return false;
    }
    // Token scade dopo 2 ore
    if (isset(\$_SESSION['csrf_token_time']) && (time() - \$_SESSION['csrf_token_time']) > 7200) {
        unset(\$_SESSION['csrf_token'], \$_SESSION['csrf_token_time']);
        return false;
    }
    return true;
}

/**
 * Funzione generica per sanitizzare input
 *
 * @param mixed \$data L'input da sanitizzare
 * @return string|NULL L'input sanitizzato o NULL
 */
function sanitizeInput(\$data) {
    if (\$data === null || \$data === '') {
        return null;
    }
    return trim(\$data);
}

/**
 * Funzione per sanitizzare input destinati al database
 *
 * @param mixed \$data L'input da sanitizzare
 * @return string|NULL L'input sanitizzato o NULL
 */
function sanitizeForDatabase(\$data) {
    if (\$data === null || \$data === '') {
        return null;
    }
    return trim(\$data);
}

/**
 * Funzione per formattare date
 *
 * @param string \$date La data da formattare
 * @return string La data formattata o vuota se invalida
 */
function formatDate(\$date) {
    global \$date_format;
    if (empty(\$date) || \$date === '0000-00-00') {
        return '';
    }
    return date(\$date_format, strtotime(\$date));
}

/**
 * Funzione per caricare file in modo sicuro
 *
 * @param string \$file_input_name Il nome dell'input file
 * @param string \$upload_dir La directory di upload (default 'uploads/')
 * @return string|null Il percorso del file caricato o null
 */
function uploadFile(\$file_input_name, \$upload_dir = 'uploads/') {
    global \$accepted_file_formats;
    if (!isset(\$_FILES[\$file_input_name])) {
        return null;
    }

    \$file = \$_FILES[\$file_input_name];
    if (\$file['error'] !== UPLOAD_ERR_OK) {
        die(\"Errore nel caricamento del file.\");
    }

    \$file_ext = strtolower(pathinfo(\$file['name'], PATHINFO_EXTENSION));
    if (!in_array(\$file_ext, \$accepted_file_formats)) {
        die(\"Formato file non accettato.\");
    }

    \$new_filename = uniqid() . '.' . \$file_ext;
    \$destination = \$upload_dir . \$new_filename;

    if (!move_uploaded_file(\$file['tmp_name'], \$destination)) {
        die(\"Errore nel salvataggio del file.\");
    }

    return \$destination;
}

/**
 * Funzione per la paginazione dei risultati
 *
 * @param string \$query La query SQL di base
 * @param array \$params I parametri da legare alla query
 * @param string \$types I tipi dei parametri
 * @param int \$per_page Numero di risultati per pagina
 * @param int \$current_page Numero della pagina corrente
 * @return mysqli_result|false I risultati paginati o false in caso di errore
 */
function paginate(\$query, \$params = [], \$types = '', \$per_page = 10, \$current_page = 1) {
    global \$mysqli;

    \$offset = (\$current_page - 1) * \$per_page;
    \$paginated_query = \$query . \" LIMIT ? OFFSET ?\";
    \$params[] = \$per_page;
    \$params[] = \$offset;
    \$types .= 'ii';

    \$stmt = executeQuery(\$paginated_query, \$params, \$types);
    if (\$stmt === false) {
        return false;
    }
    \$result = \$stmt->get_result();

    return \$result;
}

/**
 * Funzione per contare il numero totale di risultati
 *
 * @param string \$query La query SQL di base
 * @param array \$params I parametri da legare alla query
 * @param string \$types I tipi dei parametri
 * @return int Il numero totale di risultati
 */
function countResults(\$query, \$params = [], \$types = '') {
    global \$mysqli;

    \$count_query = \"SELECT COUNT(*) as total FROM (\" . \$query . \") as sub\";
    \$stmt = executeQuery(\$count_query, \$params, \$types);
    if (\$stmt === false) {
        return 0;
    }
    \$result = \$stmt->get_result();
    \$row = \$result->fetch_assoc();

    return \$row['total'] ?? 0;
}

/**
 * Funzione per controllare l'accesso all'area riservata
 */
function checkLogin() {
    global \$mysqli;
    if (empty(\$_SESSION['user_id'])) {
        global \$base_url;
        header(\"Location: \" . \$base_url . \"login.php\");
        exit;
    }

    // Recupera il ruolo dell'utente se non è già impostato
    if (!isset(\$_SESSION['user_role'])) {
        \$stmt = executeQuery(\"SELECT role FROM users WHERE id = ?\", [\$_SESSION['user_id']], 'i');
        if (\$stmt !== false) {
            \$result = \$stmt->get_result();
            if (\$row = \$result->fetch_assoc()) {
                \$_SESSION['user_role'] = \$row['role'];
            } else {
                \$_SESSION['user_role'] = 'operatore'; // Ruolo predefinito
            }
        } else {
            \$_SESSION['user_role'] = 'operatore';
        }
    }
}

/**
 * Funzione per costruire automaticamente la stringa dei tipi
 *
 * @param array \$params I parametri da legare
 * @return string La stringa dei tipi
 */
function buildTypesString(\$params) {
    \$types = '';
    foreach (\$params as \$param) {
        if (is_int(\$param)) {
            \$types .= 'i';
        } elseif (is_float(\$param)) {
            \$types .= 'd';
        } elseif (is_null(\$param)) {
            \$types .= 's'; // Usa 's' anche per i valori NULL
        } else {
            \$types .= 's';
        }
    }
    return \$types;
}

/**
 * Funzione per visualizzare i campi di testo evitando zeri non voluti
 *
 * @param mixed \$value Il valore da visualizzare
 * @return string La stringa da visualizzare
 */
function displayField(\$value) {
    if (!empty(\$value) && \$value !== '0') {
        return sanitizeForHTML(\$value);
    } else {
        return ''; // O \"Non specificato\" se preferisci
    }
}

/**
 * Funzione per acquisire un lock
 *
 * @param string \$table Nome della tabella ('aziende' o 'lavoratori')
 * @param int \$record_id ID del record da bloccare
 * @param int \$user_id ID dell'utente che acquisisce il lock
 * @return bool True se il lock è stato acquisito, False altrimenti
 */
function acquireLock(\$table, \$record_id, \$user_id) {
    \$query = \"INSERT INTO locks (table_name, record_id, user_id) VALUES (?, ?, ?)\";
    return executeQuery(\$query, [\$table, \$record_id, \$user_id], 'sii') !== false;
}

/**
 * Funzione per rilasciare un lock
 *
 * @param string \$table Nome della tabella
 * @param int \$record_id ID del record
 * @param int \$user_id ID dell'utente che rilascia il lock
 * @return bool True se il lock è stato rilasciato, False altrimenti
 */
function releaseLock(\$table, \$record_id, \$user_id) {
    \$query = \"DELETE FROM locks WHERE table_name = ? AND record_id = ? AND user_id = ?\";
    return executeQuery(\$query, [\$table, \$record_id, \$user_id], 'sii') !== false;
}

/**
 * Funzione per verificare se un record è bloccato
 *
 * @param string \$table Nome della tabella
 * @param int \$record_id ID del record
 * @return bool True se è bloccato, False altrimenti
 */
function isLocked(\$table, \$record_id) {
    \$stmt = executeQuery(\"SELECT * FROM locks WHERE table_name = ? AND record_id = ?\", [\$table, \$record_id], 'si');
    if (\$stmt === false) {
        return false;
    }
    \$result = \$stmt->get_result();
    return \$result->num_rows > 0;
}

/**
 * Funzione per ottenere l'utente che ha bloccato il record
 *
 * @param string \$table Nome della tabella
 * @param int \$record_id ID del record
 * @return int|null ID dell'utente che ha bloccato il record o null se non bloccato
 */
function getLockOwner(\$table, \$record_id) {
    \$stmt = executeQuery(\"SELECT user_id FROM locks WHERE table_name = ? AND record_id = ?\", [\$table, \$record_id], 'si');
    if (\$stmt === false) {
        return null;
    }
    \$result = \$stmt->get_result();
    if (\$result->num_rows > 0) {
        return intval(\$result->fetch_assoc()['user_id']);
    }
    return null;
}

/**
 * Funzione per liberare tutti i lock di un utente (es. logout)
 *
 * @param int \$user_id ID dell'utente
 * @return bool True se i lock sono stati rilasciati, False altrimenti
 */
function releaseAllLocks(\$user_id) {
    \$query = \"DELETE FROM locks WHERE user_id = ?\";
    return executeQuery(\$query, [\$user_id], 'i') !== false;
}

/**
 * Funzione per ottenere più impostazioni dal database
 *
 * @param array \$keys Le chiavi delle impostazioni
 * @return array Associative array delle impostazioni [key => value]
 */
function getSettings(array \$keys) {
    if (empty(\$keys)) {
        return [];
    }

    // Costruisce i placeholder per la query (e.g., \"?, ?, ?\")
    \$placeholders = implode(',', array_fill(0, count(\$keys), '?'));
    \$types = str_repeat('s', count(\$keys));

    \$stmt = executeQuery(\"SELECT setting_key, setting_value FROM settings WHERE setting_key IN (\$placeholders)\", \$keys, \$types);
    if (\$stmt === false) {
        return [];
    }
    \$result = \$stmt->get_result();
    \$settings = [];
    while (\$row = \$result->fetch_assoc()) {
        \$settings[\$row['setting_key']] = \$row['setting_value'];
    }
    return \$settings;
}

// Recupero delle impostazioni dal database
\$settings_keys = ['accepted_file_formats', 'base_url', 'logo', 'date_format'];
\$settings = getSettings(\$settings_keys);

\$accepted_file_formats_string = \$settings['accepted_file_formats'] ?? null;
if (\$accepted_file_formats_string !== null) {
    \$accepted_file_formats = array_map('trim', explode(',', \$accepted_file_formats_string));
} else {
    \$accepted_file_formats = ['jpg', 'jpeg', 'png', 'gif', 'pdf']; // Formati predefiniti
}

\$date_format = \$settings['date_format'] ?? 'Y-m-d'; // Formato predefinito ISO 8601
?>";

$config_file_path = '../config.php';
if (file_put_contents($config_file_path, $config_content) === false) {
    show_error('Errore nella creazione del file config.php.');
}

// Pulizia: Chiude la connessione al database
$mysqli->close();

// Mostra il successo e fornisce istruzioni per completare l'installazione
show_success('Installazione completata con successo! <a href="../index.php">Vai all\'applicazione</a>');

// Dopo l'installazione, è altamente consigliato eliminare la cartella install
?>
