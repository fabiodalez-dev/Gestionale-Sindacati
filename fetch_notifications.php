<?php
/**
 * fetch_notifications.php
 * Returns calendar events for the notification dropdown.
 * Shows: today's events + upcoming 7 days + last 3 days (recent).
 */
require 'config.php';
checkLogin();

header('Content-Type: application/json');

$today = date('Y-m-d');
$past_date = date('Y-m-d', strtotime('-3 days'));
$future_date = date('Y-m-d', strtotime('+7 days'));

// Fetch events from past 3 days to next 7 days
$query = "
    SELECT
        c.id,
        c.titolo,
        c.descrizione,
        c.start,
        c.end,
        c.all_day,
        c.lavoratore_id,
        c.azienda_id,
        c.is_company_event,
        CONCAT(l.nome, ' ', l.cognome) AS lavoratore_nome,
        a.nome_azienda
    FROM calendario_lavoratori c
    LEFT JOIN lavoratori l ON c.lavoratore_id = l.id
    LEFT JOIN aziende a ON c.azienda_id = a.id
    WHERE DATE(c.start) BETWEEN ? AND ?
    ORDER BY c.start ASC
";

$stmt = executeQuery($query, [$past_date, $future_date], 'ss');

$notifications = [];
$today_count = 0;
$upcoming_count = 0;

if ($stmt !== false) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $event_date = date('Y-m-d', strtotime($row['start']));

        // Classify the event
        if ($event_date === $today) {
            $category = 'today';
            $today_count++;
        } elseif ($event_date > $today) {
            $category = 'upcoming';
            $upcoming_count++;
        } else {
            $category = 'recent';
        }

        // Build subtitle
        $subtitle = '';
        if (!empty($row['lavoratore_nome'])) {
            $subtitle = $row['lavoratore_nome'];
        }
        if (!empty($row['nome_azienda'])) {
            $subtitle .= ($subtitle ? ' — ' : '') . $row['nome_azienda'];
        }
        if ($row['is_company_event']) {
            $subtitle .= ($subtitle ? ' · ' : '') . 'Evento aziendale';
        }

        // Format time
        $time_str = '';
        if ($row['all_day']) {
            $time_str = 'Tutto il giorno';
        } else {
            $time_str = date('H:i', strtotime($row['start']));
            if (!empty($row['end'])) {
                $time_str .= ' - ' . date('H:i', strtotime($row['end']));
            }
        }

        $notifications[] = [
            'id' => (int)$row['id'],
            'title' => $row['titolo'],
            'subtitle' => $subtitle,
            'date' => date('d/m/Y', strtotime($row['start'])),
            'time' => $time_str,
            'category' => $category,
            'azienda_id' => $row['azienda_id'] ? (int)$row['azienda_id'] : null,
            'lavoratore_id' => $row['lavoratore_id'] ? (int)$row['lavoratore_id'] : null
        ];
    }
}

echo json_encode([
    'success' => true,
    'today_count' => $today_count,
    'upcoming_count' => $upcoming_count,
    'total_count' => $today_count + $upcoming_count,
    'notifications' => $notifications
]);
