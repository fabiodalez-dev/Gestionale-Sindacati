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

$documento_id = intval($_POST['id'] ?? 0);
$lavoratore_id = intval($_POST['lavoratore_id'] ?? 0);

if ($documento_id <= 0 || $lavoratore_id <= 0) {
    header("Location: lavoratori.php?delete_error=" . urlencode("Parametri mancanti."));
    exit();
}

// Recupera il percorso del documento
$query = "SELECT percorso_documento FROM documenti_lavoratori WHERE id = ? AND lavoratore_id = ?";
$stmt = executeQuery($query, [$documento_id, $lavoratore_id], 'ii');
if ($stmt === false) {
    header("Location: lavoratore.php?id=" . $lavoratore_id . "&delete_error=" . urlencode("Errore nella query."));
    exit;
}
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $documento = $result->fetch_assoc();
    $percorso = $documento['percorso_documento'];

    // Elimina il file dal server con validazione path traversal
    $uploadsDir = realpath(__DIR__ . '/uploads');
    if ($uploadsDir === false) {
        header("Location: lavoratore.php?id=" . $lavoratore_id . "&delete_error=" . urlencode("Directory upload non disponibile."));
        exit;
    }
    $fullPath = realpath($uploadsDir . DIRECTORY_SEPARATOR . ltrim($percorso, '/\\'));
    if ($fullPath === false || strpos($fullPath, $uploadsDir . DIRECTORY_SEPARATOR) !== 0) {
        header("Location: lavoratore.php?id=" . $lavoratore_id . "&delete_error=" . urlencode("Percorso documento non valido."));
        exit;
    }
    if (is_file($fullPath) && !unlink($fullPath)) {
        header("Location: lavoratore.php?id=" . $lavoratore_id . "&delete_error=" . urlencode("Impossibile eliminare il file dal server."));
        exit;
    }

    // Elimina il record dal database
    $delete_query = "DELETE FROM documenti_lavoratori WHERE id = ? AND lavoratore_id = ?";
    $delete_stmt = executeQuery($delete_query, [$documento_id, $lavoratore_id], 'ii');

    if ($delete_stmt) {
        header("Location: lavoratore.php?id=" . $lavoratore_id . "&delete_success=1");
        exit;
    } else {
        header("Location: lavoratore.php?id=" . $lavoratore_id . "&delete_error=" . urlencode("Errore durante l'eliminazione del documento."));
        exit;
    }
} else {
    header("Location: lavoratore.php?id=" . $lavoratore_id . "&delete_error=" . urlencode("Documento non trovato o non autorizzato."));
    exit;
}
?>
