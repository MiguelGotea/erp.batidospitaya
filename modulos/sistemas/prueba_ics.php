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
$asistente_email = "miguel_gotea@hotmail.com"; // Cambiado a hotmail para evitar rebotes de spam por envío a sí mismo

$fecha_inicio = date('Ymd\THis', strtotime('+24 hour')); // Programado para mañana para que parezca más real
$fecha_fin = date('Ymd\THis', strtotime('+25 hours'));

// Generar contenido ICS
$ics_content = "BEGIN:VCALENDAR\r\n" .
    "VERSION:2.0\r\n" .
    "PRODID:-//Batidos Pitaya//ERP//ES\r\n" .
    "METHOD:REQUEST\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:" . date('YmdHis') . "-" . uniqid() . "@batidospitaya.com\r\n" .
    "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n" .
    "DTSTART:" . $fecha_inicio . "\r\n" .
    "DTEND:" . $fecha_fin . "\r\n" .
    "SUMMARY:" . $resumen . "\r\n" .
    "DESCRIPTION:" . $descripcion . "\r\n" .
    "LOCATION:" . $ubicacion . "\r\n" .
    "ORGANIZER;CN=" . $organizador_nombre . ":MAILTO:" . $organizador_email . "\r\n" .
    "ATTENDEE;RSVP=TRUE;CN=Invitado:MAILTO:" . $asistente_email . "\r\n" .
    "SEQUENCE:0\r\n" .
    "STATUS:CONFIRMED\r\n" .
    "TRANSP:OPAQUE\r\n" .
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
    $mail->setFrom('mgotea@batidospitaya.com', 'Miguel Gotea - ERP Pitaya');
    $mail->addAddress($asistente_email);
    $mail->addReplyTo('mgotea@batidospitaya.com', 'Miguel Gotea');

    // Contenido del correo
    $mail->isHTML(true);
    $mail->Subject = 'Confirmación de Reunión: ' . $resumen;
    $mail->Body = "Estimado usuario,<br><br>" .
        "Le informamos que se ha programado una nueva sesión de trabajo en el sistema ERP.<br><br>" .
        "<b>Detalles de la sesión:</b><br>" .
        "• <b>Asunto:</b> " . $resumen . "<br>" .
        "• <b>Fecha y Hora:</b> " . date('d/m/Y H:i', strtotime($fecha_inicio)) . "<br>" .
        "• <b>Ubicación:</b> " . $ubicacion . "<br><br>" .
        "Por favor, acepte la invitación adjunta para sincronizarla con su calendario.<br><br>" .
        "Saludos cordiales,<br><b>Equipo de Sistemas Batidos Pitaya</b>";
    $mail->AltBody = "Estimado usuario,\n\nLe informamos que se ha programado una nueva sesión de trabajo en el sistema ERP.\n\n" .
        "Detalles de la sesión:\n" .
        "- Asunto: " . $resumen . "\n" .
        "- Fecha y Hora: " . date('d/m/Y H:i', strtotime($fecha_inicio)) . "\n" .
        "- Ubicación: " . $ubicacion . "\n\n" .
        "Por favor, acepte la invitación adjunta para sincronizarla con su calendario.\n\n" .
        "Saludos cordiales,\nEquipo de Sistemas Batidos Pitaya";

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
