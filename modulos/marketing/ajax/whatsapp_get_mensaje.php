<?php
/**
 * AJAX: Obtener detalle de un mensaje
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');

try {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        throw new Exception('ID requerido');
    }

    $stmt = $conn->prepare("
        SELECT m.*, c.nombre as campana_nombre
        FROM whatsapp_mensajes m
        LEFT JOIN whatsapp_campanas c ON m.campana_id = c.id
        WHERE m.id = ?
    ");
    $stmt->execute([$id]);
    $mensaje = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mensaje) {
        throw new Exception('Mensaje no encontrado');
    }

    echo json_encode([
        'success' => true,
        'mensaje' => $mensaje
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}