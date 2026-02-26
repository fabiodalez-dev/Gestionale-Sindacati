<?php
require_once 'config.php';
checkLogin();

// Controlla che la richiesta sia di tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Accesso non autorizzato.";
    exit;
}

// Verifica la presenza dei dati necessari
if (!isset($_POST['id'], $_POST['csrf_token'])) {
    echo "Dati mancanti.";
    exit;
}

$lavoratore_id = intval($_POST['id']);


// Recupera lo stato attuale della colonna "vertenze" per il lavoratore
$query = "SELECT vertenze FROM lavoratori WHERE id = ?";
$stmt = executeQuery($query, [$lavoratore_id], 'i');
if ($stmt === false) {
    echo "Errore nella query di selezione.";
    exit;
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Lavoratore non trovato.";
    exit;
}

$row = $result->fetch_assoc();
$current_vertenze = $row['vertenze'];

// Inverte il valore: se è 1 lo imposta a 0, altrimenti a 1
$new_vertenze = ($current_vertenze == 1) ? 0 : 1;

// Aggiorna il record nella tabella "lavoratori"
$update_query = "UPDATE lavoratori SET vertenze = ? WHERE id = ?";
$stmt_update = executeQuery($update_query, [$new_vertenze, $lavoratore_id], 'ii');
if ($stmt_update === false) {
    echo "Errore nell'aggiornamento dello stato delle vertenze.";
    exit;
}

// Reindirizza alla pagina del lavoratore con un messaggio di successo o errore
if ($stmt_update->affected_rows > 0) {
    header("Location: lavoratore.php?id=" . $lavoratore_id . "&update_success=1");
} else {
    header("Location: lavoratore.php?id=" . $lavoratore_id . "&update_error=Impossibile aggiornare lo stato delle vertenze.");
}
exit;
?>
