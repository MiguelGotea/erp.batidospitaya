<?php
// ajax/promo_eliminar.php — Archivar (eliminación lógica) una promoción
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) throw new Exception('No autorizado');

    $id  = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $uid = $_SESSION['usuario_id'];
    if ($id <= 0) throw new Exception('ID inválido');

    $stmtChk = $conn->prepare('SELECT estado FROM promo_promociones WHERE id = :id');
    $stmtChk->execute([':id' => $id]);
    $promo = $stmtChk->fetch();
    if (!$promo) throw new Exception('Promoción no encontrada');

    $stmt = $conn->prepare(
        'UPDATE promo_promociones SET estado = :est, usuario_modificacion = :uid WHERE id = :id'
    );
    $stmt->execute([':est' => 'archivada', ':uid' => $uid, ':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Promoción archivada']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
