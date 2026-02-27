<?php
require 'config.php';
checkLogin();

// Inizializza l'array degli errori
$errors = [];

// Se il form è stato inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica il token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Token CSRF mancante o non valido.";
    }

    // Recupero e sanitizzazione dei dati dell'azienda
    $nome_azienda = sanitizeInput($_POST['nome_azienda'] ?? '');
    $partita_iva = !empty($_POST['partita_iva']) ? sanitizeInput($_POST['partita_iva']) : null;
    $indirizzo_via = !empty($_POST['indirizzo_via']) ? sanitizeInput($_POST['indirizzo_via']) : null;
    $indirizzo_numero_civico = !empty($_POST['indirizzo_numero_civico']) ? sanitizeInput($_POST['indirizzo_numero_civico']) : null;
    $indirizzo_cap = !empty($_POST['indirizzo_cap']) ? sanitizeInput($_POST['indirizzo_cap']) : null;
    $indirizzo_citta = !empty($_POST['indirizzo_citta']) ? sanitizeInput($_POST['indirizzo_citta']) : null;
    $indirizzo_provincia = !empty($_POST['indirizzo_provincia']) ? sanitizeInput($_POST['indirizzo_provincia']) : null;
    $telefono = !empty($_POST['telefono']) ? sanitizeInput($_POST['telefono']) : null;
    $email = !empty($_POST['email']) ? sanitizeInput($_POST['email']) : null;
    $settore = !empty($_POST['settore']) ? sanitizeInput($_POST['settore']) : null;
    $pec = !empty($_POST['pec']) ? sanitizeInput($_POST['pec']) : null; // Recupera il campo PEC
    $note = !empty($_POST['note']) ? $_POST['note'] : null;

    // Recupero e sanitizzazione dei dati delle unità operative
    $unita_operativa_nomi = $_POST['unita_operativa_nome'] ?? [];
    $unita_operativa_descrizioni = $_POST['unita_operativa_descrizione'] ?? [];

    // Validazione dei campi obbligatori dell'azienda
    if (empty($nome_azienda)) {
        $errors[] = "Il campo 'Nome Azienda' è obbligatorio.";
    }

    // Validazione della Partita IVA
    if (!empty($partita_iva) && !preg_match('/^\d{11}$/', $partita_iva)) {
        $errors[] = "La Partita IVA deve contenere esattamente 11 cifre.";
    }

    // Validazione CAP e Provincia
    if (!empty($indirizzo_cap) && !preg_match('/^\d{5}$/', $indirizzo_cap)) {
        $errors[] = "Il CAP deve contenere esattamente 5 cifre.";
    }
    if (!empty($indirizzo_provincia) && !preg_match('/^[A-Za-z]{2}$/', $indirizzo_provincia)) {
        $errors[] = "La Provincia deve contenere esattamente 2 lettere.";
    }
    if (!empty($telefono) && !preg_match('/^\+?\d{7,15}$/', $telefono)) {
        $errors[] = "Inserisci un numero di telefono valido.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Inserisci un indirizzo email valido.";
    }
    if (!empty($pec) && !filter_var($pec, FILTER_VALIDATE_EMAIL)) { // Validazione PEC come email
        $errors[] = "Inserisci un indirizzo PEC valido.";
    }

    // Validazione delle unità operative (solo se fornite)
    foreach ($unita_operativa_nomi as $index => $nome_unita) {
        $nome_unita = trim($nome_unita);
        if (!empty($nome_unita)) {
            // Esempio di ulteriore validazione: lunghezza massima
            if (strlen($nome_unita) > 255) {
                $errors[] = "Il nome dell'unità operativa #" . ($index + 1) . " non può superare 255 caratteri.";
            }
            // Puoi aggiungere altre validazioni qui (es. caratteri validi)
        }
    }

    if (empty($errors)) {
        // Verifica se il nome dell'azienda è unico
        $query = "SELECT id FROM aziende WHERE nome_azienda = ?";
        $stmt = executeQuery($query, [$nome_azienda], 's');
        if ($stmt) {
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "Esiste già un'azienda con il nome '$nome_azienda'. Per favore, scegli un altro nome.";
            }
        } else {
            $errors[] = "Errore durante la verifica dell'unicità del nome aziendale.";
        }

        // Se non ci sono errori, procedi con l'inserimento
        if (empty($errors)) {
            // Inizia una transazione per assicurare la consistenza dei dati
            $mysqli->begin_transaction();

            try {
                // Inserimento dell'azienda nel database
                $query = "
                    INSERT INTO aziende (
                        nome_azienda, partita_iva, indirizzo_via, indirizzo_numero_civico,
                        indirizzo_cap, indirizzo_citta, indirizzo_provincia, telefono, email,
                        settore, pec, note
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";

                $params = [
                    $nome_azienda,
                    $partita_iva,
                    $indirizzo_via,
                    $indirizzo_numero_civico,
                    $indirizzo_cap,
                    $indirizzo_citta,
                    $indirizzo_provincia,
                    $telefono,
                    $email,
                    $settore,
                    $pec,           // Aggiungi PEC ai parametri
                    $note
                ];

                // Definisci i tipi dei parametri in base ai campi (s = string)
                $types = '';
                foreach ($params as $param) {
                    $types .= 's';
                }

                // Esegui l'inserimento nel database
                $stmt = $mysqli->prepare($query);
                if (!$stmt) {
                    throw new Exception("Errore nella preparazione della query: " . $mysqli->error);
                }

                $stmt->bind_param($types, ...$params);
                if (!$stmt->execute()) {
                    throw new Exception("Errore durante l'inserimento dell'azienda: " . $stmt->error);
                }

                // Ottieni l'ID dell'azienda appena creata
                $new_azienda_id = $mysqli->insert_id;

                // Inserimento delle unità operative (se presenti)
                if (!empty($unita_operativa_nomi)) {
                    $insert_unita_query = "INSERT INTO unita_operativa (azienda_id, nome_unita_operativa, descrizione) VALUES (?, ?, ?)";
                    $stmt_unita = $mysqli->prepare($insert_unita_query);

                    if (!$stmt_unita) {
                        throw new Exception("Errore nella preparazione della query per le unità operative: " . $mysqli->error);
                    }

                    foreach ($unita_operativa_nomi as $index => $nome_unita) {
                        $nome_unita = sanitizeInput($nome_unita);
                        $descrizione_unita = sanitizeInput($unita_operativa_descrizioni[$index] ?? '');

                        // Salta le unità operative senza nome
                        if (empty($nome_unita)) {
                            continue;
                        }

                        $stmt_unita->bind_param("iss", $new_azienda_id, $nome_unita, $descrizione_unita);
                        if (!$stmt_unita->execute()) {
                            throw new Exception("Errore durante l'inserimento dell'unità operativa: " . $stmt_unita->error);
                        }
                    }

                    $stmt_unita->close();
                }

                // Conferma la transazione
                $mysqli->commit();

                // Reindirizza alla pagina dell'azienda appena creata con messaggio di successo
                header("Location: azienda.php?id=$new_azienda_id&add_success=1");
                exit;
            } catch (Exception $e) {
                // Annulla la transazione in caso di errore
                $mysqli->rollback();
                $errors[] = "Errore durante l'inserimento: " . sanitizeForHTML($e->getMessage());
            }
        }
    }

    // Genera un token CSRF per il form
    generateCsrfToken();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Aggiungi Azienda - CRM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- SB Admin 2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.5" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- SweetAlert2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <!-- TinyMCE -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
    <!-- Custom CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.5" rel="stylesheet">
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.css">
    <style>
        /* Eventuali stili personalizzati */
        .remove-unita-operativa-btn {
            margin-top: 32px;
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
                    <button onclick="history.back()" class="btn btn-secondary mb-4">
                        <i class="fas fa-arrow-left"></i> Indietro
                    </button>
                    <!-- Titolo della Pagina -->
                    <h1 class="h3 mb-4 text-gray-800">Aggiungi Nuova Azienda</h1>

                    <!-- Gestione dei Messaggi di Errore -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitizeForHTML($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Form per inserire una nuova azienda -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold">Dettagli Azienda</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="aggiungiAziendaForm" class="row g-3">
                                <!-- Token CSRF -->
                                <?php csrfInputField(); ?>

                                <!-- Nome Azienda (Obbligatorio) -->
                                <div class="col-md-6">
                                    <label for="nome_azienda" class="form-label">Nome Azienda <span class="text-danger">*</span></label>
                                    <input type="text" name="nome_azienda" id="nome_azienda" class="form-control" required value="<?php echo isset($_POST['nome_azienda']) ? sanitizeForHTML($_POST['nome_azienda']) : ''; ?>">
                                </div>

                                <!-- Partita IVA -->
                                <div class="col-md-6">
                                    <label for="partita_iva" class="form-label">Partita IVA</label>
                                    <input type="text" name="partita_iva" id="partita_iva" class="form-control" maxlength="11" pattern="\d{11}" title="La Partita IVA deve contenere esattamente 11 cifre." value="<?php echo isset($_POST['partita_iva']) ? sanitizeForHTML($_POST['partita_iva']) : ''; ?>">
                                </div>

                                <!-- Indirizzo Via -->
                                <div class="col-md-6">
                                    <label for="indirizzo_via" class="form-label">Indirizzo (Via)</label>
                                    <input type="text" name="indirizzo_via" id="indirizzo_via" class="form-control" value="<?php echo isset($_POST['indirizzo_via']) ? sanitizeForHTML($_POST['indirizzo_via']) : ''; ?>">
                                </div>

                                <!-- Numero Civico -->
                                <div class="col-md-2">
                                    <label for="indirizzo_numero_civico" class="form-label">N. Civico</label>
                                    <input type="text" name="indirizzo_numero_civico" id="indirizzo_numero_civico" class="form-control" value="<?php echo isset($_POST['indirizzo_numero_civico']) ? sanitizeForHTML($_POST['indirizzo_numero_civico']) : ''; ?>">
                                </div>

                                <!-- CAP -->
                                <div class="col-md-2">
                                    <label for="indirizzo_cap" class="form-label">CAP</label>
                                    <input type="text" name="indirizzo_cap" id="indirizzo_cap" class="form-control" pattern="\d{5}" title="Il CAP deve contenere esattamente 5 cifre." value="<?php echo isset($_POST['indirizzo_cap']) ? sanitizeForHTML($_POST['indirizzo_cap']) : ''; ?>">
                                </div>

                                <!-- Città -->
                                <div class="col-md-4">
                                    <label for="indirizzo_citta" class="form-label">Città</label>
                                    <input type="text" name="indirizzo_citta" id="indirizzo_citta" class="form-control" value="<?php echo isset($_POST['indirizzo_citta']) ? sanitizeForHTML($_POST['indirizzo_citta']) : ''; ?>">
                                </div>

                                <!-- Provincia -->
                                <div class="col-md-2">
                                    <label for="indirizzo_provincia" class="form-label">Provincia</label>
                                    <input type="text" name="indirizzo_provincia" id="indirizzo_provincia" class="form-control" maxlength="2" pattern="[A-Za-z]{2}" title="La Provincia deve contenere esattamente 2 lettere." value="<?php echo isset($_POST['indirizzo_provincia']) ? sanitizeForHTML($_POST['indirizzo_provincia']) : ''; ?>">
                                </div>

                                <!-- Telefono -->
                                <div class="col-md-6">
                                    <label for="telefono" class="form-label">Telefono</label>
                                    <input type="text" name="telefono" id="telefono" class="form-control" pattern="^\+?\d{7,15}$" title="Inserisci un numero di telefono valido." value="<?php echo isset($_POST['telefono']) ? sanitizeForHTML($_POST['telefono']) : ''; ?>">
                                </div>

                                <!-- Email -->
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control" value="<?php echo isset($_POST['email']) ? sanitizeForHTML($_POST['email']) : ''; ?>">
                                </div>

                                <!-- Settore -->
                                <div class="col-md-6">
                                    <label for="settore" class="form-label">Settore</label>
                                    <input type="text" name="settore" id="settore" class="form-control" value="<?php echo isset($_POST['settore']) ? sanitizeForHTML($_POST['settore']) : ''; ?>">
                                </div>

                                <!-- PEC -->
                                <div class="col-md-6">
                                    <label for="pec" class="form-label">PEC</label>
                                    <input type="email" name="pec" id="pec" class="form-control" value="<?php echo isset($_POST['pec']) ? sanitizeForHTML($_POST['pec']) : ''; ?>">
                                </div>

                                <!-- Note con TinyMCE -->
                                <div class="col-md-12">
                                    <label for="note" class="form-label">Note</label>
                                    <textarea name="note" id="note" class="form-control" rows="5"><?php echo isset($_POST['note']) ? sanitizeHTML($_POST['note']) : ''; ?></textarea>
                                </div>

                                <!-- Sezione Unità Operative -->
                                <div class="col-md-12">
                                    <label class="form-label">Unità Operative</label>
                                    <div id="unitaOperativaContainer">
                                        <!-- Primo set di campi per un'unità operativa -->
                                        <div class="form-row unita-operativa-group">
                                            <div class="form-group col-md-5">
                                                <input type="text" name="unita_operativa_nome[]" class="form-control" placeholder="Nome Unità Operativa">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <textarea name="unita_operativa_descrizione[]" class="form-control" placeholder="Descrizione" rows="1"></textarea>
                                            </div>
                                            <div class="form-group col-md-1">
                                                <button type="button" class="btn btn-danger remove-unita-operativa-btn" title="Rimuovi Unità Operativa"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" id="aggiungiUnitaOperativaBtn" class="btn btn-success mt-2">
                                        <i class="fas fa-plus"></i> Aggiungi Unità Operativa
                                    </button>
                                </div>

                                <!-- Pulsanti -->
                                <div class="col-md-12 mt-4">
                                    <button type="submit" class="btn btn-primary">Salva</button>
                                    <a href="aziende.php" class="btn btn-secondary">Annulla</a>
                                </div>
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

    <!-- Modali e script -->
    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- jQuery UI (per aggiungere/rimuovere unità operative) -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.js"></script>

    <!-- Bootstrap core JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- SB Admin 2 JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>

    <!-- SweetAlert2 per i messaggi -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/sweetalert2/sweetalert2.min.js"></script>

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
            license_key: 'gpl', // Aggiungi questa riga per risolvere l'avviso di licenza
            setup: function (editor) {
                editor.on('init', function () {
                    this.getContainer().style.zIndex = 10000;
                });
            }
        });
    </script>

    <!-- Script per Gestire l'Aggiunta/Rimozione delle Unità Operative con Feedback Utente -->
    <script>
        $(document).ready(function() {
            // Funzione per aggiungere un nuovo gruppo di unità operative
            $('#aggiungiUnitaOperativaBtn').click(function() {
                var unitaOperativaGroup = `
                    <div class="form-row unita-operativa-group">
                        <div class="form-group col-md-5">
                            <input type="text" name="unita_operativa_nome[]" class="form-control" placeholder="Nome Unità Operativa">
                        </div>
                        <div class="form-group col-md-6">
                            <textarea name="unita_operativa_descrizione[]" class="form-control" placeholder="Descrizione" rows="1"></textarea>
                        </div>
                        <div class="form-group col-md-1">
                            <button type="button" class="btn btn-danger remove-unita-operativa-btn" title="Rimuovi Unità Operativa"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                `;
                $('#unitaOperativaContainer').append(unitaOperativaGroup);

                // Mostra una notifica di aggiunta
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Unità Operativa aggiunta.',
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true
                });
            });

            // Funzione per rimuovere un gruppo di unità operative
            $('#unitaOperativaContainer').on('click', '.remove-unita-operativa-btn', function() {
                var parentGroup = $(this).closest('.unita-operativa-group');

                // Conferma la rimozione
                Swal.fire({
                    title: 'Sei sicuro?',
                    text: "Vuoi rimuovere questa Unità Operativa?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sì, rimuovi!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        parentGroup.remove();

                        // Mostra una notifica di rimozione
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Unità Operativa rimossa.',
                            showConfirmButton: false,
                            timer: 1500,
                            timerProgressBar: true
                        });
                    }
                });
            });
        });
    </script>

</body>
</html>
