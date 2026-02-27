<?php
/**
 * calendar_feed.php
 *
 * Questo script genera dinamicamente un file ICS contenente gli eventi.
 * Può essere richiamato direttamente via URL (es. https://tuodominio.it/calendar_feed.php)
 * per essere integrato con Google Calendar.
 *
 * È possibile passare un parametro GET per definire l'intervallo di eventi, ad es.:
 *   ?days=30     -> eventi nei prossimi 30 giorni (default)
 *   ?year=2025   -> tutti gli eventi dell'anno 2025
 */

date_default_timezone_set('Europe/Rome');

// Includi la configurazione e le funzioni di utilità
require_once 'config.php';

// Autenticazione basata su token (i client calendario non possono fare login con sessione)
$token = $_GET['token'] ?? '';
$expected = getSetting('calendar_token');
if (empty($expected)) {
    // Genera e salva un token casuale al primo utilizzo
    $expected = bin2hex(random_bytes(16));
    setSetting('calendar_token', $expected);
}
if (!hash_equals($expected, $token)) {
    http_response_code(403);
    die('Accesso negato. Token non valido.');
}

// Parametri per l'intervallo degli eventi
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$year = isset($_GET['year']) ? intval($_GET['year']) : 0;

if ($year) {
    $startDate = "$year-01-01";
    $endDate = "$year-12-31";
} else {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime("+$days days"));
}

// La query ora recupera anche il campo "location" (modifica il nome in base al tuo schema)
$query = "SELECT cl.*, 
                 l.nome AS lavoratore_nome, l.cognome AS lavoratore_cognome, 
                 a.nome_azienda,
                 cl.location
          FROM calendario_lavoratori cl 
          LEFT JOIN lavoratori l ON cl.lavoratore_id = l.id 
          LEFT JOIN aziende a ON cl.azienda_id = a.id 
          WHERE DATE(cl.start) BETWEEN ? AND ? 
          ORDER BY cl.start ASC";

$stmt = executeQuery($query, [$startDate, $endDate], 'ss');
if (!$stmt) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Errore nel recupero degli eventi.");
}

$result = $stmt->get_result();
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

// Se non ci sono eventi, generiamo comunque un calendario vuoto
if (empty($events)) {
    $events = [];
}

// Inizia la generazione del file ICS
$icsContent = "BEGIN:VCALENDAR\r\n";
$icsContent .= "VERSION:2.0\r\n";
$icsContent .= "PRODID:-//AdlCobas//Gestionale//IT\r\n";
$icsContent .= "CALSCALE:GREGORIAN\r\n";

// Per ogni evento, aggiungi un blocco VEVENT
foreach ($events as $event) {
    // Genera un UID univoco per l'evento
    $uid = $event['id'] . "-" . time() . "@adlcobas.it";
    $dtstamp = date('Ymd\THis\Z');  // Timestamp attuale in UTC
    $dtstart = date('Ymd\THis\Z', strtotime($event['start']));
    $dtend = (!empty($event['end'])) 
             ? date('Ymd\THis\Z', strtotime($event['end'])) 
             : date('Ymd\THis\Z', strtotime($event['start'] . " +1 hour"));

    // Se l'evento è "all day", usa il formato senza T e Z
    if ($event['all_day']) {
        $dtstart = date('Ymd', strtotime($event['start']));
        $dtend = date('Ymd', strtotime($event['start'] . " +1 day"));
    }

    // Costruisci il summary e la descrizione con tutte le informazioni utili
    $summary = $event['titolo'];
    $description  = "Titolo: " . $event['titolo'] . "\n";
    $description .= "Descrizione: " . strip_tags($event['descrizione']) . "\n";
    $description .= "Inizio: " . $event['start'] . "\n";
    $description .= "Fine: " . ($event['end'] ? $event['end'] : "Nessuno") . "\n";
    $description .= "Evento di tutto il giorno: " . ($event['all_day'] ? "Si" : "No") . "\n";
    
    $lavoratore = trim($event['lavoratore_nome'] . " " . $event['lavoratore_cognome']);
    $description .= "Lavoratore: " . ($lavoratore ? $lavoratore : "Non specificato") . "\n";
    $azienda = $event['nome_azienda'] ?? 'Non specificata';
    $description .= "Azienda: " . $azienda . "\n";

    $icsContent .= "BEGIN:VEVENT\r\n";
    $icsContent .= "UID:" . addcslashes($uid, ",;\\") . "\r\n";
    $icsContent .= "DTSTAMP:$dtstamp\r\n";
    $icsContent .= "SUMMARY:" . addcslashes($summary, ",;\\") . "\r\n";
    $icsContent .= "DESCRIPTION:" . addcslashes($description, ",;\\") . "\r\n";
    $icsContent .= "DTSTART:" . $dtstart . "\r\n";
    $icsContent .= "DTEND:" . $dtend . "\r\n";

    // Se il campo location è presente e non vuoto, aggiungilo come LOCATION
    if (!empty($event['location'])) {
        $icsContent .= "LOCATION:" . addcslashes($event['location'], ",;\\") . "\r\n";
    }

    $icsContent .= "END:VEVENT\r\n";
}

$icsContent .= "END:VCALENDAR\r\n";

// Imposta gli header per servire il file ICS direttamente
header("Content-Type: text/calendar; charset=utf-8");
header("Content-Disposition: inline; filename=calendar.ics");

echo $icsContent;
exit;
?>
