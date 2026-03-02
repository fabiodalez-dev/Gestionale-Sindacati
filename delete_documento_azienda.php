<?php
require 'config.php';
checkLogin();
checkUserRole('admin');

// Richiede metodo POST per operazioni di eliminazione
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: aziende.php");
    exit();
}

// Verifica token CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    header("Location: aziende.php?delete_error=" . urlencode("Token CSRF non valido."));
    exit;
}

$documento_id = intval($_POST['id'] ?? 0);
$azienda_id = intval($_POST['azienda_id'] ?? 0);

if ($documento_id <= 0 || $azienda_id <= 0) {
    header("Location: aziende.php?delete_error=" . urlencode("Parametri mancanti."));
    exit();
}

// Recupera il percorso del documento
$query = "SELECT percorso_documento FROM documenti_aziende WHERE id = ? AND azienda_id = ?";
$stmt = executeQuery($query, [$documento_id, $azienda_id], 'ii');
if ($stmt === false) {
    header("Location: azienda.php?id=" . $azienda_id . "&delete_error=" . urlencode("Errore nella query."));
    exit;
}
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $documento = $result->fetch_assoc();
    $percorso = $documento['percorso_documento'];

    // Elimina il file dal server con validazione path traversal
    $uploadsDir = realpath(__DIR__ . '/uploads');
    $fileDeleteFailed = false;
    if ($uploadsDir !== false) {
        $candidatePath = $uploadsDir . DIRECTORY_SEPARATOR . ltrim($percorso, '/\\');
        $fullPath = realpath($candidatePath);
        if ($fullPath !== false && strpos($fullPath, $uploadsDir . DIRECTORY_SEPARATOR) === 0) {
            if (is_file($fullPath) && !unlink($fullPath)) {
                error_log("Impossibile eliminare il file: " . basename($fullPath));
                $fileDeleteFailed = true;
            }
        } elseif ($fullPath !== false) {
            // Path traversal attempt — non eliminare il file e non procedere
            error_log("Tentativo di path traversal bloccato per documento ID $documento_id");
            header("Location: azienda.php?id=" . $azienda_id . "&delete_error=" . urlencode("Errore di sicurezza durante l'eliminazione."));
            exit;
        }
        // Se realpath restituisce false, il file non esiste più — procedi comunque con il DB
    }

    if ($fileDeleteFailed) {
        header("Location: azienda.php?id=" . $azienda_id . "&delete_error=" . urlencode("Impossibile eliminare il file fisico. Record non rimosso."));
        exit;
    }

    // Elimina il record dal database
    $delete_query = "DELETE FROM documenti_aziende WHERE id = ? AND azienda_id = ?";
    $delete_stmt = executeQuery($delete_query, [$documento_id, $azienda_id], 'ii');

    if ($delete_stmt && $delete_stmt->affected_rows === 1) {
        header("Location: azienda.php?id=" . $azienda_id . "&delete_success=1");
        exit;
    } else {
        $msg = ($delete_stmt && $delete_stmt->affected_rows === 0)
            ? "Documento non trovato o già eliminato."
            : "Errore durante l'eliminazione del documento.";
        header("Location: azienda.php?id=" . $azienda_id . "&delete_error=" . urlencode($msg));
        exit;
    }
} else {
    header("Location: azienda.php?id=" . $azienda_id . "&delete_error=" . urlencode("Documento non trovato o non autorizzato."));
    exit;
}
?>
