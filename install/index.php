<?php
// install/index.php
session_start();

// Controlla se l'applicazione è già stata installata
if (file_exists('../config.php')) {
    die('L\'applicazione è già stata installata. Se desideri reinstallarla, elimina il file <code>config.php</code> e le tabelle del database.');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Installazione Applicazione</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/style.css">
    <!-- Optional: Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
    <div class="container install-container fade-in">
        <h2>Installazione dell'Applicazione</h2>

        <?php
        // Mostra eventuali messaggi di errore o successo
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }
        ?>

        <!-- Progress Bar -->
        <div class="progress">
            <div class="progress-bar bg-success" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">Step 1 of 4</div>
        </div>

        <!-- Step 1: Configurazione del Database -->
        <form id="install-form" action="process.php" method="POST" enctype="multipart/form-data">
            <div class="step step-1">
                <h4>Configurazione del Database</h4>
                <div class="form-group">
                    <label for="db_host">Host del Database:</label>
                    <input type="text" id="db_host" name="db_host" class="form-control" required placeholder="Es. localhost">
                </div>
                <div class="form-group">
                    <label for="db_name">Nome del Database:</label>
                    <input type="text" id="db_name" name="db_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="db_user">Nome Utente del Database:</label>
                    <input type="text" id="db_user" name="db_user" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="db_pass">Password del Database:</label>
                    <input type="password" id="db_pass" name="db_pass" class="form-control">
                </div>
                <button type="button" class="btn btn-primary mt-3" id="next-1">Avanti</button>
            </div>

            <!-- Step 2: Configurazione dell'Amministratore -->
            <div class="step d-none step-2">
                <h4>Configurazione dell'Amministratore</h4>
                <div class="form-group">
                    <label for="admin_username">Nome Utente Admin:</label>
                    <input type="text" id="admin_username" name="admin_username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="admin_email">Email Admin:</label>
                    <input type="email" id="admin_email" name="admin_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="admin_password">Password Admin:</label>
                    <input type="password" id="admin_password" name="admin_password" class="form-control" required>
                </div>
                <button type="button" class="btn btn-secondary mt-3" id="prev-2">Indietro</button>
                <button type="button" class="btn btn-primary mt-3" id="next-2">Avanti</button>
            </div>

            <!-- Step 3: Configurazioni Generali -->
            <div class="step d-none step-3">
                <h4>Configurazioni Generali</h4>
                <div class="form-group">
                    <label for="base_url">Base URL dell'Applicazione:</label>
                    <input type="text" id="base_url" name="base_url" class="form-control" required placeholder="Es. http://tuodominio.com/app/">
                </div>
                <div class="form-group">
                    <label for="logo">Carica il Logo:</label>
                    <input type="file" id="logo" name="logo" class="form-control" accept="image/*" required>
                </div>
                <div class="form-group">
                    <label for="accepted_file_formats">Formati File Accettati (separati da virgola):</label>
                    <input type="text" id="accepted_file_formats" name="accepted_file_formats" class="form-control" required placeholder="Es. jpg, jpeg, png, gif, pdf">
                </div>
                <button type="button" class="btn btn-secondary mt-3" id="prev-3">Indietro</button>
                <button type="button" class="btn btn-primary mt-3" id="next-3">Avanti</button>
            </div>

            <!-- Step 4: Revisione e Installazione -->
            <div class="step d-none step-4">
                <h4>Revisione e Installazione</h4>
                <p>Verifica che tutte le informazioni siano corrette prima di procedere con l'installazione.</p>
                <ul class="list-group">
                    <li class="list-group-item"><strong>Host del Database:</strong> <span id="review_db_host"></span></li>
                    <li class="list-group-item"><strong>Nome del Database:</strong> <span id="review_db_name"></span></li>
                    <li class="list-group-item"><strong>Nome Utente del Database:</strong> <span id="review_db_user"></span></li>
                    <li class="list-group-item"><strong>Nome Utente Admin:</strong> <span id="review_admin_username"></span></li>
                    <li class="list-group-item"><strong>Email Admin:</strong> <span id="review_admin_email"></span></li>
                    <li class="list-group-item"><strong>Base URL:</strong> <span id="review_base_url"></span></li>
                    <li class="list-group-item"><strong>Logo Caricato:</strong> <span id="review_logo"></span></li>
                    <li class="list-group-item"><strong>Formati File Accettati:</strong> <span id="review_accepted_file_formats"></span></li>
                </ul>
                <button type="button" class="btn btn-secondary mt-3" id="prev-4">Indietro</button>
                <button type="submit" class="btn btn-success mt-3">Installa</button>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS and dependencies (Popper.js) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Optional: jQuery (for easier DOM manipulation) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS for Installer -->
    <script>
        $(document).ready(function() {
            // Funzione per passare allo step successivo
            function nextStep(currentStep, nextStep) {
                // Aggiorna progress bar
                var progress = (nextStep / 4) * 100;
                $('.progress-bar').css('width', progress + '%').attr('aria-valuenow', progress).text('Step ' + nextStep + ' di 4');
                
                // Nascondi current step e mostra next step
                $('.step-' + currentStep).addClass('d-none');
                $('.step-' + nextStep).removeClass('d-none').addClass('fade-in');
                
                // Scrolla in alto
                $('html, body').animate({ scrollTop: 0 }, 'fast');
            }

            // Funzione per passare allo step precedente
            function prevStep(currentStep, prevStep) {
                // Aggiorna progress bar
                var progress = (prevStep / 4) * 100;
                $('.progress-bar').css('width', progress + '%').attr('aria-valuenow', progress).text('Step ' + prevStep + ' di 4');
                
                // Nascondi current step e mostra prev step
                $('.step-' + currentStep).addClass('d-none');
                $('.step-' + prevStep).removeClass('d-none').addClass('fade-in');
                
                // Scrolla in alto
                $('html, body').animate({ scrollTop: 0 }, 'fast');
            }

            // Passa allo step 2
            $('#next-1').click(function() {
                nextStep(1, 2);
            });

            // Passa allo step 3
            $('#next-2').click(function() {
                nextStep(2, 3);
            });

            // Passa allo step 4 e popola la revisione
            $('#next-3').click(function() {
                // Popola la revisione
                $('#review_db_host').text($('#db_host').val());
                $('#review_db_name').text($('#db_name').val());
                $('#review_db_user').text($('#db_user').val());
                $('#review_admin_username').text($('#admin_username').val());
                $('#review_admin_email').text($('#admin_email').val());
                $('#review_base_url').text($('#base_url').val());

                // Mostra il nome del file caricato
                var logoName = $('#logo')[0].files[0].name;
                $('#review_logo').text(logoName);

                $('#review_accepted_file_formats').text($('#accepted_file_formats').val());

                nextStep(3, 4);
            });

            // Torna allo step 1
            $('#prev-2').click(function() {
                prevStep(2, 1);
            });

            // Torna allo step 2
            $('#prev-3').click(function() {
                prevStep(3, 2);
            });

            // Torna allo step 3
            $('#prev-4').click(function() {
                prevStep(4, 3);
            });
        });
    </script>
</body>
</html>
