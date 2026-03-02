<?php
// elimina_iscrizione.php

require 'config.php';
checkLogin();
checkUserRole('admin');

// Richiede metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: gestione_iscrizioni.php");
    exit;
}

// Verifica token CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: gestione_iscrizioni.php?error=" . urlencode("Token CSRF non valido."));
    exit;
}

// Recupera l'ID dell'iscrizione da eliminare
if (!isset($_POST['id']) || !is_scalar($_POST['id']) || !ctype_digit((string)$_POST['id'])) {
    header("Location: gestione_iscrizioni.php");
    exit;
}

$iscrizione_id = intval($_POST['id']);

// Recupera i dettagli dell'iscrizione per ottenere lavoratore_id
$stmt = executeQuery("SELECT lavoratore_id FROM iscrizioni WHERE id = ?", [$iscrizione_id], 'i');
if ($stmt === false) {
    header("Location: gestione_iscrizioni.php?error=" . urlencode("Errore nel recupero dell'iscrizione."));
    exit;
}
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: gestione_iscrizioni.php");
    exit;
}

$row = $result->fetch_assoc();
$lavoratore_id = $row['lavoratore_id'];

// Elimina l'iscrizione e aggiorna stato lavoratore in transazione
global $mysqli;
$mysqli->begin_transaction();
try {
    $delStmt = executeQuery("DELETE FROM iscrizioni WHERE id = ?", [$iscrizione_id], 'i');
    if ($delStmt === false || $delStmt->affected_rows === 0) {
        throw new Exception("Iscrizione non trovata o già eliminata.");
    }

    // Controlla se il lavoratore ha altre iscrizioni attive
    $oggi = date('Y-m-d');
    $checkStmt = executeQuery(
        "SELECT COUNT(*) AS count FROM iscrizioni WHERE lavoratore_id = ? AND (metodo_pagamento IN ('trattenuta in busta paga', 'sepa') OR (metodo_pagamento = 'rinnovo annuale' AND data_fine >= ?))",
        [$lavoratore_id, $oggi],
        'is'
    );
    if ($checkStmt === false) {
        throw new Exception("Errore verifica iscrizioni attive.");
    }
    $count = $checkStmt->get_result()->fetch_assoc()['count'];

    if ($count == 0) {
        $updStmt = executeQuery("UPDATE lavoratori SET iscritto = 0 WHERE id = ?", [$lavoratore_id], 'i');
        if ($updStmt === false) {
            throw new Exception("Errore aggiornamento stato lavoratore.");
        }
    }

    if (!$mysqli->commit()) {
        throw new Exception("Commit transazione fallito.");
    }

    header("Location: gestione_iscrizioni.php?success=" . urlencode("Iscrizione eliminata con successo."));
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Errore eliminazione iscrizione (id={$iscrizione_id}): " . $e->getMessage());
    header("Location: gestione_iscrizioni.php?error=" . urlencode("Errore nell'eliminazione dell'iscrizione."));
    exit;
}
?>
