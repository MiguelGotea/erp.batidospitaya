<?php
// compra_local_configuracion_despacho_update_minimo.php
// Actualiza el pedido mínimo de un producto para una sucursal

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

// Configurar zona horaria de Managua
date_default_timezone_set('America/Managua');

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $id_producto = $_POST['id_producto_presentacion'] ?? '';
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';
    $pedido_minimo = $_POST['pedido_minimo'] ?? 1;

    if (empty($id_producto) || empty($codigo_sucursal)) {
        throw new Exception('Datos incompletos');
    }

    $pedido_minimo = intval($pedido_minimo);
    if ($pedido_minimo < 1) {
        $pedido_minimo = 1;
    }

    // Actualizar todos los registros de este producto para esta sucursal
    $sql = "UPDATE compra_local_configuracion_despacho 
            SET pedido_minimo = ?, 
                usuario_modificacion = ?, 
                fecha_modificacion = CURRENT_TIMESTAMP 
            WHERE id_producto_presentacion = ? 
            AND codigo_sucursal = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $pedido_minimo,
        $usuario['CodOperario'],
        $id_producto,
        $codigo_sucursal
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Pedido mínimo actualizado correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
