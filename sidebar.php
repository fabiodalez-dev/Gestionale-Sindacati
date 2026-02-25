<?php
$settings = getSettings(['logo']);
$logo_path = $settings['logo'] ?? 'uploads/default_logo.png'; // Usa un logo predefinito se 'logo' non è impostato 
?>
<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?php echo $base_url; ?>dashboard.php">
        <div class="sidebar-brand-icon">
            <img src="<?php echo sanitizeForHTML($base_url . $logo_path); ?>" alt="logo ADL" width="90" height="auto">
			<h2 style="color:white; font-size:1rem;">Padova</h2>
        </div>
         <!-- <div class="sidebar-brand-text mx-3">ADL COBAS</div>-->
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
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'gestione_iscrizioni.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo $base_url; ?>sedi.php">
            <i class="fas fa-fw fa-globe"></i>
            <span>Gestione Sedi</span></a>
    </li>

    <?php if ($_SESSION['user_role'] === 'admin'): ?>
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
	<!-- Aziende Emilia Romagna-->
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'plugin_manager.php' ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $base_url; ?>aziende_emiliaromagna.php">
                <i class="fas fa-fw fa-globe"></i>
                <span>Aziende Emilia Romagna</span></a>
        </li>
	<!-- Nav Item - Plugin Manager -->
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'plugin_manager.php' ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $base_url; ?>aziende_alessandria.php">
                <i class="fas fa-fw fa-globe"></i>
                <span>Aziende Alessandria</span></a>
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
