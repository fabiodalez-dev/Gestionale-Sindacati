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

$rawId = $_POST['id'] ?? '';
if (!is_scalar($rawId) || !ctype_digit((string)$rawId)) {
    header("Location: lavoratori.php?delete_error=" . urlencode("ID lavoratore non valido."));
    exit();
}
$lavoratore_id = intval($rawId);

if ($lavoratore_id <= 0) {
    header("Location: lavoratori.php?delete_error=" . urlencode("ID lavoratore non valido."));
    exit();
}

// Elimina lavoratore e record correlati in transazione
$mysqli->begin_transaction();
try {
    // Raccogli percorsi file da eliminare dopo il commit
    $filesToDelete = [];
    $uploadsDir = realpath(__DIR__ . '/uploads');
    $docStmt = executeQuery("SELECT percorso_documento FROM documenti_lavoratori WHERE lavoratore_id = ?", [$lavoratore_id], 'i');
    if ($docStmt !== false) {
        $docResult = $docStmt->get_result();
        while ($docRow = $docResult->fetch_assoc()) {
            if ($uploadsDir !== false) {
                $candidatePath = $uploadsDir . DIRECTORY_SEPARATOR . ltrim($docRow['percorso_documento'], '/\\');
                $resolvedPath = realpath($candidatePath);
                if ($resolvedPath !== false && strpos($resolvedPath, $uploadsDir . DIRECTORY_SEPARATOR) === 0 && is_file($resolvedPath)) {
                    $filesToDelete[] = $resolvedPath;
                } elseif ($resolvedPath !== false) {
                    error_log("Path traversal bloccato durante eliminazione lavoratore ID $lavoratore_id: " . basename($docRow['percorso_documento']));
                }
            }
        }
    }

    // Elimina record correlati — fail-closed: interrompi su errore
    $delStmt = executeQuery("DELETE FROM documenti_lavoratori WHERE lavoratore_id = ?", [$lavoratore_id], 'i');
    if ($delStmt === false) { throw new Exception("Errore eliminazione documenti_lavoratori."); }

    $delStmt = executeQuery("DELETE FROM pagamenti_quote WHERE iscrizione_id IN (SELECT id FROM iscrizioni WHERE lavoratore_id = ?)", [$lavoratore_id], 'i');
    if ($delStmt === false) { throw new Exception("Errore eliminazione pagamenti_quote."); }

    $delStmt = executeQuery("DELETE FROM iscrizioni WHERE lavoratore_id = ?", [$lavoratore_id], 'i');
    if ($delStmt === false) { throw new Exception("Errore eliminazione iscrizioni."); }

    $delStmt = executeQuery("DELETE FROM storico_aziende_lavoratori WHERE lavoratore_id = ?", [$lavoratore_id], 'i');
    if ($delStmt === false) { throw new Exception("Errore eliminazione storico_aziende_lavoratori."); }

    $delStmt = executeQuery("DELETE FROM calendario_lavoratori WHERE lavoratore_id = ?", [$lavoratore_id], 'i');
    if ($delStmt === false) { throw new Exception("Errore eliminazione calendario_lavoratori."); }

    // Elimina il lavoratore
    $stmt = executeQuery("DELETE FROM lavoratori WHERE id = ?", [$lavoratore_id], 'i');

    if ($stmt && $stmt->affected_rows === 1) {
        $mysqli->commit();
        // Elimina file fisici solo dopo commit riuscito
        foreach ($filesToDelete as $filePath) {
            if (is_file($filePath) && !unlink($filePath)) {
                error_log("Impossibile eliminare file: " . basename($filePath));
            }
        }
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
