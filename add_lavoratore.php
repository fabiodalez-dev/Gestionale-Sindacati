<?php
// add_lavoratore.php

require_once 'config.php'; // Assicurati che config.php includa le funzioni necessarie

checkLogin();

// Inizializza variabili per messaggi di errore e successo
$errors = [];
$success = false;

// Genera un token CSRF per il form
generateCsrfToken();

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
    $indirizzo_via = !empty($_POST['indirizzo_via']) ? sanitizeForDatabase($_POST['indirizzo_via']) : null;
    $indirizzo_numero_civico = !empty($_POST['indirizzo_numero_civico']) ? sanitizeForDatabase($_POST['indirizzo_numero_civico']) : null;
    $indirizzo_cap = !empty($_POST['indirizzo_cap']) ? sanitizeForDatabase($_POST['indirizzo_cap']) : null;
    $indirizzo_citta = !empty($_POST['indirizzo_citta']) ? sanitizeForDatabase($_POST['indirizzo_citta']) : null;
    $indirizzo_provincia = !empty($_POST['indirizzo_provincia']) ? sanitizeForDatabase($_POST['indirizzo_provincia']) : null;
    $telefono = !empty($_POST['telefono']) ? sanitizeForDatabase($_POST['telefono']) : null;
    $email = !empty($_POST['email']) ? sanitizeForDatabase($_POST['email']) : null;
    $ruolo = !empty($_POST['ruolo']) ? sanitizeForDatabase($_POST['ruolo']) : 'NESSUNO';
    $contratto = !empty($_POST['contratto']) ? sanitizeForDatabase($_POST['contratto']) : null;
    $orario_contratto = !empty($_POST['orario_contratto']) ? sanitizeForDatabase($_POST['orario_contratto']) : 'tempo pieno';
    $data_assunzione = !empty($_POST['data_assunzione']) ? sanitizeForDatabase($_POST['data_assunzione']) : null;
    $data_fine_contratto = !empty($_POST['data_fine_contratto']) ? sanitizeForDatabase($_POST['data_fine_contratto']) : null;
    $ore_settimanali = isset($_POST['ore_settimanali']) ? floatval($_POST['ore_settimanali']) : null;
    $ral = isset($_POST['ral']) ? floatval($_POST['ral']) : null;
    $note = $_POST['note'] ?? null;
    $note_pulito = sanitizeForDatabase($note);

    // Nuovi campi: Azienda e Unità Operativa
    $nome_azienda = sanitizeForDatabase($_POST['azienda'] ?? null);
    $azienda_id = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : 0;
    $nome_unita_operativa = sanitizeForDatabase($_POST['unita_operativa'] ?? null);
    $unita_operativa_id = isset($_POST['unita_operativa_id']) ? intval($_POST['unita_operativa_id']) : 0;

    // Nuovo campo: Sede sindacato
    $sede_id = isset($_POST['sede_id']) && $_POST['sede_id'] !== '' ? intval($_POST['sede_id']) : null;

    // Validazione dei campi obbligatori
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
    if (!empty($genere) && !in_array($genere, $valid_generi)) {
        $errors[] = "Il genere selezionato non è valido.";
    }
    // Validazione dell'orario contratto
    $valid_orario_contratto = ['tempo pieno', 'part time'];
    if (!in_array($orario_contratto, $valid_orario_contratto)) {
        $errors[] = "L'orario di contratto selezionato non è valido.";
    }
    // Validazione del contratto
    $valid_contratti = ['Indeterminato', 'Determinato', 'Progetto', 'Apprendistato', 'Altro'];
    if (!empty($contratto) && !in_array($contratto, $valid_contratti)) {
        $errors[] = "Il tipo di contratto selezionato non è valido.";
    }
    // Validazione email se fornita
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email non valida.";
    }
    // Validazione della data di assunzione (non futura)
    if (!empty($data_assunzione) && strtotime($data_assunzione) > time()) {
        $errors[] = "La data di assunzione non può essere futura.";
    }

    // Inizio transazione
    $mysqli->begin_transaction();

    try {
        // Gestione dell'Azienda
        if ($azienda_id > 0) {
            $query = "SELECT id FROM aziende WHERE id = ?";
            $stmt = executeQuery($query, [$azienda_id], 'i');
            if ($stmt) {
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    throw new Exception("ID Azienda non valido.");
                }
                $stmt->close();
            } else {
                throw new Exception("Errore durante la verifica dell'Azienda.");
            }
        } elseif (!empty($nome_azienda)) {
            $query = "SELECT id FROM aziende WHERE nome_azienda = ?";
            $stmt = executeQuery($query, [$nome_azienda], 's');
            if ($stmt) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $azienda = $result->fetch_assoc();
                    $azienda_id = $azienda['id'];
                } else {
                    $query = "INSERT INTO aziende (nome_azienda, partita_iva) VALUES (?, ?)";
                    $stmt_insert_azienda = executeQuery($query, [$nome_azienda, null], 'ss');
                    if ($stmt_insert_azienda) {
                        $azienda_id = $mysqli->insert_id;
                        $stmt_insert_azienda->close();
                    } else {
                        throw new Exception("Errore durante la creazione dell'Azienda: " . $mysqli->error);
                    }
                }
                $stmt->close();
            } else {
                throw new Exception("Errore durante la ricerca dell'Azienda.");
            }
        } else {
            throw new Exception("Azienda non specificata.");
        }

        // Gestione dell'Unità Operativa
        if (!empty($nome_unita_operativa)) {
            if ($unita_operativa_id > 0) {
                $query = "SELECT id FROM unita_operativa WHERE id = ? AND azienda_id = ?";
                $stmt = executeQuery($query, [$unita_operativa_id, $azienda_id], 'ii');
                if ($stmt) {
                    $result = $stmt->get_result();
                    if ($result->num_rows === 0) {
                        throw new Exception("Unità operativa selezionata non valida per l'Azienda.");
                    }
                    $stmt->close();
                } else {
                    throw new Exception("Errore durante la verifica dell'Unità Operativa.");
                }
            } else {
                $query = "SELECT id FROM unita_operativa WHERE nome_unita_operativa = ? AND azienda_id = ?";
                $stmt = executeQuery($query, [$nome_unita_operativa, $azienda_id], 'si');
                if ($stmt) {
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $unita = $result->fetch_assoc();
                        $unita_operativa_id = $unita['id'];
                    } else {
                        $query_insert_unita = "INSERT INTO unita_operativa (nome_unita_operativa, azienda_id) VALUES (?, ?)";
                        $stmt_insert_unita = executeQuery($query_insert_unita, [$nome_unita_operativa, $azienda_id], 'si');
                        if ($stmt_insert_unita) {
                            $unita_operativa_id = $stmt_insert_unita->insert_id;
                            $stmt_insert_unita->close();
                        } else {
                            throw new Exception("Errore durante la creazione dell'Unità Operativa: " . $mysqli->error);
                        }
                    }
                    $stmt->close();
                } else {
                    throw new Exception("Errore durante la ricerca dell'Unità Operativa.");
                }
            }
        } else {
            $unita_operativa_id = null;
        }

        // Gestione di 'tipo_tessera' e calcolo delle date di iscrizione
        if ($tipo_tessera === 'rinnovo annuale') {
            if (empty($_POST['data_inizio'])) {
                throw new Exception("Data di inizio richiesta per rinnovo annuale.");
            }
            $data_inizio_iscrizione = sanitizeForDatabase($_POST['data_inizio']);
            $data_fine_iscrizione = date('Y-m-d', strtotime('+1 year', strtotime($data_inizio_iscrizione)));
        } elseif ($tipo_tessera === 'trattenuta in busta paga' || $tipo_tessera === 'sepa') {
            $data_inizio_iscrizione = !empty($_POST['data_inizio']) ? sanitizeForDatabase($_POST['data_inizio']) : date('Y-m-d');
            $data_fine_iscrizione = null;
        } else {
            $data_inizio_iscrizione = null;
            $data_fine_iscrizione = null;
        }

        // Inserimento del lavoratore (aggiungendo il campo sede_id)
        $query = "
            INSERT INTO lavoratori (
                cognome, nome, codice_fiscale, data_nascita, indirizzo_via, indirizzo_numero_civico,
                indirizzo_cap, indirizzo_citta, indirizzo_provincia, telefono, email, nazionalita, azienda_id,
                ruolo, contratto, data_assunzione, data_fine_contratto, ore_settimanali, ral, note, iscritto,
                settore, genere, data_iscrizione, paese_nascita, ccnl, tipo_tessera, orario_contratto,
                unita_operativa_id, sede_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $params = [
            $cognome, $nome, $codice_fiscale, $data_nascita, $indirizzo_via, $indirizzo_numero_civico,
            $indirizzo_cap, $indirizzo_citta, $indirizzo_provincia, $telefono, $email, $nazionalita, $azienda_id,
            $ruolo, $contratto, $data_assunzione, $data_fine_contratto, $ore_settimanali, $ral, $note_pulito, 1,
            $settore, $genere, $data_iscrizione, $paese_nascita, $ccnl, $tipo_tessera, $orario_contratto,
            $unita_operativa_id, $sede_id
        ];

        $types = buildTypesString($params);
        $stmt = executeQuery($query, $params, $types);

        if ($stmt) {
            $new_lavoratore_id = $stmt->insert_id;
            $stmt->close();

            // Gestione delle iscrizioni
            if ($tipo_tessera === 'rinnovo annuale') {
                $insert_iscrizione_query = "INSERT INTO iscrizioni (lavoratore_id, numero_tessera, metodo_pagamento, nota_pagamento, data_inizio, data_fine, created_at, updated_at)
                                           VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $numero_tessera = !empty($_POST['numero_tessera']) ? sanitizeForDatabase($_POST['numero_tessera']) : null;
                $nota_pagamento = !empty($_POST['nota_pagamento']) ? sanitizeForDatabase($_POST['nota_pagamento']) : null;

                $stmt_insert_iscrizione = executeQuery($insert_iscrizione_query, [
                    $new_lavoratore_id,
                    $numero_tessera,
                    $tipo_tessera,
                    $nota_pagamento,
                    $data_inizio_iscrizione,
                    $data_fine_iscrizione
                ], 'isssss');

                if (!$stmt_insert_iscrizione) {
                    if ($mysqli->errno === 1062) {
                        throw new Exception("Il lavoratore ha già un'iscrizione attiva.");
                    }
                    throw new Exception("Errore durante l'inserimento della nuova iscrizione: " . $mysqli->error);
                }

                $stmt_insert_iscrizione->close();
            } elseif ($tipo_tessera === 'trattenuta in busta paga' || $tipo_tessera === 'sepa') {
                $insert_iscrizione_query = "INSERT INTO iscrizioni (lavoratore_id, numero_tessera, metodo_pagamento, nota_pagamento, data_inizio, data_fine, created_at, updated_at)
                                           VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $numero_tessera = !empty($_POST['numero_tessera']) ? sanitizeForDatabase($_POST['numero_tessera']) : null;
                $nota_pagamento = !empty($_POST['nota_pagamento']) ? sanitizeForDatabase($_POST['nota_pagamento']) : null;

                $stmt_insert_iscrizione = executeQuery($insert_iscrizione_query, [
                    $new_lavoratore_id,
                    $numero_tessera,
                    $tipo_tessera,
                    $nota_pagamento,
                    $data_inizio_iscrizione,
                    $data_fine_iscrizione
                ], 'isssss');

                if (!$stmt_insert_iscrizione) {
                    if ($mysqli->errno === 1062) {
                        throw new Exception("Il lavoratore ha già un'iscrizione attiva.");
                    }
                    throw new Exception("Errore durante l'inserimento della nuova iscrizione: " . $mysqli->error);
                }

                $stmt_insert_iscrizione->close();
            }

            $mysqli->commit();
            $success = true;

            header("Location: lavoratori.php?add_success=1");
            exit;
        } else {
            throw new Exception("Errore durante l'inserimento del lavoratore: " . $mysqli->error);
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Aggiungi Lavoratore - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.4" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.css">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <link href="/styles.css?v=2.4" rel="stylesheet">
    <style>
        .ui-autocomplete {
            z-index: 1051 !important;
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include 'sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include 'topbar.php'; ?>
                <div class="container-fluid">
                    <button onclick="history.back()" class="btn btn-secondary mb-4">
                        <i class="fas fa-arrow-left"></i> Indietro
                    </button>
                    <h1 class="h3 mb-4 text-gray-800">Aggiungi Nuovo Lavoratore</h1>
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
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Lavoratore aggiunto con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            Dettagli Lavoratore
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php csrfInputField(); ?>
                                <!-- Dati Personali -->
                                <h4>Dati Personali</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="nome">Nome <span class="text-danger">*</span></label>
                                        <input type="text" name="nome" id="nome" class="form-control" value="<?php echo isset($_POST['nome']) ? sanitizeForHTML($_POST['nome']) : ''; ?>" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="cognome">Cognome <span class="text-danger">*</span></label>
                                        <input type="text" name="cognome" id="cognome" class="form-control" value="<?php echo isset($_POST['cognome']) ? sanitizeForHTML($_POST['cognome']) : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="codice_fiscale">Codice Fiscale</label>
                                        <input type="text" name="codice_fiscale" id="codice_fiscale" class="form-control" maxlength="16" value="<?php echo isset($_POST['codice_fiscale']) ? sanitizeForHTML($_POST['codice_fiscale']) : ''; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="data_nascita">Data di Nascita</label>
                                        <input type="date" name="data_nascita" id="data_nascita" class="form-control" value="<?php echo isset($_POST['data_nascita']) ? sanitizeForHTML($_POST['data_nascita']) : ''; ?>">
                                    </div>
                                </div>
                                <!-- Altri campi personali -->
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="paese_nascita">Paese di Nascita</label>
                                        <input type="text" name="paese_nascita" id="paese_nascita" class="form-control" value="<?php echo isset($_POST['paese_nascita']) ? sanitizeForHTML($_POST['paese_nascita']) : ''; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="nazionalita">Nazionalità</label>
                                        <input type="text" name="nazionalita" id="nazionalita" class="form-control" value="<?php echo isset($_POST['nazionalita']) ? sanitizeForHTML($_POST['nazionalita']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="genere">Genere</label>
                                    <select name="genere" id="genere" class="form-control">
                                        <option value="">Seleziona...</option>
                                        <option value="Maschio" <?php echo (isset($_POST['genere']) && $_POST['genere'] == 'Maschio') ? 'selected' : ''; ?>>Maschio</option>
                                        <option value="Femmina" <?php echo (isset($_POST['genere']) && $_POST['genere'] == 'Femmina') ? 'selected' : ''; ?>>Femmina</option>
                                        <option value="Altro" <?php echo (isset($_POST['genere']) && $_POST['genere'] == 'Altro') ? 'selected' : ''; ?>>Altro</option>
                                    </select>
                                </div>
                                <!-- Data di Iscrizione -->
                                <div class="form-group">
                                    <label for="data_iscrizione">Data di Iscrizione</label>
                                    <input type="date" name="data_iscrizione" id="data_iscrizione" class="form-control" value="<?php echo isset($_POST['data_iscrizione']) ? sanitizeForHTML($_POST['data_iscrizione']) : ''; ?>">
                                </div>
                                <!-- Campo Iscritto -->
                                <div class="form-group">
                                    <label for="iscritto">Attivo</label>
                                    <select name="iscritto" id="iscritto" class="form-control">
                                        <option value="0" <?php echo (isset($_POST['iscritto']) && $_POST['iscritto'] == 0) ? 'selected' : ''; ?>>No</option>
                                        <option value="1" <?php echo (isset($_POST['iscritto']) && $_POST['iscritto'] == 1) ? 'selected' : ''; ?>>Sì</option>
                                    </select>
                                </div>
                                <!-- Settore -->
                                <div class="form-group">
                                    <label for="settore">Settore</label>
                                    <select name="settore" id="settore" class="form-control">
                                        <option value="privato" <?php echo (isset($_POST['settore']) && $_POST['settore'] == 'privato') ? 'selected' : ''; ?>>Privato</option>
                                        <option value="pubblico" <?php echo (isset($_POST['settore']) && $_POST['settore'] == 'pubblico') ? 'selected' : ''; ?>>Pubblico</option>
                                    </select>
                                </div>
                                <!-- Tipo di Tessera -->
                                <div class="form-group">
                                    <label for="tipo_tessera">Tipo di Tessera <span class="text-danger">*</span></label>
                                    <select name="tipo_tessera" id="tipo_tessera" class="form-control" required>
                                        <option value="">Seleziona...</option>
                                        <option value="trattenuta in busta paga" <?php echo (isset($_POST['tipo_tessera']) && $_POST['tipo_tessera'] == 'trattenuta in busta paga') ? 'selected' : ''; ?>>Trattenuta in busta paga</option>
                                        <option value="rinnovo annuale" <?php echo (isset($_POST['tipo_tessera']) && $_POST['tipo_tessera'] == 'rinnovo annuale') ? 'selected' : ''; ?>>Rinnovo annuale</option>
                                        <option value="sepa" <?php echo (isset($_POST['tipo_tessera']) && $_POST['tipo_tessera'] == 'sepa') ? 'selected' : ''; ?>>SEPA</option>
                                    </select>
                                </div>
                                <!-- Nuovo campo: Sede sindacato (posizionato vicino ai campi tessera) -->
                                <div class="form-group">
                                    <label for="sede">Sede sindacato</label>
                                    <select name="sede_id" id="sede" class="form-control">
                                        <option value="">Tutte le sedi</option>
                                        <?php
                                        // Recupera le sedi disponibili
                                        $stmtSedi = executeQuery("SELECT id, nome FROM sedi ORDER BY nome ASC", [], '');
                                        if ($stmtSedi) {
                                            $resultSedi = $stmtSedi->get_result();
                                            while ($row = $resultSedi->fetch_assoc()) {
                                                $selected = (isset($_POST['sede_id']) && $_POST['sede_id'] == $row['id']) ? 'selected' : '';
                                                echo '<option value="' . sanitizeForHTML($row['id']) . '" ' . $selected . '>' . sanitizeForHTML($row['nome']) . '</option>';
                                            }
                                            $stmtSedi->close();
                                        }
                                        ?>
                                    </select>
                                </div>
                                <!-- Data Inizio Tessera -->
                                <div class="form-group" id="data_inizio_group" style="display: <?php echo (isset($_POST['tipo_tessera']) && in_array($_POST['tipo_tessera'], ['trattenuta in busta paga', 'rinnovo annuale', 'sepa'])) ? 'block' : 'none'; ?>;">
                                    <label for="data_inizio">Data Inizio Tessera <span class="text-danger">*</span></label>
                                    <input type="date" name="data_inizio" id="data_inizio" class="form-control" value="<?php echo isset($_POST['data_inizio']) ? sanitizeForHTML($_POST['data_inizio']) : ''; ?>">
                                </div>
                                <!-- Data Fine Tessera -->
                                <div class="form-group" id="data_fine_group" style="display: <?php echo (isset($_POST['tipo_tessera']) && $_POST['tipo_tessera'] === 'rinnovo annuale') ? 'block' : 'none'; ?>;">
                                    <label for="data_fine">Data Fine Tessera</label>
                                    <input type="date" name="data_fine" id="data_fine" class="form-control" value="<?php echo isset($_POST['data_fine']) ? sanitizeForHTML($_POST['data_fine']) : ''; ?>" readonly>
                                </div>
                                <!-- Numero Tessera -->
                                <div class="form-group">
                                    <label for="numero_tessera">Numero Tessera</label>
                                    <input type="text" name="numero_tessera" id="numero_tessera" class="form-control" value="<?php echo isset($_POST['numero_tessera']) ? sanitizeForHTML($_POST['numero_tessera']) : ''; ?>" placeholder="Lascia vuoto se non disponibile">
                                </div>
                                <!-- Nota Pagamento -->
                                <div class="form-group">
                                    <label for="nota_pagamento">Nota Pagamento</label>
                                    <textarea name="nota_pagamento" id="nota_pagamento" class="form-control" rows="3"><?php echo isset($_POST['nota_pagamento']) ? sanitizeForHTML($_POST['nota_pagamento']) : ''; ?></textarea>
                                </div>
                                <!-- Indirizzo -->
                                <h4 class="mt-4">Indirizzo</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="indirizzo_via">Via</label>
                                        <input type="text" name="indirizzo_via" id="indirizzo_via" class="form-control" value="<?php echo isset($_POST['indirizzo_via']) ? sanitizeForHTML($_POST['indirizzo_via']) : ''; ?>">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="indirizzo_numero_civico">Numero Civico</label>
                                        <input type="text" name="indirizzo_numero_civico" id="indirizzo_numero_civico" class="form-control" value="<?php echo isset($_POST['indirizzo_numero_civico']) ? sanitizeForHTML($_POST['indirizzo_numero_civico']) : ''; ?>">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="indirizzo_cap">CAP</label>
                                        <input type="text" name="indirizzo_cap" id="indirizzo_cap" class="form-control" pattern="\d{5}" title="CAP valido. Deve contenere 5 cifre." value="<?php echo isset($_POST['indirizzo_cap']) ? sanitizeForHTML($_POST['indirizzo_cap']) : ''; ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="indirizzo_citta">Città</label>
                                        <input type="text" name="indirizzo_citta" id="indirizzo_citta" class="form-control" value="<?php echo isset($_POST['indirizzo_citta']) ? sanitizeForHTML($_POST['indirizzo_citta']) : ''; ?>">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="indirizzo_provincia">Provincia</label>
                                        <input type="text" name="indirizzo_provincia" id="indirizzo_provincia" class="form-control" maxlength="2" pattern="[A-Za-z]{2}" title="Provincia valida. Deve essere composta da 2 lettere." value="<?php echo isset($_POST['indirizzo_provincia']) ? sanitizeForHTML($_POST['indirizzo_provincia']) : ''; ?>">
                                    </div>
                                </div>
                                <!-- Contatti -->
                                <h4 class="mt-4">Contatti</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="telefono">Telefono</label>
                                        <input type="text" name="telefono" id="telefono" class="form-control" value="<?php echo isset($_POST['telefono']) ? sanitizeForHTML($_POST['telefono']) : ''; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="email">Email</label>
                                        <input type="email" name="email" id="email" class="form-control" value="<?php echo isset($_POST['email']) ? sanitizeForHTML($_POST['email']) : ''; ?>">
                                    </div>
                                </div>
                                <!-- Informazioni Aziendali -->
                                <h4 class="mt-4">Informazioni Aziendali</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="azienda">Azienda <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" name="azienda" id="azienda" class="form-control" value="<?php echo isset($_POST['azienda']) ? sanitizeForHTML($_POST['azienda']) : ''; ?>" required>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#createAziendaModal">+ Nuova Azienda</button>
                                            </div>
                                        </div>
                                        <input type="hidden" name="azienda_id" id="azienda_id" value="<?php echo intval($_POST['azienda_id'] ?? 0); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="unita_operativa">Unità Operativa</label>
                                        <div class="input-group">
                                            <input type="text" name="unita_operativa" id="unita_operativa" class="form-control" value="<?php echo isset($_POST['unita_operativa']) ? sanitizeForHTML($_POST['unita_operativa']) : ''; ?>" disabled>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary" id="createUnitaOperativaBtn" disabled>+ Nuova Unità Operativa</button>
                                            </div>
                                        </div>
                                        <input type="hidden" name="unita_operativa_id" id="unita_operativa_id" value="<?php echo intval($_POST['unita_operativa_id'] ?? 0); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ruolo">Ruolo</label>
                                    <select name="ruolo" id="ruolo" class="form-control">
                                        <option value="NESSUNO" <?php echo (isset($_POST['ruolo']) && $_POST['ruolo'] == 'NESSUNO') ? 'selected' : ''; ?>>NESSUNO</option>
                                        <option value="RSU" <?php echo (isset($_POST['ruolo']) && $_POST['ruolo'] == 'RSU') ? 'selected' : ''; ?>>RSU</option>
                                        <option value="RSA" <?php echo (isset($_POST['ruolo']) && $_POST['ruolo'] == 'RSA') ? 'selected' : ''; ?>>RSA</option>
                                        <option value="RLS" <?php echo (isset($_POST['ruolo']) && $_POST['ruolo'] == 'RLS') ? 'selected' : ''; ?>>RLS</option>
                                    </select>
                                </div>
                                <!-- Contratto -->
                                <h4 class="mt-4">Contratto</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="contratto">Tipo di Contratto</label>
                                        <select name="contratto" id="contratto" class="form-control">
                                            <option value="">Seleziona...</option>
                                            <option value="Indeterminato" <?php echo (isset($_POST['contratto']) && $_POST['contratto'] == 'Indeterminato') ? 'selected' : ''; ?>>Indeterminato</option>
                                            <option value="Determinato" <?php echo (isset($_POST['contratto']) && $_POST['contratto'] == 'Determinato') ? 'selected' : ''; ?>>Determinato</option>
                                            <option value="Progetto" <?php echo (isset($_POST['contratto']) && $_POST['contratto'] == 'Progetto') ? 'selected' : ''; ?>>Progetto</option>
                                            <option value="Apprendistato" <?php echo (isset($_POST['contratto']) && $_POST['contratto'] == 'Apprendistato') ? 'selected' : ''; ?>>Apprendistato</option>
                                            <option value="Altro" <?php echo (isset($_POST['contratto']) && $_POST['contratto'] == 'Altro') ? 'selected' : ''; ?>>Altro</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="orario_contratto">Orario Contratto</label>
                                        <select name="orario_contratto" id="orario_contratto" class="form-control">
                                            <option value="tempo pieno" <?php echo (isset($_POST['orario_contratto']) && $_POST['orario_contratto'] == 'tempo pieno') ? 'selected' : ''; ?>>Tempo Pieno</option>
                                            <option value="part time" <?php echo (isset($_POST['orario_contratto']) && $_POST['orario_contratto'] == 'part time') ? 'selected' : ''; ?>>Part Time</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ccnl">CCNL</label>
                                    <input type="text" name="ccnl" id="ccnl" class="form-control" value="<?php echo isset($_POST['ccnl']) ? sanitizeForHTML($_POST['ccnl']) : ''; ?>" placeholder="Inizia a digitare per cercare...">
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="data_assunzione">Data di Assunzione</label>
                                        <input type="date" name="data_assunzione" id="data_assunzione" class="form-control" value="<?php echo isset($_POST['data_assunzione']) ? sanitizeForHTML($_POST['data_assunzione']) : ''; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="data_fine_contratto">Data Fine Contratto</label>
                                        <input type="date" name="data_fine_contratto" id="data_fine_contratto" class="form-control" value="<?php echo isset($_POST['data_fine_contratto']) ? sanitizeForHTML($_POST['data_fine_contratto']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ore_settimanali">Ore Settimanali</label>
                                    <input type="number" step="any" name="ore_settimanali" id="ore_settimanali" class="form-control" value="<?php echo isset($_POST['ore_settimanali']) ? sanitizeForHTML($_POST['ore_settimanali']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="ral">RAL</label>
                                    <input type="number" step="0.01" name="ral" id="ral" class="form-control" value="<?php echo isset($_POST['ral']) ? sanitizeForHTML($_POST['ral']) : ''; ?>">
                                </div>
                                <!-- Note -->
                                <div class="form-group">
                                    <label for="note">Note</label>
                                    <textarea name="note" id="note" class="form-control" rows="5"><?php echo isset($_POST['note']) ? htmlspecialchars($_POST['note'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                                </div>
                                <!-- Pulsanti -->
                                <button type="submit" class="btn btn-primary">Salva</button>
                                <a href="lavoratori.php" class="btn btn-secondary">Annulla</a>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- End of Page Content -->
            </div>
            <!-- End of Main Content -->
            <?php include 'footer.php'; ?>
            <!-- End of Footer -->
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->
    <!-- Modale per Creare una Nuova Azienda -->
    <div class="modal fade" id="createAziendaModal" tabindex="-1" role="dialog" aria-labelledby="createAziendaModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <form id="createAziendaForm">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="createAziendaModalLabel">Crea Nuova Azienda</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="modal-body">
                <?php csrfInputField(); ?>
                <div class="form-group">
                    <label for="new_nome_azienda">Nome Azienda <span class="text-danger">*</span></label>
                    <input type="text" name="nome_azienda" id="new_nome_azienda" class="form-control" required>
                </div>
                <!-- Altri campi per l'azienda se necessario -->
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Chiudi</button>
                <button type="submit" class="btn btn-primary">Crea Azienda</button>
              </div>
            </div>
        </form>
      </div>
    </div>
    <!-- Modale per Creare una Nuova Unità Operativa -->
    <div class="modal fade" id="createUnitaOperativaModal" tabindex="-1" role="dialog" aria-labelledby="createUnitaOperativaModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form id="createUnitaOperativaForm">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="createUnitaOperativaModalLabel">Crea Nuova Unità Operativa</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="modal-body">
                <?php csrfInputField(); ?>
                <div class="form-group">
                    <label for="new_nome_unita_operativa">Nome Unità Operativa <span class="text-danger">*</span></label>
                    <input type="text" name="nome_unita_operativa" id="new_nome_unita_operativa" class="form-control" required>
                </div>
                <input type="hidden" name="azienda_id" id="modal_azienda_id" value="<?php echo intval($_POST['azienda_id'] ?? 0); ?>">
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Chiudi</button>
                <button type="submit" class="btn btn-primary">Crea Unità Operativa</button>
              </div>
            </div>
        </form>
      </div>
    </div>
    <!-- Include JS libraries -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.js"></script>
    <!-- Inizializzazione di TinyMCE -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#note',
            plugins: 'advlist autolink lists link image charmap preview anchor pagebreak',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            entity_encoding: 'raw',
            forced_root_block: 'p',
            toolbar_mode: 'floating',
            menubar: false,
            branding: false,
            height: 300,
            license_key: 'gpl',
            setup: function (editor) {
                editor.on('init', function () {
                    this.getContainer().style.zIndex = 1040;
                });
            },
            inline: false
        });
        $(document).ready(function() {
            function sanitizeForHTML(str) {
                return $('<div>').text(str).html();
            }
            $("#azienda").autocomplete({
                source: "<?php echo sanitizeForHTML($base_url); ?>autocomplete_aziende_detailed.php",
                minLength: 2,
                select: function(event, ui) {
                    $("#azienda_id").val(ui.item.id);
                    $("#azienda").val(ui.item.label);
                    $("#unita_operativa").prop('disabled', false);
                    $("#createUnitaOperativaBtn").prop('disabled', false);
                },
                change: function(event, ui) {
                    if (!ui.item) {
                        $("#azienda_id").val(0);
                        $("#unita_operativa").val('').prop('disabled', true);
                        $("#createUnitaOperativaBtn").prop('disabled', true);
                    }
                }
            });
            $("#createAziendaForm").on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var formData = form.serialize();
                $.ajax({
                    url: "<?php echo sanitizeForHTML($base_url); ?>create_azienda_detailed.php",
                    type: "POST",
                    dataType: "json",
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Successo!', response.message, 'success');
                            $("#azienda_id").val(response.azienda_id);
                            $("#azienda").val(response.nome_azienda);
                            $('#createAziendaModal').modal('hide');
                            $("#unita_operativa").prop('disabled', false);
                            $("#createUnitaOperativaBtn").prop('disabled', false);
                        } else {
                            Swal.fire('Errore!', response.message, 'error');
                            if (response.azienda_id) {
                                $("#azienda_id").val(response.azienda_id);
                                $("#azienda").val(response.nome_azienda);
                                $("#unita_operativa").prop('disabled', false);
                                $("#createUnitaOperativaBtn").prop('disabled', false);
                                $('#createAziendaModal').modal('hide');
                            }
                        }
                    },
                    error: function() {
                        Swal.fire('Errore!', 'Errore nella comunicazione con il server.', 'error');
                    }
                });
            });
            $("#unita_operativa").autocomplete({
                source: function(request, response) {
                    var azienda_id = $("#azienda_id").val();
                    if (!azienda_id) {
                        response([]);
                        return;
                    }
                    $.ajax({
                        url: "<?php echo sanitizeForHTML($base_url); ?>autocomplete_unita_operativa_detailed.php",
                        dataType: "json",
                        data: {
                            term: request.term,
                            azienda_id: azienda_id
                        },
                        success: function(data) {
                            response(data);
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error("Errore AJAX Unità Operativa:", textStatus, errorThrown);
                            response([]);
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    $("#unita_operativa_id").val(ui.item.id);
                    $("#unita_operativa").val(ui.item.label);
                    return false;
                },
                change: function(event, ui) {
                    if (!ui.item) {
                        $("#unita_operativa_id").val(0);
                    }
                }
            });
            $("#createUnitaOperativaBtn").on('click', function() {
                var azienda_id = $("#azienda_id").val();
                if (azienda_id <= 0) {
                    Swal.fire('Errore!', 'Per creare un\'Unità Operativa devi prima selezionare un\'Azienda valida.', 'error');
                    return;
                }
                $("#modal_azienda_id").val(azienda_id);
                $('#createUnitaOperativaModal').modal('show');
            });
            $("#createUnitaOperativaForm").on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var formData = form.serialize();
                $.ajax({
                    url: "<?php echo sanitizeForHTML($base_url); ?>create_unita_operativa_detailed.php",
                    type: "POST",
                    dataType: "json",
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Successo!', response.message, 'success');
                            $("#unita_operativa_id").val(response.unita_id);
                            $("#unita_operativa").val(response.unita_nome);
                            $('#createUnitaOperativaModal').modal('hide');
                            $("#unita_operativa").autocomplete("search", response.unita_nome);
                        } else {
                            Swal.fire('Errore!', response.message, 'error');
                            if (response.unita_id) {
                                $("#unita_operativa_id").val(response.unita_id);
                                $("#unita_operativa").val(response.unita_nome);
                                $('#createUnitaOperativaModal').modal('hide');
                            }
                        }
                    },
                    error: function() {
                        Swal.fire('Errore!', 'Errore nella comunicazione con il server.', 'error');
                    }
                });
            });
            $("#ccnl").autocomplete({
                source: "<?php echo sanitizeForHTML($base_url); ?>autocomplete_ccnl.php",
                minLength: 2,
                select: function(event, ui) {
                    console.log("CCNL selezionato:", ui.item);
                },
                change: function(event, ui) {
                    if (!ui.item) {
                        console.log("CCNL non selezionato da autocomplete.");
                    }
                }
            });
            function toggleDateFields() {
                var tipo = $("#tipo_tessera").val();
                if (tipo === 'rinnovo annuale') {
                    $("#data_inizio_group").show();
                    $("#data_fine_group").show();
                    $("#data_inizio").attr('required', true);
                } else if (tipo === 'trattenuta in busta paga' || tipo === 'sepa') {
                    $("#data_inizio_group").show();
                    $("#data_fine_group").hide();
                    $("#data_inizio").attr('required', true);
                    $("#data_fine").val('');
                } else {
                    $("#data_inizio_group").hide();
                    $("#data_fine_group").hide();
                    $("#data_inizio").val('').removeAttr('required');
                    $("#data_fine").val('');
                }
            }
            toggleDateFields();
            $("#tipo_tessera").on('change', function() {
                toggleDateFields();
            });
            $("#data_inizio").on("change", function() {
                var tipo = $("#tipo_tessera").val();
                if (tipo === 'rinnovo annuale') {
                    var data_inizio = $(this).val();
                    if (data_inizio) {
                        var data_fine = new Date(data_inizio);
                        data_fine.setFullYear(data_fine.getFullYear() + 1);
                        var day = String(data_fine.getDate()).padStart(2, '0');
                        var month = String(data_fine.getMonth() + 1).padStart(2, '0');
                        var year = data_fine.getFullYear();
                        $("#data_fine").val(year + '-' + month + '-' + day);
                    }
                }
            });
            $('form').on('submit', function(e) {
                var azienda_id = $("#azienda_id").val();
                if (azienda_id == 0) {
                    e.preventDefault();
                    Swal.fire('Errore!', 'Per favore, seleziona un\'Azienda valida dall\'elenco o creane una nuova.', 'error');
                }
            });
        });
    </script>
</body>
</html>
