<?php
// ajax/postulacion_detalle_candidato_guardar_parcial.php

require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];

    $input = json_decode(file_get_contents('php://input'), true);
    $idCandidato = (int) ($input['id_candidato'] ?? 0);

    if ($idCandidato <= 0) {
        throw new Exception('ID de candidato inválido');
    }

    $conn->beginTransaction();

    // 1. Guardar Entrevista Técnica Telefónica (Upsert)
    $et = $input['entrevista_telefonica'] ?? [];

    // Primero verificar si existe
    $sqlCheck = "SELECT COUNT(*) FROM postulacion_entrevista_telefonica WHERE id_postulacion = :id";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bindValue(':id', $idCandidato, PDO::PARAM_INT);
    $stmtCheck->execute();
    $exists = (bool) $stmtCheck->fetchColumn();

    if ($exists) {
        $sqlTelefonica = "UPDATE postulacion_entrevista_telefonica 
                          SET edad = :edad, 
                              ubicacion_tienda = :ubicacion_tienda, 
                              trabaja_actualmente = :trabaja_actualmente, 
                              disponibilidad = :disponibilidad, 
                              lugar_trabajo = :lugar_trabajo, 
                              promedio_devengado = :promedio_devengado, 
                              aspiracion_salarial = :aspiracion_salarial, 
                              estudias = :estudias, 
                              modalidad_horarios = :modalidad_horarios, 
                              motivo_cambio = :motivo_cambio, 
                              disponibilidad_horarios_rotativos = :disponibilidad_horarios_rotativos, 
                              disponibilidad_traslados = :disponibilidad_traslados
                          WHERE id_postulacion = :id_postulacion";
    } else {
        $sqlTelefonica = "INSERT INTO postulacion_entrevista_telefonica 
                          (id_postulacion, edad, ubicacion_tienda, trabaja_actualmente, disponibilidad, 
                           lugar_trabajo, promedio_devengado, aspiracion_salarial, estudias, 
                           modalidad_horarios, motivo_cambio, disponibilidad_horarios_rotativos, disponibilidad_traslados)
                          VALUES 
                          (:id_postulacion, :edad, :ubicacion_tienda, :trabaja_actualmente, :disponibilidad, 
                           :lugar_trabajo, :promedio_devengado, :aspiracion_salarial, :estudias, 
                           :modalidad_horarios, :motivo_cambio, :disponibilidad_horarios_rotativos, :disponibilidad_traslados)";
    }

    $stmtTel = $conn->prepare($sqlTelefonica);
    $stmtTel->bindValue(':id_postulacion', $idCandidato, PDO::PARAM_INT);
    $stmtTel->bindValue(':edad', (int) ($et['edad'] ?? 0));
    $stmtTel->bindValue(':ubicacion_tienda', !empty($et['ubicacion_tienda']) ? $et['ubicacion_tienda'] : null);
    $stmtTel->bindValue(':trabaja_actualmente', $et['trabaja_actualmente'] ?? null);
    $stmtTel->bindValue(':disponibilidad', $et['disponibilidad'] ?? '');
    $stmtTel->bindValue(':lugar_trabajo', $et['lugar_trabajo'] ?? null);
    $stmtTel->bindValue(':promedio_devengado', !empty($et['promedio_devengado']) ? $et['promedio_devengado'] : null);
    $stmtTel->bindValue(':aspiracion_salarial', !empty($et['aspiracion_salarial']) ? $et['aspiracion_salarial'] : 0);
    $stmtTel->bindValue(':estudias', $et['estudias'] ?? null);
    $stmtTel->bindValue(':modalidad_horarios', $et['modalidad_horarios'] ?? null);
    $stmtTel->bindValue(':motivo_cambio', $et['motivo_cambio'] ?? null);
    $stmtTel->bindValue(':disponibilidad_horarios_rotativos', $et['disponibilidad_horarios_rotativos'] ?? null);
    $stmtTel->bindValue(':disponibilidad_traslados', $et['disponibilidad_traslados'] ?? null);
    $stmtTel->execute();

    // 2. Guardar datos de la entrevista (si se proporcionaron fecha/hora)
    $fechaEntrevista = $input['fecha_entrevista'] ?? '';
    $horaEntrevista = $input['hora_entrevista'] ?? '';
    $entrevistadorRRHH = (int) ($input['entrevistador_rrhh'] ?? 0);
    $modalidad = $input['modalidad'] ?? '';
    $notas = trim($input['notas'] ?? '');

    // Solo guardar entrevista si todos los campos requeridos (incluyendo hora) están presentes
    // para evitar violar el constraint NOT NULL de hora_entrevista en la BD
    if (!empty($fechaEntrevista) && !empty($horaEntrevista) && $entrevistadorRRHH > 0 && !empty($modalidad)) {
        $sqlCheckEnt = "SELECT COUNT(*) FROM entrevistas_candidatos WHERE id_postulacion = :id";
        $stmtCheckEnt = $conn->prepare($sqlCheckEnt);
        $stmtCheckEnt->bindValue(':id', $idCandidato, PDO::PARAM_INT);
        $stmtCheckEnt->execute();
        $entExists = (bool) $stmtCheckEnt->fetchColumn();

        if ($entExists) {
            $sqlEnt = "UPDATE entrevistas_candidatos 
                       SET fecha_entrevista = :fecha, 
                           hora_entrevista = :hora, 
                           reclutador_entrevista = :reclutador, 
                           modalidad_entrevista = :modalidad, 
                           notas_adicionales = :notas
                       WHERE id_postulacion = :id_postulacion";
        } else {
            $sqlEnt = "INSERT INTO entrevistas_candidatos 
                       (id_postulacion, fecha_entrevista, hora_entrevista, reclutador_entrevista, 
                        modalidad_entrevista, notas_adicionales, usuario_registra, fecha_creacion)
                       VALUES 
                       (:id_postulacion, :fecha, :hora, :reclutador, :modalidad, :notas, :usuario, NOW())";
        }

        $stmtEnt = $conn->prepare($sqlEnt);
        $stmtEnt->bindValue(':id_postulacion', $idCandidato, PDO::PARAM_INT);
        $stmtEnt->bindValue(':fecha', !empty($fechaEntrevista) ? $fechaEntrevista : null);
        $stmtEnt->bindValue(':hora', !empty($horaEntrevista) ? $horaEntrevista : null);
        $stmtEnt->bindValue(':reclutador', $entrevistadorRRHH > 0 ? $entrevistadorRRHH : null);
        $stmtEnt->bindValue(':modalidad', !empty($modalidad) ? $modalidad : null);
        $stmtEnt->bindValue(':notas', $notas);
        if (!$entExists) {
            $stmtEnt->bindValue(':usuario', $codOperario, PDO::PARAM_INT);
        }
        $stmtEnt->execute();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Datos guardados correctamente'
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
