<?php
// postulacion_seleccionar_directo.php
// Marca una postulación como 'seleccionado' directamente,
// sin requerir el registro en postulacion_evaluacion_jefe.

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json');

try {
    $usuario       = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    $codOperario   = $usuario['CodOperario'];

    // Verificar permiso
    if (!tienePermiso('postulacion_plazas_activas', 'seleccionar_directo', $cargoOperario)) {
        throw new Exception('No tienes permiso para realizar esta acción.');
    }

    $input        = json_decode(file_get_contents('php://input'), true);
    $idPostulacion = (int) ($input['id_postulacion'] ?? 0);

    if ($idPostulacion <= 0) {
        throw new Exception('ID de postulación inválido.');
    }

    // Verificar que la postulación exista y esté en estado válido para seleccionar
    $sqlCheck = "SELECT id, nombre, status FROM postulacion_plaza WHERE id = :id";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bindValue(':id', $idPostulacion, PDO::PARAM_INT);
    $stmtCheck->execute();
    $postulacion = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$postulacion) {
        throw new Exception('La postulación no existe.');
    }

    if ($postulacion['status'] === 'seleccionado') {
        throw new Exception('Este candidato ya tiene el estado "seleccionado".');
    }

    if ($postulacion['status'] === 'contratado') {
        throw new Exception('Este candidato ya fue contratado y no puede modificarse desde aquí.');
    }

    // Actualizar el estado a 'seleccionado'
    // No se inserta en postulacion_evaluacion_jefe (queda sin registro / NULL via LEFT JOIN).
    $sqlUpdate = "UPDATE postulacion_plaza 
                  SET status = 'seleccionado', 
                      fecha_actualizacion = NOW() 
                  WHERE id = :id";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bindValue(':id', $idPostulacion, PDO::PARAM_INT);
    $stmtUpdate->execute();

    if ($stmtUpdate->rowCount() === 0) {
        throw new Exception('No se pudo actualizar el estado del candidato.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'El candidato "' . htmlspecialchars($postulacion['nombre']) . '" fue seleccionado exitosamente.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
