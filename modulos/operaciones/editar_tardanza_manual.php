<?php
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';

// Verificar permiso de aprobar tardanzas
$usuario = obtenerUsuarioActual();
if (!tienePermiso('tardanzas_manual', 'aprobar', $usuario['CodNivelesCargos'])) {
    header('Location: /index.php');
    exit();
}

// Procesar edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_tardanza'])) {
    try {
        $id = (int) $_POST['id'];

        // Obtener información de la tardanza antes de editar
        $stmt = $conn->prepare("SELECT cod_operario, fecha_tardanza, cod_sucursal FROM TardanzasManuales WHERE id = ?");
        $stmt->execute([$id]);
        $tardanzaExistente = $stmt->fetch();

        if (!$tardanzaExistente) {
            $_SESSION['error'] = 'Tardanza no encontrada';
            $params = [];
            if (isset($_POST['sucursal'])) $params['sucursal'] = $_POST['sucursal'];
            if (isset($_POST['desde']))    $params['desde']    = $_POST['desde'];
            if (isset($_POST['hasta']))    $params['hasta']    = $_POST['hasta'];
            header('Location: tardanzas_manual.php?' . http_build_query($params));
            exit();
        }

        // Verificar que la fecha no sea posterior a liquidación
        if (fechaPosteriorLiquidacion($tardanzaExistente['cod_operario'], $tardanzaExistente['fecha_tardanza'])) {
            $_SESSION['error'] = 'No se puede editar: La tardanza es posterior a la fecha de liquidación del colaborador';
            $params = [];
            if (isset($_POST['sucursal'])) $params['sucursal'] = $_POST['sucursal'];
            if (isset($_POST['desde']))    $params['desde']    = $_POST['desde'];
            if (isset($_POST['hasta']))    $params['hasta']    = $_POST['hasta'];
            header('Location: tardanzas_manual.php?' . http_build_query($params));
            exit();
        }

        // Verificar que el operario tenga contrato
        if (!operarioTieneContrato($tardanzaExistente['cod_operario'])) {
            $_SESSION['error'] = 'No se puede editar: El colaborador no tiene registro de contrato. Contactar con RH.';
            $params = [];
            if (isset($_POST['sucursal'])) $params['sucursal'] = $_POST['sucursal'];
            if (isset($_POST['desde']))    $params['desde']    = $_POST['desde'];
            if (isset($_POST['hasta']))    $params['hasta']    = $_POST['hasta'];
            header('Location: tardanzas_manual.php?' . http_build_query($params));
            exit();
        }

        $estado        = $_POST['estado'];
        $observaciones = $_POST['observaciones'] ?? null;

        $stmt = $conn->prepare("
            UPDATE TardanzasManuales 
            SET estado = ?, 
                observaciones = ?,
                actualizado_por = ?,
                fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $estado,
            $observaciones,
            $_SESSION['usuario_id'],
            $id
        ]);

        // Ajustar o revertir la marcación según el nuevo estado de la tardanza manual
        if ($estado === 'Justificado') {
            // Justificada → ajustar hora_ingreso a la hora programada (respalda la real)
            ajustarMarcacionPorTardanzaManual(
                $tardanzaExistente['cod_operario'],
                $tardanzaExistente['fecha_tardanza'],
                $tardanzaExistente['cod_sucursal'],
                $id
            );
        } else {
            // No Válido o Pendiente → revertir si la marcación fue ajustada previamente
            revertirAjusteMarcacionPorFecha(
                $tardanzaExistente['cod_operario'],
                $tardanzaExistente['fecha_tardanza']
            );
        }

        $_SESSION['exito'] = 'Tardanza manual actualizada correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al actualizar la tardanza manual: ' . $e->getMessage();
    }

    $params = [];
    if (!empty($_POST['sucursal'])) $params['sucursal'] = $_POST['sucursal'];
    if (!empty($_POST['desde']))    $params['desde']    = $_POST['desde'];
    if (!empty($_POST['hasta']))    $params['hasta']    = $_POST['hasta'];

    header('Location: tardanzas_manual.php?' . http_build_query($params));
    exit();
}

// Si no es POST, redirigir con parámetros si existen
$params = [];
if (!empty($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
if (!empty($_GET['desde']))    $params['desde']    = $_GET['desde'];
if (!empty($_GET['hasta']))    $params['hasta']    = $_GET['hasta'];

header('Location: tardanzas_manual.php?' . http_build_query($params));
exit();

/**
 * Ajusta la hora_ingreso de la marcación a la hora programada del horario semanal
 * cuando una tardanza MANUAL es marcada como 'Justificado'.
 *
 * Busca la marcación por (cod_operario, fecha, sucursal) ya que TardanzasManuales
 * no almacena id_marcacion directamente.
 * Solo actúa si la marcación NO fue ajustada antes (ajustado_por_tardanza = 0).
 */
function ajustarMarcacionPorTardanzaManual($codOperario, $fechaTardanza, $codSucursal, $idTardanzaManual)
{
    global $conn;

    // Buscar marcación de entrada para ese operario, fecha y sucursal
    $stmt = $conn->prepare("
        SELECT id, hora_ingreso, hora_salida, ajustado_por_tardanza
        FROM marcaciones
        WHERE CodOperario = ?
          AND fecha = ?
          AND sucursal_codigo = ?
          AND hora_ingreso IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $fechaTardanza, $codSucursal]);
    $marcacion = $stmt->fetch();

    if (!$marcacion) {
        error_log("ajustarMarcacionPorTardanzaManual: No se encontró marcación para operario $codOperario en $fechaTardanza / $codSucursal");
        return;
    }

    // Ignorar si ya fue ajustada (no sobrescribir el backup real)
    if ($marcacion['ajustado_por_tardanza'] == 1) {
        error_log("ajustarMarcacionPorTardanzaManual: Marcación ID {$marcacion['id']} ya estaba ajustada, se ignora");
        return;
    }

    // Obtener semana del sistema para esa fecha
    $semana = obtenerSemanaPorFecha($fechaTardanza);
    if (!$semana) {
        error_log("ajustarMarcacionPorTardanzaManual: No se encontró semana para fecha $fechaTardanza");
        return;
    }

    // Obtener horario programado del operario
    $horarioProgramado = obtenerHorarioOperacionesPorDia(
        $codOperario,
        $semana['id'],
        $codSucursal,
        $fechaTardanza
    );

    if (!$horarioProgramado || empty($horarioProgramado['hora_entrada'])) {
        error_log("ajustarMarcacionPorTardanzaManual: Sin horario programado para operario $codOperario en $fechaTardanza");
        return;
    }

    $horaProgramada = $horarioProgramado['hora_entrada'];

    // Respaldar horas reales y ajustar hora_ingreso a la programada
    $stmt = $conn->prepare("
        UPDATE marcaciones
        SET hora_ingreso_original = hora_ingreso,
            hora_salida_original  = hora_salida,
            hora_ingreso          = ?,
            ajustado_por_tardanza = 1,
            id_tardanza_ajuste    = ?
        WHERE id = ?
          AND ajustado_por_tardanza = 0
    ");
    $stmt->execute([$horaProgramada, $idTardanzaManual, $marcacion['id']]);

    error_log("ajustarMarcacionPorTardanzaManual: Marcación ID {$marcacion['id']} ajustada. hora_ingreso → $horaProgramada (original: {$marcacion['hora_ingreso']})");
}

/**
 * Revierte el ajuste de marcación buscando por operario y fecha.
 * Solo actúa si la marcación fue ajustada por tardanza (id_tardanza_ajuste IS NOT NULL).
 * No revierte ajustes originados en faltas (esos solo tienen id_falta_ajuste).
 */
function revertirAjusteMarcacionPorFecha($codOperario, $fechaTardanza)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT id, hora_ingreso_original, hora_salida_original
        FROM marcaciones
        WHERE CodOperario = ?
          AND fecha = ?
          AND ajustado_por_tardanza = 1
          AND id_tardanza_ajuste IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $fechaTardanza]);
    $marcacion = $stmt->fetch();

    if (!$marcacion) {
        return; // No fue ajustada por tardanza → nada que revertir
    }

    $stmt = $conn->prepare("
        UPDATE marcaciones
        SET hora_ingreso          = hora_ingreso_original,
            hora_salida           = hora_salida_original,
            hora_ingreso_original = NULL,
            hora_salida_original  = NULL,
            ajustado_por_tardanza = 0,
            id_tardanza_ajuste    = NULL
        WHERE id = ?
          AND ajustado_por_tardanza = 1
          AND id_tardanza_ajuste IS NOT NULL
    ");
    $stmt->execute([$marcacion['id']]);

    error_log("revertirAjusteMarcacionPorFecha: Marcación ID {$marcacion['id']} revertida a horas originales (ingreso: {$marcacion['hora_ingreso_original']})");
}
?>