<?php
/**
 * ajax/anulaciones_detalle_pedido.php
 * Devuelve el detalle de un pedido desde VentasGlobalesAccessCSV.
 *
 * GET:
 *   cod_pedido : Número de pedido (requerido)
 *   sucursal   : Código de sucursal (requerido, para filtrar por `local`)
 */
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json; charset=utf-8');

$codPedido = isset($_GET['cod_pedido']) ? (int)$_GET['cod_pedido'] : 0;
$sucursal  = isset($_GET['sucursal'])   ? (int)$_GET['sucursal']   : 0;

if ($codPedido < 1) {
    echo json_encode(['success' => false, 'error' => 'cod_pedido inválido.']);
    exit();
}

global $conn;
$pdo = $conn;

try {
    // Obtener todas las líneas del pedido
    // Filtramos por CodPedido; si sucursal > 0 también filtramos por local
    $params = [':cod' => $codPedido];
    $sqlWhere = 'WHERE CodPedido = :cod';

    // Buscar el nombre de la sucursal desde la tabla Sucursales para filtrar por Sucursal_Nombre
    if ($sucursal > 0) {
        $stmtSuc = $pdo->prepare('SELECT nombre FROM Sucursales WHERE codigo = :suc LIMIT 1');
        $stmtSuc->execute([':suc' => $sucursal]);
        $sucRow = $stmtSuc->fetch(PDO::FETCH_ASSOC);
        if ($sucRow) {
            $sqlWhere .= ' AND Sucursal_Nombre = :suc_nombre';
            $params[':suc_nombre'] = $sucRow['nombre'];
        }
    }

    $stmt = $pdo->prepare(
        "SELECT CodPedido, DBBatidos_Nombre, NombreGrupo, Medida, Cantidad,
                Precio, Precio_Unitario_Sin_Descuento, CodigoPromocion,
                Anulado, MotivoAnulado, Fecha, Hora, HoraCreado,
                aPOS, Caja, Modalidad, Tipo, Delivery_Nombre,
                Motorizado, CodCliente, MontoFactura, Propina,
                Sucursal_Nombre, local, Observaciones
         FROM VentasGlobalesAccessCSV
         $sqlWhere
         ORDER BY DBBatidos_Nombre ASC"
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        echo json_encode(['success' => true, 'items' => [], 'resumen' => []]);
        exit();
    }

    // Resumen del pedido (primera fila con datos de cabecera)
    $resumen = [
        'CodPedido'     => $items[0]['CodPedido'],
        'Fecha'         => $items[0]['Fecha'],
        'Hora'          => $items[0]['Hora'],
        'HoraCreado'    => $items[0]['HoraCreado'],
        'aPOS'          => $items[0]['aPOS'],
        'Caja'          => $items[0]['Caja'],
        'Modalidad'     => $items[0]['Modalidad'],
        'Tipo'          => $items[0]['Tipo'],
        'Delivery_Nombre' => $items[0]['Delivery_Nombre'],
        'Motorizado'    => $items[0]['Motorizado'],
        'CodCliente'    => $items[0]['CodCliente'],
        'MontoFactura'  => $items[0]['MontoFactura'],
        'Propina'       => $items[0]['Propina'],
        'Sucursal_Nombre' => $items[0]['Sucursal_Nombre'],
        'Anulado'       => $items[0]['Anulado'],
        'MotivoAnulado' => $items[0]['MotivoAnulado'],
        'Observaciones' => $items[0]['Observaciones'],
    ];

    echo json_encode([
        'success' => true,
        'items'   => $items,
        'resumen' => $resumen,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
