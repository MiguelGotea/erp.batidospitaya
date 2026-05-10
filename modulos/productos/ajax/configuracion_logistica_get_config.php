<?php
// ajax/configuracion_logistica_get_config.php
// Retorna la configuración logística completa de una sucursal:
//   - dataSuc: { dias_stock_minimo, capacidad_congelados, meta de auditoría }
//   - dataProds: { 'A': { dias_ciclo, dias_desfase, ... }, 'B': { ... }, ... }

require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

try {
    $codigo_sucursal = trim($_POST['codigo_sucursal'] ?? '');

    if (empty($codigo_sucursal)) {
        throw new Exception('Código de sucursal requerido.');
    }

    // --- 1. Encabezado de sucursal ---
    $sqlSuc = "
        SELECT
            cls.dias_stock_minimo,
            cls.capacidad_congelados,
            cls.fecha_creacion,
            cls.fecha_actualizacion,
            CONCAT(oc.Nombre, ' ', oc.Apellido) AS creado_por_nombre,
            CONCAT(om.Nombre, ' ', om.Apellido) AS modificado_por_nombre
        FROM configuracion_logistica_sucursal cls
        LEFT JOIN Operarios oc ON cls.creado_por   = oc.CodOperario
        LEFT JOIN Operarios om ON cls.modificado_por = om.CodOperario
        WHERE cls.cod_sucursal = ?
        LIMIT 1
    ";
    $stmtSuc = $conn->prepare($sqlSuc);
    $stmtSuc->execute([$codigo_sucursal]);
    $dataSuc = $stmtSuc->fetch(PDO::FETCH_ASSOC) ?: (object)[];

    // --- 2. Configuración por categoría de insumo ---
    $sqlProd = "
        SELECT
            clp.codigo_insumo,
            clp.dias_ciclo,
            clp.dias_desfase,
            clp.dias_abastecimiento_despacho,
            clp.ajuste_demanda,
            clp.fecha_creacion,
            clp.fecha_actualizacion,
            CONCAT(oc.Nombre, ' ', oc.Apellido) AS creado_por_nombre,
            CONCAT(om.Nombre, ' ', om.Apellido) AS modificado_por_nombre
        FROM configuracion_logistica_producto clp
        LEFT JOIN Operarios oc ON clp.creado_por    = oc.CodOperario
        LEFT JOIN Operarios om ON clp.modificado_por = om.CodOperario
        WHERE clp.cod_sucursal = ?
        ORDER BY clp.codigo_insumo ASC
    ";
    $stmtProd = $conn->prepare($sqlProd);
    $stmtProd->execute([$codigo_sucursal]);
    $filas = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

    // Indexar por código de insumo para acceso directo desde JS
    $dataProds = [];
    foreach ($filas as $fila) {
        $dataProds[$fila['codigo_insumo']] = $fila;
    }

    echo json_encode([
        'success' => true,
        'data'    => [
            'sucursal' => $dataSuc  ?: [],
            'productos' => $dataProds
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
