<?php
require_once '../../../core/auth/auth.php';

require_once '../../../core/permissions/permissions.php';
$cargoOperario = $_SESSION['cargo_cod'] ?? 0;

// Verificar que solo usuarios con permiso de aprobar puedan acceder
if (!tienePermiso('faltas_manual', 'aprobar', $cargoOperario)) {
    header('Location: /index.php');
    exit();
}

/**
 * Obtiene el porcentaje de pago para un tipo de falta específico
 */
function obtenerPorcentajePagoTipoFalta($tipoFalta) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT porcentaje_pago 
        FROM tipos_falta 
        WHERE codigo = ? 
        LIMIT 1
    ");
    $stmt->execute([$tipoFalta]);
    $result = $stmt->fetch();
    
    return $result ? $result['porcentaje_pago'] : 0;
}

// Procesar edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_falta'])) {
    try {
        $id = (int)$_POST['id'];
        
        // NUEVA VALIDACIÓN: Obtener información de la falta antes de editar
        $stmt = $conn->prepare("SELECT cod_operario, fecha_falta FROM faltas_manual WHERE id = ?");
        $stmt->execute([$id]);
        $faltaExistente = $stmt->fetch();
        
        if (!$faltaExistente) {
            $_SESSION['error'] = 'Falta no encontrada';
            $params = [];
            if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
            if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
            if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
            if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];
            header('Location: ../faltas_manual.php?' . http_build_query($params));
            exit();
        }
        
        // NUEVA VALIDACIÓN: Verificar que la fecha de falta no sea posterior a liquidación
        if (fechaPosteriorLiquidacion($faltaExistente['cod_operario'], $faltaExistente['fecha_falta'])) {
            $_SESSION['error'] = 'No se puede editar: La falta es posterior a la fecha de liquidación del colaborador';
            $params = [];
            if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
            if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
            if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
            if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];
            header('Location: ../faltas_manual.php?' . http_build_query($params));
            exit();
        }
        
        // NUEVA VALIDACIÓN: Verificar que el operario tenga contrato
        if (!operarioTieneContrato($faltaExistente['cod_operario'])) {
            $_SESSION['error'] = 'No se puede editar: El colaborador no tiene registro de contrato. Contactar con RH.';
            $params = [];
            if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
            if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
            if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
            if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];
            header('Location: ../faltas_manual.php?' . http_build_query($params));
            exit();
        }
        
        $tipoFalta = $_POST['tipo_falta'];
        $observaciones_rrhh = $_POST['observaciones_rrhh'] ?? null;
        
        // Validar que las observaciones RRHH no estén vacías
        if (empty($observaciones_rrhh)) {
            $_SESSION['error'] = 'El campo Observaciones RRHH es obligatorio';
            header('Location: ../faltas_manual.php?' . http_build_query($_GET));
            exit();
        }
        
        // OBTENER EL NUEVO PORCENTAJE BASADO EN EL TIPO DE FALTA
        $porcentajePago = obtenerPorcentajePagoTipoFalta($tipoFalta);
        
        // Actualizar incluyendo el porcentaje de pago
        $stmt = $conn->prepare("
            UPDATE faltas_manual 
            SET tipo_falta = ?, 
                observaciones_rrhh = ?,
                porcentaje_pago = ?,
                actualizado_por = ?,
                fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $tipoFalta,
            $observaciones_rrhh,
            $porcentajePago, // NUEVO: porcentaje actualizado
            $_SESSION['usuario_id'],
            $id
        ]);
        
        // Registrar auditoría y ajustar marcación en la marcación si el tipo implica presencia del colaborador
        registrarAuditoriaMarcacionFalta(
            $faltaExistente['cod_operario'],
            $faltaExistente['fecha_falta'],
            $faltaExistente['cod_sucursal'],
            $tipoFalta,
            $id
        );
        
        $_SESSION['exito'] = 'Falta manual actualizada correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al actualizar la falta manual: ' . $e->getMessage();
    }
    
    // Redirigir manteniendo TODOS los filtros
    $params = [];
    if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
    if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
    if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
    if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];
    
    header('Location: ../faltas_manual.php?' . http_build_query($params));
    exit();
}

// Si no es POST, redirigir manteniendo filtros también
$params = [];
if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];

header('Location: ../faltas_manual.php?' . http_build_query($params));
exit();

/**
 * Registra auditoría y completa marcaciones parciales (entradas/salidas omitidas) 
 * con el horario programado cuando una falta es justificada (presencia confirmada).
 *
 * - Respalda las horas reales (pueden ser NULL) en hora_ingreso_original y hora_salida_original.
 * - Ajusta los campos vacíos (NULL) a las horas programadas del horario semanal.
 * - Si el tipo de falta ya no es justificado, revierte el ajuste.
 */
function registrarAuditoriaMarcacionFalta($codOperario, $fechaFalta, $codSucursal, $tipoFalta, $idFalta)
{
    global $conn;

    // Tipos de falta que implican presencia (omisión de marcación / ajustes)
    $tiposConPresencia = [
        'Omision_marcacion',
        'Atencion_medica',
        'Cita_medica_programada',
        'Ajuste_horario',
        'Compensacion_feria',
        'Compensacion_dia_trabajado',
    ];

    if (!in_array($tipoFalta, $tiposConPresencia)) {
        // Si no es un tipo de presencia justificada, revertir cualquier ajuste hecho por esta falta
        revertirAjusteMarcacionPorFalta($codOperario, $fechaFalta, $idFalta);
        return;
    }

    // Buscar marcación parcial ese día (solo entrada O solo salida, o incluso completa pero para vincular)
    $stmt = $conn->prepare("
        SELECT id, hora_ingreso, hora_salida, ajustado_por_tardanza, id_falta_ajuste
        FROM marcaciones
        WHERE CodOperario = ?
          AND fecha = ?
          AND sucursal_codigo = ?
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $fechaFalta, $codSucursal]);
    $marcacion = $stmt->fetch();

    if (!$marcacion) {
        return; // Sin marcación → nada que ajustar
    }

    // Ignorar si ya está ajustada por otra falta o tardanza
    if ($marcacion['ajustado_por_tardanza'] == 1 && $marcacion['id_falta_ajuste'] != $idFalta) {
        error_log("registrarAuditoriaMarcacionFalta: Marcación ID {$marcacion['id']} ya está ajustada por otra causa, se ignora");
        return;
    }

    // Obtener semana y horario programado
    $semana = obtenerSemanaPorFecha($fechaFalta);
    if (!$semana) {
        error_log("registrarAuditoriaMarcacionFalta: No se encontró semana para la fecha $fechaFalta");
        return;
    }

    $horarioProgramado = obtenerHorarioOperacionesPorDia($codOperario, $semana['id'], $codSucursal, $fechaFalta);
    if (!$horarioProgramado) {
        error_log("registrarAuditoriaMarcacionFalta: Sin horario programado para operario $codOperario en $fechaFalta");
        return;
    }

    $horaProgramadaEntrada = $horarioProgramado['hora_entrada'] ?? null;
    $horaProgramadaSalida  = $horarioProgramado['hora_salida'] ?? null;

    $original_ingreso = $marcacion['hora_ingreso'];
    $original_salida  = $marcacion['hora_salida'];

    // Completar los valores nulos con las horas programadas correspondientes
    $nuevo_ingreso = ($original_ingreso === null && !empty($horaProgramadaEntrada)) ? $horaProgramadaEntrada : $original_ingreso;
    $nuevo_salida  = ($original_salida  === null && !empty($horaProgramadaSalida))  ? $horaProgramadaSalida  : $original_salida;

    $stmt = $conn->prepare("
        UPDATE marcaciones
        SET hora_ingreso_original = ?,
            hora_salida_original  = ?,
            hora_ingreso          = ?,
            hora_salida           = ?,
            ajustado_por_tardanza = 1,
            id_falta_ajuste       = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $original_ingreso,
        $original_salida,
        $nuevo_ingreso,
        $nuevo_salida,
        $idFalta,
        $marcacion['id']
    ]);

    error_log("registrarAuditoriaMarcacionFalta: Marcación ID {$marcacion['id']} ajustada/completada por falta ID $idFalta (tipo: $tipoFalta)");
}

/**
 * Revierte el ajuste de marcación realizado por una falta manual
 * restaurando los valores nulos/originales respaldados.
 */
function revertirAjusteMarcacionPorFalta($codOperario, $fechaFalta, $idFalta)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT id, hora_ingreso_original, hora_salida_original
        FROM marcaciones
        WHERE CodOperario = ?
          AND fecha = ?
          AND ajustado_por_tardanza = 1
          AND id_falta_ajuste = ?
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $fechaFalta, $idFalta]);
    $marcacion = $stmt->fetch();

    if (!$marcacion) {
        return; // No estaba ajustada por esta falta
    }

    $stmt = $conn->prepare("
        UPDATE marcaciones
        SET hora_ingreso          = hora_ingreso_original,
            hora_salida           = hora_salida_original,
            hora_ingreso_original = NULL,
            hora_salida_original  = NULL,
            ajustado_por_tardanza = 0,
            id_falta_ajuste       = NULL
        WHERE id = ?
    ");
    $stmt->execute([$marcacion['id']]);

    error_log("revertirAjusteMarcacionPorFalta: Marcación ID {$marcacion['id']} revertida (restaurados backups de falta ID $idFalta)");
}