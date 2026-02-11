<?php
// compra_local_configuracion_despacho_toggle_status.php
// Cambiar estado activo/inactivo de un producto en una sucursal

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $id_producto = $_POST['id_producto_presentacion'] ?? '';
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';
    $status = $_POST['status'] ?? '';

    if (empty($id_producto) || empty($codigo_sucursal) || empty($status)) {
        throw new Exception('Datos incompletos');
    }

    // Validar status
    if (!in_array($status, ['activo', 'inactivo'])) {
        throw new Exception('Estado inválido');
    }

    // Actualizar status de todos los registros de este producto en esta sucursal
    $sql = "UPDATE compra_local_productos_despacho 
            SET status = ?, 
                usuario_modificacion = ?,
                fecha_modificacion = NOW()
            WHERE id_producto_presentacion = ? 
            AND codigo_sucursal = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $status,
        $usuario['CodOperario'],
        $id_producto,
        $codigo_sucursal
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
