<?php
require 'config.php';
header('Content-Type: application/json');

// Verifica che i parametri POST siano presenti
if (isset($_POST['doc_id']) && isset($_POST['description'])) {
    $docId = intval($_POST['doc_id']);
    $newDescription = trim($_POST['description']);

    // Query per aggiornare la descrizione nella tabella documenti_aziende
    $query = "UPDATE documenti_aziende SET descrizione_documento = ? WHERE id = ?";
    
    // Utilizza la funzione executeQuery con i parametri corretti:
    // 's' per la stringa e 'i' per l'intero
    $stmt = executeQuery($query, [$newDescription, $docId], 'si');

    if ($stmt) {
        echo json_encode([
            'success' => true,
            'new_description' => htmlspecialchars($newDescription)
        ]);
    } else {
        // In caso di errore, restituisce un messaggio di errore
        echo json_encode([
            'success' => false,
            'message' => 'Errore durante l\'aggiornamento della descrizione: ' . $mysqli->error
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Dati mancanti.'
    ]);
}
?>
