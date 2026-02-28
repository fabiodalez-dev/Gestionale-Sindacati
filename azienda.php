<?php
//azienda.php
require 'config.php';
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
    $query = "SELECT COUNT(*) as numero_lavoratori FROM lavoratori WHERE azienda_id = ? AND archiviato = 0";
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
    $query = "SELECT id, nome, cognome FROM lavoratori WHERE azienda_id = ? AND archiviato = 0";
    $stmt = executeQuery($query, [$azienda_id], 'i');
    if ($stmt === false) {
        echo "Errore nella query dei lavoratori.";
        exit;
    }
    $lavoratori = $stmt->get_result();

    // **SEZIONE: Recupera dati per i grafici delle sedi**
    $query_sedi = "
        SELECT 
            s.nome as sede_nome,
            s.citta as sede_citta,
            s.provincia as sede_provincia,
            COUNT(l.id) as numero_lavoratori
        FROM lavoratori l
        LEFT JOIN sedi s ON l.sede_id = s.id
        WHERE l.azienda_id = ? AND l.archiviato = 0
        GROUP BY l.sede_id, s.nome, s.citta, s.provincia
        ORDER BY numero_lavoratori DESC
    ";
    
    $stmt_sedi = executeQuery($query_sedi, [$azienda_id], 'i');
    $sedi_data = [];
    $lavoratori_senza_sede = 0;
    
    if ($stmt_sedi !== false) {
        $result_sedi = $stmt_sedi->get_result();
        while ($row = $result_sedi->fetch_assoc()) {
            if (empty($row['sede_nome'])) {
                $lavoratori_senza_sede = $row['numero_lavoratori'];
            } else {
                $sedi_data[] = $row;
            }
        }
    }

    // Prepara i dati per i grafici JavaScript
    $sedi_labels = [];
    $sedi_counts = [];
    
    foreach ($sedi_data as $index => $sede) {
        $label = $sede['sede_nome'];
        if (!empty($sede['sede_citta'])) {
            $label .= ' (' . $sede['sede_citta'] . ')';
        }
        $sedi_labels[] = $label;
        $sedi_counts[] = intval($sede['numero_lavoratori']);
    }
    
    // Aggiungi "Senza sede" se ci sono lavoratori senza sede assegnata
    if ($lavoratori_senza_sede > 0) {
        $sedi_labels[] = 'Senza sede assegnata';
        $sedi_counts[] = $lavoratori_senza_sede;
    }

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
    $note = $_POST['note'];
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
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.5" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.css">
    <!-- Custom CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.5" rel="stylesheet">
    <!-- FullCalendar CSS -->
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/common.min.css">
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/daygrid.min.css">
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/timegrid.min.css">
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/list.min.css">
    <!-- SweetAlert2 -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/moment.min.js"></script>
    <style>
        #calendar { max-width: 100%; margin: 0 auto; }
        .fc-event { cursor: pointer; overflow: hidden !important; text-overflow: ellipsis; white-space: nowrap; padding: 2px 6px !important; border-radius: 4px; font-size: 0.8rem; }
        .fc-daygrid-event-harness { padding-bottom: 20px; }
        canvas { width: 100% !important; height: auto !important; }
        .chart-container { position: relative; height: 300px; margin-bottom: 2rem; }
        .chart-small { height: 250px; }

        .stats-card {
            background: var(--slate-900, #0f172a);
            color: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(15,23,42,0.15);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(15,23,42,0.2);
        }
        .stats-number { font-size: 2.2rem; font-weight: 700; margin-bottom: 0.25rem; letter-spacing: -0.02em; }
        .stats-label { font-size: 0.85rem; opacity: 0.7; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }
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

                    <!-- Grafici Distribuzione Sedi -->
                    <?php if (!empty($sedi_data) || $lavoratori_senza_sede > 0): ?>
                    <div class="card mt-4 mb-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-map-marker-alt mr-2"></i>Distribuzione Lavoratori per Sede
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Statistiche generali -->
                                <div class="col-lg-3 col-md-6 mb-4">
                                    <div class="stats-card">
                                        <div class="stats-number"><?php echo count($sedi_data) + ($lavoratori_senza_sede > 0 ? 1 : 0); ?></div>
                                        <div class="stats-label">Sedi Totali</div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-4">
                                    <div class="stats-card">
                                        <div class="stats-number"><?php echo $numero_lavoratori; ?></div>
                                        <div class="stats-label">Lavoratori Totali</div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-4">
                                    <div class="stats-card">
                                        <div class="stats-number"><?php echo count(array_unique(array_column($sedi_data, 'sede_provincia'))); ?></div>
                                        <div class="stats-label">Province</div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-4">
                                    <div class="stats-card">
                                        <div class="stats-number"><?php echo $lavoratori_senza_sede; ?></div>
                                        <div class="stats-label">Senza Sede</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Grafico a Torta - Distribuzione per Sede -->
                                <div class="col-lg-6 col-md-12 mb-4">
                                    <h6 class="font-weight-bold text-gray-800 mb-3">Distribuzione per Sede</h6>
                                    <div class="chart-container">
                                        <canvas id="sediPieChart"></canvas>
                                    </div>
                                </div>

                                <!-- Grafico a Barre - Lavoratori per Sede -->
                                <div class="col-lg-6 col-md-12 mb-4">
                                    <h6 class="font-weight-bold text-gray-800 mb-3">Lavoratori per Sede</h6>
                                    <div class="chart-container">
                                        <canvas id="sediBarChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabella dettagliata -->
                            <div class="row">
                                <div class="col-12">
                                    <h6 class="font-weight-bold text-gray-800 mb-3">Dettaglio Sedi</h6>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Sede</th>
                                                    <th>Città</th>
                                                    <th>Provincia</th>
                                                    <th>N. Lavoratori</th>
                                                    <th>Percentuale</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($sedi_data as $sede): ?>
                                                <tr>
                                                    <td><?php echo sanitizeForHTML($sede['sede_nome']); ?></td>
                                                    <td><?php echo sanitizeForHTML($sede['sede_citta'] ?: 'N/A'); ?></td>
                                                    <td><?php echo sanitizeForHTML($sede['sede_provincia'] ?: 'N/A'); ?></td>
                                                    <td>
                                                        <span class="badge badge-primary"><?php echo $sede['numero_lavoratori']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $percentuale = $numero_lavoratori > 0 ? round(($sede['numero_lavoratori'] / $numero_lavoratori) * 100, 1) : 0;
                                                        echo $percentuale . '%';
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if ($lavoratori_senza_sede > 0): ?>
                                                <tr>
                                                    <td><em>Senza sede assegnata</em></td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>
                                                        <span class="badge badge-warning"><?php echo $lavoratori_senza_sede; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $percentuale = $numero_lavoratori > 0 ? round(($lavoratori_senza_sede / $numero_lavoratori) * 100, 1) : 0;
                                                        echo $percentuale . '%';
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Informazioni Azienda -->
<div class="card mt-4">
  <div class="card-header">
    <i class="fas fa-building"></i> Informazioni Azienda
  </div>
  <div class="card-body">
    <h4 class="card-title">Dettagli Azienda</h4>
    
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Nome Azienda:</strong>
        <p><?php echo sanitizeForHTML($azienda['nome_azienda'] ?? ''); ?></p>
      </div>
      <div class="col-12 col-md-6 mb-2">
        <strong>Partita IVA:</strong>
        <p><?php echo sanitizeForHTML($azienda['partita_iva'] ?? ''); ?></p>
      </div>
    </div>
    <hr class="my-2">
    
    <div class="row">
      <div class="col-12 col-md-6 mb-2">
        <strong>Numero di Lavoratori:</strong>
        <p><?php echo sanitizeForHTML($numero_lavoratori); ?></p>
      </div>
    </div>
    <hr class="my-2">
    
    <!-- Riga indirizzo -->
    <div class="row">
      <div class="col-12 col-md-4 mb-2">
        <strong>Indirizzo (Via):</strong>
        <p><?php echo sanitizeForHTML($azienda['indirizzo_via'] ?? ''); ?></p>
      </div>
      <div class="col-12 col-md-4 mb-2">
        <strong>N. Civico:</strong>
        <p><?php echo sanitizeForHTML($azienda['indirizzo_numero_civico'] ?? ''); ?></p>
      </div>
      <div class="col-12 col-md-4 mb-2">
        <strong>CAP:</strong>
        <p><?php echo sanitizeForHTML($azienda['indirizzo_cap'] ?? ''); ?></p>
      </div>
    </div>
    <hr class="my-2">
    
    <div class="row">
      <div class="col-12 col-md-4 mb-2">
        <strong>Città:</strong>
        <p><?php echo sanitizeForHTML($azienda['indirizzo_citta'] ?? ''); ?></p>
      </div>
      <div class="col-12 col-md-4 mb-2">
        <strong>Provincia:</strong>
        <p><?php echo sanitizeForHTML($azienda['indirizzo_provincia'] ?? ''); ?></p>
      </div>
      <div class="col-12 col-md-4 mb-2">
        <strong>Settore:</strong>
        <p><?php echo sanitizeForHTML($azienda['settore'] ?? ''); ?></p>
      </div>
    </div>
    <hr class="my-2">
    
    <!-- Nuova riga per Telefono, Email e PEC -->
    <div class="row">
      <div class="col-12 col-md-4 mb-2">
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
      <div class="col-12 col-md-4 mb-2">
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
      <div class="col-12 col-md-4 mb-2">
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
    
    <div class="mt-4">
      <a href="edit_azienda.php?id=<?php echo sanitizeForHTML($azienda_id); ?>" class="btn btn-primary">
        <i class="fas fa-edit"></i> Modifica Azienda
      </a>
    </div>
  </div>
</div>

                    <!-- Sezione Unità Operative -->
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Unità Operative</span>
                            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#aggiungiUnitaOperativaModal">
                                <i class="fas fa-plus"></i> Aggiungi Unità Operativa
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($unita_operativa)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Nome Unità Operativa</th>
                                                <th>Descrizione</th>
                                                <th>Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($unita_operativa as $unit): ?>
                                                <tr>
                                                    <td><?php echo sanitizeForHTML($unit['nome_unita_operativa']); ?></td>
                                                    <td><?php echo sanitizeForHTML($unit['descrizione']); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning edit-unita-operativa-btn" data-toggle="modal" data-target="#modificaUnitaOperativaModal" data-id="<?php echo sanitizeForHTML($unit['id']); ?>" data-nome="<?php echo sanitizeForHTML($unit['nome_unita_operativa']); ?>" data-descrizione="<?php echo sanitizeForHTML($unit['descrizione']); ?>">
                                                            <i class="fas fa-edit"></i> Modifica
                                                        </button>
                                                        <button class="btn btn-sm btn-danger delete-unita-operativa-btn" data-id="<?php echo sanitizeForHTML($unit['id']); ?>" data-nome="<?php echo sanitizeForHTML($unit['nome_unita_operativa']); ?>">
                                                            <i class="fas fa-trash"></i> Elimina
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>Nessuna unità operativa associata a questa azienda.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Modale per Aggiungere Unità Operativa -->
                    <div class="modal fade" id="aggiungiUnitaOperativaModal" tabindex="-1" role="dialog" aria-labelledby="aggiungiUnitaOperativaModalLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <form action="aggiungi_unita_operativa.php" method="POST">
                            <!-- CSRF Token -->
                            <?php csrfInputField(); ?>
                            <input type="hidden" name="azienda_id" value="<?php echo sanitizeForHTML($azienda_id); ?>">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="aggiungiUnitaOperativaModalLabel">Aggiungi Unità Operativa</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                              </div>
                              <div class="modal-body">
                                <div class="form-group">
                                    <label for="nome_unita_operativa">Nome Unità Operativa <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nome_unita_operativa" name="nome_unita_operativa" required>
                                </div>
                                <div class="form-group">
                                    <label for="descrizione_unita_operativa">Descrizione</label>
                                    <textarea class="form-control" id="descrizione_unita_operativa" name="descrizione_unita_operativa" rows="3"></textarea>
                                </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
                                <button type="submit" class="btn btn-success">Aggiungi</button>
                              </div>
                            </div>
                        </form>
                      </div>
                    </div>

                    <!-- Modale per Modificare Unità Operativa -->
                    <div class="modal fade" id="modificaUnitaOperativaModal" tabindex="-1" role="dialog" aria-labelledby="modificaUnitaOperativaModalLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <form action="modifica_unita_operativa.php" method="POST">
                            <!-- CSRF Token -->
                            <?php csrfInputField(); ?>
                            <input type="hidden" name="unita_operativa_id" id="edit_unita_operativa_id">
                            <input type="hidden" name="azienda_id" value="<?php echo sanitizeForHTML($azienda_id); ?>">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="modificaUnitaOperativaModalLabel">Modifica Unità Operativa</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                              </div>
                              <div class="modal-body">
                                <div class="form-group">
                                    <label for="edit_nome_unita_operativa">Nome Unità Operativa <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_nome_unita_operativa" name="nome_unita_operativa" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_descrizione_unita_operativa">Descrizione</label>
                                    <textarea class="form-control" id="edit_descrizione_unita_operativa" name="descrizione_unita_operativa" rows="3"></textarea>
                                </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
                                <button type="submit" class="btn btn-warning">Aggiorna</button>
                              </div>
                            </div>
                        </form>
                      </div>
                    </div>

                    <!-- Form per Modificare le Note -->
                    <div class="card mt-4">
                        <div class="card-header">
                            Modifica Note
                        </div>
                        <div class="card-body">
                            <form method="POST" action="azienda.php?id=<?php echo sanitizeForHTML($azienda_id); ?>">
                                <!-- Token CSRF -->
                                <?php csrfInputField(); ?>
                                <div class="mb-3">
                                    <label for="note" class="form-label">Note</label>
                                    <textarea name="note" id="note" class="form-control" rows="5"><?php echo sanitizeHTML($azienda['note'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-success">Salva Note</button>
                            </form>
                        </div>
                    </div>

                    <!-- Sezione Caricamento Documenti -->
                    <h3 class="mt-5">Carica Documenti</h3>
                    <div class="card mb-4">
                        <div class="card-header">
                            Carica Nuovo Documento
                        </div>
                        <div class="card-body">
                            <form action="upload_documento_azienda.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="azienda_id" value="<?php echo sanitizeForHTML($azienda_id); ?>">
                                <!-- Token CSRF -->
                                <?php csrfInputField(); ?>
                                <div class="mb-3">
                                    <label for="descrizione_documento" class="form-label">Descrizione</label>
                                    <input type="text" class="form-control" id="descrizione_documento" name="descrizione_documento" required>
                                </div>
                                <div class="mb-3">
                                    <label for="documento" class="form-label">Seleziona File</label>
                                    <input type="file" class="form-control" id="documento" name="documento" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Carica Documento</button>
                            </form>
                        </div>
                    </div>

                    <!-- Lista dei Documenti Caricati -->
                    <!-- Lista dei Documenti Caricati -->
<h3 class="mt-5">Documenti Caricati</h3>
<?php if ($documenti->num_rows > 0): ?>
    <div class="card">
        <div class="card-header">
            Lista Documenti
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
							<th>Anteprima</th>
                            <th>Descrizione</th>
                            <th>Data Caricamento</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($doc = $documenti->fetch_assoc()): ?>
                            <tr>
								<td>
  <?php
    $file_path = $base_url . 'uploads/' . $doc['percorso_documento'];
    $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
      echo '<img src="' . sanitizeForHTML($file_path) . '" alt="Anteprima Immagine" class="preview-img" style="max-width:100px;">';
    } elseif ($file_ext == 'pdf') {
      echo '<embed src="' . sanitizeForHTML($file_path) . '" type="application/pdf" class="preview-pdf" style="max-width:100px;">';
    } else {
      echo 'N/A';
    }
  ?>
</td>

                                <td id="desc-<?php echo sanitizeForHTML($doc['id']); ?>">
                                    <span id="desc-text-<?php echo sanitizeForHTML($doc['id']); ?>">
                                        <?php echo sanitizeForHTML($doc['descrizione_documento'] ?? ''); ?>
                                    </span>
                                    <br>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="editDescription(<?php echo sanitizeForHTML($doc['id']); ?>)">Modifica</button>
                                </td>
                                <td><?php echo sanitizeForHTML(date('d/m/Y H:i', strtotime($doc['data_caricamento'] ?? ''))); ?></td>
                                <td>
                                    <a href="<?php echo sanitizeForHTML($base_url . 'uploads/' . $doc['percorso_documento']); ?>" target="_blank" class="btn btn-sm btn-primary">Visualizza</a>
                                    <form action="delete_documento_azienda.php" method="POST" style="display:inline;" onsubmit="return confirm('Sei sicuro di voler eliminare questo documento?');">
                                        <?php csrfInputField(); ?>
                                        <input type="hidden" name="id" value="<?php echo sanitizeForHTML($doc['id'] ?? 0); ?>">
                                        <input type="hidden" name="azienda_id" value="<?php echo sanitizeForHTML($azienda_id); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Elimina</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <p>Nessun documento caricato.</p>
<?php endif; ?>


                    <!-- Pulsante per Creare un Evento Aziendale -->
                    <button type="button" class="btn btn-success mb-4" data-toggle="modal" data-target="#createEventModal">
                        <i class="fas fa-plus"></i> Crea Evento Aziendale
                    </button>

                    <!-- Modale per Creare un Nuovo Evento Aziendale -->
                    <div class="modal fade" id="createEventModal" tabindex="-1" role="dialog" aria-labelledby="createEventModalLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <form id="createEventForm">
                          <!-- CSRF Token -->
                          <?php csrfInputField(); ?>
                          <div class="modal-content">
                            <div class="modal-header">
                              <h5 class="modal-title" id="createEventModalLabel">Crea Nuovo Evento Aziendale</h5>
                              <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                              </button>
                            </div>
                            <div class="modal-body">
                              <!-- Altri campi -->
                              <div class="form-group">
                                <label for="eventTitle">Titolo Evento</label>
                                <input type="text" class="form-control" id="eventTitle" name="titolo" required>
                              </div>

                              <div class="form-group">
                                <label for="eventDescription">Descrizione</label>
                                <textarea class="form-control tinymce-editor" id="eventDescription" name="descrizione" rows="3"></textarea>
                              </div>

                              <div class="form-group">
                                <label for="eventStart">Data e Ora Inizio</label>
                                <input type="datetime-local" class="form-control" id="eventStart" name="start" required>
                              </div>

                              <div class="form-group">
                                <label for="eventEnd">Data e Ora Fine</label>
                                <input type="datetime-local" class="form-control" id="eventEnd" name="end">
                              </div>

                              <!-- Selettore dei Lavoratori -->
                              <div class="form-group">
                                  <label for="eventWorker">Lavoratore</label>
                                  <select class="form-control" id="eventWorker" name="worker_id">
                                      <option value="">Aziendale</option>
                                      <?php while ($lavoratore = $lavoratori->fetch_assoc()): ?>
                                          <option value="<?php echo sanitizeForHTML($lavoratore['id']); ?>">
                                              <?php echo sanitizeForHTML($lavoratore['nome'] . ' ' . $lavoratore['cognome']); ?>
                                          </option>
                                      <?php endwhile; ?>
                                  </select>
                              </div>

                              <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="" id="allDay" name="all_day">
                                <label class="form-check-label" for="allDay">
                                  Evento per l'intera giornata
                                </label>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Chiudi</button>
                              <button type="submit" class="btn btn-primary">Salva Evento</button>
                            </div>
                          </div>
                        </form>
                      </div>
                    </div>


                    <!-- Calendario Aziendale -->
                    <h3 class="mt-5">Calendario Aziendale</h3>
                    <div id="calendar" class="mb-5"></div>

                    <!-- Modale per Modificare ed Eliminare Eventi (opzionale) -->
                    <div class="modal fade" id="eventModal" tabindex="-1" role="dialog" aria-labelledby="eventModalLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <form id="eventForm">
                            <!-- CSRF Token -->
                            <?php csrfInputField(); ?>
                            <div class="modal-header">
                              <h5 class="modal-title" id="eventModalLabel">Modifica Evento</h5>
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
                                <textarea class="form-control tinymce-editor" id="description" name="description"></textarea>
                              </div>
                              <div class="form-group">
                                <label for="start">Data Inizio</label>
                                <input type="datetime-local" class="form-control" id="start" name="start" required>
                              </div>
                              <div class="form-group">
                                <label for="end">Data Fine</label>
                                <input type="datetime-local" class="form-control" id="end" name="end">
                              </div>

                              <!-- Selettore dei Lavoratori -->
                              <div class="form-group">
                                  <label for="eventWorkerEdit">Lavoratore</label>
                                  <select class="form-control" id="eventWorkerEdit" name="worker_id">
                                      <option value="">Aziendale</option>
                                      <?php
                                      // Ripristina il puntatore del risultato della query
                                      $lavoratori->data_seek(0);
                                      while ($lavoratore = $lavoratori->fetch_assoc()): ?>
                                          <option value="<?php echo sanitizeForHTML($lavoratore['id']); ?>">
                                              <?php echo sanitizeForHTML($lavoratore['nome'] . ' ' . $lavoratore['cognome']); ?>
                                          </option>
                                      <?php endwhile; ?>
                                  </select>
                              </div>

                              <div class="form-group">
                                <label>
                                  <input type="checkbox" id="allDay" name="allDay"> Evento per l'intera giornata
                                </label>
                              </div>
                              <!-- Checkbox per Evento Aziendale (se necessario) -->
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" id="is_company_event" name="is_company_event" disabled> Questo è un evento aziendale
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

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Modali e script -->
    <!-- Bootstrap core JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- SB Admin 2 JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>

    <!-- jQuery UI -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.js"></script>
    <!-- TinyMCE -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
    <!-- SweetAlert2 -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.js"></script>
    <!-- FullCalendar JS -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/core.global.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/daygrid.global.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/timegrid.global.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/list.global.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/interaction.global.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fullcalendar/locales-all.global.min.js"></script>
    <!-- Chart.js -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/chartjs4/chart.umd.js"></script>

    <!-- **Script per i grafici delle sedi con colore del progetto** -->
    <script>
        // Dati per i grafici delle sedi
        const sediLabels = <?php echo json_encode($sedi_labels); ?>;
        const sediCounts = <?php echo json_encode($sedi_counts); ?>;
        
        // Colori design system (slate/blue palette)
        const projectColor = '#0f172a';
        const colors = [
            '#0f172a', '#1e293b', '#334155', '#475569',
            '#3b82f6', '#2563eb', '#1d4ed8', '#60a5fa',
            '#64748b', '#94a3b8'
        ];

        // Grafico a Torta - Distribuzione per Sede
        if (sediLabels.length > 0) {
            const sediPieCtx = document.getElementById('sediPieChart').getContext('2d');
            const sediPieChart = new Chart(sediPieCtx, {
                type: 'doughnut',
                data: {
                    labels: sediLabels,
                    datasets: [{
                        data: sediCounts,
                        backgroundColor: colors.slice(0, sediLabels.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // Grafico a Barre - Lavoratori per Sede
            const sediBarCtx = document.getElementById('sediBarChart').getContext('2d');
            const sediBarChart = new Chart(sediBarCtx, {
                type: 'bar',
                data: {
                    labels: sediLabels,
                    datasets: [{
                        label: 'Numero Lavoratori',
                        data: sediCounts,
                        backgroundColor: projectColor,
                        borderColor: projectColor,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0
                            }
                        }
                    }
                }
            });
        }
    </script>

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
                // Invia richiesta POST per eliminazione
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'elimina_unita_operativa.php';
                var csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
                form.appendChild(csrfInput);
                var idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                form.appendChild(idInput);
                var azInput = document.createElement('input');
                azInput.type = 'hidden';
                azInput.name = 'azienda_id';
                azInput.value = '<?php echo (int)$azienda_id; ?>';
                form.appendChild(azInput);
                document.body.appendChild(form);
                form.submit();
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

  // Costruisce il form via DOM API per evitare XSS
  descCell.textContent = '';
  var form = document.createElement('form');
  form.action = 'update_document_description_azienda.php';
  form.method = 'POST';
  form.onsubmit = function(e) { return updateDescription(e, docId); };

  var csrfInput = document.createElement('input');
  csrfInput.type = 'hidden';
  csrfInput.name = 'csrf_token';
  csrfInput.value = <?php echo json_encode($_SESSION['csrf_token']); ?>;
  form.appendChild(csrfInput);

  var docIdInput = document.createElement('input');
  docIdInput.type = 'hidden';
  docIdInput.name = 'doc_id';
  docIdInput.value = docId;
  form.appendChild(docIdInput);

  var descInput = document.createElement('input');
  descInput.type = 'text';
  descInput.name = 'description';
  descInput.value = currentDesc;
  descInput.required = true;
  form.appendChild(descInput);

  var saveBtn = document.createElement('button');
  saveBtn.type = 'submit';
  saveBtn.className = 'btn btn-sm btn-success';
  saveBtn.textContent = 'Salva';
  form.appendChild(saveBtn);

  var cancelBtn = document.createElement('button');
  cancelBtn.type = 'button';
  cancelBtn.className = 'btn btn-sm btn-warning';
  cancelBtn.textContent = 'Annulla';
  cancelBtn.onclick = function() { cancelEdit(docId, currentDesc); };
  form.appendChild(cancelBtn);

  descCell.appendChild(form);
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
      descCell.innerHTML = '';
      var span = document.createElement('span');
      span.id = 'desc-text-' + docId;
      span.textContent = data.new_description;
      descCell.appendChild(span);
      descCell.appendChild(document.createElement('br'));
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm btn-secondary';
      btn.onclick = function() { editDescription(docId); };
      btn.textContent = 'Modifica';
      descCell.appendChild(btn);
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
  descCell.innerHTML = '';
  var span = document.createElement('span');
  span.id = 'desc-text-' + docId;
  span.textContent = originalDesc;
  descCell.appendChild(span);
  descCell.appendChild(document.createElement('br'));
  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'btn btn-sm btn-secondary';
  btn.textContent = 'Modifica';
  btn.onclick = function () { editDescription(docId); };
  descCell.appendChild(btn);
}
</script>


</body>
</html>