<?php
// compra_local_registro_pedidos_guardar.php
// Guardar cantidad de pedido con fecha específica

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

// Configurar zona horaria de Managua
date_default_timezone_set('America/Managua');

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';
    $id_producto = $_POST['id_producto_presentacion'] ?? '';
    $fecha_entrega = $_POST['fecha_entrega'] ?? '';
    $cantidad_pedido = $_POST['cantidad_pedido'] ?? 0;

    if (empty($codigo_sucursal) || empty($id_producto) || empty($fecha_entrega)) {
        throw new Exception('Datos incompletos');
    }

    // Validar formato de fecha
    $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_entrega);
    if (!$fecha_obj) {
        throw new Exception('Formato de fecha inválido');
    }

    // Obtener día de la semana de la fecha (1=Lun, 7=Dom)
    $dia_semana = $fecha_obj->format('N');
    if ($dia_semana == 7)
        $dia_semana = 7; // Domingo

    // Verificar que este día esté habilitado en la configuración
    $sql_config = "SELECT id FROM compra_local_configuracion_despacho 
                   WHERE id_producto_presentacion = ? 
                   AND codigo_sucursal = ? 
                   AND dia_entrega = ?
                   AND status = 'activo'";
    $stmt_config = $conn->prepare($sql_config);
    $stmt_config->execute([$id_producto, $codigo_sucursal, $dia_semana]);

    if (!$stmt_config->fetch()) {
        throw new Exception('Este día no está habilitado para pedidos de este producto');
    }

    // Verificar si ya existe un pedido para esta fecha
    $sql_check = "SELECT id, cantidad_pedido 
                  FROM compra_local_pedidos_historico 
                  WHERE id_producto_presentacion = ? 
                  AND codigo_sucursal = ? 
                  AND fecha_entrega = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([$id_producto, $codigo_sucursal, $fecha_entrega]);
    $registro = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($registro) {
        // Actualizar pedido existente
        $cantidad_anterior = $registro['cantidad_pedido'] ?? 0;

        // Solo actualizar fecha_hora_reportada si el valor cambió
        if ($cantidad_anterior != $cantidad_pedido) {
            $sql = "UPDATE compra_local_pedidos_historico 
                    SET cantidad_pedido = ?, 
                        fecha_hora_reportada = NOW(),
                        usuario_registro = ?
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $cantidad_pedido,
                $usuario['CodOperario'],
                $registro['id']
            ]);
        }
    } else {
        // Insertar nuevo pedido
        if ($cantidad_pedido > 0) {
            $sql = "INSERT INTO compra_local_pedidos_historico 
                    (id_producto_presentacion, codigo_sucursal, fecha_entrega, cantidad_pedido, usuario_registro, fecha_hora_reportada)
                    VALUES (?, ?, ?, ?, ?, NOW())";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $id_producto,
                $codigo_sucursal,
                $fecha_entrega,
                $cantidad_pedido,
                $usuario['CodOperario']
            ]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Pedido guardado correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
