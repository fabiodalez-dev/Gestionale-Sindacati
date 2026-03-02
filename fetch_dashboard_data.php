<?php
/**
 * fetch_dashboard_data.php - AJAX endpoint for dashboard chart data
 * Supports filtering by sede_id
 */
require_once 'config.php';
checkLogin();

header('Content-Type: application/json; charset=utf-8');

$sede_id = isset($_GET['sede_id']) ? intval($_GET['sede_id']) : 0;
$iscritto_filter = isset($_GET['iscritto']) ? $_GET['iscritto'] : '';

// Build WHERE clause based on filters
$where = "WHERE 1=1";
$params = [];
$types = '';

if ($sede_id > 0) {
    $where .= " AND l.sede_id = ?";
    $params[] = $sede_id;
    $types .= 'i';
}

if ($iscritto_filter === '1') {
    $where .= " AND l.iscritto = 1";
} elseif ($iscritto_filter === '0') {
    $where .= " AND l.iscritto = 0";
}

// Exclude archived
$where .= " AND l.archiviato <> 1";

// Helper function for filtered queries
function dashQuery($sql, $params, $types) {
    global $mysqli;
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return false;
    if (!empty($params) && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// ── Summary counts (single query with conditional aggregation) ──
$sql_counts = "SELECT
    COUNT(*) AS totale,
    SUM(CASE WHEN l.iscritto = 1 THEN 1 ELSE 0 END) AS iscritti,
    SUM(CASE WHEN l.iscritto = 0 OR l.iscritto IS NULL THEN 1 ELSE 0 END) AS non_iscritti
FROM lavoratori l $where";
$r = dashQuery($sql_counts, $params, $types);
if ($r && ($row = $r->fetch_assoc())) {
    $totale_lavoratori = (int)$row['totale'];
    $totale_iscritti = (int)$row['iscritti'];
    $totale_non_iscritti = (int)$row['non_iscritti'];
} else {
    $totale_lavoratori = 0;
    $totale_iscritti = 0;
    $totale_non_iscritti = 0;
}

// ── Settore ──
$sql = "SELECT l.settore AS label, COUNT(*) AS value FROM lavoratori l $where AND l.settore IS NOT NULL AND l.settore != '' GROUP BY l.settore ORDER BY value DESC";
$r = dashQuery($sql, $params, $types);
$settore = [];
if ($r) while ($row = $r->fetch_assoc()) $settore[] = $row;

// ── Genere ──
$sql = "SELECT l.genere AS label, COUNT(*) AS value FROM lavoratori l $where AND l.genere IS NOT NULL AND l.genere != '' GROUP BY l.genere ORDER BY value DESC";
$r = dashQuery($sql, $params, $types);
$genere = [];
if ($r) while ($row = $r->fetch_assoc()) $genere[] = $row;

// ── Contratto ──
$sql = "SELECT l.contratto AS label, COUNT(*) AS value FROM lavoratori l $where AND l.contratto IS NOT NULL AND l.contratto != '' GROUP BY l.contratto ORDER BY value DESC";
$r = dashQuery($sql, $params, $types);
$contratto = [];
if ($r) while ($row = $r->fetch_assoc()) $contratto[] = $row;

// ── Orario Contratto ──
$sql = "SELECT l.orario_contratto AS label, COUNT(*) AS value FROM lavoratori l $where AND l.orario_contratto IS NOT NULL AND l.orario_contratto != '' GROUP BY l.orario_contratto ORDER BY value DESC";
$r = dashQuery($sql, $params, $types);
$orario = [];
if ($r) while ($row = $r->fetch_assoc()) $orario[] = $row;

// ── CCNL ──
$sql = "SELECT l.ccnl AS label, COUNT(*) AS value FROM lavoratori l $where AND l.ccnl IS NOT NULL AND l.ccnl != '' GROUP BY l.ccnl ORDER BY value DESC";
$r = dashQuery($sql, $params, $types);
$ccnl = [];
if ($r) while ($row = $r->fetch_assoc()) $ccnl[] = $row;

// ── Città (top 20 + others) ──
$sql = "SELECT l.indirizzo_citta AS label, COUNT(*) AS value FROM lavoratori l $where AND l.indirizzo_citta IS NOT NULL AND l.indirizzo_citta != '' GROUP BY l.indirizzo_citta ORDER BY value DESC";
$r = dashQuery($sql, $params, $types);
$citta_raw = [];
if ($r) while ($row = $r->fetch_assoc()) $citta_raw[] = $row;
$citta = array_slice($citta_raw, 0, 20);
$citta_rest = array_slice($citta_raw, 20);
if (!empty($citta_rest)) {
    $citta[] = ['label' => 'Altri', 'value' => array_sum(array_column($citta_rest, 'value'))];
}

// ── Nazionalità (top 15 + others) ──
$sql = "SELECT l.nazionalita AS label, COUNT(*) AS value FROM lavoratori l $where AND l.nazionalita IS NOT NULL AND l.nazionalita != '' GROUP BY l.nazionalita ORDER BY value DESC";
$r = dashQuery($sql, $params, $types);
$nazionalita_raw = [];
if ($r) while ($row = $r->fetch_assoc()) $nazionalita_raw[] = $row;
$nazionalita = array_slice($nazionalita_raw, 0, 15);
$nazionalita_rest = array_slice($nazionalita_raw, 15);
if (!empty($nazionalita_rest)) {
    $nazionalita[] = ['label' => 'Altri', 'value' => array_sum(array_column($nazionalita_rest, 'value'))];
}

// ── Aziende (top 20) ──
$sql = "SELECT a.nome_azienda AS label, COUNT(l.id) AS value FROM aziende a JOIN lavoratori l ON a.id = l.azienda_id $where GROUP BY a.id, a.nome_azienda ORDER BY value DESC LIMIT 20";
$r = dashQuery($sql, $params, $types);
$aziende = [];
if ($r) while ($row = $r->fetch_assoc()) $aziende[] = $row;

// ── Iscrizioni nel tempo (ultimi 12 mesi) ──
$sql = "SELECT DATE_FORMAT(l.data_iscrizione, '%Y-%m') AS label, COUNT(*) AS value FROM lavoratori l $where AND l.data_iscrizione IS NOT NULL AND l.data_iscrizione >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY label ORDER BY label ASC";
$r = dashQuery($sql, $params, $types);
$iscrizioni_trend = [];
if ($r) while ($row = $r->fetch_assoc()) $iscrizioni_trend[] = $row;

// ── Sedi (for filter dropdown) ──
$sql_sedi = "SELECT id, nome FROM sedi ORDER BY nome ASC";
$r_sedi = $mysqli->query($sql_sedi);
$sedi = [];
if ($r_sedi) while ($row = $r_sedi->fetch_assoc()) $sedi[] = $row;

echo json_encode([
    'totale_lavoratori' => $totale_lavoratori,
    'totale_iscritti' => $totale_iscritti,
    'totale_non_iscritti' => $totale_non_iscritti,
    'settore' => $settore,
    'genere' => $genere,
    'contratto' => $contratto,
    'orario' => $orario,
    'ccnl' => $ccnl,
    'citta' => $citta,
    'nazionalita' => $nazionalita,
    'aziende' => $aziende,
    'iscrizioni_trend' => $iscrizioni_trend,
    'sedi' => $sedi
], JSON_UNESCAPED_UNICODE);
