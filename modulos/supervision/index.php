<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';

// Verificar acceso al módulo RH (Código 13 para Jefe de RH)
//verificarAccesoModulo('supervision');
verificarAccesoCargo([21]);

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
if (!verificarAccesoCargo(21)) {
    header('Location: ../index.php');
    exit();
}

/**
 * Obtiene las fechas de visita realizadas en un mes para una sucursal
 */
function obtenerVisitasRealizadasMes($codSucursal, $mes, $ano) {
    global $conn;
    
    $fechaInicio = "$ano-$mes-01";
    $fechaFin = date('Y-m-t', strtotime($fechaInicio));
    
    // Buscar fechas distintas donde se realizó al menos una auditoría
    $sql = "
        SELECT DISTINCT DATE(fecha_hora) as fecha_visita
        FROM (
            -- Auditorías de desempeño
            SELECT fecha_hora FROM auditoria WHERE cod_sucursal = ? AND DATE(fecha_hora) BETWEEN ? AND ?
            UNION
            SELECT fecha_hora FROM auditoria_personal WHERE cod_sucursal = ? AND DATE(fecha_hora) BETWEEN ? AND ?
            UNION
            SELECT fecha_hora FROM auditoria_servicio WHERE cod_sucursal = ? AND DATE(fecha_hora) BETWEEN ? AND ?
            UNION
            -- Auditorías de efectivo (ajustar por zona horaria)
            SELECT DATE(DATE_SUB(fecha_hora_regsys, INTERVAL 6 HOUR)) as fecha_hora FROM auditoria_facturacion WHERE sucursal_id = ? AND DATE(DATE_SUB(fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN ? AND ?
            UNION
            SELECT DATE(DATE_SUB(fecha_hora_regsys, INTERVAL 6 HOUR)) as fecha_hora FROM auditoria_caja_chica WHERE sucursal_id = ? AND DATE(DATE_SUB(fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN ? AND ?
            UNION
            SELECT DATE(DATE_SUB(fecha_hora_regsys, INTERVAL 6 HOUR)) as fecha_hora FROM auditoria_inventario WHERE sucursal_id = ? AND DATE(DATE_SUB(fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN ? AND ?
        ) as auditorias
        ORDER BY fecha_visita
    ";
    
    $stmt = $conn->prepare($sql);
    
    // Ejecutar con parámetros repetidos para cada UNION
    $params = [];
    for ($i = 0; $i < 3; $i++) { // 3 auditorías de desempeño
        $params[] = $codSucursal;
        $params[] = $fechaInicio;
        $params[] = $fechaFin;
    }
    for ($i = 0; $i < 3; $i++) { // 3 auditorías de efectivo
        $params[] = $codSucursal;
        $params[] = $fechaInicio;
        $params[] = $fechaFin;
    }
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Verifica auditoría de desempeño en fecha específica
 */
function verificarAuditoriaDesempenioFecha($tabla, $codSucursal, $fecha) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM $tabla 
        WHERE cod_sucursal = ? 
        AND DATE(fecha_hora) = ?
        LIMIT 1
    ");
    
    $stmt->execute([$codSucursal, $fecha]);
    $result = $stmt->fetch();
    return $result && $result['total'] > 0;
}

/**
 * Verifica auditoría de efectivo en fecha específica
 */
function verificarAuditoriaEfectivoFecha($tabla, $columnaSucursal, $codSucursal, $fecha) {
    global $conn;
    
    $sql = "SELECT COUNT(*) as total 
            FROM $tabla 
            WHERE $columnaSucursal = ? 
            AND DATE(DATE_SUB(fecha_hora_regsys, INTERVAL 6 HOUR)) = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$codSucursal, $fecha]);
    $result = $stmt->fetch();
    return $result && $result['total'] > 0;
}

/**
 * Obtiene el detalle completo de auditorías para una visita específica
 */
function obtenerDetalleAuditoriasVisita($codSucursal, $fechaVisita) {
    // Auditorías de desempeño
    $auditoriasDesempenio = [
        'limpieza' => [
            'nombre' => 'Limpieza',
            'completa' => verificarAuditoriaDesempenioFecha('auditoria', $codSucursal, $fechaVisita),
            'url' => '/modulos/supervision/auditorias_original/agregar.php'
        ],
        'personal' => [
            'nombre' => 'Personal', 
            'completa' => verificarAuditoriaDesempenioFecha('auditoria_personal', $codSucursal, $fechaVisita),
            'url' => '/modulos/supervision/auditorias_original/agregarpersonal.php'
        ],
        'servicio' => [
            'nombre' => 'Servicio',
            'completa' => verificarAuditoriaDesempenioFecha('auditoria_servicio', $codSucursal, $fechaVisita),
            'url' => '/modulos/supervision/auditorias_original/agregarservicio.php'
        ]
    ];
    
    // Auditorías de efectivo
    $auditoriasEfectivo = [
        'facturacion' => [
            'nombre' => 'Caja Facturación',
            'completa' => verificarAuditoriaEfectivoFecha('auditoria_facturacion', 'sucursal_id', $codSucursal, $fechaVisita),
            'url' => '/modulos/supervision/auditorias_original/auditinternas/auditoria_caja_facturacion.php'
        ],
        'caja_chica' => [
            'nombre' => 'Caja Chica',
            'completa' => verificarAuditoriaEfectivoFecha('auditoria_caja_chica', 'sucursal_id', $codSucursal, $fechaVisita),
            'url' => '/modulos/supervision/auditorias_original/auditinternas/auditoria_caja_chica.php'
        ],
        'inventario' => [
            'nombre' => 'Inventario',
            'completa' => verificarAuditoriaEfectivoFecha('auditoria_inventario', 'sucursal_id', $codSucursal, $fechaVisita),
            'url' => '/modulos/supervision/auditorias_original/auditinternas/auditoria_inventario.php'
        ]
    ];
    
    $todasAuditorias = array_merge($auditoriasDesempenio, $auditoriasEfectivo);
    $completas = 0;
    $total = count($todasAuditorias);
    
    foreach ($todasAuditorias as $auditoria) {
        if ($auditoria['completa']) {
            $completas++;
        }
    }
    
    return [
        'auditorias' => $todasAuditorias,
        'completas' => $completas,
        'total' => $total,
        'completa' => ($completas == $total)
    ];
}

/**
 * Obtiene el estado mensual de auditorías por sucursal - MEJORADA
 */
function obtenerEstadoAuditoriasMensual($codSucursal = null) {
    global $conn;
    
    $mesActual = date('n');
    $anoActual = date('Y');
    $mesNombre = date('F', mktime(0, 0, 0, $mesActual, 1));
    
    // Obtener todas las sucursales o una específica
    if ($codSucursal) {
        $sucursales = obtenerSucursalesFisicas();
        $sucursales = array_filter($sucursales, function($s) use ($codSucursal) {
            return $s['codigo'] == $codSucursal;
        });
    } else {
        $sucursales = obtenerSucursalesFisicas();
    }
    
    $resultados = [];
    $totalCompletas = 0;
    $totalSucursales = count($sucursales);
    $totalPendientes = 0;
    
    foreach ($sucursales as $sucursal) {
        $codDepartamento = $sucursal['cod_departamento'];
        
        // Determinar visitas requeridas según departamento
        $visitasRequeridas = ($codDepartamento == 1) ? 3 : 2;
        
        // Obtener las visitas realizadas este mes
        $visitasRealizadas = obtenerVisitasRealizadasMes($sucursal['codigo'], $mesActual, $anoActual);
        
        // Verificar visitas completas (con las 6 auditorías)
        $visitasCompletas = 0;
        $detalleVisitas = [];
        
        foreach ($visitasRealizadas as $fechaVisita) {
            $detalleVisita = obtenerDetalleAuditoriasVisita($sucursal['codigo'], $fechaVisita);
            $completa = $detalleVisita['completa'];
            
            if ($completa) {
                $visitasCompletas++;
            }
            
            $detalleVisitas[] = [
                'fecha' => $fechaVisita,
                'completa' => $completa,
                'detalle_auditorias' => $detalleVisita['auditorias'],
                'total_completas' => $detalleVisita['completas'],
                'total_auditorias' => $detalleVisita['total']
            ];
        }
        
        $porcentaje = $visitasRequeridas > 0 ? round(($visitasCompletas / $visitasRequeridas) * 100) : 100;
        
        // Determinar estado individual de la sucursal
        if ($visitasCompletas >= $visitasRequeridas) {
            $estadoSucursal = 'completo';
            $totalCompletas++;
        } elseif ($visitasCompletas > 0) {
            $estadoSucursal = 'parcial';
            $totalPendientes++;
        } else {
            $estadoSucursal = 'pendiente';
            $totalPendientes++;
        }
        
        $resultados[] = [
            'codigo' => $sucursal['codigo'],
            'nombre' => $sucursal['nombre'],
            'departamento' => $codDepartamento,
            'departamento_nombre' => obtenerNombreDepartamento($codDepartamento),
            'visitas_requeridas' => $visitasRequeridas,
            'visitas_completas' => $visitasCompletas,
            'visitas_realizadas' => count($visitasRealizadas),
            'porcentaje' => $porcentaje,
            'detalle_visitas' => $detalleVisitas,
            'estado' => $estadoSucursal,
            'color' => $estadoSucursal == 'completo' ? 'verde' : 
                      ($estadoSucursal == 'parcial' ? 'amarillo' : 'rojo')
        ];
    }
    
    $porcentajeGlobal = $totalSucursales > 0 ? round(($totalCompletas / $totalSucursales) * 100) : 100;
    
    // Determinar color global para el indicador
    if ($porcentajeGlobal == 100) {
        $colorGlobal = 'verde';
        $estadoGlobal = 'completo';
    } elseif ($porcentajeGlobal >= 70) {
        $colorGlobal = 'amarillo';
        $estadoGlobal = 'avanzado';
    } else {
        $colorGlobal = 'rojo';
        $estadoGlobal = 'pendiente';
    }
    
    return [
        'mes' => $mesActual,
        'ano' => $anoActual,
        'mes_nombre' => $mesNombre,
        'sucursales' => $resultados,
        'total_sucursales' => $totalSucursales,
        'total_completas' => $totalCompletas,
        'total_pendientes' => $totalPendientes,
        'porcentaje_global' => $porcentajeGlobal,
        'estado_global' => $estadoGlobal,
        'color_global' => $colorGlobal
    ];
}

/**
 * Verifica si una visita (fecha) tiene las 6 auditorías completas
 */
function visitaTieneTodasAuditorias($codSucursal, $fechaVisita) {
    $detalle = obtenerDetalleAuditoriasVisita($codSucursal, $fechaVisita);
    return $detalle['completa'];
}

// Función mejorada para verificar horarios pendientes de semanas anteriores
function tieneHorariosPendientesAnteriores() {
    global $conn;
    
    $semanaActual = obtenerSemanaActual();
    if (!$semanaActual) return false;
    
    // Buscar horarios pendientes de semanas anteriores a la actual
    // Incluye tanto no confirmados como confirmados con cambios pendientes
    $sql = "
        SELECT COUNT(*) as pendientes
        FROM (
            SELECT 
                s.codigo, 
                ss.numero_semana,
                COUNT(DISTINCT hs.cod_operario) as total_operarios,
                COUNT(DISTINCT CASE WHEN hso.confirmado = 1 THEN hso.cod_operario END) as operarios_confirmados,
                CASE 
                    WHEN MAX(hs.fecha_actualizacion) > COALESCE(MAX(hso.fecha_confirmacion), '2000-01-01') THEN 1
                    ELSE 0
                END as tiene_cambios_pendientes
            FROM sucursales s
            CROSS JOIN SemanasSistema ss
            INNER JOIN HorariosSemanales hs ON s.codigo = hs.cod_sucursal AND ss.id = hs.id_semana_sistema
            LEFT JOIN HorariosSemanalesOperaciones hso ON s.codigo = hso.cod_sucursal AND ss.id = hso.id_semana_sistema AND hs.cod_operario = hso.cod_operario
            WHERE s.activa = 1 AND s.sucursal = 1 
            AND ss.numero_semana < ?
            GROUP BY s.codigo, ss.numero_semana
            HAVING 
                -- Horarios no confirmados completamente
                operarios_confirmados < total_operarios
                -- O horarios con cambios pendientes después de confirmación
                OR tiene_cambios_pendientes = 1
        ) as pendientes
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$semanaActual['numero_semana']]);
    $result = $stmt->fetch();
    
    return $result['pendientes'] > 0;
}

// Verificar restricción de horarios pendientes
$tienePendientesAnteriores = tieneHorariosPendientesAnteriores();

/**
 * Obtiene el estado de horarios pendientes de confirmar por operaciones - VERSIÓN MEJORADA
 * AHORA DETECTA EDICIONES PENDIENTES DE CUALQUIER SEMANA
 */
function obtenerEstadoHorariosPendientesConfirmacion($codUsuario) {
    global $conn;
    
    // Obtener semana actual y siguiente
    $semanaActual = obtenerSemanaActual();
    $semanaSiguiente = obtenerSemanaPorNumero($semanaActual['numero_semana'] + 1);
    
    if (!$semanaSiguiente) {
        return [
            'estado' => 'pendiente',
            'texto' => $texto,
            'color' => $color,
            'url' => 'programar_horarios_operaciones.php?semana=' . $semanaSiguiente['numero_semana'],
            'semana_siguiente' => $semanaSiguiente,
            'periodo_activo' => true,
            'total_pendientes' => $totalPendientes,
            'total_sin_horario' => $totalSinHorario,
            'sucursales_pendientes' => $sucursalesPendientes,
            'sin_horario_lider' => $sucursalesSinHorario,
            'ediciones_pendientes' => $edicionesPendientes,
            'dias_restantes' => $diasRestantes  // ← Asegúrate de que esta línea esté presente
        ];
    }
    
    // Obtener todas las sucursales (operaciones puede ver todas)
    $sucursales = obtenerSucursalesFisicas();
    
    // Determinar si estamos en período activo (sábado 00:00 a domingo 23:59)
    $hoy = new DateTime('now', new DateTimeZone('America/Managua'));
    $domingoSemanaActual = new DateTime($semanaActual['fecha_fin'], new DateTimeZone('America/Managua'));
    $sabadoSemanaActual = clone $domingoSemanaActual;
    $sabadoSemanaActual->modify('-1 day'); // Sábado de la semana actual
    
    // Establecer horarios para el período de confirmación
    $sabadoSemanaActual->setTime(0, 0, 0); // Sábado 00:00:00
    $domingoSemanaActual->setTime(23, 59, 59); // Domingo 23:59:59
    
    // TEMPORAL: Permitir siempre el período activo para pruebas
    $periodoActivo = true; // ($hoy >= $sabadoSemanaActual && $hoy <= $domingoSemanaActual);
    
    // Arrays para clasificar los pendientes
    $sucursalesPendientes = []; // Con horario pero sin confirmar
    $sucursalesSinHorario = []; // Sin horario del líder
    $totalPendientes = 0;
    $totalSinHorario = 0;
    
    foreach ($sucursales as $sucursal) {
        // 1. Verificar si la sucursal tiene operarios activos
        $stmtOperarios = $conn->prepare("
            SELECT COUNT(DISTINCT o.CodOperario) as total_operarios
            FROM Operarios o
            JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            WHERE anc.Sucursal = ?
            AND o.Operativo = 1
            AND (anc.Fin IS NULL OR anc.Fin >= ?)
            AND anc.Fecha <= ?
            AND o.CodOperario NOT IN (
                SELECT DISTINCT anc2.CodOperario 
                FROM AsignacionNivelesCargos anc2
                WHERE anc2.CodNivelesCargos = 27
                AND (anc2.Fin IS NULL OR anc2.Fin >= ?)
            )
        ");
        
        $stmtOperarios->execute([
            $sucursal['codigo'],
            $semanaSiguiente['fecha_fin'],
            $semanaSiguiente['fecha_inicio'],
            $semanaSiguiente['fecha_fin']
        ]);
        
        $resultOperarios = $stmtOperarios->fetch();
        $totalOperariosSucursal = $resultOperarios['total_operarios'] ?? 0;
        
        if ($totalOperariosSucursal == 0) {
            continue; // No hay operarios en esta sucursal
        }
        
        // 2. Verificar cuántos operarios tienen horario del líder
        $stmtHorarioLider = $conn->prepare("
            SELECT COUNT(DISTINCT cod_operario) as con_horario_lider
            FROM HorariosSemanales
            WHERE id_semana_sistema = ? 
            AND cod_sucursal = ?
        ");
        
        $stmtHorarioLider->execute([$semanaSiguiente['id'], $sucursal['codigo']]);
        $resultHorarioLider = $stmtHorarioLider->fetch();
        $conHorarioLider = $resultHorarioLider['con_horario_lider'] ?? 0;
        
        // 3. Verificar cuántos operarios tienen horario confirmado por operaciones
        $stmtConfirmados = $conn->prepare("
            SELECT COUNT(DISTINCT cod_operario) as confirmados
            FROM HorariosSemanalesOperaciones
            WHERE id_semana_sistema = ? 
            AND cod_sucursal = ?
            AND confirmado = 1
        ");
        
        $stmtConfirmados->execute([$semanaSiguiente['id'], $sucursal['codigo']]);
        $resultConfirmados = $stmtConfirmados->fetch();
        $confirmados = $resultConfirmados['confirmados'] ?? 0;
        
        // Clasificar la sucursal
        if ($conHorarioLider == 0) {
            // Sucursal sin horario del líder
            $sucursalesSinHorario[] = [
                'sucursal' => $sucursal,
                'total_operarios' => $totalOperariosSucursal,
                'con_horario_lider' => 0,
                'confirmados' => 0,
                'pendientes_confirmar' => 0
            ];
            $totalSinHorario++; // contar sucursales
        } elseif ($confirmados < $conHorarioLider) {
            // Sucursal con horarios pendientes de confirmar
            $pendientesConfirmar = $conHorarioLider - $confirmados;
            $sucursalesPendientes[] = [
                'sucursal' => $sucursal,
                'total_operarios' => $totalOperariosSucursal,
                'con_horario_lider' => $conHorarioLider,
                'confirmados' => $confirmados,
                'pendientes_confirmar' => $pendientesConfirmar
            ];
            $totalPendientes++; // contar sucursales
        }
    }
    
    // NUEVO: Verificar ediciones pendientes de líderes de CUALQUIER SEMANA
    $edicionesPendientes = verificarEdicionesPendientesLideresTodasSemanas();
    
    // Determinar estado y color
    if (!$periodoActivo) {
        return [
            'estado' => 'fuera_periodo',
            'texto' => 'Fuera del período de confirmación',
            'color' => 'gris',
            'url' => 'programar_horarios_operaciones.php?semana=' . $semanaSiguiente['numero_semana'],
            'semana_siguiente' => $semanaSiguiente,
            'periodo_activo' => false,
            'total_pendientes' => $totalPendientes,
            'total_sin_horario' => $totalSinHorario,
            'sucursales_pendientes' => $sucursalesPendientes,
            'sin_horario_lider' => $sucursalesSinHorario,
            'ediciones_pendientes' => $edicionesPendientes
        ];
    }
    
    // Calcular total general incluyendo ediciones pendientes
    $totalGeneral = $totalPendientes + $totalSinHorario + count($edicionesPendientes);
    
    if ($totalGeneral == 0) {
        return [
            'estado' => 'completo',
            'texto' => 'Todos los horarios de la semana ' . $semanaSiguiente['numero_semana'] . ' confirmados',
            'color' => 'verde',
            'url' => 'programar_horarios_operaciones.php?semana=' . $semanaSiguiente['numero_semana'],
            'semana_siguiente' => $semanaSiguiente,
            'periodo_activo' => true,
            'total_pendientes' => 0,
            'total_sin_horario' => 0,
            'sucursales_pendientes' => [],
            'sin_horario_lider' => [],
            'ediciones_pendientes' => []
        ];
    }
    
    // Calcular días restantes para confirmación
    $diasRestantes = 0;
    if ($periodoActivo) {
        $diferencia = $hoy->diff($domingoSemanaActual);
        $diasRestantes = $diferencia->days;
        
        if ($diferencia->days == 0 && $hoy->format('H:i') > '23:59') {
            $diasRestantes = 0;
        }
    }
    
    $color = determinarColorConfirmacionPendiente($diasRestantes, $totalGeneral);
    
    // Texto mejorado que muestra todos los tipos de pendientes
    $texto = "{$totalPendientes} por confirmar";
    if ($totalSinHorario > 0) {
        $texto .= " + {$totalSinHorario} sin horario líder";
    }
    if (!empty($edicionesPendientes)) {
        $texto .= " + " . count($edicionesPendientes) . " reconfirmar";
    }
    
    return [
        'estado' => 'pendiente',
        'texto' => $texto,
        'color' => $color,
        'url' => 'programar_horarios_operaciones.php?semana=' . $semanaSiguiente['numero_semana'],
        'semana_siguiente' => $semanaSiguiente,
        'periodo_activo' => true,
        'total_pendientes' => $totalPendientes,
        'total_sin_horario' => $totalSinHorario,
        'sucursales_pendientes' => $sucursalesPendientes,
        'sin_horario_lider' => $sucursalesSinHorario,
        'ediciones_pendientes' => $edicionesPendientes,
        'dias_restantes' => $diasRestantes
    ];
}

/**
 * Verifica si hay ediciones de líderes pendientes después de confirmación
 * SOLO para casos donde YA EXISTE confirmación en HorariosSemanalesOperaciones
 * NUEVA VERSIÓN - Lógica simplificada y correcta
 */
function verificarEdicionesPendientesLideres($idSemanaSiguiente) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT DISTINCT hs.cod_sucursal, s.nombre as sucursal_nombre,
               COUNT(DISTINCT hs.cod_operario) as operarios_editados
        FROM HorariosSemanales hs
        JOIN sucursales s ON hs.cod_sucursal = s.codigo
        JOIN HorariosSemanalesOperaciones hso ON 
            hs.cod_operario = hso.cod_operario 
            AND hs.id_semana_sistema = hso.id_semana_sistema 
            AND hs.cod_sucursal = hso.cod_sucursal
        WHERE hs.id_semana_sistema = ?
        AND hso.confirmado = 1  -- SOLO donde ya estaba confirmado
        -- Lógica simplificada: líder editó después de la última acción de operaciones
        AND (
            (hso.fecha_actualizacion IS NULL AND hs.fecha_actualizacion > hso.fecha_creacion)
            OR
            (hso.fecha_actualizacion IS NOT NULL AND hs.fecha_actualizacion > hso.fecha_actualizacion)
        )
        GROUP BY hs.cod_sucursal, s.nombre
        HAVING operarios_editados > 0
    ");
    
    $stmt->execute([$idSemanaSiguiente]);
    return $stmt->fetchAll();
}

/**
 * Verifica si hay ediciones de líderes pendientes después de confirmación
 * PARA TODAS LAS SEMANAS (no solo la siguiente)
 * NUEVA VERSIÓN - Compara fechas de actualización correctamente
 */
function verificarEdicionesPendientesLideresTodasSemanas() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            hs.cod_sucursal, 
            s.nombre as sucursal_nombre,
            hs.id_semana_sistema,
            ss.numero_semana,
            ss.fecha_inicio,
            ss.fecha_fin,
            COUNT(DISTINCT hs.cod_operario) as operarios_editados,
            MAX(hs.fecha_actualizacion) as ultima_edicion_lider,
            MAX(hso.fecha_actualizacion) as ultima_confirmacion_operaciones
        FROM HorariosSemanales hs
        JOIN sucursales s ON hs.cod_sucursal = s.codigo
        JOIN SemanasSistema ss ON hs.id_semana_sistema = ss.id
        JOIN HorariosSemanalesOperaciones hso ON 
            hs.cod_operario = hso.cod_operario 
            AND hs.id_semana_sistema = hso.id_semana_sistema 
            AND hs.cod_sucursal = hso.cod_sucursal
        WHERE hso.confirmado = 1  -- Solo donde ya estaba confirmado
        AND s.activa = 1
        AND s.sucursal = 1
        -- CRITERIO PRINCIPAL: El líder editó DESPUÉS de la última actualización de operaciones
        AND (
            -- Caso 1: Operaciones nunca actualizó después de crear, y líder sí actualizó
            (hso.fecha_actualizacion IS NULL AND hs.fecha_actualizacion > hso.fecha_creacion)
            OR
            -- Caso 2: Ambos actualizaron, pero líder lo hizo después
            (hso.fecha_actualizacion IS NOT NULL AND hs.fecha_actualizacion > hso.fecha_actualizacion)
        )
        GROUP BY hs.cod_sucursal, s.nombre, hs.id_semana_sistema, ss.numero_semana, ss.fecha_inicio, ss.fecha_fin
        HAVING operarios_editados > 0
        ORDER BY ss.numero_semana DESC, s.nombre
    ");
    
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Determina el color según días restantes para confirmación
 */
function determinarColorConfirmacionPendiente($diasRestantes, $totalPendientes) {
    if ($totalPendientes == 0) {
        return 'verde';
    }
    
    if ($diasRestantes <= 0) {
        return 'rojo'; // Vencido
    } elseif ($diasRestantes <= 1) {
        return 'rojo'; // 1 día o menos (domingo)
    } else {
        return 'amarillo'; // 2 días (sábado)
    }
}

/**
 * Obtiene el texto descriptivo para el indicador de confirmación - MEJORADO
 */
function obtenerTextoIndicadorConfirmacion($estadoConfirmacion) {
    switch ($estadoConfirmacion['estado']) {
        case 'completo':
            return $estadoConfirmacion['texto'];
        case 'pendiente':
            $texto = $estadoConfirmacion['texto'];
            if ($estadoConfirmacion['periodo_activo']) {
                $texto .= ' (' . $estadoConfirmacion['dias_restantes'] . ' días restantes)';
            }
            return $texto;
        case 'fuera_periodo':
            return $estadoConfirmacion['texto'];
        case 'no_disponible':
            return $estadoConfirmacion['texto'];
        default:
            return 'Estado no disponible';
    }
}

// Obtener estado de horarios pendientes de confirmación
$estadoConfirmacion = obtenerEstadoHorariosPendientesConfirmacion($_SESSION['usuario_id']);

/**
 * Obtiene el estado de sucursales auditadas según semana par/impar
 */
function obtenerEstadoSucursalesAuditadas() {
    global $conn;
    
    // Obtener semana actual
    $semanaActual = obtenerSemanaActual();
    if (!$semanaActual) {
        return [
            'estado' => 'no_disponible',
            'texto' => 'Semana no disponible',
            'color' => 'gris',
            'total_auditadas' => 0,
            'total_esperadas' => 0,
            'porcentaje' => 0,
            'semanas_auditadas' => [],
            'sucursales_faltantes' => []
        ];
    }
    
    $numeroSemana = $semanaActual['numero_semana'];
    $esSemanaPar = ($numeroSemana % 2 == 0);
    
    // Determinar departamentos a auditar según semana par/impar
    if ($esSemanaPar) {
        // Semana PAR: solo departamento 1 (Managua)
        $departamentosAuditar = [1];
        $tipoAuditoria = 'Semanas Pares - Managua';
    } else {
        // Semana IMPAR: todos los departamentos excepto 1
        $departamentosAuditar = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17];
        $tipoAuditoria = 'Semanas Impares - Otros Departamentos';
    }
    
    // Obtener sucursales que deben ser auditadas
    $sucursalesAuditar = obtenerSucursalesParaAuditar($departamentosAuditar);
    $totalSucursales = count($sucursalesAuditar);
    
    if ($totalSucursales == 0) {
        return [
            'estado' => 'completo',
            'texto' => 'No hay sucursales para auditar',
            'color' => 'verde',
            'total_auditadas' => 0,
            'total_esperadas' => 0,
            'porcentaje' => 100,
            'semanas_auditadas' => [],
            'sucursales_faltantes' => [],
            'tipo_auditoria' => $tipoAuditoria,
            'numero_semana' => $numeroSemana
        ];
    }
    
    // Obtener sucursales que ya tienen las 6 auditorías en la semana actual
    $sucursalesAuditadas = obtenerSucursalesConTodasAuditorias($sucursalesAuditar, $semanaActual);
    $totalAuditadas = count($sucursalesAuditadas);
    
    // Calcular porcentaje
    $porcentaje = $totalSucursales > 0 ? round(($totalAuditadas / $totalSucursales) * 100) : 100;
    
    // Determinar estado y color
    if ($porcentaje == 100) {
        $estado = 'completo';
        $color = 'verde';
        $texto = "Completado: {$totalAuditadas}/{$totalSucursales}";
    } elseif ($porcentaje >= 70) {
        $estado = 'avanzado';
        $color = 'amarillo';
        $texto = "Avanzado: {$totalAuditadas}/{$totalSucursales}";
    } else {
        $estado = 'pendiente';
        $color = 'rojo';
        $texto = "Pendiente: {$totalAuditadas}/{$totalSucursales}";
    }
    
    // Obtener sucursales faltantes
    $sucursalesFaltantes = array_diff($sucursalesAuditar, $sucursalesAuditadas);
    
    return [
        'estado' => $estado,
        'texto' => $texto,
        'color' => $color,
        'total_auditadas' => $totalAuditadas,
        'total_esperadas' => $totalSucursales,
        'porcentaje' => $porcentaje,
        'sucursales_auditadas' => $sucursalesAuditadas,
        'sucursales_faltantes' => $sucursalesFaltantes,
        'tipo_auditoria' => $tipoAuditoria,
        'numero_semana' => $numeroSemana,
        'es_semana_par' => $esSemanaPar,
        'departamentos_auditar' => $departamentosAuditar
    ];
}

/**
 * Obtiene las sucursales que deben ser auditadas según los departamentos
 */
function obtenerSucursalesParaAuditar($departamentos) {
    global $conn;
    
    $placeholders = str_repeat('?,', count($departamentos) - 1) . '?';
    
    $stmt = $conn->prepare("
        SELECT codigo 
        FROM sucursales 
        WHERE cod_departamento IN ($placeholders) 
        AND activa = 1
        AND sucursal = 1
    ");
    $stmt->execute($departamentos);
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Obtiene las sucursales que tienen las 6 auditorías en la semana actual
 */
function obtenerSucursalesConTodasAuditorias($sucursalesAuditar, $semanaActual) {
    global $conn;
    
    $sucursalesCompletas = [];
    
    foreach ($sucursalesAuditar as $sucursal) {
        if (tieneTodasAuditoriasSucursal($sucursal, $semanaActual)) {
            $sucursalesCompletas[] = $sucursal;
        }
    }
    
    return $sucursalesCompletas;
}

/**
 * Verifica si una sucursal tiene las 6 auditorías en la semana actual
 */
function tieneTodasAuditoriasSucursal($codSucursal, $semanaActual) {
    // Auditorías de desempeño (sin ajuste de hora)
    $auditoriasDesempenio = [
        'limpieza' => verificarAuditoriaDesempenio('auditoria', $codSucursal, $semanaActual),
        'personal' => verificarAuditoriaDesempenio('auditoria_personal', $codSucursal, $semanaActual),
        'servicio' => verificarAuditoriaDesempenio('auditoria_servicio', $codSucursal, $semanaActual)
    ];
    
    // Auditorías de efectivo (con ajuste de -6 horas)
    $auditoriasEfectivo = [
        'facturacion' => verificarAuditoriaEfectivo('auditoria_facturacion', 'sucursal_id', $codSucursal, $semanaActual),
        'caja_chica' => verificarAuditoriaEfectivo('auditoria_caja_chica', 'sucursal_id', $codSucursal, $semanaActual),
        'inventario' => verificarAuditoriaEfectivo('auditoria_inventario', 'sucursal_id', $codSucursal, $semanaActual)
    ];
    
    // Verificar que todas las auditorías estén presentes
    return !in_array(false, $auditoriasDesempenio) && !in_array(false, $auditoriasEfectivo);
}

/**
 * Verifica auditorías de desempeño (sin ajuste de hora)
 */
function verificarAuditoriaDesempenio($tabla, $codSucursal, $semanaActual) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM $tabla 
        WHERE cod_sucursal = ? 
        AND fecha_hora BETWEEN ? AND ?
        LIMIT 1
    ");
    
    $stmt->execute([
        $codSucursal,
        $semanaActual['fecha_inicio'] . ' 00:00:00',
        $semanaActual['fecha_fin'] . ' 23:59:59'
    ]);
    
    $result = $stmt->fetch();
    return $result && $result['total'] > 0;
}

/**
 * Verifica auditorías de efectivo (con ajuste de -6 horas)
 */
function verificarAuditoriaEfectivo($tabla, $columnaSucursal, $codSucursal, $semanaActual) {
    global $conn;
    
    // Para auditoría_inventario la columna es diferente
    if ($tabla === 'auditoria_inventario') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM $tabla 
            WHERE $columnaSucursal = ? 
            AND DATE(DATE_SUB(fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN ? AND ?
            LIMIT 1
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM $tabla 
            WHERE $columnaSucursal = ? 
            AND DATE(DATE_SUB(fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN ? AND ?
            LIMIT 1
        ");
    }
    
    $stmt->execute([
        $codSucursal,
        $semanaActual['fecha_inicio'],
        $semanaActual['fecha_fin']
    ]);
    
    $result = $stmt->fetch();
    return $result && $result['total'] > 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisión - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
        
        .modules {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(135px, 135px)); /*Espacio entre las cartas del módulo*/
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .module-card {
            background: white;
            border-radius: 8px;
            padding: 7px; /*Espacio de las cartas del módulo*/
            width: auto;
            max-width: 135px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            text-align: center; /*Texto centrado*/
            display: flex;
            flex-direction: column;
            align-items: center !important;     /* Centrado horizontal */
            justify-content: center !important; /* Centrado vertical */
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .module-icon {
            font-size: 2.5rem;
            color: #51B8AC;
            margin-bottom: 12px;
        }
        
        .module-title {
            margin: 0;
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #0E544C;
        }
        
        .module-desc {
            color: #666;
            font-size: 0.9rem;
        }
        
        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .module-title-page {
            color: #51B8AC;
            font-size: 1.8rem;
        }
        
        .category-title {
            color: #0E544C;
            font-size: 1.5rem;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #51B8AC;
            text-align: center; /*Texto de categorías al centro*/
        }
        
        @media (max-width: 768px) {
            .modules {
                grid-template-columns: repeat(3, 1fr); /* 3 columnas en móvil */
                gap: 10px; /* Reducir espacio entre tarjetas */
            }
            
            .module-card {
                padding: 10px 5px;  /* Ajustar espaciado interno */
                max-width: 100%;    /* Ocupar todo el ancho disponible */
                height: 100%;       /* Asegurar altura consistente */
            }
            
            .module-icon {
                font-size: 1.8rem !important; /* Reducir tamaño de icono */
                margin-bottom: 5px; /* Menos espacio entre icono y texto */
            }
            
            .module-title {
                font-size: 0.9rem !important; /* Reducir tamaño de texto */
                margin-bottom: 5px;
            }
        }

/* Estilos mejorados para el indicador de pendientes */
        .pendientes-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

.pendientes-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 20px;
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    transition: transform 0.3s ease;
}

.pendientes-card:hover {
    transform: translateY(-2px);
}

.pendientes-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    font-size: 1rem !important;
}

.pendientes-title i {
    font-size: 1rem;
    display: none;
}

/* Estilos mejorados para las tarjetas de indicadores */
.pendientes-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 5px;
}

.pendientes-count {
    font-size: 2.5rem !important;
    font-weight: bold;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    line-height: 1;
}

.pendientes-info {
    text-align: center;
}

.pendientes-fecha {
    font-size: 0.8rem !important;
    opacity: 0.9;
    margin-bottom: 5px;
}

.pendientes-titulo {
    font-size: 0.9rem !important;
    font-weight: 600;
}

.pendientes-detalle {
    margin-bottom: 10px;
    font-size: 0.6rem;
    opacity: 0.9;
}

/* Estilos para los botones de auditorías */
.btn-ver-detalles {
    background: rgba(255,255,255,0.2);
    border: 2px solid white;
    color: white;
    padding: 12px 20px;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-ver-detalles:hover {
    background: white;
    color: #333;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

/* Estados de color según el día */
.estado-viernes .pendientes-card {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.estado-sabado .pendientes-card {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
}

.estado-domingo .pendientes-card {
    background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
}

.estado-semana .pendientes-card {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
}

/* Filtros en modal */
.filtros-modal {
    margin-bottom: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}

.filtros-modal label {
    font-weight: bold;
    margin-right: 10px;
}

.filtros-modal select {
    padding: 5px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

/* Alertas de restricción */
.alert-warning {
    text-align: center;
    padding: 20px;
}

.alert-warning i {
    color: #ffc107;
    margin-bottom: 15px;
}

.alert-warning h4 {
    color: #856404;
    margin-bottom: 15px;
}

.modal-actions {
    text-align: center;
    margin-top: 20px;
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

/* Estilos para la lista de sucursales */
.lista-sucursales {
    display: grid;
    gap: 15px;
}

.item-sucursal {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.item-sucursal:hover {
    background: #e9ecef;
    transform: translateX(5px);
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.sucursal-info h4 {
    margin: 0 0 8px 0;
    color: #495057;
    font-size: 1.1rem !important;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sucursal-info p {
    margin: 0 0 5px 0;
    font-size: 0.9rem;
    color: #6c757d;
}

.sucursal-info small {
    color: #868e96;
    font-size: 0.8rem;
}

.btn-ir-sucursal {
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
}

.btn-ir-sucursal:hover {
    background: #218838;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

/* Estados de urgencia */
.item-sucursal.urgente {
    border-left: 5px solid #dc3545;
    background: linear-gradient(135deg, #fff5f5 0%, #f8f9fa 100%);
}

.item-sucursal.alerta {
    border-left: 5px solid #ffc107;
    background: linear-gradient(135deg, #fffbf0 0%, #f8f9fa 100%);
}

.item-sucursal.normal {
    border-left: 5px solid #28a745;
    background: linear-gradient(135deg, #f0fff4 0%, #f8f9fa 100%);
}

/* Indicadores de estado */
.estado-indicador {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.7rem !important;
    font-weight: bold;
    margin-left: 8px;
    text-transform: uppercase;
}

.estado-pendiente {
    background: #dc3545;
    color: white;
}

.estado-cambios {
    background: #ffc107;
    color: #212529;
}

.estado-parcial {
    background: #17a2b8;
    color: white;
}

/* Botones generales */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
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
    
    .item-sucursal {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .btn-ir-sucursal {
        margin-left: 0;
        width: 100%;
        text-align: center;
    }
    
    .sucursal-info h4 {
        font-size: 1rem !important;
    }
    
    .indicadores-container {
        flex-direction: column; /* En móvil volver a columna */
    }
    
    .pendientes-container {
        min-width: 100%; /* En móvil ocupar todo el ancho */
    }
    
    .pendientes-count {
        font-size: 2rem !important;
    }
    
    .pendientes-fecha {
        font-size: 0.7rem !important;
    }
    
    .pendientes-titulo {
        font-size: 0.8rem !important;
    }
}

/* Estilos para la tabla de auditorías */
.tabla-auditorias {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.tabla-auditorias th,
.tabla-auditorias td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: center;
}

.tabla-auditorias th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: bold;
}

.tabla-auditorias .sucursal-header {
    background: #f8f9fa;
    color: #333;
    font-weight: bold;
    text-align: left;
}

/* Colores para los estados de auditoría */
.estado-verde {
    background-color: #28a745;
    color: white;
    border-radius: 4px;
    padding: 4px 8px;
    font-weight: bold;
}

.estado-amarillo {
    background-color: #ffc107;
    color: #212529;
    border-radius: 4px;
    padding: 4px 8px;
    font-weight: bold;
}

.estado-rojo {
    background-color: #dc3545;
    color: white;
    border-radius: 4px;
    padding: 4px 8px;
    font-weight: bold;
}

.estado-futura {
    background-color: #6c757d;
    color: white;
    border-radius: 4px;
    padding: 4px 8px;
    font-weight: bold;
}

/* Botones de acción */
.btn-auditoria {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.6rem;
    margin: 2px;
    transition: all 0.3s ease;
}

.btn-crear-auditoria {
    background: #28a745;
    color: white;
}

.btn-crear-auditoria:hover {
    background: #218838;
}

.btn-ver-auditoria {
    background: #17a2b8;
    color: white;
}

.btn-ver-auditoria:hover {
    background: #138496;
}

/* Responsive */
@media (max-width: 768px) {
    .tabla-auditorias {
        font-size: 0.8rem;
    }
    
    .tabla-auditorias th,
    .tabla-auditorias td {
        padding: 6px 4px;
    }
    
    .btn-auditoria {
        padding: 4px 8px;
        font-size: 0.7rem;
    }
    
    .btn-ver-detalles {
        padding: 10px 15px;
        font-size: 0.9rem;
        min-width: 150px !important;
    }
}

/* Estilos para el contenedor de indicadores */
.indicadores-container {
    display: flex;
    flex-direction: row; /* En una sola fila */
    gap: 15px;
    margin-bottom: 30px;
    max-width: 1200px;
    margin: 0 auto 30px auto;
    flex-wrap: wrap;
    justify-content: center;
}

/* Estilos específicos para el indicador de confirmación */
.pendientes-card.verde {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.pendientes-card.amarillo {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
}

.pendientes-card.rojo {
    background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
}

.pendientes-card.gris {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
}

/* Mejoras para los cards en el modal */
.modal-body-pendientes .sucursal-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.modal-body-pendientes .sucursal-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Efectos hover para las tarjetas del modal de confirmación */
.modal-body-pendientes .sucursal-card-hover {
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
}

.modal-body-pendientes .sucursal-card-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: #007bff;
}

/* Efecto específico para tarjetas pendientes (rojo) */
.modal-body-pendientes .sucursal-card-pendiente {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #dc3545;
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
}

.modal-body-pendientes .sucursal-card-pendiente:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(220, 53, 69, 0.2);
    border-color: #dc3545;
    background: #fff5f5;
}

/* Efecto específico para tarjetas de ediciones (amarillo) */
.modal-body-pendientes .sucursal-card-edicion {
    background: #fff3cd;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #ffc107;
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
}

.modal-body-pendientes .sucursal-card-edicion:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(255, 193, 7, 0.2);
    border-color: #ffc107;
    background: #fffbf0;
}

/* Efecto específico para tarjetas informativas (sin horario - sin cursor pointer) */
.modal-body-pendientes .sucursal-card-informativo {
    background: #fff3cd;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #ffc107;
    transition: all 0.3s ease;
    /* Sin cursor pointer porque no es clickeable */
}

.modal-body-pendientes .sucursal-card-informativo:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 193, 7, 0.15);
    background: #fffbf0;
}

@media (max-width: 1024px) {
    .pendientes-container {
        min-width: 280px; /* Reducir mínimo en tablets */
    }
}

/* ========== TRANSICIONES SUAVES PARA MODAL DE AUDITORÍAS ========== */
.sucursal-auditoria-item {
    transition: all 0.3s ease;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 10px;
}

.sucursal-header {
    background: #f8f9fa;
    padding: 12px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border-bottom: 1px solid transparent;
}

.sucursal-header:hover {
    background: #e9ecef !important;
}

.sucursal-header.active {
    background: #e3f2fd !important;
    border-bottom: 1px solid #2196f3;
}

/* Contenedor de auditorías con transición de altura */
.auditorias-contenido {
    max-height: 0;
    overflow: hidden;
    transition: all 0.4s ease-in-out;
    background: white;
}

.auditorias-contenido.open {
    max-height: 500px;
    padding: 15px;
    animation: slideDown 0.4s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        max-height: 500px;
        transform: translateY(0);
    }
}

/* Iconos de flecha con transición */
.fa-chevron-down, .fa-chevron-up {
    transition: transform 0.3s ease;
}

.fa-chevron-down.rotated {
    transform: rotate(180deg);
}

/* Items individuales de auditoría con hover */
.auditoria-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.auditoria-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.auditoria-item:last-child {
    margin-bottom: 0;
}

/* Efectos para los botones de auditoría */
.btn-auditoria {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}

.btn-crear-auditoria {
    background: #28a745;
    color: white;
}

.btn-crear-auditoria:hover {
    background: #218838;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

/* Estados de porcentaje con colores */
.porcentaje-badge {
    background: #28a745;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: bold;
}

.porcentaje-badge.medio {
    background: #ffc107;
    color: #212529;
}

.porcentaje-badge.bajo {
    background: #dc3545;
    color: white;
}

/* Grid responsivo para auditorías */
.auditorias-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

/* Efecto de carga suave */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.sucursal-auditoria-item {
    animation: fadeIn 0.5s ease-out;
}

.sucursal-auditoria-item:nth-child(even) {
    animation-delay: 0.1s;
}

.sucursal-auditoria-item:nth-child(odd) {
    animation-delay: 0.2s;
}

/* Estilos para el detalle de auditorías en visitas */
.auditoria-item-detalle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.auditoria-item-detalle:hover {
    background: #e9ecef;
}

.auditoria-completa {
    color: #28a745;
}

.auditoria-incompleta {
    color: #dc3545;
}

.btn-agregar-auditoria {
    padding: 4px 8px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.7rem;
    transition: all 0.3s ease;
}

.btn-agregar-auditoria:hover {
    background: #218838;
    transform: translateY(-1px);
}
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, ''); ?>
            
            <div class="module-header">
                <h1 class="module-title-page">Área de Supervisión</h1>
            </div>
            
            <div class="indicadores-container">
                <!-- Indicador de Horarios Pendientes de Confirmación - CON DÍAS RESTANTES -->
                <div class="pendientes-container" style="margin-bottom: 30px;">
                    <div class="pendientes-card confirmacion-indicador <?= $estadoConfirmacion['color'] ?>" 
                         onclick="mostrarModalConfirmacion()" style="cursor: pointer;">
                        <div class="pendientes-content">
                            <div class="pendientes-count">
                                <?php 
                                $totalMostrar = $estadoConfirmacion['total_pendientes'] + count($estadoConfirmacion['ediciones_pendientes']);
                                if ($estadoConfirmacion['estado'] == 'completo') {
                                    echo '<i class="fas fa-check" style="font-size: 2.5rem;"></i>';
                                } else {
                                    echo $totalMostrar;
                                }
                                ?>
                            </div>
                            <div class="pendientes-info">
                                <div class="pendientes-titulo">
                                    Horarios por Confirmar
                                </div>
                                <?php if ($estadoConfirmacion['periodo_activo'] && $estadoConfirmacion['estado'] == 'pendiente'): ?>
                                    <div class="pendientes-fecha">
                                        (<?= $estadoConfirmacion['dias_restantes'] ?? 0 ?> días restantes)
                                    </div>
                                <?php elseif ($estadoConfirmacion['estado'] == 'fuera_periodo'): ?>
                                    <div class="pendientes-fecha">
                                        (Fuera de período)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Indicador de Auditorías Mensuales -->
                <?php
                $estadoAuditoriasMensual = obtenerEstadoAuditoriasMensual();
                ?>
                <div class="pendientes-container" style="margin-bottom: 30px;">
                    <div class="pendientes-card auditorias-indicador <?= $estadoAuditoriasMensual['estado_global'] == 'completo' ? 'verde' : ($estadoAuditoriasMensual['estado_global'] == 'avanzado' ? 'amarillo' : 'rojo') ?>" 
                         onclick="mostrarModalSucursalesAuditadas()" style="cursor: pointer;">
                        <div class="pendientes-content">
                            <div class="pendientes-count">
                                <?php if ($estadoAuditoriasMensual['estado_global'] == 'completo'): ?>
                                    <i class="fas fa-check" style="font-size: 2.5rem;"></i>
                                <?php else: ?>
                                    <?= $estadoAuditoriasMensual['total_sucursales'] - $estadoAuditoriasMensual['total_completas'] ?>
                                <?php endif; ?>
                            </div>
                            <div class="pendientes-info">
                                <div class="pendientes-titulo">
                                    Auditorías Pendientes
                                </div>
                                <div class="pendientes-fecha">
                                    (<?= ucfirst($estadoAuditoriasMensual['mes_nombre']) ?>)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- INDICADOR DE AUDITORÍAS PENDIENTES <div class="pendientes-container" id="auditoriasContainer" style="margin-bottom: 30px;" style="display:none;">
                    <div class="pendientes-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <h2 class="pendientes-title">
                            <i class="fas fa-clipboard-check"></i>
                            Auditorías Pendientes
                        </h2>
                        <div class="pendientes-content">
                            <div class="pendientes-count" id="auditoriasCount">0</div>
                            <div class="pendientes-info">
                                <div style="display:none;" class="pendientes-detalle" id="auditoriasDetalle"></div>
                            </div>
                        </div>
                    </div>
                </div> -->
            </div>
            
            <!-- Modal para detalles de auditorías pendientes -->
            <div id="modalAuditorias" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 95%;">
                    <div class="modal-header-pendientes">
                        <h3>Estado de Auditorías por Semana</h3>
                        <span class="close-modal" onclick="cerrarModalAuditorias()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div id="tablaAuditorias"></div>
                    </div>
                </div>
            </div>
            
            <!-- Modal de Auditorías Mensuales MEJORADO -->
            <div id="modalSucursalesAuditadas" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 95%;">
                    <div class="modal-header-pendientes">
                        <h3><i class="fas fa-clipboard-check"></i> Auditorías Mensuales por Sucursal</h3>
                        <span class="close-modal" onclick="cerrarModalSucursalesAuditadas()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <?php
                        $estadoAuditoriasMensual = obtenerEstadoAuditoriasMensual();
                        ?>
                        
                        <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <h4>Resumen Mensual - <?= ucfirst($estadoAuditoriasMensual['mes_nombre']) ?> <?= $estadoAuditoriasMensual['ano'] ?></h4>
                            <p><strong>Progreso Global:</strong> <?= $estadoAuditoriasMensual['total_completas'] ?> de <?= $estadoAuditoriasMensual['total_sucursales'] ?> sucursales (<?= $estadoAuditoriasMensual['porcentaje_global'] ?>%)</p>
                            <p><strong>Requisitos:</strong> 
                                • Departamento 1 (Managua): 3 visitas completas/mes<br>
                                • Otros departamentos: 2 visitas completas/mes<br>
                                <small>* Cada visita debe incluir las 6 auditorías (limpieza, personal, servicio, facturación, caja chica, inventario)</small>
                            </p>
                        </div>
            
                        <?php if ($estadoAuditoriasMensual['total_sucursales'] > 0): ?>
                            <div style="display: grid; gap: 15px;">
                                <?php foreach ($estadoAuditoriasMensual['sucursales'] as $sucursal): ?>
                                    <div class="sucursal-auditoria-item" style="border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden;">
                                        <div class="sucursal-header" style="background: #f8f9fa; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; cursor: pointer;" 
                                             onclick="toggleAuditoriasSucursal(<?= $sucursal['codigo'] ?>)">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <strong><?= $sucursal['nombre'] ?></strong>
                                                <span style="background: #6c757d; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">
                                                    <?= $sucursal['departamento_nombre'] ?>
                                                </span>
                                                <?php
                                                $clasePorcentaje = 'porcentaje-badge';
                                                if ($sucursal['porcentaje'] == 100) {
                                                    $clasePorcentaje .= '';
                                                } elseif ($sucursal['porcentaje'] >= 50) {
                                                    $clasePorcentaje .= ' medio';
                                                } else {
                                                    $clasePorcentaje .= ' bajo';
                                                }
                                                ?>
                                                <span class="<?= $clasePorcentaje ?>">
                                                    <?= $sucursal['visitas_completas'] ?>/<?= $sucursal['visitas_requeridas'] ?> visitas (<?= $sucursal['porcentaje'] ?>%)
                                                </span>
                                            </div>
                                            <i class="fas fa-chevron-down" id="icon-<?= $sucursal['codigo'] ?>"></i>
                                        </div>
                                        
                                        <div id="auditorias-<?= $sucursal['codigo'] ?>" class="auditorias-contenido">
                                            <div style="padding: 15px;">
                                                <div style="margin-bottom: 15px;">
                                                    <strong>Detalle de Visitas:</strong>
                                                    <?php if (empty($sucursal['detalle_visitas'])): ?>
                                                        <p style="color: #666; font-style: italic; margin-top: 10px;">No se han realizado visitas este mes</p>
                                                    <?php else: ?>
                                                        <div style="display: grid; gap: 15px; margin-top: 10px;">
                                                            <?php foreach ($sucursal['detalle_visitas'] as $index => $visita): ?>
                                                                <div style="border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden;">
                                                                    <div style="background: <?= $visita['completa'] ? '#d4edda' : '#f8d7da' ?>; padding: 10px 15px; display: flex; justify-content: space-between; align-items: center;">
                                                                        <span>
                                                                            <i class="fas fa-calendar-day"></i>
                                                                            <strong>Visita <?= $index + 1 ?>:</strong> <?= formatoFecha($visita['fecha']) ?>
                                                                        </span>
                                                                        <span style="<?= $visita['completa'] ? 'color: #155724;' : 'color: #721c24;' ?>">
                                                                            <?php if ($visita['completa']): ?>
                                                                                <i class="fas fa-check-circle"></i> Completa (<?= $visita['total_completas'] ?>/6)
                                                                            <?php else: ?>
                                                                                <i class="fas fa-exclamation-triangle"></i> Incompleta (<?= $visita['total_completas'] ?>/6)
                                                                            <?php endif; ?>
                                                                        </span>
                                                                    </div>
                                                                    <div style="padding: 15px; background: white;">
                                                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                                                                            <?php foreach ($visita['detalle_auditorias'] as $tipo => $auditoria): ?>
                                                                                <div style="display: flex; align-items: center; gap: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                                                                                    <?php if ($auditoria['completa']): ?>
                                                                                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                                                                                    <?php else: ?>
                                                                                        <i class="fas fa-times-circle" style="color: #dc3545;"></i>
                                                                                    <?php endif; ?>
                                                                                    <span style="flex: 1;"><?= $auditoria['nombre'] ?></span>
                                                                                    <?php if (!$auditoria['completa']): ?>
                                                                                        <a href="<?= $auditoria['url'] ?>?sucursal=<?= $sucursal['codigo'] ?>&fecha=<?= $visita['fecha'] ?>" 
                                                                                           class="btn-auditoria btn-crear-auditoria" 
                                                                                           style="padding: 4px 8px; font-size: 0.7rem;" 
                                                                                           target="_blank">
                                                                                            <i class="fas fa-plus"></i> Agregar
                                                                                        </a>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div style="border-top: 1px solid #dee2e6; padding-top: 15px;">
                                                    <strong>Agregar Nueva Auditoría:</strong>
                                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; margin-top: 10px;">
                                                        <a href="/modulos/supervision/auditorias_original/agregar.php?sucursal=<?= $sucursal['codigo'] ?>" 
                                                           class="btn-auditoria btn-crear-auditoria" 
                                                           style="text-align: center; padding: 8px;" 
                                                           target="_blank">
                                                            <i class="fas fa-plus"></i> Limpieza
                                                        </a>
                                                        <a href="/modulos/supervision/auditorias_original/agregarpersonal.php?sucursal=<?= $sucursal['codigo'] ?>" 
                                                           class="btn-auditoria btn-crear-auditoria" 
                                                           style="text-align: center; padding: 8px;" 
                                                           target="_blank">
                                                            <i class="fas fa-plus"></i> Personal
                                                        </a>
                                                        <a href="/modulos/supervision/auditorias_original/agregarservicio.php?sucursal=<?= $sucursal['codigo'] ?>" 
                                                           class="btn-auditoria btn-crear-auditoria" 
                                                           style="text-align: center; padding: 8px;" 
                                                           target="_blank">
                                                            <i class="fas fa-plus"></i> Servicio
                                                        </a>
                                                        <a href="/modulos/supervision/auditorias_original/auditinternas/auditoria_caja_facturacion.php?sucursal=<?= $sucursal['codigo'] ?>" 
                                                           class="btn-auditoria btn-crear-auditoria" 
                                                           style="text-align: center; padding: 8px;" 
                                                           target="_blank">
                                                            <i class="fas fa-plus"></i> Facturación
                                                        </a>
                                                        <a href="/modulos/supervision/auditorias_original/auditinternas/auditoria_caja_chica.php?sucursal=<?= $sucursal['codigo'] ?>" 
                                                           class="btn-auditoria btn-crear-auditoria" 
                                                           style="text-align: center; padding: 8px;" 
                                                           target="_blank">
                                                            <i class="fas fa-plus"></i> Caja Chica
                                                        </a>
                                                        <a href="/modulos/supervision/auditorias_original/auditinternas/auditoria_inventario.php?sucursal=<?= $sucursal['codigo'] ?>" 
                                                           class="btn-auditoria btn-crear-auditoria" 
                                                           style="text-align: center; padding: 8px;" 
                                                           target="_blank">
                                                            <i class="fas fa-plus"></i> Inventario
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                <h4>No hay sucursales para auditar</h4>
                                <p>No se encontraron sucursales activas.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Modal para crear nueva auditoría -->
            <div id="modalNuevaAuditoria" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 500px;">
                    <div class="modal-header-pendientes">
                        <h3><i class="fas fa-plus-circle"></i> Nueva Auditoría</h3>
                        <span class="close-modal" onclick="cerrarModalNuevaAuditoria()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="filtro-group">
                            <label for="sucursalSeleccionada">Sucursal:</label>
                            <select id="sucursalSeleccionada" class="filtro-select">
                                <option value="">Seleccionar sucursal...</option>
                            </select>
                        </div>
                        <div class="filtro-group">
                            <label for="tipoAuditoria">Tipo de Auditoría:</label>
                            <select id="tipoAuditoria" class="filtro-select">
                                <option value="">Seleccionar tipo...</option>
                                <optgroup label="Desempeño">
                                    <option value="limpieza">Limpieza</option>
                                    <option value="personal">Personal</option>
                                    <option value="servicio">Servicio</option>
                                </optgroup>
                                <optgroup label="Efectivo">
                                    <option value="facturacion">Caja Facturación</option>
                                    <option value="caja_chica">Caja Chica</option>
                                    <option value="inventario">Inventario</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="modal-actions">
                            <button type="button" onclick="crearNuevaAuditoria()" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Crear Auditoría
                            </button>
                            <button type="button" onclick="cerrarModalNuevaAuditoria()" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal de restricción -->
            <div id="modalRestriccion" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 500px;">
                    <div class="modal-header-pendientes" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                        <h3><i class="fas fa-ban"></i> Restricción de Acceso</h3>
                        <span class="close-modal" onclick="cerrarModalRestriccion()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="alert alert-warning" style="text-align: center; border: none; background: transparent;">
                            <i class="fas fa-exclamation-triangle fa-3x" style="color: #dc3545; margin-bottom: 15px;"></i>
                            <h4 style="color: #dc3545; margin-bottom: 15px;">Acceso Restringido</h4>
                            <p style="color: #666; line-height: 1.5;">
                                Tiene <strong style="color: #dc3545;" id="cantidadPendientesRestriccion">0</strong> 
                                horarios pendientes de <strong>semanas anteriores</strong> que deben ser aprobados primero.
                            </p>
                            <p style="color: #666; margin-top: 10px;">
                                Por favor, complete la aprobación de todos los horarios pendientes antes de acceder a otras herramientas del sistema.
                            </p>
                        </div>
                        <div class="modal-actions">
                            <button type="button" onclick="cerrarModalRestriccion()" class="btn btn-primary" style="padding: 10px 30px;">
                                <i class="fas fa-check"></i> Entendido
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal de Detalles de Confirmación Pendiente -->
            <div id="modalConfirmacion" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 95%;">
                    <div class="modal-header-pendientes">
                        <h3><i class="fas fa-clipboard-check"></i> Detalles de Horarios Pendientes de Confirmación</h3>
                        <span class="close-modal" onclick="cerrarModalConfirmacion()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="filtros-modal" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; display:none;">
                            <div>
                                <strong>Semana:</strong> 
                                <?php if ($estadoConfirmacion['semana_siguiente']): ?>
                                    <?= $estadoConfirmacion['semana_siguiente']['numero_semana'] ?> 
                                    (<?= formatoFecha($estadoConfirmacion['semana_siguiente']['fecha_inicio']) ?> - 
                                    <?= formatoFecha($estadoConfirmacion['semana_siguiente']['fecha_fin']) ?>)
                                <?php else: ?>
                                    No disponible
                                <?php endif; ?>
                                | 
                                <strong>Por confirmar:</strong> <?= $estadoConfirmacion['total_pendientes'] ?>
                                <?php if ($estadoConfirmacion['total_sin_horario'] > 0): ?>
                                    | <strong>Sin horario líder:</strong> <?= $estadoConfirmacion['total_sin_horario'] ?>
                                <?php endif; ?>
                                <?php if (!empty($estadoConfirmacion['ediciones_pendientes'])): ?>
                                    | <strong>Ediciones Líder:</strong> <?= count($estadoConfirmacion['ediciones_pendientes']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($estadoConfirmacion['periodo_activo'] && ($estadoConfirmacion['total_pendientes'] > 0 || !empty($estadoConfirmacion['sucursales_pendientes']))): ?>
                                <a href="<?= $estadoConfirmacion['url'] ?>" class="btn-ver-detalles" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> Confirmar Horarios
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php 
                        // Definir estados para el modal
                        $estadosConfirmacion = [
                            'completo' => [
                                'clase' => 'status-published',
                                'texto' => 'Completamente Confirmado'
                            ],
                            'pendiente' => [
                                'clase' => 'status-pending',
                                'texto' => 'Pendiente de Confirmación'
                            ],
                            'fuera_periodo' => [
                                'clase' => 'status-pending',
                                'texto' => 'Fuera de Período'
                            ]
                        ];
                        ?>
                        
                        <?php if (empty($estadoConfirmacion['sucursales_pendientes']) && empty($estadoConfirmacion['ediciones_pendientes'])): ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-check-circle" style="font-size: 3rem; color: #28a745; margin-bottom: 15px;"></i>
                                <h4>Confirmación completa</h4>
                                <p>Todos los horarios de la semana <?= $estadoConfirmacion['semana_siguiente']['numero_semana'] ?? 'siguiente' ?> han sido confirmados.</p>
                            </div>
                        <?php else: ?>
                            
                            <!-- Información del Período -->
                            <?php if ($estadoConfirmacion['periodo_activo']): ?>
                                <div style="background: #d1ecf1; padding: 15px; border-radius: 8px; border-left: 4px solid #17a2b8; margin-bottom: 15px; display:none;">
                                    <p style="margin: 0;">
                                        <i class="fas fa-clock"></i>
                                        <strong>Período activo:</strong> Tienes <strong><?= $estadoConfirmacion['dias_restantes'] ?> días</strong> para confirmar los horarios 
                                        de la semana <?= $estadoConfirmacion['semana_siguiente']['numero_semana'] ?>.
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Sección de Sucursales con Horarios Pendientes de Confirmar -->
                            <?php if (!empty($estadoConfirmacion['sucursales_pendientes'])): ?>
                                <div style="margin-bottom: 30px;">
                                    <h4 style="display:none;"><i class="fas fa-clock"></i> Sucursales con Horarios Pendientes de Confirmar (<?= count($estadoConfirmacion['sucursales_pendientes']) ?>)</h4>
                                    <p style="color: #666; margin-bottom: 15px; display:none;"><i class="fas fa-clock"></i>
                                        Estas sucursales tienen horarios programados por líderes que necesitan confirmación.
                                    </p>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 15px;">
                                        <?php foreach ($estadoConfirmacion['sucursales_pendientes'] as $sucursalPendiente): ?>
                                            <div class="sucursal-card-pendiente" 
                                                 onclick="window.open('programar_horarios_operaciones.php?semana=<?= $estadoConfirmacion['semana_siguiente']['numero_semana'] ?? '' ?>&sucursal=<?= $sucursalPendiente['sucursal']['codigo'] ?>', '_blank')">
                                                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 8px;">
                                                    <strong style="flex: 1;"><?= htmlspecialchars($sucursalPendiente['sucursal']['nombre']) ?></strong>
                                                    <span style="background: #dc3545; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.8rem; display:none;">
                                                        <?= $sucursalPendiente['pendientes_confirmar'] ?> pendientes
                                                    </span>
                                                </div>
                                                <div style="font-size: 0.85rem; color: #666;">
                                                    <!--Código: <?= $sucursalPendiente['sucursal']['codigo'] ?> | -->
                                                    <?php if ($estadoConfirmacion['semana_siguiente']): ?>
                                                        <?= formatoFecha($estadoConfirmacion['semana_siguiente']['fecha_inicio']) ?> - <?= formatoFecha($estadoConfirmacion['semana_siguiente']['fecha_fin']) ?>
                                                    <?php endif; ?>
                                                    (<?= $estadoConfirmacion['semana_siguiente']['numero_semana'] ?? 'N/A' ?>)
                                                    <?php if (isset($sucursalPendiente['sucursal']['sucursal'])): ?>
                                                        <br>Tipo: <?= $sucursalPendiente['sucursal']['sucursal'] == 1 ? 'Sucursal Física' : 'Otro' ?> |
                                                    <?php endif; ?>
                                                    <!--Con horario: <?= $sucursalPendiente['con_horario_lider'] ?>/<?= $sucursalPendiente['total_operarios'] ?> | 
                                                    Confirmados: <?= $sucursalPendiente['confirmados'] ?>-->
                                                </div>
                                                <div style="margin-top: 8px; text-align: right;">
                                                    <small style="color: #007bff; font-weight: bold;">
                                                        <i class="fas fa-external-link-alt"></i> Click para confirmar
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Sección de Ediciones Pendientes de reconfirmar edición de Líderes -->
                            <?php if (!empty($estadoConfirmacion['ediciones_pendientes'])): ?>
                                <div style="margin-bottom: 30px;">
                                    <h4 style="display:none;"><i class="fas fa-edit"></i> Ediciones Pendientes de Reconfirmar (<?= count($estadoConfirmacion['ediciones_pendientes']) ?>)</h4>
                                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin-bottom: 15px; display:none;">
                                        <p style="margin: 0; display:none;">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            <strong>Atención:</strong> Los líderes han realizado modificaciones <strong>después de la confirmación inicial</strong>. 
                                            Se solicita <strong>reconfirmación</strong> debido a cambios recientes.
                                        </p>
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                                        <?php foreach ($estadoConfirmacion['ediciones_pendientes'] as $edicion): ?>
                                            <div class="sucursal-card-edicion" 
                                                 onclick="window.open('programar_horarios_operaciones.php?semana=<?= $edicion['numero_semana'] ?>&sucursal=<?= $edicion['cod_sucursal'] ?>', '_blank')">
                                                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 8px;">
                                                    <strong style="flex: 1;"><?= htmlspecialchars($edicion['sucursal_nombre']) ?></strong>
                                                    <span style="background: #ffc107; color: #856404; padding: 3px 8px; border-radius: 12px; font-size: 0.8rem; display:none;">
                                                        <?= $edicion['operarios_editados'] ?> operarios
                                                    </span>
                                                </div>
                                                <div style="font-size: 0.85rem; color: #856404;">
                                                    <!-- Código: <?= $edicion['cod_sucursal'] ?> | -->
                                                    <?= formatoFecha($edicion['fecha_inicio']) ?> al <?= formatoFecha($edicion['fecha_fin']) ?> (<?= $edicion['numero_semana'] ?>)
                                                    <!-- <br>Periodo: <?= formatoFecha($edicion['fecha_inicio']) ?> - <?= formatoFecha($edicion['fecha_fin']) ?> -->
                                                    <br><strong>Estado: Confirmado con cambios</strong>
                                                </div>
                                                <div style="margin-top: 8px; text-align: right;">
                                                    <small style="color: #007bff; font-weight: bold;">
                                                        <i class="fas fa-external-link-alt"></i> Click para reconfirmar
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Sección de Sucursales Sin Horario del Líder -->
                            <?php if (!empty($estadoConfirmacion['sin_horario_lider'])): ?>
                                <div style="margin-bottom: 30px;">
                                    <h4 style="display:none;"><i class="fas fa-exclamation-triangle"></i> Sucursales Sin Horario del Líder (<?= count($estadoConfirmacion['sin_horario_lider']) ?>)</h4>
                                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin-bottom: 15px; display:none;">
                                        <p style="margin: 0;">
                                            <i class="fas fa-info-circle"></i> 
                                            <strong>Atención:</strong> Estas sucursales no han subido horarios para la semana <?= $estadoConfirmacion['semana_siguiente']['numero_semana'] ?? 'siguiente' ?>. 
                                            Contacte a los líderes correspondientes.
                                        </p>
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                                        <?php foreach ($estadoConfirmacion['sin_horario_lider'] as $sucursalSinHorario): ?>
                                            <div class="sucursal-card-informativo">
                                                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 8px;">
                                                    <strong style="flex: 1;"><?= htmlspecialchars($sucursalSinHorario['sucursal']['nombre']) ?></strong>
                                                    <span style="background: #ffc107; color: #856404; padding: 3px 8px; border-radius: 12px; font-size: 0.8rem; display:none;">
                                                        <?= $sucursalSinHorario['total_operarios'] ?> operarios
                                                    </span>
                                                </div>
                                                <div style="font-size: 0.85rem; color: #856404;">
                                                    <!-- Código: <?= $sucursalSinHorario['sucursal']['codigo'] ?> | -->
                                                    <?php if ($estadoConfirmacion['semana_siguiente']): ?>
                                                        <?= formatoFecha($estadoConfirmacion['semana_siguiente']['fecha_inicio']) ?> - <?= formatoFecha($estadoConfirmacion['semana_siguiente']['fecha_fin']) ?>
                                                    <?php endif; ?>
                                                    (<?= $estadoConfirmacion['semana_siguiente']['numero_semana'] ?? 'N/A' ?>)
                                                    <br><strong>Sin horario programado</strong>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Grupo 1 -->
            <h2 class="category-title">Recursos Humanos</h2>
            <div class="modules">
                <a href="programar_horarios_operaciones.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3 class="module-title">Gestión de RRHH</h3>
                </a>
                
                <a href="ver_horarios_compactos.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="module-title">Control de Asistencia</h3>
                </a>
                
                <a href="gestion_categorias_colaboradores.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3 class="module-title">Gestión de Categorías</h3>
                </a>
            </div>
            
            <!-- Grupo 2 -->
            <h2 class="category-title">Comunicación Interna</h2>
            <div class="modules">
                <a href="auditorias_original/index_avisos_publico.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3 class="module-title">Vista Pública</h3>
                </a>
                
                <a href="auditorias_original/index.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3 class="module-title">Auditorías de Desempeño</h3>
                </a>
            </div>
            
            <!-- Grupo 3 -->
            <h2 class="category-title">Supervisión</h2>
            <div class="modules">
                <a href="auditorias_original/auditinternas/auditorias_consolidadas.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <h3 class="module-title">Auditorías de Efectivo</h3>
                </a>
            </div>
            
            <h2 class="category-title">Mantenimiento y Equipos</h2>
            <div class="modules">
                <!-- Histórico -->
                <a href="../supervision/pruebaodoo.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3 class="module-title">Solicitudes</h3>
                </a>
            </div>
    </div>
    
    <script>
        // Cargar horarios pendientes al abrir la página
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar auditorías pendientes
            cargarAuditoriasPendientes();
            
            // Hacer clickeable las tarjetas sin necesidad de botones
            const auditoriasCard = document.querySelector('#auditoriasContainer .pendientes-card');
            if (auditoriasCard) {
                auditoriasCard.style.cursor = 'pointer';
                auditoriasCard.addEventListener('click', function(e) {
                    if (!e.target.classList.contains('btn-ver-detalles') && 
                        !e.target.closest('.btn-ver-detalles')) {
                        mostrarModalAuditorias();
                    }
                });
            }
            
            const tarjetaConfirmacion = document.querySelector('.confirmacion-indicador');
            if (tarjetaConfirmacion) {
                tarjetaConfirmacion.style.cursor = 'pointer';
                tarjetaConfirmacion.addEventListener('click', function(e) {
                    // Evitar que se active cuando se hace click en botones dentro de la tarjeta
                    if (!e.target.classList.contains('btn-ver-detalles') && 
                        !e.target.closest('.btn-ver-detalles')) {
                        mostrarModalConfirmacion();
                    }
                });
            }
        });
        
        // Función para mostrar/ocultar las auditorías de cada sucursal con transiciones suaves
        function toggleAuditoriasSucursal(codSucursal) {
            const contenido = document.getElementById('auditorias-' + codSucursal);
            const icono = document.getElementById('icon-' + codSucursal);
            const header = contenido.previousElementSibling;
            
            if (contenido.classList.contains('open')) {
                // Cerrar con animación
                contenido.classList.remove('open');
                contenido.style.maxHeight = '0';
                contenido.style.padding = '0';
                icono.classList.remove('rotated');
                header.classList.remove('active');
                
                // Remover completamente el padding después de la animación
                setTimeout(() => {
                    if (!contenido.classList.contains('open')) {
                        contenido.style.display = 'none';
                    }
                }, 400);
            } else {
                // Abrir con animación
                contenido.style.display = 'block';
                // Pequeño delay para que el display:block se aplique antes de la animación
                setTimeout(() => {
                    contenido.classList.add('open');
                    contenido.style.maxHeight = '500px';
                    contenido.style.padding = '15px';
                    icono.classList.add('rotated');
                    header.classList.add('active');
                }, 10);
            }
        }
        
        // Cerrar todos los acordeones al abrir el modal
        function mostrarModalSucursalesAuditadas() {
            // Cerrar todos los acordeones abiertos primero
            const acordeonesAbiertos = document.querySelectorAll('.auditorias-contenido.open');
            acordeonesAbiertos.forEach(acordeon => {
                acordeon.classList.remove('open');
                acordeon.style.maxHeight = '0';
                acordeon.style.padding = '0';
                const icono = acordeon.previousElementSibling.querySelector('i');
                if (icono) icono.classList.remove('rotated');
                acordeon.previousElementSibling.classList.remove('active');
                
                setTimeout(() => {
                    acordeon.style.display = 'none';
                }, 400);
            });
            
            document.getElementById('modalSucursalesAuditadas').style.display = 'block';
        }
        
        // Cerrar todos los acordeones al cerrar el modal
        function cerrarModalSucursalesAuditadas() {
            // Cerrar todos los acordeones
            const acordeonesAbiertos = document.querySelectorAll('.auditorias-contenido.open');
            acordeonesAbiertos.forEach(acordeon => {
                acordeon.classList.remove('open');
                acordeon.style.maxHeight = '0';
                acordeon.style.padding = '0';
                const icono = acordeon.previousElementSibling.querySelector('i');
                if (icono) icono.classList.remove('rotated');
                acordeon.previousElementSibling.classList.remove('active');
            });
            
            document.getElementById('modalSucursalesAuditadas').style.display = 'none';
        }
        
        // Función para mostrar el modal de confirmación
        function mostrarModalConfirmacion() {
            document.getElementById('modalConfirmacion').style.display = 'block';
        }
        
        // Función para cerrar el modal de confirmación
        function cerrarModalConfirmacion() {
            document.getElementById('modalConfirmacion').style.display = 'none';
        }
        
        function bloquearModulos() {
            // COMENTAR TEMPORALMENTE ESTA FUNCIÓN - DESBLOQUEAR ACCESO mientras no se defina aún el implementar esto
            /*
            // Seleccionar todos los módulos excepto el de horarios
            const modulos = document.querySelectorAll('.module-card:not([href*="programar_horarios_operaciones"])');
            modulos.forEach(modulo => {
                modulo.style.opacity = '0.6';
                modulo.style.pointerEvents = 'none';
                modulo.style.cursor = 'not-allowed';
                
                // Agregar tooltip
                modulo.title = 'Complete los horarios pendientes de semanas anteriores para desbloquear';
            });
            */
            
            console.log('Módulos desbloqueados temporalmente - Función comentada');
        }
        
        function mostrarModalRestriccion(cantidad) {
            document.getElementById('cantidadPendientesRestriccion').textContent = cantidad;
            document.getElementById('modalRestriccion').style.display = 'block';
        }
        
        function cerrarModalRestriccion() {
            document.getElementById('modalRestriccion').style.display = 'none';
        }
        
        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            const modalRestriccion = document.getElementById('modalRestriccion');
            const modalAuditorias = document.getElementById('modalAuditorias');
            const modalNuevaAuditoria = document.getElementById('modalNuevaAuditoria');
            
            const modalSucursalesAuditadas = document.getElementById('modalSucursalesAuditadas');
            
            const modalConfirmacion = document.getElementById('modalConfirmacion');
            
            if (event.target === modalRestriccion) {
                cerrarModalRestriccion();
            }
            if (event.target === modalAuditorias) {
                cerrarModalAuditorias();
            }
            if (event.target === modalNuevaAuditoria) {
                cerrarModalNuevaAuditoria();
            }
            
            if (event.target === modalConfirmacion) {
                cerrarModalConfirmacion();
            }
            
            if (event.target === modalSucursalesAuditadas) {
                cerrarModalSucursalesAuditadas();
            }
        }
        
        // Variables globales para auditorías
        let auditoriasData = null;
        
        // Cargar auditorías pendientes mensuales
        function cargarAuditoriasPendientes() {
            fetch('obtener_auditorias_pendientes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        auditoriasData = data;
                        actualizarIndicadorAuditorias(data);
                    } else {
                        console.error('Error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error de conexión:', error);
                });
        }
        
        // Actualizar indicador de auditorías mensuales
        function actualizarIndicadorAuditorias(data) {
            const countElement = document.getElementById('auditoriasCount');
            const detalleElement = document.getElementById('auditoriasDetalle');
            const container = document.getElementById('auditoriasContainer');
        
            if (!countElement || !detalleElement || !container) return;
        
            countElement.textContent = data.total_pendientes;
            
            // Actualizar detalle
            if (data.total_pendientes > 0) {
                detalleElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${data.total_pendientes} sucursales pendientes`;
                detalleElement.style.color = '#ffeb3b';
            } else {
                detalleElement.innerHTML = 'Todas las auditorías están al día';
                detalleElement.style.color = 'rgba(255,255,255,0.9)';
            }
            
            // Aplicar color según cantidad de pendientes
            const card = container.querySelector('.pendientes-card');
            if (data.total_pendientes > 0) {
                card.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
            } else {
                card.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
            }
        }
        
        // Mostrar modal de auditorías
        function mostrarModalAuditorias() {
            if (!auditoriasData) {
                alert('Cargando datos...');
                return;
            }
            
            const modal = document.getElementById('modalAuditorias');
            const tablaContainer = document.getElementById('tablaAuditorias');
            
            tablaContainer.innerHTML = construirTablaAuditorias(auditoriasData);
            modal.style.display = 'block';
        }
        
        // Construir tabla de auditorías
        function construirTablaAuditorias(data) {
            let html = `
                <div style="overflow-x: auto;">
                    <table class="tabla-auditorias">
                        <thead>
                            <tr>
                                <th>Sucursal</th>
            `;
            
            // Encabezados de semanas
            Object.keys(data.semanas).forEach(semanaKey => {
                const semana = data.semanas[semanaKey];
                const esActual = semana.numero_semana === data.semana_actual;
                html += `<th>S${semana.numero_semana}${esActual ? ' (Actual)' : ''}<br>
                        <small>${formatoFechaCorta(semana.fecha_inicio)} - ${formatoFechaCorta(semana.fecha_fin)}</small></th>`;
            });
            
            html += `</tr></thead><tbody>`;
            
            // Filas de sucursales
            const primeraSemana = Object.keys(data.semanas)[0];
            data.semanas[primeraSemana].sucursales.forEach((sucursal, index) => {
                html += `<tr>
                    <td class="sucursal-header">
                        <strong>${sucursal.nombre}</strong>
                        <br><small>${sucursal.auditorias_completadas}/${sucursal.total_auditorias} completadas</small>
                    </td>`;
                
                // Celdas por semana
                Object.keys(data.semanas).forEach(semanaKey => {
                    const semanaData = data.semanas[semanaKey];
                    const sucursalSemana = semanaData.sucursales[index];
                    
                    html += `<td>`;
                    
                    if (sucursalSemana.color === 'futura') {
                        html += `<span class="estado-futura">Futura</span>`;
                    } else {
                        html += `<span class="estado-${sucursalSemana.color}">
                            ${sucursalSemana.porcentaje}%
                        </span>`;
                        
                        // Botones de acción
                        if (sucursalSemana.color !== 'verde') {
                            html += `<br>
                            <button class="btn-auditoria btn-crear-auditoria" 
                                    onclick="mostrarModalNuevaAuditoria(${sucursalSemana.codigo}, ${semanaData.numero_semana})">
                                <i class="fas fa-plus"></i> Crear
                            </button>`;
                        }
                        
                        html += `
                            <button class="btn-auditoria btn-ver-auditoria" style="display:none;" 
                                    onclick="verDetalleAuditorias(${sucursalSemana.codigo}, ${semanaData.numero_semana})">
                                <i class="fas fa-eye"></i> Ver
                            </button>
                        `;
                    }
                    
                    html += `</td>`;
                });
                
                html += `</tr>`;
            });
            
            html += `</tbody></table></div>`;
            
            return html;
        }
        
        // Formatear fecha corta
        function formatoFechaCorta(fecha) {
            const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            const fechaObj = new Date(fecha + 'T00:00:00-06:00');
            const dia = fechaObj.getDate();
            const mes = meses[fechaObj.getMonth()];
            return `${dia}-${mes}`;
        }
        
        // Mostrar modal para nueva auditoría
        function mostrarModalNuevaAuditoria(codSucursal, numeroSemana) {
            // Llenar select de sucursales
            const selectSucursal = document.getElementById('sucursalSeleccionada');
            selectSucursal.innerHTML = '<option value="">Seleccionar sucursal...</option>';
            
            if (auditoriasData) {
                const primeraSemana = Object.keys(auditoriasData.semanas)[0];
                auditoriasData.semanas[primeraSemana].sucursales.forEach(sucursal => {
                    const selected = sucursal.codigo === codSucursal ? 'selected' : '';
                    selectSucursal.innerHTML += `<option value="${sucursal.codigo}" ${selected}>${sucursal.nombre}</option>`;
                });
            }
            
            // Guardar datos en el modal
            selectSucursal.setAttribute('data-semana', numeroSemana);
            
            document.getElementById('modalNuevaAuditoria').style.display = 'block';
        }
        
        // Crear nueva auditoría
        function crearNuevaAuditoria() {
            const codSucursal = document.getElementById('sucursalSeleccionada').value;
            const tipoAuditoria = document.getElementById('tipoAuditoria').value;
            const numeroSemana = document.getElementById('sucursalSeleccionada').getAttribute('data-semana');
            
            if (!codSucursal || !tipoAuditoria) {
                alert('Por favor seleccione sucursal y tipo de auditoría');
                return;
            }
            
            // Determinar la URL según el tipo de auditoría
            let url = '';
            const auditoriasEfectivo = ['facturacion', 'caja_chica', 'inventario'];
            
            if (auditoriasEfectivo.includes(tipoAuditoria)) {
                // Auditorías de efectivo
                url = `auditorias_original/auditinternas/`;
                if (tipoAuditoria === 'facturacion') url += 'auditoria_caja_facturacion.php';
                else if (tipoAuditoria === 'caja_chica') url += 'auditoria_caja_chica.php';
                else if (tipoAuditoria === 'inventario') url += 'auditoria_inventario.php';
            } else {
                // Auditorías de desempeño
                url = `auditorias_original/`;
                if (tipoAuditoria === 'limpieza') url += 'agregar.php';
                else if (tipoAuditoria === 'personal') url += 'agregarpersonal.php';
                else if (tipoAuditoria === 'servicio') url += 'agregarservicio.php';
            }
            
            // Abrir en nueva pestaña
            window.open(url, '_blank');
            cerrarModalNuevaAuditoria();
        }
        
        // Ver detalle de auditorías de una sucursal
        function verDetalleAuditorias(codSucursal, numeroSemana) {
            // Aquí podrías implementar una vista detallada de las auditorías de esa sucursal
            alert(`Ver detalle de auditorías para sucursal ${codSucursal} en semana ${numeroSemana}`);
        }
        
        // Cerrar modales
        function cerrarModalAuditorias() {
            document.getElementById('modalAuditorias').style.display = 'none';
        }
        
        function cerrarModalNuevaAuditoria() {
            document.getElementById('modalNuevaAuditoria').style.display = 'none';
        }
        
        // Actualizar el evento de tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModalConfirmacion();
                cerrarModalSucursalesAuditadas();
                // ... otros modales existentes ...
            }
        });
    </script>
</body>
</html>