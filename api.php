<?php
/**
 * api.php - API Endpoint per connessioni inter-CRM
 *
 * Fornisce dati di aziende e lavoratori a CRM esterni autenticati tramite API key.
 *
 * Endpoints:
 *   GET /api.php?action=aziende          - Lista aziende (con ricerca e paginazione)
 *   GET /api.php?action=azienda&id=X     - Dettaglio singola azienda
 *   GET /api.php?action=ping             - Test connessione (restituisce nome CRM)
 *
 * Autenticazione: Header "X-API-Key: <chiave>"
 */

// Non usare config.php per evitare redirect login e output buffer
// Carichiamo solo le parti essenziali

// Carica .env
function loadEnvApi($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if (!isset($_ENV[$key]) && getenv($key) === false) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}
loadEnvApi(__DIR__ . '/.env');

header('Content-Type: application/json; charset=utf-8');

// Connessione al database
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$db   = $_ENV['DB_NAME'] ?? 'adlcobas_padova';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Helper: esegui query preparata
function apiQuery($query, $params = [], $types = '') {
    global $mysqli;
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        return false;
    }
    if (!empty($params) && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

// Helper: risposta JSON
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Helper: ottenere un'impostazione
function apiGetSetting($key) {
    $stmt = apiQuery("SELECT setting_value FROM settings WHERE setting_key = ?", [$key], 's');
    if (!$stmt) return null;
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return null;
}

// Validazione API Key
$action = $_GET['action'] ?? '';

// L'azione 'ping' senza chiave restituisce solo se il CRM è raggiungibile
if ($action !== 'ping_public') {
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($api_key)) {
        jsonResponse(['error' => 'API key mancante. Inviare header X-API-Key.'], 401);
    }

    // Verifica la chiave API
    $stmt = apiQuery("SELECT id, label FROM api_keys WHERE api_key = ? AND is_active = 1", [$api_key], 's');
    if (!$stmt) {
        jsonResponse(['error' => 'Errore del server'], 500);
    }
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        jsonResponse(['error' => 'API key non valida o disattivata.'], 403);
    }
    $key_row = $result->fetch_assoc();

    // Aggiorna last_used_at
    apiQuery("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?", [$key_row['id']], 'i');
}

// Router
switch ($action) {
    case 'ping':
    case 'ping_public':
        $nome_app = apiGetSetting('nome_app') ?? 'CRM';
        jsonResponse([
            'success' => true,
            'name' => $nome_app,
            'version' => '1.0'
        ]);
        break;

    case 'aziende':
        handleAziende();
        break;

    case 'azienda':
        handleAziendaDetail();
        break;

    default:
        jsonResponse(['error' => 'Azione non riconosciuta. Azioni disponibili: ping, aziende, azienda'], 400);
}

/**
 * Lista aziende con ricerca e paginazione
 */
function handleAziende() {
    $search_nome      = trim($_GET['search_nome'] ?? '');
    $search_indirizzo = trim($_GET['search_indirizzo'] ?? '');
    $search_citta     = trim($_GET['search_citta'] ?? '');
    $page             = max(1, intval($_GET['page'] ?? 1));
    $per_page         = min(100, max(1, intval($_GET['per_page'] ?? 30)));
    $offset           = ($page - 1) * $per_page;

    $query = "SELECT a.id, a.nome_azienda, a.indirizzo_via, a.indirizzo_numero_civico,
                     a.indirizzo_cap, a.indirizzo_citta, a.indirizzo_provincia,
                     a.telefono, a.email,
                     COUNT(l.id) AS numero_lavoratori
              FROM aziende a
              LEFT JOIN lavoratori l ON a.id = l.azienda_id AND l.archiviato <> 1
              WHERE 1=1";
    $params = [];
    $types  = '';

    if ($search_nome !== '') {
        $query .= " AND a.nome_azienda LIKE CONCAT('%', ?, '%')";
        $params[] = $search_nome;
        $types .= 's';
    }
    if ($search_indirizzo !== '') {
        $query .= " AND a.indirizzo_via LIKE CONCAT('%', ?, '%')";
        $params[] = $search_indirizzo;
        $types .= 's';
    }
    if ($search_citta !== '') {
        $query .= " AND a.indirizzo_citta LIKE CONCAT('%', ?, '%')";
        $params[] = $search_citta;
        $types .= 's';
    }

    // Count query
    $countQuery = "SELECT COUNT(DISTINCT a.id) as total FROM aziende a WHERE 1=1";
    $countParams = [];
    $countTypes  = '';
    if ($search_nome !== '') {
        $countQuery .= " AND a.nome_azienda LIKE CONCAT('%', ?, '%')";
        $countParams[] = $search_nome;
        $countTypes .= 's';
    }
    if ($search_indirizzo !== '') {
        $countQuery .= " AND a.indirizzo_via LIKE CONCAT('%', ?, '%')";
        $countParams[] = $search_indirizzo;
        $countTypes .= 's';
    }
    if ($search_citta !== '') {
        $countQuery .= " AND a.indirizzo_citta LIKE CONCAT('%', ?, '%')";
        $countParams[] = $search_citta;
        $countTypes .= 's';
    }

    $countStmt = apiQuery($countQuery, $countParams, $countTypes);
    $total = 0;
    if ($countStmt) {
        $countResult = $countStmt->get_result();
        $total = intval($countResult->fetch_assoc()['total']);
    }

    // Main query with pagination
    $query .= " GROUP BY a.id ORDER BY a.nome_azienda ASC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $types .= 'i';
    $params[] = $offset;
    $types .= 'i';

    $stmt = apiQuery($query, $params, $types);
    if (!$stmt) {
        jsonResponse(['error' => 'Errore nella query'], 500);
    }

    $result = $stmt->get_result();
    $aziende = [];
    while ($row = $result->fetch_assoc()) {
        $row['numero_lavoratori'] = intval($row['numero_lavoratori']);
        $aziende[] = $row;
    }

    jsonResponse([
        'success'    => true,
        'data'       => $aziende,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $per_page,
        'total_pages'=> ceil($total / $per_page)
    ]);
}

/**
 * Dettaglio singola azienda con lavoratori e unità operative
 */
function handleAziendaDetail() {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'ID azienda non valido'], 400);
    }

    // Dati azienda
    $stmt = apiQuery("SELECT * FROM aziende WHERE id = ?", [$id], 'i');
    if (!$stmt) {
        jsonResponse(['error' => 'Errore nella query'], 500);
    }
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        jsonResponse(['error' => 'Azienda non trovata'], 404);
    }
    $azienda = $result->fetch_assoc();

    // Numero lavoratori
    $stmt = apiQuery("SELECT COUNT(*) as total FROM lavoratori WHERE azienda_id = ?", [$id], 'i');
    $numero_lavoratori = 0;
    if ($stmt) {
        $numero_lavoratori = intval($stmt->get_result()->fetch_assoc()['total']);
    }

    // Lista lavoratori
    $stmt = apiQuery("SELECT id, nome, cognome FROM lavoratori WHERE azienda_id = ?", [$id], 'i');
    $lavoratori = [];
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $lavoratori[] = $row;
        }
    }

    // Unità operative
    $stmt = apiQuery("SELECT * FROM unita_operativa WHERE azienda_id = ?", [$id], 'i');
    $unita_operative = [];
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $unita_operative[] = $row;
        }
    }

    $azienda['numero_lavoratori'] = $numero_lavoratori;
    $azienda['lavoratori'] = $lavoratori;
    $azienda['unita_operative'] = $unita_operative;

    jsonResponse([
        'success' => true,
        'data'    => $azienda
    ]);
}
