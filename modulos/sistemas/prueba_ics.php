<?php
/**
 * Script de prueba para envío de archivo ICS (Calendario)
 * Ubicación: /modulos/sistemas/prueba_ics.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Ajustar ruta al autoloader de composer según la estructura del proyecto
require_once __DIR__ . '/../../core/vendor/autoload.php';

// Configuración de la reunión
$resumen = "Reunión de Prueba - Agenda ERP Batidos Pitaya";
$descripcion = "Esta es una reunión de prueba enviada automáticamente desde el ERP.";
$ubicacion = "Oficina Central / Google Meet";
$organizador_nombre = "Miguel Gotea";
$organizador_email = "mgotea@batidospitaya.com";
$asistente_email = "mgotea@batidospitaya.com"; // Enviándoselo a sí mismo para la prueba

$fecha_inicio = date('Ymd\THis', strtotime('+1 hour')); // En 1 hora
$fecha_fin = date('Ymd\THis', strtotime('+2 hours')); // 2 horas después

// Generar contenido ICS
$ics_content = "BEGIN:VCALENDAR\r\n" .
    "VERSION:2.0\r\n" .
    "PRODID:-//Batidos Pitaya//ERP//ES\r\n" .
    "METHOD:REQUEST\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:" . uniqid() . "@batidospitaya.com\r\n" .
    "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n" .
    "DTSTART:" . $fecha_inicio . "\r\n" .
    "DTEND:" . $fecha_fin . "\r\n" .
    "SUMMARY:" . $resumen . "\r\n" .
    "DESCRIPTION:" . $descripcion . "\r\n" .
    "LOCATION:" . $ubicacion . "\r\n" .
    "ORGANIZER;CN=" . $organizador_nombre . ":MAILTO:" . $organizador_email . "\r\n" .
    "ATTENDEE;RSVP=TRUE;CN=" . $organizador_nombre . ":MAILTO:" . $asistente_email . "\r\n" .
    "END:VEVENT\r\n" .
    "END:VCALENDAR";

$mail = new PHPMailer(true);

try {
    // Configuración del servidor
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'mgotea@batidospitaya.com';
    $mail->Password = 'Nihonk03#';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    // Destinatarios
    $mail->setFrom('mgotea@batidospitaya.com', 'Sistema ERP Pitaya');
    $mail->addAddress($asistente_email);

    // Contenido del correo
    $mail->isHTML(true);
    $mail->Subject = 'Invitación: ' . $resumen;
    $mail->Body = "Hola,<br><br>Se ha generado una nueva invitación para una reunión.<br><br>" .
        "<b>Asunto:</b> " . $resumen . "<br>" .
        "<b>Fecha:</b> " . date('d/m/Y H:i', strtotime($fecha_inicio)) . "<br><br>" .
        "Por favor, revisa el archivo adjunto para agendar la reunión.";
    $mail->AltBody = "Hola,\n\nSe ha generado una nueva invitación para una reunión.\n\n" .
        "Asunto: " . $resumen . "\n" .
        "Fecha: " . date('d/m/Y H:i', strtotime($fecha_inicio)) . "\n\n" .
        "Por favor, revisa el archivo adjunto para agendar la reunión.";

    // Adjuntar el archivo ICS
    // Es importante usar el método Ical para que algunos clientes (como Outlook) lo reconozcan mejor como invitación
    $mail->addStringAttachment($ics_content, 'reunion.ics', 'base64', 'text/calendar; charset=utf-8; method=REQUEST');

    // También podemos agregar el header específico para que se muestre como invitación interactiva
    $mail->addCustomHeader('Content-Type: text/calendar; charset=utf-8; method=REQUEST');

    $mail->send();
    echo "<h1>Prueba Exitosa</h1>";
    echo "<p>El mensaje ha sido enviado exitosamente a $asistente_email.</p>";
    echo "<p>Por favor verifica tu bandeja de entrada (y la carpeta de Spam por si acaso).</p>";

} catch (Exception $e) {
    echo "<h1>Error en el envío</h1>";
    echo "<p>El mensaje no pudo ser enviado. PHPMailer Error: {$mail->ErrorInfo}</p>";
}
