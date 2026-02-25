<?php
require 'config.php';
checkLogin();

// Ottieni il termine di ricerca dall'input dell'utente
$term = isset($_GET['term']) ? $_GET['term'] : '';

// Prepara la query per cercare i paesi di nascita simili al termine di ricerca
$query = "SELECT DISTINCT paese_nascita FROM lavoratori WHERE paese_nascita LIKE ? ORDER BY paese_nascita ASC";
$params = ['%' . $term . '%'];
$types = 's';

// Esegui la query
$stmt = executeQuery($query, $params, $types);

if ($stmt) {
    $result = $stmt->get_result();
    $paesi = [];

    while ($row = $result->fetch_assoc()) {
        $paesi[] = $row['paese_nascita'];
    }

    // Restituisci i risultati in formato JSON
    echo json_encode($paesi);
} else {
    // In caso di errore, restituisci un array vuoto
    echo json_encode([]);
}
?>
