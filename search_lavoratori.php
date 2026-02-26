<?php
// search_lavoratori.php

require 'config.php';
checkLogin();

// Imposta l'header per JSON
header('Content-Type: application/json');

// Recupera il termine di ricerca
$term = isset($_GET['term']) ? trim($_GET['term']) : '';

// Recupera un flag per determinare se includere 'tipo_tessera'
$include_tipo_tessera = isset($_GET['include_tipo_tessera']) && $_GET['include_tipo_tessera'] == '1';

if (empty($term)) {
    echo json_encode([]);
    exit;
}

// Prepara la query per cercare i lavoratori per cognome o nome
if ($include_tipo_tessera) {
    $query = "SELECT id, cognome, nome, tipo_tessera FROM lavoratori
              WHERE (cognome LIKE CONCAT('%', ?, '%') OR nome LIKE CONCAT('%', ?, '%'))
              ORDER BY cognome, nome";
} else {
    $query = "SELECT id, cognome, nome FROM lavoratori
              WHERE (cognome LIKE CONCAT('%', ?, '%') OR nome LIKE CONCAT('%', ?, '%'))
              ORDER BY cognome, nome";
}

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    echo json_encode([]);
    exit;
}
$stmt->bind_param('ss', $term, $term);
$stmt->execute();
$result = $stmt->get_result();

$lavoratori = [];
while ($row = $result->fetch_assoc()) {
    $entry = [
        'label' => $row['cognome'] . ' ' . $row['nome'],
        'value' => $row['id']
    ];
    
    if ($include_tipo_tessera && isset($row['tipo_tessera'])) {
        $entry['tipo_tessera'] = $row['tipo_tessera'];
    }
    
    $lavoratori[] = $entry;
}

$stmt->close();

// Restituisce i risultati in formato JSON
echo json_encode($lavoratori);
?>
