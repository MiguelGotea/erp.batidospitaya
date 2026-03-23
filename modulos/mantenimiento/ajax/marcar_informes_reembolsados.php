<?php
/**
 * Marcar masivamente los informes de una semana como reembolsados
 * Ubicación: /modulos/mantenimiento/ajax/marcar_informes_reembolsados.php
 */

header('Content-Type: application/json');
require_once '../../../core/database/conexion.php';
require_once '../models/Ticket.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $semana = $data['semana'] ?? null;
    $anio = $data['anio'] ?? date('Y');
    $reembolso_id = $data['reembolso_id'] ?? null;

    if (!$semana || !$reembolso_id) {
        throw new Exception('Semana o ID de reembolso no proporcionados.');
    }

    $db = (new Ticket())->getDb();

    // 1. Obtener rango
    $sqlS = "SELECT fecha_inicio, fecha_fin FROM SemanasSistema WHERE numero_semana = ? AND anio = ?";
    $rango = $db->fetchOne($sqlS, [$semana, $anio]);
    if (!$rango) throw new Exception("Semana no configurada.");

    $db_conn = $db->getConnection();

    // 2. Marcar masivamente
    $sqlU = "UPDATE mtto_informes_diarios 
             SET reembolso_id = ? 
             WHERE fecha BETWEEN ? AND ? 
             AND km_final IS NOT NULL AND km_inicial IS NOT NULL";
    
    $stmt = $db_conn->prepare($sqlU);
    $stmt->execute([$reembolso_id, $rango['fecha_inicio'], $rango['fecha_fin']]);
    $afectados = $stmt->rowCount();

    echo json_encode(['success' => true, 'message' => 'Informes vinculados con éxito.', 'afectados' => $afectados]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
