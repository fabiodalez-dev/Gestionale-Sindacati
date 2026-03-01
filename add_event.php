<?php
require 'config.php';
checkLogin();

// Imposta l'header per la risposta JSON
header('Content-Type: application/json');

// Log dell'ora della richiesta
error_log("add_event.php request received at " . date('Y-m-d H:i:s'));

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Metodo non consentito: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405); // Metodo Non Consentito
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito.']);
    exit;
}

// Verifica del token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    $sent_token = $_POST['csrf_token'] ?? 'null';
    $expected_token = $_SESSION['csrf_token'] ?? 'null';
    error_log("Token CSRF mancante o non valido. Inviato: $sent_token | Atteso: $expected_token");
    echo json_encode(['success' => false, 'error' => 'Token CSRF mancante o non valido.']);
    exit;
}

// Recupera e sanitizza i dati
$is_company_event = isset($_POST['is_company_event']) && $_POST['is_company_event'] == 1 ? 1 : 0;
$lavoratore_id = isset($_POST['lavoratore_id']) && $_POST['lavoratore_id'] !== '' ? intval($_POST['lavoratore_id']) : null;
$azienda_id = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : null;
$titolo = isset($_POST['title']) ? sanitizeInput($_POST['title']) : '';
$descrizione = isset($_POST['description']) ? $_POST['description'] : '';
$start = isset($_POST['start']) ? sanitizeInput($_POST['start']) : '';
$end = isset($_POST['end']) && !empty($_POST['end']) ? sanitizeInput($_POST['end']) : null;
$all_day = isset($_POST['allDay']) && $_POST['allDay'] == 1 ? 1 : 0;

// Log dei dati ricevuti
error_log("Dati ricevuti in add_event.php: " . print_r($_POST, true));
error_log("Parametri sanitizzati: titolo='$titolo', descrizione='$descrizione', start='$start', end='$end', all_day='$all_day', is_company_event='$is_company_event', lavoratore_id='$lavoratore_id', azienda_id='$azienda_id'");

// Verifica i campi obbligatori
if (empty($titolo) || empty($start)) {
    error_log("Campi obbligatori mancanti. Titolo: '$titolo', Start: '$start'");
    echo json_encode(['success' => false, 'error' => 'Compila tutti i campi obbligatori (Titolo e Data Inizio).']);
    exit;
}

// Gestione degli eventi singoli
if (!$is_company_event) {
    if ($lavoratore_id === null) {
        error_log("ID lavoratore mancante per evento singolo.");
        echo json_encode(['success' => false, 'error' => 'ID lavoratore mancante.']);
        exit;
    }

    // Recupera azienda_id dalla tabella lavoratori se non fornito
    if ($azienda_id === null) {
        $query_lavoratore = "SELECT azienda_id FROM lavoratori WHERE id = ?";
        $stmt_lavoratore = executeQuery($query_lavoratore, [$lavoratore_id], 'i');

        if ($stmt_lavoratore === false) {
            error_log("Errore DB nel recupero azienda per lavoratore $lavoratore_id: " . $mysqli->error);
            echo json_encode(['success' => false, 'error' => 'Errore nel recupero dei dati del lavoratore.']);
            exit;
        }
        $lav_result = $stmt_lavoratore->get_result();
        if ($lav_result && $lav_result->num_rows > 0) {
            $row = $lav_result->fetch_assoc();
            $azienda_id = intval($row['azienda_id']);
            error_log("Azienda ID recuperato per lavoratore $lavoratore_id: $azienda_id");
        } else {
            error_log("Lavoratore non trovato. ID lavoratore: $lavoratore_id");
            echo json_encode(['success' => false, 'error' => 'Lavoratore non trovato.']);
            exit;
        }
    }
} else {
    // Per eventi aziendali, assicurati che azienda_id sia fornito
    if ($azienda_id === null) {
        error_log("ID azienda mancante per evento aziendale.");
        echo json_encode(['success' => false, 'error' => 'ID azienda mancante per evento aziendale.']);
        exit;
    }
    // Imposta lavoratore_id a NULL per eventi aziendali
    $lavoratore_id = null;
    error_log("Evento aziendale. Azienda ID: $azienda_id");
}

// Formattazione delle date
$start_datetime = date('Y-m-d H:i:s', strtotime($start));
$end_datetime = !empty($end) ? date('Y-m-d H:i:s', strtotime($end)) : null;
error_log("Date formattate. Start: $start_datetime | End: " . ($end_datetime ?? 'NULL'));

// Preparazione della query di inserimento
if ($is_company_event) {
    $query = "INSERT INTO calendario_lavoratori (lavoratore_id, azienda_id, is_company_event, titolo, descrizione, start, end, all_day) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($query);
    if ($stmt === false) {
        error_log("Errore nella preparazione della query di inserimento evento aziendale: " . $mysqli->error);
        echo json_encode(['success' => false, 'error' => 'Errore nella preparazione della query.']);
        exit;
    }
    $stmt->bind_param('iissssi', $azienda_id, $is_company_event, $titolo, $descrizione, $start_datetime, $end_datetime, $all_day);
// Blocco corretto
} else {
    $query = "INSERT INTO calendario_lavoratori (lavoratore_id, azienda_id, is_company_event, titolo, descrizione, start, end, all_day) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($query);
    if ($stmt === false) {
        error_log("Errore nella preparazione della query di inserimento evento singolo: " . $mysqli->error);
        echo json_encode(['success' => false, 'error' => 'Errore nella preparazione della query.']);
        exit;
    }
    // La stringa dei tipi è stata corretta da 'iissssii' a 'iiissssi'
    $stmt->bind_param('iiissssi', $lavoratore_id, $azienda_id, $is_company_event, $titolo, $descrizione, $start_datetime, $end_datetime, $all_day);
}

// Log della query e dei parametri
error_log("Esecuzione della query: $query");
if ($is_company_event) {
    error_log("Parametri: azienda_id='$azienda_id', is_company_event='$is_company_event', titolo='$titolo', descrizione='$descrizione', start='$start_datetime', end='" . ($end_datetime ?? 'NULL') . "', all_day='$all_day'");
} else {
    error_log("Parametri: lavoratore_id='$lavoratore_id', azienda_id='$azienda_id', is_company_event='$is_company_event', titolo='$titolo', descrizione='$descrizione', start='$start_datetime', end='" . ($end_datetime ?? 'NULL') . "', all_day='$all_day'");
}

// Esecuzione della query
if ($stmt->execute()) {
    $insert_id = $stmt->insert_id;
    if ($is_company_event) {
        error_log("Evento aziendale creato con successo. ID evento: $insert_id");
    } else {
        error_log("Evento singolo creato con successo. ID evento: $insert_id");
    }
    echo json_encode(['success' => true, 'message' => 'Evento creato con successo.', 'event_id' => $insert_id]);
} else {
    if ($is_company_event) {
        error_log("Errore nell'inserimento dell'evento aziendale: " . $stmt->error);
    } else {
        error_log("Errore nell'inserimento dell'evento singolo: " . $stmt->error);
    }
    echo json_encode(['success' => false, 'error' => 'Errore nell\'inserimento dell\'evento.']);
}

$stmt->close();
exit;
?>
