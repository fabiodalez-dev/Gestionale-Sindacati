<?php
require 'config.php';
checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF non valido.']);
        exit;
    }

    $lavoratore_id = intval($_POST['lavoratore_id']);
    $descrizione = $_POST['descrizione_documento'];

    // Controlla se è stato caricato un file
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $fileSize = $_FILES['file']['size'];
        $fileType = $_FILES['file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Verifica il tipo di file (immagini o PDF)
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            $error = "Tipo di file non supportato. Sono permessi solo immagini e PDF.";
        } else {
            // Verifica il MIME type reale del file
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $actualMime = $finfo->file($fileTmpPath);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            if (!in_array($actualMime, $allowedMimes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Il tipo MIME del file non corrisponde a un formato consentito.']);
                exit;
            }
            // Sanifica il nome del file
            $newFileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;

            // Directory di destinazione
$uploadFileDir = __DIR__ . '/uploads/';
$dest_path = $uploadFileDir . $newFileName;

            // Controlla se la cartella uploads esiste
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            // Sposta il file caricato nella cartella uploads
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Salva i dettagli del documento nel database
                $query = "INSERT INTO documenti_lavoratori (lavoratore_id, descrizione_documento, percorso_documento, data_caricamento) VALUES (?, ?, ?, NOW())";
                $params = [$lavoratore_id, $descrizione, $newFileName];
                $types = 'iss';

                $stmt = executeQuery($query, $params, $types);

                if ($stmt) {
                    // Restituisci una risposta JSON per Dropzone
                    echo json_encode(['success' => true]);
                    exit;
                } else {
                    $error = "Errore durante il salvataggio nel database.";
                }
            } else {
                $error = "Errore durante il caricamento del file.";
            }
        }
    } else {
        $error = "Nessun file selezionato o errore durante il caricamento.";
    }

    // Restituisci una risposta JSON con l'errore
    http_response_code(400);
    echo json_encode(['error' => $error]);
    exit;
} else {
    echo "Richiesta non valida.";
}
?>
