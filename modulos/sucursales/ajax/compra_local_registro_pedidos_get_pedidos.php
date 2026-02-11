<?php
// compra_local_registro_pedidos_get_pedidos.php
// Obtiene los pedidos registrados para una sucursal

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';

    if (empty($codigo_sucursal)) {
        throw new Exception('Código de sucursal requerido');
    }

    // Obtener pedidos existentes
    $sql = "SELECT 
                id,
                id_producto_presentacion,
                dia_entrega,
                cantidad_pedido,
                fecha_hora_reportada
            FROM compra_local_productos_despacho
            WHERE codigo_sucursal = ?
            AND cantidad_pedido > 0";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codigo_sucursal]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'pedidos' => $pedidos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
