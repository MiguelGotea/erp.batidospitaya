<?php
// ajax/reporte_semanal_handler.php
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
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para esta acción']);
    exit;
}

$action = $_POST['action'] ?? '';
$db = (new Ticket())->getDb()->getConnection();

try {
    switch ($action) {
        case 'get_current_week':
            $sql = "SELECT numero_semana FROM SemanasSistema 
                    WHERE :hoy BETWEEN fecha_inicio AND fecha_fin 
                    AND anio = :anio LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':hoy' => date('Y-m-d'), ':anio' => date('Y')]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'numero_semana' => $res ? $res['numero_semana'] : null]);
            break;

        case 'get_datos_semanales':
            $numero_semana = $_POST['numero_semana'] ?? null;
            $anio = $_POST['anio'] ?? date('Y');
            
            if (!$numero_semana) throw new Exception("Número de semana no proporcionado");

            // 1. Obtener rango de la semana y ID por su número
            $sqlSemana = "SELECT id, fecha_inicio, fecha_fin FROM SemanasSistema WHERE numero_semana = :num AND anio = :anio";
            $stmtS = $db->prepare($sqlSemana);
            $stmtS->execute([':num' => $numero_semana, ':anio' => $anio]);
            $semana = $stmtS->fetch(PDO::FETCH_ASSOC);
            if (!$semana) throw new Exception("Semana #$numero_semana no encontrada para el año $anio");

            $semana_id = $semana['id'];
            $fecha_inicio = $semana['fecha_inicio'];
            $fecha_fin = $semana['fecha_fin'];

            // 2. Obtener resumen por operario
            // Nota: km_consumido = km_final - km_inicial
            // Tomamos el costo_km del primer informe encontrado o 0
            $sql = "SELECT o.CodOperario, o.Nombre, o.Apellido, 
                           SUM(i.km_final - i.km_inicial) as km_total,
                           MAX(i.costo_km) as costo_km_guardado,
                           COUNT(i.id) as dias_con_informe
                    FROM Operarios o
                    INNER JOIN mtto_informes_diarios i ON o.CodOperario = i.cod_operario
                    WHERE i.fecha BETWEEN :desde AND :hasta
                    AND i.km_final IS NOT NULL AND i.km_inicial IS NOT NULL
                    GROUP BY o.CodOperario
                    ORDER BY o.Nombre, o.Apellido";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':desde' => $fecha_inicio, ':hasta' => $fecha_fin]);
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true, 
                'datos' => $datos,
                'rango' => ['desde' => $fecha_inicio, 'hasta' => $fecha_fin]
            ]);
            break;

        case 'guardar_costo_km':
            $numero_semana = $_POST['numero_semana'] ?? null;
            $anio = $_POST['anio'] ?? date('Y');
            $costo_km = $_POST['costo_km'] ?? 0;
            $operario_id = $_POST['operario_id'] ?? null;

            if (!$numero_semana) throw new Exception("Número de semana no proporcionado");

            $sqlSemana = "SELECT fecha_inicio, fecha_fin FROM SemanasSistema WHERE numero_semana = :num AND anio = :anio";
            $stmtS = $db->prepare($sqlSemana);
            $stmtS->execute([':num' => $numero_semana, ':anio' => $anio]);
            $semana = $stmtS->fetch(PDO::FETCH_ASSOC);
            if (!$semana) throw new Exception("Semana no encontrada");
            
            $where = "fecha BETWEEN :desde AND :hasta";
            $params = [
                ':costo' => $costo_km,
                ':desde' => $semana['fecha_inicio'],
                ':hasta' => $semana['fecha_fin']
            ];

            if ($operario_id) {
                $where .= " AND cod_operario = :op_id";
                $params[':op_id'] = $operario_id;
            }

            $sql = "UPDATE mtto_informes_diarios SET costo_km = :costo WHERE $where";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => 'Costo actualizado correctamente']);
            break;

        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
