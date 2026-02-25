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
$azienda_id = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : null;
$titolo = isset($_POST['titolo']) ? sanitizeInput($_POST['titolo']) : '';
$descrizione = isset($_POST['descrizione']) ? sanitizeInput($_POST['descrizione']) : '';
$start = isset($_POST['start']) ? sanitizeInput($_POST['start']) : '';
$end = isset($_POST['end']) ? sanitizeInput($_POST['end']) : '';
$all_day = isset($_POST['all_day']) ? intval($_POST['all_day']) : 0;

// Verifica che i campi obbligatori siano compilati
if (empty($azienda_id) || empty($titolo) || empty($start)) {
    echo json_encode(['success' => false, 'message' => 'Compila tutti i campi obbligatori.']);
    exit;
}

// Validazione delle date
$start_datetime = date('Y-m-d H:i:s', strtotime($start));
$end_datetime = !empty($end) ? date('Y-m-d H:i:s', strtotime($end)) : null;

// Inserisci l'evento nella tabella calendario_lavoratori
$query = "INSERT INTO calendario_lavoratori (lavoratore_id, azienda_id, is_company_event, titolo, descrizione, start, end, all_day) VALUES (NULL, ?, 1, ?, ?, ?, ?, ?)";
$params = [$azienda_id, $titolo, $descrizione, $start_datetime, $end_datetime, $all_day];
$types = 'issssi';

$stmt = executeQuery($query, $params, $types);

if ($stmt) {
    echo json_encode(['success' => true, 'message' => 'Evento aziendale creato con successo.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore durante la creazione dell\'evento: ' . $mysqli->error]);
}
?>
