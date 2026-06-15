<?php
// ajax/hcd_get_datos.php
// Devuelve los cierres FINALES del historial con paginación y filtros.
// Replica la lógica agruparCierres() del JS (agrupación por HoraInicial ≤ 30 min)
// en PHP para identificar cuál es el cierre final de cada turno.
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $pagina     = isset($_POST['pagina'])               ? max(1, (int)$_POST['pagina'])  : 1;
    $porPagina  = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
    $filtros    = isset($_POST['filtros'])  ? json_decode($_POST['filtros'],  true) : [];
    $ordenJson  = isset($_POST['orden'])   ? json_decode($_POST['orden'],    true) : [];
    $columnaOrden = $ordenJson['columna']   ?? 'Fecha';
    $dirOrden     = strtolower($ordenJson['direccion'] ?? 'desc') === 'asc' ? 1 : -1;

    // ── Filtros a nivel SQL (solo fecha para reducir dataset) ─────────────────
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

    // ── Agrupar por (Fecha + Sucursal) y detectar cierre final ───────────────
    // Replicando exactamente la función agruparCierres() del JS
    $gruposPorDia = [];   // key → "Fecha|Sucursal"
    foreach ($todos as $row) {
        $key = $row['Fecha'] . '|' . $row['Sucursal'];
        $gruposPorDia[$key][] = $row;
    }

    // Recopilar todos los cierres finales antes de calcular el desagregado
    $cierresFinalesPorDia = [];   // key → "Fecha|Sucursal", valor → array de finales ordenados ASC por HoraInicial
    foreach ($gruposPorDia as $key => $lista) {
        // Ordenar ASC por CodigoCierre (igual que JS)
        usort($lista, fn($a, $b) => (int)$a['CodigoCierre'] - (int)$b['CodigoCierre']);

        $grupos = [];   // cada elemento: ['todos' => [...]]
        foreach ($lista as $c) {
            $minC = horaAMin($c['HoraInicial']);
            $encontrado = false;
            foreach ($grupos as &$g) {
                $minRef = horaAMin($g['todos'][0]['HoraInicial']);
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

        // Extraer el cierre final de cada grupo y ordenarlos por HoraInicial ASC
        $finalesDia = [];
        foreach ($grupos as $g) {
            $ordenados = $g['todos'];
            usort($ordenados, fn($a, $b) => (int)$b['CodigoCierre'] - (int)$a['CodigoCierre']);
            $final                    = $ordenados[0];
            $final['num_precierres']  = count($ordenados) - 1;
            $final['cajero'] = trim($final['cajero'] ?? '');
            if ($final['cajero'] === '') {
                $final['cajero'] = 'Sin cajero';
            }
            $finalesDia[] = $final;
        }

        // Ordenar los finales del día por HoraInicial ASC para calcular el desagregado
        usort($finalesDia, fn($a, $b) => horaAMin($a['HoraInicial']) - horaAMin($b['HoraInicial']));

        // Calcular FaltanteDesagregado: cierre_i - cierre_(i-1);
        // el primer cierre del día conserva su propio Faltante
        $faltanteAnterior = null;
        foreach ($finalesDia as &$fila) {
            $faltanteActual = (int)($fila['Faltante'] ?? 0);
            if ($faltanteAnterior === null) {
                $fila['FaltanteDesagregado'] = $faltanteActual;
            } else {
                $fila['FaltanteDesagregado'] = $faltanteActual - $faltanteAnterior;
            }
            $faltanteAnterior = $faltanteActual;
        }
        unset($fila);

        $cierresFinalesPorDia[$key] = $finalesDia;
    }

    // Aplanar en un único array
    $cierresFinales = [];
    foreach ($cierresFinalesPorDia as $finalesDia) {
        foreach ($finalesDia as $fila) {
            $cierresFinales[] = $fila;
        }
    }

    // ── Filtros en PHP sobre los cierres finales ──────────────────────────────
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
        'nombre_sucursal'    => 'nombre_sucursal',
        'CodigoCierre'       => 'CodigoCierre',
        'cajero'             => 'cajero',
        'Faltante'           => 'Faltante',
        'FaltanteDesagregado'=> 'FaltanteDesagregado',
        'HoraInicial'        => 'HoraInicial',
        'HoraFinal'          => 'HoraFinal',
        'Fecha'              => 'Fecha',
        'Observaciones'      => 'Observaciones',
    ];
    $campo = $campoMap[$columnaOrden] ?? 'Fecha';

    usort($cierresFinales, function ($a, $b) use ($campo, $dirOrden) {
        $va = $a[$campo] ?? '';
        $vb = $b[$campo] ?? '';
        if (is_numeric($va) && is_numeric($vb)) {
            return ($va - $vb) * $dirOrden;
        }
        return strcmp((string)$va, (string)$vb) * $dirOrden;
    });

    // ── Paginar ───────────────────────────────────────────────────────────────
    $total    = count($cierresFinales);
    $offset   = ($pagina - 1) * $porPagina;
    $paginados = array_slice($cierresFinales, $offset, $porPagina);

    echo json_encode(['success' => true, 'datos' => $paginados, 'total_registros' => $total]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Convierte "HH:MM:SS" o "HH:MM" a minutos desde medianoche
function horaAMin($h)
{
    if (!$h) return 0;
    $parts = explode(':', $h);
    return (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
}
?>
