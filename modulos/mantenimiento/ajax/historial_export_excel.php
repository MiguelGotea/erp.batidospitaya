<?php
// ajax/historial_export_excel.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../../core/auth/auth.php';
require_once __DIR__ . '/../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Solo usuarios con vista_todas_sucursales pueden descargar el informe global
if (!tienePermiso('historial_solicitudes_mantenimiento', 'vista_todas_sucursales', $cargoOperario)) {
    http_response_code(403);
    die("No tienes permiso para descargar este informe.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Método no permitido.");
}

$filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
$orden   = isset($_POST['orden'])   ? json_decode($_POST['orden'],   true) : ['columna' => null, 'direccion' => 'asc'];

// ── Mapas de etiquetas ────────────────────────────────────────────────────────

$textosUrgencia = [
    0 => 'No Clasificado',
    1 => 'No Urgente',
    2 => 'Medio',
    3 => 'Urgente',
    4 => 'Crítico',
];

$textosEstado = [
    'solicitado'  => 'Solicitado',
    'clasificado' => 'Clasificado',
    'agendado'    => 'Agendado',
    'finalizado'  => 'Finalizado',
    'cancelado'   => 'Cancelado',
];

$textosTipo = [
    'mantenimiento'         => 'Mantenimiento',
    'mantenimiento_general' => 'Mantenimiento',
    'cambio_equipos'        => 'Cambio Equipo',
];

// ── Construir WHERE (idéntico a historial_get_solicitudes.php) ────────────────

try {
    $where_conditions = [];
    $params = [];

    foreach ($filtros as $columna => $valor) {
        if (is_array($valor)) {
            if (isset($valor['desde']) || isset($valor['hasta'])) {
                // Rango de fechas
                if (!empty($valor['desde']) && !empty($valor['hasta'])) {
                    $where_conditions[] = "DATE(t.$columna) BETWEEN ? AND ?";
                    $params[] = $valor['desde'];
                    $params[] = $valor['hasta'];
                } elseif (!empty($valor['desde'])) {
                    $where_conditions[] = "DATE(t.$columna) >= ?";
                    $params[] = $valor['desde'];
                } elseif (!empty($valor['hasta'])) {
                    $where_conditions[] = "DATE(t.$columna) <= ?";
                    $params[] = $valor['hasta'];
                }
            } else {
                // Lista de valores
                if (count($valor) === 0) continue;
                $placeholders = str_repeat('?,', count($valor) - 1) . '?';

                if ($columna === 'nombre_sucursal') {
                    $where_conditions[] = "s.nombre IN ($placeholders)";
                } elseif ($columna === 'nivel_urgencia') {
                    $where_conditions[] = "t.nivel_urgencia IN ($placeholders)";
                } elseif ($columna === 'tipo_formulario') {
                    $where_conditions[] = "t.tipo_formulario IN ($placeholders)";
                } elseif ($columna === 'status') {
                    $where_conditions[] = "t.status IN ($placeholders)";
                }

                $params = array_merge($params, $valor);
            }
        } else {
            // Texto (LIKE)
            if ($columna === 'titulo') {
                $where_conditions[] = "t.titulo LIKE ?";
                $params[] = "%$valor%";
            } elseif ($columna === 'descripcion') {
                $where_conditions[] = "t.descripcion LIKE ?";
                $params[] = "%$valor%";
            }
        }
    }

    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // ── ORDER BY ──────────────────────────────────────────────────────────────

    $order_sql = '';
    if (!empty($orden['columna'])) {
        $col = $orden['columna'];
        $dir = $orden['direccion'] === 'desc' ? 'DESC' : 'ASC';

        if ($col === 'nombre_sucursal') {
            $order_sql = "ORDER BY s.nombre $dir";
        } elseif (in_array($col, ['created_at', 'titulo', 'descripcion', 'nivel_urgencia', 'status', 'fecha_inicio', 'tipo_formulario', 'tiempo_estimado'])) {
            $order_sql = "ORDER BY t.$col $dir";
        }
    } else {
        $order_sql = "ORDER BY t.created_at DESC";
    }

    // ── Consulta (sin LIMIT/OFFSET) ───────────────────────────────────────────

    $sql = "SELECT t.*,
                   s.nombre AS nombre_sucursal
            FROM mtto_tickets t
            LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
            $where_sql
            $order_sql";

    $datos = $db->fetchAll($sql, $params);

    // ── Descripción de filtros activos para la cabecera del informe ───────────

    $filtrosDesc = [];
    foreach ($filtros as $columna => $valor) {
        if (is_array($valor)) {
            if (isset($valor['desde']) || isset($valor['hasta'])) {
                $desde = $valor['desde'] ?? '';
                $hasta = $valor['hasta'] ?? '';
                if ($desde && $hasta) {
                    $filtrosDesc[] = ucfirst($columna) . ": $desde → $hasta";
                } elseif ($desde) {
                    $filtrosDesc[] = ucfirst($columna) . " desde: $desde";
                } elseif ($hasta) {
                    $filtrosDesc[] = ucfirst($columna) . " hasta: $hasta";
                }
            } elseif (count($valor) > 0) {
                $filtrosDesc[] = ucfirst(str_replace('_', ' ', $columna)) . ': ' . implode(', ', $valor);
            }
        } elseif (!empty($valor)) {
            $filtrosDesc[] = ucfirst($columna) . ": $valor";
        }
    }

    // ── Headers HTTP ──────────────────────────────────────────────────────────

    $nombreArchivo = 'Historial_Solicitudes_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    // ── Salida HTML que Excel interpreta ──────────────────────────────────────
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
    <table border="0">
        <tr>
            <td colspan="8" style="font-size:16pt; font-weight:bold; color:#0E544C;">
                Informe Global — Historial de Solicitudes de Mantenimiento
            </td>
        </tr>
        <tr>
            <td colspan="8" style="color:#555555; font-size:10pt;">
                Generado el: <?php echo date('d/m/Y H:i:s'); ?>
            </td>
        </tr>
        <?php if (!empty($filtrosDesc)): ?>
        <tr>
            <td colspan="8" style="color:#555555; font-size:10pt;">
                Filtros aplicados: <?php echo htmlspecialchars(implode(' | ', $filtrosDesc)); ?>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <td colspan="8" style="color:#888888; font-size:9pt;">
                Total de registros: <?php echo count($datos); ?>
            </td>
        </tr>
        <tr><td colspan="8"></td></tr>
    </table>

    <table border="1" cellspacing="0" cellpadding="4">
        <thead>
            <tr style="background-color:#0E544C; color:#FFFFFF; font-weight:bold; text-align:center;">
                <th>Solicitado</th>
                <th>Título</th>
                <th>Descripción</th>
                <th>Resolución</th>
                <th>Sucursal</th>
                <th>Urgencia</th>
                <th>Tiempo</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $filasPar = false;
        foreach ($datos as $row):
            $bgColor = $filasPar ? '#F5F5F5' : '#FFFFFF';
            $filasPar = !$filasPar;

            // Formatear valores
            $solicitado = $row['created_at'] ? date('d/m/Y', strtotime($row['created_at'])) : '-';
            $agendado   = $row['fecha_inicio'] ? date('d/m/Y', strtotime($row['fecha_inicio'])) : 'Pendiente';
            $urgencia   = $textosUrgencia[(int)($row['nivel_urgencia'] ?? 0)] ?? 'No Clasificado';
            $estado     = $textosEstado[$row['status']] ?? ucfirst($row['status'] ?? '-');
            $tipo       = $textosTipo[$row['tipo_formulario']] ?? ucfirst(str_replace('_', ' ', $row['tipo_formulario'] ?? '-'));
            $tiempo     = ($row['tiempo_estimado'] ?? 0) > 0 ? $row['tiempo_estimado'] : '-';
            $sucursal   = htmlspecialchars($row['nombre_sucursal'] ?? '-');
            $titulo     = htmlspecialchars($row['titulo'] ?? '-');
            $descripcion = htmlspecialchars($row['descripcion'] ?? '-');
            $resolucion = htmlspecialchars($row['resolucion'] ?? '-');
            $creadoPor  = htmlspecialchars($row['creado_por'] ?? '-');

            // Color de urgencia para la celda
            $coloresUrgencia = [
                0 => '#8b8b8b',
                1 => '#28a745',
                2 => '#ffc107',
                3 => '#fd7e14',
                4 => '#dc3545',
            ];
            $colorUrg = $coloresUrgencia[(int)($row['nivel_urgencia'] ?? 0)] ?? '#8b8b8b';

            // Color de estado para la celda
            $coloresEstado = [
                'solicitado'  => '#6c757d',
                'clasificado' => '#17a2b8',
                'agendado'    => '#ffc107',
                'finalizado'  => '#28a745',
                'cancelado'   => '#dc3545',
            ];
            $colorEst = $coloresEstado[$row['status']] ?? '#6c757d';
        ?>
            <tr style="background-color:<?php echo $bgColor; ?>;">
                <td style="white-space:nowrap;"><?php echo $solicitado; ?></td>
                <td style="max-width:200px;"><?php echo $titulo; ?></td>
                <td style="max-width:300px;"><?php echo $descripcion; ?></td>
                <td style="max-width:300px;"><?php echo $resolucion; ?></td>
                <td><?php echo $sucursal; ?></td>
                <td style="text-align:center; background-color:<?php echo $colorUrg; ?>; color:#FFFFFF; font-weight:bold;"><?php echo $urgencia; ?></td>
                <td style="text-align:center;"><?php echo $tiempo; ?></td>
                <td style="text-align:center; background-color:<?php echo $colorEst; ?>; color:#FFFFFF; font-weight:bold;"><?php echo $estado; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
    <?php
    exit();

} catch (Exception $e) {
    http_response_code(500);
    echo "Error al generar el informe: " . htmlspecialchars($e->getMessage());
}
?>
