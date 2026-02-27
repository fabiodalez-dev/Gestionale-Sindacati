<?php
require 'config.php';
checkLogin();

require __DIR__ . '/vendor/autoload.php'; // Autoload di Composer per MPDF

use Mpdf\Mpdf;

// Verifica se un lavoratore è stato selezionato per generare il PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lavoratore_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Token CSRF non valido.');
    }

    $lavoratore_id = intval($_POST['lavoratore_id']);

    // Recupera i dati del lavoratore selezionato
    $sql = "SELECT * FROM lavoratori WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $lavoratore_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Gestisci valori nulli
        foreach ($row as $key => $value) {
            if ($value === null) {
                $row[$key] = ''; // Converti null in stringa vuota
            }
        }

        // Inizializzazione di MPDF
        $mpdf = new Mpdf([
            'format' => 'A4',
            'default_font' => 'Roboto',
        ]);

        // HTML per il PDF
        $html = '
        <table class="header-table" style="width: 100%; margin-bottom: 20px;">
            <tr>
                <td style="width: 50%; text-align: left;">
                    <img src="images/logo.png" style="width: 100px; margin-bottom: 10px;">
                    <p style="font-size: 12px; line-height: 1.5;">
                        Viale Felice Cavallotti, 2<br>
                        Padova<br>
                        Email: info@adlcobas.org<br>
                        Pec – sindacato@pec.adlcobas.org
                    </p>
                </td>
                <td style="width: 50%; text-align: right; vertical-align: top;">
                    <strong>' . htmlspecialchars($row['cognome'] . ' ' . $row['nome']) . '</strong><br>
                    ' . htmlspecialchars($row['indirizzo_via'] . ' ' . $row['indirizzo_numero_civico']) . '<br>
                    ' . htmlspecialchars($row['indirizzo_cap'] . ' ' . $row['indirizzo_citta'] . ' (' . $row['indirizzo_provincia'] . ')') . '<br>
                    Tel: ' . htmlspecialchars($row['telefono']) . '<br>
                    Email: ' . htmlspecialchars($row['email']) . '
                </td>
            </tr>
        </table>';

        $html .= '<p style="margin-bottom: 20px; font-size: 12px; line-height: 1.5;">
            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
        </p>';

        // Tabella dei dati generali in due colonne
        $html .= '<table class="worker-data" style="width: 100%; border-collapse: collapse; font-size: 12px;">';
        $counter = 0;
        $columns = 2;
        foreach ($row as $key => $value) {
            if ($key !== 'id') {
                if ($counter % $columns === 0) {
                    $html .= '<tr>';
                }
                $html .= '<td style="width: 50%; border: 1px solid #ddd; padding: 8px; vertical-align: top;">
                            <strong>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $key))) . '</strong><br>
                            ' . htmlspecialchars($value) . '
                          </td>';
                $counter++;
                if ($counter % $columns === 0) {
                    $html .= '</tr>';
                }
            }
        }
        if ($counter % $columns !== 0) {
            while ($counter % $columns !== 0) {
                $html .= '<td style="width: 50%; border: 1px solid #ddd; padding: 8px;"></td>';
                $counter++;
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        // Sezione per le firme
        $html .= '<table style="width: 100%; margin-top: 50px;">
            <tr>
                <td style="width: 50%; text-align: left;">
                    ___________________________<br>
                    <span>ADL COBAS</span>
                </td>
                <td style="width: 50%; text-align: right;">
                    ___________________________<br>
                    <span>' . htmlspecialchars($row['cognome'] . ' ' . $row['nome']) . '</span>
                </td>
            </tr>
        </table>';

        // Scrittura del contenuto nel PDF
        ob_clean();
        $mpdf->WriteHTML($html);

        // Output del file come download
        $filename = $row['cognome'] . '_' . $row['nome'] . '.pdf';
        $mpdf->Output($filename, 'D'); // 'D' forza il download del file
        exit;
    } else {
        echo "Lavoratore non trovato.";
    }
}

// Recupera tutti i lavoratori per il menu a tendina
$sql = "SELECT id, cognome, nome FROM lavoratori ORDER BY cognome, nome";
$result = $mysqli->query($sql);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scarica PDF Lavoratore</title>
</head>
<body>
    <h1>Scarica PDF Lavoratore</h1>
    <form method="POST" action="">
        <?php csrfInputField(); ?>
        <label for="lavoratore_id">Seleziona un lavoratore:</label>
        <select name="lavoratore_id" id="lavoratore_id" required>
            <option value="">-- Seleziona --</option>
            <?php while ($row = $result->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($row['id']) ?>">
                    <?= htmlspecialchars($row['cognome'] . ' ' . $row['nome']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit">Scarica PDF</button>
    </form>
</body>
</html>
