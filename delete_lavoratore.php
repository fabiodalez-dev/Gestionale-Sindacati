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
    die('Token CSRF non valido.');
}

$lavoratore_id = intval($_POST['id'] ?? 0);

if ($lavoratore_id <= 0) {
    header("Location: lavoratori.php?delete_error=" . urlencode("ID lavoratore non valido."));
    exit();
}

// Elimina il lavoratore dal database
$query = "DELETE FROM lavoratori WHERE id = ?";
$stmt = executeQuery($query, [$lavoratore_id], 'i');

if ($stmt) {
    // Redirect con messaggio di successo
    header("Location: lavoratori.php?delete_success=1");
    exit;
} else {
    // Redirect con messaggio di errore
    $error = "Errore durante l'eliminazione del lavoratore.";
    header("Location: lavoratori.php?delete_error=" . urlencode($error));
    exit;
}
?>
