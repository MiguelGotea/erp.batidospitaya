<?php
// compra_local_registro_pedidos_get_pedidos.php
// Obtiene los pedidos registrados para una sucursal en un rango de fechas

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';

    if (empty($codigo_sucursal)) {
        throw new Exception('Código de sucursal requerido');
    }

    if (empty($fecha_inicio) || empty($fecha_fin)) {
        throw new Exception('Rango de fechas requerido');
    }

    // Obtener pedidos existentes en el rango de fechas
    $sql = "SELECT 
                id,
                id_producto_presentacion,
                fecha_entrega,
                cantidad_pedido,
                fecha_hora_reportada,
                DAYOFWEEK(fecha_entrega) as dia_semana
            FROM compra_local_pedidos_historico
            WHERE codigo_sucursal = ?
            AND fecha_entrega BETWEEN ? AND ?
            AND cantidad_pedido > 0";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codigo_sucursal, $fecha_inicio, $fecha_fin]);
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
