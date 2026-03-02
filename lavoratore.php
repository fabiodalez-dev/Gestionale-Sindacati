<?php
require_once 'config.php';
checkLogin();

// Verifica se l'ID del lavoratore è stato fornito
if (isset($_GET['id'])) {
    $lavoratore_id = intval($_GET['id']);

    // Recupera i dati del lavoratore e le informazioni associate
    $query = "SELECT l.*, a.nome_azienda, a.id AS azienda_id, u.nome_unita_operativa
              FROM lavoratori l
              LEFT JOIN aziende a ON l.azienda_id = a.id
              LEFT JOIN unita_operativa u ON l.unita_operativa_id = u.id
              WHERE l.id = ?";
    $stmt = executeQuery($query, [$lavoratore_id], 'i');
    if ($stmt === false) {
        echo "Errore nella query per recuperare il lavoratore.";
        exit;
    }
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $lavoratore = $result->fetch_assoc();
    } else {
        echo "Lavoratore non trovato.";
        exit;
    }

    // Recupera i documenti associati al lavoratore
    $anno_filtro = isset($_GET['anno_filtro']) ? intval($_GET['anno_filtro']) : null;
    $search_descrizione = isset($_GET['search_descrizione']) ? sanitizeForDatabase($_GET['search_descrizione']) : '';

    $documenti_query = "SELECT * FROM documenti_lavoratori WHERE lavoratore_id = ?";
    $params = [$lavoratore_id];
    $types = 'i';

    if ($anno_filtro) {
        $documenti_query .= " AND YEAR(data_caricamento) = ?";
        $params[] = $anno_filtro;
        $types .= 'i';
    }

    if (!empty($search_descrizione)) {
        $documenti_query .= " AND descrizione_documento LIKE CONCAT('%', ?, '%')";
        $params[] = $search_descrizione;
        $types .= 's';
    }

    $documenti_query .= " ORDER BY data_caricamento DESC";
    $stmt = executeQuery($documenti_query, $params, $types);
    if ($stmt === false) {
        echo "Errore nella query per recuperare i documenti.";
        exit;
    }
    $documenti = $stmt->get_result();

    // Recupera l'iscrizione attuale del lavoratore
    $subscription = null;
    $status = '';
    $dot_class = 'text-secondary';

    $today = date('Y-m-d');
    $query_subscription = "SELECT * FROM iscrizioni WHERE lavoratore_id = ? ORDER BY data_fine DESC, data_inizio DESC LIMIT 1";
    $stmt_sub = executeQuery($query_subscription, [$lavoratore_id], 'i');

    if ($stmt_sub !== false) {
        $result_sub = $stmt_sub->get_result();
        if ($result_sub->num_rows > 0) {
            $subscription = $result_sub->fetch_assoc();

            // Ottieni il tipo di tessera del lavoratore
            $tipo_tessera = $lavoratore['tipo_tessera'];

            if ($lavoratore['archiviato'] == 1) {
                // Se il lavoratore è archiviato, sospendi l'iscrizione
                $status = 'Iscrizione sospesa';
                $dot_class = 'text-danger'; // Rosso per indicare sospensione
            } else {
                if ($tipo_tessera == 'trattenuta in busta paga' || $tipo_tessera == 'sepa') {
                    // Sempre iscritto
                    $status = 'Iscrizione attiva';
                    $dot_class = 'text-success';
                } elseif ($tipo_tessera == 'rinnovo annuale') {
                    if (is_null($subscription['data_fine'])) {
                        // Senza data di fine, considerato attivo
                        $status = 'Iscrizione attiva';
                        $dot_class = 'text-success';
                    } else {
                        $data_fine = $subscription['data_fine'];
                        $diff = (strtotime($data_fine) - strtotime($today)) / (60 * 60 * 24);

                        if ($data_fine < $today) {
                            // Data di fine passata
                            $status = 'Iscrizione scaduta';
                            $dot_class = 'text-danger';
                        } elseif ($diff <= 30) {
                            // Data di fine entro i prossimi 30 giorni
                            $status = 'Iscrizione in scadenza';
                            $dot_class = 'text-warning';
                        } else {
                            // Data di fine oltre 30 giorni
                            $status = 'Iscrizione attiva';
                            $dot_class = 'text-success';
                        }
                    }
                } else {
                    // Gestione di eventuali altri tipi di tessera
                    $status = 'Tipo tessera sconosciuto';
                    $dot_class = 'text-secondary';
                }
            }
        }
        $stmt_sub->close();
    }

    // Gestione dei messaggi di successo o errore
    $upload_success = isset($_GET['upload_success']);
    $upload_error = isset($_GET['upload_error']) ? $_GET['upload_error'] : '';
    $delete_success = isset($_GET['delete_success']);
    $delete_error = isset($_GET['delete_error']) ? $_GET['delete_error'] : '';
    $update_success = isset($_GET['update_success']);
    $update_error = isset($_GET['update_error']) ? $_GET['update_error'] : '';
    $note_update_success = isset($_GET['note_update_success']);
    $note_update_error = isset($_GET['note_update_error']) ? $_GET['note_update_error'] : '';
    $archive_success = isset($_GET['archive_success']);
    $archive_error = isset($_GET['archive_error']) ? $_GET['archive_error'] : '';
    $unarchive_success = isset($_GET['unarchive_success']);
    $unarchive_error = isset($_GET['unarchive_error']) ? $_GET['unarchive_error'] : '';

    // Genera il token CSRF (già fatto in config.php, ma richiamato qui per sicurezza)
    generateCsrfToken();
} else {
    echo "ID lavoratore non specificato.";
    exit;
}


?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Scheda Anagrafica Lavoratore</title>
    <!-- Meta viewport per la responsività -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.6" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.css">
    <!-- Custom CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.6" rel="stylesheet">
    <!-- FullCalendar CSS -->
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/common.min.css">
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/daygrid.min.css">
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/timegrid.min.css">
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/list.min.css">
    <!-- SweetAlert2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <!-- Dropzone CSS -->
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/dropzone/dropzone.css">
    <style>
        /* Eventuali stili personalizzati aggiuntivi */
        #calendar {
            max-width: 100%;
            margin: 0 auto;
        }
        /* Assicurati che i canvas dei grafici siano responsivi */
        canvas {
            width: 100% !important;
            height: auto !important;
        }
        /* Per migliorare la visualizzazione su mobile */
        .form-inline .form-group {
            display: block;
            width: 100%;
            margin-bottom: 1rem;
        }
        .form-inline .form-group label,
        .form-inline .form-group input,
        .form-inline .form-group select,
        .form-inline .form-group button {
            width: 100%;
        }
        /* Stili per i pallini dello stato iscrizione */
        .status-dot {
            font-size: 1rem;
            margin-right: 0.5rem;
        }
		.fc .fc-daygrid-event-harness-abs {
   
    max-width: 100%;
}
        /* Stili per le anteprime delle immagini */
        .preview-img {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
        }
        /* Stili per l'anteprima dei PDF */
        .preview-pdf {
            width: 100px;
            height: 100px;
        }
        .dropzone {
            min-height: 150px;
            background: #e9ebf0;
            margin-bottom: 38px;
            border-radius: 20px;
        }
        .dropzone .dz-message .dz-button {
            border: solid gainsboro;
            padding: 20px;
            border-radius: 10px;
        }
        /* Stili per il Tag "Archiviato" */
        .badge-archiviato {
            background-color: #ffc107;
            color: #212529;
            font-size: 0.9rem;
            margin-left: 10px;
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
                      <div class="row"><div class="col-auto">
						  <?php
// Recupera l'ID del lavoratore
$lavoratore_id = intval($_GET['id']);

// Controlla se il lavoratore è archiviato
$is_archived = false;
$stmt = executeQuery("SELECT archiviato FROM lavoratori WHERE id = ?", [$lavoratore_id], 'i');
if ($stmt !== false) {
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $is_archived = (bool)$row['archiviato'];
    }
}

$back_url = $is_archived ? 'archived_lavoratori.php' : 'lavoratori.php';
$back_text = $is_archived ? 'Indietro agli Archiviati' : 'Indietro ai Lavoratori';
?>

<button onclick="location.href='<?php echo $back_url; ?>'" class="btn btn-secondary mb-4">
    <i class="fas fa-arrow-left"></i> <?php echo $back_text; ?>
</button>

    
     
    </div>
    <div class="col-auto">
        <a href="lavoratori.php?azienda_id=<?php echo sanitizeForHTML($lavoratore['azienda_id']); ?>" class="btn btn-secondary mb-4">
            <i class="fas fa-building"></i> Ritorna all'azienda
        </a>
    </div>
    <div class="col-auto">
		<a href="generate_pdf.php?id=<?php echo sanitizeForHTML($lavoratore_id); ?>" class="btn btn-secondary mb-4">
            <i class="fas fa-file-pdf"></i> PDF Trattenuta
        </a>
    </div>
						  <div class="col-auto">
		<a href="generate_pdf_rinnovo.php?id=<?php echo sanitizeForHTML($lavoratore_id); ?>" class="btn btn-secondary mb-4">
            <i class="fas fa-file-pdf"></i> PDF Rinnovo
        </a>
    </div>
    
</div>


                    <!-- Gestione dei messaggi -->
                    <?php if ($upload_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Documento caricato con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($upload_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($upload_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($delete_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Documento eliminato con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($delete_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($delete_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($update_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Dati aggiornati con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($update_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($update_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($note_update_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Note aggiornate con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($note_update_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($note_update_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($archive_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Lavoratore archiviato con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($archive_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($archive_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($unarchive_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Lavoratore ripristinato con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($unarchive_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($unarchive_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">&times;</button>
                        </div>
                    <?php endif; ?>

                    <!-- Visualizzazione dello Stato dell'Iscrizione -->
<?php if ($subscription): ?>
    <div class="mb-3 d-flex align-items-center flex-wrap" style="gap: 0.5rem;">
        <i class="fas fa-circle status-dot <?php echo $dot_class; ?>"></i>
        <span><?php echo $status; ?></span>
        <!-- Tag "Archiviato" -->
        <?php if ($lavoratore['archiviato'] == 1): ?>
            <span class="badge badge-archiviato">Archiviato</span>
        <?php endif; ?>
        <!-- Pulsante Modifica Dati -->
        <a href="edit_lavoratore.php?id=<?php echo sanitizeForHTML($lavoratore_id); ?>" class="btn btn-sm btn-primary">
            <i class="fas fa-edit"></i> Modifica Dati
        </a>
        <!-- Pulsante Modifica Iscrizione -->
        <a href="modifica_iscrizione.php?id=<?php echo sanitizeForHTML($subscription['id']); ?>" class="btn btn-sm btn-primary">
            <i class="fas fa-edit"></i> Modifica Iscrizione
        </a>
        <!-- Pulsante Archivia o Ripristina Lavoratore -->
        <?php if ($lavoratore['archiviato'] == 0): ?>
            <form method="POST" action="archive_lavoratore.php">
                <input type="hidden" name="id" value="<?php echo sanitizeForHTML($lavoratore_id); ?>">
                <?php csrfInputField(); ?>
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="fas fa-archive"></i> Archivia lavoratore
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="unarchive_lavoratore.php">
                <input type="hidden" name="id" value="<?php echo sanitizeForHTML($lavoratore_id); ?>">
                <?php csrfInputField(); ?>
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="fas fa-undo"></i> Ripristina lavoratore
                </button>
            </form>
        <?php endif; ?>
        <!-- Pulsante per gestire le Vertenze -->
        <form method="POST" action="toggle_vertenze.php">
            <input type="hidden" name="id" value="<?php echo sanitizeForHTML($lavoratore_id); ?>">
            <?php csrfInputField(); ?>
            <?php if ($lavoratore['vertenze'] == 1): ?>
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="fas fa-exclamation-triangle"></i> Vertenza Aperta
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="fas fa-check"></i> Nessuna Vertenza
                </button>
            <?php endif; ?>
        </form>
    </div>
<?php else: ?>
    <div class="mb-3 d-flex align-items-center flex-wrap" style="gap: 0.5rem;">
        <i class="fas fa-circle status-dot text-secondary"></i>
        <span>Nessuna iscrizione attiva</span>
        <!-- Tag "Archiviato" -->
        <?php if ($lavoratore['archiviato'] == 1): ?>
            <span class="badge badge-archiviato">Archiviato</span>
        <?php endif; ?>
        <!-- Pulsante Modifica Dati -->
        <a href="edit_lavoratore.php?id=<?php echo sanitizeForHTML($lavoratore_id); ?>" class="btn btn-sm btn-primary">
            <i class="fas fa-edit"></i> Modifica Dati
        </a>
        <!-- Pulsante Archivia o Ripristina Lavoratore -->
        <?php if ($lavoratore['archiviato'] == 0): ?>
            <form method="POST" action="archive_lavoratore.php">
                <input type="hidden" name="id" value="<?php echo sanitizeForHTML($lavoratore_id); ?>">
                <?php csrfInputField(); ?>
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="fas fa-archive"></i> Archivia lavoratore
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="unarchive_lavoratore.php">
                <input type="hidden" name="id" value="<?php echo sanitizeForHTML($lavoratore_id); ?>">
                <?php csrfInputField(); ?>
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="fas fa-undo"></i> Ripristina lavoratore
                </button>
            </form>
        <?php endif; ?>
        <!-- Pulsante per gestire le Vertenze -->
        <form method="POST" action="toggle_vertenze.php">
            <input type="hidden" name="id" value="<?php echo sanitizeForHTML($lavoratore_id); ?>">
            <?php csrfInputField(); ?>
            <?php if ($lavoratore['vertenze'] == 1): ?>
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="fas fa-exclamation-triangle"></i> Vertenza Aperta
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="fas fa-check"></i> Nessuna Vertenza
                </button>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>


                    <!-- Page Heading -->
                    <h2 class="mt-4">Scheda Anagrafica di <?php echo sanitizeForHTML($lavoratore['nome']) . ' ' . sanitizeForHTML($lavoratore['cognome']); ?></h2>
 <div class="col-12 mb-3">
    <strong>Azienda:</strong>
    <span>
        <a href="azienda.php?id=<?php echo sanitizeForHTML($lavoratore['azienda_id']); ?>">
            <?php echo sanitizeForHTML($lavoratore['nome_azienda']); ?>
        </a>
    </span>
    <span style="margin-left: 20px;">
        <strong>Sede sindacato:</strong>
        <?php 
            $sede_nome = "Tutte le sedi";
            if (!empty($lavoratore['sede_id'])) {
                $stmt_sede = executeQuery("SELECT nome FROM sedi WHERE id = ?", [$lavoratore['sede_id']], 'i');
                if ($stmt_sede) {
                    $result_sede = $stmt_sede->get_result();
                    if ($result_sede->num_rows > 0) {
                        $sede = $result_sede->fetch_assoc();
                        $sede_nome = $sede['nome'];
                    }
                    $stmt_sede->close();
                }
            }
            echo sanitizeForHTML($sede_nome);
        ?>
    </span>
</div>

                     <!-- Visualizzazione dei Dati del Lavoratore -->
<div class="card mt-4">
  <div class="card-body">
    <!-- Dati Personali -->
    <h4 class="card-title">Dati Personali</h4>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Nome:</strong>
        <p><?php echo sanitizeForHTML($lavoratore['nome']); ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Cognome:</strong>
        <p><?php echo sanitizeForHTML($lavoratore['cognome']); ?></p>
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Paese di Nascita:</strong>
        <p><?php echo !empty($lavoratore['paese_nascita']) ? sanitizeForHTML($lavoratore['paese_nascita']) : 'Non specificato'; ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Genere:</strong>
        <p><?php echo !empty($lavoratore['genere']) ? sanitizeForHTML($lavoratore['genere']) : 'Non specificato'; ?></p>
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Data di Iscrizione:</strong>
        <p><?php echo formatDate($lavoratore['data_iscrizione']); ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Tipo di Tessera:</strong>
        <p><?php echo !empty($lavoratore['tipo_tessera']) ? sanitizeForHTML($lavoratore['tipo_tessera']) : 'Non specificata'; ?></p>
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Codice Fiscale:</strong>
        <p><?php echo !empty($lavoratore['codice_fiscale']) ? sanitizeForHTML($lavoratore['codice_fiscale']) : 'Non specificato'; ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Data di Nascita:</strong>
        <p><?php echo formatDate($lavoratore['data_nascita']); ?></p>
      </div>
    </div>
    <div class="row">
      <div class="col-12 mb-2">
        <strong>Nazionalità:</strong>
        <p><?php echo !empty($lavoratore['nazionalita']) ? sanitizeForHTML($lavoratore['nazionalita']) : 'Non specificata'; ?></p>
      </div>
    </div>

    <hr class="my-3">

    <!-- Indirizzo -->
    <h4 class="mt-3">Indirizzo</h4>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Via:</strong>
        <p><?php echo !empty($lavoratore['indirizzo_via']) ? sanitizeForHTML($lavoratore['indirizzo_via']) : 'Non specificata'; ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Numero Civico:</strong>
        <p><?php echo !empty($lavoratore['indirizzo_numero_civico']) ? sanitizeForHTML($lavoratore['indirizzo_numero_civico']) : 'Non specificato'; ?></p>
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>CAP:</strong>
        <p><?php echo !empty($lavoratore['indirizzo_cap']) ? sanitizeForHTML($lavoratore['indirizzo_cap']) : 'Non specificato'; ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Città:</strong>
        <p><?php echo !empty($lavoratore['indirizzo_citta']) ? sanitizeForHTML($lavoratore['indirizzo_citta']) : 'Non specificata'; ?></p>
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Provincia:</strong>
        <p><?php echo !empty($lavoratore['indirizzo_provincia']) ? sanitizeForHTML($lavoratore['indirizzo_provincia']) : 'Non specificata'; ?></p>
      </div>
    </div>

    <hr class="my-3">

    <!-- Contatti -->
    <h4 class="mt-3">Contatti</h4>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Telefono:</strong>
        <p><?php echo !empty($lavoratore['telefono']) ? '<a href="tel:' . sanitizeForHTML($lavoratore['telefono']) . '">' . sanitizeForHTML($lavoratore['telefono']) . '</a>' : '-'; ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Email:</strong>
        <p><?php echo !empty($lavoratore['email']) ? '<a href="mailto:' . sanitizeForHTML($lavoratore['email']) . '">' . sanitizeForHTML($lavoratore['email']) . '</a>' : '-'; ?></p>
      </div>
    </div>

    <hr class="my-3">

    <!-- Contratto -->
    <h4 class="mt-3">Contratto</h4>
    <!-- Dati relativi al lavoro spostati da Dati Personali -->
    <div class="row">
        <div class="col-12 col-md-6 mb-2">
        <strong>Azienda:</strong>
        <p>
          <?php if ($lavoratore['azienda_id']): ?>
            <a href="azienda.php?id=<?php echo sanitizeForHTML($lavoratore['azienda_id']); ?>">
              <?php echo sanitizeForHTML($lavoratore['nome_azienda']); ?>
            </a>
          <?php else: ?>
            Non specificata
          <?php endif; ?>
        </p>
      </div>
      
      <div class="col-12 col-md-6 mb-2">
        <strong>Settore:</strong>
        <p><?php echo !empty($lavoratore['settore']) ? sanitizeForHTML($lavoratore['settore']) : 'Non specificato'; ?></p>
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Unità Operativa:</strong>
        <p><?php echo !empty($lavoratore['nome_unita_operativa']) ? sanitizeForHTML($lavoratore['nome_unita_operativa']) : '-'; ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Vertenze:</strong>
        <p><?php echo ($lavoratore['vertenze'] == 1) ? 'Sì' : 'No'; ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Ruolo:</strong>
        <p><?php echo !empty($lavoratore['ruolo']) ? sanitizeForHTML($lavoratore['ruolo']) : 'Non specificato'; ?></p>
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>CCNL:</strong>
        <p><?php echo !empty($lavoratore['ccnl']) ? sanitizeForHTML($lavoratore['ccnl']) : 'Non specificato'; ?></p>
      </div>
    </div>
    <!-- Dati Contrattuali -->
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Tipo di Contratto:</strong>
        <p><?php echo !empty($lavoratore['contratto']) ? sanitizeForHTML($lavoratore['contratto']) : 'Non specificato'; ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Orario del Contratto:</strong>
        <p><?php echo !empty($lavoratore['orario_contratto']) ? sanitizeForHTML($lavoratore['orario_contratto']) : 'Non specificato'; ?></p>
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Data di Assunzione:</strong>
        <p><?php echo formatDate($lavoratore['data_assunzione']); ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Data Fine Contratto:</strong>
        <p><?php echo formatDate($lavoratore['data_fine_contratto']); ?></p>
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Ore Settimanali:</strong>
        <p><?php echo isset($lavoratore['ore_settimanali']) ? floatval($lavoratore['ore_settimanali']) : 'Non specificato'; ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>RAL:</strong>
        <p><?php echo isset($lavoratore['ral']) ? number_format(floatval($lavoratore['ral']), 2, ',', '.') . ' €' : 'Non specificato'; ?></p>
      </div>
    </div>

    <!-- Pulsante per Modificare i Dati del Lavoratore -->
    <a href="edit_lavoratore.php?id=<?php echo sanitizeForHTML($lavoratore_id); ?>" class="btn btn-primary mt-3">
      <i class="fas fa-edit"></i> Modifica Dati
    </a>
  </div>
</div>


                    <!-- Sezione per il Calendario -->
                    <h3 class="mt-5">Calendario</h3>
                    <div id="calendar"></div>

                    <!-- Modale per aggiungere/modificare eventi -->
                    <div class="modal fade" id="eventModal" tabindex="-1" role="dialog" aria-labelledby="eventModalLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <form id="eventForm">
                            <!-- CSRF Token -->
                            <?php csrfInputField(); ?>
                            <div class="modal-header">
                              <h5 class="modal-title" id="eventModalLabel">Aggiungi Evento</h5>
                              <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                              </button>
                            </div>
                            <div class="modal-body">
                              <input type="hidden" name="id" id="eventId">
                              <div class="form-group">
                                <label for="title">Titolo</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                              </div>
                              <div class="form-group">
                                <label for="description">Descrizione</label>
                                <textarea class="form-control" id="description" name="description"></textarea>
                              </div>
                              <div class="form-group">
                                <label for="start">Data Inizio</label>
                                <input type="datetime-local" class="form-control" id="start" name="start" required>
                              </div>
                              <div class="form-group">
                                <label for="end">Data Fine</label>
                                <input type="datetime-local" class="form-control" id="end" name="end">
                              </div>
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" id="allDay" name="allDay"> Evento per l'intera giornata
                                </label>
                              </div>
                              <!-- Checkbox per Evento Aziendale -->
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" id="is_company_event" name="is_company_event"> Questo è un evento aziendale
                                </label>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
                              <button type="submit" class="btn btn-primary">Salva</button>
                              <button type="button" class="btn btn-danger" id="deleteEventBtn" style="display: none;">Elimina</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <!-- Sezione per le Note -->
                    <h3 class="mt-5">Note</h3>
                    <form action="update_note_lavoratore.php" method="POST">
                        <input type="hidden" name="id" value="<?php echo sanitizeForHTML($lavoratore['id']); ?>">
                        <!-- Token CSRF -->
                        <?php csrfInputField(); ?>
                        <div class="mb-3">
                            <label for="note" class="form-label">Note</label>
                            <textarea class="form-control" id="note" name="note"><?php echo htmlspecialchars($lavoratore['note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary">Salva Note</button>
                    </form>

                    <!-- Sezione per Caricare Nuovi Documenti -->
                    <h3 class="mt-5">Carica Documenti</h3>
                    <p>Prima di caricare un documento, inserire una descrizione obbligatoria.</p>
                    <form action="upload_documento.php" class="dropzone" id="documentUploadDropzone">
                      <!-- Token CSRF -->
                      <?php csrfInputField(); ?>
                      <input type="hidden" name="lavoratore_id" value="<?php echo sanitizeForHTML($lavoratore_id); ?>" />
                      <!-- Campo per la descrizione del documento -->
                      <div class="mb-3">
                        <label for="descrizione_documento" class="form-label">Descrizione <span class="text-danger">*</span></label>
                        <input
                          type="text"
                          class="form-control"
                          id="descrizione_documento"
                          name="descrizione_documento"
                          required
                        />
                      </div>
                    </form>

                    <!-- Filtro Documenti -->
                    <form method="GET" class="form-inline mb-3">
        <input type="hidden" name="id" value="<?php echo sanitizeForHTML($lavoratore_id); ?>">
        <div class="form-group mr-2">
            <label for="anno_filtro" class="mr-2">Filtra per Anno:</label>
            <select name="anno_filtro" id="anno_filtro" class="form-control">
                <option value="">Tutti</option>
                <?php
                // Ottieni gli anni disponibili
                $anni_query = "SELECT DISTINCT YEAR(data_caricamento) as anno FROM documenti_lavoratori WHERE lavoratore_id = ? ORDER BY anno DESC";
                $stmt = executeQuery($anni_query, [$lavoratore_id], 'i');
                if ($stmt !== false) {
                    $anni_result = $stmt->get_result();
                    while ($anno_row = $anni_result->fetch_assoc()) {
                        $anno = $anno_row['anno'];
                        echo '<option value="' . sanitizeForHTML($anno) . '"' . ($anno == $anno_filtro ? ' selected' : '') . '>' . sanitizeForHTML($anno) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
        <div class="form-group mr-2">
            <label for="search_descrizione" class="mr-2">Cerca per Descrizione:</label>
            <input type="text" name="search_descrizione" id="search_descrizione" class="form-control" value="<?php echo sanitizeForHTML($search_descrizione); ?>">
        </div>
        <button type="submit" class="btn btn-secondary">Filtra</button>
        <a href="lavoratore.php?id=<?php echo sanitizeForHTML($lavoratore_id); ?>" class="btn btn-link">Reset</a>
    </form>


               <!-- Lista dei Documenti Caricati -->
<!-- Lista dei Documenti Caricati -->
<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <tr>
        <th>Select</th>
        <th>Anteprima</th>
        <th>Descrizione</th>
        <th>Data Caricamento</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($doc = $documenti->fetch_assoc()): ?>
        <?php 
          // Costruisci il percorso del file e ne ottieni l'estensione
          $file_path = $base_url . 'uploads/' . sanitizeForHTML($doc['percorso_documento']);
          $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        ?>
        <tr>
          <td>
            <input type="checkbox" name="document_ids[]" value="<?php echo sanitizeForHTML($doc['id']); ?>">
          </td>
          <td>
            <?php
              if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                echo '<img src="' . sanitizeForHTML($file_path) . '" alt="Anteprima Immagine" class="preview-img">';
              } elseif ($file_ext == 'pdf') {
                echo '<embed src="' . sanitizeForHTML($file_path) . '" type="application/pdf" class="preview-pdf">';
              } else {
                echo 'N/A';
              }
            ?>
          </td>
          <td id="desc-<?php echo sanitizeForHTML($doc['id']); ?>">
            <span id="desc-text-<?php echo sanitizeForHTML($doc['id']); ?>">
              <?php echo sanitizeForHTML($doc['descrizione_documento']); ?>
            </span>
            <br>
            <button type="button" class="btn btn-sm btn-secondary" onclick="editDescription(<?php echo sanitizeForHTML($doc['id']); ?>)">Modifica</button>
          </td>
          <td><?php echo date('d/m/Y H:i', strtotime($doc['data_caricamento'])); ?></td>
          <td>
            <a href="<?php echo sanitizeForHTML($file_path); ?>" target="_blank" class="btn btn-sm btn-primary">Visualizza</a>
            <form action="delete_documento.php" method="POST" style="display:inline;" onsubmit="return confirm('Sei sicuro di voler eliminare questo documento?');">
              <?php csrfInputField(); ?>
              <input type="hidden" name="id" value="<?php echo sanitizeForHTML($doc['id']); ?>">
              <input type="hidden" name="lavoratore_id" value="<?php echo sanitizeForHTML($lavoratore_id); ?>">
              <button type="submit" class="btn btn-sm btn-danger">Elimina</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<!-- Pulsante separato per scaricare i documenti selezionati -->
<button id="downloadZipBtn" class="btn btn-primary mt-3">Scarica Documenti Selezionati</button>

<!-- Container per il link ZIP -->
<?php if(isset($_SESSION['zip_link'])): ?>
  <div id="zipLinkContainer" style="margin-top:20px;">
    <div class="input-group">
      <input type="text" id="zipLink" class="form-control" readonly value="<?php echo sanitizeForHTML($_SESSION['zip_link'] ?? ''); ?>">
      <div class="input-group-append">
        <button id="copyZipLinkBtn" class="btn btn-secondary" type="button">Copia Link</button>
        <button id="cancelZipLinkBtn" class="btn btn-danger" type="button">Elimina</button>
      </div>
    </div>
  </div>
<?php else: ?>
  <div id="zipLinkContainer" style="display:none; margin-top:20px;">
    <div class="input-group">
      <input type="text" id="zipLink" class="form-control" readonly>
      <div class="input-group-append">
        <button id="copyZipLinkBtn" class="btn btn-secondary" type="button">Copia Link</button>
        <button id="cancelZipLinkBtn" class="btn btn-danger" type="button">Elimina</button>
      </div>
    </div>
  </div>
<?php endif; ?>







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

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>
<style>.tox {
    z-index: 1040 !important;
		</style>
    <!-- Modali e script -->
    <!-- Bootstrap core JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- SB Admin 2 JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>

    <!-- jQuery UI -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.js"></script>
    <!-- TinyMCE -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>vendor/tinymce/tinymce.min.js"></script>
    <!-- Moment.js -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/moment.min.js"></script>
    <!-- FullCalendar JS -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/core.global.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/daygrid.global.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/timegrid.global.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/list.global.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/interaction.global.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/locales-all.global.min.js"></script>
    <!-- Dropzone -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/dropzone/dropzone-min.js"></script>


    <script>
      // Disabilita l'auto-discover per Dropzone
      Dropzone.autoDiscover = false;

      var myDropzone = new Dropzone("#documentUploadDropzone", {
        paramName: "file",
        maxFilesize: 10, // MB
        addRemoveLinks: true,
        dictDefaultMessage: "Trascina qui i file o clicca per selezionare",
        acceptedFiles: "image/*,application/pdf",
        init: function () {
          var descrizioneInput = document.getElementById("descrizione_documento");
          var csrfToken = '<?php echo sanitizeForHTML($_SESSION['csrf_token']); ?>';

          this.on("sending", function (file, xhr, formData) {
            // Aggiungi la descrizione e il token CSRF al formData
            formData.append("descrizione_documento", descrizioneInput.value);
            formData.append("csrf_token", csrfToken);
          });
          this.on("success", function (file, response) {
            // Ricarica la pagina per aggiornare la lista dei documenti
            location.reload();
          });
          this.on("error", function (file, errorMessage) {
            alert("Errore: " + errorMessage);
          });
        },
      });
    </script>

    <!-- Inizializzazione di TinyMCE -->
    <script>
        if (typeof tinymce !== 'undefined') { tinymce.init({
            selector: '#note, #description',
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
            base_url: '<?php echo sanitizeForHTML($base_url); ?>vendor/tinymce',
            suffix: '.min',
            license_key: 'gpl',
            setup: function (editor) {
                editor.on('init', function () {
                    this.getContainer().style.zIndex = 9000;
                });
            }
        }); }
    </script>

    <!-- Inizializzazione del Calendario -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'it',
                timeZone: 'local',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                editable: true,
                selectable: true,
                events: {
                    url: 'fetch_events.php',
                    method: 'POST',
                    extraParams: {
                        lavoratore_id: '<?php echo sanitizeForHTML($lavoratore_id); ?>',
                        azienda_id: '<?php echo sanitizeForHTML($lavoratore['azienda_id']); ?>',
                        csrf_token: '<?php echo sanitizeForHTML($_SESSION['csrf_token']); ?>'
                    },
                    failure: function() {
                        Swal.fire(
                            'Errore!',
                            'Errore nel caricamento degli eventi!',
                            'error'
                        );
                    }
                },
                select: function(info) {
                    // Reset the form
                    $('#eventForm')[0].reset();
                    tinymce.get('description').setContent('');
                    $('#eventId').val('');
                    $('#title').val('');
                    // Prefill start date with selected date
                    var selectedDate = info.startStr.substring(0,10) + (info.allDay ? '' : 'T09:00');
                    $('#start').val(info.allDay ? info.startStr.substring(0,10) + 'T00:00' : moment(info.start).format('YYYY-MM-DDTHH:mm'));
                    $('#end').val(info.endStr ? (info.allDay ? moment(info.end).subtract(1, 'days').format('YYYY-MM-DD') : moment(info.end).format('YYYY-MM-DDTHH:mm')) : '');
                    $('#allDay').prop('checked', info.allDay);
                    $('#is_company_event').prop('checked', false);
                    $('#eventModalLabel').text('Aggiungi Evento');
                    $('#deleteEventBtn').hide();
                    $('#eventModal').modal('show');
                },
                eventClick: function(info) {
                    // Populate the form with event data
                    $('#eventId').val(info.event.id);
                    $('#title').val(info.event.title);
                    tinymce.get('description').setContent(info.event.extendedProps.description || '');
                    $('#start').val(moment(info.event.start).format('YYYY-MM-DDTHH:mm'));
                    $('#end').val(info.event.end ? moment(info.event.end).format('YYYY-MM-DDTHH:mm') : '');
                    $('#allDay').prop('checked', info.event.allDay);
                    $('#is_company_event').prop('checked', info.event.extendedProps.is_company_event == 1);
                    $('#eventModalLabel').text('Modifica Evento');
                    $('#deleteEventBtn').show();
                    $('#eventModal').modal('show');
                },
                eventDrop: function(info) {
                    updateEvent(info.event);
                },
                eventResize: function(info) {
                    updateEvent(info.event);
                }
            });

            calendar.render();

            // Form submission
            $('#eventForm').on('submit', function(e) {
                e.preventDefault();
                var isCompanyEvent = $('#is_company_event').is(':checked');
                var eventData = {
                    id: $('#eventId').val(),
                    title: $('#title').val(),
                    description: tinymce.get('description').getContent(),
                    start: $('#start').val(),
                    end: $('#end').val(),
                    allDay: $('#allDay').is(':checked') ? 1 : 0,
                    is_company_event: isCompanyEvent ? 1 : 0,
                    lavoratore_id: isCompanyEvent ? null : '<?php echo sanitizeForHTML($lavoratore_id); ?>',
                    azienda_id: '<?php echo sanitizeForHTML($lavoratore['azienda_id']); ?>',
                    csrf_token: '<?php echo sanitizeForHTML($_SESSION['csrf_token']); ?>'
                };

                console.log("Dati inviati:", eventData); // Debug

                var url = eventData.id ? 'update_event.php' : 'add_event.php';

                $.ajax({
                    url: url,
                    method: 'POST',
                    data: eventData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#eventModal').modal('hide');
                            calendar.refetchEvents();
                            Swal.fire(
                                'Successo!',
                                response.message || 'Evento salvato con successo.',
                                'success'
                            );
                        } else {
                            Swal.fire(
                                'Errore!',
                                response.error || 'Si è verificato un errore.',
                                'error'
                            );
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error("Errore AJAX:", textStatus, errorThrown);
                        Swal.fire(
                            'Errore!',
                            'Errore nella comunicazione con il server.',
                            'error'
                        );
                    }
                });
            });


          // Delete event
    $('#deleteEventBtn').on('click', function() {
        var isCompanyEvent = $('#is_company_event').is(':checked');

        if (isCompanyEvent) {
            Swal.fire({
                title: 'Sei sicuro?',
                text: "Vuoi eliminare questo evento per tutti i lavoratori dell'azienda?",
                icon: 'warning',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonColor: '#d33',
                denyButtonColor: '#3085d6',
                confirmButtonText: 'Elimina per tutti',
                denyButtonText: 'Elimina solo per questo lavoratore'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Elimina per tutti i lavoratori dell'azienda
                    var deleteData = {
                        id: $('#eventId').val(),
                        lavoratore_id: '<?php echo sanitizeForHTML($lavoratore_id); ?>',
                        delete_for_all: 1, // Cambiato da 'is_company_event'
                        csrf_token: '<?php echo sanitizeForHTML($_SESSION['csrf_token']); ?>'
                    };

                    $.ajax({
                        url: 'delete_event.php',
                        method: 'POST',
                        data: deleteData,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                $('#eventModal').modal('hide');
                                calendar.refetchEvents();
                                Swal.fire(
                                    'Eliminato!',
                                    response.message || 'Evento eliminato per tutti i lavoratori dell\'azienda.',
                                    'success'
                                );
                            } else {
                                Swal.fire(
                                    'Errore!',
                                    response.error || 'Si è verificato un errore.',
                                    'error'
                                );
                            }
                        },
                        error: function() {
                            Swal.fire(
                                'Errore!',
                                'Errore nella comunicazione con il server.',
                                'error'
                            );
                        }
                    });
                } else if (result.isDenied) {
                    // Elimina solo per questo lavoratore
                    Swal.fire({
                        title: 'Sei sicuro?',
                        text: "Vuoi eliminare questo evento solo per questo lavoratore?",
                        icon: 'warning',
                        showCancelButton: false,
                        confirmButtonColor: '#d33',
                        confirmButtonText: 'Elimina'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            var deleteData = {
                                id: $('#eventId').val(),
                                lavoratore_id: '<?php echo sanitizeForHTML($lavoratore_id); ?>',
                                delete_for_all: 0, // Cambiato da 'is_company_event'
                                csrf_token: '<?php echo sanitizeForHTML($_SESSION['csrf_token']); ?>'
                            };

                            $.ajax({
                                url: 'delete_event.php',
                                method: 'POST',
                                data: deleteData,
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        $('#eventModal').modal('hide');
                                        calendar.refetchEvents();
                                        Swal.fire(
                                            'Eliminato!',
                                            response.message || 'Evento eliminato con successo.',
                                            'success'
                                        );
                                    } else {
                                        Swal.fire(
                                            'Errore!',
                                            response.error || 'Si è verificato un errore.',
                                            'error'
                                        );
                                    }
                                },
                                error: function() {
                                    Swal.fire(
                                        'Errore!',
                                        'Errore nella comunicazione con il server.',
                                        'error'
                                    );
                                }
                            });
                        }
                    });
                }
            });
        } else {
            // Elimina solo per questo lavoratore
            Swal.fire({
                title: 'Sei sicuro?',
                text: "Vuoi eliminare questo evento solo per questo lavoratore?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Elimina',
                cancelButtonText: 'Annulla'
            }).then((result) => {
                if (result.isConfirmed) {
                    var deleteData = {
                        id: $('#eventId').val(),
                        lavoratore_id: '<?php echo sanitizeForHTML($lavoratore_id); ?>',
                        delete_for_all: 0, // Cambiato da 'is_company_event'
                        csrf_token: '<?php echo sanitizeForHTML($_SESSION['csrf_token']); ?>'
                    };

                    $.ajax({
                        url: 'delete_event.php',
                        method: 'POST',
                        data: deleteData,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                $('#eventModal').modal('hide');
                                calendar.refetchEvents();
                                Swal.fire(
                                    'Eliminato!',
                                    response.message || 'Evento eliminato con successo.',
                                    'success'
                                );
                            } else {
                                Swal.fire(
                                    'Errore!',
                                    response.error || 'Si è verificato un errore.',
                                    'error'
                                );
                            }
                        },
                        error: function() {
                            Swal.fire(
                                'Errore!',
                                'Errore nella comunicazione con il server.',
                                'error'
                            );
                        }
                    });
                }
            });
        }
    });


            // Update event function
            function updateEvent(event) {
                var eventData = {
                    id: event.id,
                    title: event.title,
                    description: event.extendedProps.description || '',
                    start: moment(event.start).format('YYYY-MM-DDTHH:mm'),
                    end: event.end ? moment(event.end).format('YYYY-MM-DDTHH:mm') : '',
                    allDay: event.allDay ? 1 : 0,
                    is_company_event: event.extendedProps.is_company_event == 1 ? 1 : 0,
                    lavoratore_id: '<?php echo sanitizeForHTML($lavoratore_id); ?>',
                    azienda_id: '<?php echo sanitizeForHTML($lavoratore['azienda_id']); ?>',
                    csrf_token: '<?php echo sanitizeForHTML($_SESSION['csrf_token']); ?>'
                };

                $.ajax({
                    url: 'update_event.php',
                    method: 'POST',
                    data: eventData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire(
                                'Successo!',
                                response.message || 'Evento aggiornato con successo.',
                                'success'
                            );
                        } else {
                            Swal.fire(
                                'Errore!',
                                response.error || 'Si è verificato un errore.',
                                'error'
                            );
                            calendar.refetchEvents();
                        }
                    },
                    error: function() {
                        Swal.fire(
                            'Errore!',
                            'Errore nella comunicazione con il server.',
                            'error'
                        );
                        calendar.refetchEvents();
                    }
                });
            }
        });
    </script>
		
<script>
  // Funzioni per la modifica della descrizione (rimangono inalterate)
  function editDescription(docId) {
    var descCell = document.getElementById("desc-" + docId);
    var currentDesc = document.getElementById("desc-text-" + docId).innerText;
    descCell.textContent = '';
    var form = document.createElement('form');
    form.action = 'update_document_description.php';
    form.method = 'POST';
    form.onsubmit = function(e) { return updateDescription(e, docId); };
    var csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = <?php echo json_encode($_SESSION['csrf_token']); ?>;
    form.appendChild(csrfInput);
    var hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'doc_id';
    hiddenInput.value = docId;
    form.appendChild(hiddenInput);
    var textInput = document.createElement('input');
    textInput.type = 'text';
    textInput.name = 'description';
    textInput.value = currentDesc;
    textInput.required = true;
    form.appendChild(textInput);
    var saveBtn = document.createElement('button');
    saveBtn.type = 'submit';
    saveBtn.className = 'btn btn-sm btn-secondary';
    saveBtn.textContent = 'Salva';
    form.appendChild(saveBtn);
    var cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'btn btn-sm btn-secondary';
    cancelBtn.textContent = 'Annulla';
    cancelBtn.onclick = function() { cancelEdit(docId, currentDesc); };
    form.appendChild(cancelBtn);
    descCell.appendChild(form);
  }

  function updateDescription(event, docId) {
    event.preventDefault();
    var form = event.target;
    var formData = new FormData(form);
    fetch(form.action, {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        cancelEdit(docId, data.new_description);
      } else {
        alert("Errore: " + data.message);
      }
    })
    .catch(error => {
      console.error('Errore:', error);
    });
    return false;
  }

  function cancelEdit(docId, originalDesc) {
    var descCell = document.getElementById("desc-" + docId);
    descCell.textContent = '';
    var span = document.createElement('span');
    span.id = 'desc-text-' + docId;
    span.textContent = originalDesc;
    descCell.appendChild(span);
    descCell.appendChild(document.createElement('br'));
    var editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'btn btn-sm btn-secondary';
    editBtn.textContent = 'Modifica';
    editBtn.onclick = function() { editDescription(docId); };
    descCell.appendChild(editBtn);
  }

  // Gestione del click per scaricare i documenti selezionati
  document.getElementById('downloadZipBtn').addEventListener('click', function(e) {
    // Raccogli le checkbox selezionate
    var checkboxes = document.querySelectorAll('input[name="document_ids[]"]:checked');
    if (checkboxes.length === 0) {
      alert('Seleziona almeno un documento.');
      return;
    }
    var formData = new FormData();
    checkboxes.forEach(function(checkbox) {
      formData.append('document_ids[]', checkbox.value);
    });
    
    fetch('download_documents_zip.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        var zipLinkContainer = document.getElementById('zipLinkContainer');
        var zipLinkInput = document.getElementById('zipLink');
        zipLinkInput.value = data.download_link;
        zipLinkContainer.style.display = 'block';
      } else {
        alert('Errore: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Errore:', error);
    });
  });

  // Gestione del pulsante per copiare il link negli appunti
  document.getElementById('copyZipLinkBtn').addEventListener('click', function() {
    var zipLinkInput = document.getElementById('zipLink');
    zipLinkInput.select();
    zipLinkInput.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(zipLinkInput.value).then(function() {
      alert('Link copiato negli appunti!');
    }, function(err) {
      alert('Errore durante la copia: ' + err);
    });
  });

  // Gestione del pulsante per annullare il link e cancellarlo dalla sessione (e cancellare il file ZIP)
  document.getElementById('cancelZipLinkBtn').addEventListener('click', function() {
    fetch('clear_zip_link.php', {
      method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        document.getElementById('zipLinkContainer').style.display = 'none';
      } else {
        alert('Errore durante la cancellazione del link.');
      }
    })
    .catch(error => {
      console.error('Errore:', error);
    });
  });
</script>




</body> 
</html>