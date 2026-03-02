<?php
/**
 * import_lavoratori_ajax.php - Importazione lavoratori da CSV/XLS/XLSX via AJAX
 *
 * Accetta file CSV, XLS, XLSX. Mappa automaticamente le colonne dell'header
 * ai campi del database. Restituisce risultato JSON.
 */
require_once 'config.php';
checkLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito.']);
    exit;
}

// Verifica CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF non valido.']);
    exit;
}

if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite server).',
        UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form).',
        UPLOAD_ERR_PARTIAL => 'File caricato solo parzialmente.',
        UPLOAD_ERR_NO_FILE => 'Nessun file selezionato.',
    ];
    $errCode = $_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errMsg = $uploadErrors[$errCode] ?? 'Errore nel caricamento del file.';
    echo json_encode(['success' => false, 'error' => $errMsg]);
    exit;
}

$fileTmpPath = $_FILES['import_file']['tmp_name'];
$fileName = $_FILES['import_file']['name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

$allowedExtensions = ['csv', 'xls', 'xlsx'];
if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'error' => 'Formato non supportato. Usa CSV, XLS o XLSX.']);
    exit;
}

// MIME type validation
$allowedMimeTypes = [
    'text/csv', 'text/plain',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo === false) {
    echo json_encode(['success' => false, 'error' => 'Errore nella verifica del tipo file.']);
    exit;
}
$detectedMime = finfo_file($finfo, $fileTmpPath);
finfo_close($finfo);
if ($detectedMime === false || !in_array($detectedMime, $allowedMimeTypes)) {
    echo json_encode(['success' => false, 'error' => 'Tipo file non valido. Contenuto non corrisponde all\'estensione.']);
    exit;
}

// ── Leggi i dati dal file ──────────────────────────────────────────────
$rows = [];
$headerRow = [];

if ($fileExtension === 'csv') {
    $handle = fopen($fileTmpPath, 'r');
    if ($handle === false) {
        echo json_encode(['success' => false, 'error' => 'Impossibile aprire il file.']);
        exit;
    }
    // Detect delimiter (comma or semicolon)
    $firstLine = fgets($handle);
    rewind($handle);
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

    $headerRow = fgetcsv($handle, 0, $delimiter, '"', '\\');
    if ($headerRow === false) {
        echo json_encode(['success' => false, 'error' => 'File CSV vuoto o non valido.']);
        exit;
    }
    while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        if (count(array_filter($data, fn($v) => trim($v) !== '')) === 0) continue; // skip empty rows
        $rows[] = $data;
    }
    fclose($handle);
} else {
    // XLS/XLSX via PhpSpreadsheet
    require_once __DIR__ . '/vendor/autoload.php';
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $sheetData = $sheet->toArray(null, true, true, false);
        if (empty($sheetData)) {
            echo json_encode(['success' => false, 'error' => 'File vuoto.']);
            exit;
        }
        $headerRow = array_shift($sheetData);
        foreach ($sheetData as $row) {
            if (count(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== '')) === 0) continue;
            $rows[] = $row;
        }
    } catch (Exception $e) {
        error_log("Errore lettura file importazione: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Errore durante la lettura del file.']);
        exit;
    }
}

if (empty($rows)) {
    echo json_encode(['success' => false, 'error' => 'Il file non contiene dati (solo l\'header).']);
    exit;
}

// ── Mapping colonne ────────────────────────────────────────────────────
// Normalizza gli header per il matching
$normalizedHeaders = array_map(function($h) {
    return strtolower(trim(preg_replace('/[\s_]+/', ' ', (string)$h)));
}, $headerRow);

// Mappa flessibile: nome colonna nel file => campo database
$columnMap = [
    'cognome'               => ['cognome', 'surname', 'last name', 'lastname'],
    'nome'                  => ['nome', 'name', 'first name', 'firstname'],
    'cognome_e_nome'        => ['cognome e nome', 'cognome nome', 'nome e cognome', 'nominativo', 'lavoratore'],
    'codice_fiscale'        => ['codice fiscale', 'cf', 'fiscal code', 'cod fiscale', 'c.f.', 'codfiscale'],
    'telefono'              => ['telefono', 'tel', 'phone', 'cellulare', 'cell'],
    'email'                 => ['email', 'e-mail', 'mail', 'posta elettronica'],
    'sesso'                 => ['sesso', 'genere', 'gender', 'sex', 'm/f'],
    'data_nascita'          => ['data nascita', 'data di nascita', 'birth date', 'birthdate', 'nato il'],
    'indirizzo_citta'       => ['citta', 'città', 'comune', 'comune residenza', 'city', 'comune di residenza', 'residenza'],
    'indirizzo_provincia'   => ['provincia', 'prov', 'province'],
    'indirizzo_via'         => ['via', 'indirizzo', 'address', 'indirizzo via'],
    'indirizzo_cap'         => ['cap', 'zip', 'codice postale'],
    'paese_nascita'         => ['paese nascita', 'paese di nascita', 'nazionalita', 'nazionalità', 'nationality', 'paese origine'],
    'azienda_nome'          => ['azienda', 'azienda nome', 'nome azienda', 'company', 'ditta', 'ragione sociale'],
    'settore'               => ['settore', 'sector', 'tipo'],
    'data_iscrizione'       => ['data iscrizione', 'data di iscrizione', 'iscrizione', 'enrollment date'],
    'ccnl'                  => ['ccnl', 'contratto collettivo', 'contratto nazionale'],
    'contratto'             => ['contratto', 'tipo contratto', 'contract', 'contract type'],
    'orario_contratto'      => ['orario', 'orario contratto', 'tempo pieno', 'full time', 'part time', 'orario di lavoro'],
    'ruolo'                 => ['ruolo', 'role', 'mansione', 'qualifica'],
    'note'                  => ['note', 'notes', 'osservazioni', 'commenti'],
];

// Trova la posizione di ogni campo
$fieldPositions = [];
foreach ($columnMap as $field => $aliases) {
    foreach ($normalizedHeaders as $colIndex => $headerName) {
        if (in_array($headerName, $aliases)) {
            $fieldPositions[$field] = $colIndex;
            break;
        }
    }
}

// Verifica campi obbligatori: serve almeno cognome+nome OPPURE cognome_e_nome
$hasSeparateNames = isset($fieldPositions['cognome']) && isset($fieldPositions['nome']);
$hasCombinedName = isset($fieldPositions['cognome_e_nome']);
if (!$hasSeparateNames && !$hasCombinedName) {
    $detectedCols = implode(', ', array_map(fn($h) => '"' . $h . '"', $headerRow));
    echo json_encode([
        'success' => false,
        'error' => "Impossibile trovare le colonne Nome/Cognome. Colonne trovate: $detectedCols. Servono colonne come 'Nome', 'Cognome' oppure 'Cognome e Nome'."
    ]);
    exit;
}

// Helper per ottenere il valore di un campo da una riga
function getField($row, $field, &$fieldPositions) {
    if (!isset($fieldPositions[$field])) return '';
    $val = $row[$fieldPositions[$field]] ?? '';
    return trim((string)$val);
}

// ── Importazione ───────────────────────────────────────────────────────
$insertedCount = 0;
$skippedCount = 0;
$errorsList = [];
$rowCount = 0;

// Genere mapping
$genereMap = [
    'M' => 'Uomo', 'MASCHIO' => 'Uomo', 'UOMO' => 'Uomo', 'MALE' => 'Uomo',
    'F' => 'Donna', 'FEMMINA' => 'Donna', 'DONNA' => 'Donna', 'FEMALE' => 'Donna',
];

// ── Pre-load aziende lookup map (eliminates per-row SELECT on aziende) ──
$aziende_map = [];
$res_aziende = $mysqli->query("SELECT id, nome_azienda FROM aziende");
if ($res_aziende) {
    while ($az_row = $res_aziende->fetch_assoc()) {
        $aziende_map[strtolower(trim($az_row['nome_azienda']))] = (int)$az_row['id'];
    }
    $res_aziende->free();
} else {
    error_log("Errore pre-caricamento aziende: " . $mysqli->error);
    echo json_encode(['success' => false, 'error' => 'Errore nel caricamento dei dati aziende.']);
    exit;
}

// ── Pre-load existing lavoratori for duplicate detection (eliminates per-row SELECT on lavoratori) ──
$existing_lavoratori = [];
$existing_cf = [];
$res_lav = $mysqli->query("SELECT nome, cognome, azienda_id, codice_fiscale FROM lavoratori");
if ($res_lav) {
    while ($lav_row = $res_lav->fetch_assoc()) {
        $dup_key = strtolower(trim($lav_row['nome'])) . '|' . strtolower(trim($lav_row['cognome'])) . '|' . ($lav_row['azienda_id'] ?? 'NULL');
        $existing_lavoratori[$dup_key] = true;
        if (!empty($lav_row['codice_fiscale'])) {
            $existing_cf[strtoupper(trim($lav_row['codice_fiscale']))] = true;
        }
    }
    $res_lav->free();
} else {
    error_log("Errore pre-caricamento lavoratori: " . $mysqli->error);
    echo json_encode(['success' => false, 'error' => 'Errore nel caricamento dei dati lavoratori.']);
    exit;
}

// ── Prepare INSERT statement once outside the loop ──
$stmt_insert = $mysqli->prepare("INSERT INTO lavoratori (
    nome, cognome, codice_fiscale, telefono, email,
    indirizzo_via, indirizzo_cap, indirizzo_citta, indirizzo_provincia,
    paese_nascita, data_nascita, data_iscrizione, settore, genere,
    azienda_id, ccnl, contratto, orario_contratto, ruolo, note
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt_insert) {
    error_log("Errore preparazione query di inserimento lavoratori: " . $mysqli->error);
    echo json_encode(['success' => false, 'error' => 'Errore preparazione query di inserimento.']);
    exit;
}

// ── Prepare INSERT statement for new aziende ──
$stmt_az_insert = $mysqli->prepare("INSERT INTO aziende (nome_azienda) VALUES (?)");
if (!$stmt_az_insert) {
    error_log("Errore preparazione query aziende: " . $mysqli->error);
    echo json_encode(['success' => false, 'error' => 'Errore preparazione query aziende.']);
    exit;
}

// ── Wrap entire import in a transaction ──
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
$mysqli->begin_transaction();

foreach ($rows as $row) {
    $rowCount++;

    // Estrai nome e cognome
    if ($hasSeparateNames) {
        $nome = getField($row, 'nome', $fieldPositions);
        $cognome = getField($row, 'cognome', $fieldPositions);
    } else {
        $combinato = getField($row, 'cognome_e_nome', $fieldPositions);
        if (empty($combinato)) {
            $errorsList[] = "Riga $rowCount: nome vuoto, saltata.";
            $skippedCount++;
            continue;
        }
        $parts = explode(' ', $combinato);
        if (count($parts) > 1) {
            $cognome = ucwords(strtolower(array_shift($parts)));
            $nome = ucwords(strtolower(implode(' ', $parts)));
        } else {
            $cognome = ucwords(strtolower($combinato));
            $nome = '';
        }
    }

    // Normalizza
    $nome = ucwords(strtolower($nome));
    $cognome = ucwords(strtolower($cognome));

    if (empty($cognome) && empty($nome)) {
        $errorsList[] = "Riga $rowCount: nome e cognome vuoti, saltata.";
        $skippedCount++;
        continue;
    }

    $codice_fiscale = strtoupper(getField($row, 'codice_fiscale', $fieldPositions));
    $telefono = getField($row, 'telefono', $fieldPositions);
    $email_lav = getField($row, 'email', $fieldPositions);
    $indirizzo_citta = ucwords(strtolower(getField($row, 'indirizzo_citta', $fieldPositions)));
    $indirizzo_provincia = strtoupper(getField($row, 'indirizzo_provincia', $fieldPositions));
    $indirizzo_via = getField($row, 'indirizzo_via', $fieldPositions);
    $indirizzo_cap = getField($row, 'indirizzo_cap', $fieldPositions);
    $paese_nascita = ucwords(strtolower(getField($row, 'paese_nascita', $fieldPositions)));
    $azienda_nome = ucwords(strtolower(getField($row, 'azienda_nome', $fieldPositions)));
    $settore_raw = getField($row, 'settore', $fieldPositions);
    $ccnl = getField($row, 'ccnl', $fieldPositions);
    $contratto_raw = strtolower(getField($row, 'contratto', $fieldPositions));
    $orario_raw = strtolower(getField($row, 'orario_contratto', $fieldPositions));
    $ruolo_raw = strtoupper(getField($row, 'ruolo', $fieldPositions));
    $note = getField($row, 'note', $fieldPositions);

    // Genere
    $sesso_raw = strtoupper(getField($row, 'sesso', $fieldPositions));
    $genere = $genereMap[$sesso_raw] ?? 'Altro';

    // Settore: normalizza a 'privato' o 'pubblico'
    $settore = 'privato';
    if (preg_match('/pubblic/i', $settore_raw)) $settore = 'pubblico';

    // Contratto: mappa ai valori enum
    $contrattoMap = [
        'indeterminato' => 'indeterminato', 'tempo indeterminato' => 'indeterminato',
        'determinato' => 'determinato', 'tempo determinato' => 'determinato',
        'apprendistato' => 'apprendistato',
        'part-time' => 'part-time', 'part time' => 'part-time',
        'full-time' => 'full-time', 'full time' => 'full-time',
        'progetto' => 'progetto',
    ];
    $contratto = $contrattoMap[$contratto_raw] ?? null;

    // Orario contratto
    $orario_contratto = '';
    if (preg_match('/full|pieno/i', $orario_raw)) $orario_contratto = 'tempo pieno';
    elseif (preg_match('/part/i', $orario_raw)) $orario_contratto = 'part-time';

    // Ruolo: mappa ai valori enum
    $ruoloMap = ['RSU' => 'RSU', 'RSA' => 'RSA', 'RLS' => 'RLS'];
    $ruolo = $ruoloMap[$ruolo_raw] ?? 'NESSUNO';

    // Data nascita
    $data_nascita_raw = getField($row, 'data_nascita', $fieldPositions);
    $data_nascita = parseDate($data_nascita_raw);

    // Data iscrizione
    $data_iscrizione_raw = getField($row, 'data_iscrizione', $fieldPositions);
    $data_iscrizione = parseDate($data_iscrizione_raw);

    // Azienda: trova nella mappa pre-caricata, o crea e aggiorna la mappa
    $azienda_id = null;
    if (!empty($azienda_nome)) {
        $azienda_key = strtolower(trim($azienda_nome));
        if (isset($aziende_map[$azienda_key])) {
            $azienda_id = $aziende_map[$azienda_key];
        } else {
            // Nuova azienda: inserisci e aggiorna la mappa in-memory
            $stmt_az_insert->bind_param('s', $azienda_nome);
            try {
                $stmt_az_insert->execute();
                $azienda_id = (int)$stmt_az_insert->insert_id;
                $aziende_map[$azienda_key] = $azienda_id;
            } catch (mysqli_sql_exception $e) {
                error_log("Errore inserimento azienda '$azienda_nome': " . $e->getMessage());
                $errorsList[] = "Riga $rowCount: errore inserimento azienda '$azienda_nome'. Lavoratore saltato.";
                $skippedCount++;
                continue;
            }
        }
    }

    // Controllo duplicati: codice_fiscale (se presente) o nome + cognome + azienda
    if (!empty($codice_fiscale) && isset($existing_cf[strtoupper(trim($codice_fiscale))])) {
        $errorsList[] = "Riga $rowCount: codice fiscale '$codice_fiscale' gia' presente. Saltata.";
        $skippedCount++;
        continue;
    }
    $dup_key = strtolower(trim($nome)) . '|' . strtolower(trim($cognome)) . '|' . ($azienda_id ?? 'NULL');
    if (isset($existing_lavoratori[$dup_key])) {
        $errorsList[] = "Riga $rowCount: '$cognome $nome' gia' presente. Saltata.";
        $skippedCount++;
        continue;
    }

    // Insert (prepared statement riutilizzato)
    // azienda_id può essere NULL: usa tipo 's' per NULL, 'i' per intero
    $bind_types = 'ssssssssssssss' . ($azienda_id === null ? 's' : 'i') . 'sssss';
    $stmt_insert->bind_param(
        $bind_types,
        $nome, $cognome, $codice_fiscale, $telefono, $email_lav,
        $indirizzo_via, $indirizzo_cap, $indirizzo_citta, $indirizzo_provincia,
        $paese_nascita, $data_nascita, $data_iscrizione, $settore, $genere,
        $azienda_id, $ccnl, $contratto, $orario_contratto, $ruolo, $note
    );

    try {
        $stmt_insert->execute();
        $insertedCount++;
        // Aggiorna la mappa duplicati in-memory per righe successive nello stesso file
        $existing_lavoratori[$dup_key] = true;
        if (!empty($codice_fiscale)) {
            $existing_cf[strtoupper(trim($codice_fiscale))] = true;
        }
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) {
            $errorsList[] = "Riga $rowCount: duplicato rilevato. Lavoratore saltato.";
            $skippedCount++;
            $existing_lavoratori[$dup_key] = true;
            if (!empty($codice_fiscale)) {
                $existing_cf[strtoupper(trim($codice_fiscale))] = true;
            }
            continue;
        }
        error_log("Errore inserimento lavoratore riga $rowCount: " . $e->getMessage());
        $errorsList[] = "Riga $rowCount: errore durante l'inserimento. Lavoratore saltato.";
        $skippedCount++;
    }
}

    $mysqli->commit();
} catch (Exception $e) {
    $mysqli->rollback();
    $stmt_insert->close();
    $stmt_az_insert->close();
    error_log("Errore importazione lavoratori: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Errore durante l\'importazione. Controlla i log per dettagli.']);
    exit;
}

$stmt_insert->close();
$stmt_az_insert->close();

// Risposta
$mappedFields = [];
foreach ($fieldPositions as $field => $colIdx) {
    $mappedFields[] = $headerRow[$colIdx] . ' → ' . $field;
}

echo json_encode([
    'success' => true,
    'inserted' => $insertedCount,
    'skipped' => $skippedCount,
    'total_rows' => $rowCount,
    'errors' => $errorsList,
    'mapped_fields' => $mappedFields,
]);

// ── Helper: parse date ─────────────────────────────────────────────────
function parseDate($raw) {
    $raw = trim((string)$raw);
    if (empty($raw)) return null;

    // Already YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;

    // DD/MM/YYYY or DD-MM-YYYY
    if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{2,4})$#', $raw, $m)) {
        $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $year = $m[3];
        if (strlen($year) === 2) $year = ($year > 50 ? '19' : '20') . $year;
        return "$year-$month-$day";
    }

    // MM/DD/YYYY (US format) - try PHP parser
    $ts = strtotime($raw);
    if ($ts !== false) return date('Y-m-d', $ts);

    return null;
}
