<?php
require 'config.php';
checkLogin();

// Gestione della richiesta POST per aggiornare l'azienda
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "Token CSRF mancante o non valido.";
        header("Location: edit_azienda.php?id=" . intval($_POST['id']) . "&update_error=" . urlencode($error));
        exit;
    }

    // Recupera e sanitizza i dati
    $id = intval($_POST['id']);
    $nome_azienda = sanitizeInput($_POST['nome_azienda'] ?? '');
    $partita_iva = sanitizeInput($_POST['partita_iva'] ?? '');
    $indirizzo_via = sanitizeInput($_POST['indirizzo_via'] ?? '');
    $indirizzo_numero_civico = sanitizeInput($_POST['indirizzo_numero_civico'] ?? '');
    $indirizzo_cap = sanitizeInput($_POST['indirizzo_cap'] ?? '');
    $indirizzo_citta = sanitizeInput($_POST['indirizzo_citta'] ?? '');
    $indirizzo_provincia = sanitizeInput($_POST['indirizzo_provincia'] ?? '');
    $telefono = sanitizeInput($_POST['telefono'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $settore = sanitizeInput($_POST['settore'] ?? '');
    $pec = sanitizeInput($_POST['pec'] ?? ''); // Recupera il campo PEC
    $note = $_POST['note'] ?? '';
    $note_pulito = sanitizeHTML($note); // Usa sanitizeHTML per permettere tag HTML sicuri

    // Validazione dei campi obbligatori
    $errors = [];
    if (empty($nome_azienda)) {
        $errors[] = "Il campo 'Nome Azienda' è obbligatorio.";
    }

    // Validazione della Partita IVA
    if (!empty($partita_iva) && !preg_match('/^\d{11}$/', $partita_iva)) {
        $errors[] = "La Partita IVA deve contenere esattamente 11 cifre.";
    }

    // Validazione CAP e Provincia
    if (!empty($indirizzo_cap) && !preg_match('/^\d{5}$/', $indirizzo_cap)) {
        $errors[] = "Il CAP deve contenere esattamente 5 cifre.";
    }
    if (!empty($indirizzo_provincia) && !preg_match('/^[A-Za-z]{2}$/', $indirizzo_provincia)) {
        $errors[] = "La Provincia deve contenere esattamente 2 lettere.";
    }
    if (!empty($telefono) && !preg_match('/^\+?\d{7,15}$/', $telefono)) {
        $errors[] = "Inserisci un numero di telefono valido.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Inserisci un indirizzo email valido.";
    }
    if (!empty($pec) && !filter_var($pec, FILTER_VALIDATE_EMAIL)) { // Validazione PEC come email
        $errors[] = "Inserisci un indirizzo PEC valido.";
    }

    if (!empty($errors)) {
        // Reindirizza indietro con i messaggi di errore
        $error_string = implode(' ', $errors);
        header("Location: edit_azienda.php?id=$id&update_error=" . urlencode($error_string));
        exit;
    }

    // Prepara i parametri e i tipi per la query
    $params = [
        $nome_azienda,
        $partita_iva,
        $indirizzo_via,
        $indirizzo_numero_civico,
        $indirizzo_cap,
        $indirizzo_citta,
        $indirizzo_provincia,
        $telefono,
        $email,
        $settore,
        $pec,           // Aggiungi PEC ai parametri
        $note_pulito,
        $id
    ];
    // Costruisci la stringa dei tipi: 's' per stringhe, 'i' per interi
    // In questo caso: 12 's' e 1 'i'
    $types = 'ssssssssssssi';

    // Query di aggiornamento
    $query = "UPDATE aziende SET 
                nome_azienda = ?, 
                partita_iva = ?, 
                indirizzo_via = ?, 
                indirizzo_numero_civico = ?, 
                indirizzo_cap = ?, 
                indirizzo_citta = ?, 
                indirizzo_provincia = ?, 
                telefono = ?, 
                email = ?, 
                settore = ?, 
                pec = ?, 
                note = ? 
              WHERE id = ?";

    $stmt = executeQuery($query, $params, $types);

    if ($stmt) {
        // Reindirizza con successo
        header("Location: azienda.php?id=$id&update_success=1");
        exit;
    } else {
        // Gestione errore
        $error = "Errore durante l'aggiornamento: " . sanitizeForHTML($mysqli->error);
        header("Location: edit_azienda.php?id=$id&update_error=" . urlencode($error));
        exit;
    }
} else {
    echo "Richiesta non valida.";
    exit;
}
?>
