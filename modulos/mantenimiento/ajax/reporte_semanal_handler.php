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
        case 'get_semanas':
            $anio = $_POST['anio'] ?? date('Y');
            $sql = "SELECT id, numero_semana, fecha_inicio, fecha_fin 
                    FROM SemanasSistema 
                    WHERE anio = :anio 
                    ORDER BY numero_semana DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([':anio' => $anio]);
            echo json_encode(['success' => true, 'semanas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_datos_semanales':
            $semana_id = $_POST['semana_id'] ?? null;
            if (!$semana_id) throw new Exception("ID de semana no proporcionado");

            // 1. Obtener rango de la semana
            $sqlSemana = "SELECT fecha_inicio, fecha_fin FROM SemanasSistema WHERE id = :id";
            $stmtS = $db->prepare($sqlSemana);
            $stmtS->execute([':id' => $semana_id]);
            $semana = $stmtS->fetch(PDO::FETCH_ASSOC);
            if (!$semana) throw new Exception("Semana no encontrada");

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
            $semana_id = $_POST['semana_id'] ?? null;
            $costo_km = $_POST['costo_km'] ?? 0;
            $operario_id = $_POST['operario_id'] ?? null; // Si se quiere para uno solo

            if (!$semana_id) throw new Exception("ID de semana no proporcionado");

            $sqlSemana = "SELECT fecha_inicio, fecha_fin FROM SemanasSistema WHERE id = :id";
            $stmtS = $db->prepare($sqlSemana);
            $stmtS->execute([':id' => $semana_id]);
            $semana = $stmtS->fetch(PDO::FETCH_ASSOC);
            
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
