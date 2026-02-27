<?php
require 'config.php';
checkLogin();
checkUserRole('admin');

// Richiede metodo POST per operazioni di eliminazione
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header("Location: aziende.php");
    exit;
}

// Verifica token CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    header("Location: aziende.php?unita_delete_error=" . urlencode("Richiesta non valida."));
    exit;
}

$unita_operativa_id = intval($_POST['id'] ?? 0);
$azienda_id = intval($_POST['azienda_id'] ?? 0);

if ($unita_operativa_id <= 0 || $azienda_id <= 0) {
    header("Location: aziende.php?unita_delete_error=" . urlencode("Parametri mancanti."));
    exit();
}

// Eliminazione dell'unità operativa
$query = "DELETE FROM unita_operativa WHERE id = ? AND azienda_id = ?";
$params = [$unita_operativa_id, $azienda_id];
$types = 'ii';

$stmt = executeQuery($query, $params, $types);
if ($stmt && $stmt->affected_rows > 0) {
    header("Location: azienda.php?id=$azienda_id&unita_delete_success=1");
    exit;
} else {
    $error = "Errore durante l'eliminazione dell'unità operativa.";
    header("Location: azienda.php?id=$azienda_id&unita_delete_error=" . urlencode($error));
    exit;
}
?>
