<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
// obtiene_ausencias_colaboradores.php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

//session_start();

/**
 * Determina color del indicador según cantidad de ausencias
 */
function determinarColorIndicadorAusenciasColaboradores($ausencias) {
    if (empty($ausencias)) return 'verde';
    
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
    
    if ($diasMaximos >= 7) return 'rojo';      // 1 semana o más
    if ($diasMaximos >= 5) return 'amarillo';  // 5-6 días
    if ($diasMaximos >= 3) return 'naranja';   // 3-4 días
    return 'verde';                            // Menos de 3 días
}

/**
 * Verifica si tiene marcación en CUALQUIER sucursal en una fecha específica
 */
function tieneMarcacionCualquierSucursal($codOperario, $fecha) {
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
 * Verifica si un operario debería trabajar según NUEVO criterio:
 * SOLO si el estado del día es 'Activo' u 'Otra.Tienda'
 */
function deberiaTrabajarDiaNuevoCriterio($codOperario, $codSucursal, $fecha) {
    global $conn;
    
    // Obtener la semana del sistema para esta fecha
    $semana = obtenerSemanaPorFecha($fecha);
    if (!$semana) return false;
    
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
 * Verifica días consecutivos sin marcación según NUEVO criterio CORREGIDO:
 * Solo cuenta días con estado 'Activo' u 'Otra.Tienda' en horario programado
 * Y que NO tengan marcación, Y que el periodo de ausencia sea HASTA HOY (sin marcaciones posteriores)
 */
function verificarAusenciaConsecutivaNuevoCriterio($codOperario, $codSucursal) {
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
 * Obtiene operarios con ausencias de 3+ días consecutivos (nuevo criterio)
 * Solo cuenta cuando el estado del día es 'Activo' u 'Otra.Tienda' y no hay marcación
 */
function obtenerAusenciasColaboradores() {
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

$response = [
    'success' => false,
    'total_ausencias' => 0,
    'ausencias' => [],
    'color_indicador' => 'verde'
];

try {
    // Verificar permisos
    if (!isset($_SESSION['usuario_id']) || 
        (!verificarAccesoCargo([13, 16, 39, 30, 37]) && 
         !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin'))) {
        $response['message'] = 'Acceso no autorizado';
        echo json_encode($response);
        exit();
    }
    
    $ausencias = obtenerAusenciasColaboradores();
    $totalAusencias = count($ausencias);
    
    // Determinar color del indicador
    $color = determinarColorIndicadorAusenciasColaboradores($ausencias);
    
    $response = [
        'success' => true,
        'total_ausencias' => $totalAusencias,
        'ausencias' => $ausencias,
        'color_indicador' => $color
    ];
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>