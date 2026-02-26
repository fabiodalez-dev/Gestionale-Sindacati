<?php
// send_reminder_emails.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config.php';

// Includi PHPMailer tramite Composer
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Funzione per inviare email
function sendReminderEmail($lavoratore, $smtpSettings, $template) {
    $mail = new PHPMailer(true);
    try {
        // Configurazione SMTP
        $mail->isSMTP();
        $mail->Host = $smtpSettings['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpSettings['username'];
        $mail->Password = $smtpSettings['password'];
        $mail->SMTPSecure = $smtpSettings['encryption'];
        $mail->Port = $smtpSettings['port'];

        // Mittente e destinatario
        $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name']);
        $mail->addAddress($lavoratore['email'], $lavoratore['nome'] . ' ' . $lavoratore['cognome']);

        // Contenuto dell'email
        $mail->isHTML(false);
        $mail->Subject = $template['subject'];
        
        // Sostituzioni dinamiche nel corpo dell'email
        $body = str_replace(['{{nome}}', '{{data_fine}}'], [ 
            $lavoratore['nome'], 
            date('d-m-Y', strtotime($lavoratore['data_fine'])) 
        ], $template['body']);
        $mail->Body = $body;

        $mail->send();
        echo "Email inviata a {$lavoratore['email']}\n";
    } catch (Exception $e) {
        echo "Errore nell'invio dell'email a {$lavoratore['email']}: {$mail->ErrorInfo}\n";
    }
}

// Recupera il template di reminder
$templateQuery = "SELECT * FROM email_templates WHERE name = 'Reminder Iscrizione' LIMIT 1";
$templateStmt = $mysqli->prepare($templateQuery);
$templateStmt->execute();
$templateResult = $templateStmt->get_result();
$template = $templateResult->fetch_assoc();
$templateStmt->close();

if (!$template) {
    echo "Template email non trovato.\n";
    exit;
}

// Recupera le impostazioni SMTP
$smtpQuery = "SELECT * FROM smtp_settings LIMIT 1";
$smtpStmt = $mysqli->prepare($smtpQuery);
$smtpStmt->execute();
$smtpResult = $smtpStmt->get_result();
$smtpSettings = $smtpResult->fetch_assoc();
$smtpStmt->close();

if (!$smtpSettings) {
    echo "Impostazioni SMTP non trovate.\n";
    exit;
}

// Calcola le date
$today = date('Y-m-d');
$thresholdDate = date('Y-m-d', strtotime('+30 days'));

// Seleziona i lavoratori con iscrizioni 'rinnovo annuale' che scadono entro 30 giorni e hanno un'email valida
$reminderQuery = "
    SELECT l.id, l.nome, l.cognome, l.email, i.data_fine
    FROM iscrizioni i
    JOIN lavoratori l ON i.lavoratore_id = l.id
    WHERE i.metodo_pagamento = 'rinnovo annuale'
      AND i.data_fine BETWEEN ? AND ?
      AND l.email IS NOT NULL
      AND l.email != ''
      AND l.iscritto = 1
    GROUP BY l.id
";
$reminderStmt = $mysqli->prepare($reminderQuery);
$reminderStmt->bind_param('ss', $today, $thresholdDate);
$reminderStmt->execute();
$reminderResult = $reminderStmt->get_result();

while ($lavoratore = $reminderResult->fetch_assoc()) {
    sendReminderEmail($lavoratore, $smtpSettings, $template);
}

$reminderStmt->close();

echo "Processo di invio reminder completato.\n";
?>
