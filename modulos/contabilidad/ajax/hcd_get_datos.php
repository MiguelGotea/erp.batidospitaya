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
                cd.MFCor,
                cd.MFDol,
                cd.TotalPOS,
                cd.TotalTransferencia,
                cd.TotalPedidosYa,
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

        $minHoraInicial = "23:59:59";
        $maxHoraFinal   = "00:00:00";
        foreach ($lista as $c) {
             if (!empty($c['HoraInicial']) && $c['HoraInicial'] < $minHoraInicial) $minHoraInicial = $c['HoraInicial'];
             if (!empty($c['HoraFinal']) && $c['HoraFinal'] > $maxHoraFinal) $maxHoraFinal = $c['HoraFinal'];
        }

        // Extraer el cierre final de cada grupo y ordenarlos por HoraInicial ASC
        $finalesDia = [];
        foreach ($grupos as $g) {
            $ordenados = $g['todos'];
            usort($ordenados, fn($a, $b) => (int)$b['CodigoCierre'] - (int)$a['CodigoCierre']);
            $final                    = $ordenados[0];
            $final['num_precierres']  = count($ordenados) - 1;
            
            $tiene_precierre_anulado = false;
            foreach ($ordenados as $c) {
                $ini = horaAMin($c['HoraInicial']);
                $fin = horaAMin($c['HoraFinal']);
                $diff = $fin - $ini;
                if ($diff < 0) $diff += 24 * 60;
                if ($diff < 30) {
                    $tiene_precierre_anulado = true;
                    break;
                }
            }
            $final['tiene_precierre_anulado'] = $tiene_precierre_anulado;
            
            $final['cajero'] = trim($final['cajero'] ?? '');
            if ($final['cajero'] === '') {
                $final['cajero'] = 'Sin cajero';
            }
            $finalesDia[] = $final;
        }

        // Ordenar los finales del día por HoraInicial ASC para calcular el desagregado
        usort($finalesDia, fn($a, $b) => horaAMin($a['HoraInicial']) - horaAMin($b['HoraInicial']));

        $faltanteAnterior = null;
        foreach ($finalesDia as $idx => &$fila) {
            $faltanteActual = (int)($fila['Faltante'] ?? 0);
            if ($faltanteAnterior === null) {
                $fila['FaltanteDesagregado'] = $faltanteActual;
            } else {
                $fila['FaltanteDesagregado'] = $faltanteActual - $faltanteAnterior;
            }
            $faltanteAnterior = $faltanteActual;
            
            $fila['isFirstClosure'] = ($idx === 0);
            $fila['isLastClosure']  = ($idx === count($finalesDia) - 1);
            $fila['minHoraInicialDia'] = $minHoraInicial;
            $fila['maxHoraFinalDia'] = $maxHoraFinal;
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

    // ── Enriquecer Paginados con Alertas ──────────────────────────────────────
    $hoy = date('Y-m-d');
    foreach ($paginados as &$p) {
        $alertas = [];

        // No evaluar ninguna alerta si el cierre corresponde al día en curso
        if ($p['Fecha'] === $hoy) {
            $p['alertas'] = [];
            continue;
        }

        if (!empty($p['tiene_precierre_anulado'])) {
            $alertas[] = ['tipo' => 'danger', 'texto' => 'Cierre Anulado'];
        }

        $fecha = $p['Fecha'];
        $sucursal = $p['Sucursal'];

        // Facturación fuera de rango
        if (!empty($p['isFirstClosure'])) {
            $stmtFuera = $conn->prepare("SELECT Hora FROM VentasGlobalesAccessCSV WHERE Fecha = :fecha AND local = :sucursal AND Anulado = 0 AND Hora < :minHora ORDER BY Hora ASC");
            $stmtFuera->execute(['fecha' => $fecha, 'sucursal' => $sucursal, 'minHora' => $p['minHoraInicialDia']]);
            $fuera = $stmtFuera->fetchAll(PDO::FETCH_ASSOC);
            if ($fuera) {
                $horasFormat = array_map(function($r) { return date("h:i A", strtotime($r['Hora'])); }, $fuera);
                $horasFormat = array_unique($horasFormat);
                $alertas[] = ['tipo' => 'warning', 'texto' => 'Facturas antes de apertura: ' . implode(', ', $horasFormat)];
            }
        }
        if (!empty($p['isLastClosure'])) {
            $stmtFuera2 = $conn->prepare("SELECT Hora FROM VentasGlobalesAccessCSV WHERE Fecha = :fecha AND local = :sucursal AND Anulado = 0 AND Hora > :maxHora ORDER BY Hora ASC");
            $stmtFuera2->execute(['fecha' => $fecha, 'sucursal' => $sucursal, 'maxHora' => $p['maxHoraFinalDia']]);
            $fuera = $stmtFuera2->fetchAll(PDO::FETCH_ASSOC);
            if ($fuera) {
                $horasFormat = array_map(function($r) { return date("h:i A", strtotime($r['Hora'])); }, $fuera);
                $horasFormat = array_unique($horasFormat);
                $alertas[] = ['tipo' => 'warning', 'texto' => 'Facturas después de cierre: ' . implode(', ', $horasFormat)];
            }
        }

        // Faltante Calculado vs Guardado
        $stmtEI = $conn->prepare("SELECT Dinero, TipoCambio_C FROM msaccess_masivo_EstadoInicial WHERE Fecha = :fecha AND Sucursal = :sucursal LIMIT 1");
        $stmtEI->execute(['fecha' => $fecha, 'sucursal' => $sucursal]);
        $rowEI = $stmtEI->fetch(PDO::FETCH_ASSOC);
        $caja_inicial = $rowEI ? (float)$rowEI['Dinero'] : 0;
        $tipo_cambio  = $rowEI ? (float)$rowEI['TipoCambio_C'] : 1;
        if ($tipo_cambio <= 0) $tipo_cambio = 1;

        $sqlVentas = "SELECT sub.Modalidad, SUM(sub.MontoFactura) AS total
                      FROM (
                          SELECT DISTINCT v.CodPedido, v.Modalidad, v.MontoFactura
                          FROM VentasGlobalesAccessCSV v
                          WHERE v.Fecha = :fecha AND v.local = :sucursal AND v.Anulado = 0 AND v.Hora <= :hora_final
                      ) sub GROUP BY sub.Modalidad";
        $stmtV = $conn->prepare($sqlVentas);
        $stmtV->execute(['fecha' => $fecha, 'sucursal' => $sucursal, 'hora_final' => $p['HoraFinal']]);
        $rowsVentas = $stmtV->fetchAll(PDO::FETCH_ASSOC);
        $efectivo_sistema = 0;
        foreach ($rowsVentas as $rv) {
            if (strtoupper(trim($rv['Modalidad'])) === 'EFECTIVO') {
                $efectivo_sistema = (float)$rv['total'];
            }
        }

        $esUltimoCierre = !empty($p['isLastClosure']);
        $codCierreActual = $p['CodigoCierre'];

        $sqlDep = "SELECT Monto, Denominacion, Hora FROM msaccess_masivo_Depositos WHERE Fecha = :fecha AND Sucursal = :sucursal";
        if (!$esUltimoCierre && !empty($p['HoraFinal'])) {
            $sqlDep .= " AND Hora >= :min_hora AND Hora <= :hora_final";
        }
        $stmtDep = $conn->prepare($sqlDep);
        $stmtDep->bindValue(':fecha', $fecha);
        $stmtDep->bindValue(':sucursal', $sucursal);
        if (!$esUltimoCierre && !empty($p['HoraFinal'])) {
            $stmtDep->bindValue(':min_hora', $p['minHoraInicialDia']);
            $stmtDep->bindValue(':hora_final', $p['HoraFinal']);
        }
        $stmtDep->execute();
        $rowsDep = $stmtDep->fetchAll(PDO::FETCH_ASSOC);
        
        $aligeramientos = 0;
        foreach ($rowsDep as $dep) {
            $monto = (float)$dep['Monto'];
            $denom = strtolower(trim($dep['Denominacion']));
            if ($denom === 'dolares' || $denom === 'dólares') $monto *= $tipo_cambio;
            $aligeramientos += $monto;
        }

        if ($esUltimoCierre) {
            $stmtDepFuera = $conn->prepare("SELECT Hora FROM msaccess_masivo_Depositos WHERE Fecha = :fecha AND Sucursal = :sucursal AND (Hora < :minHora OR Hora > :maxHora) ORDER BY Hora ASC");
            $stmtDepFuera->execute(['fecha' => $fecha, 'sucursal' => $sucursal, 'minHora' => $p['minHoraInicialDia'], 'maxHora' => $p['maxHoraFinalDia']]);
            $depsFuera = $stmtDepFuera->fetchAll(PDO::FETCH_ASSOC);
            if ($depsFuera) {
                $horasFormat = array_map(function($r) { return $r['Hora'] ? date("h:i A", strtotime($r['Hora'])) : '—'; }, $depsFuera);
                $horasFormat = array_unique($horasFormat);
                $alertas[] = ['tipo' => 'warning', 'texto' => 'Aligeramientos fuera de horario de turnos: ' . implode(', ', $horasFormat)];
            }
        }

        // Obtener los CodOperario de todos los cierres del día HASTA el actual
        $stmtOps = $conn->prepare("SELECT DISTINCT CodOperario FROM msaccess_masivo_CierreDiario WHERE Fecha = :fecha AND Sucursal = :sucursal AND CodigoCierre <= :cod_cierre");
        $stmtOps->execute(['fecha' => $fecha, 'sucursal' => $sucursal, 'cod_cierre' => $codCierreActual]);
        $operarios = $stmtOps->fetchAll(PDO::FETCH_COLUMN);
        $operarios_in = empty($operarios) ? "0" : implode(',', array_map('intval', array_filter($operarios)));

        $condicionOperario = " AND (CodOperario IN ($operarios_in)";
        if ($esUltimoCierre) {
            $condicionOperario .= " OR CodOperario IS NULL OR CodOperario = '' OR CodOperario NOT IN (SELECT CodOperario FROM msaccess_masivo_CierreDiario WHERE Fecha = :fecha2 AND Sucursal = :sucursal2)";
        }
        $condicionOperario .= ")";

        $stmtComp = $conn->prepare("SELECT SUM(COALESCE(CostoTotal, 0)) AS total FROM msaccess_masivo_Compras WHERE Fecha = :fecha AND Sucursal = :sucursal AND Tipo = 'CAJA'" . $condicionOperario);
        
        $paramsComp = ['fecha' => $fecha, 'sucursal' => $sucursal];
        if ($esUltimoCierre) {
            $paramsComp['fecha2'] = $fecha;
            $paramsComp['sucursal2'] = $sucursal;
        }
        $stmtComp->execute($paramsComp);
        
        $rowComp = $stmtComp->fetch(PDO::FETCH_ASSOC);
        $compras_caja = (float)($rowComp['total'] ?? 0);

        if ($esUltimoCierre) {
            $sqlHuerfanas = "SELECT COUNT(*) FROM msaccess_masivo_Compras WHERE Fecha = :fecha1 AND Sucursal = :sucursal1 AND Tipo = 'CAJA' AND (CodOperario IS NULL OR CodOperario = '' OR CodOperario NOT IN (SELECT CodOperario FROM msaccess_masivo_CierreDiario WHERE Fecha = :fecha2 AND Sucursal = :sucursal2))";
            $stmtHuerfanas = $conn->prepare($sqlHuerfanas);
            $stmtHuerfanas->execute(['fecha1' => $fecha, 'sucursal1' => $sucursal, 'fecha2' => $fecha, 'sucursal2' => $sucursal]);
            $countHuerfanas = (int)$stmtHuerfanas->fetchColumn();
            if ($countHuerfanas > 0) {
                $alertas[] = ['tipo' => 'warning', 'texto' => "Facturas de compras reasignadas: $countHuerfanas sin cierre de turno"];
            }
        }

        $mf_cor = (float)$p['MFCor'];
        $mf_dol = (float)$p['MFDol'];
        $conteo_caja = $mf_cor + ($mf_dol * $tipo_cambio);

        $efectivoAEntregar = $caja_inicial + $efectivo_sistema - $aligeramientos - $compras_caja;
        $faltanteCalculado = $conteo_caja - $efectivoAEntregar;
        $faltanteGuardado = (float)$p['Faltante'];

        if (abs($faltanteCalculado - $faltanteGuardado) > 5) {
            $alertas[] = ['tipo' => 'danger', 'texto' => 'Incongruencia de Balance'];
        }

        $p['alertas'] = $alertas;
    }
    unset($p);

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
