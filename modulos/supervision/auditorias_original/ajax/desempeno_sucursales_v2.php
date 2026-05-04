<?php
// ajax/desempeno_sucursales_v2.php
require_once '../conexion.php';
header('Content-Type: application/json');

// ── Helpers ─────────────────────────────────────────────────────────────────

function escalaAPorcentaje($valor)
{
    return round(($valor / 5) * 100);
}

function getColorClassPct($pct)
{
    if ($pct <= 80)
        return 'rojo';
    if ($pct <= 90)
        return 'amarillo';
    return 'verde';
}

function getColorClass($promedio)
{
    if ($promedio <= 4)
        return 'rojo';
    if ($promedio <= 4.5)
        return 'amarillo';
    return 'verde';
}

function calcularPorcentajeReclamos($cantidad)
{
    if ($cantidad <= 1)
        return 100;
    if ($cantidad == 2)
        return 80;
    if ($cantidad == 3)
        return 60;
    if ($cantidad == 4)
        return 40;
    if ($cantidad == 5)
        return 20;
    return 0;
}

function obtenerFactorVisual($factor_real)
{
    if ($factor_real >= 130)
        return 130;
    return $factor_real;
}

define('MEMBRESIA_META', 64);
define('TAMANO_NORMAL_META', 85.0);
define('MOSTRADOR_META', 8.0);
define('RESENAS_META', 12);

function calcularPctMembresia($cantidad, $meta_acumulada)
{
    if ($cantidad <= 0 || $meta_acumulada <= 0)
        return 0;
    return min(100, (int) round(($cantidad / $meta_acumulada) * 100));
}

/**
 * Aplica una meta porcentual:
 * si $pct_real >= $meta → 100%, si no → proporcional
 */
function aplicarMeta($pct_real, $meta)
{
    if ($pct_real <= 0 || $meta <= 0)
        return 0;
    if ($pct_real >= $meta)
        return 100;
    return (int) round(($pct_real / $meta) * 100);
}

// ── Entrada ──────────────────────────────────────────────────────────────────

try {
    $mes = isset($_POST['mes']) ? (int) $_POST['mes'] : (int) date('n');
    $anio = isset($_POST['anio']) ? (int) $_POST['anio'] : (int) date('Y');

    // ── Cálculo de metas acumuladas (hasta un día anterior) ──────────────────
    $current_mes = (int) date('n');
    $current_anio = (int) date('Y');
    $days_in_month = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));

    if ($anio < $current_anio || ($anio == $current_anio && $mes < $current_mes)) {
        $days_passed = $days_in_month;
    } elseif ($anio == $current_anio && $mes == $current_mes) {
        $days_passed = max(0, (int) date('j') - 1);
    } else {
        $days_passed = 0;
    }

    $meta_acumulada_membresia = (MEMBRESIA_META / $days_in_month) * $days_passed;
    $meta_acumulada_resenas = (RESENAS_META / $days_in_month) * $days_passed;

    // Sucursales activas
    $stmt = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal = 1 ORDER BY nombre");
    $stmt->execute();
    $sucursales_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sucursales = [];
    foreach ($sucursales_raw as $s) {
        $sucursales[$s['codigo']] = $s['nombre'];
    }

    // Limpieza por sucursal
    $stmt = $conn->prepare("SELECT cod_sucursal, AVG(promedio_general) as promedio, COUNT(*) as cantidad
                            FROM auditoria
                            WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio
                            GROUP BY cod_sucursal");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $limpieza_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // Servicio por sucursal
    $stmt = $conn->prepare("SELECT cod_sucursal, AVG(promedio_calificacion) as promedio, COUNT(*) as cantidad
                            FROM auditoria_servicio
                            WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio
                            GROUP BY cod_sucursal");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $servicio_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // Personal por sucursal
    $stmt = $conn->prepare("SELECT cod_sucursal, AVG(promedio_personal) as promedio, COUNT(*) as cantidad
                            FROM auditoria_personal
                            WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio
                            GROUP BY cod_sucursal");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $personal_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // KPI Reclamos por sucursal (solo reclamos, sin kpi_ventas)
    $stmt = $conn->prepare("SELECT cod_sucursal, reclamos_cantidad, reclamos_totales
                            FROM kpi_reclamos
                            WHERE mes = :mes AND anio = :anio");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $kpi_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // Ventas mensuales por sucursal
    $stmt = $conn->prepare("SELECT local as cod_sucursal, SUM(Precio) as total_ventas
                            FROM VentasGlobalesAccessCSV
                            WHERE MONTH(Fecha) = :mes AND YEAR(Fecha) = :anio
                              AND DATE(Fecha) < CURDATE()
                              AND (Anulado IS NULL OR Anulado = 0)
                            GROUP BY local");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $vm_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // Ventas meta por sucursal
    $stmt = $conn->prepare("SELECT cod_sucursal, SUM(meta) as total_meta
                            FROM ventas_meta
                            WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio
                              AND DATE(fecha) < CURDATE()
                            GROUP BY cod_sucursal");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $vmt_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // KPI Reclamos totales (solo reclamos)
    $stmt = $conn->prepare("SELECT SUM(reclamos_cantidad) as reclamos_cantidad_total,
                                   SUM(reclamos_totales) as reclamos_totales_total
                            FROM kpi_reclamos
                            WHERE mes = :mes AND anio = :anio");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $kpi_total_row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Membresías por sucursal (CodGrupo=5, CodigoPromocion=5)
    // Se hace JOIN con sucursales para que el GROUP BY use s.codigo
    // que es la misma clave que usa el loop ($cod = sucursales.codigo)
    $stmt = $conn->prepare("
        SELECT s.codigo AS cod_sucursal,
               SUM(v.Cantidad) AS total_membresias
        FROM VentasGlobalesAccessCSV v
        JOIN DBBatidos b           ON v.CodProducto = b.CodBatido
        JOIN GrupoProductosVenta g ON b.CodGrupo    = g.CodGrupo
        JOIN sucursales s          ON v.local        = s.codigo
        WHERE g.CodGrupo          = 5
          AND v.CodigoPromocion   = 5
          AND MONTH(v.Fecha)      = :mes
          AND YEAR(v.Fecha)       = :anio
          AND DATE(v.Fecha)       < CURDATE()
          AND (v.Anulado IS NULL OR v.Anulado = 0)
        GROUP BY s.codigo
    ");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $membresias_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // Tamaño Normal por sucursal
    // Tipo=Batido|Limonada, excluye PedidosYa
    // Pct = SUM(Cantidad WHERE Medida='Gigantona') / SUM(Cantidad WHERE Medida IN ('Gigantona','Mediano'))
    $stmt = $conn->prepare("
        SELECT s.codigo AS cod_sucursal,
               SUM(CASE WHEN b.Medida = 'Gigantona' THEN v.Cantidad ELSE 0 END) AS cant_gigantona,
               SUM(CASE WHEN b.Medida IN ('Gigantona','Mediano') THEN v.Cantidad ELSE 0 END) AS cant_total_tam
        FROM VentasGlobalesAccessCSV v
        JOIN DBBatidos b           ON v.CodProducto = b.CodBatido
        JOIN GrupoProductosVenta g ON b.CodGrupo    = g.CodGrupo
        JOIN sucursales s          ON v.local        = s.codigo
        WHERE g.Tipo               IN ('Batido', 'Limonada')
          AND (v.Delivery_Nombre  <> 'PedidosYa' OR v.Delivery_Nombre IS NULL)
          AND MONTH(v.Fecha)       = :mes
          AND YEAR(v.Fecha)        = :anio
          AND DATE(v.Fecha)        < CURDATE()
          AND (v.Anulado IS NULL OR v.Anulado = 0)
        GROUP BY s.codigo
    ");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $tamano_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // Mostrador por sucursal
    // CodGrupo IN (5,7), excluye PedidosYa
    // Pct = SUM(Precio grupo 5|7 sin PedidosYa) / SUM(Precio total sin PedidosYa)
    // Meta: 8% → si raw_pct >= 8% = 100%, si no → proporcional
    $stmt = $conn->prepare("
        SELECT s.codigo AS cod_sucursal,
               SUM(v.Precio) AS monto_mostrador
        FROM VentasGlobalesAccessCSV v
        JOIN DBBatidos b           ON v.CodProducto = b.CodBatido
        JOIN GrupoProductosVenta g ON b.CodGrupo    = g.CodGrupo
        JOIN sucursales s          ON v.local        = s.codigo
        WHERE g.CodGrupo               IN (5, 7)
          AND (v.Delivery_Nombre       <> 'PedidosYa' OR v.Delivery_Nombre IS NULL)
          AND MONTH(v.Fecha)            = :mes
          AND YEAR(v.Fecha)             = :anio
          AND DATE(v.Fecha)             < CURDATE()
          AND (v.Anulado IS NULL OR v.Anulado = 0)
        GROUP BY s.codigo
    ");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $mostrador_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // Total Monto del mes por sucursal, sin PedidosYa (denominador de Mostrador)
    $stmt = $conn->prepare("
        SELECT s.codigo AS cod_sucursal,
               SUM(v.Precio) AS total_monto
        FROM VentasGlobalesAccessCSV v
        JOIN sucursales s ON v.local = s.codigo
        WHERE MONTH(v.Fecha)     = :mes
          AND YEAR(v.Fecha)      = :anio
          AND DATE(v.Fecha)      < CURDATE()
          AND (v.Anulado IS NULL OR v.Anulado = 0)
          AND (v.Delivery_Nombre <> 'PedidosYa' OR v.Delivery_Nombre IS NULL)
        GROUP BY s.codigo
    ");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $total_cant_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // Global cant total (para totales de Mostrador)
    $total_cant_global = 0;

    // Reseñas de 5 estrellas por sucursal (Google Reviews)
    // JOIN: sucursales.cod_googlebusiness = ResenasGoogle.locationId
    // Meta: 12 reseñas FIVE estrellas = 100%
    $stmt = $conn->prepare("
        SELECT s.codigo AS cod_sucursal,
               COUNT(*)  AS cant_resenas_cinco
        FROM ResenasGoogle r
        JOIN sucursales s ON s.cod_googlebusiness = r.locationId
        WHERE r.starRating         = 'FIVE'
          AND MONTH(r.createTime)  = :mes
          AND YEAR(r.createTime)   = :anio
          AND DATE(r.createTime)   < CURDATE()
        GROUP BY s.codigo
    ");
    $stmt->execute([':mes' => $mes, ':anio' => $anio]);
    $resenas_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // ── Construir filas ───────────────────────────────────────────────────────

    $datos = [];
    $total_ventas_global = 0;
    $total_meta_global = 0;

    // Arrays para calcular totales de columnas
    $arr_limpieza = [];
    $arr_personal = [];
    $arr_servicio = [];
    $total_membresias_global = 0;
    $total_gigantona_global = 0;
    $total_tam_global = 0;
    $total_mostrador_num = 0;
    $total_resenas_global = 0;

    foreach ($sucursales as $cod => $nombre) {
        $limpieza = $limpieza_data[$cod][0] ?? ['promedio' => 0, 'cantidad' => 0];
        $personal = $personal_data[$cod][0] ?? ['promedio' => 0, 'cantidad' => 0];
        $servicio = $servicio_data[$cod][0] ?? ['promedio' => 0, 'cantidad' => 0];
        $kpi = $kpi_data[$cod][0] ?? ['reclamos_cantidad' => 0, 'reclamos_totales' => 0];
        $vm = $vm_data[$cod][0] ?? ['total_ventas' => 0];
        $vmt = $vmt_data[$cod][0] ?? ['total_meta' => 0];
        $membresia = $membresias_data[$cod][0] ?? ['total_membresias' => 0];
        $tamano = $tamano_data[$cod][0] ?? ['cant_gigantona' => 0, 'cant_total_tam' => 0];

        $cant_membresias = (int) ($membresia['total_membresias'] ?? 0);
        $pct_membresia = calcularPctMembresia($cant_membresias, $meta_acumulada_membresia);
        $total_membresias_global += $cant_membresias;

        $cant_gigantona = (float) ($tamano['cant_gigantona'] ?? 0);
        $cant_total_tam = (float) ($tamano['cant_total_tam'] ?? 0);
        $raw_tamano = $cant_total_tam > 0 ? ($cant_gigantona / $cant_total_tam) * 100 : 0;
        $pct_tamano = aplicarMeta($raw_tamano, TAMANO_NORMAL_META);
        $total_gigantona_global += $cant_gigantona;
        $total_tam_global += $cant_total_tam;

        // Mostrador
        $mostrador = $mostrador_data[$cod][0] ?? ['cant_mostrador' => 0];
        $total_cant_row = $total_cant_data[$cod][0] ?? ['total_cantidad' => 0];
        $cant_mostr = (float) ($mostrador['cant_mostrador'] ?? 0);
        $total_cant_suc = (float) ($total_cant_row['total_cantidad'] ?? 0);
        $total_cant_global += $total_cant_suc;

        // Reseñas Google
        $resena = $resenas_data[$cod][0] ?? ['cant_resenas_cinco' => 0];
        $cant_resenas = (int) ($resena['cant_resenas_cinco'] ?? 0);
        $pct_resenas = $meta_acumulada_resenas > 0 ? min(100, (int) round(($cant_resenas / $meta_acumulada_resenas) * 100)) : 0;
        $total_resenas_global += $cant_resenas;

        $total_vm = (float) ($vm['total_ventas'] ?? 0);
        $total_vmt = (float) ($vmt['total_meta'] ?? 0);
        $total_ventas_global += $total_vm;
        $total_meta_global += $total_vmt;

        // Pct Mostrador
        $raw_mostrador = $total_cant_suc > 0 ? ($cant_mostr / $total_cant_suc) * 100 : 0;
        $pct_mostrador = aplicarMeta($raw_mostrador, MOSTRADOR_META);
        $total_mostrador_num += $cant_mostr;

        $factor = $total_vmt > 0 ? ($total_vm / $total_vmt) * 100 : 0;

        // % auditorías (sin KPI ventas en el cálculo general)
        $pct_limpieza = escalaAPorcentaje($limpieza['promedio']);
        $pct_personal = escalaAPorcentaje($personal['promedio']);
        $pct_servicio = escalaAPorcentaje($servicio['promedio']);
        $pct_reclamos = calcularPorcentajeReclamos((int) $kpi['reclamos_cantidad']);

        // Colectar para totales
        if ($limpieza['cantidad'] > 0)
            $arr_limpieza[] = (float) $limpieza['promedio'];
        if ($personal['cantidad'] > 0)
            $arr_personal[] = (float) $personal['promedio'];
        if ($servicio['cantidad'] > 0)
            $arr_servicio[] = (float) $servicio['promedio'];

        // Desempeño de Tienda: promedio de las 8 métricas (con 1 decimal)
        $pct_desempeno = round((
            $pct_limpieza + $pct_personal + $pct_servicio +
            $pct_membresia + $pct_tamano + $pct_mostrador +
            $pct_resenas + $pct_reclamos
        ) / 8, 1);

        // Total %: Desempeño de Tienda × Cumplimiento de Ventas
        $total_pct = ($pct_desempeno * obtenerFactorVisual($factor)) / 100;

        $datos[$cod] = [
            'cod' => $cod,
            'nombre' => $nombre,
            '_sort_key' => $total_pct,
            // Limpieza
            'pct_limpieza' => $pct_limpieza,
            'limpieza_cantidad' => (int) $limpieza['cantidad'],
            'color_limpieza' => getColorClassPct($pct_limpieza),
            // Personal
            'pct_personal' => $pct_personal,
            'personal_cantidad' => (int) $personal['cantidad'],
            'color_personal' => getColorClassPct($pct_personal),
            // Servicio
            'pct_servicio' => $pct_servicio,
            'servicio_cantidad' => (int) $servicio['cantidad'],
            'color_servicio' => getColorClassPct($pct_servicio),
            // Membresías
            'cant_membresias' => $cant_membresias,
            'pct_membresia' => $pct_membresia,
            'color_membresia' => getColorClassPct($pct_membresia),
            // Tamaño Normal
            'cant_gigantona' => (int) $cant_gigantona,
            'cant_total_tam' => (int) $cant_total_tam,
            'pct_tamano' => $pct_tamano,
            'color_tamano' => getColorClassPct($pct_tamano),
            // Mostrador
            'pct_mostrador' => $pct_mostrador,
            'color_mostrador' => getColorClassPct($pct_mostrador),
            // Reseñas Google
            'cant_resenas' => $cant_resenas,
            'pct_resenas' => $pct_resenas,
            'color_resenas' => getColorClassPct($pct_resenas),
            // Reclamos
            'reclamos_cantidad' => (int) ($kpi['reclamos_cantidad'] ?? 0),
            'reclamos_totales' => (int) ($kpi['reclamos_totales'] ?? 0),
            'pct_reclamos' => $pct_reclamos,
            'color_reclamos' => getColorClassPct($pct_reclamos),
            // Desempeño de Tienda
            'pct_desempeno' => $pct_desempeno,
            'color_desempeno' => getColorClassPct($pct_desempeno),
            // Ventas
            'factor_visual' => round(obtenerFactorVisual($factor), 1),
            'total_porcentaje' => round($total_pct, 2),
            // Links
            'link_reclamos' => "index_reclamos_publico.php?sucursal=" . urlencode($cod) . "&mes=$mes&anio=$anio",
            'link_limpieza' => "ver_auditorias.php?sucursal=" . urlencode($cod) . "&tipo=limpieza&mes=$mes&anio=$anio",
            'link_personal' => "ver_auditorias.php?sucursal=" . urlencode($cod) . "&tipo=personal&mes=$mes&anio=$anio",
            'link_servicio' => "ver_auditorias.php?sucursal=" . urlencode($cod) . "&tipo=servicio&mes=$mes&anio=$anio",
        ];
    }

    // Ordenar de mayor a menor por _sort_key
    uasort($datos, fn($a, $b) => $b['_sort_key'] <=> $a['_sort_key']);
    $sucursales_list = array_values($datos);

    // ── Fila de totales ───────────────────────────────────────────────────────

    $prom_limp = count($arr_limpieza) > 0 ? array_sum($arr_limpieza) / count($arr_limpieza) : 0;
    $prom_pers = count($arr_personal) > 0 ? array_sum($arr_personal) / count($arr_personal) : 0;
    $prom_serv = count($arr_servicio) > 0 ? array_sum($arr_servicio) / count($arr_servicio) : 0;

    // Reclamos: promedio de los porcentajes individuales para consistencia visual
    $arr_reclamos_pct = [];
    foreach ($datos as $d) {
        $arr_reclamos_pct[] = $d['pct_reclamos'];
    }
    $reclamos_cant_tot = (int) ($kpi_total_row['reclamos_cantidad_total'] ?? 0);
    $reclamos_tot_tot = (int) ($kpi_total_row['reclamos_totales_total'] ?? 0);
    $pct_reclamos_tot = count($arr_reclamos_pct) > 0 ? (int) round(array_sum($arr_reclamos_pct) / count($arr_reclamos_pct)) : 100;
    $factor_tot = $total_meta_global > 0 ? ($total_ventas_global / $total_meta_global) * 100 : 0;

    // Desempeño de Tienda global
    $pct_limp_tot = escalaAPorcentaje($prom_limp);
    $pct_pers_tot = escalaAPorcentaje($prom_pers);
    $pct_serv_tot = escalaAPorcentaje($prom_serv);
    $pct_desempeno_total = round((
        $pct_limp_tot + $pct_pers_tot + $pct_serv_tot +
        $pct_membresia_total +
        aplicarMeta($total_tam_global > 0 ? ($total_gigantona_global / $total_tam_global) * 100 : 0, TAMANO_NORMAL_META) +
        aplicarMeta($total_cant_global > 0 ? ($total_mostrador_num / $total_cant_global) * 100 : 0, MOSTRADOR_META) +
        $pct_resenas_total +
        $pct_reclamos_tot
    ) / 8, 1);

    $total_pct_final = ($pct_desempeno_total * obtenerFactorVisual($factor_tot)) / 100;

    // Total membresías
    $num_sucursales_con_datos = count($sucursales);
    $meta_global_membresias_acum = $num_sucursales_con_datos * $meta_acumulada_membresia;
    $pct_membresia_total = $meta_global_membresias_acum > 0
        ? min(100, (int) round(($total_membresias_global / $meta_global_membresias_acum) * 100))
        : 0;

    // Total reseñas
    $meta_global_resenas_acum = $num_sucursales_con_datos * $meta_acumulada_resenas;
    $pct_resenas_total = $meta_global_resenas_acum > 0
        ? min(100, (int) round(($total_resenas_global / $meta_global_resenas_acum) * 100))
        : 0;

    $totales = [
        'pct_limpieza' => escalaAPorcentaje($prom_limp),
        'color_limpieza' => getColorClassPct(escalaAPorcentaje($prom_limp)),
        'tiene_limpieza' => count($arr_limpieza) > 0,
        'pct_personal' => escalaAPorcentaje($prom_pers),
        'color_personal' => getColorClassPct(escalaAPorcentaje($prom_pers)),
        'tiene_personal' => count($arr_personal) > 0,
        'pct_servicio' => escalaAPorcentaje($prom_serv),
        'color_servicio' => getColorClassPct(escalaAPorcentaje($prom_serv)),
        'tiene_servicio' => count($arr_servicio) > 0,
        // Membresías totales
        'cant_membresias' => $total_membresias_global,
        'pct_membresia' => $pct_membresia_total,
        'color_membresia' => getColorClassPct($pct_membresia_total),
        // Tamaño Normal totales (con meta 85%)
        'cant_gigantona' => (int) $total_gigantona_global,
        'cant_total_tam' => (int) $total_tam_global,
        'pct_tamano' => aplicarMeta(
            $total_tam_global > 0 ? ($total_gigantona_global / $total_tam_global) * 100 : 0,
            TAMANO_NORMAL_META
        ),
        'color_tamano' => getColorClassPct(aplicarMeta(
            $total_tam_global > 0 ? ($total_gigantona_global / $total_tam_global) * 100 : 0,
            TAMANO_NORMAL_META
        )),
        // Mostrador totales (con meta 8%)
        'pct_mostrador' => aplicarMeta(
            $total_cant_global > 0 ? ($total_mostrador_num / $total_cant_global) * 100 : 0,
            MOSTRADOR_META
        ),
        'color_mostrador' => getColorClassPct(aplicarMeta(
            $total_cant_global > 0 ? ($total_mostrador_num / $total_cant_global) * 100 : 0,
            MOSTRADOR_META
        )),
        // Reseñas totales
        'cant_resenas' => $total_resenas_global,
        'pct_resenas' => $pct_resenas_total,
        'color_resenas' => getColorClassPct($pct_resenas_total),
        // Reclamos
        'reclamos_cantidad' => $reclamos_cant_tot,
        'reclamos_totales' => $reclamos_tot_tot,
        'pct_reclamos' => $pct_reclamos_tot,
        'color_reclamos' => getColorClassPct($pct_reclamos_tot),
        // Desempeño de Tienda
        'pct_desempeno' => $pct_desempeno_total,
        'color_desempeno' => getColorClassPct($pct_desempeno_total),
        'factor_visual' => round(obtenerFactorVisual($factor_tot), 1),
        'total_porcentaje' => round($total_pct_final, 2),
    ];

    echo json_encode([
        'success' => true,
        'sucursales' => $sucursales_list,
        'totales' => $totales,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>