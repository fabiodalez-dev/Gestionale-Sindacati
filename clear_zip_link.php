<?php
require_once 'config.php';
// Avvia la sessione solo se non è già attiva
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json');

// Verifica se esiste il link in sessione
if (isset($_SESSION['zip_link'])) {
    $zipLink = $_SESSION['zip_link'];
    // Estrai il nome del file ZIP dall'URL
    $zipFilename = basename($zipLink);
    // Costruisci il percorso completo sul filesystem
    $zipFilepath = __DIR__ . '/downloads/' . $zipFilename;
    
    // Se il file esiste, proviamo a cancellarlo
    if (file_exists($zipFilepath)) {
        if (!unlink($zipFilepath)) {
            // Se l'unlink fallisce, restituisci un errore JSON
            echo json_encode([
                'success' => false,
                'message' => 'Impossibile cancellare il file ZIP dal server.'
            ]);
            exit;
        }
    }
    
    // Rimuovi il link dalla sessione
    unset($_SESSION['zip_link']);
}

echo json_encode(['success' => true]);
?>
