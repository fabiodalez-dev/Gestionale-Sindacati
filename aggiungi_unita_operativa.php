<?php
require 'config.php';
checkLogin();

// Verifica se il form è stato inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "Token CSRF mancante o non valido.";
        header("Location: azienda.php?id=" . $_POST['azienda_id'] . "&unita_add_error=" . urlencode($error));
        exit;
    }

    // Recupera e sanitizza i dati
    $azienda_id = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : 0;
    $nome_unita_operativa = isset($_POST['nome_unita_operativa']) ? sanitizeInput($_POST['nome_unita_operativa']) : '';
    $descrizione_unita_operativa = isset($_POST['descrizione_unita_operativa']) ? sanitizeInput($_POST['descrizione_unita_operativa']) : '';

    // Validazione
    $errors = [];
    if (empty($nome_unita_operativa)) {
        $errors[] = "Il nome dell'unità operativa è obbligatorio.";
    }

    if (empty($azienda_id)) {
        $errors[] = "ID azienda non valido.";
    }

    if (empty($errors)) {
        // Inserimento nel database
        $query = "INSERT INTO unita_operativa (azienda_id, nome_unita_operativa, descrizione) VALUES (?, ?, ?)";
        $params = [$azienda_id, $nome_unita_operativa, $descrizione_unita_operativa];
        $types = 'iss';

        $stmt = executeQuery($query, $params, $types);
        if ($stmt) {
            header("Location: azienda.php?id=$azienda_id&unita_add_success=1");
            exit;
        } else {
            $error = "Errore durante l'inserimento dell'unità operativa: " . sanitizeForHTML($mysqli->error);
            header("Location: azienda.php?id=$azienda_id&unita_add_error=" . urlencode($error));
            exit;
        }
    } else {
        // Reindirizza con gli errori
        $error = implode(' ', $errors);
        header("Location: azienda.php?id=$azienda_id&unita_add_error=" . urlencode($error));
        exit;
    }
} else {
    echo "Accesso non autorizzato.";
    exit;
}
?>
