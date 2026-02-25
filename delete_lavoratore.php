<?php
require 'config.php';
checkLogin();

if (isset($_GET['id'])) {
    $lavoratore_id = intval($_GET['id']);

    // Elimina il lavoratore dal database
    $query = "DELETE FROM lavoratori WHERE id = ?";
    $stmt = executeQuery($query, [$lavoratore_id], 'i');

    if ($stmt) {
        // Redirect con messaggio di successo
        header("Location: lavoratori.php?delete_success=1");
        exit;
    } else {
        // Redirect con messaggio di errore
        $error = "Errore durante l'eliminazione del lavoratore.";
        header("Location: lavoratori.php?delete_error=" . urlencode($error));
        exit;
    }
} else {
    echo "ID lavoratore non specificato.";
    exit;
}
?>
