<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
require_once '../../core/auth/auth.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/layout/menu_lateral.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo RH (Código 13 para Jefe de RH)
if (!verificarAccesoCargo([13, 16, 39, 30, 37, 28]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../index.php');
    exit();
}

// Obtener todas las sucursales
$sucursales = obtenerTodasSucursales();

// Obtener cantidad de operarios con documentos incompletos
$operariosIncompletos = obtenerCantidadOperariosIncompletos();

$totalActivos = obtenerCantidadOperariosActivosFiltrados();

/**
 * Obtiene la cantidad de operarios activos con documentos obligatorios incompletos
 */
function obtenerCantidadOperariosIncompletos()
{
    global $conn;

    // Pestañas que tienen documentos obligatorios
    $pestañasConObligatorios = ['datos-personales', 'inss', 'contrato'];
    $operariosIncompletos = [];

    // Obtener todos los operarios activos
    $stmt = $conn->prepare("
        SELECT DISTINCT CodOperario 
        FROM Operarios o 
        WHERE o.Operativo = 1
        AND o.CodOperario NOT IN (
            SELECT DISTINCT anc2.CodOperario 
            FROM AsignacionNivelesCargos anc2
            WHERE anc2.CodNivelesCargos = 27
            AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
        )
    ");
    $stmt->execute();
    $operariosActivos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Verificar cada operario activo
    foreach ($operariosActivos as $codOperario) {
        foreach ($pestañasConObligatorios as $pestaña) {
            $estado = verificarEstadoDocumentosObligatorios($codOperario, $pestaña);

            // Si tiene pendientes o parcial en cualquier pestaña, es incompleto
            if ($estado === 'pendiente' || $estado === 'parcial') {
                $operariosIncompletos[] = $codOperario;
                break; // Ya encontramos un documento pendiente, no seguir buscando
            }
        }
    }

    return count(array_unique($operariosIncompletos));
}

// Funciones necesarias para calcular faltas pendientes (las mismas que en líderes)
function obtenerTotalFaltasAutomaticas($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    // 1. Obtener todos los operarios asignados a la sucursal en el rango de fechas
    $operarios = obtenerOperariosSucursalEnRango($codSucursal, $fechaDesde, $fechaHasta);
    $totalFaltas = 0;

    foreach ($operarios as $operario) {
        // 2. Para cada operario, verificar días laborables en el rango
        $diasLaborables = obtenerDiasLaborablesOperario($operario['CodOperario'], $codSucursal, $fechaDesde, $fechaHasta);

        foreach ($diasLaborables as $dia) {
            // Filtrar fechas anteriores al 14 de julio de 2025
            if (strtotime($dia['fecha']) < strtotime('2025-07-14')) {
                continue;
            }

            // 3. Verificar si hay marcación de entrada para ese día
            $marcacion = obtenerMarcacionEntrada($operario['CodOperario'], $dia['fecha']);

            if (!$marcacion) {
                $totalFaltas++;
            }
        }
    }

    return $totalFaltas;
}

// Funciones necesarias para calcular tardanzas pendientes
function obtenerTotalTardanzasAutomaticas($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    // 1. Obtener todos los operarios asignados a la sucursal en el rango de fechas
    $operarios = obtenerOperariosSucursalEnRango($codSucursal, $fechaDesde, $fechaHasta);
    $totalTardanzas = 0;

    foreach ($operarios as $operario) {
        // 2. Para cada operario, verificar días laborables en el rango
        $diasLaborables = obtenerDiasLaborablesOperario($operario['CodOperario'], $codSucursal, $fechaDesde, $fechaHasta);

        foreach ($diasLaborables as $dia) {
            // Filtrar fechas anteriores al 14 de julio de 2025
            if (strtotime($dia['fecha']) < strtotime('2025-07-14')) {
                continue;
            }

            // 3. Verificar si hay marcación de entrada para ese día
            $marcacion = obtenerMarcacionEntrada($operario['CodOperario'], $dia['fecha']);

            if ($marcacion) {
                // 4. Verificar si hay tardanza comparando con el horario programado
                $tardanza = verificarTardanza($operario['CodOperario'], $codSucursal, $dia['fecha'], $marcacion['hora_ingreso']);
                if ($tardanza) {
                    $totalTardanzas++;
                }
            }
        }
    }

    return $totalTardanzas;
}

function obtenerOperariosSucursalEnRango($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, s.nombre as sucursal_nombre
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        JOIN sucursales s ON anc.Sucursal = s.codigo
        WHERE anc.Sucursal = ?
        AND o.Operativo = 1
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
        ORDER BY o.Nombre, o.Apellido
    ");
    $stmt->execute([$codSucursal, $fechaDesde, $fechaHasta]);
    return $stmt->fetchAll();
}

function obtenerDiasLaborablesOperario($codOperario, $codSucursal, $fechaDesde, $fechaHasta)
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
            AND cod_sucursal = ?
            AND id_semana_sistema = ?
            LIMIT 1
        ");
        $stmt->execute([$codOperario, $codSucursal, $semana['id']]);
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

function obtenerTotalFaltasManuales($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM faltas_manual 
        WHERE cod_sucursal = ? 
        AND fecha_falta BETWEEN ? AND ?
        AND fecha_falta >= '2025-07-14'  -- Solo contar faltas desde el 14/07/2025
    ");
    $stmt->execute([$codSucursal, $fechaDesde, $fechaHasta]);
    $result = $stmt->fetch();

    return $result['total'] ?? 0;
}

// Calcular faltas pendientes para todas las sucursales
$faltasPendientes = 0;
if (!empty($sucursales)) {
    // Establecer rango del mes actual por defecto
    $hoy = new DateTime();
    $fechaDesde = $hoy->format('Y-m-01');
    $fechaHasta = $hoy->format('Y-m-t');

    // Calcular para cada sucursal
    foreach ($sucursales as $sucursal) {
        $totalFaltasAuto = obtenerTotalFaltasAutomaticas($sucursal['codigo'], $fechaDesde, $fechaHasta);
        $totalFaltasManuales = obtenerTotalFaltasManuales($sucursal['codigo'], $fechaDesde, $fechaHasta);

        $pendientes = $totalFaltasAuto - $totalFaltasManuales;
        if ($pendientes > 0) {
            $faltasPendientes += $pendientes;
        }
    }
}

function verificarTardanza($codOperario, $codSucursal, $fecha, $horaMarcada)
{
    global $conn;

    // Obtener la semana a la que pertenece esta fecha
    $semana = obtenerSemanaPorFecha($fecha);
    if (!$semana)
        return false;

    // Obtener el horario programado para ese operario en esa semana y sucursal
    $horarioProgramado = obtenerHorarioOperacionesPorDia(
        $codOperario,
        $semana['id'],
        $codSucursal,
        $fecha
    );

    if ($horarioProgramado && $horarioProgramado['hora_entrada']) {
        // Calcular diferencia entre hora programada y hora marcada
        $horaProgramada = new DateTime($horarioProgramado['hora_entrada']);
        $horaMarcada = new DateTime($horaMarcada);

        // Considerar como tardanza si la hora marcada es posterior a la programada
        return $horaMarcada > $horaProgramada;
    }

    return false;
}

function obtenerTotalTardanzasManuales($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "SELECT COUNT(*) as total FROM TardanzasManuales WHERE fecha_tardanza BETWEEN ? AND ? AND fecha_tardanza >= '2025-07-14'";
    $params = [$fechaDesde, $fechaHasta];

    if (!empty($codSucursal)) {
        $sql .= " AND cod_sucursal = ?";
        $params[] = $codSucursal;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();

    return $result['total'] ?? 0;
}

// Calcular tardanzas pendientes para todas las sucursales
$tardanzasPendientes = 0;
if (!empty($sucursales)) {
    // Establecer rango del mes actual por defecto
    $hoy = new DateTime();
    $fechaDesde = $hoy->format('Y-m-01');
    $fechaHasta = $hoy->format('Y-m-t');

    // Calcular para cada sucursal
    foreach ($sucursales as $sucursal) {
        $totalTardanzasAuto = obtenerTotalTardanzasAutomaticas($sucursal['codigo'], $fechaDesde, $fechaHasta);
        $totalTardanzasManuales = obtenerTotalTardanzasManuales($sucursal['codigo'], $fechaDesde, $fechaHasta);

        $pendientes = $totalTardanzasAuto - $totalTardanzasManuales;
        if ($pendientes > 0) {
            $tardanzasPendientes += $pendientes;
        }
    }
}

// Calcular faltas pendientes de revisión
$faltasPendientesRevision = obtenerTotalFaltasPendientesRevisión();
$diasRestantesFaltas = calcularDiasRestantesRevisionFaltas();
$colorIndicadorFaltas = determinarColorIndicadorFaltas($diasRestantesFaltas);

// Obtener tardanzas y faltas pendientes para RH
$tardanzasPendientesRH = obtenerTardanzasPendientesRH();
$faltasPendientesRH = obtenerFaltasPendientesRH();

// Funciones para contratos próximos a vencer
function obtenerContratosProximosVencer()
{
    global $conn;

    $fechaHoy = new DateTime();
    $fechaLimite = new DateTime();
    $fechaLimite->modify('+1 month');

    $sql = "
        SELECT 
            c.*,
            o.CodOperario,
            CONCAT(
                TRIM(o.Nombre), 
                IF(o.Nombre2 IS NOT NULL AND o.Nombre2 != '', CONCAT(' ', TRIM(o.Nombre2)), ''), 
                ' ', 
                TRIM(o.Apellido),
                IF(o.Apellido2 IS NOT NULL AND o.Apellido2 != '', CONCAT(' ', TRIM(o.Apellido2)), '')
            ) as nombre_completo,
            s.nombre as sucursal_nombre,
            DATEDIFF(c.fin_contrato, CURDATE()) as dias_restantes
        FROM Contratos c
        JOIN Operarios o ON c.cod_operario = o.CodOperario
        LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        LEFT JOIN sucursales s ON anc.Sucursal = s.codigo
        WHERE c.fin_contrato IS NOT NULL 
        AND c.fin_contrato != '0000-00-00'
        AND c.fin_contrato >= CURDATE()
        AND c.fin_contrato <= ?
        AND o.Operativo = 1
        AND (c.fecha_salida IS NULL OR c.fecha_salida = '0000-00-00')
        GROUP BY c.codigo_manual_contrato
        ORDER BY c.fin_contrato ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$fechaLimite->format('Y-m-d')]);

    return $stmt->fetchAll();
}

// Calcular contratos próximos a vencer
$contratosProximos = obtenerContratosProximosVencer();
$totalContratosProximos = count($contratosProximos);

// Determinar color del indicador según la urgencia
function determinarColorIndicadorContratos($contratos)
{
    if (empty($contratos))
        return 'verde'; // Verde cuando no hay contratos

    // Buscar el contrato más próximo
    $diasMinimos = null;
    foreach ($contratos as $contrato) {
        if ($diasMinimos === null || $contrato['dias_restantes'] < $diasMinimos) {
            $diasMinimos = $contrato['dias_restantes'];
        }
    }

    if ($diasMinimos <= 7)
        return 'rojo';      // Menos de 1 semana
    if ($diasMinimos <= 15)
        return 'amarillo'; // Menos de 15 días
    return 'verde';                            // Más de 15 días
}

$colorIndicadorContratos = determinarColorIndicadorContratos($contratosProximos);

/**
 * Verifica días consecutivos sin marcación con estado Activo - VERSIÓN MEJORADA
 */
function verificarAusenciaConsecutiva($codOperario, $codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $maxConsecutivos = 0;
    $consecutivosActual = 0;
    $fechaActual = new DateTime($fechaHasta);
    $fechaFin = new DateTime($fechaDesde);

    // Recorrer hacia atrás desde hoy hasta la fecha desde
    while ($fechaActual >= $fechaFin) {
        $fecha = $fechaActual->format('Y-m-d');

        // Verificar si este día debería haber trabajado (estado Activo)
        $deberiaTrabajar = deberiaTrabajarDia($codOperario, $codSucursal, $fecha);
        $tieneMarcacion = tieneMarcacion($codOperario, $codSucursal, $fecha); // Ahora usa la versión sin sucursal

        if ($deberiaTrabajar && !$tieneMarcacion) {
            $consecutivosActual++;
            $maxConsecutivos = max($maxConsecutivos, $consecutivosActual);
        } else {
            $consecutivosActual = 0;
        }

        $fechaActual->modify('-1 day');
    }

    return $maxConsecutivos;
}

/**
 * Verifica si un operario debería trabajar en una fecha específica (estado Activo u Otra.Tienda)
 */
function deberiaTrabajarDia($codOperario, $codSucursal, $fecha)
{
    global $conn;

    // Obtener la semana del sistema para esta fecha
    $semana = obtenerSemanaPorFecha($fecha);
    if (!$semana)
        return false;

    // Obtener el día de la semana (1=lunes, 7=domingo)
    $diaSemana = date('N', strtotime($fecha));
    $dias = ['', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $diaColumna = $dias[$diaSemana];

    $stmt = $conn->prepare("
        SELECT {$diaColumna}_estado as estado
        FROM HorariosSemanalesOperaciones
        WHERE cod_operario = ? 
        AND cod_sucursal = ?
        AND id_semana_sistema = ?
        LIMIT 1
    ");

    $stmt->execute([$codOperario, $codSucursal, $semana['id']]);
    $result = $stmt->fetch();

    // Considerar tanto "Activo" como "Otra.Tienda"
    return ($result && ($result['estado'] === 'Activo' || $result['estado'] === 'Otra.Tienda'));
}

/**
 * Verifica si tiene marcación en una fecha específica - VERSIÓN MEJORADA (sin filtro de sucursal)
 */
function tieneMarcacion($codOperario, $codSucursal, $fecha)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM marcaciones 
        WHERE CodOperario = ? 
        AND fecha = ?
        AND (hora_ingreso IS NOT NULL OR hora_salida IS NOT NULL)
    ");

    $stmt->execute([$codOperario, $fecha]);
    $result = $stmt->fetch();

    return ($result && $result['total'] > 0);
}

/**
 * Obtiene la última marcación del operario - VERSIÓN MEJORADA (sin filtro de sucursal)
 */
function obtenerUltimaMarcacion($codOperario, $codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT fecha, sucursal_codigo, hora_ingreso, hora_salida
        FROM marcaciones 
        WHERE CodOperario = ? 
        AND fecha BETWEEN ? AND ?
        AND (hora_ingreso IS NOT NULL OR hora_salida IS NOT NULL)
        ORDER BY fecha DESC, 
                 GREATEST(COALESCE(hora_ingreso, '00:00:00'), COALESCE(hora_salida, '00:00:00')) DESC
        LIMIT 1
    ");

    $stmt->execute([$codOperario, $fechaDesde, $fechaHasta]);
    $result = $stmt->fetch();

    return $result ? $result['fecha'] : null;
}

// ========== FUNCIONES PARA INDICADORES DE TARDANZAS Y FALTAS (COMO LÍDERES) ==========

/**
 * Obtiene el total de tardanzas pendientes de reportar para RH (todas las sucursales)
 */
function obtenerTardanzasPendientesRH()
{
    global $conn;

    // Determinar el periodo a revisar según el día del mes (misma lógica que líderes)
    $hoy = new DateTime();
    $diaMes = (int) $hoy->format('d');
    $diasRestantes = calcularDiasRestantesReporte();

    if ($diaMes <= 2) {
        // Días 1-2: revisar mes anterior
        $mesRevisar = new DateTime('first day of last month');
        $fechaDesde = $mesRevisar->format('Y-m-01');
        $fechaHasta = $mesRevisar->format('Y-m-t');
        $periodo = 'mes_anterior';
        $mesNombre = obtenerMesEspanol($mesRevisar) . ' ' . $mesRevisar->format('Y');
    } else {
        // Días 3+: revisar mes actual
        $fechaDesde = $hoy->format('Y-m-01');
        $fechaHasta = $hoy->format('Y-m-t');
        $periodo = 'mes_actual';
        $mesNombre = obtenerMesEspanol($hoy) . ' ' . $hoy->format('Y');
    }

    // Obtener todas las sucursales para RH
    $sucursales = obtenerTodasSucursales();
    if (empty($sucursales)) {
        return [
            'total' => 0,
            'color' => 'verde',
            'texto' => 'Sin tardanzas pendientes',
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_tardanzas' => '../operaciones/tardanzas_manual.php',
            'mes_nombre' => $mesNombre,
            'detalles' => []
        ];
    }

    $sucursalesCodigos = array_column($sucursales, 'codigo');
    $placeholders = implode(',', array_fill(0, count($sucursalesCodigos), '?'));

    // Consulta para obtener tardanzas reales no reportadas (misma que líderes)
    $sql = "
        SELECT 
            m.CodOperario,
            m.fecha,
            m.sucursal_codigo,
            s.nombre as sucursal_nombre,
            CONCAT(
                IFNULL(o.Nombre, ''), ' ', 
                IFNULL(o.Nombre2, ''), ' ', 
                IFNULL(o.Apellido, ''), ' ', 
                IFNULL(o.Apellido2, '')
            ) AS nombre_completo,
            m.hora_ingreso,
            CASE DAYOFWEEK(m.fecha)
                WHEN 2 THEN hso.lunes_entrada
                WHEN 3 THEN hso.martes_entrada
                WHEN 4 THEN hso.miercoles_entrada
                WHEN 5 THEN hso.jueves_entrada
                WHEN 6 THEN hso.viernes_entrada
                WHEN 7 THEN hso.sabado_entrada
                WHEN 1 THEN hso.domingo_entrada
            END as hora_programada,
            TIMESTAMPDIFF(
                MINUTE, 
                CASE DAYOFWEEK(m.fecha)
                    WHEN 2 THEN hso.lunes_entrada
                    WHEN 3 THEN hso.martes_entrada
                    WHEN 4 THEN hso.miercoles_entrada
                    WHEN 5 THEN hso.jueves_entrada
                    WHEN 6 THEN hso.viernes_entrada
                    WHEN 7 THEN hso.sabado_entrada
                    WHEN 1 THEN hso.domingo_entrada
                END,
                m.hora_ingreso
            ) as minutos_tardanza
        FROM marcaciones m
        INNER JOIN HorariosSemanalesOperaciones hso ON m.CodOperario = hso.cod_operario 
            AND m.sucursal_codigo = hso.cod_sucursal
        INNER JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
        INNER JOIN sucursales s ON m.sucursal_codigo = s.codigo
        INNER JOIN Operarios o ON m.CodOperario = o.CodOperario
        WHERE m.sucursal_codigo IN ($placeholders)
        AND m.fecha BETWEEN ? AND ?
        AND m.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
        AND m.hora_ingreso IS NOT NULL
        AND TIMESTAMPDIFF(
            MINUTE, 
            CASE DAYOFWEEK(m.fecha)
                WHEN 2 THEN hso.lunes_entrada
                WHEN 3 THEN hso.martes_entrada
                WHEN 4 THEN hso.miercoles_entrada
                WHEN 5 THEN hso.jueves_entrada
                WHEN 6 THEN hso.viernes_entrada
                WHEN 7 THEN hso.sabado_entrada
                WHEN 1 THEN hso.domingo_entrada
            END,
            m.hora_ingreso
        ) > 1
        AND NOT EXISTS (
            SELECT 1 FROM TardanzasManuales tm
            WHERE tm.cod_operario = m.CodOperario
            AND tm.fecha_tardanza = m.fecha
            AND tm.cod_sucursal = m.sucursal_codigo
        )
        ORDER BY m.fecha DESC, m.sucursal_codigo, nombre_completo
    ";

    $params = array_merge($sucursalesCodigos, [$fechaDesde, $fechaHasta]);

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $detalles = $stmt->fetchAll();

        $totalTardanzas = count($detalles);
        $color = determinarColorTardanzas($totalTardanzas, $diasRestantes);

        // Construir URL con parámetros
        $urlTardanzas = "../operaciones/tardanzas_manual.php?" . http_build_query([
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'sucursales' => implode(',', $sucursalesCodigos),
            'modo' => 'rh',
            'periodo' => $periodo
        ]);

        return [
            'total' => $totalTardanzas,
            'color' => $color,
            'texto' => obtenerTextoIndicador($totalTardanzas, $periodo, $mesNombre),
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_tardanzas' => $urlTardanzas,
            'mes_nombre' => $mesNombre,
            'sucursales' => $sucursalesCodigos,
            'detalles' => $detalles
        ];

    } catch (Exception $e) {
        error_log("Error obteniendo tardanzas pendientes RH: " . $e->getMessage());

        return [
            'total' => 0,
            'color' => 'verde',
            'texto' => 'Error en cálculo',
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_tardanzas' => '../operaciones/tardanzas_manual.php',
            'mes_nombre' => $mesNombre,
            'detalles' => []
        ];
    }
}

/**
 * Obtiene el total de faltas/ausencias pendientes de reportar para RH (todas las sucursales)
 */
function obtenerFaltasPendientesRH()
{
    global $conn;

    // Determinar el periodo a revisar según el día del mes
    $hoy = new DateTime();
    $diaMes = (int) $hoy->format('d');
    $diasRestantes = calcularDiasRestantesReporteFaltas();

    if ($diaMes <= 1) {
        // Día 1: revisar mes anterior
        $mesRevisar = new DateTime('first day of last month');
        $fechaDesde = $mesRevisar->format('Y-m-01');
        $fechaHasta = $mesRevisar->format('Y-m-t');
        $periodo = 'mes_anterior';
        $mesNombre = obtenerMesEspanol($mesRevisar) . ' ' . $mesRevisar->format('Y');
    } else {
        // Días 2+: revisar mes actual (hasta ayer para evitar futuros)
        $fechaDesde = $hoy->format('Y-m-01');
        $fechaHasta = date('Y-m-d', strtotime('-1 day'));
        $periodo = 'mes_actual';
        $mesNombre = obtenerMesEspanol($hoy) . ' ' . $hoy->format('Y');
    }

    // Obtener todas las sucursales para RH
    $sucursales = obtenerTodasSucursales();
    if (empty($sucursales)) {
        return [
            'total' => 0,
            'color' => 'verde',
            'texto' => 'Sin faltas pendientes',
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_faltas' => '../lideres/faltas_manual.php',
            'mes_nombre' => $mesNombre,
            'detalles' => []
        ];
    }

    $sucursalesCodigos = array_column($sucursales, 'codigo');
    $placeholders = implode(',', array_fill(0, count($sucursalesCodigos), '?'));

    // Consulta para obtener ausencias reales no reportadas (misma que líderes)
    $sql = "
        SELECT 
            hso.cod_operario,
            hso.cod_sucursal,
            s.nombre as sucursal_nombre,
            CONCAT(
                IFNULL(o.Nombre, ''), ' ', 
                IFNULL(o.Nombre2, ''), ' ', 
                IFNULL(o.Apellido, ''), ' ', 
                IFNULL(o.Apellido2, '')
            ) AS nombre_completo,
            h.fecha,
            CASE DAYOFWEEK(h.fecha)
                WHEN 2 THEN hso.lunes_entrada
                WHEN 3 THEN hso.martes_entrada
                WHEN 4 THEN hso.miercoles_entrada
                WHEN 5 THEN hso.jueves_entrada
                WHEN 6 THEN hso.viernes_entrada
                WHEN 7 THEN hso.sabado_entrada
                WHEN 1 THEN hso.domingo_entrada
            END as hora_entrada_programada,
            CASE DAYOFWEEK(h.fecha)
                WHEN 2 THEN hso.lunes_salida
                WHEN 3 THEN hso.martes_salida
                WHEN 4 THEN hso.miercoles_salida
                WHEN 5 THEN hso.jueves_salida
                WHEN 6 THEN hso.viernes_salida
                WHEN 7 THEN hso.sabado_salida
                WHEN 1 THEN hso.domingo_salida
            END as hora_salida_programada,
            CASE DAYOFWEEK(h.fecha)
                WHEN 2 THEN hso.lunes_estado
                WHEN 3 THEN hso.martes_estado
                WHEN 4 THEN hso.miercoles_estado
                WHEN 5 THEN hso.jueves_estado
                WHEN 6 THEN hso.viernes_estado
                WHEN 7 THEN hso.sabado_estado
                WHEN 1 THEN hso.domingo_estado
            END as estado_dia
        FROM (
            SELECT DATE(?) + INTERVAL (a.a + (10 * b.a)) DAY as fecha
            FROM 
            (SELECT 0 a UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
             UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
            (SELECT 0 a UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
             UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
            WHERE DATE(?) + INTERVAL (a.a + (10 * b.a)) DAY <= ?
        ) h
        INNER JOIN HorariosSemanalesOperaciones hso ON hso.cod_sucursal IN ($placeholders)
        INNER JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
        INNER JOIN sucursales s ON hso.cod_sucursal = s.codigo
        INNER JOIN Operarios o ON hso.cod_operario = o.CodOperario
        WHERE h.fecha BETWEEN ? AND ?
        AND h.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
        AND (
            (DAYOFWEEK(h.fecha) = 2 AND hso.lunes_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 3 AND hso.martes_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 4 AND hso.miercoles_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 5 AND hso.jueves_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 6 AND hso.viernes_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 7 AND hso.sabado_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 1 AND hso.domingo_estado = 'Activo')
        )
        AND NOT EXISTS (
            SELECT 1 FROM marcaciones m
            WHERE m.CodOperario = hso.cod_operario
            AND m.sucursal_codigo = hso.cod_sucursal
            AND m.fecha = h.fecha
            AND (m.hora_ingreso IS NOT NULL OR m.hora_salida IS NOT NULL)
        )
        AND NOT EXISTS (
            SELECT 1 FROM faltas_manual fm
            WHERE fm.cod_operario = hso.cod_operario
            AND fm.fecha_falta = h.fecha
            AND fm.cod_sucursal = hso.cod_sucursal
        )
        AND o.Operativo = 1
        AND EXISTS (
            SELECT 1 FROM AsignacionNivelesCargos anc
            WHERE anc.CodOperario = o.CodOperario
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        )
        ORDER BY h.fecha DESC, hso.cod_sucursal, nombre_completo
    ";

    $params = array_merge(
        [$fechaDesde, $fechaDesde, $fechaHasta],
        $sucursalesCodigos,
        [$fechaDesde, $fechaHasta]
    );

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $detalles = $stmt->fetchAll();

        $totalFaltas = count($detalles);
        $color = determinarColorFaltas($totalFaltas, $diasRestantes);

        // Construir URL con parámetros
        $urlFaltas = "../lideres/faltas_manual.php?" . http_build_query([
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'sucursales' => implode(',', $sucursalesCodigos),
            'modo' => 'rh',
            'periodo' => $periodo
        ]);

        return [
            'total' => $totalFaltas,
            'color' => $color,
            'texto' => obtenerTextoIndicadorFaltas($totalFaltas, $periodo, $mesNombre),
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_faltas' => $urlFaltas,
            'mes_nombre' => $mesNombre,
            'sucursales' => $sucursalesCodigos,
            'detalles' => $detalles
        ];

    } catch (Exception $e) {
        error_log("Error obteniendo faltas pendientes RH: " . $e->getMessage());

        return [
            'total' => 0,
            'color' => 'verde',
            'texto' => 'Error en cálculo',
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_faltas' => '../lideres/faltas_manual.php',
            'mes_nombre' => $mesNombre,
            'detalles' => []
        ];
    }
}

// Funciones auxiliares (copiar del líderes si no existen)
function calcularDiasRestantesReporte()
{
    $hoy = new DateTime();
    $diaMes = (int) $hoy->format('d');

    if ($diaMes <= 2) {
        return max(0, 2 - $diaMes);
    } else {
        $proximoMes = new DateTime('first day of next month');
        $proximoMes->modify('+1 day');
        $diferencia = $hoy->diff($proximoMes);
        return $diferencia->days;
    }
}

function determinarColorTardanzas($totalTardanzas, $diasRestantes)
{
    if ($totalTardanzas == 0)
        return 'verde';
    if ($diasRestantes <= 0)
        return 'rojo';
    if ($diasRestantes <= 1)
        return 'rojo';
    if ($diasRestantes <= 2)
        return 'amarillo';
    return 'verde';
}

function obtenerTextoIndicador($totalTardanzas, $periodo, $mesNombre)
{
    if ($totalTardanzas == 0)
        return 'Sin tardanzas pendientes';
    $mesTexto = ($periodo === 'mes_anterior') ? 'del mes anterior' : 'del mes actual';
    return "$totalTardanzas tardanzas pendientes $mesTexto";
}

function calcularDiasRestantesReporteFaltas()
{
    $hoy = new DateTime();
    $diaMes = (int) $hoy->format('d');

    if ($diaMes <= 1)
        return 0;

    $proximoMes = new DateTime('first day of next month');
    $diferencia = $hoy->diff($proximoMes);
    return $diferencia->days;
}

function determinarColorFaltas($totalFaltas, $diasRestantes)
{
    if ($totalFaltas == 0)
        return 'verde';
    if ($diasRestantes <= 0)
        return 'rojo';
    if ($diasRestantes <= 1)
        return 'rojo';
    if ($diasRestantes <= 3)
        return 'amarillo';
    return 'verde';
}

function obtenerTextoIndicadorFaltas($totalFaltas, $periodo, $mesNombre)
{
    if ($totalFaltas == 0)
        return 'Sin faltas pendientes';
    $mesTexto = ($periodo === 'mes_anterior') ? 'del mes anterior' : 'del mes actual';
    return "$totalFaltas faltas pendientes $mesTexto";
}

/**
 * Obtiene operarios con ausencias de 3+ días consecutivos (nuevo criterio)
 * Solo cuenta cuando el estado del día es 'Activo' u 'Otra.Tienda' y no hay marcación
 */
function obtenerAusenciasColaboradores()
{
    global $conn;

    // Fecha actual y fecha de hace 30 días para el rango de revisión
    $fechaHasta = date('Y-m-d');
    $fechaDesde = date('Y-m-d', strtotime('-30 days'));

    $sql = "
        SELECT DISTINCT
            o.CodOperario,
            CONCAT(
                TRIM(o.Nombre), 
                IF(o.Nombre2 IS NOT NULL AND o.Nombre2 != '', CONCAT(' ', TRIM(o.Nombre2)), ''), 
                ' ', 
                TRIM(o.Apellido),
                IF(o.Apellido2 IS NOT NULL AND o.Apellido2 != '', CONCAT(' ', TRIM(o.Apellido2)), '')
            ) as nombre_completo,
            o.Celular,
            s.nombre as sucursal_nombre,
            s.codigo as sucursal_codigo
        FROM Operarios o
        LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        LEFT JOIN sucursales s ON anc.Sucursal = s.codigo
        WHERE o.Operativo = 1
        AND o.CodOperario NOT IN (
            SELECT DISTINCT anc2.CodOperario 
            FROM AsignacionNivelesCargos anc2
            WHERE anc2.CodNivelesCargos = 27
            AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
        )
        GROUP BY o.CodOperario, o.Nombre, o.Apellido, o.Celular, s.nombre, s.codigo
        ORDER BY nombre_completo
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $operarios = $stmt->fetchAll();

    // Filtrar en PHP aquellos que tengan 3+ días consecutivos sin marcar
    $ausencias = [];
    foreach ($operarios as $operario) {
        $diasConsecutivos = verificarAusenciaConsecutivaNuevoCriterio(
            $operario['CodOperario'],
            $operario['sucursal_codigo']
        );

        if ($diasConsecutivos >= 3) {
            $ausencias[] = $operario;
        }
    }

    return $ausencias;
}

/**
 * Verifica días consecutivos sin marcación según NUEVO criterio CORREGIDO:
 * Solo cuenta días con estado 'Activo' u 'Otra.Tienda' en horario programado
 * Y que NO tengan marcación, Y que el periodo de ausencia sea HASTA HOY (sin marcaciones posteriores)
 */
function verificarAusenciaConsecutivaNuevoCriterio($codOperario, $codSucursal)
{
    global $conn;

    $maxConsecutivos = 0;
    $consecutivosActual = 0;

    // Revisar desde hoy hacia atrás
    $fechaActual = new DateTime(); // Hoy
    $fechaFin = new DateTime();
    $fechaFin->modify('-30 days'); // Revisar solo los últimos 30 días

    // Bandera para indicar si se rompió la secuencia con una marcación
    $secuenciaActiva = true; // Asumimos que podría haber una secuencia activa

    // Recorrer desde hoy hacia atrás
    while ($fechaActual >= $fechaFin) {
        $fecha = $fechaActual->format('Y-m-d');

        // Verificar SI este día debería haber trabajado según NUEVO criterio
        $deberiaTrabajar = deberiaTrabajarDiaNuevoCriterio($codOperario, $codSucursal, $fecha);

        // Verificar si tiene marcación EN CUALQUIER SUCURSAL
        $tieneMarcacion = tieneMarcacionCualquierSucursal($codOperario, $fecha);

        if ($deberiaTrabajar && !$tieneMarcacion) {
            // Si debería trabajar y NO marcó: aumenta contador SOLO si la secuencia está activa
            if ($secuenciaActiva) {
                $consecutivosActual++;
                $maxConsecutivos = max($maxConsecutivos, $consecutivosActual);
            }
        } else {
            // CASO 1: Si marcó (aunque sea parcialmente) → RESET y rompe secuencia
            // CASO 2: Si no debería trabajar (estado diferente a Activo/Otra.Tienda) → NO rompe secuencia, solo no cuenta
            if ($tieneMarcacion) {
                // ¡ESTO ES CLAVE! Si encontró una marcación, rompe la secuencia
                $secuenciaActiva = false;
                $consecutivosActual = 0; // Resetear contador
            }
            // Si no debería trabajar, solo resetear contador pero mantener secuenciaActiva
            if (!$deberiaTrabajar) {
                $consecutivosActual = 0;
            }
        }

        $fechaActual->modify('-1 day');
    }

    return $maxConsecutivos;
}

/**
 * Verifica si un operario debería trabajar según NUEVO criterio:
 * SOLO si el estado del día es 'Activo' u 'Otra.Tienda'
 */
function deberiaTrabajarDiaNuevoCriterio($codOperario, $codSucursal, $fecha)
{
    global $conn;

    // Obtener la semana del sistema para esta fecha
    $semana = obtenerSemanaPorFecha($fecha);
    if (!$semana)
        return false;

    // Obtener el día de la semana (1=lunes, 7=domingo)
    $diaSemana = date('N', strtotime($fecha));
    $dias = ['', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $diaColumna = $dias[$diaSemana];

    $stmt = $conn->prepare("
        SELECT {$diaColumna}_estado as estado
        FROM HorariosSemanalesOperaciones
        WHERE cod_operario = ? 
        AND cod_sucursal = ?
        AND id_semana_sistema = ?
        LIMIT 1
    ");

    $stmt->execute([$codOperario, $codSucursal, $semana['id']]);
    $result = $stmt->fetch();

    // NUEVO CRITERIO: SOLO 'Activo' u 'Otra.Tienda'
    return ($result && in_array($result['estado'], ['Activo', 'Otra.Tienda']));
}

/**
 * Verifica si tiene marcación en CUALQUIER sucursal en una fecha específica
 */
function tieneMarcacionCualquierSucursal($codOperario, $fecha)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM marcaciones 
        WHERE CodOperario = ? 
        AND fecha = ?
        AND (hora_ingreso IS NOT NULL OR hora_salida IS NOT NULL)
    ");

    $stmt->execute([$codOperario, $fecha]);
    $result = $stmt->fetch();

    return ($result && $result['total'] > 0);
}

/**
 * Obtiene la última marcación del operario en CUALQUIER sucursal
 */
function obtenerUltimaMarcacionCualquierSucursal($codOperario, $fechaDesde, $fechaHasta)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT fecha, sucursal_codigo, hora_ingreso, hora_salida
        FROM marcaciones 
        WHERE CodOperario = ? 
        AND fecha BETWEEN ? AND ?
        AND (hora_ingreso IS NOT NULL OR hora_salida IS NOT NULL)
        ORDER BY fecha DESC, 
                 GREATEST(COALESCE(hora_ingreso, '00:00:00'), COALESCE(hora_salida, '00:00:00')) DESC
        LIMIT 1
    ");

    $stmt->execute([$codOperario, $fechaDesde, $fechaHasta]);
    $result = $stmt->fetch();

    return $result ? $result['fecha'] : null;
}

/**
 * Obtiene detalles de días sin marcar para un operario - VERSIÓN CORREGIDA
 * Solo devuelve periodos que terminan justo antes de hoy
 */
function obtenerDetalleDiasSinMarcar($codOperario, $codSucursal, $diasRequeridos = 3)
{
    global $conn;

    $detalles = [];
    $consecutivosActual = 0;
    $diasEncontrados = [];

    // Revisar desde hoy hacia atrás
    $fechaActual = new DateTime();
    $fechaFin = new DateTime();
    $fechaFin->modify('-30 days');

    // Bandera para indicar si estamos en una secuencia activa
    $enSecuenciaActiva = false;

    while ($fechaActual >= $fechaFin) {
        $fecha = $fechaActual->format('Y-m-d');

        $deberiaTrabajar = deberiaTrabajarDiaNuevoCriterio($codOperario, $codSucursal, $fecha);
        $tieneMarcacion = tieneMarcacionCualquierSucursal($codOperario, $fecha);

        if ($deberiaTrabajar && !$tieneMarcacion) {
            // Día que debería trabajar y no marcó
            $consecutivosActual++;
            $diasEncontrados[] = [
                'fecha' => $fecha,
                'estado' => obtenerEstadoDiaProgramado($codOperario, $codSucursal, $fecha),
                'tiene_marcacion' => false
            ];
            $enSecuenciaActiva = true;
        } else {
            if ($tieneMarcacion && $enSecuenciaActiva) {
                // ¡IMPORTANTE! Encontramos una marcación que rompe la secuencia
                // Solo registrar si tenemos al menos 3 días consecutivos
                if ($consecutivosActual >= $diasRequeridos) {
                    $detalles[] = [
                        'dias_consecutivos' => $consecutivosActual,
                        'dias' => $diasEncontrados,
                        'ultimo_dia' => $diasEncontrados[0]['fecha'], // El más reciente
                        'primer_dia' => end($diasEncontrados)['fecha'] // El más antiguo
                    ];
                }
                // Resetear para buscar nueva secuencia
                $consecutivosActual = 0;
                $diasEncontrados = [];
                $enSecuenciaActiva = false;
            } elseif (!$deberiaTrabajar) {
                // No debería trabajar, resetear contador pero mantener $enSecuenciaActiva
                $consecutivosActual = 0;
                $diasEncontrados = [];
            }
        }

        $fechaActual->modify('-1 day');
    }

    // Verificar si al final del ciclo hay una secuencia activa (que llegue hasta hoy)
    if ($enSecuenciaActiva && $consecutivosActual >= $diasRequeridos) {
        $detalles[] = [
            'dias_consecutivos' => $consecutivosActual,
            'dias' => $diasEncontrados,
            'ultimo_dia' => $diasEncontrados[0]['fecha'],
            'primer_dia' => end($diasEncontrados)['fecha']
        ];
    }

    return $detalles;
}

/**
 * Obtiene el estado del día programado
 */
function obtenerEstadoDiaProgramado($codOperario, $codSucursal, $fecha)
{
    global $conn;

    // Obtener la semana del sistema para esta fecha
    $semana = obtenerSemanaPorFecha($fecha);
    if (!$semana)
        return 'Sin horario';

    // Obtener el día de la semana (1=lunes, 7=domingo)
    $diaSemana = date('N', strtotime($fecha));
    $dias = ['', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $diaColumna = $dias[$diaSemana];

    $stmt = $conn->prepare("
        SELECT {$diaColumna}_estado as estado
        FROM HorariosSemanalesOperaciones
        WHERE cod_operario = ? 
        AND cod_sucursal = ?
        AND id_semana_sistema = ?
        LIMIT 1
    ");

    $stmt->execute([$codOperario, $codSucursal, $semana['id']]);
    $result = $stmt->fetch();

    return $result['estado'] ?? 'Sin horario';
}

/**
 * Determina color del indicador según cantidad de ausencias
 */
function determinarColorIndicadorAusenciasColaboradores($ausencias)
{
    if (empty($ausencias))
        return 'verde';

    // Buscar la ausencia más prolongada
    $diasMaximos = 0;
    foreach ($ausencias as $ausencia) {
        $diasConsecutivos = verificarAusenciaConsecutivaNuevoCriterio(
            $ausencia['CodOperario'],
            $ausencia['sucursal_codigo']
        );
        if ($diasConsecutivos > $diasMaximos) {
            $diasMaximos = $diasConsecutivos;
        }
    }

    if ($diasMaximos >= 7)
        return 'rojo';      // 1 semana o más
    if ($diasMaximos >= 5)
        return 'amarillo';  // 5-6 días
    if ($diasMaximos >= 3)
        return 'naranja';   // 3-4 días
    return 'verde';                            // Menos de 3 días
}

// ========== NUEVO INDICADOR: AUSENCIAS COLABORADORES ==========
$ausenciasColaboradores = obtenerAusenciasColaboradores();
$totalAusenciasColaboradores = count($ausenciasColaboradores);
$colorIndicadorAusenciasColab = determinarColorIndicadorAusenciasColaboradores($ausenciasColaboradores);

// Función para obtener el detalle de ausencias para el modal
function obtenerDetalleAusenciasColaboradoresModal()
{
    $ausencias = obtenerAusenciasColaboradores();
    $detalle = [];

    $fechaHasta = date('Y-m-d');
    $fechaDesde = date('Y-m-d', strtotime('-30 days'));

    foreach ($ausencias as $ausencia) {
        $diasConsecutivos = verificarAusenciaConsecutivaNuevoCriterio(
            $ausencia['CodOperario'],
            $ausencia['sucursal_codigo']
        );

        // Obtener el detalle de días sin marcar
        $detalleDias = obtenerDetalleDiasSinMarcar(
            $ausencia['CodOperario'],
            $ausencia['sucursal_codigo']
        );

        // Obtener la última marcación
        $ultimaMarcacion = obtenerUltimaMarcacionCualquierSucursal(
            $ausencia['CodOperario'],
            $fechaDesde,
            $fechaHasta
        );

        $detalle[] = [
            'CodOperario' => $ausencia['CodOperario'],
            'nombre_completo' => $ausencia['nombre_completo'],
            'Celular' => $ausencia['Celular'],
            'sucursal_nombre' => $ausencia['sucursal_nombre'],
            'sucursal_codigo' => $ausencia['sucursal_codigo'],
            'dias_consecutivos' => $diasConsecutivos,
            'detalle_dias' => $detalleDias,
            'ultima_marcacion' => $ultimaMarcacion
        ];
    }

    return $detalle;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recursos Humanos - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet"
        href="../../assets/css/indexmodulos.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/indexmodulos.css') ?>">
    <!-- CSS propio con manejo de versiones  evitar cache de buscador -->
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(12px, 2vw, 18px) !important;
        }

        body {
            background-color: #F6F6F6;
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Estilos para los botones de acción */
        .btn-revisar.llamar {
            background: #007bff;
        }

        .btn-revisar.llamar:hover {
            background: #0056b3;
        }

        .status-alerta {
            color: #ffc107;
            font-weight: bold;
        }

        .status-info {
            color: #17a2b8;
            font-weight: bold;
        }

        .status-inactivo {
            color: #dc3545;
            font-weight: bold;
        }

        .btn-ver-detalles {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
        }

        .btn-ver-detalles:hover {
            background: white;
            color: #667eea;
        }



        /* Estilos para modales */
        .modal-pendientes {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content-pendientes {
            background-color: white;
            margin: 5% auto;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header-pendientes {
            background: #0E544C;
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header-pendientes h3 {
            margin: 0;
            font-size: 1.4rem !important;
        }

        .close-modal {
            font-size: 2rem;
            cursor: pointer;
            transition: color 0.3s ease;
            line-height: 1;
        }

        .close-modal:hover {
            color: #ffeb3b;
        }

        .modal-body-pendientes {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }

        /* Información de fecha límite */
        .info-fecha-limite {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }

        .info-fecha-limite p {
            margin: 5px 0;
            color: #495057;
        }

        /* Lista de faltas pendientes */
        .lista-faltas {
            display: grid;
            gap: 15px;
        }

        .item-falta {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .item-falta:hover {
            background: #e9ecef;
            transform: translateX(5px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .falta-info h4 {
            margin: 0 0 8px 0;
            color: #495057;
            font-size: 1.1rem !important;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .falta-info p {
            margin: 0 0 5px 0;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .falta-info small {
            color: #868e96;
            font-size: 0.8rem;
        }

        .btn-revisar {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
            white-space: nowrap;
            margin-left: 15px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-revisar:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modal-content-pendientes {
                margin: 10% auto;
                width: 95%;
            }

            .item-falta {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .btn-revisar {
                margin-left: 0;
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, $esAdmin); ?>

            <h2 class="section-title">
                <i class="fas fa-chart-line"></i> Indicadores de Control
            </h2>

            <!-- Contenedor para indicadores -->
            <div class="indicadores-container">
                <!-- Indicador de Contratos Próximos a Vencer -->
                <div class="indicator-container" onclick="mostrarModalContratos()" style="cursor: pointer;">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>

                    <div class="indicator-count" id="contratosCount">
                        0
                    </div>
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Contratos por Vencer
                        </div>

                        <div class="indicator-meta">
                            <span id="contratosContainer">
                                <span class="indicator-status" id="contratosFecha">
                                    <!-- Se llenará con JavaScript -->
                                </span>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Indicador de Faltas Pendientes de Revisión -->
                <a href="#" id="faltasLink" class="indicator-container" style="text-decoration: none; color: inherit;">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>

                    <div class="indicator-count" id="faltasCount">
                        0
                    </div>
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Faltas reportadas (Pendientes)
                        </div>

                        <div class="indicator-meta">
                            <span id="faltasContainer">
                                <span class="indicator-status faltas-indicador" id="faltasFecha">
                                    <!-- Se llenará con JavaScript -->
                                </span>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </a>

                <!-- Indicadores de Tardanzas y Faltas Pendientes (como líderes) -->
                <div class="indicator-container" onclick="mostrarModalTardanzasRH()" style="cursor: pointer;">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>

                    <div class="indicator-count">
                        <?= $tardanzasPendientesRH['total'] ?>
                    </div>
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Tardanzas no justificadas en tienda
                        </div>

                        <div class="indicator-meta">
                            <span>
                                <span
                                    class="indicator-status tardanzas-indicador <?= $tardanzasPendientesRH['color'] ?>"
                                    id="tardanzasFechaRH">
                                    Mes Actual
                                </span>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="indicator-container" onclick="mostrarModalFaltasRH()" style="cursor: pointer;">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>

                    <div class="indicator-count">
                        <?= $faltasPendientesRH['total'] ?>
                    </div>
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Faltas no justificadas en tienda
                        </div>

                        <div class="indicator-meta">
                            <span>
                                <span class="indicator-status faltas-indicador <?= $faltasPendientesRH['color'] ?>"
                                    id="faltasFechaRH">
                                    Mes Actual
                                </span>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Operarios Incompletos -->
                <div class="indicator-container" style="cursor: pointer;">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>

                    <div class="indicator-count">
                        <?//= $operariosIncompletos ?> <?//= $totalActivos ?>
                        <?= $operariosPendientesDoc = ($operariosIncompletos / $totalActivos) * 100;
                        $operariosPendientesDoc
                            ?> %
                    </div>
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Con documentos pendiente
                        </div>

                        <div class="indicator-meta">
                            <span>
                                <span class="indicator-status">
                                    Incompletos
                                </span>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- NUEVO: Indicador de Ausencias Colaboradores (3+ días sin marcar con estado Activo/Otra.Tienda) -->
                <?php if ($esAdmin || verificarAccesoCargo([13, 16, 39, 30, 37])): ?>
                    <div class="indicator-container" onclick="mostrarModalAusenciasColaboradores()"
                        style="cursor: pointer;">
                        <div class="indicator-header">
                            <div class="indicator-icon">
                                <i class="fas fa-stopwatch"></i>
                            </div>
                        </div>

                        <div class="indicator-count" id="ausenciasColabCount">
                            <?= $totalAusenciasColaboradores ?>
                        </div>
                        <div class="indicator-info">
                            <div class="indicator-titulo">
                                Ausencias Colaboradores
                            </div>

                            <div class="indicator-meta">
                                <span>
                                    <span class="indicator-status <?= $colorIndicadorAusenciasColab ?>"
                                        id="ausenciasColabFecha">
                                        (3+ días Activo/Otra.Tienda sin marcar)
                                    </span>
                                </span>
                                <span class="indicator-action">
                                    <i class="fas fa-arrow-right"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Modal para detalles de contratos próximos a vencer -->
            <div id="modalContratos" class="modal-pendientes">
                <div class="modal-content-pendientes">
                    <div class="modal-header-pendientes">
                        <h3>Contratos Próximos a Vencer (Próximos 30 días)</h3>
                        <span class="close-modal" onclick="cerrarModalContratos()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div style="display:none;" class="info-fecha-limite">
                            <p><strong>Total de contratos próximos a vencer:</strong> <span
                                    id="totalContratosInfo">0</span></p>
                            <p><strong>Periodo:</strong> Próximos 30 días</p>
                            <p><strong>Acción:</strong> Haga clic en "Revisar" para ver los detalles del colaborador</p>
                        </div>
                        <div id="listaContratosProximos"></div>
                    </div>
                </div>
            </div>

            <!-- Modal para detalles de Ausencias Colaboradores -->
            <div id="modalAusenciasColaboradores" class="modal-pendientes">
                <div class="modal-content-pendientes">
                    <div class="modal-header-pendientes">
                        <h3>Ausencias Colaboradores (3+ días Activo/Otra.Tienda sin marcar)</h3>
                        <span class="close-modal" onclick="cerrarModalAusenciasColaboradores()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="info-fecha-limite">
                            <p><strong>Total de colaboradores con ausencias:</strong> <span
                                    id="totalAusenciasColabInfo"><?= $totalAusenciasColaboradores ?></span></p>
                            <p><strong>Criterio:</strong> 3 o más días consecutivos con estado <strong>"Activo" u
                                    "Otra.Tienda"</strong> en horario programado y SIN marcación</p>
                            <p><strong>Acción:</strong> Contactar al colaborador para verificar situación</p>
                        </div>
                        <div id="listaAusenciasColaboradores"></div>
                    </div>
                </div>
            </div>

            <!-- Modal de Detalles de Tardanzas Pendientes RH -->
            <div id="modalTardanzasRH" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 90%;">
                    <div class="modal-header-pendientes">
                        <h3><i class="fas fa-list"></i> Detalles de Tardanzas Pendientes de Reportar por Líderes</h3>
                        <span class="close-modal" onclick="cerrarModalTardanzasRH()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="filtros-modal"
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <div>
                                <strong>Periodo:</strong>
                                <?= date('d/m/Y', strtotime($tardanzasPendientesRH['fecha_desde'])) ?> -
                                <?= date('d/m/Y', strtotime($tardanzasPendientesRH['fecha_hasta'])) ?>
                                | <strong>Total:</strong> <?= $tardanzasPendientesRH['total'] ?> tardanzas

                            </div>
                            <a href="<?= $tardanzasPendientesRH['url_tardanzas'] ?>" class="btn-ver-detalles"
                                target="_blank">
                                <i class="fas fa-external-link-alt"></i> Ver Tardanzas
                            </a>
                        </div>

                        <?php if (empty($tardanzasPendientesRH['detalles'])): ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-check-circle"
                                    style="font-size: 3rem; color: #28a745; margin-bottom: 15px;"></i>
                                <h4>No hay tardanzas pendientes de reportar</h4>
                                <p>Todas las tardanzas han sido reportadas correctamente.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto; max-height: 60vh;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #0E544C; Color: white;">
                                            <th style="padding: 12px; text-align: left;">Colaborador</th>
                                            <th style="padding: 12px; text-align: center;">Sucursal</th>
                                            <th style="padding: 12px; text-align: center;">Fecha</th>
                                            <th style="padding: 12px; text-align: center;">Horario Programado</th>
                                            <th style="padding: 12px; text-align: center;">Hora Marcada</th>
                                            <th style="padding: 12px; text-align: center;">Minutos de Tardanza</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tardanzasPendientesRH['detalles'] as $index => $tardanza): ?>
                                            <tr style="background: <?= $index % 2 === 0 ? '#f8f9fa' : 'white' ?>;">
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                                                    <strong><?= htmlspecialchars($tardanza['nombre_completo']) ?></strong>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= htmlspecialchars($tardanza['sucursal_nombre']) ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= formatoFecha($tardanza['fecha']) ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= $tardanza['hora_programada'] ? formatoHoraAmPm($tardanza['hora_programada']) : 'N/A' ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= formatoHoraAmPm($tardanza['hora_ingreso']) ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <span style="color: #dc3545; font-weight: bold;">
                                                        +<?= $tardanza['minutos_tardanza'] ?> min
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Modal de Detalles de Faltas Pendientes RH -->
            <div id="modalFaltasRH" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 90%;">
                    <div class="modal-header-pendientes">
                        <h3><i class="fas fa-list"></i> Detalles de Faltas Pendientes de Reportar por Líderes</h3>
                        <span class="close-modal" onclick="cerrarModalFaltasRH()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="filtros-modal"
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <div>
                                <strong>Periodo:</strong>
                                <?= date('d/m/Y', strtotime($faltasPendientesRH['fecha_desde'])) ?> -
                                <?= date('d/m/Y', strtotime($faltasPendientesRH['fecha_hasta'])) ?>
                                | <strong>Total:</strong> <?= $faltasPendientesRH['total'] ?> faltas

                            </div>
                            <a href="<?= $faltasPendientesRH['url_faltas'] ?>" class="btn-ver-detalles" target="_blank">
                                <i class="fas fa-external-link-alt"></i> Ver Faltas
                            </a>
                        </div>

                        <?php if (empty($faltasPendientesRH['detalles'])): ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-check-circle"
                                    style="font-size: 3rem; color: #28a745; margin-bottom: 15px;"></i>
                                <h4>No hay faltas pendientes de reportar</h4>
                                <p>Todas las ausencias han sido reportadas correctamente.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto; max-height: 60vh;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #0E544C; Color: white;">
                                            <th style="padding: 12px; text-align: left;">Colaborador</th>
                                            <th style="padding: 12px; text-align: center;">Sucursal</th>
                                            <th style="padding: 12px; text-align: center;">Fecha</th>
                                            <th style="padding: 12px; text-align: center;">Horario Programado</th>
                                            <th style="padding: 12px; text-align: center;">Estado Día</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($faltasPendientesRH['detalles'] as $index => $falta): ?>
                                            <tr style="background: <?= $index % 2 === 0 ? '#f8f9fa' : 'white' ?>;">
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                                                    <strong><?= htmlspecialchars($falta['nombre_completo']) ?></strong>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= htmlspecialchars($falta['sucursal_nombre']) ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= formatoFecha($falta['fecha']) ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= $falta['hora_entrada_programada'] ? formatoHoraAmPm($falta['hora_entrada_programada']) : 'N/A' ?>
                                                    -
                                                    <?= $falta['hora_salida_programada'] ? formatoHoraAmPm($falta['hora_salida_programada']) : 'N/A' ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <span style="color: #dc3545; font-weight: bold;">
                                                        Ausente
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Mostrar tarjetas de pendientes solo si hay alguna -->
            <div style="display:none;" class="indicator-container">
                <?php if ($faltasPendientes > 0): ?>
                    <a href="../lideres/faltas_manual.php" class="indicator-status">
                        <div style="display:none;" class="module-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="indicator-count"><?= $faltasPendientes ?></div>
                        <div class="indicator-titulo">Faltas pendientes</div>
                    </a>
                <?php endif; ?>

                <?php if ($tardanzasPendientes > 0): ?>
                    <a href="../operaciones/tardanzas_manual.php" class="indicator-status">
                        <div style="display:none;" class="module-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="indicator-count"><?= $tardanzasPendientes ?></div>
                        <div class="indicator-titulo">Tardanzas pendientes</div>
                        <small style="display:none;" class="indicator-action">Haz clic para registrar</small>
                    </a>
                <?php endif; ?>
            </div>

            <h2 class="section-title">
                <i class="fas fa-bolt"></i> Accesos Rápidos
            </h2>
            <div class="quick-access-grid">
                <a href="nuevo_colaborador.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="quick-access-title">Nuevo Colaborador</div>
                </a>
                <a href="colaboradores.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="quick-access-title">Lista Colaborador</div>
                </a>
            </div>

        </div>
    </div>

    <script>
        // Variables globales para faltas
        let faltasData = null;
        // Variables globales para contratos
        let contratosData = null;

        // Cargar indicadores al iniciar
        document.addEventListener('DOMContentLoaded', function () {
            cargarFaltasPendientes();
            cargarContratosProximos();

            // Hacer clickeable la tarjeta de faltas
            const faltasCard = document.querySelector('.indicator-container:has(#faltasCount)');
            if (faltasCard) {
                faltasCard.style.cursor = 'pointer';
                faltasCard.addEventListener('click', function (e) {
                    if (!e.target.classList.contains('btn-ver-detalles') &&
                        !e.target.closest('.btn-ver-detalles')) {
                        mostrarModalFaltas();
                    }
                });
            }

            // Hacer clickeable la tarjeta de contratos
            const contratosCard = document.querySelector('.indicator-container:has(#contratosCount)');
            if (contratosCard) {
                contratosCard.style.cursor = 'pointer';
                contratosCard.addEventListener('click', function (e) {
                    if (!e.target.classList.contains('btn-ver-detalles') &&
                        !e.target.closest('.btn-ver-detalles')) {
                        mostrarModalContratos();
                    }
                });
            }
        });

        // Cargar contratos próximos a vencer
        function cargarContratosProximos() {
            fetch('obtener_contratos_proximos_vencer.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        contratosData = data;
                        actualizarIndicadorContratos(data);
                    } else {
                        console.error('Error:', data.message);
                        // Mostrar siempre en verde cuando hay error
                        document.getElementById('contratosContainer').style.display = 'block';
                        document.getElementById('contratosCount').textContent = '0';
                        document.getElementById('contratosFecha').textContent = '(Sin datos)';
                        // Aplicar color verde
                        const card = document.querySelector('#contratosContainer .indicator-status ');
                        if (card) {
                            card.className = 'indicator-status verde';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error de conexión:', error);
                    // Mostrar siempre en verde cuando hay error
                    document.getElementById('contratosContainer').style.display = 'block';
                    document.getElementById('contratosCount').textContent = '0';
                    document.getElementById('contratosFecha').textContent = '(Error conexión)';
                    // Aplicar color verde
                    const card = document.querySelector('#contratosContainer .indicator-status ');
                    if (card) {
                        card.className = 'indicator-status verde';
                    }
                });
        }

        // Actualizar indicador de contratos
        function actualizarIndicadorContratos(data) {
            const container = document.getElementById('contratosContainer');
            const countElement = document.getElementById('contratosCount');
            const fechaElement = document.getElementById('contratosFecha');

            container.style.display = 'block';
            countElement.textContent = data.total_proximos;

            // Determinar color según urgencia
            let colorClase = 'verde';
            let fechaTexto = '';

            if (data.total_proximos === 0) {
                // Cuando no hay contratos próximos
                colorClase = 'verde';
                fechaTexto = '(Sin contratos próximos)';
            } else {
                // Encontrar el contrato más próximo
                let diasMinimos = null;
                data.contratos_proximos.forEach(contrato => {
                    if (diasMinimos === null || contrato.dias_restantes < diasMinimos) {
                        diasMinimos = contrato.dias_restantes;
                    }
                });

                if (diasMinimos <= 7) {
                    colorClase = 'rojo';
                    fechaTexto = `(${diasMinimos} días o menos)`;
                } else if (diasMinimos <= 15) {
                    colorClase = 'amarillo';
                    fechaTexto = `(${diasMinimos} días)`;
                } else {
                    colorClase = 'verde';
                    fechaTexto = `(${diasMinimos} días)`;
                }
            }

            fechaElement.textContent = fechaTexto;

            // Aplicar clase de color al card
            const card = container.querySelector('.indicator-status');
            card.className = 'indicator-status ' + colorClase;
        }

        // Mostrar modal de contratos próximos
        function mostrarModalContratos() {
            if (!contratosData) {
                alert('Cargando datos...');
                return;
            }

            const modal = document.getElementById('modalContratos');
            const lista = document.getElementById('listaContratosProximos');
            const totalInfo = document.getElementById('totalContratosInfo');

            // Actualizar información
            totalInfo.textContent = contratosData.total_proximos;

            // Construir lista de contratos
            lista.innerHTML = construirListaContratos(contratosData.contratos_proximos);

            modal.style.display = 'block';
        }

        // Construir lista de contratos próximos
        function construirListaContratos(contratos) {
            if (contratos.length === 0) {
                return '<p style="text-align: center; color: #6c757d; padding: 20px;">No hay contratos próximos a vencer</p>';
            }

            let html = '<div class="lista-faltas">';

            contratos.forEach(contrato => {
                const nombreCompleto = contrato.nombre_completo;
                const fechaVencimiento = formatoFechaCorta(contrato.fin_contrato);
                const diasRestantes = contrato.dias_restantes;

                // Determinar color según días restantes
                let colorClase = '';
                if (diasRestantes <= 7) {
                    colorClase = 'status-inactivo';
                } else if (diasRestantes <= 15) {
                    colorClase = 'status-alerta';
                } else {
                    colorClase = 'status-info';
                }

                // URL para ir directamente a la página del colaborador
                const urlColaborador = `editar_colaborador.php?id=${contrato.CodOperario}&pestaña=contrato`;

                html += `
                    <div class="item-falta">
                        <div class="falta-info">
                            <h4>
                                <i class="fas fa-user"></i>${nombreCompleto}
                            </h4>
                            <p>
                                <strong>Código:</strong> ${contrato.CodOperario} | 
                                <strong>Sucursal:</strong> ${contrato.sucursal_nombre || 'No asignada'}
                            </p>
                            <p>
                                <strong>Vence:</strong> ${fechaVencimiento} | 
                                <span class="${colorClase}">${diasRestantes} días restantes</span>
                            </p>
                            <small>
                                <strong>Inicio:</strong> ${formatoFechaCorta(contrato.inicio_contrato)}
                            </small>
                        </div>
                        <a href="${urlColaborador}" class="btn-revisar">
                            <i class="fas fa-eye"></i> Revisar
                        </a>
                    </div>
                `;
            });

            html += '</div>';
            return html;
        }

        // Cerrar modal de contratos
        function cerrarModalContratos() {
            document.getElementById('modalContratos').style.display = 'none';
        }

        // Cargar faltas pendientes
        function cargarFaltasPendientes() {
            fetch('obtener_faltas_pendientes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        faltasData = data;
                        actualizarIndicadorFaltas(data);

                        // Actualizar el enlace con la URL correcta
                        const faltasLink = document.getElementById('faltasLink');
                        if (faltasLink && data.url_faltas) {
                            faltasLink.href = data.url_faltas;
                        }
                    } else {
                        console.error('Error:', data.message);
                        document.getElementById('faltasContainer').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error de conexión:', error);
                    document.getElementById('faltasContainer').style.display = 'none';
                });
        }

        // Actualizar indicador de faltas
        function actualizarIndicadorFaltas(data) {
            const container = document.getElementById('faltasContainer');
            const countElement = document.getElementById('faltasCount');
            const fechaElement = document.getElementById('faltasFecha');

            container.style.display = 'block'; // ← SIEMPRE MOSTRAR

            if (data.total_pendientes > 0) {
                countElement.textContent = data.total_pendientes;

                // Actualizar fecha según días restantes
                let fechaTexto = '';
                let colorClase = 'indicator-status ' + data.color_indicador;

                if (data.dias_restantes < 0) {
                    fechaTexto = `(Vencido hace ${Math.abs(data.dias_restantes)} días)`;
                } else if (data.dias_restantes === 0) {
                    fechaTexto = '(Vence hoy)';
                } else {
                    fechaTexto = `(${data.dias_restantes} días restantes)`;
                }

                fechaElement.textContent = fechaTexto;

                // Aplicar clase de color al card
                const card = container.querySelector('.indicator-status');
                card.className = 'indicator-status ' + data.color_indicador;
            } else {
                countElement.textContent = '0';
                fechaElement.textContent = '(Sin faltas pendientes)';
                // Aplicar color verde
                const card = container.querySelector('.indicator-status');
                card.className = 'indicator-status verde';
            }
        }

        // Formatear fecha corta
        function formatoFechaCorta(fecha) {
            const fechaObj = new Date(fecha + 'T00:00:00');
            const opciones = { day: '2-digit', month: 'short', year: 'numeric' };
            return fechaObj.toLocaleDateString('es-ES', opciones);
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function (event) {
            const modalFaltas = document.getElementById('modalFaltas');
            const modalContratos = document.getElementById('modalContratos');
            const modalAusencias = document.getElementById('modalAusencias');

            if (event.target === modalFaltas) {
                cerrarModalFaltas();
            }
            if (event.target === modalContratos) {
                cerrarModalContratos();
            }
            if (event.target === modalAusencias) {
                cerrarModalAusencias();
            }
        }

        // Funciones para los modales de RH
        function mostrarModalTardanzasRH() {
            document.getElementById('modalTardanzasRH').style.display = 'block';
        }

        function cerrarModalTardanzasRH() {
            document.getElementById('modalTardanzasRH').style.display = 'none';
        }

        function mostrarModalFaltasRH() {
            document.getElementById('modalFaltasRH').style.display = 'block';
        }

        function cerrarModalFaltasRH() {
            document.getElementById('modalFaltasRH').style.display = 'none';
        }

        // Actualizar el evento onclick para incluir los nuevos modales
        window.onclick = function (event) {
            const modals = ['modalAusenciasColaboradores', 'modalTardanzasRH', 'modalFaltasRH', 'modalContratos'];

            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    if (modalId === 'modalAusenciasColaboradores') cerrarModalAusenciasColaboradores();
                    if (modalId === 'modalTardanzasRH') cerrarModalTardanzasRH();
                    if (modalId === 'modalFaltasRH') cerrarModalFaltasRH();
                    if (modalId === 'modalContratos') cerrarModalContratos();
                }
            });
        }

        // Cerrar modales con tecla ESC
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                cerrarModalTardanzasRH();
                cerrarModalFaltasRH();
                cerrarModalContratos();
                cerrarModalAusencias();
            }
        });

        // Variables globales para ausencias colaboradores
        let ausenciasColabData = null;

        // Cargar ausencias colaboradores al iniciar
        document.addEventListener('DOMContentLoaded', function () {
            cargarAusenciasColaboradores();
        });

        // Cargar ausencias colaboradores
        function cargarAusenciasColaboradores() {
            fetch('obtener_ausencias_colaboradores.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        ausenciasColabData = data;
                        actualizarIndicadorAusenciasColab(data);
                    } else {
                        console.error('Error:', data.message);
                        // Mostrar siempre el contador
                        document.getElementById('ausenciasColabCount').textContent = '0';
                        document.getElementById('ausenciasColabFecha').textContent = '(Error)';
                        const card = document.querySelector('.indicator-status ');
                        if (card) card.className = 'indicator-status verde';
                    }
                })
                .catch(error => {
                    console.error('Error de conexión:', error);
                    document.getElementById('ausenciasColabCount').textContent = '0';
                    document.getElementById('ausenciasColabFecha').textContent = '(Error conexión)';
                    const card = document.querySelector('.indicator-status ');
                    if (card) card.className = 'indicator-status verde';
                });
        }

        // Actualizar indicador de ausencias colaboradores
        function actualizarIndicadorAusenciasColab(data) {
            const countElement = document.getElementById('ausenciasColabCount');
            const fechaElement = document.getElementById('ausenciasColabFecha');
            const card = document.querySelector('.indicator-status ');

            countElement.textContent = data.total_ausencias;

            if (data.total_ausencias === 0) {
                fechaElement.textContent = '(Sin casos)';
                card.className = 'indicator-status verde';
            } else {
                // Encontrar la ausencia más prolongada
                let diasMaximos = 0;
                data.ausencias.forEach(ausencia => {
                    if (ausencia.dias_consecutivos > diasMaximos) {
                        diasMaximos = ausencia.dias_consecutivos;
                    }
                });

                fechaElement.textContent = `(hasta ${diasMaximos} días)`;
                card.className = 'indicator-status ' + data.color_indicador;
            }
        }

        // Mostrar modal de ausencias colaboradores
        function mostrarModalAusenciasColaboradores() {
            if (!ausenciasColabData) {
                alert('Cargando datos...');
                return;
            }

            const modal = document.getElementById('modalAusenciasColaboradores');
            const lista = document.getElementById('listaAusenciasColaboradores');
            const totalInfo = document.getElementById('totalAusenciasColabInfo');

            // Actualizar información
            totalInfo.textContent = ausenciasColabData.total_ausencias;

            // Construir lista de ausencias
            lista.innerHTML = construirListaAusenciasColab(ausenciasColabData.ausencias);

            modal.style.display = 'block';
        }

        // Construir lista de ausencias colaboradores
        function construirListaAusenciasColab(ausencias) {
            if (ausencias.length === 0) {
                return '<p style="text-align: center; color: #6c757d; padding: 20px;">No hay colaboradores con ausencias de 3+ días consecutivos con estado "Activo" u "Otra.Tienda" sin marcación</p>';
            }

            let html = '<div class="lista-faltas">';

            ausencias.forEach(ausencia => {
                const nombreCompleto = ausencia.nombre_completo;
                const celular = ausencia.Celular || 'No registrado';
                const diasConsecutivos = ausencia.dias_consecutivos;
                const ultimaMarcacion = ausencia.ultima_marcacion ? formatoFechaCorta(ausencia.ultima_marcacion) : 'Sin registros recientes';
                const sucursal = ausencia.sucursal_nombre || 'Sin asignar';

                // Determinar color según días sin marcar
                let colorClase = '';
                if (diasConsecutivos >= 7) {
                    colorClase = 'status-inactivo';
                } else if (diasConsecutivos >= 5) {
                    colorClase = 'status-alerta';
                } else {
                    colorClase = 'status-info';
                }

                // Construir detalle de días
                let detalleDiasHTML = '';
                if (ausencia.detalle_dias && ausencia.detalle_dias.length > 0) {
                    detalleDiasHTML = '<div style="margin-top: 5px; font-size: 0.9em;">';
                    detalleDiasHTML += '<strong>Períodos encontrados:</strong><br>';
                    ausencia.detalle_dias.forEach(periodo => {
                        if (periodo.dias && periodo.dias.length > 0) {
                            const primerDia = periodo.dias[periodo.dias.length - 1]; // El más antiguo
                            const ultimoDia = periodo.dias[0]; // El más reciente
                            detalleDiasHTML += `• ${periodo.dias_consecutivos} días: ${formatoFechaCorta(primerDia.fecha)} al ${formatoFechaCorta(ultimoDia.fecha)}<br>`;
                        }
                    });
                    detalleDiasHTML += '</div>';
                }

                html += `
                    <div class="item-falta">
                        <div class="falta-info">
                            <h4>
                                <i class="fas fa-user-clock"></i>${nombreCompleto}
                                <small>
                                    (${ausencia.CodOperario})
                                </small>
                            </h4>
                            <p>
                                <strong>Sucursal:</strong> ${sucursal} | 
                                <strong>Celular:</strong> ${celular}
                            </p>
                            <p>
                                <strong>Días consecutivos sin marcar:</strong> 
                                <span class="${colorClase}">${diasConsecutivos} días</span>
                                <small style="color: #6c757d; margin-left: 10px;">
                                    (estado: Activo/Otra.Tienda)
                                </small>
                            </p>
                            <p>
                                <strong>Última marcación:</strong> ${ultimaMarcacion}
                            </p>
                            ${detalleDiasHTML}
                            <small style="color: #6c757d;">
                                <i class="fas fa-info-circle"></i> 
                                Solo se consideran días con estado "Activo" u "Otra.Tienda" en horario programado
                            </small>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            ${celular !== 'No registrado' ? `
                                <a href="tel:${celular}" class="btn-revisar llamar">
                                    <i class="fas fa-phone"></i> Llamar
                                </a>
                            ` : ''}
                            <a href="ver_marcaciones_todas.php?operario_id=${ausencia.CodOperario}" class="btn-revisar">
                                <i class="fas fa-eye"></i> Marcaciones
                            </a>
                            <a href="editar_colaborador.php?id=${ausencia.CodOperario}" class="btn-revisar" style="background: #6c757d;">
                                <i class="fas fa-user-edit"></i> Editar
                            </a>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            return html;
        }

        // Cerrar modal de ausencias colaboradores
        function cerrarModalAusenciasColaboradores() {
            document.getElementById('modalAusenciasColaboradores').style.display = 'none';
        }

        // Función auxiliar para formatear fecha corta
        function formatoFechaCorta(fecha) {
            if (!fecha) return 'N/A';
            const fechaObj = new Date(fecha + 'T00:00:00');
            const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            const dia = fechaObj.getDate().toString().padStart(2, '0');
            const mes = meses[fechaObj.getMonth()];
            const año = fechaObj.getFullYear().toString().slice(-2);
            return `${dia}-${mes}-${año}`;
        }
    </script>
</body>

</html>