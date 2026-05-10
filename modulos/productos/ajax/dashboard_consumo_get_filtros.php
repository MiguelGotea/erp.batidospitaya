<?php
/* ============================================================
   AJAX: Obtener filtros del dashboard de consumo
   modulos/productos/ajax/dashboard_consumo_get_filtros.php
   Devuelve: semana_actual (basada en CURDATE), sucursales activas,
             insumos ERP mapeados desde SubReceta
   ============================================================ */
require_once '../../../core/auth/auth.php';
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
    // ── Semana actual: la semana que contiene la fecha de hoy ─────────
    $stmtSemActual = $conn->prepare("
        SELECT
            ss.numero_semana,
            ss.anio,
            ss.fecha_inicio,
            ss.fecha_fin
        FROM SemanasSistema ss
        WHERE CURDATE() BETWEEN ss.fecha_inicio AND ss.fecha_fin
        ORDER BY ss.fecha_inicio DESC
        LIMIT 1
    ");
    $stmtSemActual->execute();
    $semanaActual = $stmtSemActual->fetch(PDO::FETCH_ASSOC);

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

    // ── Insumos ERP mapeados desde SubReceta ──────────────────────────
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
        'ok'           => true,
        'semana_actual' => $semanaActual ? [
            'numero_semana' => (int)$semanaActual['numero_semana'],
            'anio'          => (int)$semanaActual['anio'],
            'fecha_inicio'  => $semanaActual['fecha_inicio'],
            'fecha_fin'     => $semanaActual['fecha_fin'],
        ] : null,
        'sucursales' => $sucursales,
        'insumos'    => $insumos,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al obtener filtros: ' . $e->getMessage()]);
}
