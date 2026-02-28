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
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Migrazioni Database - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.5" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.5" rel="stylesheet">
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
                                <div class="alert alert-success py-2 small"><?php echo sanitizeForHTML($msg); ?></div>
                            <?php endforeach; ?>
                            <?php foreach ($index_errors as $err): ?>
                                <div class="alert alert-danger py-2 small"><?php echo sanitizeForHTML($err); ?></div>
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
