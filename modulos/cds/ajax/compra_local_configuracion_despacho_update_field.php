<?php
// compra_local_configuracion_despacho_update_field.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $id_producto = $_POST['id_producto_presentacion'] ?? '';
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';
    $campo = $_POST['campo'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $usuario = obtenerUsuarioActual();

    if (empty($id_producto) || empty($codigo_sucursal) || empty($campo)) {
        throw new Exception('Faltan parámetros requeridos');
    }

    // Lista blanca de campos permitidos para evitar SQL Injection
    $campos_permitidos = ['base_consumption', 'lead_time_days', 'shelf_life_days', 'event_factor', 'is_delivery', 'status'];
    if (!in_array($campo, $campos_permitidos)) {
        throw new Exception('Campo no permitido');
    }

    // Campos que se aplican a nivel general del producto (los 7 días)
    $campos_generales = ['lead_time_days', 'shelf_life_days', 'status'];
    $is_general = in_array($campo, $campos_generales);

    // Validar y limpiar valor según el campo
    if (in_array($campo, ['base_consumption', 'event_factor'])) {
        $valor = floatval($valor);
    } else {
        $valor = ($campo == 'status') ? $valor : intval($valor);
    }

    // Preparar el WHERE
    $where_sql = "WHERE id_producto_presentacion = ? AND codigo_sucursal = ?";
    $params = [$valor, $usuario['CodOperario'], $id_producto, $codigo_sucursal];

    // Si no es general, solo actualizamos el día específico
    if (!$is_general) {
        $dia = $_POST['dia_entrega'] ?? '';
        if (empty($dia)) {
            throw new Exception('Día no especificado para campo diario');
        }
        $where_sql .= " AND dia_entrega = ?";
        $params[] = $dia;
    }

    // Actualizar registros
    $sql = "UPDATE compra_local_configuracion_despacho 
            SET $campo = ?, 
                usuario_modificacion = ?, 
                fecha_modificacion = CURRENT_TIMESTAMP 
            $where_sql";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

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
