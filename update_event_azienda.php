<?php
require 'config.php';
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
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token CSRF mancante o non valido.']);
    exit;
}

// Recupera e sanitizza i dati
$id = isset($_POST['id']) ? intval($_POST['id']) : null;
$titolo = isset($_POST['titolo']) ? sanitizeInput($_POST['titolo']) : '';
$descrizione = isset($_POST['descrizione']) ? sanitizeInput($_POST['descrizione']) : '';
$start = isset($_POST['start']) ? sanitizeInput($_POST['start']) : '';
$end = isset($_POST['end']) ? sanitizeInput($_POST['end']) : '';
$all_day = isset($_POST['all_day']) ? intval($_POST['all_day']) : 0;
$azienda_id = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : null;

// Verifica che i campi obbligatori siano compilati
if (empty($id) || empty($titolo) || empty($start) || empty($azienda_id)) {
    echo json_encode(['success' => false, 'message' => 'Compila tutti i campi obbligatori.']);
    exit;
}

// Validazione delle date
$start_datetime = date('Y-m-d H:i:s', strtotime($start));
$end_datetime = !empty($end) ? date('Y-m-d H:i:s', strtotime($end)) : null;

// Verifica che l'evento appartenga all'azienda e sia un evento aziendale
$query_check = "SELECT * FROM calendario_lavoratori WHERE id = ? AND azienda_id = ? AND is_company_event = 1";
$params_check = [$id, $azienda_id];
$types_check = 'ii';
$stmt_check = executeQuery($query_check, $params_check, $types_check);

if ($stmt_check === false || $stmt_check->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Evento non trovato o non autorizzato.']);
    exit;
}

// Aggiorna l'evento
$query_update = "UPDATE calendario_lavoratori SET titolo = ?, descrizione = ?, start = ?, end = ?, all_day = ? WHERE id = ?";
$params_update = [$titolo, $descrizione, $start_datetime, $end_datetime, $all_day, $id];
$types_update = 'sssiii';

$stmt_update = executeQuery($query_update, $params_update, $types_update);

if ($stmt_update) {
    echo json_encode(['success' => true, 'message' => 'Evento aggiornato con successo.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento dell\'evento: ' . $mysqli->error]);
}
?>
