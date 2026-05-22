<?php
// ajax/historial_informes_get_data.php
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
$puedeVerTodos = tienePermiso('agenda_mantenimiento', 'todos_colaboradores', $cargoOperario);
$puedeGenerarReembolso = tienePermiso('agenda_mantenimiento', 'generar_reembolso', $cargoOperario);

$pagina = isset($_POST['pagina']) ? intval($_POST['pagina']) : 1;
$registros_por_pagina = isset($_POST['registros_por_pagina']) ? intval($_POST['registros_por_pagina']) : 25;
$filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
$orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];

$offset = ($pagina - 1) * $registros_por_pagina;

$ticketModel = new Ticket();

try {
    $where_conditions = [];
    $params = [];

    // Filtro de visibilidad obligatorio si no es admin
    if (!$puedeVerTodos && !$puedeGenerarReembolso) {
        $where_conditions[] = "i.cod_operario = :user_id";
        $params[':user_id'] = $usuario['CodOperario'];
    }

    // Aplicar filtros dinámicos
    foreach ($filtros as $columna => $valor) {
        if (is_array($valor)) {
            if (isset($valor['desde']) || isset($valor['hasta'])) {
                // Rango de fechas
                if (!empty($valor['desde']) && !empty($valor['hasta'])) {
                    $where_conditions[] = "i.fecha BETWEEN :desde AND :hasta";
                    $params[':desde'] = $valor['desde'];
                    $params[':hasta'] = $valor['hasta'];
                } elseif (!empty($valor['desde'])) {
                    $where_conditions[] = "i.fecha >= :desde";
                    $params[':desde'] = $valor['desde'];
                } elseif (!empty($valor['hasta'])) {
                    $where_conditions[] = "i.fecha <= :hasta";
                    $params[':hasta'] = $valor['hasta'];
                }
            } else {
                // Lista de valores
                $placeholders = [];
                foreach ($valor as $idx => $v) {
                    $pName = ":" . $columna . "_" . $idx;
                    $placeholders[] = $pName;
                    $params[$pName] = $v;
                }
                
                if ($columna === 'Nombre') {
                    $where_conditions[] = "i.cod_operario IN (" . implode(',', $placeholders) . ")";
                } elseif ($columna === 'estado') {
                    $where_conditions[] = "i.estado IN (" . implode(',', $placeholders) . ")";
                }
            }
        } else {
            // Texto libre (no suele haber en este dashboard pero por estándar)
            if ($columna === 'fecha') {
                $where_conditions[] = "i.fecha LIKE :val";
                $params[':val'] = "%$valor%";
            }
        }
    }

    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Ordenamiento
    $order_sql = "ORDER BY i.fecha DESC, i.created_at DESC";
    if ($orden['columna']) {
        $dir = $orden['direccion'] === 'desc' ? 'DESC' : 'ASC';
        if ($orden['columna'] === 'Nombre') {
            $order_sql = "ORDER BY o.Nombre $dir, o.Apellido $dir";
        } elseif (in_array($orden['columna'], ['fecha', 'km_inicial', 'km_final', 'monto_caja_chica', 'estado'])) {
            $order_sql = "ORDER BY i.{$orden['columna']} $dir";
        }
    }

    // Consulta principal
    $sql = "SELECT i.*, o.Nombre, o.Apellido,
            (SELECT GROUP_CONCAT(DISTINCT s.nombre SEPARATOR ', ') 
             FROM mtto_informe_visitas v 
             JOIN sucursales s ON v.cod_sucursal = s.codigo 
             WHERE v.informe_id = i.id) as sucursales_list,
            (SELECT COUNT(*) 
             FROM mtto_informe_compras c 
             JOIN mtto_informe_visitas v ON c.visita_id = v.id 
             WHERE v.informe_id = i.id) as total_compras,
            (SELECT COALESCE(SUM(c.monto), 0)
             FROM mtto_informe_compras c 
             JOIN mtto_informe_visitas v ON c.visita_id = v.id 
             WHERE v.informe_id = i.id) as total_gastado,
            (SELECT COUNT(*) 
             FROM mtto_informe_visitas v 
             WHERE v.informe_id = i.id 
             AND v.reembolso_id IS NULL 
             AND (SELECT COUNT(*) FROM mtto_informe_compras WHERE visita_id = v.id) > 0) as compras_sin_reembolso
            FROM mtto_informes_diarios i
            LEFT JOIN Operarios o ON i.cod_operario = o.CodOperario
            $where_sql
            $order_sql
            LIMIT :limit OFFSET :offset";
    
    $stmt = $ticketModel->getDb()->getConnection()->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conteo total
    $sql_count = "SELECT COUNT(*) as total FROM mtto_informes_diarios i 
                  LEFT JOIN Operarios o ON i.cod_operario = o.CodOperario $where_sql";
    $stmt_count = $ticketModel->getDb()->getConnection()->prepare($sql_count);
    foreach ($params as $key => $val) {
        $stmt_count->bindValue($key, $val);
    }
    $stmt_count->execute();
    $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'success' => true,
        'datos' => $datos,
        'total_registros' => $total,
        'puedeVerTodos' => $puedeVerTodos,
        'puedeGenerarReembolso' => $puedeGenerarReembolso
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
