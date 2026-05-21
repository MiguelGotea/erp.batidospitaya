<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php';

// Verificar conexión

if (!$conn) {
    die("Error de conexión a la base de datos");
}

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
// Verificar acceso al módulo
if (!verificarAccesoCargo([8, 13, 16, 49])) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Establecer fechas por defecto (mes actual)
$hoy = new DateTime();
$primerDiaMes = $hoy->format('Y-m-01');
$ultimoDiaMes = $hoy->format('Y-m-t');

// Obtener fechas desde los parámetros GET o usar el mes actual
$fechaDesde = $_GET['desde'] ?? $primerDiaMes;
$fechaHasta = $_GET['hasta'] ?? $ultimoDiaMes;

// Validar que las fechas no estén vacías
if (empty($fechaDesde)) $fechaDesde = $primerDiaMes;
if (empty($fechaHasta)) $fechaHasta = $ultimoDiaMes;

// FUNCIONES DE EXPORTACIÓN (ACTUALIZADAS - SIN FILTRO DE OPERATIVO)

/**
 * Exportar faltas automáticas + séptimo día (EXCLUYENDO LAS QUE YA TIENEN JUSTIFICACIÓN)
 * ACTUALIZADA: Respeta rango de fechas y excluye faltas justificadas
 */
function exportarFaltasAutoSeptimo($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    // 1. Obtener todas las faltas automáticas (detectadas por el sistema) - RESPETANDO RANGO DE FECHAS
    $faltasAutomaticas = obtenerFaltasAutomaticasParaContabilidad($codSucursal, $fechaDesde, $fechaHasta);
    
    // 2. Obtener todas las faltas manuales que JUSTIFICAN faltas (excluyendo Pendiente y No_Pagado) - RESPETANDO RANGO
    $sqlFaltasJustificadas = "
        SELECT fm.cod_operario, fm.fecha_falta
        FROM faltas_manual fm
        WHERE fm.fecha_falta BETWEEN ? AND ?
        AND fm.tipo_falta NOT IN ('Pendiente', 'No_Pagado')
    ";
    
    $paramsJustificadas = [$fechaDesde, $fechaHasta];
    
    if (!empty($codSucursal) && $codSucursal !== 'todas') {
        $sqlFaltasJustificadas .= " AND fm.cod_sucursal = ?";
        $paramsJustificadas[] = $codSucursal;
    }
    
    $stmt = $conn->prepare($sqlFaltasJustificadas);
    $stmt->execute($paramsJustificadas);
    $faltasJustificadas = $stmt->fetchAll();
    
    // 3. Crear un array de claves únicas para faltas justificadas (operario + fecha)
    $justificadasMap = [];
    foreach ($faltasJustificadas as $fj) {
        $clave = $fj['cod_operario'] . '_' . $fj['fecha_falta'];
        $justificadasMap[$clave] = true;
    }
    
    // 4. Filtrar las faltas automáticas: excluir las que ya están justificadas
    $faltasAutomaticasFiltradas = [];
    foreach ($faltasAutomaticas as $fa) {
        $clave = $fa['cod_operario'] . '_' . $fa['fecha_falta'];
        if (!isset($justificadasMap[$clave])) {
            $faltasAutomaticasFiltradas[] = $fa;
        }
    }
    
    // 5. Obtener faltas manuales de tipo Dia_mas_septimo Y Pendiente - RESPETANDO RANGO
    $sql = "
        SELECT fm.*, 
               o.Nombre as operario_nombre, o.Nombre2 as operario_nombre2,
               o.Apellido as operario_apellido, o.Apellido2 as operario_apellido2,
               s.nombre as sucursal_nombre
        FROM faltas_manual fm
        JOIN Operarios o ON fm.cod_operario = o.CodOperario
        JOIN sucursales s ON fm.cod_sucursal = s.codigo
        WHERE (fm.tipo_falta = 'Dia_mas_septimo' OR fm.tipo_falta = 'Pendiente')
        AND fm.fecha_falta BETWEEN ? AND ?
    ";
    
    $params = [$fechaDesde, $fechaHasta];
    
    if (!empty($codSucursal) && $codSucursal !== 'todas') {
        $sql .= " AND fm.cod_sucursal = ?";
        $params[] = $codSucursal;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $faltasSeptimo = $stmt->fetchAll();
    
    // Configurar headers para descarga de archivo Excel CON UTF-8 y rango de fechas
    $nombreArchivo = "faltas_auto_septimo_{$fechaDesde}_a_{$fechaHasta}.xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
    echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '</head>';
    echo '<body>';
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Código</th>';
    echo '<th>Persona</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha</th>';
    echo '<th>Observaciones</th>';
    echo '<th>Fecha Registro</th>';
    echo '</tr>';
    
    // Agregar faltas automáticas FILTRADAS (excluyendo justificadas)
    foreach ($faltasAutomaticasFiltradas as $falta) {
        echo '<tr>';
        $nombreCompleto = obtenerNombreCompletoOperario([
            'Nombre' => $falta['operario_nombre'],
            'Nombre2' => $falta['operario_nombre2'] ?? '',
            'Apellido' => $falta['operario_apellido'],
            'Apellido2' => $falta['operario_apellido2'] ?? ''
        ]);
        
        // FECHA REGISTRO CON AJUSTE DE -6 HORAS
        $fechaRegistro = '';
        if (!empty($falta['fecha_registro'])) {
            $fechaObj = new DateTime($falta['fecha_registro']);
            $fechaObj->modify('-0 hours');
            $fechaRegistro = $fechaObj->format('Y-m-d');
        }
        
        // COLUMNA PERSONA MODIFICADA: código contrato + nombre completo
        $persona = ($falta['cod_contrato'] ?? '') . ' ' . htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8');
        
        echo '<td>' . ($falta['cod_contrato'] ?? '') . '</td>';
        echo '<td>' . $persona . '</td>';
        echo '<td>' . htmlspecialchars($falta['sucursal_nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . $falta['fecha_falta'] . '</td>';
        echo '<td>No se presentó</td>';
        echo '<td>' . $fechaRegistro . '</td>';
        echo '</tr>';
    }
    
    // Agregar faltas de séptimo día
    foreach ($faltasSeptimo as $falta) {
        echo '<tr>';
        $nombreCompleto = obtenerNombreCompletoOperario([
            'Nombre' => $falta['operario_nombre'],
            'Nombre2' => $falta['operario_nombre2'] ?? '',
            'Apellido' => $falta['operario_apellido'],
            'Apellido2' => $falta['operario_apellido2'] ?? ''
        ]);
        
        // COLUMNA PERSONA MODIFICADA: código contrato + nombre completo
        $persona = ($falta['cod_contrato'] ?? '') . ' ' . htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8');
        
        // FECHA REGISTRO CON AJUSTE DE -6 HORAS
        $fechaRegistro = '';
        if (!empty($falta['fecha_registro'])) {
            $fechaObj = new DateTime($falta['fecha_registro']);
            $fechaObj->modify('-6 hours');
            $fechaRegistro = $fechaObj->format('Y-m-d');
        }
        
        // Convertir el tipo de falta a texto legible
        $tipoFalta = str_replace(
            ['Dia_mas_septimo', 'Pendiente', 'No_Pagado'],
            ['Día + Séptimo', 'Líder subió reporte, pendiente por rrhh', 'No Pagado'],
            $falta['tipo_falta']
        );
        
        echo '<td>' . ($falta['cod_contrato'] ?? '') . '</td>';
        echo '<td>' . $persona . '</td>';
        echo '<td>' . htmlspecialchars($falta['sucursal_nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . $falta['fecha_falta'] . '</td>';
        echo '<td>' . htmlspecialchars($tipoFalta, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . $fechaRegistro . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

/**
 * Exportar permisos (todos los tipos excepto Vacaciones y Dia_mas_septimo) - RESPETANDO RANGO
 */
function exportarPermisos($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    $sql = "
        SELECT fm.*, 
               o.Nombre as operario_nombre, o.Nombre2 as operario_nombre2,
               o.Apellido as operario_apellido, o.Apellido2 as operario_apellido2,
               s.nombre as sucursal_nombre
        FROM faltas_manual fm
        JOIN Operarios o ON fm.cod_operario = o.CodOperario
        JOIN sucursales s ON fm.cod_sucursal = s.codigo
        WHERE fm.tipo_falta NOT IN ('Vacaciones', 'Dia_mas_septimo', 'Pendiente')
        AND fm.fecha_falta BETWEEN ? AND ?
    ";
    
    $params = [$fechaDesde, $fechaHasta];
    
    if (!empty($codSucursal) && $codSucursal !== 'todas') {
        $sql .= " AND fm.cod_sucursal = ?";
        $params[] = $codSucursal;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $permisos = $stmt->fetchAll();
    
    // Configurar headers para descarga con rango de fechas
    $nombreArchivo = "permisos_{$fechaDesde}_a_{$fechaHasta}.xls";
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Código</th>';
    echo '<th>Persona</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha</th>';
    echo '<th>Días</th>';
    echo '<th>% Salario a Pagar</th>';
    echo '<th>Tipo Permiso</th>';
    echo '<th>Observaciones</th>';
    echo '<th>Fecha Registro</th>';
    echo '</tr>';
    
    foreach ($permisos as $permiso) {
        echo '<tr>';
        $nombreCompleto = obtenerNombreCompletoOperario([
            'Nombre' => $permiso['operario_nombre'],
            'Nombre2' => $permiso['operario_nombre2'] ?? '',
            'Apellido' => $permiso['operario_apellido'],
            'Apellido2' => $permiso['operario_apellido2'] ?? ''
        ]);
        
        // COLUMNA PERSONA MODIFICADA: código contrato + nombre completo
        $persona = ($permiso['cod_contrato'] ?? '') . ' ' . htmlspecialchars($nombreCompleto);
        
        // FECHA REGISTRO CON AJUSTE DE -6 HORAS
        $fechaRegistro = '';
        if (!empty($permiso['fecha_registro'])) {
            $fechaObj = new DateTime($permiso['fecha_registro']);
            $fechaObj->modify('-6 hours');
            $fechaRegistro = $fechaObj->format('Y-m-d');
        }
        
        echo '<td>' . ($permiso['cod_contrato'] ?? '') . '</td>';
        echo '<td>' . $persona . '</td>';
        echo '<td>' . htmlspecialchars($permiso['sucursal_nombre']) . '</td>';
        echo '<td>' . $permiso['fecha_falta'] . '</td>';
        echo '<td>' . 1 . '</td>';
        echo '<td>' . ($permiso['porcentaje_pago'] ?? 0) . '%</td>';
        echo '<td>' . str_replace('_', ' ', $permiso['tipo_falta']) . '</td>';
        $obsDisplay = !empty($permiso['observaciones_rrhh']) ? $permiso['observaciones_rrhh'] : $permiso['observaciones'];
        echo '<td>' . ($obsDisplay ? htmlspecialchars($obsDisplay) : 'Sin comentarios') . '</td>';
        echo '<td>' . $fechaRegistro . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

/**
 * Exportar vacaciones - RESPETANDO RANGO
 */
function exportarVacaciones($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    $sql = "
        SELECT fm.*, 
               o.Nombre as operario_nombre, o.Nombre2 as operario_nombre2,
               o.Apellido as operario_apellido, o.Apellido2 as operario_apellido2,
               s.nombre as sucursal_nombre
        FROM faltas_manual fm
        JOIN Operarios o ON fm.cod_operario = o.CodOperario
        JOIN sucursales s ON fm.cod_sucursal = s.codigo
        WHERE fm.tipo_falta = 'Vacaciones'
        AND fm.fecha_falta BETWEEN ? AND ?
    ";
    
    $params = [$fechaDesde, $fechaHasta];
    
    if (!empty($codSucursal) && $codSucursal !== 'todas') {
        $sql .= " AND fm.cod_sucursal = ?";
        $params[] = $codSucursal;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $vacaciones = $stmt->fetchAll();
    
    // Configurar headers para descarga con rango de fechas
    $nombreArchivo = "vacaciones_{$fechaDesde}_a_{$fechaHasta}.xls";
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Código</th>';
    echo '<th>Persona</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha Inicio</th>';
    echo '<th>Fecha Fin</th>';
    echo '<th>Dias</th>';
    echo '<th>Observaciones</th>';
    echo '<th>Tipo</th>';
    echo '<th>Fecha Registro</th>';
    echo '</tr>';
    
    foreach ($vacaciones as $vacacion) {
        echo '<tr>';
        $nombreCompleto = obtenerNombreCompletoOperario([
            'Nombre' => $vacacion['operario_nombre'],
            'Nombre2' => $vacacion['operario_nombre2'] ?? '',
            'Apellido' => $vacacion['operario_apellido'],
            'Apellido2' => $vacacion['operario_apellido2'] ?? ''
        ]);
        
        // COLUMNA PERSONA MODIFICADA: código contrato + nombre completo
        $persona = ($vacacion['cod_contrato'] ?? '') . ' ' . htmlspecialchars($nombreCompleto);
        
        // FECHA REGISTRO CON AJUSTE DE -6 HORAS
        $fechaRegistro = '';
        if (!empty($vacacion['fecha_registro'])) {
            $fechaObj = new DateTime($vacacion['fecha_registro']);
            $fechaObj->modify('-6 hours');
            $fechaRegistro = $fechaObj->format('Y-m-d');
        }
        
        echo '<td>' . ($vacacion['cod_contrato'] ?? '') . '</td>';
        echo '<td>' . $persona . '</td>';
        echo '<td>' . htmlspecialchars($vacacion['sucursal_nombre']) . '</td>';
        echo '<td>' . $vacacion['fecha_falta'] . '</td>';
        echo '<td>' . $vacacion['fecha_falta'] . '</td>';
        echo '<td>1</td>';
        $obsDisplay = !empty($vacacion['observaciones_rrhh']) ? $vacacion['observaciones_rrhh'] : $vacacion['observaciones'];
        echo '<td>' . ($obsDisplay ? htmlspecialchars($obsDisplay) : 'Sin comentarios') . '</td>';
        echo '<td>Descansadas</td>';
        echo '<td>' . $fechaRegistro . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

/**
 * Obtiene todas las faltas automáticas (detectadas por el sistema) para el reporte de contabilidad - SIN FILTRO Operativo
 * ACTUALIZADA: Respeta correctamente el rango de fechas
 */
function obtenerFaltasAutomaticasParaContabilidad($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    // 1. Obtener todos los operarios asignados a la sucursal en el rango de fechas - SIN FILTRO Operativo
    $sqlOperarios = "
        SELECT DISTINCT o.CodOperario, 
               o.Nombre as operario_nombre, 
               o.Nombre2 as operario_nombre2,
               o.Apellido as operario_apellido,
               o.Apellido2 as operario_apellido2, 
               s.nombre as sucursal_nombre,
               anc.Sucursal as cod_sucursal
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        JOIN sucursales s ON anc.Sucursal = s.codigo
        WHERE (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
    ";
    
    $params = [$fechaDesde, $fechaHasta];
    
    // CORRECCIÓN: Si no es 'todas' y no está vacío, filtrar por sucursal
    if (!empty($codSucursal) && $codSucursal !== 'todas') {
        $sqlOperarios .= " AND anc.Sucursal = ?";
        $params[] = $codSucursal;
    }
    
    $sqlOperarios .= " ORDER BY o.Nombre, o.Apellido";
    
    $stmt = $conn->prepare($sqlOperarios);
    $stmt->execute($params);
    $operarios = $stmt->fetchAll();
    
    $faltas = [];
    
    foreach ($operarios as $operario) {
        // OBTENER CÓDIGO DE CONTRATO - CONSULTA DIRECTA
        $stmt_contrato = $conn->prepare("
            SELECT CodContrato 
            FROM Contratos 
            WHERE cod_operario = ? 
            ORDER BY inicio_contrato DESC, CodContrato DESC 
            LIMIT 1
        ");
        $stmt_contrato->execute([$operario['CodOperario']]);
        $contrato = $stmt_contrato->fetch();
        $cod_contrato = $contrato ? $contrato['CodContrato'] : null;
        
        // 2. Para cada operario, verificar días laborables en el rango
        $diasLaborables = obtenerDiasLaborablesOperario(
            $operario['CodOperario'], 
            $operario['cod_sucursal'],
            $fechaDesde, 
            $fechaHasta
        );
        
        foreach ($diasLaborables as $dia) {
            // 3. Verificar si NO hay marcación de entrada para ese día
            $marcacion = obtenerMarcacionEntrada($operario['CodOperario'], $dia['fecha']);
            
            if (!$marcacion) {
                $faltas[] = [
                    'cod_operario' => $operario['CodOperario'],
                    'operario_nombre' => $operario['operario_nombre'],
                    'operario_nombre2' => $operario['operario_nombre2'] ?? '',
                    'operario_apellido' => $operario['operario_apellido'],
                    'operario_apellido2' => $operario['operario_apellido2'] ?? '',
                    'sucursal_nombre' => $operario['sucursal_nombre'],
                    'fecha_falta' => $dia['fecha'],
                    'hora_entrada_programada' => $dia['hora_entrada'],
                    'cod_contrato' => $cod_contrato,
                    'fecha_registro' => $dia['fecha'] // Para faltas automáticas, usar la fecha de falta
                ];
            }
        }
    }
    
    return $faltas;
}

/**
 * Función auxiliar para obtener días laborables de un operario
 */
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

/**
 * Función auxiliar para verificar marcación de entrada
 */
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

/**
 * Exportar tardanzas - RESPETANDO RANGO
 * BASADO EN LA FUNCIÓN DE ver_marcaciones_todas.php
 */
function exportarTardanzas($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    try {
        // Obtener todos los códigos de contrato únicos con sus operarios
        $sqlContratos = "SELECT DISTINCT 
                tm.cod_contrato,
                tm.cod_operario,
                o.Operativo,
                CONCAT(
                    IFNULL(o.Nombre, ''), ' ', 
                    IFNULL(o.Nombre2, ''), ' ', 
                    IFNULL(o.Apellido, ''), ' ', 
                    IFNULL(o.Apellido2, '')
                ) AS nombre_completo,
                COALESCE(
                    (SELECT s.nombre 
                     FROM AsignacionNivelesCargos anc 
                     JOIN sucursales s ON anc.Sucursal = s.codigo 
                     WHERE anc.CodOperario = tm.cod_operario 
                     AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                     ORDER BY anc.Fecha DESC 
                     LIMIT 1),
                    s.nombre,
                    'Sin asignar'
                ) AS nombre_sucursal,
                COALESCE(
                    (SELECT anc.Sucursal 
                     FROM AsignacionNivelesCargos anc 
                     WHERE anc.CodOperario = tm.cod_operario 
                     AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                     ORDER BY anc.Fecha DESC 
                     LIMIT 1),
                    o.Sucursal
                ) AS codigo_sucursal
            FROM TardanzasManuales tm
            JOIN Operarios o ON tm.cod_operario = o.CodOperario
            LEFT JOIN sucursales s ON o.Sucursal = s.codigo
            WHERE tm.fecha_tardanza BETWEEN ? AND ?
            AND (o.CodOperario NOT IN (
                SELECT CodOperario FROM AsignacionNivelesCargos 
                WHERE CodNivelesCargos = 27 AND (Fin IS NULL OR Fin >= CURDATE())
            ) OR o.CodOperario NOT IN (
                SELECT CodOperario FROM AsignacionNivelesCargos 
                WHERE CodNivelesCargos = 27
            ))";
        
        $params = [$fechaDesde, $fechaHasta];
        
        // Aplicar filtros según modo de vista
        if (!empty($codSucursal) && $codSucursal !== 'todas') {
            $sqlContratos .= " AND (COALESCE(
                (SELECT anc.Sucursal 
                 FROM AsignacionNivelesCargos anc 
                 WHERE anc.CodOperario = tm.cod_operario 
                 AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                 ORDER BY anc.Fecha DESC 
                 LIMIT 1),
                o.Sucursal
            ) = ?)";
            $params[] = $codSucursal;
        }
        
        $sqlContratos .= " GROUP BY tm.cod_contrato, tm.cod_operario 
                          ORDER BY nombre_completo, tm.cod_contrato";
        
        $stmt = $conn->prepare($sqlContratos);
        $stmt->execute($params);
        $contratos = $stmt->fetchAll();
        
        // Configurar headers para descarga de archivo Excel CON UTF-8 y rango de fechas
        $nombreArchivo = "tardanzas_{$fechaDesde}_a_{$fechaHasta}.xls";
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
        echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        echo '</head>';
        echo '<body>';
        
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Código</th>';
        echo '<th>Persona</th>';
        echo '<th>Sucursal</th>';
        echo '<th>Fecha Pago</th>';
        echo '<th>Tardanzas</th>';
        echo '<th>Tardanzas Justificadas</th>';
        echo '</tr>';
        
        foreach ($contratos as $contrato) {
            $codContrato = $contrato['cod_contrato'];
            $codOperario = $contrato['cod_operario'];
            
            // TOTAL TARDANZAS: Consulta por código de contrato
            $sqlTotalTardanzas = "SELECT COUNT(*) as total 
                                 FROM marcaciones m
                                 JOIN TardanzasManuales tm ON m.CodOperario = tm.cod_operario 
                                     AND m.fecha = tm.fecha_tardanza
                                 WHERE m.CodOperario = ?
                                 AND tm.cod_contrato = ?
                                 AND m.fecha BETWEEN ? AND ?
                                 AND m.hora_ingreso IS NOT NULL
                                 AND EXISTS (
                                     SELECT 1 FROM HorariosSemanalesOperaciones h
                                     JOIN SemanasSistema ss ON h.id_semana_sistema = ss.id
                                     WHERE h.cod_operario = m.CodOperario
                                     AND h.cod_sucursal = m.sucursal_codigo
                                     AND m.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
                                     AND TIMESTAMPDIFF(MINUTE, 
                                         CASE DAYOFWEEK(m.fecha)
                                             WHEN 2 THEN h.lunes_entrada
                                             WHEN 3 THEN h.martes_entrada
                                             WHEN 4 THEN h.miercoles_entrada
                                             WHEN 5 THEN h.jueves_entrada
                                             WHEN 6 THEN h.viernes_entrada
                                             WHEN 7 THEN h.sabado_entrada
                                             WHEN 1 THEN h.domingo_entrada
                                         END,
                                         m.hora_ingreso
                                     ) > 1
                                 )";
            
            $stmtTotal = $conn->prepare($sqlTotalTardanzas);
            $stmtTotal->execute([$codOperario, $codContrato, $fechaDesde, $fechaHasta]);
            $totalTardanzas = $stmtTotal->fetch()['total'] ?? 0;
            
            // TARDANZAS JUSTIFICADAS: Tardanzas manuales con estado "Justificado" para este contrato
            $sqlTardanzasJustificadas = "SELECT COUNT(*) as total
                                        FROM TardanzasManuales 
                                        WHERE cod_operario = ? 
                                        AND cod_contrato = ?
                                        AND fecha_tardanza BETWEEN ? AND ?
                                        AND estado = 'Justificado'";
            
            $stmtJustificadas = $conn->prepare($sqlTardanzasJustificadas);
            $stmtJustificadas->execute([$codOperario, $codContrato, $fechaDesde, $fechaHasta]);
            $tardanzasJustificadas = $stmtJustificadas->fetch()['total'] ?? 0;
            
            // TARDANZAS EJECUTADAS: Total - Justificadas
            $tardanzasEjecutadas = max(0, $totalTardanzas - $tardanzasJustificadas);
            
            // COLUMNA PERSONA: código contrato + nombre completo
            $persona = $codContrato . ' ' . htmlspecialchars($contrato['nombre_completo'], ENT_QUOTES, 'UTF-8');
            
            echo '<tr>';
            echo '<td>' . $codContrato . '</td>';
            echo '<td>' . $persona . '</td>';
            echo '<td>' . htmlspecialchars($contrato['nombre_sucursal'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td></td>'; // Fecha Pago (vacío)
            echo '<td>' . $tardanzasEjecutadas . '</td>';
            echo '<td>' . $tardanzasJustificadas . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</body>';
        echo '</html>';
        exit;
        
    } catch (Exception $e) {
        error_log("Error en exportarTardanzas: " . $e->getMessage());
        die("Error al generar el reporte de tardanzas");
    }
}

/**
 * Exportar feriados trabajados - RESPETANDO RANGO
 * BASADO EN LA FUNCIÓN DE feriados.php (operaciones)
 */
function exportarFeriados($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    try {
        // Obtener feriados trabajados - misma lógica que en feriados.php
        $feriadosTrabajados = obtenerFeriadosTrabajadosParaContabilidad('todas', $fechaDesde, $fechaHasta, null);
        
        // Filtrar solo los feriados con estado "Pagado" (como en el archivo original)
        $feriadosPagados = array_filter($feriadosTrabajados, function($ft) {
            return $ft['estado'] === 'Pagado';
        });
        
        // Configurar headers para descarga de archivo Excel CON UTF-8 y rango de fechas
        $nombreArchivo = "feriados_trabajados_{$fechaDesde}_a_{$fechaHasta}.xls";
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
        echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        echo '</head>';
        echo '<body>';
        
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Código</th>';
        echo '<th>Persona</th>';
        echo '<th>Sucursal</th>';
        echo '<th>Fecha</th>';
        echo '<th>HORAS LABORADAS EN FERIADOS</th>';
        echo '<th>Observaciones</th>';
        echo '<th>Fecha Registro</th>';
        echo '</tr>';
        
        foreach ($feriadosPagados as $ft) {
            echo '<tr>';
            echo '<td>' . ($ft['cod_contrato'] ?? '') . '</td>';
            
            // COLUMNA PERSONA: código contrato + nombre completo (como en feriados.php)
            echo '<td>';
            if (!empty($ft['cod_contrato'])) {
                echo htmlspecialchars($ft['cod_contrato'] . ' ' . $ft['nombre_operario'], ENT_QUOTES, 'UTF-8');
            } else {
                echo htmlspecialchars($ft['nombre_operario'], ENT_QUOTES, 'UTF-8');
            }
            echo '</td>';
            
            echo '<td>' . htmlspecialchars($ft['sucursal_nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . formatoFecha($ft['fecha']) . '</td>';
            echo '<td>8.00</td>'; // Siempre 8 horas por feriado trabajado
            
            // Observaciones: nombre del feriado y tipo con departamento
            $observacionCompleta = $ft['feriado_nombre'] . ' (' . $ft['feriado_tipo'];
            if ($ft['feriado_tipo'] === 'Departamental') {
                $observacionCompleta .= ' - ' . $ft['departamento_nombre'];
            }
            $observacionCompleta .= ')';
            echo '<td>' . htmlspecialchars($observacionCompleta, ENT_QUOTES, 'UTF-8') . '</td>';
            
            // FECHA REGISTRO
            echo '<td>';
            if (!empty($ft['fecha_creacion'])) {
                echo formatoFecha($ft['fecha_creacion']);
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</body>';
        echo '</html>';
        exit;
        
    } catch (Exception $e) {
        error_log("Error en exportarFeriados: " . $e->getMessage());
        die("Error al generar el reporte de feriados");
    }
}

/**
 * Función para obtener feriados trabajados (adaptada para contabilidad)
 * Incluye todas las sucursales y todos los operarios
 */
function obtenerFeriadosTrabajadosParaContabilidad($codSucursal, $fechaDesde, $fechaHasta, $codOperario = null) {
    global $conn;
    
    try {
        // Obtener todas las marcaciones en el rango de fechas
        $sqlMarcaciones = "
            SELECT m.id, m.CodOperario, m.fecha, 
                   m.hora_ingreso, m.hora_salida, m.sucursal_codigo,
                   s.nombre as sucursal_nombre, s.cod_departamento,
                   d.nombre as nombre_departamento
            FROM marcaciones m
            JOIN sucursales s ON m.sucursal_codigo = s.codigo
            JOIN departamentos d ON s.cod_departamento = d.codigo
            WHERE m.fecha BETWEEN ? AND ?
            AND s.activa = 1
        ";
        
        $params = [$fechaDesde, $fechaHasta];
        
        $sqlMarcaciones .= " ORDER BY m.fecha DESC, m.hora_ingreso DESC";
        
        $stmt = $conn->prepare($sqlMarcaciones);
        $stmt->execute($params);
        $marcaciones = $stmt->fetchAll();
        
        $resultados = [];
        
        foreach ($marcaciones as $marcacion) {
            // Obtener datos completos del operario
            $datosOperario = obtenerDatosCompletosOperario($marcacion['CodOperario']);
            $nombreCompleto = trim(
                ($datosOperario['Nombre'] ?? '') . ' ' . 
                ($datosOperario['Apellido'] ?? '') . ' ' . 
                ($datosOperario['Apellido2'] ?? '')
            );
            
            // VERIFICAR SI LA FECHA DE MARCACIÓN ES FERIADO PARA ESTE DEPARTAMENTO
            $sqlFeriados = "
                SELECT f.id, f.nombre, f.tipo, f.departamento_codigo, 
                       COALESCE(dep.nombre, 'Nacional') as nombre_departamento
                FROM feriadosnic f
                LEFT JOIN departamentos dep ON f.departamento_codigo = dep.codigo
                WHERE f.fecha = ?
                AND (
                    -- Feriados Nacionales (aplican a todos los departamentos)
                    f.tipo = 'Nacional' 
                    OR 
                    -- Feriados Departamentales (solo aplican al departamento específico)
                    (f.tipo = 'Departamental' AND f.departamento_codigo = ?)
                )
            ";
            
            $stmtFeriados = $conn->prepare($sqlFeriados);
            $stmtFeriados->execute([
                $marcacion['fecha'], 
                $marcacion['cod_departamento'] // Departamento de la sucursal donde trabajó
            ]);
            $feriados = $stmtFeriados->fetchAll();
            
            if (!empty($feriados)) {
                // Calcular horas trabajadas
                $horasTrabajadas = 0;
                if ($marcacion['hora_ingreso'] && $marcacion['hora_salida']) {
                    $entrada = new DateTime($marcacion['hora_ingreso']);
                    $salida = new DateTime($marcacion['hora_salida']);
                    $diferencia = $salida->diff($entrada);
                    $horasTrabajadas = $diferencia->h + ($diferencia->i / 60);
                }
                
                // Obtener estado del feriado trabajado y código de contrato
                $estadoFeriado = obtenerEstadoFeriadoTrabajadoContabilidad($marcacion['id']);
                
                foreach ($feriados as $feriado) {
                    // Determinar el nombre del departamento para mostrar
                    $departamentoMostrar = $feriado['tipo'] === 'Nacional' 
                        ? 'Nacional' 
                        : $feriado['nombre_departamento'];
                    
                    $resultados[] = [
                        'id_marcacion' => $marcacion['id'],
                        'cod_operario' => $marcacion['CodOperario'],
                        'nombre_operario' => $nombreCompleto,
                        'fecha' => $marcacion['fecha'],
                        'sucursal_codigo' => $marcacion['sucursal_codigo'],
                        'sucursal_nombre' => $marcacion['sucursal_nombre'],
                        'sucursal_departamento' => $marcacion['nombre_departamento'],
                        'sucursal_cod_departamento' => $marcacion['cod_departamento'],
                        'hora_entrada' => $marcacion['hora_ingreso'],
                        'hora_salida' => $marcacion['hora_salida'],
                        'horas_trabajadas' => $horasTrabajadas,
                        'feriado_id' => $feriado['id'],
                        'feriado_nombre' => $feriado['nombre'],
                        'feriado_tipo' => $feriado['tipo'],
                        'departamento_nombre' => $departamentoMostrar,
                        'feriado_departamento_codigo' => $feriado['departamento_codigo'],
                        'estado' => $estadoFeriado['estado'] ?? 'Pendiente',
                        'observaciones' => $estadoFeriado['observaciones'] ?? null,
                        'id_aprobacion' => $estadoFeriado['id'] ?? null,
                        'cod_contrato' => $estadoFeriado['cod_contrato'] ?? null,
                        'fecha_creacion' => $estadoFeriado['fecha_creacion'] ?? null
                    ];
                }
            }
        }
        
        return $resultados;
        
    } catch (PDOException $e) {
        error_log("Error al obtener feriados trabajados: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener estado del feriado trabajado con código de contrato
 */
function obtenerEstadoFeriadoTrabajadoContabilidad($idMarcacion) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT id, estado, observaciones, horas_trabajadas, cod_contrato, fecha_creacion 
        FROM FeriadosStatus 
        WHERE id_marcacion = ?
        LIMIT 1
    ");
    $stmt->execute([$idMarcacion]);
    return $stmt->fetch();
}

/**
 * Exportar horas extras manuales - RESPETANDO RANGO
 * BASADO EN LA FUNCIÓN DE horas_extras_manual.php (operaciones)
 */
function exportarHorasExtras($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    try {
        // Obtener horas extras manuales - misma lógica que en horas_extras_manual.php
        $horasExtrasManuales = obtenerHorasExtrasManualesParaContabilidad('todas', $fechaDesde, $fechaHasta, null);
        
        // Configurar headers para descarga de archivo Excel CON UTF-8 y rango de fechas
        $nombreArchivo = "horas_extras_{$fechaDesde}_a_{$fechaHasta}.xls";
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
        echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        echo '</head>';
        echo '<body>';
        
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Código</th>';
        echo '<th>Persona</th>';
        echo '<th>Sucursal</th>';
        echo '<th>Fecha</th>';
        echo '<th>HORAS EXTRAS AUTORIZADAS</th>';
        echo '<th>Observaciones</th>';
        echo '<th>Fecha Registro</th>';
        echo '</tr>';
        
        foreach ($horasExtrasManuales as $hem) {
            // COLUMNA PERSONA: código contrato + nombre completo (como en horas_extras_manual.php)
            $nombreCompleto = trim(
                $hem['cod_contrato'] . ' ' . 
                $hem['operario_nombre'] . ' ' . 
                ($hem['operario_nombre2'] ?? '') . ' ' . 
                $hem['operario_apellido'] . ' ' . 
                ($hem['operario_apellido2'] ?? '')
            );
            
            echo '<tr>';
            echo '<td>' . ($hem['cod_contrato'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($hem['sucursal_nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . formatoFecha($hem['fecha']) . '</td>';
            echo '<td>' . number_format($hem['horas_extras'], 2) . '</td>';
            echo '<td>' . ($hem['observaciones'] ? htmlspecialchars($hem['observaciones'], ENT_QUOTES, 'UTF-8') : '-') . '</td>';
            echo '<td>' . formatoFecha($hem['fecha_registro']) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</body>';
        echo '</html>';
        exit;
        
    } catch (Exception $e) {
        error_log("Error en exportarHorasExtras: " . $e->getMessage());
        die("Error al generar el reporte de horas extras");
    }
}

/**
 * Función para obtener horas extras manuales (adaptada para contabilidad)
 * Incluye todas las sucursales y todos los operarios
 */
function obtenerHorasExtrasManualesParaContabilidad($codSucursal, $fechaDesde, $fechaHasta, $codOperario = null) {
    global $conn;
    
    try {
        // Preparar la consulta base - ADAPTADA DE horas_extras_manual.php
        $sql = "
            SELECT hem.*, 
                   o.Nombre AS operario_nombre, 
                   o.Nombre2 AS operario_nombre2,
                   o.Apellido AS operario_apellido,
                   o.Apellido2 AS operario_apellido2,
                   s.nombre AS sucursal_nombre,
                   r.Nombre AS registrador_nombre,
                   r.Apellido AS registrador_apellido,
                   hem.cod_contrato,
                   hem.fecha_registro
            FROM horas_extras_manual hem
            JOIN Operarios o ON hem.cod_operario = o.CodOperario
            JOIN sucursales s ON hem.cod_sucursal = s.codigo
            JOIN Operarios r ON hem.registrado_por = r.CodOperario
            WHERE hem.fecha BETWEEN ? AND ?
            AND hem.estado = 'Aprobado'  -- Solo horas extras aprobadas
        ";
        
        // Parámetros para la consulta
        $params = [$fechaDesde, $fechaHasta];
        
        // Agregar condición de sucursal si no es "Todas"
        if (!empty($codSucursal) && $codSucursal !== 'todas') {
            $sql .= " AND hem.cod_sucursal = ?";
            $params[] = $codSucursal;
        }
        
        // Agregar condición de operario si se seleccionó uno
        if (!empty($codOperario)) {
            $sql .= " AND hem.cod_operario = ?";
            $params[] = $codOperario;
        }
        
        // Ordenar los resultados
        $sql .= " ORDER BY hem.fecha DESC, o.Nombre, o.Apellido";
        
        // Preparar y ejecutar la consulta
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error al obtener horas extras manuales: " . $e->getMessage());
        return [];
    }
}

// Procesar exportaciones
if (isset($_GET['exportar_faltas_auto_septimo'])) {
    exportarFaltasAutoSeptimo('todas', $fechaDesde, $fechaHasta);
}

if (isset($_GET['exportar_permisos'])) {
    exportarPermisos('todas', $fechaDesde, $fechaHasta);
}

if (isset($_GET['exportar_vacaciones'])) {
    exportarVacaciones('todas', $fechaDesde, $fechaHasta);
}

if (isset($_GET['exportar_tardanzas'])) {
    exportarTardanzas('todas', $fechaDesde, $fechaHasta);
}

if (isset($_GET['exportar_feriados'])) {
    exportarFeriados('todas', $fechaDesde, $fechaHasta);
}

if (isset($_GET['exportar_horas_extras'])) {
    exportarHorasExtras('todas', $fechaDesde, $fechaHasta);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar a Excel</title>
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
            padding: 5px;
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

        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .filtros-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .filtros-form {
            display: flex;
            justify-content: center;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filtro-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }
        
        .filtro-group label {
            margin-bottom: 5px;
            text-align: left;
            font-weight: bold;
            color: #0E544C;
        }
        
        .filtro-group input {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ddd;
            width: 100%;
        }
        
        .btn-aplicar {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-aplicar:hover {
            background-color: #0E544C;
        }
        
        .exportaciones-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        
        .exportacion-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #51B8AC;
        }
        
        .exportacion-info h3 {
            color: #0E544C;
            margin-bottom: 5px;
        }
        
        .exportacion-info p {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .btn-exportar {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }
        
        .btn-exportar:hover {
            background-color: #218838;
        }
        
        .btn-exportar.warning {
            background-color: #ffc107;
            color: #000;
        }
        
        .btn-exportar.warning:hover {
            background-color: #e0a800;
        }
        
        .btn-exportar.info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-exportar.info:hover {
            background-color: #138496;
        }
        
        .info-text {
            text-align: center;
            color: #6c757d;
            margin-top: 10px;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .filtros-form {
                flex-direction: column;
                align-items: center;
            }
            
            .filtro-group {
                width: 100%;
                max-width: 300px;
            }
            
            .exportacion-item {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .buttons-container {
                position: static;
                transform: none;
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../../core/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <a href="exportar_excel.php" class="btn-agregar activo">
                        <i class="fas fa-file-excel"></i> <span class="btn-text">Exportar a Excel</span>
                    </a>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?= false ? 
                            strtoupper(substr($usuario['nombre'], 0, 1)) : 
                            strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div>
                            <?= false ? 
                                htmlspecialchars($usuario['nombre']) : 
                                htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                        </div>
                        <small>
                            <?= htmlspecialchars($cargoUsuario) ?>
                        </small>
                    </div>
                    <a href="../../../index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <h1 class="title" style="display:none;">Exportar a Excel</h1>
        
        <!-- Filtros -->
        <div class="filtros-container">
            <form method="get" action="exportar_excel.php" class="filtros-form">
                <div class="filtro-group">
                    <label for="desde">Desde</label>
                    <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($fechaDesde) ?>" required>
                </div>
                
                <div class="filtro-group">
                    <label for="hasta">Hasta</label>
                    <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($fechaHasta) ?>" required>
                </div>
                
                <div class="filtro-group">
                    <button type="submit" class="btn-aplicar">
                        <i class="fas fa-search"></i> Aplicar Filtros
                    </button>
                </div>
            </form>
            <div class="info-text" style="display:none;">
                <i class="fas fa-info-circle"></i> 
                Todas las exportaciones incluyen <strong>todas las sucursales</strong> y <strong>todos los colaboradores</strong> (activos e inactivos) en el rango de fechas seleccionado
            </div>
        </div>
        
        <!-- Grid de exportaciones -->
        <div class="exportaciones-grid">
            <!-- Fila 1: No Reportadas + 7mo -->
            <div class="exportacion-item">
                <div class="exportacion-info">
                    <h3>No Reportadas + 7mo Día</h3>
                    <p>Incluye faltas automáticas no justificadas y días + séptimo reportados</p>
                    <small>Periodo: <?= formatoFechaCorta($fechaDesde) ?> - <?= formatoFechaCorta($fechaHasta) ?></small>
                </div>
                <a href="exportar_excel.php?<?= http_build_query([
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                    'exportar_faltas_auto_septimo' => 1
                ]) ?>" class="btn-exportar warning">
                    <i class="fas fa-file-excel"></i>
                </a>
            </div>
            
            <!-- Fila 2: Permisos -->
            <div class="exportacion-item">
                <div class="exportacion-info">
                    <h3>Permisos</h3>
                    <p>Todos los tipos de permisos excepto vacaciones y día + séptimo</p>
                    <small>Periodo: <?= formatoFechaCorta($fechaDesde) ?> - <?= formatoFechaCorta($fechaHasta) ?></small>
                </div>
                <a href="exportar_excel.php?<?= http_build_query([
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                    'exportar_permisos' => 1
                ]) ?>" class="btn-exportar info">
                    <i class="fas fa-file-excel"></i>
                </a>
            </div>
            
            <!-- Fila 3: Vacaciones -->
            <div class="exportacion-item">
                <div class="exportacion-info">
                    <h3>Vacaciones</h3>
                    <p>Días de vacaciones tomados por los colaboradores</p>
                    <small>Periodo: <?= formatoFechaCorta($fechaDesde) ?> - <?= formatoFechaCorta($fechaHasta) ?></small>
                </div>
                <a href="exportar_excel.php?<?= http_build_query([
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                    'exportar_vacaciones' => 1
                ]) ?>" class="btn-exportar">
                    <i class="fas fa-file-excel"></i>
                </a>
            </div>
            
            <!-- Fila 4: TARDANZAS -->
            <div class="exportacion-item">
                <div class="exportacion-info">
                    <h3>Tardanzas</h3>
                    <p>Tardanzas ejecutadas y tardanzas justificadas</p>
                    <small>Periodo: <?= formatoFechaCorta($fechaDesde) ?> - <?= formatoFechaCorta($fechaHasta) ?></small>
                </div>
                <a href="exportar_excel.php?<?= http_build_query([
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                    'exportar_tardanzas' => 1
                ]) ?>" class="btn-exportar" style="background-color: #ffc107; color: #000;">
                    <i class="fas fa-file-excel"></i>
                </a>
            </div>
            
            <!-- Fila 5: FERIADOS -->
            <div class="exportacion-item">
                <div class="exportacion-info">
                    <h3>Feriados Trabajados</h3>
                    <p>Días feriados en los que se trabajó (solo estado "Pagado")</p>
                    <small>Periodo: <?= formatoFechaCorta($fechaDesde) ?> - <?= formatoFechaCorta($fechaHasta) ?></small>
                </div>
                <a href="exportar_excel.php?<?= http_build_query([
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                    'exportar_feriados' => 1
                ]) ?>" class="btn-exportar" style="background-color: #6f42c1; color: white;">
                    <i class="fas fa-file-excel"></i>
                </a>
            </div>
            
            <!-- Fila 6: HORAS EXTRAS (NUEVA) -->
            <div class="exportacion-item">
                <div class="exportacion-info">
                    <h3>Horas Extras</h3>
                    <p>Horas extras manuales autorizadas (solo estado "Aprobado")</p>
                    <small>Periodo: <?= formatoFechaCorta($fechaDesde) ?> - <?= formatoFechaCorta($fechaHasta) ?></small>
                </div>
                <a href="exportar_excel.php?<?= http_build_query([
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                    'exportar_horas_extras' => 1
                ]) ?>" class="btn-exportar" style="background-color: #28a745; color: white;">
                    <i class="fas fa-file-excel"></i>
                </a>
            </div>
        </div>
        
        <!-- Información adicional -->
        <div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border-radius: 8px; border-left: 4px solid #17a2b8; display:none;">
            <h4 style="color: #0E544C; margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Información Importante</h4>
            <ul style="color: #6c757d; padding-left: 20px;">
                <li>Todas las exportaciones incluyen <strong>todas las sucursales</strong> del sistema</li>
                <li>Se incluyen <strong>todos los colaboradores</strong> (activos e inactivos) que tuvieron registros en el periodo seleccionado</li>
                <li>Los archivos se generan en formato Excel (.xls) listos para descargar</li>
                <li>Las fechas se ajustan automáticamente al rango seleccionado</li>
                <li>No es posible filtrar por sucursal o colaborador específico en esta página</li>
            </ul>
        </div>
    </div>
    
    <script>
        // Validación básica de fechas en el cliente
        document.querySelector('form').addEventListener('submit', function(e) {
            const desde = document.getElementById('desde').value;
            const hasta = document.getElementById('hasta').value;
            
            if (!desde || !hasta) {
                e.preventDefault();
                alert('Por favor seleccione ambas fechas');
                return;
            }
            
            if (new Date(desde) > new Date(hasta)) {
                e.preventDefault();
                alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return;
            }
        });
        
        // Establecer fecha máxima como hoy
        document.getElementById('hasta').max = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>