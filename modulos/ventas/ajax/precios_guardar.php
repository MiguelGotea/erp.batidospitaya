<?php
require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    $usuario = obtenerUsuarioActual();
    $idOperario = $usuario['CodOperario'] ?? null;

    $id_producto_presentacion = $_POST['id_producto_presentacion'] ?? null;
    $cod_sucursal = !empty($_POST['cod_sucursal']) ? $_POST['cod_sucursal'] : null;
    $precio = $_POST['precio'] ?? null;
    $fecha_desde = $_POST['fecha_desde'] ?? null;

    if (!$id_producto_presentacion || $precio === null || !$fecha_desde) {
        throw new Exception("Faltan datos obligatorios.");
    }

    $conn->beginTransaction();

    // 1. Encontrar si hay un precio vigente anterior (del mismo tipo: global o sucursal)
    // y cerrar su vigencia al día anterior de la nueva fecha_desde
    
    // Calculamos el día anterior
    $fecha_hasta_nuevo = date('Y-m-d', strtotime($fecha_desde . ' - 1 day'));

    if ($cod_sucursal === null) {
        $sqlCerrar = "UPDATE pos_ventas_precios_producto 
                      SET fecha_hasta = :fecha_hasta 
                      WHERE id_producto_presentacion = :id_producto 
                        AND cod_sucursal IS NULL 
                        AND (fecha_hasta IS NULL OR fecha_hasta > :fecha_desde_nueva)";
    } else {
        $sqlCerrar = "UPDATE pos_ventas_precios_producto 
                      SET fecha_hasta = :fecha_hasta 
                      WHERE id_producto_presentacion = :id_producto 
                        AND cod_sucursal = :cod_sucursal 
                        AND (fecha_hasta IS NULL OR fecha_hasta > :fecha_desde_nueva)";
    }

    $stmtCerrar = $conn->prepare($sqlCerrar);
    $stmtCerrar->bindValue(':fecha_hasta', $fecha_hasta_nuevo);
    $stmtCerrar->bindValue(':id_producto', $id_producto_presentacion);
    $stmtCerrar->bindValue(':fecha_desde_nueva', $fecha_desde);
    
    if ($cod_sucursal !== null) {
        $stmtCerrar->bindValue(':cod_sucursal', $cod_sucursal);
    }
    
    $stmtCerrar->execute();

    // 2. Insertar el nuevo precio
    $sqlInsert = "INSERT INTO pos_ventas_precios_producto 
                  (id_producto_presentacion, cod_sucursal, precio, fecha_desde, registrado_por) 
                  VALUES (:id_producto, :cod_sucursal, :precio, :fecha_desde, :registrado_por)";
    
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bindValue(':id_producto', $id_producto_presentacion);
    $stmtInsert->bindValue(':cod_sucursal', $cod_sucursal);
    $stmtInsert->bindValue(':precio', $precio);
    $stmtInsert->bindValue(':fecha_desde', $fecha_desde);
    $stmtInsert->bindValue(':registrado_por', $idOperario);
    
    $stmtInsert->execute();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Precio guardado exitosamente.']);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
