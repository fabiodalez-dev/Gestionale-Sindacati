<?php
require 'config.php';
checkLogin();
checkUserRole('admin');

// Richiede metodo POST per operazioni di eliminazione
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: lavoratori.php");
    exit();
}

// Verifica token CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    header("Location: lavoratori.php?delete_error=" . urlencode("Token CSRF non valido."));
    exit;
}

$lavoratore_id = intval($_POST['id'] ?? 0);

if ($lavoratore_id <= 0) {
    header("Location: lavoratori.php?delete_error=" . urlencode("ID lavoratore non valido."));
    exit();
}

// Elimina lavoratore e record correlati in transazione
$mysqli->begin_transaction();
try {
    // Elimina documenti fisici associati
    $docStmt = executeQuery("SELECT percorso_documento FROM documenti_lavoratori WHERE lavoratore_id = ?", [$lavoratore_id], 'i');
    if ($docStmt !== false) {
        $docResult = $docStmt->get_result();
        while ($docRow = $docResult->fetch_assoc()) {
            $filePath = __DIR__ . '/uploads/' . $docRow['percorso_documento'];
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }

    // Elimina record correlati
    executeQuery("DELETE FROM documenti_lavoratori WHERE lavoratore_id = ?", [$lavoratore_id], 'i');
    executeQuery("DELETE FROM pagamenti_quote WHERE iscrizione_id IN (SELECT id FROM iscrizioni WHERE lavoratore_id = ?)", [$lavoratore_id], 'i');
    executeQuery("DELETE FROM iscrizioni WHERE lavoratore_id = ?", [$lavoratore_id], 'i');
    executeQuery("DELETE FROM storico_aziende_lavoratori WHERE lavoratore_id = ?", [$lavoratore_id], 'i');
    executeQuery("DELETE FROM calendario_lavoratori WHERE lavoratore_id = ?", [$lavoratore_id], 'i');

    // Elimina il lavoratore
    $stmt = executeQuery("DELETE FROM lavoratori WHERE id = ?", [$lavoratore_id], 'i');

    if ($stmt && $stmt->affected_rows === 1) {
        $mysqli->commit();
        header("Location: lavoratori.php?delete_success=1");
        exit;
    } else {
        $mysqli->rollback();
        $error = ($stmt && $stmt->affected_rows === 0)
            ? "Lavoratore non trovato o già eliminato."
            : "Errore durante l'eliminazione del lavoratore.";
        header("Location: lavoratori.php?delete_error=" . urlencode($error));
        exit;
    }
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Errore eliminazione lavoratore ID $lavoratore_id: " . $e->getMessage());
    header("Location: lavoratori.php?delete_error=" . urlencode("Errore durante l'eliminazione del lavoratore."));
    exit;
}
?>
