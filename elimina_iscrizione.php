<?php
// elimina_iscrizione.php

require 'config.php';
checkLogin();

// Recupera l'ID dell'iscrizione da eliminare
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestione_iscrizioni.php");
    exit;
}

$iscrizione_id = intval($_GET['id']);

// Recupera i dettagli dell'iscrizione per ottenere lavoratore_id e metodo_pagamento
$query = "SELECT lavoratore_id, metodo_pagamento FROM iscrizioni WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $iscrizione_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: gestione_iscrizioni.php");
    exit;
}

$row = $result->fetch_assoc();
$lavoratore_id = $row['lavoratore_id'];
$metodo_pagamento = $row['metodo_pagamento'];
$stmt->close();

// Elimina l'iscrizione
$delete_query = "DELETE FROM iscrizioni WHERE id = ?";
$stmt = $mysqli->prepare($delete_query);
$stmt->bind_param('i', $iscrizione_id);
if ($stmt->execute()) {
    // Controlla se il lavoratore ha altre iscrizioni attive
    $check_query = "SELECT COUNT(*) AS count FROM iscrizioni WHERE lavoratore_id = ? AND (metodo_pagamento IN ('trattenuta in busta paga', 'sepa') OR (metodo_pagamento = 'rinnovo annuale' AND data_fine >= ?))";
    $oggi = date('Y-m-d');
    $stmt_check = $mysqli->prepare($check_query);
    $stmt_check->bind_param('is', $lavoratore_id, $oggi);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $count = $result_check->fetch_assoc()['count'];
    $stmt_check->close();

    if ($count == 0) {
        // Se non ci sono altre iscrizioni attive, aggiorna lo stato 'iscritto' a 0
        $update_lavoratore = "UPDATE lavoratori SET iscritto = 0 WHERE id = ?";
        $stmt_update = $mysqli->prepare($update_lavoratore);
        $stmt_update->bind_param('i', $lavoratore_id);
        $stmt_update->execute();
        $stmt_update->close();
    }

    $success_message = "Iscrizione eliminata con successo.";
} else {
    $error_message = "Errore nell'eliminazione dell'iscrizione: " . $stmt->error;
}
$stmt->close();

// Reindirizza con messaggio di successo o errore
if (isset($success_message)) {
    header("Location: gestione_iscrizioni.php?success=" . urlencode($success_message));
    exit;
} elseif (isset($error_message)) {
    header("Location: gestione_iscrizioni.php?error=" . urlencode($error_message));
    exit;
}
?>
