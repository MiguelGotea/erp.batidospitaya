<?php
/* ============================================================
   AJAX: Obtener filtros del dashboard de consumo
   modulos/productos/ajax/dashboard_consumo_get_filtros.php
   Devuelve: semanas (SemanasSistema), sucursales activas,
             insumos ERP que aparecen en SubReceta mapeada
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_consumo_insumos', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}

try {
    // ── Semanas del sistema (últimas 52 + próximas 4) ──────────────────
    $stmtSem = $conn->prepare("
        SELECT ss.id, ss.numero_semana, ss.anio, ss.fecha_inicio, ss.fecha_fin
        FROM SemanasSistema ss
        ORDER BY ss.anio DESC, ss.numero_semana DESC
        LIMIT 80
    ");
    $stmtSem->execute();
    $semanas = $stmtSem->fetchAll(PDO::FETCH_ASSOC);

    // Formatear para el select
    $semanasFormateadas = array_map(function ($s) {
        return [
            'id'    => $s['id'],
            'label' => 'Sem ' . $s['numero_semana'] . ' / ' . $s['anio']
                     . ' (' . date('d/M', strtotime($s['fecha_inicio'])) . '–' . date('d/M', strtotime($s['fecha_fin'])) . ')',
            'numero_semana' => (int)$s['numero_semana'],
            'anio'          => (int)$s['anio'],
            'fecha_inicio'  => $s['fecha_inicio'],
            'fecha_fin'     => $s['fecha_fin'],
        ];
    }, $semanas);

    // ── Sucursales activas ─────────────────────────────────────────────
    $stmtSuc = $conn->prepare("
        SELECT s.codigo, s.nombre
        FROM sucursales s
        WHERE s.activa = 1
          AND s.sucursal = 1
        ORDER BY s.nombre ASC
    ");
    $stmtSuc->execute();
    $sucursales = $stmtSuc->fetchAll(PDO::FETCH_ASSOC);

    // ── Insumos ERP que aparecem en SubReceta (con mapeo) ─────────────
    // Solo los que están en el diccionario (presentaciones simples + globales)
    $stmtIns = $conn->prepare("
        SELECT DISTINCT
            pp.id,
            CONCAT(pm.Nombre, ' — ', pp.Nombre) AS nombre_completo,
            pp.Nombre,
            u.nombre AS unidad,
            CASE WHEN pp.Id_receta_producto IS NOT NULL THEN 1 ELSE 0 END AS es_global
        FROM SubReceta sr
        LEFT JOIN Cotizaciones c ON c.CodIngrediente = sr.CodIngrediente
            AND c.Conversion = 1
            AND (c.Subproducto IS NULL OR c.Subproducto != 1)
            AND (c.Marca IS NULL OR c.Marca != 'Almacen Global')
        LEFT JOIN diccionario_productos_legado d
            ON d.CodCotizacion = COALESCE(sr.codporcion, c.CodCotizacion)
        INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
        LEFT  JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
        LEFT  JOIN unidad_producto u ON u.id = pp.id_unidad_producto
        WHERE d.id IS NOT NULL
          AND pp.Activo = 'SI'
        ORDER BY pm.Nombre ASC, pp.Nombre ASC
    ");
    $stmtIns->execute();
    $insumos = $stmtIns->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'        => true,
        'semanas'   => $semanasFormateadas,
        'sucursales'=> $sucursales,
        'insumos'   => $insumos,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al obtener filtros: ' . $e->getMessage()]);
}
