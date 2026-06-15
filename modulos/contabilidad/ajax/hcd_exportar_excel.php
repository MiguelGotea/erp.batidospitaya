<?php
// ajax/hcd_exportar_excel.php
// Exporta los cierres finales del historial a Excel respetando los filtros activos.
// Replica la misma lógica de agrupación y filtrado de hcd_get_datos.php (sin paginación).
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('balance_cierre_diario', 'exportar', $cargoOperario)) {
    die('Sin permiso para descargar este reporte.');
}

try {
    $filtros   = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $ordenJson = isset($_POST['orden'])   ? json_decode($_POST['orden'],   true) : [];

    $columnaOrden = $ordenJson['columna']   ?? 'Fecha';
    $dirOrden     = strtolower($ordenJson['direccion'] ?? 'desc') === 'asc' ? 1 : -1;

    // ── Filtro de fecha a nivel SQL ───────────────────────────────────────────
    $where  = [];
    $params = [];

    if (!empty($filtros['Fecha']) && is_array($filtros['Fecha'])) {
        $f = $filtros['Fecha'];
        if (!empty($f['desde'])) {
            $where[]              = 'cd.Fecha >= :fecha_desde';
            $params[':fecha_desde'] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $where[]             = 'cd.Fecha <= :fecha_hasta';
            $params[':fecha_hasta'] = $f['hasta'];
        }
    }

    $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT
                cd.Sucursal,
                cd.CodigoCierre,
                cd.HoraInicial,
                cd.HoraFinal,
                cd.Fecha,
                cd.CodOperario,
                cd.Faltante,
                cd.Observaciones,
                COALESCE(s.nombre, CONCAT('Sucursal ', cd.Sucursal)) AS nombre_sucursal,
                TRIM(REGEXP_REPLACE(CONCAT_WS(' ',
                    COALESCE(o.Nombre,''),
                    COALESCE(o.Nombre2,''),
                    COALESCE(o.Apellido,''),
                    COALESCE(o.Apellido2,'')), '[ ]+', ' ')) AS cajero
            FROM msaccess_masivo_CierreDiario cd
            LEFT JOIN sucursales s  ON s.codigo      = cd.Sucursal
            LEFT JOIN Operarios  o  ON o.CodOperario = cd.CodOperario
            $whereSQL
            ORDER BY cd.Fecha DESC, cd.Sucursal ASC, cd.CodigoCierre ASC";

    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Agrupar y extraer cierres finales (misma lógica que hcd_get_datos.php) ──
    $gruposPorDia = [];
    foreach ($todos as $row) {
        $key = $row['Fecha'] . '|' . $row['Sucursal'];
        $gruposPorDia[$key][] = $row;
    }

    $cierresFinalesPorDia = [];
    foreach ($gruposPorDia as $key => $lista) {
        usort($lista, fn($a, $b) => (int)$a['CodigoCierre'] - (int)$b['CodigoCierre']);

        $grupos = [];
        foreach ($lista as $c) {
            $minC       = horaAMinExcel($c['HoraInicial']);
            $encontrado = false;
            foreach ($grupos as &$g) {
                $minRef = horaAMinExcel($g['todos'][0]['HoraInicial']);
                if (abs($minC - $minRef) <= 30) {
                    $g['todos'][] = $c;
                    $encontrado   = true;
                    break;
                }
            }
            unset($g);
            if (!$encontrado) {
                $grupos[] = ['todos' => [$c]];
            }
        }

        $finalesDia = [];
        foreach ($grupos as $g) {
            $ordenados = $g['todos'];
            usort($ordenados, fn($a, $b) => (int)$b['CodigoCierre'] - (int)$a['CodigoCierre']);
            $final           = $ordenados[0];
            $final['cajero'] = trim($final['cajero'] ?? '');
            if ($final['cajero'] === '') $final['cajero'] = 'Sin cajero';
            $finalesDia[] = $final;
        }

        // Ordenar por HoraInicial ASC para calcular el desagregado correctamente
        usort($finalesDia, fn($a, $b) => horaAMinExcel($a['HoraInicial']) - horaAMinExcel($b['HoraInicial']));

        $faltanteAnterior = null;
        foreach ($finalesDia as &$fila) {
            $faltanteActual = (int)($fila['Faltante'] ?? 0);
            $fila['FaltanteDesagregado'] = ($faltanteAnterior === null)
                ? $faltanteActual
                : ($faltanteActual - $faltanteAnterior);
            $faltanteAnterior = $faltanteActual;
        }
        unset($fila);

        $cierresFinalesPorDia[$key] = $finalesDia;
    }

    $cierresFinales = [];
    foreach ($cierresFinalesPorDia as $finalesDia) {
        foreach ($finalesDia as $fila) {
            $cierresFinales[] = $fila;
        }
    }

    // ── Filtros en PHP ────────────────────────────────────────────────────────
    if (!empty($filtros['nombre_sucursal']) && is_array($filtros['nombre_sucursal'])) {
        $vals = $filtros['nombre_sucursal'];
        $cierresFinales = array_values(array_filter(
            $cierresFinales,
            fn($r) => in_array($r['nombre_sucursal'], $vals)
        ));
    }

    if (isset($filtros['CodigoCierre']) && $filtros['CodigoCierre'] !== '') {
        $q = strtolower((string)$filtros['CodigoCierre']);
        $cierresFinales = array_values(array_filter(
            $cierresFinales,
            fn($r) => str_contains(strtolower((string)$r['CodigoCierre']), $q)
        ));
    }

    if (isset($filtros['cajero']) && $filtros['cajero'] !== '') {
        $q = strtolower((string)$filtros['cajero']);
        $cierresFinales = array_values(array_filter(
            $cierresFinales,
            fn($r) => str_contains(strtolower($r['cajero']), $q)
        ));
    }

    if (!empty($filtros['Faltante']) && is_array($filtros['Faltante'])) {
        $fMin = $filtros['Faltante']['min'] ?? null;
        $fMax = $filtros['Faltante']['max'] ?? null;
        $cierresFinales = array_values(array_filter($cierresFinales, function ($r) use ($fMin, $fMax) {
            $v = (int)($r['Faltante'] ?? 0);
            if ($fMin !== null && $fMin !== '' && $v < (int)$fMin) return false;
            if ($fMax !== null && $fMax !== '' && $v > (int)$fMax) return false;
            return true;
        }));
    }

    if (!empty($filtros['FaltanteDesagregado']) && is_array($filtros['FaltanteDesagregado'])) {
        $fMin = $filtros['FaltanteDesagregado']['min'] ?? null;
        $fMax = $filtros['FaltanteDesagregado']['max'] ?? null;
        $cierresFinales = array_values(array_filter($cierresFinales, function ($r) use ($fMin, $fMax) {
            $v = (int)($r['FaltanteDesagregado'] ?? 0);
            if ($fMin !== null && $fMin !== '' && $v < (int)$fMin) return false;
            if ($fMax !== null && $fMax !== '' && $v > (int)$fMax) return false;
            return true;
        }));
    }

    if (isset($filtros['Observaciones']) && $filtros['Observaciones'] !== '') {
        $q = strtolower((string)$filtros['Observaciones']);
        $cierresFinales = array_values(array_filter(
            $cierresFinales,
            fn($r) => str_contains(strtolower($r['Observaciones'] ?? ''), $q)
        ));
    }

    // ── Ordenar ───────────────────────────────────────────────────────────────
    $campoMap = [
        'nombre_sucursal'     => 'nombre_sucursal',
        'CodigoCierre'        => 'CodigoCierre',
        'cajero'              => 'cajero',
        'Faltante'            => 'Faltante',
        'FaltanteDesagregado' => 'FaltanteDesagregado',
        'HoraInicial'         => 'HoraInicial',
        'HoraFinal'           => 'HoraFinal',
        'Fecha'               => 'Fecha',
        'Observaciones'       => 'Observaciones',
    ];
    $campo = $campoMap[$columnaOrden] ?? 'Fecha';

    usort($cierresFinales, function ($a, $b) use ($campo, $dirOrden) {
        $va = $a[$campo] ?? '';
        $vb = $b[$campo] ?? '';
        if (is_numeric($va) && is_numeric($vb)) return ($va - $vb) * $dirOrden;
        return strcmp((string)$va, (string)$vb) * $dirOrden;
    });

    // ── Generar Excel ─────────────────────────────────────────────────────────
    $filename = 'HistorialCierresDiarios_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
    <table border="1">
        <thead>
            <tr style="background-color:#0E544C; color:#FFFFFF; font-weight:bold;">
                <th>Sucursal</th>
                <th>Fecha</th>
                <th>Cierre Final</th>
                <th>Cajero</th>
                <th>Sobrante / Faltante</th>
                <th>Monto S/F</th>
                <th>Sobrante / Faltante Acumulado</th>
                <th>Monto Acumulado</th>
                <th>Hora Inicial</th>
                <th>Hora Final</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($cierresFinales as $row) {
        $faltanteDesag = (int)($row['FaltanteDesagregado'] ?? 0);
        $faltanteAcum  = (int)($row['Faltante'] ?? 0);

        // Sobrante / Faltante (desagregado)
        if ($faltanteDesag === 0)   $tipoSF = 'Exacto';
        elseif ($faltanteDesag > 0) $tipoSF = 'Sobrante';
        else                        $tipoSF = 'Faltante';
        $montoSF = abs($faltanteDesag);

        // Sobrante / Faltante Acumulado
        if ($faltanteAcum === 0)    $tipoAcum = 'Exacto';
        elseif ($faltanteAcum > 0)  $tipoAcum = 'Sobrante';
        else                        $tipoAcum = 'Faltante';
        $montoAcum = abs($faltanteAcum);

        $hi = $row['HoraInicial'] ? substr($row['HoraInicial'], 0, 5) : '';
        $hf = $row['HoraFinal']   ? substr($row['HoraFinal'],   0, 5) : '';

        echo '<tr>';
        echo '<td>' . htmlspecialchars((string)($row['nombre_sucursal'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['Fecha']           ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['CodigoCierre']    ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['cajero']          ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars($tipoSF)        . '</td>';
        echo '<td>' . htmlspecialchars((string)$montoSF)   . '</td>';
        echo '<td>' . htmlspecialchars($tipoAcum)      . '</td>';
        echo '<td>' . htmlspecialchars((string)$montoAcum) . '</td>';
        echo '<td>' . htmlspecialchars($hi) . '</td>';
        echo '<td>' . htmlspecialchars($hf) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['Observaciones']   ?? '')) . '</td>';
        echo '</tr>';
    }

    echo '      </tbody>
    </table>
</body>
</html>';
    exit();

} catch (Exception $e) {
    echo 'Error al generar el Excel: ' . htmlspecialchars($e->getMessage());
}

function horaAMinExcel($h)
{
    if (!$h) return 0;
    $parts = explode(':', $h);
    return (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
}
?>
