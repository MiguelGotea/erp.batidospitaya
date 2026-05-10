<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/layout/menu_lateral.php';


$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];  
//verificarAccesoModulo('operaciones');
verificarAccesoCargo([11, 16, 36, 55]);

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
if (!verificarAccesoCargo([11, 16, 36, 55])) {
    header('Location: ../index.php');
    exit();
}

// Obtener todas las sucursales
$sucursales = obtenerTodasSucursales();

// Funciones necesarias para calcular faltas pendientes (las mismas que en líderes)
function obtenerTotalFaltasAutomaticas($codSucursal, $fechaDesde, $fechaHasta) {
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
function obtenerTotalTardanzasAutomaticas($codSucursal, $fechaDesde, $fechaHasta) {
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

function obtenerOperariosSucursalEnRango($codSucursal, $fechaDesde, $fechaHasta) {
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

function obtenerDiasLaborablesOperario($codOperario, $codSucursal, $fechaDesde, $fechaHasta) {
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
                'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 
                'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'domingo' => 7
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

function obtenerMarcacionEntrada($codOperario, $fecha) {
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

function obtenerTotalFaltasManuales($codSucursal, $fechaDesde, $fechaHasta) {
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

function verificarTardanza($codOperario, $codSucursal, $fecha, $horaMarcada) {
    global $conn;
    
    // Obtener la semana a la que pertenece esta fecha
    $semana = obtenerSemanaPorFecha($fecha);
    if (!$semana) return false;
    
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

function obtenerTotalTardanzasManuales($codSucursal, $fechaDesde, $fechaHasta) {
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

// ========== FUNCIONES PARA INDICADORES DE TARDANZAS Y FALTAS (COMO LÍDERES) ==========

/**
 * Obtiene el total de tardanzas pendientes de reportar para Operaciones (todas las sucursales)
 */
function obtenerTardanzasPendientesOperaciones() {
    global $conn;
    
    // Determinar el periodo a revisar según el día del mes (misma lógica que líderes)
    $hoy = new DateTime();
    $diaMes = (int)$hoy->format('d');
    $diasRestantes = calcularDiasRestantesReporteOperaciones();
    
    if ($diaMes <= 2) {
        // Días 1-2: revisar mes anterior
        $mesRevisar = new DateTime('first day of last month');
        $fechaDesde = $mesRevisar->format('Y-m-01');
        $fechaHasta = $mesRevisar->format('Y-m-t');
        $periodo = 'mes_anterior';
        $mesNombre = obtenerMesEspanolOperaciones($mesRevisar) . ' ' . $mesRevisar->format('Y');
    } else {
        // Días 3+: revisar mes actual
        $fechaDesde = $hoy->format('Y-m-01');
        $fechaHasta = $hoy->format('Y-m-t');
        $periodo = 'mes_actual';
        $mesNombre = obtenerMesEspanolOperaciones($hoy) . ' ' . $hoy->format('Y');
    }
    
    // Obtener todas las sucursales para Operaciones
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
            'url_tardanzas' => '../lideres/tardanzas_manual.php',
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
        $color = determinarColorTardanzasOperaciones($totalTardanzas, $diasRestantes);
        
        // Construir URL con parámetros
        $urlTardanzas = "../lideres/tardanzas_manual.php?" . http_build_query([
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'sucursales' => implode(',', $sucursalesCodigos),
            'modo' => 'operaciones',
            'periodo' => $periodo
        ]);
        
        return [
            'total' => $totalTardanzas,
            'color' => $color,
            'texto' => obtenerTextoIndicadorTardanzasOperaciones($totalTardanzas, $periodo, $mesNombre),
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
        error_log("Error obteniendo tardanzas pendientes Operaciones: " . $e->getMessage());
        
        return [
            'total' => 0,
            'color' => 'verde',
            'texto' => 'Error en cálculo',
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_tardanzas' => '../lideres/tardanzas_manual.php',
            'mes_nombre' => $mesNombre,
            'detalles' => []
        ];
    }
}

/**
 * Obtiene el total de faltas/ausencias pendientes de reportar para Operaciones (todas las sucursales)
 */
function obtenerFaltasPendientesOperaciones() {
    global $conn;
    
    // Determinar el periodo a revisar según el día del mes
    $hoy = new DateTime();
    $diaMes = (int)$hoy->format('d');
    $diasRestantes = calcularDiasRestantesReporteFaltasOperaciones();
    
    if ($diaMes <= 1) {
        // Día 1: revisar mes anterior
        $mesRevisar = new DateTime('first day of last month');
        $fechaDesde = $mesRevisar->format('Y-m-01');
        $fechaHasta = $mesRevisar->format('Y-m-t');
        $periodo = 'mes_anterior';
        $mesNombre = obtenerMesEspanolOperaciones($mesRevisar) . ' ' . $mesRevisar->format('Y');
    } else {
        // Días 2+: revisar mes actual (hasta ayer para evitar futuros)
        $fechaDesde = $hoy->format('Y-m-01');
        $fechaHasta = date('Y-m-d', strtotime('-1 day'));
        $periodo = 'mes_actual';
        $mesNombre = obtenerMesEspanolOperaciones($hoy) . ' ' . $hoy->format('Y');
    }
    
    // Obtener todas las sucursales para Operaciones
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
        $color = determinarColorFaltasOperaciones($totalFaltas, $diasRestantes);
        
        // Construir URL con parámetros
        $urlFaltas = "../lideres/faltas_manual.php?" . http_build_query([
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'sucursales' => implode(',', $sucursalesCodigos),
            'modo' => 'operaciones',
            'periodo' => $periodo
        ]);
        
        return [
            'total' => $totalFaltas,
            'color' => $color,
            'texto' => obtenerTextoIndicadorFaltasOperaciones($totalFaltas, $periodo, $mesNombre),
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
        error_log("Error obteniendo faltas pendientes Operaciones: " . $e->getMessage());
        
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

// Funciones auxiliares para Operaciones
function calcularDiasRestantesReporteOperaciones() {
    $hoy = new DateTime();
    $diaMes = (int)$hoy->format('d');
    
    if ($diaMes <= 2) {
        return max(0, 2 - $diaMes);
    } else {
        $proximoMes = new DateTime('first day of next month');
        $proximoMes->modify('+1 day');
        $diferencia = $hoy->diff($proximoMes);
        return $diferencia->days;
    }
}

function determinarColorTardanzasOperaciones($totalTardanzas, $diasRestantes) {
    if ($totalTardanzas == 0) return 'verde';
    if ($diasRestantes <= 0) return 'rojo';
    if ($diasRestantes <= 1) return 'rojo';
    if ($diasRestantes <= 2) return 'amarillo';
    return 'verde';
}

function obtenerTextoIndicadorTardanzasOperaciones($totalTardanzas, $periodo, $mesNombre) {
    if ($totalTardanzas == 0) return 'Sin tardanzas pendientes';
    $mesTexto = ($periodo === 'mes_anterior') ? 'del mes anterior' : 'del mes actual';
    return "$totalTardanzas tardanzas pendientes $mesTexto";
}

function calcularDiasRestantesReporteFaltasOperaciones() {
    $hoy = new DateTime();
    $diaMes = (int)$hoy->format('d');
    
    if ($diaMes <= 1) return 0;
    
    $proximoMes = new DateTime('first day of next month');
    $diferencia = $hoy->diff($proximoMes);
    return $diferencia->days;
}

function determinarColorFaltasOperaciones($totalFaltas, $diasRestantes) {
    if ($totalFaltas == 0) return 'verde';
    if ($diasRestantes <= 0) return 'rojo';
    if ($diasRestantes <= 1) return 'rojo';
    if ($diasRestantes <= 3) return 'amarillo';
    return 'verde';
}

function obtenerTextoIndicadorFaltasOperaciones($totalFaltas, $periodo, $mesNombre) {
    if ($totalFaltas == 0) return 'Sin faltas pendientes';
    $mesTexto = ($periodo === 'mes_anterior') ? 'del mes anterior' : 'del mes actual';
    return "$totalFaltas faltas pendientes $mesTexto";
}

function obtenerMesEspanolOperaciones($fecha) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[(int)$fecha->format('m')];
}

function formatoFechaOperaciones($fecha) {
    return date('d/m/Y', strtotime($fecha));
}

function formatoHoraAmPmOperaciones($hora) {
    return date('h:i A', strtotime($hora));
}

// Obtener tardanzas y faltas pendientes para Operaciones
$tardanzasPendientesOperaciones = obtenerTardanzasPendientesOperaciones();
$faltasPendientesOperaciones = obtenerFaltasPendientesOperaciones();

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operaciones - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/indexmodulos.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/css/indexmodulos.css') ?>"> <!-- CSS propio con manejo de versiones  evitar cache de buscador -->
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
            background: #51B8AC;
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
    background-color: rgba(0,0,0,0.5);
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
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
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

/* Lista de tardanzas pendientes */
.lista-tardanzas {
    display: grid;
    gap: 15px;
}

.item-tardanza {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.item-tardanza:hover {
    background: #e9ecef;
    transform: translateX(5px);
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.tardanza-info h4 {
    margin: 0 0 8px 0;
    color: #495057;
    font-size: 1.1rem !important;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tardanza-info p {
    margin: 0 0 5px 0;
    font-size: 0.9rem;
    color: #6c757d;
}

.tardanza-info small {
    color: #868e96;
    font-size: 0.8rem;
}

.btn-justificar {
    background: #51B8AC;
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

.btn-justificar:hover {
    background: #0E544C;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

/* Alertas */
.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.alert-warning h4 {
    color: #856404;
    margin-bottom: 10px;
}

.alert-warning i {
    color: #ffc107;
    margin-bottom: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .modal-content-pendientes {
        margin: 10% auto;
        width: 95%;
    }
    
    .item-tardanza {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .btn-justificar {
        margin-left: 0;
        width: 100%;
        text-align: center;
    }
    
    .pendientes-content {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .pendientes-info {
        text-align: center;
    }
    
    .pendientes-fecha {
        font-size: 0.7rem !important;
    }
    
    .indicadores-container {
        flex-direction: column;
        align-items: center;
    }
    
    .pendientes-container {
        min-width: 100%;
        max-width: 100%;
    }
}


    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, ''); ?>
            <!-- Obtener cantidad de anuncios no leídos -->
            <?php
            $cantidadAnunciosNoLeidos = 0;
            if (isset($_SESSION['usuario_id'])) {
                $cantidadAnunciosNoLeidos = obtenerCantidadAnunciosNoLeidos($_SESSION['usuario_id']);
            }
            ?>
            <h2 class="section-title">
                <i class="fas fa-chart-line"></i> Indicadores de Control
            </h2>
            <!-- Contenedor para indicadores -->
            <div class="indicadores-container">
                
                <!-- Indicador de Anuncios Nuevos -->
                <div class="indicator-container">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>
                    <div class="indicator-count" id="anunciosCount">
                    </div>
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Nuevos Anuncios
                        </div>
                        <div class="indicator-meta">
                            <span id="anunciosContainer">
                                <span class="indicator-status" id="anunciosFecha">
                                    
                                </span>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Indicador de Tardanzas Pendientes -->
                <div class="indicator-container" style="Display: None">  
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>
                    
                    <div class="indicator-count" id="tardanzasCount">
                        0
                    </div>
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Tardanzas Pendientes de Inverstigacion
                        </div>
                        
                        <div class="indicator-meta">
                            <span id="tardanzasContainer">
                                <span class="indicator-status" id="tardanzasFecha">
                                    <!-- Se llenará con JavaScript -->
                                </span>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </div>
            
                <!-- Indicador de Feriados Pendientes -->
                <div class="indicator-container">  
                
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>
                    
                    <div class="indicator-count" id="feriadosCount">
                        0
                    </div>
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Feriados por Reportar Status
                        </div>
                        <div class="indicator-meta">
                            <span id="feriadosContainer">
                                <span class="indicator-status" id="feriadosFecha">
                                    <!-- Se llenará con JavaScript -->
                                </span>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                
                </div>
                
                <!-- Indicador de Reclamos Pendientes -->
                <div class="indicator-container">  
                
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>
                    
                    <div class="indicator-count" id="reclamosCount">
                        0
                    </div>
                    
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Reclamos Pendientes
                        </div>
                        
                        <div class="indicator-meta">
                            <span id="reclamosContainer">
                                <span class="indicator-status" id="reclamosFecha">
                                    Tolerancia: 7 días
                                </span>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                
                </div>
                
                <!-- Indicador de KPI Pendientes -->
                <div class="indicator-container">                  
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>                    
                    <div class="indicator-count" id="kpiCount">
                        0
                    </div>                    
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            KPI Semanales Pendientes
                        </div>                        
                        <div class="indicator-meta">
                            <span id="kpiContainer">
                                <span class="indicator-status" id="kpiFecha">
                                    Mes actual
                                </span>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>                
                </div>
                
                <!-- Indicadores de Tardanzas y Faltas Pendientes (como líderes) -->
                <div class="indicator-container"  onclick="mostrarModalTardanzasOperaciones()" style="cursor: pointer;">                  
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>                    
                    <div class="indicator-count">
                        <?= $tardanzasPendientesOperaciones['total'] ?>
                    </div>                    
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Tardanzas No Reportadas + No Validas
                        </div>                        
                        <div class="indicator-meta">
                            <span class="indicator-status <?= $tardanzasPendientesOperaciones['color'] ?>" id="tardanzasFechaOperaciones">
                                    <?php 
                                    $diasRestantes = $tardanzasPendientesOperaciones['dias_restantes'];
                                    if ($tardanzasPendientesOperaciones['total'] == 0) {
                                        echo 'Al día';
                                    } elseif ($diasRestantes < 0) {
                                        echo 'Vencido hace ' . abs($diasRestantes) . ' días';
                                    } elseif ($diasRestantes === 0) {
                                        echo 'Vence hoy';
                                    } else {
                                        echo $diasRestantes . ' días restantes';
                                    }
                                    ?>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>                
                </div>
 
                 <div class="indicator-container" onclick="mostrarModalFaltasOperaciones()" style="cursor: pointer;">                  
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>                    
                    <div class="indicator-count">
                        <?= $faltasPendientesOperaciones['total'] ?>
                    </div>                    
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Faltas No Reportadas + No Validas 
                        </div>                        
                        <div class="indicator-meta">
                            <span class="indicator-status <?= $faltasPendientesOperaciones['color'] ?>" id="faltasFechaOperaciones">
                                <?php 
                                $diasRestantes = $faltasPendientesOperaciones['dias_restantes'];
                                if ($faltasPendientesOperaciones['total'] == 0) {
                                    echo 'Al día';
                                } elseif ($diasRestantes < 0) {
                                    echo 'Vencido hace ' . abs($diasRestantes) . ' días';
                                } elseif ($diasRestantes === 0) {
                                    echo 'Vence hoy';
                                } else {
                                    echo $diasRestantes . ' días restantes';
                                }
                                ?>                                
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>                
                </div>               
            </div>
            
            <!-- Modal para detalles de KPI pendientes -->
            <div id="modalKPI" class="modal-pendientes">
                <div class="modal-content-pendientes">
                    <div class="modal-header-pendientes">
                        <h3>KPI Pendientes de Actualización</h3>
                        <span class="close-modal" onclick="cerrarModalKPI()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="info-fecha-limite">
                            <p><strong>Periodo:</strong> <span id="periodoKPIInfo"></span></p>
                            <p><strong>Completitud:</strong> <span id="completitudKPIInfo"></span></p>
                            <p><strong>Sucursales pendientes:</strong> <span id="pendientesKPIInfo">0</span> de <span id="totalKPIInfo">0</span></p>
                        </div>
                        <div id="listaKPIPendientes"></div>
                    </div>
                </div>
            </div>
            
            <!-- Modal de restricción para KPI -->
            <div id="modalRestriccionKPI" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 500px;">
                    <div class="modal-header-pendientes" style="background: #0E544C;">
                        <h3><i class="fas fa-ban"></i> Restricción de Acceso - KPI</h3>
                        <span class="close-modal" onclick="cerrarModalRestriccionKPI()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="alert alert-warning" style="text-align: center; border: none; background: transparent;">
                            <i class="fas fa-exclamation-triangle fa-3x" style="color: #dc3545; margin-bottom: 15px;"></i>
                            <h4 style="color: #dc3545; margin-bottom: 15px;">Acceso Restringido</h4>
                            <p style="color: #666; line-height: 1.5;">
                                Tiene <strong style="color: #dc3545;" id="cantidadKPIRestriccion">0</strong> 
                                sucursales pendientes de actualizar KPI.
                            </p>
                            <p style="color: #666; margin-top: 10px;">
                                El porcentaje de completitud es del <strong id="porcentajeKPIRestriccion">0%</strong> 
                                (mínimo requerido: 70%).
                            </p>
                            <p style="color: #666; margin-top: 10px;">
                                <strong>Por favor, proceda a actualizar los KPI pendientes para acceder a las herramientas del sistema.</strong>
                            </p>
                        </div>
                        <div class="modal-actions" style="text-align: center;">
                            <button type="button" onclick="irAKPI()" class="btn btn-primary" style="padding: 10px 30px; margin: 5px;">
                                <i class="fas fa-chart-line"></i> Ir a KPI
                            </button>
                            <button type="button" onclick="cerrarModalRestriccionKPI()" class="btn btn-secondary" style="margin: 5px;">
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal de Detalles de Tardanzas Pendientes Operaciones -->
            <div id="modalTardanzasOperaciones" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 90%;">
                    <div class="modal-header-pendientes">
                        <h3><i class="fas fa-list"></i> Detalles de Tardanzas Pendientes de Reportar por Líderes</h3>
                        <span class="close-modal" onclick="cerrarModalTardanzasOperaciones()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="filtros-modal" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <div>
                                <strong>Periodo:</strong> 
                                <?= date('d/m/Y', strtotime($tardanzasPendientesOperaciones['fecha_desde'])) ?> - 
                                <?= date('d/m/Y', strtotime($tardanzasPendientesOperaciones['fecha_hasta'])) ?> 
                                | <strong>Total:</strong> <?= $tardanzasPendientesOperaciones['total'] ?> tardanzas
                                <?php 
                                $diasRestantes = $tardanzasPendientesOperaciones['dias_restantes'];
                                if ($diasRestantes < 0) {
                                    echo "<span style='color: #dc3545;'> (Vencido hace " . abs($diasRestantes) . " días)</span>";
                                } elseif ($diasRestantes === 0) {
                                    echo "<span style='color: #dc3545;'> (Vence hoy)</span>";
                                } else {
                                    echo " (" . $diasRestantes . " días restantes)";
                                }
                                ?>
                            </div>
                            <a href="../rh/ver_marcaciones_todas.php" class="btn-ver-detalles" target="_blank">
                                <i class="fas fa-external-link-alt"></i> Ver Tardanzas
                            </a>
                        </div>
                        
                        <?php if (empty($tardanzasPendientesOperaciones['detalles'])): ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-check-circle" style="font-size: 3rem; color: #28a745; margin-bottom: 15px;"></i>
                                <h4>No hay tardanzas pendientes de reportar</h4>
                                <p>Todas las tardanzas han sido reportadas correctamente.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto; max-height: 60vh;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #0E544C; color: white;">
                                            <th style="padding: 12px; text-align: left;">Colaborador</th>
                                            <th style="padding: 12px; text-align: center;">Sucursal</th>
                                            <th style="padding: 12px; text-align: center;">Fecha</th>
                                            <th style="padding: 12px; text-align: center;">Horario Programado</th>
                                            <th style="padding: 12px; text-align: center;">Hora Marcada</th>
                                            <th style="padding: 12px; text-align: center;">Minutos de Tardanza</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tardanzasPendientesOperaciones['detalles'] as $index => $tardanza): ?>
                                            <tr style="background: <?= $index % 2 === 0 ? '#f8f9fa' : 'white' ?>;">
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                                                    <strong><?= htmlspecialchars($tardanza['nombre_completo']) ?></strong>
                                                    <br><small>Código: <?= $tardanza['CodOperario'] ?></small>
                                                </td>
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= htmlspecialchars($tardanza['sucursal_nombre']) ?>
                                                </td>
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= formatoFechaOperaciones($tardanza['fecha']) ?>
                                                </td>
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= $tardanza['hora_programada'] ? formatoHoraAmPmOperaciones($tardanza['hora_programada']) : 'N/A' ?>
                                                </td>
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= formatoHoraAmPmOperaciones($tardanza['hora_ingreso']) ?>
                                                </td>
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
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
            
            <!-- Modal de Detalles de Faltas Pendientes Operaciones -->
            <div id="modalFaltasOperaciones" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 90%;">
                    <div class="modal-header-pendientes">
                        <h3><i class="fas fa-list"></i> Detalles de Faltas Pendientes de Reportar por Líderes</h3>
                        <span class="close-modal" onclick="cerrarModalFaltasOperaciones()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="filtros-modal" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <div>
                                <strong>Periodo:</strong> 
                                <?= date('d/m/Y', strtotime($faltasPendientesOperaciones['fecha_desde'])) ?> - 
                                <?= date('d/m/Y', strtotime($faltasPendientesOperaciones['fecha_hasta'])) ?> 
                                | <strong>Total:</strong> <?= $faltasPendientesOperaciones['total'] ?> faltas
                                <?php 
                                $diasRestantes = $faltasPendientesOperaciones['dias_restantes'];
                                if ($diasRestantes < 0) {
                                    echo "<span style='color: #dc3545;'> (Vencido hace " . abs($diasRestantes) . " días)</span>";
                                } elseif ($diasRestantes === 0) {
                                } else {
                                    echo " (" . $diasRestantes . " días restantes)";
                                }
                                ?>
                            </div>
                            <a href="../rh/ver_marcaciones_todas.php" class="btn-ver-detalles" target="_blank">
                                <i class="fas fa-external-link-alt"></i> Ver Faltas
                            </a>
                        </div>
                        
                        <?php if (empty($faltasPendientesOperaciones['detalles'])): ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-check-circle" style="font-size: 3rem; color: #28a745; margin-bottom: 15px;"></i>
                                <h4>No hay faltas pendientes de reportar</h4>
                                <p>Todas las ausencias han sido reportadas correctamente.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto; max-height: 60vh;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #0E544C; color: white;">
                                            <th style="padding: 12px; text-align: left;">Colaborador</th>
                                            <th style="padding: 12px; text-align: center;">Sucursal</th>
                                            <th style="padding: 12px; text-align: center;">Fecha</th>
                                            <th style="padding: 12px; text-align: center;">Horario Programado</th>
                                            <th style="padding: 12px; text-align: center;">Estado Día</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($faltasPendientesOperaciones['detalles'] as $index => $falta): ?>
                                            <tr style="background: <?= $index % 2 === 0 ? '#f8f9fa' : 'white' ?>;">
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                                                    <strong><?= htmlspecialchars($falta['nombre_completo']) ?></strong>
                                                    <br><small>Código: <?= $falta['cod_operario'] ?></small>
                                                </td>
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= htmlspecialchars($falta['sucursal_nombre']) ?>
                                                </td>
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= formatoFechaOperaciones($falta['fecha']) ?>
                                                </td>
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= $falta['hora_entrada_programada'] ? formatoHoraAmPmOperaciones($falta['hora_entrada_programada']) : 'N/A' ?> - 
                                                    <?= $falta['hora_salida_programada'] ? formatoHoraAmPmOperaciones($falta['hora_salida_programada']) : 'N/A' ?>
                                                </td>
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
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
 
            <h2 class="section-title">
                <i class="fas fa-bolt"></i> Accesos Rápidos
            </h2>          
            
            <div class="quick-access-grid">
                <a href="horas_extras_manual.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="quick-access-title">Horas Extras</div>
                </a>
                
                <a href="feriados.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="quick-access-title">Feriados</div>
                </a>
                
                <a href="../supervision/auditorias_original/agregarAviso.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-search-dollar"></i>
                    </div>
                    <div class="quick-access-title">Nuevo Aviso</div>
                </a>
                
                <a href="../supervision/auditorias_original/kpi.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="quick-access-title">Registrar KPIs</div>
                </a>
                
                <a href="../supervision/auditorias_original/reclamospend.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="quick-access-title">Procesar Reclamos</div>
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Variables globales para indicadores
        let anunciosData = null;
        let tardanzasData = null;
        let feriadosData = null;
        let reclamosData = null;
        let kpiData = null;
        
        // Cargar indicadores al iniciar
        document.addEventListener('DOMContentLoaded', function() {
            cargarAnunciosNuevos();
            cargarTardanzasPendientes();
            cargarFeriadosPendientes();
            cargarReclamosPendientes();
            cargarKPIPendientes();
            
            // Hacer clickeables las tarjetas de indicadores
            const anunciosCard = document.querySelector('.indicator-container:has(#anunciosCount)');
            const tardanzasCard = document.querySelector('.indicator-container:has(#tardanzasCount)');
            const feriadosCard = document.querySelector('.indicator-container:has(#feriadosCount)');
            const reclamosCard = document.querySelector('.indicator-container:has(#reclamosCount)');
            const kpiCard = document.querySelector('.indicator-container:has(#kpiCount)');
            
            // Anuncios: redirigir y marcar como leídos
            if (anunciosCard) {
                anunciosCard.style.cursor = 'pointer';
                anunciosCard.addEventListener('click', function() {
                    irAAnuncios();
                });
            }
            
            // Tardanzas: redirigir directamente
            if (tardanzasCard) {
                tardanzasCard.style.cursor = 'pointer';
                tardanzasCard.addEventListener('click', function() {
                    irATardanzas();
                });
            }
            
            // Feriados: redirigir directamente
            if (feriadosCard) {
                feriadosCard.style.cursor = 'pointer';
                feriadosCard.addEventListener('click', function() {
                    irAFeriados();
                });
            }
            
            // Reclamos: redirigir directamente
            if (reclamosCard) {
                reclamosCard.style.cursor = 'pointer';
                reclamosCard.addEventListener('click', function() {
                    irAReclamos();
                });
            }
            
            // KPI: mantener modal
            if (kpiCard) {
                kpiCard.style.cursor = 'pointer';
                kpiCard.addEventListener('click', function(e) {
                    if (!e.target.classList.contains('btn-ver-detalles') && 
                        !e.target.closest('.btn-ver-detalles')) {
                        mostrarModalKPI();
                    }
                });
            }
        });
        
        // ========== FUNCIONES PARA ANUNCIOS NUEVOS ==========
        
        // Cargar anuncios nuevos
        function cargarAnunciosNuevos() {
            // Usar los datos de PHP directamente
            const cantidadAnuncios = <?= $cantidadAnunciosNoLeidos ?>;
            anunciosData = {
                total_pendientes: cantidadAnuncios,
                color_indicador: cantidadAnuncios > 0 ? 'rojo' : 'verde'
            };
            actualizarIndicadorAnuncios(anunciosData);
        }
        
        // Actualizar indicador de anuncios
        function actualizarIndicadorAnuncios(data) {
            const container = document.getElementById('anunciosContainer');
            const countElement = document.getElementById('anunciosCount');
            const fechaElement = document.getElementById('anunciosFecha');
            
            // MOSTRAR SIEMPRE, incluso si es cero
            container.style.display = 'block';
            countElement.textContent = data.total_pendientes;
            
            // Actualizar texto según cantidad
            let fechaTexto = '';
            if (data.total_pendientes === 0) {
                fechaTexto = '(Al día)';
            } else if (data.total_pendientes === 1) {
                fechaTexto = '(1 sin leer)';
            } else {
                fechaTexto = `(${data.total_pendientes} sin leer)`;
            }
            
            fechaElement.textContent = fechaTexto;
            
            // Aplicar clase de color
            let colorClase = data.color_indicador;
            container.className = 'indicator-status ' + colorClase;
        }
        
        // Ir a la página de anuncios
        function irAAnuncios() {
            // Primero marcar todos como leídos
            fetch('../supervision/auditorias_original/marcar_anuncios_leidos.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Actualizar el indicador localmente
                        anunciosData.total_pendientes = 0;
                        actualizarIndicadorAnuncios(anunciosData);
                    }
                    // Redirigir a anuncios
                    window.location.href = '../supervision/auditorias_original/index_avisos.php';
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Redirigir incluso si hay error
                    window.location.href = '../supervision/auditorias_original/index_avisos.php';
                });
        }
        
        // ========== FUNCIONES PARA TARDANZAS ==========
        
        // Cargar tardanzas pendientes
        function cargarTardanzasPendientes() {
            fetch('ajax/obtener_tardanzas_pendientes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        tardanzasData = data;
                        actualizarIndicadorTardanzas(data);
                    } else {
                        console.error('Error:', data.message);
                        // Mostrar con valor 0
                        document.getElementById('tardanzasCount').textContent = '0';
                        document.getElementById('tardanzasContainer').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error de conexión:', error);
                    // Mostrar con valor 0
                    document.getElementById('tardanzasCount').textContent = '0';
                    document.getElementById('tardanzasContainer').style.display = 'block';
                });
        }
        
        // Actualizar indicador de tardanzas
        function actualizarIndicadorTardanzas(data) {
            const container = document.getElementById('tardanzasContainer');
            const countElement = document.getElementById('tardanzasCount');
            const fechaElement = document.getElementById('tardanzasFecha');
            
            // MOSTRAR SIEMPRE, incluso si es cero
            container.style.display = 'block';
            countElement.textContent = data.total_pendientes;
            
            // Actualizar fecha límite
            let fechaTexto = '';
            if (data.total_pendientes === 0) {
                fechaTexto = '(Al día)';
            } else if (data.dias_restantes < 0) {
                fechaTexto = `(Vencido hace ${Math.abs(data.dias_restantes)} días)`;
            } else if (data.dias_restantes === 0) {
                fechaTexto = '(Vence hoy)';
            } else {
                fechaTexto = `(${data.dias_restantes} días restantes)`;
            }
            
            fechaElement.textContent = fechaTexto;
            
            // Aplicar clase de color
            let colorClase = data.color_indicador;
            container.className = 'indicator-status ' + colorClase;
        }
        
        // Ir a la página de tardanzas con filtros aplicados
        function irATardanzas() {
            if (!tardanzasData || !tardanzasData.periodo_tardanzas) {
                // Si no hay datos, redirigir sin filtros
                window.location.href = 'tardanzas_manual.php';
                return;
            }
            
            const { inicio, fin } = tardanzasData.periodo_tardanzas;
            window.location.href = `tardanzas_manual.php?desde=${inicio}&hasta=${fin}`;
        }
        
        // ========== FUNCIONES PARA FERIADOS ==========
        
        // Cargar feriados pendientes
        function cargarFeriadosPendientes() {
            fetch('ajax/obtener_feriados_pendientes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        feriadosData = data;
                        actualizarIndicadorFeriados(data);
                    } else {
                        console.error('Error:', data.message);
                        // Mostrar con valor 0
                        document.getElementById('feriadosCount').textContent = '0';
                        document.getElementById('feriadosContainer').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error de conexión:', error);
                    // Mostrar con valor 0
                    document.getElementById('feriadosCount').textContent = '0';
                    document.getElementById('feriadosContainer').style.display = 'block';
                });
        }
        
        // Actualizar indicador de feriados
        function actualizarIndicadorFeriados(data) {
            const container = document.getElementById('feriadosContainer');
            const countElement = document.getElementById('feriadosCount');
            const fechaElement = document.getElementById('feriadosFecha');
            
            // MOSTRAR SIEMPRE, incluso si es cero
            container.style.display = 'block';
            countElement.textContent = data.total_pendientes;
            
            // Actualizar fecha límite
            let fechaTexto = '';
            if (data.total_pendientes === 0) {
                fechaTexto = '(Al día)';
            } else if (data.dias_restantes < 0) {
                fechaTexto = `(Vencido hace ${Math.abs(data.dias_restantes)} días)`;
            } else if (data.dias_restantes === 0) {
                fechaTexto = '(Vence hoy)';
            } else {
                fechaTexto = `(${data.dias_restantes} días restantes)`;
            }
            
            fechaElement.textContent = fechaTexto;
            
            // Aplicar clase de color
            let colorClase = data.color_indicador;
            container.className = 'indicator-status ' + colorClase;
        }
        
        // Ir a la página de feriados con filtros aplicados
        function irAFeriados() {
            if (!feriadosData || !feriadosData.periodo_actual) {
                // Si no hay datos, redirigir sin filtros
                window.location.href = 'feriados.php';
                return;
            }
            
            const { inicio, fin } = feriadosData.periodo_actual;
            window.location.href = `feriados.php?desde=${inicio}&hasta=${fin}`;
        }
        
        // ========== FUNCIONES PARA RECLAMOS ==========
        
        // Cargar reclamos pendientes
        function cargarReclamosPendientes() {
            fetch('ajax/obtener_reclamos_pendientes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        reclamosData = data;
                        actualizarIndicadorReclamos(data);
                    } else {
                        console.error('Error:', data.message);
                        // Mostrar con valor 0
                        document.getElementById('reclamosCount').textContent = '0';
                        document.getElementById('reclamosContainer').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error de conexión:', error);
                    // Mostrar con valor 0
                    document.getElementById('reclamosCount').textContent = '0';
                    document.getElementById('reclamosContainer').style.display = 'block';
                });
        }
        
        // Actualizar indicador de reclamos
        function actualizarIndicadorReclamos(data) {
            const container = document.getElementById('reclamosContainer');
            const countElement = document.getElementById('reclamosCount');
            const fechaElement = document.getElementById('reclamosFecha');
            
            // MOSTRAR SIEMPRE, incluso si es cero
            container.style.display = 'block';
            countElement.textContent = data.total_pendientes;
            
            // Actualizar información de tolerancia
            let fechaTexto = '(Tolerancia: 7 días)';
            if (data.total_pendientes > 0 && data.reclamos_pendientes && data.reclamos_pendientes.length > 0) {
                const reclamoMasAntiguo = data.reclamos_pendientes.reduce((masAntiguo, reclamo) => {
                    return (!masAntiguo || reclamo.dias_pendiente > masAntiguo.dias_pendiente) ? reclamo : masAntiguo;
                });
                
                if (reclamoMasAntiguo && reclamoMasAntiguo.dias_pendiente > 7) {
                    fechaTexto = `(Excedido por ${reclamoMasAntiguo.dias_pendiente - 7} días)`;
                }
            }
            
            fechaElement.textContent = fechaTexto;
            
            // Aplicar clase de color
            let colorClase = data.color_indicador;
            container.className = 'indicator-status ' + colorClase;
        }
        
        // Ir a la página de reclamos (sin filtros)
        function irAReclamos() {
            window.location.href = '../supervision/auditorias_original/reclamospend.php';
        }
        
        // ========== FUNCIONES PARA KPI ==========
        
        // Cargar KPI pendientes
        function cargarKPIPendientes() {
            fetch('ajax/obtener_kpi_pendientes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        kpiData = data;
                        actualizarIndicadorKPI(data);
                    } else {
                        console.error('Error:', data.message);
                        document.getElementById('kpiContainer').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error de conexión:', error);
                    document.getElementById('kpiContainer').style.display = 'none';
                });
        }
        
        // Actualizar indicador de KPI
        function actualizarIndicadorKPI(data) {
            const container = document.getElementById('kpiContainer');
            const countElement = document.getElementById('kpiCount');
            const fechaElement = document.getElementById('kpiFecha');
            
            // Mostrar siempre el indicador, incluso si está al 100%
            container.style.display = 'block';
            countElement.textContent = data.sucursales_sin_kpi;
            
            // Actualizar información del período
            let fechaTexto = '(Mes actual)';
            if (data.periodo_actual) {
                fechaTexto = `(${data.periodo_actual.mes_nombre_es} ${data.periodo_actual.anio})`;
            }
            
            fechaElement.textContent = fechaTexto;
            
            // Aplicar clase de color
            let colorClase = data.color_indicador;
            container.className = 'indicator-status ' + colorClase;
        }
        
        // Ir a la página de KPI
        function irAKPI() {
            if (!kpiData) return;
            
            const mes = kpiData.periodo_actual.mes;
            const anio = kpiData.periodo_actual.anio;
            
            window.location.href = `../supervision/auditorias_original/kpi.php?mes=${mes}&anio=${anio}`;
        }
        
        // Mostrar modal de KPI pendientes
        function mostrarModalKPI() {
            if (!kpiData) {
                alert('Cargando datos...');
                return;
            }
            
            const modal = document.getElementById('modalKPI');
            const lista = document.getElementById('listaKPIPendientes');
            const periodoInfo = document.getElementById('periodoKPIInfo');
            const completitudInfo = document.getElementById('completitudKPIInfo');
            const pendientesInfo = document.getElementById('pendientesKPIInfo');
            const totalInfo = document.getElementById('totalKPIInfo');
            
            // Actualizar información con estado
            periodoInfo.textContent = `${kpiData.periodo_actual.mes_nombre_es} ${kpiData.periodo_actual.anio}`;
            let completitudTexto = `${kpiData.porcentaje_completitud}%`;
            if (kpiData.porcentaje_completitud < 70) {
                completitudTexto += ` (${70 - kpiData.porcentaje_completitud}% faltante para mínimo)`;
            } else if (kpiData.porcentaje_completitud < 100) {
                completitudTexto += ` (${100 - kpiData.porcentaje_completitud}% faltante para completar)`;
            } else {
                completitudTexto += ` (Completado al 100%)`;
            }
            completitudInfo.textContent = completitudTexto;
            pendientesInfo.textContent = kpiData.sucursales_sin_kpi;
            totalInfo.textContent = kpiData.total_sucursales;
            
            // Construir lista de sucursales pendientes
            lista.innerHTML = construirListaKPI(kpiData.sucursales_pendientes);
            
            modal.style.display = 'block';
        }
        
        // Cerrar modal de KPI
        function cerrarModalKPI() {
            document.getElementById('modalKPI').style.display = 'none';
        }
        
        // Cerrar modal de restricción KPI
        function cerrarModalRestriccionKPI() {
            document.getElementById('modalRestriccionKPI').style.display = 'none';
        }
        
        // Construir lista de sucursales pendientes de KPI
        function construirListaKPI(sucursalesPendientes) {
            if (sucursalesPendientes.length === 0) {
                return `
                    <div style="text-align: center; padding: 20px; color: #28a745;">
                        <i class="fas fa-check-circle fa-3x" style="margin-bottom: 15px;"></i>
                        <p style="font-size: 1.1rem; font-weight: bold;">¡Todo al día!</p>
                        <p>Todas las sucursales tienen su KPI actualizado para este periodo.</p>
                    </div>
                `;
            }
            
            let html = '<div class="lista-tardanzas">';
            
            sucursalesPendientes.forEach(sucursal => {
                html += `
                    <div class="item-tardanza">
                        <div class="tardanza-info">
                            <h4>
                                <i class="fas fa-store"></i>${sucursal}
                            </h4>
                            <p>
                                <strong>Estado:</strong> <span style="color: #dc3545;">Pendiente de actualización</span>
                            </p>
                            <small>
                                <strong>Periodo:</strong> ${kpiData.periodo_actual.mes_nombre_es} ${kpiData.periodo_actual.anio}
                            </small>
                        </div>
                        <a href="../supervision/auditorias_original/kpi.php?mes=${kpiData.periodo_actual.mes}&anio=${kpiData.periodo_actual.anio}" class="btn-justificar">
                            <i class="fas fa-edit"></i> Actualizar
                        </a>
                    </div>
                `;
            });
            
            html += '</div>';
            return html;
        }
        
        // Funciones para los modales de Operaciones
        function mostrarModalTardanzasOperaciones() {
            document.getElementById('modalTardanzasOperaciones').style.display = 'block';
        }
        
        function cerrarModalTardanzasOperaciones() {
            document.getElementById('modalTardanzasOperaciones').style.display = 'none';
        }
        
        function mostrarModalFaltasOperaciones() {
            document.getElementById('modalFaltasOperaciones').style.display = 'block';
        }
        
        function cerrarModalFaltasOperaciones() {
            document.getElementById('modalFaltasOperaciones').style.display = 'none';
        }
        
        // ========== FUNCIONES UTILITARIAS ==========
        
        // Formatear fecha corta
        function formatoFechaCorta(fecha) {
            const fechaObj = new Date(fecha + 'T00:00:00');
            const opciones = { day: '2-digit', month: 'short', year: 'numeric' };
            return fechaObj.toLocaleDateString('es-ES', opciones);
        }
        
        // Cerrar modales al hacer clic fuera (solo para KPI)
        window.onclick = function(event) {
            const modals = ['modalTardanzasOperaciones', 'modalFaltasOperaciones', 'modalKPI', 'modalRestriccionKPI'];
            
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    if (modalId === 'modalTardanzasOperaciones') cerrarModalTardanzasOperaciones();
                    if (modalId === 'modalFaltasOperaciones') cerrarModalFaltasOperaciones();
                    if (modalId === 'modalKPI') cerrarModalKPI();
                    if (modalId === 'modalRestriccionKPI') cerrarModalRestriccionKPI();
                }
            });
        }
        
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModalTardanzasOperaciones();
                cerrarModalFaltasOperaciones();
                cerrarModalKPI();
                cerrarModalRestriccionKPI();
            }
        });
    </script>
</body>
</html>