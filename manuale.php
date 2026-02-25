<?php
// manuale.php

// Abilita la visualizzazione degli errori per lo sviluppo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
checkLogin();
generateCsrfToken();

?><!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Manuale Utente - Gestionale ADL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css" rel="stylesheet">
    <style>
        h2, h3 { scroll-margin-top: 90px; }
        .toc a { display: block; margin-bottom: 5px; }
		.card-body div {
    padding: 2rem 0;
    border-bottom: 1px solid gainsboro;
}
    </style>
	
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include 'sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include 'topbar.php'; ?>
                <div class="container-fluid">

                    <h1 class="mt-4">Manuale Utente - Gestionale ADL</h1>
                    <h5 class="mb-4">Sezione: Gestione Lavoratori</h5>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="mb-3">📑 Indice dei Contenuti (TOC)</h4>
                            <div class="toc">
                                <a href="#inserimento">📌 Inserimento Nuovo Lavoratore</a>
                                <a href="#archiviazione">📌 Archiviazione Lavoratore</a>
                                <a href="#iscrizioni">📌 Gestione delle Iscrizioni Attive</a>
                                <a href="#pdf">📌 Esportazioni in PDF</a>
                                <a href="#filtri">📌 Utilizzo dei Filtri</a>
                                <a href="#campi-condizionali">📌 Campi Condizionali nel Form</a>
                                <a href="#scheda">📌 Scheda Anagrafica del Lavoratore</a>
                                <a href="#stato-iscrizione">✅ Stato Iscrizione e Archiviazione</a>
                                <a href="#gestione-iscrizioni">✅ Gestione delle Iscrizioni</a>
                                <a href="#vertenza">✅ Pulsante Vertenza</a>
                                <a href="#calendario">✅ Gestione Eventi nel Calendario</a>
                                <a href="#documenti">✅ Documenti e Pacchetto ZIP</a>
                                <a href="#criteri-iscrizione">📌 Criteri Iscrizione</a>
                                <a href="#attualmente-iscritto">📌 Verifica Iscrizione Attuale</a>
                                <a href="#riattivazione">🗂 Come Riattivare un Lavoratore Archiviato</a>
                                <a href="#conclusione">📌 Conclusioni Stato Iscrizione</a>
                                <a href="#modifica">🔖 Modifica Lavoratore</a>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <?php include 'manuale_contenuto.php'; ?>
                        </div>
                    </div>

                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>
</body>
</html>
