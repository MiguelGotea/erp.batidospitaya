<?php
/**
 * PASO 2: Actualización de Reunión (Reprogramación)
 * Ubicación: /modulos/sistemas/pruebas_ics_correo/paso2_actualizar_reunion.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../../core/vendor/autoload.php';

// UID IDÉNTICO AL PASO 1
$uid_fijo = "PRUEBA-ACTUALIZACION-UID-12345@batidospitaya.com";

$resumen = "Reunión de Coordinación - ACTUALIZADA"; // Opcionalmente cambiamos el asunto
$descripcion = "Esta reunión ha sido movida a las 3:00 PM del mismo día.";
$ubicacion = "Sala de Juntas B";
$organizador_email = "mantenimiento@batidospitaya.com";
$asistente_email = "mgotea@batidospitaya.com";

// NUEVA FECHA: Mañana a las 3:00 PM (15:00)
$fecha_inicio = date('Ymd\T150000');
$fecha_fin = date('Ymd\T160000');

// Generar ICS para ACTUALIZACIÓN (SEQUENCE: 1)
$ics_content = "BEGIN:VCALENDAR\r\n" .
    "VERSION:2.0\r\n" .
    "PRODID:-//Batidos Pitaya//ERP//ES\r\n" .
    "METHOD:REQUEST\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:" . $uid_fijo . "\r\n" .
    "SEQUENCE:1\r\n" . // INCREMENTADO A 1
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
    $mail->Subject = 'ACTUALIZACION: ' . $resumen;
    $mail->Body = "<h3>PASO 2: Actualización de Evento</h3><p>La reunión ha sido reprogramada para las 3:00 PM.</p><p>Vuelve a abrir el archivo adjunto para actualizar tu calendario.</p>";

    $mail->Ical = $ics_content;
    $mail->addStringAttachment($ics_content, 'reunion_actualizada.ics', '8bit', 'text/calendar; charset=utf-8; method=REQUEST');

    $mail->send();
    echo "<h1>Paso 2 Completado</h1><p>Se envió la actualización con el MISMO UID ($uid_fijo) y SEQUENCE 1</p>";
    echo "<p><a href='paso1_crear_reunion.php'>Volver al Paso 1</a></p>";

} catch (Exception $e) {
    echo "Error: {$mail->ErrorInfo}";
}
