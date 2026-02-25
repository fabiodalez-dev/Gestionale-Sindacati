<?php
require 'config.php';
checkLogin();

// Imposta l'encoding della connessione al database
$mysqli->set_charset("utf8mb4");

// Query per ottenere il numero totale di lavoratori iscritti (iscritto = 1)
$query_iscritti = "SELECT COUNT(*) AS totale_iscritti FROM lavoratori WHERE iscritto = 1";
$result_iscritti = $mysqli->query($query_iscritti);
$totale_iscritti = (int)$result_iscritti->fetch_assoc()['totale_iscritti'];

// Query per ottenere il numero totale di lavoratori
$query_totale_lavoratori = "SELECT COUNT(*) AS totale_lavoratori FROM lavoratori";
$result_totale_lavoratori = $mysqli->query($query_totale_lavoratori);
$totale_lavoratori = (int)$result_totale_lavoratori->fetch_assoc()['totale_lavoratori'];

// Query per la distribuzione per città
$query_citta = "SELECT indirizzo_citta, COUNT(*) AS totale FROM lavoratori WHERE indirizzo_citta IS NOT NULL AND indirizzo_citta != '' GROUP BY indirizzo_citta";
$result_citta = $mysqli->query($query_citta);
$citta_labels = [];
$citta_data = [];
while ($row = $result_citta->fetch_assoc()) {
    $citta_labels[] = $row['indirizzo_citta'];
    $citta_data[] = (int)$row['totale'];
}

// Query per la distribuzione per nazionalità
$query_nazionalita = "SELECT nazionalita, COUNT(*) AS totale FROM lavoratori WHERE nazionalita IS NOT NULL AND nazionalita != '' GROUP BY nazionalita";
$result_nazionalita = $mysqli->query($query_nazionalita);
$nazionalita_labels = [];
$nazionalita_data = [];
while ($row = $result_nazionalita->fetch_assoc()) {
    $nazionalita_labels[] = $row['nazionalita'];
    $nazionalita_data[] = (int)$row['totale'];
}

// Calcola il totale per le percentuali nel grafico a torta
$totale_nazionalita = array_sum($nazionalita_data);

// Query per il numero di lavoratori per azienda
$query_aziende = "
    SELECT a.nome_azienda, COUNT(l.id) AS totale_lavoratori
    FROM aziende a
    JOIN lavoratori l ON a.id = l.azienda_id
    GROUP BY a.nome_azienda
    ORDER BY totale_lavoratori DESC
";
$result_aziende = $mysqli->query($query_aziende);
$aziende_labels = [];
$aziende_data = [];
while ($row = $result_aziende->fetch_assoc()) {
    $aziende_labels[] = $row['nome_azienda'];
    $aziende_data[] = (int)$row['totale_lavoratori'];
}

// Query per la distribuzione per Settore
$query_settore = "SELECT settore, COUNT(*) AS totale FROM lavoratori WHERE settore IS NOT NULL AND settore != '' GROUP BY settore";
$result_settore = $mysqli->query($query_settore);
$settore_labels = [];
$settore_data = [];
while ($row = $result_settore->fetch_assoc()) {
    $settore_labels[] = $row['settore'];
    $settore_data[] = (int)$row['totale'];
}

// Calcola il totale per le percentuali nel grafico a torta Settore
$totale_settore = array_sum($settore_data);

// Query per la distribuzione per Genere
$query_genere = "SELECT genere, COUNT(*) AS totale FROM lavoratori WHERE genere IS NOT NULL AND genere != '' GROUP BY genere";
$result_genere = $mysqli->query($query_genere);
$genere_labels = [];
$genere_data = [];
while ($row = $result_genere->fetch_assoc()) {
    $genere_labels[] = $row['genere'];
    $genere_data[] = (int)$row['totale'];
}

// Calcola il totale per le percentuali nel grafico a torta Genere
$totale_genere = array_sum($genere_data);

// Query per Tipo di Contratto
$query_contratto = "SELECT contratto, COUNT(*) AS totale FROM lavoratori WHERE contratto IS NOT NULL AND contratto != '' GROUP BY contratto";
$result_contratto = $mysqli->query($query_contratto);
$contratto_labels = [];
$contratto_data = [];
while ($row = $result_contratto->fetch_assoc()) {
    $contratto_labels[] = $row['contratto'];
    $contratto_data[] = (int)$row['totale'];
}

// Query per Orario di Contratto
$query_orario_contratto = "SELECT orario_contratto, COUNT(*) AS totale FROM lavoratori WHERE orario_contratto IS NOT NULL AND orario_contratto != '' GROUP BY orario_contratto";
$result_orario_contratto = $mysqli->query($query_orario_contratto);
$orario_contratto_labels = [];
$orario_contratto_data = [];
while ($row = $result_orario_contratto->fetch_assoc()) {
    $orario_contratto_labels[] = $row['orario_contratto'];
    $orario_contratto_data[] = (int)$row['totale'];
}

// Query per CCNL
$query_ccnl = "SELECT ccnl, COUNT(*) AS totale FROM lavoratori WHERE ccnl IS NOT NULL AND ccnl != '' GROUP BY ccnl";
$result_ccnl = $mysqli->query($query_ccnl);
$ccnl_labels = [];
$ccnl_data = [];
while ($row = $result_ccnl->fetch_assoc()) {
    $ccnl_labels[] = $row['ccnl'];
    $ccnl_data[] = (int)$row['totale'];
}

// Calcola il totale per le percentuali nel grafico a torta CCNL
$totale_ccnl = array_sum($ccnl_data);

// Recupera il messaggio personalizzato degli amministratori
$query_messaggio_admin = "SELECT setting_value FROM settings WHERE setting_key = 'messaggio_admin'";
$result_messaggio_admin = $mysqli->query($query_messaggio_admin);
$messaggio_admin = '';
if ($result_messaggio_admin && $row = $result_messaggio_admin->fetch_assoc()) {
    $messaggio_admin = $row['setting_value'];
}

// Gestione della richiesta POST per aggiornare il messaggio degli amministratori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['messaggio_admin'])) {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "Token CSRF mancante o non valido.";
        header("Location: dashboard.php?update_error=" . urlencode($error));
        exit;
    }

    // Recupera e sanitizza il messaggio
    $messaggio_admin_nuovo = $_POST['messaggio_admin'];

    // Utilizza una dichiarazione preparata per prevenire SQL injection
    // Verifica se esiste già una riga con setting_key = 'messaggio_admin'
    $check_query = "SELECT COUNT(*) as count FROM settings WHERE setting_key = 'messaggio_admin'";
    $result = $mysqli->query($check_query);
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        // Esegui un UPDATE se la riga esiste
        $update_query = "UPDATE settings SET setting_value = ? WHERE setting_key = 'messaggio_admin'";
        $stmt = $mysqli->prepare($update_query);
        $stmt->bind_param('s', $messaggio_admin_nuovo);
        $success = $stmt->execute();
    } else {
        // Esegui un INSERT se la riga non esiste
        $insert_query = "INSERT INTO settings (setting_key, setting_value) VALUES ('messaggio_admin', ?)";
        $stmt = $mysqli->prepare($insert_query);
        $stmt->bind_param('s', $messaggio_admin_nuovo);
        $success = $stmt->execute();
    }

    if ($success) {
        // Reindirizza con successo
        header("Location: dashboard.php?update_success=1");
        exit;
    } else {
        $error = "Errore durante l'aggiornamento del messaggio: " . $mysqli->error;
        header("Location: dashboard.php?update_error=" . urlencode($error));
        exit;
    }
}

// Funzione per verificare se l'utente è un amministratore
function isAdmin() {
    return $_SESSION['user_role'] === 'admin';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <!-- Meta viewport per la responsività -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Font Awesome -->
    <link href="<?php echo $base_url; ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <!-- Bootstrap 4.6 CSS -->
    <link href="<?php echo $base_url; ?>theme/vendor/bootstrap/scss/bootstrap.scss" rel="stylesheet">
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo $base_url; ?>theme/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- Custom CSS (se necessario) -->
    <link href="<?php echo $base_url; ?>styles.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="<?php echo $base_url; ?>theme/vendor/chart.js/Chart.min.js"></script>
    <!-- FullCalendar CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <!-- FullCalendar Locale -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js"></script>
    <!-- Moment.js -->
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <!-- TinyMCE -->
    <script src="<?php echo $base_url; ?>vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
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
		.fc .fc-daygrid-event-harness-abs {

    max-width: 100% !important;
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

                    <!-- Messaggio Personalizzato degli Amministratori -->
                    <!-- Gestione dei Messaggi -->
                    <?php if (isset($_GET['update_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Messaggio aggiornato con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['update_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($_GET['update_error']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Messaggio degli Amministratori</span>
                            <?php if (isAdmin()): ?>
                                <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editMessaggioModal">
                                    <i class="fas fa-edit"></i> Modifica Messaggio
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php echo $messaggio_admin; ?>
                        </div>
                    </div>

                    <!-- Modale per Modificare il Messaggio degli Amministratori -->
                    <?php if (isAdmin()): ?>
                    <div class="modal fade" id="editMessaggioModal" tabindex="-1" role="dialog" aria-labelledby="editMessaggioModalLabel" aria-hidden="true">
                      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <form method="POST" action="dashboard.php">
                            <?php csrfInputField(); ?>
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="editMessaggioModalLabel">Modifica Messaggio degli Amministratori</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                              </div>
                              <div class="modal-body">
                                <textarea name="messaggio_admin" id="messaggio_admin_editor"><?php echo htmlspecialchars($messaggio_admin); ?></textarea>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
                                <button type="submit" class="btn btn-primary">Salva</button>
                              </div>
                            </div>
                        </form>
                      </div>
                    </div>
                    <?php endif; ?>

                    <!-- Calendario Generale -->
                    <h1 class="h3 mb-4 text-gray-800">Calendario Generale</h1>
                    <div id="calendar" class="mb-5"></div>

                    <!-- Modale per Visualizzare i Dettagli dell'Evento -->
                    <div class="modal fade" id="eventDetailsModal" tabindex="-1" role="dialog" aria-labelledby="eventDetailsModalLabel" aria-hidden="true">
                      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="eventDetailsModalLabel">Dettagli Evento</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <p><strong>Titolo:</strong> <span id="modalTitle"></span></p>
                            <p><strong>Descrizione:</strong> <span id="modalDescription"></span></p>
                            <p><strong>Data Inizio:</strong> <span id="modalStart"></span></p>
                            <p><strong>Data Fine:</strong> <span id="modalEnd"></span></p>
                            <p><strong>All Day:</strong> <span id="modalAllDay"></span></p>
                            <p id="modalAziendaWrapper"><strong>Azienda:</strong> <a href="#" id="modalAziendaLink"></a></p>
                            <p id="modalLavoratoreWrapper"><strong>Lavoratore:</strong> <a href="#" id="modalLavoratoreLink"></a></p>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Chiudi</button>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Resto della Pagina -->
                    <!-- Titolo della Pagina -->
                    <h1 class="h3 mb-4 text-gray-800">Dashboard</h1>

                    <!-- Contenuto della Pagina -->
                    <div class="row">
                        <!-- Totale Lavoratori -->
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Totale Lavoratori</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totale_lavoratori; ?></div>
                                        </div>
                                        <div class="col-4 text-right">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Totale Iscritti -->
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Lavoratori Iscritti</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totale_iscritti; ?></div>
                                        </div>
                                        <div class="col-4 text-right">
                                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Altri box informativi puoi aggiungerli qui -->
                   </div>

                    <!-- Grafici -->
                    <div class="row">
                        <!-- Distribuzione per Settore -->
                        <div class="col-12 col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header bg-warning text-white">Distribuzione per Settore</div>
                                <div class="card-body">
                                    <canvas id="chartSettore"></canvas>
                                </div>
                            </div>
                        </div>
                        <!-- Distribuzione per Genere -->
                        <div class="col-12 col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header bg-danger text-white">Distribuzione per Genere</div>
                                <div class="card-body">
                                    <canvas id="chartGenere"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Tipo di Contratto -->
                        <div class="col-12 col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header bg-info text-white">Tipo di Contratto</div>
                                <div class="card-body">
                                    <canvas id="chartContratto"></canvas>
                                </div>
                            </div>
                        </div>
                        <!-- Orario di Contratto -->
                        <div class="col-12 col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header bg-secondary text-white">Orario di Contratto</div>
                                <div class="card-body">
                                    <canvas id="chartOrarioContratto"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Distribuzione per CCNL -->
                        <div class="col-12 col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header bg-success text-white">Distribuzione per CCNL</div>
                                <div class="card-body">
                                    <canvas id="chartCCNL"></canvas>
                                </div>
                            </div>
                        </div>
                        <!-- Distribuzione per Città -->
                        <div class="col-12 col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header bg-primary text-white">Distribuzione per Città</div>
                                <div class="card-body">
                                    <canvas id="chartCitta"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Distribuzione per Nazionalità -->
                        <div class="col-12 col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header bg-success text-white">Distribuzione per Nazionalità</div>
                                <div class="card-body">
                                    <canvas id="chartNazionalita"></canvas>
                                </div>
                            </div>
                        </div>
                        <!-- Lavoratori per Azienda -->
                        <div class="col-12 col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header bg-info text-white">Numero di Lavoratori per Azienda</div>
                                <div class="card-body">
                                    <canvas id="chartAziende"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Script per i Grafici -->
                    <script>
                        // Funzione per generare colori casuali
                        function generateRandomColors(num) {
                            var colors = [];
                            for (var i = 0; i < num; i++) {
                                colors.push('hsl(' + Math.floor(Math.random() * 360) + ', 70%, 60%)');
                            }
                            return colors;
                        }

                        // Distribuzione per Settore
                        var ctxSettore = document.getElementById('chartSettore').getContext('2d');
                        var settoreData = <?php echo json_encode($settore_data); ?>;
                        var settoreLabels = <?php echo json_encode($settore_labels); ?>;
                        var totalSettore = settoreData.reduce((a, b) => a + b, 0);

                        var chartSettore = new Chart(ctxSettore, {
                            type: 'pie',
                            data: {
                                labels: settoreLabels,
                                datasets: [{
                                    data: settoreData,
                                    backgroundColor: generateRandomColors(settoreData.length),
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        generateLabels: function(chart) {
                                            var data = chart.data;
                                            if (data.labels.length && data.datasets.length) {
                                                return data.labels.map(function(label, i) {
                                                    var meta = chart.getDatasetMeta(0);
                                                    var style = meta.controller.getStyle(i);

                                                    var value = data.datasets[0].data[i];
                                                    var percentage = ((value / totalSettore) * 100).toFixed(2) + '%';

                                                    return {
                                                        text: label + ' (' + value + ', ' + percentage + ')',
                                                        fillStyle: style.backgroundColor,
                                                        strokeStyle: style.borderColor,
                                                        lineWidth: style.borderWidth,
                                                        hidden: isNaN(data.datasets[0].data[i]) || meta.data[i].hidden,
                                                        index: i
                                                    };
                                                });
                                            } else {
                                                return [];
                                            }
                                        }
                                    }
                                },
                                tooltips: {
                                    callbacks: {
                                        label: function(tooltipItem, data) {
                                            var dataset = data.datasets[tooltipItem.datasetIndex];
                                            var total = dataset.data.reduce(function(previousValue, currentValue) {
                                                return previousValue + currentValue;
                                            }, 0);
                                            var currentValue = dataset.data[tooltipItem.index];
                                            var percentage = ((currentValue / total) * 100).toFixed(2);
                                            return data.labels[tooltipItem.index] + ': ' + currentValue + ' (' + percentage + '%)';
                                        }
                                    }
                                }
                            }
                        });

                        // Distribuzione per Genere
                        var ctxGenere = document.getElementById('chartGenere').getContext('2d');
                        var genereData = <?php echo json_encode($genere_data); ?>;
                        var genereLabels = <?php echo json_encode($genere_labels); ?>;
                        var totalGenere = genereData.reduce((a, b) => a + b, 0);

                        var chartGenere = new Chart(ctxGenere, {
                            type: 'pie',
                            data: {
                                labels: genereLabels,
                                datasets: [{
                                    data: genereData,
                                    backgroundColor: generateRandomColors(genereData.length),
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        generateLabels: function(chart) {
                                            var data = chart.data;
                                            if (data.labels.length && data.datasets.length) {
                                                return data.labels.map(function(label, i) {
                                                    var meta = chart.getDatasetMeta(0);
                                                    var style = meta.controller.getStyle(i);

                                                    var value = data.datasets[0].data[i];
                                                    var percentage = ((value / totalGenere) * 100).toFixed(2) + '%';

                                                    return {
                                                        text: label + ' (' + value + ', ' + percentage + ')',
                                                        fillStyle: style.backgroundColor,
                                                        strokeStyle: style.borderColor,
                                                        lineWidth: style.borderWidth,
                                                        hidden: isNaN(data.datasets[0].data[i]) || meta.data[i].hidden,
                                                        index: i
                                                    };
                                                });
                                            } else {
                                                return [];
                                            }
                                        }
                                    }
                                },
                                tooltips: {
                                    callbacks: {
                                        label: function(tooltipItem, data) {
                                            var dataset = data.datasets[tooltipItem.datasetIndex];
                                            var total = dataset.data.reduce(function(previousValue, currentValue) {
                                                return previousValue + currentValue;
                                            }, 0);
                                            var currentValue = dataset.data[tooltipItem.index];
                                            var percentage = ((currentValue / total) * 100).toFixed(2);
                                            return data.labels[tooltipItem.index] + ': ' + currentValue + ' (' + percentage + '%)';
                                        }
                                    }
                                }
                            }
                        });

                        // Tipo di Contratto
                        var ctxContratto = document.getElementById('chartContratto').getContext('2d');
                        var chartContratto = new Chart(ctxContratto, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode($contratto_labels); ?>,
                                datasets: [{
                                    label: 'Numero di Lavoratori',
                                    data: <?php echo json_encode($contratto_data); ?>,
                                    backgroundColor: '#36b9cc',
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                legend: { display: false },
                                tooltips: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y;
                                        }
                                    }
                                },
                                scales: {
                                    xAxes: [{
                                        ticks: {
                                            beginAtZero: true
                                        },
                                        scaleLabel: {
                                            display: true,
                                            labelString: 'Tipo di Contratto'
                                        }
                                    }],
                                    yAxes: [{
                                        ticks: {
                                            beginAtZero: true,
                                            stepSize: 1,
                                            callback: function(value) {
                                                if (Number.isInteger(value)) {
                                                    return value;
                                                }
                                            }
                                        },
                                        scaleLabel: {
                                            display: true,
                                            labelString: 'Numero di Lavoratori'
                                        }
                                    }]
                                }
                            }
                        });

                        // Orario di Contratto
                        var ctxOrarioContratto = document.getElementById('chartOrarioContratto').getContext('2d');
                        var chartOrarioContratto = new Chart(ctxOrarioContratto, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode($orario_contratto_labels); ?>,
                                datasets: [{
                                    label: 'Numero di Lavoratori',
                                    data: <?php echo json_encode($orario_contratto_data); ?>,
                                    backgroundColor: '#4e73df',
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                legend: { display: false },
                                tooltips: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y;
                                        }
                                    }
                                },
                                scales: {
                                    xAxes: [{
                                        ticks: {
                                            beginAtZero: true
                                        },
                                        scaleLabel: {
                                            display: true,
                                            labelString: 'Orario di Contratto'
                                        }
                                    }],
                                    yAxes: [{
                                        ticks: {
                                            beginAtZero: true,
                                            stepSize: 1,
                                            callback: function(value) {
                                                if (Number.isInteger(value)) {
                                                    return value;
                                                }
                                            }
                                        },
                                        scaleLabel: {
                                            display: true,
                                            labelString: 'Numero di Lavoratori'
                                        }
                                    }]
                                }
                            }
                        });

                        // Distribuzione per CCNL
                        var ctxCCNL = document.getElementById('chartCCNL').getContext('2d');
                        var ccnlData = <?php echo json_encode($ccnl_data); ?>;
                        var ccnlLabels = <?php echo json_encode($ccnl_labels); ?>;
                        var totalCCNL = ccnlData.reduce((a, b) => a + b, 0);

                        var chartCCNL = new Chart(ctxCCNL, {
                            type: 'pie',
                            data: {
                                labels: ccnlLabels,
                                datasets: [{
                                    data: ccnlData,
                                    backgroundColor: generateRandomColors(ccnlData.length),
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        generateLabels: function(chart) {
                                            var data = chart.data;
                                            if (data.labels.length && data.datasets.length) {
                                                return data.labels.map(function(label, i) {
                                                    var meta = chart.getDatasetMeta(0);
                                                    var style = meta.controller.getStyle(i);

                                                    var value = data.datasets[0].data[i];
                                                    var percentage = ((value / totalCCNL) * 100).toFixed(2) + '%';

                                                    return {
                                                        text: label + ' (' + value + ', ' + percentage + ')',
                                                        fillStyle: style.backgroundColor,
                                                        strokeStyle: style.borderColor,
                                                        lineWidth: style.borderWidth,
                                                        hidden: isNaN(data.datasets[0].data[i]) || meta.data[i].hidden,
                                                        index: i
                                                    };
                                                });
                                            } else {
                                                return [];
                                            }
                                        }
                                    }
                                },
                                tooltips: {
                                    callbacks: {
                                        label: function(tooltipItem, data) {
                                            var dataset = data.datasets[tooltipItem.datasetIndex];
                                            var total = dataset.data.reduce(function(previousValue, currentValue) {
                                                return previousValue + currentValue;
                                            }, 0);
                                            var currentValue = dataset.data[tooltipItem.index];
                                            var percentage = ((currentValue / total) * 100).toFixed(2);
                                            return data.labels[tooltipItem.index] + ': ' + currentValue + ' (' + percentage + '%)';
                                        }
                                    }
                                }
                            }
                        });

                        // Distribuzione per Città
                        var ctxCitta = document.getElementById('chartCitta').getContext('2d');
                        var chartCitta = new Chart(ctxCitta, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode($citta_labels); ?>,
                                datasets: [{
                                    label: 'Numero di Lavoratori',
                                    data: <?php echo json_encode($citta_data); ?>,
                                    backgroundColor: '#4e73df',
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                legend: { display: false },
                                tooltips: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y;
                                        }
                                    }
                                },
                                scales: {
                                    xAxes: [{
                                        ticks: {
                                            beginAtZero: true
                                        },
                                        scaleLabel: {
                                            display: true,
                                            labelString: 'Città'
                                        }
                                    }],
                                    yAxes: [{
                                        ticks: {
                                            beginAtZero: true,
                                            stepSize: 1,
                                            callback: function(value) {
                                                if (Number.isInteger(value)) {
                                                    return value;
                                                }
                                            }
                                        },
                                        scaleLabel: {
                                            display: true,
                                            labelString: 'Numero di Lavoratori'
                                        }
                                    }]
                                }
                            }
                        });

                        // Distribuzione per Nazionalità
                        var ctxNazionalita = document.getElementById('chartNazionalita').getContext('2d');
                        var nazionalitaData = <?php echo json_encode($nazionalita_data); ?>;
                        var nazionalitaLabels = <?php echo json_encode($nazionalita_labels); ?>;
                        var totalNazionalita = nazionalitaData.reduce((a, b) => a + b, 0);

                        var chartNazionalita = new Chart(ctxNazionalita, {
                            type: 'pie',
                            data: {
                                labels: nazionalitaLabels,
                                datasets: [{
                                    data: nazionalitaData,
                                    backgroundColor: generateRandomColors(nazionalitaData.length),
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        generateLabels: function(chart) {
                                            var data = chart.data;
                                            if (data.labels.length && data.datasets.length) {
                                                return data.labels.map(function(label, i) {
                                                    var meta = chart.getDatasetMeta(0);
                                                    var style = meta.controller.getStyle(i);

                                                    var value = data.datasets[0].data[i];
                                                    var percentage = ((value / totalNazionalita) * 100).toFixed(2) + '%';

                                                    return {
                                                        text: label + ' (' + value + ', ' + percentage + ')',
                                                        fillStyle: style.backgroundColor,
                                                        strokeStyle: style.borderColor,
                                                        lineWidth: style.borderWidth,
                                                        hidden: isNaN(data.datasets[0].data[i]) || meta.data[i].hidden,
                                                        index: i
                                                    };
                                                });
                                            } else {
                                                return [];
                                            }
                                        }
                                    }
                                },
                                tooltips: {
                                    callbacks: {
                                        label: function(tooltipItem, data) {
                                            var dataset = data.datasets[tooltipItem.datasetIndex];
                                            var total = dataset.data.reduce(function(previousValue, currentValue) {
                                                return previousValue + currentValue;
                                            }, 0);
                                            var currentValue = dataset.data[tooltipItem.index];
                                            var percentage = ((currentValue / total) * 100).toFixed(2);
                                            return data.labels[tooltipItem.index] + ': ' + currentValue + ' (' + percentage + '%)';
                                        }
                                    }
                                }
                            }
                        });

                        // Numero di Lavoratori per Azienda
                        var ctxAziende = document.getElementById('chartAziende').getContext('2d');
                        var chartAziende = new Chart(ctxAziende, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode($aziende_labels); ?>,
                                datasets: [{
                                    label: 'Numero di Lavoratori',
                                    data: <?php echo json_encode($aziende_data); ?>,
                                    backgroundColor: '#36b9cc',
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                legend: { display: false },
                                tooltips: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y;
                                        }
                                    }
                                },
                                scales: {
                                    xAxes: [{
                                        ticks: {
                                            beginAtZero: true
                                        },
                                        scaleLabel: {
                                            display: true,
                                            labelString: 'Azienda'
                                        }
                                    }],
                                    yAxes: [{
                                        ticks: {
                                            beginAtZero: true,
                                            stepSize: 1,
                                            callback: function(value) {
                                                if (Number.isInteger(value)) {
                                                    return value;
                                                }
                                            }
                                        },
                                        scaleLabel: {
                                            display: true,
                                            labelString: 'Numero di Lavoratori'
                                        }
                                    }]
                                }
                            }
                        });
                    </script>
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

    <!-- Script per Bootstrap e altri plugin -->
    <!-- jQuery prima di Bootstrap JS -->
      <!--   <script src="<?php echo $base_url; ?>theme/vendor/jquery/jquery.min.js"></script>-->
    <script src="<?php echo $base_url; ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?php echo $base_url; ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- SB Admin 2 JavaScript-->
    <script src="<?php echo $base_url; ?>theme/js/sb-admin-2.min.js"></script>

    <!-- TinyMCE Initialization -->
    <script>
        // Inizializzazione di TinyMCE per il messaggio degli amministratori
        tinymce.init({
            selector: '#messaggio_admin_editor',
            plugins: 'advlist autolink lists link image charmap preview anchor pagebreak',
            toolbar: 'undo redo | formatselect | bold italic backcolor | ' +
                      'alignleft aligncenter alignright alignjustify | ' +
                      'bullist numlist outdent indent | removeformat | help',
            height: 300,
            menubar: false,
            branding: false,
            entity_encoding: 'raw',
            forced_root_block: 'false',
            toolbar_mode: 'floating',
            base_url: '<?php echo $base_url; ?>vendor/tinymce',
            suffix: '.min',
            license_key: 'gpl',
        });
    </script>

    <!-- Script per FullCalendar e Gestione dei Modali -->
    <script>
        // Inizializzazione del Calendario
        $(document).ready(function() {
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
                events: {
                    url: 'fetch_events_dashboard.php',
                    method: 'POST',
                    extraParams: {
                        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                    },
                    failure: function() {
                        alert('Errore nel caricamento degli eventi!');
                    }
                },
                eventClick: function(info) {
                    // Popola il modale con i dettagli dell'evento
                    $('#modalTitle').text(info.event.title);
                    $('#modalDescription').html(info.event.extendedProps.description || '');
                    $('#modalStart').text(moment(info.event.start).format('DD/MM/YYYY HH:mm'));
                    $('#modalEnd').text(info.event.end ? moment(info.event.end).format('DD/MM/YYYY HH:mm') : 'Non specificata');
                    $('#modalAllDay').text(info.event.allDay ? 'Sì' : 'No');

                    if (info.event.extendedProps.is_company_event == 1) {
                        $('#modalAziendaWrapper').show();
                        $('#modalAziendaLink').text(info.event.extendedProps.nome_azienda);
                        $('#modalAziendaLink').attr('href', 'azienda.php?id=' + info.event.extendedProps.azienda_id);
                        $('#modalLavoratoreWrapper').hide();
                    } else {
                        $('#modalLavoratoreWrapper').show();
                        $('#modalLavoratoreLink').text(info.event.extendedProps.nome_lavoratore);
                        $('#modalLavoratoreLink').attr('href', 'lavoratore.php?id=' + info.event.extendedProps.lavoratore_id);
                        $('#modalAziendaWrapper').hide();
                    }

                    // Mostra il modale utilizzando jQuery
                    $('#eventDetailsModal').modal('show');
                }
            });

            calendar.render();
        });
    </script>

</body>
</html>
