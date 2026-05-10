<?php
// facturas_guardar.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    // ---- Datos cabecera ----
    $numeroFactura = isset($_POST['numero_factura']) ? trim($_POST['numero_factura']) : '';
    $fecha         = isset($_POST['fecha'])          ? trim($_POST['fecha'])          : '';
    $idProveedor   = isset($_POST['id_proveedor'])   ? (int)$_POST['id_proveedor']   : 0;
    $notas         = isset($_POST['notas'])          ? trim($_POST['notas'])          : null;
    $detalle       = isset($_POST['detalle'])        ? json_decode($_POST['detalle'], true) : [];

    $usuarioId = $_SESSION['usuario_id'];

    // ---- Validaciones ----
    if (empty($numeroFactura)) throw new Exception('El número de factura es obligatorio.');
    if (empty($fecha))         throw new Exception('La fecha es obligatoria.');
    if ($idProveedor <= 0)     throw new Exception('Debe seleccionar un proveedor.');
    if (empty($detalle) || !is_array($detalle) || count($detalle) === 0) {
        throw new Exception('Debe agregar al menos un producto a la factura.');
    }
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error procesando el detalle de productos.');
    }

    // ---- Validar y calcular total ----
    $totalFactura = 0;
    foreach ($detalle as $i => $item) {
        $cantidad      = isset($item['cantidad'])      ? (float)$item['cantidad']      : 0;
        $costoTotalIva = isset($item['costo_total_iva']) ? (float)$item['costo_total_iva'] : 0;
        $idPresentacion = isset($item['id_presentacion']) ? (int)$item['id_presentacion'] : 0;

        if ($idPresentacion <= 0) throw new Exception("Producto inválido en la línea ".($i+1).".");
        if ($cantidad <= 0)       throw new Exception("La cantidad debe ser mayor a 0 en la línea ".($i+1).".");
        if ($costoTotalIva < 0)   throw new Exception("El costo no puede ser negativo en la línea ".($i+1).".");

        $totalFactura += $costoTotalIva;
    }

    $conn->beginTransaction();

    // ---- Insertar cabecera ----
    $sqlHeader = "INSERT INTO pos_facturas
                  (numero_factura, fecha, id_proveedor, total_factura, notas, estado, registrado_por)
                  VALUES (:numero, :fecha, :proveedor, :total, :notas, 'activa', :usuario)";
    $stmtHeader = $conn->prepare($sqlHeader);
    $stmtHeader->execute([
        ':numero'    => $numeroFactura,
        ':fecha'     => $fecha,
        ':proveedor' => $idProveedor,
        ':total'     => round($totalFactura, 2),
        ':notas'     => $notas ?: null,
        ':usuario'   => $usuarioId
    ]);
    $idFactura = $conn->lastInsertId();

    // Si el número era AUTO, actualizar con el ID autoincremental
    if ($numeroFactura === 'AUTO') {
        $stmtUpdate = $conn->prepare("UPDATE pos_facturas SET numero_factura = :num WHERE id = :id");
        $stmtUpdate->execute([':num' => $idFactura, ':id' => $idFactura]);
    }

    // ---- Insertar detalle ----
    $sqlDetalle = "INSERT INTO pos_facturas_detalle
                   (id_factura, id_presentacion, cantidad, costo_total_iva, costo_unitario)
                   VALUES (:id_factura, :id_pres, :cant, :total_iva, :unitario)";
    $stmtDetalle = $conn->prepare($sqlDetalle);

    foreach ($detalle as $item) {
        $cantidad      = (float)$item['cantidad'];
        $costoTotalIva = (float)$item['costo_total_iva'];
        $costoUnitario = ($cantidad > 0) ? round($costoTotalIva / $cantidad, 4) : 0;

        $stmtDetalle->execute([
            ':id_factura' => $idFactura,
            ':id_pres'    => (int)$item['id_presentacion'],
            ':cant'       => $cantidad,
            ':total_iva'  => $costoTotalIva,
            ':unitario'   => $costoUnitario
        ]);
    }

    $conn->commit();

    echo json_encode([
        'success'    => true,
        'message'    => 'Factura guardada exitosamente.',
        'id_factura' => $idFactura
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
