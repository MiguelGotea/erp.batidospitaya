<?php
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'obtener_operarios_sucursal':
            $codSucursal = $_GET['sucursal'] ?? null;
            if (!$codSucursal) {
                throw new Exception('Sucursal no especificada');
            }
            
            $operarios = obtenerOperariosSucursalLider($codSucursal, $_SESSION['usuario_id']);
            echo json_encode($operarios);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}