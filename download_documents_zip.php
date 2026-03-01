<?php
require_once 'config.php';
checkLogin();

// Avvia la sessione solo se non è già attiva
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Avvia l'output buffering per evitare output indesiderato
ob_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Token CSRF non valido.']);
    exit;
}

// Cleanup old ZIP files (older than 1 hour)
$downloadDir = __DIR__ . '/downloads/';
if (is_dir($downloadDir)) {
    foreach ((glob($downloadDir . '*.zip') ?: []) as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && $mtime < (time() - 3600)) {
            if (!@unlink($file)) {
                error_log("Impossibile eliminare ZIP temporaneo: " . $file);
            }
        }
    }
}

if (isset($_POST['document_ids']) && is_array($_POST['document_ids']) && count($_POST['document_ids']) > 0) {
    $document_ids = $_POST['document_ids'];

    // Imposta il percorso della cartella dove salvare il file ZIP
    $zipDir = __DIR__ . '/downloads/';
    if (!is_dir($zipDir)) {
        if (!mkdir($zipDir, 0755, true)) {
            ob_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Impossibile creare la cartella downloads.'
            ]);
            exit;
        }
    }
    
    // Nome del file ZIP (personalizzabile)
    $zipFilename = 'documenti_lavoratore_' . time() . '.zip';
    $zipFilepath = $zipDir . $zipFilename;
    
    $zip = new ZipArchive();
    if ($zip->open($zipFilepath, ZipArchive::CREATE) !== TRUE) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Impossibile creare il file ZIP.'
        ]);
        exit;
    }
    
    // Aggiungi ogni file selezionato allo ZIP
    foreach ($document_ids as $docId) {
        $docId = intval($docId);
        $stmt = executeQuery("SELECT percorso_documento FROM documenti_lavoratori WHERE id = ?", [$docId], 'i');
        if ($stmt !== false) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Costruisci il percorso file sul filesystem
                $filePath = __DIR__ . '/uploads/' . $row['percorso_documento'];
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, basename($row['percorso_documento']));
                }
            }
        }
    }
    
    $zip->close();
    
    // Costruisci il link assoluto per il file ZIP usando $base_url
    $downloadLink = rtrim($base_url, '/') . '/downloads/' . $zipFilename;
    
    // Salva il link in sessione per renderlo persistente
    $_SESSION['zip_link'] = $downloadLink;
    
    // Pulisci l'output buffer e restituisci il JSON
    ob_clean();
    echo json_encode([
        'success' => true,
        'download_link' => $downloadLink
    ]);
    exit;
} else {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Nessun documento selezionato.'
    ]);
    exit;
}
?>
