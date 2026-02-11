<?php
// compra_local_registro_pedidos_guardar.php
// Guardar cantidad de pedido (solo actualiza fecha_hora_reportada si el valor cambió)

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

// Configurar zona horaria de Managua
date_default_timezone_set('America/Managua');

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';
    $id_producto = $_POST['id_producto_presentacion'] ?? '';
    $dia_entrega = $_POST['dia_entrega'] ?? '';
    $cantidad_pedido = $_POST['cantidad_pedido'] ?? 0;

    if (empty($codigo_sucursal) || empty($id_producto) || empty($dia_entrega)) {
        throw new Exception('Datos incompletos');
    }

    // Validar día de entrega (1-7)
    if ($dia_entrega < 1 || $dia_entrega > 7) {
        throw new Exception('Día de entrega inválido');
    }

    // Verificar si existe el registro
    $sql_check = "SELECT id, cantidad_pedido 
                  FROM compra_local_productos_despacho 
                  WHERE id_producto_presentacion = ? 
                  AND codigo_sucursal = ? 
                  AND dia_entrega = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([$id_producto, $codigo_sucursal, $dia_entrega]);
    $registro = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        throw new Exception('No existe configuración para este producto y día');
    }

    // Obtener cantidad anterior
    $cantidad_anterior = $registro['cantidad_pedido'] ?? 0;

    // Solo actualizar fecha_hora_reportada si el valor cambió
    if ($cantidad_anterior != $cantidad_pedido) {
        $sql = "UPDATE compra_local_productos_despacho 
                SET cantidad_pedido = ?, 
                    fecha_hora_reportada = NOW(),
                    usuario_modificacion = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $cantidad_pedido,
            $usuario['CodOperario'],
            $registro['id']
        ]);
    } else {
        // Si no cambió, solo actualizar usuario_modificacion sin cambiar fecha_hora_reportada
        $sql = "UPDATE compra_local_productos_despacho 
                SET usuario_modificacion = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $usuario['CodOperario'],
            $registro['id']
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Pedido guardado correctamente',
        'valor_cambio' => $cantidad_anterior != $cantidad_pedido
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
