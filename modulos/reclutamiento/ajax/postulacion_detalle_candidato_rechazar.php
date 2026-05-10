<?php
// postulacion_detalle_candidato_rechazar.php

require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];

    $input = json_decode(file_get_contents('php://input'), true);
    $idCandidato = (int) ($input['id_candidato'] ?? 0);
    $motivo = trim($input['motivo'] ?? '');

    if ($idCandidato <= 0) {
        throw new Exception('ID de candidato inválido');
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
    $sql = "UPDATE postulacion_plaza 
            SET status = 'Rechazado',
                notas_entrevista = :motivo,
                fecha_actualizacion = NOW()
            WHERE id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':motivo', $motivo);
    $stmt->bindValue(':id', $idCandidato, PDO::PARAM_INT);
    $stmt->execute();

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

    echo json_encode([
        'success' => true,
        'message' => 'Candidato rechazado'
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
