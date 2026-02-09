<?php
/**
 * AJAX: Obtener lista de plantillas
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');

try {
    $stmt = $conn->prepare("
        SELECT * FROM whatsapp_plantillas 
        ORDER BY activa DESC, tipo, nombre
    ");
    $stmt->execute();
    $plantillas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'plantillas' => $plantillas
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}