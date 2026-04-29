<?php
// ajax/promo_get_grupos.php — Devuelve grupos, subgrupos o sucursales según parámetro ?tipo=
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) throw new Exception('No autorizado');

    $tipo     = isset($_GET['tipo'])     ? trim($_GET['tipo'])   : 'grupos';
    $idGrupo  = isset($_GET['id_grupo']) ? (int)$_GET['id_grupo'] : 0;

    switch ($tipo) {

        case 'sucursales':
            $stmt = $conn->prepare(
                "SELECT codigo AS id, nombre FROM sucursales WHERE activa = 1 AND sucursal = 1 ORDER BY nombre"
            );
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;

        case 'subgrupos':
            if ($idGrupo <= 0) throw new Exception('id_grupo requerido para subgrupos');
            $stmt = $conn->prepare(
                'SELECT id, Nombre
                 FROM subgrupo_presentacion_producto
                 WHERE id_grupo_presentacion_producto = :gid
                 ORDER BY Nombre'
            );
            $stmt->execute([':gid' => $idGrupo]);
            $data = $stmt->fetchAll();
            break;

        case 'grupos':
        default:
            $stmt = $conn->prepare(
                'SELECT id, Nombre FROM grupo_presentacion_producto ORDER BY Nombre'
            );
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
}
?>
