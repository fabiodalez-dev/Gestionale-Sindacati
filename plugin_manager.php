<?php
// plugin_manager.php
require 'config.php';

// Verifica che l'utente sia loggato e abbia il ruolo di admin
checkLogin();
checkUserRole('admin'); // Assicurati che l'admin abbia il ruolo 'admin'

// Inizializza variabili per messaggi di errore e successo
$errors = [];
$success = false;

// Gestione delle azioni (attivare, disattivare, installare, rimuovere)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Token CSRF mancante o non valido.";
    } else {
        // Azione da eseguire
        $action = $_POST['action'] ?? '';
        $plugin_name = $_POST['plugin_name'] ?? '';

        switch ($action) {
            case 'activate':
                if (activatePlugin($plugin_name)) {
                    $success = "Plugin '$plugin_name' attivato con successo.";
                    // Ricarica i plugin attivi
                    loadActivePlugins();
                } else {
                    $errors[] = "Errore nell'attivazione del plugin '$plugin_name'.";
                }
                break;

            case 'deactivate':
                if (deactivatePlugin($plugin_name)) {
                    $success = "Plugin '$plugin_name' disattivato con successo.";
                } else {
                    $errors[] = "Errore nella disattivazione del plugin '$plugin_name'.";
                }
                break;

            case 'upload':
                // Gestione del caricamento del plugin
                if (isset($_FILES['plugin_zip']) && $_FILES['plugin_zip']['error'] === UPLOAD_ERR_OK) {
                    $plugin_tmp = $_FILES['plugin_zip']['tmp_name'];
                    $plugin_name = pathinfo($_FILES['plugin_zip']['name'], PATHINFO_FILENAME);
                    $plugin_extension = pathinfo($_FILES['plugin_zip']['name'], PATHINFO_EXTENSION);

                    // Controlla che l'estensione sia .zip
                    if (strtolower($plugin_extension) !== 'zip') {
                        $errors[] = "Formato file non supportato. Carica un file .zip.";
                    } else {
                        // Estrai il file zip nella cartella plugins
                        $zip = new ZipArchive;
                        if ($zip->open($plugin_tmp) === TRUE) {
                            $extract_path = __DIR__ . "/plugins/" . $plugin_name . "/";

                            // Verifica se il plugin esiste già
                            if (is_dir($extract_path)) {
                                $errors[] = "Il plugin '$plugin_name' è già installato.";
                                $zip->close();
                            } else {
                                // Estrai
                                $zip->extractTo(__DIR__ . "/plugins/");
                                $zip->close();

                                // Verifica che esista il file plugin.php
                                if (!file_exists($extract_path . "plugin.php")) {
                                    $errors[] = "Il plugin '$plugin_name' non contiene un file plugin.php.";
                                    // Rimuovi la cartella estratta
                                    rrmdir($extract_path);
                                } else {
                                    // Leggi il file plugin.json per ottenere informazioni
                                    $plugin_json_path = $extract_path . "plugin.json";
                                    if (file_exists($plugin_json_path)) {
                                        $plugin_data = json_decode(file_get_contents($plugin_json_path), true);
                                        if (json_last_error() !== JSON_ERROR_NONE) {
                                            $errors[] = "Il file plugin.json del plugin '$plugin_name' contiene errori.";
                                            rrmdir($extract_path);
                                        } else {
                                            // Inserisci il plugin nel database
                                            $name = $plugin_data['name'] ?? $plugin_name;
                                            $description = $plugin_data['description'] ?? '';
                                            $version = $plugin_data['version'] ?? '1.0.0';
                                            $author = $plugin_data['author'] ?? 'Sconosciuto';

                                            if (installPlugin($name, $description, $version, $author)) {
                                                $success = "Plugin '$name' installato con successo. Puoi attivarlo ora.";
                                            } else {
                                                $errors[] = "Errore nell'installazione del plugin '$name'.";
                                                rrmdir($extract_path);
                                            }
                                        }
                                    } else {
                                        // Se plugin.json non esiste, usa valori di default
                                        $name = $plugin_name;
                                        $description = '';
                                        $version = '1.0.0';
                                        $author = 'Sconosciuto';

                                        if (installPlugin($name, $description, $version, $author)) {
                                            $success = "Plugin '$name' installato con successo. Puoi attivarlo ora.";
                                        } else {
                                            $errors[] = "Errore nell'installazione del plugin '$name'.";
                                            rrmdir($extract_path);
                                        }
                                    }
                                }
                            }
                        } else {
                            $errors[] = "Errore nell'apertura del file zip.";
                        }
                    }
                } else {
                    $errors[] = "Errore nel caricamento del file plugin.";
                }
                break;

            case 'remove':
                // Rimozione di un plugin
                // Recupera il percorso del plugin
                $stmt = executeQuery("SELECT name FROM plugins WHERE name = ?", [$plugin_name], 's');
                if ($stmt !== false) {
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $plugin_record = $result->fetch_assoc();
                        $plugin_path = __DIR__ . "/plugins/" . $plugin_record['name'] . "/";

                        // Rimuovi dal database
                        if (removePlugin($plugin_name)) {
                            // Rimuovi la cartella del plugin
                            if (is_dir($plugin_path)) {
                                rrmdir($plugin_path);
                            }
                            $success = "Plugin '$plugin_name' rimosso con successo.";
                        } else {
                            $errors[] = "Errore nella rimozione del plugin '$plugin_name'.";
                        }
                    } else {
                        $errors[] = "Plugin '$plugin_name' non trovato.";
                    }
                } else {
                    $errors[] = "Errore nel recuperare il plugin dal database.";
                }
                break;

            default:
                $errors[] = "Azione non riconosciuta.";
                break;
        }
    }
}

/**
 * Funzione ricorsiva per rimuovere una cartella e tutti i suoi contenuti
 *
 * @param string $dir Il percorso della cartella da rimuovere
 */
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                $path = $dir . "/" . $object;
                if (is_dir($path)) {
                    rrmdir($path);
                } else {
                    unlink($path);
                }
            }
        }
        rmdir($dir);
    }
}

// Genera un token CSRF per il form
generateCsrfToken();

// Recupera l'elenco dei plugin installati
$installed_plugins = getAllPlugins();

// Recupera l'elenco delle cartelle nella directory plugins
$available_plugin_dirs = array_filter(scandir(__DIR__ . "/plugins/"), function($dir) {
    return $dir !== '.' && $dir !== '..' && is_dir(__DIR__ . "/plugins/" . $dir);
});

// Crea un array dei nomi dei plugin già installati
$installed_plugin_names = array_map(function($plugin) {
    return $plugin['name'];
}, $installed_plugins);

// Filtra i plugin disponibili che non sono già installati
$new_available_plugins = array_diff($available_plugin_dirs, $installed_plugin_names);

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Plugin Manager - CRM Admin</title>
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo $base_url; ?>theme/css/sb-admin-2.min.css?v=2.4" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="<?php echo $base_url; ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Custom CSS -->
    <link href="<?php echo $base_url; ?>styles.css?v=2.4" rel="stylesheet">
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
                    <button onclick="history.back()" class="btn btn-secondary mb-4">
                        <i class="fas fa-arrow-left"></i> Indietro
                    </button>
                    <!-- Titolo della Pagina -->
                    <h1 class="h3 mb-4 text-gray-800">Plugin Manager</h1>

                    <!-- Messaggi di errore o successo -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo sanitizeForHTML($error); ?></p>
                            <?php endforeach; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <p><?php echo sanitizeForHTML($success); ?></p>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Elenco dei Plugin Installati -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-primary">
                            <h6 class="m-0 font-weight-bold text-white">Plugin Installati</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($installed_plugins)): ?>
                                <p>Nessun plugin installato.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Descrizione</th>
                                                <th>Versione</th>
                                                <th>Autore</th>
                                                <th>Stato</th>
                                                <th>Azione</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($installed_plugins as $plugin): ?>
                                                <tr>
                                                    <td><?php echo sanitizeForHTML($plugin['name']); ?></td>
                                                    <td><?php echo sanitizeForHTML($plugin['description']); ?></td>
                                                    <td><?php echo sanitizeForHTML($plugin['version']); ?></td>
                                                    <td><?php echo sanitizeForHTML($plugin['author']); ?></td>
                                                    <td>
                                                        <?php if ($plugin['status'] === 'active'): ?>
                                                            <span class="badge badge-success">Attivo</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Inattivo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($plugin['status'] === 'active'): ?>
                                                            <form method="POST" style="display:inline;">
                                                                <?php csrfInputField(); ?>
                                                                <input type="hidden" name="action" value="deactivate">
                                                                <input type="hidden" name="plugin_name" value="<?php echo sanitizeForHTML($plugin['name']); ?>">
                                                                <button type="submit" class="btn btn-warning btn-sm">Disattiva</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="POST" style="display:inline;">
                                                                <?php csrfInputField(); ?>
                                                                <input type="hidden" name="action" value="activate">
                                                                <input type="hidden" name="plugin_name" value="<?php echo sanitizeForHTML($plugin['name']); ?>">
                                                                <button type="submit" class="btn btn-success btn-sm">Attiva</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Sei sicuro di voler rimuovere questo plugin?');">
                                                            <?php csrfInputField(); ?>
                                                            <input type="hidden" name="action" value="remove">
                                                            <input type="hidden" name="plugin_name" value="<?php echo sanitizeForHTML($plugin['name']); ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">Rimuovi</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Elenco dei Plugin Disponibili -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-primary">
                            <h6 class="m-0 font-weight-bold text-white">Plugin Disponibili</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($new_available_plugins)): ?>
                                <p>Nessun plugin disponibile per l'installazione.</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($new_available_plugins as $plugin_dir): ?>
                                        <?php
                                        $plugin_json_path = __DIR__ . "/plugins/" . $plugin_dir . "/plugin.json";
                                        $plugin_info = [
                                            'name' => $plugin_dir,
                                            'description' => 'Descrizione non disponibile.',
                                            'version' => '1.0.0',
                                            'author' => 'Sconosciuto'
                                        ];

                                        if (file_exists($plugin_json_path)) {
                                            $json_data = json_decode(file_get_contents($plugin_json_path), true);
                                            if (json_last_error() === JSON_ERROR_NONE) {
                                                $plugin_info = array_merge($plugin_info, $json_data);
                                            }
                                        }
                                        ?>
                                        <div class="list-group-item">
                                            <h5 class="mb-1"><?php echo sanitizeForHTML($plugin_info['name']); ?></h5>
                                            <p class="mb-1"><?php echo sanitizeForHTML($plugin_info['description']); ?></p>
                                            <small>Versione: <?php echo sanitizeForHTML($plugin_info['version']); ?> | Autore: <?php echo sanitizeForHTML($plugin_info['author']); ?></small>
                                            <form method="POST" style="margin-top:10px;">
                                                <?php csrfInputField(); ?>
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="plugin_name" value="<?php echo sanitizeForHTML($plugin_info['name']); ?>">
                                                <button type="submit" class="btn btn-success btn-sm">Installa & Attiva</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Form per Caricare un Nuovo Plugin -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-primary">
                            <h6 class="m-0 font-weight-bold text-white">Carica Nuovo Plugin</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <!-- Token CSRF -->
                                <?php csrfInputField(); ?>

                                <div class="form-group">
                                    <label for="plugin_zip">File Plugin (.zip)</label>
                                    <input type="file" name="plugin_zip" id="plugin_zip" class="form-control-file" accept=".zip" required>
                                    <small class="form-text text-muted">Carica un file .zip contenente la cartella del plugin.</small>
                                </div>
                                <button type="submit" name="action" value="upload" class="btn btn-primary">Carica Plugin</button>
                            </form>
                        </div>
                    </div>

                </div>
                <!-- End of Page Content -->

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

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sei sicuro di voler uscire?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Chiudi">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Sei pronto a terminare la tua sessione corrente?</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Annulla</button>
                    <a class="btn btn-primary" href="<?php echo $base_url; ?>logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="<?php echo $base_url; ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?php echo $base_url; ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- SB Admin 2 JavaScript-->
    <script src="<?php echo $base_url; ?>theme/js/sb-admin-2.min.js"></script>

</body>
</html>
