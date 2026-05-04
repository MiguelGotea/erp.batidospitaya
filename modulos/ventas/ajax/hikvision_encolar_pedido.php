<?php
/**
 * hikvision_encolar_pedido.php
 * Proxy ERP → API: encola un pedido específico para análisis IA
 * POST → { cod_pedido: int, local: string }
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario      = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Requiere permiso de analizar atención
if (!tienePermiso('historial_pedidos_globales', 'analizar_atencion_cliente_bot', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso para analizar atención al cliente']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$cod_pedido = isset($data['cod_pedido']) ? intval($data['cod_pedido']) : null;
$local      = isset($data['local'])      ? trim($data['local'])        : null;

if (!$cod_pedido || !$local) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros: cod_pedido, local']);
    exit;
}

try {
    $ch = curl_init('https://api.batidospitaya.com/api/hikvision/encolar_pedido.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['cod_pedido' => $cod_pedido, 'local' => $local]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-WSP-Token: a8f5e2d9c4b7a1e6f3d8c5b2a9e6d3f0c7a4b1e8d5c2a9f6e3d0c7b4a1e8f5d2',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['success' => false, 'message' => 'Error de conexión con API: ' . $curlError]);
        exit;
    }

    $resultado = json_decode($response, true);

    if ($httpCode === 200 && isset($resultado['success']) && $resultado['success']) {
        echo json_encode([
            'success'   => true,
            'encolado'  => $resultado['encolado'] ?? true,
            'id_cola'   => $resultado['id_cola']   ?? null,
            'sucursal'  => $resultado['sucursal']  ?? null,
            'mensaje'   => $resultado['mensaje']   ?? 'Pedido encolado correctamente',
        ]);
    } else {
        $error = $resultado['error'] ?? $resultado['mensaje'] ?? 'Error al encolar pedido';
        echo json_encode(['success' => false, 'message' => $error]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
