<?php
// config.php

// Avvio del buffer di output per prevenire invio accidentale di output prima delle intestazioni
ob_start();

// Impostazioni di visualizzazione degli errori (disabilitare in produzione)
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// Definizione delle funzioni di utilità
function sanitizeForHTML($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Funzione per sanitizzare input HTML consentendo solo determinati tag
 *
 * @param string $data L'input HTML da sanitizzare
 * @return string L'HTML sanitizzato
 */
function sanitizeHTML($data) {
    // Definisci i tag e gli attributi consentiti
    // Puoi personalizzare questi tag secondo le tue necessità
    $allowed_tags = '<p><a><b><strong><i><em><ul><ol><li><br><hr><span><div><img><h1><h2><h3><h4><h5><h6>';

    // Rimuove tutti i tag non consentiti
    $data = strip_tags($data, $allowed_tags);

    // Opzionale: Puoi ulteriormente sanitizzare gli attributi, ad esempio per gli href degli <a>
    // Utilizzando una libreria come HTML Purifier per una sanitizzazione avanzata

    return $data;
}
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfInputField() {
    $token = generateCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . sanitizeForHTML($token) . '">';
}

// Definizione del percorso base
$base_url = '/';
// Rimozione di eventuali backslash su Windows
$base_url = str_replace('\\', '/', $base_url);

// Imposta il fuso orario
date_default_timezone_set('Europe/Rome');

// Impostazione della directory personalizzata per le sessioni
$session_directory = __DIR__ . '/sessions';
if (!is_dir($session_directory)) {
    // Tenta di creare la directory se non esiste
    if (!mkdir($session_directory, 0755, true)) {
        error_log("Impossibile creare la directory delle sessioni: $session_directory");
        die("Errore di configurazione del server.");
    }
}

// Imposta la directory delle sessioni
session_save_path($session_directory);

// Avvio della sessione con parametri corretti
function startSecureSession() {
    global $remember_me;

    // Gestione della durata della sessione basata sul cookie "remember_me"
    $remember_me = isset($_COOKIE['remember_me']) && $_COOKIE['remember_me'] === '1';

    if ($remember_me) {
        // Sessione che dura 30 giorni
        $cookie_lifetime = 30 * 24 * 60 * 60; // 30 giorni in secondi
    } else {
        // Sessione che dura 2 ore (aumentato da 30 minuti)
        $cookie_lifetime = 2 * 60 * 60; // 2 ore in secondi
    }

    // Override delle impostazioni del server per la garbage collection
    ini_set('session.gc_maxlifetime', $cookie_lifetime);
    ini_set('session.cookie_lifetime', $cookie_lifetime);

    // Impostazione delle direttive per la garbage collection
    ini_set('session.gc_probability', 1); // Frequenza della garbage collection
    ini_set('session.gc_divisor', 100);    // Divisore per determinare la probabilità
    // Con gc_probability = 1 e gc_divisor = 100, c'è l'1% di probabilità di eseguire la GC ad ogni richiesta

    // Imposta i parametri del cookie di sessione
    session_set_cookie_params([
        'lifetime' => $cookie_lifetime,
        'path' => '/',
        'domain' => '', // Imposta il tuo dominio se necessario
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Usa solo HTTPS se disponibile
        'httponly' => true,
        'samesite' => 'Lax' // Può essere 'Strict', 'Lax' o 'None'
    ]);

    // Avvio della sessione
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Avvio della sessione
startSecureSession();

// Includi l'autoload di Composer principale (se presente)
$main_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($main_autoload)) {
    require_once $main_autoload;
} else {
    error_log("Autoload di Composer principale non trovato.");
}

/**
 * Funzione per eseguire query SQL in modo sicuro (prepared statements)
 *
 * @param string $query La query SQL con placeholder
 * @param array $params I parametri da legare alla query
 * @param string $types I tipi dei parametri (es. 's', 'i', 'd', 'b')
 * @return mysqli_stmt|false La dichiarazione preparata o false in caso di errore
 */
function executeQuery($query, $params = [], $types = '') {
    global $mysqli;

    // Prepara la query
    $stmt = $mysqli->prepare($query);
    if ($stmt === false) {
        error_log("Errore nella preparazione della query: " . $mysqli->error);
        return false;
    }

    // Lega i parametri se presenti
    if (!empty($params) && !empty($types)) {
        // Assicurati che il numero di tipi corrisponda al numero di parametri
        if (strlen($types) !== count($params)) {
            error_log("Il numero di tipi non corrisponde al numero di parametri.");
            return false;
        }

        // Gestione dei valori NULL
        $refs = [];
        $new_types = '';
        for ($i = 0; $i < count($params); $i++) {
            if (is_null($params[$i])) {
                // Usa 's' come tipo per i valori NULL
                $new_types .= 's';
            } else {
                $new_types .= $types[$i];
            }
            $refs[$i] = &$params[$i];
        }

        // Bind parameters
        array_unshift($refs, $new_types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    // Esegui la query
    if (!$stmt->execute()) {
        error_log("Errore nell'esecuzione della query: " . $stmt->error);
        return false;
    }

    return $stmt;
}

/**
 * Funzione per ottenere un'impostazione dal database
 *
 * @param string $key La chiave dell'impostazione
 * @return string|null Il valore dell'impostazione o null se non trovato
 */
function getSetting($key) {
    $stmt = executeQuery("SELECT setting_value FROM settings WHERE setting_key = ?", [$key], 's');
    if ($stmt === false) {
        return null;
    }
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return null;
}

/**
 * Funzione per impostare un valore di configurazione nel database
 *
 * @param string $key La chiave dell'impostazione
 * @param string $value Il valore dell'impostazione
 * @return bool True se l'operazione ha avuto successo, False altrimenti
 */
function setSetting($key, $value) {
    // Verifica se l'impostazione esiste già
    $stmt = executeQuery("SELECT id FROM settings WHERE setting_key = ?", [$key], 's');
    if ($stmt === false) {
        return false;
    }
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        // Aggiorna l'impostazione esistente
        $query = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
        $params = [$value, $key];
        $types = 'ss';
    } else {
        // Inserisce una nuova impostazione
        $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)";
        $params = [$key, $value];
        $types = 'ss';
    }
    $stmt = executeQuery($query, $params, $types);
    return $stmt !== false;
}

/**
 * Funzione per verificare il ruolo dell'utente
 *
 * @param string|array $required_roles Il ruolo richiesto o un array di ruoli richiesti
 */
function checkUserRole($required_roles) {
    if (!isset($_SESSION['user_role'])) {
        // L'utente non ha il ruolo richiesto
        header("Location: unauthorized.php");
        exit();
    }

    if (is_array($required_roles)) {
        if (!in_array($_SESSION['user_role'], $required_roles)) {
            header("Location: unauthorized.php");
            exit();
        }
    } else {
        if ($_SESSION['user_role'] !== $required_roles) {
            header("Location: unauthorized.php");
            exit();
        }
    }
}

/**
 * Funzione per verificare il token CSRF
 *
 * @param string $token Il token CSRF da verificare
 * @return bool True se il token è valido, False altrimenti
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Funzione generica per sanitizzare input
 *
 * @param mixed $data L'input da sanitizzare
 * @return string|NULL L'input sanitizzato o NULL
 */
function sanitizeInput($data) {
    if ($data === null || $data === '') {
        return null;
    }
    return trim($data);
}

/**
 * Funzione per sanitizzare input destinati al database
 *
 * @param mixed $data L'input da sanitizzare
 * @return string|NULL L'input sanitizzato o NULL
 */
function sanitizeForDatabase($data) {
    if ($data === null || $data === '') {
        return null;
    }
    return trim($data);
}

/**
 * Funzione per formattare date
 *
 * @param string $date La data da formattare (formato: YYYY-MM-DD)
 * @return string La data formattata come dd-mm-YYYY o vuota se invalida
 */
function formatDate($date) {
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }

    // Formattazione della data
    return date('d-m-Y', strtotime($date));
}

/**
 * Funzione per caricare file in modo sicuro
 *
 * @param string $file_input_name Il nome dell'input file
 * @param string $upload_dir La directory di upload (default 'uploads/')
 * @return string|null Il percorso del file caricato o null
 */
function uploadFile($file_input_name, $upload_dir = 'uploads/') {
    global $accepted_file_formats;
    if (!isset($_FILES[$file_input_name])) {
        return null;
    }

    $file = $_FILES[$file_input_name];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Errore nel caricamento del file: " . $file['error']);
        return null;
    }

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $accepted_file_formats)) {
        error_log("Formato file non accettato: " . $file_ext);
        return null;
    }

    $new_filename = uniqid() . '.' . $file_ext;
    $destination = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        error_log("Errore nel salvataggio del file: " . $file['tmp_name']);
        return null;
    }

    return $destination;
}

/**
 * Funzione per la paginazione dei risultati
 *
 * @param string $query La query SQL di base
 * @param array $params I parametri da legare alla query
 * @param string $types I tipi dei parametri
 * @param int $per_page Numero di risultati per pagina
 * @param int $current_page Numero della pagina corrente
 * @return mysqli_result|false I risultati paginati o false in caso di errore
 */
function paginate($query, $params = [], $types = '', $per_page = 10, $current_page = 1) {
    global $mysqli;

    $offset = ($current_page - 1) * $per_page;
    $paginated_query = $query . " LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = executeQuery($paginated_query, $params, $types);
    if ($stmt === false) {
        return false;
    }
    $result = $stmt->get_result();

    return $result;
}

/**
 * Funzione per contare il numero totale di risultati
 *
 * @param string $query La query SQL di base
 * @param array $params I parametri da legare alla query
 * @param string $types I tipi dei parametri
 * @return int Il numero totale di risultati
 */
function countResults($query, $params = [], $types = '') {
    global $mysqli;

    $count_query = "SELECT COUNT(*) as total FROM (" . $query . ") as sub";
    $stmt = executeQuery($count_query, $params, $types);
    if ($stmt === false) {
        return 0;
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['total'] ?? 0;
}

/**
 * Funzione per controllare l'accesso all'area riservata
 */
function checkLogin() {
    global $mysqli, $base_url;
    if (empty($_SESSION['user_id'])) {
        header("Location: " . $base_url . "login.php");
        exit();
    }

    // Recupera ruolo e sede dell'utente se non sono già impostati
    if (!isset($_SESSION['user']['role']) || !isset($_SESSION['user']['sede_id'])) {
        $stmt = executeQuery("SELECT role, sede_id FROM users WHERE id = ?", [$_SESSION['user_id']], 'i');
        if ($stmt !== false) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Imposta le informazioni in un array "user" in sessione
                $_SESSION['user'] = [
                    'role'    => $row['role'] ?? 'operator',
                    'sede_id' => $row['sede_id'] ?? null
                ];
            } else {
                // Se non troviamo l'utente, impostiamo valori di default
                $_SESSION['user'] = [
                    'role'    => 'operator',
                    'sede_id' => null
                ];
            }
        } else {
            $_SESSION['user'] = [
                'role'    => 'operator',
                'sede_id' => null
            ];
        }
    }
}


/**
 * Funzione per costruire automaticamente la stringa dei tipi
 *
 * @param array $params I parametri da legare
 * @return string La stringa dei tipi
 */
function buildTypesString($params) {
    $types = '';
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } elseif (is_null($param)) {
            $types .= 's'; // Usa 's' anche per i valori NULL
        } else {
            $types .= 's';
        }
    }
    return $types;
}

/**
 * Funzione per visualizzare i campi di testo evitando zeri non voluti
 *
 * @param mixed $value Il valore da visualizzare
 * @return string La stringa da visualizzare
 */
function displayField($value) {
    if (!empty($value)) {
        return sanitizeForHTML($value);
    } else {
        return ''; // O "Non specificato" se preferisci
    }
}

/**
 * Funzione per acquisire un lock
 *
 * @param string $table Nome della tabella ('aziende' o 'lavoratori')
 * @param int $record_id ID del record da bloccare
 * @param int $user_id ID dell'utente che acquisisce il lock
 * @return bool True se il lock è stato acquisito, False altrimenti
 */
function acquireLock($table, $record_id, $user_id) {
    $query = "INSERT INTO locks (table_name, record_id, user_id) VALUES (?, ?, ?)";
    return executeQuery($query, [$table, $record_id, $user_id], 'sii') !== false;
}

/**
 * Funzione per rilasciare un lock
 *
 * @param string $table Nome della tabella
 * @param int $record_id ID del record
 * @param int $user_id ID dell'utente che rilascia il lock
 * @return bool True se il lock è stato rilasciato, False altrimenti
 */
function releaseLock($table, $record_id, $user_id) {
    $query = "DELETE FROM locks WHERE table_name = ? AND record_id = ? AND user_id = ?";
    return executeQuery($query, [$table, $record_id, $user_id], 'sii') !== false;
}

/**
 * Funzione per verificare se un record è bloccato
 *
 * @param string $table Nome della tabella
 * @param int $record_id ID del record
 * @return bool True se è bloccato, False altrimenti
 */
function isLocked($table, $record_id) {
    $stmt = executeQuery("SELECT * FROM locks WHERE table_name = ? AND record_id = ?", [$table, $record_id], 'si');
    if ($stmt === false) {
        return false;
    }
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

/**
 * Funzione per ottenere l'utente che ha bloccato il record
 *
 * @param string $table Nome della tabella
 * @param int $record_id ID del record
 * @return int|null ID dell'utente che ha bloccato il record o null se non bloccato
 */
function getLockOwner($table, $record_id) {
    $stmt = executeQuery("SELECT user_id FROM locks WHERE table_name = ? AND record_id = ?", [$table, $record_id], 'si');
    if ($stmt === false) {
        return null;
    }
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return intval($result->fetch_assoc()['user_id']);
    }
    return null;
}

/**
 * Funzione per liberare tutti i lock di un utente (es. logout)
 *
 * @param int $user_id ID dell'utente
 * @return bool True se i lock sono stati rilasciati, False altrimenti
 */
function releaseAllLocks($user_id) {
    $query = "DELETE FROM locks WHERE user_id = ?";
    return executeQuery($query, [$user_id], 'i') !== false;
}

/**
 * Funzione per ottenere più impostazioni dal database
 *
 * @param array $keys Le chiavi delle impostazioni
 * @return array Associative array delle impostazioni [key => value]
 */
function getSettings(array $keys) {
    if (empty($keys)) {
        return [];
    }

    // Costruisce i placeholder per la query (e.g., "?, ?, ?")
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $types = str_repeat('s', count($keys));

    $stmt = executeQuery("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)", $keys, $types);
    if ($stmt === false) {
        return [];
    }
    $result = $stmt->get_result();
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

// Definizione della chiave di crittografia generale per i plugin
define('GENERAL_ENCRYPTION_KEY', getenv('GENERAL_ENCRYPTION_KEY') ?: 'Z0MHmscNKs2mTaFEn4dbNhsYE18fZQetltBB4TrHM2k=');

/**
 * ===========================
 *          Hook System
 * ===========================
 */
define('CRM_INIT', true);
/**
 * Array globale per memorizzare gli hook e le loro callback.
 */
$hooks = [];

/**
 * Funzione per registrare una callback a un hook specifico.
 *
 * @param string $hook Nome dell'hook.
 * @param callable $callback Funzione di callback da eseguire.
 * @param int $priority Priorità della callback (più basso = eseguito prima).
 */
function addHook($hook, $callback, $priority = 10) {
    global $hooks;
    if (!isset($hooks[$hook])) {
        $hooks[$hook] = [];
    }
    $hooks[$hook][] = ['callback' => $callback, 'priority' => $priority];
    
    // Ordina le callback per priorità
    usort($hooks[$hook], function($a, $b) {
        return $a['priority'] - $b['priority'];
    });
}

/**
 * Funzione per eseguire tutte le callback registrate a un hook.
 *
 * @param string $hook Nome dell'hook.
 * @param mixed ...$args Argomenti da passare alle callback.
 * @return void
 */
function doHook($hook, ...$args) {
    global $hooks;
    if (isset($hooks[$hook])) {
        foreach ($hooks[$hook] as $hook_callback) {
            $callback = $hook_callback['callback'];
            if (is_callable($callback)) {
                call_user_func_array($callback, $args);
            } else {
                error_log("Callback '$callback' per l'hook '$hook' non è valida o non esiste.");
            }
        }
    }
}

/**
 * ===========================
 *          Plugin System
 * ===========================
 */

/**
 * Funzione per caricare i plugin attivi
 */
function loadActivePlugins() {
    global $mysqli, $base_url;

    // Query per recuperare i plugin attivi
    $stmt = executeQuery("SELECT name FROM plugins WHERE status = 'active'", [], '');
    if ($stmt === false) {
        error_log("Errore nel recuperare i plugin attivi: " . $mysqli->error);
        return;
    }

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $plugin_name = $row['name'];
        $plugin_path = __DIR__ . "/plugins/" . $plugin_name . "/plugin.php";

        if (file_exists($plugin_path)) {
            error_log("Caricamento del plugin: $plugin_name");
            require_once $plugin_path;
            error_log("Plugin $plugin_name caricato con successo.");
        } else {
            error_log("Il file del plugin '$plugin_name' non esiste.");
        }
    }

    $stmt->close();

    // Esegui tutti gli hook 'plugins_loaded' dopo aver caricato tutti i plugin
    doHook('plugins_loaded');
}

/**
 * Funzione per ottenere tutti i plugin dal database
 *
 * @return array Array associativo dei plugin
 */
function getAllPlugins() {
    $stmt = executeQuery("SELECT * FROM plugins", [], '');
    if ($stmt === false) {
        return [];
    }
    $result = $stmt->get_result();
    $plugins = [];
    while ($row = $result->fetch_assoc()) {
        $plugins[] = $row;
    }
    return $plugins;
}

/**
 * Funzione per attivare un plugin
 *
 * @param string $plugin_name Nome del plugin da attivare
 * @return bool True se l'operazione ha avuto successo, False altrimenti
 */
function activatePlugin($plugin_name) {
    return executeQuery("UPDATE plugins SET status = 'active' WHERE name = ?", [$plugin_name], 's');
}

/**
 * Funzione per disattivare un plugin
 *
 * @param string $plugin_name Nome del plugin da disattivare
 * @return bool True se l'operazione ha avuto successo, False altrimenti
 */
function deactivatePlugin($plugin_name) {
    return executeQuery("UPDATE plugins SET status = 'inactive' WHERE name = ?", [$plugin_name], 's');
}

/**
 * Funzione per installare un nuovo plugin
 *
 * @param string $plugin_name Nome del plugin
 * @param string $description Descrizione del plugin
 * @param string $version Versione del plugin
 * @param string $author Autore del plugin
 * @return bool True se l'operazione ha avuto successo, False altrimenti
 */
function installPlugin($plugin_name, $description, $version, $author) {
    return executeQuery(
        "INSERT INTO plugins (name, description, version, author, status) VALUES (?, ?, ?, ?, 'inactive')",
        [$plugin_name, $description, $version, $author],
        'ssss'
    );
}

/**
 * Funzione per rimuovere un plugin
 *
 * @param string $plugin_name Nome del plugin da rimuovere
 * @return bool True se l'operazione ha avuto successo, False altrimenti
 */
function removePlugin($plugin_name) {
    return executeQuery("DELETE FROM plugins WHERE name = ?", [$plugin_name], 's');
}

/**
 * Funzione per criptare la password email
 *
 * @param string $password La password in chiaro
 * @return string La password criptata
 */
function encryptPassword($password) {
    $encryption_key = base64_decode(GENERAL_ENCRYPTION_KEY); // Decodifica la chiave se è base64
    $cipher = "aes-256-cbc";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext = openssl_encrypt($password, $cipher, $encryption_key, 0, $iv);
    if ($ciphertext === false) {
        return '';
    }
    return base64_encode($iv . $ciphertext);
}

/**
 * Funzione per decriptare la password email
 *
 * @param string $encrypted_password La password criptata
 * @return string La password decriptata
 */
function decryptPassword($encrypted_password) {
    $encryption_key = base64_decode(GENERAL_ENCRYPTION_KEY); // Decodifica la chiave se è base64
    $cipher = "aes-256-cbc";
    $data = base64_decode($encrypted_password, true);
    if (!is_string($data)) {
        return '';
    }
    $ivlen = openssl_cipher_iv_length($cipher);
    if (strlen($data) < $ivlen) {
        return '';
    }
    $iv = substr($data, 0, $ivlen);
    $ciphertext = substr($data, $ivlen);
    return openssl_decrypt($ciphertext, $cipher, $encryption_key, 0, $iv);
}

// Connessione al database con gestione degli errori
$host = '127.0.0.1';
$db = 'adlcobas_padova';               // Nome del database
$user = 'root';                        // Nome utente del database
$pass = 'Fa310reds?';                  // Password del database

$mysqli = new mysqli($host, $user, $pass, $db);

// Verifica la connessione
if ($mysqli->connect_error) {
    die("Connessione fallita: " . $mysqli->connect_error);
}

// Imposta la codifica dei caratteri per la connessione al database
if (!$mysqli->set_charset("utf8mb4")) {
    printf("Errore durante il caricamento del set di caratteri utf8mb4: %s\n", $mysqli->error);
    exit();
}

// Carica i plugin attivi
loadActivePlugins();

// Fine del buffer di output e invio al browser
ob_end_flush();