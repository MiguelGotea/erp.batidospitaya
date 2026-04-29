<?php
// ajax/promo_cambiar_estado.php — Activar / pausar sin entrar al formulario
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) throw new Exception('No autorizado');

    $id     = isset($_POST['id'])     ? (int)$_POST['id']        : 0;
    $estado = isset($_POST['estado']) ? trim($_POST['estado'])    : '';
    $uid    = $_SESSION['usuario_id'];

    if ($id <= 0) throw new Exception('ID inválido');

    $validos = ['activa','inactiva','borrador','archivada'];
    if (!in_array($estado, $validos)) throw new Exception('Estado inválido');

    $stmt = $conn->prepare(
        'UPDATE promo_promociones
         SET estado = :estado, usuario_modificacion = :uid
         WHERE id = :id'
    );
    $stmt->execute([':estado' => $estado, ':uid' => $uid, ':id' => $id]);

    $labels = [
        'activa'    => 'Promoción activada',
        'inactiva'  => 'Promoción pausada',
        'borrador'  => 'Promoción enviada a borrador',
        'archivada' => 'Promoción archivada'
    ];

    echo json_encode([
        'success'      => true,
        'nuevo_estado' => $estado,
        'message'      => $labels[$estado]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
