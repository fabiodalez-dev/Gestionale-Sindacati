<?php
require 'config.php';
checkLogin();

// Recupera i dati dell'utente dalla sessione o dal database
$user_id = $_SESSION['user_id'];

// Recupera i dati dell'utente
$query = "SELECT * FROM users WHERE id = ?";
$stmt = executeQuery($query, [$user_id], 'i');
$user = $stmt->get_result()->fetch_assoc();

// Inizializza variabili per messaggi di errore e successo
$errors = [];
$success = false;

// Gestione del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Token CSRF mancante o non valido.";
    }

    // Recupero e sanitizzazione dei dati
    $username = sanitizeForDatabase($_POST['username'] ?? '');
    $email = sanitizeForDatabase($_POST['email'] ?? '');

    if (empty($username) || empty($email)) {
        $errors[] = "I campi Username ed Email sono obbligatori.";
    }

    if (empty($errors)) {
        // Aggiorna i dati dell'utente
        $query = "UPDATE users SET username = ?, email = ? WHERE id = ?";
        $stmt = executeQuery($query, [$username, $email, $user_id], 'ssi');

        if ($stmt) {
            $success = true;
            // Aggiorna i dati nella sessione
            $_SESSION['username'] = $username;
            // Aggiorna l'array $user
            $user['username'] = $username;
            $user['email'] = $email;
        } else {
            $errors[] = "Errore durante l'aggiornamento dei dati.";
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
    <title>Profilo Utente - CRM Admin</title>
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.10" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Custom CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.10" rel="stylesheet">
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
                    <h1 class="h3 mb-4 text-gray-800">Profilo Utente</h1>

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
                            <p>Dati aggiornati con successo.</p>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Form per il profilo utente -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-primary">
                            <h6 class="m-0 font-weight-bold text-white">Modifica Profilo</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <!-- Token CSRF -->
                                <?php csrfInputField(); ?>

                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input type="text" name="username" id="username" class="form-control" required value="<?php echo sanitizeForHTML($user['username']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" name="email" id="email" class="form-control" required value="<?php echo sanitizeForHTML($user['email']); ?>">
                                </div>
                                <!-- Aggiungi altri campi se necessario -->
                                <button type="submit" class="btn btn-primary">Aggiorna Profilo</button>
                            </form>
                        </div>
                    </div>

                </div>
                <!-- End of Page Content -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; La Tua Azienda 2024</span>
                    </div>
                </div>
            </footer>
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
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Sei pronto a terminare la tua sessione corrente?</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Annulla</button>
                    <a class="btn btn-primary" href="<?php echo sanitizeForHTML($base_url); ?>logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- SB Admin 2 JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>

</body>
</html>
