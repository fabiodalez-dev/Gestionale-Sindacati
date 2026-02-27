<?php
header('Content-Type: application/json');
require_once 'config.php';
checkLogin();

// Verifica CSRF token
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Token CSRF non valido.'
    ]);
    exit;
}

if (isset($_POST['doc_id']) && isset($_POST['description'])) {
    $docId = intval($_POST['doc_id']);
    $newDescription = trim($_POST['description']);

    // Aggiorna la tabella documenti_lavoratori (non "documenti")
    $stmt = $mysqli->prepare("UPDATE documenti_lavoratori SET descrizione_documento = ? WHERE id = ?");
    if ($stmt === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Errore nella preparazione della query: ' . $mysqli->error
        ]);
        exit;
    }
    $stmt->bind_param("si", $newDescription, $docId);
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'new_description' => htmlspecialchars($newDescription)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Impossibile aggiornare la descrizione: ' . $stmt->error
        ]);
    }
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Dati mancanti.'
    ]);
}
?>
