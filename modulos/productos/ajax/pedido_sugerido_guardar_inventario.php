<?php
/* ============================================================
   AJAX: Guardar inventario actual
   modulos/productos/ajax/pedido_sugerido_guardar_inventario.php
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
if (!tienePermiso('pedido_sugerido', 'edicion', $usuario['CodNivelesCargos'])) {
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso para guardar.']);
    exit();
}

try {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!$data || !isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
        echo json_encode(['ok' => false, 'msg' => 'Error: El JSON enviado está vacío o es inválido en la función GUARDAR.']);
        exit();
    }

    $items = $data['items'];
    $st = $conn->prepare("INSERT INTO inventario (cod_sucursal, id_producto_presentacion, cantidad, fecha_inventario) VALUES (?, ?, ?, ?)");
    $conn->beginTransaction();
    $c = 0;
    foreach ($items as $it) {
        $st->execute([$it['cod_sucursal'], $it['id_producto_presentacion'], $it['cantidad'], $it['fecha_inventario']]);
        $c++;
    }
    $conn->commit();
    echo json_encode(['ok' => true, 'guardados' => $c, 'msg' => 'Guardado exitoso.']);
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Error en guardado: ' . $e->getMessage()]);
}
