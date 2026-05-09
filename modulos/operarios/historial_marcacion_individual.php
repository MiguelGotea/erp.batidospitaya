<?php
ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Limpiar cualquier output previo
if (ob_get_length()) ob_clean();
ob_start();

require_once '../../includes/auth.php';
// Después de los requires, se agrega para corregir el redireccionamiento de imágenes
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/';
$assets_url = '/assets/';

//******************************Estándar para header******************************
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo (solo cargos 2 y 5)
//verificarAccesoCargo([2,5,43]);

// Verificar acceso al módulo
//if (!$esAdmin && !verificarAccesoCargo([2,5,43,44,45,46,47])) {
//    header('Location: /index.php');
//    exit();
//}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
$codOperario = $_SESSION['usuario_id'];
//******************************Estándar para header, termina******************************

// Obtener parámetros de fecha (últimos 20 días por defecto)
$fechaHoy = date('Y-m-d');
$fechaDesde = date('Y-m-d', strtotime('-20 days'));
$fechaHasta = $fechaHoy;

// Asegurar que la fecha hasta no sea mayor a hoy
if ($fechaHasta > $fechaHoy) {
    $fechaHasta = $fechaHoy;
}

// Validar fechas
if (strtotime($fechaDesde) > strtotime($fechaHasta)) {
    $_SESSION['error'] = "La fecha 'Desde' no puede ser mayor a la fecha 'Hasta'";
    header('Location: historial_marcacion_individual.php');
    exit();
}

// Obtener el historial de marcaciones del usuario actual
$historial = obtenerHistorialMarcacionesIndividual($codOperario, $fechaDesde, $fechaHasta);

// Obtener las deducciones del usuario actual (últimos 20 días para operarios/líderes)
$deducciones = obtenerDeduccionesUsuario($codOperario, $fechaDesde, $fechaHasta);

/**
 * Verifica si una tardanza está justificada en TardanzasManuales
 */
function tardanzaEstaJustificada($codOperario, $codSucursal, $fecha) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM TardanzasManuales 
        WHERE cod_operario = ? 
        AND cod_sucursal = ? 
        AND fecha_tardanza = ? 
        AND estado = 'Justificado'
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $codSucursal, $fecha]);
    $result = $stmt->fetch();
    
    return ($result && $result['total'] > 0);
}

/**
 * Verifica si una falta está justificada en faltas_manual
 */
function faltaEstaJustificada($codOperario, $codSucursal, $fecha) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM faltas_manual 
        WHERE cod_operario = ? 
        AND cod_sucursal = ? 
        AND fecha_falta = ? 
        AND tipo_falta NOT IN ('Pendiente', 'No_Pagado', 'Dia_mas_septimo')
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $codSucursal, $fecha]);
    $result = $stmt->fetch();
    
    return ($result && $result['total'] > 0);
}

/**
 * Obtiene el historial completo de marcaciones para un operario específico
 */
function obtenerHistorialMarcacionesIndividual($codOperario, $fechaDesde, $fechaHasta) {
    global $conn;
    
    // LIMITAR FECHA HASTA AL DÍA ACTUAL SI ES FUTURO
    $fechaHoy = date('Y-m-d');
    if ($fechaHasta > $fechaHoy) {
        $fechaHasta = $fechaHoy;
    }
    
    // PRIMERO: Obtener todos los horarios programados en el rango de fechas
    $sqlHorarios = "
        SELECT 
            hso.cod_operario,
            hso.cod_sucursal,
            ss.fecha_inicio,
            ss.fecha_fin,
            ss.numero_semana,
            -- Campos para cada día de la semana
            hso.lunes_estado, hso.lunes_entrada, hso.lunes_salida,
            hso.martes_estado, hso.martes_entrada, hso.martes_salida,
            hso.miercoles_estado, hso.miercoles_entrada, hso.miercoles_salida,
            hso.jueves_estado, hso.jueves_entrada, hso.jueves_salida,
            hso.viernes_estado, hso.viernes_entrada, hso.viernes_salida,
            hso.sabado_estado, hso.sabado_entrada, hso.sabado_salida,
            hso.domingo_estado, hso.domingo_entrada, hso.domingo_salida,
            s.nombre as nombre_sucursal,
            s.codigo as codigo_sucursal
        FROM HorariosSemanalesOperaciones hso
        JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
        JOIN sucursales s ON hso.cod_sucursal = s.codigo
        WHERE hso.cod_operario = ?
        AND ss.fecha_inicio <= ? AND ss.fecha_fin >= ?
    ";
    
    $paramsHorarios = [$codOperario, $fechaHasta, $fechaDesde];
    
    $stmtHorarios = $conn->prepare($sqlHorarios);
    $stmtHorarios->execute($paramsHorarios);
    $horariosProgramados = $stmtHorarios->fetchAll();
    
    // SEGUNDO: Obtener todas las marcaciones en el rango de fechas
    $sqlMarcaciones = "
        SELECT 
            m.id,
            m.fecha,
            m.hora_ingreso,
            m.hora_salida,
            m.CodOperario,
            m.sucursal_codigo,
            s.nombre as nombre_sucursal,
            s.codigo as codigo_sucursal,
            (SELECT ss.numero_semana 
             FROM SemanasSistema ss 
             WHERE m.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin 
             LIMIT 1) as numero_semana
        FROM marcaciones m
        JOIN sucursales s ON m.sucursal_codigo = s.codigo
        WHERE m.CodOperario = ?
        AND m.fecha BETWEEN ? AND ?
        ORDER BY m.fecha DESC, m.hora_ingreso DESC
    ";
    
    $paramsMarcaciones = [$codOperario, $fechaDesde, $fechaHasta];
    
    $stmtMarcaciones = $conn->prepare($sqlMarcaciones);
    $stmtMarcaciones->execute($paramsMarcaciones);
    $marcaciones = $stmtMarcaciones->fetchAll();
    
    // TERCERO: Combinar horarios programados y marcaciones
    $resultado = [];
    
    // Procesar horarios programados (generar un registro por cada día con horario)
    foreach ($horariosProgramados as $horario) {
        $fechaInicio = new DateTime($horario['fecha_inicio']);
        $fechaFin = new DateTime($horario['fecha_fin']);
        
        // Generar registros para cada día de la semana del horario
        for ($fecha = clone $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 day')) {
            $fechaStr = $fecha->format('Y-m-d');
            
            // Solo incluir fechas dentro del rango solicitado Y NO FUTURAS
            if ($fechaStr < $fechaDesde || $fechaStr > $fechaHasta || $fechaStr > $fechaHoy) {
                continue;
            }
            
            $diaSemana = $fecha->format('N'); // 1=lunes, 7=domingo
            
            // Obtener datos del día específico según el día de la semana
            $estadoDia = '';
            $horaEntradaProgramada = '';
            $horaSalidaProgramada = '';
            
            switch ($diaSemana) {
                case 1: // lunes
                    $estadoDia = $horario['lunes_estado'];
                    $horaEntradaProgramada = $horario['lunes_entrada'];
                    $horaSalidaProgramada = $horario['lunes_salida'];
                    break;
                case 2: // martes
                    $estadoDia = $horario['martes_estado'];
                    $horaEntradaProgramada = $horario['martes_entrada'];
                    $horaSalidaProgramada = $horario['martes_salida'];
                    break;
                case 3: // miércoles
                    $estadoDia = $horario['miercoles_estado'];
                    $horaEntradaProgramada = $horario['miercoles_entrada'];
                    $horaSalidaProgramada = $horario['miercoles_salida'];
                    break;
                case 4: // jueves
                    $estadoDia = $horario['jueves_estado'];
                    $horaEntradaProgramada = $horario['jueves_entrada'];
                    $horaSalidaProgramada = $horario['jueves_salida'];
                    break;
                case 5: // viernes
                    $estadoDia = $horario['viernes_estado'];
                    $horaEntradaProgramada = $horario['viernes_entrada'];
                    $horaSalidaProgramada = $horario['viernes_salida'];
                    break;
                case 6: // sábado
                    $estadoDia = $horario['sabado_estado'];
                    $horaEntradaProgramada = $horario['sabado_entrada'];
                    $horaSalidaProgramada = $horario['sabado_salida'];
                    break;
                case 7: // domingo
                    $estadoDia = $horario['domingo_estado'];
                    $horaEntradaProgramada = $horario['domingo_entrada'];
                    $horaSalidaProgramada = $horario['domingo_salida'];
                    break;
            }
            
            // Solo incluir días que tienen horario programado y estado Activo
            if (!empty($estadoDia)) {
                $key = $fechaStr . '_' . $horario['codigo_sucursal'];
                
                // Buscar si existe marcación para este día y sucursal
                $marcacionExistente = null;
                foreach ($marcaciones as $marcacion) {
                    if ($marcacion['fecha'] == $fechaStr && $marcacion['codigo_sucursal'] == $horario['codigo_sucursal']) {
                        $marcacionExistente = $marcacion;
                        break;
                    }
                }
                
                // Verificar tardanza (pero verificar si está justificada)
                $tieneTardanza = false;
                $tardanzaJustificada = false;
                if ($marcacionExistente && $marcacionExistente['hora_ingreso'] && $horaEntradaProgramada) {
                    $tieneTardanza = verificarTardanzaConTolerancia($marcacionExistente['hora_ingreso'], $horaEntradaProgramada);
                    
                    // Si hay tardanza, verificar si está justificada
                    if ($tieneTardanza) {
                        $tardanzaJustificada = tardanzaEstaJustificada($codOperario, $horario['codigo_sucursal'], $fechaStr);
                        // Si está justificada, no contar como tardanza
                        if ($tardanzaJustificada) {
                            $tieneTardanza = false;
                        }
                    }
                }
                
                // Verificar falta (pero verificar si está justificada)
                $tieneFalta = false;
                $faltaJustificada = false;
                if ($estadoDia === 'Activo' && (!$marcacionExistente || (!$marcacionExistente['hora_ingreso'] && !$marcacionExistente['hora_salida']))) {
                    $tieneFalta = true;
                    
                    // Si hay falta, verificar si está justificada
                    if ($tieneFalta) {
                        $faltaJustificada = faltaEstaJustificada($codOperario, $horario['codigo_sucursal'], $fechaStr);
                        // Si está justificada, no contar como falta
                        if ($faltaJustificada) {
                            $tieneFalta = false;
                        }
                    }
                }
                
                // Verificar viático
                $tieneViatico = verificarViatico($marcacionExistente, $horario['codigo_sucursal']);
                
                // Crear registro combinado
                $registro = [
                    'fecha' => $fechaStr,
                    'sucursal_codigo' => $horario['codigo_sucursal'],
                    'nombre_sucursal' => $horario['nombre_sucursal'],
                    'hora_entrada_programada' => $horaEntradaProgramada,
                    'hora_salida_programada' => $horaSalidaProgramada,
                    'hora_ingreso' => $marcacionExistente ? $marcacionExistente['hora_ingreso'] : null,
                    'hora_salida' => $marcacionExistente ? $marcacionExistente['hora_salida'] : null,
                    'tiene_horario' => true,
                    'tiene_marcacion' => !is_null($marcacionExistente),
                    'tiene_tardanza' => $tieneTardanza,
                    'tiene_falta' => $tieneFalta,
                    'tiene_viatico' => $tieneViatico,
                    'tipo_registro' => 'programado',
                    // NUEVOS CAMPOS PARA DEBUG
                    'tardanza_justificada' => $tardanzaJustificada,
                    'falta_justificada' => $faltaJustificada
                ];
                
                $resultado[$key] = $registro;
            }
        }
    }
    
    // CUARTO: Agregar marcaciones que no tengan horario programado (casos especiales)
    foreach ($marcaciones as $marcacion) {
        $key = $marcacion['fecha'] . '_' . $marcacion['codigo_sucursal'];
        
        if (!isset($resultado[$key])) {
            // Esta marcación no tiene horario programado correspondiente
            // Verificar viático
            $tieneViatico = verificarViatico($marcacion, $marcacion['codigo_sucursal']);
            
            $registro = [
                'fecha' => $marcacion['fecha'],
                'sucursal_codigo' => $marcacion['codigo_sucursal'],
                'nombre_sucursal' => $marcacion['nombre_sucursal'],
                'hora_entrada_programada' => null,
                'hora_salida_programada' => null,
                'hora_ingreso' => $marcacion['hora_ingreso'],
                'hora_salida' => $marcacion['hora_salida'],
                'tiene_horario' => false,
                'tiene_marcacion' => true,
                'tiene_tardanza' => false, // No hay horario programado para comparar
                'tiene_falta' => false,    // No había horario programado
                'tiene_viatico' => $tieneViatico,
                'tipo_registro' => 'sin_programar'
            ];
            
            $resultado[$key] = $registro;
        }
    }
    
    // Convertir el array asociativo a numérico y ordenar por fecha
    $resultado = array_values($resultado);
    usort($resultado, function($a, $b) {
        return strtotime($b['fecha']) - strtotime($a['fecha']);
    });
    
    return $resultado;
}

/**
 * Obtiene el estado programado para un día específico
 */
function obtenerEstadoDia($fecha, $codSucursal, $codOperario) {
    global $conn;
    
    // Obtener el día de la semana (1=lunes, 7=domingo)
    $diaSemana = date('N', strtotime($fecha));
    
    // Mapear a nombres de columna
    $dias = [
        1 => 'lunes',
        2 => 'martes', 
        3 => 'miercoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sabado',
        7 => 'domingo'
    ];
    $diaColumna = $dias[$diaSemana];
    
    // Buscar en horarios programados
    $sql = "
        SELECT {$diaColumna}_estado as estado
        FROM HorariosSemanalesOperaciones hso
        JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
        WHERE hso.cod_operario = ?
        AND hso.cod_sucursal = ?
        AND ss.fecha_inicio <= ? 
        AND ss.fecha_fin >= ?
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario, $codSucursal, $fecha, $fecha]);
    $result = $stmt->fetch();
    
    return $result['estado'] ?? 'No programado';
}

/**
 * Obtiene las deducciones del usuario actual
 */
function obtenerDeduccionesUsuario($codOperario, $fechaDesde, $fechaHasta) {
    global $conn;
    
    try {
        // Obtener el último CodContrato del operario
        $ultimo_cod_contrato = obtenerUltimoCodigoContrato($codOperario);
        
        if ($ultimo_cod_contrato) {
            $operario_id = $ultimo_cod_contrato; // Usar CodContrato en lugar de CodOperario
        } else {
            $operario_id = $codOperario; // Fallback al CodOperario
        }
        
        // Consulta para obtener todas las deducciones del usuario CON FECHA LOCAL
        $sql = "
            (SELECT 
                'facturacion' AS tipo,
                af.id,
                -- CONVERTIR FECHA A HORA LOCAL ANTES DE FILTRAR
                DATE_SUB(af.fecha_hora_regsys, INTERVAL 6 HOUR) AS fecha_evento_local,
                af.fecha_hora_regsys AS fecha_evento_utc,
                af.fecha_deduccion,
                af.sucursal_id,
                s.nombre AS sucursal_nombre,
                af.cod_contrato AS operario_id,
                CONCAT(
                    IFNULL(o.Nombre, ''), ' ', 
                    IFNULL(o.Nombre2, ''), ' ', 
                    IFNULL(o.Apellido, ''), ' ', 
                    IFNULL(o.Apellido2, '')
                ) AS operario_nombre,
                af.comentarios,
                af.faltante_sobrante AS monto_original,
                CASE WHEN af.faltante_sobrante < 0 THEN ABS(af.faltante_sobrante) ELSE 0 END AS monto,
                'ver_auditorias_facturacion.php' AS url_ver,
                af.cod_contrato AS cod_contrato,
                af.fecha_hora_regsys AS fecha_registro,
                -- Estado (usando fecha local)
                CASE 
                    WHEN DAY(DATE_SUB(af.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 5 AND 12 THEN 'Planilla 1er Quincena'
                    WHEN DAY(DATE_SUB(af.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 13 AND 26 THEN 'Planilla 2da Quincena'
                    ELSE 'Propina'
                END AS estado_deduccion,
                IFNULL(af.cobrado, 0) AS cobrado
            FROM auditoria_facturacion af
            JOIN Operarios o ON af.cajero = o.CodOperario
            JOIN sucursales s ON af.sucursal_id = s.codigo
            WHERE (af.cod_contrato = ? OR af.cajero = ?))
            
            UNION ALL
            
            (SELECT 
                'caja_chica' AS tipo,
                acc.id,
                -- CONVERTIR FECHA A HORA LOCAL ANTES DE FILTRAR
                DATE_SUB(acc.fecha_hora_regsys, INTERVAL 6 HOUR) AS fecha_evento_local,
                acc.fecha_hora_regsys AS fecha_evento_utc,
                acc.fecha_deduccion,
                acc.sucursal_id,
                s.nombre AS sucursal_nombre,
                acc.cod_contrato AS operario_id,
                CONCAT(
                    IFNULL(o.Nombre, ''), ' ', 
                    IFNULL(o.Nombre2, ''), ' ', 
                    IFNULL(o.Apellido, ''), ' ', 
                    IFNULL(o.Apellido2, '')
                ) AS operario_nombre,
                acc.comentarios,
                acc.faltante_sobrante AS monto_original,
                CASE WHEN acc.faltante_sobrante < 0 THEN ABS(acc.faltante_sobrante) ELSE 0 END AS monto,
                'ver_auditorias_caja_chica.php' AS url_ver,
                acc.cod_contrato AS cod_contrato,
                acc.fecha_hora_regsys AS fecha_registro,
                -- Estado (usando fecha local)
                CASE 
                    WHEN DAY(DATE_SUB(acc.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 5 AND 12 THEN 'Planilla 1er Quincena'
                    WHEN DAY(DATE_SUB(acc.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 13 AND 26 THEN 'Planilla 2da Quincena'
                    ELSE 'Propina'
                END AS estado_deduccion,
                IFNULL(acc.cobrado, 0) AS cobrado
            FROM auditoria_caja_chica acc
            JOIN Operarios o ON acc.lider_tienda_codigo = o.CodOperario
            JOIN sucursales s ON acc.sucursal_id = s.codigo
            WHERE (acc.cod_contrato = ? OR acc.lider_tienda_codigo = ?))
            
            UNION ALL
            
            (SELECT 
                'inventario' AS tipo,
                ai.id,
                -- CONVERTIR FECHA A HORA LOCAL ANTES DE FILTRAR
                DATE_SUB(ai.fecha_hora_regsys, INTERVAL 6 HOUR) AS fecha_evento_local,
                ai.fecha_hora_regsys AS fecha_evento_utc,
                aio.fecha_deduccion,
                ai.sucursal_id,
                s.nombre AS sucursal_nombre,
                aio.cod_contrato AS operario_id,
                CONCAT(
                    IFNULL(o.Nombre, ''), ' ', 
                    IFNULL(o.Nombre2, ''), ' ', 
                    IFNULL(o.Apellido, ''), ' ', 
                    IFNULL(o.Apellido2, '')
                ) AS operario_nombre,
                ai.comentarios,
                aio.monto AS monto_original,
                aio.monto AS monto,
                'ver_auditorias_inventario.php' AS url_ver,
                aio.cod_contrato AS cod_contrato,
                ai.fecha_hora_regsys AS fecha_registro,
                -- Estado (usando fecha local)
                CASE 
                    WHEN DAY(DATE_SUB(ai.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 5 AND 12 THEN 'Planilla 1er Quincena'
                    WHEN DAY(DATE_SUB(ai.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 13 AND 26 THEN 'Planilla 2da Quincena'
                    ELSE 'Propina'
                END AS estado_deduccion,
                IFNULL(aio.cobrado, 0) AS cobrado
            FROM auditoria_inventario ai
            JOIN auditoria_inventario_operarios aio ON ai.id = aio.auditoria_id
            JOIN Operarios o ON aio.operario_id = o.CodOperario
            JOIN sucursales s ON ai.sucursal_id = s.codigo
            WHERE (aio.cod_contrato = ? OR aio.operario_id = ?))
            
            UNION ALL
            
            (SELECT 
                'faltante_inventario' AS tipo,
                fi.id,
                -- CONVERTIR FECHA A HORA LOCAL ANTES DE FILTRAR
                DATE_SUB(fi.fecha_hora_regsys, INTERVAL 6 HOUR) AS fecha_evento_local,
                fi.fecha_hora_regsys AS fecha_evento_utc,
                fio.fecha_deduccion,
                fi.sucursal_id,
                s.nombre AS sucursal_nombre,
                fio.cod_contrato AS operario_id,
                CONCAT(
                    IFNULL(o.Nombre, ''), ' ', 
                    IFNULL(o.Nombre2, ''), ' ', 
                    IFNULL(o.Apellido, ''), ' ', 
                    IFNULL(o.Apellido2, '')
                ) AS operario_nombre,
                fi.comentarios,
                fio.monto AS monto_original,
                fio.monto AS monto,
                'ver_faltante_inventario.php' AS url_ver,
                fio.cod_contrato AS cod_contrato,
                fi.fecha_hora_regsys AS fecha_registro,
                -- Estado (usando fecha local)
                CASE 
                    WHEN DAY(DATE_SUB(fi.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 5 AND 12 THEN 'Planilla 1er Quincena'
                    WHEN DAY(DATE_SUB(fi.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 13 AND 26 THEN 'Planilla 2da Quincena'
                    ELSE 'Propina'
                END AS estado_deduccion,
                IFNULL(fio.cobrado, 0) AS cobrado
            FROM faltante_inventario fi
            JOIN faltante_inventario_operarios fio ON fi.id = fio.faltante_id
            JOIN Operarios o ON fio.operario_id = o.CodOperario
            JOIN sucursales s ON fi.sucursal_id = s.codigo
            WHERE (fio.cod_contrato = ? OR fio.operario_id = ?))
            
            UNION ALL
            
            (SELECT 
                'faltante_danos' AS tipo,
                fd.id,
                -- CONVERTIR FECHA A HORA LOCAL ANTES DE FILTRAR
                DATE_SUB(fd.fecha_hora_regsys, INTERVAL 6 HOUR) AS fecha_evento_local,
                fd.fecha_hora_regsys AS fecha_evento_utc,
                fdo.fecha_deduccion,
                fd.sucursal_codigo AS sucursal_id,
                s.nombre AS sucursal_nombre,
                fdo.cod_contrato AS operario_id,
                CONCAT(
                    IFNULL(o.Nombre, ''), ' ', 
                    IFNULL(o.Nombre2, ''), ' ', 
                    IFNULL(o.Apellido, ''), ' ', 
                    IFNULL(o.Apellido2, '')
                ) AS operario_nombre,
                fd.comentarios,
                fdo.monto AS monto_original,
                fdo.monto AS monto,
                'ver_faltante_danos.php' AS url_ver,
                fdo.cod_contrato AS cod_contrato,
                fd.fecha_hora_regsys AS fecha_registro,
                -- Estado (usando fecha local)
                CASE 
                    WHEN DAY(DATE_SUB(fd.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 5 AND 12 THEN 'Planilla 1er Quincena'
                    WHEN DAY(DATE_SUB(fd.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 13 AND 26 THEN 'Planilla 2da Quincena'
                    ELSE 'Propina'
                END AS estado_deduccion,
                IFNULL(fdo.cobrado, 0) AS cobrado
            FROM faltante_danos fd
            JOIN faltante_danos_operarios fdo ON fd.id = fdo.faltante_id
            JOIN Operarios o ON fdo.operario_id = o.CodOperario
            JOIN sucursales s ON fd.sucursal_codigo = s.codigo
            WHERE (fdo.cod_contrato = ? OR fdo.operario_id = ?))
            
            UNION ALL
            
            (SELECT 
                'faltante_caja' AS tipo,
                fc.id,
                -- PARA FALTANTE_CAJA, USAR FECHA DIRECTAMENTE SIN CONVERSIÓN
                fc.fecha AS fecha_evento_local,
                fc.fecha AS fecha_evento_utc, -- Mismo valor ya que no necesita conversión
                fc.fecha_deduccion,
                fc.sucursal_id,
                s.nombre AS sucursal_nombre,
                fc.cod_contrato AS operario_id,
                fc.operario_nombre AS operario_nombre,
                fc.comentarios,
                fc.monto AS monto_original,
                fc.monto AS monto,
                'ver_faltante_caja.php' AS url_ver,
                fc.cod_contrato AS cod_contrato,
                fc.fecha_hora_regsys AS fecha_registro,
                -- Estado (para faltante_caja usa fecha directamente)
                CASE 
                    WHEN DAY(fc.fecha) BETWEEN 5 AND 12 THEN 'Planilla 1er Quincena'
                    WHEN DAY(fc.fecha) BETWEEN 13 AND 26 THEN 'Planilla 2da Quincena'
                    ELSE 'Propina'
                END AS estado_deduccion,
                IFNULL(fc.cobrado, 0) AS cobrado
            FROM faltante_caja fc
            JOIN sucursales s ON fc.sucursal_id = s.codigo
            WHERE (fc.cod_contrato = ? OR fc.operario_id = ?))
        ";
        
        $params = [
            $operario_id, $codOperario, 
            $operario_id, $codOperario, 
            $operario_id, $codOperario, 
            $operario_id, $codOperario, 
            $operario_id, $codOperario, 
            $operario_id, $codOperario
        ];
        
        // Aplicar filtros de fecha SOBRE LA FECHA LOCAL
        $where = [];
        
        if (!empty($fechaDesde)) {
            $where[] = "DATE(fecha_evento_local) >= ?";  // FILTRAR POR FECHA LOCAL
            $params[] = $fechaDesde;
        }
        
        if (!empty($fechaHasta)) {
            $where[] = "DATE(fecha_evento_local) <= ?";  // FILTRAR POR FECHA LOCAL
            $params[] = $fechaHasta;
        }
        
        // Crear una consulta derivada para aplicar los filtros
        if (!empty($where)) {
            $sql = "SELECT * FROM ($sql) AS subquery WHERE " . implode(" AND ", $where);
        }
        
        // Ordenar por fecha de evento local descendente
        $sql .= " ORDER BY fecha_evento_local DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $registros;
        
    } catch (PDOException $e) {
        error_log("Error obteniendo deducciones: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica si aplica viático para una marcación
 */
function verificarViatico($marcacion, $codSucursal) {
    if (!$marcacion || (!$marcacion['hora_ingreso'] && !$marcacion['hora_salida'])) {
        return false;
    }
    
    $codSucursal = (string)$codSucursal;
    
    // Viático para sucursales específicas después de las 8:00 PM
    $sucursalesViaticoNocturno = ['7', '9', '10', '11', '12', '13', '16', '19'];
    
    // Verificar viático nocturno (después de 8:00 PM)
    if (in_array($codSucursal, $sucursalesViaticoNocturno)) {
        if ($marcacion['hora_salida'] && esHoraNocturna($marcacion['hora_salida'])) {
            return true;
        }
    }
    
    // Viático especial para sucursal 19 entre 5:00 AM - 5:40 AM
    if ($codSucursal === '19') {
        if ($marcacion['hora_ingreso'] && esHoraMadrugada($marcacion['hora_ingreso'])) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verifica si una hora está en el rango nocturno (8:00 PM - 11:59 PM)
 */
function esHoraNocturna($hora) {
    if (empty($hora)) return false;
    
    $horaObj = DateTime::createFromFormat('H:i:s', $hora);
    if (!$horaObj) return false;
    
    $horaNum = (int)$horaObj->format('H');
    return $horaNum >= 20 && $horaNum < 24;
}

/**
 * Verifica si una hora está en el rango de madrugada (5:00 AM - 5:40 AM) para viático especial
 */
function esHoraMadrugada($hora) {
    if (empty($hora)) return false;
    
    $horaObj = DateTime::createFromFormat('H:i:s', $hora);
    if (!$horaObj) return false;
    
    $horaNum = (int)$horaObj->format('H');
    $minutoNum = (int)$horaObj->format('i');
    
    // Entre 5:00 AM y 5:40 AM
    return $horaNum == 5 && $minutoNum <= 40;
}

// Obtener nombre completo del usuario para mostrar
$nombreUsuario = obtenerNombreUsuario();

// Calcular total de deducciones
$total_deducciones = 0;
foreach ($deducciones as $deduccion) {
    if (isset($deduccion['monto']) && $deduccion['monto'] !== null) {
        $total_deducciones += abs(floatval($deduccion['monto']));
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indicadores de Asistencia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="<?= $assets_url ?>img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
            padding: 5px;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 5px;
            box-sizing: border-box;
            margin: 1px auto;
            flex-wrap: wrap;
        }

        .logo {
            height: 50px;
        }

        .logo-container {
            flex-shrink: 0;
            margin-right: auto;
        }

        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            flex-grow: 1;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .btn-agregar {
            background-color: transparent;
            color: #51B8AC;
            border: 1px solid #51B8AC;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 14px;
            flex-shrink: 0;
        }

        .btn-agregar.activo {
            background-color: #51B8AC;
            color: white;
            font-weight: normal;
        }

        .btn-agregar:hover {
            background-color: #0E544C;
            color: white;
            border-color: #0E544C;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #51B8AC;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .btn-logout {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #0E544C;
        }
        
        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #51B8AC;
            font-size: 1.2rem !important;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .section-title {
            color: #0E544C;
            font-size: 1.3rem !important;
            margin: 30px 0 15px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #51B8AC;
        }
        
        .user-welcome {
            text-align: center;
            background-color: #e7f3ff;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #51B8AC;
        }
        
        .filters {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }
        
        label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }
        
        select, input, button {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn {
            padding: 8px 15px;
            background-color: #51B8AC;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            align-self: flex-end;
        }
        
        .btn:hover {
            background-color: #0E544C;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 8px;
            text-align: center;
            border: 1px solid #ddd;
        }

        th {
            background-color: #0E544C;
            color: white;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .text-center {
            text-align: center;
        }
        
        .no-results {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .icon-si {
            color: #28a745;
            font-size: 1.2rem;
        }
        
        .icon-no {
            color: #dc3545;
            font-size: 1.2rem;
        }
        
        .icon-na {
            color: #6c757d;
            font-size: 1.2rem;
        }
        
        /* Estilos para los badges de tipo de auditoría */
        .badge-tipo {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        
        .badge-facturacion {
            background-color: #3498db;
            border: 1px solid #2980b9;
        }
        
        .badge-caja_chica {
            background-color: #9b59b6;
            border: 1px solid #8e44ad;
        }
        
        .badge-inventario {
            background-color: #2ecc71;
            border: 1px solid #27ae60;
        }
        
        .badge-faltante_inventario {
            background-color: #e67e22;
            border: 1px solid #d35400;
        }
        
        .badge-faltante_danos {
            background-color: #e74c3c;
            border: 1px solid #c0392b;
        }
        
        .badge-faltante_caja {
            background-color: #f39c12;
            border: 1px solid #e67e22;
        }
        
        .monto-faltante {
            font-weight: bold;
        }
        
        .monto-positivo {
            color: #27ae60;
        }
        
        .monto-negativo {
            color: #e74c3c;
        }
        
        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .header-container {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }
            
            .buttons-container {
                position: static;
                transform: none;
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
            
            .logo-container {
                order: 1;
                margin-right: 0;
            }
            
            .user-info {
                order: 2;
                margin-left: auto;
            }
            
            .btn-agregar {
                padding: 6px 10px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-agregar {
                flex-grow: 1;
                justify-content: center;
                white-space: normal;
                text-align: center;
                padding: 8px 5px;
            }
            
            .user-info {
                flex-direction: column;
                align-items: flex-end;
            }
        }

        a.btn{
            text-decoration: none;
        }

        .resumen-indicadores {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .indicador {
            text-align: center;
            padding: 10px;
            min-width: 120px;
        }

        .indicador-numero {
            font-size: 1.5rem;
            font-weight: bold;
            color: #0E544C;
        }

        .indicador-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .resumen-deducciones {
            background-color: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        
        .total-deducciones {
            font-size: 1.2rem;
            font-weight: bold;
            color: #856404;
            text-align: center;
        }
        
        /* Efecto hover para filas de tabla - AGREGAR ESTO */
        tr:hover {
            background-color: rgba(81, 184, 172, 0.1) !important;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }
        
        /* Para mantener los colores de fondo originales en las filas especiales */
        .registro-programado:hover {
            background-color: rgba(248, 255, 248, 0.8) !important;
        }
        
        .registro-sin-programar:hover {
            background-color: rgba(255, 248, 248, 0.8) !important;
        }
        
        .sin-marcacion:hover {
            background-color: rgba(255, 243, 205, 0.8) !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="<?= $assets_url ?>img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container" style="display:none;">
                    <a href="historial_marcacion_individual.php" class="btn-agregar activo">
                        <i class="fas fa-history"></i> <span class="btn-text">Mi Asistencia</span>
                    </a>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div>
                            <?= htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                        </div>
                        <small>
                            <?= htmlspecialchars($cargoUsuario) ?>
                        </small>
                    </div>
                    <a href="/index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <h1 class="title">Indicadores de Asistencia</h1>
        
        <div class="user-welcome" style="display:none;">
            <h3>Bienvenido/a, <?= htmlspecialchars($nombreUsuario) ?></h3>
            <p>Consulta tu historial de asistencia y deducciones</p>
        </div>
        
        <?php if (isset($_SESSION['exito'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['exito'] ?>
                <?php unset($_SESSION['exito']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php
        // Calcular resumen de indicadores de asistencia
        $totalRegistros = count($historial);
        $totalTardanzas = 0;
        $totalFaltas = 0;
        $totalViaticos = 0;
        
        foreach ($historial as $registro) {
            if ($registro['tiene_tardanza']) $totalTardanzas++;
            if ($registro['tiene_falta']) $totalFaltas++;
            if ($registro['tiene_viatico']) $totalViaticos++;
        }
        ?>
        
        <div class="resumen-indicadores" style="display:none;">
            <div class="indicador">
                <div class="indicador-numero"><?= $totalRegistros ?></div>
                <div class="indicador-label">Días</div>
            </div>
            <div class="indicador">
                <div class="indicador-numero" style="color: #dc3545;"><?= $totalTardanzas ?></div>
                <div class="indicador-label">Tardanzas</div>
            </div>
            <div class="indicador">
                <div class="indicador-numero" style="color: #ffc107;"><?= $totalFaltas ?></div>
                <div class="indicador-label">Faltas</div>
            </div>
            <div class="indicador">
                <div class="indicador-numero" style="color: #28a745;"><?= $totalViaticos ?></div>
                <div class="indicador-label">Viáticos</div>
            </div>
        </div>

        <h2 class="section-title">Asistencia (últimos 20 días):</h2>
        
        <div class="table-container">
            <?php if (empty($historial)): ?>
                <div class="no-results">
                    No se encontraron registros de asistencia para el período seleccionado.
                </div>
            <?php else: ?>
                <table id="tabla-asistencia">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Sucursal</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Tardanza No Justificada</th>
                            <th>Falta No Justificada</th>
                            <th>Viático</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $registro): ?>
                            <tr <?= $registro['tipo_registro'] === 'programado' ? 'registro-programado' : 'registro-sin-programar' ?><?= !$registro['tiene_marcacion'] ? ' sin-marcacion' : '' ?>>
                                <td><?= formatoFecha($registro['fecha']) ?></td>
                                <td><?= htmlspecialchars($registro['nombre_sucursal']) ?></td>
                                <td>
                                    <?php if ($registro['hora_ingreso']): ?>
                                        <?= formatoHoraAmPm($registro['hora_ingreso']) ?>
                                        <?php if ($registro['hora_entrada_programada'] || $registro['tiene_horario']): ?>
                                            <br>
                                            <small style="color: #6c757d;">
                                                Prog: 
                                                <?php if ($registro['hora_entrada_programada']): ?>
                                                    <?= formatoHoraAmPm($registro['hora_entrada_programada']) ?>
                                                <?php else: ?>
                                                    <?= 
                                                        $registro['tiene_horario'] ? 
                                                        obtenerEstadoDia($registro['fecha'], $registro['sucursal_codigo'], $codOperario) : 
                                                        'No programado'
                                                    ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #6c757d;">No marcado</span>
                                        <?php if ($registro['tiene_horario']): ?>
                                            <br>
                                            <small style="color: #6c757d;">
                                                Prog: 
                                                <?php if ($registro['hora_entrada_programada']): ?>
                                                    <?= formatoHoraAmPm($registro['hora_entrada_programada']) ?>
                                                <?php else: ?>
                                                    <?= obtenerEstadoDia($registro['fecha'], $registro['sucursal_codigo'], $codOperario) ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <br>
                                            <small style="color: #6c757d;">
                                                Prog: No programado
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($registro['hora_salida']): ?>
                                        <?= formatoHoraAmPm($registro['hora_salida']) ?>
                                        <?php if ($registro['hora_salida_programada'] || $registro['tiene_horario']): ?>
                                            <br>
                                            <small style="color: #6c757d;">
                                                Prog: 
                                                <?php if ($registro['hora_salida_programada']): ?>
                                                    <?= formatoHoraAmPm($registro['hora_salida_programada']) ?>
                                                <?php else: ?>
                                                    <?= 
                                                        $registro['tiene_horario'] ? 
                                                        obtenerEstadoDia($registro['fecha'], $registro['sucursal_codigo'], $codOperario) : 
                                                        'No programado'
                                                    ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #6c757d;">No marcado</span>
                                        <?php if ($registro['tiene_horario']): ?>
                                            <br>
                                            <small style="color: #6c757d;">
                                                Prog: 
                                                <?php if ($registro['hora_salida_programada']): ?>
                                                    <?= formatoHoraAmPm($registro['hora_salida_programada']) ?>
                                                <?php else: ?>
                                                    <?= obtenerEstadoDia($registro['fecha'], $registro['sucursal_codigo'], $codOperario) ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <br>
                                            <small style="color: #6c757d;">
                                                Prog: No programado
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($registro['tiene_tardanza']): ?>
                                        <i class="fas fa-check-circle" style="color: red;" title="Tardanza registrada"></i>
                                        <?php if (isset($registro['tardanza_justificada']) && $registro['tardanza_justificada']): ?>
                                            <br><small style="color: green;">(Justificada)</small>
                                        <?php endif; ?>
                                    <?php elseif ($registro['tiene_horario'] && $registro['tiene_marcacion'] && $registro['hora_ingreso']): ?>
                                        <!-- No mostrar ícono cuando llegó a tiempo -->
                                    <?php else: ?>
                                        <!-- No mostrar ícono cuando no aplica -->
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($registro['tiene_falta']): ?>
                                        <i class="fas fa-check-circle" style="color: red;" title="Falta registrada"></i>
                                        <?php if (isset($registro['falta_justificada']) && $registro['falta_justificada']): ?>
                                            <br><small style="color: green;">(Justificada)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- No mostrar ícono cuando no aplica -->
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($registro['tiene_viatico']): ?>
                                        <i class="fas fa-check-circle icon-si" title="Viático aplicado"></i>
                                    <?php else: ?>
                                        <!-- No mostrar ícono cuando no aplica -->
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <h2 class="section-title">Deducciones (últimos 20 días):</h2>
        
        <?php if (!empty($deducciones)): ?>
            <div class="resumen-deducciones" style="display:none;">
                <div class="total-deducciones">
                    Total de Deducciones: C$ <?= number_format($total_deducciones, 2) ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <?php if (empty($deducciones)): ?>
                <div class="no-results">
                    No se encontraron deducciones para el período seleccionado.
                </div>
            <?php else: ?>
                <table id="tabla-deducciones">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Sucursal</th>
                            <th>Detalle</th>
                            <th>Monto (C$)</th>
                            <th>Tipo</th>
                            <th>A aplicarse en</th>
                            <th>Cobrado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deducciones as $deduccion): ?>
                        <tr>
                            <td>
                                <?php
                                    // USAR fecha_evento_local DIRECTAMENTE (ya convertida)
                                    if ($deduccion['tipo'] == 'faltante_caja') {
                                        // Para faltante_caja, ya está en hora local
                                        echo formatoFechaCorta($deduccion['fecha_evento_local']);
                                    } else {
                                        // Para los demás tipos, ya está convertida
                                        $fecha_evento = new DateTime($deduccion['fecha_evento_local']);
                                        echo formatoFechaCorta($fecha_evento->format('Y-m-d'));
                                    }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($deduccion['sucursal_nombre']) ?></td>
                            <td style="text-align: left;">
                                <?php if (!empty($deduccion['comentarios'])): ?>
                                    <?= htmlspecialchars($deduccion['comentarios']) ?>
                                <?php else: ?>
                                    <span style="color: #6c757d; font-style: italic; font-size: 0.9em;">
                                        <?php
                                        // Mostrar "Faltante de caja + fecha" cuando no hay comentarios
                                        if ($deduccion['tipo'] == 'faltante_caja') {
                                            // USAR fecha_evento_local QUE YA ESTÁ CONVERTIDA
                                            $fecha_evento = new DateTime($deduccion['fecha_evento_local']);
                                            echo 'Faltante de caja ' . $fecha_evento->format('d/m/Y');
                                        } else {
                                            // Para otros tipos, mantener el texto original
                                            echo 'Sin comentarios';
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="monto-faltante <?= ($deduccion['monto'] == 0) ? 'monto-positivo' : 'monto-negativo' ?>">
                                <?= number_format(abs($deduccion['monto']), 2) ?>
                            </td>
                            <td>
                                <?php 
                                    // Mostrar el tipo de auditoría con un badge de color
                                    $tipo = $deduccion['tipo'] ?? '';
                                    $badge_class = 'badge-' . $tipo;
                                    $tipo_text = '';
                                    
                                    switch($tipo) {
                                        case 'facturacion':
                                            $tipo_text = 'Caja Facturación';
                                            break;
                                        case 'caja_chica':
                                            $tipo_text = 'Caja Chica';
                                            break;
                                        case 'inventario':
                                            $tipo_text = 'Auditoría Inventario';
                                            break;
                                        case 'faltante_inventario':
                                            $tipo_text = 'Faltante Inventario';
                                            break;
                                        case 'faltante_danos':
                                            $tipo_text = 'Faltante Daños';
                                            break;
                                        case 'faltante_caja':
                                            $tipo_text = 'Faltante de Caja';
                                            break;
                                        default:
                                            $tipo_text = 'Desconocido';
                                            $badge_class = 'badge-default';
                                    }
                                    
                                    echo '<span class="badge-tipo ' . $badge_class . '">' . $tipo_text . '</span>';
                                ?>
                            </td>
                             <td>
                                <?= htmlspecialchars($deduccion['estado_deduccion'] ?? '') ?>
                            </td>
                            <td>
                                <?php if ($deduccion['cobrado'] == 1): ?>
                                    <span class="badge-tipo badge-success" style="background-color: #28a745; padding: 2px 8px; border-radius: 10px; color: white; display: inline-block;"><i class="fas fa-check-circle"></i> Sí</span>
                                <?php else: ?>
                                    <span class="badge-tipo badge-secondary" style="background-color: #6c757d; padding: 2px 8px; border-radius: 10px; color: white; display: inline-block;"><i class="fas fa-clock"></i> No</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 20px; padding: 10px; background-color: #f8f9fa; border-radius: 5px; font-size: 0.9rem; display:none;">
            <p><strong>Leyenda:</strong></p>
            <ul style="list-style: none; padding-left: 0;">
                <li><i class="fas fa-check-circle icon-si"></i> = Sí aplica</li>
                <li><i class="fas fa-times-circle icon-no"></i> = No aplica</li>
                <li><i class="fas fa-minus-circle icon-na"></i> = No disponible para evaluación</li>
                <li><span style="background-color: #f8fff8; padding: 2px 5px;">Fondo verde claro</span> = Día con horario programado</li>
                <li><span style="background-color: #fff8f8; padding: 2px 5px;">Fondo rojo claro</span> = Día sin horario programado</li>
                <li><span style="background-color: #fff3cd; padding: 2px 5px;">Fondo amarillo</span> = Día con horario programado pero sin marcación</li>
            </ul>
        </div>
    </div>
    
    <script>
        // Mostrar notificaciones si hay en sesión
        <?php if (isset($_SESSION['exito'])): ?>
            setTimeout(() => {
                const alert = document.querySelector('.alert-success');
                if (alert) alert.style.display = 'none';
            }, 5000);
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            setTimeout(() => {
                const alert = document.querySelector('.alert-danger');
                if (alert) alert.style.display = 'none';
            }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>