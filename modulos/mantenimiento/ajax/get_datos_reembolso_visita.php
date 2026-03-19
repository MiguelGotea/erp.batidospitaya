<?php
/**
 * Obtener datos de una visita para pre-llenar un reembolso
 * Ubicación: /modulos/mantenimiento/ajax/get_datos_reembolso_visita.php
 */

header('Content-Type: application/json');
require_once '../../../core/database/conexion.php';
require_once '../models/Ticket.php';

try {
    $visita_id = isset($_GET['visita_id']) ? (int)$_GET['visita_id'] : null;
    
    if (!$visita_id) {
        throw new Exception('ID de visita no proporcionado.');
    }

    $ticketModel = new Ticket();
    
    // Obtener info de la visita
    $sqlV = "SELECT v.*, s.nombre as nombre_sucursal, i.fecha
             FROM mtto_informe_visitas v
             JOIN sucursales s ON v.cod_sucursal = s.codigo
             JOIN mtto_informes_diarios i ON v.informe_id = i.id
             WHERE v.id = ?";
    $visita = $ticketModel->getDb()->fetchOne($sqlV, [$visita_id]);

    if (!$visita) {
        throw new Exception('Visita no encontrada.');
    }

    // Obtener compras de la visita
    $sqlC = "SELECT * FROM mtto_informe_compras WHERE visita_id = ?";
    $compras = $ticketModel->getDb()->fetchAll($sqlC, [$visita_id]);

    echo json_encode([
        'success' => true,
        'visita' => $visita,
        'compras' => $compras
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
