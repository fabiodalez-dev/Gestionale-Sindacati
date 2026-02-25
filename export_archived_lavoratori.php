<?php
// export_archived_lavoratori.php

require_once 'config.php';
checkLogin();

// Recupera i parametri di filtro e ricerca se presenti
$aziendaFilter = isset($_GET['azienda_id']) && intval($_GET['azienda_id']) > 0 ? intval($_GET['azienda_id']) : null;
$unitaOperativaFilter = isset($_GET['unita_operativa_id']) && intval($_GET['unita_operativa_id']) > 0 ? intval($_GET['unita_operativa_id']) : null;
$paeseNascitaFilter = isset($_GET['paese_nascita']) ? trim($_GET['paese_nascita']) : '';
$iscrittoFilter = isset($_GET['iscritto']) ? $_GET['iscritto'] : ''; // Aggiornato per includere 'sì', 'in_scadenza', 'no'
$vertenzaFilter = isset($_GET['vertenza']) && $_GET['vertenza'] !== '' ? $_GET['vertenza'] : '';
$settoreFilter = isset($_GET['settore']) && $_GET['settore'] !== '' ? $_GET['settore'] : '';
$tipoTesseraFilter = isset($_GET['tipo_tessera']) && $_GET['tipo_tessera'] !== '' ? $_GET['tipo_tessera'] : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Costruzione della query SQL con filtri e ricerca
$query = "
    SELECT 
        l.id, 
        l.nome, 
        l.cognome, 
        l.indirizzo_citta, 
        l.paese_nascita, 
        l.email, 
        l.telefono, 
        l.vertenze, 
        l.settore, 
        a.nome_azienda, 
        u.nome_unita_operativa, 
        l.tipo_tessera, 
        i.data_inizio, 
        i.data_fine
    FROM lavoratori l
    LEFT JOIN aziende a ON l.azienda_id = a.id
    LEFT JOIN unita_operativa u ON l.unita_operativa_id = u.id
    LEFT JOIN iscrizioni i ON l.id = i.lavoratore_id AND i.id = (
        SELECT MAX(id) FROM iscrizioni WHERE lavoratore_id = l.id
    )
    WHERE l.archiviato = 1
";

$params = [];
$types = '';

// Applica i filtri se presenti (come in lavoratori.php)
if ($aziendaFilter !== null) {
    $query .= " AND a.id = ?";
    $params[] = $aziendaFilter;
    $types .= 'i';
}

if ($unitaOperativaFilter !== null) {
    $query .= " AND u.id = ?";
    $params[] = $unitaOperativaFilter;
    $types .= 'i';
}

if ($paeseNascitaFilter !== '') {
    $query .= " AND l.paese_nascita LIKE ?";
    $params[] = '%' . $paeseNascitaFilter . '%';
    $types .= 's';
}

if ($iscrittoFilter !== '') {
    if ($iscrittoFilter === 'sì') {
        $query .= " AND (l.tipo_tessera IN ('trattenuta in busta paga', 'sepa') OR (l.tipo_tessera = 'rinnovo annuale' AND (i.data_fine >= ? OR i.data_fine IS NULL)))";
        $params[] = date('Y-m-d', strtotime('+31 days'));
        $types .= 's';
    } elseif ($iscrittoFilter === 'in_scadenza') {
        $query .= " AND (l.tipo_tessera = 'rinnovo annuale' AND i.data_fine BETWEEN ? AND ?)";
        $params[] = date('Y-m-d');
        $params[] = date('Y-m-d', strtotime('+30 days'));
        $types .= 'ss';
    } elseif ($iscrittoFilter === 'no') {
        $query .= " AND (l.tipo_tessera = 'rinnovo annuale' AND i.data_fine < ?)";
        $params[] = date('Y-m-d');
        $types .= 's';
    }
}

if ($vertenzaFilter !== '') {
    if ($vertenzaFilter === '1' || $vertenzaFilter === '0') {
        $query .= " AND l.vertenze = ?";
        $params[] = intval($vertenzaFilter);
        $types .= 'i';
    }
}

if ($settoreFilter !== '') {
    $query .= " AND l.settore = ?";
    $params[] = $settoreFilter;
    $types .= 's';
}

if ($tipoTesseraFilter !== '') {
    $query .= " AND l.tipo_tessera = ?";
    $params[] = $tipoTesseraFilter;
    $types .= 's';
}

if ($searchQuery !== '') {
    $query .= " AND (l.nome LIKE ? OR l.cognome LIKE ? OR l.note LIKE ?)";
    $params[] = '%' . $searchQuery . '%';
    $params[] = '%' . $searchQuery . '%';
    $params[] = '%' . $searchQuery . '%';
    $types .= 'sss';
}

// Esegui la query
$stmt = executeQuery($query, $params, $types);
if ($stmt === false) {
    die("Errore nell'esecuzione della query per l'esportazione.");
}
$result = $stmt->get_result();

// Imposta le intestazioni per l'esportazione CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=archived_lavoratori_' . date('Y-m-d') . '.csv');

// Apri l'output come file
$output = fopen('php://output', 'w');

// Scrivi le intestazioni delle colonne (includendo "Indirizzo Città")
fputcsv($output, [
    'ID', 
    'Nome', 
    'Cognome', 
    'Indirizzo Città', 
    'Paese di Nascita', 
    'Email', 
    'Telefono', 
    'Vertenza', 
    'Settore', 
    'Azienda', 
    'Unità Operativa', 
    'Tipo Tessera', 
    'Data Inizio Iscrizione', 
    'Data Fine Iscrizione'
]);

// Scrivi i dati dei lavoratori
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['nome'],
        $row['cognome'],
        $row['indirizzo_citta'],
        $row['paese_nascita'],
        $row['email'],
        $row['telefono'],
        $row['vertenze'] ? 'Sì' : 'No',
        ucfirst($row['settore']),
        $row['nome_azienda'] ?? 'N/A',
        $row['nome_unita_operativa'] ?? 'N/A',
        $row['tipo_tessera'],
        $row['data_inizio'] ? date('d/m/Y', strtotime($row['data_inizio'])) : 'N/A',
        $row['data_fine'] ? date('d/m/Y', strtotime($row['data_fine'])) : 'N/A'
    ]);
}

// Chiudi l'output e termina lo script
fclose($output);
exit;
?>
