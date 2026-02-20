<?php
/**
 * get_batidos_por_grupo.php
 * Retorna los productos de DBBatidos filtrados por CodGrupo.
 *
 * GET params:
 *   cod_grupo => int (CodGrupo de GrupoProductosVenta)
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

    $codGrupo = intval($_GET['cod_grupo'] ?? 0);
    if ($codGrupo <= 0) {
        echo json_encode(['success' => false, 'message' => 'CodGrupo requerido']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            b.CodBatido,
            b.Nombre,
            b.Medida,
            b.Precio,
            b.Vigencia,
            b.Marca,
            b.CodSubGrupo,
            g.NombreGrupo
        FROM DBBatidos b
        INNER JOIN GrupoProductosVenta g ON g.CodGrupo = b.CodGrupo
        WHERE b.CodGrupo = :cg
        ORDER BY b.Nombre ASC
    ");
    $stmt->execute([':cg' => $codGrupo]);
    $batidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $batidos]);

} catch (Exception $e) {
    error_log("Error en get_batidos_por_grupo.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener productos']);
}
?>