<?php
//generate_pdf_rinnovo.php
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use Mpdf\Mpdf;

checkLogin();

if (!isset($_GET['id'])) {
    die("ID lavoratore non specificato.");
}
$lavoratore_id = intval($_GET['id']);

// Query modificata per includere il settore dell'azienda
$query = "SELECT l.*, 
                 a.nome_azienda,
                 a.settore,
                 a.indirizzo_via AS azienda_via, 
                 a.indirizzo_numero_civico AS azienda_numero, 
                 a.indirizzo_cap AS azienda_cap, 
                 a.indirizzo_citta AS azienda_citta, 
                 a.indirizzo_provincia AS azienda_provincia
          FROM lavoratori l 
          LEFT JOIN aziende a ON l.azienda_id = a.id 
          WHERE l.id = ?";
$stmt = executeQuery($query, [$lavoratore_id], 'i');
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Lavoratore non trovato.");
}
$lavoratore = $result->fetch_assoc();

// Gestione delle date con formattazione corretta
foreach ($lavoratore as $key => $value) {
    if ($value === null || $value === '') {
        $lavoratore[$key] = '';
    } elseif (strpos($key, 'data') !== false && $value !== '0000-00-00') {
        // Verifica che sia una data valida e formatta in dd-mm-YYYY
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            $lavoratore[$key] = date('d-m-Y', $timestamp);
        } else {
            $lavoratore[$key] = '';
        }
    }
}

// Funzione per gestire i campi vuoti (lascia vuoto per compilazione manuale)
function formatField($value) {
    return !empty($value) ? $value : '';
}

// Funzione per generare i checkbox dinamici del contratto
function generaCheckboxContratti($contratto_attuale) {
    $tipi_contratto = [
        'dipendente' => 'Dipendente',
        'socio' => 'Socio',
        'indeterminato' => 'Tempo Indeterminato',
        'determinato' => 'Tempo Determinato',
        'part-time' => 'Part Time',
        'lavoro-domestico' => 'Lavoro Domestico',
        'disoccupato' => 'Disoccupato'
    ];
    
    $html_checkboxes = [];
    $counter = 0;
    
    foreach ($tipi_contratto as $valore => $etichetta) {
        if ($contratto_attuale === $valore) {
            $html_checkboxes[] = '☑ ' . $etichetta;
        } else {
            $html_checkboxes[] = '☐ ' . $etichetta;
        }
        $counter++;
        
        // Aggiungi un break dopo "Part Time" per mantenere il layout
        if ($counter == 5) {
            $html_checkboxes[] = '<br>';
        }
    }
    
    return implode(' ', $html_checkboxes);
}

$settings = getSettings(['theme_color', 'logo', 'nome_completo', 'denominazione_e_iban', 'territoriale']);
$data_attuale = date('d-m-Y');
$anno_corrente = date('Y');  // Anno corrente per la quota di adesione

// Costruzione indirizzo azienda
$azienda_indirizzo = '';
$via_numero = trim(($lavoratore['azienda_via'] ?? '') . ' ' . ($lavoratore['azienda_numero'] ?? ''));
$citta_info = trim(($lavoratore['azienda_cap'] ?? '') . ' ' . ($lavoratore['azienda_citta'] ?? '') . ' ' . ($lavoratore['azienda_provincia'] ?? ''));

if (!empty($via_numero) || !empty($citta_info)) {
    $azienda_indirizzo = trim($via_numero . '<br>' . $citta_info);
}

$logo_path = $settings['logo'] ?? 'uploads/default_logo.png';
// Usa path assoluto del filesystem per evitare che mPDF faccia richieste HTTP
$logo_path = __DIR__ . '/' . ltrim($logo_path, '/');

// Genera i checkbox dinamici per il contratto
$contratto_corrente = $lavoratore['contratto'] ?? '';
$checkbox_contratti = generaCheckboxContratti($contratto_corrente);

$mpdf = new Mpdf([
    'format' => 'A4',
    'default_font_size' => 10,
    'default_font' => 'Roboto',
    'margin_bottom' => 30,
]);

$html = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Roboto, sans-serif; font-size: 10pt; margin:0; padding:0; }
    .container { width: 100%; padding: 10px; }
    .header { width: 100%; padding-bottom: 5px; margin-bottom: 0; }
    .header-left { float: left; width: 40%; }
    .header-right { float: right; width: 55%; text-align: right; font-weight: bold; font-size: 12pt; }
    .clear { clear: both; }
    .zebra-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .zebra-table td { border: 1px solid #000; padding: 8px; height: 40px; vertical-align: middle; }
    .zebra-table tr:nth-child(even) { background-color: #f0f0f0; }
    .bold { font-weight: bold; }
    .checkboxes { font-family: monospace; font-size: 10pt; text-align:center; margin: 20px 0 10px 0; font-weight: bold; }
    .comunicazione { margin-top: 10px; text-align: justify; }
    .center { text-align: center; }
  </style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="header-left">
      <img src="' . htmlspecialchars($logo_path) . '" style="width:100px;">
    </div>
    <div class="header-right">
      ' . htmlspecialchars($settings['nome_completo'] ?? '') . '
    </div>
    <div class="clear"></div>
  </div>

  <div class="checkboxes">
    ' . $checkbox_contratti . '
  </div>

  <table class="zebra-table">
    <tr>
      <td class="bold" style="width:35%;">Nome azienda:</td>
      <td>' . htmlspecialchars(formatField($lavoratore['nome_azienda'])) . '</td>
    </tr>
    <tr>
      <td class="bold">Indirizzo:</td>
      <td>' . ($azienda_indirizzo ?: '') . '</td>
    </tr>
    <tr>
      <td class="bold">Settore azienda:</td>
      <td>' . htmlspecialchars(!empty($lavoratore['settore']) ? ucfirst($lavoratore['settore']) : '') . '</td>
    </tr>
    <tr>
      <td class="bold">Data assunzione:</td>
      <td>' . htmlspecialchars(formatField($lavoratore['data_assunzione'])) . '</td>
    </tr>
    <tr>
      <td class="bold">Nome e cognome:</td>
      <td>' . htmlspecialchars(trim(($lavoratore['nome'] ?? '') . ' ' . ($lavoratore['cognome'] ?? ''))) . '</td>
    </tr>
    <tr>
      <td class="bold">Residenza (via e numero):</td>
      <td>' . htmlspecialchars(formatField(trim(($lavoratore['indirizzo_via'] ?? '') . ' ' . ($lavoratore['indirizzo_numero_civico'] ?? '')))) . '</td>
    </tr>
    <tr>
      <td class="bold">Città (provincia e CAP):</td>
      <td>' . htmlspecialchars(formatField(trim(($lavoratore['indirizzo_citta'] ?? '') . ' ' . ($lavoratore['indirizzo_provincia'] ?? '') . ' ' . ($lavoratore['indirizzo_cap'] ?? '')))) . '</td>
    </tr>
    <tr>
      <td class="bold">Data di nascita:</td>
      <td>' . htmlspecialchars(formatField($lavoratore['data_nascita'])) . '</td>
    </tr>
    <tr>
      <td class="bold">Codice fiscale:</td>
      <td>' . htmlspecialchars(formatField($lavoratore['codice_fiscale'])) . '</td>
    </tr>
  </table>

  <div class="comunicazione">
    <p>
      Si impegna a rispettare lo statuto e versa la quota di adesione per il anno ' . $anno_corrente . ',
      per un importo di euro 100.
    </p>
    <p>
      CONSENSO AL TRATTAMENTO DEI DATI PERSONALI<br>
      Ricevuta la informativa di cui alla art. 13 del D. Lgs. 30/06/2003 n. 196 e fermo che il consenso e condizionato al rispetto delle disposizioni della vigente normativa, acconsento, ai sensi degli art. 23 e 26 del suddetto decreto, al trattamento da parte di ADL Cobas dei dati personali che mi riguardano, funzionali alla gestione di quanto da me richiesto.
    </p>
    
  </div>
</div>
</body>
</html>';

$footer = '
<table width="100%" style="border-top:1px solid #000;font-size:10pt;margin-top:20px;">
  <tr>
    <td>Data ' . $data_attuale . '</td>
    <td style="text-align:right;padding-top:10px;">Firma ____________________</td>
  </tr>
  <tr>
    <td colspan="2" style="text-align:center;padding-top:5px;">' . htmlspecialchars($settings['nome_completo'] ?? '') . '</td>
  </tr>
</table>';

$mpdf->SetHTMLFooter($footer);
$mpdf->WriteHTML($html);

// Pulizia del nome file da caratteri problematici
$nome_file = preg_replace('/[^a-zA-Z0-9_-]/', '', $lavoratore['cognome']) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $lavoratore['nome']) . '_rinnovo.pdf';

$mpdf->Output($nome_file, 'D');
?>