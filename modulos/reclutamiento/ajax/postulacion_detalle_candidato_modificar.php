<?php
// ajax/postulacion_detalle_candidato_modificar.php

require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    $cargoOperario = $usuario['CodNivelesCargos'];

    if ($cargoOperario != 13) {
        throw new Exception('No tiene permisos para modificar entrevistas');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $idCandidato = (int) ($input['id_candidato'] ?? 0);
    $fechaEntrevista = $input['fecha_entrevista'] ?? '';
    $horaEntrevista = $input['hora_entrevista'] ?? '';
    $entrevistadorRRHH = (int) ($input['entrevistador_rrhh'] ?? 0);
    $modalidad = $input['modalidad'] ?? '';
    $notas = trim($input['notas'] ?? '');

    if ($idCandidato <= 0)
        throw new Exception('ID de candidato inválido');

    $conn->beginTransaction();

    // Actualizar entrevista
    $sqlEntrevista = "UPDATE entrevistas_candidatos 
                      SET fecha_entrevista = :fecha, 
                          hora_entrevista = :hora, 
                          reclutador_entrevista = :reclu, 
                          modalidad_entrevista = :mod, 
                          notas_adicionales = :notas,
                          usuario_actualiza = :usu,
                          fecha_actualizacion = NOW()
                      WHERE id_postulacion = :id";

    $stmt = $conn->prepare($sqlEntrevista);
    $stmt->bindValue(':fecha', $fechaEntrevista);
    $stmt->bindValue(':hora', $horaEntrevista);
    $stmt->bindValue(':reclu', $entrevistadorRRHH, PDO::PARAM_INT);
    $stmt->bindValue(':mod', $modalidad);
    $stmt->bindValue(':notas', $notas);
    $stmt->bindValue(':usu', $codOperario, PDO::PARAM_INT);
    $stmt->bindValue(':id', $idCandidato, PDO::PARAM_INT);
    $stmt->execute();

    // Actualizar entrevista telefónica
    $et = $input['entrevista_telefonica'] ?? [];
    $sqlTel = "UPDATE postulacion_entrevista_telefonica 
               SET edad = :edad,
                   ubicacion_tienda = :ubicacion,
                   trabaja_actualmente = :trabaja,
                   disponibilidad = :disp,
                   lugar_trabajo = :lugar,
                   promedio_devengado = :prom,
                   aspiracion_salarial = :aspir,
                   estudias = :est,
                   modalidad_horarios = :modh,
                   motivo_cambio = :mot,
                   disponibilidad_horarios_rotativos = :hor,
                   disponibilidad_traslados = :tras
               WHERE id_postulacion = :id";

    $stmtTel = $conn->prepare($sqlTel);
    $stmtTel->bindValue(':id', $idCandidato, PDO::PARAM_INT);
    $stmtTel->bindValue(':edad', (int) ($et['edad'] ?? 0));
    $stmtTel->bindValue(':ubicacion', !empty($et['ubicacion_tienda']) ? $et['ubicacion_tienda'] : null);
    $stmtTel->bindValue(':trabaja', $et['trabaja_actualmente'] ?? null);
    $stmtTel->bindValue(':disp', $et['disponibilidad'] ?? '');
    $stmtTel->bindValue(':lugar', $et['lugar_trabajo'] ?? null);
    $stmtTel->bindValue(':prom', !empty($et['promedio_devengado']) ? $et['promedio_devengado'] : null);
    $stmtTel->bindValue(':aspir', $et['aspiracion_salarial'] ?? 0);
    $stmtTel->bindValue(':est', $et['estudias'] ?? null);
    $stmtTel->bindValue(':modh', $et['modalidad_horarios'] ?? null);
    $stmtTel->bindValue(':mot', $et['motivo_cambio'] ?? null);
    $stmtTel->bindValue(':hor', $et['disponibilidad_horarios_rotativos'] ?? null);
    $stmtTel->bindValue(':tras', $et['disponibilidad_traslados'] ?? null);
    $stmtTel->execute();

    $conn->commit();

    // Re-enviar invitación
    $emailEnviado = true;
    $emailError = '';
    try {
        require_once __DIR__ . '/../../../core/email/EmailService.php';
        $emailService = new EmailService($conn);

        $sqlCandi = "SELECT nombre, correo, cargo_aplicado FROM postulacion_plaza WHERE id = :id";
        $stmtCandi = $conn->prepare($sqlCandi);
        $stmtCandi->bindValue(':id', $idCandidato, PDO::PARAM_INT);
        $stmtCandi->execute();
        $datosCandi = $stmtCandi->fetch();

        $sqlCargo = "SELECT Nombre FROM NivelesCargos WHERE CodNivelesCargos = :cod";
        $stmtCargo = $conn->prepare($sqlCargo);
        $stmtCargo->bindValue(':cod', $datosCandi['cargo_aplicado'], PDO::PARAM_INT);
        $stmtCargo->execute();
        $nombreCargo = $stmtCargo->fetchColumn();

        $asunto = "ACTUALIZACIÓN - Entrevista de Trabajo: " . $nombreCargo . " - " . $datosCandi['nombre'];
        $descripcion = "Se ha modificado la fecha/hora de su entrevista.\n\nNotas: {$notas}";

        if (!empty($datosCandi['correo'])) {
            $emailService->enviarInvitacionCalendario($codOperario, $datosCandi['correo'], $datosCandi['nombre'], $asunto, $descripcion, $fechaEntrevista, $horaEntrevista, 60, $modalidad);
        }
    } catch (Exception $e) {
        $emailEnviado = false;
        $emailError = $e->getMessage();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Entrevista modificada correctamente',
        'email_status' => $emailEnviado,
        'email_error' => $emailError
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction())
        $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
