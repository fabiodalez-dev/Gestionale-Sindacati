<?php
require 'config.php';
checkLogin();
checkUserRole('admin');

// Richiede metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Metodo non consentito. Usa POST.");
}

// Verifica token CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die("Token CSRF non valido.");
}

$dbConnection = $mysqli;

// Controlla se il campo 'iscritto' esiste già
$query = "SHOW COLUMNS FROM lavoratori LIKE 'iscritto'";
$result = $dbConnection->query($query);

if ($result->num_rows == 0) {
    // Aggiunge il campo 'iscritto' alla tabella 'lavoratori'
    $alterQuery = "ALTER TABLE lavoratori ADD COLUMN iscritto TINYINT(1) NOT NULL DEFAULT 0";
    if ($dbConnection->query($alterQuery)) {
        echo "Campo 'iscritto' aggiunto con successo alla tabella 'lavoratori'.";
    } else {
        error_log("Errore durante l'aggiunta del campo 'iscritto': " . $dbConnection->error);
        echo "Errore durante l'aggiunta del campo 'iscritto'. Controlla i log per i dettagli.";
    }
} else {
    echo "Il campo 'iscritto' esiste già nella tabella 'lavoratori'.";
}
?>
