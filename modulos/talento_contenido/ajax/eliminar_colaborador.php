<?php
// eliminar_colaborador.php - Eliminar un colaborador del carrusel
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('talento_contenido', 'eliminar', $cargoOperario)) {
        throw new Exception("No tienes privilegios para eliminar colaboradores.");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $colaborador_id = isset($input['id']) ? intval($input['id']) : 0;

    if ($colaborador_id <= 0) {
        throw new Exception("ID de colaborador inválido.");
    }

    // 1. Obtener la foto para eliminarla físicamente
    $stmtFoto = $conn->prepare("SELECT foto FROM colaboradores_talento WHERE id = :id LIMIT 1");
    $stmtFoto->bindValue(':id', $colaborador_id, PDO::PARAM_INT);
    $stmtFoto->execute();
    $foto = $stmtFoto->fetchColumn();

    // 2. Eliminar el registro en la BD
    $stmt = $conn->prepare("DELETE FROM colaboradores_talento WHERE id = :id");
    $stmt->bindValue(':id', $colaborador_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Eliminar foto física del disco si existe
        if ($foto && file_exists("../../../../talento.batidospitaya/uploads/equipo/" . $foto)) {
            @unlink("../../../../talento.batidospitaya/uploads/equipo/" . $foto);
        }
        echo json_encode([
            'success' => true,
            'message' => "Colaborador eliminado correctamente."
        ]);
    } else {
        throw new Exception("Error al eliminar el registro de la base de datos.");
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
