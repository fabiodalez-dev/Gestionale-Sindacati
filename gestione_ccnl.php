<?php
/**
 * gestione_ccnl.php - Gestione e normalizzazione CCNL
 * Permette di visualizzare, unificare e rinominare i CCNL dei lavoratori.
 */
require_once 'config.php';
checkLogin();
checkUserRole('admin');
generateCsrfToken();

// ── Gestione merge AJAX ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF non valido']);
        exit;
    }

    $action = $_POST['ajax_action'];

    if ($action === 'merge_ccnl') {
        $old_values = json_decode($_POST['old_values'] ?? '[]', true);
        $new_value = trim($_POST['new_value'] ?? '');

        if (!is_array($old_values)) {
            http_response_code(400);
            echo json_encode(['error' => 'Formato elenco CCNL non valido.']);
            exit;
        }

        if (empty($old_values) || empty($new_value)) {
            http_response_code(400);
            echo json_encode(['error' => 'Seleziona almeno un CCNL e inserisci il nuovo nome.']);
            exit;
        }

        // Filtra: non aggiornare quelli che sono già uguali al nuovo valore
        $to_update = array_filter($old_values, function($v) use ($new_value) {
            return $v !== $new_value;
        });

        if (empty($to_update)) {
            echo json_encode(['success' => true, 'updated' => 0, 'message' => 'Nessuna modifica necessaria.']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($to_update), '?'));
        $types = str_repeat('s', count($to_update) + 1);
        $params = array_merge([$new_value], array_values($to_update));

        $stmt = executeQuery(
            "UPDATE lavoratori SET ccnl = ? WHERE ccnl IN ($placeholders) AND archiviato = 0",
            $params,
            $types
        );

        if ($stmt) {
            $updated = $mysqli->affected_rows;
            echo json_encode(['success' => true, 'updated' => $updated, 'message' => "Aggiornati $updated lavoratori."]);
        } else {
            echo json_encode(['error' => 'Errore durante l\'aggiornamento.']);
        }
        exit;
    }

    if ($action === 'rename_ccnl') {
        $old_value = trim($_POST['old_value'] ?? '');
        $new_value = trim($_POST['new_value'] ?? '');

        if (empty($old_value) || empty($new_value)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valori mancanti.']);
            exit;
        }

        if ($old_value === $new_value) {
            echo json_encode(['success' => true, 'updated' => 0, 'message' => 'Nessuna modifica.']);
            exit;
        }

        $stmt = executeQuery(
            "UPDATE lavoratori SET ccnl = ? WHERE ccnl = ? AND archiviato = 0",
            [$new_value, $old_value],
            'ss'
        );

        if ($stmt) {
            $updated = $mysqli->affected_rows;
            echo json_encode(['success' => true, 'updated' => $updated]);
        } else {
            echo json_encode(['error' => 'Errore durante la rinomina.']);
        }
        exit;
    }

    if ($action === 'delete_ccnl') {
        $value = trim($_POST['value'] ?? '');

        if (empty($value)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valore mancante.']);
            exit;
        }

        $stmt = executeQuery(
            "UPDATE lavoratori SET ccnl = NULL WHERE ccnl = ? AND archiviato = 0",
            [$value],
            's'
        );

        if ($stmt) {
            $updated = $mysqli->affected_rows;
            echo json_encode(['success' => true, 'updated' => $updated]);
        } else {
            echo json_encode(['error' => 'Errore durante la cancellazione.']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Azione non riconosciuta.']);
    exit;
}

// ── Recupera dati CCNL ──
$ccnl_data = [];
$stmt = executeQuery("
    SELECT ccnl, COUNT(*) as tot
    FROM lavoratori
    WHERE ccnl IS NOT NULL AND ccnl != '' AND archiviato = 0
    GROUP BY ccnl
    ORDER BY tot DESC
", [], '');
if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ccnl_data[] = $row;
    }
}

$total_with_ccnl = array_sum(array_column($ccnl_data, 'tot'));
$stmt2 = executeQuery("SELECT COUNT(*) as tot FROM lavoratori WHERE archiviato = 0", [], '');
$total_workers = $stmt2 ? intval($stmt2->get_result()->fetch_assoc()['tot']) : 0;
$total_without_ccnl = $total_workers - $total_with_ccnl;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione CCNL - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.10" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.10" rel="stylesheet">
    <!-- stat-card styles in styles.css -->
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/topbar.php'; ?>
                <div class="container-fluid">

                    <h1 class="h3 mb-4 text-gray-800">Gestione CCNL</h1>

                    <!-- KPI Cards -->
                    <div class="row mb-4">
                        <div class="col-12 col-sm-6 col-lg-4 mb-3">
                            <div class="card stat-card stat-card--dark shadow-sm h-100">
                                <div class="card-body py-3 px-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="stat-icon-wrap mr-3">
                                            <i class="fas fa-file-contract"></i>
                                        </div>
                                        <div class="stat-label mb-0">CCNL Distinti</div>
                                    </div>
                                    <div class="stat-number"><?php echo count($ccnl_data); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-4 mb-3">
                            <div class="card stat-card stat-card--green shadow-sm h-100">
                                <div class="card-body py-3 px-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="stat-icon-wrap mr-3">
                                            <i class="fas fa-user-check"></i>
                                        </div>
                                        <div class="stat-label mb-0">Lavoratori con CCNL</div>
                                    </div>
                                    <div class="stat-number stat-number--green"><?php echo $total_with_ccnl; ?> / <?php echo $total_workers; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-4 mb-3">
                            <div class="card stat-card stat-card--amber shadow-sm h-100">
                                <div class="card-body py-3 px-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="stat-icon-wrap mr-3">
                                            <i class="fas fa-user-slash"></i>
                                        </div>
                                        <div class="stat-label mb-0">Senza CCNL</div>
                                    </div>
                                    <div class="stat-number stat-number--amber"><?php echo $total_without_ccnl; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Merge Panel -->
                    <div class="card shadow mb-4" id="mergePanel" style="display:none;">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-primary text-white">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-compress-arrows-alt"></i> Unifica CCNL selezionati</h6>
                            <button class="btn btn-sm btn-light" onclick="cancelMerge()"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>CCNL selezionati:</strong>
                                <div id="selectedList" class="mt-2"></div>
                            </div>
                            <div class="form-group">
                                <label for="newCcnlName"><strong>Nuovo nome CCNL:</strong></label>
                                <input type="text" class="form-control" id="newCcnlName" placeholder="Inserisci il nome definitivo del CCNL">
                                <small class="form-text text-muted">Puoi digitare un nuovo nome o cliccare su uno dei badge sopra per usarlo.</small>
                            </div>
                            <button class="btn btn-primary" onclick="executeMerge()">
                                <i class="fas fa-check"></i> Unifica
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Elenco CCNL</h6>
                            <button class="btn btn-primary btn-sm" id="btnMerge" onclick="startMerge()" disabled>
                                <i class="fas fa-compress-arrows-alt"></i> Unifica selezionati (<span id="selectedCount">0</span>)
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="ccnlTable">
                                    <thead>
                                        <tr>
                                            <th style="width:40px;">
                                                <input type="checkbox" id="selectAll" title="Seleziona tutto">
                                            </th>
                                            <th>CCNL</th>
                                            <th style="width:120px;">Lavoratori</th>
                                            <th style="width:150px;">Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ccnl_data as $row): ?>
                                        <tr data-ccnl="<?php echo sanitizeForHTML($row['ccnl']); ?>">
                                            <td>
                                                <input type="checkbox" class="ccnl-check" value="<?php echo sanitizeForHTML($row['ccnl']); ?>">
                                            </td>
                                            <td>
                                                <span class="ccnl-name"><?php echo sanitizeForHTML($row['ccnl']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-primary badge-pill"><?php echo intval($row['tot']); ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-secondary" onclick="renameCcnl(this)" title="Rinomina">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteCcnl(this)" title="Svuota (imposta NULL)">
                                                    <i class="fas fa-eraser"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($ccnl_data)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">Nessun CCNL trovato.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
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
    var csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;

    function apiPost(action, data, callback) {
        data.ajax_action = action;
        data.csrf_token = csrfToken;
        $.post('gestione_ccnl.php', data, function(resp) {
            if (resp.error) {
                Swal.fire('Errore', resp.error, 'error');
                return;
            }
            callback(resp);
        }, 'json').fail(function() {
            Swal.fire('Errore', 'Errore di comunicazione.', 'error');
        });
    }

    // ── Selezione checkbox ──
    function updateSelectedCount() {
        var count = $('.ccnl-check:checked').length;
        $('#selectedCount').text(count);
        $('#btnMerge').prop('disabled', count < 2);
    }

    $('#selectAll').on('change', function() {
        $('.ccnl-check').prop('checked', this.checked);
        updateSelectedCount();
    });

    $(document).on('change', '.ccnl-check', function() {
        updateSelectedCount();
        if (!this.checked) $('#selectAll').prop('checked', false);
    });

    // ── Merge ──
    function startMerge() {
        var selected = [];
        $('.ccnl-check:checked').each(function() { selected.push($(this).val()); });
        if (selected.length < 2) return;

        var container = document.getElementById('selectedList');
        container.textContent = '';
        selected.forEach(function(v) {
            var badge = document.createElement('span');
            badge.className = 'badge badge-secondary mr-1 mb-1 p-2';
            badge.style.cssText = 'cursor:pointer;font-size:0.9rem;';
            badge.textContent = v;
            badge.onclick = function() { document.getElementById('newCcnlName').value = this.textContent; };
            container.appendChild(badge);
        });
        $('#newCcnlName').val('');
        $('#mergePanel').slideDown(200);
        $('html, body').animate({ scrollTop: $('#mergePanel').offset().top - 80 }, 300);
    }

    function cancelMerge() {
        $('#mergePanel').slideUp(200);
    }

    function executeMerge() {
        var selected = [];
        $('.ccnl-check:checked').each(function() { selected.push($(this).val()); });
        var newName = $('#newCcnlName').val().trim();

        if (!newName) {
            Swal.fire('Attenzione', 'Inserisci il nome del CCNL.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Conferma unificazione',
            html: 'Unificare <strong>' + selected.length + '</strong> CCNL in <strong>' + $('<span>').text(newName).html() + '</strong>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Unifica',
            cancelButtonText: 'Annulla'
        }).then(function(result) {
            if (result.isConfirmed) {
                apiPost('merge_ccnl', {
                    old_values: JSON.stringify(selected),
                    new_value: newName
                }, function(resp) {
                    Swal.fire('Fatto!', resp.message, 'success').then(function() {
                        location.reload();
                    });
                });
            }
        });
    }

    // ── Rinomina singolo ──
    function renameCcnl(btn) {
        var row = $(btn).closest('tr');
        var oldVal = row.data('ccnl');

        Swal.fire({
            title: 'Rinomina CCNL',
            input: 'text',
            inputValue: oldVal,
            inputLabel: 'Nuovo nome per "' + oldVal + '"',
            showCancelButton: true,
            confirmButtonText: 'Rinomina',
            cancelButtonText: 'Annulla',
            inputValidator: function(v) { if (!v || !v.trim()) return 'Inserisci un nome.'; }
        }).then(function(result) {
            if (result.isConfirmed) {
                apiPost('rename_ccnl', { old_value: oldVal, new_value: result.value.trim() }, function(resp) {
                    Swal.fire('Fatto!', 'Aggiornati ' + resp.updated + ' lavoratori.', 'success').then(function() {
                        location.reload();
                    });
                });
            }
        });
    }

    // ── Elimina (svuota) ──
    function deleteCcnl(btn) {
        var row = $(btn).closest('tr');
        var val = row.data('ccnl');

        Swal.fire({
            title: 'Svuota CCNL',
            html: 'Rimuovere il CCNL "<strong>' + $('<span>').text(val).html() + '</strong>" da tutti i lavoratori associati?<br><small class="text-muted">Il campo CCNL verrà impostato a vuoto.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Svuota',
            cancelButtonText: 'Annulla',
            confirmButtonColor: '#e74a3b'
        }).then(function(result) {
            if (result.isConfirmed) {
                apiPost('delete_ccnl', { value: val }, function(resp) {
                    Swal.fire('Fatto!', 'Aggiornati ' + resp.updated + ' lavoratori.', 'success').then(function() {
                        location.reload();
                    });
                });
            }
        });
    }
    </script>
</body>
</html>
