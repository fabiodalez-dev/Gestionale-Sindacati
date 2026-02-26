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
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Migrazioni Database - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.0" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.0" rel="stylesheet">
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
