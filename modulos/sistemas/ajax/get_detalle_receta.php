<?php
/**
 * get_detalle_receta.php
 * Retorna el detalle completo de una receta de SubReceta con:
 *   - Datos del ingrediente (DBIngredientes)
 *   - Presentación resuelta (Cotizaciones):
 *       si codporcion != NULL  → codporcion IS CodCotizacion
 *       si codporcion IS NULL  → buscar en Cotizaciones por CodIngrediente donde Conversion = 1
 *   - Traducción al nuevo ERP (diccionario_productos_legado → producto_presentacion)
 *
 * GET params:
 *   cod_batido => varchar (CodBatido de DBBatidos)
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $usuario = obtenerUsuarioActual();
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    if (!tienePermiso('visor_recetas', 'vista', $usuario['CodNivelesCargos'])) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso']);
        exit;
    }

    $codBatido = trim($_GET['cod_batido'] ?? '');
    if ($codBatido === '') {
        echo json_encode(['success' => false, 'message' => 'CodBatido requerido']);
        exit;
    }

    // ── Datos del producto ────────────────────────────────────────────────
    $stmtBatido = $conn->prepare("
        SELECT
            b.CodBatido, b.Nombre, b.Medida, b.Precio,
            b.Vigencia, b.Marca, b.CodigoBarras,
            b.CodGrupo, b.CodSubGrupo,
            g.NombreGrupo
        FROM DBBatidos b
        LEFT JOIN GrupoProductosVenta g ON g.CodGrupo = b.CodGrupo
        WHERE b.CodBatido = :cb
        LIMIT 1
    ");
    $stmtBatido->execute([':cb' => $codBatido]);
    $batido = $stmtBatido->fetch(PDO::FETCH_ASSOC);

    if (!$batido) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit;
    }

    // ── Ingredientes de la receta ─────────────────────────────────────────
    $stmtIngr = $conn->prepare("
        SELECT
            sr.CodSubReceta,
            sr.CodIngrediente,
            sr.CodBatido,
            sr.Cantidad,
            sr.Tipo,
            sr.codporcion,
            sr.InsumoClave,
            sr.tiposervido,
            sr.ordenreceta,
            i.Nombre         AS NombreIngrediente,
            i.Unidad         AS UnidadIngrediente,
            i.Tipo           AS TipoIngrediente,
            i.Vigente        AS VigenteIngrediente
        FROM SubReceta sr
        LEFT JOIN DBIngredientes i ON i.CodIngrediente = sr.CodIngrediente
        WHERE sr.CodBatido = :cb
        ORDER BY sr.Tipo ASC, sr.ordenreceta ASC, sr.CodSubReceta ASC
    ");
    $stmtIngr->execute([':cb' => $codBatido]);
    $ingredientes = $stmtIngr->fetchAll(PDO::FETCH_ASSOC);

    // ── Resolver presentación + traducción para cada ingrediente ──────────
    foreach ($ingredientes as &$ingr) {
        $codIngrediente = $ingr['CodIngrediente'];
        $codporcion = $ingr['codporcion'];

        // 1) Resolver CodCotizacion
        if ($codporcion !== null && $codporcion > 0) {
            // codporcion ES el CodCotizacion directamente
            $stmtCot = $conn->prepare("
                SELECT CodCotizacion, Marca, Linea, Capacidad, Unidad, Conversion
                FROM Cotizaciones
                WHERE CodCotizacion = :cc
                LIMIT 1
            ");
            $stmtCot->execute([':cc' => $codporcion]);
        } else {
            // Buscar en Cotizaciones el registro base (Conversion = 1) del ingrediente
            $stmtCot = $conn->prepare("
                SELECT CodCotizacion, Marca, Linea, Capacidad, Unidad, Conversion
                FROM Cotizaciones
                WHERE CodIngrediente = :ci AND Conversion = 1
                LIMIT 1
            ");
            $stmtCot->execute([':ci' => $codIngrediente]);
        }
        $cotizacion = $stmtCot->fetch(PDO::FETCH_ASSOC);

        $ingr['cotizacion'] = $cotizacion ?: null;
        $codCotizacion = $cotizacion['CodCotizacion'] ?? null;

        // 2) Traducción al nuevo ERP via diccionario_productos_legado
        $ingr['nuevo_producto'] = null;
        if ($codCotizacion) {
            $stmtDic = $conn->prepare("
                SELECT
                    d.id        AS mapeo_id,
                    pp.id       AS id_presentacion,
                    pp.SKU,
                    pp.Nombre   AS NombreNuevo,
                    pp.cantidad,
                    pp.Activo   AS activoNuevo,
                    u.Nombre    AS unidadNueva,
                    pm.Nombre   AS productoMaestro
                FROM diccionario_productos_legado d
                INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
                LEFT JOIN unidad_producto u          ON u.id = pp.id_unidad_producto
                LEFT JOIN producto_maestro pm        ON pm.id = pp.id_producto_maestro
                WHERE d.CodCotizacion = :cot
                LIMIT 1
            ");
            $stmtDic->execute([':cot' => $codCotizacion]);
            $ingr['nuevo_producto'] = $stmtDic->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }
    unset($ingr); // romper referencia

    echo json_encode([
        'success' => true,
        'batido' => $batido,
        'ingredientes' => $ingredientes,
    ]);

} catch (Exception $e) {
    error_log("Error en get_detalle_receta.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener receta']);
}
?>