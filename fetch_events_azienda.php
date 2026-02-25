<?php
require 'config.php';
checkLogin();

// Verifica del token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    echo json_encode([]);
    exit;
}

$azienda_id = intval($_POST['azienda_id']);

// Recupera tutti gli eventi per l'azienda, inclusi aziendali e individuali
$query = "
    SELECT 
        cl.id,
        cl.titolo,
        cl.start,
        cl.end,
        cl.all_day,
        cl.descrizione,
        cl.is_company_event,
        cl.lavoratore_id,
        l.nome AS nome_lavoratore,
        l.cognome AS cognome_lavoratore
    FROM 
        calendario_lavoratori cl
    LEFT JOIN 
        lavoratori l ON cl.lavoratore_id = l.id
    WHERE 
        cl.azienda_id = ?
    ORDER BY 
        cl.start ASC
";

$stmt = executeQuery($query, [$azienda_id], 'i');
if ($stmt === false) {
    error_log("Errore nella query degli eventi: " . $mysqli->error);
    echo json_encode([]);
    exit;
}

$result = $stmt->get_result();

$events = [];

while ($row = $result->fetch_assoc()) {
    // Determina se è un evento aziendale o individuale
    $is_company_event = intval($row['is_company_event']) === 1;
    
    // Costruisci il titolo
    if (!$is_company_event && !empty($row['nome_lavoratore']) && !empty($row['cognome_lavoratore'])) {
        $title = $row['titolo'] . ' - ' . $row['nome_lavoratore'] . ' ' . $row['cognome_lavoratore'];
    } else {
        $title = $row['titolo'];
    }
    
    // Assegna il colore in base al tipo di evento
    $color = $is_company_event ? '#3788d8' : '#f7b731'; // Blu per aziendali, Giallo per individuali

    $events[] = [
        'id' => $row['id'],
        'title' => $title,
        'start' => $row['start'],
        'end' => $row['end'],
        'allDay' => $row['all_day'] == 1,
        'description' => $row['descrizione'],
        'is_company_event' => $is_company_event,
        'worker_id' => $row['lavoratore_id'],
        'worker_name' => (!$is_company_event) ? trim($row['nome_lavoratore'] . ' ' . $row['cognome_lavoratore']) : null,
        'color' => $color
    ];
}

echo json_encode($events);
?>
