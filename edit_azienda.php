<?php
require 'config.php';
checkLogin();

if (isset($_GET['id'])) {
    $azienda_id = intval($_GET['id']);

    // Recupera i dati dell'azienda
    $query = "SELECT * FROM aziende WHERE id = ?";
    $stmt = executeQuery($query, [$azienda_id], 'i');
    if ($stmt === false) {
        echo "Errore nella query.";
        exit;
    }
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $azienda = $result->fetch_assoc();
    } else {
        echo "Azienda non trovata.";
        exit;
    }
} else {
    echo "ID azienda non specificato.";
    exit;
}

// Gestione dei messaggi di errore
$update_error = isset($_GET['update_error']) ? sanitizeForHTML($_GET['update_error']) : '';

// Genera un token CSRF per il form
generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica Azienda - CRM Admin</title>
    <!-- Meta viewport per la responsività -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.5" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- jQuery UI CSS per l'autocomplete -->
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.css">
    <!-- TinyMCE -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
    <!-- Custom CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.5" rel="stylesheet">
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
                    <h1 class="h3 mb-4 text-gray-800">Modifica Azienda: <?php echo sanitizeForHTML($azienda['nome_azienda']); ?></h1>

                    <!-- Messaggi di errore -->
                    <?php if (!empty($update_error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <p><?php echo $update_error; ?></p>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Form per modificare l'azienda -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold">Dettagli Azienda</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="update_azienda.php" class="row g-3">
                                <!-- Token CSRF -->
                                <?php csrfInputField(); ?>
                                <input type="hidden" name="id" value="<?php echo sanitizeForHTML($azienda_id); ?>">

                                <!-- Dati azienda -->
                                <div class="col-md-6">
                                    <label for="nome_azienda" class="form-label">Nome Azienda <span class="text-danger">*</span></label>
                                    <input type="text" name="nome_azienda" id="nome_azienda" class="form-control" required value="<?php echo sanitizeForHTML($azienda['nome_azienda']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="partita_iva" class="form-label">Partita IVA</label>
                                    <input type="text" name="partita_iva" id="partita_iva" class="form-control" maxlength="11" pattern="\d{11}" title="La Partita IVA deve contenere esattamente 11 cifre." value="<?php echo sanitizeForHTML($azienda['partita_iva']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="indirizzo_via" class="form-label">Indirizzo (Via)</label>
                                    <input type="text" name="indirizzo_via" id="indirizzo_via" class="form-control" value="<?php echo sanitizeForHTML($azienda['indirizzo_via']); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="indirizzo_numero_civico" class="form-label">N. Civico</label>
                                    <input type="text" name="indirizzo_numero_civico" id="indirizzo_numero_civico" class="form-control" value="<?php echo sanitizeForHTML($azienda['indirizzo_numero_civico']); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="indirizzo_cap" class="form-label">CAP</label>
                                    <input type="text" name="indirizzo_cap" id="indirizzo_cap" class="form-control" pattern="\d{5}" title="Il CAP deve contenere esattamente 5 cifre." value="<?php echo sanitizeForHTML($azienda['indirizzo_cap']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="indirizzo_citta" class="form-label">Città</label>
                                    <input type="text" name="indirizzo_citta" id="indirizzo_citta" class="form-control" value="<?php echo sanitizeForHTML($azienda['indirizzo_citta']); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="indirizzo_provincia" class="form-label">Provincia</label>
                                    <input type="text" name="indirizzo_provincia" id="indirizzo_provincia" class="form-control" maxlength="2" pattern="[A-Za-z]{2}" title="La Provincia deve contenere esattamente 2 lettere." value="<?php echo sanitizeForHTML($azienda['indirizzo_provincia']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="telefono" class="form-label">Telefono</label>
                                    <input type="text" name="telefono" id="telefono" class="form-control" pattern="^\+?\d{7,15}$" title="Inserisci un numero di telefono valido." value="<?php echo sanitizeForHTML($azienda['telefono']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control" value="<?php echo sanitizeForHTML($azienda['email']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="settore" class="form-label">Settore</label>
                                    <input type="text" name="settore" id="settore" class="form-control" value="<?php echo sanitizeForHTML($azienda['settore']); ?>">
                                </div>
                                <!-- Campo PEC -->
                                <div class="col-md-6">
                                    <label for="pec" class="form-label">PEC</label>
                                    <input type="email" name="pec" id="pec" class="form-control" value="<?php echo sanitizeForHTML($azienda['pec']); ?>">
                                </div>
                                <!-- Note con TinyMCE -->
                                <div class="col-md-12">
                                    <label for="note" class="form-label">Note</label>
                                    <textarea name="note" id="note" class="form-control" rows="5"><?php echo sanitizeHTML($azienda['note'] ?? ''); ?></textarea>
                                </div>
                                <!-- Pulsanti -->
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">Salva Modifiche</button>
                                    <a href="azienda.php?id=<?php echo sanitizeForHTML($azienda_id); ?>" class="btn btn-secondary">Annulla</a>
                                </div>
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

    <!-- Logout Modal -->
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
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.js"></script> <!-- jQuery UI JS -->

    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- SB Admin 2 JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>

    <!-- Inizializzazione di TinyMCE -->
    <script>
        tinymce.init({
            selector: '#note',
            plugins: 'advlist autolink lists link image charmap preview anchor pagebreak',
            toolbar: 'undo redo | formatselect | bold italic backcolor | ' +
                     'alignleft aligncenter alignright alignjustify | ' +
                     'bullist numlist outdent indent | removeformat | help',
            toolbar_mode: 'floating',
            menubar: false,
            branding: false,
            height: 300,
            license_key: 'gpl', // Aggiunto per risolvere l'avviso di licenza
            setup: function (editor) {
                editor.on('init', function () {
                    this.getContainer().style.zIndex = 10000;
                });
            }
        });
    </script>

    <!-- Inizializzazione di jQuery UI Autocomplete (Esempio) -->
    <script>
        $(document).ready(function() {
            // Esempio di utilizzo di autocomplete per il campo 'nome_azienda'
            $("#nome_azienda").autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: "<?php echo sanitizeForHTML($base_url); ?>fetch_aziende.php",
                        type: "POST",
                        dataType: "json",
                        data: {
                            term: request.term
                        },
                        success: function(data) {
                            response(data);
                        },
                        error: function() {
                            response([]);
                        }
                    });
                },
                minLength: 2,
                delay: 300
            });
        });
    </script>

</body>
</html>
