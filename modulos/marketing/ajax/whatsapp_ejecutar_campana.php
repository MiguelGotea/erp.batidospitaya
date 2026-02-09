<?php
/**
 * AJAX: Ejecutar campaña (iniciar envío)
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');
require_once('../../../core/permissions/permissions.php');

try {
    $codNivelCargo = $_SESSION['cargo_cod'];

    if (!tienePermiso('whatsapp_campanas', 'enviar', $codNivelCargo)) {
        throw new Exception('No tienes permiso para ejecutar campañas');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $campanaId = $input['id'] ?? null;

    if (!$campanaId) {
        throw new Exception('ID de campaña requerido');
    }

    // Obtener campaña
    $stmt = $conn->prepare("SELECT * FROM whatsapp_campanas WHERE id = ? AND estado = 'borrador'");
    $stmt->execute([$campanaId]);
    $campana = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campana) {
        throw new Exception('Campaña no encontrada o no está en estado borrador');
    }

    // Obtener plantilla
    $stmt = $conn->prepare("SELECT * FROM whatsapp_plantillas WHERE id = ?");
    $stmt->execute([$campana['plantilla_id']]);
    $plantilla = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plantilla) {
        throw new Exception('Plantilla no encontrada');
    }

    // Obtener configuración
    $stmt = $conn->prepare("SELECT * FROM whatsapp_config ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        throw new Exception('Servidor WhatsApp no configurado');
    }

    // Obtener clientes según el tipo de campaña
    // Por simplicidad, obtenemos todos los clientes con teléfono
    $stmt = $conn->prepare("
        SELECT id_clienteclub, nombre, apellido, celular, nombre_sucursal, puntos_iniciales 
        FROM clientesclub 
        WHERE celular IS NOT NULL 
          AND celular != ''
          AND LENGTH(REPLACE(REPLACE(celular, ' ', ''), '-', '')) >= 8
        LIMIT ?
    ");
    $stmt->execute([$campana['total_destinatarios']]);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Insertar mensajes
    $mensajesAgregados = 0;
    $mensajesParaVPS = [];

    foreach ($clientes as $cliente) {
        // Personalizar mensaje
        $mensaje = $plantilla['mensaje'];
        $mensaje = str_ireplace('{nombre}', $cliente['nombre'] ?? '', $mensaje);
        $mensaje = str_ireplace('{apellido}', $cliente['apellido'] ?? '', $mensaje);
        $mensaje = str_ireplace('{sucursal}', $cliente['nombre_sucursal'] ?? '', $mensaje);
        $mensaje = str_ireplace('{puntos}', $cliente['puntos_iniciales'] ?? '0', $mensaje);

        // Insertar mensaje
        $stmt = $conn->prepare("
            INSERT INTO whatsapp_mensajes 
            (campana_id, cliente_id, telefono, nombre_cliente, mensaje, imagen_url, estado)
            VALUES (?, ?, ?, ?, ?, ?, 'pendiente')
        ");
        $stmt->execute([
            $campanaId,
            $cliente['id_clienteclub'],
            $cliente['celular'],
            $cliente['nombre'] . ' ' . ($cliente['apellido'] ?? ''),
            $mensaje,
            $campana['imagen_url'] ?: $plantilla['imagen_url']
        ]);

        $mensajesParaVPS[] = [
            'phone' => $cliente['celular'],
            'message' => $mensaje,
            'mediaUrl' => $campana['imagen_url'] ?: $plantilla['imagen_url']
        ];

        $mensajesAgregados++;
    }

    // Enviar al servidor VPS
    $url = rtrim($config['servidor_url'], '/') . '/api/send/bulk';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'messages' => $mensajesParaVPS,
            'campaignId' => $campanaId
        ]),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['servidor_token'],
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        // Marcar campaña como pausada si falla
        $stmt = $conn->prepare("UPDATE whatsapp_campanas SET estado = 'pausada' WHERE id = ?");
        $stmt->execute([$campanaId]);
        throw new Exception('Error al enviar al servidor WhatsApp');
    }

    // Actualizar estado de campaña
    $stmt = $conn->prepare("UPDATE whatsapp_campanas SET estado = 'en_proceso', fecha_inicio = NOW() WHERE id = ?");
    $stmt->execute([$campanaId]);

    // Actualizar uso de plantilla
    $stmt = $conn->prepare("UPDATE whatsapp_plantillas SET uso_count = uso_count + ? WHERE id = ?");
    $stmt->execute([$mensajesAgregados, $plantilla['id']]);

    // Tiempo estimado en minutos
    $tiempoEstimado = ceil($mensajesAgregados * 1.5);

    echo json_encode([
        'success' => true,
        'mensajes_agregados' => $mensajesAgregados,
        'tiempo_estimado' => $tiempoEstimado
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}