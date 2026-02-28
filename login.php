<?php
require 'config.php';

// --- Rate Limiting Functions (file-based) ---

/**
 * Atomically check rate limit and reserve a slot for the current attempt.
 * Returns true if allowed, false if rate-limited, null on I/O error.
 */
function checkLoginRateLimit($ip) {
    $lockFile = __DIR__ . '/sessions/login_attempts_' . md5($ip) . '.json';
    $fh = fopen($lockFile, 'c+');
    if ($fh === false || !flock($fh, LOCK_EX)) {
        if (is_resource($fh)) fclose($fh);
        error_log("Rate limiter non disponibile per IP: $ip");
        return null;
    }
    $raw = stream_get_contents($fh);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data) || !isset($data['attempts']) || !is_array($data['attempts'])) {
        $data = ['attempts' => []];
    }
    $now = time();
    $data['attempts'] = array_values(array_filter($data['attempts'], fn($t) => $t > $now - 900));
    if (count($data['attempts']) >= 5) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return false;
    }
    // Reserve a slot atomically
    $data['attempts'][] = $now;
    $json = json_encode($data);
    if (ftruncate($fh, 0) === false || rewind($fh) === false || fwrite($fh, $json) === false || fflush($fh) === false) {
        error_log("Rate limiter: errore scrittura file per IP: $ip");
        flock($fh, LOCK_UN);
        fclose($fh);
        return null;
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return true;
}

/**
 * Clear all failed login attempts for the given IP (on successful login).
 */
function clearLoginAttempts($ip) {
    $lockFile = __DIR__ . '/sessions/login_attempts_' . md5($ip) . '.json';
    if (file_exists($lockFile)) {
        if (!unlink($lockFile)) {
            error_log("Impossibile rimuovere il file di rate limit: $lockFile");
        }
    }
}

// --- End Rate Limiting Functions ---

// Recupera le impostazioni attuali, inclusa la voce 'logo'
$settings = getSettings(['logo']);
$logo_path = $settings['logo'] ?? 'uploads/default_logo.png';

// Se l'utente è già loggato, reindirizza alla dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF non valido.';
    } else {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Check rate limit before processing login
        $rateLimitResult = checkLoginRateLimit($clientIp);
        if ($rateLimitResult === false) {
            $error = 'Troppi tentativi di accesso. Riprova tra 15 minuti.';
        } elseif ($rateLimitResult === null) {
            $error = 'Servizio temporaneamente non disponibile. Riprova.';
        } else {
            $email = sanitizeInput($_POST['email']);
            $password = $_POST['password'];
            $remember_me = isset($_POST['remember_me']) ? true : false;

            $stmt = executeQuery("SELECT id, username, password, role FROM users WHERE email = ?", [$email], 's');
            if ($stmt) {
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        // Regenerate session ID to prevent session fixation attacks
                        session_regenerate_id(true);

                        // Clear failed login attempts on success
                        clearLoginAttempts($clientIp);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];

                        if ($remember_me) {
                            setcookie('remember_me', '1', time() + (30 * 24 * 60 * 60), '/', '', isset($_SERVER['HTTPS']), true);
                        } else {
                            if (isset($_COOKIE['remember_me'])) {
                                setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
                            }
                        }

                        header('Location: ' . $base_url . 'dashboard.php');
                        exit;
                    } else {
                        $error = 'Email o password errati.';
                    }
                } else {
                    $error = 'Email o password errati.';
                }
            } else {
                $error = 'Errore nella connessione al database.';
            }
        }
    }
}

generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Login - ADL Cobas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.5" rel="stylesheet">
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.5" rel="stylesheet">
</head>
<body class="bg-gradient-primary">

    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

                <div class="text-center mb-4">
                    <img src="<?php echo sanitizeForHTML($base_url . $logo_path); ?>" alt="logo ADL" class="img-fluid" style="max-width: 100px; filter: brightness(0) invert(1); opacity: 0.9;">
                </div>

                <div class="card border-0" style="border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.4);">
                    <div class="card-body p-0">
                        <div class="p-4 pt-5 pb-5" style="padding-left:2rem!important;padding-right:2rem!important;">
                            <h1 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Accedi</h1>
                            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.75rem;">Inserisci le tue credenziali per continuare</p>

                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert" style="font-size: 0.85rem;">
                                    <?php echo sanitizeForHTML($error); ?>
                                </div>
                            <?php endif; ?>

                            <form class="user" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <?php csrfInputField(); ?>
                                <div class="form-group">
                                    <label for="email" style="font-size: 0.8rem; font-weight: 500; color: #475569;">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="nome@esempio.it" required autofocus
                                           style="height: 44px; border-radius: 8px; font-size: 0.88rem;">
                                </div>
                                <div class="form-group">
                                    <label for="password" style="font-size: 0.8rem; font-weight: 500; color: #475569;">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="La tua password" required
                                           style="height: 44px; border-radius: 8px; font-size: 0.88rem;">
                                </div>
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox small">
                                        <input type="checkbox" class="custom-control-input" id="rememberMe" name="remember_me">
                                        <label class="custom-control-label" for="rememberMe" style="color: #64748b;">Resta connesso</label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block" style="height: 44px; border-radius: 8px; font-size: 0.9rem; font-weight: 600;">
                                    Accedi
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <p class="text-center mt-4" style="font-size: 0.75rem; color: rgba(255,255,255,0.35);">&copy; ADL Cobas <?php echo date('Y'); ?></p>
            </div>
        </div>
    </div>

    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/gsap/gsap.min.js"></script>
    <script>
    gsap.fromTo('.text-center.mb-4 img', { opacity: 0 }, { opacity: 1, duration: 0.3, delay: 0 });
    gsap.fromTo('.card', { opacity: 0, y: 10 }, { opacity: 1, y: 0, duration: 0.3, ease: 'power2.out', delay: 0.05 });
    </script>
</body>
</html>
