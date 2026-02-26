<?php
require 'config.php';
checkLogin();

// Gestione dell'upload del file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];

        // Verifica l'estensione del file
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        if (strtolower($fileExtension) !== 'csv') {
            $error = "Per favore, carica un file CSV valido.";
        } else {
            // Processa il file CSV
            $handle = fopen($fileTmpPath, 'r');
            if ($handle !== false) {
                // Legge la prima riga (header)
                $header = fgetcsv($handle, 1000, ',', '"');
                if ($header === false) {
                    $error = "Il file CSV è vuoto o non valido.";
                } else {
                    $rowCount = 0;
                    $insertedCount = 0;
                    $errors = [];

                    while (($data = fgetcsv($handle, 1000, ',', '"')) !== false) {
                        $rowCount++;

                        // Mappa le colonne del CSV ai campi del database
                        $cognome_e_nome = isset($data[0]) ? trim($data[0]) : '';
                        $azienda_nome = isset($data[1]) ? trim($data[1]) : '';
                        $telefono = isset($data[2]) ? trim($data[2]) : '';
                        $sesso = isset($data[3]) ? trim($data[3]) : '';
                        $comune_residenza = isset($data[4]) ? trim($data[4]) : '';
                        $provincia = isset($data[5]) ? trim($data[5]) : '';
                        $paese_nascita = isset($data[6]) ? trim($data[6]) : '';
                        $settore = 'Pubblico'; // Il settore è sempre 'Pubblico'

                        // Controlla che 'COGNOME e NOME' non sia vuoto
                        if (empty($cognome_e_nome)) {
                            $errors[] = "Riga $rowCount: 'COGNOME e NOME' è vuoto. Riga saltata.";
                            continue;
                        }

                        // Divisione di 'COGNOME e NOME' in 'nome' e 'cognome'
                        $parts = explode(' ', $cognome_e_nome);
                        $nome = '';
                        $cognome = '';
                        if (count($parts) > 1) {
                            $nome = ucfirst(strtolower(array_shift($parts)));
                            $cognome = ucwords(strtolower(implode(' ', $parts)));
                        } else {
                            $nome = ucfirst(strtolower($cognome_e_nome));
                            $cognome = '';
                        }

                        // Normalizza il caso delle stringhe
                        $azienda_nome = ucwords(strtolower($azienda_nome));
                        $comune_residenza = ucwords(strtolower($comune_residenza));
                        $provincia = strtoupper($provincia);
                        $paese_nascita = ucwords(strtolower($paese_nascita));

                        // Mappa 'SESSO' a 'genere'
                        $genere_map = [
                            'M' => 'Uomo',
                            'F' => 'Donna',
                        ];
                        $genere = isset($genere_map[strtoupper($sesso)]) ? $genere_map[strtoupper($sesso)] : 'Altro';

                        // Imposta la data di iscrizione a NULL (o a una data predefinita se necessario)
                        $data_iscrizione_formattata = null;

                        // Ottieni o crea l'ID dell'azienda
                        if (!empty($azienda_nome)) {
                            $stmt_azienda = $mysqli->prepare("SELECT id FROM aziende WHERE nome_azienda = ?");
                            $stmt_azienda->bind_param('s', $azienda_nome);
                            $stmt_azienda->execute();
                            $result_azienda = $stmt_azienda->get_result();
                            if ($result_azienda->num_rows > 0) {
                                $azienda = $result_azienda->fetch_assoc();
                                $azienda_id = $azienda['id'];
                            } else {
                                // Crea una nuova azienda se non esiste
                                $stmt_insert_azienda = $mysqli->prepare("INSERT INTO aziende (nome_azienda) VALUES (?)");
                                $stmt_insert_azienda->bind_param('s', $azienda_nome);
                                if ($stmt_insert_azienda->execute()) {
                                    $azienda_id = $stmt_insert_azienda->insert_id;
                                } else {
                                    $errors[] = "Errore nell'inserimento dell'azienda alla riga $rowCount: " . $stmt_insert_azienda->error;
                                    continue;
                                }
                            }
                        } else {
                            $azienda_id = null;
                        }

                        // Controllo duplicati
                        $stmt_check = $mysqli->prepare("SELECT id FROM lavoratori WHERE nome = ? AND cognome = ? AND azienda_id = ?");
                        $stmt_check->bind_param('ssi', $nome, $cognome, $azienda_id);
                        $stmt_check->execute();
                        $result_check = $stmt_check->get_result();
                        if ($result_check->num_rows > 0) {
                            $errors[] = "Riga $rowCount: Lavoratore '$nome $cognome' già presente nel database. Riga saltata.";
                            $stmt_check->close();
                            continue;
                        }
                        $stmt_check->close();

                        // Prepara la query di inserimento
                        $stmt = $mysqli->prepare("INSERT INTO lavoratori (nome, cognome, indirizzo_citta, indirizzo_provincia, telefono, paese_nascita, settore, genere, azienda_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if ($stmt === false) {
                            $errors[] = "Errore nella preparazione della query alla riga $rowCount: " . $mysqli->error;
                            continue;
                        }

                        $stmt->bind_param(
                            'ssssssssi',
                            $nome,
                            $cognome,
                            $comune_residenza,
                            $provincia,
                            $telefono,
                            $paese_nascita,
                            $settore,
                            $genere,
                            $azienda_id
                        );

                        if ($stmt->execute()) {
                            $insertedCount++;
                        } else {
                            $errors[] = "Errore nell'inserimento del lavoratore alla riga $rowCount: " . $stmt->error;
                        }

                        $stmt->close();
                    }

                    fclose($handle);
                }
            } else {
                $error = "Impossibile aprire il file.";
            }
        }
    } else {
        $error = "Per favore, seleziona un file CSV da caricare.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Importa Lavoratori Settore Pubblico</title>
    <!-- Font Awesome -->
    <link href="<?php echo $base_url; ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Bootstrap CSS -->
    <link href="<?php echo $base_url; ?>theme/css/sb-admin-2.min.css?v=2.0" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="<?php echo $base_url; ?>styles.css?v=2.0" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Importa Lavoratori Settore Pubblico da CSV</h1>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($insertedCount)): ?>
            <div class="alert alert-success">
                Importazione completata: <?php echo $insertedCount; ?> lavoratori importati con successo.
                <?php if (!empty($errors)): ?>
                    <p>Tuttavia, si sono verificati alcuni errori:</p>
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form action="import_lavoratori_pubblico.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="csv_file">Seleziona il file CSV</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control-file" accept=".csv">
            </div>
            <button type="submit" class="btn btn-primary">Importa</button>
        </form>
    </div>
</body>
</html>
