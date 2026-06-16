<?php
// postulacion_cambiar_plaza.php
// Cambia la plaza asignada a un candidato (modifica cargo_aplicado y sucursal_aplicada)
// sin alterar su historial de evaluaciones o entrevistas.

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar permiso de aprobación/edición
    if (!tienePermiso('postulacion_plazas_activas', 'aprobar', $cargoOperario)) {
        throw new Exception('No tienes permiso para realizar esta acción.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $idCandidato = (int) ($input['id_candidato'] ?? 0);
    $idPlaza = (int) ($input['id_plaza'] ?? 0);

    if ($idCandidato <= 0) {
        throw new Exception('ID de candidato inválido.');
    }
    if ($idPlaza <= 0) {
        throw new Exception('ID de plaza destino inválido.');
    }

    // 1. Obtener la plaza destino para conocer su cargo y sucursal
    $sqlPlaza = "SELECT cargo, sucursal FROM plazas_cargos WHERE id = :id_plaza";
    $stmtPlaza = $conn->prepare($sqlPlaza);
    $stmtPlaza->bindValue(':id_plaza', $idPlaza, PDO::PARAM_INT);
    $stmtPlaza->execute();
    $plaza = $stmtPlaza->fetch(PDO::FETCH_ASSOC);

    if (!$plaza) {
        throw new Exception('La plaza seleccionada no existe o no está activa.');
    }

    $nuevoCargo = $plaza['cargo'];
    $nuevaSucursal = $plaza['sucursal'];

    // 2. Actualizar el candidato en postulacion_plaza
    $sqlUpdate = "UPDATE postulacion_plaza 
                  SET cargo_aplicado = :cargo, 
                      sucursal_aplicada = :sucursal, 
                      fecha_actualizacion = NOW() 
                  WHERE id = :id_candidato";
    
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bindValue(':cargo', $nuevoCargo, PDO::PARAM_INT);
    if ($nuevaSucursal === null) {
        $stmtUpdate->bindValue(':sucursal', null, PDO::PARAM_NULL);
    } else {
        $stmtUpdate->bindValue(':sucursal', $nuevaSucursal, PDO::PARAM_INT);
    }
    $stmtUpdate->bindValue(':id_candidato', $idCandidato, PDO::PARAM_INT);
    $stmtUpdate->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Plaza del candidato cambiada exitosamente.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
