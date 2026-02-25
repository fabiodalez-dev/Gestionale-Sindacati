<?php
require 'config.php';
checkLogin();

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: azienda.php?id=" . $_POST['azienda_id'] . "&unita_update_error=" . urlencode("Metodo di richiesta non valido."));
    exit;
}

// Verifica il token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    header("Location: azienda.php?id=" . $_POST['azienda_id'] . "&unita_update_error=" . urlencode("Token CSRF mancante o non valido."));
    exit;
}

// Recupera e sanitizza i dati
$unita_operativa_id = isset($_POST['unita_operativa_id']) ? intval($_POST['unita_operativa_id']) : 0;
$azienda_id = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : 0;
$nome_unita_operativa = isset($_POST['nome_unita_operativa']) ? sanitizeInput($_POST['nome_unita_operativa']) : '';
$descrizione_unita_operativa = isset($_POST['descrizione_unita_operativa']) ? sanitizeInput($_POST['descrizione_unita_operativa']) : '';

// Validazione dei dati
if ($unita_operativa_id <= 0 || $azienda_id <= 0 || empty($nome_unita_operativa)) {
    header("Location: azienda.php?id=$azienda_id&unita_update_error=" . urlencode("Dati mancanti o non validi."));
    exit;
}

// Verifica se l'unità operativa esiste già (escludendo quella corrente)
$check_query = "SELECT id FROM unita_operativa WHERE azienda_id = ? AND nome_unita_operativa = ? AND id != ?";
$check_stmt = executeQuery($check_query, [$azienda_id, $nome_unita_operativa, $unita_operativa_id], 'isi');
if ($check_stmt && $check_stmt->get_result()->num_rows > 0) {
    header("Location: azienda.php?id=$azienda_id&unita_update_error=" . urlencode("Un'altra unità operativa con questo nome esiste già."));
    exit;
}

// Aggiorna l'unità operativa
$update_query = "UPDATE unita_operativa SET nome_unita_operativa = ?, descrizione = ? WHERE id = ?";
$update_stmt = executeQuery($update_query, [$nome_unita_operativa, $descrizione_unita_operativa, $unita_operativa_id], 'ssi');

if ($update_stmt) {
    header("Location: azienda.php?id=$azienda_id&unita_update_success=1");
    exit;
} else {
    error_log("Errore durante l'aggiornamento dell'unità operativa: " . $mysqli->error);
    header("Location: azienda.php?id=$azienda_id&unita_update_error=" . urlencode("Errore durante l'aggiornamento dell'unità operativa."));
    exit;
}
?>
