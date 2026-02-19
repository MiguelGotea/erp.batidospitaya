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
$asistente_email = "mantenimiento@batidospitaya.com";

$fecha_inicio = date('Ymd\THis', strtotime('+24 hour'));
$fecha_fin = date('Ymd\THis', strtotime('+25 hours'));


// Generar contenido ICS (Formato más estricto)
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
    "ATTENDEE;RSVP=TRUE;PARTSTAT=NEEDS-ACTION;CN=Invitado:MAILTO:" . $asistente_email . "\r\n" .
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
    $mail->setFrom('mgotea@batidospitaya.com', 'Miguel Gotea');
    $mail->addAddress($asistente_email);

    // Contenido del correo
    $mail->isHTML(true);
    $mail->Subject = 'Invitación a Sesión: ' . $resumen;
    $mail->Body = "Estimado equipo,<br><br>" .
        "Se ha programado una nueva sesión de coordinación en el sistema ERP.<br><br>" .
        "<b>Asunto:</b> " . $resumen . "<br>" .
        "<b>Fecha:</b> " . date('d/m/Y H:i', strtotime($fecha_inicio)) . "<br><br>" .
        "Por favor, confirme su asistencia a través del calendario adjunto.<br><br>" .
        "Atentamente,<br>Sistemas Batidos Pitaya";

    $mail->AltBody = "Estimado equipo,\n\nSe ha programado una nueva sesión de coordinación en el sistema ERP.\n\n" .
        "Asunto: " . $resumen . "\n" .
        "Fecha: " . date('d/m/Y H:i', strtotime($fecha_inicio)) . "\n\n" .
        "Por favor, confirme su asistencia a través del calendario adjunto.";

    // Método específico de PHPMailer para iCal (Mejor compatibilidad con Outlook/Gmail)
    $mail->Ical = $ics_content;

    // También lo enviamos como adjunto tradicional por si acaso, pero con codificación 8bit
    $mail->addStringAttachment($ics_content, 'invite.ics', '8bit', 'text/calendar; charset=utf-8; method=REQUEST');

    $mail->send();
    echo "<h1>Envío corporativo intentado</h1>";
    echo "<p>Correo enviado a $asistente_email.</p>";
    echo "<p>Si falla de nuevo, es posible que el servidor de Hostinger esté bloqueando archivos .ics internos por políticas de seguridad estrictas.</p>";

} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>Error: {$mail->ErrorInfo}</p>";
}
?>