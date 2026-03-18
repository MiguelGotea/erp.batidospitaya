<?php
/**
 * Obtener detalles de una solicitud de reembolso para edición
 * Ubicación: /modulos/compras/ajax/reembolsos_ia_get_detalle.php
 */

require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$id) {
        throw new Exception('ID de solicitud no proporcionado.');
    }

    // Obtener cabecera
    $stmt = $conn->prepare("
        SELECT s.*, p.nombre as proveedor_nombre, cp.banco, cp.numero_cuenta
        FROM reembolsos_solicitudes s
        LEFT JOIN proveedores p ON s.id_proveedor = p.id
        LEFT JOIN cuenta_proveedor cp ON s.id_cuenta_proveedor = cp.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        throw new Exception('Solicitud no encontrada.');
    }

    // Obtener detalles
    $stmtDet = $conn->prepare("SELECT * FROM reembolsos_detalles WHERE id_solicitud = ?");
    $stmtDet->execute([$id]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'solicitud' => $solicitud,
        'detalles' => $detalles
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
