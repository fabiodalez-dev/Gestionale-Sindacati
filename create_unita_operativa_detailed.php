<?php
// create_unita_operativa_detailed.php

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
$nome_unita_operativa = sanitizeForDatabase($_POST['nome_unita_operativa'] ?? '');
$azienda_id = intval($_POST['azienda_id'] ?? 0);

// Validazione dei campi obbligatori
if (empty($nome_unita_operativa)) {
    echo json_encode(['success' => false, 'message' => 'Il nome dell\'Unità Operativa è obbligatorio.']);
    exit;
}

if ($azienda_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Azienda non valido.']);
    exit;
}

// Controlla se l'Unità Operativa esiste già per l'Azienda
$query_check = "SELECT id FROM unita_operativa WHERE nome_unita_operativa = ? AND azienda_id = ? LIMIT 1";
$params_check = [$nome_unita_operativa, $azienda_id];
$types_check = 'si';

$stmt_check = executeQuery($query_check, $params_check, $types_check);
if ($stmt_check) {
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) {
        $unita = $result_check->fetch_assoc();
        echo json_encode(['success' => false, 'message' => 'L\'Unità Operativa esiste già.', 'unita_id' => $unita['id']]);
        $stmt_check->close();
        exit;
    }
    $stmt_check->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Errore nel controllo dell\'esistenza dell\'Unità Operativa.']);
    exit;
}

// Inserisce la nuova Unità Operativa
$query_insert = "INSERT INTO unita_operativa (nome_unita_operativa, azienda_id) VALUES (?, ?)";
$params_insert = [$nome_unita_operativa, $azienda_id];
$types_insert = 'si';

$stmt_insert = executeQuery($query_insert, $params_insert, $types_insert);

if ($stmt_insert) {
    $unita_id = $stmt_insert->insert_id;
    $stmt_insert->close();
    echo json_encode(['success' => true, 'message' => 'Unità Operativa creata con successo.', 'unita_id' => $unita_id, 'unita_nome' => $nome_unita_operativa]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Errore durante la creazione dell\'Unità Operativa.']);
    exit;
}
?>
