<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <!-- Bottone per togglare la sidebar su dispositivi mobili -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3" aria-label="Apri Sidebar">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Barra di Ricerca con Autocomplete -->
    <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search" id="topbarSearchForm">
        <div class="input-group">
            <input type="text" id="topbarSearch" class="form-control bg-light border-0 small ui-autocomplete-input" placeholder="Cerca lavoratore o lavoratrice..." aria-label="Search" aria-describedby="basic-addon2" autocomplete="off">
            <div class="input-group-append">
                <button class="btn btn-primary" type="button" id="topbarSearchButton">
                    <i class="fas fa-search fa-sm"></i>
                </button>
            </div>
        </div>
    </form>

    <!-- Hook per inserire contenuti personalizzati nella topbar -->
    <?php doHook('topbar_custom_content'); ?>

    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
	<link rel="stylesheet" href="styles.css">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Navbar -->
    <ul class="navbar-nav ml-auto">
		                <!-- Manuale -->

 <li class="nav-item">
	 <a class="nav-link" href="manuale.php" id="manuale" role="button"
               >
                <i class="fas  fa-book-open"></i>
                <!-- Contatore - Messaggi -->
            </a></li>
        <!-- Nav Item - Messaggi (opzionale, puoi rimuoverlo se non necessario) -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button"
               data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-envelope fa-fw"></i>
                <!-- Contatore - Messaggi -->
                <span class="badge badge-danger badge-counter">7</span>
            </a>
            <!-- Dropdown - Messaggi -->
            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                 aria-labelledby="messagesDropdown">
                <h6 class="dropdown-header">
                    Centro Messaggi
                </h6>
                <!-- Esempio di messaggio -->
                <!-- ... -->
                <a class="dropdown-item text-center small text-gray-500" href="#">Leggi Tutti i Messaggi</a>
            </div>
        </li>

        <div class="topbar-divider d-none d-sm-block"></div>

        <!-- Nav Item - Informazioni Utente -->
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
               data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <img class="img-profile rounded-circle" src="<?php echo $base_url; ?>theme/img/undraw_profile.svg">
            </a>
            <!-- Dropdown - Informazioni Utente -->
            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                 aria-labelledby="userDropdown">
                <a class="dropdown-item" href="<?php echo $base_url; ?>profile.php">
                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                    Profilo
                </a>
                <a class="dropdown-item" href="<?php echo $base_url; ?>settings.php">
                    <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                    Impostazioni
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?php echo $base_url; ?>logout.php">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                    Logout
                </a>
            </div>
        </li>

    </ul>

</nav>

<!-- Hook per inserire script personalizzati nella topbar -->
<?php doHook('topbar_custom_scripts'); ?>

<!-- jQuery prima di jQuery UI -->
<script src="/theme/vendor/jquery/jquery.min.js"></script>
<!-- jQuery UI JavaScript -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<!-- SweetAlert2 JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- JavaScript Personalizzato -->
<script>
    $(document).ready(function() {
        // Funzione per verificare se l'utente è su un dispositivo mobile
        function isMobile() {
            return window.matchMedia("(max-width: 767.98px)").matches;
        }

        // Aggiungi la classe 'toggled' alla sidebar se è un dispositivo mobile all'apertura della pagina
        if (isMobile()) {
            $(".sidebar").addClass("toggled");
        }

        // Funzione per navigare a lavoratori.php con il filtro di ricerca
        function searchInLavoratori(query) {
            if (query.trim() !== '') {
                window.location.href = '<?php echo $base_url; ?>lavoratori.php?search=' + encodeURIComponent(query.trim());
            }
        }

        // Autocomplete per la barra di ricerca
        $("#topbarSearch").autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: '<?php echo $base_url; ?>search_lavoratori.php',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        term: request.term
                    },
                    success: function(data) {
                        response($.map(data, function(item) {
                            return {
                                label: item.label,
                                value: item.value,
                                searchLabel: item.label
                            };
                        }));
                    },
                    error: function(xhr, status, error) {
                        console.error("Errore nella richiesta AJAX:", status, error);
                        response([]);
                    }
                });
            },
            minLength: 2,
            select: function(event, ui) {
                // Quando si seleziona un risultato, naviga alla scheda del lavoratore
                if (ui.item && ui.item.value) {
                    window.location.href = '<?php echo $base_url; ?>lavoratore.php?id=' + ui.item.value;
                }
                return false;
            }
        });

        // Enter nel campo di ricerca → vai a lavoratori.php con filtro
        $("#topbarSearchForm").on('submit', function(e) {
            e.preventDefault();
            searchInLavoratori($("#topbarSearch").val());
        });

        // Click sul pulsante di ricerca → vai a lavoratori.php con filtro
        $("#topbarSearchButton").on('click', function() {
            searchInLavoratori($("#topbarSearch").val());
        });
    });
</script>
<!-- End of Topbar -->
