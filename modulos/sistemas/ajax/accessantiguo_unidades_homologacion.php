<?php
/**
 * accessantiguo_unidades_homologacion.php
 *
 * Resolución DINÁMICA de unidades del sistema Access al catálogo ERP,
 * usando las tablas:
 *   - unidad_producto             (id, nombre, abreviado, nombres_opcionales)
 *   - conversion_unidad_producto  (id_inicio, id_final, cantidad)
 *
 * Flujo de resolución:
 *   1. Busca la unidad ERP por abreviado exacto, luego nombre exacto,
 *      luego token exacto en nombres_opcionales (vía FIND_IN_SET).
 *   2. Si no encuentra presentación directa, expande a unidades relacionadas
 *      por conversion_unidad_producto y devuelve el factor de conversión.
 */

/**
 * Resuelve la unidad del sistema Access al registro de unidad_producto
 * y expande con unidades relacionadas por conversión.
 * También detecta coincidencias secundarias (otras unidades ERP que comparten
 * el mismo string, ej: "oz" = Onzas Peso (primaria) y Onzas Líquidas (secundaria)).
 *
 * @param PDO    $conn          Conexión activa
 * @param string $unidadAntigua Unidad del sistema Access (ej: "gr", "oz", "unid")
 * @return array|null  {
 *   id:             int
 *   nombre:         string    – nombre canónico ERP (ej: "Gramos")
 *   directos:       string[]  – [nombre] – coincidencia principal
 *   multi_directos: string[]  – directos + coincidencias secundarias del mismo string
 *   convertibles:   string[]  – nombres relacionados por conversión
 *   todos:          string[]  – multi_directos + convertibles
 *   conversiones:   [{nombre, factor}]
 * }
 */
function resolverUnidadERP(PDO $conn, string $unidadAntigua): ?array
{
    $u = strtolower(trim($unidadAntigua));
    if ($u === '') return null;

    try {
        // ── Paso 1: Localizar la unidad ERP ──────────────────────────────────
        // Prioridad: abreviado exacto → nombre exacto → token en nombres_opcionales
        // Usamos FIND_IN_SET después de normalizar la coma-lista (quitar espacios)
        // para evitar falsos positivos del LIKE (ej: 'l' dentro de 'ml.')
        $stmtU = $conn->prepare("
            SELECT id, nombre
            FROM unidad_producto
            WHERE LOWER(abreviado) = ?
               OR LOWER(nombre) = ?
               OR FIND_IN_SET(
                   ?,
                   LOWER(REPLACE(REPLACE(nombres_opcionales, ', ', ','), ' ,', ','))
               ) > 0
            ORDER BY
                CASE
                    WHEN LOWER(abreviado) = ? THEN 1
                    WHEN LOWER(nombre)    = ? THEN 2
                    ELSE 3
                END
            LIMIT 1
        ");
        $stmtU->execute([$u, $u, $u, $u, $u]);
        $unidad = $stmtU->fetch(PDO::FETCH_ASSOC);

        if (!$unidad) return null;

        $unidadId        = (int)$unidad['id'];
        $nombrePrincipal = $unidad['nombre'];

        // ── Paso 2: Unidades relacionadas por conversión ──────────────────────
        // IMPORTANTE: usar ? posicionales, no :id repetido → evita PDO HY093
        $stmtConv = $conn->prepare("
            SELECT
                CASE
                    WHEN c.id_unidad_producto_inicio = ? THEN uf.nombre
                    ELSE ui.nombre
                END                      AS nombre_relacionado,
                CASE
                    WHEN c.id_unidad_producto_inicio = ? THEN c.cantidad
                    ELSE (1 / c.cantidad)
                END                      AS factor_a_relacionado
            FROM conversion_unidad_producto c
            JOIN unidad_producto ui ON ui.id = c.id_unidad_producto_inicio
            JOIN unidad_producto uf ON uf.id = c.id_unidad_producto_final
            WHERE c.id_unidad_producto_inicio = ?
               OR c.id_unidad_producto_final  = ?
        ");
        $stmtConv->execute([$unidadId, $unidadId, $unidadId, $unidadId]);

        $convertibles = [];
        $conversiones = [];
        foreach ($stmtConv->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $nom = $row['nombre_relacionado'];
            if (!in_array($nom, $convertibles)) {
                $convertibles[] = $nom;
                $conversiones[] = [
                    'nombre' => $nom,
                    'factor' => (float)$row['factor_a_relacionado'],
                ];
            }
        }

        $directos = [$nombrePrincipal];

        // ── Paso 3: Coincidencias secundarias ──────────────────────────────────
        // Otras unidades ERP con el mismo string en abreviado/nombre/nombres_opcionales.
        // Ej: 'oz' aparece también en nombres_opcionales de Onzas Liquidas.
        $stmtSec = $conn->prepare("
            SELECT nombre
            FROM unidad_producto
            WHERE id != ?
              AND (
                LOWER(abreviado) = ?
                OR LOWER(nombre) = ?
                OR FIND_IN_SET(
                    ?,
                    LOWER(REPLACE(REPLACE(nombres_opcionales, ', ', ','), ' ,', ','))
                ) > 0
              )
        ");
        $stmtSec->execute([$unidadId, $u, $u, $u]);
        $secundarias   = array_column($stmtSec->fetchAll(PDO::FETCH_ASSOC), 'nombre');
        $multiDirectos = array_merge($directos, $secundarias);

        $todos = array_merge($multiDirectos, $convertibles);

        return [
            'id'             => $unidadId,
            'nombre'         => $nombrePrincipal,
            'directos'       => $directos,
            'multi_directos' => $multiDirectos,
            'convertibles'   => $convertibles,
            'todos'          => $todos,
            'conversiones'   => $conversiones,
        ];

    } catch (\Exception $e) {
        // Si la columna aún no existe en DB o cualquier error SQL → falla silenciosa
        error_log('[resolverUnidadERP] Error: ' . $e->getMessage() . ' | unidad: ' . $unidadAntigua);
        return null;
    }
}

/**
 * Busca la primera presentación activa de un producto maestro
 * cuya unidad esté en la lista de nombres proporcionados.
 *
 * @param PDO    $conn      Conexión activa
 * @param int    $idMaestro producto_maestro.id
 * @param array  $nombres   unidad_producto.nombre a buscar (IN)
 * @return array|null
 */
function buscarPresentacionPorUnidades(PDO $conn, int $idMaestro, array $nombres): ?array
{
    if (empty($nombres)) return null;

    $placeholders = implode(',', array_fill(0, count($nombres), '?'));
    $stmt = $conn->prepare("
        SELECT
            pp.id       AS id_presentacion,
            pp.SKU,
            pp.Nombre   AS NombreNuevo,
            pp.cantidad,
            pp.Activo   AS activoNuevo,
            u.nombre    AS unidadNueva,
            pm.id       AS id_maestro,
            pm.Nombre   AS productoMaestro,
            pp.presentacion_receta
        FROM producto_presentacion pp
        INNER JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
        LEFT  JOIN unidad_producto  u  ON u.id  = pp.id_unidad_producto
        WHERE pp.id_producto_maestro = ?
          AND u.nombre IN ($placeholders)
          AND pp.Id_receta_producto IS NULL
          AND pp.Activo = 'SI'
          AND pp.presentacion_receta = 1
        ORDER BY
            CASE WHEN pp.cantidad = 1 THEN 0 ELSE 1 END ASC,
            pp.cantidad ASC
        LIMIT 1
    ");
    $stmt->execute(array_merge([$idMaestro], $nombres));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
?>