<?php
/**
 * guardar_caja_inicial.php
 * AJAX endpoint — Guarda un registro de Caja Inicial en BD
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

// Solo POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

// Leer JSON del body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['ok' => false, 'mensaje' => 'Datos inválidos.']);
    exit;
}

// Validar campos obligatorios
$campos = ['fecha', 'sucursal_id', 'tipo_cambio_usado', 'total_cordobas', 'total_dolares',
           'total_dolares_en_cordobas', 'total_efectivo_global', 'detalles'];
foreach ($campos as $c) {
    if (!isset($data[$c]) || (empty($data[$c]) && $data[$c] !== 0)) {
        echo json_encode(['ok' => false, 'mensaje' => "Campo faltante o vacío: $c"]);
        exit;
    }
}

$fecha      = $data['fecha'];
$sucursalId = $data['sucursal_id'];

// Obtener usuario de sesión si está disponible
$codUsuario = null;
try {
    $usuario = obtenerUsuarioActual();
    $codUsuario = $usuario['CodOperario'] ?? ($usuario['id'] ?? null);
} catch (Exception $e) {
    // Si la función no existe o falla, dejamos null
}

try {
    // 0. Verificar si ya existe un conteo para esta sucursal y fecha
    $stmtCheck = $conn->prepare("SELECT id FROM pos_caja_inicial WHERE fecha = ? AND sucursal_id = ?");
    $stmtCheck->execute([$fecha, $sucursalId]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['ok' => false, 'mensaje' => "Ya existe un registro de Caja Inicial para esta sucursal en la fecha seleccionada ($fecha)."]);
        exit;
    }

    $conn->beginTransaction();

    // 1. Insertar registro maestro
    $stmtMaster = $conn->prepare("
        INSERT INTO pos_caja_inicial
            (fecha, sucursal_id, tipo_cambio_usado, total_cordobas, total_dolares,
             total_dolares_en_cordobas, total_efectivo_global, cod_usuario)
        VALUES
            (:fecha, :sucursal, :tc, :total_nio, :total_usd,
             :total_usd_nio, :total_global, :cod_usuario)
    ");
    $stmtMaster->execute([
        ':fecha'        => $fecha,
        ':sucursal'     => $sucursalId,
        ':tc'           => round((float)$data['tipo_cambio_usado'], 4),
        ':total_nio'    => round((float)$data['total_cordobas'], 2),
        ':total_usd'    => round((float)$data['total_dolares'], 2),
        ':total_usd_nio'=> round((float)$data['total_dolares_en_cordobas'], 2),
        ':total_global' => round((float)$data['total_efectivo_global'], 2),
        ':cod_usuario'  => $codUsuario,
    ]);

    $cajaId = (int)$conn->lastInsertId();

    // 2. Insertar detalles por denominación
    $stmtDetalle = $conn->prepare("
        INSERT INTO pos_caja_inicial_detalle
            (caja_inicial_id, moneda, denominacion, cantidad, total)
        VALUES
            (:caja_id, :moneda, :denom, :cantidad, :total)
    ");

    foreach ($data['detalles'] as $det) {
        $stmtDetalle->execute([
            ':caja_id'  => $cajaId,
            ':moneda'   => $det['moneda'],
            ':denom'    => round((float)$det['denominacion'], 2),
            ':cantidad' => (int)$det['cantidad'],
            ':total'    => round((float)$det['total'], 2),
        ]);
    }

    $conn->commit();

    echo json_encode(['ok' => true, 'caja_inicial_id' => $cajaId]);

} catch (PDOException $e) {
    $conn->rollBack();
    error_log('[guardar_caja_inicial] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'mensaje' => 'Error en base de datos. Intente nuevamente.']);
}
