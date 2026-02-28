<?php
require 'config.php';

checkLogin();
checkUserRole('admin');

$errors = [];
$success = false;
$api_message = '';
$api_error = '';

// Recupera impostazioni
$theme_color = getSetting('theme_color') ?? 'default';
$logo = getSetting('logo') ?? 'uploads/default_logo.png';
$nome_app = getSetting('nome_app') ?? '';
$nome_completo = getSetting('nome_completo') ?? '';
$denominazione_e_iban = getSetting('denominazione_e_iban') ?? '';
$territoriale = getSetting('territoriale') ?? '';

// Gestione azioni AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Token CSRF non valido']);
        exit;
    }

    $ajax_action = $_POST['ajax_action'];

    // Crea nuova API key
    if ($ajax_action === 'create_api_key') {
        $label = trim($_POST['label'] ?? '');
        if (empty($label)) {
            echo json_encode(['error' => 'Inserire un nome per la chiave']);
            exit;
        }
        $new_key = bin2hex(random_bytes(32));
        $stmt = executeQuery(
            "INSERT INTO api_keys (api_key, label, created_by) VALUES (?, ?, ?)",
            [$new_key, $label, $_SESSION['user_id']],
            'ssi'
        );
        if ($stmt) {
            echo json_encode(['success' => true, 'key' => $new_key, 'message' => 'Chiave API creata con successo']);
        } else {
            echo json_encode(['error' => 'Errore nella creazione della chiave']);
        }
        exit;
    }

    // Toggle API key attiva/disattiva
    if ($ajax_action === 'toggle_api_key') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = executeQuery("UPDATE api_keys SET is_active = NOT is_active WHERE id = ?", [$id], 'i');
        echo json_encode(['success' => $stmt !== false]);
        exit;
    }

    // Elimina API key
    if ($ajax_action === 'delete_api_key') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = executeQuery("DELETE FROM api_keys WHERE id = ?", [$id], 'i');
        echo json_encode(['success' => $stmt !== false]);
        exit;
    }

    // Aggiungi connessione esterna
    if ($ajax_action === 'add_connection') {
        $name = trim($_POST['conn_name'] ?? '');
        $url  = trim($_POST['conn_url'] ?? '');
        $key  = trim($_POST['conn_key'] ?? '');
        if (empty($name) || empty($url) || empty($key)) {
            echo json_encode(['error' => 'Compilare tutti i campi']);
            exit;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['error' => 'URL non valido']);
            exit;
        }
        $stmt = executeQuery(
            "INSERT INTO api_connections (name, endpoint_url, api_key, created_by) VALUES (?, ?, ?, ?)",
            [$name, $url, $key, $_SESSION['user_id']],
            'sssi'
        );
        if ($stmt) {
            echo json_encode(['success' => true, 'message' => 'Connessione aggiunta']);
        } else {
            echo json_encode(['error' => 'Errore nel salvataggio']);
        }
        exit;
    }

    // Toggle connessione
    if ($ajax_action === 'toggle_connection') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = executeQuery("UPDATE api_connections SET is_active = NOT is_active WHERE id = ?", [$id], 'i');
        echo json_encode(['success' => $stmt !== false]);
        exit;
    }

    // Elimina connessione
    if ($ajax_action === 'delete_connection') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = executeQuery("DELETE FROM api_connections WHERE id = ?", [$id], 'i');
        echo json_encode(['success' => $stmt !== false]);
        exit;
    }

    // Test connessione
    if ($ajax_action === 'test_connection') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = executeQuery("SELECT endpoint_url, api_key FROM api_connections WHERE id = ?", [$id], 'i');
        if (!$stmt) {
            echo json_encode(['error' => 'Connessione non trovata']);
            exit;
        }
        $conn = $stmt->get_result()->fetch_assoc();
        if (!$conn) {
            echo json_encode(['error' => 'Connessione non trovata']);
            exit;
        }

        $url = rtrim($conn['endpoint_url'], '/') . '?action=ping';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $conn['api_key'],
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            echo json_encode(['error' => 'Errore di connessione: ' . $curlError]);
        } elseif ($httpCode !== 200) {
            echo json_encode(['error' => "Risposta HTTP $httpCode"]);
        } else {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                executeQuery("UPDATE api_connections SET last_sync_at = NOW() WHERE id = ?", [$id], 'i');
                echo json_encode(['success' => true, 'name' => $data['name'] ?? 'CRM Remoto', 'message' => 'Connessione riuscita!']);
            } else {
                echo json_encode(['error' => 'Risposta non valida dal server remoto']);
            }
        }
        exit;
    }

    echo json_encode(['error' => 'Azione non riconosciuta']);
    exit;
}

// Gestione form impostazioni generali (POST non-AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Token CSRF mancante o non valido.";
    }

    $theme_color = sanitizeForDatabase($_POST['theme_color'] ?? 'default');
    $nome_app = sanitizeForDatabase($_POST['nome_app'] ?? '');
    $nome_completo = sanitizeForDatabase($_POST['nome_completo'] ?? '');
    $denominazione_e_iban = sanitizeForDatabase($_POST['denominazione_e_iban'] ?? '');
    $territoriale = sanitizeForDatabase($_POST['territoriale'] ?? '');

    // Logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_tmp = $_FILES['logo']['tmp_name'];
        $logo_name = basename($_FILES['logo']['name']);
        $logo_extension = pathinfo($logo_name, PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

        if (in_array(strtolower($logo_extension), $allowed_extensions)) {
            $new_logo_name = 'logo.' . strtolower($logo_extension);
            $upload_dir = 'uploads/';
            $destination = $upload_dir . $new_logo_name;

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (empty($errors) && move_uploaded_file($logo_tmp, $destination)) {
                $logo = $upload_dir . $new_logo_name;
                setSetting('logo', $logo);
            } else {
                $errors[] = "Errore nel salvataggio del logo.";
            }
        } else {
            $errors[] = "Formato del logo non supportato.";
        }
    }

    if (empty($errors)) {
        setSetting('theme_color', $theme_color);
        setSetting('nome_app', $nome_app);
        setSetting('nome_completo', $nome_completo);
        setSetting('denominazione_e_iban', $denominazione_e_iban);
        setSetting('territoriale', $territoriale);
        $success = true;
    }

    // Ricarica
    $theme_color = getSetting('theme_color') ?? 'default';
    $logo = getSetting('logo') ?? 'uploads/default_logo.png';
    $nome_app = getSetting('nome_app') ?? '';
    $nome_completo = getSetting('nome_completo') ?? '';
    $denominazione_e_iban = getSetting('denominazione_e_iban') ?? '';
    $territoriale = getSetting('territoriale') ?? '';
}

// Recupera API keys e connessioni
$api_keys = [];
$api_connections = [];

// Controlla se le tabelle esistono (pre-migrazione)
$tables_exist = true;
$check = $mysqli->query("SHOW TABLES LIKE 'api_keys'");
if (!$check || $check->num_rows === 0) {
    $tables_exist = false;
}

if ($tables_exist) {
    $stmt = executeQuery("SELECT * FROM api_keys ORDER BY created_at DESC", [], '');
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $api_keys[] = $row;
        }
    }

    $stmt = executeQuery("SELECT * FROM api_connections ORDER BY name ASC", [], '');
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $api_connections[] = $row;
        }
    }
}

generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Impostazioni - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.5" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.5" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <style>
        .form-group { margin-bottom: 15px; }
        .api-key-display {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: #f3f4f6;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            word-break: break-all;
        }
        .connection-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        .connection-card.inactive { opacity: 0.5; }
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        .status-dot.active { background: #16a34a; }
        .status-dot.inactive { background: #9ca3af; }
        .nav-pills .nav-link { color: #374151; border-radius: 0.5rem; }
        .nav-pills .nav-link.active { background-color: #000; color: #fff; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/topbar.php'; ?>
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Impostazioni</h1>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-0"><?php echo sanitizeForHTML($error); ?></p>
                            <?php endforeach; ?>
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        </div>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Impostazioni aggiornate con successo.
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <!-- Tab Navigation -->
                    <ul class="nav nav-pills mb-4" id="settingsTabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#tab-generale">
                                <i class="fas fa-cog"></i> Generale
                            </a>
                        </li>
                        <?php if ($tables_exist): ?>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#tab-api-keys">
                                <i class="fas fa-key"></i> Chiavi API
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#tab-connessioni">
                                <i class="fas fa-plug"></i> Connessioni
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <div class="tab-content">
                        <!-- Tab Generale -->
                        <div class="tab-pane fade show active" id="tab-generale">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold">Personalizzazione</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data">
                                        <?php csrfInputField(); ?>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="nome_app">Nome dell'Applicazione</label>
                                                    <input type="text" id="nome_app" name="nome_app" class="form-control" value="<?php echo sanitizeForHTML($nome_app); ?>" placeholder="Es: ADL Cobas Padova">
                                                    <small class="form-text text-muted">Questo nome apparirà nella sidebar e nella pagina di login.</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="theme_color">Colore del Tema</label>
                                                    <select name="theme_color" id="theme_color" class="form-control">
                                                        <option value="default" <?php echo ($theme_color === 'default' ? 'selected' : ''); ?>>Default</option>
                                                        <option value="dark" <?php echo ($theme_color === 'dark' ? 'selected' : ''); ?>>Scuro</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="logo">Logo</label><br>
                                            <img src="<?php echo sanitizeForHTML($base_url . $logo); ?>" alt="Logo Attuale" style="max-width: 200px; margin-bottom: 10px;" /><br>
                                            <input type="file" name="logo" id="logo" class="form-control-file">
                                            <small class="form-text text-muted">Carica un'immagine in formato jpg, jpeg, png, gif o svg.</small>
                                        </div>

                                        <hr>
                                        <h6 class="font-weight-bold mb-3">Impostazioni PDF</h6>

                                        <div class="form-group">
                                            <label for="nome_completo">Nome Completo Associazione</label>
                                            <input type="text" id="nome_completo" name="nome_completo" class="form-control" value="<?php echo sanitizeForHTML($nome_completo); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="denominazione_e_iban">Denominazione e IBAN</label>
                                            <input type="text" id="denominazione_e_iban" name="denominazione_e_iban" class="form-control" value="<?php echo sanitizeForHTML($denominazione_e_iban); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="territoriale">Valore Territoriale</label>
                                            <input type="text" id="territoriale" name="territoriale" class="form-control" value="<?php echo sanitizeForHTML($territoriale); ?>">
                                        </div>

                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Salva Impostazioni
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <?php if ($tables_exist): ?>
                        <!-- Tab Chiavi API -->
                        <div class="tab-pane fade" id="tab-api-keys">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Chiavi API</h6>
                                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createKeyModal">
                                        <i class="fas fa-plus"></i> Nuova Chiave
                                    </button>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">
                                        Le chiavi API permettono ad altri CRM di accedere ai dati di questo gestionale.
                                        Condividi una chiave e l'endpoint con l'amministratore del CRM remoto.
                                    </p>
                                    <div class="alert alert-info d-flex align-items-center mb-3">
                                        <i class="fas fa-link mr-2"></i>
                                        <div>
                                            <strong>Endpoint API:</strong>
                                            <code id="apiEndpointUrl"><?php
                                                $configuredOrigin = rtrim($_ENV['APP_URL'] ?? '', '/');
                                                if ($configuredOrigin === '') {
                                                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                                    $configuredOrigin = $protocol . '://' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
                                                }
                                                echo sanitizeForHTML($configuredOrigin . $base_url . 'api.php');
                                            ?></code>
                                            <button class="btn btn-sm btn-outline-secondary ml-2" id="copyEndpointBtn" title="Copia endpoint">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="apiKeysList">
                                        <?php if (empty($api_keys)): ?>
                                            <p class="text-muted text-center py-4">Nessuna chiave API creata.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Nome</th>
                                                            <th>Chiave</th>
                                                            <th>Stato</th>
                                                            <th>Creata</th>
                                                            <th>Ultimo utilizzo</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($api_keys as $key): ?>
                                                        <tr id="key-row-<?php echo $key['id']; ?>" class="<?php echo $key['is_active'] ? '' : 'text-muted'; ?>">
                                                            <td><strong><?php echo sanitizeForHTML($key['label']); ?></strong></td>
                                                            <td>
                                                                <code class="api-key-display"><?php echo sanitizeForHTML(substr($key['api_key'], 0, 12)); ?>...<?php echo sanitizeForHTML(substr($key['api_key'], -4)); ?></code>
                                                                <button class="btn btn-sm btn-outline-secondary ml-1 copy-key-btn" data-key="<?php echo sanitizeForHTML($key['api_key']); ?>" title="Copia">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                            </td>
                                                            <td>
                                                                <span class="status-dot <?php echo $key['is_active'] ? 'active' : 'inactive'; ?>"></span>
                                                                <?php echo $key['is_active'] ? 'Attiva' : 'Disattivata'; ?>
                                                            </td>
                                                            <td><?php echo date('d/m/Y', strtotime($key['created_at'])); ?></td>
                                                            <td><?php echo $key['last_used_at'] ? date('d/m/Y H:i', strtotime($key['last_used_at'])) : '-'; ?></td>
                                                            <td>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <button class="table-action-icon toggle-key-btn" data-id="<?php echo $key['id']; ?>" title="<?php echo $key['is_active'] ? 'Disattiva' : 'Attiva'; ?>">
                                                                        <i class="fas fa-<?php echo $key['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                                    </button>
                                                                    <button class="table-action-icon delete-key-btn" data-id="<?php echo $key['id']; ?>" title="Elimina">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Connessioni -->
                        <div class="tab-pane fade" id="tab-connessioni">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Connessioni a CRM Esterni</h6>
                                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addConnectionModal">
                                        <i class="fas fa-plus"></i> Aggiungi Connessione
                                    </button>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">
                                        Collega altri gestionali ADL per consultare le loro aziende e lavoratori tramite API sicure.
                                    </p>
                                    <div id="connectionsList">
                                        <?php if (empty($api_connections)): ?>
                                            <p class="text-muted text-center py-4">Nessuna connessione configurata.</p>
                                        <?php else: ?>
                                            <?php foreach ($api_connections as $conn): ?>
                                            <div class="connection-card <?php echo $conn['is_active'] ? '' : 'inactive'; ?>" id="conn-<?php echo $conn['id']; ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <span class="status-dot <?php echo $conn['is_active'] ? 'active' : 'inactive'; ?>"></span>
                                                            <strong><?php echo sanitizeForHTML($conn['name']); ?></strong>
                                                        </h6>
                                                        <small class="text-muted d-block">
                                                            <i class="fas fa-link"></i> <?php echo sanitizeForHTML($conn['endpoint_url']); ?>
                                                        </small>
                                                        <?php if ($conn['last_sync_at']): ?>
                                                            <small class="text-muted d-block mt-1">
                                                                <i class="fas fa-clock"></i> Ultimo test: <?php echo date('d/m/Y H:i', strtotime($conn['last_sync_at'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <button class="btn btn-sm btn-outline-primary test-conn-btn" data-id="<?php echo $conn['id']; ?>" title="Testa connessione">
                                                            <i class="fas fa-wifi"></i> Test
                                                        </button>
                                                        <button class="table-action-icon toggle-conn-btn" data-id="<?php echo $conn['id']; ?>" title="<?php echo $conn['is_active'] ? 'Disattiva' : 'Attiva'; ?>">
                                                            <i class="fas fa-<?php echo $conn['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        </button>
                                                        <button class="table-action-icon delete-conn-btn" data-id="<?php echo $conn['id']; ?>" title="Elimina">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i>
                            Le tabelle API non sono ancora state create.
                            <a href="migrate.php">Esegui le migrazioni</a> per abilitare il sistema di connessioni inter-CRM.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php include __DIR__ . '/footer.php'; ?>
        </div>
    </div>

    <!-- Modal: Crea Chiave API -->
    <div class="modal fade" id="createKeyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crea Nuova Chiave API</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="keyLabel">Nome/Etichetta</label>
                        <input type="text" id="keyLabel" class="form-control" placeholder="Es: CRM Emilia Romagna">
                        <small class="form-text text-muted">Un nome descrittivo per identificare chi usa questa chiave.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" id="createKeyBtn">Crea Chiave</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Aggiungi Connessione -->
    <div class="modal fade" id="addConnectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Connessione CRM</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="connName">Nome</label>
                        <input type="text" id="connName" class="form-control" placeholder="Es: Emilia Romagna">
                    </div>
                    <div class="form-group">
                        <label for="connUrl">URL Endpoint API</label>
                        <input type="url" id="connUrl" class="form-control" placeholder="https://crm-emiliaromagna.example.com/api.php">
                        <small class="form-text text-muted">L'URL completo del file api.php del CRM remoto.</small>
                    </div>
                    <div class="form-group">
                        <label for="connKey">Chiave API</label>
                        <input type="text" id="connKey" class="form-control" placeholder="Incolla qui la chiave API fornita dall'altro CRM">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" id="addConnBtn">Aggiungi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Chiave API Creata -->
    <div class="modal fade" id="showKeyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chiave API Creata</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Importante:</strong> Copia questa chiave adesso. Non sarà possibile visualizzarla nuovamente per intero.
                    </div>
                    <div class="form-group">
                        <label>Chiave API:</label>
                        <div class="api-key-display p-3" id="newKeyDisplay" style="user-select: all;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="copyNewKeyBtn">
                        <i class="fas fa-copy"></i> Copia
                    </button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Chiudi</button>
                </div>
            </div>
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
        $.post('settings.php', data, callback, 'json').fail(function() {
            Swal.fire('Errore!', 'Errore di comunicazione con il server.', 'error');
        });
    }

    // Chiavi API
    $('#createKeyBtn').on('click', function() {
        var label = $('#keyLabel').val().trim();
        if (!label) { Swal.fire('Attenzione', 'Inserire un nome per la chiave.', 'warning'); return; }
        apiPost('create_api_key', { label: label }, function(resp) {
            if (resp.error) { Swal.fire('Errore', resp.error, 'error'); return; }
            $('#createKeyModal').modal('hide');
            $('#keyLabel').val('');
            $('#newKeyDisplay').text(resp.key);
            $('#showKeyModal').modal('show');
            // Reload dopo che l'utente chiude il modal
            $('#showKeyModal').on('hidden.bs.modal', function() { location.reload(); });
        });
    });

    function copyToClipboard(text, message) {
        message = message || 'Copiato!';
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: message, showConfirmButton: false, timer: 1500 });
            });
        } else {
            var temp = $('<textarea>').val(text).appendTo('body').select();
            document.execCommand('copy');
            temp.remove();
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: message, showConfirmButton: false, timer: 1500 });
        }
    }

    $('#copyEndpointBtn').on('click', function() {
        copyToClipboard($('#apiEndpointUrl').text().trim(), 'Endpoint copiato!');
    });

    $('#copyNewKeyBtn').on('click', function() {
        copyToClipboard($('#newKeyDisplay').text(), 'Chiave copiata!');
    });

    $(document).on('click', '.copy-key-btn', function() {
        copyToClipboard($(this).data('key'), 'Chiave copiata!');
    });

    $(document).on('click', '.toggle-key-btn', function() {
        var id = $(this).data('id');
        apiPost('toggle_api_key', { id: id }, function(resp) {
            if (resp.success) location.reload();
        });
    });

    $(document).on('click', '.delete-key-btn', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Eliminare questa chiave?',
            text: "Tutti i CRM che la utilizzano perderanno l'accesso.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Elimina',
            cancelButtonText: 'Annulla'
        }).then(function(result) {
            if (result.isConfirmed) {
                apiPost('delete_api_key', { id: id }, function(resp) {
                    if (resp.success) location.reload();
                });
            }
        });
    });

    // Connessioni
    $('#addConnBtn').on('click', function() {
        var name = $('#connName').val().trim();
        var url = $('#connUrl').val().trim();
        var key = $('#connKey').val().trim();
        if (!name || !url || !key) { Swal.fire('Attenzione', 'Compilare tutti i campi.', 'warning'); return; }
        apiPost('add_connection', { conn_name: name, conn_url: url, conn_key: key }, function(resp) {
            if (resp.error) { Swal.fire('Errore', resp.error, 'error'); return; }
            $('#addConnectionModal').modal('hide');
            location.reload();
        });
    });

    $(document).on('click', '.toggle-conn-btn', function() {
        var id = $(this).data('id');
        apiPost('toggle_connection', { id: id }, function(resp) {
            if (resp.success) location.reload();
        });
    });

    $(document).on('click', '.delete-conn-btn', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Eliminare questa connessione?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Elimina',
            cancelButtonText: 'Annulla'
        }).then(function(result) {
            if (result.isConfirmed) {
                apiPost('delete_connection', { id: id }, function(resp) {
                    if (resp.success) location.reload();
                });
            }
        });
    });

    $(document).on('click', '.test-conn-btn', function() {
        var btn = $(this);
        var id = btn.data('id');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Test...');
        apiPost('test_connection', { id: id }, function(resp) {
            btn.prop('disabled', false).html('<i class="fas fa-wifi"></i> Test');
            if (resp.error) {
                Swal.fire('Connessione Fallita', resp.error, 'error');
            } else {
                Swal.fire('Connessione Riuscita!', 'CRM collegato: ' + resp.name, 'success');
            }
        });
    });

    // Attiva tab da hash URL
    if (window.location.hash) {
        var tab = $('a[href="' + window.location.hash + '"]');
        if (tab.length) tab.tab('show');
    }
    $('a[data-toggle="pill"]').on('shown.bs.tab', function(e) {
        window.location.hash = $(e.target).attr('href');
    });
    </script>
</body>
</html>
