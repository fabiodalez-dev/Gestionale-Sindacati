<?php
require 'config.php';
checkLogin();

// Verifica se l'ID dell'unità operativa e dell'azienda sono stati forniti
if (isset($_GET['id']) && isset($_GET['azienda_id'])) {
    $unita_operativa_id = intval($_GET['id']);
    $azienda_id = intval($_GET['azienda_id']);

    // Eliminazione dell'unità operativa
    $query = "DELETE FROM unita_operativa WHERE id = ? AND azienda_id = ?";
    $params = [$unita_operativa_id, $azienda_id];
    $types = 'ii';

    $stmt = executeQuery($query, $params, $types);
    if ($stmt) {
        header("Location: azienda.php?id=$azienda_id&unita_delete_success=1");
        exit;
    } else {
        $error = "Errore durante l'eliminazione dell'unità operativa: " . sanitizeForHTML($mysqli->error);
        header("Location: azienda.php?id=$azienda_id&unita_delete_error=" . urlencode($error));
        exit;
    }
} else {
    echo "Parametri mancanti.";
    exit;
}
?>
