<?php
/**
 * Guardar solicitud de reembolso
 * Ubicación: /modulos/compras/ajax/reembolsos_ia_guardar.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

@session_start();
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos inválidos.');
    }

    $id_proveedor = !empty($input['id_proveedor']) ? $input['id_proveedor'] : null;
    $id_cuenta_proveedor = !empty($input['id_cuenta_proveedor']) ? $input['id_cuenta_proveedor'] : null;
    $concepto = !empty($input['concepto']) ? trim($input['concepto']) : '';
    $ceco = !empty($input['ceco']) ? trim($input['ceco']) : '';
    $fecha_solicitud = !empty($input['fecha_solicitud']) ? $input['fecha_solicitud'] : date('Y-m-d');
    $total_cordobas = isset($input['total_cordobas']) ? (float)$input['total_cordobas'] : 0;
    $items = isset($input['items']) ? $input['items'] : [];
    $usuario_registro = $_SESSION['usuario_id'] ?? 1;

    if (empty($concepto)) {
        throw new Exception('El concepto es obligatorio.');
    }

    $conn->beginTransaction();

    // Insertar cabecera
    $stmt = $conn->prepare("
        INSERT INTO reembolsos_solicitudes 
        (id_proveedor, id_cuenta_proveedor, concepto, ceco, total_cordobas, usuario_registro, fecha_solicitud)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $id_proveedor,
        $id_cuenta_proveedor,
        $concepto,
        $ceco,
        $total_cordobas,
        $usuario_registro,
        $fecha_solicitud
    ]);

    $id_solicitud = $conn->lastInsertId();

    // Insertar detalles
    if (!empty($items)) {
        $stmtDetalle = $conn->prepare("
            INSERT INTO reembolsos_detalles 
            (id_solicitud, cantidad, detalle, monto_cordobas, foto_factura)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $stmtDetalle->execute([
                $id_solicitud,
                $item['cantidad'] ?? 1,
                $item['detalle'] ?? 'Gasto general',
                $item['total_cordobas'] ?? 0,
                $item['foto_path'] ?? null
            ]);
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Solicitud de reembolso guardada correctamente.',
        'id' => $id_solicitud
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
