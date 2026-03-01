<?php
require 'config.php';
checkLogin();

$mysqli->set_charset("utf8mb4");

// Recupera il messaggio personalizzato degli amministratori
$query_messaggio_admin = "SELECT setting_value FROM settings WHERE setting_key = 'messaggio_admin'";
$result_messaggio_admin = $mysqli->query($query_messaggio_admin);
$messaggio_admin = '';
if ($result_messaggio_admin && $row = $result_messaggio_admin->fetch_assoc()) {
    $messaggio_admin = $row['setting_value'];
}

// Gestione della richiesta POST per aggiornare il messaggio degli amministratori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['messaggio_admin'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "Token CSRF mancante o non valido.";
        header("Location: dashboard.php?update_error=" . urlencode($error));
        exit;
    }
    $messaggio_admin_nuovo = $_POST['messaggio_admin'];
    $check_query = "SELECT COUNT(*) as count FROM settings WHERE setting_key = 'messaggio_admin'";
    $result = $mysqli->query($check_query);
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        $update_query = "UPDATE settings SET setting_value = ? WHERE setting_key = 'messaggio_admin'";
        $stmt = $mysqli->prepare($update_query);
        $stmt->bind_param('s', $messaggio_admin_nuovo);
        $success = $stmt->execute();
    } else {
        $insert_query = "INSERT INTO settings (setting_key, setting_value) VALUES ('messaggio_admin', ?)";
        $stmt = $mysqli->prepare($insert_query);
        $stmt->bind_param('s', $messaggio_admin_nuovo);
        $success = $stmt->execute();
    }
    if ($success) {
        header("Location: dashboard.php?update_success=1");
        exit;
    } else {
        $error = "Errore durante l'aggiornamento del messaggio: " . $mysqli->error;
        header("Location: dashboard.php?update_error=" . urlencode($error));
        exit;
    }
}

function isAdmin() {
    return $_SESSION['user_role'] === 'admin';
}

// Recupera sedi per il filtro
$sedi = [];
$r_sedi = $mysqli->query("SELECT id, nome FROM sedi ORDER BY nome ASC");
if ($r_sedi) while ($row = $r_sedi->fetch_assoc()) $sedi[] = $row;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo $base_url; ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo $base_url; ?>theme/css/sb-admin-2.min.css?v=2.5" rel="stylesheet">
    <link href="<?php echo $base_url; ?>styles.css?v=2.5" rel="stylesheet">
    <!-- FullCalendar CSS (local) -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>theme/vendor/fullcalendar/common.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>theme/vendor/fullcalendar/daygrid.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>theme/vendor/fullcalendar/timegrid.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>theme/vendor/fullcalendar/list.min.css">
    <!-- Chart.js v4 (local) -->
    <script src="<?php echo $base_url; ?>theme/vendor/chartjs4/chart.umd.js"></script>
    <script src="<?php echo $base_url; ?>theme/vendor/chartjs4/chartjs-plugin-datalabels.min.js"></script>
    <!-- FullCalendar JS (local) -->
    <script src="<?php echo $base_url; ?>theme/vendor/fullcalendar/core.global.min.js"></script>
    <script src="<?php echo $base_url; ?>theme/vendor/fullcalendar/daygrid.global.min.js"></script>
    <script src="<?php echo $base_url; ?>theme/vendor/fullcalendar/timegrid.global.min.js"></script>
    <script src="<?php echo $base_url; ?>theme/vendor/fullcalendar/list.global.min.js"></script>
    <script src="<?php echo $base_url; ?>theme/vendor/fullcalendar/interaction.global.min.js"></script>
    <script src="<?php echo $base_url; ?>theme/vendor/fullcalendar/locales-all.global.min.js"></script>
    <script src="<?php echo $base_url; ?>theme/vendor/fullcalendar/moment.min.js"></script>
    <!-- TinyMCE -->
    <script src="<?php echo $base_url; ?>vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        #calendar { width: 100%; min-height: 600px; }
        .fc { font-size: 0.9rem; }
        .fc-event { cursor: pointer; }
        .fc .fc-daygrid-event-harness-abs { max-width: 100% !important; }
        .fc .fc-toolbar { flex-wrap: wrap; gap: 0.5rem; }
        .fc .fc-toolbar-title { font-size: 1.25rem !important; font-weight: 600; }
        .fc .fc-button { font-size: 0.8rem; padding: 0.35em 0.65em; border-radius: 0.4rem; }
        .fc .fc-button-primary { background-color: #1e293b; border-color: #1e293b; }
        .fc .fc-button-primary:hover { background-color: #334155; border-color: #334155; }
        .fc .fc-button-primary:not(:disabled).fc-button-active { background-color: #000; border-color: #000; }
        .fc .fc-daygrid-day-number { font-weight: 500; padding: 6px 8px; }
        .fc .fc-col-header-cell-cushion { font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.03em; }
        .chart-container { position: relative; height: 350px; }
        .chart-container-lg { position: relative; height: 400px; }
        .chart-container-xl { position: relative; height: 500px; }
        /* stat-card styles now in styles.css */
        .filter-bar { background: #fff; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 1rem 1.25rem; }
        .chart-card { border: none; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .chart-card .card-header { background: #fff; border-bottom: 1px solid #f0f0f0; font-weight: 600; font-size: 0.9rem; padding: 1rem 1.25rem; border-radius: 0.75rem 0.75rem 0 0 !important; }
        .chart-card .card-body { padding: 1rem 1.25rem; }
        .badge-filter { cursor: pointer; padding: 0.4em 0.8em; font-size: 0.8rem; border-radius: 2rem; transition: all 0.15s ease; }
        .badge-filter:hover { opacity: 0.85; }
        .badge-filter.active { box-shadow: 0 0 0 2px #000; }
    </style>
</head>
<body id="page-top">
<div id="wrapper">
    <?php include 'sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <?php include 'topbar.php'; ?>
            <div class="container-fluid">

                <!-- Alerts -->
                <?php if (isset($_GET['update_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Messaggio aggiornato con successo.
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['update_error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo sanitizeForHTML($_GET['update_error']); ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <!-- Messaggio Admin -->
                <div class="card mb-4" style="border:none; border-radius:0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background:#fff; border-radius:0.75rem 0.75rem 0 0; border-bottom:1px solid #f0f0f0;">
                        <span style="font-weight:600;">Messaggio degli Amministratori</span>
                        <?php if (isAdmin()): ?>
                            <button class="btn btn-sm btn-outline-dark" data-toggle="modal" data-target="#editMessaggioModal">
                                <i class="fas fa-edit"></i> Modifica
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body"><?php echo $messaggio_admin; ?></div>
                </div>

                <!-- Modal Messaggio Admin -->
                <?php if (isAdmin()): ?>
                <div class="modal fade" id="editMessaggioModal" tabindex="-1">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <form method="POST" action="dashboard.php">
                        <?php csrfInputField(); ?>
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">Modifica Messaggio</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
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

                <!-- Calendario -->
                <div class="card mb-5" style="border:none; border-radius:0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                    <div class="card-header" style="background:#fff; border-radius:0.75rem 0.75rem 0 0; border-bottom:1px solid #f0f0f0;">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center" style="gap: 0.5rem;">
                        <span style="font-weight:600; font-size: 1.1rem;"><i class="fas fa-calendar-alt mr-2"></i>Calendario Generale</span>
                        <div class="d-flex align-items-center calendar-ics-section" style="flex-wrap: wrap; gap: 0.5rem;">
                            <div class="input-group input-group-sm" style="max-width: 420px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" style="background:#f8f9fa; border-color:#dee2e6; font-size:0.75rem;">
                                        <i class="fas fa-link mr-1"></i> ICS
                                    </span>
                                </div>
                                <?php
                                    $ics_absolute_url = null;
                                    $configuredHost = parse_url($base_url, PHP_URL_HOST);
                                    $configuredScheme = parse_url($base_url, PHP_URL_SCHEME);
                                    $configuredPort = parse_url($base_url, PHP_URL_PORT);
                                    $ics_scheme = $configuredScheme ?: ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
                                    $ics_host = $configuredHost ?: ($_SERVER['SERVER_NAME'] ?? 'localhost');
                                    if ($configuredPort) { $ics_host .= ':' . $configuredPort; }
                                    $calendarToken = getSetting('calendar_token');
                                    if (empty($calendarToken)) {
                                        $generatedToken = bin2hex(random_bytes(16));
                                        if (setSetting('calendar_token', $generatedToken)) {
                                            $calendarToken = $generatedToken;
                                        } else {
                                            error_log("Impossibile salvare calendar_token nelle impostazioni.");
                                            $calendarToken = null;
                                        }
                                    }
                                    if ($calendarToken !== null) {
                                        $configuredPath = parse_url($base_url, PHP_URL_PATH);
                                        $basePath = ($configuredPath !== null && $configuredPath !== false) ? $configuredPath : '/';
                                        $basePath = '/' . trim($basePath, '/');
                                        if ($basePath === '/') { $basePath = ''; }
                                        $ics_absolute_url = $ics_scheme . '://' . $ics_host . $basePath . '/calendar_feed.php?days=365&token=' . urlencode($calendarToken);
                                    }
                                ?>
                                <?php if (!empty($ics_absolute_url)): ?>
                                <input type="text" id="icsUrlInput" class="form-control form-control-sm"
                                       value="<?php echo sanitizeForHTML($ics_absolute_url); ?>"
                                       readonly style="font-size:0.75rem; background:#f8f9fa; cursor:text;">
                                <div class="input-group-append">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="copyIcsBtn" title="Copia URL ICS">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <a href="<?php echo sanitizeForHTML($ics_absolute_url); ?>"
                               class="btn btn-sm btn-outline-secondary" download="calendar.ics" title="Scarica file ICS">
                                <i class="fas fa-download"></i>
                            </a>
                                <?php else: ?>
                                <span class="form-control form-control-sm text-muted" style="font-size:0.75rem; background:#f8f9fa;">Feed ICS non disponibile</span>
                                </div>
                                <?php endif; ?>
                        </div>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div id="calendar"></div>
                    </div>
                </div>

                <!-- Modal Evento -->
                <div class="modal fade" id="eventDetailsModal" tabindex="-1">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Dettagli Evento</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
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

                <!-- ═══ DASHBOARD ANALYTICS ═══ -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <h1 class="h3 mb-0 text-gray-800">Dashboard Analitica</h1>
                </div>

                <!-- Filtri globali -->
                <div class="filter-bar mb-4 d-flex flex-wrap align-items-center gap-3 dashboard-filters">
                    <div class="d-flex align-items-center mr-md-3">
                        <label class="mb-0 mr-2 font-weight-bold" style="font-size:0.85rem; white-space:nowrap;">
                            <i class="fas fa-filter"></i> Sede:
                        </label>
                        <select id="filterSede" class="form-control form-control-sm" style="max-width:200px; border-radius:0.5rem;">
                            <option value="0">Tutte le sedi</option>
                            <?php foreach ($sedi as $s): ?>
                                <option value="<?php echo intval($s['id']); ?>"><?php echo sanitizeForHTML($s['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex align-items-center mr-md-3">
                        <label class="mb-0 mr-2 font-weight-bold" style="font-size:0.85rem; white-space:nowrap;">Iscrizione:</label>
                        <select id="filterIscritto" class="form-control form-control-sm" style="max-width:160px; border-radius:0.5rem;">
                            <option value="">Tutti</option>
                            <option value="1">Solo Iscritti</option>
                            <option value="0">Solo Non Iscritti</option>
                        </select>
                    </div>
                    <button id="btnApplyFilter" class="btn btn-sm btn-primary">Applica Filtri</button>
                    <button id="btnResetFilter" class="btn btn-sm btn-outline-secondary">Reset</button>
                    <span id="filterStatus" class="text-muted" style="font-size:0.8rem;"></span>
                </div>

                <!-- KPI Cards -->
                <div class="row mb-4" id="kpiRow">
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="card stat-card stat-card--dark shadow-sm h-100">
                            <div class="card-body py-3 px-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="stat-icon-wrap mr-3">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-label mb-0">Totale Lavoratori</div>
                                </div>
                                <div class="stat-number" id="kpiTotal">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="card stat-card stat-card--green shadow-sm h-100">
                            <div class="card-body py-3 px-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="stat-icon-wrap mr-3">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div class="stat-label mb-0">Iscritti</div>
                                </div>
                                <div class="stat-number stat-number--green" id="kpiIscritti">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="card stat-card stat-card--red shadow-sm h-100">
                            <div class="card-body py-3 px-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="stat-icon-wrap mr-3">
                                        <i class="fas fa-user-times"></i>
                                    </div>
                                    <div class="stat-label mb-0">Non Iscritti</div>
                                </div>
                                <div class="stat-number stat-number--red" id="kpiNonIscritti">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="card stat-card stat-card--blue shadow-sm h-100">
                            <div class="card-body py-3 px-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="stat-icon-wrap mr-3">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="stat-label mb-0">Tasso Iscrizione</div>
                                </div>
                                <div class="stat-number stat-number--blue" id="kpiRate">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 1: Genere + Settore -->
                <div class="row mb-4">
                    <div class="col-12 col-lg-4 mb-4 mb-lg-0">
                        <div class="card chart-card h-100">
                            <div class="card-header">Distribuzione per Genere</div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="chartGenere"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-8">
                        <div class="card chart-card h-100">
                            <div class="card-header">Distribuzione per Settore</div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="chartSettore"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Contratto + Orario -->
                <div class="row mb-4">
                    <div class="col-12 col-lg-6 mb-4 mb-lg-0">
                        <div class="card chart-card h-100">
                            <div class="card-header">Tipo di Contratto</div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="chartContratto"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card chart-card h-100">
                            <div class="card-header">Orario di Contratto</div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="chartOrario"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 3: CCNL (full width) -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card chart-card">
                            <div class="card-header">Distribuzione per CCNL</div>
                            <div class="card-body">
                                <div class="chart-container-lg"><canvas id="chartCCNL"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Città + Nazionalità -->
                <div class="row mb-4">
                    <div class="col-12 col-lg-6 mb-4 mb-lg-0">
                        <div class="card chart-card h-100">
                            <div class="card-header">Top 20 Città</div>
                            <div class="card-body">
                                <div class="chart-container-xl"><canvas id="chartCitta"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card chart-card h-100">
                            <div class="card-header">Top 15 Nazionalità</div>
                            <div class="card-body">
                                <div class="chart-container-xl"><canvas id="chartNazionalita"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 5: Aziende (full width) -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card chart-card">
                            <div class="card-header">Top 20 Aziende per Numero di Lavoratori</div>
                            <div class="card-body">
                                <div class="chart-container-xl"><canvas id="chartAziende"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 6: Trend iscrizioni -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card chart-card">
                            <div class="card-header">Trend Iscrizioni (ultimi 12 mesi)</div>
                            <div class="card-body">
                                <div class="chart-container"><canvas id="chartTrend"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include 'footer.php'; ?>
    </div>
</div>

<a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

<script src="<?php echo $base_url; ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_url; ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="<?php echo $base_url; ?>theme/js/sb-admin-2.min.js"></script>

<!-- TinyMCE Init -->
<script>
tinymce.init({
    selector: '#messaggio_admin_editor',
    plugins: 'advlist autolink lists link image charmap preview anchor pagebreak',
    toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
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

<!-- FullCalendar Init -->
<script>
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
            extraParams: { csrf_token: '<?php echo $_SESSION['csrf_token']; ?>' },
            failure: function() { alert('Errore nel caricamento degli eventi!'); }
        },
        eventClick: function(info) {
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
            $('#eventDetailsModal').modal('show');
        }
    });
    calendar.render();

    // ── ICS URL copy button ──
    $('#copyIcsBtn').on('click', function() {
        var icsInput = document.getElementById('icsUrlInput');
        icsInput.select();
        icsInput.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(icsInput.value).then(function() {
            var btn = $('#copyIcsBtn');
            btn.html('<i class="fas fa-check"></i>');
            btn.removeClass('btn-outline-secondary').addClass('btn-success');
            setTimeout(function() {
                btn.html('<i class="fas fa-copy"></i>');
                btn.removeClass('btn-success').addClass('btn-outline-secondary');
            }, 2000);
        }).catch(function() {
            document.execCommand('copy');
        });
    });
});
</script>

<!-- ═══ CHART.JS v4 DASHBOARD ═══ -->
<script>
(function() {
    'use strict';

    // ── Color palette (curated, not random) ──
    var PALETTE = [
        '#1e293b', '#3b82f6', '#22c55e', '#f59e0b', '#ef4444',
        '#8b5cf6', '#06b6d4', '#ec4899', '#f97316', '#14b8a6',
        '#6366f1', '#84cc16', '#d946ef', '#0ea5e9', '#a855f7',
        '#10b981', '#e11d48', '#eab308', '#7c3aed', '#0891b2',
        '#be185d', '#65a30d', '#c026d3', '#0284c7', '#7e22ce'
    ];

    function getColors(n) {
        var out = [];
        for (var i = 0; i < n; i++) out.push(PALETTE[i % PALETTE.length]);
        return out;
    }

    // ── Chart instances ──
    var charts = {};

    // ── Default chart options ──
    var defaultFont = { family: "'Inter', -apple-system, sans-serif", size: 12, weight: '500' };

    Chart.defaults.font = defaultFont;
    Chart.defaults.color = '#374151';
    Chart.defaults.plugins.legend.display = false;

    // ── Create/Update charts from API data ──
    function buildCharts(data) {
        // KPI
        $('#kpiTotal').text(data.totale_lavoratori.toLocaleString('it-IT'));
        $('#kpiIscritti').text(data.totale_iscritti.toLocaleString('it-IT'));
        $('#kpiNonIscritti').text(data.totale_non_iscritti.toLocaleString('it-IT'));
        var rate = data.totale_lavoratori > 0 ? ((data.totale_iscritti / data.totale_lavoratori) * 100).toFixed(1) : '0';
        $('#kpiRate').text(rate + '%');

        // Destroy existing charts
        Object.keys(charts).forEach(function(k) { if (charts[k]) charts[k].destroy(); });

        // ── Genere (Doughnut) ──
        charts.genere = new Chart(document.getElementById('chartGenere'), {
            type: 'doughnut',
            data: {
                labels: data.genere.map(function(d) { return d.label; }),
                datasets: [{
                    data: data.genere.map(function(d) { return parseInt(d.value); }),
                    backgroundColor: getColors(data.genere.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { padding: 15, usePointStyle: true, pointStyle: 'circle', font: { size: 12, weight: '500' } } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                                var pct = ((ctx.parsed / total) * 100).toFixed(1);
                                return ctx.label + ': ' + ctx.parsed.toLocaleString('it-IT') + ' (' + pct + '%)';
                            }
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold', size: 13 },
                        formatter: function(val, ctx) {
                            var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                            var pct = ((val/total)*100).toFixed(0);
                            return pct > 5 ? pct+'%' : '';
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // ── Settore (Horizontal bar) ──
        charts.settore = createHBar('chartSettore', data.settore, '#3b82f6');

        // ── Contratto (Horizontal bar) ──
        charts.contratto = createHBar('chartContratto', data.contratto, '#06b6d4');

        // ── Orario (Doughnut) ──
        charts.orario = new Chart(document.getElementById('chartOrario'), {
            type: 'doughnut',
            data: {
                labels: data.orario.map(function(d) { return d.label; }),
                datasets: [{
                    data: data.orario.map(function(d) { return parseInt(d.value); }),
                    backgroundColor: ['#1e293b', '#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6'],
                    borderWidth: 2, borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { padding: 15, usePointStyle: true, pointStyle: 'circle', font: { size: 12, weight: '500' } } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                                var pct = ((ctx.parsed / total) * 100).toFixed(1);
                                return ctx.label + ': ' + ctx.parsed.toLocaleString('it-IT') + ' (' + pct + '%)';
                            }
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold', size: 13 },
                        formatter: function(val, ctx) {
                            var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                            var pct = ((val/total)*100).toFixed(0);
                            return pct > 5 ? pct+'%' : '';
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // ── CCNL (Horizontal bar - full width) ──
        charts.ccnl = createHBar('chartCCNL', data.ccnl, '#8b5cf6');

        // ── Città (Horizontal bar) ──
        charts.citta = createHBar('chartCitta', data.citta, '#22c55e');

        // ── Nazionalità (Horizontal bar) ──
        charts.nazionalita = createHBar('chartNazionalita', data.nazionalita, '#f59e0b');

        // ── Aziende (Horizontal bar) ──
        charts.aziende = createHBar('chartAziende', data.aziende, '#1e293b');

        // ── Trend iscrizioni (Line chart) ──
        var trendLabels = data.iscrizioni_trend.map(function(d) {
            var parts = d.label.split('-');
            var months = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];
            return months[parseInt(parts[1])-1] + ' ' + parts[0].slice(2);
        });
        charts.trend = new Chart(document.getElementById('chartTrend'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Nuove Iscrizioni',
                    data: data.iscrizioni_trend.map(function(d) { return parseInt(d.value); }),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(ctx) { return ctx.parsed.y + ' iscrizioni'; } } },
                    datalabels: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, callback: function(v) { return Number.isInteger(v) ? v : ''; } }, grid: { color: 'rgba(0,0,0,0.04)' } },
                    x: { grid: { display: false } }
                }
            },
            plugins: [ChartDataLabels]
        });
    }

    // ── Helper: horizontal bar chart ──
    function createHBar(canvasId, rawData, color) {
        var labels = rawData.map(function(d) { return d.label; });
        var values = rawData.map(function(d) { return parseInt(d.value); });
        var maxVal = Math.max.apply(null, values) || 1;

        return new Chart(document.getElementById(canvasId), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: color + 'cc',
                    borderColor: color,
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.85
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                                var pct = ((ctx.parsed.x / total) * 100).toFixed(1);
                                return ctx.parsed.x.toLocaleString('it-IT') + ' (' + pct + '%)';
                            }
                        }
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'right',
                        color: '#374151',
                        font: { weight: '600', size: 11 },
                        formatter: function(val) { return val > 0 ? val.toLocaleString('it-IT') : ''; },
                        clamp: true,
                        clip: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { callback: function(v) { return Number.isInteger(v) ? v : ''; } },
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        suggestedMax: maxVal * 1.15
                    },
                    y: {
                        ticks: {
                            font: { size: 11, weight: '500' },
                            callback: function(val, idx) {
                                var lbl = this.getLabelForValue(val);
                                return lbl.length > 30 ? lbl.substring(0,28) + '…' : lbl;
                            }
                        },
                        grid: { display: false }
                    }
                },
                layout: { padding: { right: 40 } }
            },
            plugins: [ChartDataLabels]
        });
    }

    // ── Fetch and render ──
    function loadDashboard() {
        var sede = $('#filterSede').val() || 0;
        var iscritto = $('#filterIscritto').val() || '';
        $('#filterStatus').text('Caricamento...');

        $.ajax({
            url: 'fetch_dashboard_data.php',
            data: { sede_id: sede, iscritto: iscritto },
            dataType: 'json',
            success: function(data) {
                buildCharts(data);
                var txt = 'Dati aggiornati';
                if (sede > 0) {
                    var sedeNome = $('#filterSede option:selected').text();
                    txt += ' — Sede: ' + sedeNome;
                }
                if (iscritto === '1') txt += ' — Solo iscritti';
                else if (iscritto === '0') txt += ' — Solo non iscritti';
                $('#filterStatus').text(txt);
            },
            error: function() {
                $('#filterStatus').text('Errore nel caricamento dei dati.');
            }
        });
    }

    // ── Event bindings ──
    $(document).ready(function() {
        loadDashboard();

        $('#btnApplyFilter').on('click', function() { loadDashboard(); });
        $('#btnResetFilter').on('click', function() {
            $('#filterSede').val('0');
            $('#filterIscritto').val('');
            loadDashboard();
        });

        // Also allow Enter key in selects
        $('#filterSede, #filterIscritto').on('change', function() { loadDashboard(); });
    });
})();
</script>
</body>
</html>
