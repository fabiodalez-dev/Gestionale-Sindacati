<?php
// cron_event_notification.php

// Imposta il fuso orario
date_default_timezone_set('Europe/Rome');

// Include la configurazione e le funzioni di utilità (connessione al DB, executeQuery(), sanitizeForHTML(), ecc.)
require_once 'config.php';

// Data odierna nel formato dd-mm-YYYY per l'oggetto e il corpo della mail
$todayFormatted = date('d-m-Y');
$todayForQuery = date('Y-m-d'); // Formato YYYY-MM-DD per la query SQL

// Query per recuperare tutti gli eventi in calendario per oggi
// Recupera anche:
// - GROUP_CONCAT dei lavoratori (nome e cognome)
// - GROUP_CONCAT dei distinti ID delle sedi dei lavoratori (sedi_involti)
// - Il conteggio totale dei lavoratori (total_workers)
// - Il conteggio dei lavoratori che hanno una sede (workers_with_sede)
$query = "SELECT cl.*, 
                 GROUP_CONCAT(DISTINCT CONCAT(l.nome, ' ', l.cognome) SEPARATOR ', ') AS lavoratori,
                 GROUP_CONCAT(DISTINCT l.sede_id SEPARATOR ',') AS sedi_involti,
                 COUNT(*) AS total_workers,
                 COUNT(l.sede_id) AS workers_with_sede,
                 a.nome_azienda 
          FROM calendario_lavoratori cl 
          LEFT JOIN lavoratori l ON cl.lavoratore_id = l.id 
          LEFT JOIN aziende a ON cl.azienda_id = a.id 
          WHERE DATE(cl.start) = ?
          GROUP BY cl.id, a.nome_azienda";

$stmt = executeQuery($query, [$todayForQuery], 's');
if (!$stmt) {
    exit("Errore durante l'esecuzione della query degli eventi.\n");
}

$result = $stmt->get_result();
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

if (empty($events)) {
    exit("Nessun evento previsto per oggi ($todayFormatted).\n");
}

// Per ogni evento, invia una mail ai destinatari appropriati
foreach ($events as $event) {

    // Costruisci il contenuto della mail per questo evento
    $emailContent  = "Notifica evento per oggi ($todayFormatted):\n\n";
    $emailContent .= "Titolo: " . $event['titolo'] . "\n";
    $emailContent .= "Descrizione: " . strip_tags($event['descrizione'] ?? '') . "\n";

    // Formatta la data di inizio e di fine
    $startDate = date('d-m-Y H:i', strtotime($event['start']));
    $endDate = $event['end'] ? date('d-m-Y H:i', strtotime($event['end'])) : "Nessuno";

    // Se l'evento è di tutto il giorno, mostra solo la data senza ora
    if ($event['all_day']) {
        $startDate = date('d-m-Y', strtotime($event['start']));
        $endDate = $event['end'] ? date('d-m-Y', strtotime($event['end'])) : "Nessuno";
    }

    $emailContent .= "Inizio: " . $startDate . "\n";
    $emailContent .= "Fine: " . $endDate . "\n";
    $emailContent .= "Evento di tutto il giorno: " . ($event['all_day'] ? "Si" : "No") . "\n";

    // Se non ci sono lavoratori specificati, mostra "Tutti i lavoratori dell'azienda"
    $lavoratori = !empty($event['lavoratori']) ? $event['lavoratori'] : "Tutti i lavoratori dell'azienda";
    $emailContent .= "Lavoratore/i: " . $lavoratori . "\n";

    $emailContent .= "Azienda: " . $event['nome_azienda'] . "\n";
    $emailContent .= "--------------------------\n";

    // Determina i destinatari in base alle sedi dei lavoratori
    // Se almeno un lavoratore non ha sede (ossia total_workers > workers_with_sede)
    // allora la notifica va mandata a tutti gli utenti.
    $recipientEmails = [];
    if ($event['total_workers'] > $event['workers_with_sede']) {
        // Recupera tutti gli utenti
        $userQuery = "SELECT email FROM users";
        $stmtUsers = executeQuery($userQuery);
        if ($stmtUsers) {
            $userResult = $stmtUsers->get_result();
            while ($user = $userResult->fetch_assoc()) {
                $recipientEmails[] = $user['email'];
            }
        }
    } else {
        // Altrimenti, recupera gli utenti associati alle sedi indicate in sedi_involti
        if (!empty($event['sedi_involti'])) {
            // Ottieni l'array degli ID delle sedi
            $sedeIds = array_filter(array_map('trim', explode(',', $event['sedi_involti'])));
            if (!empty($sedeIds)) {
                $placeholders = implode(',', array_fill(0, count($sedeIds), '?'));
                $userQuery = "SELECT email FROM users WHERE sede_id IN ($placeholders)";
                $stmtUsers = executeQuery($userQuery, $sedeIds, str_repeat('i', count($sedeIds)));
                if ($stmtUsers) {
                    $userResult = $stmtUsers->get_result();
                    while ($user = $userResult->fetch_assoc()) {
                        $recipientEmails[] = $user['email'];
                    }
                }
            }
        }
        // Se, per qualsiasi motivo, non si trovano utenti per quelle sedi, invia a tutti
        if (empty($recipientEmails)) {
            $userQuery = "SELECT email FROM users";
            $stmtUsers = executeQuery($userQuery);
            if ($stmtUsers) {
                $userResult = $stmtUsers->get_result();
                while ($user = $userResult->fetch_assoc()) {
                    $recipientEmails[] = $user['email'];
                }
            }
        }
    }

    // Prepara il subject e gli headers della mail
    $subject = "Gestionale ADL | Notifica evento: " . $event['titolo'] . " (" . $todayFormatted . ")";
    $headers = "From: no-reply@adlcobas.it\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";

    // Invia la mail a ciascun utente destinatario per questo evento
    foreach ($recipientEmails as $email) {
        mail($email, $subject, $emailContent, $headers);
    }

    echo "Notifica per l'evento '{$event['titolo']}' inviata a " . count($recipientEmails) . " destinatari.\n";
}
?>
