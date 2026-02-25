<?php
// Script per inserire un lavoratore di prova nel database

$host = 'localhost';
$db = 'fabiodal_adl';  // Sostituire con il nome del database
$user = 'fabiodal_adl_user';     // Sostituire con il nome utente del database
$pass = 'Zd10)uwziWlK';     // Sostituire con la password del database

// Connessione al database
$mysqli = new mysqli($host, $user, $pass, $db);

// Verifica della connessione
if ($mysqli->connect_error) {
    die("Connessione fallita: " . $mysqli->connect_error);
}

// Imposta la codifica dei caratteri
$mysqli->set_charset("utf8mb4");

// Funzione per eseguire query SQL in modo sicuro
function executeQuery($query, $params = [], $types = '') {
    global $mysqli;
    $stmt = $mysqli->prepare($query);
    if ($stmt === false) {
        die("Errore nella preparazione della query: " . $mysqli->error);
    }

    if ($params && $types) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        die("Errore nell'esecuzione della query: " . $stmt->error);
    }

    return $stmt;
}

// Inserimento di un'azienda di prova (se non esiste già)
$nome_azienda = 'Azienda di Prova';
$partita_iva = '12345678901';
$indirizzo_via = 'Via Roma';
$indirizzo_numero_civico = '1';
$indirizzo_cap = '00100';
$indirizzo_citta = 'Roma';
$indirizzo_provincia = 'RM';
$telefono_azienda = '0612345678';
$email_azienda = 'info@aziendadiprova.it';
$settore = 'Informatica';

// Verifica se l'azienda esiste già
$stmt = executeQuery(
    "SELECT id FROM aziende WHERE partita_iva = ?",
    [$partita_iva],
    's'
);
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    // L'azienda esiste già
    $azienda = $result->fetch_assoc();
    $azienda_id = $azienda['id'];
} else {
    // Inserisci l'azienda di prova
    executeQuery(
        "INSERT INTO aziende (nome_azienda, partita_iva, indirizzo_via, indirizzo_numero_civico, indirizzo_cap, indirizzo_citta, indirizzo_provincia, telefono, email, settore)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$nome_azienda, $partita_iva, $indirizzo_via, $indirizzo_numero_civico, $indirizzo_cap, $indirizzo_citta, $indirizzo_provincia, $telefono_azienda, $email_azienda, $settore],
        'ssssssssss'
    );
    // Ottieni l'id dell'azienda inserita
    $azienda_id = $mysqli->insert_id;
}

// Inserimento del lavoratore di prova
$nome = 'Mario';
$cognome = 'Rossi';
$codice_fiscale = 'RSSMRA80A01H501U'; // Codice fiscale di esempio
$data_nascita = '1980-01-01';
$indirizzo_via = 'Via Milano';
$indirizzo_numero_civico = '10';
$indirizzo_cap = '20100';
$indirizzo_citta = 'Milano';
$indirizzo_provincia = 'MI';
$telefono = '0212345678';
$email = 'mario.rossi@example.com';
$nazionalita = 'Italiana';
$foto = null; // Percorso alla foto se disponibile
$ruolo = 'Sviluppatore';
$contratto = 'indeterminato'; // Deve essere uno dei valori ammessi
$data_assunzione = '2020-01-01';
$data_fine_contratto = null; // Null per contratti indeterminati
$ore_settimanali = 40;
$ral = 30000.00;
$note = 'Lavoratore di prova per testare il sistema';

// Verifica se il lavoratore esiste già
$stmt = executeQuery(
    "SELECT id FROM lavoratori WHERE codice_fiscale = ?",
    [$codice_fiscale],
    's'
);
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo "Il lavoratore esiste già nel database.";
} else {
    // Inserisci il lavoratore di prova
    executeQuery(
        "INSERT INTO lavoratori (nome, cognome, codice_fiscale, data_nascita, indirizzo_via, indirizzo_numero_civico, indirizzo_cap, indirizzo_citta, indirizzo_provincia, telefono, email, nazionalita, foto, azienda_id, ruolo, contratto, data_assunzione, data_fine_contratto, ore_settimanali, ral, note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$nome, $cognome, $codice_fiscale, $data_nascita, $indirizzo_via, $indirizzo_numero_civico, $indirizzo_cap, $indirizzo_citta, $indirizzo_provincia, $telefono, $email, $nazionalita, $foto, $azienda_id, $ruolo, $contratto, $data_assunzione, $data_fine_contratto, $ore_settimanali, $ral, $note],
        'ssssssssssssissssdids'
    );
    echo "Lavoratore di prova inserito con successo.";
}

// Chiusura della connessione
$mysqli->close();
?>
