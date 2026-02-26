<?php
// aziende.php - Versione Corretta e Migliorata con DataTables

// Configurazione per debugging (disabilitare in produzione)
$debug_mode = (bool)(getenv('APP_DEBUG') ?: false);
if (!$debug_mode) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Includi il file di configurazione e funzioni comuni
require_once 'config.php';

// Verifica se l'utente è loggato
checkLogin();

// **GESTIONE RICHIESTA AJAX PER DATATABLES**
if (isset($_GET['datatables_ajax']) && $_GET['datatables_ajax'] == 1) {
    
    // Pulizia output buffer per evitare contenuto non voluto
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Imposta header JSON immediatamente
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    try {
        // Validazione e sanitizzazione parametri DataTables
        $draw = isset($_GET['draw']) ? max(1, intval($_GET['draw'])) : 1;
        $start = isset($_GET['start']) ? max(0, intval($_GET['start'])) : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 30;
if ($length > 0) {
    $length = max(1, min(1000, $length)); // Limite massimo di sicurezza a 1000
}
		$searchValue = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
        
        // Ordinamento con validazione
        $orderColumnIndex = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
        $orderDirection = isset($_GET['order'][0]['dir']) && strtoupper($_GET['order'][0]['dir']) === 'DESC' ? 'DESC' : 'ASC';
        
        // Mappa delle colonne per l'ordinamento (validazione sicurezza)
        $allowedColumns = [
            0 => 'a.nome_azienda',
            1 => 'a.indirizzo_citta', 
            2 => 'a.telefono',
            3 => 'a.email',
            4 => 'numero_lavoratori'
        ];
        
        $orderColumn = isset($allowedColumns[$orderColumnIndex]) ? $allowedColumns[$orderColumnIndex] : 'a.nome_azienda';
        
        // Filtri personalizzati con sanitizzazione
        $nomeFilter = isset($_GET['nome_filter']) ? trim($_GET['nome_filter']) : '';
        $indirizzoFilter = isset($_GET['indirizzo_filter']) ? trim($_GET['indirizzo_filter']) : '';
        $cittaFilter = isset($_GET['citta_filter']) ? trim($_GET['citta_filter']) : '';
        
        // Filtri per colonna DataTables
        $columnFilters = [];
        for ($i = 0; $i < 5; $i++) {
            if (isset($_GET['columns'][$i]['search']['value']) && !empty($_GET['columns'][$i]['search']['value'])) {
                $columnFilters[$i] = trim($_GET['columns'][$i]['search']['value']);
            }
        }
        
        // Costruzione query base con ottimizzazioni
        $baseQuery = "
            FROM aziende a
            LEFT JOIN lavoratori l ON a.id = l.azienda_id AND l.archiviato = 0
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        // Applicazione filtri personalizzati
        if (!empty($nomeFilter)) {
            $baseQuery .= " AND a.nome_azienda LIKE ?";
            $params[] = '%' . $nomeFilter . '%';
            $types .= 's';
        }
        
        if (!empty($indirizzoFilter)) {
            $baseQuery .= " AND a.indirizzo_via LIKE ?";
            $params[] = '%' . $indirizzoFilter . '%';
            $types .= 's';
        }
        
        if (!empty($cittaFilter)) {
            $baseQuery .= " AND a.indirizzo_citta LIKE ?";
            $params[] = '%' . $cittaFilter . '%';
            $types .= 's';
        }
        
        // Ricerca globale DataTables
        if (!empty($searchValue)) {
            $baseQuery .= " AND (a.nome_azienda LIKE ? OR a.indirizzo_via LIKE ? OR a.indirizzo_citta LIKE ? OR a.telefono LIKE ? OR a.email LIKE ?)";
            $searchParam = '%' . $searchValue . '%';
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
            $types .= 'sssss';
        }
        
        // Filtri per colonna DataTables
        if (isset($columnFilters[0])) { // Nome Azienda
            $baseQuery .= " AND a.nome_azienda LIKE ?";
            $params[] = '%' . $columnFilters[0] . '%';
            $types .= 's';
        }
        
        if (isset($columnFilters[1])) { // Indirizzo/Città
            $baseQuery .= " AND (a.indirizzo_via LIKE ? OR a.indirizzo_citta LIKE ?)";
            $params[] = '%' . $columnFilters[1] . '%';
            $params[] = '%' . $columnFilters[1] . '%';
            $types .= 'ss';
        }
        
        if (isset($columnFilters[2])) { // Telefono
            $baseQuery .= " AND a.telefono LIKE ?";
            $params[] = '%' . $columnFilters[2] . '%';
            $types .= 's';
        }
        
        if (isset($columnFilters[3])) { // Email
            $baseQuery .= " AND a.email LIKE ?";
            $params[] = '%' . $columnFilters[3] . '%';
            $types .= 's';
        }
        
        // Conteggio totale (senza filtri) - eseguito per primo per verifica connessione
        $totalCountQuery = "SELECT COUNT(*) as total FROM aziende";
        $totalCountStmt = executeQuery($totalCountQuery, [], '');
        $totalCount = 0;
        
        if ($totalCountStmt !== false) {
            $totalCountResult = $totalCountStmt->get_result();
            if ($totalCountResult) {
                $totalCountRow = $totalCountResult->fetch_assoc();
                $totalCount = intval($totalCountRow['total']);
                $totalCountResult->free();
            }
            $totalCountStmt->close();
        } else {
            // Errore connessione database
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => "Errore connessione database"
            ]);
            exit;
        }
        
        // Conteggio filtrato
        $countQuery = "SELECT COUNT(DISTINCT a.id) as total " . $baseQuery;
        $countStmt = executeQuery($countQuery, $params, $types);
        $filteredCount = 0;
        
        if ($countStmt !== false) {
            $countResult = $countStmt->get_result();
            if ($countResult) {
                $countRow = $countResult->fetch_assoc();
                $filteredCount = intval($countRow['total']);
                $countResult->free();
            }
            $countStmt->close();
        } else {
            $filteredCount = $totalCount; // Fallback
        }
        
        // Query principale con dati
        $dataQuery = "
            SELECT a.id, a.nome_azienda, a.indirizzo_via, a.indirizzo_numero_civico, 
                   a.indirizzo_cap, a.indirizzo_citta, a.indirizzo_provincia, 
                   a.telefono, a.email,
                   COUNT(l.id) AS numero_lavoratori,
                   (SELECT COUNT(*) 
                    FROM lavoratori l_archiviati 
                    WHERE l_archiviati.azienda_id = a.id 
                      AND l_archiviati.archiviato = 1) AS numero_lavoratori_archiviati
        " . $baseQuery . " GROUP BY a.id";
        
        // Ordinamento
$dataQuery .= " ORDER BY " . $orderColumn . " " . $orderDirection;

// Paginazione condizionale
// Paginazione condizionale
if ($length != -1) {
    $dataQuery .= " LIMIT ? OFFSET ?";
    $params[] = $length;
    $types .= 'i';
    $params[] = $start;
    $types .= 'i';
} 


        
        $dataStmt = executeQuery($dataQuery, $params, $types);
        $data = [];
        
        if ($dataStmt !== false) {
            $result = $dataStmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    // Costruzione indirizzo completo
                    $indirizzo_parts = array_filter([
                        trim($row['indirizzo_via'] ?? ''),
                        trim($row['indirizzo_numero_civico'] ?? ''),
                        trim($row['indirizzo_cap'] ?? ''),
                        trim($row['indirizzo_citta'] ?? ''),
                        trim($row['indirizzo_provincia'] ?? '')
                    ], function($value) { return !empty($value); });
                    
                    $indirizzo_completo = implode(', ', $indirizzo_parts);
                    
                    // Nome azienda con link sicuro
                    $nomeAzienda = '<a href="azienda.php?id=' . intval($row['id']) . '" class="fw-bold text-decoration-none">'
                                 . htmlspecialchars($row['nome_azienda'], ENT_QUOTES, 'UTF-8') . '</a>';
                    
                    // Telefono con link se presente
                    $telefono = !empty($row['telefono']) ? 
                        '<a href="tel:' . htmlspecialchars($row['telefono'], ENT_QUOTES, 'UTF-8') . '">' . 
                        htmlspecialchars($row['telefono'], ENT_QUOTES, 'UTF-8') . '</a>' : 
                        '<span class="text-muted">-</span>';
                    
                    // Email con link se presente
                    $email = !empty($row['email']) ? 
                        '<a href="mailto:' . htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') . '">' . 
                        htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') . '</a>' : 
                        '<span class="text-muted">-</span>';
                    
                    // Conteggio lavoratori (FIX: nome campo corretto)
                    $attivi = intval($row['numero_lavoratori']);
                    $archiviati = intval($row['numero_lavoratori_archiviati']);
                    
                    $lavoratori = '<div>';
                    $lavoratori .= '<strong>Attivi: ';
                    if ($attivi > 0) {
                        $lavoratori .= '<a href="lavoratori.php?azienda_filter=' . intval($row['id']) . '">' . $attivi . '</a>';
                    } else {
                        $lavoratori .= '0';
                    }
                    $lavoratori .= '</strong>';
                    
                    if ($archiviati > 0) {
                        $lavoratori .= '<br><a href="archived_lavoratori.php?azienda_id=' . intval($row['id']) . '" class="text-muted small">';
                        $lavoratori .= '(+ ' . $archiviati . ' archiviati)</a>';
                    }
                    
                    // Dettagli per sede con query ottimizzata
                    $sedeQuery = "
                        SELECT 
                            s.id,
                            s.nome,
                            COUNT(l.id) as numero_lavoratori
                        FROM lavoratori l
                        LEFT JOIN sedi s ON l.sede_id = s.id
                        WHERE l.azienda_id = ? AND l.archiviato = 0
                        GROUP BY s.id, s.nome
                        ORDER BY s.nome ASC
                    ";
                    
                    $sedeStmt = executeQuery($sedeQuery, [intval($row['id'])], 'i');
                    $sedi_info = [];
                    $lavoratori_senza_sede = 0;
                    
                    if ($sedeStmt !== false) {
                        $sedeResult = $sedeStmt->get_result();
                        if ($sedeResult) {
                            while ($sede = $sedeResult->fetch_assoc()) {
                                if ($sede['id'] === null) {
                                    $lavoratori_senza_sede = intval($sede['numero_lavoratori']);
                                } else {
                                    $sedi_info[] = $sede;
                                }
                            }
                            $sedeResult->free();
                        }
                        $sedeStmt->close();
                    }
                    
                    // Aggiungi informazioni sedi
                    if (count($sedi_info) > 0 || $lavoratori_senza_sede > 0) {
                        $lavoratori .= '<div class="mt-1 small">';
                        foreach ($sedi_info as $sede) {
                            $lavoratori .= '<div><span class="text-muted">' . htmlspecialchars($sede['nome'], ENT_QUOTES, 'UTF-8') . ':</span> ';
                            $lavoratori .= '<a href="lavoratori.php?azienda_filter=' . intval($row['id']) . '&sede_filter=' . intval($sede['id']) . '">';
                            $lavoratori .= intval($sede['numero_lavoratori']) . '</a></div>';
                        }
                        if ($lavoratori_senza_sede > 0) {
                            $lavoratori .= '<div><span class="text-muted">Senza sede:</span> ';
                            $lavoratori .= '<a href="lavoratori.php?azienda_filter=' . intval($row['id']) . '&sede_filter=">';
                            $lavoratori .= $lavoratori_senza_sede . '</a></div>';
                        }
                        $lavoratori .= '</div>';
                    }
                    $lavoratori .= '</div>';
                    
                    // Azioni
                    $azioni = '<div class="d-flex align-items-center gap-2">'
                            . '<a href="edit_azienda.php?id=' . intval($row['id']) . '" class="table-action-icon" title="Modifica">'
                            . '<i class="fas fa-edit"></i></a>'
                            . '<a href="delete_azienda.php?id=' . intval($row['id']) . '" '
                            . 'class="table-action-icon" title="Elimina" onclick="return confirm(\'Sei sicuro di voler eliminare questa azienda?\');">'
                            . '<i class="fas fa-trash"></i></a>'
                            . '</div>';
                    
                    // Costruzione riga dati
                    $data[] = [
                        $nomeAzienda,
                        htmlspecialchars($indirizzo_completo, ENT_QUOTES, 'UTF-8'),
                        $telefono,
                        $email,
                        $lavoratori,
                        $azioni
                    ];
                }
                $result->free();
            }
            $dataStmt->close();
        }
        
        // Risposta JSON per DataTables
        $response = [
            "draw" => $draw,
            "recordsTotal" => $totalCount,
            "recordsFiltered" => $filteredCount,
            "data" => $data
        ];
        
        // Output JSON pulito
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        
    } catch (Exception $e) {
        // Gestione errori con risposta JSON valida
        $errorResponse = [
            "draw" => isset($draw) ? $draw : 1,
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => [],
            "error" => $debug_mode ? $e->getMessage() : "Errore interno del server"
        ];
        
        echo json_encode($errorResponse);
    }
    
    exit; // IMPORTANTE: terminare l'esecuzione qui per AJAX
}

// **GESTIONE MESSAGGI E INTERFACCIA HTML**
$add_success = isset($_GET['add_success']) && $_GET['add_success'] == 1;
$update_success = isset($_GET['update_success']) && $_GET['update_success'] == 1;
$delete_success = isset($_GET['delete_success']) && $_GET['delete_success'] == 1;
$add_error = isset($_GET['add_error']) ? htmlspecialchars($_GET['add_error'], ENT_QUOTES, 'UTF-8') : '';
$update_error = isset($_GET['update_error']) ? htmlspecialchars($_GET['update_error'], ENT_QUOTES, 'UTF-8') : '';
$delete_error = isset($_GET['delete_error']) ? htmlspecialchars($_GET['delete_error'], ENT_QUOTES, 'UTF-8') : '';

// Genera un token CSRF
generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Lista Aziende</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    
    <!-- Font Awesome -->
    <link href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    
    <!-- Bootstrap CSS -->
    <link href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/css/sb-admin-2.min.css?v=2.0" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/datatables/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/datatables/extensions/responsive/responsive.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/datatables/extensions/buttons/buttons.bootstrap4.min.css">

    <!-- jQuery UI CSS per l'autocomplete -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/jquery-ui/jquery-ui.min.css">

    <!-- SweetAlert2 -->
    <link href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <link href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>styles.css?v=2.0" rel="stylesheet">
    
    <style>
        /* Stili migliorati per l'usabilità */
        .table th {
            vertical-align: middle;
            background-color: #f8f9fc;
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .filters-container {
            background: linear-gradient(135deg, #f8f9fc 0%, #f1f3f6 100%);
            border-radius: .5rem;
            border: 1px solid #e3e6f0;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .filters-container .form-control {
            border-radius: .375rem;
            border: 1px solid #d1d3e2;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .filters-container .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .dataTables_wrapper .dataTables_filter input {
            border-radius: .375rem;
            border: 1px solid #d1d3e2;
        }
        
        .dataTables_wrapper .dataTables_length select {
            border-radius: .375rem;
            border: 1px solid #d1d3e2;
        }
        
        .table-responsive {
            border-radius: .5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .dt-buttons {
            margin-bottom: 1rem;
        }
        
        .dt-button {
            margin-right: 0.5rem !important;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 400;
            transition: all 0.15s ease-in-out;
        }
        
        .dt-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .column-filter {
            width: 100%;
            margin-top: 5px;
            font-size: 12px;
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
            transition: border-color 0.15s ease-in-out;
        }
        
        .column-filter:focus {
            border-color: #4e73df;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .lavoratori-info {
            min-width: 200px;
        }
        
        .lavoratori-info .small {
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .btn-group-vertical .btn {
            border-radius: .25rem;
            margin-bottom: 3px;
            font-size: 0.8rem;
            padding: 0.375rem 0.75rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
        }
        
        .btn-group-vertical .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .dataTables_processing {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #e3e6f0;
            border-radius: .375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .column-filters {
            background-color: #f1f3f6;
        }
        
        .column-filters th {
            padding: 0.75rem !important;
            border-bottom: 2px solid #d1d3e2;
            background-color: #f1f3f6 !important;
            font-weight: normal;
        }
        
        /* Loading Indicator Migliorato */
        .loading-indicator {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            z-index: 9999;
            display: none;
        }
        
        .spinner-border-custom {
            width: 3rem;
            height: 3rem;
            color: #4e73df;
        }
        
        /* Responsive Improvements */
        @media (max-width: 768px) {
            .filters-container {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .filters-container .row .col-md-4 {
                margin-bottom: 1rem;
            }
            
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            #aziendeTable {
                min-width: 1000px;
            }
            
            .table th, .table td {
                white-space: nowrap;
                padding: 0.5rem 0.25rem;
                font-size: 0.875rem;
            }
            
            .btn-group-vertical .btn {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            
            .column-filters {
                display: none !important;
            }
        }
        
        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .card {
                margin-left: -5px;
                margin-right: -5px;
            }
            
            .table th, .table td {
                font-size: 0.8rem;
                padding: 0.4rem 0.2rem;
            }
        }
        
        /* Animazioni smooth */
        .card {
            transition: box-shadow 0.15s ease-in-out;
        }
        
        .card:hover {
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        
        /* Alert migliorati */
        .alert {
            border-radius: 0.5rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
    </style>
</head>
<body id="page-top">
    <!-- Loading Indicator -->
    <div class="loading-indicator" id="loadingIndicator">
        <div class="text-center">
            <div class="spinner-border spinner-border-custom" role="status">
                <span class="sr-only">Caricamento...</span>
            </div>
            <div class="mt-2">
                <small class="text-muted">Caricamento dati...</small>
            </div>
        </div>
    </div>

    <div id="wrapper">
        <?php include 'sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include 'topbar.php'; ?>
                <div class="container-fluid">
                    
                    <!-- Messaggi di feedback migliorati -->
                    <?php if ($add_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> 
                            <strong>Successo!</strong> Azienda aggiunta con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($update_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> 
                            <strong>Successo!</strong> Azienda aggiornata con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($delete_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> 
                            <strong>Successo!</strong> Azienda eliminata con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($add_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> 
                            <strong>Errore!</strong> <?php echo $add_error; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($update_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> 
                            <strong>Errore!</strong> <?php echo $update_error; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($delete_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> 
                            <strong>Errore!</strong> <?php echo $delete_error; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Header migliorato -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-0 text-gray-800">
                                <i class="fas fa-building text-primary"></i> Gestione Aziende
                            </h1>
                            <small class="text-muted">Visualizza e gestisci tutte le aziende registrate nel sistema</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="add_azienda.php" class="btn btn-success shadow-sm">
                                <i class="fas fa-plus"></i> Aggiungi Azienda
                            </a>
                        </div>
                    </div>

                    <!-- Filtri Personalizzati migliorati -->
                    <div class="filters-container" id="customFilters">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-filter"></i> Filtri di Ricerca Avanzata
                            </h6>
                            <button class="btn btn-sm btn-outline-secondary" id="toggleFilters">
                                <i class="fas fa-chevron-up"></i> Nascondi Filtri
                            </button>
                        </div>
                        
                        <div id="filtersContent">
                            <form id="customFilterForm">
                                <div class="row">
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="nome_filter" class="form-label text-sm font-weight-bold text-gray-900">Nome Azienda</label>
                                        <input type="text" name="nome_filter" id="nome_filter" class="form-control" 
                                               placeholder="Cerca per nome azienda..." autocomplete="off">
                                    </div>
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="indirizzo_filter" class="form-label text-sm font-weight-bold text-gray-900">Indirizzo</label>
                                        <input type="text" name="indirizzo_filter" id="indirizzo_filter" class="form-control" 
                                               placeholder="Cerca per indirizzo..." autocomplete="off">
                                    </div>
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="citta_filter" class="form-label text-sm font-weight-bold text-gray-900">Città</label>
                                        <input type="text" name="citta_filter" id="citta_filter" class="form-control" 
                                               placeholder="Cerca per città..." autocomplete="off">
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap">
                                    <button type="button" id="applyFilters" class="btn btn-primary me-2 mb-2">
                                        <i class="fas fa-search"></i> Applica Filtri
                                    </button>
                                    <button type="button" id="resetCustomFilters" class="btn btn-secondary mb-2">
                                        <i class="fas fa-undo"></i> Reset Filtri
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Card principale migliorata -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-table"></i> Elenco Aziende
                            </h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" 
                                   data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" 
                                     aria-labelledby="dropdownMenuLink">
                                    <div class="dropdown-header">Opzioni Tabella:</div>
                                    <a class="dropdown-item" href="#" id="resetAllFilters">
                                        <i class="fas fa-undo fa-sm fa-fw mr-2 text-gray-400"></i>
                                        Reset Tutti i Filtri
                                    </a>
                                    <a class="dropdown-item" href="#" id="toggleColumnFilters">
                                        <i class="fas fa-filter fa-sm fa-fw mr-2 text-gray-400"></i>
                                        <span>Mostra Filtri Colonna</span>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" id="refreshTable">
                                        <i class="fas fa-sync fa-sm fa-fw mr-2 text-gray-400"></i>
                                        Aggiorna Tabella
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <!-- Tabella principale -->
                            <div class="table-responsive">
                                <table id="aziendeTable" class="table table-bordered table-striped table-hover w-100">
                                    <thead>
                                        <tr>
                                            <th>Nome Azienda</th>
                                            <th>Indirizzo Completo</th>
                                            <th>Telefono</th>
                                            <th>Email</th>
                                            <th class="lavoratori-info">Lavoratori per Sede</th>
                                            <th class="text-center" style="width: 120px;">Azioni</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Scroll to Top Button -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Scripts -->

    <!-- jQuery UI PRIMA di altri script per evitare conflitti -->
    <script src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/jquery-ui/jquery-ui.min.js"></script>
    
    <script src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/js/sb-admin-2.min.js"></script>
    
    <!-- DataTables Scripts -->
    <script type="text/javascript" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/datatables/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script type="text/javascript" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/datatables/extensions/responsive/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/datatables/extensions/responsive/responsive.bootstrap4.min.js"></script>
    <script type="text/javascript" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/datatables/extensions/buttons/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/datatables/extensions/buttons/buttons.bootstrap4.min.js"></script>
    <script type="text/javascript" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/jszip/jszip.min.js"></script>
    <script type="text/javascript" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/pdfmake/pdfmake.min.js"></script>
    <script type="text/javascript" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/pdfmake/vfs_fonts.js"></script>
    <script type="text/javascript" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/datatables/extensions/buttons/buttons.html5.min.js"></script>
    <script type="text/javascript" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/datatables/extensions/buttons/buttons.print.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>theme/vendor/sweetalert2/sweetalert2.min.js"></script>

    <script>
        $(document).ready(function() {
            var table;
            var columnFiltersVisible = false;
            var customFiltersVisible = true;
            
            // Mostra indicatore di caricamento
            function showLoading() {
                $('#loadingIndicator').fadeIn(300);
            }
            
            // Nascondi indicatore di caricamento
            function hideLoading() {
                $('#loadingIndicator').fadeOut(300);
            }
            
            // Inizializzazione DataTable con gestione errori migliorata
            table = $('#aziendeTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'aziende.php',
                    type: 'GET',
                    data: function(d) {
                        d.datatables_ajax = 1;
                        // Aggiungi i filtri custom
                        d.nome_filter = $('#nome_filter').val();
                        d.indirizzo_filter = $('#indirizzo_filter').val();
                        d.citta_filter = $('#citta_filter').val();
                    },
                    error: function(xhr, error, code) {
                        hideLoading();
                        console.error('Errore AJAX:', error, code);
                        console.error('Risposta server:', xhr.responseText);
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Errore di Connessione',
                            html: 'Si è verificato un errore nel caricamento dei dati.<br>' +
                                  'Controlla la console per maggiori dettagli.<br><br>' +
                                  '<small class="text-muted">Codice errore: ' + code + '</small>',
                            confirmButtonText: 'Riprova',
                            confirmButtonColor: '#4e73df'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                table.ajax.reload();
                            }
                        });
                    }
                },
                columns: [
                    { 
                        data: 0, 
                        name: 'nome_azienda',
                        responsivePriority: 1,
                        exportOptions: { 
                            orthogonal: "export" 
                        },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.find('a').first().text() || temp.text();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 1, 
                        name: 'indirizzo',
                        responsivePriority: 3
                    },
                    { 
                        data: 2, 
                        name: 'telefono',
                        responsivePriority: 4,
                        exportOptions: { 
                            orthogonal: "export" 
                        },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.find('a').first().text() || temp.text();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 3, 
                        name: 'email',
                        responsivePriority: 5,
                        exportOptions: { 
                            orthogonal: "export" 
                        },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.find('a').first().text() || temp.text();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 4, 
                        name: 'lavoratori',
                        responsivePriority: 2,
                        exportOptions: { 
                            orthogonal: "export" 
                        },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.text().replace(/\s+/g, ' ').trim();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 5, 
                        orderable: false, 
                        searchable: false,
                        className: 'text-center',
                        responsivePriority: 1
                    }
                ],
                order: [[0, 'asc']], // Ordina per nome azienda
                pageLength: 30,
                lengthMenu: [[10, 25, 30, 50, 100, -1], [10, 25, 30, 50, 100, "Tutti"]],
                responsive: {
                    details: {
                        type: 'column',
                        target: 'tr'
                    }
                },
                language: {
                    url: '<?php echo htmlspecialchars($base_url, ENT_QUOTES, "UTF-8"); ?>theme/vendor/datatables/i18n-it-IT.json',
                    processing: '<div class="d-flex justify-content-center align-items-center">' +
                               '<div class="spinner-border text-primary" role="status">' +
                               '<span class="sr-only">Caricamento...</span></div>' +
                               '<span class="ml-2">Caricamento dati...</span></div>',
                    emptyTable: "Nessuna azienda trovata",
                    zeroRecords: "Nessuna azienda corrisponde ai criteri di ricerca"
                },
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'B>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success btn-sm',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4] // Escludi azioni
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger btn-sm',
                        orientation: 'landscape',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4]
                        }
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        className: 'btn btn-info btn-sm',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4]
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Stampa',
                        className: 'btn btn-secondary btn-sm',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4]
                        }
                    }
                ],
                initComplete: function() {
                    addColumnFilters();
                    setupAutocomplete();
                    hideLoading();
                }
            });
            
            // Eventi per indicatore di caricamento
            table.on('preXhr.dt', function() {
                showLoading();
            });
            
            table.on('xhr.dt', function() {
                hideLoading();
            });
            
            // Funzione per aggiungere filtri per colonna
            function addColumnFilters() {
                $('#aziendeTable thead tr').clone(true).addClass('column-filters').appendTo('#aziendeTable thead');
                $('#aziendeTable thead tr:eq(1) th').each(function(i) {
                    var title = $(this).text();
                    
                    if (i === 5) { // Colonna azioni
                        $(this).html('');
                    } else {
                        $(this).html('<input type="text" class="column-filter" placeholder="Filtra ' + title + '" />');
                        
                        $('input', this).on('keyup change', function() {
                            if (table.column(i).search() !== this.value) {
                                table.column(i).search(this.value).draw();
                            }
                        });
                    }
                });
                
                $('.column-filters').hide();
            }
            
            // Setup autocomplete per il campo nome azienda
            function setupAutocomplete() {
                $("#nome_filter").autocomplete({
                    source: function(request, response) {
                        $.ajax({
                            url: "autocomplete_aziende.php",
                            type: "GET",
                            dataType: "json",
                            data: { term: request.term },
                            success: function(data) {
                                response(data);
                            },
                            error: function() {
                                response([]);
                            }
                        });
                    },
                    minLength: 2,
                    delay: 300,
                    select: function(event, ui) {
                        $(this).val(ui.item.value);
                        table.ajax.reload();
                    }
                });
            }
            
            // Toggle filtri custom
            $('#toggleFilters').on('click', function() {
                customFiltersVisible = !customFiltersVisible;
                $('#filtersContent').slideToggle(300);
                $(this).find('i').toggleClass('fa-chevron-up fa-chevron-down');
                $(this).html(customFiltersVisible ? 
                    '<i class="fas fa-chevron-up"></i> Nascondi Filtri' : 
                    '<i class="fas fa-chevron-down"></i> Mostra Filtri'
                );
            });
            
            // Toggle filtri per colonna
            $('#toggleColumnFilters').on('click', function(e) {
                e.preventDefault();
                columnFiltersVisible = !columnFiltersVisible;
                $('.column-filters').slideToggle(300);
                var icon = columnFiltersVisible ? 'fa-eye-slash' : 'fa-filter';
                var text = columnFiltersVisible ? 'Nascondi Filtri Colonna' : 'Mostra Filtri Colonna';
                $(this).find('i').removeClass('fa-filter fa-eye-slash').addClass(icon);
                $(this).find('span').text(text);
            });
            
            // Applica filtri custom
            $('#applyFilters').on('click', function() {
                showLoading();
                table.ajax.reload();
            });
            
            // Reset filtri custom
            $('#resetCustomFilters').on('click', function() {
                $('#customFilterForm')[0].reset();
                showLoading();
                table.ajax.reload();
            });
            
            // Reset tutti i filtri
            $('#resetAllFilters').on('click', function(e) {
                e.preventDefault();
                // Reset filtri custom
                $('#customFilterForm')[0].reset();
                // Reset filtri DataTables
                table.search('').columns().search('').draw();
                $('.column-filter').val('');
                showLoading();
            });
            
            // Refresh tabella
            $('#refreshTable').on('click', function(e) {
                e.preventDefault();
                showLoading();
                table.ajax.reload();
            });
            
            // Filtri custom con debounce migliorato
            var filterTimeout;
            $('#customFilterForm input').on('keyup change', function() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(function() {
                    showLoading();
                    table.ajax.reload();
                }, 500); // Aumentato il delay per ridurre le chiamate
            });
            
            // Gestione dismiss automatico degli alert
            setTimeout(function() {
                $('.alert').fadeOut(500);
            }, 5000);
        });
    </script>
</body>
</html>