<?php
require 'config.php';

// Rilascia tutti i lock associati all'utente (se applicabile)
if (isset($_SESSION['user_id'])) {
    releaseAllLocks($_SESSION['user_id']);
}

// Rimuove il cookie "remember_me"
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

// Distrugge la sessione
$_SESSION = [];
session_unset();
session_destroy();

// Reindirizza alla pagina di login
header('Location: ' . $base_url . 'login.php');
exit;
?>
