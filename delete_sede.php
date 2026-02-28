<?php
require 'config.php';
checkLogin();
checkUserRole('admin');

// Richiede metodo POST per operazioni di eliminazione
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: sedi.php");
    exit();
}

// Verifica token CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    header("Location: sedi.php?delete_error=" . urlencode("Token CSRF non valido."));
    exit;
}

$sede_id = intval($_POST['id'] ?? 0);

if ($sede_id <= 0) {
    header("Location: sedi.php?delete_error=" . urlencode("ID sede non valido."));
    exit();
}

// Rimuovi il riferimento alla sede dai lavoratori associati
$updateStmt = executeQuery("UPDATE lavoratori SET sede_id = NULL WHERE sede_id = ?", [$sede_id], 'i');
if ($updateStmt === false) {
    header("Location: sedi.php?delete_error=" . urlencode("Errore durante l'aggiornamento dei lavoratori associati."));
    exit();
}

// Elimina la sede dal database
$query = "DELETE FROM sedi WHERE id = ?";
$stmt = executeQuery($query, [$sede_id], 'i');

if ($stmt) {
    header("Location: sedi.php?delete_success=1");
    exit;
} else {
    header("Location: sedi.php?delete_error=" . urlencode("Errore durante l'eliminazione della sede."));
    exit;
}
?>
