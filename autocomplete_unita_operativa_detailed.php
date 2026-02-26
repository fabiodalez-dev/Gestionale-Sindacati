<?php
// autocomplete_unita_operativa_detailed.php

require 'config.php';

// Imposta l'intestazione per JSON
header('Content-Type: application/json');

// Recupera il termine di ricerca e l'ID dell'Azienda
$term = isset($_GET['term']) ? sanitizeInput($_GET['term']) : '';
$azienda_id = isset($_GET['azienda_id']) ? intval($_GET['azienda_id']) : 0;

// Verifica che il termine non sia vuoto e che azienda_id sia valido
if (empty($term) || $azienda_id <= 0) {
    echo json_encode([]);
    exit;
}

// Prepara la query con prepared statements
$query = "SELECT DISTINCT id, nome_unita_operativa AS label FROM unita_operativa WHERE nome_unita_operativa LIKE ? AND azienda_id = ? ORDER BY nome_unita_operativa ASC LIMIT 10";
$params = ['%' . $term . '%', $azienda_id];
$types = 'si';

$stmt = executeQuery($query, $params, $types);
if ($stmt === false) {
    echo json_encode([]);
    exit;
}

$result = $stmt->get_result();

$unita_operative = [];
while ($row = $result->fetch_assoc()) {
    $unita_operative[] = $row;
}

echo json_encode($unita_operative);
?>
