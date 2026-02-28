<?php
header('Content-Type: application/json');
require_once 'config.php';
checkLogin();

// Verifica CSRF token
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Token CSRF non valido.'
    ]);
    exit;
}

// Verifica che i parametri POST siano presenti
if (isset($_POST['doc_id']) && isset($_POST['description'])) {
    $docId = intval($_POST['doc_id']);
    $newDescription = trim($_POST['description']);

    if ($docId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID documento non valido.']);
        exit;
    }

    // Verifica che il documento esista
    $checkStmt = executeQuery("SELECT id FROM documenti_aziende WHERE id = ?", [$docId], 'i');
    if ($checkStmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Errore interno durante la verifica del documento.']);
        exit;
    }
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Documento non trovato.']);
        exit;
    }

    // Query per aggiornare la descrizione nella tabella documenti_aziende
    $stmt = executeQuery("UPDATE documenti_aziende SET descrizione_documento = ? WHERE id = ?", [$newDescription, $docId], 'si');

    if ($stmt !== false && $stmt->affected_rows >= 0) {
        echo json_encode([
            'success' => true,
            'new_description' => htmlspecialchars($newDescription)
        ]);
    } else {
        error_log("Errore aggiornamento descrizione documento azienda ID $docId");
        echo json_encode([
            'success' => false,
            'message' => 'Impossibile aggiornare la descrizione.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Dati mancanti.'
    ]);
}
?>
