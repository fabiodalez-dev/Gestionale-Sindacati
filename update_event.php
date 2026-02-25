<?php
require 'config.php';
checkLogin();

// Imposta l'header per la risposta JSON
header('Content-Type: application/json');

// Funzione per restituire errore JSON e terminare
function returnError($message, $details = '') {
    error_log("update_event.php ERROR: $message" . ($details ? " - Details: $details" : ""));
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Funzione per restituire successo JSON e terminare
function returnSuccess($message, $extra_data = []) {
    error_log("update_event.php SUCCESS: $message");
    $response = ['success' => true, 'message' => $message];
    if (!empty($extra_data)) {
        $response = array_merge($response, $extra_data);
    }
    echo json_encode($response);
    exit;
}

try {
    // Log dell'ora della richiesta
    error_log("update_event.php request ricevuta alle " . date('Y-m-d H:i:s'));

    // Verifica che la richiesta sia POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        returnError('Metodo non consentito.', $_SERVER['REQUEST_METHOD']);
    }

    // Verifica del token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $sent_token = $_POST['csrf_token'] ?? 'null';
        $expected_token = $_SESSION['csrf_token'] ?? 'null';
        returnError('Token CSRF mancante o non valido.', "Inviato: $sent_token | Atteso: $expected_token");
    }

    // Recupera e sanitizza i dati
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $titolo = isset($_POST['title']) ? sanitizeInput($_POST['title']) : '';
    $descrizione = isset($_POST['description']) ? $_POST['description'] : ''; // Permette HTML se necessario
    $start = isset($_POST['start']) ? sanitizeInput($_POST['start']) : '';
    $end = isset($_POST['end']) && !empty($_POST['end']) ? sanitizeInput($_POST['end']) : null;
    $all_day = isset($_POST['allDay']) && $_POST['allDay'] == 1 ? 1 : 0;
    $delete_for_all = isset($_POST['delete_for_all']) ? intval($_POST['delete_for_all']) : 0;

    // Log dei dati ricevuti
    error_log("Dati ricevuti in update_event.php: " . print_r($_POST, true));
    error_log("Parametri sanitizzati: titolo='$titolo', start='$start', end='$end', all_day='$all_day', delete_for_all='$delete_for_all', id='$id'");

    // Verifica che l'ID dell'evento sia presente
    if ($id === null) {
        returnError('ID evento mancante.');
    }

    // Recupera i dettagli dell'evento originale
    $query_original = "SELECT * FROM calendario_lavoratori WHERE id = ?";
    $stmt_original = executeQuery($query_original, [$id], 'i');

    if ($stmt_original === false) {
        returnError('Errore nella preparazione della query originale.', $mysqli->error);
    }

    $result_original = $stmt_original->get_result();

    if ($result_original === false || $result_original->num_rows === 0) {
        $stmt_original->close();
        returnError('Evento originale non trovato.', "ID evento: $id");
    }

    $evento_original = $result_original->fetch_assoc();
    $stmt_original->close();

    // Gestione della cancellazione per tutti gli eventi aziendali
    if ($delete_for_all === 1 && intval($evento_original['is_company_event']) === 1) {
        // Elimina tutti gli eventi aziendali per questa azienda
        $delete_query = "DELETE FROM calendario_lavoratori WHERE azienda_id = ? AND is_company_event = 1";
        $params_delete = [intval($evento_original['azienda_id'])];
        $delete_stmt = executeQuery($delete_query, $params_delete, 'i');
        
        if ($delete_stmt === false) {
            returnError('Errore nell\'eliminazione degli eventi aziendali.', $mysqli->error);
        }
        
        $delete_stmt->close();
        
        // Inserisci il nuovo evento aziendale
        $query_insert = "INSERT INTO calendario_lavoratori (lavoratore_id, azienda_id, is_company_event, titolo, descrizione, start, end, all_day) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $mysqli->prepare($query_insert);
        
        if ($stmt_insert === false) {
            returnError('Errore nella preparazione della query di inserimento evento aziendale.', $mysqli->error);
        }
        
        // Prepara i dati per l'inserimento
        $is_company_event = 1;
        $azienda_id = intval($evento_original['azienda_id']);
        
        // Validazione e conversione delle date
        $start_timestamp = strtotime($start);
        $end_timestamp = !empty($end) ? strtotime($end) : null;
        
        if ($start_timestamp === false) {
            $stmt_insert->close();
            returnError('Data di inizio non valida.', "Start: $start");
        }
        
        if (!empty($end) && $end_timestamp === false) {
            $stmt_insert->close();
            returnError('Data di fine non valida.', "End: $end");
        }
        
        $start_datetime = date('Y-m-d H:i:s', $start_timestamp);
        $end_datetime = $end_timestamp ? date('Y-m-d H:i:s', $end_timestamp) : null;
        
        // Bind dei parametri
        $stmt_insert->bind_param('iissssi', $azienda_id, $is_company_event, $titolo, $descrizione, $start_datetime, $end_datetime, $all_day);
        
        // Log dei parametri di inserimento
        error_log("Inserimento nuovo evento aziendale: azienda_id='$azienda_id', titolo='$titolo', start='$start_datetime', end='" . ($end_datetime ?? 'NULL') . "'");
        
        // Esegui la query di inserimento
        if ($stmt_insert->execute()) {
            $new_event_id = $stmt_insert->insert_id;
            $stmt_insert->close();
            returnSuccess('Evento aziendale aggiornato con successo.', ['event_id' => $new_event_id]);
        } else {
            $error = $stmt_insert->error;
            $stmt_insert->close();
            returnError('Errore nell\'inserimento del nuovo evento aziendale.', $error);
        }
    }

    // Altrimenti, aggiorna l'evento individuale o aziendale esistente
    $is_company_event = isset($_POST['is_company_event']) && $_POST['is_company_event'] == 1 ? 1 : 0;
    $lavoratore_id = isset($_POST['lavoratore_id']) && $_POST['lavoratore_id'] !== '' ? intval($_POST['lavoratore_id']) : null;
    $azienda_id = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : null;

    // Se l'evento non è aziendale, recupera azienda_id dalla tabella lavoratori
    if (!$is_company_event) {
        if ($lavoratore_id === null) {
            returnError('ID lavoratore mancante per aggiornamento evento singolo.');
        }

        // Recupera azienda_id dalla tabella lavoratori
        $query_lavoratore = "SELECT azienda_id FROM lavoratori WHERE id = ?";
        $stmt_lavoratore = executeQuery($query_lavoratore, [$lavoratore_id], 'i');

        if ($stmt_lavoratore === false) {
            returnError('Errore nella preparazione della query lavoratore.', $mysqli->error);
        }

        $result_lavoratore = $stmt_lavoratore->get_result();

        if ($result_lavoratore === false || $result_lavoratore->num_rows === 0) {
            $stmt_lavoratore->close();
            returnError('Lavoratore non trovato o non associato a nessuna azienda.', "ID lavoratore: $lavoratore_id");
        }

        $row = $result_lavoratore->fetch_assoc();
        $azienda_id = intval($row['azienda_id']);
        $stmt_lavoratore->close();

        error_log("Azienda ID recuperato per lavoratore $lavoratore_id: $azienda_id");
    } else {
        // Per eventi aziendali, verifica che azienda_id sia fornito
        if ($azienda_id === null) {
            returnError('ID azienda mancante per aggiornamento evento aziendale.');
        }
        // Imposta lavoratore_id a NULL per eventi aziendali
        $lavoratore_id = null;
        error_log("Aggiornamento evento aziendale. Azienda ID: $azienda_id");
    }

    // Verifica che i campi obbligatori siano compilati
    if (empty($titolo) || empty($start)) {
        returnError('Compila tutti i campi obbligatori (Titolo e Data Inizio).', "Titolo: '$titolo', Start: '$start'");
    }

    // Validazione delle date
    $start_timestamp = strtotime($start);
    $end_timestamp = !empty($end) ? strtotime($end) : null;
    
    if ($start_timestamp === false) {
        returnError('Data di inizio non valida.', "Start: $start");
    }
    
    if (!empty($end) && $end_timestamp === false) {
        returnError('Data di fine non valida.', "End: $end");
    }
    
    $start_datetime = date('Y-m-d H:i:s', $start_timestamp);
    $end_datetime = $end_timestamp ? date('Y-m-d H:i:s', $end_timestamp) : null;
    
    error_log("Date formattate durante l'aggiornamento. Start: $start_datetime | End: " . ($end_datetime ?? 'NULL'));

    // Preparazione della query di aggiornamento
    if ($lavoratore_id === null) {
        // Evento aziendale: lavoratore_id è NULL
        $query_update = "UPDATE calendario_lavoratori SET azienda_id = ?, is_company_event = ?, titolo = ?, descrizione = ?, start = ?, end = ?, all_day = ? WHERE id = ?";
        $stmt_update = $mysqli->prepare($query_update);
        if ($stmt_update === false) {
            returnError('Errore nella preparazione della query di aggiornamento evento aziendale.', $mysqli->error);
        }
        $stmt_update->bind_param('iissssii', $azienda_id, $is_company_event, $titolo, $descrizione, $start_datetime, $end_datetime, $all_day, $id);
    } else {
        // Evento individuale: lavoratore_id specificato
        $query_update = "UPDATE calendario_lavoratori SET lavoratore_id = ?, azienda_id = ?, is_company_event = ?, titolo = ?, descrizione = ?, start = ?, end = ?, all_day = ? WHERE id = ?";
        $stmt_update = $mysqli->prepare($query_update);
        if ($stmt_update === false) {
            returnError('Errore nella preparazione della query di aggiornamento evento singolo.', $mysqli->error);
        }
        $stmt_update->bind_param('iisssssii', $lavoratore_id, $azienda_id, $is_company_event, $titolo, $descrizione, $start_datetime, $end_datetime, $all_day, $id);
    }

    // Log della query e dei parametri
    error_log("Esecuzione della query di aggiornamento");
    if ($lavoratore_id === null) {
        error_log("Parametri evento aziendale: azienda_id='$azienda_id', is_company_event='$is_company_event', titolo='$titolo', start='$start_datetime', end='" . ($end_datetime ?? 'NULL') . "', all_day='$all_day', id='$id'");
    } else {
        error_log("Parametri evento singolo: lavoratore_id='$lavoratore_id', azienda_id='$azienda_id', is_company_event='$is_company_event', titolo='$titolo', start='$start_datetime', end='" . ($end_datetime ?? 'NULL') . "', all_day='$all_day', id='$id'");
    }

    // Esegui la query di aggiornamento
    if ($stmt_update->execute()) {
        $stmt_update->close();
        returnSuccess('Evento aggiornato con successo.');
    } else {
        $error = $stmt_update->error;
        $stmt_update->close();
        returnError('Errore nell\'aggiornamento dell\'evento.', $error);
    }

} catch (Exception $e) {
    returnError('Errore interno del server.', $e->getMessage());
} catch (Error $e) {
    returnError('Errore fatale.', $e->getMessage());
}
?>