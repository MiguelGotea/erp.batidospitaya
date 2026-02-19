<?php
/**
 * PASO 1: Creación de Reunión
 * Ubicación: /modulos/sistemas/pruebas_ics_correo/paso1_crear_reunion.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../../core/vendor/autoload.php';

// UID Fijo para la prueba (en la realidad lo guardarías en DB)
$uid_fijo = "PRUEBA-ACTUALIZACION-UID-12345@batidospitaya.com";

$resumen = "Reunión de Coordinación - PASO 1";
$descripcion = "Esta es la reunión inicial. En el paso 2 la moveremos de hora.";
$ubicacion = "Oficina Sistemas";
$organizador_email = "mantenimiento@batidospitaya.com";
$asistente_email = "mgotea@batidospitaya.com";

// Fecha: Mañana a las 10:00 AM
$fecha_inicio = date('Ymd\T100000');
$fecha_fin = date('Ymd\T110000');

// Generar ICS para CREACIÓN (SEQUENCE: 0)
$ics_content = "BEGIN:VCALENDAR\r\n" .
    "VERSION:2.0\r\n" .
    "PRODID:-//Batidos Pitaya//ERP//ES\r\n" .
    "METHOD:REQUEST\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:" . $uid_fijo . "\r\n" .
    "SEQUENCE:0\r\n" .
    "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n" .
    "DTSTART:" . $fecha_inicio . "\r\n" .
    "DTEND:" . $fecha_fin . "\r\n" .
    "SUMMARY:" . $resumen . "\r\n" .
    "DESCRIPTION:" . $descripcion . "\r\n" .
    "LOCATION:" . $ubicacion . "\r\n" .
    "ORGANIZER;CN=Mantenimiento Pitaya:MAILTO:" . $organizador_email . "\r\n" .
    "ATTENDEE;RSVP=TRUE;PARTSTAT=NEEDS-ACTION;CN=Miguel Gotea:MAILTO:" . $asistente_email . "\r\n" .
    "STATUS:CONFIRMED\r\n" .
    "END:VEVENT\r\n" .
    "END:VCALENDAR";

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'mantenimiento@batidospitaya.com';
    $mail->Password = 'Nihonk03#';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom('mantenimiento@batidospitaya.com', 'Mantenimiento Pitaya');
    $mail->addAddress($asistente_email);

    $mail->isHTML(true);
    $mail->Subject = 'INVITACION: ' . $resumen;
    $mail->Body = "<h3>PASO 1: Creación de Evento</h3><p>Se ha creado una reunión para mañana a las 10:00 AM.</p>";

    $mail->Ical = $ics_content;
    $mail->addStringAttachment($ics_content, 'reunion_paso1.ics', '8bit', 'text/calendar; charset=utf-8; method=REQUEST');

    $mail->send();
    echo "<h1>Paso 1 Completado</h1><p>Se envió la invitación inicial con UID: $uid_fijo</p>";
    echo "<p><a href='paso2_actualizar_reunion.php'>Ir al Paso 2 (Actualizar Fecha)</a></p>";

} catch (Exception $e) {
    echo "Error: {$mail->ErrorInfo}";
}
