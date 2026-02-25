<?php
require 'config.php';
checkLogin();

// Verifica che l'ID del documento e dell'azienda siano forniti
if (isset($_GET['id']) && isset($_GET['lavoratore_id'])) {
    $documento_id = intval($_GET['id']);
    $lavoratore_id = intval($_GET['lavoratore_id']);

    // Recupera il percorso del documento
    $query = "SELECT percorso_documento FROM documenti_lavoratori WHERE id = ? AND lavoratore_id = ?";
    $stmt = executeQuery($query, [$documento_id, $lavoratore_id], 'ii');
    if ($stmt === false) {
        echo "Errore nella query.";
        exit;
    }
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $documento = $result->fetch_assoc();
        $percorso = $documento['percorso_documento'];

        // Elimina il file dal server
        if (file_exists("uploads/" . $percorso)) {
            unlink("uploads/" . $percorso);
        }

        // Elimina il record dal database
        $delete_query = "DELETE FROM documenti_lavoratori WHERE id = ? AND lavoratore_id = ?";
        $delete_stmt = executeQuery($delete_query, [$documento_id, $lavoratore_id], 'ii');

        if ($delete_stmt) {
            header("Location: lavoratore.php?id=" . $lavoratore_id . "&delete_success=1");
            exit;
        } else {
            echo "Errore durante l'eliminazione del documento.";
            exit;
        }
    } else {
        echo "Documento non trovato o non autorizzato.";
        exit;
    }
} else {
    echo "Parametri mancanti.";
    exit;
}
?>
