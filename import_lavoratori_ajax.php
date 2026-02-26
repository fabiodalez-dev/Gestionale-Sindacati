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
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
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
        echo json_encode(['success' => false, 'error' => 'Errore lettura file: ' . $e->getMessage()]);
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

    // Azienda: trova o crea
    $azienda_id = null;
    if (!empty($azienda_nome)) {
        $stmt_az = $mysqli->prepare("SELECT id FROM aziende WHERE nome_azienda = ?");
        $stmt_az->bind_param('s', $azienda_nome);
        $stmt_az->execute();
        $res_az = $stmt_az->get_result();
        if ($res_az->num_rows > 0) {
            $azienda_id = $res_az->fetch_assoc()['id'];
        } else {
            $stmt_ins = $mysqli->prepare("INSERT INTO aziende (nome_azienda) VALUES (?)");
            $stmt_ins->bind_param('s', $azienda_nome);
            if ($stmt_ins->execute()) {
                $azienda_id = $stmt_ins->insert_id;
            }
            $stmt_ins->close();
        }
        $stmt_az->close();
    }

    // Controllo duplicati: nome + cognome + azienda
    $stmt_dup = $mysqli->prepare("SELECT id FROM lavoratori WHERE nome = ? AND cognome = ? AND (azienda_id = ? OR (azienda_id IS NULL AND ? IS NULL))");
    $stmt_dup->bind_param('ssii', $nome, $cognome, $azienda_id, $azienda_id);
    $stmt_dup->execute();
    if ($stmt_dup->get_result()->num_rows > 0) {
        $errorsList[] = "Riga $rowCount: '$cognome $nome' gia' presente. Saltata.";
        $skippedCount++;
        $stmt_dup->close();
        continue;
    }
    $stmt_dup->close();

    // Insert
    $stmt = $mysqli->prepare("INSERT INTO lavoratori (
        nome, cognome, codice_fiscale, telefono, email,
        indirizzo_via, indirizzo_cap, indirizzo_citta, indirizzo_provincia,
        paese_nascita, data_nascita, data_iscrizione, settore, genere,
        azienda_id, ccnl, contratto, orario_contratto, ruolo, note
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        $errorsList[] = "Riga $rowCount: errore preparazione query.";
        continue;
    }

    $stmt->bind_param(
        'ssssssssssssssssssss',
        $nome, $cognome, $codice_fiscale, $telefono, $email_lav,
        $indirizzo_via, $indirizzo_cap, $indirizzo_citta, $indirizzo_provincia,
        $paese_nascita, $data_nascita, $data_iscrizione, $settore, $genere,
        $azienda_id, $ccnl, $contratto, $orario_contratto, $ruolo, $note
    );

    if ($stmt->execute()) {
        $insertedCount++;
    } else {
        $errorsList[] = "Riga $rowCount: " . $stmt->error;
    }
    $stmt->close();
}

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
