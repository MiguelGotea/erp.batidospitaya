<?php
// guardar_habilidad.php - Guardar/Editar habilidad del catálogo
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    $habilidad_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // Verificar permisos específicos
    if ($habilidad_id > 0) {
        if (!tienePermiso('talento_contenido', 'editar', $cargoOperario)) {
            throw new Exception("No tienes privilegios para editar habilidades.");
        }
    } else {
        if (!tienePermiso('talento_contenido', 'crear', $cargoOperario)) {
            throw new Exception("No tienes privilegios para crear habilidades.");
        }
    }

    // Validar entradas básicas
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $categoria = isset($_POST['categoria']) ? trim($_POST['categoria']) : '';
    $activo = isset($_POST['activo']) ? intval($_POST['activo']) : 1;

    if (empty($nombre) || empty($categoria)) {
        throw new Exception("Por favor rellena todos los campos obligatorios (*).");
    }

    if ($habilidad_id > 0) {
        // MODO EDICIÓN
        $stmt = $conn->prepare("UPDATE habilidades_talento SET nombre = :nombre, categoria = :categoria, activo = :activo WHERE id = :id");
        $stmt->bindValue(':id', $habilidad_id, PDO::PARAM_INT);
    } else {
        // MODO CREACIÓN
        $stmt = $conn->prepare("INSERT INTO habilidades_talento (nombre, categoria, activo) VALUES (:nombre, :categoria, :activo)");
    }

    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':categoria', $categoria, PDO::PARAM_STR);
    $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => $habilidad_id > 0 ? "Habilidad actualizada correctamente." : "Habilidad agregada correctamente."
        ]);
    } else {
        throw new Exception("Error al guardar en la base de datos.");
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
