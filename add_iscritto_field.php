<?php
require 'config.php';
checkLogin();
checkUserRole('admin');

// Usa la variabile di connessione corretta
// Supponiamo che la connessione sia memorizzata in $conn oppure $mysqli

// Se la connessione è in $mysqli, sostituisci $conn con $mysqli
// Se la connessione è in $db, sostituisci $conn con $db

// Controlliamo quale variabile contiene la connessione
if (isset($conn)) {
    $dbConnection = $conn;
} elseif (isset($mysqli)) {
    $dbConnection = $mysqli;
} elseif (isset($db)) {
    $dbConnection = $db;
} else {
    die("Connessione al database non trovata.");
}

// Controlla se il campo 'iscritto' esiste già
$query = "SHOW COLUMNS FROM lavoratori LIKE 'iscritto'";
$result = $dbConnection->query($query);

if ($result->num_rows == 0) {
    // Aggiunge il campo 'iscritto' alla tabella 'lavoratori'
    $alterQuery = "ALTER TABLE lavoratori ADD COLUMN iscritto TINYINT(1) NOT NULL DEFAULT 0";
    if ($dbConnection->query($alterQuery)) {
        echo "Campo 'iscritto' aggiunto con successo alla tabella 'lavoratori'.";
    } else {
        echo "Errore durante l'aggiunta del campo 'iscritto': " . $dbConnection->error;
    }
} else {
    echo "Il campo 'iscritto' esiste già nella tabella 'lavoratori'.";
}
?>
