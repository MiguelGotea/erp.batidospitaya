<?php
/**
 * AJAX: Enviar mensajes de cumpleaños
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');
require_once('../../../core/permissions/permissions.php');

try {
    // Verificar permiso
    $codNivelCargo = $_SESSION['cargo_cod'];
    if (!tienePermiso('whatsapp_campanas', 'enviar', $codNivelCargo)) {
        throw new Exception('No tienes permiso para enviar mensajes');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $clientesIds = $input['clientes'] ?? [];
    $plantillaId = $input['plantilla_id'] ?? null;

    if (empty($clientesIds)) {
        throw new Exception('No se seleccionaron clientes');
    }

    if (!$plantillaId) {
        throw new Exception('No se seleccionó una plantilla');
    }

    // Obtener plantilla
    $stmt = $conn->prepare("SELECT * FROM whatsapp_plantillas WHERE id = ? AND activa = 1");
    $stmt->execute([$plantillaId]);
    $plantilla = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plantilla) {
        throw new Exception('Plantilla no encontrada o inactiva');
    }

    // Obtener configuración del servidor
    $stmt = $conn->prepare("SELECT * FROM whatsapp_config ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config || empty($config['servidor_url'])) {
        throw new Exception('Servidor WhatsApp no configurado');
    }

    // Obtener datos de los clientes
    $placeholders = str_repeat('?,', count($clientesIds) - 1) . '?';
    $stmt = $conn->prepare("
        SELECT id_clienteclub, nombre, apellido, celular, nombre_sucursal, puntos_iniciales 
        FROM clientesclub 
        WHERE id_clienteclub IN ($placeholders)
    ");
    $stmt->execute($clientesIds);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear campaña de cumpleaños del día
    $fechaHoy = date('Y-m-d');
    $nombreCampana = "Cumpleaños " . date('d/m/Y');

    $stmt = $conn->prepare("
        INSERT INTO whatsapp_campanas (nombre, tipo, plantilla_id, estado, total_destinatarios, creado_por)
        VALUES (?, 'cumpleanos', ?, 'en_proceso', ?, ?)
    ");
    $stmt->execute([$nombreCampana, $plantillaId, count($clientes), $_SESSION['usuario_id']]);
    $campanaId = $conn->lastInsertId();

    // Preparar mensajes para envío
    $mensajesParaEnviar = [];
    $mensajesInsertados = 0;

    foreach ($clientes as $cliente) {
        // Personalizar mensaje
        $mensaje = $plantilla['mensaje'];
        $mensaje = str_ireplace('{nombre}', $cliente['nombre'] ?? '', $mensaje);
        $mensaje = str_ireplace('{apellido}', $cliente['apellido'] ?? '', $mensaje);
        $mensaje = str_ireplace('{sucursal}', $cliente['nombre_sucursal'] ?? '', $mensaje);
        $mensaje = str_ireplace('{puntos}', $cliente['puntos_iniciales'] ?? '0', $mensaje);

        // Insertar en whatsapp_mensajes
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
            $plantilla['imagen_url']
        ]);

        $mensajeId = $conn->lastInsertId();

        // Agregar a array para enviar al VPS
        $mensajesParaEnviar[] = [
            'phone' => $cliente['celular'],
            'message' => $mensaje,
            'mediaUrl' => $plantilla['imagen_url'] ?: null,
            'dbMessageId' => $mensajeId
        ];

        $mensajesInsertados++;
    }

    // Enviar al servidor VPS
    $url = rtrim($config['servidor_url'], '/') . '/api/send/birthday';

    $postData = [
        'recipients' => array_map(function ($c) {
            return [
                'celular' => $c['celular'],
                'nombre' => $c['nombre'],
                'apellido' => $c['apellido'] ?? '',
                'sucursal' => $c['nombre_sucursal'] ?? '',
                'puntos' => $c['puntos_iniciales'] ?? 0
            ];
        }, $clientes),
        'template' => $plantilla['mensaje'],
        'mediaUrl' => $plantilla['imagen_url'] ?: null,
        'campaignId' => $campanaId
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['servidor_token'],
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        // Actualizar estado de campaña a error
        $stmt = $conn->prepare("UPDATE whatsapp_campanas SET estado = 'pausada' WHERE id = ?");
        $stmt->execute([$campanaId]);

        throw new Exception('Error al comunicarse con el servidor WhatsApp');
    }

    $responseData = json_decode($response, true);

    // Actualizar uso de plantilla
    $stmt = $conn->prepare("UPDATE whatsapp_plantillas SET uso_count = uso_count + ? WHERE id = ?");
    $stmt->execute([count($clientes), $plantillaId]);

    // Registrar en log
    $stmt = $conn->prepare("INSERT INTO whatsapp_logs (tipo, mensaje, datos, usuario_id) VALUES ('envio', ?, ?, ?)");
    $stmt->execute([
        "Enviados $mensajesInsertados mensajes de cumpleaños",
        json_encode(['campana_id' => $campanaId, 'plantilla_id' => $plantillaId]),
        $_SESSION['usuario_id']
    ]);

    // Calcular tiempo estimado (1.5 min promedio por mensaje considerando delays)
    $tiempoEstimado = ceil($mensajesInsertados * 1.5);

    echo json_encode([
        'success' => true,
        'agregados' => $mensajesInsertados,
        'campana_id' => $campanaId,
        'tiempoEstimado' => $tiempoEstimado
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}