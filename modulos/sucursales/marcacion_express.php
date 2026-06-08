<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Extender sesión a 6 horas (21600 segundos equivalente) para coincidir con la configuración del ERP
ini_set('session.gc_maxlifetime', 21600);
session_set_cookie_params(21600);
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/helpers/funciones.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';

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
        if ($codSucursal == 18) {
            $sql .= " AND m.sucursal_codigo IN (6, 18)";
        } else {
            $sql .= " AND m.sucursal_codigo = ?";
            $params[] = $codSucursal;
        }
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

    return true;
}

/**
 * Calcula la fecha de inicio del período continuo actual de contrato de un colaborador.
 * Une contratos si el nuevo inicia inmediatamente (hasta 2 días de diferencia para cubrir fin de semana/feriado)
 * después de que finaliza el anterior.
 */
function obtenerFechaInicioContinua($codOperario) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT MIN(inicio_contrato) as fecha_inicio 
            FROM Contratos 
            WHERE cod_operario = ? 
            AND inicio_contrato IS NOT NULL 
            AND inicio_contrato != '0000-00-00'
        ");
        $stmt->execute([$codOperario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['fecha_inicio'] : null;
        
    } catch (Exception $e) {
        error_log("Error en obtenerFechaInicioContinua para operario $codOperario: " . $e->getMessage());
        return null;
    }
}

// Identificar sucursal por la cookie de token
function identificarSucursalPorToken()
{
    // 1. Verificar Navegador (Chrome/Edge)
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $esChromium = (strpos($ua, 'Chrome') !== false || strpos($ua, 'Edg') !== false);

    if (!$esChromium) {
        return ['status' => false, 'msg' => 'Navegador no permitido. Favor usar Google Chrome o Microsoft Edge.'];
    }

    // 2. Verificar Cookie Token
    $tokenCookie = $_COOKIE['erp_device_token'] ?? null;
    if (empty($tokenCookie)) {
        return [
            'status' => false,
            'msg' => 'Este dispositivo no está autorizado para realizar marcaciones. Favor configure el token de dispositivo en este navegador.'
        ];
    }

    global $conn;
    try {
        $stmt = $conn->prepare("SELECT codigo, nombre, cookie_token FROM sucursales WHERE cookie_token = ? LIMIT 1");
        $stmt->execute([$tokenCookie]);
        $sucursal = $stmt->fetch();

        if (!$sucursal) {
            return [
                'status' => false,
                'msg' => 'Dispositivo no autorizado para esta sucursal o el token de dispositivo expiró/es incorrecto.'
            ];
        }

        return ['status' => true, 'sucursal' => $sucursal];
    } catch (Exception $e) {
        error_log("Error al identificar sucursal por token: " . $e->getMessage());
        return ['status' => false, 'msg' => 'Error de sistema al validar dispositivo.'];
    }
}

// Validación de dispositivo al cargar la página
$validacion = identificarSucursalPorToken();
$dispositivoAutorizado = $validacion['status'];
$sucursalUsuarioReal = null;
$sucursalUsuario = null;
$sucursalNombre = null;

if ($dispositivoAutorizado) {
    $sucursalUsuarioReal = $validacion['sucursal']['codigo'];
    $sucursalNombre = $validacion['sucursal']['nombre'];
    
    // Si la sucursal del dispositivo es 6 o 18, tratar como sucursal 18 para registrar marcación
    if ($sucursalUsuarioReal == 6 || $sucursalUsuarioReal == 18) {
        $sucursalUsuario = 18;
    } else {
        $sucursalUsuario = $sucursalUsuarioReal;
    }
}

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dispositivoAutorizado) {
    $clave = isset($_POST['clave']) ? trim($_POST['clave']) : '';

    if (empty($clave)) {
        $_SESSION['error'] = "Debe ingresar una contraseña.";
        header('Location: marcacion_express.php');
        exit();
    }

    // Primero obtenemos el departamento de la sucursal
    $codDepartamento = obtenerCodigoDepartamentoSucursal($sucursalUsuario);

    // Validar credenciales buscando por contraseña en la sucursal autorizada (combinando 6 y 18)
    $sql = "SELECT o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2, o.usuario, o.Cumpleanos, 
                   nc.Nombre as cargo_nombre, s.nombre as sucursal_nombre, 
                   s.codigo as sucursal_codigo, nc.CodNivelesCargos as cargo_codigo
            FROM Operarios o
            JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
                AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                AND anc.Fecha <= CURDATE()
            JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
            JOIN sucursales s ON anc.Sucursal = s.codigo
            WHERE (o.clave = ? OR (o.clave_hash IS NOT NULL AND ? = o.clave_hash))
            AND (s.codigo = ? OR (? = 18 AND s.codigo = 6))
            AND o.Operativo = 1";

    $stmt = ejecutarConsulta($sql, [$clave, $clave, $sucursalUsuario, $sucursalUsuario]);
    $operarios = $stmt ? $stmt->fetchAll() : [];

    if (count($operarios) === 0) {
        $_SESSION['error'] = "Contraseña incorrecta, o no tienes asignada esta sucursal. Compra tu batido Triple Berry e inténtalo de nuevo...";
        header('Location: marcacion_express.php');
        exit();
    } elseif (count($operarios) > 1) {
        $_SESSION['error'] = "Ambigüedad detectada: Varios colaboradores tienen esta misma contraseña en esta sucursal. Favor cambiar su contraseña para poder marcar.";
        header('Location: marcacion_express.php');
        exit();
    }

    $operario = $operarios[0];

    // Validar que no sea cargo 27 (Sucursales)
    if ($operario['cargo_codigo'] == 27) {
        $_SESSION['error'] = "Usuario no corresponde a parámetros válidos de marcación. Toma tu batido Proteico e inténtalo de nuevo...";
        header('Location: marcacion_express.php');
        exit();
    }

    // NUEVA VALIDACIÓN: Verificar si puede marcar según su contrato
    $codOperario = $operario['CodOperario'];
    if (!puedeMarcarOperario($codOperario)) {
        $_SESSION['error'] = "Usuario no puede marcar porque ya tiene fecha de salida registrada en su contrato. Contacta con Recursos Humanos si necesitas aclaraciones.";
        header('Location: marcacion_express.php');
        exit();
    }

    // CONFIRMACIÓN DE MARCACIÓN RECIENTE
    $confirmado = isset($_POST['confirmado']) && $_POST['confirmado'] == '1';

    // Omitir confirmación de marcación reciente en sucursales 6 y 18 por completo (marcan entrada/salida de almuerzo a horas variables)
    $omitirPorAlmuerzo = false;
    if ($sucursalUsuario == 6 || $sucursalUsuario == 18 || $sucursalUsuarioReal == 6 || $sucursalUsuarioReal == 18) {
        $omitirPorAlmuerzo = true;
    }

    if (!$confirmado && !$omitirPorAlmuerzo) {
        $sqlRecent = "SELECT * FROM marcaciones 
                      WHERE CodOperario = ? 
                      ORDER BY fecha DESC, id DESC 
                      LIMIT 1";
        $stmtRecent = ejecutarConsulta($sqlRecent, [$codOperario]);
        $recentMarc = $stmtRecent ? $stmtRecent->fetch() : false;

        if ($recentMarc && $recentMarc['fecha'] == date('Y-m-d')) {
            $horaSalidaReciente = $recentMarc['hora_salida'];

            if (empty($horaSalidaReciente)) {
                // CASO 1: Tiene entrada abierta hoy (sin salida) → posible marcación accidental de salida
                // Mostrar confirmación si la entrada fue hace menos de 8.5 horas (30,600 seg = turno completo)
                $tsEntrada = strtotime($recentMarc['fecha'] . ' ' . $recentMarc['hora_ingreso']);
                $diffSeg = time() - $tsEntrada;
                if ($diffSeg >= 0 && $diffSeg < 30600) {
                    $_SESSION['marcacion_pendiente'] = [
                        'clave'         => $clave,
                        'nombre'        => obtenerNombreCompletoOperario($operario),
                        'caso'          => 'salida_pendiente',
                        'reciente_hora' => date('g:i:s a', $tsEntrada),
                    ];
                    header('Location: marcacion_express.php');
                    exit();
                }
            } else {
                // CASO 2: Ya tiene jornada completa hoy → va a crear una nueva entrada
                $_SESSION['marcacion_pendiente'] = [
                    'clave'             => $clave,
                    'nombre'            => obtenerNombreCompletoOperario($operario),
                    'caso'              => 'nueva_entrada_hoy',
                    'reciente_hora'     => date('g:i:s a', strtotime($recentMarc['fecha'] . ' ' . $recentMarc['hora_ingreso'])),
                    'reciente_hora_sal' => date('g:i:s a', strtotime($recentMarc['fecha'] . ' ' . $horaSalidaReciente)),
                ];
                header('Location: marcacion_express.php');
                exit();
            }
        }
    }

    $horaActual = date('H:i:s');
    $fechaActual = date('Y-m-d');
    $nombreCompleto = obtenerNombreCompletoOperario($operario);

    // Verificar omisión del día anterior (combinando 6 y 18)
    if ($sucursalUsuario == 18) {
        $omisionDiaAnterior = verificarOmisionDiaAnterior($codOperario, 18) && verificarOmisionDiaAnterior($codOperario, 6);
    } else {
        $omisionDiaAnterior = verificarOmisionDiaAnterior($codOperario, $sucursalUsuario);
    }

    // Obtener horario programado
    $horarioProgramado = obtenerHorarioProgramado($codOperario, $sucursalUsuario, $fechaActual);

    // Lógica especial para almuerzo en sucursales 6 y 18
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
                         AND (sucursal_codigo = ? OR (? = 18 AND sucursal_codigo = 6))
                         ORDER BY fecha DESC, hora_ingreso DESC 
                         LIMIT 1";
    $stmtMarcacion = ejecutarConsulta($sqlMarcacion, [$codOperario, $sucursalUsuario, $sucursalUsuario]);
    $ultimaMarcacion = $stmtMarcacion ? $stmtMarcacion->fetch() : false;

    $registrarEntrada = true;

    if ($ultimaMarcacion) {
        if ($ultimaMarcacion['fecha'] == $fechaActual && $ultimaMarcacion['hora_salida'] === null) {
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
                $operario['sucursal_codigo'],
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
                $operario['sucursal_codigo'],
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
            $operario['sucursal_codigo'],
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
            $operario['sucursal_codigo'],
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
        $idMarcacionNueva = $conn->lastInsertId();
    } elseif (!$registrarEntrada || $tipo === 'salida' || $registrarSoloSalida) {
        $idMarcacionNueva = $ultimaMarcacion['id'] ?? 0;
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
        'cod_sucursal' => (int)$sucursalUsuarioReal,
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

    // Consulta de omisiones
    $sqlOmisiones = "SELECT COUNT(*) as omisiones 
                            FROM marcaciones 
                            WHERE CodOperario = ? 
                            AND MONTH(fecha) = MONTH(CURDATE())
                            AND YEAR(fecha) = YEAR(CURDATE())
                            AND (hora_ingreso IS NULL OR hora_salida IS NULL)";
    $stmtOmisiones = ejecutarConsulta($sqlOmisiones, [$codOperario]);
    $omisionesMes = $stmtOmisiones ? $stmtOmisiones->fetch()['omisiones'] : 0;

    // Faltas totales
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

    // Verificar si hoy es su cumpleaños
    $esCumpleanosHoy = false;
    if (!empty($operario['Cumpleanos']) && $operario['Cumpleanos'] !== '0000-00-00 00:00:00') {
        $cumpleMD = date('m-d', strtotime($operario['Cumpleanos']));
        if ($cumpleMD === date('m-d')) {
            $esCumpleanosHoy = true;
        }
    }

    // Verificar si hoy es su aniversario de contrato
    $esAniversarioHoy = false;
    $aniosAniversario = 0;
    $fechaInicioContinua = obtenerFechaInicioContinua($codOperario);
    if ($fechaInicioContinua) {
        $inicioMD = date('m-d', strtotime($fechaInicioContinua));
        if ($inicioMD === date('m-d')) {
            $hoyAnio = (int)date('Y');
            $inicioAnio = (int)date('Y', strtotime($fechaInicioContinua));
            $aniosAniversario = $hoyAnio - $inicioAnio;
            if ($aniosAniversario > 0) {
                $esAniversarioHoy = true;
            }
        }
    }

    // Mensaje de confirmación
    $_SESSION['marcacion_mensaje'] = [
        'nombre' => $nombreCompleto,
        'cargo' => $operario['cargo_nombre'],
        'sucursal' => $operario['sucursal_nombre'],
        'sucursal_codigo' => $operario['sucursal_codigo'],
        'tipo' => $tipo,
        'hora' => date('h:i a', strtotime($horaActual)),
        'tardanza_entrada' => $tardanzaEntrada,
        'salida_tardia' => $salidaTardia,
        'en_minuto_gracia' => $enMinutoGracia,
        'hora_entrada_programada' => $horarioProgramado['hora_entrada'] ?? null,
        'hora_salida_programada' => $horarioProgramado['hora_salida'] ?? null,
        'tardanzas_totales' => $tardanzasTotales,
        'tardanzas_ejecutadas' => $tardanzasEjecutadas,
        'omisiones_mes' => $omisionesMes,
        'faltas_totales' => $faltasTotales,
        'faltas_ejecutadas' => $faltasEjecutadas,
        'tiene_horario' => $horarioProgramado !== null,
        'solo_salida' => $registrarSoloSalida,
        'numero_semana' => $horarioProgramado['numero_semana'] ?? null,
        'omision_dia_anterior' => $omisionDiaAnterior,
        'faltas_pendientes' => $faltasEjecutadas,
        'es_cumpleanos' => $esCumpleanosHoy,
        'es_aniversario' => $esAniversarioHoy,
        'anios_aniversario' => $aniosAniversario
    ];

    header('Location: marcacion_express.php');
    exit();
}

$colaboradoresEnTurno = [];
$totalEnTurno = 0;
if ($dispositivoAutorizado) {
    $colaboradoresEnTurno = obtenerColaboradoresEnTurno($sucursalUsuario);
    $totalEnTurno = count($colaboradoresEnTurno);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batidos Pitaya - Marcación Express</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bcryptjs/2.4.3/bcrypt.min.js"></script>
    <script src="js/offline_marcacion.js"></script>
    <meta name="autocomplete" content="off">
    <meta name="safari-auto-fill" content="off">
    <style>
        :root {
            --primary-color: #0E544C;
            --secondary-color: #51B8AC;
            --secondary-hover: #3f9b90;
            --background-gradient: linear-gradient(135deg, #eef7f6 0%, #dbeee8 100%);
            --card-background: rgba(255, 255, 255, 0.92);
            --text-main: #2c3e50;
            --text-muted: #7f8c8d;
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --shadow-light: 0 8px 32px 0 rgba(14, 84, 76, 0.06);
            --shadow-btn: 0 4px 6px rgba(0, 0, 0, 0.05);
            --transition-speed: 0.3s;
        }

        body.dark-mode {
            --background-gradient: linear-gradient(135deg, #07231f 0%, #030d0b 100%);
            --card-background: rgba(14, 30, 27, 0.95);
            --text-main: #eef7f6;
            --text-muted: #a3bda8;
            --shadow-light: 0 8px 32px 0 rgba(0, 0, 0, 0.4);
            --shadow-btn: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        body, .container, .sidebar, .password-display, .unauthorized-card, .modal-content, .key, .item-turno {
            transition: background var(--transition-speed) ease, 
                        background-color var(--transition-speed) ease, 
                        border-color var(--transition-speed) ease, 
                        color var(--transition-speed) ease, 
                        box-shadow var(--transition-speed) ease,
                        transform 0.2s ease;
        }

        /* Estilos de Teclado y Botones en Modo Oscuro */
        body.dark-mode .key {
            background: #11312b;
            color: #51B8AC;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        body.dark-mode .key:hover {
            background: #1a443c;
        }
        body.dark-mode .key:active {
            background: #0d2621;
        }
        body.dark-mode .key.wide {
            background: #193f38;
        }
        body.dark-mode .key.action-clear {
            background: #3a1a1a;
            color: #ec7063;
        }
        body.dark-mode .key.action-clear:hover {
            background: #c0392b;
            color: white;
        }
        
        /* Tecla Especial Marcar */
        .key.action-confirm {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #3f9b90 100%) !important;
            color: white !important;
        }
        .key.action-confirm:hover {
            background: linear-gradient(135deg, #5fcbbd 0%, var(--secondary-color) 100%) !important;
            color: white !important;
        }
        .key.action-confirm:active {
            background: #34847b !important;
        }
        body.dark-mode .key.action-confirm {
            background: linear-gradient(135deg, #1b7367 0%, #0e544c 100%) !important;
            color: white !important;
        }

        body.dark-mode .password-display {
            background: #0d211e;
            border-color: #214d45;
            color: var(--secondary-color);
        }

        body.dark-mode .item-turno {
            background: #0f2b26;
            border-left-color: var(--secondary-color);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        body.dark-mode .item-turno:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
        }
        body.dark-mode .colaborador-nombre {
            color: #eef7f6;
        }
        body.dark-mode .avatar-colaborador {
            border-color: var(--secondary-color);
            background: #0e201d;
        }

        body.dark-mode .modal-content {
            background: #0e201d;
            border: 1px solid rgba(81, 184, 172, 0.2);
            color: #eef7f6;
        }
        body.dark-mode .modal-title {
            color: #eef7f6;
        }
        body.dark-mode .modal-mensaje {
            color: #eef7f6;
        }
        body.dark-mode .info-operario {
            background: rgba(81, 184, 172, 0.05);
            border-left-color: var(--secondary-color);
        }
        body.dark-mode .info-operario p {
            color: #dbeee8;
        }
        body.dark-mode .modal-close {
            color: var(--text-muted);
        }

        body.dark-mode .keyboard {
            background: rgba(81, 184, 172, 0.03);
            border-color: rgba(81, 184, 172, 0.08);
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        body.dark-mode .unauthorized-card {
            border-color: rgba(81, 184, 172, 0.15);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: var(--background-gradient);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Dispositivo No Autorizado Screen */
        .unauthorized-card {
            max-width: 500px;
            width: 100%;
            background: var(--card-background);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.6);
            animation: fadeIn 0.5s ease-out;
        }

        .lock-icon {
            font-size: 4rem;
            color: var(--danger-color);
            margin-bottom: 25px;
            animation: shake 1s ease-in-out infinite alternate;
        }

        .unauthorized-card h2 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 700;
        }

        .unauthorized-card p {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .btn-reload {
            display: inline-block;
            background: var(--secondary-color);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: var(--shadow-btn);
            transition: all 0.3s;
        }

        .btn-reload:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Main Workspace Layout */
        .workspace {
            display: flex;
            gap: 30px;
            max-width: 1100px;
            width: 100%;
            align-items: flex-start;
        }

        @media (max-width: 950px) {
            .workspace {
                flex-direction: column;
                align-items: center;
            }
        }

        .container {
            flex: 1.4;
            width: 100%;
            background: var(--card-background);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-light);
            border: 1px solid rgba(255, 255, 255, 0.6);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .logo {
            height: 60px;
            display: block;
            margin: 0 auto 15px;
        }

        .header-info {
            text-align: center;
            margin-bottom: 25px;
        }

        .header-info h1 {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .sucursal-badge {
            display: inline-block;
            background: rgba(81, 184, 172, 0.15);
            color: var(--primary-color);
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        /* Reloj Rediseñado */
        .clock-widget {
            background: linear-gradient(135deg, var(--primary-color) 0%, #06312c 100%);
            color: #ffffff;
            border-radius: 16px;
            padding: 18px 25px;
            margin: 15px auto 25px;
            max-width: 450px;
            box-shadow: 0 10px 25px rgba(14, 84, 76, 0.15), 
                        inset 0 1px 1px rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(81, 184, 172, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .clock-widget::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(81, 184, 172, 0.15) 0%, transparent 75%);
            pointer-events: none;
        }

        .clock-display {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 8px;
            margin-bottom: 6px;
            z-index: 2;
        }

        .clock-time {
            font-size: 3.4rem;
            font-weight: 700;
            letter-spacing: 2px;
            line-height: 1;
            font-variant-numeric: tabular-nums;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
            background: linear-gradient(to bottom, #ffffff 60%, #e0f2f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .clock-ampm {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--secondary-color);
            text-transform: uppercase;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
            letter-spacing: 0.5px;
        }

        .clock-date {
            font-size: 0.95rem;
            font-weight: 500;
            color: #dbeee8;
            letter-spacing: 0.8px;
            text-transform: capitalize;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            padding-top: 8px;
            width: 100%;
            text-align: center;
            z-index: 2;
        }

        /* Botón Pantalla Completa Floating */
        .fullscreen-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--card-background);
            border: 1px solid rgba(14, 84, 76, 0.15);
            color: var(--primary-color);
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
            z-index: 1000;
        }

        .fullscreen-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(14, 84, 76, 0.18);
        }

        .fullscreen-btn:active {
            transform: scale(0.95);
        }

        /* Botón de Tema Floating */
        .theme-btn {
            position: fixed;
            top: 20px;
            right: 76px;
            background: var(--card-background);
            border: 1px solid rgba(14, 84, 76, 0.15);
            color: var(--primary-color);
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
            z-index: 1000;
        }

        .theme-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(14, 84, 76, 0.18);
        }

        .theme-btn:active {
            transform: scale(0.95);
        }

        body.dark-mode .theme-btn {
            color: var(--secondary-color);
            border-color: rgba(81, 184, 172, 0.2);
        }
        
        body.dark-mode .theme-btn:hover {
            background: var(--secondary-color);
            color: #0e201d;
        }

        body.dark-mode .fullscreen-btn {
            color: var(--secondary-color);
            border-color: rgba(81, 184, 172, 0.2);
        }

        body.dark-mode .fullscreen-btn:hover {
            background: var(--secondary-color);
            color: #0e201d;
        }

        /* Indicador de Conexión */
        .connection-status-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            margin-left: 10px;
            vertical-align: middle;
            transition: all 0.3s ease;
        }

        .connection-status-dot.online {
            background-color: var(--success-color);
            box-shadow: 0 0 8px var(--success-color);
            animation: pulse-dot-green 2s infinite;
        }

        .connection-status-dot.offline {
            background-color: var(--danger-color);
            box-shadow: 0 0 8px var(--danger-color);
            animation: pulse-dot-red 2s infinite;
        }

        @keyframes pulse-dot-green {
            0% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(46, 204, 113, 0); }
            100% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); }
        }

        @keyframes pulse-dot-red {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }

        /* Separadores pulsantes del reloj */
        .clock-colon {
            animation: pulse-colon 1s infinite;
            display: inline-block;
            transition: opacity 0.2s;
        }

        @keyframes pulse-colon {
            0%, 100% { opacity: 0.2; }
            50% { opacity: 1; }
        }

        /* Password Input Display */
        .password-display-wrapper {
            position: relative;
            max-width: 500px;
            margin: 0 auto 20px;
        }

        .password-display {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e2ebd5;
            background: #fdfdfd;
            border-radius: 12px;
            font-size: 1.8rem;
            letter-spacing: 6px;
            text-align: center;
            font-weight: 700;
            color: var(--primary-color);
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.02);
            outline: none;
            transition: all 0.3s;
        }

        .password-display:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(81, 184, 172, 0.2);
        }

        .toggle-visibility {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.2rem;
            transition: color 0.2s;
        }

        .toggle-visibility:hover {
            color: var(--primary-color);
        }

        .matched-user {
            text-align: center;
            height: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        /* On-Screen Keyboard */
        .keyboard {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 0 auto;
            max-width: 600px;
            background: rgba(14, 84, 76, 0.04);
            padding: 12px;
            border-radius: 16px;
            border: 1px solid rgba(14, 84, 76, 0.06);
        }

        .keyboard-row {
            display: flex;
            justify-content: center;
            gap: 6px;
            width: 100%;
        }

        .key {
            background: white;
            border: none;
            border-radius: 8px;
            height: 48px;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            cursor: pointer;
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            transition: all 0.15s cubic-bezier(0.2, 0.8, 0.2, 1);
            user-select: none;
        }

        .key:hover {
            background: #f4faf8;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(14, 84, 76, 0.1);
        }

        .key:active {
            background: #dbefe9;
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(14, 84, 76, 0.08);
        }

        .key.wide {
            flex: 1.5;
            background: #f3f6f6;
        }

        .key.shift.active {
            background: var(--primary-color);
            color: white;
        }

        .key.action-clear {
            background: #f9ebea;
            color: var(--danger-color);
        }

        .key.action-clear:hover {
            background: var(--danger-color);
            color: white;
        }

        .btn-marcar {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 14px;
            width: 100%;
            max-width: 500px;
            margin: 20px auto 0;
            display: block;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: var(--shadow-btn);
            transition: all 0.3s;
        }

        .btn-marcar:hover {
            background: var(--primary-color);
            transform: translateY(-1px);
        }

        .btn-marcar.entrada {
            background: var(--success-color);
        }

        .btn-marcar.salida {
            background: #e67e22;
            /* Color naranja para salida */
        }

        .btn-marcar:disabled {
            background: var(--text-muted);
            cursor: not-allowed;
            transform: none;
        }

        /* Sidebar: Colaboradores en turno */
        .sidebar {
            flex: 1;
            width: 100%;
            background: var(--card-background);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-light);
            border: 1px solid rgba(255, 255, 255, 0.6);
            align-self: stretch;
            max-height: 600px;
            display: flex;
            flex-direction: column;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.1s both;
        }

        .sidebar h2 {
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .count-badge {
            background: var(--secondary-color);
            color: white;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 50px;
        }

        .lista-turno {
            flex: 1;
            overflow-y: auto;
            padding-right: 5px;
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
            background: white;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 12px;
            border-left: 4px solid var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
            transition: all 0.2s;
        }

        .item-turno:hover {
            transform: translateX(3px);
            box-shadow: 0 4px 10px rgba(14, 84, 76, 0.06);
        }

        .colaborador-info {
            display: flex;
            align-items: center;
        }

        .avatar-colaborador {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 12px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--secondary-color);
        }

        .avatar-colaborador img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .colaborador-nombre {
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.95rem;
        }

        .entrada-hora {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .status-badge {
            font-size: 0.75rem;
            color: var(--success-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Error/Flash Alert */
        .error-alert {
            background: #fdf2f2;
            color: #ec7063;
            border: 1px solid #fadbd8;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.05);
            animation: shake 0.5s ease;
        }

        .error-alert i {
            font-size: 1.3rem;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(14, 84, 76, 0.4);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s;
        }

        .modal-content {
            background: white;
            padding: 35px;
            border-radius: 20px;
            text-align: center;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 50px rgba(14, 84, 76, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            animation: zoomIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .modal-icon {
            font-size: 3.5rem;
            color: var(--success-color);
            margin-bottom: 15px;
        }

        .modal-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .modal-mensaje {
            font-size: 1.1rem;
            margin-bottom: 25px;
            color: var(--text-main);
            line-height: 1.5;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }

        .info-operario {
            background: rgba(81, 184, 172, 0.08);
            padding: 15px;
            border-radius: 12px;
            margin-top: 15px;
            border-left: 4px solid var(--secondary-color);
            text-align: left;
        }

        .info-operario p {
            margin: 6px 0;
            color: var(--primary-color);
            font-size: 0.95rem;
        }

        .btn-aceptar {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-aceptar:hover {
            background: var(--primary-color);
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes zoomIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        @keyframes pulse-dot {
            0% {
                transform: scale(0.95);
                opacity: 0.5;
            }

            50% {
                transform: scale(1);
                opacity: 1;
            }

            100% {
                transform: scale(0.95);
                opacity: 0.5;
            }
        }

        /* Animaciones para cumpleaños y aniversarios en marcaciones */
        @keyframes pulseAniversario {
            0% { transform: scale(1); }
            50% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }

        @keyframes confetiFall {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.85;
            }
            100% {
                transform: translateY(105vh) rotate(360deg);
                opacity: 0;
            }
        }

        @keyframes estrellaFall {
            0% {
                transform: translateY(0) rotate(0deg) scale(0.8);
                opacity: 0.85;
            }
            50% {
                transform: translateY(50vh) rotate(180deg) scale(1.2);
                opacity: 0.9;
            }
            100% {
                transform: translateY(105vh) rotate(360deg) scale(0.8);
                opacity: 0;
            }
        }

        /* ── Offline Banner ───────────────────────── */
        .offline-banner {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 9000;
            padding: 10px 20px;
            text-align: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .offline-banner.mode-offline      { background: linear-gradient(90deg,#e67e22,#d35400); color:#fff; display:block; }
        .offline-banner.mode-syncing      { background: linear-gradient(90deg,#2980b9,#1a6a99); color:#fff; display:block; }
        .offline-banner.mode-sync-ok      { background: linear-gradient(90deg,#27ae60,#1e8449); color:#fff; display:block; }
        .offline-banner.mode-token-expired{ background: linear-gradient(90deg,#c0392b,#922b21); color:#fff; display:block; }
        .offline-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(0,0,0,0.15); border-radius: 50px;
            padding: 2px 12px; margin-left: 10px; font-size: 0.85rem;
        }
    </style>
</head>

<body>

    <?php if (!$dispositivoAutorizado): ?>
        <div class="unauthorized-card">
            <div class="lock-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h2>Dispositivo No Autorizado</h2>
            <p><?= htmlspecialchars($validacion['msg']) ?></p>
            <a href="marcacion_express.php" class="btn-reload">
                <i class="fas fa-sync-alt"></i> Reintentar
            </a>
        </div>
    <?php else: ?>

        <!-- Banner Offline (oculto por defecto, JS lo muestra/oculta) -->
        <div id="offlineBanner" class="offline-banner" style="display:none;"></div>

        <!-- Botón Cambiar Tema (Claro/Oscuro) -->
        <button type="button" class="theme-btn" id="btnTheme" title="Cambiar tema">
            <i class="fas fa-moon"></i>
        </button>

        <!-- Botón Pantalla Completa -->
        <button type="button" class="fullscreen-btn" id="btnFullscreen" title="Pantalla completa">
            <i class="fas fa-expand"></i>
        </button>

        <div class="workspace">
            <div class="container">
                <img src="../../core/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">

                <div class="header-info">
                    <div class="sucursal-badge">
                        <i class="fas fa-store"></i> <?= htmlspecialchars($sucursalNombre) ?>
                        <span class="connection-status-dot" id="connectionStatusDot" title="Estado de la conexión"></span>
                    </div>
                    <h1 style="font-size: 1.6rem; margin-bottom: 15px;">Marcación</h1>
                    <div class="clock-widget">
                        <div class="clock-display">
                            <span class="clock-time" id="clockTime">00<span class="clock-colon">:</span>00<span class="clock-colon">:</span>00</span>
                            <span class="clock-ampm" id="clockAmPm">--</span>
                        </div>
                        <div class="clock-date" id="clockDate">Cargando fecha...</div>
                    </div>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="error-alert" id="errorMessage">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div><?= nl2br(htmlspecialchars($_SESSION['error'])) ?></div>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form id="formMarcacion" method="POST" autocomplete="off">
                    <div class="password-display-wrapper">
                        <input type="password" id="clave" name="clave" class="password-display"
                            readonly required inputmode="none">
                        <button type="button" class="toggle-visibility" id="btnToggleVis" title="Mostrar/Ocultar">
                            <i class="fas fa-eye-slash" id="eyeIcon"></i>
                        </button>
                    </div>

                    <div class="matched-user" id="matchedUser">
                        <!-- Nombre del colaborador detectado -->
                    </div>

                    <!-- Teclado en Pantalla -->
                    <div class="keyboard" id="virtualKeyboard"></div>

                    <button type="submit" class="btn-marcar" id="btnMarcar" disabled>
                        Marcar Entrada/Salida
                    </button>
                </form>
            </div>

            <!-- Panel Lateral de Colaboradores en Turno -->
            <div class="sidebar">
                <h2>
                    <span><i class="fas fa-users"></i> En Turno</span>
                    <span class="count-badge"><?= $totalEnTurno ?> activos</span>
                </h2>

                <div class="lista-turno">
                    <?php if ($totalEnTurno > 0): ?>
                        <?php foreach ($colaboradoresEnTurno as $colaborador):
                            $nombreCompleto = obtenerNombreCompletoOperario($colaborador);
                            $horaEntrada = formatoHoraAmigable($colaborador['hora_entrada_formateada']);
                        ?>
                            <div class="item-turno">
                                <div class="colaborador-info">
                                    <div class="avatar-colaborador">
                                        <?php if (!empty($colaborador['foto_perfil']) && file_exists($colaborador['foto_perfil'])): ?>
                                            <img src="<?= htmlspecialchars($colaborador['foto_perfil']) ?>" alt="Foto">
                                        <?php else: ?>
                                            <i class="fas fa-user" style="color: #ccc; font-size: 18px;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="colaborador-nombre"><?= htmlspecialchars($nombreCompleto) ?></div>
                                        <div class="entrada-hora">
                                            <i class="fas fa-sign-in-alt"></i> Entrada: <?= $horaEntrada ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="status-badge">
                                    <i class="fas fa-circle" style="font-size: 8px; animation: pulse-dot 2s infinite;"></i>
                                    Activo
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                            <i class="fas fa-user-clock" style="font-size: 2.2rem; margin-bottom: 12px; display: block; color: rgba(14, 84, 76, 0.2);"></i>
                            <p style="font-size: 0.95rem;">No hay colaboradores en turno actualmente</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Modal de Confirmación -->
        <div class="modal" id="modalConfirmacion">
            <div class="modal-content">
                <button class="modal-close" id="btnCerrarModal">&times;</button>
                <div class="modal-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="modal-title">¡Registro Exitoso!</div>
                <div class="modal-mensaje" id="modalMensaje">
                    <!-- Mensaje dinámico -->
                </div>
                <div class="info-operario" id="modalInfoOperario">
                    <!-- Información del operario -->
                </div>
                <button class="btn-aceptar" id="btnAceptar">Aceptar</button>
            </div>
        </div>

        <!-- Modal de Pre-Confirmación por marcación reciente -->
        <div class="modal" id="modalPreConfirmacion" style="display: none;">
            <div class="modal-content" style="border-top: 5px solid #e67e22; max-width: 450px; position: relative;">
                <button class="modal-close" id="btnCerrarPreConfirmacion">&times;</button>
                <div class="modal-icon" style="color: #e67e22; font-size: 3.5rem; margin-bottom: 15px;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="modal-title" style="color: #d35400; font-size: 1.5rem; font-weight: 700; margin-bottom: 15px;">
                    ¡Marcación Reciente Detectada!
                </div>
                <div class="modal-mensaje" id="preConfirmacionMensaje" style="font-size: 1.1rem; line-height: 1.6; margin-bottom: 20px; color: var(--text-main); text-align: center;">
                    <!-- Mensaje dinámico -->
                </div>
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px; width: 100%;">
                    <button class="btn-aceptar" id="btnPreConfirmarAceptar" style="background: #e67e22; margin-top: 0; flex: 1; min-width: 120px;" disabled>Confirmar (3s)</button>
                    <button class="btn-aceptar" id="btnPreConfirmarCancelar" style="background: #7f8c8d; margin-top: 0; flex: 1; min-width: 120px;">Cancelar</button>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['marcacion_pendiente'])): ?>
            <form id="formConfirmarPendiente" method="POST" style="display:none;">
                <input type="hidden" name="clave" value="<?= htmlspecialchars($_SESSION['marcacion_pendiente']['clave']) ?>">
                <input type="hidden" name="confirmado" value="1">
            </form>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const modal = document.getElementById('modalPreConfirmacion');
                    const mensaje = document.getElementById('preConfirmacionMensaje');
                    const btnAceptar = document.getElementById('btnPreConfirmarAceptar');
                    const btnCancelar = document.getElementById('btnPreConfirmarCancelar');
                    const btnCerrar = document.getElementById('btnCerrarPreConfirmacion');

                    const pendienteInfo = <?= json_encode($_SESSION['marcacion_pendiente']) ?>;
                    const nombre = pendienteInfo.nombre;

                    if (pendienteInfo.caso === 'nueva_entrada_hoy') {
                        document.querySelector('#modalPreConfirmacion .modal-title').textContent = '¡Ya tienes jornada registrada hoy!';
                        mensaje.innerHTML = `<strong>¡Atención, ${nombre}!</strong><br><br>` +
                            `Ya tienes una jornada completa registrada hoy:<br>` +
                            `<strong style="color:var(--primary-color);">Entrada:</strong> ${pendienteInfo.reciente_hora} &nbsp;|&nbsp; ` +
                            `<strong style="color:#e67e22;">Salida:</strong> ${pendienteInfo.reciente_hora_sal}<br><br>` +
                            `¿Deseas registrar una <strong style="color: #e67e22;">NUEVA ENTRADA</strong> para hoy?`;
                    } else {
                        mensaje.innerHTML = `<strong>¡Atención, ${nombre}!</strong><br><br>` +
                            `Registraste tu <strong style="color:var(--primary-color);">ENTRADA</strong> a las <strong>${pendienteInfo.reciente_hora}</strong>.<br><br>` +
                            `¿Estás seguro de que deseas registrar tu <strong style="color: #e67e22;">SALIDA</strong> ahora?`;
                    }

                    modal.style.display = 'flex';

                    let countdown = 3;
                    btnAceptar.disabled = true;
                    btnAceptar.textContent = `Confirmar (${countdown}s)`;
                    btnAceptar.style.opacity = '0.6';
                    btnAceptar.style.cursor = 'not-allowed';

                    const interval = setInterval(() => {
                        countdown--;
                        if (countdown > 0) {
                            btnAceptar.textContent = `Confirmar (${countdown}s)`;
                        } else {
                            clearInterval(interval);
                            btnAceptar.disabled = false;
                            btnAceptar.style.opacity = '1';
                            btnAceptar.style.cursor = 'pointer';
                            btnAceptar.textContent = 'Confirmar';
                        }
                    }, 1000);

                    btnAceptar.addEventListener('click', function() {
                        modal.style.display = 'none';
                        document.getElementById('formConfirmarPendiente').submit();
                    });

                    const closeAction = () => {
                        clearInterval(interval);
                        modal.style.display = 'none';
                        // Limpiar campos del formulario original
                        const keyInput = document.getElementById('clave');
                        if (keyInput) keyInput.value = '';
                        // Resetear botón de marcación original y matchedUser
                        const btnMarcar = document.getElementById('btnMarcar');
                        if (btnMarcar) {
                            btnMarcar.textContent = 'Marcar Entrada/Salida';
                            btnMarcar.className = 'btn-marcar';
                            btnMarcar.disabled = true;
                        }
                        const matchedUser = document.getElementById('matchedUser');
                        if (matchedUser) matchedUser.style.opacity = '0';
                    };

                    btnCancelar.addEventListener('click', closeAction);
                    btnCerrar.addEventListener('click', closeAction);

                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            closeAction();
                        }
                    });
                });
            </script>
            <?php unset($_SESSION['marcacion_pendiente']); ?>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Reloj en tiempo real rediseñado con separadores pulsantes
                function updateClock() {
                    const timeEl = document.getElementById('clockTime');
                    const ampmEl = document.getElementById('clockAmPm');
                    const dateEl = document.getElementById('clockDate');
                    if (!timeEl || !ampmEl || !dateEl) return;

                    const now = new Date();
                    const optionsDate = {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    };
                    let dateStr = now.toLocaleDateString('es-ES', optionsDate);
                    dateStr = dateStr.charAt(0).toUpperCase() + dateStr.slice(1);

                    let hours = now.getHours();
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const seconds = String(now.getSeconds()).padStart(2, '0');
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12; // 0 deberia ser 12

                    timeEl.innerHTML = `${hours}<span class="clock-colon">:</span>${minutes}<span class="clock-colon">:</span>${seconds}`;
                    ampmEl.textContent = ampm;
                    dateEl.textContent = dateStr;
                }
                updateClock();
                setInterval(updateClock, 1000);

                // Control de Pantalla Completa (Fullscreen)
                const fullscreenBtn = document.getElementById('btnFullscreen');
                if (fullscreenBtn) {
                    fullscreenBtn.addEventListener('click', () => {
                        if (!document.fullscreenElement) {
                            document.documentElement.requestFullscreen().catch(err => {
                                console.error(`Error al intentar pantalla completa: ${err.message}`);
                            });
                        } else {
                            document.exitFullscreen();
                        }
                    });

                    document.addEventListener('fullscreenchange', () => {
                        const icon = fullscreenBtn.querySelector('i');
                        if (document.fullscreenElement) {
                            if (icon) {
                                icon.className = 'fas fa-compress';
                            }
                            fullscreenBtn.setAttribute('title', 'Salir de pantalla completa');
                        } else {
                            if (icon) {
                                icon.className = 'fas fa-expand';
                            }
                            fullscreenBtn.setAttribute('title', 'Pantalla completa');
                        }
                    });
                }

                // Control de Tema (Modo Oscuro / Claro)
                const themeBtn = document.getElementById('btnTheme');
                
                function getSystemThemePreference() {
                    const now = new Date();
                    const hour = now.getHours();
                    // Auto dark mode: antes de 7:00 AM o después de 6:00 PM (18:00)
                    return (hour < 7 || hour >= 18) ? 'dark' : 'light';
                }

                function applyTheme(theme) {
                    if (!themeBtn) return;
                    const icon = themeBtn.querySelector('i');
                    if (theme === 'dark') {
                        document.body.classList.add('dark-mode');
                        if (icon) icon.className = 'fas fa-sun';
                        themeBtn.setAttribute('title', 'Cambiar a modo claro');
                    } else {
                        document.body.classList.remove('dark-mode');
                        if (icon) icon.className = 'fas fa-moon';
                        themeBtn.setAttribute('title', 'Cambiar a modo oscuro');
                    }
                }

                // Cargar tema inicial (preferencia o automático)
                let savedTheme = localStorage.getItem('pitaya_theme');
                if (!savedTheme) {
                    savedTheme = getSystemThemePreference();
                }
                applyTheme(savedTheme);

                if (themeBtn) {
                    themeBtn.addEventListener('click', () => {
                        const isDark = document.body.classList.contains('dark-mode');
                        const newTheme = isDark ? 'light' : 'dark';
                        localStorage.setItem('pitaya_theme', newTheme);
                        applyTheme(newTheme);
                    });
                }

                // Indicador de Estado de Conexión (Online/Offline)
                function updateConnectionStatus() {
                    const dot = document.getElementById('connectionStatusDot');
                    if (!dot) return;
                    if (navigator.onLine) {
                        dot.className = 'connection-status-dot online';
                        dot.setAttribute('title', 'Conectado al servidor (Online)');
                    } else {
                        dot.className = 'connection-status-dot offline';
                        dot.setAttribute('title', 'Sin conexión (Trabajando localmente)');
                    }
                }
                updateConnectionStatus();
                window.addEventListener('online', updateConnectionStatus);
                window.addEventListener('offline', updateConnectionStatus);

                // Ocultar mensaje de error tras 8 segundos
                const errorAlert = document.getElementById('errorMessage');
                if (errorAlert) {
                    setTimeout(() => {
                        errorAlert.style.transition = 'opacity 0.8s, transform 0.8s';
                        errorAlert.style.opacity = '0';
                        errorAlert.style.transform = 'translateY(-10px)';
                        setTimeout(() => errorAlert.remove(), 800);
                    }, 8000);
                }

                // Elementos del formulario y teclado
                const passwordInput = document.getElementById('clave');
                const toggleVisBtn = document.getElementById('btnToggleVis');
                const eyeIcon = document.getElementById('eyeIcon');
                const matchedUserDiv = document.getElementById('matchedUser');
                const btnMarcar = document.getElementById('btnMarcar');
                const keyboardContainer = document.getElementById('virtualKeyboard');

                // Toggle visibilidad contraseña
                toggleVisBtn.addEventListener('click', () => {
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        eyeIcon.className = 'fas fa-eye';
                    } else {
                        passwordInput.type = 'password';
                        eyeIcon.className = 'fas fa-eye-slash';
                    }
                });

                // Lógica del teclado en pantalla
                let isUppercase = false;

                const keysLayout = [
                    ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
                    ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
                    ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'ñ'],
                    ['Shift', 'z', 'x', 'c', 'v', 'b', 'n', 'm', ',', '.', 'Backspace'],
                    ['Limpiar', '@', '-', '_', 'Space', 'Marcar']
                ];

                function renderKeyboard() {
                    keyboardContainer.innerHTML = '';
                    keysLayout.forEach(row => {
                        const rowDiv = document.createElement('div');
                        rowDiv.className = 'keyboard-row';

                        row.forEach(keyChar => {
                            const keyBtn = document.createElement('button');
                            keyBtn.type = 'button';
                            keyBtn.className = 'key';

                            // Estilizados y layouts especiales
                            if (keyChar === 'Shift') {
                                keyBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
                                keyBtn.classList.add('wide', 'shift');
                                if (isUppercase) keyBtn.classList.add('active');
                            } else if (keyChar === 'Backspace') {
                                keyBtn.innerHTML = '<i class="fas fa-backspace"></i>';
                                keyBtn.classList.add('wide');
                            } else if (keyChar === 'Space') {
                                keyBtn.textContent = 'Espacio';
                                keyBtn.classList.add('wide');
                            } else if (keyChar === 'Limpiar') {
                                keyBtn.textContent = 'Limpiar';
                                keyBtn.classList.add('action-clear');
                            } else if (keyChar === 'Marcar') {
                                keyBtn.textContent = '✓';
                                keyBtn.classList.add('wide', 'action-confirm');
                            } else {
                                keyBtn.textContent = isUppercase ? keyChar.toUpperCase() : keyChar.toLowerCase();
                            }

                            // Click Handler
                            keyBtn.addEventListener('click', () => {
                                playKeySound();
                                handleKeyPress(keyChar);
                            });

                            rowDiv.appendChild(keyBtn);
                        });
                        keyboardContainer.appendChild(rowDiv);
                    });
                }

                // Efecto táctil sutil de sonido o vibración
                function playKeySound() {
                    if (navigator.vibrate) {
                        navigator.vibrate(15);
                    }
                }

                function handleKeyPress(key) {
                    if (key === 'Shift') {
                        isUppercase = !isUppercase;
                        renderKeyboard();
                    } else if (key === 'Backspace') {
                        passwordInput.value = passwordInput.value.slice(0, -1);
                        handlePasswordChange();
                    } else if (key === 'Limpiar') {
                        passwordInput.value = '';
                        handlePasswordChange();
                    } else if (key === 'Space') {
                        passwordInput.value += ' ';
                        handlePasswordChange();
                    } else if (key === 'Marcar') {
                        if (!btnMarcar.disabled) {
                            const form = document.getElementById('formMarcacion');
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                            } else {
                                const event = new Event('submit', { cancelable: true, bubbles: true });
                                if (form.dispatchEvent(event)) {
                                    form.submit();
                                }
                            }
                        }
                    } else {
                        const finalChar = isUppercase ? key.toUpperCase() : key.toLowerCase();
                        passwordInput.value += finalChar;
                        handlePasswordChange();
                    }
                }

                // Debounce e Identificación en tiempo real del operario
                let checkTimeout;

                function handlePasswordChange() {
                    clearTimeout(checkTimeout);
                    const val = passwordInput.value;

                    if (val.length < 3) {
                        matchedUserDiv.style.opacity = '0';
                        btnMarcar.textContent = 'Marcar Entrada/Salida';
                        btnMarcar.className = 'btn-marcar';
                        btnMarcar.disabled = true;
                        return;
                    }

                    checkTimeout = setTimeout(async () => {
                        if (navigator.onLine) {
                            // ── Online: verificar en servidor ─────────────
                            const formData = new FormData();
                            formData.append('clave', val);
                            try {
                                const res  = await fetch('ajax/marcacion_express_verificar.php', { method: 'POST', body: formData });
                                const data = await res.json();
                                if (data.success) {
                                    matchedUserDiv.innerHTML = `<i class="fas fa-user-check" style="margin-right:6px;color:var(--success-color);"></i> ${data.nombre}`;
                                    matchedUserDiv.style.opacity = '1';
                                    btnMarcar.textContent = data.tipoMarcacion === 'salida' ? `Marcar Salida (${data.nombre})` : `Marcar Entrada (${data.nombre})`;
                                    btnMarcar.className   = 'btn-marcar ' + (data.tipoMarcacion === 'salida' ? 'salida' : 'entrada');
                                    btnMarcar.disabled    = false;
                                    btnMarcar.dataset.offlineNombre      = '';
                                    btnMarcar.dataset.offlineCodOperario = '';
                                } else {
                                    matchedUserDiv.style.opacity = '0';
                                    btnMarcar.textContent = 'Marcar Entrada/Salida';
                                    btnMarcar.className   = 'btn-marcar';
                                    btnMarcar.disabled    = true;
                                }
                            } catch (err) {
                                console.error('Error verificando:', err);
                            }
                        } else {
                            // ── Offline: validar localmente con bcrypt ────
                            if (!window.PitayaOffline) return;
                            const result = await PitayaOffline.checkPasswordOffline(val);
                            if (result.found) {
                                matchedUserDiv.innerHTML = `<i class="fas fa-user-check" style="margin-right:6px;color:var(--success-color);"></i> ${result.nombre} <span style="font-size:0.75rem;color:#e67e22;">(offline)</span>`;
                                matchedUserDiv.style.opacity = '1';
                                btnMarcar.textContent = `Marcar Offline (${result.nombre})`;
                                btnMarcar.className   = 'btn-marcar entrada';
                                btnMarcar.disabled    = false;
                                // Guardar datos para el submit offline
                                btnMarcar.dataset.offlineNombre      = result.nombre;
                                btnMarcar.dataset.offlineCodOperario = result.CodOperario;
                            } else {
                                matchedUserDiv.style.opacity = '0';
                                btnMarcar.textContent = 'Marcar Entrada/Salida';
                                btnMarcar.className   = 'btn-marcar';
                                btnMarcar.disabled    = true;
                            }
                        }
                    }, navigator.onLine ? 250 : 450);
                }

                renderKeyboard();

                // Evitar envío por ENTER del teclado físico
                document.getElementById('formMarcacion').addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        return false;
                    }
                });

                // Manejo de confirmación de envío (online/offline)
                let formSubmitted = false;
                document.getElementById('formMarcacion').addEventListener('submit', async function(e) {
                    if (formSubmitted) { e.preventDefault(); return false; }

                    if (!navigator.onLine && window.PitayaOffline) {
                        // ── MODO OFFLINE: guardar en cola ─────────────────
                        e.preventDefault();
                        const nombre      = btnMarcar.dataset.offlineNombre      || '?';
                        const codOperario = parseInt(btnMarcar.dataset.offlineCodOperario) || 0;
                        const clave       = passwordInput.value;
                        if (!codOperario || !clave) return;

                        formSubmitted = true;
                        btnMarcar.disabled = true;
                        btnMarcar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando offline...';

                        const item = await PitayaOffline.enqueue(clave, codOperario, nombre);

                        // Mostrar confirmación offline
                        const modal   = document.getElementById('modalConfirmacion');
                        const msgEl   = document.getElementById('modalMensaje');
                        const infoEl  = document.getElementById('modalInfoOperario');
                        const now     = new Date();
                        const hora    = now.toLocaleTimeString('es', {hour:'2-digit',minute:'2-digit'});
                        if (modal && msgEl && infoEl) {
                            document.querySelector('#modalConfirmacion .modal-icon i').className = 'fas fa-clock';
                            document.querySelector('#modalConfirmacion .modal-icon').style.color = '#e67e22';
                            document.querySelector('#modalConfirmacion .modal-title').textContent = 'Marcación Guardada Offline';
                            msgEl.innerHTML = `<span style="font-weight:700;font-size:1.2rem;color:#e67e22;">GUARDADA OFFLINE</span><br>a las <strong>${hora}</strong><br><br><span style="color:#888;font-size:0.9rem;">Se sincronizará automáticamente al recuperar conexión.</span>`;
                            infoEl.innerHTML = `<p><strong>Colaborador:</strong> ${nombre}</p><p><strong>Estado:</strong> En cola offline</p>`;
                            modal.style.display = 'flex';
                            setTimeout(() => {
                                modal.style.display = 'none';
                                passwordInput.value = '';
                                btnMarcar.textContent = 'Marcar Entrada/Salida';
                                btnMarcar.className   = 'btn-marcar';
                                btnMarcar.disabled    = true;
                                matchedUserDiv.style.opacity = '0';
                                formSubmitted = false;
                            }, 5000);
                        }
                        return;
                    }

                    // ── MODO ONLINE: envío normal ─────────────────────────
                    formSubmitted = true;
                    btnMarcar.disabled = true;
                    btnMarcar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando marcación...';
                });
                // Inicializar módulo offline
                if (window.PitayaOffline) PitayaOffline.init();
            });
        </script>

        <!-- Modal de confirmación tras marcación -->
        <?php if (isset($_SESSION['marcacion_mensaje'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const modal = document.getElementById('modalConfirmacion');
                    const mensaje = document.getElementById('modalMensaje');
                    const infoOperario = document.getElementById('modalInfoOperario');

                    const marcacionInfo = <?= json_encode($_SESSION['marcacion_mensaje']) ?>;

                    let tipoTexto = marcacionInfo.tipo === 'entrada' ? 'ENTRADA' : 'SALIDA';
                    let statusColor = marcacionInfo.tipo === 'entrada' ? '#2ecc71' : '#e67e22';

                    let mensajeHTML = `<span style="font-weight: 700; font-size: 1.3rem; color: ${statusColor};">` +
                        `${tipoTexto} REGISTRADA</span><br>` +
                        `a las <strong style="font-size: 1.2rem; color: var(--primary-color);">${marcacionInfo.hora}</strong>`;

                    // Banner de cumpleaños / aniversario (corto y elegante)
                    if (marcacionInfo.es_cumpleanos) {
                        mensajeHTML += `<div style="margin-top: 12px; background: linear-gradient(135deg, #FF6B6B, #FF8E53); color: white; padding: 10px; border-radius: 10px; font-weight: bold; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 10px rgba(255, 107, 107, 0.25); animation: pulseAniversario 2s infinite;">` +
                            `🎉 ¡Feliz Cumpleaños! 🎂🎈</div>`;
                        setTimeout(lanzarConfetiModal, 300);
                    }
                    if (marcacionInfo.es_aniversario) {
                        const aniosTexto = marcacionInfo.anios_aniversario === 1 ? '1 año' : `${marcacionInfo.anios_aniversario} años`;
                        mensajeHTML += `<div style="margin-top: 12px; background: linear-gradient(135deg, #7B2CBF, #9D4EDD); color: white; padding: 10px; border-radius: 10px; font-weight: bold; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 10px rgba(123, 44, 191, 0.25); animation: pulseAniversario 2s infinite;">` +
                            `🏆 ¡Feliz Aniversario de ${aniosTexto}! ✨🎖️</div>`;
                        setTimeout(lanzarEstrellasModal, 300);
                    }

                    // Tardanza o minuto de gracia
                    if (marcacionInfo.sucursal_codigo == 6 || marcacionInfo.sucursal_codigo == 18) {
                        if (marcacionInfo.tipo === 'entrada') {
                            if (marcacionInfo.hora >= '13:00' && marcacionInfo.hora <= '13:30') {
                                mensajeHTML += `<br><span style="color: #17a2b8; font-weight: bold;">¡Regreso de almuerzo!</span>`;
                            } else if (marcacionInfo.en_minuto_gracia) {
                                mensajeHTML += `<br><span style="color: #e67e22; font-weight: bold;">¡Por poco!</span><br>Tolerancia de gracia utilizada.`;
                            } else if (marcacionInfo.tardanza_entrada) {
                                mensajeHTML += `<br><span style="color: var(--danger-color); font-weight: bold;">¡Llegada Tarde!</span><br>Hora de entrada: 7:00 AM`;
                            } else {
                                mensajeHTML += `<br><span style="color: var(--success-color); font-weight: bold;">¡Excelente puntualidad!</span>`;
                            }
                        }
                    } else {
                        if (marcacionInfo.omision_dia_anterior) {
                            mensajeHTML += `<br><span style="color: var(--warning-color); font-weight: bold;">¡Atención!</span><br>Omisión detectada el día de ayer.`;
                        } else if (marcacionInfo.solo_salida) {
                            mensajeHTML += `<br><span style="color: #17a2b8; font-weight: bold;">Salida Especial</span><br>Registrado en rango previo de 30 min.`;
                        } else if (marcacionInfo.tipo === 'entrada' && marcacionInfo.tardanza_entrada) {
                            mensajeHTML += `<br><span style="color: var(--danger-color); font-weight: bold;">Llegada Tarde</span>`;
                        } else if (marcacionInfo.tipo === 'entrada') {
                            mensajeHTML += `<br><span style="color: var(--success-color); font-weight: bold;">Puntual</span>`;
                        }
                    }

                    // Resumen mensual
                    mensajeHTML += `<br><br><div style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px; font-size: 0.9rem;">` +
                        `<strong>Resumen de Asistencia (Mes Actual):</strong><br>` +
                        `• Tardanzas: ${marcacionInfo.tardanzas_ejecutadas ?? 0}<br>` +
                        `• Omisiones: ${marcacionInfo.omisiones_mes ?? 0}<br>` +
                        `• Faltas: ${marcacionInfo.faltas_ejecutadas ?? 0}</div>`;

                    mensaje.innerHTML = mensajeHTML;

                    infoOperario.innerHTML = `
                        <p><strong>Colaborador:</strong> ${marcacionInfo.nombre}</p>
                        <p><strong>Puesto:</strong> ${marcacionInfo.cargo || 'Sin cargo'}</p>
                        <p><strong>Sucursal:</strong> ${marcacionInfo.sucursal || 'Sin sucursal'}</p>
                    `;

                    // Funciones para efectos en modal
                    function lanzarConfetiModal() {
                        const emojis = ['🎉', '🎊', '🎂', '🥳', '✨', '🎈'];
                        for (let i = 0; i < 20; i++) {
                            setTimeout(() => {
                                const p = document.createElement('div');
                                p.textContent = emojis[Math.floor(Math.random() * emojis.length)];
                                p.style.position = 'fixed';
                                p.style.fontSize = '1.8rem';
                                p.style.opacity = '0.85';
                                p.style.zIndex = '9999';
                                p.style.left = (10 + Math.random() * 80) + 'vw';
                                p.style.top = '-40px';
                                p.style.pointerEvents = 'none';
                                p.style.animation = `confetiFall ${2.5 + Math.random() * 2}s linear forwards`;
                                document.body.appendChild(p);
                                setTimeout(() => p.remove(), 4500);
                            }, i * 150);
                        }
                    }

                    function lanzarEstrellasModal() {
                        const emojis = ['🏆', '✨', '🎖️', '👏', '⭐', '🌟'];
                        for (let i = 0; i < 20; i++) {
                            setTimeout(() => {
                                const p = document.createElement('div');
                                p.textContent = emojis[Math.floor(Math.random() * emojis.length)];
                                p.style.position = 'fixed';
                                p.style.fontSize = '1.8rem';
                                p.style.opacity = '0.85';
                                p.style.zIndex = '9999';
                                p.style.left = (10 + Math.random() * 80) + 'vw';
                                p.style.top = '-40px';
                                p.style.pointerEvents = 'none';
                                p.style.animation = `estrellaFall ${2.5 + Math.random() * 2}s linear forwards`;
                                document.body.appendChild(p);
                                setTimeout(() => p.remove(), 4500);
                            }, i * 150);
                        }
                    }

                    modal.style.display = 'flex';

                    // Cerrar el modal recarga limpia la página
                    const closeActions = () => {
                        modal.style.display = 'none';
                        window.location.href = 'marcacion_express.php';
                    };

                    document.getElementById('btnAceptar').addEventListener('click', closeActions);
                    document.getElementById('btnCerrarModal').addEventListener('click', closeActions);

                    // Auto-cerrar tras 8 segundos para que quede disponible para el siguiente
                    setTimeout(closeActions, 8000);
                });
            </script>

            <!-- Captura DVR silenciosa -->
            <script>
                (function() {
                    var _dvrPendiente = <?= json_encode($_SESSION['dvr_captura_pendiente'] ?? null) ?>;
                    if (_dvrPendiente && _dvrPendiente.id_marcacion > 0) {
                        try {
                            fetch('/modulos/sucursales/ajax/dvr_capturar_marcacion.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    id_marcacion: _dvrPendiente.id_marcacion,
                                    tipo: _dvrPendiente.tipo,
                                    cod_sucursal: _dvrPendiente.cod_sucursal
                                }),
                                keepalive: true
                            }).catch(function() {
                                /* silencioso */
                            });
                        } catch (e) {
                            /* silencioso */
                        }
                    }
                })();
            </script>
            <?php unset($_SESSION['marcacion_mensaje']);
            unset($_SESSION['dvr_captura_pendiente']); ?>
        <?php endif; ?>

    <?php endif; ?>

</body>

</html>