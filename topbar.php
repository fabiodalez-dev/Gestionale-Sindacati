<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top">

    <!-- Bottone per togglare la sidebar su dispositivi mobili -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3" aria-label="Apri Sidebar">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Barra di Ricerca con Autocomplete (desktop) -->
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

    <!-- jQuery UI CSS (local) -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>theme/vendor/jquery-ui/jquery-ui.min.css">
    <!-- SweetAlert2 CSS (local) -->
    <link href="<?php echo $base_url; ?>theme/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">

    <!-- Navbar -->
    <ul class="navbar-nav ml-auto">
        <!-- Pulsante ricerca mobile -->
        <li class="nav-item d-sm-none">
            <a class="nav-link" href="#" role="button" id="mobileSearchToggle" aria-label="Cerca">
                <i class="fas fa-search"></i>
            </a>
        </li>
        <!-- Manuale -->
        <li class="nav-item">
            <a class="nav-link" href="manuale.php" role="button">
                <i class="fas fa-book-open"></i>
            </a>
        </li>

        <!-- Nav Item - Notifiche Calendario -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button"
               data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
                <span class="badge badge-danger badge-counter" id="notifBadge" style="display:none;">0</span>
            </a>
            <!-- Dropdown - Notifiche -->
            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                 aria-labelledby="notificationsDropdown" style="width: 380px; max-height: 420px; overflow-y: auto;">
                <h6 class="dropdown-header" style="background: var(--slate-900, #0f172a); color: #fff; border: none;">
                    <i class="fas fa-calendar-alt mr-1"></i> Eventi Calendario
                </h6>
                <div id="notifContainer">
                    <div class="text-center py-3 text-muted small">Caricamento...</div>
                </div>
                <a class="dropdown-item text-center small" href="<?php echo $base_url; ?>dashboard.php#calendar" style="font-weight: 500; color: var(--accent, #3b82f6);">
                    Vai al Calendario Completo
                </a>
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

<!-- Overlay sidebar mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Modal ricerca mobile -->
<div class="mobile-search-overlay" id="mobileSearchOverlay">
    <div class="mobile-search-box">
        <form id="mobileSearchForm">
            <div class="input-group">
                <input type="text" id="mobileSearchInput" class="form-control" placeholder="Cerca lavoratore o lavoratrice..." autocomplete="off" autofocus>
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit" style="border-radius: 0 var(--radius) var(--radius) 0;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Hook per inserire script personalizzati nella topbar -->
<?php doHook('topbar_custom_scripts'); ?>

<!-- jQuery prima di jQuery UI -->
<script src="<?php echo $base_url; ?>theme/vendor/jquery/jquery.min.js"></script>
<!-- jQuery UI (local) -->
<script src="<?php echo $base_url; ?>theme/vendor/jquery-ui/jquery-ui.min.js"></script>
<!-- SweetAlert2 (local) -->
<script src="<?php echo $base_url; ?>theme/vendor/sweetalert2/sweetalert2.min.js"></script>

<!-- JavaScript Personalizzato -->
<script>
$(document).ready(function() {
    // Mobile sidebar toggle with overlay
    var sidebarOverlay = $('#sidebarOverlay');

    if (window.matchMedia("(max-width: 767.98px)").matches) {
        $(".sidebar").addClass("toggled");
    }

    // SB Admin 2 toggles the class BEFORE our handler runs,
    // so when sidebar is open (no 'toggled' class), show overlay
    $('#sidebarToggleTop').on('click', function() {
        var sidebar = $(".sidebar");
        if (!sidebar.hasClass("toggled")) {
            sidebarOverlay.addClass('active');
        } else {
            sidebarOverlay.removeClass('active');
        }
    });

    sidebarOverlay.on('click', function() {
        $(".sidebar").addClass("toggled");
        sidebarOverlay.removeClass('active');
    });

    // Mobile search
    var mobileSearchOverlay = $('#mobileSearchOverlay');

    $('#mobileSearchToggle').on('click', function(e) {
        e.preventDefault();
        mobileSearchOverlay.addClass('active');
        setTimeout(function() { $('#mobileSearchInput').focus(); }, 100);
    });

    mobileSearchOverlay.on('click', function(e) {
        if (e.target === this) {
            mobileSearchOverlay.removeClass('active');
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            mobileSearchOverlay.removeClass('active');
            sidebarOverlay.removeClass('active');
            $(".sidebar").addClass("toggled");
        }
    });

    function searchInLavoratori(query) {
        if (query.trim() !== '') {
            window.location.href = '<?php echo $base_url; ?>lavoratori.php?search=' + encodeURIComponent(query.trim());
        }
    }

    // Autocomplete function factory
    function setupAutocomplete(selector) {
        $(selector).autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: '<?php echo $base_url; ?>search_lavoratori.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { term: request.term },
                    success: function(data) {
                        response($.map(data, function(item) {
                            return { label: item.label, value: item.value };
                        }));
                    },
                    error: function() { response([]); }
                });
            },
            minLength: 2,
            select: function(event, ui) {
                if (ui.item && ui.item.value) {
                    window.location.href = '<?php echo $base_url; ?>lavoratore.php?id=' + ui.item.value;
                }
                return false;
            }
        });
    }

    // Setup autocomplete on both desktop and mobile inputs
    setupAutocomplete("#topbarSearch");
    setupAutocomplete("#mobileSearchInput");

    $("#topbarSearchForm").on('submit', function(e) {
        e.preventDefault();
        searchInLavoratori($("#topbarSearch").val());
    });
    $("#topbarSearchButton").on('click', function() {
        searchInLavoratori($("#topbarSearch").val());
    });

    $("#mobileSearchForm").on('submit', function(e) {
        e.preventDefault();
        searchInLavoratori($("#mobileSearchInput").val());
    });

    // ── Notification System ──
    function loadNotifications() {
        $.ajax({
            url: '<?php echo $base_url; ?>fetch_notifications.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (!data.success) return;

                var badge = $('#notifBadge');
                var container = $('#notifContainer');

                // Update badge
                if (data.total_count > 0) {
                    badge.text(data.total_count).show();
                } else {
                    badge.hide();
                }

                // Build notification list
                var html = '';

                if (data.notifications.length === 0) {
                    html = '<div class="text-center py-3"><span class="text-muted small">Nessun evento nei prossimi giorni</span></div>';
                } else {
                    var lastCategory = '';
                    data.notifications.forEach(function(n) {
                        // Category header
                        if (n.category !== lastCategory) {
                            lastCategory = n.category;
                            var catLabel = '';
                            var catIcon = '';
                            if (n.category === 'today') {
                                catLabel = 'Oggi';
                                catIcon = 'fa-calendar-day';
                            } else if (n.category === 'upcoming') {
                                catLabel = 'Prossimi giorni';
                                catIcon = 'fa-calendar-plus';
                            } else {
                                catLabel = 'Recenti';
                                catIcon = 'fa-history';
                            }
                            html += '<div style="padding: 0.4rem 1rem; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; background: #f8fafc;">';
                            html += '<i class="fas ' + catIcon + ' mr-1"></i>' + catLabel;
                            html += '</div>';
                        }

                        // Build link
                        var link = '#';
                        if (n.lavoratore_id) {
                            link = '<?php echo $base_url; ?>lavoratore.php?id=' + n.lavoratore_id;
                        } else if (n.azienda_id) {
                            link = '<?php echo $base_url; ?>azienda.php?id=' + n.azienda_id;
                        } else {
                            link = '<?php echo $base_url; ?>dashboard.php';
                        }

                        var isToday = (n.category === 'today');

                        html += '<a class="dropdown-item d-flex align-items-start py-2" href="' + link + '" style="white-space: normal; border-left: 3px solid ' + (isToday ? '#3b82f6' : 'transparent') + ';">';
                        html += '<div class="mr-2 mt-1" style="min-width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; background: ' + (isToday ? '#dbeafe' : '#f1f5f9') + '; color: ' + (isToday ? '#2563eb' : '#64748b') + ';">';
                        html += '<i class="fas fa-calendar"></i>';
                        html += '</div>';
                        html += '<div style="flex: 1; min-width: 0;">';
                        html += '<div style="font-size: 0.82rem; font-weight: ' + (isToday ? '600' : '500') + '; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + escapeHtml(n.title) + '</div>';
                        if (n.subtitle) {
                            html += '<div style="font-size: 0.72rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + escapeHtml(n.subtitle) + '</div>';
                        }
                        html += '<div style="font-size: 0.7rem; color: #94a3b8; margin-top: 1px;">' + n.date + ' · ' + n.time + '</div>';
                        html += '</div>';
                        html += '</a>';
                    });
                }

                container.html(html);
            },
            error: function() {
                $('#notifContainer').html('<div class="text-center py-3 text-muted small">Errore nel caricamento</div>');
            }
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Load on page load
    loadNotifications();

    // Refresh every 5 minutes
    setInterval(loadNotifications, 5 * 60 * 1000);
});
</script>
<!-- End of Topbar -->
