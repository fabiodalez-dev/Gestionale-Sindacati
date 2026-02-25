<?php
require 'config.php';
checkLogin();

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: azienda.php?id=" . intval($_POST['azienda_id']) . "&upload_error=Metodo non consentito.");
    exit;
}

// Verifica il token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    $error = "Token CSRF mancante o non valido.";
    header("Location: azienda.php?id=" . intval($_POST['azienda_id']) . "&upload_error=" . urlencode($error));
    exit;
}

// Recupera e sanitizza i dati
$azienda_id = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : null;
$descrizione_documento = isset($_POST['descrizione_documento']) ? sanitizeInput($_POST['descrizione_documento']) : '';

// Verifica che i campi obbligatori siano presenti
if ($azienda_id === null || empty($descrizione_documento) || !isset($_FILES['documento'])) {
    $error = "Tutti i campi sono obbligatori.";
    header("Location: azienda.php?id=" . $azienda_id . "&upload_error=" . urlencode($error));
    exit;
}

// Gestione dell'upload del file
$target_dir = "uploads/";
$target_file = $target_dir . basename($_FILES["documento"]["name"]);
$uploadOk = 1;
$fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

// Controlla se il file è un documento
$allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'];
if(!in_array($fileType, $allowed_types)) {
    $error = "Solo i seguenti formati di file sono permessi: " . implode(", ", $allowed_types);
    $uploadOk = 0;
}

// Controlla se $uploadOk è settato a 0 da un errore
if ($uploadOk == 0) {
    header("Location: azienda.php?id=" . $azienda_id . "&upload_error=" . urlencode($error));
    exit;
// Se tutto è ok, tenta di caricare il file
} else {
    // Crea una stringa univoca per evitare conflitti di nome
    $unique_name = uniqid() . "_" . basename($_FILES["documento"]["name"]);
    $target_file = $target_dir . $unique_name;
    
    if (move_uploaded_file($_FILES["documento"]["tmp_name"], $target_file)) {
        // Inserisci il documento nella tabella
        $query = "INSERT INTO documenti_aziende (azienda_id, descrizione_documento, percorso_documento, data_caricamento) VALUES (?, ?, ?, NOW())";
        $params = [$azienda_id, $descrizione_documento, $unique_name];
        $types = 'iss';
        
        $stmt = executeQuery($query, $params, $types);
        
        if ($stmt) {
            header("Location: azienda.php?id=" . $azienda_id . "&upload_success=1");
            exit;
        } else {
            // Elimina il file caricato in caso di errore
            unlink($target_file);
            $error = "Errore durante l'inserimento del documento nel database.";
            header("Location: azienda.php?id=" . $azienda_id . "&upload_error=" . urlencode($error));
            exit;
        }
    } else {
        $error = "Si è verificato un errore durante l'upload del file.";
        header("Location: azienda.php?id=" . $azienda_id . "&upload_error=" . urlencode($error));
        exit;
    }
}
?>
