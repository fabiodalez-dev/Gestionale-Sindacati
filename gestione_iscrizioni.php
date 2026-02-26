<?php
// gestione_iscrizioni.php

require 'config.php';
checkLogin();

// Imposta l'encoding della connessione al database
$mysqli->set_charset("utf8mb4");

/**
 * Funzione per formattare le date dal formato PHP al formato frontend
 */
function formatDateForDisplay($date, $date_format = 'd-m-Y') {
    if (empty($date) || $date === '0000-00-00') return '-';
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format($date_format) : '-';
}

/**
 * Funzione per aggiornare lo stato 'iscritto' nella tabella 'lavoratori' se la data di fine è passata
 */
function aggiornaStatoIscrizioni($mysqli) {
    $oggi = date('Y-m-d');

    // Seleziona tutte le iscrizioni con tipo_tessera 'rinnovo annuale' e data_fine <= oggi e iscritto =1
    // Rimosso GROUP BY lavoratore_id perché lavoratore_id è unico
    $query = "SELECT i.lavoratore_id FROM iscrizioni i 
              JOIN lavoratori l ON i.lavoratore_id = l.id 
              WHERE l.tipo_tessera = 'rinnovo annuale' 
              AND i.data_fine <= ? 
              AND l.iscritto = 1";

    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("Errore nella preparazione della query di aggiornamento: " . $mysqli->error);
        return;
    }
    $stmt->bind_param('s', $oggi);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $lavoratore_id = $row['lavoratore_id'];

        // Controlla se il lavoratore ha altre iscrizioni attive
        // Con la UNIQUE constraint, questa verifica può essere semplificata
        $check_query = "SELECT i.id
                        FROM iscrizioni i
                        JOIN lavoratori l ON i.lavoratore_id = l.id
                        WHERE i.lavoratore_id = ? 
                        AND (
                            l.tipo_tessera IN ('trattenuta in busta paga', 'sepa')
                            OR (
                                l.tipo_tessera = 'rinnovo annuale'
                                AND i.data_fine >= ?
                            )
                        )";
        $stmt_check = $mysqli->prepare($check_query);
        if (!$stmt_check) {
            error_log("Errore nella preparazione della query di controllo: " . $mysqli->error);
            continue;
        }
        $stmt_check->bind_param('is', $lavoratore_id, $oggi);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $count = $result_check->num_rows;
        $stmt_check->close();

        if ($count == 0) {
            // Se non ci sono altre iscrizioni attive, aggiorna lo stato 'iscritto' a 0
            $update_lavoratore = "UPDATE lavoratori SET iscritto = 0 WHERE id = ?";
            $stmt_update = $mysqli->prepare($update_lavoratore);
            if ($stmt_update) {
                $stmt_update->bind_param('i', $lavoratore_id);
                $stmt_update->execute();
                $stmt_update->close();
            } else {
                error_log("Errore nella preparazione della query di aggiornamento lavoratore: " . $mysqli->error);
            }
        }
    }

    $stmt->close();
}

// Esegui l'aggiornamento degli stati
aggiornaStatoIscrizioni($mysqli);

/**
 * Funzione per validare la data
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Gestione della richiesta POST per aggiungere una nuova iscrizione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'aggiungi_iscrizione') {
    // Recupera e sanitizza i dati del modulo
    $lavoratore_id = isset($_POST['lavoratore_id']) ? intval($_POST['lavoratore_id']) : 0;
    $numero_tessera = trim($_POST['numero_tessera']) ?: NULL; // Permette NULL
    $data_inizio = $_POST['data_inizio'] ?? '';
    $nota_pagamento = trim($_POST['nota_pagamento']) ?: '';
    $data_fine_input = isset($_POST['data_fine']) && !empty($_POST['data_fine']) ? $_POST['data_fine'] : NULL;

    // Validazioni di base
    $errors = [];
    if ($lavoratore_id <= 0) {
        $errors[] = "Lavoratore non valido. Assicurati di selezionare un lavoratore esistente.";
    }
    if (empty($data_inizio) || !validateDate($data_inizio)) {
        $errors[] = "Data di inizio non valida.";
    }

    if (empty($errors)) {
        // Recupera il tipo_tessera e lo stato 'iscritto' dal lavoratore
        $query_tipo_tessera = "SELECT tipo_tessera, iscritto FROM lavoratori WHERE id = ?";
        $stmt_tipo = $mysqli->prepare($query_tipo_tessera);
        if (!$stmt_tipo) {
            $errors[] = "Errore nella preparazione della query per tipo_tessera: " . $mysqli->error;
        } else {
            $stmt_tipo->bind_param('i', $lavoratore_id);
            $stmt_tipo->execute();
            $result_tipo = $stmt_tipo->get_result();
            if ($result_tipo->num_rows === 0) {
                $errors[] = "Lavoratore non trovato.";
            } else {
                $row_tipo = $result_tipo->fetch_assoc();
                $tipo_tessera = $row_tipo['tipo_tessera'];
                $iscritto = $row_tipo['iscritto'];
                if (empty($tipo_tessera)) {
                    $errors[] = "Il lavoratore selezionato non ha un tipo_tessera valido.";
                }
                // Verifica se il lavoratore è già iscritto (tranne per 'trattenuta in busta paga' e 'sepa')
                if ($iscritto && $tipo_tessera !== 'trattenuta in busta paga' && $tipo_tessera !== 'sepa') {
                    $errors[] = "Il lavoratore è già iscritto. Non è possibile aggiungere un'altra iscrizione.";
                }
            }
            $stmt_tipo->close();
        }
    }

    if (empty($errors)) {
        // Gestione della data di fine basata su tipo_tessera e input dell'utente
        if ($tipo_tessera === 'rinnovo annuale') {
            if ($data_fine_input) {
                if (!validateDate($data_fine_input)) {
                    $errors[] = "Data di fine non valida.";
                } else {
                    // Assicurati che la data di fine sia almeno un giorno dopo la data di inizio
                    if (strtotime($data_fine_input) <= strtotime($data_inizio)) {
                        $errors[] = "La data di fine deve essere successiva alla data di inizio.";
                    } else {
                        $data_fine = $data_fine_input;
                    }
                }
            } else {
                // Se non viene fornita una data di fine, impostala automaticamente a un anno dalla data di inizio
                $data_fine = date('Y-m-d', strtotime('+1 year', strtotime($data_inizio)));
            }
        } else {
            // Per 'trattenuta in busta paga', la data di fine non viene considerata
            $data_fine = NULL;
        }
    }

    if (empty($errors)) {
        // Avvia una transazione
        $mysqli->begin_transaction();

        try {
            // Controlla se esiste già un'iscrizione attiva per questo lavoratore
            $check_existing = executeQuery(
                "SELECT id FROM iscrizioni WHERE lavoratore_id = ?",
                [$lavoratore_id],
                'i'
            );

            if ($check_existing && $check_existing->get_result()->num_rows > 0) {
                throw new Exception("Il lavoratore ha già un'iscrizione attiva. Non è possibile aggiungere un'altra iscrizione.");
            }

            // Inserisci la nuova iscrizione
            $insert_query = "INSERT INTO iscrizioni (lavoratore_id, numero_tessera, data_inizio, data_fine, nota_pagamento) 
                             VALUES (?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($insert_query);
            if (!$stmt) {
                throw new Exception("Errore nella preparazione della query di inserimento: " . $mysqli->error);
            }
            $stmt->bind_param('issss', $lavoratore_id, $numero_tessera, $data_inizio, $data_fine, $nota_pagamento);
            if (!$stmt->execute()) {
                if ($mysqli->errno === 1062) { // Duplicate entry
                    throw new Exception("Il lavoratore ha già un'iscrizione attiva. Non è possibile aggiungere un'altra iscrizione.");
                } else {
                    throw new Exception("Errore nell'inserimento dell'iscrizione: " . $stmt->error);
                }
            }
            $stmt->close();

            // Aggiorna lo stato 'iscritto' del lavoratore a 1 solo se non è 'trattenuta in busta paga' o 'sepa'
            if ($tipo_tessera !== 'trattenuta in busta paga' && $tipo_tessera !== 'sepa') {
                $update_lavoratore = "UPDATE lavoratori SET iscritto = 1 WHERE id = ?";
                $stmt_update = $mysqli->prepare($update_lavoratore);
                if (!$stmt_update) {
                    throw new Exception("Errore nella preparazione della query di aggiornamento lavoratore: " . $mysqli->error);
                }
                $stmt_update->bind_param('i', $lavoratore_id);
                if (!$stmt_update->execute()) {
                    throw new Exception("Errore nell'aggiornamento dello stato iscritto: " . $stmt_update->error);
                }
                $stmt_update->close();
            }

            // Conferma la transazione
            $mysqli->commit();
            $success_message = "Iscrizione aggiunta con successo.";
        } catch (Exception $e) {
            // Rollback della transazione in caso di errore
            $mysqli->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

// Recupera le iscrizioni in scadenza (tessere in scadenza)
$scadenze = [];
$query_scadenze = "SELECT i.id, l.cognome, l.nome, l.tipo_tessera, i.data_fine, i.lavoratore_id 
                   FROM iscrizioni i 
                   JOIN lavoratori l ON i.lavoratore_id = l.id
                   WHERE l.tipo_tessera = 'rinnovo annuale' 
                   AND i.data_fine BETWEEN ? AND ?
                   ORDER BY i.data_fine ASC
                   LIMIT 20";
$stmt_scadenze = $mysqli->prepare($query_scadenze);
if ($stmt_scadenze) {
    $oggi = date('Y-m-d');
    $un_mese_dopo = date('Y-m-d', strtotime('+1 month'));
    $stmt_scadenze->bind_param('ss', $oggi, $un_mese_dopo);
    $stmt_scadenze->execute();
    $result_scadenze = $stmt_scadenze->get_result();
    while ($row = $result_scadenze->fetch_assoc()) {
        $scadenze[] = $row;
    }
    $stmt_scadenze->close();
} else {
    error_log("Errore nella preparazione della query scadenze: " . $mysqli->error);
}

// Recupera le iscrizioni scadute (limitate alle ultime 20)
$scaduti = [];
$query_scaduti = "SELECT i.id, i.lavoratore_id, l.cognome, l.nome, l.tipo_tessera, i.numero_tessera, i.data_inizio, i.data_fine, i.nota_pagamento 
                FROM iscrizioni i 
                JOIN lavoratori l ON i.lavoratore_id = l.id
                WHERE l.tipo_tessera = 'rinnovo annuale' 
                AND i.data_fine < ? 
                AND l.iscritto = 0
                ORDER BY i.data_fine DESC
                LIMIT 20";
$stmt_scaduti = $mysqli->prepare($query_scaduti);
if ($stmt_scaduti) {
    $oggi = date('Y-m-d');
    $stmt_scaduti->bind_param('s', $oggi);
    $stmt_scaduti->execute();
    $result_scaduti = $stmt_scaduti->get_result();
    while ($row = $result_scaduti->fetch_assoc()) {
        $scaduti[] = $row;
    }
    $stmt_scaduti->close();
} else {
    error_log("Errore nella preparazione della query scaduti: " . $mysqli->error);
}

// Recupera le iscrizioni attive (tutte)
$attivi = [];
$query_attivi = "SELECT i.id, i.lavoratore_id, l.cognome, l.nome, l.tipo_tessera, i.numero_tessera, i.data_inizio, i.data_fine, i.nota_pagamento 
                FROM iscrizioni i 
                JOIN lavoratori l ON i.lavoratore_id = l.id
                WHERE (l.tipo_tessera IN ('trattenuta in busta paga', 'sepa') AND l.iscritto = 1)
                OR (l.tipo_tessera = 'rinnovo annuale' AND i.data_fine >= ? AND l.iscritto = 1)
                ORDER BY i.data_inizio DESC";
$stmt_attivi = $mysqli->prepare($query_attivi);
if ($stmt_attivi) {
    $oggi = date('Y-m-d');
    $stmt_attivi->bind_param('s', $oggi);
    $stmt_attivi->execute();
    $result_attivi = $stmt_attivi->get_result();
    while ($row = $result_attivi->fetch_assoc()) {
        $attivi[] = $row;
    }
    $stmt_attivi->close();
} else {
    error_log("Errore nella preparazione della query attivi: " . $mysqli->error);
}

// Recupera i lavoratori non iscritti per aggiungere nuove iscrizioni
// Esclude i lavoratori con tipo_tessera = 'trattenuta in busta paga' o 'sepa'
$lavoratori = [];
$query_lavoratori = "SELECT id, cognome, nome, tipo_tessera
                     FROM lavoratori
                     WHERE (iscritto = 0 OR iscritto IS NULL)
                     AND tipo_tessera NOT IN ('trattenuta in busta paga', 'sepa')
                     ORDER BY cognome, nome";
$result_lavoratori = $mysqli->query($query_lavoratori);
if ($result_lavoratori) {
    while ($row = $result_lavoratori->fetch_assoc()) {
        $lavoratori[] = $row;
    }
} else {
    error_log("Errore nella query lavoratori non iscritti: " . $mysqli->error);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Iscrizioni</title>
    <!-- Meta viewport per la responsività -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- SB Admin 2 CSS (includes Bootstrap) -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.0" rel="stylesheet">
    <!-- jQuery UI CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <!-- Custom CSS (se necessario) -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.0" rel="stylesheet">
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
                    <button onclick="location.href='dashboard.php'" class="btn btn-secondary mb-4">
                        <i class="fas fa-arrow-left"></i> Torna alla Dashboard
                    </button>
                    <!-- Pulsante a Destra -->
                    <button onclick="location.href='email_reminder.php'" class="btn btn-primary mb-4">
                        <i class="fas fa-envelope"></i> Modifica Email Reminder
                    </button>


                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Gestione Iscrizioni</h1>
                    </div>

                    <!-- Messaggi di Successo o Errore -->
                    <?php 
                    // Gestione dei messaggi di success o error via GET
                    if (isset($_GET['success'])): 
                        $success_message = $_GET['success'];
                    ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($success_message); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php 
                    if (isset($_GET['error'])): 
                        $error_message = $_GET['error'];
                    ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($error_message); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($success_message); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
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

                    <!-- Sezione Aggiunta Nuova Iscrizione -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-user-plus mr-1"></i>
                            Aggiungi Nuova Iscrizione
                        </div>
                        <div class="card-body">
                            <form method="POST" action="gestione_iscrizioni.php">
                                <input type="hidden" name="action" value="aggiungi_iscrizione">
                                <?php csrfInputField(); ?>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="lavoratore_search">Lavoratore</label>
                                        <input type="text" class="form-control" id="lavoratore_search" name="lavoratore_search" placeholder="Inizia a digitare il nome o cognome..." required>
                                        <input type="hidden" id="lavoratore_id" name="lavoratore_id" value="<?php echo isset($_POST['lavoratore_id']) ? sanitizeForHTML($_POST['lavoratore_id']) : ''; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="numero_tessera">Numero Tessera</label>
                                        <input type="text" class="form-control" id="numero_tessera" name="numero_tessera" placeholder="Lascia vuoto se non disponibile" value="<?php echo isset($_POST['numero_tessera']) ? sanitizeForHTML($_POST['numero_tessera']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="data_inizio">Data Inizio Iscrizione</label>
                                        <input type="date" class="form-control" id="data_inizio" name="data_inizio" value="<?php echo isset($_POST['data_inizio']) ? sanitizeForHTML($_POST['data_inizio']) : date('Y-m-d'); ?>" required>
                                    </div>
                                    <!-- Campo per la Data di Fine, visibile solo se tipo_tessera è 'rinnovo annuale' -->
                                    <div class="form-group col-md-6" id="data_fine_group" style="display: none;">
                                        <label for="data_fine">Data Fine Iscrizione</label>
                                        <input type="date" class="form-control" id="data_fine" name="data_fine" value="<?php echo isset($_POST['data_fine']) ? sanitizeForHTML($_POST['data_fine']) : ''; ?>">
                                        <small class="form-text text-muted">Lascia vuoto per impostare automaticamente la scadenza a un anno dalla data di inizio.</small>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="nota_pagamento">Nota</label>
                                    <textarea class="form-control" id="nota_pagamento" name="nota_pagamento" rows="3" placeholder="Aggiungi una nota"><?php echo isset($_POST['nota_pagamento']) ? sanitizeForHTML($_POST['nota_pagamento']) : ''; ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Aggiungi Iscrizione</button>
                                <a href="gestione_iscrizioni.php" class="btn btn-secondary">Annulla</a>
                            </form>
                        </div>
                    </div>

                    <!-- Sezione Iscrizioni in Scadenza -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Iscrizioni in Scadenza
                        </div>
                        <div class="card-body">
                            <?php if (empty($scadenze)): ?>
                                <p>Nessuna iscrizione in scadenza trovata.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" id="dataTableScadenze" width="100%" cellspacing="0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>ID Iscrizione</th>
                                                <th>Lavoratore</th>
                                                <th>Data Fine</th>
                                                <th>Tipo Tessera</th>
                                                <th>Azione</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($scadenze as $scadenza): ?>
                                                <tr>
                                                    <td><?php echo sanitizeForHTML($scadenza['id']); ?></td>
                                                    <td>
                                                        <a href="lavoratore.php?id=<?php echo sanitizeForHTML($scadenza['lavoratore_id']); ?>">
                                                            <?php echo sanitizeForHTML($scadenza['cognome'] . ' ' . $scadenza['nome']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo formatDateForDisplay($scadenza['data_fine']); ?></td>
                                                    <td><?php echo sanitizeForHTML(ucwords($scadenza['tipo_tessera'] ?? '')); ?></td>
                                                    <td>
                                                        <!-- Pulsanti per Azioni -->
                                                        <a href="modifica_iscrizione.php?id=<?php echo sanitizeForHTML($scadenza['id']); ?>" class="btn btn-sm btn-info">Modifica</a>
                                                        <a href="elimina_iscrizione.php?id=<?php echo sanitizeForHTML($scadenza['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa iscrizione?');">Elimina</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sezione Iscrizioni Scadute -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-times-circle mr-1"></i>
                            Iscrizioni Scadute
                        </div>
                        <div class="card-body">
                            <?php if (empty($scaduti)): ?>
                                <p>Nessuna iscrizione scaduta trovata.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" id="dataTableScaduti" width="100%" cellspacing="0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>ID Iscrizione</th>
                                                <th>Lavoratore</th>
                                                <th>Numero Tessera</th>
                                                <th>Tipo Tessera</th>
                                                <th>Data Inizio</th>
                                                <th>Data Fine</th>
                                                <th>Nota</th>
                                                <th>Azione</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($scaduti as $scaduto): ?>
                                                <tr>
                                                    <td><?php echo sanitizeForHTML($scaduto['id']); ?></td>
                                                    <td>
                                                        <a href="lavoratore.php?id=<?php echo sanitizeForHTML($scaduto['lavoratore_id']); ?>">
                                                            <?php echo sanitizeForHTML($scaduto['cognome'] . ' ' . $scaduto['nome']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo sanitizeForHTML($scaduto['numero_tessera'] ?? '-'); ?></td>
                                                    <td><?php echo sanitizeForHTML(ucwords($scaduto['tipo_tessera'] ?? '')); ?></td>
                                                    <td><?php echo formatDateForDisplay($scaduto['data_inizio']); ?></td>
                                                    <td><?php echo formatDateForDisplay($scaduto['data_fine']); ?></td>
                                                    <td><?php echo sanitizeForHTML($scaduto['nota_pagamento'] ?? '-'); ?></td>
                                                    <td>
                                                        <!-- Pulsanti per Azioni (Modifica, Elimina) -->
                                                        <a href="modifica_iscrizione.php?id=<?php echo sanitizeForHTML($scaduto['id']); ?>" class="btn btn-sm btn-info">Modifica</a>
                                                        <a href="elimina_iscrizione.php?id=<?php echo sanitizeForHTML($scaduto['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa iscrizione?');">Elimina</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sezione Iscrizioni Attive -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-list mr-1"></i>
                            Iscrizioni Attive
                        </div>
                        <div class="card-body">
                            <?php if (empty($attivi)): ?>
                                <p>Nessuna iscrizione attiva trovata.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" id="dataTableAttivi" width="100%" cellspacing="0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>ID Iscrizione</th>
                                                <th>Lavoratore</th>
                                                <th>Numero Tessera</th>
                                                <th>Tipo Tessera</th>
                                                <th>Data Inizio</th>
                                                <th>Data Fine</th>
                                                <th>Nota</th>
                                                <th>Azione</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attivi as $attivo): ?>
                                                <tr>
                                                    <td><?php echo sanitizeForHTML($attivo['id']); ?></td>
                                                    <td>
                                                        <a href="lavoratore.php?id=<?php echo sanitizeForHTML($attivo['lavoratore_id']); ?>">
                                                            <?php echo sanitizeForHTML($attivo['cognome'] . ' ' . $attivo['nome']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo sanitizeForHTML($attivo['numero_tessera'] ?? '-'); ?></td>
                                                    <td><?php echo sanitizeForHTML(ucwords($attivo['tipo_tessera'] ?? '')); ?></td>
                                                    <td><?php echo formatDateForDisplay($attivo['data_inizio']); ?></td>
                                                    <td><?php echo ($attivo['tipo_tessera'] === 'rinnovo annuale') ? formatDateForDisplay($attivo['data_fine']) : '-'; ?></td>
                                                    <td><?php echo sanitizeForHTML($attivo['nota_pagamento'] ?? '-'); ?></td>
                                                    <td>
                                                        <!-- Pulsanti per Azioni (Modifica, Elimina) -->
                                                        <a href="modifica_iscrizione.php?id=<?php echo sanitizeForHTML($attivo['id']); ?>" class="btn btn-sm btn-info">Modifica</a>
                                                        <a href="elimina_iscrizione.php?id=<?php echo sanitizeForHTML($attivo['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa iscrizione?');">Elimina</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <?php include 'footer.php'; ?>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- jQuery, Popper.js, and Bootstrap JS -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery UI JS -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.js"></script>

    <!-- SB Admin 2 JS -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>

    <!-- DataTables JS -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/dataTables.bootstrap4.min.js"></script>

    <!-- Inizializzazione DataTables e Autocomplete -->
    <script>
        $(document).ready(function() {
            // Inizializza DataTables
            $('#dataTableScadenze').DataTable({
                "order": [[ 2, "asc" ]], // Ordinamento per 'data_fine' che è la terza colonna (index 2)
                "pageLength": 20
            });
            $('#dataTableScaduti').DataTable({
                "order": [[ 4, "desc" ]], // Ordinamento per 'data_fine' che è la quinta colonna (index 4)
                "pageLength": 20
            });
            $('#dataTableAttivi').DataTable({
                "order": [[ 4, "desc" ]], // Ordinamento per 'data_inizio' che è la quinta colonna (index 4)
                "pageLength": 50, // Puoi modificare il numero di righe per pagina
                "searching": true
            });

            // Inizializza Autocomplete per la ricerca del lavoratore
            $("#lavoratore_search").autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: "search_lavoratori.php",
                        type: "GET",
                        dataType: "json",
                        data: {
                            term: request.term
                        },
                        success: function(data) {
                            response(data);
                        },
                        error: function(xhr, status, error) {
                            console.error("Errore durante la ricerca:", error);
                            response([]);
                        }
                    });
                },
                minLength: 2, // Numero minimo di caratteri prima di avviare la ricerca
                select: function(event, ui) {
                    // Imposta l'ID del lavoratore selezionato nel campo nascosto
                    $("#lavoratore_id").val(ui.item.value);
                    // Verifica il tipo_tessera per mostrare o nascondere il campo Data Fine
                    if (ui.item.tipo_tessera === 'rinnovo annuale') {
                        $("#data_fine_group").show();
                    } else {
                        $("#data_fine_group").hide();
                        $("#data_fine").val('');
                    }
                }
            });

            // Assicurati che l'ID del lavoratore sia impostato quando si digita o si seleziona
            $("#lavoratore_search").on("blur", function() {
                if (!$("#lavoratore_id").val()) {
                    $(this).val('');
                    $("#data_fine_group").hide();
                    $("#data_fine").val('');
                }
            });

            // Mostra o nasconde il campo Data Fine all'avvio, se necessario
            <?php if (isset($_POST['tipo_tessera']) && $_POST['tipo_tessera'] === 'rinnovo annuale'): ?>
                $("#data_fine_group").show();
            <?php endif; ?>
        });
    </script>

</body>
</html>
