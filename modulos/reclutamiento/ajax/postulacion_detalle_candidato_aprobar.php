<?php
// postulacion_detalle_candidato_aprobar.php

require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];

    $input = json_decode(file_get_contents('php://input'), true);
    $idCandidato = (int) ($input['id_candidato'] ?? 0);
    $fechaEntrevista = $input['fecha_entrevista'] ?? '';
    $horaEntrevista = $input['hora_entrevista'] ?? '';
    $entrevistadorRRHH = (int) ($input['entrevistador_rrhh'] ?? 0);
    $modalidad = $input['modalidad'] ?? '';
    $notas = trim($input['notas'] ?? '');

    if ($idCandidato <= 0) {
        throw new Exception('ID de candidato inválido');
    }

    if (empty($fechaEntrevista) || empty($horaEntrevista)) {
        throw new Exception('Debe especificar fecha y hora de la entrevista');
    }

    if ($entrevistadorRRHH <= 0) {
        throw new Exception('Debe seleccionar un entrevistador');
    }

    if (empty($modalidad)) {
        throw new Exception('Debe seleccionar la modalidad de entrevista');
    }

    // Verificar que el candidato existe y está en estado Solicitado
    $sqlCheck = "SELECT status FROM postulacion_plaza WHERE id = :id";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bindValue(':id', $idCandidato, PDO::PARAM_INT);
    $stmtCheck->execute();
    $candidato = $stmtCheck->fetch();

    if (!$candidato) {
        throw new Exception('Candidato no encontrado');
    }

    if (strtolower($candidato['status']) !== 'solicitado' && strtolower($candidato['status']) !== 'rechazado') {
        throw new Exception('El candidato ya se encuentra en estado ' . strtoupper($candidato['status']));
    }

    $conn->beginTransaction();

    // Actualizar estado del candidato
    $sqlUpdate = "UPDATE postulacion_plaza 
                  SET status = 'Aprobado',
                      fecha_actualizacion = NOW()
                  WHERE id = :id";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bindValue(':id', $idCandidato, PDO::PARAM_INT);
    $stmtUpdate->execute();

    // Insertar registro de entrevista
    $sqlEntrevista = "INSERT INTO entrevistas_candidatos 
                      (id_postulacion, fecha_entrevista, hora_entrevista, reclutador_entrevista, 
                       modalidad_entrevista, notas_adicionales, usuario_registra, fecha_creacion)
                      VALUES 
                      (:id_postulacion, :fecha_entrevista, :hora_entrevista, :reclutador_entrevista,
                       :modalidad_entrevista, :notas_adicionales, :usuario_registra, NOW())";

    $stmtEntrevista = $conn->prepare($sqlEntrevista);
    $stmtEntrevista->bindValue(':id_postulacion', $idCandidato, PDO::PARAM_INT);
    $stmtEntrevista->bindValue(':fecha_entrevista', $fechaEntrevista);
    $stmtEntrevista->bindValue(':hora_entrevista', $horaEntrevista);
    $stmtEntrevista->bindValue(':reclutador_entrevista', $entrevistadorRRHH, PDO::PARAM_INT);
    $stmtEntrevista->bindValue(':modalidad_entrevista', $modalidad);
    $stmtEntrevista->bindValue(':notas_adicionales', $notas);
    $stmtEntrevista->bindValue(':usuario_registra', $codOperario, PDO::PARAM_INT);
    $stmtEntrevista->execute();

    // Insertar registro de entrevista telefónica (REPLACE para evitar duplicados si ya existía)
    $et = $input['entrevista_telefonica'] ?? [];
    $sqlTelefonica = "REPLACE INTO postulacion_entrevista_telefonica 
                      (id_postulacion, edad, ubicacion_tienda, trabaja_actualmente, disponibilidad, 
                       lugar_trabajo, promedio_devengado, aspiracion_salarial, estudias, 
                       modalidad_horarios, motivo_cambio, disponibilidad_horarios_rotativos, disponibilidad_traslados)
                      VALUES 
                      (:id_postulacion, :edad, :ubicacion_tienda, :trabaja_actualmente, :disponibilidad, 
                       :lugar_trabajo, :promedio_devengado, :aspiracion_salarial, :estudias, 
                       :modalidad_horarios, :motivo_cambio, :disponibilidad_horarios_rotativos, :disponibilidad_traslados)";

    $stmtTel = $conn->prepare($sqlTelefonica);
    $stmtTel->bindValue(':id_postulacion', $idCandidato, PDO::PARAM_INT);
    $stmtTel->bindValue(':edad', (int) ($et['edad'] ?? 0));
    $stmtTel->bindValue(':ubicacion_tienda', !empty($et['ubicacion_tienda']) ? $et['ubicacion_tienda'] : null);
    $stmtTel->bindValue(':trabaja_actualmente', $et['trabaja_actualmente'] ?? null);
    $stmtTel->bindValue(':disponibilidad', $et['disponibilidad'] ?? '');
    $stmtTel->bindValue(':lugar_trabajo', $et['lugar_trabajo'] ?? null);
    $stmtTel->bindValue(':promedio_devengado', !empty($et['promedio_devengado']) ? $et['promedio_devengado'] : null);
    $stmtTel->bindValue(':aspiracion_salarial', $et['aspiracion_salarial'] ?? 0);
    $stmtTel->bindValue(':estudias', $et['estudias'] ?? null);
    $stmtTel->bindValue(':modalidad_horarios', $et['modalidad_horarios'] ?? null);
    $stmtTel->bindValue(':motivo_cambio', $et['motivo_cambio'] ?? null);
    $stmtTel->bindValue(':disponibilidad_horarios_rotativos', $et['disponibilidad_horarios_rotativos'] ?? null);
    $stmtTel->bindValue(':disponibilidad_traslados', $et['disponibilidad_traslados'] ?? null);
    $stmtTel->execute();

    $conn->commit();

    $emailEnviado = true;
    $emailError = '';

    // Enviar invitaciones de calendario
    try {
        require_once __DIR__ . '/../../../core/email/EmailService.php';
        $emailService = new EmailService($conn);

        // Obtener datos del candidato para el email
        $sqlCandi = "SELECT nombre, correo, cargo_aplicado FROM postulacion_plaza WHERE id = :id";
        $stmtCandi = $conn->prepare($sqlCandi);
        $stmtCandi->bindValue(':id', $idCandidato, PDO::PARAM_INT);
        $stmtCandi->execute();
        $datosCandi = $stmtCandi->fetch();

        // Obtener nombre del cargo
        $sqlCargo = "SELECT Nombre FROM NivelesCargos WHERE CodNivelesCargos = :cod";
        $stmtCargo = $conn->prepare($sqlCargo);
        $stmtCargo->bindValue(':cod', $datosCandi['cargo_aplicado'], PDO::PARAM_INT);
        $stmtCargo->execute();
        $nombreCargo = $stmtCargo->fetchColumn();

        // Obtener datos del entrevistador
        $sqlEntrev = "SELECT Nombre, Apellido, email_trabajo FROM Operarios WHERE CodOperario = :cod";
        $stmtEntrev = $conn->prepare($sqlEntrev);
        $stmtEntrev->bindValue(':cod', $entrevistadorRRHH, PDO::PARAM_INT);
        $stmtEntrev->execute();
        $datosEntrev = $stmtEntrev->fetch();

        $asunto = "Entrevista de Trabajo: " . $nombreCargo . " - " . $datosCandi['nombre'];

        $linkEvaluacion = "https://erp.batidospitaya.com/modulos/reclutamiento/postulacion_evaluacion_rh.php?id=" . $idCandidato;
        $descripcion = "Entrevista programada para el puesto de {$nombreCargo}.\n\n" .
            "POR FAVOR, REALICE LA EVALUACIÓN AQUÍ AL FINALIZAR: " . $linkEvaluacion . "\n\n" .
            "Notas: {$notas}";

        // Enviar al candidato (si tiene correo)
        if (!empty($datosCandi['correo'])) {
            $emailService->enviarInvitacionCalendario(
                $codOperario, // El usuario que está aprobando/programando
                $datosCandi['correo'],
                $datosCandi['nombre'],
                $asunto,
                $descripcion,
                $fechaEntrevista,
                $horaEntrevista,
                60,
                $modalidad
            );
        }
    } catch (Exception $e) {
        $emailEnviado = false;
        $emailError = $e->getMessage();
        error_log("Error al enviar invitaciones post-aprobación: " . $e->getMessage());
        // No lanzamos excepción para no revertir la aprobación si solo falla el correo
    }

    echo json_encode([
        'success' => true,
        'message' => 'Candidato aprobado y entrevista programada exitosamente',
        'email_status' => $emailEnviado,
        'email_error' => $emailError,
        'redirect' => 'postulacion_calendario.php'
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
