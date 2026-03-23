<?php
/**
 * gestion_tareas_reuniones_pitayabot_status.php
 * Proxy: consulta el estado de la sesión wsp-pitayabot en la BD
 * (mismo mecanismo que campanas_wsp_get_status.php pero para esta instancia)
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pitayabot', 'ver_estado', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT instancia, estado, qr_base64, numero_telefono, ultimo_ping, ip_vps
        FROM wsp_sesion_vps_
        WHERE instancia = 'wsp-pitayabot'
        LIMIT 1
    ");
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fila) {
        echo json_encode([
            'estado'           => 'desconectado',
            'qr'               => null,
            'numero_telefono'  => null,
            'ultimo_ping'      => null,
            '_sin_registro'    => true
        ]);
        exit;
    }

    // Calcular si el ping es reciente (< 2 minutos)
    $ultimoPing = $fila['ultimo_ping'];
    $segundosDesde = $ultimoPing
        ? (time() - strtotime($ultimoPing))
        : 9999;

    $estadoReal = ($segundosDesde > 120 && $fila['estado'] === 'conectado')
        ? 'desconectado'
        : $fila['estado'];

    echo json_encode([
        'estado'           => $estadoReal,
        'qr'               => $fila['qr_base64'],
        'numero_telefono'  => $fila['numero_telefono'],
        'ultimo_ping'      => $ultimoPing,
        'segundos_desde'   => $segundosDesde,
        'ip_vps'           => $fila['ip_vps']
    ]);

} catch (Exception $e) {
    echo json_encode(['estado' => 'desconectado', 'qr' => null]);
}
