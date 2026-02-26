<?php
require_once 'config.php';
checkLogin();

// Imposta il fuso orario corretto
date_default_timezone_set('Europe/Rome');

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        header("Location: lavoratori.php?archive_error=Token CSRF non valido.");
        exit;
    }

    // Recupera e sanitizza l'ID del lavoratore
    $lavoratore_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($lavoratore_id > 0) {
        // Inizia una transazione per assicurare la coerenza dei dati
        $mysqli->begin_transaction();

        try {
            // Aggiorna le colonne 'archiviato' a 1 e 'iscritto' a 0
            $query = "UPDATE lavoratori SET archiviato = 1, iscritto = 0 WHERE id = ?";
            $stmt = executeQuery($query, [$lavoratore_id], 'i');
            if ($stmt === false) {
                throw new Exception("Errore durante l'archiviazione del lavoratore.");
            }

            // Recupera il tipo di tessera del lavoratore
            $query_tessera = "SELECT tipo_tessera FROM lavoratori WHERE id = ?";
            $stmt_tessera = executeQuery($query_tessera, [$lavoratore_id], 'i');
            if ($stmt_tessera === false) {
                throw new Exception("Errore nel recupero del tipo di tessera.");
            }
            $result_tessera = $stmt_tessera->get_result();
            if ($result_tessera->num_rows > 0) {
                $lavoratore = $result_tessera->fetch_assoc();
                $tipo_tessera = $lavoratore['tipo_tessera'];
            } else {
                throw new Exception("Lavoratore non trovato durante l'archiviazione.");
            }
            $stmt_tessera->close();

            // Se il tipo di tessera è 'rinnovo annuale', sospendi l'iscrizione
            if ($tipo_tessera === 'rinnovo annuale') {
                // Recupera l'iscrizione attuale
                $today = date('Y-m-d');
                $query_subscription = "SELECT id, data_fine FROM iscrizioni WHERE lavoratore_id = ? ORDER BY data_fine DESC, data_inizio DESC LIMIT 1";
                $stmt_sub = executeQuery($query_subscription, [$lavoratore_id], 'i');
                if ($stmt_sub === false) {
                    throw new Exception("Errore nel recupero dell'iscrizione.");
                }
                $result_sub = $stmt_sub->get_result();
                if ($result_sub->num_rows > 0) {
                    $subscription = $result_sub->fetch_assoc();
                    $subscription_id = $subscription['id'];
                    $current_data_fine = $subscription['data_fine'];

                    // Solo se l'iscrizione non è già scaduta
                    if (is_null($current_data_fine) || $current_data_fine >= $today) {
                        $query_update_subscription = "UPDATE iscrizioni SET data_fine = ? WHERE id = ?";
                        $stmt_update = executeQuery($query_update_subscription, [$today, $subscription_id], 'si');
                        if ($stmt_update === false) {
                            throw new Exception("Errore nell'aggiornamento dell'iscrizione.");
                        }
                    }
                }
                $stmt_sub->close();
            }

            // Conferma la transazione
            $mysqli->commit();

            header("Location: lavoratore.php?id=" . $lavoratore_id . "&archive_success=1");
            exit;
        } catch (Exception $e) {
            // Annulla la transazione in caso di errore
            $mysqli->rollback();
            header("Location: lavoratore.php?id=" . $lavoratore_id . "&archive_error=" . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header("Location: lavoratori.php?archive_error=ID lavoratore non valido.");
        exit;
    }
} else {
    header("Location: lavoratori.php?archive_error=Metodo di richiesta non valido.");
    exit;
}
?>
