<?php
// create_unita_operativa.php
require_once 'config.php'; // Usa require_once per evitare inclusioni multiple
checkLogin();

// Imposta l'header per la risposta JSON
header('Content-Type: application/json');

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Metodo non consentito
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

// Verifica il token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    http_response_code(400); // Richiesta non valida
    echo json_encode(['success' => false, 'message' => 'Token CSRF mancante o non valido.']);
    exit;
}

// Recupera e sanitizza i dati
$azienda_id = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : 0;
$nome_unita_operativa = isset($_POST['nome_unita_operativa']) ? sanitizeInput($_POST['nome_unita_operativa']) : '';

// Verifica che i campi obbligatori siano compilati
if ($azienda_id <= 0 || empty($nome_unita_operativa)) {
    echo json_encode(['success' => false, 'message' => 'Compila tutti i campi obbligatori.']);
    exit;
}

// Verifica che l'azienda esista
$query = "SELECT id FROM aziende WHERE id = ?";
$stmt = executeQuery($query, [$azienda_id], 'i');

if ($stmt) {
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Azienda non valida.']);
        $stmt->close();
        exit;
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Errore durante la verifica dell\'azienda.']);
    exit;
}

// Controlla se l'unità operativa esiste già per questa azienda
$query = "SELECT id FROM unita_operativa WHERE azienda_id = ? AND nome_unita_operativa = ?";
$stmt = executeQuery($query, [$azienda_id, $nome_unita_operativa], 'is');

if ($stmt) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'L\'unità operativa esiste già per questa azienda.']);
        $stmt->close();
        exit;
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Errore durante la verifica dell\'unità operativa.']);
    exit;
}

// Inserisci la nuova unità operativa
$query = "INSERT INTO unita_operativa (azienda_id, nome_unita_operativa, descrizione) VALUES (?, ?, ?)";
$descrizione_unita_operativa = ''; // Puoi modificare se necessario

$stmt_insert = executeQuery($query, [$azienda_id, $nome_unita_operativa, $descrizione_unita_operativa], 'iss');

if ($stmt_insert) {
    $unita_id = $mysqli->insert_id;
    $stmt_insert->close();
    echo json_encode([
        'success' => true,
        'message' => 'Unità operativa creata con successo.',
        'unita_id' => $unita_id,
        'unita_nome' => $nome_unita_operativa
    ]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Errore durante la creazione dell\'unità operativa: ' . $mysqli->error]);
    exit;
}
?>
