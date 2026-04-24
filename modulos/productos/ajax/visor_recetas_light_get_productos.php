<?php
/**
 * visor_recetas_light_get_productos.php
 * Retorna todos los productos ACTIVOS (Vigencia = 1) agrupados por grupo,
 * con sus versiones/tamaños para el menú del visor light.
 *
 * Response: {
 *   success: true,
 *   grupos: [
 *     { CodGrupo, NombreGrupo, alias, prioridad,
 *       productos: [
 *         { Nombre, versiones: [{ CodBatido, Medida, Precio }] }
 *       ]
 *     }
 *   ]
 * }
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
    if (!tienePermiso('recetario_access_traducido', 'vista', $usuario['CodNivelesCargos'])) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso']);
        exit;
    }

    // Traer todos los productos activos con su grupo en un solo query
    // Solo grupos con MenuVendible = 'VERDADERO'
    $stmt = $conn->prepare("
        SELECT
            g.CodGrupo,
            g.NombreGrupo,
            g.alias,
            g.prioridad,
            b.CodBatido,
            b.Nombre,
            b.Medida,
            b.Precio
        FROM DBBatidos b
        INNER JOIN GrupoProductosVenta g ON g.CodGrupo = b.CodGrupo
        WHERE b.Vigencia = 1
          AND g.MenuVendible = 'VERDADERO'
        ORDER BY g.prioridad ASC, g.NombreGrupo ASC, b.Nombre ASC, b.Medida ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar en árbol: grupo → producto → versiones
    $grupos = [];

    foreach ($rows as $row) {
        $cg  = $row['CodGrupo'];
        $nom = $row['Nombre'];

        if (!isset($grupos[$cg])) {
            $grupos[$cg] = [
                'CodGrupo'    => $cg,
                'NombreGrupo' => $row['NombreGrupo'],
                'alias'       => $row['alias'],
                'prioridad'   => $row['prioridad'],
                'productos'   => [],
            ];
        }

        if (!isset($grupos[$cg]['productos'][$nom])) {
            $grupos[$cg]['productos'][$nom] = [
                'Nombre'      => $nom,
                'NombreGrupo' => $row['NombreGrupo'],
                'versiones'   => [],
            ];
        }

        $grupos[$cg]['productos'][$nom]['versiones'][] = [
            'CodBatido' => $row['CodBatido'],
            'Medida'    => $row['Medida'],
            'Precio'    => $row['Precio'],
        ];
    }

    // Convertir a arrays indexados
    $result = [];
    foreach ($grupos as $grupo) {
        $grupo['productos'] = array_values($grupo['productos']);
        $result[] = $grupo;
    }

    echo json_encode(['success' => true, 'grupos' => $result]);

} catch (Exception $e) {
    error_log("Error en visor_recetas_light_get_productos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener productos']);
}
?>
