<?php
// backup.php

$cron_key = "RMolEMPSEtea";


// Abilita la visualizzazione degli errori per lo sviluppo (disabilita in produzione)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
checkLogin();

// Imposta l'encoding della connessione al database
$mysqli->set_charset("utf8mb4");

// Impostazioni backup
$backupDir = __DIR__ . '/backup';
$cronBackupDir = $backupDir . '/cron';
$maxManualBackups = 4;
$maxCronBackups = 2;

/**
 * Genera un dump del database in formato simile a mysqldump.
 * Il dump include DROP TABLE, CREATE TABLE e UNICA INSERT per tutti i dati della tabella.
 */
function generateBackupDump($mysqli, $db) {
    $dump = "-- Backup of database: $db\n";
    $dump .= "-- Generated on: " . date("Y-m-d H:i:s") . "\n\n";
    $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // Recupera l'elenco delle tabelle
    $tablesResult = $mysqli->query("SHOW TABLES");
    if (!$tablesResult) {
        return false;
    }
    while ($row = $tablesResult->fetch_array()) {
        $table = $row[0];
        $dump .= "-- --------------------------------------------------------\n";
        $dump .= "-- Table structure for table `$table`\n";
        $dump .= "-- --------------------------------------------------------\n\n";
        $dump .= "DROP TABLE IF EXISTS `$table`;\n";

        $createResult = $mysqli->query("SHOW CREATE TABLE `$table`");
        if ($createResult) {
            $createRow = $createResult->fetch_assoc();
            $dump .= $createRow['Create Table'] . ";\n\n";
        }

        // Dati della tabella
        $dataResult = $mysqli->query("SELECT * FROM `$table`");
        if ($dataResult && $dataResult->num_rows > 0) {
            $dump .= "-- --------------------------------------------------------\n";
            $dump .= "-- Dumping data for table `$table`\n";
            $dump .= "-- --------------------------------------------------------\n\n";
            $dump .= "LOCK TABLES `$table` WRITE;\n";
            $dump .= "/*!40000 ALTER TABLE `$table` DISABLE KEYS */;\n";
            
            // Inizia una singola INSERT per tutte le righe
            $dump .= "INSERT INTO `$table` VALUES ";
            $firstRow = true;
            while ($dataRow = $dataResult->fetch_row()) {
                if (!$firstRow) {
                    $dump .= ",\n";
                }
                $rowValues = [];
                foreach ($dataRow as $val) {
                    if (is_null($val)) {
                        $rowValues[] = "NULL";
                    } else {
                        $rowValues[] = "'" . $mysqli->real_escape_string($val) . "'";
                    }
                }
                $dump .= "(" . implode(", ", $rowValues) . ")";
                $firstRow = false;
            }
            $dump .= ";\n";
            $dump .= "/*!40000 ALTER TABLE `$table` ENABLE KEYS */;\n";
            $dump .= "UNLOCK TABLES;\n\n";
        }
    }
    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $dump;
}

/**
 * Salva il dump in un file nella cartella specificata.
 */
function saveBackupToFile($dump, $path, $db) {
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }
    $timestamp = date("Ymd_His");
    $filename = "{$db}_backup_{$timestamp}.sql";
    $fullPath = $path . "/" . $filename;
    if (file_put_contents($fullPath, $dump) !== false) {
        return $fullPath;
    }
    return false;
}

/**
 * Rimuove i backup più vecchi per mantenere al massimo $maxBackups file.
 */
function pruneBackups($path, $maxBackups) {
    $files = glob($path . "/*.sql");
    if ($files === false) return;
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    while (count($files) > $maxBackups) {
        unlink(array_shift($files));
    }
}

// Gestione del download dei backup
if (isset($_GET['action']) && $_GET['action'] == 'download' && isset($_GET['file'])) {
    $requestedFile = basename($_GET['file']); // Previeni path traversal
    if (file_exists($backupDir . '/' . $requestedFile)) {
        $filePath = $backupDir . '/' . $requestedFile;
    } elseif (file_exists($cronBackupDir . '/' . $requestedFile)) {
        $filePath = $cronBackupDir . '/' . $requestedFile;
    } else {
        die("File non trovato.");
    }
    header('Content-Description: File Transfer');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// Variabile per messaggi di feedback all'utente
$backupMessage = "";

// Se la pagina viene chiamata dal cron (ad esempio, backup.php?cron=1)
if (isset($_GET['cron']) && $_GET['cron'] == 1) {
    $dump = generateBackupDump($mysqli, $db);
    if ($dump !== false) {
        $result = saveBackupToFile($dump, $cronBackupDir, $db);
        if ($result !== false) {
            $backupMessage = "Backup (cron) eseguito: " . basename($result);
            pruneBackups($cronBackupDir, $maxCronBackups);
        } else {
            $backupMessage = "Errore nel salvataggio del backup (cron).";
        }
    } else {
        $backupMessage = "Errore nella generazione del dump (cron).";
    }
}
// Se l'utente preme il pulsante per il backup manuale
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_manual'])) {
    // Verifica CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $backupMessage = "Token CSRF non valido.";
    } else {
        $dump = generateBackupDump($mysqli, $db);
        if ($dump !== false) {
            $result = saveBackupToFile($dump, $backupDir, $db);
            if ($result !== false) {
                $backupMessage = "Backup eseguito: " . basename($result);
                pruneBackups($backupDir, $maxManualBackups);
            } else {
                $backupMessage = "Errore nel salvataggio del backup.";
            }
        } else {
            $backupMessage = "Errore nella generazione del dump.";
        }
    }
}

// Recupera la lista dei backup manuali e cron
$manualBackups = glob($backupDir . "/*.sql");
if ($manualBackups === false) $manualBackups = [];
usort($manualBackups, function($a, $b) {
    return filemtime($b) - filemtime($a);
});
$cronBackups = glob($cronBackupDir . "/*.sql");
if ($cronBackups === false) $cronBackups = [];
usort($cronBackups, function($a, $b) {
    return filemtime($b) - filemtime($a);
});
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Backup del Database - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.0" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.0" rel="stylesheet">
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
                    <h1 class="h3 mb-4 text-gray-800">Backup del Database</h1>
                    
                    <?php if ($backupMessage != ""): ?>
                        <div class="alert alert-info"><?php echo sanitizeForHTML($backupMessage); ?></div>
                    <?php endif; ?>

                    <!-- Form per il Backup Manuale -->
                    <form method="post" class="mb-4">
                        <input type="hidden" name="backup_manual" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="btn btn-primary">Esegui Backup Manuale</button>
                    </form>

                    <div class="row">
                        <!-- Lista Backup Manuali -->
                        <div class="col-md-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Backup Manuali (Ultimi <?php echo $maxManualBackups; ?>)</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (count($manualBackups) > 0): ?>
                                        <ul class="list-group">
                                            <?php foreach ($manualBackups as $file): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?php echo basename($file); ?>
                                                    <a href="backup.php?action=download&file=<?php echo urlencode(basename($file)); ?>" class="btn btn-sm btn-outline-secondary">Scarica</a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>Nessun backup manuale presente.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <!-- Lista Backup Cron -->
                        <div class="col-md-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Backup Cron (Ultimi <?php echo $maxCronBackups; ?>)</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (count($cronBackups) > 0): ?>
                                        <ul class="list-group">
                                            <?php foreach ($cronBackups as $file): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?php echo basename($file); ?>
                                                    <a href="backup.php?action=download&file=<?php echo urlencode(basename($file)); ?>" class="btn btn-sm btn-outline-secondary">Scarica</a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>Nessun backup cron presente.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted">
                        Nota: I backup cron vengono eseguiti automaticamente una volta a settimana se il cron è configurato per chiamare questa pagina con il parametro <code>?cron=1</code>.
                    </p>
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

    <!-- Bootstrap core JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- Core plugin JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <!-- SB Admin 2 JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>
</body>
</html>
