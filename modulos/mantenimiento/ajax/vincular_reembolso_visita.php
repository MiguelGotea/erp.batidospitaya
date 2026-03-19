<?php
/**
 * Vincular un reembolso a una visita de informe
 * Ubicación: /modulos/mantenimiento/ajax/vincular_reembolso_visita.php
 */

header('Content-Type: application/json');
require_once '../../../core/database/conexion.php';
require_once '../models/Ticket.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $visita_id = isset($input['visita_id']) ? (int)$input['visita_id'] : null;
    $reembolso_id = isset($input['reembolso_id']) ? (int)$input['reembolso_id'] : null;
    
    if (!$visita_id || !$reembolso_id) {
        throw new Exception('Faltan parámetros requeridos.');
    }

    $ticketModel = new Ticket();
    $success = $ticketModel->vincularReembolsoAVisita($visita_id, $reembolso_id);

    echo json_encode([
        'success' => $success !== false,
        'message' => $success !== false ? 'Vínculo creado correctamente.' : 'Error al crear el vínculo.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
