<?php
// postulacion_detalle_requisicion_aprobar.php

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
    
    // Verificar que la requisición existe y está en estado Solicitado
    $sqlCheck = "SELECT * FROM requisicion_personal WHERE id = :id";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bindValue(':id', $idRequisicion, PDO::PARAM_INT);
    $stmtCheck->execute();
    $requisicion = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$requisicion) {
        throw new Exception('Requisición no encontrada');
    }
    
    if ($requisicion['status'] !== 'Solicitado') {
        throw new Exception('La requisición ya fue procesada anteriormente');
    }
    
    $conn->beginTransaction();
    
    // ========================================
    // PASO 1: Validar que no existe el cargo en NivelesCargos
    // ========================================
    $nombreCargo = trim($requisicion['nombre_cargo']);
    
    $sqlCheckCargo = "SELECT CodNivelesCargos FROM NivelesCargos WHERE LOWER(Nombre) = LOWER(:nombre)";
    $stmtCheckCargo = $conn->prepare($sqlCheckCargo);
    $stmtCheckCargo->bindValue(':nombre', $nombreCargo);
    $stmtCheckCargo->execute();
    $cargoExistente = $stmtCheckCargo->fetch();
    
    if ($cargoExistente) {
        throw new Exception('Ya existe un cargo con el nombre "' . $nombreCargo . '" en el sistema');
    }
    
    // ========================================
    // PASO 2: Insertar nuevo cargo en NivelesCargos
    // ========================================
    $areaCargo = $requisicion['area_cargo'] ?: null;
    $reportaA = $requisicion['cargo_reporta_a']; // Este es CodOperario del jefe
    
    // Obtener el CodNivelesCargos del jefe para el campo ReportaA
    $sqlJefe = "SELECT anc.CodNivelesCargos 
                FROM AsignacionNivelesCargos anc
                WHERE anc.CodOperario = :cod_operario
                AND anc.Fin IS NULL
                ORDER BY anc.fecha_hora_regsys DESC
                LIMIT 1";
    $stmtJefe = $conn->prepare($sqlJefe);
    $stmtJefe->bindValue(':cod_operario', $reportaA, PDO::PARAM_INT);
    $stmtJefe->execute();
    $jefeData = $stmtJefe->fetch();
    $reportaACodCargo = $jefeData ? $jefeData['CodNivelesCargos'] : null;
    
    $sqlInsertCargo = "INSERT INTO NivelesCargos 
                       (Nombre, Area, ReportaA, Peso, Operaciones, Marcacion, DisponibleRegistros, 
                        BeneficiosAdministrativos, PermisosLider)
                       VALUES 
                       (:nombre, :area, :reporta_a, 0.0, NULL, NULL, NULL, NULL, NULL)";
    
    $stmtInsertCargo = $conn->prepare($sqlInsertCargo);
    $stmtInsertCargo->bindValue(':nombre', $nombreCargo);
    $stmtInsertCargo->bindValue(':area', $areaCargo);
    $stmtInsertCargo->bindValue(':reporta_a', $reportaACodCargo, PDO::PARAM_INT);
    $stmtInsertCargo->execute();
    
    $nuevoCodCargo = $conn->lastInsertId();
    
    // ========================================
    // PASO 3: Actualizar estado de la requisición
    // ========================================
    $sqlUpdate = "UPDATE requisicion_personal 
                  SET status = 'Aprobado',
                      comentario_aprobacion_rechazo = :comentario,
                      usuario_modifica = :usuario_modifica,
                      fecha_actualizacion = NOW()
                  WHERE id = :id";
    
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bindValue(':comentario', $comentario);
    $stmtUpdate->bindValue(':usuario_modifica', $codOperario, PDO::PARAM_INT);
    $stmtUpdate->bindValue(':id', $idRequisicion, PDO::PARAM_INT);
    $stmtUpdate->execute();
    
    // ========================================
    // PASO 4: Crear registro en plazas_cargos
    // ========================================
    // Determinar el área y sucursal según la requisición
    $areaTablaCargos = 'Administrativo'; // Por defecto
    $sucursalCargos = $requisicion['sucursal'];
    
    // Si el cargo nuevo está en producción (opcional, puedes ajustar esta lógica)
    // Por ahora asumimos que todos los nuevos cargos van a Administrativo
    
    $sqlInsertPlaza = "INSERT INTO plazas_cargos 
                       (cargo, cantidad_real, sucursal, area, salario_propuesto, nivel_urgencia, 
                        visible_web, usuario_registra, fecha_creacion)
                       VALUES 
                       (:cargo, :cantidad, :sucursal, :area, :salario, :urgencia, 0, :usuario, NOW())";
    
    $stmtInsertPlaza = $conn->prepare($sqlInsertPlaza);
    $stmtInsertPlaza->bindValue(':cargo', $nuevoCodCargo, PDO::PARAM_INT);
    $stmtInsertPlaza->bindValue(':cantidad', $requisicion['cantidad'], PDO::PARAM_INT);
    $stmtInsertPlaza->bindValue(':sucursal', $sucursalCargos, PDO::PARAM_INT);
    $stmtInsertPlaza->bindValue(':area', $areaTablaCargos);
    $stmtInsertPlaza->bindValue(':salario', $requisicion['salario_propuesto']);
    $stmtInsertPlaza->bindValue(':urgencia', $requisicion['nivel_urgencia'], PDO::PARAM_INT);
    $stmtInsertPlaza->bindValue(':usuario', $codOperario, PDO::PARAM_INT);
    $stmtInsertPlaza->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Requisición aprobada y cargo creado exitosamente',
        'nuevo_cod_cargo' => $nuevoCodCargo
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
?>