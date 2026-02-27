<?php
// add_sede.php

// Includi il file di configurazione e funzioni comuni
require_once 'config.php'; // Assicurati che questo file contenga: executeQuery(), sanitizeForHTML(), checkLogin(), generateCsrfToken()

// Verifica se l'utente è loggato
checkLogin();

// Gestione del form di inserimento della sede
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica del token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF non valido.");
    }
    
    // Recupera e pulisci i dati inviati
    $nome = trim($_POST['nome'] ?? '');
    $indirizzo = trim($_POST['indirizzo'] ?? '');
    $citta = trim($_POST['citta'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $cap = trim($_POST['cap'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Validazione: il campo nome è obbligatorio
    if (empty($nome)) {
        $error = "Il campo 'Nome Sede' è obbligatorio.";
    }
    
    // Se non ci sono errori, inserisci la sede nel database
    if (!isset($error)) {
        $query = "INSERT INTO sedi (nome, indirizzo, citta, provincia, cap, telefono, email, data_creazione)
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $params = [$nome, $indirizzo, $citta, $provincia, $cap, $telefono, $email];
        $types = 'sssssss';
        $stmt = executeQuery($query, $params, $types);
        if ($stmt) {
            // Reindirizza alla pagina delle sedi con un messaggio di successo
            header("Location: sedi.php?add_success=1");
            exit;
        } else {
            $error = "Errore nell'inserimento della sede.";
        }
    }
}

// Genera un token CSRF per il form
generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Aggiungi Sede - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.4" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.4" rel="stylesheet">
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include 'sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include 'topbar.php'; ?>
                <div class="container-fluid">
                    <h2 class="mt-4">Aggiungi Nuova Sede</h2>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo sanitizeForHTML($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <form action="add_sede.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="form-group">
                                    <label for="nome">Nome Sede <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nome" name="nome" required>
                                </div>
                                <div class="form-group">
                                    <label for="indirizzo">Indirizzo</label>
                                    <input type="text" class="form-control" id="indirizzo" name="indirizzo">
                                </div>
                                <div class="form-group">
                                    <label for="citta">Città</label>
                                    <input type="text" class="form-control" id="citta" name="citta">
                                </div>
                                <div class="form-group">
                                    <label for="provincia">Provincia</label>
                                    <input type="text" class="form-control" id="provincia" name="provincia">
                                </div>
                                <div class="form-group">
                                    <label for="cap">CAP</label>
                                    <input type="text" class="form-control" id="cap" name="cap">
                                </div>
                                <div class="form-group">
                                    <label for="telefono">Telefono</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono">
                                </div>
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                                <button type="submit" class="btn btn-primary">Salva Sede</button>
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
