<?php
// require_once '../../core/auth/auth.php';
// require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

// Verificar acceso
verificarAccesoCargo([21]);

header('Content-Type: application/json');

// Habilitar errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Obtener el mes y año actual
    $mesActual = date('n');
    $anoActual = date('Y');
    
    // Obtener todas las sucursales activas
    $sucursales = obtenerTodasSucursales();
    
    // Tipos de auditorías
    $tiposAuditorias = [
        'desempeño' => ['limpieza', 'personal', 'servicio'],
        'efectivo' => ['facturacion', 'caja_chica', 'inventario']
    ];
    
    $resultados = [];
    $totalPendientes = 0;
    
    foreach ($sucursales as $sucursal) {
        $codDepartamento = $sucursal['cod_departamento'];
        
        // Determinar visitas requeridas según departamento
        $visitasRequeridas = ($codDepartamento == 1) ? 3 : 2;
        
        // Obtener las visitas realizadas este mes
        $visitasRealizadas = obtenerVisitasRealizadasMes($sucursal['codigo'], $mesActual, $anoActual);
        
        // Verificar si cada visita tiene las 6 auditorías
        $visitasCompletas = 0;
        foreach ($visitasRealizadas as $fechaVisita) {
            if (visitaTieneTodasAuditorias($sucursal['codigo'], $fechaVisita)) {
                $visitasCompletas++;
            }
        }
        
        $sucursalData = [
            'codigo' => $sucursal['codigo'],
            'nombre' => $sucursal['nombre'],
            'departamento' => $codDepartamento,
            'departamento_nombre' => obtenerNombreDepartamento($codDepartamento),
            'visitas_requeridas' => $visitasRequeridas,
            'visitas_completas' => $visitasCompletas,
            'visitas_realizadas' => count($visitasRealizadas),
            'porcentaje' => $visitasRequeridas > 0 ? round(($visitasCompletas / $visitasRequeridas) * 100) : 0,
            'detalle_visitas' => $visitasRealizadas
        ];
        
        // Determinar color según porcentaje
        if ($sucursalData['porcentaje'] == 100) {
            $sucursalData['color'] = 'verde';
        } elseif ($sucursalData['porcentaje'] >= 70) {
            $sucursalData['color'] = 'amarillo';
        } else {
            $sucursalData['color'] = 'rojo';
            $totalPendientes++;
        }
        
        $resultados[] = $sucursalData;
    }
    
    echo json_encode([
        'success' => true,
        'sucursales' => $resultados,
        'mes_actual' => $mesActual,
        'ano_actual' => $anoActual,
        'total_pendientes' => $totalPendientes,
        'total_sucursales' => count($sucursales)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
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
 * Verifica si una visita (fecha) tiene las 6 auditorías completas
 */
function visitaTieneTodasAuditorias($codSucursal, $fechaVisita) {
    $detalle = obtenerDetalleAuditoriasVisita($codSucursal, $fechaVisita);
    return $detalle['completa'];
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
?>