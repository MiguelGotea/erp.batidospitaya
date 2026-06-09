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
    $id_proveedor_reembolso = !empty($input['id_proveedor_reembolso']) ? $input['id_proveedor_reembolso'] : $id_proveedor; // Por defecto: mismo proveedor
    $id_cuenta_proveedor = !empty($input['id_cuenta_proveedor']) ? $input['id_cuenta_proveedor'] : null;
    $concepto = !empty($input['concepto']) ? trim($input['concepto']) : '';
    $ceco = !empty($input['ceco']) ? (int) $input['ceco'] : null;
    $fecha_solicitud = !empty($input['fecha_solicitud']) ? $input['fecha_solicitud'] : date('Y-m-d');
    $moneda = !empty($input['moneda']) ? $input['moneda'] : 'Cordobas';
    $total_cordobas = isset($input['total_cordobas']) ? (float) $input['total_cordobas'] : 0;
    $items = isset($input['items']) ? $input['items'] : [];
    $usuario_registro = $_SESSION['usuario_id'] ?? 1;
    $id_solicitud = !empty($input['id']) ? (int) $input['id'] : null;

    if (empty($concepto)) {
        throw new Exception('El concepto es obligatorio.');
    }

    $conn->beginTransaction();

    if ($id_solicitud) {
        // Actualizar cabecera
        $stmt = $conn->prepare("
            UPDATE reembolsos_solicitudes 
            SET id_proveedor = ?, id_proveedor_reembolso = ?, id_cuenta_proveedor = ?, concepto = ?, ceco = ?, total_cordobas = ?, fecha_solicitud = ?, moneda = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $id_proveedor,
            $id_proveedor_reembolso,
            $id_cuenta_proveedor,
            $concepto,
            $ceco,
            $total_cordobas,
            $fecha_solicitud,
            $moneda,
            $id_solicitud
        ]);

        // Eliminar detalles anteriores para re-insertar (más simple que hacer un diff)
        $conn->prepare("DELETE FROM reembolsos_detalles WHERE id_solicitud = ?")->execute([$id_solicitud]);
    } else {
        // Insertar cabecera
        $stmt = $conn->prepare("
            INSERT INTO reembolsos_solicitudes 
            (id_proveedor, id_proveedor_reembolso, id_cuenta_proveedor, concepto, ceco, total_cordobas, usuario_registro, fecha_solicitud, moneda)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_proveedor,
            $id_proveedor_reembolso,
            $id_cuenta_proveedor,
            $concepto,
            $ceco,
            $total_cordobas,
            $usuario_registro,
            $fecha_solicitud,
            $moneda
        ]);
        $id_solicitud = $conn->lastInsertId();
    }

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

