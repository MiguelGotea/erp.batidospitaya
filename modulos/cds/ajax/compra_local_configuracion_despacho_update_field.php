<?php
// compra_local_configuracion_despacho_update_field.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $id = $_POST['id'] ?? '';
    $id_producto = $_POST['id_producto_presentacion'] ?? '';
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';
    $campo = $_POST['campo'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $dia_entrega = $_POST['dia_entrega'] ?? '';
    $usuario = obtenerUsuarioActual();

    if ((empty($id) && empty($id_producto)) || empty($codigo_sucursal) || empty($campo)) {
        throw new Exception('Faltan parámetros requeridos');
    }

    // Lista blanca de campos permitidos
    $campos_permitidos = ['is_delivery', 'base_consumption', 'event_factor', 'lead_time_days', 'shelf_life_days', 'status', 'pedido_minimo'];
    if (!in_array($campo, $campos_permitidos)) {
        throw new Exception('Campo no permitido');
    }

    // Campos que se pueden actualizar por día (ID específico o por día)
    $campos_diarios = ['is_delivery', 'base_consumption', 'event_factor'];

    if (in_array($campo, $campos_diarios)) {
        if (!empty($id)) {
            // Actualizamos el registro específico por ID
            $sql = "UPDATE compra_local_configuracion_despacho 
                    SET $campo = ?, 
                        usuario_modificacion = ?, 
                        fecha_modificacion = CURRENT_TIMESTAMP 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$valor, $usuario['CodOperario'], $id]);
        } elseif (!empty($dia_entrega)) {
            // Actualizamos el registro por Producto, Sucursal y Día
            $sql = "UPDATE compra_local_configuracion_despacho 
                    SET $campo = ?, 
                        usuario_modificacion = ?, 
                        fecha_modificacion = CURRENT_TIMESTAMP 
                    WHERE id_producto_presentacion = ? 
                    AND codigo_sucursal = ?
                    AND dia_entrega = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$valor, $usuario['CodOperario'], $id_producto, $codigo_sucursal, $dia_entrega]);
        } else {
            throw new Exception('Se requiere ID o Día de Entrega para este campo');
        }
    } else {
        // Para los demás campos (lead_time, shelf_life, status, pedido_minimo), actualizamos todos los días del producto
        $sql = "UPDATE compra_local_configuracion_despacho 
                SET $campo = ?, 
                    usuario_modificacion = ?, 
                    fecha_modificacion = CURRENT_TIMESTAMP 
                WHERE id_producto_presentacion = ? 
                AND codigo_sucursal = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$valor, $usuario['CodOperario'], $id_producto, $codigo_sucursal]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Configuración actualizada'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
