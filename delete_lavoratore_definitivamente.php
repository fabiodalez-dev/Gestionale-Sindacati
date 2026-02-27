<?php
// delete_lavoratore_definitivamente.php

require_once 'config.php';
checkLogin();
checkUserRole('admin');

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        header("Location: archived_lavoratori.php?delete_error=Token CSRF non valido.");
        exit;
    }

    // Recupera e sanitizza l'ID del lavoratore
    $lavoratore_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($lavoratore_id > 0) {
        global $mysqli;
        $mysqli->begin_transaction();
        try {
            // Prima elimina eventuali dati correlati (es. documenti, iscrizioni, etc.)
            // Eliminazione dei documenti associati
            $deleteDocumentsQuery = "DELETE FROM documenti_lavoratori WHERE lavoratore_id = ?";
            $stmtDocs = executeQuery($deleteDocumentsQuery, [$lavoratore_id], 'i');
            if ($stmtDocs === false) {
                throw new Exception("Errore eliminazione documenti lavoratore");
            }

            // Eliminazione delle iscrizioni
            $deleteIscrizioniQuery = "DELETE FROM iscrizioni WHERE lavoratore_id = ?";
            $stmtIscrizioni = executeQuery($deleteIscrizioniQuery, [$lavoratore_id], 'i');
            if ($stmtIscrizioni === false) {
                throw new Exception("Errore eliminazione iscrizioni lavoratore");
            }

            // Eliminazione del lavoratore
            $deleteLavoratoreQuery = "DELETE FROM lavoratori WHERE id = ?";
            $stmt = executeQuery($deleteLavoratoreQuery, [$lavoratore_id], 'i');
            if ($stmt === false) {
                throw new Exception("Errore eliminazione lavoratore");
            }
            if ($stmt->affected_rows !== 1) {
                throw new Exception("Lavoratore non trovato o già eliminato");
            }

            $mysqli->commit();
            header("Location: archived_lavoratori.php?delete_success=1");
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            error_log("Errore eliminazione definitiva lavoratore: " . $e->getMessage());
            header("Location: archived_lavoratori.php?delete_error=Errore durante l'eliminazione definitiva.");
            exit;
        }
    } else {
        header("Location: archived_lavoratori.php?delete_error=ID lavoratore non valido.");
        exit;
    }
} else {
    header("Location: archived_lavoratori.php?delete_error=Metodo di richiesta non valido.");
    exit;
}
?>
