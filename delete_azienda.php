<?php
require 'config.php';
checkLogin();
checkUserRole('admin');

// Richiede metodo POST per operazioni di eliminazione
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: aziende.php");
    exit();
}

// Verifica token CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: aziende.php?delete_error=" . urlencode("Token CSRF non valido."));
    exit;
}

$azienda_id = intval($_POST['id'] ?? 0);

if ($azienda_id <= 0) {
    header("Location: aziende.php?delete_error=" . urlencode("ID azienda non valido."));
    exit();
}

// Controlla e elimina in transazione per evitare race condition
global $mysqli;
$mysqli->begin_transaction();
try {
    // Lock riga azienda per prevenire eliminazioni concorrenti
    $lockStmt = executeQuery("SELECT id FROM aziende WHERE id = ? FOR UPDATE", [$azienda_id], 'i');
    if ($lockStmt === false || $lockStmt->get_result()->num_rows === 0) {
        throw new Exception("Azienda non trovata o già eliminata.");
    }

    // Controlla se ci sono lavoratori associati
    $countStmt = executeQuery("SELECT COUNT(*) AS count FROM lavoratori WHERE azienda_id = ?", [$azienda_id], 'i');
    if ($countStmt === false) {
        throw new Exception("Errore durante il controllo dei lavoratori associati.");
    }
    $count = $countStmt->get_result()->fetch_assoc()['count'];

    if ($count > 0) {
        $mysqli->rollback();
        $error = "Non è possibile eliminare l'azienda perché ci sono lavoratori associati. Alcuni di questi potrebbero essere archiviati";
        header("Location: aziende.php?delete_error=" . urlencode($error));
        exit;
    }

    // Elimina l'azienda
    $delStmt = executeQuery("DELETE FROM aziende WHERE id = ?", [$azienda_id], 'i');
    if ($delStmt === false || $delStmt->affected_rows !== 1) {
        throw new Exception("Errore durante l'eliminazione dell'azienda.");
    }

    if (!$mysqli->commit()) {
        throw new Exception("Commit transazione fallito.");
    }

    header("Location: aziende.php?delete_success=1");
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Errore eliminazione azienda (id={$azienda_id}): " . $e->getMessage());
    header("Location: aziende.php?delete_error=" . urlencode($e->getMessage()));
    exit;
}
?>
