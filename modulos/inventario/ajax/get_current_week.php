<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $hoy = date('Y-m-d');
    $stmt = $conn->prepare("SELECT numero_semana FROM SemanasSistema WHERE ? BETWEEN fecha_inicio AND fecha_fin LIMIT 1");
    $stmt->execute([$hoy]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($res) {
        echo json_encode(['ok' => true, 'semana' => (int)$res['numero_semana']]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'No se encontró semana para la fecha actual.']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
