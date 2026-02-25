<?php
require 'config.php';
checkLogin();

// Verifica del token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    echo json_encode([]);
    exit;
}

// Recupera tutti gli eventi
$query = "
    SELECT cl.*, 
           l.nome AS nome_lavoratore, l.cognome AS cognome_lavoratore,
           a.nome_azienda
    FROM calendario_lavoratori cl
    LEFT JOIN lavoratori l ON cl.lavoratore_id = l.id
    LEFT JOIN aziende a ON cl.azienda_id = a.id
    GROUP BY cl.id
";

$stmt = $mysqli->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

$events = [];

while ($row = $result->fetch_assoc()) {
    $events[] = [
        'id' => $row['id'],
        'title' => $row['titolo'],
        'start' => $row['start'],
        'end' => $row['end'],
        'allDay' => $row['all_day'] == 1,
        'description' => $row['descrizione'],
        'is_company_event' => $row['is_company_event'],
        'lavoratore_id' => $row['lavoratore_id'],
        'nome_lavoratore' => $row['nome_lavoratore'] . ' ' . $row['cognome_lavoratore'],
        'azienda_id' => $row['azienda_id'],
        'nome_azienda' => $row['nome_azienda'],
    ];
}

echo json_encode($events);
?>
