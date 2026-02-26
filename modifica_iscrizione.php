<?php
// modifica_iscrizione.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusione della configurazione e delle funzioni necessarie
require 'config.php';
checkLogin();

// Funzione per formattare le date dal formato PHP al formato frontend
function formatDateForDisplay($date, $date_format = 'Y-m-d') {
    if (empty($date) || $date === '0000-00-00') return '-';
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d-m-Y') : '-';
}

// Funzione per validare la data
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Recupera l'ID dell'iscrizione da modificare
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestione_iscrizioni.php?error=" . urlencode("ID Iscrizione non valido."));
    exit;
}

$iscrizione_id = intval($_GET['id']);

// Imposta l'encoding della connessione al database
$mysqli->set_charset("utf8mb4");

// Recupera i dettagli dell'iscrizione e del lavoratore
$query = "SELECT i.*, l.cognome, l.nome, l.tipo_tessera, l.iscritto 
          FROM iscrizioni i 
          JOIN lavoratori l ON i.lavoratore_id = l.id 
          WHERE i.id = ?";
$stmt = $mysqli->prepare($query);
if (!$stmt) {
    die("Errore nella preparazione della query: " . $mysqli->error);
}
$stmt->bind_param('i', $iscrizione_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: gestione_iscrizioni.php?error=" . urlencode("Iscrizione non trovata."));
    exit;
}

$iscrizione = $result->fetch_assoc();
$stmt->close();

// Gestione della richiesta POST per aggiornare l'iscrizione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifica_iscrizione') {
    // Recupera e sanitizza i dati del modulo
    $lavoratore_id = isset($_POST['lavoratore_id']) ? intval($_POST['lavoratore_id']) : 0;
    $tipo_tessera = isset($_POST['tipo_tessera']) ? trim($_POST['tipo_tessera']) : '';
    $numero_tessera = trim($_POST['numero_tessera']) ?: NULL; // Permette NULL
    $data_inizio = isset($_POST['data_inizio']) ? $_POST['data_inizio'] : '';
    $nota_pagamento = trim($_POST['nota_pagamento']) ?: NULL;

    // Validazioni di base
    $errors = [];
    if ($lavoratore_id <= 0) {
        $errors[] = "Lavoratore non valido. Assicurati di selezionare un lavoratore esistente.";
    }
    if (empty($tipo_tessera) || !in_array($tipo_tessera, ['trattenuta in busta paga', 'rinnovo annuale', 'sepa'])) {
        $errors[] = "Tipo Tessera non valido.";
    }
    if (empty($data_inizio) || !validateDate($data_inizio)) {
        $errors[] = "Data di inizio non valida.";
    }

    // Se tipo_tessera è 'rinnovo annuale', calcola la data_fine
    if ($tipo_tessera === 'rinnovo annuale') {
        $data_fine = date('Y-m-d', strtotime('+1 year', strtotime($data_inizio)));
    } else {
        $data_fine = NULL;
    }

    // Verifica se il nuovo lavoratore ha già un'iscrizione attiva (esclusa l'iscrizione corrente)
    if (empty($errors)) {
        $check_query = "SELECT id FROM iscrizioni 
                        WHERE lavoratore_id = ? 
                        AND id != ?";
        $stmt_check = $mysqli->prepare($check_query);
        if (!$stmt_check) {
            $errors[] = "Errore nella preparazione della query di controllo: " . $mysqli->error;
        } else {
            $stmt_check->bind_param('ii', $lavoratore_id, $iscrizione_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $errors[] = "Il lavoratore selezionato ha già un'iscrizione attiva. Non è possibile assegnare un'altra iscrizione.";
            }
            $stmt_check->close();
        }
    }

    if (empty($errors)) {
        // Inizia una transazione per garantire integrità
        $mysqli->begin_transaction();

        try {
            // Aggiorna l'iscrizione nella tabella iscrizioni
            $update_iscrizione_query = "UPDATE iscrizioni 
                                        SET lavoratore_id = ?, numero_tessera = ?, data_inizio = ?, data_fine = ?, nota_pagamento = ?
                                        WHERE id = ?";
            $stmt_update_iscrizione = $mysqli->prepare($update_iscrizione_query);
            if (!$stmt_update_iscrizione) {
                throw new Exception("Errore nella preparazione della query di aggiornamento iscrizione: " . $mysqli->error);
            }
            $stmt_update_iscrizione->bind_param('issssi', $lavoratore_id, $numero_tessera, $data_inizio, $data_fine, $nota_pagamento, $iscrizione_id);
            if (!$stmt_update_iscrizione->execute()) {
                throw new Exception("Errore nell'esecuzione della query di aggiornamento iscrizione: " . $stmt_update_iscrizione->error);
            }
            $stmt_update_iscrizione->close();

            // Aggiorna lo stato 'iscritto' del nuovo lavoratore
            $update_lavoratore_query = "UPDATE lavoratori 
                                        SET tipo_tessera = ?, iscritto = ?
                                        WHERE id = ?";
            $iscritto = 1; // Default

            if ($tipo_tessera === 'trattenuta in busta paga' || $tipo_tessera === 'sepa') {
                $iscritto = 1;
            } elseif ($tipo_tessera === 'rinnovo annuale') {
                $oggi = date('Y-m-d');
                if ($data_fine < $oggi) {
                    $iscritto = 0;
                } else {
                    $iscritto = 1;
                }
            }

            $stmt_update_lavoratore = $mysqli->prepare($update_lavoratore_query);
            if (!$stmt_update_lavoratore) {
                throw new Exception("Errore nella preparazione della query di aggiornamento lavoratore: " . $mysqli->error);
            }
            $stmt_update_lavoratore->bind_param('sii', $tipo_tessera, $iscritto, $lavoratore_id);
            if (!$stmt_update_lavoratore->execute()) {
                throw new Exception("Errore nell'esecuzione della query di aggiornamento lavoratore: " . $stmt_update_lavoratore->error);
            }
            $stmt_update_lavoratore->close();

            // Se il lavoratore è cambiato, verifica lo stato 'iscritto' del lavoratore precedente
            if ($lavoratore_id != $iscrizione['lavoratore_id']) {
                $prev_lavoratore_id = $iscrizione['lavoratore_id'];

                // Verifica se il lavoratore precedente ha altre iscrizioni attive
                $check_prev_query = "SELECT COUNT(*) AS count FROM iscrizioni
                                     WHERE lavoratore_id = ?
                                     AND (tipo_tessera IN ('trattenuta in busta paga', 'sepa')
                                         OR (tipo_tessera = 'rinnovo annuale' AND data_fine >= ?))";
                $stmt_check_prev = $mysqli->prepare($check_prev_query);
                if (!$stmt_check_prev) {
                    throw new Exception("Errore nella preparazione della query di controllo lavoratore precedente: " . $mysqli->error);
                }
                $oggi = date('Y-m-d');
                $stmt_check_prev->bind_param('is', $prev_lavoratore_id, $oggi);
                if (!$stmt_check_prev->execute()) {
                    throw new Exception("Errore nell'esecuzione della query di controllo lavoratore precedente: " . $stmt_check_prev->error);
                }
                $result_check_prev = $stmt_check_prev->get_result();
                $count_prev = $result_check_prev->fetch_assoc()['count'];
                $stmt_check_prev->close();

                if ($count_prev == 0) {
                    // Se non ci sono altre iscrizioni attive, aggiorna lo stato 'iscritto' a 0
                    $update_prev_lavoratore_query = "UPDATE lavoratori SET iscritto = 0 WHERE id = ?";
                    $stmt_update_prev_lavoratore = $mysqli->prepare($update_prev_lavoratore_query);
                    if (!$stmt_update_prev_lavoratore) {
                        throw new Exception("Errore nella preparazione della query di aggiornamento lavoratore precedente: " . $mysqli->error);
                    }
                    $stmt_update_prev_lavoratore->bind_param('i', $prev_lavoratore_id);
                    if (!$stmt_update_prev_lavoratore->execute()) {
                        throw new Exception("Errore nell'esecuzione della query di aggiornamento lavoratore precedente: " . $stmt_update_prev_lavoratore->error);
                    }
                    $stmt_update_prev_lavoratore->close();
                }
            }

            // Rifetch dei dettagli dell'iscrizione aggiornata per mantenere nome e cognome
            $query = "SELECT i.*, l.cognome, l.nome, l.tipo_tessera, l.iscritto 
                      FROM iscrizioni i 
                      JOIN lavoratori l ON i.lavoratore_id = l.id 
                      WHERE i.id = ?";
            $stmt = $mysqli->prepare($query);
            if (!$stmt) {
                throw new Exception("Errore nella preparazione della query di rifetch: " . $mysqli->error);
            }
            $stmt->bind_param('i', $iscrizione_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $iscrizione = $result->fetch_assoc();
            }
            $stmt->close();

            // Commit della transazione
            $mysqli->commit();

            $success_message = "Iscrizione aggiornata con successo.";
        } catch (Exception $e) {
            // Rollback della transazione in caso di errore
            $mysqli->rollback();
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica Iscrizione</title>
    <!-- Meta viewport per la responsività -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- SB Admin 2 CSS (includes Bootstrap) -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.0" rel="stylesheet">
    <!-- jQuery UI CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.css" rel="stylesheet">
    <!-- Custom CSS (se necessario) -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.0" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
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
                    <button onclick="location.href='gestione_iscrizioni.php'" class="btn btn-secondary mb-4">
                        <i class="fas fa-arrow-left"></i> Torna alla Gestione Iscrizioni
                    </button>
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Modifica Iscrizione di 
                            <a href="lavoratore.php?id=<?php echo sanitizeForHTML($iscrizione['lavoratore_id']); ?>">
                                <?php echo sanitizeForHTML($iscrizione['nome'] . ' ' . $iscrizione['cognome']); ?>
                            </a>
                        </h1>
                    </div>

                    <!-- Messaggi di Successo o Errore -->
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

                    <!-- Informazione sul Lavoratore -->
                    <div class="alert alert-info" role="alert">
                        <strong>Lavoratore:</strong> 
                        <a href="lavoratore.php?id=<?php echo sanitizeForHTML($iscrizione['lavoratore_id']); ?>">
                            <?php echo sanitizeForHTML($iscrizione['nome'] . ' ' . $iscrizione['cognome']); ?>
                        </a>
                    </div>

                    <!-- Modulo per Modificare l'Iscrizione -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-edit mr-1"></i>
                            Dettagli Iscrizione
                        </div>
                        <div class="card-body">
                            <form method="POST" action="modifica_iscrizione.php?id=<?php echo sanitizeForHTML($iscrizione_id); ?>">
                                <input type="hidden" name="action" value="modifica_iscrizione">
                                <!-- Campo CSRF (se implementato) -->
                                <?php
                                    // Assicurati di avere una funzione per generare e verificare i token CSRF
                                    csrfInputField();
                                ?>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="lavoratore_search">Lavoratore</label>
                                        <input type="text" class="form-control" id="lavoratore_search" name="lavoratore_search" placeholder="Inizia a digitare il nome o cognome..." value="<?php echo sanitizeForHTML($iscrizione['cognome'] . ' ' . $iscrizione['nome']); ?>" required readonly>
                                        <input type="hidden" id="lavoratore_id" name="lavoratore_id" value="<?php echo sanitizeForHTML($iscrizione['lavoratore_id']); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="numero_tessera">Numero Tessera</label>
                                        <input type="text" class="form-control" id="numero_tessera" name="numero_tessera" value="<?php echo sanitizeForHTML($iscrizione['numero_tessera']); ?>" placeholder="Lascia vuoto se non disponibile">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="tipo_tessera">Tipo Tessera</label>
                                        <select id="tipo_tessera" name="tipo_tessera" class="form-control" required>
                                            <option value="">Scegli...</option>
                                            <option value="trattenuta in busta paga" <?php echo ($iscrizione['tipo_tessera'] === 'trattenuta in busta paga') ? 'selected' : ''; ?>>Trattenuta in Busta Paga</option>
                                            <option value="rinnovo annuale" <?php echo ($iscrizione['tipo_tessera'] === 'rinnovo annuale') ? 'selected' : ''; ?>>Rinnovo Annuale</option>
                                            <option value="sepa" <?php echo ($iscrizione['tipo_tessera'] === 'sepa') ? 'selected' : ''; ?>>SEPA</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="data_inizio">Data Inizio Iscrizione</label>
                                        <input type="date" class="form-control" id="data_inizio" name="data_inizio" value="<?php echo sanitizeForHTML($iscrizione['data_inizio']); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row" id="data_fine_group" style="<?php echo ($iscrizione['tipo_tessera'] === 'rinnovo annuale') ? 'display: block;' : 'display: none;'; ?>">
                                    <div class="form-group col-md-6">
                                        <label for="data_fine">Data Fine Iscrizione</label>
                                        <input type="date" class="form-control" id="data_fine" name="data_fine" value="<?php echo sanitizeForHTML($iscrizione['data_fine']); ?>" placeholder="Auto-calcolata per rinnovo annuale" readonly>
                                        <small class="form-text text-muted">La data di fine verrà calcolata automaticamente.</small>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="nota_pagamento">Nota Pagamento</label>
                                    <textarea class="form-control" id="nota_pagamento" name="nota_pagamento" rows="3" placeholder="Aggiungi eventuali note al pagamento"><?php echo sanitizeForHTML($iscrizione['nota_pagamento']); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Aggiorna Iscrizione</button>
                                <a href="gestione_iscrizioni.php" class="btn btn-secondary">Annulla</a>
                            </form>
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

    <!-- SweetAlert2 JS -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.js"></script>

    <!-- SB Admin 2 JS -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>

    <!-- Inizializzazione Autocomplete e Altri Script -->
    <script>
        $(document).ready(function() {
            // Inizializza Autocomplete per la ricerca del lavoratore con tipo_tessera
            $("#lavoratore_search").autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: "search_lavoratori.php",
                        type: "GET",
                        dataType: "json",
                        data: {
                            term: request.term,
                            include_tipo_tessera: 1 // Include tipo_tessera nella risposta
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
                    // Aggiorna l'informazione sul lavoratore
                    Swal.fire(
                        'Lavoratore Selezionato',
                        'Hai selezionato: ' + ui.item.label,
                        'success'
                    );

                    // Gestisci la visibilità del campo Data Fine Iscrizione
                    if (ui.item.tipo_tessera === 'rinnovo annuale') {
                        $("#data_fine_group").show();
                        // Auto-calcola la data_fine
                        var data_inizio = $("#data_inizio").val();
                        if (data_inizio) {
                            var data_fine = new Date(data_inizio);
                            data_fine.setFullYear(data_fine.getFullYear() + 1);
                            var day = String(data_fine.getDate()).padStart(2, '0');
                            var month = String(data_fine.getMonth() + 1).padStart(2, '0'); // Months are zero-based
                            var year = data_fine.getFullYear();
                            $("#data_fine").val(year + '-' + month + '-' + day);
                        }
                    } else {
                        $("#data_fine_group").hide();
                        $("#data_fine").val('');
                    }
                }
            });

            // Se il campo lavoratore_search è readonly, disabilita l'autocomplete
            if ($("#lavoratore_search").prop('readonly')) {
                $("#lavoratore_search").autocomplete("disable");
            }

            // Gestione del cambiamento del tipo_tessera
            $("#tipo_tessera").on("change", function() {
                var tipo = $(this).val();
                if (tipo === 'rinnovo annuale') {
                    $("#data_fine_group").show();
                    var data_inizio = $("#data_inizio").val();
                    if (data_inizio) {
                        var data_fine = new Date(data_inizio);
                        data_fine.setFullYear(data_fine.getFullYear() + 1);
                        var day = String(data_fine.getDate()).padStart(2, '0');
                        var month = String(data_fine.getMonth() + 1).padStart(2, '0'); // Months are zero-based
                        var year = data_fine.getFullYear();
                        $("#data_fine").val(year + '-' + month + '-' + day);
                    }
                } else {
                    $("#data_fine_group").hide();
                    $("#data_fine").val('');
                }
            });

            // Auto-calcola data_fine quando data_inizio cambia e tipo_tessera è 'rinnovo annuale'
            $("#data_inizio").on("change", function() {
                var tipo = $("#tipo_tessera").val();
                if (tipo === 'rinnovo annuale') {
                    var data_inizio = $(this).val();
                    if (data_inizio) {
                        var data_fine = new Date(data_inizio);
                        data_fine.setFullYear(data_fine.getFullYear() + 1);
                        var day = String(data_fine.getDate()).padStart(2, '0');
                        var month = String(data_fine.getMonth() + 1).padStart(2, '0'); // Months are zero-based
                        var year = data_fine.getFullYear();
                        $("#data_fine").val(year + '-' + month + '-' + day);
                    }
                }
            });

            // Visualizza un messaggio di successo tramite SweetAlert2
            <?php if (isset($success_message)): ?>
                Swal.fire(
                    'Successo!',
                    '<?php echo sanitizeForHTML($success_message); ?>',
                    'success'
                );
            <?php endif; ?>

            // Visualizza messaggi di errore tramite SweetAlert2
            <?php if (!empty($errors)): ?>
                Swal.fire(
                    'Errore!',
                    '<?php echo implode("\\n", array_map("sanitizeForHTML", $errors)); ?>',
                    'error'
                );
            <?php endif; ?>
        });
    </script>

</body>
</html>
