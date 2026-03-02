<?php
// email_reminder.php



require 'config.php';
checkLogin();
checkUserRole('admin');

// Includi PHPMailer tramite Composer
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Recupera il template di reminder
$templateQuery = "SELECT * FROM email_templates WHERE name = 'Reminder Iscrizione' LIMIT 1";
$templateStmt = $mysqli->prepare($templateQuery);
$templateStmt->execute();
$templateResult = $templateStmt->get_result();
$template = $templateResult->fetch_assoc();
$templateStmt->close();

// Recupera le impostazioni SMTP
$smtpQuery = "SELECT * FROM smtp_settings LIMIT 1";
$smtpStmt = $mysqli->prepare($smtpQuery);
$smtpStmt->execute();
$smtpResult = $smtpStmt->get_result();
$smtpSettings = $smtpResult->fetch_assoc();
$smtpStmt->close();

// Gestione della richiesta POST per aggiornare il template o SMTP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Token CSRF non valido.';
    } elseif (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_template') {
            // Aggiorna il template email
            $subject = trim($_POST['subject']);
            $body = trim($_POST['body']);

            if (empty($subject) || empty($body)) {
                $error_message = "Sia l'oggetto che il corpo dell'email sono richiesti.";
            } else {
                $updateTemplateQuery = "UPDATE email_templates SET subject = ?, body = ? WHERE id = ?";
                $updateTemplateStmt = $mysqli->prepare($updateTemplateQuery);
                $updateTemplateStmt->bind_param('ssi', $subject, $body, $template['id']);
                if ($updateTemplateStmt->execute()) {
                    $success_message = "Template email aggiornato con successo.";
                } else {
                    $error_message = "Errore nell'aggiornamento del template: " . $mysqli->error;
                }
                $updateTemplateStmt->close();
            }
        } elseif ($_POST['action'] === 'update_smtp') {
            // Aggiorna le impostazioni SMTP
            $host = trim($_POST['host']);
            $port = intval($_POST['port']);
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $encryption = $_POST['encryption'];
            $from_email = trim($_POST['from_email']);
            $from_name = trim($_POST['from_name']);

            // Validazioni di base
            if (empty($host) || empty($port) || empty($username) || empty($password) || empty($encryption) || empty($from_email) || empty($from_name)) {
                $error_message = "Tutti i campi SMTP sono richiesti.";
            } elseif (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "L'indirizzo email del mittente non è valido.";
            } else {
                $updateSMTPQuery = "UPDATE smtp_settings SET host = ?, port = ?, username = ?, password = ?, encryption = ?, from_email = ?, from_name = ? WHERE id = ?";
                $updateSMTPStmt = $mysqli->prepare($updateSMTPQuery);
                $updateSMTPStmt->bind_param('sisssssi', $host, $port, $username, $password, $encryption, $from_email, $from_name, $smtpSettings['id']);
                if ($updateSMTPStmt->execute()) {
                    $success_message = "Impostazioni SMTP aggiornate con successo.";
                    // Aggiorna le variabili per riflettere i nuovi valori
                    $smtpSettings['host'] = $host;
                    $smtpSettings['port'] = $port;
                    $smtpSettings['username'] = $username;
                    $smtpSettings['password'] = $password;
                    $smtpSettings['encryption'] = $encryption;
                    $smtpSettings['from_email'] = $from_email;
                    $smtpSettings['from_name'] = $from_name;
                } else {
                    $error_message = "Errore nell'aggiornamento delle impostazioni SMTP: " . $mysqli->error;
                }
                $updateSMTPStmt->close();
            }
        }
    }
}

// Funzione per inviare email (può essere utilizzata in altri script)
function sendReminderEmail($lavoratore, $smtpSettings, $template) {
    $mail = new PHPMailer(true);
    try {
        // Configurazione SMTP
        $mail->isSMTP();
        $mail->Host = $smtpSettings['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpSettings['username'];
        $mail->Password = $smtpSettings['password'];
        $mail->SMTPSecure = $smtpSettings['encryption'];
        $mail->Port = $smtpSettings['port'];

        // Mittente e destinatario
        $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name']);
        $mail->addAddress($lavoratore['email'], $lavoratore['nome'] . ' ' . $lavoratore['cognome']);

        // Contenuto dell'email
        $mail->isHTML(false);
        $mail->Subject = $template['subject'];
        
        // Sostituzioni dinamiche nel corpo dell'email
        $body = str_replace(['{{nome}}', '{{data_fine}}'], [ $lavoratore['nome'], date('d-m-Y', strtotime($lavoratore['data_fine'])) ], $template['body']);
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        $workerId = isset($lavoratore['id']) ? (int)$lavoratore['id'] : 0;
        error_log("Errore nell'invio dell'email a lavoratore ID {$workerId}: {$mail->ErrorInfo}");
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Configurazione Reminder Email</title>
    <!-- Meta viewport per la responsività -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- SB Admin 2 CSS (includes Bootstrap) -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.6" rel="stylesheet">
    <!-- Custom CSS (se necessario) -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.6" rel="stylesheet">
    <style>
        /* Stili personalizzati */
        .hidden-editor {
            display: none;
        }
    </style>
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
                    <button onclick="location.href='gestione_iscrizioni.php'" class="btn btn-secondary mb-4">
                        <i class="fas fa-arrow-left"></i> Torna alla Gestione Iscrizioni
                    </button>

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Configurazione Reminder Email</h1>
                        <button id="toggleEditorBtn" class="btn btn-primary">Mostra Messaggio Reminder di Iscrizione</button>
                    </div>

                    <!-- Messaggi di Successo o Errore -->
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($success_message); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($error_message); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Sezione Template Email -->
                    <div class="card mb-4 hidden-editor" id="templateCard">
                        <div class="card-header">
                            <i class="fas fa-envelope mr-1"></i>
                            Template Email Reminder
                        </div>
                        <div class="card-body">
                            <form method="POST" action="email_reminder.php">
                                <input type="hidden" name="action" value="update_template">
                                <?php csrfInputField(); ?>
                                <div class="form-group">
                                    <label for="subject">Oggetto</label>
                                    <input type="text" class="form-control" id="subject" name="subject" required value="<?php echo sanitizeForHTML($template['subject']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="body">Corpo del Messaggio</label>
                                    <textarea class="form-control" id="body" name="body" rows="10" required><?php echo sanitizeForHTML($template['body']); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-success">Salva Template</button>
                            </form>
                        </div>
                    </div>

                    <!-- Sezione Impostazioni SMTP -->
                    <div class="card mb-4 hidden-editor" id="smtpCard">
                        <div class="card-header">
                            <i class="fas fa-cogs mr-1"></i>
                            Impostazioni SMTP
                        </div>
                        <div class="card-body">
                            <form method="POST" action="email_reminder.php">
                                <input type="hidden" name="action" value="update_smtp">
                                <?php csrfInputField(); ?>
                                <div class="form-group">
                                    <label for="host">Host SMTP</label>
                                    <input type="text" class="form-control" id="host" name="host" required value="<?php echo sanitizeForHTML($smtpSettings['host']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="port">Porta SMTP</label>
                                    <input type="number" class="form-control" id="port" name="port" required value="<?php echo sanitizeForHTML($smtpSettings['port']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="username">Username SMTP</label>
                                    <input type="text" class="form-control" id="username" name="username" required value="<?php echo sanitizeForHTML($smtpSettings['username']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="password">Password SMTP</label>
                                    <input type="password" class="form-control" id="password" name="password" required value="<?php echo sanitizeForHTML($smtpSettings['password']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="encryption">Crittografia</label>
                                    <select id="encryption" name="encryption" class="form-control" required>
                                        <option value="tls" <?php echo ($smtpSettings['encryption'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo ($smtpSettings['encryption'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="from_email">Email Mittente</label>
                                    <input type="email" class="form-control" id="from_email" name="from_email" required value="<?php echo sanitizeForHTML($smtpSettings['from_email']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="from_name">Nome Mittente</label>
                                    <input type="text" class="form-control" id="from_name" name="from_name" required value="<?php echo sanitizeForHTML($smtpSettings['from_name']); ?>">
                                </div>
                                <button type="submit" class="btn btn-success">Salva Impostazioni SMTP</button>
                            </form>
                        </div>
                    </div>

                    <!-- Pulsanti per Gestire l'Editor e le Impostazioni SMTP -->
                    <div class="mb-4">
                        <button id="toggleSMTPBtn" class="btn btn-secondary">Mostra Impostazioni SMTP</button>
                    </div>

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

    <!-- TinyMCE -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>vendor/tinymce/tinymce.min.js"></script>

    <!-- Inizializzazione di TinyMCE (deferred fino a quando il template è visibile) -->
    <script>
        var tinymceBodyInitialized = false;
        function initTinyMCEBody() {
            if (!tinymceBodyInitialized && typeof tinymce !== 'undefined') {
                tinymce.init({
                    selector: '#body',
                    height: 300,
                    plugins: 'advlist autolink lists link image charmap preview anchor pagebreak',
                    toolbar: 'undo redo | formatselect | bold italic backcolor | ' +
                             'alignleft aligncenter alignright alignjustify | ' +
                             'bullist numlist outdent indent | removeformat | help',
                    menubar: false,
                    branding: false,
                    entity_encoding: 'raw',
                    forced_root_block: 'p',
                    base_url: '<?php echo sanitizeForHTML($base_url); ?>vendor/tinymce',
                    suffix: '.min',
                    license_key: 'gpl',
                });
                tinymceBodyInitialized = true;
            }
        }
    </script>

    <!-- Script per Gestire la Visualizzazione degli Editor -->
    <script>
        document.getElementById('toggleEditorBtn').addEventListener('click', function() {
            var templateCard = document.getElementById('templateCard');
            var smtpCard = document.getElementById('smtpCard');
            if (templateCard.classList.contains('hidden-editor')) {
                templateCard.classList.remove('hidden-editor');
                this.textContent = 'Nascondi Messaggio Reminder di Iscrizione';
                // Inizializza TinyMCE solo dopo che il contenitore è visibile
                initTinyMCEBody();
            } else {
                templateCard.classList.add('hidden-editor');
                this.textContent = 'Mostra Messaggio Reminder di Iscrizione';
            }
        });

        document.getElementById('toggleSMTPBtn').addEventListener('click', function() {
            var smtpCard = document.getElementById('smtpCard');
            var templateCard = document.getElementById('templateCard');
            if (smtpCard.classList.contains('hidden-editor')) {
                smtpCard.classList.remove('hidden-editor');
                this.textContent = 'Nascondi Impostazioni SMTP';
            } else {
                smtpCard.classList.add('hidden-editor');
                this.textContent = 'Mostra Impostazioni SMTP';
            }
        });
    </script>

</body>
</html>
