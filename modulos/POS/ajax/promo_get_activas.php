<?php
// ajax/promo_get_activas.php — Devuelve lista de promociones ACTIVAS, de prueba
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) throw new Exception('No autorizado');

    $sql = "SELECT id, nombre, codigo_interno, resultado_tipo, resultado_valor, objetivo_descuento 
            FROM promo_promociones 
            WHERE estado = 'activa' 
            ORDER BY prioridad ASC";
    $stmt = $conn->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
}
?>
    