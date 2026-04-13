<?php
/* ============================================================
   AJAX: Exportar consumo de insumos a CSV
   modulos/productos/ajax/dashboard_consumo_exportar.php
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_consumo_insumos', 'exportar_consumo', $cargoOperario)) {
    http_response_code(403);
    echo 'Sin permiso de exportación.';
    exit();
}

// Recibir datos JSON desde POST
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true);

$consumo    = $data['consumo']    ?? [];
$semanas    = $data['semanas']    ?? [];
$sinMapeo   = $data['sin_mapeo'] ?? [];
$semDesde   = $data['sem_desde'] ?? '';
$semHasta   = $data['sem_hasta'] ?? '';
$modo       = $data['modo']      ?? 'historial'; // historial | proyeccion | sin_mapeo

// Headers para descarga CSV
$fecha     = date('Y-m-d');
$filename  = "consumo_insumos_sem{$semDesde}_a_{$semHasta}_{$fecha}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// BOM UTF-8 para Excel
fputs($out, "\xEF\xBB\xBF");

if ($modo === 'historial') {
    // Encabezado
    fputcsv($out, [
        'Insumo ERP',
        'Producto Maestro',
        'Unidad',
        'Tipo',
        'Consumo Total',
        'Prom/Semana',
        'Semana Pico (Nro)',
        'Consumo Semana Pico',
        'Proyección 4 Semanas',
        'Stock Mínimo (1 sem)',
        'Stock Máximo (2 sem)',
        'Tendencia',
    ]);

    foreach ($consumo as $item) {
        fputcsv($out, [
            $item['nombre'],
            $item['maestro'],
            $item['unidad'],
            $item['es_global'] ? 'Receta Global' : 'Insumo Simple',
            number_format($item['total'], 4, '.', ''),
            number_format($item['prom_semana'], 4, '.', ''),
            $item['semana_pico_num'] ?? '',
            number_format($item['max_consumo_sem'] ?? 0, 4, '.', ''),
            number_format($item['proyeccion_4sem'], 4, '.', ''),
            number_format($item['stock_min'], 4, '.', ''),
            number_format($item['stock_max'], 4, '.', ''),
            $item['tendencia'] === 'up' ? '↑ Creciente' : ($item['tendencia'] === 'down' ? '↓ Decreciente' : '→ Estable'),
        ]);
    }

    // Separador
    fputcsv($out, []);
    fputcsv($out, ['=== INSUMOS SIN MAPEO ERP ===']);
    fputcsv($out, ['CodIngrediente', 'Nombre Ingrediente', 'Unidad Access', 'Productos Afectados', 'Ventas Afectadas']);
    foreach ($sinMapeo as $sm) {
        fputcsv($out, [
            $sm['cod_ingrediente'],
            $sm['nombre'],
            $sm['unidad_access'],
            $sm['num_productos'],
            $sm['ventas_afectadas'],
        ]);
    }

} elseif ($modo === 'proyeccion') {
    fputcsv($out, [
        'Insumo ERP',
        'Unidad',
        'Prom/Semana',
        'Proyección 4 Semanas',
        'Stock Mínimo',
        'Stock Máximo',
        'Semana Pico',
        'Semana Baja',
        'Tendencia',
    ]);
    foreach ($consumo as $item) {
        fputcsv($out, [
            $item['nombre'],
            $item['unidad'],
            number_format($item['prom_semana'], 4, '.', ''),
            number_format($item['proyeccion_4sem'], 4, '.', ''),
            number_format($item['stock_min'], 4, '.', ''),
            number_format($item['stock_max'], 4, '.', ''),
            $item['semana_pico_num'] ?? '',
            $item['semana_low_num'] ?? '',
            $item['tendencia'] === 'up' ? 'Creciente' : ($item['tendencia'] === 'down' ? 'Decreciente' : 'Estable'),
        ]);
    }

} elseif ($modo === 'sin_mapeo') {
    fputcsv($out, ['CodIngrediente', 'Nombre Ingrediente', 'Unidad Access', 'Productos Afectados', 'Ventas Afectadas']);
    foreach ($sinMapeo as $sm) {
        fputcsv($out, [
            $sm['cod_ingrediente'],
            $sm['nombre'],
            $sm['unidad_access'],
            $sm['num_productos'],
            $sm['ventas_afectadas'],
        ]);
    }
}

fclose($out);
