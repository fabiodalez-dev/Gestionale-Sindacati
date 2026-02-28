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
    header("Location: aziende.php?delete_error=" . urlencode("Token CSRF non valido."));
    exit;
}

$azienda_id = intval($_POST['id'] ?? 0);

if ($azienda_id <= 0) {
    header("Location: aziende.php?delete_error=" . urlencode("ID azienda non valido."));
    exit();
}

// Controlla se ci sono lavoratori associati a questa azienda
$query = "SELECT COUNT(*) AS count FROM lavoratori WHERE azienda_id = ?";
$stmt = executeQuery($query, [$azienda_id], 'i');
if ($stmt === false) {
    header("Location: aziende.php?delete_error=" . urlencode("Errore durante il controllo dei lavoratori associati."));
    exit();
}
$result = $stmt->get_result();
if (!$result) {
    header("Location: aziende.php?delete_error=" . urlencode("Errore durante il controllo dei lavoratori associati."));
    exit();
}
$count = $result->fetch_assoc()['count'];

if ($count > 0) {
    // Non è possibile eliminare l'azienda se ci sono lavoratori associati
    $error = "Non è possibile eliminare l'azienda perché ci sono lavoratori associati. Alcuni di questi potrebbero essere archiviati";
    header("Location: aziende.php?delete_error=" . urlencode($error));
    exit;
}

// Elimina l'azienda dal database
$query = "DELETE FROM aziende WHERE id = ?";
$stmt = executeQuery($query, [$azienda_id], 'i');

if ($stmt && $stmt->affected_rows === 1) {
    header("Location: aziende.php?delete_success=1");
    exit;
} else {
    $error = ($stmt && $stmt->affected_rows === 0)
        ? "Azienda non trovata o già eliminata."
        : "Errore durante l'eliminazione dell'azienda.";
    header("Location: aziende.php?delete_error=" . urlencode($error));
    exit;
}
?>
