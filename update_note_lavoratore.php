<?php
require 'config.php';
checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $note = $_POST['note'];

    // Aggiorna le note nel database
    $query = "UPDATE lavoratori SET note = ? WHERE id = ?";
    $params = [$note, $id];
    $types = 'si';

    $stmt = executeQuery($query, $params, $types);

    if ($stmt) {
        // Reindirizza alla scheda anagrafica con un messaggio di successo
        header("Location: lavoratore.php?id=$id&note_update_success=1");
        exit;
    } else {
        $error = "Errore durante l'aggiornamento delle note.";
        header("Location: lavoratore.php?id=$id&note_update_error=" . urlencode($error));
        exit;
    }
} else {
    echo "Richiesta non valida.";
}
?>
