<?php
// postulacion_detalle_requisicion_rechazar.php

require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $idRequisicion = (int)($input['id_requisicion'] ?? 0);
    $comentario = trim($input['comentario'] ?? '');
    
    if ($idRequisicion <= 0) {
        throw new Exception('ID de requisición inválido');
    }
    
    if (empty($comentario)) {
        throw new Exception('El comentario es obligatorio para rechazar una requisición');
    }
    
    // Verificar que la requisición existe y está en estado Solicitado
    $sqlCheck = "SELECT status FROM requisicion_personal WHERE id = :id";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bindValue(':id', $idRequisicion, PDO::PARAM_INT);
    $stmtCheck->execute();
    $requisicion = $stmtCheck->fetch();
    
    if (!$requisicion) {
        throw new Exception('Requisición no encontrada');
    }
    
    if ($requisicion['status'] !== 'Solicitado') {
        throw new Exception('La requisición ya fue procesada anteriormente');
    }
    
    // Actualizar estado a Rechazado
    $sql = "UPDATE requisicion_personal 
            SET status = 'Rechazado',
                comentario_aprobacion_rechazo = :comentario,
                usuario_modifica = :usuario_modifica,
                fecha_actualizacion = NOW()
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':comentario', $comentario);
    $stmt->bindValue(':usuario_modifica', $codOperario, PDO::PARAM_INT);
    $stmt->bindValue(':id', $idRequisicion, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Requisición rechazada'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>