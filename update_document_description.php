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

if (isset($_POST['doc_id']) && isset($_POST['description'])) {
    $docId = intval($_POST['doc_id']);
    $newDescription = trim($_POST['description']);

    if ($docId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID documento non valido.']);
        exit;
    }

    // Verifica che il documento esista e controlla autorizzazione
    $checkStmt = executeQuery("SELECT dl.id, dl.lavoratore_id FROM documenti_lavoratori dl WHERE dl.id = ?", [$docId], 'i');
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

    // Verifica permessi: solo admin o operatori con accesso alla sede del lavoratore.
    // Operatori con sede_id = NULL hanno accesso globale a tutte le sedi.
    // L'accesso viene negato solo quando sede_id dell'utente è impostato e non corrisponde
    // alla sede_id del lavoratore associato al documento.
    $currentRole = $_SESSION['user_role'] ?? ($_SESSION['user']['role'] ?? null);
    if ($currentRole !== 'admin') {
        $docRow = $checkResult->fetch_assoc();
        $sedeCheck = executeQuery(
            "SELECT l.sede_id FROM lavoratori l WHERE l.id = ?",
            [$docRow['lavoratore_id']], 'i'
        );
        if ($sedeCheck === false) {
            // Fail-closed: se la query fallisce, nega l'accesso
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Errore interno durante la verifica dei permessi.']);
            exit;
        }
        $lavSede = $sedeCheck->get_result()->fetch_assoc();
        if (!$lavSede) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Non autorizzato.']);
            exit;
        }
        $userSedeId = $_SESSION['user']['sede_id'] ?? null;
        if ($userSedeId !== null && (int)$lavSede['sede_id'] !== (int)$userSedeId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Non autorizzato.']);
            exit;
        }
    }

    // Aggiorna la tabella documenti_lavoratori (non "documenti")
    $stmt = executeQuery("UPDATE documenti_lavoratori SET descrizione_documento = ? WHERE id = ?", [$newDescription, $docId], 'si');
    if ($stmt !== false && $stmt->affected_rows >= 0) {
        echo json_encode([
            'success' => true,
            'new_description' => $newDescription
        ]);
    } else {
        http_response_code(500);
        error_log("Errore aggiornamento descrizione documento ID $docId");
        echo json_encode([
            'success' => false,
            'message' => 'Impossibile aggiornare la descrizione.'
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Dati mancanti.'
    ]);
}
?>
