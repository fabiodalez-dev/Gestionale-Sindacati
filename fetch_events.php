<?php
require 'config.php';
checkLogin();

// Verifica del token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    echo json_encode([]);
    exit;
}

$lavoratore_id = intval($_POST['lavoratore_id']);
$azienda_id = intval($_POST['azienda_id']);

// Recupera gli eventi dal database, escludendo quelli con eccezioni
$query = "
    SELECT cl.*
    FROM calendario_lavoratori cl
    LEFT JOIN event_exceptions ee ON cl.id = ee.event_id AND ee.lavoratore_id = ?
    WHERE (cl.lavoratore_id = ? OR (cl.is_company_event = 1 AND cl.azienda_id = ?))
    AND ee.id IS NULL
";
$params = [$lavoratore_id, $lavoratore_id, $azienda_id];
$types = 'iii';

$stmt = executeQuery($query, $params, $types);
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
        'is_company_event' => $row['is_company_event']
    ];
}

echo json_encode($events);
?>
