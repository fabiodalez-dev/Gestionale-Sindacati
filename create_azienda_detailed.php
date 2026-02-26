<?php
// create_azienda_detailed.php

require 'config.php';

// Imposta l'intestazione per JSON
header('Content-Type: application/json');

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo di richiesta non valido.']);
    exit;
}

// Verifica il token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF mancante o non valido.']);
    exit;
}

// Recupera e sanitizza i dati
$nome_azienda = sanitizeForDatabase($_POST['nome_azienda'] ?? '');
$partita_iva = sanitizeForDatabase($_POST['partita_iva'] ?? null);
$indirizzo_via = sanitizeForDatabase($_POST['indirizzo_via'] ?? null);
$indirizzo_numero_civico = sanitizeForDatabase($_POST['indirizzo_numero_civico'] ?? null);
$indirizzo_cap = sanitizeForDatabase($_POST['indirizzo_cap'] ?? null);
$indirizzo_citta = sanitizeForDatabase($_POST['indirizzo_citta'] ?? null);
$indirizzo_provincia = sanitizeForDatabase($_POST['indirizzo_provincia'] ?? null);
$telefono = sanitizeForDatabase($_POST['telefono'] ?? null);
$email = sanitizeForDatabase($_POST['email'] ?? null);
$pec = sanitizeForDatabase($_POST['pec'] ?? null);
$settore = sanitizeForDatabase($_POST['settore'] ?? null);
$note = sanitizeHTML($_POST['note'] ?? null);

// Validazione dei campi obbligatori
if (empty($nome_azienda)) {
    echo json_encode(['success' => false, 'message' => 'Il nome dell\'Azienda è obbligatorio.']);
    exit;
}

// Controlla se l'Azienda esiste già
$query_check = "SELECT id FROM aziende WHERE nome_azienda = ? LIMIT 1";
$params_check = [$nome_azienda];
$types_check = 's';

$stmt_check = executeQuery($query_check, $params_check, $types_check);
if ($stmt_check) {
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) {
        $azienda = $result_check->fetch_assoc();
        echo json_encode(['success' => false, 'message' => 'L\'Azienda esiste già.', 'azienda_id' => $azienda['id']]);
        $stmt_check->close();
        exit;
    }
    $stmt_check->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Errore nel controllo dell\'esistenza dell\'Azienda.']);
    exit;
}

// Inserisce la nuova Azienda
$query_insert = "INSERT INTO aziende (nome_azienda, partita_iva, indirizzo_via, indirizzo_numero_civico, indirizzo_cap, indirizzo_citta, indirizzo_provincia, telefono, email, pec, settore, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$params_insert = [$nome_azienda, $partita_iva, $indirizzo_via, $indirizzo_numero_civico, $indirizzo_cap, $indirizzo_citta, $indirizzo_provincia, $telefono, $email, $pec, $settore, $note];
$types_insert = 'ssssssssssss';

$stmt_insert = executeQuery($query_insert, $params_insert, $types_insert);

if ($stmt_insert) {
    $azienda_id = $stmt_insert->insert_id;
    $stmt_insert->close();
    echo json_encode(['success' => true, 'message' => 'Azienda creata con successo.', 'azienda_id' => $azienda_id, 'nome_azienda' => $nome_azienda]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Errore durante la creazione dell\'Azienda.']);
    exit;
}
?>
