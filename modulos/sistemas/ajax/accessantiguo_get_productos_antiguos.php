<?php
/**
 * get_productos_antiguos.php
 * Devuelve los productos del sistema antiguo (DBIngredientes + Cotizaciones)
 * con su estado de mapeo en diccionario_productos_legado.
 *
 * GET params:
 *   filtro   => 'todos' | 'mapeados' | 'pendientes'  (default: 'todos')
 *   busqueda => texto libre para filtrar por nombre/marca/línea
 *   pagina   => número de página (default: 1)
 *   por_pagina => registros por página (default: 50)
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
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('diccionario_productos', 'vista', $cargoOperario)) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso de vista']);
        exit;
    }

    // Parámetros
    $filtro = in_array($_GET['filtro'] ?? 'todos', ['todos', 'mapeados', 'pendientes'])
        ? ($_GET['filtro'] ?? 'todos')
        : 'todos';
    $busqueda = trim($_GET['busqueda'] ?? '');
    $pagina = max(1, intval($_GET['pagina'] ?? 1));
    $porPagina = max(10, min(200, intval($_GET['por_pagina'] ?? 50)));
    $offset = ($pagina - 1) * $porPagina;

    // ── WHERE dinámico ──────────────────────────────────────────────────────
    $where = [];
    $params = [];

    // Filtro por estado de mapeo
    if ($filtro === 'mapeados') {
        $where[] = 'd.id IS NOT NULL';
    } elseif ($filtro === 'pendientes') {
        $where[] = 'd.id IS NULL';
    }

    // Búsqueda de texto
    if ($busqueda !== '') {
        $where[] = '(i.Nombre LIKE :bus OR c.Marca LIKE :bus2 OR c.Linea LIKE :bus3
                     OR c.Unidad LIKE :bus4 OR c.Capacidad LIKE :bus5
                     OR i.CodIngrediente LIKE :bus6)';
        $params[':bus'] = "%$busqueda%";
        $params[':bus2'] = "%$busqueda%";
        $params[':bus3'] = "%$busqueda%";
        $params[':bus4'] = "%$busqueda%";
        $params[':bus5'] = "%$busqueda%";
        $params[':bus6'] = "%$busqueda%";
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // ── Query principal ─────────────────────────────────────────────────────
    $baseSql = "
        FROM Cotizaciones c
        INNER JOIN DBIngredientes i ON c.CodIngrediente = i.CodIngrediente
        LEFT JOIN diccionario_productos_legado d ON d.CodCotizacion = c.CodCotizacion
        LEFT JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
        $whereSQL
    ";

    // Conteo total
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total $baseSql");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    // Datos paginados
    $sql = "
        SELECT
            c.CodCotizacion,
            c.CodIngrediente,
            i.Nombre                        AS Nombre,
            c.Marca,
            c.Linea,
            c.Unidad,
            c.Capacidad,
            c.Conversion,
            c.Especificaciones,
            c.Descontinuado,
            i.Tipo,
            i.Vigente,
            d.id                            AS mapeo_id,
            d.id_producto_presentacion,
            pp.SKU                          AS nuevo_sku,
            pp.Nombre                       AS nuevo_nombre,
            d.notas                         AS mapeo_notas
        $baseSql
        ORDER BY i.Nombre ASC, c.Marca ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas globales (sin filtro) para el contador
    $stmtStats = $conn->prepare("
        SELECT
            COUNT(*) AS total_global,
            SUM(CASE WHEN d.id IS NOT NULL THEN 1 ELSE 0 END) AS total_mapeados
        FROM Cotizaciones c
        INNER JOIN DBIngredientes i ON c.CodIngrediente = i.CodIngrediente
        LEFT JOIN diccionario_productos_legado d ON d.CodCotizacion = c.CodCotizacion
    ");
    $stmtStats->execute();
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'total' => $total,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
        'total_paginas' => (int) ceil($total / $porPagina),
        'estadisticas' => [
            'total_global' => (int) $stats['total_global'],
            'total_mapeados' => (int) $stats['total_mapeados'],
            'total_pendientes' => (int) $stats['total_global'] - (int) $stats['total_mapeados'],
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en get_productos_antiguos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener productos']);
}
?>