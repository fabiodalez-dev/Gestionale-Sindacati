<?php
// lavoratori.php - Versione Avanzata con DataTables, Cognome/Nome e Filtri Completi

// Includi il file di configurazione e funzioni comuni
require_once 'config.php';

// Verifica se l'utente è loggato
checkLogin();

// Recupera l'elenco delle sedi per il filtro e per le operazioni bulk
$sediList = [];
$stmtSedi = executeQuery("SELECT id, nome FROM sedi ORDER BY nome ASC", [], '');
if ($stmtSedi) {
    $resultSedi = $stmtSedi->get_result();
    while ($row = $resultSedi->fetch_assoc()) {
        $sediList[] = $row;
    }
    $resultSedi->free();
    $stmtSedi->close();
}

// Recupera le aziende per il filtro
$aziendeList = [];
$aziendeStmt = executeQuery("SELECT id, nome_azienda FROM aziende ORDER BY nome_azienda ASC", [], '');
if ($aziendeStmt !== false) {
    $aziendeResult = $aziendeStmt->get_result();
    while ($rowAzienda = $aziendeResult->fetch_assoc()) {
        $aziendeList[] = $rowAzienda;
    }
}

// Recupera i paesi di nascita per il filtro
$paesiNascitaList = [];
$paesiNascitaStmt = executeQuery("SELECT DISTINCT paese_nascita FROM lavoratori WHERE paese_nascita IS NOT NULL AND paese_nascita <> '' ORDER BY paese_nascita ASC", [], '');
if ($paesiNascitaStmt !== false) {
    $paesiNascitaResult = $paesiNascitaStmt->get_result();
    while ($rowPaese = $paesiNascitaResult->fetch_assoc()) {
        $paesiNascitaList[] = $rowPaese['paese_nascita'];
    }
}

// Recupera i CCNL per il filtro
$ccnlList = [];
$ccnlStmt = executeQuery("SELECT DISTINCT ccnl FROM lavoratori WHERE ccnl IS NOT NULL AND ccnl <> '' ORDER BY ccnl ASC", [], '');
if ($ccnlStmt !== false) {
    $ccnlResult = $ccnlStmt->get_result();
    while ($row = $ccnlResult->fetch_assoc()) {
        $ccnlList[] = $row['ccnl'];
    }
}

// Leggi parametri URL per i filtri - gestisce sia azienda_filter che azienda_id
$urlFilters = [
    'azienda_filter' => isset($_GET['azienda_filter']) ? intval($_GET['azienda_filter']) : (isset($_GET['azienda_id']) ? intval($_GET['azienda_id']) : null),
    'sede_filter' => isset($_GET['sede_filter']) ? intval($_GET['sede_filter']) : null,
    'unita_operativa_filter' => isset($_GET['unita_operativa_filter']) ? intval($_GET['unita_operativa_filter']) : null,
    'paese_nascita_filter' => isset($_GET['paese_nascita_filter']) ? sanitizeForHTML($_GET['paese_nascita_filter']) : '',
    'iscritto_filter' => isset($_GET['iscritto_filter']) ? sanitizeForHTML($_GET['iscritto_filter']) : '',
    'vertenza_filter' => isset($_GET['vertenza_filter']) ? sanitizeForHTML($_GET['vertenza_filter']) : '',
    'settore_filter' => isset($_GET['settore_filter']) ? sanitizeForHTML($_GET['settore_filter']) : '',
    'tipo_tessera_filter' => isset($_GET['tipo_tessera_filter']) ? sanitizeForHTML($_GET['tipo_tessera_filter']) : '',
    'ruolo_filter' => isset($_GET['ruolo_filter']) ? sanitizeForHTML($_GET['ruolo_filter']) : '',
    'ccnl_filter' => isset($_GET['ccnl_filter']) ? sanitizeForHTML($_GET['ccnl_filter']) : ''
];

// Gestione richieste POST per aggiornamenti in batch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(["error" => "Token CSRF non valido."]);
        exit;
    }
    
    $workerIds = [];
    if (isset($_POST['worker_ids'])) {
        if (is_array($_POST['worker_ids'])) {
            $workerIds = array_map('intval', $_POST['worker_ids']);
        } elseif (is_string($_POST['worker_ids']) && !empty($_POST['worker_ids'])) {
            $workerIds = array_map('intval', explode(',', $_POST['worker_ids']));
        }
    }

    if (empty($workerIds)) {
        echo json_encode(["error" => "Nessun lavoratore selezionato."]);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($workerIds), '?'));
    $typesForWorkerIds = str_repeat('i', count($workerIds));

    // Aggiornamento azienda per i lavoratori selezionati
    if (isset($_POST['update_azienda'])) {
        $newAziendaId = isset($_POST['new_azienda_id']) ? intval($_POST['new_azienda_id']) : 0;
        if ($newAziendaId <= 0) {
            echo json_encode(["error" => "ID Azienda non valido per il cambio."]);
            exit;
        }
        $queryUpdate = "UPDATE lavoratori SET azienda_id = ? WHERE id IN ($placeholders)";
        $paramsUpdate = array_merge([$newAziendaId], $workerIds);
        $typesUpdate = 'i' . $typesForWorkerIds;
        $stmtUpdate = executeQuery($queryUpdate, $paramsUpdate, $typesUpdate);
        if ($stmtUpdate === false) {
            echo json_encode(["error" => "Errore nell'aggiornamento dei lavoratori per il cambio azienda."]);
            exit;
        }
        echo json_encode(["success" => "Azienda aggiornata con successo per i lavoratori selezionati."]);
        exit;
    }
    
    // Aggiornamento sede per i lavoratori selezionati
    if (isset($_POST['update_sede'])) {
        $newSedeId = isset($_POST['new_sede_id']) && $_POST['new_sede_id'] !== '' ? intval($_POST['new_sede_id']) : null;
        if ($newSedeId === 0) $newSedeId = null;

        if ($newSedeId === null) {
            $queryUpdate = "UPDATE lavoratori SET sede_id = NULL WHERE id IN ($placeholders)";
            $paramsUpdate = $workerIds;
            $typesUpdate = $typesForWorkerIds;
        } else {
            $queryUpdate = "UPDATE lavoratori SET sede_id = ? WHERE id IN ($placeholders)";
            $paramsUpdate = array_merge([$newSedeId], $workerIds);
            $typesUpdate = 'i' . $typesForWorkerIds;
        }
        $stmtUpdate = executeQuery($queryUpdate, $paramsUpdate, $typesUpdate);
        if ($stmtUpdate === false) {
            echo json_encode(["error" => "Errore nell'aggiornamento dei lavoratori per il cambio sede."]);
            exit;
        }
        echo json_encode(["success" => "Sede aggiornata con successo per i lavoratori selezionati."]);
        exit;
    }

    // Aggiornamento CCNL per i lavoratori selezionati
    if (isset($_POST['update_ccnl'])) {
        $newCcnl = isset($_POST['new_ccnl']) ? trim($_POST['new_ccnl']) : '';
        if ($newCcnl === '') {
            echo json_encode(["error" => "Seleziona un CCNL valido."]);
            exit;
        }
        $queryUpdate = "UPDATE lavoratori SET ccnl = ? WHERE id IN ($placeholders)";
        $paramsUpdate = array_merge([$newCcnl], $workerIds);
        $typesUpdate = 's' . $typesForWorkerIds;
        $stmtUpdate = executeQuery($queryUpdate, $paramsUpdate, $typesUpdate);
        if ($stmtUpdate === false) {
            echo json_encode(["error" => "Errore nell'aggiornamento del CCNL per i lavoratori selezionati."]);
            exit;
        }
        echo json_encode(["success" => "CCNL aggiornato con successo per i lavoratori selezionati."]);
        exit;
    }

    // Aggiornamento Settore per i lavoratori selezionati
    if (isset($_POST['update_settore'])) {
        $newSettore = isset($_POST['new_settore']) ? trim($_POST['new_settore']) : '';
        $allowedSettori = ['pubblico', 'privato'];
        if (!in_array($newSettore, $allowedSettori, true)) {
            echo json_encode(["error" => "Seleziona un settore valido."]);
            exit;
        }
        $queryUpdate = "UPDATE lavoratori SET settore = ? WHERE id IN ($placeholders)";
        $paramsUpdate = array_merge([$newSettore], $workerIds);
        $typesUpdate = 's' . $typesForWorkerIds;
        $stmtUpdate = executeQuery($queryUpdate, $paramsUpdate, $typesUpdate);
        if ($stmtUpdate === false) {
            echo json_encode(["error" => "Errore nell'aggiornamento del settore per i lavoratori selezionati."]);
            exit;
        }
        echo json_encode(["success" => "Settore aggiornato con successo per i lavoratori selezionati."]);
        exit;
    }

    // Aggiornamento Tipo Tessera per i lavoratori selezionati
    if (isset($_POST['update_tipo_tessera'])) {
        $newTipoTessera = isset($_POST['new_tipo_tessera']) ? trim($_POST['new_tipo_tessera']) : '';
        $allowedTipi = ['rinnovo annuale', 'trattenuta in busta paga', 'sepa'];
        if (!in_array($newTipoTessera, $allowedTipi, true)) {
            echo json_encode(["error" => "Seleziona un tipo tessera valido."]);
            exit;
        }
        $queryUpdate = "UPDATE lavoratori SET tipo_tessera = ? WHERE id IN ($placeholders)";
        $paramsUpdate = array_merge([$newTipoTessera], $workerIds);
        $typesUpdate = 's' . $typesForWorkerIds;
        $stmtUpdate = executeQuery($queryUpdate, $paramsUpdate, $typesUpdate);
        if ($stmtUpdate === false) {
            echo json_encode(["error" => "Errore nell'aggiornamento del tipo tessera per i lavoratori selezionati."]);
            exit;
        }
        echo json_encode(["success" => "Tipo tessera aggiornato con successo per i lavoratori selezionati."]);
        exit;
    }

    // Archiviazione in batch
    if (isset($_POST['archive_workers'])) {
        $queryArchive = "UPDATE lavoratori SET archiviato = 1 WHERE id IN ($placeholders)";
        $paramsArchive = $workerIds;
        $typesArchive = $typesForWorkerIds;
        $stmtArchive = executeQuery($queryArchive, $paramsArchive, $typesArchive);
        if ($stmtArchive === false) {
            echo json_encode(["error" => "Errore nell'archiviazione dei lavoratori."]);
            exit;
        }
        echo json_encode(["success" => "Lavoratori archiviati con successo."]);
        exit;
    }
    
    // Eliminazione in batch
    if (isset($_POST['delete_workers'])) {
        global $mysqli;
        $mysqli->begin_transaction();
        try {
            $queryDeleteDocs = "DELETE FROM documenti_lavoratori WHERE lavoratore_id IN ($placeholders)";
            $stmtDeleteDocs = executeQuery($queryDeleteDocs, $workerIds, $typesForWorkerIds);
            if ($stmtDeleteDocs === false) throw new Exception("Errore eliminazione documenti.");

            $queryDeleteIscrizioni = "DELETE FROM iscrizioni WHERE lavoratore_id IN ($placeholders)";
            $stmtDeleteIscrizioni = executeQuery($queryDeleteIscrizioni, $workerIds, $typesForWorkerIds);
            if ($stmtDeleteIscrizioni === false) throw new Exception("Errore eliminazione iscrizioni.");
            
            $queryDelete = "DELETE FROM lavoratori WHERE id IN ($placeholders)";
            $stmtDelete = executeQuery($queryDelete, $workerIds, $typesForWorkerIds);
            if ($stmtDelete === false) throw new Exception("Errore eliminazione lavoratori.");

            $mysqli->commit();
            echo json_encode(["success" => "Lavoratori eliminati con successo."]);
        } catch (Exception $e) {
            $mysqli->rollback();
            echo json_encode(["error" => $e->getMessage()]);
        }
        exit;
    }
}

// Gestione richiesta AJAX per DataTables
if (isset($_GET['datatables_ajax']) && $_GET['datatables_ajax'] == 1) {
    
    // Parametri DataTables
    $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 25;
    $searchValue = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
    
    // Ordinamento
    $orderColumnIndex = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 1;
    $orderDirection = isset($_GET['order'][0]['dir']) && $_GET['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';
    
    // Mappa delle colonne per l'ordinamento - COGNOME PRIMA
    $columns = [
        0 => '', // checkbox - non ordinabile
        1 => 'l.cognome',
        2 => 'l.nome',
        3 => 's.nome',
        4 => 'a.nome_azienda',
        5 => 'u.nome_unita_operativa',
        6 => 'l.paese_nascita',
        7 => 'l.vertenze',
        8 => 'l.settore',
        9 => 'l.ruolo',
        10 => 'status_iscrizione',
        11 => ''
    ];
    
    $orderColumn = isset($columns[$orderColumnIndex]) ? $columns[$orderColumnIndex] : 'l.cognome';
    
    // Filtri dai form originali - gestisce anche azienda_id
    $aziendaFilter = isset($_GET['azienda_filter']) && intval($_GET['azienda_filter']) > 0 ? intval($_GET['azienda_filter']) : (isset($_GET['azienda_id']) && intval($_GET['azienda_id']) > 0 ? intval($_GET['azienda_id']) : null);
    $unitaOperativaFilter = isset($_GET['unita_operativa_filter']) && intval($_GET['unita_operativa_filter']) > 0 ? intval($_GET['unita_operativa_filter']) : null;
    $paeseNascitaFilter = isset($_GET['paese_nascita_filter']) ? trim($_GET['paese_nascita_filter']) : '';
    $iscrittoFilter = isset($_GET['iscritto_filter']) ? $_GET['iscritto_filter'] : '';
    $vertenzaFilter = isset($_GET['vertenza_filter']) && $_GET['vertenza_filter'] !== '' ? $_GET['vertenza_filter'] : '';
    $settoreFilter = isset($_GET['settore_filter']) && $_GET['settore_filter'] !== '' ? $_GET['settore_filter'] : '';
    $tipoTesseraFilter = isset($_GET['tipo_tessera_filter']) && $_GET['tipo_tessera_filter'] !== '' ? $_GET['tipo_tessera_filter'] : '';
    $ruoloFilter = isset($_GET['ruolo_filter']) && $_GET['ruolo_filter'] !== '' ? $_GET['ruolo_filter'] : '';
    $ccnlFilter = isset($_GET['ccnl_filter']) ? trim($_GET['ccnl_filter']) : '';
    $sedeFilter = isset($_GET['sede_filter']) && $_GET['sede_filter'] !== '' ? intval($_GET['sede_filter']) : null;

    // Filtri individuali per colonna DataTables
    $columnFilters = [];
    for ($i = 0; $i < 12; $i++) {
        if (isset($_GET['columns'][$i]['search']['value']) && !empty($_GET['columns'][$i]['search']['value'])) {
            $columnFilters[$i] = trim($_GET['columns'][$i]['search']['value']);
        }
    }
    
    // Query base con JOIN per sede
    $baseQuery = "
        FROM lavoratori l
        LEFT JOIN aziende a ON l.azienda_id = a.id
        LEFT JOIN unita_operativa u ON l.unita_operativa_id = u.id
        LEFT JOIN sedi s ON l.sede_id = s.id
        LEFT JOIN (
            SELECT isc.*, ROW_NUMBER() OVER (PARTITION BY isc.lavoratore_id ORDER BY isc.data_fine DESC, isc.id DESC) as rn
            FROM iscrizioni isc
        ) i_sub ON l.id = i_sub.lavoratore_id AND i_sub.rn = 1
        WHERE l.archiviato = 0
    ";
    
    $params = [];
    $types = '';
    
    // Filtri originali del form
    if ($aziendaFilter !== null) {
        $baseQuery .= " AND a.id = ?";
        $params[] = $aziendaFilter;
        $types .= 'i';
    }
    if ($unitaOperativaFilter !== null) {
        $baseQuery .= " AND u.id = ?";
        $params[] = $unitaOperativaFilter;
        $types .= 'i';
    }
    if ($paeseNascitaFilter !== '') {
        $baseQuery .= " AND l.paese_nascita LIKE ?";
        $params[] = '%' . $paeseNascitaFilter . '%';
        $types .= 's';
    }
    if ($sedeFilter !== null) {
        $baseQuery .= " AND l.sede_id = ?";
        $params[] = $sedeFilter;
        $types .= 'i';
    }
    if ($iscrittoFilter !== '') {
        $today = date('Y-m-d');
        $thirtyDaysFromNow = date('Y-m-d', strtotime('+30 days'));
        $thirtyOneDaysFromNow = date('Y-m-d', strtotime('+31 days'));
        
        if ($iscrittoFilter === 'sì') {
            $baseQuery .= " AND (l.tipo_tessera IN ('trattenuta in busta paga', 'sepa') OR (l.tipo_tessera = 'rinnovo annuale' AND (i_sub.data_fine >= ? OR i_sub.data_fine IS NULL)))";
            $params[] = $thirtyOneDaysFromNow;
            $types .= 's';
        } elseif ($iscrittoFilter === 'in scadenza') {
            $baseQuery .= " AND (l.tipo_tessera = 'rinnovo annuale' AND i_sub.data_fine BETWEEN ? AND ?)";
            $params[] = $today;
            $params[] = $thirtyDaysFromNow;
            $types .= 'ss';
        } elseif ($iscrittoFilter === 'no') {
            $baseQuery .= " AND ((l.tipo_tessera = 'rinnovo annuale' AND i_sub.data_fine < ?) OR i_sub.id IS NULL)";
            $params[] = $today;
            $types .= 's';
        }
    }
    if ($vertenzaFilter !== '') {
        if ($vertenzaFilter === '1' || $vertenzaFilter === '0') {
            $baseQuery .= " AND l.vertenze = ?";
            $params[] = intval($vertenzaFilter);
            $types .= 'i';
        }
    }
    if ($settoreFilter !== '') {
        $baseQuery .= " AND l.settore = ?";
        $params[] = $settoreFilter;
        $types .= 's';
    }
    if ($tipoTesseraFilter !== '') {
        $baseQuery .= " AND l.tipo_tessera = ?";
        $params[] = $tipoTesseraFilter;
        $types .= 's';
    }
    if ($ruoloFilter !== '') {
        if ($ruoloFilter === 'Delegato') {
            $baseQuery .= " AND (l.ruolo = 'RSU' OR l.ruolo = 'RSA' OR l.ruolo = 'RLS')";
        } elseif ($ruoloFilter === 'Nessuno') {
            $baseQuery .= " AND (l.ruolo IS NULL OR l.ruolo = '' OR l.ruolo = 'NESSUNO')";
        } else {
            $baseQuery .= " AND l.ruolo = ?";
            $params[] = $ruoloFilter;
            $types .= 's';
        }
    }
    if ($ccnlFilter !== '') {
        $baseQuery .= " AND l.ccnl = ?";
        $params[] = $ccnlFilter;
        $types .= 's';
    }

    // Ricerca globale
    if (!empty($searchValue)) {
        $baseQuery .= " AND (l.nome LIKE ? OR l.cognome LIKE ? OR l.note LIKE ? OR s.nome LIKE ? OR a.nome_azienda LIKE ?)";
        $searchParam = '%' . $searchValue . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        $types .= 'sssss';
    }
    
    // Filtri per colonna DataTables - COGNOME COLONNA 1, NOME COLONNA 2
    if (isset($columnFilters[1])) {
        $baseQuery .= " AND l.cognome LIKE ?";
        $params[] = '%' . $columnFilters[1] . '%';
        $types .= 's';
    }
    
    if (isset($columnFilters[2])) {
        $baseQuery .= " AND l.nome LIKE ?";
        $params[] = '%' . $columnFilters[2] . '%';
        $types .= 's';
    }
    
    if (isset($columnFilters[3])) {
        $baseQuery .= " AND s.nome LIKE ?";
        $params[] = '%' . $columnFilters[3] . '%';
        $types .= 's';
    }
    
    if (isset($columnFilters[4])) {
        $baseQuery .= " AND a.nome_azienda LIKE ?";
        $params[] = '%' . $columnFilters[4] . '%';
        $types .= 's';
    }
    
    if (isset($columnFilters[5])) {
        $baseQuery .= " AND u.nome_unita_operativa LIKE ?";
        $params[] = '%' . $columnFilters[5] . '%';
        $types .= 's';
    }
    
    if (isset($columnFilters[6])) {
        $baseQuery .= " AND l.paese_nascita LIKE ?";
        $params[] = '%' . $columnFilters[6] . '%';
        $types .= 's';
    }
    
    if (isset($columnFilters[7])) {
        if (strtolower($columnFilters[7]) === 'sì' || $columnFilters[7] === '1') {
            $baseQuery .= " AND l.vertenze = 1";
        } elseif (strtolower($columnFilters[7]) === 'no' || $columnFilters[7] === '0') {
            $baseQuery .= " AND l.vertenze = 0";
        }
    }
    
    if (isset($columnFilters[8])) {
        $baseQuery .= " AND l.settore LIKE ?";
        $params[] = '%' . $columnFilters[8] . '%';
        $types .= 's';
    }
    
    if (isset($columnFilters[9])) {
        $baseQuery .= " AND l.ruolo LIKE ?";
        $params[] = '%' . $columnFilters[9] . '%';
        $types .= 's';
    }
    
    if (isset($columnFilters[10])) {
        $statusFilter = strtolower($columnFilters[10]);
        if (strpos($statusFilter, 'attiv') !== false) {
            $baseQuery .= " AND (l.tipo_tessera IN ('trattenuta in busta paga', 'sepa') OR (l.tipo_tessera = 'rinnovo annuale' AND (i_sub.data_fine >= CURDATE() + INTERVAL 31 DAY OR i_sub.data_fine IS NULL)))";
        } elseif (strpos($statusFilter, 'scadenz') !== false) {
            $baseQuery .= " AND (l.tipo_tessera = 'rinnovo annuale' AND i_sub.data_fine BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY)";
        } elseif (strpos($statusFilter, 'scadut') !== false) {
            $baseQuery .= " AND (l.tipo_tessera = 'rinnovo annuale' AND i_sub.data_fine < CURDATE())";
        }
    }
    
    // Conteggio totale filtrato
    $countQuery = "SELECT COUNT(DISTINCT l.id) as total " . $baseQuery;
    $countStmt = executeQuery($countQuery, $params, $types);
    $filteredCount = 0;
    if ($countStmt !== false) {
        $countResult = $countStmt->get_result();
        $countRow = $countResult->fetch_assoc();
        $filteredCount = intval($countRow['total']);
    }
    
    // Query principale con dati
    $dataQuery = "
        SELECT l.id, l.nome, l.cognome, l.paese_nascita, l.ruolo, l.vertenze, l.settore, 
               a.nome_azienda, a.id as azienda_id, u.nome_unita_operativa, u.id as unita_operativa_id,
               l.tipo_tessera, l.iscritto, i_sub.data_inizio, i_sub.data_fine, i_sub.nota_pagamento,
               s.nome as nome_sede, s.id as sede_id
    " . $baseQuery;
    
    // Ordinamento
    if (!empty($orderColumn)) {
        $dataQuery .= " ORDER BY " . $orderColumn . " " . $orderDirection;
    } else {
        $dataQuery .= " ORDER BY l.cognome ASC, l.nome ASC";
    }
    
    // Paginazione
    $dataQuery .= " LIMIT ? OFFSET ?";
    $params[] = $length;
    $types .= 'i';
    $params[] = $start;
    $types .= 'i';
    
    $dataStmt = executeQuery($dataQuery, $params, $types);
    $data = [];
    
    if ($dataStmt !== false) {
        $result = $dataStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Calcola stato iscrizione
            $status = 'Nessuna iscrizione';
            $statusClass = 'secondary';
            $tipo_tessera = $row['tipo_tessera'];
            $data_fine = $row['data_fine'];
            $today = date('Y-m-d');

            if ($tipo_tessera === 'trattenuta in busta paga' || $tipo_tessera === 'sepa') {
                $status = 'Attiva';
                $statusClass = 'success';
            } elseif ($tipo_tessera === 'rinnovo annuale') {
                if (!empty($data_fine)) {
                    $data_fine_obj = new DateTime($data_fine);
                    $today_obj = new DateTime($today);
                    $interval = $today_obj->diff($data_fine_obj);
                    $days_diff = (int)$interval->format('%r%a');
                    
                    if ($days_diff < 0) {
                        $status = 'Scaduta';
                        $statusClass = 'danger';
                    } elseif ($days_diff <= 30) {
                        $status = 'In scadenza';
                        $statusClass = 'warning';
                    } else {
                        $status = 'Attiva';
                        $statusClass = 'success';
                    }
                } elseif (is_null($data_fine) && !empty($row['data_inizio'])) {
                    $status = 'Attiva';
                    $statusClass = 'success';
                } else {
                    $status = 'Dati mancanti';
                    $statusClass = 'secondary';
                }
            }
            
            // Costruisci le colonne separate - COGNOME PRIMA
            $cognome = '<a href="lavoratore.php?id=' . sanitizeForHTML($row['id']) . '" class="fw-bold text-decoration-none">'
                     . sanitizeForHTML($row['cognome']) . '</a>';
            
            $nome = '<a href="lavoratore.php?id=' . sanitizeForHTML($row['id']) . '" class="fw-bold text-decoration-none">'
                  . sanitizeForHTML($row['nome']) . '</a>';
            if ($row['tipo_tessera'] === 'rinnovo annuale' && !empty($row['nota_pagamento'])) {
                $nome .= '<br><small class="text-info">' . sanitizeForHTML($row['nota_pagamento']) . '</small>';
            }
            
            $sede = !empty($row['nome_sede']) ? 
                '<i class="fas fa-map-marker-alt text-muted"></i> ' . sanitizeForHTML($row['nome_sede']) : 
                '<small class="text-muted"><i class="fas fa-map-marker-alt"></i> Non assegnata</small>';
                
            $azienda = $row['azienda_id'] ? 
                '<a href="azienda.php?id=' . sanitizeForHTML($row['azienda_id']) . '">' . sanitizeForHTML($row['nome_azienda']) . '</a>' : 
                '<span class="text-muted">N/A</span>';
                
            $unitaOperativa = $row['unita_operativa_id'] ? 
                '<a href="unita_operativa.php?id=' . sanitizeForHTML($row['unita_operativa_id']) . '">' . sanitizeForHTML($row['nome_unita_operativa']) . '</a>' : 
                '<span class="text-muted">N/A</span>';
            
            $ruolo = '';
            if ($row['ruolo'] === 'RSU' || $row['ruolo'] === 'RSA' || $row['ruolo'] === 'RLS') {
                $ruolo = '<span class="badge badge-primary">' . sanitizeForHTML($row['ruolo']) . '</span>';
            } elseif ($row['ruolo'] && $row['ruolo'] !== 'NESSUNO') {
                $ruolo = '<span class="badge badge-secondary">' . sanitizeForHTML($row['ruolo']) . '</span>';
            } else {
                $ruolo = '<span class="text-muted">-</span>';
            }
            
            $statusBadge = '<span class="badge badge-' . $statusClass . '">' . $status . '</span>';
            
            $azioni = '<div class="d-flex align-items-center gap-2">'
                    . '<a href="edit_lavoratore.php?id=' . sanitizeForHTML($row['id']) . '" class="table-action-icon" title="Modifica">'
                    . '<i class="fas fa-edit"></i></a>'
                    . '<a href="delete_lavoratore.php?id=' . sanitizeForHTML($row['id']) . '&csrf_token=' . $_SESSION['csrf_token'] . '" '
                    . 'class="table-action-icon" title="Elimina" onclick="return confirm(\'Sei sicuro di voler eliminare questo lavoratore?\');">'
                    . '<i class="fas fa-trash"></i></a>'
                    . '</div>';
            
            // ORDINE COLONNE: checkbox, COGNOME, NOME, resto...
            $data[] = [
                '<input type="checkbox" class="worker-checkbox" value="' . sanitizeForHTML($row['id']) . '">',
                $cognome,
                $nome,
                $sede,
                $azienda,
                $unitaOperativa,
                sanitizeForHTML($row['paese_nascita']),
                $row['vertenze'] ? '<span class="badge badge-warning">Sì</span>' : '<span class="badge badge-light">No</span>',
                '<span class="badge badge-info">' . ucfirst(sanitizeForHTML($row['settore'])) . '</span>',
                $ruolo,
                $statusBadge,
                $azioni
            ];
        }
    }
    
    // Conteggio totale (senza filtri)
    $totalCountStmt = executeQuery("SELECT COUNT(*) as total FROM lavoratori WHERE archiviato = 0", [], '');
    $totalCount = 0;
    if ($totalCountStmt !== false) {
        $totalCountResult = $totalCountStmt->get_result();
        $totalCountRow = $totalCountResult->fetch_assoc();
        $totalCount = intval($totalCountRow['total']);
    }
    
    // Risposta JSON per DataTables
    $response = [
        "draw" => $draw,
        "recordsTotal" => $totalCount,
        "recordsFiltered" => $filteredCount,
        "data" => $data
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Gestione dei messaggi
$delete_success = isset($_GET['delete_success']) && $_GET['delete_success'] == 1;
$delete_error = isset($_GET['delete_error']) ? $_GET['delete_error'] : '';
$add_success = isset($_GET['add_success']) && $_GET['add_success'] == 1;

// Genera un token CSRF
generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Lavoratori</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    
    <!-- Bootstrap CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.5" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/responsive/responsive.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/buttons/buttons.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/select/select.bootstrap4.min.css">

    <!-- SweetAlert2 -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.5" rel="stylesheet">
    <style>
        .table th {
            vertical-align: middle;
            background-color: #f8f9fc;
            border-top: none;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .filters-container {
            background-color: #f8f9fc;
            border-radius: .35rem;
            border: 1px solid #e3e6f0;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .bulk-actions-container {
            margin: 1rem 0;
            padding: 1rem;
            background-color: #f8f9fc;
            border-radius: .35rem;
            border: 1px solid #e3e6f0;
        }
        
        .bulk-actions-container .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .dataTables_wrapper .dataTables_filter input {
            border-radius: .35rem;
            border: 1px solid #d1d3e2;
        }
        
        .dataTables_wrapper .dataTables_length select {
            border-radius: .35rem;
            border: 1px solid #d1d3e2;
        }
        
        .table-responsive {
            border-radius: .35rem;
        }
        
        .dt-buttons {
            margin-bottom: 1rem;
        }
        
        .dt-button {
            margin-right: 0.5rem !important;
            padding: 0.375rem 0.75rem;
            border-radius: 0.35rem;
        }
        
        .column-filter {
            width: 100%;
            margin-top: 5px;
            font-size: 12px;
            padding: 3px 6px;
            border: 1px solid #ddd;
            border-radius: 3px;
            background-color: white;
        }
        
        .badge {
            font-size: 0.75em;
        }
        
        /* Mobile Responsive Improvements */
        @media (max-width: 768px) {
            .bulk-actions-container .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .filters-container .row .col-md-3,
            .filters-container .row .col-md-4,
            .filters-container .row .col-md-2 {
                margin-bottom: 1rem;
            }
            
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            #lavoratoriTable {
                min-width: 1400px;
            }
            
            .table th, .table td {
                white-space: nowrap;
                padding: 0.5rem 0.25rem;
                font-size: 0.875rem;
            }
            
            .table-action-icon {
                width: 1.75rem;
                height: 1.75rem;
                font-size: 0.75rem;
            }
        }
        
        .status-badge {
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }
        
        
        .dataTables_processing {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #ddd;
            border-radius: .35rem;
        }
        
        .column-filters {
            background-color: #e3e6f0;
        }
        
        .column-filters th {
            padding: 0.5rem !important;
            border-bottom: 2px solid #d1d3e2;
        }
        
        @media (max-width: 768px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }

            .filters-container .row .col-12 {
                margin-bottom: 1rem;
            }

            #customFilterForm .form-label {
                font-weight: 500;
            }

            .table-responsive {
                border: none;
            }

            .table th, .table td {
                font-size: 0.9rem;
                padding: 0.6rem 0.4rem;
                white-space: normal;
                word-break: break-word;
            }

            #lavoratoriTable {
                min-width: 0;
            }

            .table-action-icon {
                width: 2rem;
                height: 2rem;
                font-size: 0.85rem;
            }

            .badge {
                font-size: 0.8rem;
                padding: 0.4em 0.6em;
            }

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                text-align: left;
                width: 100%;
                margin-bottom: 10px;
            }

            .dt-buttons .btn {
                width: 100%;
                margin-bottom: 5px;
            }
        }

        @media (max-width: 576px) {
            .column-filters {
                display: none !important;
            }
            
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
            
            .column-filter {
                font-size: 11px;
                padding: 2px 4px;
            }
        }
        
        thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .column-filters th {
            background-color: #f1f3f6 !important;
            font-weight: normal;
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
                            <i class="fas fa-check-circle"></i> Lavoratore eliminato con successo.
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
                            <i class="fas fa-plus-circle"></i> Lavoratore aggiunto con successo.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Header -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-users"></i> Gestione Lavoratori
                        </h1>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary shadow-sm" data-toggle="modal" data-target="#importModal">
                                <i class="fas fa-file-import"></i> Importa
                            </button>
                            <a href="add_lavoratore.php" class="btn btn-success shadow-sm">
                                <i class="fas fa-plus"></i> Aggiungi Lavoratore
                            </a>
                        </div>
                    </div>

                    <!-- Filtri Originali -->
                    <div class="filters-container" id="customFilters">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-filter"></i> Filtri di Ricerca
                            </h6>
                            <button class="btn btn-sm btn-outline-secondary" id="toggleFilters">
                                <i class="fas fa-chevron-up"></i> Nascondi Filtri
                            </button>
                        </div>
                        
                        <div id="filtersContent">
                            <form id="customFilterForm">
                                <div class="row">
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="azienda_filter" class="form-label">Azienda</label>
                                        <select name="azienda_filter" id="azienda_filter" class="form-control">
                                            <option value="">Tutte le aziende</option>
                                            <?php foreach ($aziendeList as $azienda): ?>
                                                <option value="<?php echo sanitizeForHTML($azienda['id']); ?>" 
                                                    <?php echo ($urlFilters['azienda_filter'] == $azienda['id']) ? 'selected' : ''; ?>>
                                                    <?php echo sanitizeForHTML($azienda['nome_azienda']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="unita_operativa_filter" class="form-label">Unità Operativa</label>
                                        <select name="unita_operativa_filter" id="unita_operativa_filter" class="form-control" disabled>
                                            <option value="">Tutte le unità operative</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="paese_nascita_filter" class="form-label">Paese di Nascita</label>
                                        <select name="paese_nascita_filter" id="paese_nascita_filter" class="form-control">
                                            <option value="">Tutti i Paesi</option>
                                            <?php foreach ($paesiNascitaList as $paese): ?>
                                                <option value="<?php echo sanitizeForHTML($paese); ?>"
                                                    <?php echo ($urlFilters['paese_nascita_filter'] == $paese) ? 'selected' : ''; ?>>
                                                    <?php echo sanitizeForHTML($paese); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="sede_filter" class="form-label">Sede Sindacato</label>
                                        <select name="sede_filter" id="sede_filter" class="form-control">
                                            <option value="">Tutte le sedi</option>
                                            <?php foreach ($sediList as $sede_item): ?>
                                                <option value="<?php echo sanitizeForHTML($sede_item['id']); ?>"
                                                    <?php echo ($urlFilters['sede_filter'] == $sede_item['id']) ? 'selected' : ''; ?>>
                                                    <?php echo sanitizeForHTML($sede_item['nome']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="iscritto_filter" class="form-label">Iscritto</label>
                                        <select name="iscritto_filter" id="iscritto_filter" class="form-control">
                                            <option value="">Tutti</option>
                                            <option value="sì" <?php echo ($urlFilters['iscritto_filter'] == 'sì') ? 'selected' : ''; ?>>Sì (Attiva)</option>
                                            <option value="in scadenza" <?php echo ($urlFilters['iscritto_filter'] == 'in scadenza') ? 'selected' : ''; ?>>In Scadenza (entro 30gg)</option>
                                            <option value="no" <?php echo ($urlFilters['iscritto_filter'] == 'no') ? 'selected' : ''; ?>>No (Scaduta/Non Iscritto)</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="vertenza_filter" class="form-label">Vertenza</label>
                                        <select name="vertenza_filter" id="vertenza_filter" class="form-control">
                                            <option value="">Tutti</option>
                                            <option value="1" <?php echo ($urlFilters['vertenza_filter'] == '1') ? 'selected' : ''; ?>>Sì</option>
                                            <option value="0" <?php echo ($urlFilters['vertenza_filter'] == '0') ? 'selected' : ''; ?>>No</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="settore_filter" class="form-label">Settore</label>
                                        <select name="settore_filter" id="settore_filter" class="form-control">
                                            <option value="">Tutti</option>
                                            <option value="privato" <?php echo ($urlFilters['settore_filter'] == 'privato') ? 'selected' : ''; ?>>Privato</option>
                                            <option value="pubblico" <?php echo ($urlFilters['settore_filter'] == 'pubblico') ? 'selected' : ''; ?>>Pubblico</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="tipo_tessera_filter" class="form-label">Tipo Tessera</label>
                                        <select name="tipo_tessera_filter" id="tipo_tessera_filter" class="form-control">
                                            <option value="">Tutti</option>
                                            <option value="rinnovo annuale" <?php echo ($urlFilters['tipo_tessera_filter'] == 'rinnovo annuale') ? 'selected' : ''; ?>>Rinnovo Annuale</option>
                                            <option value="trattenuta in busta paga" <?php echo ($urlFilters['tipo_tessera_filter'] == 'trattenuta in busta paga') ? 'selected' : ''; ?>>Trattenuta in Busta Paga</option>
                                            <option value="sepa" <?php echo ($urlFilters['tipo_tessera_filter'] == 'sepa') ? 'selected' : ''; ?>>SEPA</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="ruolo_filter" class="form-label">Ruolo Sindacale</label>
                                        <select name="ruolo_filter" id="ruolo_filter" class="form-control">
                                            <option value="">Tutti</option>
                                            <option value="Nessuno" <?php echo ($urlFilters['ruolo_filter'] == 'Nessuno') ? 'selected' : ''; ?>>Nessuno</option>
                                            <option value="RSU" <?php echo ($urlFilters['ruolo_filter'] == 'RSU') ? 'selected' : ''; ?>>RSU</option>
                                            <option value="RSA" <?php echo ($urlFilters['ruolo_filter'] == 'RSA') ? 'selected' : ''; ?>>RSA</option>
                                            <option value="RLS" <?php echo ($urlFilters['ruolo_filter'] == 'RLS') ? 'selected' : ''; ?>>RLS</option>
                                            <option value="Delegato" <?php echo ($urlFilters['ruolo_filter'] == 'Delegato') ? 'selected' : ''; ?>>Delegato (RSU/RSA/RLS)</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="ccnl_filter" class="form-label">CCNL</label>
                                        <select name="ccnl_filter" id="ccnl_filter" class="form-control">
                                            <option value="">Tutti i CCNL</option>
                                            <?php foreach ($ccnlList as $ccnl): ?>
                                                <option value="<?= sanitizeForHTML($ccnl) ?>"
                                                    <?= ($urlFilters['ccnl_filter'] == $ccnl) ? 'selected' : '' ?>>
                                                    <?= sanitizeForHTML($ccnl) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
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

                    <!-- Card principale -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-table"></i> Elenco Lavoratori
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
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            
                            <!-- Pulsanti per operazioni bulk -->
                            <div class="bulk-actions-container">
                                <div class="d-flex flex-wrap align-items-center">
                                    <div class="mr-3 mb-2">
                                        <strong>Operazioni in lotto:</strong>
                                    </div>
                                    <button id="selectAllBtn" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-check-double"></i> Seleziona Tutti
                                    </button>
                                    <button id="deselectAllBtn" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-times"></i> Deseleziona Tutti
                                    </button>
                                    <button id="updateAziendaBtn" class="btn btn-sm btn-warning d-none">
                                        <i class="fas fa-building"></i> Modifica Azienda
                                    </button>
                                    <button id="updateSedeBtn" class="btn btn-sm btn-info d-none">
                                        <i class="fas fa-map-marker-alt"></i> Modifica Sede
                                    </button>
                                    <button id="updateCcnlBtn" class="btn btn-sm btn-primary d-none">
                                        <i class="fas fa-file-contract"></i> Modifica CCNL
                                    </button>
                                    <button id="updateSettoreBtn" class="btn btn-sm btn-success d-none">
                                        <i class="fas fa-industry"></i> Modifica Settore
                                    </button>
                                    <button id="updateTipoTesseraBtn" class="btn btn-sm btn-dark d-none">
                                        <i class="fas fa-id-card"></i> Modifica Tipo Tessera
                                    </button>
                                    <button id="archiveWorkersBtn" class="btn btn-sm btn-secondary d-none">
                                        <i class="fas fa-archive"></i> Archivia
                                    </button>
                                    <button id="deleteWorkersBtn" class="btn btn-sm btn-danger d-none">
                                        <i class="fas fa-trash-alt"></i> Elimina
                                    </button>
                                </div>
                            </div>

                            <!-- Tabella principale - COGNOME PRIMA DI NOME -->
                            <div class="table-responsive">
                                <table id="lavoratoriTable" class="table table-bordered table-striped table-hover w-100">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 40px;">
                                                <input type="checkbox" id="selectAllVisible">
                                            </th>
                                            <th>Cognome</th>
                                            <th>Nome</th>
                                            <th>Sede</th>
                                            <th>Azienda</th>
                                            <th>Unità Operativa</th>
                                            <th>Paese Nascita</th>
                                            <th>Vertenza</th>
                                            <th>Settore</th>
                                            <th>Ruolo Sind.</th>
                                            <th>Stato Iscrizione</th>
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
    
    <!-- Modal per aggiornamento azienda -->
    <div class="modal fade" id="updateAziendaModal" tabindex="-1" role="dialog" aria-labelledby="updateAziendaModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateAziendaModalLabel">
                        <i class="fas fa-building"></i> Modifica Azienda Lavoratori Selezionati
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="updateAziendaFormModal">
                        <div class="form-group">
                            <label for="newAziendaSelect">Seleziona la nuova azienda</label>
                            <select id="newAziendaSelect" name="new_azienda_id" class="form-control" required>
                                <option value="">Seleziona...</option>
                                <?php foreach ($aziendeList as $azienda): ?>
                                    <option value="<?php echo sanitizeForHTML($azienda['id']); ?>">
                                        <?php echo sanitizeForHTML($azienda['nome_azienda']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="csrf_token_modal_azienda" value="<?php echo $_SESSION['csrf_token']; ?>">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Annulla
                    </button>
                    <button type="button" id="confirmUpdateAzienda" class="btn btn-primary">
                        <i class="fas fa-check"></i> Conferma Cambio Azienda
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal per aggiornamento sede -->
    <div class="modal fade" id="updateSedeModal" tabindex="-1" role="dialog" aria-labelledby="updateSedeModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateSedeModalLabel">
                        <i class="fas fa-map-marker-alt"></i> Modifica Sede Lavoratori Selezionati
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="updateSedeFormModal">
                        <div class="form-group">
                            <label for="newSedeSelect">Seleziona la nuova sede</label>
                            <select id="newSedeSelect" name="new_sede_id" class="form-control">
                                <option value="0">Nessuna sede (rimuovi)</option>
                                <?php foreach ($sediList as $sede_item): ?>
                                    <option value="<?php echo sanitizeForHTML($sede_item['id']); ?>">
                                        <?php echo sanitizeForHTML($sede_item['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="csrf_token_modal_sede" value="<?php echo $_SESSION['csrf_token']; ?>">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Annulla
                    </button>
                    <button type="button" id="confirmUpdateSede" class="btn btn-primary">
                        <i class="fas fa-check"></i> Conferma Cambio Sede
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal per aggiornamento CCNL -->
    <div class="modal fade" id="updateCcnlModal" tabindex="-1" role="dialog" aria-labelledby="updateCcnlModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateCcnlModalLabel">
                        <i class="fas fa-file-contract"></i> Modifica CCNL Lavoratori Selezionati
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="updateCcnlFormModal">
                        <div class="form-group">
                            <label for="newCcnlSelect">Seleziona il nuovo CCNL</label>
                            <select id="newCcnlSelect" name="new_ccnl" class="form-control" required>
                                <option value="">Seleziona...</option>
                                <?php foreach ($ccnlList as $ccnl): ?>
                                    <option value="<?= sanitizeForHTML($ccnl) ?>">
                                        <?= sanitizeForHTML($ccnl) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="csrf_token_modal_ccnl" value="<?php echo $_SESSION['csrf_token']; ?>">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Annulla
                    </button>
                    <button type="button" id="confirmUpdateCcnl" class="btn btn-primary">
                        <i class="fas fa-check"></i> Conferma Cambio CCNL
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal per aggiornamento Settore -->
    <div class="modal fade" id="updateSettoreModal" tabindex="-1" role="dialog" aria-labelledby="updateSettoreModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateSettoreModalLabel">
                        <i class="fas fa-industry"></i> Modifica Settore Lavoratori Selezionati
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="updateSettoreFormModal">
                        <div class="form-group">
                            <label for="newSettoreSelect">Seleziona il nuovo Settore</label>
                            <select id="newSettoreSelect" name="new_settore" class="form-control" required>
                                <option value="">Seleziona...</option>
                                <option value="pubblico">Pubblico</option>
                                <option value="privato">Privato</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Annulla
                    </button>
                    <button type="button" id="confirmUpdateSettore" class="btn btn-success">
                        <i class="fas fa-check"></i> Conferma Cambio Settore
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal per aggiornamento Tipo Tessera -->
    <div class="modal fade" id="updateTipoTesseraModal" tabindex="-1" role="dialog" aria-labelledby="updateTipoTesseraModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateTipoTesseraModalLabel">
                        <i class="fas fa-id-card"></i> Modifica Tipo Tessera Lavoratori Selezionati
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="updateTipoTesseraFormModal">
                        <div class="form-group">
                            <label for="newTipoTesseraSelect">Seleziona il nuovo Tipo Tessera</label>
                            <select id="newTipoTesseraSelect" name="new_tipo_tessera" class="form-control" required>
                                <option value="">Seleziona...</option>
                                <option value="rinnovo annuale">Rinnovo Annuale</option>
                                <option value="trattenuta in busta paga">Trattenuta in Busta Paga</option>
                                <option value="sepa">SEPA</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Annulla
                    </button>
                    <button type="button" id="confirmUpdateTipoTessera" class="btn btn-dark">
                        <i class="fas fa-check"></i> Conferma Cambio Tipo Tessera
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll to Top Button -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Scripts -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>

    <!-- DataTables Scripts -->
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/responsive/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/responsive/responsive.bootstrap4.min.js"></script>
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/buttons/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/buttons/buttons.bootstrap4.min.js"></script>
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jszip/jszip.min.js"></script>
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/pdfmake/pdfmake.min.js"></script>
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/pdfmake/vfs_fonts.js"></script>
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/buttons/buttons.html5.min.js"></script>
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/buttons/buttons.print.min.js"></script>
    <script type="text/javascript" src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/extensions/select/dataTables.select.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.js"></script>

    <script>
        $(document).ready(function() {
            var selectedWorkers = {};
            var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
            var table;
            var columnFiltersVisible = false;
            var customFiltersVisible = true;
            
            // Funzione per ottenere parametri URL
            function getUrlParameters() {
                var params = {};
                var urlSearchParams = new URLSearchParams(window.location.search);
                for (var pair of urlSearchParams.entries()) {
                    params[pair[0]] = pair[1];
                }
                return params;
            }
            
            // Inizializza filtri da URL
            function initializeFiltersFromUrl() {
                var urlParams = getUrlParameters();
                
                // Gestisce sia azienda_filter che azienda_id
                var aziendaId = urlParams.azienda_filter || urlParams.azienda_id;
                if (aziendaId) {
                    $('#azienda_filter').val(aziendaId);
                    loadUnitaOperativa(aziendaId, urlParams.unita_operativa_filter);
                }
                if (urlParams.sede_filter) {
                    $('#sede_filter').val(urlParams.sede_filter);
                }
                if (urlParams.paese_nascita_filter) {
                    $('#paese_nascita_filter').val(urlParams.paese_nascita_filter);
                }
                if (urlParams.iscritto_filter) {
                    $('#iscritto_filter').val(urlParams.iscritto_filter);
                }
                if (urlParams.vertenza_filter) {
                    $('#vertenza_filter').val(urlParams.vertenza_filter);
                }
                if (urlParams.settore_filter) {
                    $('#settore_filter').val(urlParams.settore_filter);
                }
                if (urlParams.tipo_tessera_filter) {
                    $('#tipo_tessera_filter').val(urlParams.tipo_tessera_filter);
                }
                if (urlParams.ruolo_filter) {
                    $('#ruolo_filter').val(urlParams.ruolo_filter);
                }
                if (urlParams.ccnl_filter) {
                    $('#ccnl_filter').val(urlParams.ccnl_filter);
                }
            }
            
            // Inizializza filtri da URL prima di creare la DataTable
            initializeFiltersFromUrl();
            
            // Inizializzazione DataTable - ORDINE COLONNE: COGNOME, NOME
            table = $('#lavoratoriTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'lavoratori.php',
                    type: 'GET',
                    data: function(d) {
                        d.datatables_ajax = 1;
                        d.azienda_filter = $('#azienda_filter').val();
                        d.unita_operativa_filter = $('#unita_operativa_filter').val();
                        d.paese_nascita_filter = $('#paese_nascita_filter').val();
                        d.sede_filter = $('#sede_filter').val();
                        d.iscritto_filter = $('#iscritto_filter').val();
                        d.vertenza_filter = $('#vertenza_filter').val();
                        d.settore_filter = $('#settore_filter').val();
                        d.tipo_tessera_filter = $('#tipo_tessera_filter').val();
                        d.ruolo_filter = $('#ruolo_filter').val();
                        d.ccnl_filter = $('#ccnl_filter').val();
                    }
                },
                columns: [
                    {
                        data: 0,
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        responsivePriority: 1,
                        exportOptions: { orthogonal: "export" }
                    },
                    {
                        data: 1,
                        name: 'cognome',
                        responsivePriority: 2,
                        exportOptions: { orthogonal: "export" },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.find('a').first().text() || temp.text();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 2, 
                        name: 'nome',
                        responsivePriority: 2,
                        exportOptions: { orthogonal: "export" },
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
                        name: 'sede',
                        responsivePriority: 7,
                        exportOptions: { orthogonal: "export" },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.text().replace('Non assegnata', '').trim();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 4, 
                        name: 'azienda',
                        responsivePriority: 3,
                        exportOptions: { orthogonal: "export" },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.find('a').first().text() || temp.text();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 5, 
                        name: 'unita_operativa',
                        responsivePriority: 6,
                        exportOptions: { orthogonal: "export" },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.find('a').first().text() || temp.text();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 6, 
                        name: 'paese_nascita',
                        responsivePriority: 8
                    },
                    { 
                        data: 7, 
                        name: 'vertenza',
                        responsivePriority: 9,
                        exportOptions: { orthogonal: "export" },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.text();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 8, 
                        name: 'settore',
                        responsivePriority: 5,
                        exportOptions: { orthogonal: "export" },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.text();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 9,
                        name: 'ruolo',
                        responsivePriority: 10,
                        exportOptions: { orthogonal: "export" },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.text();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 10, 
                        name: 'stato_iscrizione',
                        responsivePriority: 4,
                        exportOptions: { orthogonal: "export" },
                        render: function(data, type, row) {
                            if (type === 'export') {
                                var temp = $('<div>').html(data);
                                return temp.text();
                            }
                            return data;
                        }
                    },
                    { 
                        data: 11, 
                        orderable: false, 
                        searchable: false,
                        className: 'text-center',
                        responsivePriority: 1,
                        exportOptions: { orthogonal: "export" }
                    }
                ],
                order: [[1, 'asc']], // Ordina per cognome (colonna 1)
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, 200, -1], [10, 25, 50, 100, 200, "Tutti"]],
                responsive: {
                    details: {
                        type: 'column',
                        target: 'tr'
                    }
                },
                language: {
                    url: '<?php echo sanitizeForHTML($base_url); ?>theme/vendor/datatables/i18n-it-IT.json'
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
                            columns: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger btn-sm',
                        orientation: 'landscape',
                        exportOptions: {
                            columns: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
                        }
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        className: 'btn btn-info btn-sm',
                        exportOptions: {
                            columns: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Stampa',
                        className: 'btn btn-secondary btn-sm',
                        exportOptions: {
                            columns: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
                        }
                    }
                ],
                initComplete: function() {
                    addColumnFilters();
                },
                drawCallback: function() {
                    reapplySelections();
                }
            });

            // Se c'è un parametro "search" nell'URL, applica la ricerca alla DataTable
            var urlSearchParam = getUrlParameters().search;
            if (urlSearchParam) {
                table.search(urlSearchParam).draw();
                // Popola anche il campo di ricerca della DataTable
                $('div.dataTables_filter input').val(urlSearchParam);
            }

            // Funzione per caricare unità operative
            function loadUnitaOperativa(azienda_id, selected_unita_id = null) {
                var unitaSelect = $('#unita_operativa_filter');
                if (azienda_id) {
                    $.ajax({
                        url: 'autocomplete_unita_operativa.php',
                        type: 'GET',
                        dataType: 'json',
                        data: { azienda_id: azienda_id },
                        success: function(data){
                            unitaSelect.empty().append('<option value="">Tutte le unità operative</option>');
                            $.each(data, function(index, unita){
                                var selected = (unita.id == selected_unita_id) ? 'selected' : '';
                                unitaSelect.append('<option value="'+ unita.id +'" '+ selected +'>'+ unita.label +'</option>');
                            });
                            unitaSelect.prop('disabled', false);
                        },
                        error: function(){
                            unitaSelect.prop('disabled', true).empty().append('<option value="">Errore caricamento</option>'); 
                        }
                    });
                } else {
                    unitaSelect.prop('disabled', true).empty().append('<option value="">Tutte le unità operative</option>');
                }
            }
            
            // Gestione cambio azienda
            $('#azienda_filter').on('change', function(){
                loadUnitaOperativa($(this).val());
            });
            
            // Funzione per aggiungere filtri per colonna
            function addColumnFilters() {
                $('#lavoratoriTable thead tr').clone(true).addClass('column-filters').appendTo('#lavoratoriTable thead');
                $('#lavoratoriTable thead tr:eq(1) th').each(function(i) {
                    var title = $(this).text();
                    
                    if (i === 0 || i === 11) {
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
            
            // Toggle filtri custom
            $('#toggleFilters').on('click', function() {
                customFiltersVisible = !customFiltersVisible;
                $('#filtersContent').slideToggle();
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
                $('.column-filters').toggle();
                var icon = columnFiltersVisible ? 'fa-eye-slash' : 'fa-filter';
                var text = columnFiltersVisible ? 'Nascondi Filtri Colonna' : 'Mostra Filtri Colonna';
                $(this).find('i').removeClass('fa-filter fa-eye-slash').addClass(icon);
                $(this).find('span').text(text);
            });
            
            // Applica filtri custom
            $('#applyFilters').on('click', function() {
                table.ajax.reload();
            });
            
            // Reset filtri custom
            $('#resetCustomFilters').on('click', function() {
                $('#customFilterForm')[0].reset();
                $('#unita_operativa_filter').prop('disabled', true).empty().append('<option value="">Tutte le unità operative</option>');
                var newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
                table.ajax.reload();
            });
            
            // Reset tutti i filtri
            $('#resetAllFilters').on('click', function(e) {
                e.preventDefault();
                $('#customFilterForm')[0].reset();
                $('#unita_operativa_filter').prop('disabled', true).empty().append('<option value="">Tutte le unità operative</option>');
                table.search('').columns().search('').draw();
                $('.column-filter').val('');
                var newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            });
            
            // Filtri custom con change automatico
            $('#customFilterForm select').on('change', function() {
                table.ajax.reload();
            });
            
            // Gestione selezione checkbox
            function toggleBulkActionButtons() {
                var count = Object.keys(selectedWorkers).length;
                if (count > 0) {
                    $('#updateAziendaBtn, #updateSedeBtn, #updateCcnlBtn, #updateSettoreBtn, #updateTipoTesseraBtn, #archiveWorkersBtn, #deleteWorkersBtn').removeClass('d-none');
                } else {
                    $('#updateAziendaBtn, #updateSedeBtn, #updateCcnlBtn, #updateSettoreBtn, #updateTipoTesseraBtn, #archiveWorkersBtn, #deleteWorkersBtn').addClass('d-none');
                }
            }
            
            function reapplySelections() {
                $('.worker-checkbox').each(function() {
                    var id = $(this).val();
                    $(this).prop('checked', selectedWorkers[id] || false);
                });
                
                var visibleCheckboxes = $('.worker-checkbox');
                var checkedVisible = visibleCheckboxes.filter(':checked').length;
                $('#selectAllVisible').prop('checked', visibleCheckboxes.length > 0 && checkedVisible === visibleCheckboxes.length);
            }
            
            // Selezione individuale
            $(document).on('change', '.worker-checkbox', function() {
                var id = $(this).val();
                if ($(this).is(':checked')) {
                    selectedWorkers[id] = true;
                } else {
                    delete selectedWorkers[id];
                }
                toggleBulkActionButtons();
                reapplySelections();
            });
            
            // Seleziona tutti visibili
            $('#selectAllVisible').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.worker-checkbox').each(function() {
                    $(this).prop('checked', isChecked);
                    var id = $(this).val();
                    if (isChecked) {
                        selectedWorkers[id] = true;
                    } else {
                        delete selectedWorkers[id];
                    }
                });
                toggleBulkActionButtons();
            });
            
            // Seleziona tutti
            $('#selectAllBtn').on('click', function() {
                Swal.fire({
                    title: 'Conferma selezione',
                    text: 'Vuoi selezionare tutti i lavoratori (inclusi quelli filtrati)?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sì, seleziona tutti',
                    cancelButtonText: 'Annulla'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('.worker-checkbox').each(function() {
                            selectedWorkers[$(this).val()] = true;
                        });
                        toggleBulkActionButtons();
                        reapplySelections();
                        Swal.fire('Selezionati!', 'Tutti i lavoratori visibili sono stati selezionati.', 'success');
                    }
                });
            });
            
            // Deseleziona tutti
            $('#deselectAllBtn').on('click', function() {
                selectedWorkers = {};
                toggleBulkActionButtons();
                $('.worker-checkbox').prop('checked', false);
                $('#selectAllVisible').prop('checked', false);
            });
            
            // Modifica Azienda
            $('#updateAziendaBtn').on('click', function() {
                if (Object.keys(selectedWorkers).length === 0) {
                    Swal.fire('Attenzione', 'Seleziona almeno un lavoratore.', 'warning');
                    return;
                }
                $('#updateAziendaModal').modal('show');
            });
            
            $('#confirmUpdateAzienda').on('click', function() {
                var newAziendaId = $('#newAziendaSelect').val();
                if (newAziendaId === '') {
                    Swal.fire('Attenzione', 'Seleziona una nuova azienda.', 'warning');
                    return;
                }
                
                var worker_ids_array = Object.keys(selectedWorkers);
                var postData = {
                    update_azienda: 1,
                    new_azienda_id: newAziendaId,
                    csrf_token: csrfToken,
                    worker_ids: worker_ids_array
                };
                
                $.ajax({
                    url: 'lavoratori.php',
                    type: 'POST',
                    dataType: 'json',
                    data: postData,
                    success: function(response) {
                        handleBulkActionResponse(response, 'Azienda modificata con successo.');
                        $('#updateAziendaModal').modal('hide');
                    },
                    error: function() {
                        Swal.fire('Errore', 'Si è verificato un errore durante l\'aggiornamento.', 'error');
                    }
                });
            });
            
            // Modifica Sede
            $('#updateSedeBtn').on('click', function() {
                if (Object.keys(selectedWorkers).length === 0) {
                    Swal.fire('Attenzione', 'Seleziona almeno un lavoratore.', 'warning');
                    return;
                }
                $('#updateSedeModal').modal('show');
            });
            
            $('#confirmUpdateSede').on('click', function() {
                var newSedeId = $('#newSedeSelect').val();
                var worker_ids_array = Object.keys(selectedWorkers);
                var postData = {
                    update_sede: 1,
                    new_sede_id: newSedeId,
                    csrf_token: csrfToken,
                    worker_ids: worker_ids_array
                };

                $.ajax({
                    url: 'lavoratori.php',
                    type: 'POST',
                    dataType: 'json',
                    data: postData,
                    success: function(response) {
                        handleBulkActionResponse(response, 'Sede modificata con successo.');
                        $('#updateSedeModal').modal('hide');
                    },
                    error: function() {
                        Swal.fire('Errore', 'Si è verificato un errore durante l\'aggiornamento.', 'error');
                    }
                });
            });

            // Modifica CCNL
            $('#updateCcnlBtn').on('click', function() {
                if (Object.keys(selectedWorkers).length === 0) {
                    Swal.fire('Attenzione', 'Seleziona almeno un lavoratore.', 'warning');
                    return;
                }
                $('#updateCcnlModal').modal('show');
            });

            $('#confirmUpdateCcnl').on('click', function() {
                var newCcnl = $('#newCcnlSelect').val();
                if (newCcnl === '') {
                    Swal.fire('Attenzione', 'Seleziona un CCNL.', 'warning');
                    return;
                }

                var worker_ids_array = Object.keys(selectedWorkers);
                var postData = {
                    update_ccnl: 1,
                    new_ccnl: newCcnl,
                    csrf_token: csrfToken,
                    worker_ids: worker_ids_array
                };

                $.ajax({
                    url: 'lavoratori.php',
                    type: 'POST',
                    dataType: 'json',
                    data: postData,
                    success: function(response) {
                        handleBulkActionResponse(response, 'CCNL modificato con successo.');
                        $('#updateCcnlModal').modal('hide');
                    },
                    error: function() {
                        Swal.fire('Errore', 'Si è verificato un errore durante l\'aggiornamento.', 'error');
                    }
                });
            });

            // Modifica Settore
            $('#updateSettoreBtn').on('click', function() {
                if (Object.keys(selectedWorkers).length === 0) {
                    Swal.fire('Attenzione', 'Seleziona almeno un lavoratore.', 'warning');
                    return;
                }
                $('#updateSettoreModal').modal('show');
            });

            $('#confirmUpdateSettore').on('click', function() {
                var newSettore = $('#newSettoreSelect').val();
                if (newSettore === '') {
                    Swal.fire('Attenzione', 'Seleziona un settore.', 'warning');
                    return;
                }

                var worker_ids_array = Object.keys(selectedWorkers);
                var postData = {
                    update_settore: 1,
                    new_settore: newSettore,
                    csrf_token: csrfToken,
                    worker_ids: worker_ids_array
                };

                $.ajax({
                    url: 'lavoratori.php',
                    type: 'POST',
                    dataType: 'json',
                    data: postData,
                    success: function(response) {
                        handleBulkActionResponse(response, 'Settore modificato con successo.');
                        $('#updateSettoreModal').modal('hide');
                    },
                    error: function() {
                        Swal.fire('Errore', 'Si è verificato un errore durante l\'aggiornamento.', 'error');
                    }
                });
            });

            // Modifica Tipo Tessera
            $('#updateTipoTesseraBtn').on('click', function() {
                if (Object.keys(selectedWorkers).length === 0) {
                    Swal.fire('Attenzione', 'Seleziona almeno un lavoratore.', 'warning');
                    return;
                }
                $('#updateTipoTesseraModal').modal('show');
            });

            $('#confirmUpdateTipoTessera').on('click', function() {
                var newTipoTessera = $('#newTipoTesseraSelect').val();
                if (newTipoTessera === '') {
                    Swal.fire('Attenzione', 'Seleziona un tipo tessera.', 'warning');
                    return;
                }

                var worker_ids_array = Object.keys(selectedWorkers);
                var postData = {
                    update_tipo_tessera: 1,
                    new_tipo_tessera: newTipoTessera,
                    csrf_token: csrfToken,
                    worker_ids: worker_ids_array
                };

                $.ajax({
                    url: 'lavoratori.php',
                    type: 'POST',
                    dataType: 'json',
                    data: postData,
                    success: function(response) {
                        handleBulkActionResponse(response, 'Tipo tessera modificato con successo.');
                        $('#updateTipoTesseraModal').modal('hide');
                    },
                    error: function() {
                        Swal.fire('Errore', 'Si è verificato un errore durante l\'aggiornamento.', 'error');
                    }
                });
            });

            // Archivia lavoratori
            $('#archiveWorkersBtn').on('click', function() {
                confirmAndExecuteBulkAction(
                    'archive_workers',
                    'Confermi l\'archiviazione di ' + Object.keys(selectedWorkers).length + ' lavoratori selezionati?',
                    'Sì, archivia!',
                    'Lavoratori archiviati con successo.'
                );
            });
            
            // Elimina lavoratori
            $('#deleteWorkersBtn').on('click', function() {
                confirmAndExecuteBulkAction(
                    'delete_workers',
                    'ATTENZIONE! Sei sicuro di voler eliminare definitivamente ' + Object.keys(selectedWorkers).length + ' lavoratori selezionati? Questa operazione è IRREVERSIBILE e cancellerà anche tutti i documenti e le iscrizioni associate.',
                    'Sì, elimina!',
                    'Lavoratori eliminati con successo.',
                    'error'
                );
            });
            
            function confirmAndExecuteBulkAction(action, title, confirmButtonText, successMessage, iconType = 'warning') {
                if (Object.keys(selectedWorkers).length === 0) {
                    Swal.fire('Attenzione', 'Seleziona almeno un lavoratore.', 'warning');
                    return;
                }
                
                Swal.fire({
                    title: title,
                    icon: iconType,
                    showCancelButton: true,
                    confirmButtonText: confirmButtonText,
                    cancelButtonText: 'Annulla',
                    confirmButtonColor: (action === 'delete_workers' ? '#d33' : '#3085d6'),
                    cancelButtonColor: '#aaa',
                }).then((result) => {
                    if (result.isConfirmed) {
                        var worker_ids_array = Object.keys(selectedWorkers);
                        var postData = {
                            csrf_token: csrfToken,
                            worker_ids: worker_ids_array
                        };
                        postData[action] = 1;
                        
                        $.ajax({
                            url: 'lavoratori.php',
                            type: 'POST',
                            dataType: 'json',
                            data: postData,
                            success: function(response) {
                                handleBulkActionResponse(response, successMessage);
                            },
                            error: function() {
                                Swal.fire('Errore', 'Si è verificato un errore durante l\'operazione.', 'error');
                            }
                        });
                    }
                });
            }
            
            function handleBulkActionResponse(response, successMsgDefault) {
                if (response.error) {
                    Swal.fire('Errore', response.error, 'error');
                } else {
                    Swal.fire('Successo', response.success || successMsgDefault, 'success');
                    selectedWorkers = {};
                    toggleBulkActionButtons();
                    table.ajax.reload(null, false);
                }
            }
            
            // Inizializzazione
            toggleBulkActionButtons();
        });
    </script>

    <!-- Modal Importazione Lavoratori -->
    <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="importModalLabel">
                        <i class="fas fa-file-import"></i> Importa Lavoratori da File
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Chiudi">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Step 1: Selezione file -->
                    <div id="importStep1">
                        <div class="mb-3">
                            <p class="text-muted mb-2">Formati supportati: <strong>CSV</strong>, <strong>XLS</strong>, <strong>XLSX</strong></p>
                            <p class="text-muted small">
                                Il file deve avere un header nella prima riga. Le colonne vengono mappate automaticamente
                                (es: "cognome", "nome", "codice fiscale", "telefono", "azienda", "settore"...).
                            </p>
                            <a href="templates/esempio_import_lavoratori.csv" download class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download"></i> Scarica file di esempio (.csv)
                            </a>
                        </div>
                        <div class="custom-file mb-3">
                            <input type="file" class="custom-file-input" id="importFileInput" accept=".csv,.xls,.xlsx">
                            <label class="custom-file-label" for="importFileInput" data-browse="Sfoglia">Scegli file...</label>
                        </div>
                        <div id="importFileInfo" class="d-none mb-3">
                            <div class="alert alert-info mb-0 py-2">
                                <i class="fas fa-file-alt"></i>
                                <span id="importFileName"></span>
                                <span class="badge badge-secondary ml-2" id="importFileSize"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Progresso -->
                    <div id="importStep2" class="d-none">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="sr-only">Importazione in corso...</span>
                            </div>
                            <p class="text-muted">Importazione in corso, attendere...</p>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Risultati -->
                    <div id="importStep3" class="d-none">
                        <div id="importResultIcon" class="text-center mb-3"></div>
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <div class="h4 mb-0 text-success" id="importInserted">0</div>
                                    <small class="text-muted">Inseriti</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <div class="h4 mb-0 text-warning" id="importSkipped">0</div>
                                    <small class="text-muted">Saltati</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <div class="h4 mb-0 text-primary" id="importTotal">0</div>
                                    <small class="text-muted">Totale righe</small>
                                </div>
                            </div>
                        </div>

                        <!-- Campi mappati -->
                        <div id="importMappedFieldsContainer" class="d-none mb-3">
                            <h6 class="font-weight-bold text-primary"><i class="fas fa-columns"></i> Colonne mappate:</h6>
                            <div id="importMappedFields" class="small"></div>
                        </div>

                        <!-- Errori/avvisi -->
                        <div id="importErrorsContainer" class="d-none mb-3">
                            <h6 class="font-weight-bold text-danger"><i class="fas fa-exclamation-triangle"></i> Dettagli:</h6>
                            <div id="importErrors" class="small" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" id="importCloseBtn">Chiudi</button>
                    <button type="button" class="btn btn-primary" id="importStartBtn" disabled>
                        <i class="fas fa-upload"></i> Avvia Importazione
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(function() {
        var $fileInput = $('#importFileInput');
        var $startBtn = $('#importStartBtn');
        var $closeBtn = $('#importCloseBtn');

        // Aggiorna label del file selezionato
        $fileInput.on('change', function() {
            var file = this.files[0];
            if (file) {
                $(this).next('.custom-file-label').text(file.name);
                var sizeKB = (file.size / 1024).toFixed(1);
                var sizeLabel = sizeKB > 1024 ? (sizeKB / 1024).toFixed(1) + ' MB' : sizeKB + ' KB';
                $('#importFileName').text(file.name);
                $('#importFileSize').text(sizeLabel);
                $('#importFileInfo').removeClass('d-none');
                $startBtn.prop('disabled', false);
            } else {
                $(this).next('.custom-file-label').text('Scegli file...');
                $('#importFileInfo').addClass('d-none');
                $startBtn.prop('disabled', true);
            }
        });

        // Reset modale alla chiusura
        $('#importModal').on('hidden.bs.modal', function() {
            $fileInput.val('').next('.custom-file-label').text('Scegli file...');
            $('#importFileInfo').addClass('d-none');
            $startBtn.prop('disabled', true).show();
            $('#importStep1').removeClass('d-none');
            $('#importStep2, #importStep3').addClass('d-none');
        });

        // Avvia importazione
        $startBtn.on('click', function() {
            var file = $fileInput[0].files[0];
            if (!file) return;

            // Mostra progresso
            $('#importStep1').addClass('d-none');
            $('#importStep2').removeClass('d-none');
            $startBtn.hide();
            $closeBtn.prop('disabled', true);

            var formData = new FormData();
            formData.append('import_file', file);
            formData.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');

            $.ajax({
                url: 'import_lavoratori_ajax.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                timeout: 120000,
                success: function(resp) {
                    $('#importStep2').addClass('d-none');
                    $('#importStep3').removeClass('d-none');
                    $closeBtn.prop('disabled', false);

                    if (resp.success) {
                        var icon = resp.inserted > 0
                            ? '<i class="fas fa-check-circle text-success fa-3x"></i><p class="mt-2 font-weight-bold text-success">Importazione completata</p>'
                            : '<i class="fas fa-info-circle text-warning fa-3x"></i><p class="mt-2 font-weight-bold text-warning">Nessun nuovo lavoratore inserito</p>';
                        $('#importResultIcon').html(icon);
                        $('#importInserted').text(resp.inserted);
                        $('#importSkipped').text(resp.skipped);
                        $('#importTotal').text(resp.total_rows);

                        // Campi mappati
                        if (resp.mapped_fields && resp.mapped_fields.length > 0) {
                            var badges = resp.mapped_fields.map(function(f) {
                                return '<span class="badge badge-light border mr-1 mb-1">' + $('<span>').text(f).html() + '</span>';
                            }).join('');
                            $('#importMappedFields').html(badges);
                            $('#importMappedFieldsContainer').removeClass('d-none');
                        }

                        // Errori
                        if (resp.errors && resp.errors.length > 0) {
                            var errHtml = resp.errors.map(function(e) {
                                return '<div class="text-danger small"><i class="fas fa-exclamation-circle"></i> ' + $('<span>').text(e).html() + '</div>';
                            }).join('');
                            $('#importErrors').html(errHtml);
                            $('#importErrorsContainer').removeClass('d-none');
                        }

                        // Ricarica tabella se ci sono inserimenti
                        if (resp.inserted > 0 && typeof table !== 'undefined') {
                            table.ajax.reload(null, false);
                        }
                    } else {
                        $('#importResultIcon').html(
                            '<i class="fas fa-times-circle text-danger fa-3x"></i>' +
                            '<p class="mt-2 font-weight-bold text-danger">' + $('<span>').text(resp.error || 'Errore sconosciuto').html() + '</p>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $('#importStep2').addClass('d-none');
                    $('#importStep3').removeClass('d-none');
                    $closeBtn.prop('disabled', false);

                    var msg = 'Errore di connessione.';
                    if (status === 'timeout') msg = 'Timeout: il file potrebbe essere troppo grande.';
                    else if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;

                    $('#importResultIcon').html(
                        '<i class="fas fa-times-circle text-danger fa-3x"></i>' +
                        '<p class="mt-2 font-weight-bold text-danger">' + $('<span>').text(msg).html() + '</p>'
                    );
                }
            });
        });
    });
    </script>
</body>
</html>