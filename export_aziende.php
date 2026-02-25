<?php
// export_aziende.php

// Includi il file di configurazione e funzioni comuni
require_once 'config.php';

// Verifica se l'utente è loggato
checkLogin();

// Recupera i parametri di filtro e ricerca se presenti
$search_nome = isset($_GET['search_nome']) ? trim($_GET['search_nome']) : '';
$search_indirizzo = isset($_GET['search_indirizzo']) ? trim($_GET['search_indirizzo']) : '';
$search_citta = isset($_GET['search_citta']) ? trim($_GET['search_citta']) : '';

// Costruzione della query SQL con filtri e ricerca
$query = "
    SELECT a.id, a.nome_azienda, a.indirizzo_via, a.indirizzo_numero_civico, a.indirizzo_cap, 
           a.indirizzo_citta, a.indirizzo_provincia, a.telefono, a.email,
           COUNT(l.id) AS numero_lavoratori
    FROM aziende a
    LEFT JOIN lavoratori l ON a.id = l.azienda_id
    WHERE 1=1
";

$params = [];
$types = '';

// Aggiunge filtri se presenti
if ($search_nome !== '') {
    $query .= " AND a.nome_azienda LIKE CONCAT('%', ?, '%')";
    $params[] = $search_nome;
    $types .= 's';
}
if ($search_indirizzo !== '') {
    $query .= " AND a.indirizzo_via LIKE CONCAT('%', ?, '%')";
    $params[] = $search_indirizzo;
    $types .= 's';
}
if ($search_citta !== '') {
    $query .= " AND a.indirizzo_citta LIKE CONCAT('%', ?, '%')";
    $params[] = $search_citta;
    $types .= 's';
}

$query .= " GROUP BY a.id ORDER BY a.nome_azienda ASC";

// Esegui la query
$stmt = executeQuery($query, $params, $types);
if ($stmt === false) {
    die("Errore nell'esecuzione della query per l'esportazione.");
}
$aziende = $stmt->get_result();

// Imposta le intestazioni per l'esportazione CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=aziende_' . date('Y-m-d') . '.csv');

// Apri l'output come file
$output = fopen('php://output', 'w');

// Scrivi l'intestazione del CSV
fputcsv($output, ['Nome Azienda', 'Indirizzo', 'Telefono', 'Email', 'Numero di Lavoratori']);

// Scrivi i dati delle aziende
if ($aziende->num_rows > 0) {
    while ($azienda = $aziende->fetch_assoc()) {
        // Combina indirizzo_via, indirizzo_numero_civico, indirizzo_cap, indirizzo_citta, indirizzo_provincia
        $indirizzo = [
            $azienda['indirizzo_via'] ?? '',
            $azienda['indirizzo_numero_civico'] ?? '',
            $azienda['indirizzo_cap'] ?? '',
            $azienda['indirizzo_citta'] ?? '',
            $azienda['indirizzo_provincia'] ?? ''
        ];
        $indirizzo_completo = implode(', ', array_filter($indirizzo, function($value) {
            return !empty($value);
        }));

        fputcsv($output, [
            $azienda['nome_azienda'],
            $indirizzo_completo,
            $azienda['telefono'],
            $azienda['email'],
            intval($azienda['numero_lavoratori'])
        ]);
    }
}

// Chiudi il file
fclose($output);
exit;
?>
