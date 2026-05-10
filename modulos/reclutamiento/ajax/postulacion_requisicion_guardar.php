<?php
// postulacion_requisicion_guardar.php

require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];

    // Validar campos obligatorios
    $nombreCargo = trim($_POST['nombre_cargo'] ?? '');
    $areaCargo = trim($_POST['area_cargo'] ?? ''); // Puede ser vacío
    $cantidad = (int) ($_POST['cantidad'] ?? 1);
    $salarioPropuesto = (float) ($_POST['salario_propuesto'] ?? 0);
    $nivelUrgencia = (int) ($_POST['nivel_urgencia'] ?? 1);
    $cargoReportaA = (int) ($_POST['cargo_reporta_a'] ?? 0);
    $justificacion = trim($_POST['justificacion'] ?? '');
    $sucursal = (int) ($_POST['sucursal'] ?? 0);

    // Nuevos campos
    $estudiosMinimos = trim($_POST['estudios_minimos'] ?? '');
    $carrerasAptas = trim($_POST['carreras_aptas'] ?? '');
    $conocimientosEspecificos = trim($_POST['conocimientos_especificos'] ?? '');
    $idiomas = trim($_POST['idiomas'] ?? '');
    $herramientasOffice = trim($_POST['herramientas_office'] ?? '');
    $aptitudesEspecificas = trim($_POST['aptitudes_especificas'] ?? '');
    $experienciaDeseada = trim($_POST['experiencia_deseada'] ?? '');
    $funcionesResponsabilidades = trim($_POST['funciones_responsabilidades'] ?? '');
    $idRequisicion = (int) ($_POST['id_requisicion'] ?? 0);

    // Validaciones
    if (empty($nombreCargo) || strlen($nombreCargo) < 3) {
        throw new Exception('El nombre del cargo debe tener al menos 3 caracteres');
    }

    if ($sucursal <= 0) {
        throw new Exception('Debe seleccionar una sucursal válida');
    }

    if ($cantidad < 1) {
        throw new Exception('La cantidad debe ser al menos 1');
    }

    if ($salarioPropuesto <= 0) {
        throw new Exception('El salario propuesto debe ser mayor a 0');
    }

    if (strlen($justificacion) < 20) {
        throw new Exception('La justificación debe tener al menos 20 caracteres');
    }

    if ($cargoReportaA <= 0) {
        throw new Exception('Debe seleccionar un jefe directo válido');
    }

    if ($idRequisicion > 0) {
        // Actualizar
        $sql = "UPDATE requisicion_personal SET 
                nombre_cargo = :nombre_cargo, area_cargo = :area_cargo, sucursal = :sucursal, 
                cantidad = :cantidad, salario_propuesto = :salario_propuesto, 
                nivel_urgencia = :nivel_urgencia, cargo_reporta_a = :cargo_reporta_a, 
                justificacion = :justificacion, estudios_minimos = :estudios_minimos, 
                carreras_aptas = :carreras_aptas, conocimientos_especificos = :conocimientos_especificos, 
                idiomas = :idiomas, herramientas_office = :herramientas_office, 
                aptitudes_especificas = :aptitudes_especificas, experiencia_deseada = :experiencia_deseada, 
                funciones_responsabilidades = :funciones_responsabilidades
                WHERE id = :id AND status = 'Solicitado'";
    } else {
        // Insertar en la base de datos
        $sql = "INSERT INTO requisicion_personal 
                (nombre_cargo, area_cargo, sucursal, cantidad, salario_propuesto, 
                 nivel_urgencia, cargo_reporta_a, justificacion, 
                 estudios_minimos, carreras_aptas, conocimientos_especificos, idiomas, 
                 herramientas_office, aptitudes_especificas, experiencia_deseada, funciones_responsabilidades,
                 status, usuario_registra, fecha_creacion)
                VALUES 
                (:nombre_cargo, :area_cargo, :sucursal, :cantidad, :salario_propuesto, 
                 :nivel_urgencia, :cargo_reporta_a, :justificacion, 
                 :estudios_minimos, :carreras_aptas, :conocimientos_especificos, :idiomas, 
                 :herramientas_office, :aptitudes_especificas, :experiencia_deseada, :funciones_responsabilidades,
                 'Solicitado', :usuario_registra, NOW())";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':nombre_cargo', $nombreCargo);
    $stmt->bindValue(':area_cargo', $areaCargo ?: null); // NULL si está vacío
    $stmt->bindValue(':sucursal', $sucursal, PDO::PARAM_INT);
    $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
    $stmt->bindValue(':salario_propuesto', $salarioPropuesto);
    $stmt->bindValue(':nivel_urgencia', $nivelUrgencia, PDO::PARAM_INT);
    $stmt->bindValue(':cargo_reporta_a', $cargoReportaA, PDO::PARAM_INT);
    $stmt->bindValue(':justificacion', $justificacion);

    $stmt->bindValue(':estudios_minimos', $estudiosMinimos);
    $stmt->bindValue(':carreras_aptas', $carrerasAptas);
    $stmt->bindValue(':conocimientos_especificos', $conocimientosEspecificos);
    $stmt->bindValue(':idiomas', $idiomas);
    $stmt->bindValue(':herramientas_office', $herramientasOffice);
    $stmt->bindValue(':aptitudes_especificas', $aptitudesEspecificas);
    $stmt->bindValue(':experiencia_deseada', $experienciaDeseada);
    $stmt->bindValue(':funciones_responsabilidades', $funcionesResponsabilidades);

    if ($idRequisicion > 0) {
        $stmt->bindValue(':id', $idRequisicion, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':usuario_registra', $codOperario, PDO::PARAM_INT);
    }

    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => $idRequisicion > 0 ? 'Requisición actualizada exitosamente' : 'Requisición enviada exitosamente',
        'id' => $idRequisicion > 0 ? $idRequisicion : $conn->lastInsertId()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>