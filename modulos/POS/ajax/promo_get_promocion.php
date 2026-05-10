<?php
// ajax/promo_get_promocion.php — Carga una promoción y sus condiciones para editar
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) throw new Exception('No autorizado');

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) throw new Exception('ID inválido');

    $stmtP = $conn->prepare('SELECT * FROM promo_promociones WHERE id = :id');
    $stmtP->execute([':id' => $id]);
    $promo = $stmtP->fetch();
    if (!$promo) throw new Exception('Promoción no encontrada');

    $stmtC = $conn->prepare(
        'SELECT id, tipo_cond, nombre_cond, opcion_id, valor_json, orden
         FROM promo_condiciones
         WHERE promo_id = :id
         ORDER BY orden ASC'
    );
    $stmtC->execute([':id' => $id]);
    $condiciones = $stmtC->fetchAll();

    // Decodificar valor_json para enviar como objeto
    foreach ($condiciones as &$c) {
        $c['valor_json'] = json_decode($c['valor_json'], true);
    }

    echo json_encode(['success' => true, 'promo' => $promo, 'condiciones' => $condiciones]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
