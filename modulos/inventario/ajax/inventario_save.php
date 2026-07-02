<?php
/* ============================================================
   AJAX: Guardar Inventario Semanal
   Ruta: modulos/inventario/ajax/inventario_save.php
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargo = $usuario['CodNivelesCargos'];
$idOperario = $usuario['CodOperario'];

// Permisos: 27, 16, 55 para crear/editar
if (!tienePermiso('inventario_semanal', 'edicion', $cargo)) {
    echo json_encode(['ok' => false, 'msg' => 'Sin permisos para realizar cambios.']);
    exit();
}

try {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!$data || !isset($data['items']) || empty($data['items'])) {
        echo json_encode(['ok' => false, 'msg' => 'No hay datos para guardar.']);
        exit();
    }

    $codSucursal = $data['cod_sucursal'];
    $numSemanaInv = isset($data['semana_inv']) ? (int)$data['semana_inv'] : 0;
    $items = $data['items'];

    if (!$codSucursal || !$numSemanaInv) throw new Exception("Datos incompletos (sucursal o semana).");

    // 1. Obtener rango de la semana para limpiar registros previos
    $stmtS = $conn->prepare("SELECT fecha_inicio, fecha_fin FROM SemanasSistema WHERE numero_semana = ?");
    $stmtS->execute([$numSemanaInv]);
    $sem = $stmtS->fetch();
    if (!$sem) throw new Exception("Semana de inventario inválida.");
    
    // Determinar la fecha para el registro: hoy (si cae dentro de la semana) o el fin de semana
    $hoy = date('Y-m-d');
    if ($hoy >= $sem['fecha_inicio'] && $hoy <= $sem['fecha_fin']) {
        $fechaParaRegistro = $hoy;
    } else {
        $fechaParaRegistro = $sem['fecha_fin'];
    }

    $conn->beginTransaction();

    // Guardar usando ON DUPLICATE KEY UPDATE por día
    $stmt = $conn->prepare("
        INSERT INTO inventario_semanal (
            cod_sucursal, id_producto_presentacion, cantidad_unidades, 
            cantidad_presentacion, fecha_inventario, creado_por
        ) VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            cantidad_unidades = VALUES(cantidad_unidades),
            cantidad_presentacion = VALUES(cantidad_presentacion),
            modificado_por = ?
    ");

    $count = 0;
    foreach ($items as $it) {
        $stmt->execute([
            $codSucursal,
            $it['id_producto_presentacion'],
            $it['cantidad_unidades'] ?: 0,
            $it['cantidad_presentacion'] ?: 0,
            $fechaParaRegistro,
            $idOperario,
            $idOperario // para modificado_por
        ]);
        $count++;
    }

    $conn->commit();
    echo json_encode(['ok' => true, 'msg' => "Se guardaron $count registros para la semana $numSemanaInv."]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar: ' . $e->getMessage()]);
}
