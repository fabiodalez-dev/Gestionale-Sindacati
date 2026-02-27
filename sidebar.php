<?php
$settings = getSettings(['logo', 'nome_app']);
$logo_path = $settings['logo'] ?? 'uploads/default_logo.png';
$nome_app = $settings['nome_app'] ?? 'Padova';

// Recupera connessioni attive per la sidebar (solo admin)
$active_connections = [];
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $conn_stmt = executeQuery("SELECT id, name FROM api_connections WHERE is_active = 1 ORDER BY name ASC", [], '');
    if ($conn_stmt) {
        $conn_result = $conn_stmt->get_result();
        while ($row = $conn_result->fetch_assoc()) {
            $active_connections[] = $row;
        }
    }
}
?>
<!-- Sidebar -->
<ul class="navbar-nav sidebar sidebar-light accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?php echo $base_url; ?>dashboard.php">
        <div class="sidebar-brand-icon">
            <img src="<?php echo sanitizeForHTML($base_url . $logo_path); ?>" alt="logo ADL" width="90" height="auto">
			<h2 style="color:#000; font-size:1rem; font-weight:700;"><?php echo sanitizeForHTML($nome_app); ?></h2>
        </div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo $base_url; ?>dashboard.php">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span></a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        Interfacce
    </div>

    <!-- Nav Item - Lavoratori -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'lavoratori.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo $base_url; ?>lavoratori.php">
            <i class="fas fa-fw fa-user"></i>
            <span>Lavoratori</span></a>
    </li>

    <!-- Nav Item - Lavoratori Archiviati -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'archived_lavoratori.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo $base_url; ?>archived_lavoratori.php">
            <i class="fas fa-fw fa-archive"></i>
            <span>Lavoratori Archiviati</span></a>
    </li>

    <!-- Nav Item - Aziende -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'aziende.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo $base_url; ?>aziende.php">
            <i class="fas fa-fw fa-building"></i>
            <span>Aziende</span></a>
    </li>

    <!-- Nav Item - Gestione Iscrizioni -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'gestione_iscrizioni.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo $base_url; ?>gestione_iscrizioni.php">
            <i class="fas fa-fw fa-edit"></i>
            <span>Gestione Iscrizioni</span></a>
    </li>
	<!-- Nav Item - Gestione sedi -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sedi.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo $base_url; ?>sedi.php">
            <i class="fas fa-fw fa-globe"></i>
            <span>Gestione Sedi</span></a>
    </li>

    <?php if ($_SESSION['user_role'] === 'admin'): ?>
    <!-- Nav Item - Gestione CCNL -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'gestione_ccnl.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo $base_url; ?>gestione_ccnl.php">
            <i class="fas fa-fw fa-file-contract"></i>
            <span>Gestione CCNL</span></a>
    </li>
        <!-- Nav Item - Gestione Utenti -->
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'gestione_utenti.php' ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $base_url; ?>gestione_utenti.php">
                <i class="fas fa-fw fa-users-cog"></i>
                <span>Gestione Utenti</span></a>
        </li>
	 <!-- Nav Item - Gestione Backup -->
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $base_url; ?>backup.php">
                <i class="fas fa-fw fa-lock"></i>
                <span>Gestione Backup</span></a>
        </li>

        <!-- Nav Item - Plugin Manager -->
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'plugin_manager.php' ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $base_url; ?>plugin_manager.php">
                <i class="fas fa-fw fa-puzzle-piece"></i>
                <span>Gestione Plugin</span></a>
        </li>

        <!-- Connessioni Remote Dinamiche -->
        <?php if (!empty($active_connections)): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">CRM Collegati</div>
            <?php foreach ($active_connections as $remote_conn): ?>
                <?php
                $is_active_page = (basename($_SERVER['PHP_SELF']) === 'aziende_remote.php' || basename($_SERVER['PHP_SELF']) === 'azienda_remote.php')
                                  && intval($_GET['conn_id'] ?? 0) === intval($remote_conn['id']);
                ?>
                <li class="nav-item <?php echo $is_active_page ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?php echo $base_url; ?>aziende_remote.php?conn_id=<?php echo intval($remote_conn['id']); ?>">
                        <i class="fas fa-fw fa-globe"></i>
                        <span>Aziende <?php echo sanitizeForHTML($remote_conn['name']); ?></span></a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>

        <hr class="sidebar-divider">
        <div class="sidebar-heading">Amministrazione</div>

        <!-- Nav Item - Impostazioni -->
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $base_url; ?>settings.php">
                <i class="fas fa-fw fa-cog"></i>
                <span>Impostazioni</span></a>
        </li>

        <!-- Nav Item - Migrazioni -->
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'migrate.php' ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $base_url; ?>migrate.php">
                <i class="fas fa-fw fa-database"></i>
                <span>Migrazioni DB</span></a>
        </li>
    <?php endif; ?>

    <!-- Hook per aggiungere voci di menu personalizzate -->
    <?php doHook('sidebar_menu_items'); ?>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>
<!-- End of Sidebar -->
