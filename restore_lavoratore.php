<?php
// restore_lavoratore.php

require_once 'config.php';
checkLogin();

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        header("Location: archived_lavoratori.php?restore_error=Token CSRF non valido.");
        exit;
    }

    // Recupera e sanitizza l'ID del lavoratore
    $lavoratore_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($lavoratore_id > 0) {
        // Aggiorna la colonna 'archiviato' a 0
        $query = "UPDATE lavoratori SET archiviato = 0 WHERE id = ?";
        $stmt = executeQuery($query, [$lavoratore_id], 'i');
        if ($stmt !== false) {
            header("Location: archived_lavoratori.php?restore_success=1");
            exit;
        } else {
            header("Location: archived_lavoratori.php?restore_error=Errore durante il ripristino.");
            exit;
        }
    } else {
        header("Location: archived_lavoratori.php?restore_error=ID lavoratore non valido.");
        exit;
    }
} else {
    header("Location: archived_lavoratori.php?restore_error=Metodo di richiesta non valido.");
    exit;
}
?>
