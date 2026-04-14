<?php
// compra_local_configuracion_despacho_guardar.php
// Guardar configuración de día de entrega

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

// Configurar zona horaria de Managua
date_default_timezone_set('America/Managua');

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $id_producto = $_POST['id_producto_presentacion'] ?? '';
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';
    $crear_nuevo = isset($_POST['crear_nuevo']) && $_POST['crear_nuevo'] === 'true';

    if (empty($id_producto) || empty($codigo_sucursal)) {
        throw new Exception('Datos incompletos');
    }

    if ($crear_nuevo) {
        // Al agregar un producto nuevo, crear los 7 registros de la semana (inactivos)
        // Verificar primero si ya existe algún registro para este producto en esta sucursal
        $sql_check = "SELECT COUNT(*) as total FROM compra_local_configuracion_despacho 
                      WHERE id_producto_presentacion = ? 
                      AND codigo_sucursal = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([$id_producto, $codigo_sucursal]);
        $row = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($row['total'] > 0) {
            throw new Exception('Este producto ya está configurado para esta sucursal');
        }

        // Insertar los 7 días con status inactivo e is_delivery = 0
        $sql = "INSERT INTO compra_local_configuracion_despacho 
                (id_producto_presentacion, codigo_sucursal, dia_entrega, status, is_delivery, pedido_minimo, usuario_creacion)
                VALUES (?, ?, ?, 'inactivo', 0, 1, ?)";
        $stmt = $conn->prepare($sql);

        for ($dia = 1; $dia <= 7; $dia++) {
            $stmt->execute([$id_producto, $codigo_sucursal, $dia, $usuario['CodOperario']]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Producto agregado con los 7 días de la semana configurados'
        ]);

    } else {
        // Modo individual: agregar un día específico
        $dia_entrega = $_POST['dia_entrega'] ?? '';

        if (empty($dia_entrega)) {
            throw new Exception('Datos incompletos');
        }

        // Validar día de entrega (1-7)
        if ($dia_entrega < 1 || $dia_entrega > 7) {
            throw new Exception('Día de entrega inválido');
        }

        // Verificar si ya existe este registro
        $sql_check = "SELECT id FROM compra_local_configuracion_despacho 
                      WHERE id_producto_presentacion = ? 
                      AND codigo_sucursal = ? 
                      AND dia_entrega = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([$id_producto, $codigo_sucursal, $dia_entrega]);

        if ($stmt_check->fetch()) {
            throw new Exception('Este día ya está configurado para este producto');
        }

        // Insertar nuevo registro
        $sql = "INSERT INTO compra_local_configuracion_despacho 
                (id_producto_presentacion, codigo_sucursal, dia_entrega, status, pedido_minimo, usuario_creacion)
                VALUES (?, ?, ?, 'inactivo', 1, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $id_producto,
            $codigo_sucursal,
            $dia_entrega,
            $usuario['CodOperario']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Configuración guardada correctamente'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
