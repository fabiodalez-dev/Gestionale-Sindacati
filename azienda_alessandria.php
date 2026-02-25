<?php
require 'config_alessandria.php';
checkLogin();

// Funzione per recuperare le unità operative dell'azienda
function getUnitaOperative($mysqli, $azienda_id) {
    $query = "SELECT * FROM unita_operativa WHERE azienda_id = ?";
    $stmt = executeQuery($query, [$azienda_id], 'i');
    if ($stmt === false) {
        return false;
    }
    return $stmt->get_result();
}

// Verifica se l'ID dell'azienda è stato fornito
if (isset($_GET['id'])) {
    $azienda_id = intval($_GET['id']);

    // Recupera i dati dell'azienda
    $query = "SELECT * FROM aziende WHERE id = ?";
    $stmt = executeQuery($query, [$azienda_id], 'i');
    if ($stmt === false) {
        echo "Errore nella query.";
        exit;
    }
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $azienda = $result->fetch_assoc();
    } else {
        echo "Azienda non trovata.";
        exit;
    }

    // Recupera le unità operative associate all'azienda
    $unita_operativa_result = getUnitaOperative($mysqli, $azienda_id);
    if ($unita_operativa_result === false) {
        echo "Errore nella query delle unità operative.";
        exit;
    }

    // Recupera i documenti associati all'azienda
    $query = "SELECT * FROM documenti_aziende WHERE azienda_id = ?";
    $stmt = executeQuery($query, [$azienda_id], 'i');
    if ($stmt === false) {
        echo "Errore nella query dei documenti.";
        exit;
    }
    $documenti = $stmt->get_result();

    // Recupera il numero di lavoratori associati all'azienda
    $query = "SELECT COUNT(*) as numero_lavoratori FROM lavoratori WHERE azienda_id = ?";
    $stmt = executeQuery($query, [$azienda_id], 'i');
    if ($stmt === false) {
        echo "Errore nella query dei lavoratori.";
        exit;
    }
    $result = $stmt->get_result();
    $numero_lavoratori = 0;
    if ($row = $result->fetch_assoc()) {
        $numero_lavoratori = $row['numero_lavoratori'];
    }

    // Recupera la lista dei lavoratori associati all'azienda
    $query = "SELECT id, nome, cognome FROM lavoratori WHERE azienda_id = ?";
    $stmt = executeQuery($query, [$azienda_id], 'i');
    if ($stmt === false) {
        echo "Errore nella query dei lavoratori.";
        exit;
    }
    $lavoratori = $stmt->get_result();
} else {
    echo "ID azienda non specificato.";
    exit;
}

// Recupera le unità operative
$unita_operativa = $unita_operativa_result->fetch_all(MYSQLI_ASSOC);

// Gestione dei messaggi di successo o errore
$upload_success = isset($_GET['upload_success']);
$upload_error = isset($_GET['upload_error']) ? $_GET['upload_error'] : '';
$delete_success = isset($_GET['delete_success']);
$delete_error = isset($_GET['delete_error']) ? $_GET['delete_error'] : '';
$update_success = isset($_GET['update_success']);
$update_error = isset($_GET['update_error']) ? $_GET['update_error'] : '';
$unita_add_success = isset($_GET['unita_add_success']);
$unita_add_error = isset($_GET['unita_add_error']) ? $_GET['unita_add_error'] : '';
$unita_update_success = isset($_GET['unita_update_success']);
$unita_update_error = isset($_GET['unita_update_error']) ? $_GET['unita_update_error'] : '';
$unita_delete_success = isset($_GET['unita_delete_success']);
$unita_delete_error = isset($_GET['unita_delete_error']) ? $_GET['unita_delete_error'] : '';

// Gestione della richiesta POST per aggiornare le note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note'])) {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "Token CSRF mancante o non valido.";
        header("Location: azienda.php?id=" . $azienda_id . "&update_error=" . urlencode($error));
        exit;
    }

    // Recupera e sanitizza le note
    $note = isset($_POST['note']) ? $_POST['note'] : '';
    $note_sanitized = sanitizeHTML($note); // Utilizza sanitizeHTML per permettere tag HTML

    // Prepara i parametri e i tipi per la query di aggiornamento
    $params = [
        $note_sanitized,
        $azienda_id
    ];
    $types = 'si';

    // Query di aggiornamento delle note
    $update_query = "UPDATE aziende SET note = ? WHERE id = ?";

    $update_stmt = executeQuery($update_query, $params, $types);

    if ($update_stmt) {
        // Reindirizza con successo
        header("Location: azienda.php?id=$azienda_id&update_success=1");
        exit;
    } else {
        error_log("Errore durante l'aggiornamento delle note: " . $mysqli->error);
        $error = "Errore durante l'aggiornamento delle note: " . $mysqli->error;
        header("Location: azienda.php?id=$azienda_id&update_error=" . urlencode($error));
        exit;
    }
}

// Genera il token CSRF
generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dettaglio Azienda - CRM Admin</title>
    <!-- Meta viewport per la responsività -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <!-- jQuery UI CSS per l'autocomplete (se necessario) -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <!-- Custom CSS (se necessario) -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css" rel="stylesheet">
    <!-- FullCalendar CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <!-- SweetAlert2 per notifiche -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>
    <style>
        /* Eventuali stili personalizzati */
        #calendar {
            max-width: 100%;
            margin: 0 auto;
        }
        .fc-event {
            cursor: pointer;
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
		/* Stili eventi calendario migliorati */
.fc-event {
    overflow: hidden !important;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 2px 4px !important;
    background-color: #4e73df !important; /* colore di sfondo migliore */
    color: #fff !important; /* testo bianco visibile */
    border-radius: 3px;
    font-size: 0.8rem;
}
		.fc-daygrid-event-harness {
    padding-bottom: 20px;
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
                    <h1 class="h3 mb-4 text-gray-800">Dettaglio Azienda: <?php echo sanitizeForHTML($azienda['nome_azienda']); ?></h1>

                    <!-- Gestione dei Messaggi -->
                    <?php if ($upload_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Documento caricato con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($upload_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($upload_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($delete_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Documento eliminato con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($delete_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($delete_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($update_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Dati aggiornati con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($update_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($update_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($unita_add_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Unità operativa aggiunta con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($unita_add_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($unita_add_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($unita_update_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Unità operativa aggiornata con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($unita_update_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($unita_update_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($unita_delete_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Unità operativa eliminata con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($unita_delete_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($unita_delete_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Informazioni Azienda -->
                    <div class="card mt-4">
                        <div class="card-header bg-primary text-white">
                            Informazioni Azienda
                        </div>
                        <div class="card-body">
                            <h4 class="card-title">Dettagli Azienda</h4>
                            <div class="row">
                                <div class="col-12 col-md-6 mb-3">
                                    <strong>Nome Azienda:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['nome_azienda'] ?? ''); ?></p>
                                </div>
                                <div class="col-12 col-md-6 mb-3">
                                    <strong>Partita IVA:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['partita_iva'] ?? ''); ?></p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-md-6 mb-3">
                                    <strong>Numero di Lavoratori:</strong>
                                    <p><?php echo sanitizeForHTML($numero_lavoratori); ?></p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-md-4 mb-3">
                                    <strong>Indirizzo (Via):</strong>
                                    <p><?php echo sanitizeForHTML($azienda['indirizzo_via'] ?? ''); ?></p>
                                </div>
                                <div class="col-6 col-md-2 mb-3">
                                    <strong>N. Civico:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['indirizzo_numero_civico'] ?? ''); ?></p>
                                </div>
                                <div class="col-6 col-md-2 mb-3">
                                    <strong>CAP:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['indirizzo_cap'] ?? ''); ?></p>
                                </div>
                                <div class="col-12 col-md-4 mb-3">
                                    <strong>PEC:</strong>
                                    <p>
                                        <?php if (!empty($azienda['pec'])): ?>
                                            <a href="mailto:<?php echo sanitizeForHTML($azienda['pec']); ?>">
                                                <?php echo sanitizeForHTML($azienda['pec']); ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-md-4 mb-3">
                                    <strong>Città:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['indirizzo_citta'] ?? ''); ?></p>
                                </div>
                                <div class="col-12 col-md-4 mb-3">
                                    <strong>Provincia:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['indirizzo_provincia'] ?? ''); ?></p>
                                </div>
                                <div class="col-12 col-md-4 mb-3">
                                    <strong>Settore:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['settore'] ?? ''); ?></p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-md-6 mb-3">
                                    <strong>Telefono:</strong>
                                    <p>
                                        <?php if (!empty($azienda['telefono'])): ?>
                                            <a href="tel:<?php echo sanitizeForHTML($azienda['telefono']); ?>">
                                                <?php echo sanitizeForHTML($azienda['telefono']); ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-12 col-md-6 mb-3">
                                    <strong>Email:</strong>
                                    <p>
                                        <?php if (!empty($azienda['email'])): ?>
                                            <a href="mailto:<?php echo sanitizeForHTML($azienda['email']); ?>">
                                                <?php echo sanitizeForHTML($azienda['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <!-- Pulsante per modificare l'azienda -->
                           
                        </div>
                    </div>

                    

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

    <!-- Modali e script -->
    <!-- Bootstrap core JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- SB Admin 2 JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>

    <!-- jQuery UI per l'autocomplete -->
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <!-- TinyMCE -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <!-- FullCalendar Locale -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js"></script>

    <!-- Inizializzazione di TinyMCE -->
    <script>
        tinymce.init({
            selector: '#note, #description, #eventDescription', // Selettore per i campi di testo
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
            license_key: 'gpl', // Aggiunto per risolvere l'avviso di licenza
            setup: function (editor) {
                editor.on('init', function () {
                    // Imposta il z-index di TinyMCE inferiore a quello del modale Bootstrap (1050)
                    this.getContainer().style.zIndex = 1040;
                });
            },
            // Aggiungi questa opzione per gestire i modali correttamente
            inline: false
        });
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
            url: 'fetch_events_azienda.php',
            method: 'POST',
            extraParams: {
                azienda_id: '<?php echo sanitizeForHTML($azienda_id); ?>',
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
        eventContent: function(arg) {
            let title = arg.event.title;
            // Aggiungi il nome del lavoratore solo se presente
            if (arg.event.extendedProps.worker_name) {
                title += ' - ' + arg.event.extendedProps.worker_name;
            }
            return { html: '<b>' + title + '</b>' };
        },
        select: function(info) {
            // Reset del form nel modale di creazione evento
            $('#createEventForm')[0].reset();
            tinymce.get('eventDescription').setContent('');
            $('#eventWorker').val('');
            $('#allDay').prop('checked', info.allDay);
            $('#eventStart').val(info.startStr.substring(0,16));
            $('#eventEnd').val(info.endStr ? info.endStr.substring(0,16) : '');
            $('#createEventModalLabel').text('Crea Nuovo Evento Aziendale');
            $('#createEventModal').modal('show');
        },
        eventClick: function(info) {
            // Popola il form nel modale di modifica evento
            $('#eventId').val(info.event.id);
            $('#title').val(info.event.title);
            tinymce.get('description').setContent(info.event.extendedProps.description || '');
            $('#start').val(moment(info.event.start).format('YYYY-MM-DDTHH:mm'));
            $('#end').val(info.event.end ? moment(info.event.end).format('YYYY-MM-DDTHH:mm') : '');
            $('#allDay').prop('checked', info.event.allDay);

            // Imposta il selettore dei lavoratori
            if (info.event.extendedProps.worker_id) {
                $('#eventWorkerEdit').val(info.event.extendedProps.worker_id);
            } else {
                $('#eventWorkerEdit').val('');
            }

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

    // Gestione del submit del form di creazione evento
    $('#createEventForm').on('submit', function(e) {
        e.preventDefault();

        // Raccogli i dati del form
        var formData = {
            csrf_token: $('input[name="csrf_token"]').val(),
            azienda_id: '<?php echo sanitizeForHTML($azienda_id); ?>',
            titolo: $('#eventTitle').val(),
            descrizione: tinymce.get('eventDescription').getContent(),
            start: $('#eventStart').val(),
            end: $('#eventEnd').val(),
            all_day: $('#allDay').is(':checked') ? 1 : 0,
            worker_id: $('#eventWorker').val()
        };

        // Invia la richiesta AJAX per creare l'evento
        $.ajax({
            url: 'create_event_azienda.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response){
                if(response.success){
                    // Chiudi il modale
                    $('#createEventModal').modal('hide');

                    // Pulisci il form
                    $('#createEventForm')[0].reset();
                    tinymce.get('eventDescription').setContent('');
                    $('#eventWorker').val('');

                    // Mostra un messaggio di successo
                    Swal.fire(
                        'Successo!',
                        response.message,
                        'success'
                    );

                    // Aggiorna il calendario
                    calendar.refetchEvents();
                } else {
                    // Mostra un messaggio di errore
                    Swal.fire(
                        'Errore!',
                        response.message,
                        'error'
                    );
                }
            },
            error: function(){
                Swal.fire(
                    'Errore!',
                    'Si è verificato un errore durante la creazione dell\'evento.',
                    'error'
                );
            }
        });
    });

    // Gestione del submit del form di modifica evento
    $('#eventForm').on('submit', function(e) {
        e.preventDefault();
        var eventData = {
            id: $('#eventId').val(),
            titolo: $('#title').val(),
            descrizione: tinymce.get('description').getContent(),
            start: $('#start').val(),
            end: $('#end').val(),
            all_day: $('#allDay').is(':checked') ? 1 : 0,
            azienda_id: '<?php echo sanitizeForHTML($azienda_id); ?>',
            worker_id: $('#eventWorkerEdit').val(),
            csrf_token: '<?php echo sanitizeForHTML($_SESSION['csrf_token']); ?>'
        };

        var url = eventData.id ? 'update_event_azienda.php' : 'create_event_azienda.php';

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
                        response.message,
                        'success'
                    );
                } else {
                    Swal.fire(
                        'Errore!',
                        response.message,
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
    });

   // Delete event
    $('#deleteEventBtn').on('click', function() {
        var evento_id = $('#eventId').val();
        var azienda_id = '<?php echo sanitizeForHTML($azienda_id); ?>';

        if (!evento_id) {
            Swal.fire(
                'Errore!',
                'ID evento mancante.',
                'error'
            );
            return;
        }

        // Conferma eliminazione
        Swal.fire({
            title: 'Sei sicuro?',
            text: "Vuoi eliminare questo evento?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Elimina'
        }).then((result) => {
            if (result.isConfirmed) {
                // Invia la richiesta AJAX per eliminare l'evento
                $.ajax({
                    url: 'delete_event_azienda.php',
                    method: 'POST',
                    data: {
                        id: evento_id,
                        azienda_id: azienda_id,
                        csrf_token: '<?php echo sanitizeForHTML($_SESSION['csrf_token']); ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#eventModal').modal('hide');
                            calendar.refetchEvents();
                            Swal.fire(
                                'Eliminato!',
                                response.message,
                                'success'
                            );
                        } else {
                            Swal.fire(
                                'Errore!',
                                response.message,
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
    });

    // Funzione per aggiornare l'evento dopo drag-and-drop o resize
    function updateEvent(event) {
        var eventData = {
            id: event.id,
            titolo: event.title,
            descrizione: event.extendedProps.description || '',
            start: moment(event.start).format('YYYY-MM-DDTHH:mm'),
            end: event.end ? moment(event.end).format('YYYY-MM-DDTHH:mm') : '',
            all_day: event.allDay ? 1 : 0,
            azienda_id: '<?php echo sanitizeForHTML($azienda_id); ?>',
            worker_id: event.extendedProps.worker_id || null,
            csrf_token: '<?php echo sanitizeForHTML($_SESSION['csrf_token']); ?>'
        };

        $.ajax({
            url: 'update_event_azienda.php',
            method: 'POST',
            data: eventData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire(
                        'Successo!',
                        response.message,
                        'success'
                    );
                } else {
                    Swal.fire(
                        'Errore!',
                        response.message,
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

    // Gestione Modale per Modificare Unità Operative
    $('#modificaUnitaOperativaModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var nome = button.data('nome');
        var descrizione = button.data('descrizione');

        var modal = $(this);
        modal.find('#edit_unita_operativa_id').val(id);
        modal.find('#edit_nome_unita_operativa').val(nome);
        modal.find('#edit_descrizione_unita_operativa').val(descrizione);
    });

    // Gestione Modale per Eliminare Unità Operative
    $('.delete-unita-operativa-btn').on('click', function() {
        var id = $(this).data('id');
        var nome = $(this).data('nome');

        Swal.fire({
            title: 'Sei sicuro?',
            text: "Vuoi eliminare l'unità operativa: " + nome + "?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Elimina'
        }).then((result) => {
            if (result.isConfirmed) {
                // Reindirizza alla pagina di eliminazione
                window.location.href = 'elimina_unita_operativa.php?id=' + id + '&azienda_id=<?php echo sanitizeForHTML($azienda_id); ?>';
            }
        });
    });
});
    </script>
	<script>
function editDescription(docId) {
  // Recupera la cella della descrizione e il testo attuale
  var descCell = document.getElementById("desc-" + docId);
  var currentDesc = document.getElementById("desc-text-" + docId).innerText;
  
  // Sostituisce il contenuto della cella con un form di modifica
  descCell.innerHTML = `
    <form action=" update_document_description_azienda.php" method="POST" onsubmit="return updateDescription(event, ${docId});">
      <input type="hidden" name="doc_id" value="${docId}">
      <input type="text" name="description" value="${currentDesc}" required>
      <button type="submit" class="btn btn-sm btn-success">Salva</button>
      <button type="button" class="btn btn-sm btn-warning" onclick="cancelEdit(${docId}, '${currentDesc.replace(/'/g, "\\'")}')">Annulla</button>
    </form>
  `;
}

function updateDescription(event, docId) {
  event.preventDefault();
  var form = event.target;
  var formData = new FormData(form);

  // Invio dati via AJAX allo script PHP che aggiorna la descrizione
  fetch(form.action, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Ripristina la cella con la nuova descrizione e il bottone "Modifica"
      var descCell = document.getElementById("desc-" + docId);
      descCell.innerHTML = `<span id="desc-text-${docId}">${data.new_description}</span>
      <br><button type="button" class="btn btn-sm btn-secondary" onclick="editDescription(${docId})">Modifica</button>`;
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
  // Ripristina la visualizzazione originale in caso di annullamento
  var descCell = document.getElementById("desc-" + docId);
  descCell.innerHTML = `<span id="desc-text-${docId}">${originalDesc}</span>
  <br><button type="button" class="btn btn-sm btn-secondary" onclick="editDescription(${docId})">Modifica</button>`;
}
</script>


</body>
</html>
