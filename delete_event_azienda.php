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

// Recupera e sanitizza i dati
$evento_id = isset($_POST['id']) ? intval($_POST['id']) : null;
$azienda_id = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : null;
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

// Verifica il token CSRF
if (!verifyCsrfToken($csrf_token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token CSRF non valido.']);
    exit;
}

// Verifica che i campi obbligatori siano presenti
if ($evento_id === null || $azienda_id === null) {
    echo json_encode(['success' => false, 'message' => 'Dati mancanti.']);
    exit;
}

// Verifica che l'evento appartenga all'azienda
$query = "SELECT * FROM calendario_lavoratori WHERE id = ? AND azienda_id = ? AND is_company_event = 1";
$params = [$evento_id, $azienda_id];
$types = 'ii';

$stmt = executeQuery($query, $params, $types);
if ($stmt === false || $stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Evento non trovato o non autorizzato.']);
    exit;
}

// Elimina l'evento
$delete_query = "DELETE FROM calendario_lavoratori WHERE id = ?";
$delete_params = [$evento_id];
$delete_types = 'i';

$delete_stmt = executeQuery($delete_query, $delete_params, $delete_types);

if ($delete_stmt) {
    echo json_encode(['success' => true, 'message' => 'Evento eliminato con successo.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione dell\'evento.']);
}
?>
