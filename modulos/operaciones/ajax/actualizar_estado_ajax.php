<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';


header('Content-Type: application/json');

// Verificar que sea petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar permisos
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
if (!tienePermiso('tardanzas_manual', 'aprobar', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

try {
    $id = $_POST['id'] ?? null;
    $estado = $_POST['estado'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';

    if (!$id || !$estado) {
        throw new Exception('Datos incompletos');
    }

    // Validar estado
    if (!in_array($estado, ['Justificado', 'No Válido'])) {
        throw new Exception('Estado no válido');
    }

    // ─── Pre-validación: verificar horario programado antes de tocar nada ────
    // Si no hay horario confirmado en HorariosSemanalesOperaciones para ese
    // operario/fecha, NO se puede justificar ni ajustar marcaciones.
    // Protocolo: coordinar con quien programa los horarios desde
    // /modulos/supervision/programar_horarios_operaciones.php
    if ($estado === 'Justificado') {
        $checkHorario = verificarHorarioParaTardanza($id);
        if (!$checkHorario['tiene_horario']) {
            echo json_encode([
                'success' => false,
                'message' => $checkHorario['mensaje']
            ]);
            exit;
        }
    }

    // ─── 1. Actualizar estado en TardanzasManuales ───────────────────────────
    $stmt = $conn->prepare("UPDATE TardanzasManuales 
            SET estado = ?, 
                observaciones = ?,
                actualizado_por = ?,
                fecha_actualizacion = NOW()
            WHERE id = ?");

    $resultado = $stmt->execute([
        $estado,
        $observaciones,
        $_SESSION['usuario_id'],
        $id
    ]);

    if (!$resultado) {
        throw new Exception('Error al actualizar el estado');
    }

    // ─── 2. Ajuste / Reversión de marcación ─────────────────────────────────
    $mensajeAjuste = '';

    if ($estado === 'Justificado') {
        $mensajeAjuste = ajustarMarcacionPorTardanza($id);
    } elseif ($estado === 'No Válido') {
        $mensajeAjuste = revertirAjusteMarcacion($id);
    }

    // ─── 3. Registrar en log ─────────────────────────────────────────────────
    registrarLogSistema(
        'ACTUALIZAR_TARDANZA',
        "Estado de tardanza actualizado a {$estado}",
        [
            'tardanza_id'    => $id,
            'nuevo_estado'   => $estado,
            'usuario_id'     => $_SESSION['usuario_id'],
            'ajuste_marcacion' => $mensajeAjuste
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado correctamente',
        'estado'  => $estado
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// ────────────────────────────────────────────────────────────────────────────
// Pre-valida que exista un horario programado activo en HorariosSemanalesOperaciones
// para el operario y fecha de la tardanza.
// Si no existe → retorna error descriptivo; NO se toca estado ni marcaciones.
// ────────────────────────────────────────────────────────────────────────────
function verificarHorarioParaTardanza($idTardanza)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT cod_operario, fecha_tardanza, cod_sucursal
        FROM TardanzasManuales
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$idTardanza]);
    $tardanza = $stmt->fetch();

    if (!$tardanza) {
        return ['tiene_horario' => false, 'mensaje' => 'Tardanza no encontrada.'];
    }

    $semana = obtenerSemanaPorFecha($tardanza['fecha_tardanza']);
    if (!$semana) {
        return [
            'tiene_horario' => false,
            'mensaje' => "No se puede justificar: no existe una semana del sistema definida para la fecha {$tardanza['fecha_tardanza']}."
        ];
    }

    $horario = obtenerHorarioOperacionesPorDia(
        $tardanza['cod_operario'],
        $semana['id'],
        $tardanza['cod_sucursal'],
        $tardanza['fecha_tardanza']
    );

    if (!$horario || empty($horario['hora_entrada']) || $horario['estado'] !== 'Activo') {
        return [
            'tiene_horario' => false,
            'mensaje' => "No se puede justificar: el colaborador no tiene horario programado activo en HorariosSemanalesOperaciones para la fecha {$tardanza['fecha_tardanza']}. "
                       . "Coordinar con quien gestiona los horarios en Programar Horarios (Supervisión) para que confirmen el horario antes de justificar la tardanza."
        ];
    }

    return ['tiene_horario' => true, 'mensaje' => ''];
}

// ────────────────────────────────────────────────────────────────────────────
// Ajusta la hora_ingreso de la marcación más cercana a la entrada programada.
// Si no existe ningún registro en marcaciones, crea una fila de auditoría
// para dejar constancia del ajuste aunque no haya marcación real previa.
// ────────────────────────────────────────────────────────────────────────────
function ajustarMarcacionPorTardanza($idTardanza)
{
    global $conn;

    try {
        // 1. Obtener datos de la tardanza
        $stmt = $conn->prepare("
            SELECT cod_operario, fecha_tardanza, cod_sucursal
            FROM TardanzasManuales
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$idTardanza]);
        $tardanza = $stmt->fetch();

        if (!$tardanza) {
            error_log("ajustarMarcacionPorTardanza: tardanza #$idTardanza no encontrada");
            return 'Sin tardanza';
        }

        $codOperario  = $tardanza['cod_operario'];
        $fecha        = $tardanza['fecha_tardanza'];
        $codSucursal  = $tardanza['cod_sucursal'];

        // 2. Obtener semana del sistema para esa fecha
        $semana = obtenerSemanaPorFecha($fecha);
        if (!$semana) {
            error_log("ajustarMarcacionPorTardanza: no hay semana para fecha $fecha");
            return 'Sin semana';
        }

        // 3. Obtener horario programado del operario para ese día
        $horario = obtenerHorarioOperacionesPorDia($codOperario, $semana['id'], $codSucursal, $fecha);

        if (!$horario || empty($horario['hora_entrada']) || $horario['estado'] !== 'Activo') {
            error_log("ajustarMarcacionPorTardanza: sin horario activo para operario $codOperario el $fecha");
            return 'Sin horario';
        }

        $horaEntradaProgramada = $horario['hora_entrada'];

        // 4. Buscar la marcación más cercana a la hora programada.
        //    Filtramos por hora_ingreso IS NOT NULL para que TIMESTAMPDIFF
        //    no retorne NULL y rompa el ORDER BY.
        $stmt = $conn->prepare("
            SELECT id, hora_ingreso, hora_salida, ajustado_por_tardanza
            FROM marcaciones
            WHERE CodOperario = ?
            AND fecha = ?
            AND hora_ingreso IS NOT NULL
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, hora_ingreso, ?))
            LIMIT 1
        ");
        $stmt->execute([$codOperario, $fecha, $horaEntradaProgramada]);
        $marcacion = $stmt->fetch();

        if (!$marcacion) {
            // 4b. No hay ninguna fila con hora_ingreso. Verificar si al menos
            //     existe alguna fila en marcaciones (sin hora) para esa fecha.
            $stmtAny = $conn->prepare("
                SELECT id, hora_ingreso, hora_salida, ajustado_por_tardanza
                FROM marcaciones
                WHERE CodOperario = ?
                AND fecha = ?
                LIMIT 1
            ");
            $stmtAny->execute([$codOperario, $fecha]);
            $marcacionSinHora = $stmtAny->fetch();

            if ($marcacionSinHora) {
                // Existe fila pero sin hora_ingreso. Si ya fue ajustada, no pisar.
                if ($marcacionSinHora['ajustado_por_tardanza'] == 1) {
                    error_log("ajustarMarcacionPorTardanza: marcación #{$marcacionSinHora['id']} ya ajustada (sin hora previa)");
                    return 'Ya ajustada';
                }
                // Respaldar (hora_ingreso_original quedará NULL, eso es correcto)
                // y establecer la hora programada como la nueva hora_ingreso.
                $stmtUpd = $conn->prepare("
                    UPDATE marcaciones
                    SET hora_ingreso_original  = hora_ingreso,
                        hora_salida_original   = hora_salida,
                        hora_ingreso           = ?,
                        ajustado_por_tardanza  = 1,
                        id_tardanza_ajuste     = ?
                    WHERE id = ?
                ");
                $stmtUpd->execute([$horaEntradaProgramada, $idTardanza, $marcacionSinHora['id']]);
                error_log("ajustarMarcacionPorTardanza: marcación #{$marcacionSinHora['id']} (sin hora previa) ajustada a $horaEntradaProgramada");
                return "Ajustada (sin hora previa) → $horaEntradaProgramada";
            }

            // No existe ninguna fila en marcaciones para este operario/fecha.
            // Insertamos una fila de auditoría para dejar constancia del ajuste.
            $stmtIns = $conn->prepare("
                INSERT INTO marcaciones
                    (CodOperario, fecha, sucursal_codigo,
                     hora_ingreso, hora_salida,
                     hora_ingreso_original, hora_salida_original,
                     ajustado_por_tardanza, id_tardanza_ajuste)
                VALUES (?, ?, ?, ?, NULL, NULL, NULL, 1, ?)
            ");
            $stmtIns->execute([$codOperario, $fecha, $codSucursal, $horaEntradaProgramada, $idTardanza]);
            $nuevaId = $conn->lastInsertId();
            error_log("ajustarMarcacionPorTardanza: sin marcación previa — insertada fila de auditoría #$nuevaId para operario $codOperario el $fecha");
            return "Sin marcación previa — insertada fila auditoría #$nuevaId → $horaEntradaProgramada";
        }

        // 5. Si ya fue ajustada por otra tardanza, no pisar
        if ($marcacion['ajustado_por_tardanza'] == 1) {
            error_log("ajustarMarcacionPorTardanza: marcación #{$marcacion['id']} ya ajustada");
            return 'Ya ajustada';
        }

        // 6. Respaldar hora real y establecer la hora programada
        $stmt = $conn->prepare("
            UPDATE marcaciones
            SET hora_ingreso_original  = hora_ingreso,
                hora_salida_original   = hora_salida,
                hora_ingreso           = ?,
                ajustado_por_tardanza  = 1,
                id_tardanza_ajuste     = ?
            WHERE id = ?
        ");
        $stmt->execute([$horaEntradaProgramada, $idTardanza, $marcacion['id']]);

        error_log("ajustarMarcacionPorTardanza: marcación #{$marcacion['id']} ajustada {$marcacion['hora_ingreso']} → $horaEntradaProgramada");
        return "Ajustada: {$marcacion['hora_ingreso']} → $horaEntradaProgramada";

    } catch (Exception $e) {
        error_log("ajustarMarcacionPorTardanza: " . $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Revierte el ajuste de la marcación asociada a esta tardanza.
// Si la fila fue insertada artificialmente (sin marcación real previa),
// se elimina en lugar de dejar un registro huérfano con todos NULLs.
// ────────────────────────────────────────────────────────────────────────────
function revertirAjusteMarcacion($idTardanza)
{
    global $conn;

    try {
        // 1. Buscar la marcación ajustada por esta tardanza
        $stmt = $conn->prepare("
            SELECT id, hora_ingreso_original, hora_salida_original
            FROM marcaciones
            WHERE id_tardanza_ajuste = ?
            AND ajustado_por_tardanza = 1
            LIMIT 1
        ");
        $stmt->execute([$idTardanza]);
        $marcacion = $stmt->fetch();

        if (!$marcacion) {
            error_log("revertirAjusteMarcacion: no hay marcación ajustada por tardanza #$idTardanza");
            return 'Sin ajuste previo';
        }

        // 2. Restaurar hora_ingreso a su valor original (puede ser NULL si no había
        //    marcación de entrada antes del ajuste) y limpiar columnas de auditoría.
        //
        //    Nota: siempre hacemos UPDATE, nunca DELETE. Si la fila fue insertada
        //    artificialmente quedará con hora_ingreso = NULL y ajustado_por_tardanza = 0,
        //    estado que es invisible para todos los cálculos de tardanza (filtran IS NOT NULL).
        //    Si era una fila real con hora_ingreso = NULL, se restaura correctamente.
        $stmt = $conn->prepare("
            UPDATE marcaciones
            SET hora_ingreso           = hora_ingreso_original,
                hora_ingreso_original  = NULL,
                hora_salida_original   = NULL,
                ajustado_por_tardanza  = 0,
                id_tardanza_ajuste     = NULL
            WHERE id = ?
        ");
        $stmt->execute([$marcacion['id']]);

        $horaRestaurada = $marcacion['hora_ingreso_original'] ?? 'NULL';
        error_log("revertirAjusteMarcacion: marcación #{$marcacion['id']} revertida a $horaRestaurada");
        return "Revertida a: $horaRestaurada";

    } catch (Exception $e) {
        error_log("revertirAjusteMarcacion: " . $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

function registrarLogSistema($tipo, $mensaje, $datos = [])
{
    global $conn;

    try {
        $sql = "INSERT INTO logs_sistema (tipo, mensaje, datos, fecha) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$tipo, $mensaje, json_encode($datos)]);
    } catch (Exception $e) {
        error_log("Error al registrar log: " . $e->getMessage());
    }
}
