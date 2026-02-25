<?php
// autocomplete_ccnl.php

require_once 'config.php'; // Include le funzioni necessarie

header('Content-Type: application/json');

// Recupera il termine di ricerca
$term = isset($_GET['term']) ? $_GET['term'] : '';
$term = '%' . $term . '%';

// Prepara la query per ottenere valori distinti di CCNL che corrispondono al termine
$query = "SELECT DISTINCT ccnl FROM lavoratori WHERE ccnl LIKE ? ORDER BY ccnl ASC LIMIT 10";
$stmt = executeQuery($query, [$term], 's');

$results = [];

if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['ccnl'])) {
            $results[] = $row['ccnl'];
        }
    }
    $stmt->close();
}

echo json_encode($results);
?>
