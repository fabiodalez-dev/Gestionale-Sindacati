<?php
// aziende_emiliaromagna.php - Lista aziende (consultazione) dal database "adlcobas_emiliaromagna"
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Includi il file di configurazione indipendente (config_emiliaromagna.php)
require_once 'config_emiliaromagna.php';

// Creazione della connessione
$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die("Connessione al database fallita: " . $mysqli->connect_error);
}

// Definizione delle funzioni utili, se non già presenti
if (!function_exists('checkLogin')) {
    function checkLogin() {
        // In modalità consultazione non forziamo il login
    }
}
if (!function_exists('sanitizeForHTML')) {
    function sanitizeForHTML($str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('executeQuery')) {
    function executeQuery($query, $params = [], $types = '') {
        global $mysqli;
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            return false;
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    }
}
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken() {
        // Non necessario in modalità consultazione
    }
}

checkLogin();

// Funzione per rilevare se la richiesta è AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
$isAjax = isAjaxRequest();

// Recupera i parametri per la ricerca
$search_nome      = isset($_GET['search_nome']) ? trim($_GET['search_nome']) : '';
$search_indirizzo = isset($_GET['search_indirizzo']) ? trim($_GET['search_indirizzo']) : '';
$search_citta     = isset($_GET['search_citta']) ? trim($_GET['search_citta']) : '';

// Impaginazione
$recordsPerPage = 30;
$page           = (isset($_GET['page']) && intval($_GET['page']) > 0) ? intval($_GET['page']) : 1;
$offset         = ($page - 1) * $recordsPerPage;

// Costruzione della query con eventuali filtri
$query = "SELECT a.id, a.nome_azienda, a.indirizzo_via, a.indirizzo_numero_civico, a.indirizzo_cap,
                 a.indirizzo_citta, a.indirizzo_provincia, a.telefono, a.email,
                 COUNT(l.id) AS numero_lavoratori
          FROM aziende a
          LEFT JOIN lavoratori l ON a.id = l.azienda_id AND l.archiviato <> 1
          WHERE 1=1";
$params = [];
$types  = '';

if ($search_nome !== '') {
    $query   .= " AND a.nome_azienda LIKE CONCAT('%', ?, '%')";
    $params[] = $search_nome;
    $types  .= 's';
}
if ($search_indirizzo !== '') {
    $query   .= " AND a.indirizzo_via LIKE CONCAT('%', ?, '%')";
    $params[] = $search_indirizzo;
    $types  .= 's';
}
if ($search_citta !== '') {
    $query   .= " AND a.indirizzo_citta LIKE CONCAT('%', ?, '%')";
    $params[] = $search_citta;
    $types  .= 's';
}
$query .= " GROUP BY a.id ORDER BY a.nome_azienda ASC LIMIT ? OFFSET ?";
$params[] = $recordsPerPage;
$types  .= 'i';
$params[] = $offset;
$types  .= 'i';

$stmt = executeQuery($query, $params, $types);
if ($stmt === false) {
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(["error" => "Errore nell'esecuzione della query principale."]);
        exit;
    } else {
        die("Errore nell'esecuzione della query principale.");
    }
}
$aziende = $stmt->get_result();

// Query per il conteggio totale (impaginazione)
$countQuery  = "SELECT COUNT(*) as total FROM aziende a WHERE 1=1";
$countParams = [];
$countTypes  = '';
if ($search_nome !== '') {
    $countQuery   .= " AND a.nome_azienda LIKE CONCAT('%', ?, '%')";
    $countParams[] = $search_nome;
    $countTypes  .= 's';
}
if ($search_indirizzo !== '') {
    $countQuery   .= " AND a.indirizzo_via LIKE CONCAT('%', ?, '%')";
    $countParams[] = $search_indirizzo;
    $countTypes  .= 's';
}
if ($search_citta !== '') {
    $countQuery   .= " AND a.indirizzo_citta LIKE CONCAT('%', ?, '%')";
    $countParams[] = $search_citta;
    $countTypes  .= 's';
}
$countStmt = executeQuery($countQuery, $countParams, $countTypes);
$filteredCount = 0;
if ($countStmt !== false) {
    $countResult = $countStmt->get_result();
    $countRow    = $countResult->fetch_assoc();
    $filteredCount = intval($countRow['total']);
}
$totalPages = ceil($filteredCount / $recordsPerPage);

generateCsrfToken();

// Se la richiesta è AJAX restituisce i dati in JSON
if ($isAjax) {
    ob_start();
    if ($aziende->num_rows > 0) {
        while ($row = $aziende->fetch_assoc()) {
            ?>
            <tr>
                <td>
                    <a href="azienda_emiliaromagna.php?id=<?php echo sanitizeForHTML($row['id']); ?>">
                        <?php echo sanitizeForHTML($row['nome_azienda']); ?>
                    </a>
                </td>
                <td>
                    <?php
                    $indirizzo = [
                        $row['indirizzo_via'] ?? '',
                        $row['indirizzo_numero_civico'] ?? '',
                        $row['indirizzo_cap'] ?? '',
                        $row['indirizzo_citta'] ?? '',
                        $row['indirizzo_provincia'] ?? ''
                    ];
                    echo sanitizeForHTML(implode(', ', array_filter($indirizzo, function($v) { return !empty($v); })));
                    ?>
                </td>
                <td>
                    <?php echo !empty($row['telefono']) ? '<a href="tel:' . sanitizeForHTML($row['telefono']) . '">' . sanitizeForHTML($row['telefono']) . '</a>' : '-'; ?>
                </td>
                <td>
                    <?php echo !empty($row['email']) ? '<a href="mailto:' . sanitizeForHTML($row['email']) . '">' . sanitizeForHTML($row['email']) . '</a>' : '-'; ?>
                </td>
                <td><?php echo intval($row['numero_lavoratori']); ?></td>
            </tr>
            <?php
        }
    } else {
        ?>
        <tr><td colspan="5" class="text-center">Nessuna azienda trovata.</td></tr>
        <?php
    }
    $tableRows = ob_get_clean();

    ob_start();
    if ($totalPages > 1) {
        ?>
        <nav aria-label="Navigazione paginazione">
            <ul class="pagination justify-content-center">
                <?php
                $range = 4;
                $start = max(1, $page - $range);
                $end   = min($totalPages, $page + $range);
                if (($end - $start + 1) < 10) {
                    if ($start == 1) {
                        $end = min($start + 9, $totalPages);
                    } elseif ($end == $totalPages) {
                        $start = max(1, $end - 9);
                    }
                }
                if ($page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="#" data-page="'.($page-1).'" aria-label="Precedente">&laquo;</a></li>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    $active = ($i == $page) ? 'active' : '';
                    echo '<li class="page-item ' . $active . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
                }
                if ($page < $totalPages) {
                    echo '<li class="page-item"><a class="page-link" href="#" data-page="'.($page+1).'" aria-label="Successivo">&raquo;</a></li>';
                }
                ?>
            </ul>
        </nav>
        <?php
    }
    $paginationControls = ob_get_clean();

    echo json_encode([
        'tableRows'          => $tableRows,
        'paginationControls' => $paginationControls,
        'filteredCount'      => $filteredCount,
        'totalCount'         => $filteredCount
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Lista Aziende - Consultazione Emilia Romagna</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css" rel="stylesheet">
    <style>
        @media (max-width: 767.98px) { .desktop-table { display: none; } }
        @media (min-width: 768px) { .mobile-cards { display: none; } }
        #aziendeTableBody, #aziendeList { min-height: 200px; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/topbar.php'; ?>
                <div class="container-fluid">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-4">
                        <h1 class="h3 mb-4 text-gray-800">Lista Aziende - Consultazione Emilia Romagna</h1>
                        <div class="mt-2">
                            <!-- In modalità consultazione non è previsto il pulsante Aggiungi -->
                            <button id="exportCsvBtn" class="btn btn-outline-secondary">Esporta CSV</button>
                        </div>
                    </div>

                    <!-- Eventuali messaggi (se presenti) -->
                    <!-- ... eventuale codice per messaggi ... -->

                    <!-- Form di Ricerca -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">Ricerca Aziende</div>
                        <div class="card-body">
                            <form id="filterForm" class="row g-3">
                                <div class="col-md-4">
                                    <label for="search_nome" class="form-label">Nome Azienda</label>
                                    <input type="text" name="search_nome" id="search_nome" class="form-control" placeholder="Cerca per nome" value="<?php echo sanitizeForHTML($search_nome); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="search_indirizzo" class="form-label">Indirizzo</label>
                                    <input type="text" name="search_indirizzo" id="search_indirizzo" class="form-control" placeholder="Cerca per indirizzo" value="<?php echo sanitizeForHTML($search_indirizzo); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="search_citta" class="form-label">Città</label>
                                    <input type="text" name="search_citta" id="search_citta" class="form-control" placeholder="Cerca per città" value="<?php echo sanitizeForHTML($search_citta); ?>">
                                </div>
                                <div class="col-12 d-flex">
                                    <button type="submit" class="btn btn-primary me-2">Cerca</button>
                                    <button type="button" id="resetBtn" class="btn btn-secondary me-2">Reset</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Vista Desktop -->
                    <div class="desktop-table">
                        <div class="card">
                            <div class="card-header bg-primary text-white">Lista Aziende</div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-primary">
                                            <tr>
                                                <th>Nome Azienda</th>
                                                <th>Indirizzo</th>
                                                <th>Telefono</th>
                                                <th>Email</th>
                                                <th>Numero di Lavoratori</th>
                                            </tr>
                                        </thead>
                                        <tbody id="aziendeTableBody">
                                            <!-- Dati caricati via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vista Mobile -->
                    <div id="aziendeList" class="mobile-cards">
                        <!-- Dati caricati via AJAX -->
                    </div>

                    <!-- Impaginazione -->
                    <div id="paginationControls"></div>

                </div>
                <!-- End of Page Content -->
            </div>
            <!-- End of Main Content -->
            <?php include __DIR__ . '/footer.php'; ?>
        </div>
    </div>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sei sicuro di voler uscire?</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">Sei pronto a terminare la tua sessione corrente?</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Annulla</button>
                    <a class="btn btn-primary" href="<?php echo sanitizeForHTML($base_url); ?>logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll to Top Button -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- jQuery, Bootstrap e SweetAlert2 -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function(){
        function updateList(page = 1) {
            var data = {
                search_nome: $('#search_nome').val(),
                search_indirizzo: $('#search_indirizzo').val(),
                search_citta: $('#search_citta').val(),
                page: page
            };
            for (var key in data) {
                if (data[key] === '' || data[key] === null) {
                    delete data[key];
                }
            }
            $.ajax({
                url: 'aziende_emiliaromagna.php',
                type: 'GET',
                dataType: 'json',
                data: data,
                success: function(response){
                    if(response.error){
                        Swal.fire('Errore!', response.error, 'error');
                        return;
                    }
                    $('#aziendeTableBody').html(response.tableRows);
                    $('#paginationControls').html(response.paginationControls);
                    var newUrl = window.location.pathname;
                    if (Object.keys(data).length > 0) {
                        newUrl += '?' + $.param(data);
                    }
                    window.history.replaceState({path: newUrl}, '', newUrl);
                },
                error: function(){
                    Swal.fire('Errore!', 'Si è verificato un errore durante il filtraggio delle aziende.', 'error');
                }
            });
        }
        function initializeAutocomplete() {
            $("#search_nome").autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: "autocomplete_aziende.php",
                        type: "GET",
                        dataType: "json",
                        data: { term: request.term },
                        success: function(data) { response(data); },
                        error: function() { response([]); }
                    });
                },
                minLength: 2,
                delay: 300
            });
        }
        $('#filterForm').on('submit', function(e){
            e.preventDefault();
            updateList();
        });
        $('#resetBtn').on('click', function(){
            $('#filterForm')[0].reset();
            var newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
            updateList();
        });
        $(document).on('click', '.pagination a.page-link', function(e){
            e.preventDefault();
            var page = $(this).data('page');
            if (page) { updateList(page); }
        });
        $('#exportCsvBtn').on('click', function(){
            var query = $.param({
                search_nome: $('#search_nome').val(),
                search_indirizzo: $('#search_indirizzo').val(),
                search_citta: $('#search_citta').val(),
                export_csv: 1
            });
            window.location.href = 'export_aziende.php?' + query;
        });
        initializeAutocomplete();
        updateList();
    });
    </script>
</body>
</html>
