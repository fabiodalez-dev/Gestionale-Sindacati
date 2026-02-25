<?php
header('Content-Type: application/json');
require_once 'config.php';

// Crea una connessione usando i parametri definiti in config.php
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode([
        'success' => false, 
        'message' => 'Errore di connessione: ' . $conn->connect_error
    ]);
    exit;
}


if (isset($_POST['doc_id']) && isset($_POST['description'])) {
    $docId = intval($_POST['doc_id']);
    $newDescription = trim($_POST['description']);

    // Aggiorna la tabella documenti_lavoratori (non "documenti")
    $stmt = $conn->prepare("UPDATE documenti_lavoratori SET descrizione_documento = ? WHERE id = ?");
    if ($stmt === false) {
        echo json_encode([
            'success' => false, 
            'message' => 'Errore nella preparazione della query: ' . $conn->error
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
$conn->close();
?>
