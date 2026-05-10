<?php
// ajax/postulacion_detalle_candidato_cancelar.php

require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if ($cargoOperario != 13) {
        throw new Exception('No tiene permisos para cancelar entrevistas');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $idCandidato = (int) ($input['id_candidato'] ?? 0);

    if ($idCandidato <= 0)
        throw new Exception('ID de candidato inválido');

    $conn->beginTransaction();

    // Revertir estado del candidato
    $sqlPlaza = "UPDATE postulacion_plaza SET status = 'solicitado', fecha_actualizacion = NOW() WHERE id = :id";
    $stmtPlaza = $conn->prepare($sqlPlaza);
    $stmtPlaza->bindValue(':id', $idCandidato, PDO::PARAM_INT);
    $stmtPlaza->execute();

    // Eliminar entrevista (esto opcional, pero mejor marcar como cancelada o borrar)
    $sqlDelete = "DELETE FROM entrevistas_candidatos WHERE id_postulacion = :id";
    $stmtDelete = $conn->prepare($sqlDelete);
    $stmtDelete->bindValue(':id', $idCandidato, PDO::PARAM_INT);
    $stmtDelete->execute();

    // Opcional: Eliminar datos de entrevista telefónica si se prefiere empezar de cero
    // $sqlDeleteTel = "DELETE FROM postulacion_entrevista_telefonica WHERE id_postulacion = :id";
    // $stmtDeleteTel = $conn->prepare($sqlDeleteTel);
    // $stmtDeleteTel->bindValue(':id', $idCandidato, PDO::PARAM_INT);
    // $stmtDeleteTel->execute();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Entrevista cancelada y candidato devuelto a Solicitado']);

} catch (Exception $e) {
    if ($conn->inTransaction())
        $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
