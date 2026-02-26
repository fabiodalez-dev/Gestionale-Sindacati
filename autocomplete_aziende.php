<?php
require 'config.php';

// Recupera il termine di ricerca
$term = isset($_GET['term']) ? sanitizeInput($_GET['term']) : '';

// Verifica che il termine non sia vuoto
if (empty($term)) {
    echo json_encode([]);
    exit;
}

// Prepara la query con prepared statements per prevenire SQL Injection
$query = "SELECT DISTINCT nome_azienda FROM aziende WHERE nome_azienda LIKE ? LIMIT 10";
$params = ['%' . $term . '%'];
$types = 's';

$stmt = executeQuery($query, $params, $types);
if ($stmt === false) {
    // Restituisce un array vuoto in caso di errore
    echo json_encode([]);
    exit;
}

$result = $stmt->get_result();

$aziende = [];
while ($row = $result->fetch_assoc()) {
    $aziende[] = $row['nome_azienda'];
}

// Restituisce i dati in formato JSON
echo json_encode($aziende);
?>
