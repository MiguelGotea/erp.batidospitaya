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
    $usuario = obtenerUsuarioActual();

    if ((empty($id) && empty($id_producto)) || empty($codigo_sucursal) || empty($campo)) {
        throw new Exception('Faltan parámetros requeridos');
    }

    // Lista blanca de campos permitidos
    $campos_permitidos = ['is_delivery', 'base_consumption', 'lead_time_days', 'shelf_life_days', 'status'];
    if (!in_array($campo, $campos_permitidos)) {
        throw new Exception('Campo no permitido');
    }

    // Si es is_delivery, actualizamos el registro específico por ID
    if ($campo == 'is_delivery' && !empty($id)) {
        $sql = "UPDATE compra_local_configuracion_despacho 
                SET is_delivery = ?, 
                    usuario_modificacion = ?, 
                    fecha_modificacion = CURRENT_TIMESTAMP 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([intval($valor), $usuario['CodOperario'], $id]);
    } else {
        // Para los demás campos (consumo, contingencia, etc), actualizamos todos los días del producto
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
