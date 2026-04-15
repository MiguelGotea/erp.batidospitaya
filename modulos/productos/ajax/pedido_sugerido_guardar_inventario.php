<?php
/* ============================================================
   AJAX: Guardar inventario actual
   modulos/productos/ajax/pedido_sugerido_guardar_inventario.php
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pedido_sugerido', 'edicion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso para guardar inventario.']);
    exit();
}

try {
    // Parsear JSON del body
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
        echo json_encode(['ok' => false, 'msg' => 'No se recibieron datos.']);
        exit();
    }

    $items = $data['items'];
    $guardados = 0;

    // Preparar INSERT (cada registro es histórico → nuevo INSERT por diseño)
    $stmt = $conn->prepare("
        INSERT INTO inventario
            (cod_sucursal, id_producto_presentacion, cantidad, fecha_inventario)
        VALUES
            (?, ?, ?, ?)
    ");

    $conn->beginTransaction();

    foreach ($items as $item) {
        $codSucursal    = trim($item['cod_sucursal']    ?? '');
        $idPP           = (int)($item['id_producto_presentacion'] ?? 0);
        $cantidad       = (int)($item['cantidad']       ?? 0);
        $fechaInventario = trim($item['fecha_inventario'] ?? date('Y-m-d'));

        if (!$codSucursal || !$idPP) continue;

        // Validar formato de fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInventario)) {
            $fechaInventario = date('Y-m-d');
        }

        $stmt->execute([$codSucursal, $idPP, $cantidad, $fechaInventario]);
        $guardados++;
    }

    $conn->commit();

    echo json_encode([
        'ok'        => true,
        'guardados' => $guardados,
        'msg'       => "Se guardaron {$guardados} registros de inventario."
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar: ' . $e->getMessage()]);
}
