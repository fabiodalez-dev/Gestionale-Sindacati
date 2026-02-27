<?php
/**
 * aziende_remote.php - Lista aziende da un CRM remoto tramite API
 *
 * Parametri GET:
 *   conn_id - ID della connessione API da utilizzare
 *   search_nome, search_indirizzo, search_citta - filtri di ricerca
 *   page - numero pagina
 */
require_once 'config.php';
checkLogin();

$conn_id = intval($_GET['conn_id'] ?? 0);
if ($conn_id <= 0) {
    die("Connessione non specificata.");
}

// Recupera i dati della connessione
$stmt = executeQuery("SELECT * FROM api_connections WHERE id = ? AND is_active = 1", [$conn_id], 'i');
if (!$stmt) {
    die("Errore nel recupero della connessione.");
}
$conn = $stmt->get_result()->fetch_assoc();
if (!$conn) {
    die("Connessione non trovata o disattivata.");
}

$conn_name = $conn['name'];
$endpoint_url = rtrim($conn['endpoint_url'], '/');
$api_key = $conn['api_key'];

// Funzione per chiamare l'API remota
function callRemoteApi($endpoint_url, $api_key, $params = []) {
    $url = $endpoint_url . '?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'X-API-Key: ' . $api_key,
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => 'Errore di connessione: ' . $curlError];
    }
    if ($httpCode !== 200) {
        return ['error' => "Errore HTTP $httpCode"];
    }
    $data = json_decode($response, true);
    if (!$data) {
        return ['error' => 'Risposta non valida dal server'];
    }
    return $data;
}

// Parametri di ricerca
$search_nome      = trim($_GET['search_nome'] ?? '');
$search_indirizzo = trim($_GET['search_indirizzo'] ?? '');
$search_citta     = trim($_GET['search_citta'] ?? '');
$page             = max(1, intval($_GET['page'] ?? 1));

// Rileva richiesta AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Chiama l'API
$apiParams = ['action' => 'aziende', 'page' => $page, 'per_page' => 30];
if ($search_nome !== '') $apiParams['search_nome'] = $search_nome;
if ($search_indirizzo !== '') $apiParams['search_indirizzo'] = $search_indirizzo;
if ($search_citta !== '') $apiParams['search_citta'] = $search_citta;

$apiResponse = callRemoteApi($endpoint_url, $api_key, $apiParams);

$api_error = null;
if (isset($apiResponse['error'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $apiResponse['error']]);
        exit;
    }
    $api_error = $apiResponse['error'];
    $aziende = [];
    $total = 0;
    $totalPages = 0;
} else {
    $aziende = $apiResponse['data'] ?? [];
    $total = $apiResponse['total'] ?? 0;
    $totalPages = $apiResponse['total_pages'] ?? 0;
}

// Risposta AJAX
if ($isAjax) {
    header('Content-Type: application/json');

    ob_start();
    if (!empty($aziende)) {
        foreach ($aziende as $row) {
            $indirizzo = array_filter([
                $row['indirizzo_via'] ?? '',
                $row['indirizzo_numero_civico'] ?? '',
                $row['indirizzo_cap'] ?? '',
                $row['indirizzo_citta'] ?? '',
                $row['indirizzo_provincia'] ?? ''
            ], function($v) { return !empty($v); });
            ?>
            <tr>
                <td><a href="azienda_remote.php?conn_id=<?php echo $conn_id; ?>&id=<?php echo intval($row['id']); ?>"><?php echo sanitizeForHTML($row['nome_azienda'] ?? ''); ?></a></td>
                <td><?php echo sanitizeForHTML(implode(', ', $indirizzo)); ?></td>
                <td><?php echo !empty($row['telefono']) ? '<a href="tel:' . sanitizeForHTML($row['telefono']) . '">' . sanitizeForHTML($row['telefono']) . '</a>' : '-'; ?></td>
                <td><?php echo !empty($row['email']) ? '<a href="mailto:' . sanitizeForHTML($row['email']) . '">' . sanitizeForHTML($row['email']) . '</a>' : '-'; ?></td>
                <td><?php echo intval($row['numero_lavoratori'] ?? 0); ?></td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="5" class="text-center">Nessuna azienda trovata.</td></tr>';
    }
    $tableRows = ob_get_clean();

    ob_start();
    if ($totalPages > 1) {
        $range = 4;
        $start = max(1, $page - $range);
        $end   = min($totalPages, $page + $range);
        echo '<nav><ul class="pagination justify-content-center">';
        if ($page > 1) echo '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page-1) . '">&laquo;</a></li>';
        for ($i = $start; $i <= $end; $i++) {
            $active = $i == $page ? 'active' : '';
            echo '<li class="page-item ' . $active . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
        }
        if ($page < $totalPages) echo '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page+1) . '">&raquo;</a></li>';
        echo '</ul></nav>';
    }
    $paginationControls = ob_get_clean();

    echo json_encode([
        'tableRows'          => $tableRows,
        'paginationControls' => $paginationControls,
        'filteredCount'      => $total,
        'totalCount'         => $total
    ]);
    exit;
}

generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Aziende - <?php echo sanitizeForHTML($conn_name); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.5" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.5" rel="stylesheet">
    <style>
        @media (max-width: 767.98px) { .desktop-table { display: none; } }
        @media (min-width: 768px) { .mobile-cards { display: none; } }
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
                        <h1 class="h3 mb-4 text-gray-800">
                            <i class="fas fa-globe"></i> Aziende - <?php echo sanitizeForHTML($conn_name); ?>
                        </h1>
                    </div>

                    <?php if ($api_error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo sanitizeForHTML($api_error); ?>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header">Ricerca Aziende</div>
                        <div class="card-body">
                            <form id="filterForm" class="row g-3">
                                <div class="col-md-4">
                                    <label for="search_nome">Nome Azienda</label>
                                    <input type="text" name="search_nome" id="search_nome" class="form-control" placeholder="Cerca per nome" value="<?php echo sanitizeForHTML($search_nome); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="search_indirizzo">Indirizzo</label>
                                    <input type="text" name="search_indirizzo" id="search_indirizzo" class="form-control" placeholder="Cerca per indirizzo" value="<?php echo sanitizeForHTML($search_indirizzo); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="search_citta">Città</label>
                                    <input type="text" name="search_citta" id="search_citta" class="form-control" placeholder="Cerca per città" value="<?php echo sanitizeForHTML($search_citta); ?>">
                                </div>
                                <div class="col-12 d-flex">
                                    <button type="submit" class="btn btn-primary me-2">Cerca</button>
                                    <button type="button" id="resetBtn" class="btn btn-secondary me-2">Reset</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="desktop-table">
                        <div class="card">
                            <div class="card-header">Lista Aziende</div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nome Azienda</th>
                                                <th>Indirizzo</th>
                                                <th>Telefono</th>
                                                <th>Email</th>
                                                <th>N. Lavoratori</th>
                                            </tr>
                                        </thead>
                                        <tbody id="aziendeTableBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="paginationControls"></div>
                </div>
            </div>
            <?php include __DIR__ . '/footer.php'; ?>
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.js"></script>
    <script>
    $(document).ready(function(){
        var connId = <?php echo $conn_id; ?>;

        function updateList(page) {
            page = page || 1;
            var data = {
                conn_id: connId,
                search_nome: $('#search_nome').val(),
                search_indirizzo: $('#search_indirizzo').val(),
                search_citta: $('#search_citta').val(),
                page: page
            };
            for (var key in data) {
                if (data[key] === '' || data[key] === null) delete data[key];
            }
            data.conn_id = connId;
            $.ajax({
                url: 'aziende_remote.php',
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
                },
                error: function(){
                    Swal.fire('Errore!', 'Errore nella comunicazione.', 'error');
                }
            });
        }

        $('#filterForm').on('submit', function(e){ e.preventDefault(); updateList(); });
        $('#resetBtn').on('click', function(){ $('#filterForm')[0].reset(); updateList(); });
        $(document).on('click', '.pagination a.page-link', function(e){
            e.preventDefault();
            var page = $(this).data('page');
            if (page) updateList(page);
        });
        updateList();
    });
    </script>
</body>
</html>
