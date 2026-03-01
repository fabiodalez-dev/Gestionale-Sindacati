<?php
require 'config.php';
checkLogin();

// Imposta l'header per la risposta JSON
header('Content-Type: application/json');

// Log dell'ora della richiesta
error_log("delete_event.php request ricevuta alle " . date('Y-m-d H:i:s'));

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Metodo non consentito: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405); // Metodo Non Consentito
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito.']);
    exit;
}

// Verifica del token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    error_log("Token CSRF non valido in delete_event.php");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF mancante o non valido.']);
    exit;
}

// Recupera e sanitizza i dati
$id = isset($_POST['id']) ? intval($_POST['id']) : null;
$lavoratore_id = isset($_POST['lavoratore_id']) ? intval($_POST['lavoratore_id']) : null;
$delete_for_all = isset($_POST['delete_for_all']) ? intval($_POST['delete_for_all']) : 0;

// Log dei parametri ricevuti (senza dati sensibili)
error_log("delete_event.php: id='$id', lavoratore_id='$lavoratore_id', delete_for_all='$delete_for_all'");

// Verifica che l'ID dell'evento sia presente
if ($id === null) {
    error_log("ID evento mancante.");
    echo json_encode(['success' => false, 'error' => 'ID evento mancante.']);
    exit;
}

// Recupera i dettagli dell'evento
$query = "SELECT * FROM calendario_lavoratori WHERE id = ?";
$stmt = executeQuery($query, [$id], 'i');

if ($stmt === false) {
    error_log("Errore nella preparazione della query di selezione: " . $mysqli->error);
    echo json_encode(['success' => false, 'error' => 'Errore nella preparazione della query di selezione.']);
    exit;
}

$result = $stmt->get_result();

if ($result === false || $result->num_rows === 0) {
    error_log("Evento non trovato. ID evento: $id");
    echo json_encode(['success' => false, 'error' => 'Evento non trovato.']);
    $stmt->close();
    exit;
}

$evento = $result->fetch_assoc();

// Libera il risultato e chiudi lo statement di selezione
$stmt->free_result();
$stmt->close();

// Gestione della cancellazione per tutti gli eventi aziendali
if ($delete_for_all === 1 && intval($evento['is_company_event']) === 1) {
    // Solo admin può eliminare tutti gli eventi aziendali
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Solo gli amministratori possono eliminare tutti gli eventi aziendali.']);
        exit;
    }
    // Elimina tutti gli eventi aziendali per questa azienda
    $delete_query = "DELETE FROM calendario_lavoratori WHERE azienda_id = ? AND is_company_event = 1";
    $params = [intval($evento['azienda_id'])];
    $types = 'i';
    $delete_stmt = executeQuery($delete_query, $params, $types);
    
    if ($delete_stmt === false) {
        error_log("Errore nell'eliminazione degli eventi aziendali: " . $mysqli->error);
        echo json_encode(['success' => false, 'error' => 'Errore nell\'eliminazione degli eventi aziendali.']);
        exit;
    }
    
    // Chiudi lo statement di eliminazione
    $delete_stmt->close();
    
    error_log("Tutti gli eventi aziendali per azienda_id=" . intval($evento['azienda_id']) . " sono stati eliminati.");
    echo json_encode(['success' => true, 'message' => 'Tutti gli eventi aziendali sono stati eliminati con successo.']);
    exit;
}

// Altrimenti, elimina l'evento singolo

// Verifica se l'evento è aziendale ma delete_for_all non è impostato
if ($evento['is_company_event'] === '1') {
    error_log("Tentativo di eliminare un evento aziendale senza specificare delete_for_all. ID evento: $id");
    echo json_encode(['success' => false, 'error' => 'Impossibile eliminare un evento aziendale senza specificare delete_for_all.']);
    exit;
}

// Verifica che l'evento appartenga al lavoratore specificato
if ($evento['lavoratore_id'] != $lavoratore_id) {
    error_log("Tentativo di eliminare un evento appartenente a un altro lavoratore. ID evento: $id, Lavoratore ID: $lavoratore_id");
    echo json_encode(['success' => false, 'error' => 'Impossibile eliminare un evento appartenente a un altro lavoratore.']);
    exit;
}

// Elimina l'evento singolo
$delete_query = "DELETE FROM calendario_lavoratori WHERE id = ? AND lavoratore_id = ?";
$params = [$id, $lavoratore_id];
$types = 'ii';
$delete_stmt = executeQuery($delete_query, $params, $types);

if ($delete_stmt === false) {
    error_log("Errore nella preparazione della query di eliminazione evento singolo: " . $mysqli->error);
    echo json_encode(['success' => false, 'error' => 'Errore nella preparazione della query di eliminazione evento singolo.']);
    exit;
}

error_log("Evento singolo eliminato con successo. ID evento: $id");
echo json_encode(['success' => true, 'message' => 'Evento eliminato con successo.']);

// Chiudi lo statement di eliminazione
$delete_stmt->close();
exit;
?>
