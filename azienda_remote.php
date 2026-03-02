<?php
/**
 * azienda_remote.php - Dettaglio azienda da CRM remoto via API
 */
require_once 'config.php';
checkLogin();

$conn_id = intval($_GET['conn_id'] ?? 0);
$azienda_id = intval($_GET['id'] ?? 0);

if ($conn_id <= 0 || $azienda_id <= 0) {
    die("Parametri mancanti.");
}

// Recupera connessione
$stmt = executeQuery("SELECT * FROM api_connections WHERE id = ? AND is_active = 1", [$conn_id], 'i');
if (!$stmt) die("Errore.");
$conn = $stmt->get_result()->fetch_assoc();
if (!$conn) die("Connessione non trovata o disattivata.");

$conn_name = $conn['name'];
$endpoint_url = rtrim($conn['endpoint_url'], '/');
$api_key_value = $conn['api_key'];

// Verifica che l'endpoint usi HTTPS
$scheme = strtolower((string) parse_url($endpoint_url, PHP_URL_SCHEME));
if ($scheme !== 'https') {
    die("Endpoint API non sicuro: è richiesto HTTPS.");
}

// Chiama API remota
$url = $endpoint_url . '?' . http_build_query(['action' => 'azienda', 'id' => $azienda_id]);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $api_key_value,
        'Accept: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) die("Errore di connessione: " . htmlspecialchars($curlError));
if ($httpCode !== 200) die("Errore HTTP $httpCode");

$data = json_decode($response, true);
if (!$data || !isset($data['success']) || !$data['success']) {
    die("Errore: " . htmlspecialchars($data['error'] ?? 'Risposta non valida'));
}

$azienda = $data['data'];
$lavoratori = $azienda['lavoratori'] ?? [];
$unita_operative = $azienda['unita_operative'] ?? [];
$numero_lavoratori = $azienda['numero_lavoratori'] ?? count($lavoratori);

generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dettaglio Azienda - <?php echo sanitizeForHTML($conn_name); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.10" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.10" rel="stylesheet">
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/topbar.php'; ?>
                <div class="container-fluid">
                    <a href="aziende_remote.php?conn_id=<?php echo $conn_id; ?>" class="btn btn-secondary mb-4">
                        <i class="fas fa-arrow-left"></i> Torna alla lista
                    </a>

                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-globe"></i> <?php echo sanitizeForHTML($azienda['nome_azienda'] ?? ''); ?>
                        <small class="text-muted" style="font-size: 0.6em;">(<?php echo sanitizeForHTML($conn_name); ?>)</small>
                    </h1>

                    <div class="card mt-2">
                        <div class="card-header">Informazioni Azienda</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-md-6 mb-3">
                                    <strong>Nome Azienda:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['nome_azienda'] ?? ''); ?></p>
                                </div>
                                <div class="col-12 col-md-6 mb-3">
                                    <strong>Partita IVA:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['partita_iva'] ?? ''); ?></p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-md-6 mb-3">
                                    <strong>Numero di Lavoratori:</strong>
                                    <p><?php echo intval($numero_lavoratori); ?></p>
                                </div>
                                <div class="col-12 col-md-6 mb-3">
                                    <strong>Settore:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['settore'] ?? ''); ?></p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-md-4 mb-3">
                                    <strong>Indirizzo (Via):</strong>
                                    <p><?php echo sanitizeForHTML($azienda['indirizzo_via'] ?? ''); ?></p>
                                </div>
                                <div class="col-6 col-md-2 mb-3">
                                    <strong>N. Civico:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['indirizzo_numero_civico'] ?? ''); ?></p>
                                </div>
                                <div class="col-6 col-md-2 mb-3">
                                    <strong>CAP:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['indirizzo_cap'] ?? ''); ?></p>
                                </div>
                                <div class="col-12 col-md-4 mb-3">
                                    <strong>PEC:</strong>
                                    <p>
                                        <?php if (!empty($azienda['pec'])): ?>
                                            <a href="mailto:<?php echo sanitizeForHTML($azienda['pec']); ?>"><?php echo sanitizeForHTML($azienda['pec']); ?></a>
                                        <?php else: ?>-<?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-md-4 mb-3">
                                    <strong>Città:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['indirizzo_citta'] ?? ''); ?></p>
                                </div>
                                <div class="col-12 col-md-4 mb-3">
                                    <strong>Provincia:</strong>
                                    <p><?php echo sanitizeForHTML($azienda['indirizzo_provincia'] ?? ''); ?></p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-md-6 mb-3">
                                    <strong>Telefono:</strong>
                                    <p>
                                        <?php if (!empty($azienda['telefono'])): ?>
                                            <a href="tel:<?php echo sanitizeForHTML($azienda['telefono']); ?>"><?php echo sanitizeForHTML($azienda['telefono']); ?></a>
                                        <?php else: ?>-<?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-12 col-md-6 mb-3">
                                    <strong>Email:</strong>
                                    <p>
                                        <?php if (!empty($azienda['email'])): ?>
                                            <a href="mailto:<?php echo sanitizeForHTML($azienda['email']); ?>"><?php echo sanitizeForHTML($azienda['email']); ?></a>
                                        <?php else: ?>-<?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>


                    <?php if (!empty($unita_operative)): ?>
                    <div class="card mt-4 mb-4">
                        <div class="card-header">Unità Operative</div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead><tr><th>Nome</th><th>Descrizione</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($unita_operative as $uo): ?>
                                        <tr>
                                            <td><?php echo sanitizeForHTML($uo['nome'] ?? ''); ?></td>
                                            <td><?php echo sanitizeForHTML($uo['descrizione'] ?? ''); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php include __DIR__ . '/footer.php'; ?>
        </div>
    </div>
    <a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>
</body>
</html>
