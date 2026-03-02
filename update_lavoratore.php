<?php
require_once 'config.php'; // Usa require_once per evitare inclusioni multiple
checkLogin();

// Inizializza variabili per messaggi di errore e successo
$errors = [];

// Genera un token CSRF per il form
generateCsrfToken();

// Recupera l'ID del lavoratore da modificare
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("ID lavoratore non valido.");
}

// Recupera i dati del lavoratore per precompilare il modulo
$query = "SELECT * FROM lavoratori WHERE id = ?";
$stmt = executeQuery($query, [$id], 'i');

if ($stmt) {
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die("Lavoratore non trovato.");
    }
    $lavoratore = $result->fetch_assoc();
} else {
    die("Errore durante il recupero dei dati del lavoratore.");
}

// Se il form è stato inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Token CSRF mancante o non valido.";
    }

    // Recupero e sanitizzazione dei dati
    $nome = sanitizeForDatabase($_POST['nome'] ?? '');
    $cognome = sanitizeForDatabase($_POST['cognome'] ?? '');
    $codice_fiscale = !empty($_POST['codice_fiscale']) ? sanitizeForDatabase($_POST['codice_fiscale']) : null;
    $data_nascita = !empty($_POST['data_nascita']) ? sanitizeForDatabase($_POST['data_nascita']) : null;
    $nazionalita = !empty($_POST['nazionalita']) ? sanitizeForDatabase($_POST['nazionalita']) : null;
    $paese_nascita = !empty($_POST['paese_nascita']) ? sanitizeForDatabase($_POST['paese_nascita']) : null;
    $genere = !empty($_POST['genere']) ? sanitizeForDatabase($_POST['genere']) : null;
    $data_iscrizione = !empty($_POST['data_iscrizione']) ? sanitizeForDatabase($_POST['data_iscrizione']) : null;
    $ccnl = !empty($_POST['ccnl']) ? sanitizeForDatabase($_POST['ccnl']) : null;
    $tipo_tessera = !empty($_POST['tipo_tessera']) ? sanitizeForDatabase($_POST['tipo_tessera']) : 'rinnovo annuale';
    $settore = isset($_POST['settore']) ? sanitizeForDatabase($_POST['settore']) : 'privato';
    $vertenze = isset($_POST['vertenze']) ? intval($_POST['vertenze']) : 0;
    $iscritto = isset($_POST['iscritto']) ? intval($_POST['iscritto']) : 0;
    // Forza iscritto = 1 per trattenuta in busta paga e SEPA
    if ($tipo_tessera === 'trattenuta in busta paga' || $tipo_tessera === 'sepa') {
        $iscritto = 1;
    }
    $indirizzo_via = !empty($_POST['indirizzo_via']) ? sanitizeForDatabase($_POST['indirizzo_via']) : null;
    $indirizzo_numero_civico = !empty($_POST['indirizzo_numero_civico']) ? sanitizeForDatabase($_POST['indirizzo_numero_civico']) : null;
    $indirizzo_cap = !empty($_POST['indirizzo_cap']) ? sanitizeForDatabase($_POST['indirizzo_cap']) : null;
    $indirizzo_citta = !empty($_POST['indirizzo_citta']) ? sanitizeForDatabase($_POST['indirizzo_citta']) : null;
    $indirizzo_provincia = !empty($_POST['indirizzo_provincia']) ? sanitizeForDatabase($_POST['indirizzo_provincia']) : null;
    $telefono = !empty($_POST['telefono']) ? sanitizeForDatabase($_POST['telefono']) : null;
    $email = !empty($_POST['email']) ? sanitizeForDatabase($_POST['email']) : null;
    $ruolo = !empty($_POST['ruolo']) ? sanitizeForDatabase($_POST['ruolo']) : null;
    $tipo_contratto = !empty($_POST['tipo_contratto']) ? sanitizeForDatabase($_POST['tipo_contratto']) : null;
    $orario_contratto = !empty($_POST['orario_contratto']) ? sanitizeForDatabase($_POST['orario_contratto']) : 'tempo pieno';
    $data_assunzione = !empty($_POST['data_assunzione']) ? sanitizeForDatabase($_POST['data_assunzione']) : null;
    $data_fine_contratto = !empty($_POST['data_fine_contratto']) ? sanitizeForDatabase($_POST['data_fine_contratto']) : null;
    $ore_settimanali = isset($_POST['ore_settimanali']) ? intval($_POST['ore_settimanali']) : null;
    $ral = isset($_POST['ral']) ? floatval($_POST['ral']) : null;
    $note = $_POST['note'] ?? '';
    $note_pulito = $note; // Permetti il rendering dei tag HTML

    // Nuovi campi: Azienda e Unità Operativa
    $nome_azienda = sanitizeForDatabase($_POST['azienda'] ?? '');
    $nome_unita_operativa = sanitizeForDatabase($_POST['unita_operativa'] ?? '');
    $unita_operativa_id = intval($_POST['unita_operativa_id'] ?? 0); // ID esistente

    // Validazione dei campi obbligatori (solo Nome e Cognome)
    if (empty($nome)) {
        $errors[] = "Il campo 'Nome' è obbligatorio.";
    }
    if (empty($cognome)) {
        $errors[] = "Il campo 'Cognome' è obbligatorio.";
    }

    // Validazione del settore
    $valid_settori = ['privato', 'pubblico'];
    if (!in_array($settore, $valid_settori)) {
        $errors[] = "Il settore selezionato non è valido.";
    }

    // Validazione del tipo di tessera
    $valid_tipi_tessera = ['trattenuta in busta paga', 'rinnovo annuale', 'sepa'];
    if (!in_array($tipo_tessera, $valid_tipi_tessera)) {
        $errors[] = "Il tipo di tessera selezionato non è valido.";
    }

    // Validazione del genere
    $valid_generi = ['Maschio', 'Femmina', 'Altro'];
    if (!in_array($genere, $valid_generi)) {
        $errors[] = "Il genere selezionato non è valido.";
    }

    // Validazione dell'orario contratto
    $valid_orario_contratto = ['tempo pieno', 'part time'];
    if (!in_array($orario_contratto, $valid_orario_contratto)) {
        $errors[] = "L'orario di contratto selezionato non è valido.";
    }

    // Validazione del tipo di contratto
    $valid_tipo_contratto = ['Indeterminato', 'Determinato', 'Progetto', 'Apprendistato', 'Altro'];
    if (!in_array($tipo_contratto, $valid_tipo_contratto)) {
        $errors[] = "Il tipo di contratto selezionato non è valido.";
    }

    // Validazione di vertenze
    $vertenze = ($vertenze === 1) ? 1 : 0;

    // Validazione email se fornita
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email non valida.";
    }

    // Validazione della data di assunzione (non futura)
    if (!empty($data_assunzione) && strtotime($data_assunzione) > time()) {
        $errors[] = "La data di assunzione non può essere futura.";
    }

    // Gestione dell'azienda
    if (!empty($nome_azienda)) {
        // Controlla se l'azienda esiste
        $query = "SELECT id FROM aziende WHERE nome_azienda = ?";
        $stmt = executeQuery($query, [$nome_azienda], 's');
        if ($stmt) {
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $azienda = $result->fetch_assoc();
                $azienda_id = $azienda['id'];
            } else {
                // Se l'azienda non esiste, la crea con partita_iva NULL
                $query = "INSERT INTO aziende (nome_azienda, partita_iva) VALUES (?, ?)";
                $stmt = executeQuery($query, [$nome_azienda, null], 'ss');
                if ($stmt) {
                    $azienda_id = $mysqli->insert_id;
                } else {
                    // Gestione errore durante la creazione dell'azienda
                    error_log("Errore nella creazione dell'azienda: " . $mysqli->error);
                    $errors[] = "Errore durante la creazione dell'azienda.";
                }
            }
        } else {
            $errors[] = "Errore durante la ricerca dell'azienda.";
        }
    } else {
        $azienda_id = null;
    }

    // Gestione dell'unità operativa
    if (!empty($nome_unita_operativa)) {
        if ($unita_operativa_id > 0) {
            // Verifica che l'unità operativa appartenga all'azienda selezionata
            $query = "SELECT id FROM unita_operativa WHERE id = ? AND azienda_id = ?";
            $stmt = executeQuery($query, [$unita_operativa_id, $azienda_id], 'ii');
            if ($stmt) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    // L'unità operativa esiste e appartiene all'azienda
                } else {
                    $errors[] = "Unità operativa selezionata non valida per l'azienda.";
                }
            } else {
                $errors[] = "Errore durante la verifica dell'unità operativa.";
            }
        } else {
            // Crea una nuova unità operativa
            $query = "INSERT INTO unita_operativa (azienda_id, nome_unita_operativa, descrizione) VALUES (?, ?, ?)";
            $descrizione_unita_operativa = ''; // Puoi modificare se necessario
            $stmt = executeQuery($query, [$azienda_id, $nome_unita_operativa, $descrizione_unita_operativa], 'iss');
            if ($stmt) {
                $unita_operativa_id = $mysqli->insert_id;
            } else {
                error_log("Errore durante la creazione dell'unità operativa: " . $mysqli->error);
                $errors[] = "Errore durante la creazione dell'unità operativa.";
            }
        }
    } else {
        $unita_operativa_id = null;
    }

    // Se non ci sono errori, procedi con l'aggiornamento
    if (empty($errors)) {
        // Inserisci nel database
        $query = "
            UPDATE lavoratori SET
                nome = ?, cognome = ?, codice_fiscale = ?, data_nascita = ?, nazionalita = ?, paese_nascita = ?, genere = ?, data_iscrizione = ?,
                ccnl = ?, tipo_tessera = ?, settore = ?, vertenze = ?, iscritto = ?,
                indirizzo_via = ?, indirizzo_numero_civico = ?, indirizzo_cap = ?, indirizzo_citta = ?, indirizzo_provincia = ?,
                telefono = ?, email = ?, ruolo = ?, azienda_id = ?, unita_operativa_id = ?, tipo_contratto = ?, orario_contratto = ?, data_assunzione = ?, data_fine_contratto = ?,
                ore_settimanali = ?, ral = ?, note = ?
            WHERE id = ?
        ";

        $params = [
            $nome, $cognome, $codice_fiscale, $data_nascita, $nazionalita, $paese_nascita, $genere, $data_iscrizione,
            $ccnl, $tipo_tessera, $settore, $vertenze, $iscritto,
            $indirizzo_via, $indirizzo_numero_civico, $indirizzo_cap, $indirizzo_citta, $indirizzo_provincia,
            $telefono, $email, $ruolo, $azienda_id, $unita_operativa_id, $tipo_contratto, $orario_contratto, $data_assunzione, $data_fine_contratto,
            $ore_settimanali, $ral, $note_pulito,
            $id
        ];

        $types = buildTypesString($params);

        // Esegui l'aggiornamento nel database
        $stmt = executeQuery($query, $params, $types);

        if ($stmt) {
            // Reindirizza alla pagina del lavoratore con un messaggio di successo
            header("Location: lavoratore.php?id=$id&update_success=1");
            exit;
        } else {
            error_log("Errore durante l'aggiornamento del lavoratore: " . $mysqli->error);
            $errors[] = "Errore durante l'aggiornamento del lavoratore.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Aggiornamento Lavoratore - CRM Admin</title>
    <!-- SB Admin 2 CSS -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.6" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- jQuery UI CSS per l'autocomplete -->
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.css">
    <!-- Custom CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.6" rel="stylesheet">
    <!-- TinyMCE -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        /* Personalizza gli stili dell'autocomplete */
        .ui-autocomplete {
            z-index: 1051 !important; /* Assicurati che l'autocomplete appaia sopra i modali */
        }
    </style>
</head>
<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <?php include 'topbar.php'; ?>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <button onclick="history.back()" class="btn btn-secondary mb-4">
                        <i class="fas fa-arrow-left"></i> Indietro
                    </button>
                    <!-- Titolo della Pagina -->
                    <h1 class="h3 mb-4 text-gray-800">Aggiornamento Lavoratore</h1>

                    <!-- Gestione dei Messaggi -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitizeForHTML($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <a href="edit_lavoratore.php?id=<?php echo $id; ?>" class="btn btn-primary">Torna indietro</a>
                    <?php endif; ?>


                    <!-- Modulo per aggiornare il lavoratore -->
                    <div class="card mb-4">
                        <div class="card-header">
                            Dettagli Lavoratore
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <!-- Token CSRF -->
                                <?php csrfInputField(); ?>

                                <!-- Dati Personali -->
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="nome">Nome <span class="text-danger">*</span></label>
                                        <input type="text" name="nome" id="nome" class="form-control" value="<?php echo isset($_POST['nome']) ? sanitizeForHTML($_POST['nome']) : sanitizeForHTML($lavoratore['nome']); ?>" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="cognome">Cognome <span class="text-danger">*</span></label>
                                        <input type="text" name="cognome" id="cognome" class="form-control" value="<?php echo isset($_POST['cognome']) ? sanitizeForHTML($_POST['cognome']) : sanitizeForHTML($lavoratore['cognome']); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="codice_fiscale">Codice Fiscale</label>
                                        <input type="text" name="codice_fiscale" id="codice_fiscale" class="form-control" maxlength="16" value="<?php echo isset($_POST['codice_fiscale']) ? sanitizeForHTML($_POST['codice_fiscale']) : sanitizeForHTML($lavoratore['codice_fiscale']); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="data_nascita">Data di Nascita</label>
                                        <input type="date" name="data_nascita" id="data_nascita" class="form-control" value="<?php echo isset($_POST['data_nascita']) ? sanitizeForHTML($_POST['data_nascita']) : sanitizeForHTML($lavoratore['data_nascita']); ?>">
                                    </div>
                                </div>
                                <!-- Altri campi personali -->
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="paese_nascita">Paese di Nascita</label>
                                        <input type="text" name="paese_nascita" id="paese_nascita" class="form-control" value="<?php echo isset($_POST['paese_nascita']) ? sanitizeForHTML($_POST['paese_nascita']) : sanitizeForHTML($lavoratore['paese_nascita']); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="nazionalita">Nazionalità</label>
                                        <input type="text" name="nazionalita" id="nazionalita" class="form-control" value="<?php echo isset($_POST['nazionalita']) ? sanitizeForHTML($_POST['nazionalita']) : sanitizeForHTML($lavoratore['nazionalita']); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="genere">Genere</label>
                                    <select name="genere" id="genere" class="form-control">
                                        <option value="">Seleziona...</option>
                                        <option value="Maschio" <?php echo (isset($_POST['genere']) && $_POST['genere'] == 'Maschio') || ($lavoratore['genere'] == 'Maschio' && !isset($_POST['genere'])) ? 'selected' : ''; ?>>Maschio</option>
                                        <option value="Femmina" <?php echo (isset($_POST['genere']) && $_POST['genere'] == 'Femmina') || ($lavoratore['genere'] == 'Femmina' && !isset($_POST['genere'])) ? 'selected' : ''; ?>>Femmina</option>
                                        <option value="Altro" <?php echo (isset($_POST['genere']) && $_POST['genere'] == 'Altro') || ($lavoratore['genere'] == 'Altro' && !isset($_POST['genere'])) ? 'selected' : ''; ?>>Altro</option>
                                    </select>
                                </div>

                                <!-- Campo Data di Iscrizione -->
                                <div class="form-group">
                                    <label for="data_iscrizione">Data di Iscrizione</label>
                                    <input type="date" name="data_iscrizione" id="data_iscrizione" class="form-control" value="<?php echo isset($_POST['data_iscrizione']) ? sanitizeForHTML($_POST['data_iscrizione']) : sanitizeForHTML($lavoratore['data_iscrizione']); ?>">
                                </div>

                                <!-- Campo Iscritto -->
                                <div class="form-group">
                                    <label for="iscritto">Attivo</label>
                                    <select name="iscritto" id="iscritto" class="form-control">
                                        <option value="0" <?php echo (isset($_POST['iscritto']) && $_POST['iscritto'] == 0) || ($lavoratore['iscritto'] == 0 && !isset($_POST['iscritto'])) ? 'selected' : ''; ?>>No</option>
                                        <option value="1" <?php echo (isset($_POST['iscritto']) && $_POST['iscritto'] == 1) || ($lavoratore['iscritto'] == 1 && !isset($_POST['iscritto'])) ? 'selected' : ''; ?>>Sì</option>
                                    </select>
                                </div>

                                <!-- Campo Settore -->
                                <div class="form-group">
                                    <label for="settore">Settore</label>
                                    <select name="settore" id="settore" class="form-control">
                                        <option value="privato" <?php echo (isset($_POST['settore']) && $_POST['settore'] == 'privato') || ($lavoratore['settore'] == 'privato' && !isset($_POST['settore'])) ? 'selected' : ''; ?>>Privato</option>
                                        <option value="pubblico" <?php echo (isset($_POST['settore']) && $_POST['settore'] == 'pubblico') || ($lavoratore['settore'] == 'pubblico' && !isset($_POST['settore'])) ? 'selected' : ''; ?>>Pubblico</option>
                                    </select>
                                </div>

                                <!-- Campo Tipo di Tessera -->
                                <div class="form-group">
                                    <label for="tipo_tessera">Tipo di Tessera</label>
                                    <select name="tipo_tessera" id="tipo_tessera" class="form-control">
                                        <option value="trattenuta in busta paga" <?php echo (isset($_POST['tipo_tessera']) && $_POST['tipo_tessera'] == 'trattenuta in busta paga') || ($lavoratore['tipo_tessera'] == 'trattenuta in busta paga' && !isset($_POST['tipo_tessera'])) ? 'selected' : ''; ?>>Trattenuta in busta paga</option>
                                        <option value="rinnovo annuale" <?php echo (isset($_POST['tipo_tessera']) && $_POST['tipo_tessera'] == 'rinnovo annuale') || ($lavoratore['tipo_tessera'] == 'rinnovo annuale' && !isset($_POST['tipo_tessera'])) ? 'selected' : ''; ?>>Rinnovo annuale</option>
                                        <option value="sepa" <?php echo (isset($_POST['tipo_tessera']) && $_POST['tipo_tessera'] == 'sepa') || ($lavoratore['tipo_tessera'] == 'sepa' && !isset($_POST['tipo_tessera'])) ? 'selected' : ''; ?>>SEPA</option>
                                    </select>
                                </div>

                                <!-- Campo Vertenze -->
                                <div class="form-group">
                                    <label for="vertenze">Vertenze</label>
                                    <select name="vertenze" id="vertenze" class="form-control">
                                        <option value="0" <?php echo (isset($_POST['vertenze']) && $_POST['vertenze'] == 0) || ($lavoratore['vertenze'] == 0 && !isset($_POST['vertenze'])) ? 'selected' : ''; ?>>No</option>
                                        <option value="1" <?php echo (isset($_POST['vertenze']) && $_POST['vertenze'] == 1) || ($lavoratore['vertenze'] == 1 && !isset($_POST['vertenze'])) ? 'selected' : ''; ?>>Sì</option>
                                    </select>
                                </div>

                                <!-- Indirizzo -->
                                <h4 class="mt-4">Indirizzo</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="indirizzo_via">Via</label>
                                        <input type="text" name="indirizzo_via" id="indirizzo_via" class="form-control" value="<?php echo isset($_POST['indirizzo_via']) ? sanitizeForHTML($_POST['indirizzo_via']) : sanitizeForHTML($lavoratore['indirizzo_via']); ?>">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="indirizzo_numero_civico">Numero Civico</label>
                                        <input type="text" name="indirizzo_numero_civico" id="indirizzo_numero_civico" class="form-control" value="<?php echo isset($_POST['indirizzo_numero_civico']) ? sanitizeForHTML($_POST['indirizzo_numero_civico']) : sanitizeForHTML($lavoratore['indirizzo_numero_civico']); ?>">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="indirizzo_cap">CAP</label>
                                        <input type="text" name="indirizzo_cap" id="indirizzo_cap" class="form-control" pattern="\d{5}" title="CAP valido. Deve contenere 5 cifre." value="<?php echo isset($_POST['indirizzo_cap']) ? sanitizeForHTML($_POST['indirizzo_cap']) : sanitizeForHTML($lavoratore['indirizzo_cap']); ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="indirizzo_citta">Città</label>
                                        <input type="text" name="indirizzo_citta" id="indirizzo_citta" class="form-control" value="<?php echo isset($_POST['indirizzo_citta']) ? sanitizeForHTML($_POST['indirizzo_citta']) : sanitizeForHTML($lavoratore['indirizzo_citta']); ?>">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="indirizzo_provincia">Provincia</label>
                                        <input type="text" name="indirizzo_provincia" id="indirizzo_provincia" class="form-control" maxlength="2" pattern="[A-Za-z]{2}" title="Provincia valida. Deve essere composta da 2 lettere." value="<?php echo isset($_POST['indirizzo_provincia']) ? sanitizeForHTML($_POST['indirizzo_provincia']) : sanitizeForHTML($lavoratore['indirizzo_provincia']); ?>">
                                    </div>
                                </div>

                                <!-- Contatti -->
                                <h4 class="mt-4">Contatti</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="telefono">Telefono</label>
                                        <input type="text" name="telefono" id="telefono" class="form-control" value="<?php echo isset($_POST['telefono']) ? sanitizeForHTML($_POST['telefono']) : sanitizeForHTML($lavoratore['telefono']); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="email">Email</label>
                                        <input type="email" name="email" id="email" class="form-control" value="<?php echo isset($_POST['email']) ? sanitizeForHTML($_POST['email']) : sanitizeForHTML($lavoratore['email']); ?>">
                                    </div>
                                </div>

                                <!-- Informazioni Aziendali -->
                                <h4 class="mt-4">Informazioni Aziendali</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="azienda">Azienda <span class="text-danger">*</span></label>
                                        <input type="text" name="azienda" id="azienda" class="form-control" value="<?php echo isset($_POST['azienda']) ? sanitizeForHTML($_POST['azienda']) : sanitizeForHTML($lavoratore['nome_azienda']); ?>" required>
                                        <input type="hidden" name="azienda_id" id="azienda_id" value="<?php echo intval($_POST['azienda_id'] ?? $lavoratore['azienda_id']); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="unita_operativa">Unità Operativa <span class="text-danger">*</span></label>
                                        <input type="text" name="unita_operativa" id="unita_operativa" class="form-control" value="<?php echo isset($_POST['unita_operativa']) ? sanitizeForHTML($_POST['unita_operativa']) : sanitizeForHTML($lavoratore['nome_unita_operativa']); ?>" required>
                                        <input type="hidden" name="unita_operativa_id" id="unita_operativa_id" value="<?php echo intval($_POST['unita_operativa_id'] ?? $lavoratore['unita_operativa_id']); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ruolo">Ruolo</label>
                                    <input type="text" name="ruolo" id="ruolo" class="form-control" value="<?php echo isset($_POST['ruolo']) ? sanitizeForHTML($_POST['ruolo']) : sanitizeForHTML($lavoratore['ruolo']); ?>">
                                </div>

                                <!-- Contratto -->
                                <h4 class="mt-4">Contratto</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="tipo_contratto">Tipo di Contratto</label>
                                        <select name="tipo_contratto" id="tipo_contratto" class="form-control">
                                            <option value="Indeterminato" <?php echo (isset($_POST['tipo_contratto']) && $_POST['tipo_contratto'] == 'Indeterminato') || ($lavoratore['tipo_contratto'] == 'Indeterminato' && !isset($_POST['tipo_contratto'])) ? 'selected' : ''; ?>>Indeterminato</option>
                                            <option value="Determinato" <?php echo (isset($_POST['tipo_contratto']) && $_POST['tipo_contratto'] == 'Determinato') || ($lavoratore['tipo_contratto'] == 'Determinato' && !isset($_POST['tipo_contratto'])) ? 'selected' : ''; ?>>Determinato</option>
                                            <option value="Progetto" <?php echo (isset($_POST['tipo_contratto']) && $_POST['tipo_contratto'] == 'Progetto') || ($lavoratore['tipo_contratto'] == 'Progetto' && !isset($_POST['tipo_contratto'])) ? 'selected' : ''; ?>>Progetto</option>
                                            <option value="Apprendistato" <?php echo (isset($_POST['tipo_contratto']) && $_POST['tipo_contratto'] == 'Apprendistato') || ($lavoratore['tipo_contratto'] == 'Apprendistato' && !isset($_POST['tipo_contratto'])) ? 'selected' : ''; ?>>Apprendistato</option>
                                            <option value="Altro" <?php echo (isset($_POST['tipo_contratto']) && $_POST['tipo_contratto'] == 'Altro') || ($lavoratore['tipo_contratto'] == 'Altro' && !isset($_POST['tipo_contratto'])) ? 'selected' : ''; ?>>Altro</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="orario_contratto">Orario del Contratto</label>
                                        <select name="orario_contratto" id="orario_contratto" class="form-control">
                                            <option value="tempo pieno" <?php echo (isset($_POST['orario_contratto']) && $_POST['orario_contratto'] == 'tempo pieno') || ($lavoratore['orario_contratto'] == 'tempo pieno' && !isset($_POST['orario_contratto'])) ? 'selected' : ''; ?>>Tempo Pieno</option>
                                            <option value="part time" <?php echo (isset($_POST['orario_contratto']) && $_POST['orario_contratto'] == 'part time') || ($lavoratore['orario_contratto'] == 'part time' && !isset($_POST['orario_contratto'])) ? 'selected' : ''; ?>>Part Time</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ccnl">CCNL</label>
                                    <input type="text" name="ccnl" id="ccnl" class="form-control" value="<?php echo isset($_POST['ccnl']) ? sanitizeForHTML($_POST['ccnl']) : sanitizeForHTML($lavoratore['ccnl']); ?>">
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="data_assunzione">Data di Assunzione</label>
                                        <input type="date" name="data_assunzione" id="data_assunzione" class="form-control" value="<?php echo isset($_POST['data_assunzione']) ? sanitizeForHTML($_POST['data_assunzione']) : sanitizeForHTML($lavoratore['data_assunzione']); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="data_fine_contratto">Data Fine Contratto</label>
                                        <input type="date" name="data_fine_contratto" id="data_fine_contratto" class="form-control" value="<?php echo isset($_POST['data_fine_contratto']) ? sanitizeForHTML($_POST['data_fine_contratto']) : sanitizeForHTML($lavoratore['data_fine_contratto']); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ore_settimanali">Ore Settimanali</label>
                                    <input type="number" name="ore_settimanali" id="ore_settimanali" class="form-control" value="<?php echo isset($_POST['ore_settimanali']) ? intval($_POST['ore_settimanali']) : intval($lavoratore['ore_settimanali']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="ral">RAL</label>
                                    <input type="number" step="0.01" name="ral" id="ral" class="form-control" value="<?php echo isset($_POST['ral']) ? sanitizeForHTML($_POST['ral']) : sanitizeForHTML($lavoratore['ral']); ?>">
                                </div>

                                <!-- Note -->
                                <div class="form-group">
                                    <label for="note">Note</label>
                                    <textarea name="note" id="note" class="form-control" rows="5"><?php echo isset($_POST['note']) ? htmlspecialchars($_POST['note'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($lavoratore['note'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>

                                <!-- Pulsanti -->
                                <button type="submit" class="btn btn-primary">Aggiorna</button>
                                <a href="lavoratori.php" class="btn btn-secondary">Annulla</a>
                            </form>
                        </div>
                    </div>

                </div>
                <!-- End of Page Content -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <?php include 'footer.php'; ?>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Modali e script -->
    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Bootstrap core JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- SB Admin 2 JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>

    <!-- jQuery UI per l'autocomplete -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.js"></script>

    <!-- SweetAlert2 per i messaggi -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.js"></script>

    <!-- Inizializzazione di TinyMCE -->
    <script>
        if (typeof tinymce !== 'undefined') { tinymce.init({
            selector: '#note',
            plugins: 'advlist autolink lists link image charmap preview anchor pagebreak',
            toolbar: 'undo redo | formatselect | bold italic backcolor | ' +
                      'alignleft aligncenter alignright alignjustify | ' +
                      'bullist numlist outdent indent | removeformat | help',
            entity_encoding: 'raw',
            forced_root_block: '',
            toolbar_mode: 'floating',
            menubar: false,
            branding: false,
            height: 300,
            setup: function (editor) {
                editor.on('init', function () {
                    this.getContainer().style.zIndex = 1040;
                });
            },
            inline: false
        }); }
    </script>

    <script>
        $(function() {
            // Autocomplete per il campo Azienda
            $("#azienda").autocomplete({
                source: "<?php echo sanitizeForHTML($base_url); ?>autocomplete_aziende.php",
                minLength: 2,
                select: function(event, ui) {
                    $("#azienda_id").val(ui.item.id);
                    $("#azienda").val(ui.item.label);
                    // Reset del campo Unità Operativa
                    $("#unita_operativa").val('');
                    $("#unita_operativa_id").val(0);
                    // Aggiorna l'autocomplete delle unità operative
                    $("#unita_operativa").autocomplete("option", "source", "<?php echo sanitizeForHTML($base_url); ?>autocomplete_unita_operativa.php?azienda_id=" + ui.item.id);
                }
            });

            // Autocomplete per il campo Unità Operativa
            $("#unita_operativa").autocomplete({
                source: function(request, response) {
                    var azienda_id = $("#azienda_id").val();
                    if (!azienda_id) {
                        response([]);
                        return;
                    }
                    $.ajax({
                        url: "<?php echo sanitizeForHTML($base_url); ?>autocomplete_unita_operativa.php",
                        dataType: "json",
                        data: {
                            term: request.term,
                            azienda_id: azienda_id
                        },
                        success: function(data) {
                            response(data);
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    $("#unita_operativa_id").val(ui.item.id);
                    $("#unita_operativa").val(ui.item.label);
                    return false;
                },
                create: function () {
                    $(this).data('ui-autocomplete')._renderItem = function(ul, item) {
                        return $("<li>")
                            .append("<div>" + item.label + "</div>")
                            .appendTo(ul);
                    };
                }
            }).on('autocompleteselect', function (e, ui) {
                // Selezionare un'unità operativa esistente
                $("#unita_operativa_id").val(ui.item.id);
                $("#unita_operativa").val(ui.item.label);
            }).on('autocompleteselect autocompletechange', function(event, ui) {
                if (!ui.item) {
                    // Se l'unità operativa non esiste, resettare l'ID
                    $("#unita_operativa_id").val(0);
                }
            });

            // Permetti la creazione di una nuova unità operativa
            $("#unita_operativa").on('blur', function() {
                var unita_val = $(this).val().trim();
                if (unita_val !== "" && $("#unita_operativa_id").val() === "0") {
                    // Chiedi conferma per creare una nuova unità operativa
                    Swal.fire({
                        title: 'Creare una nuova Unità Operativa?',
                        text: "L'unità operativa '" + unita_val + "' non esiste. Vuoi crearla?",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Crea'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Invia richiesta AJAX per creare la nuova unità operativa
                            $.ajax({
                                url: "<?php echo sanitizeForHTML($base_url); ?>create_unita_operativa.php",
                                type: "POST",
                                dataType: "json",
                                data: {
                                    nome_unita_operativa: unita_val,
                                    azienda_id: $("#azienda_id").val(),
                                    csrf_token: $('input[name="csrf_token"]').val()
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // Imposta l'ID e aggiorna il campo
                                        $("#unita_operativa_id").val(response.unita_id);
                                        $("#unita_operativa").val(response.unita_nome);
                                        Swal.fire(
                                            'Creato!',
                                            response.message,
                                            'success'
                                        );
                                    } else {
                                        Swal.fire(
                                            'Errore!',
                                            response.message,
                                            'error'
                                        );
                                        // Reset del campo
                                        $("#unita_operativa").val('');
                                        $("#unita_operativa_id").val(0);
                                    }
                                },
                                error: function() {
                                    Swal.fire(
                                        'Errore!',
                                        'Errore nella comunicazione con il server.',
                                        'error'
                                    );
                                    // Reset del campo
                                    $("#unita_operativa").val('');
                                    $("#unita_operativa_id").val(0);
                                }
                            });
                        }
                    });
                }
            });
            // Auto-imposta "Attivo = Sì" per trattenuta e SEPA
            $("#tipo_tessera").on('change', function() {
                var tipo = $(this).val();
                if (tipo === 'trattenuta in busta paga' || tipo === 'sepa') {
                    $("#iscritto").val('1');
                }
            });
        });
    </script>

</body>
</html>
