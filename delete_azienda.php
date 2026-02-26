<?php
require 'config.php';
checkLogin();

if (isset($_GET['id'])) {
    $azienda_id = intval($_GET['id']);

    // Controlla se ci sono lavoratori associati a questa azienda
    $query = "SELECT COUNT(*) AS count FROM lavoratori WHERE azienda_id = ?";
    $stmt = executeQuery($query, [$azienda_id], 'i');
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];

    if ($count > 0) {
        // Non è possibile eliminare l'azienda se ci sono lavoratori associati
        $error = "Non è possibile eliminare l'azienda perché ci sono lavoratori associati. Alcuni di questi potrebbero essere archiviati";
        header("Location: aziende.php?delete_error=" . urlencode($error));
        exit;
    }

    // Elimina l'azienda dal database
    $query = "DELETE FROM aziende WHERE id = ?";
    $stmt = executeQuery($query, [$azienda_id], 'i');

    if ($stmt) {
        // Redirect con messaggio di successo
        header("Location: aziende.php?delete_success=1");
        exit;
    } else {
        // Redirect con messaggio di errore
        $error = "Errore durante l'eliminazione dell'azienda.";
        header("Location: aziende.php?delete_error=" . urlencode($error));
        exit;
    }
} else {
    echo "ID azienda non specificato.";
    exit;
}
?>
