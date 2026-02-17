<?php
// compra_local_configuracion_despacho_update_field.php
// Actualiza un campo específico de configuración para un producto y sucursal

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

// Configurar zona horaria de Managua
date_default_timezone_set('America/Managua');

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $id_producto = $_POST['id_producto_presentacion'] ?? '';
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';
    $campo = $_POST['campo'] ?? '';
    $valor = $_POST['valor'] ?? '';

    if (empty($id_producto) || empty($codigo_sucursal) || empty($campo)) {
        throw new Exception('Datos incompletos');
    }

    // Lista blanca de campos permitidos para evitar SQL Injection
    $campos_permitidos = ['base_consumption', 'lead_time_days', 'shelf_life_days', 'event_factor'];
    if (!in_array($campo, $campos_permitidos)) {
        throw new Exception('Campo no permitido');
    }

    // Validar y limpiar valor según el campo
    if (in_array($campo, ['base_consumption', 'event_factor'])) {
        $valor = floatval($valor);
    } else {
        $valor = intval($valor);
    }

    // Actualizar todos los registros de este producto para esta sucursal (todos los días de entrega)
    $sql = "UPDATE compra_local_configuracion_despacho 
            SET $campo = ?, 
                usuario_modificacion = ?, 
                fecha_modificacion = CURRENT_TIMESTAMP 
            WHERE id_producto_presentacion = ? 
            AND codigo_sucursal = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $valor,
        $usuario['CodOperario'],
        $id_producto,
        $codigo_sucursal
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Campo actualizado correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
