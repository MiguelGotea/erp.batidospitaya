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
            i.Nombre                AS NombreIngrediente,
            i.Unidad                AS UnidadIngrediente,
            i.Tipo                  AS TipoIngrediente,
            i.Vigente               AS VigenteIngrediente,
            i.presentacionpreparacion,
            i.conversionpreparacion,
            CASE WHEN mpa.CodCotizacionPorcion IS NOT NULL THEN 1 ELSE 0 END AS es_artilugio
        FROM SubReceta sr
        LEFT JOIN DBIngredientes i ON i.CodIngrediente = sr.CodIngrediente
        LEFT JOIN (SELECT DISTINCT CodCotizacionPorcion FROM MezclaPorcionesAccess) mpa ON mpa.CodCotizacionPorcion = sr.codporcion
        WHERE sr.CodBatido = :cb
        ORDER BY sr.Tipo ASC, sr.ordenreceta ASC, sr.CodSubReceta ASC
    ");
    $stmtIngr->execute([':cb' => $codBatido]);
    $ingredientes = $stmtIngr->fetchAll(PDO::FETCH_ASSOC);

    // ── Resolver presentación + traducción para cada ingrediente ──────────
    foreach ($ingredientes as &$ingr) {
        $codIngrediente = $ingr['CodIngrediente'];
        $codporcion = $ingr['codporcion'];

        // Si es un componente de artilugio, no se mapea
        if ((int) $ingr['es_artilugio'] === 1) {
            $ingr['cotizacion'] = null;
            $ingr['metodo_cotizacion'] = 'artilugio';
            $ingr['nuevo_producto'] = null;
            $ingr['metodo_resolucion'] = 'ninguno';
            $ingr['presentacion_despacho'] = null;
            $ingr['insumo_receta'] = null;
            $ingr['escenario_erp'] = 'sin_mapeo';
            continue;
        }


        $cotizacion = null;
        $ingr['metodo_cotizacion'] = null;

        // 1) Prioridad 1: codporcion ES el CodCotizacion directamente
        if ($codporcion !== null && $codporcion > 0) {
            $stmtCot = $conn->prepare("
                SELECT CodCotizacion, Marca, Linea, Capacidad, Unidad, Conversion
                FROM Cotizaciones
                WHERE CodCotizacion = :cc
                LIMIT 1
            ");
            $stmtCot->execute([':cc' => $codporcion]);
            $cotizacion = $stmtCot->fetch(PDO::FETCH_ASSOC);
            if ($cotizacion) {
                $ingr['metodo_cotizacion'] = 'directa';
            }
        } else {
            // 2) Prioridad 2: registro base del ingrediente con Conversion = 1 y Prioridad = 1
            //    (excluye subproductos y Almacen Global, igual que Prioridad 3)
            $stmtCot = $conn->prepare("
                SELECT CodCotizacion, Marca, Linea, Capacidad, Unidad, Conversion
                FROM Cotizaciones
                WHERE CodIngrediente = :ci
                  AND Conversion = 1
                  AND Prioridad = 1
                  AND (Subproducto IS NULL OR Subproducto != 1)
                  AND (Marca IS NULL OR Marca != 'Almacen Global')
                LIMIT 1
            ");
            $stmtCot->execute([':ci' => $codIngrediente]);
            $cotizacion = $stmtCot->fetch(PDO::FETCH_ASSOC);
            if ($cotizacion) {
                $ingr['metodo_cotizacion'] = 'conversion1';
            } else {
                // 3) Prioridad 3: cotización prioritaria (sin subproducto, sin Almacen Global, Prioridad=1)
                $stmtCot3 = $conn->prepare("
                    SELECT CodCotizacion, Marca, Linea, Capacidad, Unidad, Conversion
                    FROM Cotizaciones
                    WHERE CodIngrediente = :ci
                      AND (Subproducto IS NULL OR Subproducto != 1)
                      AND (Marca IS NULL OR Marca != 'Almacen Global')
                      AND Prioridad = 1
                    LIMIT 1
                ");
                $stmtCot3->execute([':ci' => $codIngrediente]);
                $cotizacion = $stmtCot3->fetch(PDO::FETCH_ASSOC);
                if ($cotizacion) {
                    $ingr['metodo_cotizacion'] = 'prioritaria';
                }
            }
        }

        $ingr['cotizacion'] = $cotizacion ?: null;
        $codCotizacion = $cotizacion['CodCotizacion'] ?? null;

        // 2) Traducción al nuevo ERP
        $ingr['nuevo_producto'] = null;
        $ingr['metodo_resolucion'] = 'ninguno';


        // Intentar resolución directa primero (si hay CodCotizacion)
        if ($codCotizacion) {
            $stmtDic = $conn->prepare("
                SELECT
                    d.id                  AS mapeo_id,
                    pp.id                 AS id_presentacion,
                    pp.SKU,
                    pp.Nombre             AS NombreNuevo,
                    pp.cantidad,
                    pp.Activo             AS activoNuevo,
                    pp.Id_receta_producto  AS id_receta_producto,
                    u.Nombre              AS unidadNueva,
                    pm.id                 AS id_maestro,
                    pm.Nombre             AS productoMaestro,
                    pp.presentacion_receta,
                    pp.presentacion_basica_inventario,
                    pp.presentacion_despacho
                FROM diccionario_productos_legado d
                INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
                LEFT JOIN unidad_producto u          ON u.id = pp.id_unidad_producto
                LEFT JOIN producto_maestro pm        ON pm.id = pp.id_producto_maestro
                WHERE d.CodCotizacion = :cot
                  AND pp.presentacion_basica_inventario = 1
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
                // Homologación de unidades vía DB dinámica
                $resAuto = null;
                $resUnidad = resolverUnidadERP($conn, $ingr['UnidadIngrediente']);

                if ($resUnidad) {
                    // Buscar presentación con unidad directa del ingrediente
                    $resAuto = buscarPresentacionPorUnidades($conn, $idMaestro, $resUnidad['directos'], 'inventario');

                    // Si no hay directa, buscar con unidades convertibles
                    if (!$resAuto && !empty($resUnidad['convertibles'])) {
                        $resAuto = buscarPresentacionPorUnidades($conn, $idMaestro, $resUnidad['convertibles'], 'inventario');
                    }
                }

                // Fallback: buscar cualquier presentación activa no-receta del maestro
                if (!$resAuto) {
                    $stmtAny = $conn->prepare("
                        SELECT
                            pp.id       AS id_presentacion,
                            pp.SKU,
                            pp.Nombre   AS NombreNuevo,
                            pp.cantidad,
                            pp.Activo   AS activoNuevo,
                            u.nombre    AS unidadNueva,
                            pm.id       AS id_maestro,
                            pm.Nombre   AS productoMaestro,
                            pp.presentacion_receta,
                            pp.presentacion_basica_inventario,
                            pp.presentacion_despacho
                        FROM producto_presentacion pp
                        INNER JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
                        LEFT JOIN unidad_producto u    ON u.id  = pp.id_unidad_producto
                        WHERE pp.id_producto_maestro = ?
                          AND pp.Id_receta_producto IS NULL
                          AND pp.Activo = 'SI'
                          AND pp.presentacion_basica_inventario = 1
                        LIMIT 1
                    ");
                    $stmtAny->execute([$idMaestro]);
                    $resAuto = $stmtAny->fetch(PDO::FETCH_ASSOC) ?: null;
                }

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

        // 4) Obtener Presentación Despacho
        $ingr['presentacion_despacho'] = null;
        $idMaestroResolucion = $ingr['nuevo_producto']['id_maestro'] ?? null;
        $esPorcion = ($ingr['codporcion'] !== null && (int) $ingr['codporcion'] > 0);

        // ── Lógica por tipo de ingrediente ────────────────────────────────────────
        //
        // PORCIÓN (codporcion > 0):
        //   1º  Fallback 2: buscar receta-paquete cuyo único componente sea la Presentación Uso.
        //       → Ej: Fresa Congelada 2oz → Fresa paquete 10 unid  ✓
        //   2º  Si no existe paquete (ej: Banano congelado que se convierte en tienda),
        //       caer al flujo normal por unidades (Nivel 1 → Nivel 2 → Fallback 1).
        //
        // NO PORCIÓN:
        //   1º  Flujo normal por unidades (Nivel 1 → Nivel 2 → Fallback 1).
        //   2º  Si no encuentra, intentar Fallback 2 (receta-paquete).

        // --- Paso A: para porciones, intentar primero la receta-paquete ---
        if ($esPorcion) {
            $idPresentacionUso = $ingr['nuevo_producto']['id_presentacion'] ?? null;
            if ($idPresentacionUso) {
                $stmtPkgA = $conn->prepare("
                    SELECT
                        pp.id       AS id_presentacion,
                        pp.SKU,
                        pp.Nombre   AS NombreNuevo,
                        pp.cantidad,
                        pp.Activo   AS activoNuevo,
                        u.nombre    AS unidadNueva,
                        pm.id       AS id_maestro,
                        pm.Nombre   AS productoMaestro,
                        pp.presentacion_receta,
                        pp.presentacion_basica_inventario,
                        pp.presentacion_despacho
                    FROM producto_presentacion pp
                    LEFT JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
                    LEFT JOIN unidad_producto u   ON u.id  = pp.id_unidad_producto
                    WHERE pp.Id_receta_producto IS NOT NULL
                      AND pp.Activo = 'SI'
                      AND pp.presentacion_despacho = 1
                      AND (
                          SELECT COUNT(DISTINCT crp.id_presentacion_producto)
                          FROM componentes_receta_producto crp
                          WHERE crp.id_receta_producto_global = pp.Id_receta_producto
                      ) = 1
                      AND EXISTS (
                          SELECT 1
                          FROM componentes_receta_producto crp2
                          WHERE crp2.id_receta_producto_global = pp.Id_receta_producto
                            AND crp2.id_presentacion_producto = ?
                      )
                    LIMIT 1
                ");
                $stmtPkgA->execute([$idPresentacionUso]);
                $ingr['presentacion_despacho'] = $stmtPkgA->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }

        // --- Paso B: flujo normal por unidades ---
        // Se ejecuta si: (a) no es porción, O (b) es porción pero no encontró receta-paquete.
        if (!$ingr['presentacion_despacho'] && $idMaestroResolucion) {
            // Nivel 1: unidad directa + despacho=1 (mismo maestro)
            $resolucionU = resolverUnidadERP($conn, $ingr['UnidadIngrediente']);
            if ($resolucionU) {
                $ingr['presentacion_despacho'] = buscarPresentacionPorUnidades(
                    $conn,
                    $idMaestroResolucion,
                    $resolucionU['directos'],
                    'despacho'
                );

                // Nivel 2: unidad convertible + despacho=1 (mismo maestro)
                if (!$ingr['presentacion_despacho'] && !empty($resolucionU['convertibles'])) {
                    $ingr['presentacion_despacho'] = buscarPresentacionPorUnidades(
                        $conn,
                        $idMaestroResolucion,
                        $resolucionU['convertibles'],
                        'despacho'
                    );
                }
            }

            // Fallback 1: cualquier presentación de despacho del mismo maestro (sin restricción de unidad).
            if (!$ingr['presentacion_despacho']) {
                $stmtAnyD = $conn->prepare("
                    SELECT
                        pp.id       AS id_presentacion,
                        pp.SKU,
                        pp.Nombre   AS NombreNuevo,
                        pp.cantidad,
                        pp.Activo   AS activoNuevo,
                        u.nombre    AS unidadNueva,
                        pm.id       AS id_maestro,
                        pm.Nombre   AS productoMaestro,
                        pp.presentacion_receta,
                        pp.presentacion_basica_inventario,
                        pp.presentacion_despacho
                    FROM producto_presentacion pp
                    LEFT JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
                    LEFT JOIN unidad_producto u    ON u.id  = pp.id_unidad_producto
                    WHERE pp.id_producto_maestro = ?
                      AND pp.Activo = 'SI'
                      AND pp.presentacion_despacho = 1
                    LIMIT 1
                ");
                $stmtAnyD->execute([$idMaestroResolucion]);
                $ingr['presentacion_despacho'] = $stmtAnyD->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }

        // --- Paso C: Fallback 2 para NO porciones (ya se ejecutó en Paso A para porciones) ---
        // Busca receta-paquete de despacho cuyo único componente sea la Presentación Uso exacta.
        if (!$ingr['presentacion_despacho'] && !$esPorcion) {
            $idPresentacionUso = $ingr['nuevo_producto']['id_presentacion'] ?? null;
            if ($idPresentacionUso) {
                $stmtPkgB = $conn->prepare("
                    SELECT
                        pp.id       AS id_presentacion,
                        pp.SKU,
                        pp.Nombre   AS NombreNuevo,
                        pp.cantidad,
                        pp.Activo   AS activoNuevo,
                        u.nombre    AS unidadNueva,
                        pm.id       AS id_maestro,
                        pm.Nombre   AS productoMaestro,
                        pp.presentacion_receta,
                        pp.presentacion_basica_inventario,
                        pp.presentacion_despacho
                    FROM producto_presentacion pp
                    LEFT JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
                    LEFT JOIN unidad_producto u   ON u.id  = pp.id_unidad_producto
                    WHERE pp.Id_receta_producto IS NOT NULL
                      AND pp.Activo = 'SI'
                      AND pp.presentacion_despacho = 1
                      AND (
                          SELECT COUNT(DISTINCT crp.id_presentacion_producto)
                          FROM componentes_receta_producto crp
                          WHERE crp.id_receta_producto_global = pp.Id_receta_producto
                      ) = 1
                      AND EXISTS (
                          SELECT 1
                          FROM componentes_receta_producto crp2
                          WHERE crp2.id_receta_producto_global = pp.Id_receta_producto
                            AND crp2.id_presentacion_producto = ?
                      )
                    LIMIT 1
                ");
                $stmtPkgB->execute([$idPresentacionUso]);
                $ingr['presentacion_despacho'] = $stmtPkgB->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }

        // --- Paso D: Fallback 3 — receta-paquete de despacho cuyo componente es cualquier
        //     presentación del mismo maestro (cubre el caso donde la cajilla de despacho está
        //     registrada como receta con un componente distinto a la presentación de uso).
        //     Ej: Naranja oz (uso) → Cajilla 100u (despacho, receta con componente "Naranja Unidad").
        if (!$ingr['presentacion_despacho'] && $idMaestroResolucion) {
            $stmtPkgD = $conn->prepare("
                SELECT
                    pp.id       AS id_presentacion,
                    pp.SKU,
                    pp.Nombre   AS NombreNuevo,
                    pp.cantidad,
                    pp.Activo   AS activoNuevo,
                    u.nombre    AS unidadNueva,
                    pm.id       AS id_maestro,
                    pm.Nombre   AS productoMaestro,
                    pp.presentacion_receta,
                    pp.presentacion_basica_inventario,
                    pp.presentacion_despacho
                FROM producto_presentacion pp
                LEFT JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
                LEFT JOIN unidad_producto u   ON u.id  = pp.id_unidad_producto
                WHERE pp.Id_receta_producto IS NOT NULL
                  AND pp.Activo = 'SI'
                  AND pp.presentacion_despacho = 1
                  AND EXISTS (
                      SELECT 1
                      FROM componentes_receta_producto crp
                      INNER JOIN producto_presentacion pp_comp
                          ON pp_comp.id = crp.id_presentacion_producto
                      WHERE crp.id_receta_producto_global = pp.Id_receta_producto
                        AND pp_comp.id_producto_maestro = ?
                  )
                LIMIT 1
            ");
            $stmtPkgD->execute([$idMaestroResolucion]);
            $ingr['presentacion_despacho'] = $stmtPkgD->fetch(PDO::FETCH_ASSOC) ?: null;
        }


        // 5) Determinar escenario ERP e Insumo Receta (para las 3 columnas de Nuevo Sistema)
        $ingr['insumo_receta'] = null;
        $ingr['escenario_erp'] = 'sin_mapeo';

        if ($ingr['nuevo_producto']) {

            // ── Caso especial: Receta Global ─────────────────────────────────────
            // producto_presentacion.Id_receta_producto IS NOT NULL → es un compuesto.
            // Sus campos cantidad / id_unidad_producto / id_producto_maestro son NULL.
            // Tratamiento: cantidad=1, unidad=Unidades, factor=1, Insumo=mismo producto.
            if (!empty($ingr['nuevo_producto']['id_receta_producto'])) {
                $ingr['escenario_erp'] = 'receta_global';
                $ingr['nuevo_producto']['cantidad'] = 1;
                $ingr['nuevo_producto']['unidadNueva'] = 'Unidades';
                $ingr['insumo_receta'] = array_merge($ingr['nuevo_producto'], [
                    'factor_conversion' => null,  // sin conversión, factor implícito = 1
                ]);
            } else {
                // ── Lógica normal: presentación simple ───────────────────────────────
                // Prioridad 1 (porción directa): el item mapeado ES el insumo receta.
                // Aunque la porción ya está resuelta, la unidad del ingrediente en Access
                // puede diferir de la del ERP (ej: gr en Access vs Onzas Peso en ERP).
                // Se resuelve el factor de conversión para que la cantidad sea correcta.
                $npEsReceta = isset($ingr['nuevo_producto']['presentacion_receta']) && (int) $ingr['nuevo_producto']['presentacion_receta'] === 1;

                if (($ingr['metodo_cotizacion'] ?? '') === 'directa' && $npEsReceta) {
                    $ingr['escenario_erp'] = 'directo';
                    $ingr['insumo_receta'] = $ingr['nuevo_producto'];

                    // Resolver factor si la unidad Access ≠ unidad ERP
                    $unidadAccessP1 = trim($ingr['UnidadIngrediente'] ?? '');
                    $unidadERP_P1 = trim($ingr['insumo_receta']['unidadNueva'] ?? '');
                    if ($unidadAccessP1 !== '' && $unidadERP_P1 !== '') {
                        $resP1 = resolverUnidadERP($conn, $unidadAccessP1);
                        if ($resP1) {
                            $multiP1 = $resP1['multi_directos'] ?? $resP1['directos'];
                            // Si la unidad ERP NO está en el grupo de la unidad Access → mismatch
                            if (!in_array($unidadERP_P1, $multiP1)) {
                                foreach ($resP1['conversiones'] as $conv) {
                                    if ($conv['nombre'] === $unidadERP_P1) {
                                        $ingr['insumo_receta']['factor_conversion'] = $conv['factor'];
                                        break;
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // Prioridad 2 y 3: resolución dinámica desde DB
                    // 1) Resolver la unidad Access → registro ERP + unidades convertibles
                    $resolucion = resolverUnidadERP($conn, $ingr['UnidadIngrediente']);
                    $unidadResERP = trim($ingr['nuevo_producto']['unidadNueva'] ?? '');

                    // 2) ¿La presentación ERP ya resuelta está en el mismo grupo de unidad?
                    $esDirecto = $resolucion && in_array($unidadResERP, $resolucion['directos']);
                    $npEsReceta = isset($ingr['nuevo_producto']['presentacion_receta']) && (int) $ingr['nuevo_producto']['presentacion_receta'] === 1;

                    if ($esDirecto && $npEsReceta) {
                        // Misma unidad y es la de receta → Insumo Receta = Presentación Uso
                        $ingr['escenario_erp'] = 'directo';
                        $ingr['insumo_receta'] = $ingr['nuevo_producto'];
                    } else {
                        $ingr['escenario_erp'] = 'diferente_presentacion';
                        $idMaestroERP = $ingr['nuevo_producto']['id_maestro'] ?? null;
                        $irRow = null;

                        if ($idMaestroERP) {
                            if ($resolucion) {
                                // Nivel 1: buscar presentación con TODAS las unidades que
                                // coinciden con la unidad del ingrediente (principal + secundarias).
                                // Ej: 'oz' → ['Onzas Peso', 'Onzas Liquidas']
                                $irRow = buscarPresentacionPorUnidades(
                                    $conn,
                                    $idMaestroERP,
                                    $resolucion['multi_directos'] ?? $resolucion['directos'],
                                    'receta'
                                );

                                // Nivel 2: buscar con unidades convertibles (ej: Onzas Peso)
                                if (!$irRow && !empty($resolucion['convertibles'])) {
                                    $irRow = buscarPresentacionPorUnidades($conn, $idMaestroERP, $resolucion['convertibles'], 'receta');
                                    if ($irRow) {
                                        foreach ($resolucion['conversiones'] as $conv) {
                                            if ($conv['nombre'] === $irRow['unidadNueva']) {
                                                $irRow['factor_conversion'] = $conv['factor'];
                                                break;
                                            }
                                        }
                                    }
                                }
                            }

                            // Nivel 3: si resolverUnidadERP falló o no encontró, usar la unidad
                            // ya conocida de nuevo_producto (Onzas Peso, Litros, etc.)
                            if (!$irRow && $unidadResERP !== '') {
                                $irRow = buscarPresentacionPorUnidades($conn, $idMaestroERP, [$unidadResERP], 'receta');
                                if ($irRow) {
                                    // Buscar factor de conversión entre la unidad del ingrediente y la encontrada
                                    // usando ? posicionales (evita HY093 con named params repetidos)
                                    try {
                                        $stmtFact = $conn->prepare("
                                        SELECT c.cantidad, c.id_unidad_producto_inicio
                                        FROM conversion_unidad_producto c
                                        JOIN unidad_producto ui ON ui.id = c.id_unidad_producto_inicio
                                        JOIN unidad_producto uf ON uf.id = c.id_unidad_producto_final
                                        WHERE (LOWER(ui.abreviado) = ? OR LOWER(ui.nombre) = ?)
                                          AND LOWER(uf.nombre) = ?
                                        LIMIT 1
                                    ");
                                        $uOrig = strtolower($ingr['UnidadIngrediente']);
                                        $uDest = strtolower($irRow['unidadNueva']);
                                        $stmtFact->execute([$uOrig, $uOrig, $uDest]);
                                        $factRow = $stmtFact->fetch(PDO::FETCH_ASSOC);
                                        if ($factRow) {
                                            $irRow['factor_conversion'] = (float) $factRow['cantidad'];
                                        }
                                    } catch (\Exception $e) {
                                        // Factor no encontrado — quantity se mostrará sin conversión
                                    }
                                }
                            }
                        }

                        if ($irRow) {
                            $ingr['insumo_receta'] = $irRow;
                        }
                    }
                }
            } // fin else: lógica normal (no es receta_global)
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