<?php
/**
 * hikvision_worker_control.php
 * Proxy ERP → API: start/stop/status del worker HikvisionIA
 * GET  → obtiene estado actual + stats de cola
 * POST → { action: "start"|"stop" }
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Vista solo necesita permiso de vista, el control requiere permiso especial
$puedeVer       = tienePermiso('historial_pedidos_globales', 'vista', $cargoOperario);
$puedeControlar = tienePermiso('historial_pedidos_globales', 'activar_bot_atencion_cliente', $cargoOperario);

if (!$puedeVer) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Si es POST de control, verificar permiso extra
if ($method === 'POST' && !$puedeControlar) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso para controlar el worker']);
    exit;
}

$nombreUsuario = trim(($usuario['Nombre'] ?? '') . ' ' . ($usuario['Apellido'] ?? ''));

try {
    if ($method === 'POST') {
        $data   = json_decode(file_get_contents('php://input'), true);
        $action = isset($data['action']) ? trim($data['action']) : null;

        if (!in_array($action, ['start', 'stop'])) {
            echo json_encode(['success' => false, 'message' => 'Acción inválida']);
            exit;
        }

        // Llamar a worker_status.php — maneja flag + encolar día en un solo request
        $ch = curl_init('https://api.batidospitaya.com/api/hikvision/worker_status.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'action'     => $action,
                'updated_by' => $nombreUsuario,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-WSP-Token: a8f5e2d9c4b7a1e6f3d8c5b2a9e6d3f0c7a4b1e8d5c2a9f6e3d0c7b4a1e8f5d2',
            ],
            CURLOPT_TIMEOUT        => 30, // más largo porque incluye encolado
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            echo json_encode(['success' => false, 'message' => 'Error al comunicar con la API: ' . $curlErr]);
            exit;
        }

        $apiResp   = json_decode($resp, true);
        $encolados = $apiResp['encolados_hoy'] ?? 0;

        echo json_encode([
            'success'           => true,
            'action'            => $action,
            'worker_habilitado' => $apiResp['worker_habilitado'] ?? ($action === 'start'),
            'encolados_hoy'     => $encolados,
            'mensaje'           => $apiResp['mensaje']
                ?? ($action === 'start'
                    ? "Worker activado. $encolados pedido(s) de hoy encolados."
                    : 'Worker detenido. No procesará nuevos pedidos.'),
        ]);
        exit;
    }

    // ── GET: estado actual ───────────────────────────────────
    $ch = curl_init('https://api.batidospitaya.com/api/hikvision/worker_status.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'X-WSP-Token: a8f5e2d9c4b7a1e6f3d8c5b2a9e6d3f0c7a4b1e8d5c2a9f6e3d0c7b4a1e8f5d2',
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200) {
        echo json_encode(['success' => false, 'message' => 'No se pudo obtener estado del worker']);
        exit;
    }

    $status = json_decode($resp, true);

    echo json_encode([
        'success'           => true,
        'worker_habilitado' => $status['worker_habilitado']  ?? false,
        'worker_procesando' => $status['worker_procesando']  ?? false,
        'puede_controlar'   => $puedeControlar,
        'cola_hoy'          => $status['cola_hoy']           ?? [],
        'config_updated_by' => $status['config_updated_by']  ?? null,
        'config_updated_at' => $status['config_updated_at']  ?? null,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
