<?php
// sedi.php - Versione migliorata con conteggio lavoratori e link ai filtri

// Includi il file di configurazione e funzioni comuni
require_once 'config.php';

// Verifica se l'utente è loggato
checkLogin();

// Genera un token CSRF per eventuali form
generateCsrfToken();

// Gestione messaggi di feedback
$delete_success = isset($_GET['delete_success']) && $_GET['delete_success'] == 1;
$delete_error = isset($_GET['delete_error']) ? $_GET['delete_error'] : '';
$add_success = isset($_GET['add_success']) && $_GET['add_success'] == 1;
$edit_success = isset($_GET['edit_success']) && $_GET['edit_success'] == 1;

// Impostazioni per la paginazione
$recordsPerPage = 25;
$page = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $recordsPerPage;

// Filtro di ricerca
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';
$searchParams = [];
$searchTypes = '';

if (!empty($searchTerm)) {
    $searchCondition = " WHERE s.nome LIKE ? OR s.indirizzo LIKE ? OR s.citta LIKE ? OR s.provincia LIKE ?";
    $searchParam = '%' . $searchTerm . '%';
    $searchParams = [$searchParam, $searchParam, $searchParam, $searchParam];
    $searchTypes = 'ssss';
}

// Query per il listing delle sedi con conteggio lavoratori
$query = "
    SELECT 
        s.*,
        COUNT(l.id) as numero_lavoratori,
        COUNT(CASE WHEN l.archiviato = 0 THEN 1 END) as lavoratori_attivi
    FROM sedi s
    LEFT JOIN lavoratori l ON s.id = l.sede_id
    $searchCondition
    GROUP BY s.id
    ORDER BY s.nome ASC 
    LIMIT ? OFFSET ?
";

$params = array_merge($searchParams, [$recordsPerPage, $offset]);
$types = $searchTypes . 'ii';
$stmt = executeQuery($query, $params, $types);
$sedi = [];
if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $sedi[] = $row;
    }
}

// Query per il conteggio totale (per la paginazione)
$countQuery = "SELECT COUNT(DISTINCT s.id) as total FROM sedi s $searchCondition";
$countStmt = executeQuery($countQuery, $searchParams, $searchTypes);
$total = 0;
if ($countStmt) {
    $countRow = $countStmt->get_result()->fetch_assoc();
    $total = intval($countRow['total']);
}
$totalPages = ceil($total / $recordsPerPage);

// Statistiche generali per il dashboard (SENZA lavoratori_senza_sede)
$statsQuery = "
    SELECT 
        COUNT(DISTINCT s.id) as totale_sedi,
        COUNT(l.id) as totale_lavoratori,
        COUNT(CASE WHEN l.archiviato = 0 THEN 1 END) as lavoratori_attivi
    FROM sedi s
    LEFT JOIN lavoratori l ON s.id = l.sede_id
";
$statsStmt = executeQuery($statsQuery, [], '');
$stats = [];
if ($statsStmt) {
    $stats = $statsStmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Sedi - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.5" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.5" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/responsive/responsive.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/buttons/buttons.bootstrap4.min.css">
    
    <!-- Custom CSS -->
    <style>
        /* stat-card styles now in styles.css */
        .sede-name {
            font-weight: 600;
            color: #5a5c69;
            text-decoration: none;
        }
        .sede-name:hover {
            color: #224abe;
            text-decoration: none;
        }
        .badge-lavoratori {
            font-size: 0.875rem;
        }
        .table th {
            border-top: none;
            background-color: #f8f9fc;
            font-weight: 600;
        }
        .search-container {
            background: #f8f9fc;
            border-radius: 0.35rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .btn-outline-primary:hover {
            color: #fff;
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include 'sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include 'topbar.php'; ?>
                <div class="container-fluid">
                    
                    <!-- Messaggi di feedback -->
                    <?php if ($delete_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> Sede eliminata con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($delete_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo sanitizeForHTML($delete_error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($add_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-plus-circle"></i> Sede aggiunta con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($edit_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-edit"></i> Sede modificata con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Header della pagina -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-map-marker-alt"></i> Gestione Sedi
                        </h1>
                        <div class="d-flex gap-2">
                            <a href="add_sede.php" class="btn btn-success shadow-sm">
                                <i class="fas fa-plus"></i> Aggiungi Sede
                            </a>
                        </div>
                    </div>

                    <!-- Statistiche generali -->
                    <?php if (!empty($stats)): ?>
                    <div class="row mb-4">
                        <div class="col-xl-4 col-md-6 mb-3">
                            <div class="card stat-card stat-card--blue shadow-sm h-100">
                                <div class="card-body py-3 px-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="stat-icon-wrap mr-3">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="stat-label">Totale Sedi</div>
                                    </div>
                                    <div class="stat-number"><?php echo number_format($stats['totale_sedi']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6 mb-3">
                            <div class="card stat-card stat-card--green shadow-sm h-100">
                                <div class="card-body py-3 px-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="stat-icon-wrap mr-3">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="stat-label">Lavoratori Attivi</div>
                                    </div>
                                    <div class="stat-number"><?php echo number_format($stats['lavoratori_attivi']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6 mb-3">
                            <div class="card stat-card stat-card--cyan shadow-sm h-100">
                                <div class="card-body py-3 px-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="stat-icon-wrap mr-3">
                                            <i class="fas fa-user-friends"></i>
                                        </div>
                                        <div class="stat-label">Totale Lavoratori</div>
                                    </div>
                                    <div class="stat-number"><?php echo number_format($stats['totale_lavoratori']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Barra di ricerca -->
                    <div class="search-container">
                        <form method="GET" action="sedi.php" class="d-flex flex-wrap align-items-center">
                            <div class="input-group input-group-lg mr-3 mb-2" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                </div>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Cerca per nome, indirizzo, città..." 
                                       value="<?php echo sanitizeForHTML($searchTerm); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg mr-2 mb-2">
                                <i class="fas fa-search"></i> Cerca
                            </button>
                            <?php if (!empty($searchTerm)): ?>
                                <a href="sedi.php" class="btn btn-outline-secondary btn-lg mb-2">
                                    <i class="fas fa-times"></i> Reset
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Card principale -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-table"></i> Elenco Sedi
                                <?php if (!empty($searchTerm)): ?>
                                    <small class="text-muted">- Risultati per: "<?php echo sanitizeForHTML($searchTerm); ?>"</small>
                                <?php endif; ?>
                            </h6>
                            <div class="text-muted">
                                <small>
                                    Totale: <?php echo number_format($total); ?> sedi
                                    <?php if ($totalPages > 1): ?>
                                        | Pagina <?php echo $page; ?> di <?php echo $totalPages; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <?php if (count($sedi) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th style="width: 60px;">#</th>
                                                <th>Nome Sede</th>
                                                <th>Indirizzo Completo</th>
                                                <th>Contatti</th>
                                                <th class="text-center" style="width: 120px;">Lavoratori</th>
                                                <th class="text-center" style="width: 140px;">Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sedi as $sede): ?>
                                                <tr>
                                                    <td class="text-center font-weight-bold text-muted">
                                                        <?php echo sanitizeForHTML($sede['id']); ?>
                                                    </td>
                                                    <td>
                                                        <a href="lavoratori.php?sede_filter=<?php echo sanitizeForHTML($sede['id']); ?>" 
                                                           class="sede-name">
                                                            <i class="fas fa-map-marker-alt text-primary mr-2"></i>
                                                            <?php echo sanitizeForHTML($sede['nome']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <div class="text-sm">
                                                            <?php if (!empty($sede['indirizzo'])): ?>
                                                                <div>
                                                                    <i class="fas fa-road text-muted mr-1"></i>
                                                                    <?php echo sanitizeForHTML($sede['indirizzo']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($sede['citta']) || !empty($sede['provincia']) || !empty($sede['cap'])): ?>
                                                                <div class="mt-1">
                                                                    <i class="fas fa-city text-muted mr-1"></i>
                                                                    <?php 
                                                                    $location = [];
                                                                    if (!empty($sede['cap'])) $location[] = sanitizeForHTML($sede['cap']);
                                                                    if (!empty($sede['citta'])) $location[] = sanitizeForHTML($sede['citta']);
                                                                    if (!empty($sede['provincia'])) $location[] = '(' . sanitizeForHTML($sede['provincia']) . ')';
                                                                    echo implode(' ', $location);
                                                                    ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="text-sm">
                                                            <?php if (!empty($sede['telefono'])): ?>
                                                                <div>
                                                                    <i class="fas fa-phone text-success mr-1"></i>
                                                                    <a href="tel:<?php echo sanitizeForHTML($sede['telefono']); ?>" 
                                                                       class="text-decoration-none">
                                                                        <?php echo sanitizeForHTML($sede['telefono']); ?>
                                                                    </a>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($sede['email'])): ?>
                                                                <div class="mt-1">
                                                                    <i class="fas fa-envelope text-info mr-1"></i>
                                                                    <a href="mailto:<?php echo sanitizeForHTML($sede['email']); ?>" 
                                                                       class="text-decoration-none">
                                                                        <?php echo sanitizeForHTML($sede['email']); ?>
                                                                    </a>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($sede['lavoratori_attivi'] > 0): ?>
                                                            <a href="lavoratori.php?sede_filter=<?php echo sanitizeForHTML($sede['id']); ?>" 
                                                               class="badge badge-success badge-lavoratori p-2 text-decoration-none" 
                                                               title="<?php echo $sede['lavoratori_attivi']; ?> lavoratori attivi di <?php echo $sede['numero_lavoratori']; ?> totali">
                                                                <i class="fas fa-users mr-1"></i>
                                                                <?php echo number_format($sede['lavoratori_attivi']); ?>
                                                                <?php if ($sede['numero_lavoratori'] != $sede['lavoratori_attivi']): ?>
                                                                    <small class="ml-1">(<?php echo $sede['numero_lavoratori']; ?>)</small>
                                                                <?php endif; ?>
                                                            </a>
                                                        <?php elseif ($sede['numero_lavoratori'] > 0): ?>
                                                            <a href="lavoratori.php?sede_filter=<?php echo sanitizeForHTML($sede['id']); ?>" 
                                                               class="badge badge-secondary badge-lavoratori p-2 text-decoration-none" 
                                                               title="<?php echo $sede['numero_lavoratori']; ?> lavoratori archiviati">
                                                                <i class="fas fa-archive mr-1"></i>
                                                                <?php echo number_format($sede['numero_lavoratori']); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="badge badge-light p-2" title="Nessun lavoratore">
                                                                <i class="fas fa-minus"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <a href="edit_sede.php?id=<?php echo sanitizeForHTML($sede['id']); ?>"
                                                               class="table-action-icon"
                                                               title="Modifica sede">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="delete_sede.php?id=<?php echo sanitizeForHTML($sede['id']); ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>"
                                                               class="table-action-icon"
                                                               onclick="return confirm('Sei sicuro di voler eliminare questa sede? I lavoratori associati perderanno il riferimento alla sede.');"
                                                               title="Elimina sede">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-search fa-3x text-gray-300 mb-3"></i>
                                    <h5 class="text-gray-500">
                                        <?php if (!empty($searchTerm)): ?>
                                            Nessuna sede trovata per la ricerca "<?php echo sanitizeForHTML($searchTerm); ?>"
                                        <?php else: ?>
                                            Nessuna sede presente nel sistema
                                        <?php endif; ?>
                                    </h5>
                                    <p class="text-gray-400 mb-4">
                                        <?php if (!empty($searchTerm)): ?>
                                            Prova a modificare i termini di ricerca o 
                                            <a href="sedi.php" class="text-primary">visualizza tutte le sedi</a>
                                        <?php else: ?>
                                            Inizia aggiungendo la prima sede al sistema
                                        <?php endif; ?>
                                    </p>
                                    <a href="add_sede.php" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Aggiungi Prima Sede
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Paginazione migliorata -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Paginazione sedi" class="d-flex justify-content-center">
                            <ul class="pagination pagination-lg">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" aria-label="Prima">
                                            <span aria-hidden="true">&laquo;&laquo;</span>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" aria-label="Precedente">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start = max(1, $page - 2);
                                $end = min($totalPages, $page + 2);
                                
                                if ($start > 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor;
                                
                                if ($end < $totalPages) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" aria-label="Successivo">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" aria-label="Ultima">
                                            <span aria-hidden="true">&raquo;&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

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
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>

    <script>
        $(document).ready(function() {
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
            
            // Tooltip per i badge dei lavoratori
            $('[title]').tooltip();
        });
    </script>
</body>
</html>