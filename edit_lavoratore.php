<?php
// edit_lavoratore.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php'; // Inclusione sicura di config.php

checkLogin(); // Verifica se l'utente è loggato
checkUserRole(['admin', 'manager']); // Verifica se l'utente ha i permessi necessari

// Funzione per formattare le date per gli input HTML (es. YYYY-MM-DD)
function formatDateForInput($date) {
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    return $date;
}

// Recupera l'elenco delle sedi (per il menu a tendina "Sede sindacato")
$sediList = [];
$stmtSedi = executeQuery("SELECT id, nome FROM sedi ORDER BY nome ASC", [], '');
if ($stmtSedi) {
    $resultSedi = $stmtSedi->get_result();
    while ($row = $resultSedi->fetch_assoc()) {
        $sediList[] = $row;
    }
    $resultSedi->free();
    $stmtSedi->close();
}

// Verifica se l'ID del lavoratore è fornito tramite GET
if (isset($_GET['id'])) {
    $lavoratore_id = intval($_GET['id']);
    
    // Recupera i dati del lavoratore e, se presente, la sua iscrizione attiva
    $query = "SELECT l.*, a.nome_azienda, u.nome_unita_operativa, i.id as iscrizione_id, 
                     i.numero_tessera, i.metodo_pagamento, i.nota_pagamento, i.data_inizio, i.data_fine,
                     l.sede_id
              FROM lavoratori l 
              LEFT JOIN aziende a ON l.azienda_id = a.id 
              LEFT JOIN unita_operativa u ON l.unita_operativa_id = u.id 
              LEFT JOIN iscrizioni i ON l.id = i.lavoratore_id AND i.metodo_pagamento = l.tipo_tessera
              WHERE l.id = ?";
    $stmt = executeQuery($query, [$lavoratore_id], 'i');
    if ($stmt === false) {
        error_log("Errore nella query per recuperare il lavoratore: " . $mysqli->error);
        header("Location: lavoratori.php?error=errore_recupero_dati");
        exit;
    }
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $lavoratore = $result->fetch_assoc();
    } else {
        header("Location: lavoratori.php?error=lavoratore_non_trovato");
        exit;
    }
    $stmt->close();
} else {
    header("Location: lavoratori.php?error=id_non_specificato");
    exit;
}

// Inizializza variabili per errori e messaggi di successo
$errors = [];
$success = false;

// Se il form è stato inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica del token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Token CSRF mancante o non valido.";
    }
    
    // Recupero e sanitizzazione dei dati inviati
    $id = intval($_POST['id']);
    $nome = sanitizeForDatabase($_POST['nome'] ?? '');
    $cognome = sanitizeForDatabase($_POST['cognome'] ?? '');
    $codice_fiscale = !empty($_POST['codice_fiscale']) ? sanitizeForDatabase($_POST['codice_fiscale']) : null;
    $data_nascita = !empty($_POST['data_nascita']) ? sanitizeForDatabase($_POST['data_nascita']) : null;
    $nazionalita = !empty($_POST['nazionalita']) ? sanitizeForDatabase($_POST['nazionalita']) : null;
    $paese_nascita = !empty($_POST['paese_nascita']) ? sanitizeForDatabase($_POST['paese_nascita']) : null;
    $genere = !empty($_POST['genere']) ? sanitizeForDatabase($_POST['genere']) : null;
    $data_iscrizione = !empty($_POST['data_iscrizione']) ? sanitizeForDatabase($_POST['data_iscrizione']) : null;
    $ccnl = !empty($_POST['ccnl']) ? sanitizeForDatabase($_POST['ccnl']) : null;
    $tipo_tessera = !empty($_POST['tipo_tessera']) ? sanitizeForDatabase($_POST['tipo_tessera']) : 'trattenuta in busta paga';
    $settore = isset($_POST['settore']) ? sanitizeForDatabase($_POST['settore']) : 'privato';
    $vertenze = 0; // Default
    $indirizzo_via = !empty($_POST['indirizzo_via']) ? sanitizeForDatabase($_POST['indirizzo_via']) : null;
    $indirizzo_numero_civico = !empty($_POST['indirizzo_numero_civico']) ? sanitizeForDatabase($_POST['indirizzo_numero_civico']) : null;
    $indirizzo_cap = !empty($_POST['indirizzo_cap']) ? sanitizeForDatabase($_POST['indirizzo_cap']) : null;
    $indirizzo_citta = !empty($_POST['indirizzo_citta']) ? sanitizeForDatabase($_POST['indirizzo_citta']) : null;
    $indirizzo_provincia = !empty($_POST['indirizzo_provincia']) ? sanitizeForDatabase($_POST['indirizzo_provincia']) : null;
    $telefono = !empty($_POST['telefono']) ? sanitizeForDatabase($_POST['telefono']) : null;
    $email = !empty($_POST['email']) ? sanitizeForDatabase($_POST['email']) : null;
    $ruolo = isset($_POST['ruolo']) ? sanitizeForDatabase($_POST['ruolo']) : ($lavoratore['ruolo'] ?? 'NESSUNO');
    $contratto = isset($_POST['contratto']) ? sanitizeForDatabase($_POST['contratto']) : ($lavoratore['contratto'] ?? null);
    $orario_contratto = !empty($_POST['orario_contratto']) ? sanitizeForDatabase($_POST['orario_contratto']) : 'tempo pieno';
    $data_assunzione = !empty($_POST['data_assunzione']) ? sanitizeForDatabase($_POST['data_assunzione']) : null;
    $data_fine_contratto = !empty($_POST['data_fine_contratto']) ? sanitizeForDatabase($_POST['data_fine_contratto']) : null;
    $ore_settimanali = isset($_POST['ore_settimanali']) ? floatval($_POST['ore_settimanali']) : null;
    $ral = isset($_POST['ral']) ? floatval($_POST['ral']) : null;
    $note = $_POST['note'] ?? '';
    $note_pulito = sanitizeForDatabase($note);
    
    // Dati per l'iscrizione
    $numero_tessera = !empty($_POST['numero_tessera']) ? sanitizeForDatabase($_POST['numero_tessera']) : null;
    $nota_pagamento = !empty($_POST['nota_pagamento']) ? sanitizeForDatabase($_POST['nota_pagamento']) : null;
    
    // Azienda e Unità Operativa
    $nome_azienda = sanitizeForDatabase($_POST['azienda'] ?? '');
    $nome_unita_operativa = sanitizeForDatabase($_POST['unita_operativa'] ?? '');
    $unita_operativa_id = intval($_POST['unita_operativa_id'] ?? 0);
    
    // NUOVO CAMPO: Sede sindacato
    $sede_id = isset($_POST['sede_id']) && $_POST['sede_id'] !== '' ? intval($_POST['sede_id']) : null;
    
    // Validazione dei campi obbligatori (come nel tuo codice originale)
    if (empty($nome)) {
        $errors[] = "Il campo 'Nome' è obbligatorio.";
    }
    if (empty($cognome)) {
        $errors[] = "Il campo 'Cognome' è obbligatorio.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email non valida.";
    }
    
    // (Ulteriori validazioni come da codice originale...)
    
    // Gestione dell'azienda e dell'unità operativa (come nel tuo codice originale)
    if (!empty($nome_azienda)) {
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
                } else {
                    error_log("Errore durante la creazione dell'azienda: " . $mysqli->error);
                    $errors[] = "Errore durante la creazione dell'azienda.";
                }
            }
            $stmt->close();
        } else {
            $errors[] = "Errore durante la ricerca dell'azienda.";
        }
    } else {
        $azienda_id = null;
    }
    
    if (!empty($nome_unita_operativa)) {
        if ($unita_operativa_id > 0) {
            $query = "SELECT id FROM unita_operativa WHERE id = ? AND azienda_id = ?";
            $stmt = executeQuery($query, [$unita_operativa_id, $azienda_id], 'ii');
            if ($stmt) {
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    $errors[] = "Unità operativa selezionata non valida per l'azienda.";
                }
                $stmt->close();
            } else {
                $errors[] = "Errore durante la verifica dell'unità operativa.";
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
                    } else {
                        error_log("Errore durante la creazione dell'unità operativa: " . $mysqli->error);
                        $errors[] = "Errore durante la creazione dell'unità operativa.";
                    }
                }
                $stmt->close();
            } else {
                $errors[] = "Errore durante la ricerca dell'unità operativa.";
            }
        }
    } else {
        $unita_operativa_id = null;
    }
    
    $valid_ruoli = ['RSU', 'RSA', 'RLS', 'NESSUNO'];
    if (!in_array($ruolo, $valid_ruoli)) {
        $errors[] = "Il ruolo selezionato non è valido.";
    }
    
    // Se non ci sono errori, inizia la transazione per aggiornare lavoratore e iscrizione
    if (empty($errors)) {
        $mysqli->begin_transaction();
        try {
            // Gestione delle date per l'iscrizione
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
            
            // Aggiorna i dati del lavoratore (incluso il campo sede_id)
            $query = "
                UPDATE lavoratori SET
                    nome = ?, cognome = ?, codice_fiscale = ?, data_nascita = ?, nazionalita = ?, paese_nascita = ?, genere = ?, data_iscrizione = ?,
                    ccnl = ?, tipo_tessera = ?, settore = ?, vertenze = ?,
                    indirizzo_via = ?, indirizzo_numero_civico = ?, indirizzo_cap = ?, indirizzo_citta = ?, indirizzo_provincia = ?,
                    telefono = ?, email = ?, ruolo = ?, azienda_id = ?, unita_operativa_id = ?, contratto = ?, orario_contratto = ?, data_assunzione = ?, data_fine_contratto = ?,
                    ore_settimanali = ?, ral = ?, note = ?, sede_id = ?
                WHERE id = ?
            ";
            $params = [
                $nome, $cognome, $codice_fiscale, $data_nascita, $nazionalita, $paese_nascita, $genere, $data_iscrizione,
                $ccnl, $tipo_tessera, $settore, $vertenze,
                $indirizzo_via, $indirizzo_numero_civico, $indirizzo_cap, $indirizzo_citta, $indirizzo_provincia,
                $telefono, $email, $ruolo, $azienda_id, $unita_operativa_id, $contratto, $orario_contratto, $data_assunzione, $data_fine_contratto,
                $ore_settimanali, $ral, $note_pulito,
                $sede_id,
                $id
            ];
            $types = buildTypesString($params);
            $stmt = executeQuery($query, $params, $types);
            if (!$stmt) {
                throw new Exception("Errore durante l'aggiornamento del lavoratore: " . $mysqli->error);
            }
            $stmt->close();
            
            // Gestione dell'iscrizione
            $check_iscrizione_query = "SELECT id, metodo_pagamento FROM iscrizioni WHERE lavoratore_id = ?";
            $stmt_check = executeQuery($check_iscrizione_query, [$lavoratore_id], 'i');
            if (!$stmt_check) {
                throw new Exception("Errore durante la verifica dell'iscrizione esistente: " . $mysqli->error);
            }
            $result_check = $stmt_check->get_result();
            $iscrizione_esistente = $result_check->fetch_assoc();
            $stmt_check->close();
            
            if ($iscrizione_esistente) {
                if ($tipo_tessera === 'rinnovo annuale') {
                    $update_iscrizione_query = "UPDATE iscrizioni 
                                                SET metodo_pagamento = ?, numero_tessera = ?, nota_pagamento = ?, data_inizio = ?, data_fine = ?, updated_at = NOW()
                                                WHERE id = ?";
                    $stmt_update = executeQuery($update_iscrizione_query, [
                        $tipo_tessera,
                        $numero_tessera,
                        $nota_pagamento,
                        $data_inizio_iscrizione,
                        $data_fine_iscrizione,
                        $iscrizione_esistente['id']
                    ], 'sssssi');
                } elseif ($tipo_tessera === 'trattenuta in busta paga' || $tipo_tessera === 'sepa') {
                    $update_iscrizione_query = "UPDATE iscrizioni
                                                SET metodo_pagamento = ?, numero_tessera = ?, nota_pagamento = ?, data_inizio = ?, data_fine = NULL, updated_at = NOW()
                                                WHERE id = ?";
                    $stmt_update = executeQuery($update_iscrizione_query, [
                        $tipo_tessera,
                        $numero_tessera,
                        $nota_pagamento,
                        $data_inizio_iscrizione,
                        $iscrizione_esistente['id']
                    ], 'ssssi');
                }
                if (!$stmt_update) {
                    throw new Exception("Errore durante l'aggiornamento dell'iscrizione: " . $mysqli->error);
                }
                $stmt_update->close();
            } else {
                if ($tipo_tessera === 'rinnovo annuale') {
                    $insert_iscrizione_query = "INSERT INTO iscrizioni (lavoratore_id, numero_tessera, metodo_pagamento, nota_pagamento, data_inizio, data_fine, created_at, updated_at)
                                                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt_insert = executeQuery($insert_iscrizione_query, [
                        $lavoratore_id,
                        $numero_tessera,
                        $tipo_tessera,
                        $nota_pagamento,
                        $data_inizio_iscrizione,
                        $data_fine_iscrizione
                    ], 'isssss');
                } elseif ($tipo_tessera === 'trattenuta in busta paga' || $tipo_tessera === 'sepa') {
                    $insert_iscrizione_query = "INSERT INTO iscrizioni (lavoratore_id, numero_tessera, metodo_pagamento, nota_pagamento, data_inizio, data_fine, created_at, updated_at)
                                                VALUES (?, ?, ?, ?, ?, NULL, NOW(), NOW())";
                    $stmt_insert = executeQuery($insert_iscrizione_query, [
                        $lavoratore_id,
                        $numero_tessera,
                        $tipo_tessera,
                        $nota_pagamento,
                        $data_inizio_iscrizione
                    ], 'issss');
                }
                if (!$stmt_insert) {
                    if ($mysqli->errno === 1062) {
                        throw new Exception("Il lavoratore ha già un'iscrizione attiva.");
                    }
                    throw new Exception("Errore durante l'inserimento della nuova iscrizione: " . $mysqli->error);
                }
                $stmt_insert->close();
            }
            
            $mysqli->commit();
            $success = true;
            header("Location: lavoratore.php?id=$id&update_success=1");
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            $errors[] = $e->getMessage();
        }
    }
    
    if (!empty($_POST)) {
        $lavoratore['nome'] = $_POST['nome'] ?? $lavoratore['nome'];
        $lavoratore['cognome'] = $_POST['cognome'] ?? $lavoratore['cognome'];
        $lavoratore['codice_fiscale'] = $_POST['codice_fiscale'] ?? $lavoratore['codice_fiscale'];
        $lavoratore['data_nascita'] = $_POST['data_nascita'] ?? $lavoratore['data_nascita'];
        $lavoratore['nazionalita'] = $_POST['nazionalita'] ?? $lavoratore['nazionalita'];
        $lavoratore['paese_nascita'] = $_POST['paese_nascita'] ?? $lavoratore['paese_nascita'];
        $lavoratore['genere'] = $_POST['genere'] ?? $lavoratore['genere'];
        $lavoratore['data_iscrizione'] = $_POST['data_iscrizione'] ?? $lavoratore['data_iscrizione'];
        $lavoratore['ccnl'] = $_POST['ccnl'] ?? $lavoratore['ccnl'];
        $lavoratore['tipo_tessera'] = $_POST['tipo_tessera'] ?? $lavoratore['tipo_tessera'];
        $lavoratore['settore'] = $_POST['settore'] ?? $lavoratore['settore'];
        $lavoratore['vertenze'] = $_POST['vertenze'] ?? $lavoratore['vertenze'];
        $lavoratore['indirizzo_via'] = $_POST['indirizzo_via'] ?? $lavoratore['indirizzo_via'];
        $lavoratore['indirizzo_numero_civico'] = $_POST['indirizzo_numero_civico'] ?? $lavoratore['indirizzo_numero_civico'];
        $lavoratore['indirizzo_cap'] = $_POST['indirizzo_cap'] ?? $lavoratore['indirizzo_cap'];
        $lavoratore['indirizzo_citta'] = $_POST['indirizzo_citta'] ?? $lavoratore['indirizzo_citta'];
        $lavoratore['indirizzo_provincia'] = $_POST['indirizzo_provincia'] ?? $lavoratore['indirizzo_provincia'];
        $lavoratore['telefono'] = $_POST['telefono'] ?? $lavoratore['telefono'];
        $lavoratore['email'] = $_POST['email'] ?? $lavoratore['email'];
        $lavoratore['ruolo'] = $_POST['ruolo'] ?? $lavoratore['ruolo'];
        $lavoratore['contratto'] = $_POST['contratto'] ?? $lavoratore['contratto'];
        $lavoratore['orario_contratto'] = $_POST['orario_contratto'] ?? $lavoratore['orario_contratto'];
        $lavoratore['data_assunzione'] = $_POST['data_assunzione'] ?? $lavoratore['data_assunzione'];
        $lavoratore['data_fine_contratto'] = $_POST['data_fine_contratto'] ?? $lavoratore['data_fine_contratto'];
        $lavoratore['ore_settimanali'] = $_POST['ore_settimanali'] ?? $lavoratore['ore_settimanali'];
        $lavoratore['ral'] = $_POST['ral'] ?? $lavoratore['ral'];
        $lavoratore['note'] = $_POST['note'] ?? $lavoratore['note'];
        $lavoratore['nota_pagamento'] = $_POST['nota_pagamento'] ?? $lavoratore['nota_pagamento'];
        $lavoratore['numero_tessera'] = $_POST['numero_tessera'] ?? $lavoratore['numero_tessera'];
        $lavoratore['azienda_id'] = $_POST['azienda_id'] ?? $lavoratore['azienda_id'];
        $lavoratore['unita_operativa_id'] = $_POST['unita_operativa_id'] ?? $lavoratore['unita_operativa_id'];
        $lavoratore['nome_azienda'] = $_POST['azienda'] ?? $lavoratore['nome_azienda'];
        $lavoratore['nome_unita_operativa'] = $_POST['unita_operativa'] ?? $lavoratore['nome_unita_operativa'];
        $lavoratore['sede_id'] = isset($_POST['sede_id']) && $_POST['sede_id'] !== '' ? intval($_POST['sede_id']) : $lavoratore['sede_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica Lavoratore - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.0" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.css">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.0" rel="stylesheet">
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
                    <h1 class="h3 mb-4 text-gray-800">Modifica Dati di 
                        <a href="lavoratore.php?id=<?php echo sanitizeForHTML($lavoratore['id']); ?>">
                            <?php echo sanitizeForHTML($lavoratore['nome'] . ' ' . $lavoratore['cognome']); ?>
                        </a>
                    </h1>
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
                            Dati aggiornati con successo.
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
                                <input type="hidden" name="id" value="<?php echo intval($lavoratore['id'] ?? 0); ?>">
                                <h4>Dati Personali</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="nome">Nome <span class="text-danger">*</span></label>
                                        <input type="text" name="nome" id="nome" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['nome'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="cognome">Cognome <span class="text-danger">*</span></label>
                                        <input type="text" name="cognome" id="cognome" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['cognome'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="codice_fiscale">Codice Fiscale</label>
                                        <input type="text" name="codice_fiscale" id="codice_fiscale" class="form-control" maxlength="16" value="<?php echo sanitizeForHTML($lavoratore['codice_fiscale'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="data_nascita">Data di Nascita</label>
                                        <input type="date" name="data_nascita" id="data_nascita" class="form-control" value="<?php echo formatDateForInput($lavoratore['data_nascita'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="paese_nascita">Paese di Nascita</label>
                                        <input type="text" name="paese_nascita" id="paese_nascita" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['paese_nascita'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="nazionalita">Nazionalità</label>
                                        <input type="text" name="nazionalita" id="nazionalita" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['nazionalita'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="genere">Genere</label>
                                    <select name="genere" id="genere" class="form-control">
                                        <option value="">Seleziona...</option>
                                        <option value="Maschio" <?php echo (($lavoratore['genere'] ?? '') == 'Maschio') ? 'selected' : ''; ?>>Maschio</option>
                                        <option value="Femmina" <?php echo (($lavoratore['genere'] ?? '') == 'Femmina') ? 'selected' : ''; ?>>Femmina</option>
                                        <option value="Altro" <?php echo (($lavoratore['genere'] ?? '') == 'Altro') ? 'selected' : ''; ?>>Altro</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="data_iscrizione">Data di Iscrizione</label>
                                    <input type="date" name="data_iscrizione" id="data_iscrizione" class="form-control" value="<?php echo formatDateForInput($lavoratore['data_iscrizione'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="settore">Settore</label>
                                    <select name="settore" id="settore" class="form-control">
                                        <option value="privato" <?php echo (($lavoratore['settore'] ?? '') == 'privato') ? 'selected' : ''; ?>>Privato</option>
                                        <option value="pubblico" <?php echo (($lavoratore['settore'] ?? '') == 'pubblico') ? 'selected' : ''; ?>>Pubblico</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="tipo_tessera">Tipo di Tessera <span class="text-danger">*</span></label>
                                    <select name="tipo_tessera" id="tipo_tessera" class="form-control" required>
                                        <option value="">Seleziona...</option>
                                        <option value="trattenuta in busta paga" <?php echo (($lavoratore['tipo_tessera'] ?? '') == 'trattenuta in busta paga') ? 'selected' : ''; ?>>Trattenuta in busta paga</option>
                                        <option value="rinnovo annuale" <?php echo (($lavoratore['tipo_tessera'] ?? '') == 'rinnovo annuale') ? 'selected' : ''; ?>>Rinnovo annuale</option>
                                        <option value="sepa" <?php echo (($lavoratore['tipo_tessera'] ?? '') == 'sepa') ? 'selected' : ''; ?>>SEPA</option>
                                    </select>
                                </div>
                                
                                <!-- Nuovo campo: Sede sindacato -->
                                <div class="form-group">
                                    <label for="sede">Sede sindacato</label>
                                    <select name="sede_id" id="sede" class="form-control">
                                        <option value="">Tutte le sedi</option>
                                        <?php foreach ($sediList as $sede): ?>
                                            <option value="<?php echo sanitizeForHTML($sede['id']); ?>"
                                                <?php echo (isset($lavoratore['sede_id']) && $lavoratore['sede_id'] == $sede['id']) ? 'selected' : ''; ?>>
                                                <?php echo sanitizeForHTML($sede['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Campi Tessera -->
                                <div class="form-group" id="data_inizio_group" style="display: <?php echo in_array($lavoratore['tipo_tessera'] ?? '', ['rinnovo annuale','trattenuta in busta paga','sepa']) ? 'block' : 'none'; ?>;">
                                    <label for="data_inizio">Data Inizio Tessera <span class="text-danger">*</span></label>
                                    <input type="date" name="data_inizio" id="data_inizio" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['data_inizio'] ?? ''); ?>">
                                </div>
                                <div class="form-group" id="data_fine_group" style="display: <?php echo (($lavoratore['tipo_tessera'] ?? '') === 'rinnovo annuale') ? 'block' : 'none'; ?>;">
                                    <label for="data_fine">Data Fine Tessera</label>
                                    <input type="date" name="data_fine" id="data_fine" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['data_fine'] ?? ''); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="numero_tessera">Numero Tessera</label>
                                    <input type="text" name="numero_tessera" id="numero_tessera" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['numero_tessera'] ?? ''); ?>" placeholder="Lascia vuoto se non disponibile">
                                </div>
                                <div class="form-group">
                                    <label for="nota_pagamento">Nota Pagamento</label>
                                    <textarea name="nota_pagamento" id="nota_pagamento" class="form-control" rows="3"><?php echo sanitizeForHTML($lavoratore['nota_pagamento'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Indirizzo -->
                                <h4 class="mt-4">Indirizzo</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="indirizzo_via">Via</label>
                                        <input type="text" name="indirizzo_via" id="indirizzo_via" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['indirizzo_via'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="indirizzo_numero_civico">Numero Civico</label>
                                        <input type="text" name="indirizzo_numero_civico" id="indirizzo_numero_civico" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['indirizzo_numero_civico'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="indirizzo_cap">CAP</label>
                                        <input type="text" name="indirizzo_cap" id="indirizzo_cap" class="form-control" pattern="\d{5}" title="CAP valido. Deve contenere 5 cifre." value="<?php echo sanitizeForHTML($lavoratore['indirizzo_cap'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="indirizzo_citta">Città</label>
                                        <input type="text" name="indirizzo_citta" id="indirizzo_citta" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['indirizzo_citta'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="indirizzo_provincia">Provincia</label>
                                        <input type="text" name="indirizzo_provincia" id="indirizzo_provincia" class="form-control" maxlength="2" pattern="[A-Za-z]{2}" title="Provincia valida. Deve essere composta da 2 lettere." value="<?php echo sanitizeForHTML($lavoratore['indirizzo_provincia'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <!-- Contatti -->
                                <h4 class="mt-4">Contatti</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="telefono">Telefono</label>
                                        <input type="text" name="telefono" id="telefono" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['telefono'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="email">Email</label>
                                        <input type="email" name="email" id="email" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['email'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <!-- Informazioni Aziendali -->
                                <h4 class="mt-4">Informazioni Aziendali</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="azienda">Azienda <span class="text-danger">*</span></label>
                                        <input type="text" name="azienda" id="azienda" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['nome_azienda'] ?? ''); ?>" required>
                                        <input type="hidden" name="azienda_id" id="azienda_id" value="<?php echo intval($lavoratore['azienda_id'] ?? 0); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="unita_operativa">Unità Operativa</label>
                                        <input type="text" name="unita_operativa" id="unita_operativa" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['nome_unita_operativa'] ?? ''); ?>">
                                        <input type="hidden" name="unita_operativa_id" id="unita_operativa_id" value="<?php echo intval($lavoratore['unita_operativa_id'] ?? 0); ?>">
                                    </div>
                                </div>
                                <?php 
                                    $ruolo_val = $_POST['ruolo'] ?? ($lavoratore['ruolo'] ?? 'NESSUNO');
                                ?>
                                <div class="form-group">
                                    <label for="ruolo">Ruolo</label>
                                    <select name="ruolo" id="ruolo" class="form-control">
                                        <option value="NESSUNO" <?php echo ($ruolo_val === 'NESSUNO') ? 'selected' : ''; ?>>NESSUNO</option>
                                        <option value="RSU" <?php echo ($ruolo_val === 'RSU') ? 'selected' : ''; ?>>RSU</option>
                                        <option value="RSA" <?php echo ($ruolo_val === 'RSA') ? 'selected' : ''; ?>>RSA</option>
                                        <option value="RLS" <?php echo ($ruolo_val === 'RLS') ? 'selected' : ''; ?>>RLS</option>
                                    </select>
                                </div>
                                <?php 
                                    $contratto_val = $_POST['contratto'] ?? ($lavoratore['contratto'] ?? '');
                                ?>
                                <h4 class="mt-4">Contratto</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="contratto">Tipo di Contratto</label>
                                        <select name="contratto" id="contratto" class="form-control">
                                            <option value="">Seleziona...</option>
                                            <option value="indeterminato" <?php echo ($contratto_val === 'indeterminato') ? 'selected' : ''; ?>>Indeterminato</option>
                                            <option value="determinato" <?php echo ($contratto_val === 'determinato') ? 'selected' : ''; ?>>Determinato</option>
                                            <option value="progetto" <?php echo ($contratto_val === 'progetto') ? 'selected' : ''; ?>>Progetto</option>
                                            <option value="apprendistato" <?php echo ($contratto_val === 'apprendistato') ? 'selected' : ''; ?>>Apprendistato</option>
                                            <option value="part-time" <?php echo ($contratto_val === 'part-time') ? 'selected' : ''; ?>>Part Time</option>
                                            <option value="full-time" <?php echo ($contratto_val === 'full-time') ? 'selected' : ''; ?>>Full Time</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="orario_contratto">Orario Contratto</label>
                                        <select name="orario_contratto" id="orario_contratto" class="form-control">
                                            <option value="tempo pieno" <?php echo (($lavoratore['orario_contratto'] ?? '') == 'tempo pieno') ? 'selected' : ''; ?>>Tempo Pieno</option>
                                            <option value="part time" <?php echo (($lavoratore['orario_contratto'] ?? '') == 'part time') ? 'selected' : ''; ?>>Part Time</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ccnl">CCNL</label>
                                    <input type="text" name="ccnl" id="ccnl" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['ccnl'] ?? ''); ?>">
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="data_assunzione">Data di Assunzione</label>
                                        <input type="date" name="data_assunzione" id="data_assunzione" class="form-control" value="<?php echo formatDateForInput($lavoratore['data_assunzione'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="data_fine_contratto">Data Fine Contratto</label>
                                        <input type="date" name="data_fine_contratto" id="data_fine_contratto" class="form-control" value="<?php echo formatDateForInput($lavoratore['data_fine_contratto'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ore_settimanali">Ore Settimanali</label>
                                    <input type="number" step="any" name="ore_settimanali" id="ore_settimanali" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['ore_settimanali'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="ral">RAL</label>
                                    <input type="number" step="0.01" name="ral" id="ral" class="form-control" value="<?php echo sanitizeForHTML($lavoratore['ral'] ?? ''); ?>">
                                </div>
                                
                                <!-- Note -->
                                <div class="form-group">
                                    <label for="note">Note</label>
                                    <textarea name="note" id="note" class="form-control" rows="5"><?php echo sanitizeForHTML($lavoratore['note'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Salva Modifiche</button>
                                <a href="lavoratori.php" class="btn btn-secondary">Annulla</a>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- End of Page Content -->
            </div>
            <!-- End of Main Content -->
            <?php include 'footer.php'; ?>
        </div>
    </div>
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
    <script src="<?php echo sanitizeForHTML($base_url); ?>vendor/tinymce/tinymce.min.js"></script>
    <script>
        tinymce.init({
            selector: '#note',
            plugins: 'advlist autolink lists link image charmap preview anchor pagebreak',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
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
        });
        $(document).ready(function() {
            $("#azienda").autocomplete({
                source: "<?php echo sanitizeForHTML($base_url); ?>autocomplete_aziende.php",
                minLength: 2,
                select: function(event, ui) {
                    $("#azienda_id").val(ui.item.id);
                    $("#azienda").val(ui.item.label);
                    $("#unita_operativa").val('');
                    $("#unita_operativa_id").val(0);
                    $("#unita_operativa").autocomplete("option", "source", "<?php echo sanitizeForHTML($base_url); ?>autocomplete_unita_operativa.php?azienda_id=" + ui.item.id);
                }
            });
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
                        },
                        error: function() {
                            console.error("Errore durante l'autocomplete delle Unità Operative.");
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
                create: function () {
                    $(this).data('ui-autocomplete')._renderItem = function(ul, item) {
                        return $("<li>")
                            .append("<div>" + item.label + "</div>")
                            .appendTo(ul);
                    };
                }
            }).on('autocompleteselect autocompletechange', function(event, ui) {
                if (ui.item) {
                    $("#unita_operativa_id").val(ui.item.id);
                    $("#unita_operativa").val(ui.item.label);
                } else {
                    var unita_val = $(this).val().trim();
                    var azienda_id = $("#azienda_id").val();
                    if (unita_val !== "" && azienda_id) {
                        $.ajax({
                            url: "<?php echo sanitizeForHTML($base_url); ?>create_unita_operativa.php",
                            type: "POST",
                            dataType: "json",
                            data: {
                                nome_unita_operativa: unita_val,
                                azienda_id: azienda_id,
                                csrf_token: $('input[name="csrf_token"]').val()
                            },
                            success: function(response) {
                                if (response.success) {
                                    $("#unita_operativa_id").val(response.unita_id);
                                    $("#unita_operativa").val(response.unita_nome);
                                    $("#unita_operativa").autocomplete("option", "source", "<?php echo sanitizeForHTML($base_url); ?>autocomplete_unita_operativa.php?azienda_id=" + azienda_id);
                                    if ($("#unita_operativa").next(".alert").length === 0) {
                                        $('<div class="alert alert-success alert-dismissible fade show mt-2" role="alert">Unità Operativa creata con successo.<button type="button" class="close" data-dismiss="alert" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button></div>').insertAfter("#unita_operativa");
                                    }
                                } else {
                                    if ($("#unita_operativa").next(".alert").length === 0) {
                                        $('<div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">' + sanitizeForHTML(response.message) + '<button type="button" class="close" data-dismiss="alert" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button></div>').insertAfter("#unita_operativa");
                                    }
                                    $("#unita_operativa").val('');
                                    $("#unita_operativa_id").val(0);
                                }
                            },
                            error: function() {
                                if ($("#unita_operativa").next(".alert").length === 0) {
                                    $('<div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">Errore nella comunicazione con il server.<button type="button" class="close" data-dismiss="alert" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button></div>').insertAfter("#unita_operativa");
                                }
                                $("#unita_operativa").val('');
                                $("#unita_operativa_id").val(0);
                            }
                        });
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
        });
    </script>
</body>
</html>
