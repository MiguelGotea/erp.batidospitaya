<?php
/**
 * AJAX: Obtener una plantilla específica
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');

try {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        throw new Exception('ID requerido');
    }

    $stmt = $conn->prepare("SELECT * FROM whatsapp_plantillas WHERE id = ?");
    $stmt->execute([$id]);
    $plantilla = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plantilla) {
        throw new Exception('Plantilla no encontrada');
    }

    echo json_encode([
        'success' => true,
        'plantilla' => $plantilla
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}