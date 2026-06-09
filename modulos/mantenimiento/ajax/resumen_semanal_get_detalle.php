<?php
// ajax/resumen_semanal_get_detalle.php
// Devuelve el detalle completo de visitas, tareas y compras por rango de semana

require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$cargoOperario = $usuario['CodNivelesCargos'];
if (!tienePermiso('agenda_mantenimiento', 'reporte_semanal', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$numero_semana = $_POST['numero_semana'] ?? null;
$anio          = $_POST['anio']          ?? date('Y');

if (!$numero_semana) {
    echo json_encode(['success' => false, 'message' => 'Semana no indicada']);
    exit;
}

try {
    $db = (new Ticket())->getDb()->getConnection();

    // 1. Rango de la semana
    $stmtS = $db->prepare("SELECT fecha_inicio, fecha_fin FROM SemanasSistema WHERE numero_semana = :num AND anio = :anio LIMIT 1");
    $stmtS->execute([':num' => $numero_semana, ':anio' => $anio]);
    $semana = $stmtS->fetch(PDO::FETCH_ASSOC);
    if (!$semana) throw new Exception("Semana #$numero_semana no encontrada");

    $desde = $semana['fecha_inicio'];
    $hasta = $semana['fecha_fin'];

    // 2. Informes del período (solo los finalizados con km completos)
    $stmtI = $db->prepare("
        SELECT i.id, i.fecha, i.km_inicial, i.km_final, i.monto_caja_chica,
               i.km_foto_inicial, i.km_foto_final, i.estado,
               o.CodOperario, o.Nombre, o.Apellido
          FROM mtto_informes_diarios i
          INNER JOIN Operarios o ON i.cod_operario = o.CodOperario
         WHERE i.fecha BETWEEN :desde AND :hasta
           AND i.km_final IS NOT NULL AND i.km_inicial IS NOT NULL
         ORDER BY o.Nombre, o.Apellido, i.fecha ASC
    ");
    $stmtI->execute([':desde' => $desde, ':hasta' => $hasta]);
    $informes = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    if (empty($informes)) {
        echo json_encode(['success' => true, 'informes' => [], 'desde' => $desde, 'hasta' => $hasta]);
        exit;
    }

    $informe_ids = array_column($informes, 'id');
    $placeholders = implode(',', array_fill(0, count($informe_ids), '?'));

    // 3. Visitas de todos esos informes
    $stmtV = $db->prepare("
        SELECT v.id, v.informe_id, v.cod_sucursal, v.hora_llegada, v.hora_salida,
               v.materiales_stock, v.reembolso_id,
               s.nombre AS nombre_sucursal
          FROM mtto_informe_visitas v
          LEFT JOIN sucursales s ON v.cod_sucursal = s.codigo
         WHERE v.informe_id IN ($placeholders)
         ORDER BY v.hora_llegada ASC
    ");
    $stmtV->execute($informe_ids);
    $visitas = $stmtV->fetchAll(PDO::FETCH_ASSOC);

    $visita_ids = array_column($visitas, 'id');

    // 4. Tareas
    $tareas = [];
    if (!empty($visita_ids)) {
        $placeholdersTareas = implode(',', array_fill(0, count($visita_ids), '?'));
        $stmtT = $db->prepare("
            SELECT t.id, t.visita_id, t.ticket_id, t.trabajo_realizado, t.completado_100,
                   (SELECT GROUP_CONCAT(f.foto ORDER BY f.orden SEPARATOR '||') FROM mtto_informe_tareas_fotos f WHERE f.tarea_id = t.id) as fotos
              FROM mtto_informe_tareas t
             WHERE t.visita_id IN ($placeholdersTareas)
             ORDER BY t.id ASC
        ");
        $stmtT->execute($visita_ids);
        $tareas = $stmtT->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. Compras
    $compras = [];
    if (!empty($visita_ids)) {
        $placeholdersCompras = implode(',', array_fill(0, count($visita_ids), '?'));
        $stmtC = $db->prepare("
            SELECT c.id, c.visita_id, c.monto, c.detalle, c.foto_factura
              FROM mtto_informe_compras c
             WHERE c.visita_id IN ($placeholdersCompras)
             ORDER BY c.id ASC
        ");
        $stmtC->execute($visita_ids);
        $compras = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    }

    // 6. Indexar visitas/tareas/compras por ID padre
    $tareasPorVisita   = [];
    foreach ($tareas  as $t) $tareasPorVisita[$t['visita_id']][]  = $t;

    $comprasPorVisita  = [];
    foreach ($compras as $c) $comprasPorVisita[$c['visita_id']][] = $c;

    $visitasPorInforme = [];
    foreach ($visitas as &$v) {
        $v['tareas']  = $tareasPorVisita[$v['id']]  ?? [];
        $v['compras'] = $comprasPorVisita[$v['id']] ?? [];
        $visitasPorInforme[$v['informe_id']][] = $v;
    }
    unset($v);

    // 7. Ensamblar respuesta
    foreach ($informes as &$inf) {
        $inf['visitas'] = $visitasPorInforme[$inf['id']] ?? [];
    }
    unset($inf);

    echo json_encode([
        'success'  => true,
        'informes' => $informes,
        'desde'    => $desde,
        'hasta'    => $hasta,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
