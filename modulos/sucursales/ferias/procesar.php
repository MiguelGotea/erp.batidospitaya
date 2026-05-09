<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar acceso
verificarAccesoModulo('sucursales');
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

if (!$esAdmin && !verificarAccesoSucursalCargo([27], [14])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

require_once 'db_ferias.php';

// Función para enviar respuesta JSON consistente
function sendJsonResponse($success, $message = '', $data = []) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data) $response = array_merge($response, $data);
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Método no permitido');
    }

    // Obtener datos JSON
    $jsonInput = file_get_contents('php://input');
    if (empty($jsonInput)) {
        sendJsonResponse(false, 'No se recibieron datos');
    }

    $data = json_decode($jsonInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse(false, 'Error decodificando JSON: ' . json_last_error_msg());
    }

    // Validar datos mínimos
    if (empty($data['productos']) || !isset($data['tipoPago'])) {
        sendJsonResponse(false, 'Datos incompletos');
    }

    // Procesar la venta
    $nombreCliente = !empty($data['nombreCliente']) ? trim($data['nombreCliente']) : null;
    $result = procesarVenta($data['productos'], $data['tipoPago'], $nombreCliente);
    
    if (!$result['success']) {
        sendJsonResponse(false, $result['message'] ?? 'Error al procesar venta');
    }

    sendJsonResponse(true, 'Venta procesada', ['ventaId' => $result['ventaId']]);

} catch (Exception $e) {
    error_log('Error en procesar.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Error interno: ' . $e->getMessage());
}