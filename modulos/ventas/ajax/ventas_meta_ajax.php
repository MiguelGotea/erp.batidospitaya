<?php
// ventas_meta_ajax.php

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

$action = $_POST['action'] ?? '';

if ($action === 'get_data') {
    if (!tienePermiso('ventas_meta', 'vista', $cargoOperario)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sin acceso']);
        exit;
    }

    $anio = intval($_POST['anio']);

    try {
        // 1. Obtener sucursales
        $stmtSuc = $conn->query("SELECT codigo, nombre FROM sucursales WHERE sucursal = 1 AND activa = 1 ORDER BY nombre ASC");
        $sucursales = $stmtSuc->fetchAll(PDO::FETCH_ASSOC);

        // 2. Obtener metas registradas para el año
        $stmtMetas = $conn->prepare("
            SELECT 
                cod_sucursal, 
                MONTH(fecha) as mes, 
                MAX(meta) as meta_diaria
            FROM ventas_meta 
            WHERE YEAR(fecha) = ?
            GROUP BY cod_sucursal, MONTH(fecha)
        ");
        $stmtMetas->execute([$anio]);
        $metasRaw = $stmtMetas->fetchAll(PDO::FETCH_ASSOC);

        $metas = [];
        foreach ($metasRaw as $m) {
            // REQUERIMIENTO: Devolver el valor tal cual está en la base de datos (valor bruto)
            $metaMensual = round($m['meta_diaria'], 2);
            $metas[$m['cod_sucursal']][$m['mes']] = $metaMensual;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'sucursales' => $sucursales,
            'metas' => $metas
        ]);

    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'save_meta') {
    if (!tienePermiso('ventas_meta', 'edicion', $cargoOperario)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sin permiso de edición']);
        exit;
    }

    $codSucursal = $_POST['cod_sucursal']; // Es el dato de la columna codigo (varchar)
    $mes = intval($_POST['mes']);
    $anio = intval($_POST['anio']);
    $metaValor = round(floatval($_POST['valor']), 2); // REQUERIMIENTO: Valor bruto sin división

    $diasMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
    $metaDiaria = $metaValor; // REQUERIMIENTO: Almacenar el mismo valor ingresado sin conversiones

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("
            INSERT INTO ventas_meta (cod_sucursal, fecha, meta) 
            VALUES (:suc, :fecha, :meta)
            ON DUPLICATE KEY UPDATE meta = VALUES(meta)
        ");

        for ($dia = 1; $dia <= $diasMes; $dia++) {
            $fecha = sprintf("%04d-%02d-%02d", $anio, $mes, $dia);
            $stmt->execute([
                ':suc' => $codSucursal,
                ':fecha' => $fecha,
                ':meta' => $metaDiaria
            ]);
        }

        $conn->commit();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        if ($conn->inTransaction())
            $conn->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
