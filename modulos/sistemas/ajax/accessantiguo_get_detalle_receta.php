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
require_once 'accessantiguo_unidades_homologacion.php';

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

        // 2) Traducción al nuevo ERP
        $ingr['nuevo_producto'] = null;
        $ingr['metodo_resolucion'] = 'ninguno';

        // Intentar resolución directa primero (si hay CodCotizacion)
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
                    pm.id       AS id_maestro,
                    pm.Nombre   AS productoMaestro
                FROM diccionario_productos_legado d
                INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
                LEFT JOIN unidad_producto u          ON u.id = pp.id_unidad_producto
                LEFT JOIN producto_maestro pm        ON pm.id = pp.id_producto_maestro
                WHERE d.CodCotizacion = :cot
                LIMIT 1
            ");
            $stmtDic->execute([':cot' => $codCotizacion]);
            $res = $stmtDic->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                $ingr['nuevo_producto'] = $res;
                $ingr['metodo_resolucion'] = 'directo';
            }
        }

        // Si no se resolvió directo (o no había codporcion), intentar rastreo por Maestro + Unidad
        if (!$ingr['nuevo_producto']) {
            /**
             * LÓGICA DE RASTREO AUTOMÁTICO:
             * 1. Buscar cualquier Cotización del CodIngrediente que esté mapeada a un producto NO receta.
             * 2. Obtener el id_producto_maestro de ese producto.
             * 3. Buscar en el nuevo ERP una presentación de ese Maestro con la misma unidad (homologada).
             */

            // Pasos 1 y 2: Buscar Maestro vía cualquier cotización mapeada
            $stmtMaster = $conn->prepare("
                SELECT pp.id_producto_maestro, pp.Nombre as nombre_mapeado
                FROM Cotizaciones c
                INNER JOIN diccionario_productos_legado d ON d.CodCotizacion = c.CodCotizacion
                INNER JOIN producto_presentacion pp       ON pp.id = d.id_producto_presentacion
                WHERE c.CodIngrediente = :ci 
                  AND pp.Id_receta_producto IS NULL
                LIMIT 1
            ");
            $stmtMaster->execute([':ci' => $codIngrediente]);
            $masterInfo = $stmtMaster->fetch(PDO::FETCH_ASSOC);
            $idMaestro = $masterInfo['id_producto_maestro'] ?? null;

            if ($idMaestro) {
                // Homologación de unidades vía librería
                $unidadesBusqueda = obtenerUnidadesERPSimilares($ingr['UnidadIngrediente']);

                // Paso 4: Buscar presentación por Maestro + Unidad (que no sea receta)
                $placeholders = implode(',', array_fill(0, count($unidadesBusqueda), '?'));
                $sqlFinal = "
                    SELECT 
                        pp.id       AS id_presentacion,
                        pp.SKU,
                        pp.Nombre   AS NombreNuevo,
                        pp.cantidad,
                        pp.Activo   AS activoNuevo,
                        u.Nombre    AS unidadNueva,
                        pm.Nombre   AS productoMaestro
                    FROM producto_presentacion pp
                    INNER JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
                    LEFT JOIN unidad_producto u    ON u.id = pp.id_unidad_producto
                    WHERE pp.id_producto_maestro = ?
                      AND u.Nombre IN ($placeholders)
                      AND pp.Id_receta_producto IS NULL
                      AND pp.Activo = 'SI'
                    LIMIT 1
                ";
                $stmtFinal = $conn->prepare($sqlFinal);
                $stmtFinal->execute(array_merge([$idMaestro], $unidadesBusqueda));
                $resAuto = $stmtFinal->fetch(PDO::FETCH_ASSOC);

                if ($resAuto) {
                    $ingr['nuevo_producto'] = $resAuto;
                    $ingr['metodo_resolucion'] = 'maestro';
                } else {
                    error_log("AUTO-RES FAIL: No presentation found for Master $idMaestro with units " . implode(',', $unidadesBusqueda));
                }
            } else {
                error_log("AUTO-RES FAIL: No master found for Ingredient $codIngrediente via mapped cotizaciones");
            }
        }

        // 3) Obtener variedades si se encontró producto
        if ($ingr['nuevo_producto']) {
            $stmtVar = $conn->prepare("
                SELECT id, nombre, es_principal
                FROM variedad_producto_presentacion
                WHERE id_presentacion_producto = :idp
                ORDER BY es_principal DESC, nombre ASC
            ");
            $stmtVar->execute([':idp' => $ingr['nuevo_producto']['id_presentacion']]);
            $ingr['nuevo_producto']['variedades'] = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
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