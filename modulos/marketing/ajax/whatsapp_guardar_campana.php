<?php
/**
 * AJAX: Guardar campaña
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');
require_once('../../../core/permissions/permissions.php');

try {
    $codNivelCargo = $_SESSION['cargo_cod'];

    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');
    $tipo = $_POST['tipo'] ?? 'personalizada';
    $plantillaId = $_POST['plantilla_id'] ?: null;
    $fechaProgramada = $_POST['fecha_programada'] ?: null;
    $imagenUrl = trim($_POST['imagen_url'] ?? '');
    $segmento = $_POST['segmento'] ?? 'todos';
    $sucursal = $_POST['sucursal'] ?? '';
    $enviarAhora = $_POST['enviar_ahora'] ?? '0';

    if (empty($nombre)) {
        throw new Exception('El nombre es obligatorio');
    }

    // Contar destinatarios según segmento
    $sqlCount = "SELECT COUNT(*) FROM clientesclub WHERE celular IS NOT NULL AND celular != ''";
    $paramsCount = [];

    switch ($segmento) {
        case 'sucursal':
            if (!empty($sucursal)) {
                $sqlCount .= " AND nombre_sucursal = ?";
                $paramsCount[] = $sucursal;
            }
            break;
        case 'activos':
            // Clientes con actividad en últimos 60 días (requiere tabla de ventas)
            // Por ahora usamos todos
            break;
        case 'inactivos':
            // Clientes sin actividad en más de 60 días
            break;
    }

    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($paramsCount);
    $totalDestinatarios = $stmtCount->fetchColumn();

    if ($id) {
        // Verificar permiso editar
        if (!tienePermiso('whatsapp_campanas', 'editar', $codNivelCargo)) {
            throw new Exception('No tienes permiso para editar campañas');
        }

        $stmt = $conn->prepare("
            UPDATE whatsapp_campanas SET
                nombre = ?,
                tipo = ?,
                plantilla_id = ?,
                imagen_url = ?,
                fecha_programada = ?,
                total_destinatarios = ?,
                fecha_actualizacion = NOW()
            WHERE id = ? AND estado = 'borrador'
        ");
        $stmt->execute([$nombre, $tipo, $plantillaId, $imagenUrl, $fechaProgramada, $totalDestinatarios, $id]);

    } else {
        // Verificar permiso crear
        if (!tienePermiso('whatsapp_campanas', 'crear', $codNivelCargo)) {
            throw new Exception('No tienes permiso para crear campañas');
        }

        $estado = $enviarAhora === '1' ? 'en_proceso' : 'borrador';

        $stmt = $conn->prepare("
            INSERT INTO whatsapp_campanas 
            (nombre, tipo, plantilla_id, imagen_url, estado, fecha_programada, total_destinatarios, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $nombre,
            $tipo,
            $plantillaId,
            $imagenUrl,
            $estado,
            $fechaProgramada,
            $totalDestinatarios,
            $_SESSION['usuario_id']
        ]);

        $id = $conn->lastInsertId();
    }

    // Si se solicitó enviar ahora
    if ($enviarAhora === '1') {
        // Aquí iría la lógica para ejecutar la campaña
        // Similar a whatsapp_ejecutar_campana.php
    }

    echo json_encode([
        'success' => true,
        'id' => $id,
        'mensaje' => 'Campaña guardada correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}