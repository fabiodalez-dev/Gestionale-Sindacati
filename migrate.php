<?php
/**
 * migrate.php - Database Migration System
 *
 * Esegue migrazioni SQL per aggiornare lo schema del database.
 * Le migrazioni vengono tracciate nella tabella `migrations`.
 *
 * Uso: Accedere a questa pagina come admin per eseguire le migrazioni pendenti.
 */
require_once 'config.php';
checkLogin();
checkUserRole('admin');

generateCsrfToken();

// Crea la tabella migrations se non esiste
$mysqli->query("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL UNIQUE,
        executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Directory delle migrazioni
$migrations_dir = __DIR__ . '/migrations';
if (!is_dir($migrations_dir)) {
    mkdir($migrations_dir, 0755, true);
}

// Recupera le migrazioni già eseguite
$executed = [];
$result = $mysqli->query("SELECT migration_name FROM migrations ORDER BY id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $executed[] = $row['migration_name'];
    }
}

// Trova i file di migrazione
$migration_files = glob($migrations_dir . '/*.sql');
sort($migration_files);

$pending = [];
foreach ($migration_files as $file) {
    $name = basename($file);
    if (!in_array($name, $executed)) {
        $pending[] = $name;
    }
}

$messages = [];
$errors_list = [];

// Esegui le migrazioni se richiesto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors_list[] = "Token CSRF non valido.";
    } else {
        foreach ($pending as $migration_name) {
            $file_path = $migrations_dir . '/' . $migration_name;
            $sql = file_get_contents($file_path);
            if ($sql === false) {
                $errors_list[] = "Impossibile leggere il file: $migration_name";
                continue;
            }

            // Esegui le query multiple nel file di migrazione
            $mysqli->begin_transaction();
            try {
                if ($mysqli->multi_query($sql)) {
                    // Consuma tutti i risultati delle query multiple
                    do {
                        if ($result = $mysqli->store_result()) {
                            $result->free();
                        }
                    } while ($mysqli->next_result());
                }

                if ($mysqli->errno) {
                    throw new Exception($mysqli->error);
                }

                // Registra la migrazione come eseguita
                $stmt = $mysqli->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
                $stmt->bind_param('s', $migration_name);
                $stmt->execute();
                $stmt->close();

                $mysqli->commit();
                $messages[] = "Migrazione eseguita: $migration_name";
            } catch (Exception $e) {
                $mysqli->rollback();
                $errors_list[] = "Errore nella migrazione $migration_name: " . $e->getMessage();
                break; // Stop al primo errore
            }
        }

        // Ricarica le migrazioni pendenti
        $executed = [];
        $result = $mysqli->query("SELECT migration_name FROM migrations ORDER BY id");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $executed[] = $row['migration_name'];
            }
        }
        $pending = [];
        foreach ($migration_files as $file) {
            $name = basename($file);
            if (!in_array($name, $executed)) {
                $pending[] = $name;
            }
        }
    }
}

// ── Ottimizzazione Indici ──────────────────────────────────────────────

// Definizione indici raccomandati: [tabella, nome_indice, colonne, descrizione]
$recommended_indexes = [
    // LAVORATORI - Alta priorità
    ['lavoratori', 'idx_lav_archiviato', 'archiviato', 'Filtro archiviati/attivi (usato nel 90% delle query)'],
    ['lavoratori', 'idx_lav_iscritto', 'iscritto', 'Filtro iscritti/non iscritti (dashboard, report)'],
    ['lavoratori', 'idx_lav_tipo_tessera', 'tipo_tessera', 'Filtro tipo tessera (gestione iscrizioni, promemoria)'],
    ['lavoratori', 'idx_lav_archiviato_azienda', 'archiviato, azienda_id', 'Composito per liste lavoratori per azienda'],
    ['lavoratori', 'idx_lav_cognome_nome', 'cognome, nome', 'Ricerca e ordinamento per nome'],
    // LAVORATORI - Media priorità
    ['lavoratori', 'idx_lav_settore', 'settore', 'Analisi per settore (dashboard)'],
    ['lavoratori', 'idx_lav_data_iscrizione', 'data_iscrizione', 'Trend iscrizioni ultimi 12 mesi'],
    ['lavoratori', 'idx_lav_genere', 'genere', 'Distribuzione per genere (dashboard)'],
    ['lavoratori', 'idx_lav_contratto', 'contratto', 'Distribuzione tipo contratto (dashboard)'],
    ['lavoratori', 'idx_lav_ccnl', 'ccnl', 'Distribuzione per CCNL (dashboard)'],
    ['lavoratori', 'idx_lav_indirizzo_citta', 'indirizzo_citta', 'Top 20 citta (dashboard)'],
    ['lavoratori', 'idx_lav_paese_nascita', 'paese_nascita', 'Top 15 nazionalita (dashboard)'],
    ['lavoratori', 'idx_lav_ruolo', 'ruolo', 'Filtro delegati RSU/RSA/RLS'],
    ['lavoratori', 'idx_lav_orario_contratto', 'orario_contratto', 'Distribuzione orario contratto (dashboard)'],
    // ISCRIZIONI
    ['iscrizioni', 'idx_isc_data_fine', 'data_fine', 'Scadenze e range query sulle iscrizioni'],
    ['iscrizioni', 'idx_isc_metodo_pagamento', 'metodo_pagamento', 'Filtro per metodo di pagamento'],
    ['iscrizioni', 'idx_isc_metodo_datafine', 'metodo_pagamento, data_fine', 'Ricerca rinnovi in scadenza'],
    // AZIENDE
    ['aziende', 'idx_az_nome', 'nome_azienda', 'Ricerca e ordinamento per nome azienda'],
];

// Recupera gli indici esistenti nel database
$existing_indexes = [];
$idx_result = $mysqli->query("
    SELECT TABLE_NAME, INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    GROUP BY TABLE_NAME, INDEX_NAME
");
if ($idx_result) {
    while ($row = $idx_result->fetch_assoc()) {
        $existing_indexes[$row['TABLE_NAME'] . '.' . $row['INDEX_NAME']] = true;
    }
}

// Calcola indici mancanti
$missing_indexes = [];
$present_indexes = [];
foreach ($recommended_indexes as $idx) {
    $key = $idx[0] . '.' . $idx[1];
    if (isset($existing_indexes[$key])) {
        $present_indexes[] = $idx;
    } else {
        $missing_indexes[] = $idx;
    }
}

$index_messages = [];
$index_errors = [];

// Esegui creazione indici se richiesto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_indexes'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $index_errors[] = "Token CSRF non valido.";
    } else {
        $created_count = 0;
        foreach ($missing_indexes as $idx) {
            $table = $idx[0];
            $index_name = $idx[1];
            $columns = $idx[2];
            $sql = "CREATE INDEX `$index_name` ON `$table` ($columns)";
            if ($mysqli->query($sql)) {
                $index_messages[] = "Indice creato: <strong>" . sanitizeForHTML($index_name) . "</strong> su " . sanitizeForHTML($table) . "(" . sanitizeForHTML($columns) . ")";
                $created_count++;
            } else {
                $index_errors[] = "Errore creazione " . sanitizeForHTML($index_name) . ": " . sanitizeForHTML($mysqli->error);
            }
        }
        if ($created_count > 0) {
            $index_messages[] = "<strong>" . (int)$created_count . " indici creati con successo.</strong>";
        }

        // Ricalcola dopo creazione
        $existing_indexes = [];
        $idx_result = $mysqli->query("
            SELECT TABLE_NAME, INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            GROUP BY TABLE_NAME, INDEX_NAME
        ");
        if ($idx_result) {
            while ($row = $idx_result->fetch_assoc()) {
                $existing_indexes[$row['TABLE_NAME'] . '.' . $row['INDEX_NAME']] = true;
            }
        }
        $missing_indexes = [];
        $present_indexes = [];
        foreach ($recommended_indexes as $idx) {
            $key = $idx[0] . '.' . $idx[1];
            if (isset($existing_indexes[$key])) {
                $present_indexes[] = $idx;
            } else {
                $missing_indexes[] = $idx;
            }
        }
    }
}
// ── Sincronizzazione Stato Iscrizioni ─────────────────────────────────

$sync_messages = [];
$sync_errors = [];

// Conta lo stato attuale per l'anteprima
$sync_preview = [];

// SEPA/Trattenuta non archiviati con iscritto = 0
$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM lavoratori WHERE tipo_tessera IN ('trattenuta in busta paga', 'sepa') AND archiviato <> 1 AND (iscritto = 0 OR iscritto IS NULL)");
$sync_preview['sepa_trattenuta_da_attivare'] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;

// Rinnovo annuale non archiviati con iscrizione valida ma iscritto = 0
$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM lavoratori l
    WHERE l.tipo_tessera = 'rinnovo annuale' AND l.archiviato <> 1 AND (l.iscritto = 0 OR l.iscritto IS NULL)
    AND EXISTS (SELECT 1 FROM iscrizioni i WHERE i.lavoratore_id = l.id AND i.data_fine >= CURDATE())");
$sync_preview['rinnovo_da_attivare'] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;

// Rinnovo annuale non archiviati con iscritto = 1 ma nessuna iscrizione valida
$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM lavoratori l
    WHERE l.tipo_tessera = 'rinnovo annuale' AND l.archiviato <> 1 AND l.iscritto = 1
    AND NOT EXISTS (SELECT 1 FROM iscrizioni i WHERE i.lavoratore_id = l.id AND i.data_fine >= CURDATE())");
$sync_preview['rinnovo_da_disattivare'] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;

// SEPA/Trattenuta non archiviati senza record in iscrizioni
$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM lavoratori l
    WHERE l.tipo_tessera IN ('trattenuta in busta paga', 'sepa') AND l.archiviato <> 1
    AND NOT EXISTS (SELECT 1 FROM iscrizioni i WHERE i.lavoratore_id = l.id)");
$sync_preview['senza_iscrizione'] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;

$sync_total_changes = $sync_preview['sepa_trattenuta_da_attivare'] + $sync_preview['rinnovo_da_attivare'] + $sync_preview['rinnovo_da_disattivare'] + $sync_preview['senza_iscrizione'];

// Esegui sincronizzazione se richiesto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_iscritti'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $sync_errors[] = "Token CSRF non valido.";
    } else {
        $mysqli->begin_transaction();
        try {
            // 1. SEPA e Trattenuta: attiva
            $q1 = $mysqli->query("UPDATE lavoratori SET iscritto = 1 WHERE tipo_tessera IN ('trattenuta in busta paga', 'sepa') AND archiviato <> 1 AND (iscritto = 0 OR iscritto IS NULL)");
            $count_attivati_sepa = $mysqli->affected_rows;

            // 2. Rinnovo annuale con iscrizione valida: attiva
            $q2 = $mysqli->query("UPDATE lavoratori l
                INNER JOIN iscrizioni i ON i.lavoratore_id = l.id AND i.data_fine >= CURDATE()
                SET l.iscritto = 1
                WHERE l.tipo_tessera = 'rinnovo annuale' AND l.archiviato <> 1 AND (l.iscritto = 0 OR l.iscritto IS NULL)");
            $count_attivati_rinnovo = $mysqli->affected_rows;

            // 3. Rinnovo annuale senza iscrizione valida: disattiva
            $q3 = $mysqli->query("UPDATE lavoratori l
                SET l.iscritto = 0
                WHERE l.tipo_tessera = 'rinnovo annuale' AND l.archiviato <> 1 AND l.iscritto = 1
                AND NOT EXISTS (SELECT 1 FROM iscrizioni i WHERE i.lavoratore_id = l.id AND i.data_fine >= CURDATE())");
            $count_disattivati_rinnovo = $mysqli->affected_rows;

            // 4. Crea record iscrizione per SEPA/Trattenuta che non ne hanno uno
            $r_missing = $mysqli->query("SELECT l.id, l.tipo_tessera FROM lavoratori l
                WHERE l.tipo_tessera IN ('trattenuta in busta paga', 'sepa') AND l.archiviato <> 1
                AND NOT EXISTS (SELECT 1 FROM iscrizioni i WHERE i.lavoratore_id = l.id)");
            $count_iscrizioni_create = 0;
            if ($r_missing) {
                $stmt_ins = $mysqli->prepare("INSERT INTO iscrizioni (lavoratore_id, metodo_pagamento, data_inizio, created_at, updated_at) VALUES (?, ?, CURDATE(), NOW(), NOW())");
                while ($row_m = $r_missing->fetch_assoc()) {
                    $stmt_ins->bind_param('is', $row_m['id'], $row_m['tipo_tessera']);
                    $stmt_ins->execute();
                    $count_iscrizioni_create++;
                }
                $stmt_ins->close();
            }

            $mysqli->commit();

            if ($count_attivati_sepa > 0) {
                $sync_messages[] = (int)$count_attivati_sepa . " lavoratori SEPA/Trattenuta attivati";
            }
            if ($count_attivati_rinnovo > 0) {
                $sync_messages[] = (int)$count_attivati_rinnovo . " lavoratori Rinnovo Annuale attivati (iscrizione valida)";
            }
            if ($count_disattivati_rinnovo > 0) {
                $sync_messages[] = (int)$count_disattivati_rinnovo . " lavoratori Rinnovo Annuale disattivati (iscrizione scaduta)";
            }
            if ($count_iscrizioni_create > 0) {
                $sync_messages[] = (int)$count_iscrizioni_create . " record iscrizione creati per SEPA/Trattenuta";
            }
            $total = $count_attivati_sepa + $count_attivati_rinnovo + $count_disattivati_rinnovo + $count_iscrizioni_create;
            if ($total === 0) {
                $sync_messages[] = "Nessuna modifica necessaria. I dati erano gia' coerenti.";
            }

            // Ricalcola anteprima dopo sync
            $sync_preview['sepa_trattenuta_da_attivare'] = 0;
            $sync_preview['rinnovo_da_attivare'] = 0;
            $sync_preview['rinnovo_da_disattivare'] = 0;
            $sync_preview['senza_iscrizione'] = 0;
            $sync_total_changes = 0;
        } catch (Exception $e) {
            $mysqli->rollback();
            $sync_errors[] = "Errore durante la sincronizzazione: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Migrazioni Database - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.10" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.10" rel="stylesheet">
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/topbar.php'; ?>
                <div class="container-fluid">
                    <button onclick="history.back()" class="btn btn-secondary mb-4">
                        <i class="fas fa-arrow-left"></i> Indietro
                    </button>
                    <h1 class="h3 mb-4 text-gray-800">Migrazioni Database</h1>

                    <?php foreach ($messages as $msg): ?>
                        <div class="alert alert-success"><?php echo sanitizeForHTML($msg); ?></div>
                    <?php endforeach; ?>
                    <?php foreach ($errors_list as $err): ?>
                        <div class="alert alert-danger"><?php echo sanitizeForHTML($err); ?></div>
                    <?php endforeach; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold">Stato Migrazioni</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pending)): ?>
                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-check-circle"></i> Tutte le migrazioni sono state eseguite. Il database è aggiornato.
                                </div>
                            <?php else: ?>
                                <p>Ci sono <strong><?php echo count($pending); ?></strong> migrazioni pendenti:</p>
                                <ul class="list-group mb-3">
                                    <?php foreach ($pending as $p): ?>
                                        <li class="list-group-item">
                                            <i class="fas fa-clock text-warning"></i>
                                            <?php echo sanitizeForHTML($p); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <form method="POST">
                                    <?php csrfInputField(); ?>
                                    <input type="hidden" name="run_migrations" value="1">
                                    <button type="submit" class="btn btn-primary" onclick="return confirm('Sei sicuro di voler eseguire le migrazioni pendenti?');">
                                        <i class="fas fa-play"></i> Esegui Migrazioni
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (!empty($executed)): ?>
                                <hr>
                                <h6 class="font-weight-bold">Migrazioni Eseguite</h6>
                                <ul class="list-group">
                                    <?php foreach (array_reverse($executed) as $e): ?>
                                        <li class="list-group-item">
                                            <i class="fas fa-check text-success"></i>
                                            <?php echo sanitizeForHTML($e); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Ottimizzazione Indici -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-tachometer-alt mr-1"></i> Ottimizzazione Indici Database</h6>
                            <span class="badge badge-<?php echo empty($missing_indexes) ? 'success' : 'warning'; ?>">
                                <?php echo count($present_indexes); ?>/<?php echo count($recommended_indexes); ?> attivi
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Gli indici migliorano le performance delle query piu' frequenti (filtri, ricerche, dashboard).
                                L'operazione e' sicura e non modifica i dati.
                            </p>

                            <?php foreach ($index_messages as $msg): ?>
                                <div class="alert alert-success py-2 small"><?php echo $msg; ?></div>
                            <?php endforeach; ?>
                            <?php foreach ($index_errors as $err): ?>
                                <div class="alert alert-danger py-2 small"><?php echo $err; ?></div>
                            <?php endforeach; ?>

                            <?php if (!empty($missing_indexes)): ?>
                                <h6 class="font-weight-bold text-warning"><i class="fas fa-exclamation-triangle mr-1"></i> Indici Mancanti (<?php echo count($missing_indexes); ?>)</h6>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.82rem;">
                                        <thead style="background: #f8fafc;">
                                            <tr>
                                                <th>Tabella</th>
                                                <th>Colonne</th>
                                                <th>Motivo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($missing_indexes as $idx): ?>
                                                <tr>
                                                    <td><code><?php echo sanitizeForHTML($idx[0]); ?></code></td>
                                                    <td><code><?php echo sanitizeForHTML($idx[2]); ?></code></td>
                                                    <td><?php echo sanitizeForHTML($idx[3]); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <form method="POST">
                                    <?php csrfInputField(); ?>
                                    <input type="hidden" name="create_indexes" value="1">
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Creare <?php echo count($missing_indexes); ?> indici mancanti? L\'operazione potrebbe richiedere qualche secondo.');">
                                        <i class="fas fa-bolt"></i> Crea <?php echo count($missing_indexes); ?> Indici Mancanti
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-check-circle"></i> Tutti gli indici raccomandati sono gia' presenti. Il database e' ottimizzato.
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($present_indexes)): ?>
                                <hr>
                                <details>
                                    <summary class="font-weight-bold" style="cursor: pointer;">
                                        <i class="fas fa-check text-success mr-1"></i> Indici Attivi (<?php echo count($present_indexes); ?>)
                                    </summary>
                                    <div class="table-responsive mt-2">
                                        <table class="table table-sm table-bordered mb-0" style="font-size: 0.82rem;">
                                            <thead style="background: #f0fdf4;">
                                                <tr>
                                                    <th>Tabella</th>
                                                    <th>Indice</th>
                                                    <th>Colonne</th>
                                                    <th>Motivo</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($present_indexes as $idx): ?>
                                                    <tr>
                                                        <td><code><?php echo sanitizeForHTML($idx[0]); ?></code></td>
                                                        <td><code><?php echo sanitizeForHTML($idx[1]); ?></code></td>
                                                        <td><code><?php echo sanitizeForHTML($idx[2]); ?></code></td>
                                                        <td><?php echo sanitizeForHTML($idx[3]); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Sincronizzazione Stato Iscrizioni -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-sync-alt mr-1"></i> Sincronizzazione Stato Iscrizioni</h6>
                            <span class="badge badge-<?php echo $sync_total_changes === 0 ? 'success' : 'warning'; ?>">
                                <?php echo $sync_total_changes === 0 ? 'Coerente' : (int)$sync_total_changes . ' da correggere'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Verifica e corregge lo stato di iscrizione dei lavoratori in base al tipo di tessera:
                                <strong>SEPA</strong> e <strong>Trattenuta in busta paga</strong> vengono impostati come iscritti,
                                <strong>Rinnovo annuale</strong> viene verificato in base alla data di scadenza dell'iscrizione.
                                I lavoratori archiviati non vengono modificati.
                            </p>

                            <?php foreach ($sync_messages as $msg): ?>
                                <div class="alert alert-success py-2 small"><?php echo sanitizeForHTML($msg); ?></div>
                            <?php endforeach; ?>
                            <?php foreach ($sync_errors as $err): ?>
                                <div class="alert alert-danger py-2 small"><?php echo sanitizeForHTML($err); ?></div>
                            <?php endforeach; ?>

                            <?php if ($sync_total_changes > 0): ?>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.85rem;">
                                        <thead style="background: #f8fafc;">
                                            <tr>
                                                <th>Azione</th>
                                                <th>Tipo Tessera</th>
                                                <th class="text-center">Lavoratori</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($sync_preview['sepa_trattenuta_da_attivare'] > 0): ?>
                                            <tr>
                                                <td><span class="badge badge-success">Imposta Iscritto</span></td>
                                                <td>SEPA / Trattenuta in busta paga</td>
                                                <td class="text-center"><strong><?php echo (int)$sync_preview['sepa_trattenuta_da_attivare']; ?></strong></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if ($sync_preview['rinnovo_da_attivare'] > 0): ?>
                                            <tr>
                                                <td><span class="badge badge-success">Imposta Iscritto</span></td>
                                                <td>Rinnovo Annuale (iscrizione valida)</td>
                                                <td class="text-center"><strong><?php echo (int)$sync_preview['rinnovo_da_attivare']; ?></strong></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if ($sync_preview['rinnovo_da_disattivare'] > 0): ?>
                                            <tr>
                                                <td><span class="badge badge-danger">Imposta Non Iscritto</span></td>
                                                <td>Rinnovo Annuale (iscrizione scaduta)</td>
                                                <td class="text-center"><strong><?php echo (int)$sync_preview['rinnovo_da_disattivare']; ?></strong></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if ($sync_preview['senza_iscrizione'] > 0): ?>
                                            <tr>
                                                <td><span class="badge badge-warning">Crea Iscrizione</span></td>
                                                <td>SEPA / Trattenuta senza record iscrizione</td>
                                                <td class="text-center"><strong><?php echo (int)$sync_preview['senza_iscrizione']; ?></strong></td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <form method="POST">
                                    <?php csrfInputField(); ?>
                                    <input type="hidden" name="sync_iscritti" value="1">
                                    <button type="submit" class="btn btn-info" onclick="return confirm('Sincronizzare lo stato di <?php echo (int)$sync_total_changes; ?> lavoratori?');">
                                        <i class="fas fa-sync-alt"></i> Sincronizza <?php echo (int)$sync_total_changes; ?> Lavoratori
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-check-circle"></i> Tutti gli stati di iscrizione sono coerenti con il tipo di tessera.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
            <?php include __DIR__ . '/footer.php'; ?>
        </div>
    </div>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>
</body>
</html>
