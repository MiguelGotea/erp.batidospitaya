<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../auth.php';
require_once '../../includes/funciones.php';
require_once '../../includes/conexion.php';

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([16, 27, 13]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([16, 27, 13]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Configuración de la sesión
session_start();

// Establecer tiempo de vida de la sesión (15 minutos = 900 segundos)
$inactividad = 900;
if (
    isset($_SESSION['historial_operario_time']) &&
    (time() - $_SESSION['historial_operario_time'] > $inactividad)
) {
    unset($_SESSION['historial_operario']);
    unset($_SESSION['historial_operario_time']);
}

// Actualizar marca de tiempo en cada carga
if (isset($_SESSION['historial_operario'])) {
    $_SESSION['historial_operario_time'] = time();
}

// Limpiar sesión si viene de otra página (excepto al enviar el formulario)
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_SERVER['HTTP_REFERER']) &&
    !str_contains($_SERVER['HTTP_REFERER'], 'historial_marcaciones_sucursales.php')
) {
    unset($_SESSION['historial_operario']);
    unset($_SESSION['historial_operario_time']);
}

// Obtener información del usuario logueado (administrador)
$usuarioActual = obtenerUsuarioActual();
$sucursalUsuario = $usuarioActual['sucursal_codigo'] ?? null;

// Verificar que la IP coincida con la de la sucursal
//if (!verificarIpSucursal($sucursalUsuario)) {
//    $_SESSION['error'] = "Acceso denegado: No estás conectado desde la red autorizada para esta sucursal.";
//    header('Location: index.php');
//    exit();
//}

// Procesar el formulario de credenciales
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $clave = trim($_POST['clave']);

    // Validar credenciales
    $sql = "SELECT o.CodOperario, o.Nombre, o.Apellido, o.usuario, 
                   nc.Nombre as cargo_nombre, s.nombre as sucursal_nombre, s.codigo as sucursal_codigo
            FROM Operarios o
            JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
                AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                AND anc.Fecha <= CURDATE()
            JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
            JOIN sucursales s ON anc.Sucursal = s.codigo
            WHERE o.usuario = ? AND (o.clave = ? OR (o.clave_hash IS NOT NULL AND ? = o.clave_hash))
            AND o.Operativo = 1";

    $stmt = ejecutarConsulta($sql, [$usuario, $clave, $clave]);

    if ($stmt && $operario = $stmt->fetch()) {
        $_SESSION['historial_operario'] = $operario;
        $_SESSION['historial_operario_time'] = time(); // Establecer marca de tiempo
        header('Location: historial_marcaciones_sucursales.php');
        exit();
    } else {
        $_SESSION['error'] = "Usuario o contraseña incorrectos, o el usuario no está activo.";
    }
}

// Obtener datos del operario si ya se autenticó
$operario = $_SESSION['historial_operario'] ?? null;
$categoriaOperario = $operario ? obtenerCategoriaOperario($operario['CodOperario']) : null;

// Determinar el rango de fechas del mes actual
function obtenerRangoMes()
{
    $hoy = new DateTime();
    $mes = $hoy->format('m');
    $anio = $hoy->format('Y');

    $inicio = new DateTime("$anio-$mes-01");
    $fin = new DateTime("$anio-$mes-01");
    $fin->modify('last day of this month');

    return [
        'inicio' => $inicio->format('Y-m-d'),
        'fin' => $fin->format('Y-m-d'),
        'nombre' => 'Mes de ' . formatoMesAnio($inicio->format('Y-m-d')) // Usamos tu función aquí
    ];
}

function fechaValidaParaCalculo($fecha)
{
    $fechaCorte = new DateTime('2025-07-14');
    $fechaComparar = new DateTime($fecha);
    return $fechaComparar >= $fechaCorte;
}

function obtenerCargosOperario($codOperario)
{
    global $conn;

    $sql = "SELECT nc.Nombre 
                FROM AsignacionNivelesCargos anc
                JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                WHERE anc.CodOperario = ?
                AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario]);
    $resultados = $stmt->fetchAll();

    return array_column($resultados, 'Nombre');
}

// Función para obtener la categoría actual del operario
function obtenerCategoriaOperario($codOperario)
{
    global $conn;

    $sql = "SELECT co.NombreCategoria, co.Peso 
            FROM OperariosCategorias oc
            JOIN CategoriasOperarios co ON oc.idCategoria = co.idCategoria
            WHERE oc.CodOperario = ?
            AND oc.FechaInicio <= CURDATE()
            AND (oc.FechaFin IS NULL OR oc.FechaFin >= CURDATE())
            ORDER BY oc.FechaInicio DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario]);
    $resultado = $stmt->fetch();

    return $resultado ? $resultado : null;
}

function obtenerSucursalesOperario($codOperario)
{
    global $conn;

    $sql = "SELECT s.nombre 
                FROM AsignacionNivelesCargos anc
                JOIN sucursales s ON anc.Sucursal = s.codigo
                WHERE anc.CodOperario = ?
                AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                GROUP BY s.nombre";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario]);
    $resultados = $stmt->fetchAll();

    return array_column($resultados, 'nombre');
}

// Función para verificar si el operario tiene asignada alguna de las sucursales especiales (6 o 18)
function tieneSucursalesEspeciales($codOperario)
{
    global $conn;

    $sql = "SELECT COUNT(*) as count 
            FROM AsignacionNivelesCargos 
            WHERE CodOperario = ? 
            AND Sucursal NOT IN (6, 18)
            AND (Fin IS NULL OR Fin >= CURDATE())";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario]);
    $resultado = $stmt->fetch();

    return $resultado['count'] > 0;
}

// Determinar el rango de fechas de la quincena actual
function obtenerRangoQuincena()
{
    $hoy = new DateTime();
    $dia = (int) $hoy->format('d');
    $mes = (int) $hoy->format('m');
    $anio = (int) $hoy->format('Y');

    if ($dia <= 15) {
        // Primera quincena (1-15)
        $inicio = new DateTime("$anio-$mes-01");
        $fin = new DateTime("$anio-$mes-15");
    } else {
        // Segunda quincena (16-fin de mes)
        $inicio = new DateTime("$anio-$mes-16");
        // Obtener el último día del mes de forma correcta
        $fin = new DateTime("$anio-$mes-01");
        $fin->modify('last day of this month');
    }

    return [
        'inicio' => $inicio->format('Y-m-d'),
        'fin' => $fin->format('Y-m-d'),
        'nombre' => $dia <= 15 ? 'Primera Quincena' : 'Segunda Quincena'
    ];
}

// Función para obtener estadísticas del mes actual
function obtenerEstadisticasMes($codOperario)
{
    global $conn;

    $hoy = new DateTime();
    $mes = $hoy->format('m');
    $anio = $hoy->format('Y');

    // Obtener primer y último día del mes
    $inicioMes = new DateTime("$anio-$mes-01");
    $finMes = new DateTime("$anio-$mes-01");
    $finMes->modify('last day of this month');

    $estadisticas = [
        'faltas_totales' => 0,
        'faltas_ejecutadas' => 0,
        'tardanzas_totales' => 0,
        'tardanzas_ejecutadas' => 0,
        'turnos_nocturnos' => 0,
        'omisiones_marcacion' => 0,
        'dias_fuera_horario' => 0,
        'por_sucursal' => [],   // Para estadísticas por sucursal
        'dias_contados' => []    // Para evitar duplicados por día
    ];

    // 1. Obtener tardanzas totales (marcaciones fuera de hora + omisiones de entrada)
    $sqlTardanzasTotales = "SELECT 
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
                 )
             ))
             /* O omisiones de marcado de entrada */
             OR (m.hora_ingreso IS NULL)
         )) as tardanzas_totales";

    $stmtTardanzasTotales = ejecutarConsulta($sqlTardanzasTotales, [$codOperario]);
    $tardanzasTotales = $stmtTardanzasTotales ? $stmtTardanzasTotales->fetch()['tardanzas_totales'] : 0;

    // 2. Obtener tardanzas justificadas (para calcular tardanzas ejecutadas)
    $sqlTardanzasJustificadas = "SELECT COUNT(*) as tardanzas_justificadas 
                                FROM TardanzasManuales
                                WHERE cod_operario = ?
                                AND MONTH(fecha_tardanza) = MONTH(CURDATE())
                                AND YEAR(fecha_tardanza) = YEAR(CURDATE())
                                AND estado = 'Justificado'";
    $stmtTardanzasJustificadas = ejecutarConsulta($sqlTardanzasJustificadas, [$codOperario]);
    $tardanzasJustificadas = $stmtTardanzasJustificadas ? $stmtTardanzasJustificadas->fetch()['tardanzas_justificadas'] : 0;

    // 3. Obtener omisiones de marcación (entrada o salida faltante)
    $sqlOmisiones = "SELECT COUNT(*) as omisiones 
                    FROM marcaciones 
                    WHERE CodOperario = ? 
                    AND MONTH(fecha) = MONTH(CURDATE())
                    AND YEAR(fecha) = YEAR(CURDATE())
                    AND (hora_ingreso IS NULL OR hora_salida IS NULL)";
    $stmtOmisiones = ejecutarConsulta($sqlOmisiones, [$codOperario]);
    $omisionesMes = $stmtOmisiones ? $stmtOmisiones->fetch()['omisiones'] : 0;

    // 4. Obtener faltas totales (días sin marcación + omisión entrada + omisión salida)
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

    // 5. Obtener faltas no pagadas (para calcular faltas ejecutadas)
    $sqlFaltasNoPagadas = "SELECT COUNT(*) as faltas_no_pagadas 
                          FROM faltas_manual 
                          WHERE cod_operario = ? 
                          AND MONTH(fecha_falta) = MONTH(CURDATE())
                          AND YEAR(fecha_falta) = YEAR(CURDATE())
                          AND tipo_falta = 'No_Pagado'";
    $stmtFaltasNoPagadas = ejecutarConsulta($sqlFaltasNoPagadas, [$codOperario]);
    $faltasNoPagadas = $stmtFaltasNoPagadas ? $stmtFaltasNoPagadas->fetch()['faltas_no_pagadas'] : 0;

    // 6. Obtener turnos nocturnos (marcaciones de salida después de 8pm)
    $sqlTurnosNocturnos = "SELECT COUNT(*) as turnos_nocturnos 
                          FROM marcaciones 
                          WHERE CodOperario = ? 
                          AND MONTH(fecha) = MONTH(CURDATE())
                          AND YEAR(fecha) = YEAR(CURDATE())
                          AND hora_salida IS NOT NULL
                          AND TIME(hora_salida) >= '20:00:00'";
    $stmtTurnosNocturnos = ejecutarConsulta($sqlTurnosNocturnos, [$codOperario]);
    $turnosNocturnos = $stmtTurnosNocturnos ? $stmtTurnosNocturnos->fetch()['turnos_nocturnos'] : 0;

    // 7. Obtener días fuera de horario programado
    $sqlDiasFueraHorario = "SELECT COUNT(DISTINCT fecha) as dias_fuera_horario
                           FROM marcaciones m
                           LEFT JOIN HorariosSemanalesOperaciones h ON m.numero_semana = h.numero_semana
                               AND m.CodOperario = h.cod_operario 
                               AND m.sucursal_codigo = h.cod_sucursal
                           WHERE m.CodOperario = ? 
                           AND MONTH(m.fecha) = MONTH(CURDATE())
                           AND YEAR(m.fecha) = YEAR(CURDATE())
                           AND h.id IS NULL";
    $stmtDiasFueraHorario = ejecutarConsulta($sqlDiasFueraHorario, [$codOperario]);
    $diasFueraHorario = $stmtDiasFueraHorario ? $stmtDiasFueraHorario->fetch()['dias_fuera_horario'] : 0;

    // Calcular estadísticas finales
    $estadisticas['tardanzas_totales'] = $tardanzasTotales;
    $estadisticas['tardanzas_ejecutadas'] = max(0, $tardanzasTotales - $tardanzasJustificadas);
    $estadisticas['omisiones_marcacion'] = $omisionesMes;
    $estadisticas['faltas_totales'] = $faltasTotales;
    $estadisticas['faltas_ejecutadas'] = max(0, $faltasTotales - $faltasNoPagadas);
    $estadisticas['turnos_nocturnos'] = $turnosNocturnos;
    $estadisticas['dias_fuera_horario'] = $diasFueraHorario;

    // Obtener estadísticas por sucursal
    $sqlSucursales = "SELECT DISTINCT sucursal_codigo 
                      FROM marcaciones 
                      WHERE CodOperario = ? 
                      AND MONTH(fecha) = MONTH(CURDATE())
                      AND YEAR(fecha) = YEAR(CURDATE())";
    $stmtSucursales = ejecutarConsulta($sqlSucursales, [$codOperario]);
    $sucursales = $stmtSucursales ? $stmtSucursales->fetchAll() : [];

    foreach ($sucursales as $sucursal) {
        $codSucursal = $sucursal['sucursal_codigo'];
        $nombreSucursal = obtenerNombreSucursal1($codSucursal);

        // Tardanzas por sucursal
        $sqlTardanzasSucursal = "SELECT COUNT(*) as tardanzas 
                                FROM marcaciones m
                                LEFT JOIN HorariosSemanalesOperaciones h ON m.numero_semana = h.numero_semana
                                    AND m.CodOperario = h.cod_operario 
                                    AND m.sucursal_codigo = h.cod_sucursal
                                WHERE m.CodOperario = ? 
                                AND m.sucursal_codigo = ?
                                AND MONTH(m.fecha) = MONTH(CURDATE())
                                AND YEAR(m.fecha) = YEAR(CURDATE())
                                AND (
                                    (m.hora_ingreso IS NOT NULL AND (
                                        (m.sucursal_codigo IN (6, 18) AND TIME(m.hora_ingreso) > '07:00:00')
                                        OR
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
                                        )
                                    ))
                                    OR (m.hora_ingreso IS NULL)
                                )";
        $stmtTardanzasSucursal = ejecutarConsulta($sqlTardanzasSucursal, [$codOperario, $codSucursal]);
        $tardanzasSucursal = $stmtTardanzasSucursal ? $stmtTardanzasSucursal->fetch()['tardanzas'] : 0;

        // Omisiones por sucursal
        $sqlOmisionesSucursal = "SELECT COUNT(*) as omisiones 
                                FROM marcaciones 
                                WHERE CodOperario = ? 
                                AND sucursal_codigo = ?
                                AND MONTH(fecha) = MONTH(CURDATE())
                                AND YEAR(fecha) = YEAR(CURDATE())
                                AND (hora_ingreso IS NULL OR hora_salida IS NULL)";
        $stmtOmisionesSucursal = ejecutarConsulta($sqlOmisionesSucursal, [$codOperario, $codSucursal]);
        $omisionesSucursal = $stmtOmisionesSucursal ? $stmtOmisionesSucursal->fetch()['omisiones'] : 0;

        // Turnos nocturnos por sucursal
        $sqlNocturnosSucursal = "SELECT COUNT(*) as turnos_nocturnos 
                                FROM marcaciones 
                                WHERE CodOperario = ? 
                                AND sucursal_codigo = ?
                                AND MONTH(fecha) = MONTH(CURDATE())
                                AND YEAR(fecha) = YEAR(CURDATE())
                                AND hora_salida IS NOT NULL
                                AND TIME(hora_salida) >= '20:00:00'";
        $stmtNocturnosSucursal = ejecutarConsulta($sqlNocturnosSucursal, [$codOperario, $codSucursal]);
        $nocturnosSucursal = $stmtNocturnosSucursal ? $stmtNocturnosSucursal->fetch()['turnos_nocturnos'] : 0;

        // Días fuera de horario por sucursal
        $sqlFueraHorarioSucursal = "SELECT COUNT(DISTINCT fecha) as dias_fuera_horario
                                   FROM marcaciones m
                                   LEFT JOIN HorariosSemanalesOperaciones h ON m.numero_semana = h.numero_semana
                                       AND m.CodOperario = h.cod_operario 
                                       AND m.sucursal_codigo = h.cod_sucursal
                                   WHERE m.CodOperario = ? 
                                   AND m.sucursal_codigo = ?
                                   AND MONTH(m.fecha) = MONTH(CURDATE())
                                   AND YEAR(m.fecha) = YEAR(CURDATE())
                                   AND h.id IS NULL";
        $stmtFueraHorarioSucursal = ejecutarConsulta($sqlFueraHorarioSucursal, [$codOperario, $codSucursal]);
        $fueraHorarioSucursal = $stmtFueraHorarioSucursal ? $stmtFueraHorarioSucursal->fetch()['dias_fuera_horario'] : 0;

        $estadisticas['por_sucursal'][$codSucursal] = [
            'nombre' => $nombreSucursal,
            'tardanzas_ejecutadas' => $tardanzasSucursal,
            'turnos_nocturnos' => $nocturnosSucursal,
            'omisiones_marcacion' => $omisionesSucursal,
            'dias_fuera_horario' => $fueraHorarioSucursal
        ];
    }

    return $estadisticas;
}

// Función para obtener estadísticas del rango de fechas especificado
function obtenerEstadisticasRango($codOperario, $fechaInicio, $fechaFin)
{
    global $conn;

    $estadisticas = [
        'faltas_totales' => 0,
        'faltas_ejecutadas' => 0,
        'tardanzas_totales' => 0,
        'tardanzas_ejecutadas' => 0,
        'turnos_nocturnos' => 0,
        'omisiones_marcacion' => 0,
        'dias_fuera_horario' => 0,
        'por_sucursal' => [],
        'dias_contados' => []
    ];

    // 1. Obtener tardanzas totales (marcaciones fuera de hora + omisiones de entrada)
    $sqlTardanzasTotales = "SELECT 
        (SELECT COUNT(*) FROM marcaciones m
         LEFT JOIN HorariosSemanalesOperaciones h ON m.numero_semana = h.numero_semana
             AND m.CodOperario = h.cod_operario 
             AND m.sucursal_codigo = h.cod_sucursal
         WHERE m.CodOperario = ? 
         AND m.fecha BETWEEN ? AND ?
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
                 )
             ))
             /* O omisiones de marcado de entrada */
             OR (m.hora_ingreso IS NULL)
         )) as tardanzas_totales";

    $stmtTardanzasTotales = ejecutarConsulta($sqlTardanzasTotales, [$codOperario, $fechaInicio, $fechaFin]);
    $tardanzasTotales = $stmtTardanzasTotales ? $stmtTardanzasTotales->fetch()['tardanzas_totales'] : 0;

    // 2. Obtener tardanzas justificadas (para calcular tardanzas ejecutadas)
    $sqlTardanzasJustificadas = "SELECT COUNT(*) as tardanzas_justificadas 
                                FROM TardanzasManuales
                                WHERE cod_operario = ?
                                AND fecha_tardanza BETWEEN ? AND ?
                                AND estado = 'Justificado'";
    $stmtTardanzasJustificadas = ejecutarConsulta($sqlTardanzasJustificadas, [$codOperario, $fechaInicio, $fechaFin]);
    $tardanzasJustificadas = $stmtTardanzasJustificadas ? $stmtTardanzasJustificadas->fetch()['tardanzas_justificadas'] : 0;

    // 3. Obtener omisiones de marcación (entrada o salida faltante)
    $sqlOmisiones = "SELECT COUNT(*) as omisiones 
                    FROM marcaciones 
                    WHERE CodOperario = ? 
                    AND fecha BETWEEN ? AND ?
                    AND (hora_ingreso IS NULL OR hora_salida IS NULL)";
    $stmtOmisiones = ejecutarConsulta($sqlOmisiones, [$codOperario, $fechaInicio, $fechaFin]);
    $omisionesMes = $stmtOmisiones ? $stmtOmisiones->fetch()['omisiones'] : 0;

    // 4. Obtener faltas totales (días sin marcación + omisión entrada + omisión salida)
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

    // 5. Obtener faltas no pagadas (para calcular faltas ejecutadas)
    $sqlFaltasNoPagadas = "SELECT COUNT(*) as faltas_no_pagadas 
                          FROM faltas_manual 
                          WHERE cod_operario = ? 
                          AND MONTH(fecha_falta) = MONTH(CURDATE())
                          AND YEAR(fecha_falta) = YEAR(CURDATE())
                          AND tipo_falta = 'No_Pagado'";
    $stmtFaltasNoPagadas = ejecutarConsulta($sqlFaltasNoPagadas, [$codOperario]);
    $faltasNoPagadas = $stmtFaltasNoPagadas ? $stmtFaltasNoPagadas->fetch()['faltas_no_pagadas'] : 0;

    // 6. Obtener turnos nocturnos (marcaciones de salida después de 8pm)
    $sqlTurnosNocturnos = "SELECT COUNT(*) as turnos_nocturnos 
                          FROM marcaciones 
                          WHERE CodOperario = ? 
                          AND MONTH(fecha) = MONTH(CURDATE())
                          AND YEAR(fecha) = YEAR(CURDATE())
                          AND hora_salida IS NOT NULL
                          AND TIME(hora_salida) >= '20:00:00'";
    $stmtTurnosNocturnos = ejecutarConsulta($sqlTurnosNocturnos, [$codOperario]);
    $turnosNocturnos = $stmtTurnosNocturnos ? $stmtTurnosNocturnos->fetch()['turnos_nocturnos'] : 0;

    // 7. Obtener días fuera de horario programado
    $sqlDiasFueraHorario = "SELECT COUNT(DISTINCT fecha) as dias_fuera_horario
                           FROM marcaciones m
                           LEFT JOIN HorariosSemanalesOperaciones h ON m.numero_semana = h.numero_semana
                               AND m.CodOperario = h.cod_operario 
                               AND m.sucursal_codigo = h.cod_sucursal
                           WHERE m.CodOperario = ? 
                           AND m.fecha BETWEEN ? AND ?
                           AND YEAR(m.fecha) = YEAR(CURDATE())
                           AND h.id IS NULL";
    $stmtDiasFueraHorario = ejecutarConsulta($sqlDiasFueraHorario, [$codOperario]);
    $diasFueraHorario = $stmtDiasFueraHorario ? $stmtDiasFueraHorario->fetch()['dias_fuera_horario'] : 0;

    // Calcular estadísticas finales
    $estadisticas['tardanzas_totales'] = $tardanzasTotales;
    $estadisticas['tardanzas_ejecutadas'] = max(0, $tardanzasTotales - $tardanzasJustificadas);
    $estadisticas['omisiones_marcacion'] = $omisionesMes;
    $estadisticas['faltas_totales'] = $faltasTotales;
    $estadisticas['faltas_ejecutadas'] = max(0, $faltasTotales - $faltasNoPagadas);
    $estadisticas['turnos_nocturnos'] = $turnosNocturnos;
    $estadisticas['dias_fuera_horario'] = $diasFueraHorario;

    // Obtener estadísticas por sucursal
    $sqlSucursales = "SELECT DISTINCT sucursal_codigo 
                      FROM marcaciones 
                      WHERE CodOperario = ? 
                      AND MONTH(fecha) = MONTH(CURDATE())
                      AND YEAR(fecha) = YEAR(CURDATE())";
    $stmtSucursales = ejecutarConsulta($sqlSucursales, [$codOperario]);
    $sucursales = $stmtSucursales ? $stmtSucursales->fetchAll() : [];

    foreach ($sucursales as $sucursal) {
        $codSucursal = $sucursal['sucursal_codigo'];
        $nombreSucursal = obtenerNombreSucursal1($codSucursal);

        // Tardanzas por sucursal
        $sqlTardanzasSucursal = "SELECT COUNT(*) as tardanzas 
                                FROM marcaciones m
                                LEFT JOIN HorariosSemanalesOperaciones h ON m.numero_semana = h.numero_semana
                                    AND m.CodOperario = h.cod_operario 
                                    AND m.sucursal_codigo = h.cod_sucursal
                                WHERE m.CodOperario = ? 
                                AND m.sucursal_codigo = ?
                                AND m.fecha BETWEEN ? AND ?
                                AND YEAR(m.fecha) = YEAR(CURDATE())
                                AND (
                                    (m.hora_ingreso IS NOT NULL AND (
                                        (m.sucursal_codigo IN (6, 18) AND TIME(m.hora_ingreso) > '07:00:00')
                                        OR
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
                                        )
                                    ))
                                    OR (m.hora_ingreso IS NULL)
                                )";
        $stmtTardanzasSucursal = ejecutarConsulta($sqlTardanzasSucursal, [$codOperario, $codSucursal]);
        $tardanzasSucursal = $stmtTardanzasSucursal ? $stmtTardanzasSucursal->fetch()['tardanzas'] : 0;

        // Omisiones por sucursal
        $sqlOmisionesSucursal = "SELECT COUNT(*) as omisiones 
                                FROM marcaciones 
                                WHERE CodOperario = ? 
                                AND sucursal_codigo = ?
                                AND MONTH(fecha) = MONTH(CURDATE())
                                AND YEAR(fecha) = YEAR(CURDATE())
                                AND (hora_ingreso IS NULL OR hora_salida IS NULL)";
        $stmtOmisionesSucursal = ejecutarConsulta($sqlOmisionesSucursal, [$codOperario, $codSucursal]);
        $omisionesSucursal = $stmtOmisionesSucursal ? $stmtOmisionesSucursal->fetch()['omisiones'] : 0;

        // Turnos nocturnos por sucursal
        $sqlNocturnosSucursal = "SELECT COUNT(*) as turnos_nocturnos 
                                FROM marcaciones 
                                WHERE CodOperario = ? 
                                AND sucursal_codigo = ?
                                AND MONTH(fecha) = MONTH(CURDATE())
                                AND YEAR(fecha) = YEAR(CURDATE())
                                AND hora_salida IS NOT NULL
                                AND TIME(hora_salida) >= '20:00:00'";
        $stmtNocturnosSucursal = ejecutarConsulta($sqlNocturnosSucursal, [$codOperario, $codSucursal]);
        $nocturnosSucursal = $stmtNocturnosSucursal ? $stmtNocturnosSucursal->fetch()['turnos_nocturnos'] : 0;

        // Días fuera de horario por sucursal
        $sqlFueraHorarioSucursal = "SELECT COUNT(DISTINCT fecha) as dias_fuera_horario
                                   FROM marcaciones m
                                   LEFT JOIN HorariosSemanalesOperaciones h ON m.numero_semana = h.numero_semana
                                       AND m.CodOperario = h.cod_operario 
                                       AND m.sucursal_codigo = h.cod_sucursal
                                   WHERE m.CodOperario = ? 
                                   AND m.sucursal_codigo = ?
                                   AND m.fecha BETWEEN ? AND ?
                                   AND YEAR(m.fecha) = YEAR(CURDATE())
                                   AND h.id IS NULL";
        $stmtFueraHorarioSucursal = ejecutarConsulta($sqlFueraHorarioSucursal, [$codOperario, $codSucursal]);
        $fueraHorarioSucursal = $stmtFueraHorarioSucursal ? $stmtFueraHorarioSucursal->fetch()['dias_fuera_horario'] : 0;

        $estadisticas['por_sucursal'][$codSucursal] = [
            'nombre' => $nombreSucursal,
            'tardanzas_ejecutadas' => $tardanzasSucursal,
            'turnos_nocturnos' => $nocturnosSucursal,
            'omisiones_marcacion' => $omisionesSucursal,
            'dias_fuera_horario' => $fueraHorarioSucursal
        ];
    }

    return $estadisticas;
}

// Función auxiliar para obtener nombre de sucursal
function obtenerNombreSucursal1($codigo)
{
    global $conn;

    $stmt = $conn->prepare("SELECT nombre FROM sucursales WHERE codigo = ? LIMIT 1");
    $stmt->execute([$codigo]);
    $result = $stmt->fetch();

    return $result ? $result['nombre'] : 'Desconocida';
}

// Función auxiliar para obtener las sucursales donde el operario tiene horario programado
function obtenerSucursalesConHorario($codOperario, $fechaInicio, $fechaFin)
{
    global $conn;

    $sql = "SELECT DISTINCT cod_sucursal 
            FROM HorariosSemanalesOperaciones h
            JOIN SemanasSistema s ON h.id_semana_sistema = s.id
            WHERE h.cod_operario = ?
            AND s.fecha_inicio <= ?
            AND s.fecha_fin >= ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario, $fechaFin, $fechaInicio]);
    $resultados = $stmt->fetchAll();

    return array_column($resultados, 'cod_sucursal');
}

// Función para obtener tardanzas y faltas del mes
function obtenerTardanzasFaltasMes($codOperario)
{
    global $conn;

    $hoy = new DateTime();
    $mes = $hoy->format('m');
    $anio = $hoy->format('Y');

    // Obtener primer y último día del mes
    $inicioMes = new DateTime("$anio-$mes-01");
    $finMes = new DateTime("$anio-$mes-01");
    $finMes->modify('last day of this month');

    $resultados = [];

    // Obtener todas las marcaciones del mes ordenadas por fecha y hora
    $sqlMarcaciones = "SELECT m.*, s.nombre as sucursal_nombre, s.codigo as sucursal_codigo
                      FROM marcaciones m
                      LEFT JOIN sucursales s ON m.sucursal_codigo = s.codigo
                      WHERE m.CodOperario = ? 
                      AND m.fecha BETWEEN ? AND ?
                      ORDER BY m.fecha, m.hora_ingreso, m.hora_salida";
    $stmtMarcaciones = $conn->prepare($sqlMarcaciones);
    $stmtMarcaciones->execute([$codOperario, $inicioMes->format('Y-m-d'), $finMes->format('Y-m-d')]);
    $marcaciones = $stmtMarcaciones->fetchAll();

    // Filtrar marcaciones para incluir solo las del 14/07/2025 en adelante
    $marcaciones = array_filter($marcaciones, function ($marcacion) {
        return fechaValidaParaCalculo($marcacion['fecha']);
    });

    // Agrupar marcaciones por fecha y sucursal
    $marcacionesAgrupadas = [];
    foreach ($marcaciones as $marcacion) {
        $key = $marcacion['fecha'] . '-' . $marcacion['sucursal_codigo'];
        if (!isset($marcacionesAgrupadas[$key])) {
            $marcacionesAgrupadas[$key] = [];
        }
        $marcacionesAgrupadas[$key][] = $marcacion;
    }

    // Obtener semanas del mes
    $sqlSemanas = "SELECT id, fecha_inicio, fecha_fin 
                  FROM SemanasSistema
                  WHERE fecha_inicio <= ? AND fecha_fin >= ?";
    $stmtSemanas = $conn->prepare($sqlSemanas);
    $stmtSemanas->execute([$finMes->format('Y-m-d'), $inicioMes->format('Y-m-d')]);
    $semanas = $stmtSemanas->fetchAll();

    foreach ($marcacionesAgrupadas as $grupo) {
        $primeraMarcacion = $grupo[0];
        $fecha = $primeraMarcacion['fecha'];
        $sucursal = $primeraMarcacion['sucursal_codigo'];
        $sucursalNombre = $primeraMarcacion['sucursal_nombre'];
        $horaEntradaMarcada = $primeraMarcacion['hora_ingreso'] ? date('H:i', strtotime($primeraMarcacion['hora_ingreso'])) : null;

        $fechaObj = new DateTime($fecha);
        $diaSemana = $fechaObj->format('N'); // 1=lunes, 7=domingo

        // Lógica especial para sucursales 6 y 18
        if ($sucursal == 6 || $sucursal == 18) {
            // Verificar que haya al menos 1 marcación de entrada
            if (!$horaEntradaMarcada) {
                $resultados[] = [
                    'sucursal' => $sucursalNombre,
                    'fecha' => $fecha,
                    'tipo' => 'Falta',
                    'estado_horario' => 'Activo',
                    'minutos' => 0,
                    'tipo_incidencia' => 'Sin marcación de entrada mañana',
                    'hora_entrada_marcada' => null
                ];
                continue;
            }

            // Verificar tardanza en la mañana (después de 7:00)
            $horaEntradaTime = strtotime($primeraMarcacion['hora_ingreso']);
            if ($horaEntradaTime > strtotime('07:00:00')) {
                $diferencia = $horaEntradaTime - strtotime('07:00:00');
                $minutosTardanza = floor($diferencia / 60);

                $resultados[] = [
                    'sucursal' => $sucursalNombre,
                    'fecha' => $fecha,
                    'tipo' => 'Tardanza',
                    'estado_horario' => 'Activo',
                    'minutos' => $minutosTardanza,
                    'tipo_incidencia' => 'Entrada tardía entrada mañana (después de 7:00 AM)',
                    'hora_entrada_marcada' => $horaEntradaMarcada
                ];
            }

            // Verificar si hay marcación de almuerzo (segunda marcación)
            if (count($grupo) < 2 || !$grupo[1]['hora_ingreso']) {
                $resultados[] = [
                    'sucursal' => $sucursalNombre,
                    'fecha' => $fecha,
                    'tipo' => 'Falta',
                    'estado_horario' => 'Activo',
                    'minutos' => 0,
                    'tipo_incidencia' => 'Sin marcación de entrada después de almuerzo',
                    'hora_entrada_marcada' => $horaEntradaMarcada
                ];
            } else {
                // Verificar tardanza después de almuerzo (después de 13:00)
                $horaEntradaTarde = strtotime($grupo[1]['hora_ingreso']);
                if ($horaEntradaTarde > strtotime('13:00:00')) {
                    $diferencia = $horaEntradaTarde - strtotime('13:00:00');
                    $minutosTardanza = floor($diferencia / 60);

                    $resultados[] = [
                        'sucursal' => $sucursalNombre,
                        'fecha' => $fecha,
                        'tipo' => 'Tardanza',
                        'estado_horario' => 'Activo',
                        'minutos' => $minutosTardanza,
                        'tipo_incidencia' => 'Entrada tardía tarde almuerzo (después de 1:00 PM)',
                        'hora_entrada_marcada' => date('H:i', $horaEntradaTarde)
                    ];
                }
            }

            continue;
        }

        // Lógica para otras sucursales
        $semana = null;
        foreach ($semanas as $s) {
            if ($fecha >= $s['fecha_inicio'] && $fecha <= $s['fecha_fin']) {
                $semana = $s;
                break;
            }
        }

        if (!$semana)
            continue;

        // Obtener horario programado
        $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        $diaColumna = $dias[$diaSemana - 1];

        $sqlHorario = "SELECT 
                        {$diaColumna}_estado as estado,
                        {$diaColumna}_entrada as hora_entrada,
                        {$diaColumna}_salida as hora_salida
                      FROM HorariosSemanalesOperaciones
                      WHERE id_semana_sistema = ? 
                      AND cod_operario = ?
                      AND cod_sucursal = ?";
        $stmtHorario = $conn->prepare($sqlHorario);
        $stmtHorario->execute([$semana['id'], $codOperario, $sucursal]);
        $horario = $stmtHorario->fetch();

        // Si no hay horario o no es Activo
        if (!$horario || $horario['estado'] !== 'Activo') {
            if ($horaEntradaMarcada) {
                $resultados[] = [
                    'sucursal' => $sucursalNombre,
                    'fecha' => $fecha,
                    'tipo' => 'Marcación no programada',
                    'estado_horario' => $horario ? $horario['estado'] : 'No programado',
                    'minutos' => 0,
                    'tipo_incidencia' => 'Marcación no requerida',
                    'hora_entrada_marcada' => $horaEntradaMarcada
                ];
            }
            continue;
        }

        // Verificar tardanza
        if ($horario['hora_entrada'] && $horaEntradaMarcada) {
            $horaProgramada = new DateTime($horario['hora_entrada']);
            $horaReal = new DateTime($primeraMarcacion['hora_ingreso']);

            if ($horaReal > $horaProgramada) {
                $diferencia = $horaReal->diff($horaProgramada);
                $minutosTardanza = $diferencia->h * 60 + $diferencia->i;

                $resultados[] = [
                    'sucursal' => $sucursalNombre,
                    'fecha' => $fecha,
                    'tipo' => 'Tardanza',
                    'estado_horario' => 'Activo',
                    'minutos' => $minutosTardanza,
                    'tipo_incidencia' => 'Entrada tardía',
                    'hora_entrada_marcada' => $horaEntradaMarcada
                ];
            }
        } elseif (!$horaEntradaMarcada) {
            // Falta de marcación de entrada
            $resultados[] = [
                'sucursal' => $sucursalNombre,
                'fecha' => $fecha,
                'tipo' => 'Falta',
                'estado_horario' => 'Activo',
                'minutos' => 0,
                'tipo_incidencia' => 'Sin marcación de entrada',
                'hora_entrada_marcada' => null
            ];
        }
    }

    return $resultados;
}

// Función para verificar estado de marcación
function verificarEstadoMarcacion($codOperario, $fecha, $horaMarcacion, $horaSalida = null)
{
    global $conn;

    $diaSemana = date('N', strtotime($fecha));
    $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $diaColumna = $dias[$diaSemana - 1];

    // Obtener la semana del sistema
    $sqlSemana = "SELECT id FROM SemanasSistema 
                  WHERE fecha_inicio <= ? AND fecha_fin >= ?";
    $stmtSemana = $conn->prepare($sqlSemana);
    $stmtSemana->execute([$fecha, $fecha]);
    $semana = $stmtSemana->fetch();

    if (!$semana) {
        return ['estado' => 'Sin horario programado', 'clase' => 'no-programado'];
    }

    // Obtener horario programado
    $sqlHorario = "SELECT 
                    {$diaColumna}_estado as estado,
                    {$diaColumna}_entrada as hora_entrada,
                    {$diaColumna}_salida as hora_salida
                  FROM HorariosSemanalesOperaciones
                  WHERE id_semana_sistema = ? 
                  AND cod_operario = ?";
    $stmtHorario = $conn->prepare($sqlHorario);
    $stmtHorario->execute([$semana['id'], $codOperario]);
    $horario = $stmtHorario->fetch();

    if (!$horario) {
        return ['estado' => 'Sin horario programado', 'clase' => 'no-programado'];
    }

    // Manejar diferentes estados
    if ($horario['estado'] !== 'Activo') {
        // Si tiene un estado diferente pero marcó algo
        if ($horaMarcacion || $horaSalida) {
            return [
                'estado' => 'Marcación no requerida (' . $horario['estado'] . ')',
                'clase' => 'estado-especial'
            ];
        }
        return [
            'estado' => 'Día no laborable (' . $horario['estado'] . ')',
            'clase' => 'no-laborable'
        ];
    }

    // Verificar tardanza
    if ($horario['hora_entrada'] && $horaMarcacion) {
        $horaProgramada = strtotime($horario['hora_entrada']);
        $horaReal = strtotime($horaMarcacion);

        if ($horaReal > $horaProgramada) {
            return ['estado' => 'Tardanza', 'clase' => 'tardanza'];
        }
    }

    // Verificar turno incompleto
    if (!$horaMarcacion || !$horaSalida) {
        return ['estado' => 'Turno incompleto', 'clase' => 'incompleto'];
    }

    return ['estado' => 'A tiempo', 'clase' => 'puntual'];
}

// Si hay un operario autenticado, obtener su historial y estadísticas
$historial = [];
$historialMes = []; // <-- Añade esta línea
$estadisticasQuincena = []; // <-- Cambiar nombre a estadísticasMes si se quiere ver del mes completo actual
$rangoQuincena = obtenerRangoQuincena();
$rangoMes = obtenerRangoMes(); // <-- Añade esta línea

if ($operario) {
    // Obtener estadísticas del mes
    //$estadisticasMes = obtenerEstadisticasMes($operario['CodOperario']);

    $estadisticasQuincena = obtenerEstadisticasRango($operario['CodOperario'], $rangoQuincena['inicio'], $rangoQuincena['fin']);

    // Obtener historial de la quincena
    $sql = "SELECT m.*, s.nombre as sucursal_nombre 
            FROM marcaciones m
            LEFT JOIN sucursales s ON m.sucursal_codigo = s.codigo
            WHERE m.CodOperario = ? 
            AND m.fecha BETWEEN ? AND ?
            ORDER BY m.fecha DESC, m.hora_ingreso DESC";

    $stmt = ejecutarConsulta($sql, [
        $operario['CodOperario'],
        $rangoQuincena['inicio'],
        $rangoQuincena['fin']
    ]);

    if ($stmt) {
        $historial = $stmt->fetchAll();

        // Para cada registro, determinar estado
        foreach ($historial as &$registro) {
            $resultadoEstado = verificarEstadoMarcacion(
                $operario['CodOperario'],
                $registro['fecha'],
                $registro['hora_ingreso'],
                $registro['hora_salida']
            );
            $registro['estado'] = $resultadoEstado['estado'];
            $registro['estado_clase'] = $resultadoEstado['clase'];
        }
        unset($registro); // Romper la referencia
    }

    // Obtener historial del mes <-- Añade este bloque
    $stmtMes = ejecutarConsulta($sql, [
        $operario['CodOperario'],
        $rangoMes['inicio'],
        $rangoMes['fin']
    ]);

    if ($stmtMes) {
        $historialMes = $stmtMes->fetchAll();

        // Para cada registro, determinar estado
        foreach ($historialMes as &$registroMes) {
            $resultadoEstado = verificarEstadoMarcacion(
                $operario['CodOperario'],
                $registroMes['fecha'],
                $registroMes['hora_ingreso'],
                $registroMes['hora_salida']
            );
            $registroMes['estado'] = $resultadoEstado['estado'];
            $registroMes['estado_clase'] = $resultadoEstado['clase'];
        }
        unset($registroMes); // Romper la referencia
    }
}

// Limpiar sesión si se solicita
if (isset($_GET['logout'])) {
    unset($_SESSION['historial_operario']);
    header('Location: historial_marcaciones_sucursales.php');
    exit();
}

// Limpiar sesión si viene de otra página (excepto al enviar el formulario)
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_SERVER['HTTP_REFERER']) &&
    !str_contains($_SERVER['HTTP_REFERER'], 'historial_marcaciones_sucursales.php')
) {
    unset($_SESSION['historial_operario']);
}

// Función auxiliar para obtener días laborables de un operario
function obtenerDiasLaborablesOperario($codOperario, $fechaDesde, $fechaHasta)
{
    global $conn;

    // Obtener todas las semanas que cubren el rango de fechas
    $stmt = $conn->prepare("
        SELECT * FROM SemanasSistema 
        WHERE fecha_inicio <= ? AND fecha_fin >= ?
    ");
    $stmt->execute([$fechaHasta, $fechaDesde]);
    $semanas = $stmt->fetchAll();

    $diasLaborables = [];

    foreach ($semanas as $semana) {
        // Obtener horario programado para esta semana
        $stmt = $conn->prepare("
            SELECT * FROM HorariosSemanalesOperaciones
            WHERE cod_operario = ? 
            AND id_semana_sistema = ?
            LIMIT 1
        ");
        $stmt->execute([$codOperario, $semana['id']]);
        $horario = $stmt->fetch();

        if ($horario) {
            // Verificar cada día de la semana
            $dias = [
                'lunes' => 1,
                'martes' => 2,
                'miercoles' => 3,
                'jueves' => 4,
                'viernes' => 5,
                'sabado' => 6,
                'domingo' => 7
            ];

            foreach ($dias as $dia => $diaNumero) {
                $columnaEstado = $dia . '_estado';
                $columnaEntrada = $dia . '_entrada';
                $columnaSalida = $dia . '_salida';

                if ($horario[$columnaEstado] === 'Activo' && $horario[$columnaEntrada] !== null) {
                    // Calcular fecha del día específico
                    $fechaDia = date('Y-m-d', strtotime($semana['fecha_inicio'] . ' + ' . ($diaNumero - 1) . ' days'));

                    // Verificar si la fecha está dentro del rango solicitado
                    if ($fechaDia >= $fechaDesde && $fechaDia <= $fechaHasta) {
                        $diasLaborables[] = [
                            'fecha' => $fechaDia,
                            'hora_entrada' => $horario[$columnaEntrada],
                            'hora_salida' => $horario[$columnaSalida],
                            'id_horario' => $horario['id']
                        ];
                    }
                }
            }
        }
    }

    return $diasLaborables;
}

// Función auxiliar para verificar marcación de entrada
function obtenerMarcacionEntrada($codOperario, $fecha)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT * FROM marcaciones 
        WHERE CodOperario = ? 
        AND fecha = ?
        AND hora_ingreso IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $fecha]);
    return $stmt->fetch();
}

// Función para calcular el porcentaje de puntualidad basado en tardanzas ejecutadas
function calcularPuntualidad($tardanzasEjecutadas)
{
    if ($tardanzasEjecutadas <= 2) {
        return 100;
    } elseif ($tardanzasEjecutadas == 3) {
        return 60;
    } elseif ($tardanzasEjecutadas == 4) {
        return 40;
    } else {
        return 0;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batidos Pitaya - Historial de Marcaciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
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
        }

        .container {
            max-width: 750px;
            /*Tarjeta contenedora de los textos y campos en pantalla*/
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .logo {
            height: 50px;
            display: block;
            margin: 0 auto 20px;
        }

        h1 {
            text-align: center;
            color: #0E544C;
            margin-bottom: 20px;
        }

        h2 {
            color: #0E544C;
            margin: 20px 0 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
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

        .btn {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: #0E544C;
        }

        .btn-secundario {
            background: #6c757d;
        }

        .btn-secundario:hover {
            background: #5a6268;
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

        .info-operario {
            background: #f0f9f8;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #51B8AC;
        }

        .info-operario p {
            margin: 5px 0;
            color: #0E544C;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin: 20px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            color: #0E544C;
            font-weight: bold;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .quincena-info {
            background-color: #e9f7ef;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }

        .btn-regresar {
            display: inline-block;
            margin-top: 15px;
        }

        .btn-regresar i {
            margin-right: 5px;
        }

        .tardanza {
            color: #dc3545;
            font-weight: bold;
        }

        .puntual {
            color: #28a745;
        }

        .no-programado {
            color: #6c757d;
            font-style: italic;
        }

        .no-laborable {
            color: #ffc107;
        }

        .incompleto {
            color: #fd7e14;
        }

        .estadisticas-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            overflow-x: auto;
            /* Permite scroll horizontal si no caben */
            padding-bottom: 10px;
            /* Espacio para el scroll */
            scrollbar-width: thin;
            /* Para Firefox */
            -webkit-overflow-scrolling: touch;
            /* Mejor scrolling en iOS */
        }

        .estadisticas-container::-webkit-scrollbar {
            height: 5px;
            /* Scrollbar más delgada */
        }

        .estadistica-card {
            flex: 0 0 auto;
            /* No crecer, no encoger, tamaño según contenido */
            min-width: 90px;
            /* Ancho mínimo para cada tarjeta */
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .estadistica-titulo {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .estadistica-valor {
            font-size: 24px;
            font-weight: bold;
            color: #0E544C;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            th,
            td {
                padding: 8px 10px;
                font-size: 14px;
            }
        }

        .estado-especial {
            color: #9c27b0;
            font-style: italic;
        }

        .tardanzas-faltas-mes {
            margin-bottom: 30px;
            background: #fff8e1;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
        }

        .tardanzas-faltas-mes h2 {
            color: #ff6f00;
        }

        .estado-especial {
            color: #9c27b0;
            font-style: italic;
        }

        .no-laborable {
            color: #ff9800;
            font-style: italic;
        }
    </style>

    <script>
        // Detectar cuando la página se carga desde caché (como al presionar Atrás)
        window.onpageshow = function (event) {
            if (event.persisted) {
                // Forzar recarga limpia
                window.location.href = window.location.href.split('?')[0] + '?clean_cache=1';
            }
        };

        // Si el parámetro clean_cache está presente, limpiar localStorage/sessionStorage
        if (window.location.search.includes('clean_cache')) {
            sessionStorage.clear();
            localStorage.clear();
        }

        // Detectar cierre de pestaña/ventana
        window.addEventListener('beforeunload', function () {
            // Usar sessionStorage para marcar que la pestaña se está cerrando
            sessionStorage.setItem('cerrando_pestana', 'true');
        });

        // Al cargar la página, verificar si es una nueva pestaña
        window.addEventListener('load', function () {
            if (!sessionStorage.getItem('cerrando_pestana') &&
                performance.navigation.type === performance.navigation.TYPE_RELOAD) {
                // La página se recargó manualmente, no hacer nada
            } else {
                // Es una nueva pestaña o navegación, limpiar el marcador
                sessionStorage.removeItem('cerrando_pestana');
            }
        });

        // Técnica para evitar autocompletado en Chrome
        if (window.chrome) {
            document.getElementById('usuario').autocomplete = 'new-password';
            document.getElementById('clave').autocomplete = 'new-password';
        }

        // Limpiar campos al cargar
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(function () {
                document.getElementById('usuario').value = '';
                document.getElementById('clave').value = '';
            }, 0);
        });
    </script>
</head>

<body>
    <div class="container">
        <img src="../../core/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
        <h1>Indicadores de Asistencia</h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (!$operario): ?>
            <form method="POST" autocomplete="off">
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

                <div style="text-align:center;">
                    <button type="submit" class="btn">Ver Historial del Colaborador(a)</button>

                    <!-- Botón de Regresar a Módulo -->
                    <a href="/modulos/sucursales/index.php" class="btn btn-secundario btn-regresar">
                        <i class="fas fa-arrow-left"></i> Regresar
                    </a>
                </div>
            </form>
        <?php else: ?>
            <div class="info-operario" style="display:none;">
                <h2>Información del Colaborador(a)</h2>
                <p><strong>Nombre:</strong> <?= htmlspecialchars($operario['Nombre'] . ' ' . $operario['Apellido']) ?></p>
                <p><strong>Cargos Actuales:</strong>
                    <?php
                    $cargos = obtenerCargosOperario($operario['CodOperario']);
                    echo htmlspecialchars(implode(', ', $cargos));
                    ?>
                </p>
                <p><strong>Sucursales Asignadas:</strong>
                    <?php
                    $sucursales = obtenerSucursalesOperario($operario['CodOperario']);
                    echo htmlspecialchars(implode(', ', $sucursales));
                    ?>
                </p>
                <?php if ($categoriaOperario): ?>
                    <p><strong>Categoría Actual:</strong>
                        <?= htmlspecialchars($categoriaOperario['NombreCategoria']) ?>
                        <!--(Peso: <?= htmlspecialchars($categoriaOperario['Peso']) ?>)-->
                    </p>
                <?php else: ?>
                    <p><strong>Categoría Actual:</strong> No asignada</p>
                <?php endif; ?>
            </div>

            <?php if ($categoriaOperario && $categoriaOperario['Peso'] != 0.0): ?>
                <!-- TÍTULO DE CUADROS DE ESTADÍSTICAS -->
                <h2 style="text-align: center; margin: 20px 0; color: #0E544C; display:none;">Indicadores Quincenales</h2>

                <?php
                // Obtener estadísticas de la quincena actual
                if ($operario) {
                    $estadisticasQuincena = obtenerEstadisticasQuincenaOperario($operario['CodOperario']);

                    // Calcular porcentaje de puntualidad
                    $tardanzasEjecutadas = $estadisticasQuincena['tardanzas']['tardanzas_ejecutadas'] ?? 0;
                    $porcentajePuntualidad = calcularPuntualidad($tardanzasEjecutadas);

                    // DEBUG TEMPORAL - mostrar información
                    echo "<!-- DEBUG: ";
                    echo "Fecha hoy: " . date('Y-m-d') . " | ";
                    echo "Rango quincena: " . $estadisticasQuincena['rango_quincena']['inicio'] . " al " . $estadisticasQuincena['rango_quincena']['fin'];
                    echo " -->";
                }
                ?>

                <!-- Estadísticas de la quincena en una sola fila -->
                <div class="estadisticas-container">
                    <!-- Faltas Ejecutadas -->
                    <div class="estadistica-card">
                        <div class="estadistica-titulo">Faltas</div>
                        <div class="estadistica-valor"><?= $estadisticasQuincena['faltas']['faltas_ejecutadas'] ?? 0 ?></div>
                        <small style="display:none;">Ejecutadas</small>
                    </div>

                    <!-- Tardanzas Ejecutadas -->
                    <div class="estadistica-card">
                        <div class="estadistica-titulo">Tardanzas</div>
                        <div class="estadistica-valor"><?= $estadisticasQuincena['tardanzas']['tardanzas_ejecutadas'] ?? 0 ?>
                        </div>
                        <small style="display:none;">Ejecutadas</small>
                    </div>

                    <!-- Turnos Nocturnos -->
                    <div class="estadistica-card">
                        <div class="estadistica-titulo">Turnos Nocturnos</div>
                        <div class="estadistica-valor"><?= $estadisticasQuincena['turnos_nocturnos'] ?? 0 ?></div>
                        <small style="display:none;">Salida después de 8pm</small>
                    </div>

                    <!-- Puntualidad -->
                    <div class="estadistica-card" style="display:none;">
                        <div class="estadistica-titulo">Puntualidad</div>
                        <div class="estadistica-valor porcentaje-puntualidad porcentaje-<?= $porcentajePuntualidad ?>">
                            <?= $porcentajePuntualidad ?>%
                        </div>
                        <small>Tardanzas: <?= $tardanzasEjecutadas ?></small>
                    </div>
                </div>

                <!-- Información de la quincena -->
                <div
                    style="margin: 15px 0; padding: 10px; background-color: #e9f7ef; border-radius: 5px; text-align: center; display:none;">
                    <strong><?= $estadisticasQuincena['rango_quincena']['nombre'] ?? 'Quincena Actual' ?></strong><br>
                    Del <?= formatoFecha($estadisticasQuincena['rango_quincena']['inicio'] ?? '') ?> al
                    <?= formatoFecha($estadisticasQuincena['rango_quincena']['fin'] ?? '') ?>
                </div>

                <!-- Detalles de los cálculos (opcional, puedes ocultarlo) -->
                <div
                    style="margin: 15px 0; padding: 15px; background-color: #f8f9fa; border-radius: 5px; font-size: 14px; display: none;">
                    <strong>Detalles del cálculo:</strong><br>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                        <div>
                            <strong>Faltas:</strong><br>
                            - Automáticas: <?= $estadisticasQuincena['faltas']['faltas_automaticas'] ?? 0 ?><br>
                            - Justificadas: <?= $estadisticasQuincena['faltas']['faltas_justificadas'] ?? 0 ?><br>
                            - <strong>Ejecutadas: <?= $estadisticasQuincena['faltas']['faltas_ejecutadas'] ?? 0 ?></strong>
                        </div>
                        <div>
                            <strong>Tardanzas:</strong><br>
                            - Totales: <?= $estadisticasQuincena['tardanzas']['total_tardanzas'] ?? 0 ?><br>
                            - Justificadas: <?= $estadisticasQuincena['tardanzas']['tardanzas_justificadas'] ?? 0 ?><br>
                            - <strong>Ejecutadas:
                                <?= $estadisticasQuincena['tardanzas']['tardanzas_ejecutadas'] ?? 0 ?></strong>
                        </div>
                    </div>
                </div>

                <style>
                    .estadisticas-container {
                        display: flex;
                        justify-content: space-between;
                        /* Distribuye el espacio uniformemente */
                        flex-wrap: nowrap;
                        /* Evita que las tarjetas salten a otra línea */
                        overflow-x: auto;
                        /* Permite scroll horizontal si no caben */
                        gap: 5px;
                        padding: 3px;
                        width: 100%;
                        /* Ocupa todo el ancho disponible */
                        margin: 0 auto;
                    }

                    .estadistica-card {
                        flex: 1;
                        /* Todas las tarjetas crecen por igual */
                        min-width: 120px;
                        /* Ancho mínimo para cada tarjeta */
                        max-width: 150px;
                        /* Ancho máximo opcional */
                        padding: 10px 15px;
                        border-radius: 8px;
                        background-color: #f9f9f9;
                        box-shadow: 1px 1px 5px rgba(0, 0, 0, 0.1);
                        text-align: center;
                        border: 1px solid #e0e0e0;
                    }

                    /* Agregar esto en la sección de estilos */
                    .porcentaje-puntualidad {
                        font-weight: bold;
                        font-size: 0.8em;
                    }

                    /* Colores según el porcentaje */
                    .porcentaje-100 {
                        color: #28a745;
                    }

                    /* Verde */
                    .porcentaje-60 {
                        color: #ffc107;
                    }

                    /* Amarillo */
                    .porcentaje-40 {
                        color: #fd7e14;
                    }

                    /* Naranja */
                    .porcentaje-0 {
                        color: #dc3545;
                    }

                    /* Rojo */

                    .estadistica-titulo {
                        font-size: 14px;
                        color: #6c757d;
                        margin-bottom: 8px;
                        font-weight: bold;
                    }

                    .estadistica-valor {
                        font-size: 24px;
                        font-weight: bold;
                        color: #0E544C;
                        margin-bottom: 5px;
                    }

                    /* Estilo para el texto pequeño */
                    .estadistica-card small {
                        font-size: 12px;
                        color: #666;
                        margin-top: 3px;
                        display: block;
                        /*probar quitar o dejar*/
                    }

                    @media (max-width: 768px) {
                        .estadisticas-container {
                            display: grid;
                            grid-template-columns: repeat(3, 1fr);
                            /* 3 columnas */
                            gap: 8px;
                        }

                        .estadistica-card {
                            min-width: unset;
                            /* Elimina el ancho mínimo fijo */
                            max-width: 100%;
                            /* Ocupa el ancho completo de la celda */
                            padding: 8px;
                        }

                        .estadistica-valor {
                            font-size: 20px;
                        }
                    }
                </style>

                <!-- Leyenda de Puntualidad -->
                <div
                    style="margin: 15px 0; padding: 10px; background-color: #f0f9f8; border-radius: 5px; font-size: 14px; display:none;">
                    <strong>Leyenda de Puntualidad:</strong><br>
                    <ul style="margin-top: 5px; padding-left: 20px;">
                        <li>0-2 tardanzas: 100% de puntualidad</li>
                        <li>3 tardanzas: 60% de puntualidad</li>
                        <li>4 tardanzas: 40% de puntualidad</li>
                        <li>Más de 4 tardanzas: 0% de puntualidad</li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Nueva sección para el historial del mes completo -->
            <div style="margin-top: 40px;">
                <div class="quincena-info">
                    <?= $rangoMes['nombre'] ?> del
                    <?= formatoFecha($rangoMes['inicio']) ?> al
                    <?= formatoFecha($rangoMes['fin']) ?>
                </div>

                <?php if (empty($historialMes)): ?>
                    <div class="text-center" style="padding: 20px;">
                        <p>No se encontraron registros de marcaciones para este mes.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Sucursal</th>
                                    <th>Entrada</th>
                                    <th>Salida</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historialMes as $registro): ?>
                                    <tr>
                                        <td><?= formatoFecha($registro['fecha']) ?></td>
                                        <td><?= htmlspecialchars($registro['sucursal_nombre'] ?? 'Desconocida') ?></td>
                                        <td>
                                            <?= $registro['hora_ingreso'] ? date('h:i A', strtotime($registro['hora_ingreso'])) : 'No marcada' ?>
                                            <?php if ($registro['estado_clase'] === 'tardanza'): ?>
                                                <span class="tardanza" title="Marcación tardía"><i
                                                        class="fas fa-exclamation-triangle"></i></span>
                                            <?php elseif ($registro['estado_clase'] === 'puntual'): ?>
                                                <span class="puntual" title="Marcación a tiempo"><i
                                                        class="fas fa-check-circle"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $registro['hora_salida'] ? date('h:i A', strtotime($registro['hora_salida'])) : 'No marcada' ?>
                                        </td>
                                        <td>
                                            <span class="<?= $registro['estado_clase'] ?>">
                                                <?= $registro['estado'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display:none;" class="quincena-info">
                <?= $rangoQuincena['nombre'] ?> del
                <?= formatoFecha($rangoQuincena['inicio']) ?> al
                <?= formatoFecha($rangoQuincena['fin']) ?>
            </div>

            <?php if (empty($historial)): ?>
                <div style="display:none;" class="text-center" style="padding: 20px;">
                    <p>No se encontraron registros de marcaciones para la quincena actual.</p>
                </div>
            <?php else: ?>
                <div style="display:none;" class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Sucursal</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $registro): ?>
                                <tr>
                                    <td><?= formatoFecha($registro['fecha']) ?></td>
                                    <td><?= htmlspecialchars($registro['sucursal_nombre'] ?? 'Desconocida') ?></td>
                                    <td>
                                        <?= $registro['hora_ingreso'] ? date('h:i A', strtotime($registro['hora_ingreso'])) : 'No marcada' ?>
                                        <?php if ($registro['estado_clase'] === 'tardanza'): ?>
                                            <span class="tardanza" title="Marcación tardía"><i
                                                    class="fas fa-exclamation-triangle"></i></span>
                                        <?php elseif ($registro['estado_clase'] === 'puntual'): ?>
                                            <span class="puntual" title="Marcación a tiempo"><i class="fas fa-check-circle"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $registro['hora_salida'] ? date('h:i A', strtotime($registro['hora_salida'])) : 'No marcada' ?>
                                    </td>
                                    <td>
                                        <span class="<?= $registro['estado_clase'] ?>">
                                            <?= $registro['estado'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($categoriaOperario && $categoriaOperario['Peso'] != 0.0): ?>
                <!-- Reporte de Tardanzas Manuales -->
                <div class="estadisticas-sucursales">
                    <h3>Reporte de Tardanzas</h3>
                    <?php
                    if ($operario) {
                        // Obtener primer y último día del mes actual
                        $fechaInicioQuincena = $rangoQuincena['inicio'];
                        $fechaFinQuincena = $rangoQuincena['fin'];

                        $sqlTardanzas = "SELECT 
                            tm.fecha_tardanza as fecha,
                            s.nombre as sucursal_nombre,
                            tm.minutos_tardanza as minutos,
                            tm.tipo_justificacion as tipo,
                            tm.estado,
                            tm.observaciones
                        FROM TardanzasManuales tm
                        JOIN sucursales s ON tm.cod_sucursal = s.codigo
                        WHERE tm.cod_operario = ?
                        AND tm.fecha_tardanza BETWEEN ? AND ?
                        ORDER BY tm.fecha_tardanza DESC";

                        $stmtTardanzas = ejecutarConsulta($sqlTardanzas, [
                            $operario['CodOperario'],
                            $primerDiaMes,
                            $ultimoDiaMes
                        ]);

                        $tardanzas = $stmtTardanzas ? $stmtTardanzas->fetchAll() : [];
                        ?>

                        <?php if (empty($tardanzas)): ?>
                            <div class="text-center" style="padding: 20px;">
                                <p>No se encontraron registros de tardanzas para la quincena actual.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Sucursal</th>
                                            <th>Fecha</th>
                                            <th style="display:none;">Minutos</th>
                                            <th>Tipo Justificación</th>
                                            <th>Estado</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tardanzas as $tardanza): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($tardanza['sucursal_nombre']) ?></td>
                                                <td><?= formatoFecha($tardanza['fecha']) ?></td>
                                                <td style="display:none;"><?= $tardanza['minutos'] ?></td>
                                                <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $tardanza['tipo']))) ?></td>
                                                <td><?= htmlspecialchars($tardanza['estado']) ?></td>
                                                <td><?= htmlspecialchars($tardanza['observaciones'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php } ?>
                </div>

                <!-- Reporte de Horas Extras -->
                <div class="estadisticas-sucursales">
                    <h3>Reporte de Horas Extras</h3>
                    <?php
                    if ($operario) {
                        // Obtener primer y último día del mes actual
                        $fechaInicioQuincena = $rangoQuincena['inicio'];
                        $fechaFinQuincena = $rangoQuincena['fin'];

                        $sqlHorasExtras = "SELECT 
                                hem.fecha as fecha,
                                s.nombre as sucursal_nombre,
                                hem.horas_extras as horas,
                                hem.estado,
                                hem.observaciones
                            FROM horas_extras_manual hem
                            JOIN sucursales s ON hem.cod_sucursal = s.codigo
                            WHERE hem.cod_operario = ?
                            AND hem.fecha BETWEEN ? AND ?
                            ORDER BY hem.fecha DESC";

                        $stmtHorasExtras = ejecutarConsulta($sqlHorasExtras, [
                            $operario['CodOperario'],
                            $primerDiaMes,
                            $ultimoDiaMes
                        ]);

                        $horasExtras = $stmtHorasExtras ? $stmtHorasExtras->fetchAll() : [];
                        ?>

                        <?php if (empty($horasExtras)): ?>
                            <div class="text-center" style="padding: 20px;">
                                <p>No se encontraron registros de horas extras para la quincena actual.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Sucursal</th>
                                            <th>Fecha</th>
                                            <th>Horas Extras</th>
                                            <th>Estado</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($horasExtras as $horaExtra): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($horaExtra['sucursal_nombre']) ?></td>
                                                <td><?= formatoFecha($horaExtra['fecha']) ?></td>
                                                <td><?= number_format($horaExtra['horas'], 2) ?></td>
                                                <td><?= htmlspecialchars($horaExtra['estado']) ?></td>
                                                <td><?= htmlspecialchars($horaExtra['observaciones'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php } ?>
                </div>

                <!-- Reporte de Faltas Manuales -->
                <div class="estadisticas-sucursales">
                    <h3>Reporte de Faltas</h3>
                    <?php
                    if ($operario) {
                        // Obtener primer y último día del mes actual
                        //$primerDiaMes = date('Y-m-01');
                        //$ultimoDiaMes = date('Y-m-t');
            
                        $fechaInicioQuincena = $rangoQuincena['inicio'];
                        $fechaFinQuincena = $rangoQuincena['fin'];

                        $sqlFaltas = "SELECT 
                        fm.fecha_falta as fecha,
                        s.nombre as sucursal_nombre,
                        fm.tipo_falta as tipo,
                        fm.observaciones
                    FROM faltas_manual fm
                    JOIN sucursales s ON fm.cod_sucursal = s.codigo
                    WHERE fm.cod_operario = ?
                    AND fm.fecha_falta BETWEEN ? AND ?
                    ORDER BY fm.fecha_falta DESC";

                        $stmtFaltas = ejecutarConsulta($sqlFaltas, [
                            $operario['CodOperario'],
                            $primerDiaMes,
                            $ultimoDiaMes
                        ]);

                        $faltas = $stmtFaltas ? $stmtFaltas->fetchAll() : [];
                        ?>

                        <?php if (empty($faltas)): ?>
                            <div class="text-center" style="padding: 20px;">
                                <p>No se encontraron registros de faltas para la quincena actual.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Sucursal</th>
                                            <th>Fecha</th>
                                            <th>Tipo de Falta</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($faltas as $falta): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($falta['sucursal_nombre']) ?></td>
                                                <td><?= formatoFecha($falta['fecha']) ?></td>
                                                <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $falta['tipo']))) ?></td>
                                                <td><?= htmlspecialchars($falta['observaciones'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php } ?>
                </div>
            <?php endif; ?>

            <?php /* if ($operario && tieneSucursalesEspeciales($operario['CodOperario'])): */ ?>
            <!-- Nueva sección de Tardanzas y Faltas del Mes - SOLO si no tiene sucursales 6 o 18, en function tieneSucursalesEspeciales($codOperario) -->
            <div style="display:none;" class="tardanzas-faltas-mes">
                <h2>Tardanzas y Faltas del Mes Actual</h2>

                <?php
                $tardanzasFaltas = obtenerTardanzasFaltasMes($operario['CodOperario']);
                ?>

                <?php if (empty($tardanzasFaltas)): ?>
                    <div class="text-center" style="padding: 20px;">
                        <p>No se encontraron tardanzas o faltas este mes.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Sucursal</th>
                                    <th>Fecha</th>
                                    <th>Hora Entrada Marcada</th>
                                    <th>Minutos</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tardanzasFaltas as $incidencia): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($incidencia['sucursal']) ?></td>
                                        <td><?= formatoFecha($incidencia['fecha']) ?></td>
                                        <td><?= $incidencia['hora_entrada_marcada'] ?? '-' ?></td>
                                        <td><?= $incidencia['minutos'] > 0 ? $incidencia['minutos'] : '-' ?></td>
                                        <td>
                                            <?php if ($incidencia['tipo'] === 'Tardanza'): ?>
                                                <span class="tardanza"><?= $incidencia['tipo_incidencia'] ?></span>
                                            <?php else: ?>
                                                <span class="incompleto"><?= $incidencia['tipo_incidencia'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($incidencia['estado_horario'] === 'Activo'): ?>
                                                <span class="<?= $incidencia['tipo'] === 'Tardanza' ? 'tardanza' : 'incompleto' ?>">
                                                    <?= $incidencia['tipo'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="estado-especial"><?= $incidencia['estado_horario'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php /* endif; */ ?>

            <!-- Estadísticas detalladas por sucursal del Colaborador/a -->
            <div class="estadisticas-sucursales">
                <h3>Estadísticas por Sucursal (Quincena Actual)</h3>

                <!-- DEBUG TEMPORAL - Mostrar datos de sucursales -->
                <div style="display: none; background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px;">
                    <strong>DEBUG Sucursales:</strong>
                    <pre><?php
                    if ($operario) {
                        echo "Operario: " . $operario['CodOperario'] . "\n";
                        echo "Rango: " . $estadisticasQuincena['rango_quincena']['inicio'] . " al " . $estadisticasQuincena['rango_quincena']['fin'] . "\n";
                        echo "Sucursales encontradas: " . count($estadisticasQuincena['por_sucursal']) . "\n";
                        print_r($estadisticasQuincena['por_sucursal']);
                    }
                    ?></pre>
                </div>

                <?php if (!empty($estadisticasQuincena['por_sucursal'])): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Sucursal</th>
                                    <th>Tardanzas</th>
                                    <th>Turnos Nocturnos</th>
                                    <th>Omisiones</th>
                                    <th>Días Fuera Horario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estadisticasQuincena['por_sucursal'] as $codigo => $sucursal): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sucursal['nombre']) ?></td>
                                        <td><?= $sucursal['tardanzas_ejecutadas'] ?? 0 ?></td>
                                        <td><?= $sucursal['turnos_nocturnos'] ?? 0 ?></td>
                                        <td><?= $sucursal['omisiones_marcacion'] ?? 0 ?></td>
                                        <td><?= $sucursal['dias_fuera_horario'] ?? 0 ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center" style="padding: 20px;">
                        <p>No se encontraron estadísticas por sucursal para la quincena actual.</p>
                        <small>El colaborador no tiene marcaciones registradas en diferentes sucursales durante esta
                            quincena.</small>
                    </div>
                <?php endif; ?>
            </div>

            <div style="text-align:center;">
                <a href="historial_marcaciones_sucursales.php?logout=1" class="btn btn-secundario">
                    <i class="fas fa-sign-out-alt"></i> Regresar
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Técnica para evitar autocompletado en Chrome
        if (window.chrome) {
            document.getElementById('usuario').autocomplete = 'new-password';
            document.getElementById('clave').autocomplete = 'new-password';
        }

        // Limpiar campos al cargar
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(function () {
                document.getElementById('usuario').value = '';
                document.getElementById('clave').value = '';
            }, 0);
        });
    </script>

    <script>
        // Control de pestañas simultáneas mejorado
        document.addEventListener('DOMContentLoaded', function () {
            const PAGINA_MARCACION = 'marcacion';
            const PAGINA_HISTORIAL = 'historial';
            const TIMEOUT_REDIRECCION = 1000; // 1 segundo

            // Verificar si la página de marcación está abierta
            if (localStorage.getItem('pagina_activa') === PAGINA_MARCACION) {
                alert('Error: No puedes abrir el historial de marcaciones mientras tengas abierta la página de marcación.\n\nPor favor, cierra la pestaña de marcación primero.');
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, TIMEOUT_REDIRECCION);
                return;
            }

            // Marcar que esta página está abierta
            localStorage.setItem('pagina_activa', PAGINA_HISTORIAL);
            localStorage.setItem('timestamp_actividad', Date.now());

            // Limpiar al cerrar la pestaña
            window.addEventListener('beforeunload', function () {
                if (localStorage.getItem('pagina_activa') === PAGINA_HISTORIAL) {
                    localStorage.removeItem('pagina_activa');
                }
            });

            // Detectar cambios entre pestañas
            window.addEventListener('storage', function (e) {
                if (e.key === 'pagina_activa' && e.newValue === PAGINA_MARCACION) {
                    alert('Atención: Se ha abierto la página de marcación en otra pestaña.\n\nEsta página se cerrará automáticamente.');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, TIMEOUT_REDIRECCION);
                }
            });

            // Verificación periódica para casos donde el evento storage no se dispara
            setInterval(() => {
                if (localStorage.getItem('pagina_activa') === PAGINA_MARCACION) {
                    alert('Se detectó la página de marcación abierta en otra pestaña.\n\nRedirigiendo...');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, TIMEOUT_REDIRECCION);
                }
            }, 3000); // Verificar cada 3 segundos
        });
    </script>
</body>

</html>