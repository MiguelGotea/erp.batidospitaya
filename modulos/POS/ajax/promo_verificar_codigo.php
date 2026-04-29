<?php
// ajax/promo_verificar_codigo.php — Verifica que codigo_interno no esté duplicado
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) throw new Exception('No autorizado');

    $codigo = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
    $id     = isset($_POST['id'])     ? (int)$_POST['id']     : 0;

    if ($codigo === '') {
        echo json_encode(['success' => true, 'disponible' => true]);
        exit();
    }

    $sql = 'SELECT COUNT(*) as n FROM promo_promociones WHERE codigo_interno = :cod';
    if ($id > 0) $sql .= ' AND id != :id';

    $stmt = $conn->prepare($sql);
    $params = [':cod' => $codigo];
    if ($id > 0) $params[':id'] = $id;
    $stmt->execute($params);
    $n = $stmt->fetch()['n'];

    echo json_encode(['success' => true, 'disponible' => ($n == 0)]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'disponible' => false, 'message' => $e->getMessage()]);
}
?>
