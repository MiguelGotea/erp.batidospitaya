<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php';

/**
 * Obtiene los colaboradores que están actualmente en turno (marcaron entrada hoy pero no salida)
 */
function obtenerColaboradoresEnTurno($codSucursal = null)
{
    global $conn;

    $fechaActual = date('Y-m-d');

    $sql = "
            SELECT DISTINCT
                m.CodOperario,
                o.Nombre,
                o.Apellido,
                o.Nombre2,
                o.Apellido2,
                m.hora_ingreso,
                m.fecha,
                m.sucursal_codigo,
                s.nombre as sucursal_nombre,
                o.foto_perfil,
                TIME(m.hora_ingreso) as hora_entrada_formateada
            FROM marcaciones m
            INNER JOIN Operarios o ON m.CodOperario = o.CodOperario
            INNER JOIN sucursales s ON m.sucursal_codigo = s.codigo
            WHERE m.fecha = ?
            AND m.hora_ingreso IS NOT NULL
            AND (m.hora_salida IS NULL OR m.hora_salida = '')
            AND o.Operativo = 1
        ";

    $params = [$fechaActual];

    if ($codSucursal !== null) {
        $sql .= " AND m.sucursal_codigo = ?";
        $params[] = $codSucursal;
    }

    // Ordenar por hora de entrada (los que entraron más tarde primero)
    $sql .= " ORDER BY m.hora_ingreso DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Formatea la hora para mostrar de manera más amigable
 */
function formatoHoraAmigable($hora)
{
    if (empty($hora)) {
        return 'No registrada';
    }

    $timestamp = strtotime($hora);
    $horaFormateada = date('g:i a', $timestamp);

    // Agregar "hoy" si es el día actual
    $hoy = date('Y-m-d');
    $fechaHora = date('Y-m-d', $timestamp);

    if ($fechaHora === $hoy) {
        return "a las {$horaFormateada}";
    } else {
        return date('d/m g:i a', $timestamp);
    }
}

// Función para verificar si el operario tiene contrato activo (puede marcar)
function puedeMarcarOperario($codOperario)
{
    global $conn;

    // Obtener el último contrato del operario
    $stmt = $conn->prepare("
            SELECT CodContrato, fecha_salida, fin_contrato
            FROM Contratos 
            WHERE cod_operario = ? 
            ORDER BY CodContrato DESC 
            LIMIT 1
        ");
    $stmt->execute([$codOperario]);
    $ultimoContrato = $stmt->fetch();

    // Si no hay contrato, no puede marcar
    if (!$ultimoContrato) {
        return false;
    }

    // Si tiene fecha_salida y esta es menor o igual a la fecha actual, NO puede marcar
    if (!empty($ultimoContrato['fecha_salida']) && $ultimoContrato['fecha_salida'] != '0000-00-00') {
        $fechaSalida = new DateTime($ultimoContrato['fecha_salida']);
        $fechaActual = new DateTime();

        // Si la fecha de salida ya pasó, no puede marcar
        if ($fechaSalida <= $fechaActual) {
            return false;
        }
    }

    // Si fecha_salida es NULL o está en el futuro, SÍ puede marcar
    // NOTA: No estamos validando fin_contrato, solo fecha_salida como solicitaste
    return true;
}

// Función para detectar dispositivos móviles
function esDispositivoMovil()
{
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $dispositivosMoviles = [
        'Android',
        'webOS',
        'iPhone',
        'iPad',
        'iPod',
        'BlackBerry',
        'Windows Phone',
        'Mobile',
        'IEMobile',
        'Opera Mini',
        'Mobi'
    ];

    foreach ($dispositivosMoviles as $dispositivo) {
        if (stripos($userAgent, $dispositivo) !== false) {
            return true;
        }
    }
    return false;
}

// Verificar si es móvil y redirigir
if (esDispositivoMovil()) {
    $_SESSION['error'] = "Acceso Restringido.";
    header('Location: /modulos/sucursales/index.php');
    exit();
}

verificarAccesoModulo('sucursales');

// Obtener información del usuario logueado
$usuarioActual = obtenerUsuarioActual();
$sucursalUsuario = $usuarioActual['sucursal_codigo'] ?? null;

// DOBLE VALIDACIÓN: Verificar dispositivo y navegador para la sucursal
if (!$sucursalUsuario) {
    $_SESSION['error'] = "Acceso Denegado: Tu usuario no tiene una sucursal asignada. Contacta a soporte técnico.";
    header('Location: /index.php');
    exit();
}

$validacion = verificarDispositivoAutorizado($sucursalUsuario);

if (!$validacion['status']) {
    $_SESSION['error'] = $validacion['msg'];
    header('Location: /index.php');
    exit();
}

// Obtener colaboradores en turno para esta sucursal
$colaboradoresEnTurno = obtenerColaboradoresEnTurno($sucursalUsuario);

// Contar total de colaboradores en turno
$totalEnTurno = count($colaboradoresEnTurno);

// Verificar IP de sucursal, no se toma en cuenta la sucursal 10 por mala conexión a internet: if ($sucursalUsuario != 10 && $sucursalUsuario != 4  && !verificarIpSucursal($sucursalUsuario)) {
if (!verificarIpSucursal($sucursalUsuario)) {
}
//if ($sucursalUsuario != 14 && $sucursalUsuario != 2 && !verificarIpSucursal($sucursalUsuario)) {
//    $_SESSION['error'] = "Acceso denegado. Contactar con Sistemas-TI";
//    header('Location: index.php');
//    exit();
//    }

// Función para obtener el horario programado del operario (actualizada)
function obtenerHorarioProgramado($codOperario, $sucursalCodigo, $fecha)
{
    // Para sucursales 6 y 18, usar horario fijo con almuerzo
    if ($sucursalCodigo == 6 || $sucursalCodigo == 18) {
        $horaActual = date('H:i:s');

        // Si es entre 12:00 PM y 1:00 PM, es horario de almuerzo
        if ($horaActual >= '12:00:00' && $horaActual <= '13:00:00') {
            return [
                'estado' => 'Almuerzo',
                'hora_entrada' => '13:00:00',
                'hora_salida' => '12:00:00',
                'numero_semana' => date('W', strtotime($fecha))
            ];
        }

        return [
            'estado' => 'Activo',
            'hora_entrada' => '07:00:00',
            'hora_salida' => '17:30:00',
            'numero_semana' => date('W', strtotime($fecha))
        ];
    }

    // Resto del código original para otras sucursales
    $diaSemana = strtolower(date('l', strtotime($fecha)));
    $diasEspanol = [
        'monday' => 'lunes',
        'tuesday' => 'martes',
        'wednesday' => 'miercoles',
        'thursday' => 'jueves',
        'friday' => 'viernes',
        'saturday' => 'sabado',
        'sunday' => 'domingo'
    ];
    $dia = $diasEspanol[$diaSemana] ?? '';

    if (empty($dia)) {
        return null;
    }

    // Buscar semana por fecha
    $sqlSemana = "SELECT numero_semana FROM SemanasSistema 
                      WHERE fecha_inicio <= ? AND fecha_fin >= ?";
    $stmtSemana = ejecutarConsulta($sqlSemana, [$fecha, $fecha]);

    if ($stmtSemana && $semana = $stmtSemana->fetch()) {
        $sqlHorario = "SELECT 
                            id,
                            numero_semana,
                            {$dia}_estado as estado,
                            {$dia}_entrada as hora_entrada,
                            {$dia}_salida as hora_salida
                          FROM HorariosSemanalesOperaciones
                          WHERE numero_semana = ? 
                          AND cod_operario = ? 
                          AND cod_sucursal = ?";
        $stmtHorario = ejecutarConsulta($sqlHorario, [
            $semana['numero_semana'],
            $codOperario,
            $sucursalCodigo
        ]);

        if ($stmtHorario && $horario = $stmtHorario->fetch()) {
            return $horario['estado'] === 'Activo' ? $horario : null;
        }
    }

    return null;
}

// Procesar formulario de marcación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $clave = trim($_POST['clave']);

    // Primero obtenemos el departamento de la sucursal del usuario logueado
    $codDepartamento = obtenerCodigoDepartamentoSucursal($sucursalUsuario);

    // Validar credenciales - Añadiendo validación para cargo 27
    $sql = "SELECT o.CodOperario, o.Nombre, o.Apellido, o.usuario, 
               nc.Nombre as cargo_nombre, s.nombre as sucursal_nombre, 
               s.codigo as sucursal_codigo, nc.CodNivelesCargos as cargo_codigo
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            AND anc.Fecha <= CURDATE()
        JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
        JOIN sucursales s ON anc.Sucursal = s.codigo
        WHERE o.usuario = ? AND (o.clave = ? OR (o.clave_hash IS NOT NULL AND ? = o.clave_hash))
        AND s.codigo IS NOT NULL";  // Eliminada restricción de sucursal/departamento

    $stmt = ejecutarConsulta($sql, [$usuario, $clave, $clave]); // Eliminados los últimos 2 parámetros: $sucursalUsuario, $codDepartamento

    if ($stmt && $operario = $stmt->fetch()) {
        // Validar que no sea cargo 27 (Sucursales)
        if ($operario['cargo_codigo'] == 27) {
            $_SESSION['error'] = "Usuario no corresponde a parámetros válidos de marcación. Toma tu batido Proteico e inténtalo de nuevo...";
            header('Location: marcacion.php');
            exit();
        }

        // NUEVA VALIDACIÓN: Verificar si puede marcar según su contrato
        $codOperario = $operario['CodOperario'];
        if (!puedeMarcarOperario($codOperario)) {
            $_SESSION['error'] = "Usuario no puede marcar porque ya tiene fecha de salida registrada en su contrato. Contacta con Recursos Humanos si necesitas aclaraciones.";
            header('Location: marcacion.php');
            exit();
        }

        $horaActual = date('H:i:s');
        $fechaActual = date('Y-m-d');
        $nombreCompleto = $operario['Nombre'] . ' ' . $operario['Apellido'];

        // Verificar omisión del día anterior
        $omisionDiaAnterior = verificarOmisionDiaAnterior($codOperario, $sucursalUsuario);

        // Obtener horario programado
        $horarioProgramado = obtenerHorarioProgramado($codOperario, $sucursalUsuario, $fechaActual);

        // Lógica especial para almuerzo en sucursales 6 y 8
        $esHorarioAlmuerzo = ($sucursalUsuario == 6 || $sucursalUsuario == 18) &&
            $horarioProgramado &&
            isset($horarioProgramado['estado']) &&
            $horarioProgramado['estado'] === 'Almuerzo';

        // Lógica de marcación (similar a la anterior pero con los nuevos campos)
        $registrarSoloSalida = false;
        if ($horarioProgramado && $horarioProgramado['hora_salida'] && !$esHorarioAlmuerzo) {
            $horaSalidaProgramada = strtotime($horarioProgramado['hora_salida']);
            $horaActualTimestamp = strtotime($horaActual);
            $diferenciaMinutos = ($horaSalidaProgramada - $horaActualTimestamp) / 60;

            if ($diferenciaMinutos <= 30) {
                $registrarSoloSalida = true;
            }
        }

        // Verificar última marcación
        $sqlMarcacion = "SELECT * FROM marcaciones 
                             WHERE CodOperario = ? 
                             AND sucursal_codigo = ?
                             ORDER BY fecha DESC, hora_ingreso DESC 
                             LIMIT 1";
        $stmtMarcacion = ejecutarConsulta($sqlMarcacion, [$codOperario, $sucursalUsuario]);
        $ultimaMarcacion = $stmtMarcacion ? $stmtMarcacion->fetch() : false;

        $registrarEntrada = true;

        if ($ultimaMarcacion) {
            if ($ultimaMarcacion['fecha'] == $fechaActual && $ultimaMarcacion['hora_salida'] === null) {
                $horaEntrada = strtotime($ultimaMarcacion['hora_ingreso']);
                $horaActualTimestamp = strtotime($horaActual);
                $diferenciaMinutos = ($horaActualTimestamp - $horaEntrada) / 60;

                if ($diferenciaMinutos < 30) {
                    $_SESSION['error'] = "{$operario['Nombre']} {$operario['Apellido']} ya marcó entrada recientemente. 
                                         Debe esperar al menos 30 minutos para registrar la salida. 
                                         Tiempo transcurrido: " . floor($diferenciaMinutos) . " minutos.";
                    header('Location: marcacion.php');
                    exit();
                }

                $registrarEntrada = false;
            }
        }

        // Obtener el código del último contrato
        $codContrato = obtenerUltimoCodigoContrato($codOperario);

        if ($registrarSoloSalida) {
            $registrarEntrada = false;

            if (!$ultimaMarcacion || $ultimaMarcacion['fecha'] != $fechaActual) {
                $sql = "INSERT INTO marcaciones (
                        hora_ingreso, 
                        hora_salida, 
                        fecha, 
                        CodOperario, 
                        cod_contrato,
                        sucursal_codigo, 
                        nombre_operario,
                        id_horario_semanal,
                        numero_semana
                    ) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [
                    $horaActual,
                    $fechaActual,
                    $codOperario,
                    $codContrato,
                    $sucursalUsuario,
                    $nombreCompleto,
                    $horarioProgramado['id'] ?? null,
                    $horarioProgramado['numero_semana'] ?? null
                ];
            } else {
                $sql = "UPDATE marcaciones 
                            SET hora_salida = ?, 
                                sucursal_codigo = ?,
                                id_horario_semanal = ?,
                                numero_semana = ?,
                                cod_contrato = ?
                            WHERE id = ?";
                $params = [
                    $horaActual,
                    $sucursalUsuario,
                    $horarioProgramado['id'] ?? null,
                    $horarioProgramado['numero_semana'] ?? null,
                    $codContrato,
                    $ultimaMarcacion['id']
                ];
            }
            $tipo = "salida";

            if ($horarioProgramado && $horarioProgramado['hora_salida']) {
                $horaSalidaReal = strtotime($horaActual);
                $salidaTardia = $horaSalidaReal > strtotime($horarioProgramado['hora_salida']);
            }
        } elseif (!$registrarEntrada) {
            $sql = "UPDATE marcaciones 
                        SET hora_salida = ?, 
                            sucursal_codigo = ?,
                            id_horario_semanal = ?,
                            numero_semana = ?,
                            cod_contrato = ?
                        WHERE id = ?";
            $params = [
                $horaActual,
                $sucursalUsuario,
                $horarioProgramado['id'] ?? null,
                $horarioProgramado['numero_semana'] ?? null,
                $codContrato,
                $ultimaMarcacion['id']
            ];
            $tipo = "salida";

            if ($horarioProgramado && $horarioProgramado['hora_salida']) {
                $salidaTardia = strtotime($horaActual) > strtotime($horarioProgramado['hora_salida']);
            }
        } else {
            $sql = "INSERT INTO marcaciones (
                    hora_ingreso, 
                    fecha, 
                    CodOperario, 
                    cod_contrato,
                    sucursal_codigo, 
                    nombre_operario,
                    id_horario_semanal,
                    numero_semana
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $horaActual,
                $fechaActual,
                $codOperario,
                $codContrato,
                $sucursalUsuario,
                $nombreCompleto,
                $horarioProgramado['id'] ?? null,
                $horarioProgramado['numero_semana'] ?? null
            ];
            $tipo = "entrada";

            if ($horarioProgramado && $horarioProgramado['hora_entrada']) {
                $tardanzaEntrada = strtotime($horaActual) > strtotime($horarioProgramado['hora_entrada']);
            }
        }

        // Definir si hay tardanza (excepto en horario de almuerzo) CON TOLERANCIA
        $tardanzaEntrada = false;
        $salidaTardia = false;
        $enMinutoGracia = false;

        // Lógica especial para sucursales 6 y 18 (CDS/Administrativo)
        if ($sucursalUsuario == 6 || $sucursalUsuario == 18) {
            // Para horario de almuerzo (12:00 PM - 1:00 PM)
            if ($horaActual >= '12:00:00' && $horaActual <= '13:00:00') {
                // Durante almuerzo, no hay tardanzas
                $tardanzaEntrada = false;
                $salidaTardia = false;
                $enMinutoGracia = false;
            }
            // Para entrada normal (7:00 AM)
            elseif ($tipo === 'entrada') {
                $tardanzaEntrada = verificarTardanzaConTolerancia($horaActual, '07:00:00');
                $enMinutoGracia = !$tardanzaEntrada && strtotime($horaActual) > strtotime('07:00:00');
            }
            // Para salida normal (5:30 PM)
            elseif ($tipo === 'salida') {
                $salidaTardia = verificarTardanzaConTolerancia($horaActual, '17:30:00');
                $enMinutoGracia = false; // No aplica minuto de gracia para salidas
            }
        } else {
            // Para otras sucursales (lógica original)
            $tardanzaEntrada = !$esHorarioAlmuerzo &&
                $horarioProgramado &&
                $horarioProgramado['hora_entrada'] &&
                verificarTardanzaConTolerancia($horaActual, $horarioProgramado['hora_entrada']);

            $salidaTardia = !$esHorarioAlmuerzo &&
                $horarioProgramado &&
                $horarioProgramado['hora_salida'] &&
                verificarTardanzaConTolerancia($horaActual, $horarioProgramado['hora_salida']);

            $enMinutoGracia = !$esHorarioAlmuerzo &&
                $horarioProgramado &&
                $horarioProgramado['hora_entrada'] &&
                !$tardanzaEntrada &&
                strtotime($horaActual) > strtotime($horarioProgramado['hora_entrada']);
        }

        $stmt = ejecutarConsulta($sql, $params);

        // Obtener el ID de la marcacion recien creada o actualizada para captura DVR
        if ($registrarEntrada && $tipo === 'entrada') {
            // INSERT nuevo registro
            $idMarcacionNueva = $conn->lastInsertId();
        } elseif (!$registrarEntrada || $tipo === 'salida' || $registrarSoloSalida) {
            // UPDATE: usar el ID de la ultima marcacion
            $idMarcacionNueva = $ultimaMarcacion['id'] ?? 0;
            // Si fue INSERT de solo salida sin marcacion previa
            if ($registrarSoloSalida && (!$ultimaMarcacion || $ultimaMarcacion['fecha'] != $fechaActual)) {
                $idMarcacionNueva = $conn->lastInsertId();
            }
        } else {
            $idMarcacionNueva = $conn->lastInsertId();
        }

        // Guardar en sesion para que JS dispare la captura DVR silenciosa
        $_SESSION['dvr_captura_pendiente'] = [
            'id_marcacion' => (int)$idMarcacionNueva,
            'tipo'         => $tipo,
            'cod_sucursal' => (int)$sucursalUsuario,
        ];

        // Consulta de tardanzas actualizada
        $sqlTardanzas = "SELECT 
                (SELECT COUNT(*) FROM marcaciones m
                 LEFT JOIN HorariosSemanalesOperaciones h ON m.numero_semana = h.numero_semana
                     AND m.CodOperario = h.cod_operario 
                     AND m.sucursal_codigo = h.cod_sucursal
                 WHERE m.CodOperario = ? 
                 AND MONTH(m.fecha) = MONTH(CURDATE())
                 AND YEAR(m.fecha) = YEAR(CURDATE())
                 AND (
                     /* Tardanzas por entrada fuera de hora */
                     (m.hora_ingreso IS NOT NULL AND (
                         /* Para sucursales 6 y 18 */
                         (m.sucursal_codigo IN (6, 18) AND TIME(m.hora_ingreso) > '07:00:00')
                         OR
                         /* Para otras sucursales con horario programado */
                         (m.sucursal_codigo NOT IN (6, 18) AND m.hora_ingreso > (
                             SELECT CASE 
                                 WHEN DAYOFWEEK(m.fecha) = 1 THEN h.domingo_entrada
                                 WHEN DAYOFWEEK(m.fecha) = 2 THEN h.lunes_entrada
                                 WHEN DAYOFWEEK(m.fecha) = 3 THEN h.martes_entrada
                                 WHEN DAYOFWEEK(m.fecha) = 4 THEN h.miercoles_entrada
                                 WHEN DAYOFWEEK(m.fecha) = 5 THEN h.jueves_entrada
                                 WHEN DAYOFWEEK(m.fecha) = 6 THEN h.viernes_entrada
                                 WHEN DAYOFWEEK(m.fecha) = 7 THEN h.sabado_entrada
                             END
                         ))
                     ))
                     /* O omisiones de marcado de entrada */
                     OR (m.hora_ingreso IS NULL)
                 )) as tardanzas_totales,
                 
                 (SELECT COUNT(*) FROM TardanzasManuales
                  WHERE cod_operario = ?
                  AND MONTH(fecha_tardanza) = MONTH(CURDATE())
                  AND YEAR(fecha_tardanza) = YEAR(CURDATE())
                  AND estado = 'Justificado') as tardanzas_justificadas";

        $stmtTardanzas = ejecutarConsulta($sqlTardanzas, [$codOperario, $codOperario]);
        $tardanzasData = $stmtTardanzas ? $stmtTardanzas->fetch() : ['tardanzas_totales' => 0, 'tardanzas_justificadas' => 0];
        $tardanzasTotales = $tardanzasData['tardanzas_totales'];
        $tardanzasEjecutadas = $tardanzasTotales - $tardanzasData['tardanzas_justificadas'];

        // Consulta de omisiones (actualizada para detectar días con solo 1 marcación o NULL en entrada/salida)
        $sqlOmisiones = "SELECT COUNT(*) as omisiones 
                                FROM marcaciones 
                                WHERE CodOperario = ? 
                                AND MONTH(fecha) = MONTH(CURDATE())
                                AND YEAR(fecha) = YEAR(CURDATE())
                                AND (hora_ingreso IS NULL OR hora_salida IS NULL)";
        $stmtOmisiones = ejecutarConsulta($sqlOmisiones, [$codOperario]);
        $omisionesMes = $stmtOmisiones ? $stmtOmisiones->fetch()['omisiones'] : 0;

        // Nueva consulta para faltas totales
        $sqlFaltasTotales = "SELECT COUNT(*) as faltas_totales
                                 FROM (
                                     /* Días sin ninguna marcación */
                                     SELECT DISTINCT fecha FROM marcaciones
                                     WHERE CodOperario = ?
                                     AND MONTH(fecha) = MONTH(CURDATE())
                                     AND YEAR(fecha) = YEAR(CURDATE())
                                     AND hora_ingreso IS NULL
                                     
                                     UNION
                                     
                                     /* Días con omisión de entrada */
                                     SELECT fecha FROM marcaciones
                                     WHERE CodOperario = ?
                                     AND MONTH(fecha) = MONTH(CURDATE())
                                     AND YEAR(fecha) = YEAR(CURDATE())
                                     AND hora_ingreso IS NULL
                                     
                                     UNION
                                     
                                     /* Días con omisión de salida */
                                     SELECT fecha FROM (
                                         SELECT fecha, MAX(CASE WHEN hora_salida IS NULL THEN 1 ELSE 0 END) as sin_salida
                                         FROM marcaciones
                                         WHERE CodOperario = ?
                                         AND MONTH(fecha) = MONTH(CURDATE())
                                         AND YEAR(fecha) = YEAR(CURDATE())
                                         GROUP BY fecha
                                     ) as temp
                                     WHERE sin_salida = 1
                                 ) as faltas";
        $stmtFaltasTotales = ejecutarConsulta($sqlFaltasTotales, [$codOperario, $codOperario, $codOperario]);
        $faltasTotales = $stmtFaltasTotales ? $stmtFaltasTotales->fetch()['faltas_totales'] : 0;

        // Consulta de faltas manuales no pagadas
        $sqlFaltasNoPagadas = "SELECT COUNT(*) as faltas_no_pagadas 
                                   FROM faltas_manual 
                                   WHERE cod_operario = ? 
                                   AND MONTH(fecha_falta) = MONTH(CURDATE())
                                   AND YEAR(fecha_falta) = YEAR(CURDATE())
                                   AND tipo_falta = 'No_Pagado'";
        $stmtFaltasNoPagadas = ejecutarConsulta($sqlFaltasNoPagadas, [$codOperario]);
        $faltasNoPagadas = $stmtFaltasNoPagadas ? $stmtFaltasNoPagadas->fetch()['faltas_no_pagadas'] : 0;

        $faltasEjecutadas = $faltasTotales - $faltasNoPagadas;
        if ($faltasEjecutadas < 0)
            $faltasEjecutadas = 0;

        // Mensaje de confirmación
        $_SESSION['marcacion_mensaje'] = [
            'nombre' => $nombreCompleto,
            'cargo' => $operario['cargo_nombre'],
            'sucursal' => $operario['sucursal_nombre'],
            'sucursal_codigo' => $sucursalUsuario,
            'tipo' => $tipo,
            'hora' => date('h:i a', strtotime($horaActual)),
            'tardanza_entrada' => $tardanzaEntrada,
            'salida_tardia' => $salidaTardia,
            'en_minuto_gracia' => $enMinutoGracia,
            'hora_entrada_programada' => $horarioProgramado['hora_entrada'] ?? null,
            'hora_salida_programada' => $horarioProgramado['hora_salida'] ?? null,
            'tardanzas_totales' => $tardanzasTotales, // Cambiado de 'tardanzas_mes'
            'tardanzas_ejecutadas' => $tardanzasEjecutadas, // Nuevo campo
            'omisiones_mes' => $omisionesMes,
            'faltas_totales' => $faltasTotales, // Nuevo campo
            'faltas_ejecutadas' => $faltasEjecutadas, // Nuevo campo
            'tiene_horario' => $horarioProgramado !== null,
            'solo_salida' => $registrarSoloSalida,
            'numero_semana' => $horarioProgramado['numero_semana'] ?? null,
            'omision_dia_anterior' => $omisionDiaAnterior,
            'faltas_pendientes' => $faltasEjecutadas
        ];
    } else {
        $_SESSION['error'] = "Usuario o contraseña incorrectos, o no tienes permiso para marcar en esta sucursal. Compra tu batido Triple Berry e inténtalo de nuevo...";
    }

    header('Location: marcacion.php');
    exit();
}

// Obtener última marcación para mostrar botón correcto
$ultimaMarcacionHoy = false;
if (isset($_SESSION['usuario_id'])) {
    $sql = "SELECT m.*, s.nombre as sucursal_nombre 
                FROM marcaciones m
                LEFT JOIN sucursales s ON m.sucursal_codigo = s.codigo
                WHERE m.CodOperario = ? AND m.fecha = ?
                ORDER BY m.hora_ingreso DESC LIMIT 1";
    $stmt = ejecutarConsulta($sql, [$_SESSION['usuario_id'], date('Y-m-d')]);
    $ultimaMarcacionHoy = $stmt ? $stmt->fetch() : false;
}

// Calcular faltas pendientes (faltas automáticas - faltas manuales reportadas)
$sqlFaltasAuto = "SELECT COUNT(*) as faltas_auto 
                      FROM (
                          SELECT DISTINCT fecha 
                          FROM marcaciones 
                          WHERE CodOperario = ? 
                          AND MONTH(fecha) = MONTH(CURDATE())
                          AND YEAR(fecha) = YEAR(CURDATE())
                          AND hora_ingreso IS NULL
                      ) as dias_falta";
$stmtFaltasAuto = ejecutarConsulta($sqlFaltasAuto, [$_SESSION['usuario_id']]);
$faltasAuto = $stmtFaltasAuto ? $stmtFaltasAuto->fetch()['faltas_auto'] : 0;

$sqlFaltasManuales = "SELECT COUNT(*) as faltas_manual 
                          FROM faltas_manual 
                          WHERE cod_operario = ? 
                          AND MONTH(fecha_falta) = MONTH(CURDATE())
                          AND YEAR(fecha_falta) = YEAR(CURDATE())";
$stmtFaltasManuales = ejecutarConsulta($sqlFaltasManuales, [$_SESSION['usuario_id']]);
$faltasManuales = $stmtFaltasManuales ? $stmtFaltasManuales->fetch()['faltas_manual'] : 0;

$faltasPendientes = $faltasAuto - $faltasManuales;
if ($faltasPendientes < 0)
    $faltasPendientes = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batidos Pitaya - Marcación</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <meta name="autocomplete" content="off">
    <meta name="safari-auto-fill" content="off">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
        }
        
        .container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .logo {
            height: 50px;
            display: block;
            margin: 0 auto 20px;
        }
        
        h1 {
            text-align: center;
            color: #0E544C;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #0E544C;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #51B8AC;
            outline: none;
        }
        
        .btn-marcar {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }
        
        .btn-marcar:hover {
            background: #0E544C;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .modal-icon {
            font-size: 3rem;
            color: #51B8AC;
            margin-bottom: 20px;
        }
        
        .modal-mensaje {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #333;
        }
        
        .btn-aceptar {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-aceptar:hover {
            background: #0E544C;
        }
        
        .info-operario {
            background: #f0f9f8;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #51B8AC;
        }
        
        .info-operario p {
            margin: 5px 0;
            color: #0E544C;
        }
        
        .error {
            background-color: #fff3e0;
            color: #e65100;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            border-left: 4px solid #ff9800;
        }
        
        .error i {
            margin-right: 10px;
            font-size: 1.2em;
        }
        
        .modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .btn-regresar {
            display: inline-block;
            background: #6c757d;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 15px;
            transition: background 0.3s;
            text-align: center;
            width: auto;
        }
        
        .btn-regresar:hover {
            background: #5a6268;
            color: white;
        }
        
        .btn-regresar i {
            margin-right: 5px;
        }
        
        .oculto {
            display: none;
        }
        
        .mensaje-gracia {
            color: #6f42c1 !important;
            font-weight: bold;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #6f42c1;
            margin: 10px 0;
        }
        
        .batido-icono {
            color: #e83e8c;
            margin-right: 5px;
        }
        
        /* Estilos para la sección de turno */
.seccion-turno {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    font-size: 13px !important;
}

.lista-turno::-webkit-scrollbar {
    width: 6px;
}

.lista-turno::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.lista-turno::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.lista-turno::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

.item-turno {
    transition: transform 0.2s, box-shadow 0.2s;
    width: 400px;
}

@media (max-width: 480px) {
    .item-turno {
        width: 285px;
    }
}

.item-turno:hover {
    transform: translateX(5px);
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

.badge-turno {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.8; }
    100% { opacity: 1; }
}
    </style>
    
    <script>
        // Verificar tamaño de pantalla al cargar
        //function verificarPantalla() {
          //  const anchoMinimo = 1024; // Ancho mínimo en píxeles para permitir acceso
            
        //    if (window.innerWidth < anchoMinimo) {
            //    document.body.innerHTML = `
          //          <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        //                <h1 style="color: #dc3545;">Acceso restringido</h1>
          //              <a href="/modulos/sucursales/index.php" style="color: #0E544C; text-decoration: underline;">Volver al módulo</a>
            //        </div>
              //  `;
                //document.body.style.backgroundColor = "#f8f9fa";
                //throw new Error("Acceso restringido a dispositivos móviles");
          //  }
        //}
        
        // Verificar también al cambiar el tamaño de la ventana
        //window.addEventListener('resize', verificarPantalla);
    </script>
</head>
<body onload="verificarPantalla()">
    <div class="container">
        <img src="../../core/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
        <h1>Registro de Asistencia</h1>
        
        <?php if (isset($_SESSION['error'])): ?>
                                                        <div class="error" id="errorMessage" style="padding: 15px; margin-bottom: 20px; border-left: 4px solid #ff9800;">
                                                            <i class="fas fa-exclamation-circle" style="margin-right: 10px; color: #ff9800;"></i>
                                                            <?= nl2br(htmlspecialchars($_SESSION['error'])) ?>
                                                        </div>
                                                        <?php unset($_SESSION['error']); ?>
            
                                                        <script>
                                                            // Desaparecer después de 10 segundos
                                                            document.addEventListener('DOMContentLoaded', function() {
                                                                setTimeout(function() {
                                                                    const errorDiv = document.getElementById('errorMessage');
                                                                    if (errorDiv) {
                                                                        errorDiv.style.transition = 'opacity 1s ease';
                                                                        errorDiv.style.opacity = '0';
                                                                        setTimeout(() => errorDiv.remove(), 1000);
                                                                    }
                                                                }, 10000); // 10mil son 10 segundos para ocultar el mensaje
                                                            });
                                                        </script>
        <?php endif; ?>
        
        <form id="formMarcacion" method="POST" autocomplete="off">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" placeholder="Ingrese su usuario" required 
                       autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');">
            </div>
            
            <div class="form-group">
                <label for="clave">Contraseña</label>
                <input type="password" id="clave" name="clave" placeholder="Ingrese su contraseña" required 
                       autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');">
            </div>
            
            <button type="submit" class="btn-marcar" id="btnMarcar">
                <?= ($ultimaMarcacionHoy && !$ultimaMarcacionHoy['hora_salida']) ? 'Marcar Salida' : 'Marcar Entrada' ?>
            </button>
        </form>

        <!-- Botón de test DVR (solo para diagnóstico, eliminar cuando no se necesite) -->
        <div style="margin-top:8px;text-align:right;">
            <button id="btnTestDVR" onclick="testCapturaDVR()" title="Test captura DVR silenciosa"
                style="font-size:10px;padding:3px 8px;background:#e9ecef;border:1px solid #ccc;
                       border-radius:3px;cursor:pointer;color:#666;opacity:0.7;"
                type="button">
                📷 Test DVR
            </button>
        </div>
        
        <!--Información de usuario y sucursal del usuario debajo del modal <?php if (isset($_SESSION['usuario_id'])): ?>
        <div class="info-operario">
            <p><strong>Sucursal actual:</strong> <?= htmlspecialchars($usuarioActual['sucursal_nombre'] ?? 'Sin sucursal asignada') ?></p>
            <p><strong>Usuario:</strong> <?= htmlspecialchars($usuarioActual['usuario'] ?? '') ?></p>
        </div>
        <?php endif; ?>
        -->
        
        <!-- Sección de Colaboradores en Turno -->
        <div class="seccion-turno" style="margin-top: 30px; background: #f8f9fa; border-radius: 8px; padding: 20px;">
            <h3 style="color: #0E544C; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                <span>
                    <i class="fas fa-users" style="margin-right: 10px;"></i>
                    Colaboradores en Turno
                </span>
                <span class="badge-turno" style="background: #51B8AC; color: white; padding: 5px 10px; border-radius: 20px; font-size: 14px;">
                    <?= $totalEnTurno ?> en turno
                </span>
            </h3>
            
            <?php if ($totalEnTurno > 0): ?>
                                                            <div class="lista-turno" style="max-height: 300px; overflow-y: auto;">
                                                                <?php foreach ($colaboradoresEnTurno as $colaborador):
                                                                    $nombreCompleto = obtenerNombreCompletoOperario($colaborador);
                                                                    $horaEntrada = formatoHoraAmigable($colaborador['hora_entrada_formateada']);
                                                                    ?>
                                                                                 <div class="item-turno" style="background: white; padding: 10px 15px; margin-bottom: 8px; border-radius: 6px; border-left: 4px solid #51B8AC; display: flex; justify-content: space-between; align-items: center;">
                                                                                    <div style="display: flex; align-items: center;">
                                                                                        <!-- Avatar/Foto del colaborador -->
                                                                                        <div class="avatar-colaborador" style="width: 45px; height: 45px; border-radius: 50%; overflow: hidden; margin-right: 12px; background: #eee; display: flex; align-items: center; justify-content: center; border: 2px solid #51B8AC;">
                                                                                            <?php if (!empty($colaborador['foto_perfil']) && file_exists($colaborador['foto_perfil'])): ?>
                                                                                                <img src="<?= htmlspecialchars($colaborador['foto_perfil']) ?>" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
                                                                                            <?php else: ?>
                                                                                                <i class="fas fa-user" style="color: #ccc; font-size: 20px;"></i>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                        
                                                                                        <div>
                                                                                            <strong style="color: #333;"><?= htmlspecialchars($nombreCompleto) ?></strong>
                                                                                            <div style="font-size: 13px; color: #666;">
                                                                                                <i class="fas fa-sign-in-alt" style="margin-right: 5px;"></i>
                                                                                                Entrada: <?= $horaEntrada ?>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div style="font-size: 12px; color: #28a745; display: flex; align-items: center;">
                                                                                        <i class="fas fa-circle" style="font-size: 8px; margin-right: 5px;"></i>
                                                                                        Activo
                                                                                    </div>
                                                                                </div>
                                                                <?php endforeach; ?>
                                                            </div>
            <?php else: ?>
                                                            <div style="text-align: center; padding: 20px; color: #6c757d;">
                                                                <i class="fas fa-user-clock" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                                                <p>No hay colaboradores en turno actualmente</p>
                                                            </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 15px; font-size: 12px; color: #6c757d;">
                <i class="fas fa-info-circle"></i>
                Mostrando colaboradores que marcaron entrada hoy pero no han marcado salida
            </div>
        </div>
        
        <!-- Botón de Regresar a Módulo -->
        <a href="/modulos/sucursales/index.php" class="btn-regresar">
            <i class="fas fa-arrow-left"></i> Regresar
        </a>
    </div>
    
    <!-- Modal de confirmación -->
    <div class="modal" id="modalConfirmacion">
        <div class="modal-content">
            <button class="modal-close" id="btnCerrarModal">&times;</button>
            <div class="modal-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="modal-mensaje" id="modalMensaje">
                <!-- Mensaje dinámico -->
            </div>
            <div style="display:none;" class="info-operario" id="modalInfoOperario">
                <!-- Información del operario -->
            </div>
            <button class="btn-aceptar" id="btnAceptar">Aceptar</button>
        </div>
    </div>
    
    <script>
        // Mostrar modal si hay mensaje de marcación
        <?php if (isset($_SESSION['marcacion_mensaje'])): ?>
                                                        document.addEventListener('DOMContentLoaded', function() {
                                                            // Limpiar cualquier dato almacenado en caché
                                                            document.getElementById('usuario').value = '';
                                                            document.getElementById('clave').value = '';
                
                                                            // Deshabilitar autocompletado de manera más agresiva
                                                            setTimeout(function() {
                                                                document.getElementById('formMarcacion').reset();
                                                            }, 0);
                
                                                            // Prevenir el comportamiento por defecto del autocompletado
                                                            document.getElementById('formMarcacion').addEventListener('submit', function(e) {
                                                                const ultimaMarcacion = <?= $ultimaMarcacionHoy ? json_encode($ultimaMarcacionHoy) : 'null' ?>;
                    
                                                                if (ultimaMarcacion && !ultimaMarcacion.hora_salida) {
                                                                    const horaEntrada = new Date(`2000-01-01T${ultimaMarcacion.hora_ingreso}`);
                                                                    const ahora = new Date();
                                                                    const diferenciaMinutos = (ahora - horaEntrada) / (1000 * 60);
                        
                                                                    if (diferenciaMinutos < 30) {
                                                                        e.preventDefault();
                            
                                                                        // Mostrar modal de confirmación
                                                                        const modal = document.getElementById('modalConfirmacion');
                                                                        const modalMensaje = document.getElementById('modalMensaje');
                            
                                                                        modalMensaje.innerHTML = `
                                <p>Has marcado entrada recientemente a las ${ultimaMarcacion.hora_ingreso}.</p>
                                <p>¿Estás seguro que deseas marcar salida ahora?</p>
                                <p>Tiempo transcurrido: ${Math.floor(diferenciaMinutos)} minutos.</p>
                            `;
                            
                                                                        modal.style.display = 'flex';
                            
                                                                        // Configurar botón de aceptar
                                                                        document.getElementById('btnAceptar').onclick = function() {
                                                                            modal.style.display = 'none';
                                                                            document.getElementById('formMarcacion').submit();
                                                                        };
                                                                    }
                                                                }
                                                            });
                
                                                            // Evitar que los navegadores guarden los datos
                                                            document.getElementById('usuario').setAttribute('autocomplete', 'off');
                                                            document.getElementById('clave').setAttribute('autocomplete', 'new-password');
                
                                                            const usuarioInput = document.getElementById('usuario');
                                                            const claveInput = document.getElementById('clave');
                                                            const btnMarcar = document.getElementById('btnMarcar');
                
                                                            // Cambio inicial del botón
                                                            btnMarcar.textContent = 'Marcar Entrada/Salida';
                
                                                            // Verificar usuario al perder foco o cambiar
                                                            usuarioInput.addEventListener('blur', verificarUsuario);
                                                            usuarioInput.addEventListener('input', verificarUsuario);
                
                                                            function verificarUsuario() {
                                                                if (usuarioInput.value.trim() === '') {
                                                                    btnMarcar.textContent = 'Marcar Entrada/Salida';
                                                                    return;
                                                                }
                    
                                                                // Verificar con AJAX el estado del usuario
                                                                fetch('verificar_marcacion.php', {
                                                                    method: 'POST',
                                                                    headers: {
                                                                        'Content-Type': 'application/x-www-form-urlencoded',
                                                                    },
                                                                    body: `usuario=${encodeURIComponent(usuarioInput.value)}&sucursal=${encodeURIComponent('<?= $sucursalUsuario ?>')}`
                                                                })
                                                                .then(response => response.json())
                                                                .then(data => {
                                                                    if (data.success) {
                                                                        btnMarcar.textContent = data.ultimaMarcacion && !data.ultimaMarcacion.hora_salida 
                                                                            ? 'Marcar Salida' 
                                                                            : 'Marcar Entrada';
                                                                    } else {
                                                                        btnMarcar.textContent = 'Marcar Entrada/Salida';
                                                                    }
                                                                })
                                                                .catch(error => {
                                                                    console.error('Error:', error);
                                                                });
                                                            }
                                                        });
        
                                                        document.addEventListener('DOMContentLoaded', function() {
                                                            const mensaje = document.getElementById('modalMensaje');
                                                            const infoOperario = document.getElementById('modalInfoOperario');
                                                            const modal = document.getElementById('modalConfirmacion');
                
                                                            // En el JavaScript que muestra el modal, actualizamos:
                                                            let mensajeHTML = `<?= $_SESSION['marcacion_mensaje']['nombre'] ?> ha marcado <?= $_SESSION['marcacion_mensaje']['tipo'] ?> a las <?= $_SESSION['marcacion_mensaje']['hora'] ?>`;
                
                                                            <?php if ($_SESSION['marcacion_mensaje']['sucursal_codigo'] == 6 || $_SESSION['marcacion_mensaje']['sucursal_codigo'] == 18): ?>
                                                                                                            <?php if ($_SESSION['marcacion_mensaje']['tipo'] === 'entrada'): ?>
                                                                                                                                                            <?php if ($_SESSION['marcacion_mensaje']['hora'] >= '13:00' && $_SESSION['marcacion_mensaje']['hora'] <= '13:30'): ?>
                                                                                                                                                                                                            mensajeHTML += `<br><br><strong>Sucursal CDS/Administrativo</strong>`;
                                                                                                                                                                                                            mensajeHTML += `<br><span style="color: #17a2b8; font-weight: bold;">¡Regreso de almuerzo!</span>`;
                                                                                                                                                                                                            mensajeHTML += `<br>Hora de almuerzo: 12:00 PM a 1:00 PM`;
                                                                                                                                                            <?php elseif ($_SESSION['marcacion_mensaje']['en_minuto_gracia']): ?>
                                                                                                                                                                                                            mensajeHTML += `<br><br><strong>Sucursal CDS/Administrativo</strong>`;
                                                                                                                                                                                                            mensajeHTML += `<br><span style="color: #fd7e14; font-weight: bold;">¡Por poco!</span>`;
                                                                                                                                                                                                            mensajeHTML += `<br>Has utilizado el minuto de tolerancia. Hora programada: 7:00 AM`;
                                                                                                                                                                                                            mensajeHTML += `<br><span style="color: #6f42c1;">Intenta llegar justo a tiempo la próxima vez.</span>`;
                                                                                                                                                            <?php elseif ($_SESSION['marcacion_mensaje']['tardanza_entrada']): ?>
                                                                                                                                                                                                            mensajeHTML += `<br><br><strong>Sucursal CDS/Administrativo</strong>`;
                                                                                                                                                                                                            mensajeHTML += `<br><span style="color: #dc3545; font-weight: bold;">¡Has llegado tarde!</span>`;
                                                                                                                                                                                                            mensajeHTML += `<br>Hora límite: 7:00 AM`;
                                                                                                                                                            <?php else: ?>
                                                                                                                                                                                                            mensajeHTML += `<br><br><strong>Sucursal CDS/Administrativo</strong>`;
                                                                                                                                                                                                            mensajeHTML += `<br><span style="color: #28a745; font-weight: bold;">¡Felicidades por tu puntualidad!</span>`;
                                                                                                                                                                                                            mensajeHTML += `<br>Has marcado antes de las 7:00 AM.`;
                                                                                                                                                            <?php endif; ?>
                                                                                                            <?php else: ?>
                                                                                                                                                            <?php if ($_SESSION['marcacion_mensaje']['hora'] >= '12:00' && $_SESSION['marcacion_mensaje']['hora'] <= '12:30'): ?>
                                                                                                                                                                                                            mensajeHTML += `<br><br><strong>Sucursal CDS/Administrativo</strong>`;
                                                                                                                                                                                                            mensajeHTML += `<br><span style="color: #17a2b8; font-weight: bold;">¡Hora de almuerzo!</span>`;
                                                                                                                                                                                                            mensajeHTML += `<br>Regresa a marcar entrada a la 1:00 PM`;
                                                                                                                                                            <?php elseif ($_SESSION['marcacion_mensaje']['salida_tardia']): ?>
                                                                                                                                                                                                            mensajeHTML += `<br><br><strong>Sucursal CDS/Administrativo</strong>`;
                                                                                                                                                                                                            mensajeHTML += `<br><span style="color: #dc3545; font-weight: bold;">¡Has salido después de tu hora!</span>`;
                                                                                                                                                                                                            mensajeHTML += `<br>Hora programada: 5:30 PM`;
                                                                                                                                                            <?php else: ?>
                                                                                                                                                                                                            mensajeHTML += `<br><br><strong>Sucursal CDS/Administrativo</strong>`;
                                                                                                                                                                                                            mensajeHTML += `<br><span style="color: #28a745; font-weight: bold;">¡Gracias por tu trabajo hoy!</span>`;
                                                                                                                                                                                                            mensajeHTML += `<br>Has salido a tiempo.`;
                                                                                                                                                            <?php endif; ?>
                                                                                                            <?php endif; ?>
                                                            <?php else: ?>
                                                                                                            <?php if ($_SESSION['marcacion_mensaje']['omision_dia_anterior']): ?>
                                                                                                                                                            mensajeHTML += `<br><br><span style="color: #ffc107; font-weight: bold;">¡Atención!</span>`;
                                                                                                                                                            mensajeHTML += `<br>Ayer hubo una omisión en tu marcación. Recuerda marcar siempre tu entrada y salida.`;
                                                                                                            <?php elseif ($_SESSION['marcacion_mensaje']['solo_salida']): ?>
                                                                                                                                                            mensajeHTML += `<br><br><span style="color: #17a2b8; font-weight: bold;">¡Registro especial!</span>`;
                                                                                                                                                            mensajeHTML += `<br>Se ha registrado solo la salida (dentro del período de 30 minutos previos a la hora programada).`;
                                                                                                            <?php elseif (!$_SESSION['marcacion_mensaje']['tiene_horario']): ?>
                                                                                                                                                            mensajeHTML += `<br><br><span style="color: #ffc107; font-weight: bold;">¡Atención!</span>`;
                                                                                                                                                            mensajeHTML += `<br>Has marcado en una fecha que no tienes horario programado.`;
                                                                                                            <?php elseif ($_SESSION['marcacion_mensaje']['tipo'] === 'entrada' && $_SESSION['marcacion_mensaje']['en_minuto_gracia']): ?>
                                                                                                                                                            mensajeHTML += `<br><br><span style="color: #17a2b8; font-weight: bold;">¡Minuto de tolerancia!</span>`;
                                                                                                                                                            mensajeHTML += `<br>Has marcado dentro del minuto de gracia. Hora programada: <?= date('h:i a', strtotime($_SESSION['marcacion_mensaje']['hora_entrada_programada'])) ?>`;
                                                                                                                                                            mensajeHTML += `<br><span style="color: #6c757d; font-style: italic;">Recuerda que este minuto es una cortesía ocasional.</span>`;
                                                                                                            <?php elseif ($_SESSION['marcacion_mensaje']['tipo'] === 'entrada' && $_SESSION['marcacion_mensaje']['tardanza_entrada']): ?>
                                                                                                                                                            mensajeHTML += `<br><br><span style="color: #dc3545; font-weight: bold;">¡Has llegado tarde!</span>`;
                                                                                                                                                            mensajeHTML += `<br>Hora programada: <?= date('h:i a', strtotime($_SESSION['marcacion_mensaje']['hora_entrada_programada'])) ?>`;
                                                                                                            <?php elseif ($_SESSION['marcacion_mensaje']['tipo'] === 'entrada'): ?>
                                                                                                                                                            mensajeHTML += `<br><br><span style="color: #28a745; font-weight: bold;">¡Felicidades por tu puntualidad!</span>`;
                                                                                                                                                            mensajeHTML += `<br>Has marcado a tiempo (<?= date('h:i a', strtotime($_SESSION['marcacion_mensaje']['hora_entrada_programada'])) ?>).`;
                                                                                                            <?php elseif ($_SESSION['marcacion_mensaje']['tipo'] === 'salida' && $_SESSION['marcacion_mensaje']['salida_tardia']): ?>
                                                                                                                                                            mensajeHTML += `<br><br><span style="color: #dc3545; font-weight: bold;">¡Has salido después de tu hora!</span>`;
                                                                                                                                                            mensajeHTML += `<br>Hora programada: <?= date('h:i a', strtotime($_SESSION['marcacion_mensaje']['hora_salida_programada'])) ?>`;
                                                                                                            <?php elseif ($_SESSION['marcacion_mensaje']['tipo'] === 'salida'): ?>
                                                                                                                                                            mensajeHTML += `<br><br><span style="color: #28a745; font-weight: bold;">¡Gracias por tu trabajo hoy!</span>`;
                                                                                                                                                            mensajeHTML += `<br>Has salido a tiempo (<?= date('h:i a', strtotime($_SESSION['marcacion_mensaje']['hora_salida_programada'])) ?>).`;
                                                                                                            <?php endif; ?>
                    
                                                                                                            // Mostrar resumen mensual (siempre visible)
                                                                                                            mensajeHTML += `<br><br><strong>Resumen mensual:</strong>`;
                                                                                                            mensajeHTML += `<div class="oculto">Tardanzas totales (sistema): <?= $_SESSION['marcacion_mensaje']['tardanzas_totales'] ?? 0 ?></div>`;
                                                                                                            mensajeHTML += `<br>Tardanzas: <?= $_SESSION['marcacion_mensaje']['tardanzas_ejecutadas'] ?? 0 ?>`;
                                                                                                            mensajeHTML += `<br>Omisiones de marcación: <?= $_SESSION['marcacion_mensaje']['omisiones_mes'] ?? 0 ?>`;
                                                                                                            mensajeHTML += `<div class="oculto">Faltas totales (sistema): <?= $_SESSION['marcacion_mensaje']['faltas_totales'] ?? 0 ?></div>`;
                                                                                                            mensajeHTML += `<br>Faltas: <?= $_SESSION['marcacion_mensaje']['faltas_ejecutadas'] ?? 0 ?>`;
                                                            <?php endif; ?>
                
                                                            mensaje.innerHTML = mensajeHTML;
                
                                                            infoOperario.innerHTML = `
                    <p><strong>Colaborador/a:</strong> <?= $_SESSION['marcacion_mensaje']['nombre'] ?></p>
                    <p><strong>Cargo:</strong> <?= $_SESSION['marcacion_mensaje']['cargo'] ?? 'Sin cargo asignado' ?></p>
                    <p><strong>Sucursal:</strong> <?= $_SESSION['marcacion_mensaje']['sucursal'] ?? 'Sin sucursal asignada' ?></p>
                `;
                
                                                            modal.style.display = 'flex';
                
                                                            // Cerrar modal al hacer clic en el botón aceptar o en la X
                                                            document.getElementById('btnAceptar').addEventListener('click', function() {
                                                                modal.style.display = 'none';
                                                                window.location.href = 'index.php';
                                                            });
                
                                                            document.getElementById('btnCerrarModal').addEventListener('click', function() {
                                                                modal.style.display = 'none';
                                                                window.location.href = 'index.php';
                                                            });
                
                                                            modal.addEventListener('click', function(e) {
                                                                if (e.target === modal) {
                                                                    modal.style.display = 'none';
                                                                    window.location.href = 'index.php';
                                                                }
                                                            });
                                                        });
            
                                                        // Técnica adicional para Chrome
                                                        if (window.chrome) {
                                                            document.getElementById('usuario').autocomplete = 'new-password';
                                                            document.getElementById('clave').autocomplete = 'new-password';
                                                        }
            
                                                        <?php unset($_SESSION['marcacion_mensaje']); ?>
        <?php endif; ?>
        
        // Evitar envío de formulario con tecla enter
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('formMarcacion');
            const btnMarcar = document.getElementById('btnMarcar');
            let formSubmitted = false;
            
            // Prevenir Enter en cualquier parte del formulario
            form.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    
                    // Mostrar mensaje indicando que debe usar el botón
                    if (!document.getElementById('enter-message')) {
                        const message = document.createElement('div');
                        message.id = 'enter-message';
                        message.style.color = '#dc3545';
                        message.style.marginTop = '10px';
                        message.style.fontSize = '0.9em';
                        message.textContent = 'Por favor, use el botón "Marcar Entrada/Salida"';
                        form.appendChild(message);
                        
                        // Eliminar el mensaje después de 3 segundos
                        setTimeout(() => {
                            if (message.parentNode) {
                                message.remove();
                            }
                        }, 3000);
                    }
                    
                    return false;
                }
            });
            
            // Manejar el envío del formulario
            form.addEventListener('submit', function(e) {
                if (formSubmitted) {
                    e.preventDefault();
                    return false;
                }
                
                // Solo permitir envío mediante clic en el botón
                if (!(e.submitter && e.submitter.id === 'btnMarcar')) {
                    e.preventDefault();
                    return false;
                }
                
                formSubmitted = true;
                btnMarcar.disabled = true;
                btnMarcar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            });
        });
    </script>

    <!-- ═══════════════════════════════════════════════════════
         CAPTURA DVR SILENCIOSA — Se dispara tras cada marcación
         ═══════════════════════════════════════════════════════ -->
    <script>
    (function () {
        // Datos inyectados por PHP tras una marcación exitosa
        <?php if (isset($_SESSION['dvr_captura_pendiente'])): ?>
        var _dvrPendiente = <?= json_encode($_SESSION['dvr_captura_pendiente']) ?>;
        <?php unset($_SESSION['dvr_captura_pendiente']); ?>
        <?php else: ?>
        var _dvrPendiente = null;
        <?php endif; ?>

        /**
         * Captura silenciosa: fire-and-forget, nunca muestra errores al usuario.
         */
        function capturarDVRSilencioso(idMarcacion, tipo, codSucursal) {
            if (!idMarcacion || !tipo || !codSucursal) return;
            try {
                fetch('/modulos/sucursales/ajax/dvr_capturar_marcacion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id_marcacion: idMarcacion,
                        tipo:         tipo,
                        cod_sucursal: codSucursal
                    }),
                    keepalive: true
                }).catch(function () { /* silencioso */ });
            } catch (e) { /* silencioso */ }
        }

        // Disparar captura automática cuando hay marcación pendiente
        if (_dvrPendiente && _dvrPendiente.id_marcacion > 0) {
            // Disparar inmediatamente (sin esperar a DOMContentLoaded)
            capturarDVRSilencioso(
                _dvrPendiente.id_marcacion,
                _dvrPendiente.tipo,
                _dvrPendiente.cod_sucursal
            );
        }

        /**
         * Función de test: simula una captura de entrada para la sucursal actual.
         * Usada por el botón #btnTestDVR. No espera resultado visible.
         */
        window.testCapturaDVR = function () {
            var btn = document.getElementById('btnTestDVR');
            if (btn) {
                btn.textContent = '⏳ Enviando...';
                btn.disabled = true;
            }

            // Obtener la última marcación del día (si hay) para usar su ID
            // Si no hay, usamos id=0 para que el backend detecte el error silenciosamente
            var idTest      = <?= json_encode($ultimaMarcacionHoy ? (int)$ultimaMarcacionHoy['id'] : 0) ?>;
            var tipoTest    = 'entrada';
            var sucTest     = <?= json_encode((int)$sucursalUsuario) ?>;

            fetch('/modulos/sucursales/ajax/dvr_capturar_marcacion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_marcacion: idTest > 0 ? idTest : 1,  // fallback a 1 para test
                    tipo:         tipoTest,
                    cod_sucursal: sucTest
                }),
                keepalive: true
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (btn) {
                    if (data.success) {
                        btn.textContent = '✅ OK (' + (data.size_kb || '?') + ' KB)';
                        btn.style.color = '#28a745';
                        btn.style.borderColor = '#28a745';
                    } else {
                        btn.textContent = '❌ ' + (data.message || 'Error').substring(0, 30);
                        btn.style.color = '#dc3545';
                        btn.style.borderColor = '#dc3545';
                    }
                    btn.disabled = false;
                }
            })
            .catch(function (e) {
                if (btn) {
                    btn.textContent = '❌ Error de red';
                    btn.style.color = '#dc3545';
                    btn.disabled = false;
                }
            });
        };
    })();
    </script>
    
    <script>
    // Control de pestañas simultáneas mejorado
    document.addEventListener('DOMContentLoaded', function() {
        const PAGINA_MARCACION = 'marcacion';
        const PAGINA_HISTORIAL = 'historial';
        const TIMEOUT_REDIRECCION = 1000; // 1 segundo
        
        // Verificar si la página de historial está abierta
        if (localStorage.getItem('pagina_activa') === PAGINA_HISTORIAL) {
            alert('Error: No puedes abrir la página de marcación mientras tengas abierto el historial de marcaciones.\n\nPor favor, cierra la pestaña del historial primero.');
            setTimeout(() => {
                window.location.href = '/modulos/sucursales/index.php';
            }, TIMEOUT_REDIRECCION);
            return;
        }
        
        // Marcar que esta página está abierta
        localStorage.setItem('pagina_activa', PAGINA_MARCACION);
        localStorage.setItem('timestamp_actividad', Date.now());
        
        // Limpiar al cerrar la pestaña
        window.addEventListener('beforeunload', function() {
            if (localStorage.getItem('pagina_activa') === PAGINA_MARCACION) {
                localStorage.removeItem('pagina_activa');
            }
        });
        
        // Detectar cambios entre pestañas
        window.addEventListener('storage', function(e) {
            if (e.key === 'pagina_activa' && e.newValue === PAGINA_HISTORIAL) {
                alert('Atención: Se ha abierto el historial de marcaciones en otra pestaña.\n\nEsta página se cerrará automáticamente.');
                setTimeout(() => {
                    window.location.href = '/modulos/sucursales/index.php';
                }, TIMEOUT_REDIRECCION);
            }
        });
        
        // Verificación periódica para casos donde el evento storage no se dispara
        setInterval(() => {
            if (localStorage.getItem('pagina_activa') === PAGINA_HISTORIAL) {
                alert('Se detectó el historial de marcaciones abierto en otra pestaña.\n\nRedirigiendo...');
                setTimeout(() => {
                    window.location.href = '/modulos/sucursales/index.php';
                }, TIMEOUT_REDIRECCION);
            }
        }, 3000); // Verificar cada 3 segundos
    });
    </script>
</body>
</html>