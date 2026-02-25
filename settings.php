<?php
require 'config.php';
checkLogin();

// Inizializza variabili per errori e successo
$errors = [];
$success = false;

// Recupera singolarmente le impostazioni dal database
$theme_color = getSetting('theme_color') ?? 'default';
$logo = getSetting('logo') ?? 'uploads/default_logo.png';
$nome_completo = getSetting('nome_completo') ?? '';
$denominazione_e_iban = getSetting('denominazione_e_iban') ?? '';
$territoriale = getSetting('territoriale') ?? '';

// Gestione del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Token CSRF mancante o non valido.";
    }

    // Recupera e sanitizza i dati inviati, usando valori di default se non impostati
    $theme_color = sanitizeForDatabase($_POST['theme_color'] ?? 'default');
    $nome_completo = sanitizeForDatabase($_POST['nome_completo'] ?? '');
    $denominazione_e_iban = sanitizeForDatabase($_POST['denominazione_e_iban'] ?? '');
    $territoriale = sanitizeForDatabase($_POST['territoriale'] ?? '');

    // Gestione del logo (se viene caricato un file)
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_tmp = $_FILES['logo']['tmp_name'];
        $logo_name = basename($_FILES['logo']['name']);
        $logo_extension = pathinfo($logo_name, PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array(strtolower($logo_extension), $allowed_extensions)) {
            $new_logo_name = 'logo.' . strtolower($logo_extension);
            $upload_dir = 'uploads/';
            $destination = $upload_dir . $new_logo_name;

            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $errors[] = "Impossibile creare la directory uploads.";
                }
            }

            if (!is_writable($upload_dir)) {
                $errors[] = "La directory uploads non ha i permessi di scrittura.";
            }

            if (empty($errors)) {
                if (move_uploaded_file($logo_tmp, $destination)) {
                    $logo = $upload_dir . $new_logo_name;
                    if (!setSetting('logo', $logo)) {
                        $errors[] = "Errore nell'aggiornamento del logo nel database.";
                    }
                } else {
                    $errors[] = "Errore nel salvataggio del logo.";
                }
            }
        } else {
            $errors[] = "Formato del logo non supportato. Formati consentiti: jpg, jpeg, png, gif.";
        }
    }

    if (empty($errors)) {
        if (!setSetting('theme_color', $theme_color)) {
            $errors[] = "Errore nell'aggiornamento delle impostazioni.";
        }
        if (!setSetting('nome_completo', $nome_completo)) {
            $errors[] = "Errore nell'aggiornamento del nome completo per il PDF.";
        }
        if (!setSetting('denominazione_e_iban', $denominazione_e_iban)) {
            $errors[] = "Errore nell'aggiornamento della denominazione e IBAN per il PDF.";
        }
        if (!setSetting('territoriale', $territoriale)) {
            $errors[] = "Errore nell'aggiornamento del valore territoriale per il PDF.";
        }

        if (empty($errors)) {
            $success = true;
        }
    }
    
    // Dopo l'aggiornamento, recupera nuovamente le impostazioni aggiornate
    $theme_color = getSetting('theme_color') ?? 'default';
    $logo = getSetting('logo') ?? 'uploads/default_logo.png';
    $nome_completo = getSetting('nome_completo') ?? '';
    $denominazione_e_iban = getSetting('denominazione_e_iban') ?? '';
    $territoriale = getSetting('territoriale') ?? '';
}

// Genera il token CSRF per il form
generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Impostazioni - CRM Admin</title>
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo $base_url; ?>theme/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="<?php echo $base_url; ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Custom CSS -->
    <link href="<?php echo $base_url; ?>styles.css" rel="stylesheet">
    <style>
        .form-group { margin-bottom: 15px; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include 'sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include 'topbar.php'; ?>
                <div class="container-fluid">
                    <button onclick="history.back()" class="btn btn-secondary mb-4">
                        <i class="fas fa-arrow-left"></i> Indietro
                    </button>
                    <h1 class="h3 mb-4 text-gray-800">Impostazioni</h1>
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
                            <p>Impostazioni aggiornate con successo.</p>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-primary">
                            <h6 class="m-0 font-weight-bold text-white">Personalizza</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <?php csrfInputField(); ?>
                                <div class="form-group">
                                    <label for="theme_color">Colore del Tema</label>
                                    <select name="theme_color" id="theme_color" class="form-control">
                                        <option value="default" <?php echo ($theme_color === 'default' ? 'selected' : ''); ?>>Default</option>
                                        <option value="dark" <?php echo ($theme_color === 'dark' ? 'selected' : ''); ?>>Scuro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="logo">Logo</label><br>
                                    <img src="<?php echo sanitizeForHTML($base_url . $logo); ?>" alt="Logo Attuale" style="max-width: 200px; margin-bottom: 10px;" /><br>
                                    <input type="file" name="logo" id="logo" class="form-control-file">
                                    <small class="form-text text-muted">Carica un'immagine in formato jpg, jpeg, png o gif.</small>
                                </div>
                                <div class="form-group">
                                    <label for="nome_completo">Nome Completo Associazione per il PDF</label>
                                    <input type="text" id="nome_completo" name="nome_completo" class="form-control" value="<?php echo sanitizeForHTML($nome_completo); ?>" required>
                                    <small class="form-text text-muted">Questo testo apparirà nel PDF come nome completo dell'associazione.</small>
                                </div>
                                <div class="form-group">
                                    <label for="denominazione_e_iban">Denominazione e IBAN per il PDF</label>
                                    <input type="text" id="denominazione_e_iban" name="denominazione_e_iban" class="form-control" value="<?php echo sanitizeForHTML($denominazione_e_iban); ?>" required>
                                    <small class="form-text text-muted">Questo testo apparirà nel PDF come denominazione e IBAN. Inserire Banca e Iban</small>
                                </div>
                                <div class="form-group">
                                    <label for="territoriale">Valore Territoriale per il PDF</label>
                                    <input type="text" id="territoriale" name="territoriale" class="form-control" value="<?php echo sanitizeForHTML($territoriale); ?>" required>
                                    <small class="form-text text-muted">Questo valore verrà usato come valore territoriale nel PDF.</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Salva Impostazioni</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>
    <script src="<?php echo $base_url; ?>theme/vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo $base_url; ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $base_url; ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?php echo $base_url; ?>theme/js/sb-admin-2.min.js"></script>
</body>
</html>
