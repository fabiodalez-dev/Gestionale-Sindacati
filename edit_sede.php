<?php
// edit_sede.php

// Includi il file di configurazione e funzioni comuni
require_once 'config.php'; // Deve contenere: executeQuery(), sanitizeForHTML(), checkLogin(), generateCsrfToken()

// Verifica se l'utente è loggato
checkLogin();

// Verifica che l'ID della sede sia presente e valido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID sede non valido.");
}

$sede_id = intval($_GET['id']);

// Recupera i dati della sede da modificare
$query = "SELECT * FROM sedi WHERE id = ?";
$params = [$sede_id];
$types = 'i';
$stmt = executeQuery($query, $params, $types);
$sede = $stmt ? $stmt->get_result()->fetch_assoc() : null;

if (!$sede) {
    die("Sede non trovata.");
}

// Gestione dell'invio del form per aggiornare la sede
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica del token CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header("Location: sedi.php?error=" . urlencode("Token CSRF non valido."), true, 303);
        exit;
    }
    
    // Recupera e pulisci i dati inviati
    $nome = trim($_POST['nome'] ?? '');
    $indirizzo = trim($_POST['indirizzo'] ?? '');
    $citta = trim($_POST['citta'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $cap = trim($_POST['cap'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Validazione: il campo "Nome Sede" è obbligatorio
    if (empty($nome)) {
        $error = "Il campo 'Nome Sede' è obbligatorio.";
    }
    
    // Se non ci sono errori, esegui l'aggiornamento nel database
    if (!isset($error)) {
        $updateQuery = "UPDATE sedi SET nome = ?, indirizzo = ?, citta = ?, provincia = ?, cap = ?, telefono = ?, email = ?, data_modifica = NOW() WHERE id = ?";
        $updateParams = [$nome, $indirizzo, $citta, $provincia, $cap, $telefono, $email, $sede_id];
        $updateTypes = 'sssssssi';
        $updateStmt = executeQuery($updateQuery, $updateParams, $updateTypes);
        if ($updateStmt) {
            header("Location: sedi.php?edit_success=1");
            exit;
        } else {
            $error = "Errore durante l'aggiornamento della sede.";
        }
    }
    
    // Se c'è un errore, aggiorna l'array $sede per ripopolare il form
    $sede['nome'] = $nome;
    $sede['indirizzo'] = $indirizzo;
    $sede['citta'] = $citta;
    $sede['provincia'] = $provincia;
    $sede['cap'] = $cap;
    $sede['telefono'] = $telefono;
    $sede['email'] = $email;
}

// Genera un token CSRF per il form
generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica Sede - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.10" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.10" rel="stylesheet">
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include 'sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include 'topbar.php'; ?>
                <div class="container-fluid">
                    <h2 class="mt-4">Modifica Sede</h2>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo sanitizeForHTML($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <form action="edit_sede.php?id=<?php echo sanitizeForHTML($sede_id); ?>" method="POST">
                                <?php csrfInputField(); ?>
                                
                                <div class="form-group">
                                    <label for="nome">Nome Sede <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nome" name="nome" value="<?php echo sanitizeForHTML($sede['nome']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="indirizzo">Indirizzo</label>
                                    <input type="text" class="form-control" id="indirizzo" name="indirizzo" value="<?php echo sanitizeForHTML($sede['indirizzo']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="citta">Città</label>
                                    <input type="text" class="form-control" id="citta" name="citta" value="<?php echo sanitizeForHTML($sede['citta']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="provincia">Provincia</label>
                                    <input type="text" class="form-control" id="provincia" name="provincia" value="<?php echo sanitizeForHTML($sede['provincia']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="cap">CAP</label>
                                    <input type="text" class="form-control" id="cap" name="cap" value="<?php echo sanitizeForHTML($sede['cap']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="telefono">Telefono</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo sanitizeForHTML($sede['telefono']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo sanitizeForHTML($sede['email']); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary">Aggiorna Sede</button>
                                <a href="sedi.php" class="btn btn-secondary">Annulla</a>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- End of Page Content -->
            </div>
            <!-- End of Main Content -->
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
