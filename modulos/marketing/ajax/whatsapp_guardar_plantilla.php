<?php
/**
 * AJAX: Guardar plantilla
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
    $mensaje = trim($_POST['mensaje'] ?? '');
    $imagenUrl = trim($_POST['imagen_url'] ?? '');
    $activa = $_POST['activa'] ?? 1;

    if (empty($nombre)) {
        throw new Exception('El nombre es obligatorio');
    }

    if (empty($mensaje)) {
        throw new Exception('El mensaje es obligatorio');
    }

    if ($id) {
        // Verificar permiso editar
        if (!tienePermiso('whatsapp_campanas', 'editar', $codNivelCargo)) {
            throw new Exception('No tienes permiso para editar plantillas');
        }

        $stmt = $conn->prepare("
            UPDATE whatsapp_plantillas SET
                nombre = ?,
                tipo = ?,
                mensaje = ?,
                imagen_url = ?,
                activa = ?,
                fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$nombre, $tipo, $mensaje, $imagenUrl, $activa, $id]);

    } else {
        // Verificar permiso crear
        if (!tienePermiso('whatsapp_campanas', 'crear', $codNivelCargo)) {
            throw new Exception('No tienes permiso para crear plantillas');
        }

        $stmt = $conn->prepare("
            INSERT INTO whatsapp_plantillas 
            (nombre, tipo, mensaje, imagen_url, activa, creado_por)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $tipo, $mensaje, $imagenUrl, $activa, $_SESSION['usuario_id']]);

        $id = $conn->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'id' => $id,
        'mensaje' => 'Plantilla guardada correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}