<?php
require 'config.php';
checkLogin();

// Verifica se è una richiesta per esportare CSV
if (!isset($_GET['export_csv']) || $_GET['export_csv'] != 1) {
    header("Location: lavoratori.php");
    exit;
}

// Recupera i parametri di filtro dalla query string
// Uso "azienda_id" per coerenza, perché selezioni l'azienda tramite il suo id
$aziendaFilter     = isset($_GET['azienda_id']) ? intval($_GET['azienda_id']) : '';
$nazionalitaFilter = isset($_GET['nazionalita']) ? trim($_GET['nazionalita']) : '';
$iscrittoFilter    = isset($_GET['iscritto']) ? $_GET['iscritto'] : '';
$searchQuery       = isset($_GET['search']) ? trim($_GET['search']) : '';

// Costruzione della query SQL con join su aziende e sedi
$query = "
    SELECT 
        l.id,
        l.nome,
        l.cognome,
        l.nazionalita,
        l.email,
        l.telefono,
        l.iscritto,
        a.nome_azienda,
        s.nome AS nome_sede
    FROM lavoratori l
    LEFT JOIN aziende a ON l.azienda_id = a.id
    LEFT JOIN sedi s ON l.sede_id = s.id
    WHERE 1=1
";

$params = [];
$types  = '';

// Applica il filtro per azienda se presente
if ($aziendaFilter !== '') {
    $query   .= " AND a.id = ?";
    $params[] = $aziendaFilter;
    $types  .= 'i';
}

// Filtro per nazionalità (ricerca parziale)
if ($nazionalitaFilter !== '') {
    $query   .= " AND l.nazionalita LIKE CONCAT('%', ?, '%')";
    $params[] = $nazionalitaFilter;
    $types  .= 's';
}

// Filtro per iscritto (accetta '1' o '0')
if ($iscrittoFilter !== '') {
    if ($iscrittoFilter === '1' || $iscrittoFilter === '0') {
        $query   .= " AND l.iscritto = ?";
        $params[] = intval($iscrittoFilter);
        $types  .= 'i';
    }
}

// Filtro per la ricerca su nome o cognome
if ($searchQuery !== '') {
    $query   .= " AND (l.nome LIKE CONCAT('%', ?, '%') OR l.cognome LIKE CONCAT('%', ?, '%'))";
    $searchTerm = '%' . $searchQuery . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types  .= 'ss';
}

// Ordina i risultati per nome e cognome in ordine ascendente
$query .= " ORDER BY l.nome ASC, l.cognome ASC";

// Esecuzione della query
$stmt = executeQuery($query, $params, $types);
if ($stmt === false) {
    die("Errore nell'esecuzione della query per l'esportazione.");
}
$result = $stmt->get_result();

// Imposta gli header per il download del CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=lavoratori.csv');

// Crea un file "output" in memoria
$output = fopen('php://output', 'w');

// Intestazione delle colonne, ora include anche la colonna "Sede"
fputcsv($output, ['ID', 'Nome', 'Cognome', 'Nazionalità', 'Email', 'Telefono', 'Iscritto', 'Azienda', 'Sede']);

// Popolamento del CSV: per ogni lavoratore scrive una riga con i dati
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['nome'],
        $row['cognome'],
        $row['nazionalita'],
        $row['email'],
        $row['telefono'],
        $row['iscritto'] ? 'Sì' : 'No',
        $row['nome_azienda'],
        $row['nome_sede']
    ]);
}

fclose($output);
exit;
?>
