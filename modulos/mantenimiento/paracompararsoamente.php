<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar conexión
if (!$conn) {
    die("Error de conexión a la base de datos");
}

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([5, 8, 13, 16]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([5, 8, 13, 16]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

$esLider = verificarAccesoCargo([5]);
$esRH = verificarAccesoCargo([13, 8]);

/**
 * Obtiene los tipos de falta con sus porcentajes
 */
function obtenerTiposFaltaConPorcentajes() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT codigo, nombre, porcentaje_pago, descripcion 
        FROM tipos_falta 
        WHERE activo = 1 
        ORDER BY nombre
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Obtiene el porcentaje de pago para un tipo de falta específico
 */
function obtenerPorcentajePagoTipoFalta($tipoFalta) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT porcentaje_pago 
        FROM tipos_falta 
        WHERE codigo = ? 
        LIMIT 1
    ");
    $stmt->execute([$tipoFalta]);
    $result = $stmt->fetch();
    
    return $result ? $result['porcentaje_pago'] : 0;
}

/**
 * Obtiene TODAS las faltas manuales (para mostrar en columnas adicionales) ORDENADAS POR FECHA_FALTA
 */
function obtenerTodasFaltasManuales($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    $sql = "
        SELECT fm.cod_operario, fm.fecha_falta, fm.tipo_falta, fm.cod_contrato,
               fm.fecha_registro,
               o.Nombre as operario_nombre, o.Nombre2 as operario_nombre2,
               o.Apellido as operario_apellido, o.Apellido2 as operario_apellido2,
               s.nombre as sucursal_nombre
        FROM faltas_manual fm
        JOIN Operarios o ON fm.cod_operario = o.CodOperario
        JOIN sucursales s ON fm.cod_sucursal = s.codigo
        WHERE fm.fecha_falta BETWEEN ? AND ?
    ";
    
    $params = [$fechaDesde, $fechaHasta];

    // CORRECCIÓN: Solo agregar condición de sucursal si se proporciona un código válido
    if (!empty($codSucursal) && $codSucursal !== 'todas') {
        $sql .= " AND fm.cod_sucursal = ?";
        $params[] = $codSucursal;
    }
    
    $sql .= " ORDER BY fm.fecha_falta ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// Verificar si se solicitó la exportación a Excel para contabilidad
if (isset($_GET['exportar_contabilidad'])) {
    // Obtener parámetros de filtro
    $sucursalSeleccionada = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 month'));
    $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
    
    // Determinar modo de vista basado en la selección de sucursal
    $modoVista = ($sucursalSeleccionada === 'todas') ? 'todas' : 'sucursal';
    
    // 1. Obtener todas las faltas automáticas (detectadas por el sistema)
    $faltasAutomaticas = obtenerFaltasAutomaticasParaContabilidad(
        $sucursalSeleccionada,
        $fechaDesde, 
        $fechaHasta
    );
    
    // 2. Obtener TODAS las faltas manuales (para mostrar en columnas adicionales)
    $faltasReportadas = obtenerTodasFaltasManuales(
        $sucursalSeleccionada,
        $fechaDesde, 
        $fechaHasta
    );
    
    // 3. Calcular faltas por operario según las nuevas definiciones
    $faltasPorOperario = [];
    
    // Primero procesar todas las faltas automáticas
    foreach ($faltasAutomaticas as $fa) {
        $codOperario = $fa['cod_operario'];
        $nombreCompleto = obtenerNombreCompletoOperario([
            'Nombre' => $fa['operario_nombre'],
            'Nombre2' => $fa['operario_nombre2'] ?? '',
            'Apellido' => $fa['operario_apellido'] ?? '',
            'Apellido2' => $fa['operario_apellido2'] ?? ''
        ]);
        
        if (!isset($faltasPorOperario[$codOperario])) {
            $faltasPorOperario[$codOperario] = [
                'cod_operario' => $codOperario,
                'nombre_completo' => $nombreCompleto,
                'sucursal' => $fa['sucursal_nombre'],
                'cod_contrato' => $fa['cod_contrato'] ?? null,
                'total_faltas_automaticas' => 1,
                'total_faltas_reportadas' => 0,
                'total_faltas_justificadas' => 0,
                'faltas_ejecutadas' => 0
            ];
        } else {
            $faltasPorOperario[$codOperario]['total_faltas_automaticas']++;
        }
    }
    
    // 4. Procesar faltas manuales para calcular reportadas y justificadas
    foreach ($faltasReportadas as $fr) {
        $codOperario = $fr['cod_operario'];
        
        if (!isset($faltasPorOperario[$codOperario])) {
            $nombreCompleto = obtenerNombreCompletoOperario([
                'Nombre' => $fr['operario_nombre'],
                'Nombre2' => $fr['operario_nombre2'] ?? '',
                'Apellido' => $fr['operario_apellido'] ?? '',
                'Apellido2' => $fr['operario_apellido2'] ?? ''
            ]);
            
            $faltasPorOperario[$codOperario] = [
                'cod_operario' => $codOperario,
                'nombre_completo' => $nombreCompleto,
                'sucursal' => $fr['sucursal_nombre'],
                'cod_contrato' => $fr['cod_contrato'] ?? null,
                'total_faltas_automaticas' => 0,
                'total_faltas_reportadas' => 1,
                'total_faltas_justificadas' => 0,
                'faltas_ejecutadas' => 0
            ];
        } else {
            $faltasPorOperario[$codOperario]['total_faltas_reportadas']++;
        }
        
        // CONTAR FALTAS JUSTIFICADAS (todo lo que NO es "Pendiente" ni "No_Pagado")
        if ($fr['tipo_falta'] !== 'Pendiente' && $fr['tipo_falta'] !== 'No_Pagado') {
            $faltasPorOperario[$codOperario]['total_faltas_justificadas']++;
        }
    }
    
    // 5. Calcular faltas ejecutadas según la nueva fórmula
    foreach ($faltasPorOperario as $codOperario => $operarioData) {
        // NUEVA FÓRMULA: Faltas Ejecutadas = Faltas Automáticas - Faltas Justificadas
        $faltasPorOperario[$codOperario]['faltas_ejecutadas'] = 
            $faltasPorOperario[$codOperario]['total_faltas_automaticas'] - 
            $faltasPorOperario[$codOperario]['total_faltas_justificadas'];
        
        // Asegurar que no sea negativo
        if ($faltasPorOperario[$codOperario]['faltas_ejecutadas'] < 0) {
            $faltasPorOperario[$codOperario]['faltas_ejecutadas'] = 0;
        }
    }
    
    // Ordenar por nombre de operario
    usort($faltasPorOperario, function($a, $b) {
        return strcmp($a['nombre_completo'], $b['nombre_completo']);
    });
    
    // Configurar headers para descarga de archivo Excel con rango de fechas
    $nombreArchivo = "faltas_pendientes_contabilidad_{$fechaDesde}_a_{$fechaHasta}.xls";
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    
    // Iniciar salida - UNA SOLA FILA POR OPERARIO, SIN COLUMNAS DE DETALLE
    echo '<table border="1">';
    echo '<tr>';
    // echo '<th>Código</th>';
    echo '<th>Código</th>'; // NUEVA COLUMNA
    echo '<th>Persona</th>'; // Esta columna ahora incluirá código de contrato + nombre
    echo '<th>Sucursal</th>';
    echo '<th>Faltas Automaticas</th>';
    echo '<th>Faltas Reportadas</th>';
    echo '<th>Faltas Justificadas</th>';
    echo '<th>Faltas Ejecutadas</th>'; // Calculada como Reportadas - Justificadas
    echo '<th>Fecha Registro</th>'; // NUEVA COLUMNA
    echo '</tr>';
    
    foreach ($faltasPorOperario as $operario) {
        echo '<tr>';
        // echo '<td>' . $operario['cod_operario'] . '</td>';
        echo '<td>' . ($operario['cod_contrato'] ?? '') . '</td>'; // NUEVA COLUMNA
        // COLUMNA PERSONA MODIFICADA: código contrato + nombre completo
        $persona = ($operario['cod_contrato'] ?? '') . ' ' . htmlspecialchars($operario['nombre_completo']);
        echo '<td>' . $persona . '</td>';
        echo '<td>' . htmlspecialchars($operario['sucursal']) . '</td>';
        echo '<td>' . $operario['total_faltas_automaticas'] . '</td>';
        echo '<td>' . $operario['total_faltas_reportadas'] . '</td>';
        echo '<td>' . $operario['total_faltas_justificadas'] . '</td>';
        echo '<td>' . $operario['faltas_ejecutadas'] . '</td>';
        // FECHA REGISTRO CON AJUSTE DE -6 HORAS
        $fechaRegistro = '';
        if (!empty($operario['fecha_registro'])) {
            $fechaObj = new DateTime($operario['fecha_registro']);
            $fechaObj->modify('-6 hours');
            $fechaRegistro = $fechaObj->format('Y-m-d');
        }
        echo '<td>' . $fechaRegistro . '</td>';
        
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

// Verificar si se solicitó exportación de Faltas Auto + 7mo
if (isset($_GET['exportar_faltas_auto_septimo'])) {
    $sucursalSeleccionada = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 month'));
    $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
    
    exportarFaltasAutoSeptimo($sucursalSeleccionada, $fechaDesde, $fechaHasta);
}

// Verificar si se solicitó exportación de Permisos
if (isset($_GET['exportar_permisos'])) {
    $sucursalSeleccionada = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? date('Y-m-d', strrotime('-1 month'));
    $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
    
    exportarPermisos($sucursalSeleccionada, $fechaDesde, $fechaHasta);
}

// Verificar si se solicitó exportación de Vacaciones
if (isset($_GET['exportar_vacaciones'])) {
    $sucursalSeleccionada = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 month'));
    $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
    
    exportarVacaciones($sucursalSeleccionada, $fechaDesde, $fechaHasta);
}

/**
 * Obtiene todas las faltas automáticas (detectadas por el sistema) para el reporte de contabilidad
 */
function obtenerFaltasAutomaticasParaContabilidad($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    // 1. Obtener todos los operarios asignados a la sucursal en el rango de fechas
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
        WHERE o.Operativo = 1
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
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
 * Obtiene las faltas manuales reportadas como "No_Pagado" para restar de las automáticas
 */
function obtenerFaltasManualesReportadas($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    $sql = "
        SELECT fm.cod_operario, fm.fecha_falta,
               o.Nombre as operario_nombre, o.Apellido as operario_apellido,
               o.Apellido2 as operario_apellido2, 
               s.nombre as sucursal_nombre
        FROM faltas_manual fm
        JOIN Operarios o ON fm.cod_operario = o.CodOperario
        JOIN sucursales s ON fm.cod_sucursal = s.codigo
        WHERE fm.tipo_falta = 'No_Pagado'
        AND fm.fecha_falta BETWEEN ? AND ?
    ";
    
    $params = [$fechaDesde, $fechaHasta];
    
    if (!empty($codSucursal)) {
        $sql .= " AND fm.cod_sucursal = ?";
        $params[] = $codSucursal;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Exportar faltas automáticas + séptimo día (EXCLUYENDO LAS QUE YA TIENEN JUSTIFICACIÓN)
 */
function exportarFaltasAutoSeptimo($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    // 1. Obtener todas las faltas automáticas (detectadas por el sistema)
    $faltasAutomaticas = obtenerFaltasAutomaticasParaContabilidad($codSucursal, $fechaDesde, $fechaHasta);
    
    // 2. Obtener todas las faltas manuales que JUSTIFICAN faltas (excluyendo Pendiente y No_Pagado)
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
    
    // 5. Obtener faltas manuales de tipo Dia_mas_septimo Y Pendiente
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
    // echo '<th>Código</th>';
    echo '<th>Código</th>'; // NUEVA COLUMNA
    echo '<th>Persona</th>'; // Esta columna ahora incluirá código de contrato + nombre
    echo '<th>Sucursal</th>';
    echo '<th>Fecha</th>';
    echo '<th>Observaciones</th>';
    echo '<th>Fecha Registro</th>'; // NUEVA COLUMNA
    // echo '<th>Tipo</th>';
    // echo '<th>Origen</th>';
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
        
        // echo '<td>' . $falta['cod_operario'] . '</td>';
        echo '<td>' . ($falta['cod_contrato'] ?? '') . '</td>'; // NUEVA COLUMNA
        echo '<td>' . $persona . '</td>';
        echo '<td>' . htmlspecialchars($falta['sucursal_nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . $falta['fecha_falta'] . '</td>';
        echo '<td>No se presentó</td>';
        echo '<td>' . $fechaRegistro . '</td>'; // NUEVA COLUMNA
        // echo '<td>Sistema</td>';
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
        
        // echo '<td>' . $falta['cod_operario'] . '</td>';
        echo '<td>' . ($falta['cod_contrato'] ?? '') . '</td>'; // NUEVA COLUMNA
        echo '<td>' . $persona . '</td>';
        echo '<td>' . htmlspecialchars($falta['sucursal_nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . $falta['fecha_falta'] . '</td>';
        // echo '<td>' . (!empty($falta['observaciones_rrhh']) ? $falta['observaciones_rrhh'] : 'En revisión por rrhh') . '</td>';
        echo '<td>' . htmlspecialchars($tipoFalta, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . $fechaRegistro . '</td>'; // NUEVA COLUMNA
        // echo '<td>Manual</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

/**
 * Exportar permisos (todos los tipos excepto Vacaciones y Dia_mas_septimo)
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
    // echo '<th>Código</th>';
    echo '<th>Código</th>'; // NUEVA COLUMNA
    echo '<th>Persona</th>'; // Esta columna ahora incluirá código de contrato + nombre
    echo '<th>Sucursal</th>';
    echo '<th>Fecha</th>';
    echo '<th>Días</th>';
    echo '<th>% Salario a Pagar</th>';
    echo '<th>Tipo Permiso</th>';
    echo '<th>Observaciones</th>';
    echo '<th>Fecha Registro</th>'; // NUEVA COLUMNA
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
        
        // echo '<td>' . $permiso['cod_operario'] . '</td>';
        echo '<td>' . ($permiso['cod_contrato'] ?? '') . '</td>'; // NUEVA COLUMNA
        echo '<td>' . $persona . '</td>';
        echo '<td>' . htmlspecialchars($permiso['sucursal_nombre']) . '</td>';
        echo '<td>' . $permiso['fecha_falta'] . '</td>';
        echo '<td>' . 1 . '</td>';
        echo '<td>' . ($permiso['porcentaje_pago'] ?? 0) . '%</td>'; // PORCENTAJE DESDE BD
        echo '<td>' . str_replace('_', ' ', $permiso['tipo_falta']) . '</td>';
        echo '<td>' . (!empty($permiso['observaciones_rrhh']) ? htmlspecialchars($permiso['observaciones_rrhh']) : 'Sin comentarios por rrhh') . '</td>';
        echo '<td>' . $fechaRegistro . '</td>'; // NUEVA COLUMNA
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

/**
 * Exportar vacaciones
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
    // echo '<th>Código</th>';
    echo '<th>Código</th>'; // NUEVA COLUMNA
    echo '<th>Persona</th>'; // Esta columna ahora incluirá código de contrato + nombre
    echo '<th>Sucursal</th>';
    echo '<th>Fecha Inicio</th>';
    echo '<th>Fecha Fin</th>';
    echo '<th>Dias</th>';
    echo '<th>Observaciones</th>';
    echo '<th>Tipo</th>';
    echo '<th>Fecha Registro</th>'; // NUEVA COLUMNA
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
        
        // echo '<td>' . $vacacion['cod_operario'] . '</td>';
        echo '<td>' . ($vacacion['cod_contrato'] ?? '') . '</td>'; // NUEVA COLUMNA
        echo '<td>' . $persona . '</td>';
        echo '<td>' . htmlspecialchars($vacacion['sucursal_nombre']) . '</td>';
        echo '<td>' . $vacacion['fecha_falta'] . '</td>';
        echo '<td>' . $vacacion['fecha_falta'] . '</td>'; // Misma fecha para inicio y fin (día individual)
        echo '<td>1</td>';
        echo '<td>' . (!empty($vacacion['observaciones_rrhh']) ? htmlspecialchars($vacacion['observaciones_rrhh']) : 'Sin comentarios por rrhh') . '</td>';
        echo '<td>Descansadas</td>';
        echo '<td>' . $fechaRegistro . '</td>'; // NUEVA COLUMNA
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

// Verificar si se solicitó la exportación a Excel
if (isset($_GET['exportar_excel'])) {
    // Obtener parámetros de filtro
    $sucursalSeleccionada = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? $primerDiaMes;
    $fechaHasta = $_GET['hasta'] ?? $ultimoDiaMes;
    
    // Determinar modo de vista basado en la selección de sucursal
    $modoVista = ($sucursalSeleccionada === 'todas') ? 'todas' : 'sucursal';
    
    // Obtener los datos con los mismos filtros, excluyendo las de tipo "Pendiente"
    $faltasManuales = obtenerFaltasManuales(
        $sucursalSeleccionada, // Pasar el valor directamente
        $fechaDesde, 
        $fechaHasta, 
        $esRH, 
        $modoVista,
        true // Nuevo parámetro para excluir pendientes
    );
    
    // Obtener datos para calcular "Faltas Ejecutadas" (necesitamos los mismos datos que usa contabilidad)
    $faltasAutomaticas = obtenerFaltasAutomaticasParaContabilidad(
        ($modoVista === 'todas') ? null : $sucursalSeleccionada,
        $fechaDesde, 
        $fechaHasta
    );
    
    $faltasReportadas = obtenerTodasFaltasManuales(
        ($modoVista === 'todas') ? null : $sucursalSeleccionada,
        $fechaDesde, 
        $fechaHasta
    );
    
    // Calcular faltas por operario para obtener "Faltas Ejecutadas" - CORRECCIÓN
    $faltasPorOperario = [];
    
    // Procesar faltas automáticas (todas son no justificadas inicialmente)
    foreach ($faltasAutomaticas as $fa) {
        $codOperario = $fa['cod_operario'];
        
        if (!isset($faltasPorOperario[$codOperario])) {
            $faltasPorOperario[$codOperario] = [
                'total_faltas' => 1,
                'total_faltas_no_pagadas' => 0,
                'total_faltas_justificadas' => 0 // NUEVO: agregar contador de justificadas
            ];
        } else {
            $faltasPorOperario[$codOperario]['total_faltas']++;
        }
    }
    
    // Procesar faltas reportadas - SOLO CONTAR LAS DE TIPO "No_Pagado" COMO NO PAGADAS
    foreach ($faltasReportadas as $fr) {
        $codOperario = $fr['cod_operario'];
        
        if (!isset($faltasPorOperario[$codOperario])) {
            $faltasPorOperario[$codOperario] = [
                'total_faltas' => 0,
                'total_faltas_no_pagadas' => 0,
                'total_faltas_justificadas' => 0
            ];
        }
        
        // SOLO LAS FALTAS "No_Pagado" CUENTAN COMO NO PAGADAS
        if ($fr['tipo_falta'] === 'No_Pagado') {
            $faltasPorOperario[$codOperario]['total_faltas_no_pagadas']++;
        } else {
            // NUEVO: CONTAR FALTAS JUSTIFICADAS (todo lo que NO es No_Pagado)
            $faltasPorOperario[$codOperario]['total_faltas_justificadas']++;
        }
    }
    
    // Configurar headers para descarga de archivo Excel con rango de fechas
    $nombreArchivo = "faltas_manuales_{$fechaDesde}_a_{$fechaHasta}.xls";
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    
    // Iniciar salida
    echo '<table border="1">';
    echo '<tr>';
    // echo '<th>Código</th>';
    echo '<th>Código</th>';  // NUEVA COLUMNA
    echo '<th>Persona</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha Falta</th>';
    echo '<th>Dias</th>';
    echo '<th>Tipo Falta</th>';
    echo '<th>% Salario a Pagar</th>';
    echo '<th>Observaciones</th>';
    // COMENTAR LAS SIGUIENTES COLUMNAS
    // echo '<th>Registrado por</th>';
    // echo '<th>Fecha Registro</th>';
    echo '<th>Faltas Automaticas</th>'; // NUEVA COLUMNA
    echo '<th>Faltas No Pagadas</th>'; // NUEVA COLUMNA
    echo '<th>Faltas Justificadas</th>'; // NUEVA COLUMNA
    echo '<th>Faltas Ejecutadas</th>'; // NUEVA COLUMNA - CALCULADA CORRECTAMENTE
    echo '</tr>';
    
    foreach ($faltasManuales as $falta) {
        $codOperario = $falta['cod_operario'];
        $faltasEjecutadas = 0;
        $totalFaltasAuto = 0;
        $totalNoPagadas = 0;
        $totalJustificadas = 0;
        
        // Obtener los totales para este operario
        if (isset($faltasPorOperario[$codOperario])) {
            $totalFaltasAuto = $faltasPorOperario[$codOperario]['total_faltas'];
            $totalNoPagadas = $faltasPorOperario[$codOperario]['total_faltas_no_pagadas'];
            $totalJustificadas = $faltasPorOperario[$codOperario]['total_faltas_justificadas'];
            
            // CALCULAR FALTAS EJECUTADAS CON LA MISMA FÓRMULA QUE CONTABILIDAD
            // Faltas Ejecutadas = Total Faltas Automáticas - Faltas Justificadas
            $faltasEjecutadas = $totalFaltasAuto - $totalJustificadas;
            if ($faltasEjecutadas < 0) {
                $faltasEjecutadas = 0;
            }
        }
        
        echo '<tr>';
        $nombreCompleto = obtenerNombreCompletoOperario([
            'Nombre' => $falta['operario_nombre'],
            'Nombre2' => $falta['operario_nombre2'] ?? '',
            'Apellido' => $falta['operario_apellido'],
            'Apellido2' => $falta['operario_apellido2'] ?? ''
        ]);
        
        // echo '<td>' . $falta['cod_operario'] . '</td>';
        echo '<td>' . ($falta['cod_contrato'] ?? '') . '</td>';  // NUEVA COLUMNA
        
        echo '<td>' . htmlspecialchars($nombreCompleto) . '</td>';
        
        echo '<td>' . htmlspecialchars($falta['sucursal_nombre']) . '</td>';
        echo '<td>' . formatoFechaCorta($falta['fecha_falta']) . '</td>';
        echo '<td>1</td>'; // NUEVA COLUMNA - SIEMPRE 1
        echo '<td>' . str_replace(
            ['_', 'No_Pagado', 'Pendiente', 'Subsidio_3dias', 'Subsidio_INSS', 'Subsidio_maternidad', 'Reposo_hasta_3dias', 'Compensacion_feria', 'Compensacion_dia_trabajado', 'Cuido_materno'], 
            [' ', 'No Pagado', 'Pendiente', 'Subsidio (3 días)', 'Subsidio INSS', 'Subsidio maternidad', 'Reposo (3 días)', 'Compensación feria', 'Compensación día trabajado', 'Cuido materno'], 
            $falta['tipo_falta']
        ) . '</td>';
        // echo '<td></td>'; // % Salario a Pagar - VACÍO
        echo '<td>' . ($falta['porcentaje_pago'] ?? 0) . '%</td>';
        echo '<td>' . ($falta['observaciones'] ? htmlspecialchars($falta['observaciones']) : '-') . '</td>';
        // COMENTAR LAS SIGUIENTES COLUMNAS
        // echo '<td>' . htmlspecialchars($falta['registrador_nombre'] . ' ' . htmlspecialchars($falta['registrador_apellido'])) . '</td>';
        // echo '<td>' . formatoFechaCorta($falta['fecha_registro']) . '</td>';
        // NUEVAS COLUMNAS CON LOS TOTALES
        echo '<td>' . $totalFaltasAuto . '</td>';
        echo '<td>' . $totalNoPagadas . '</td>';
        echo '<td>' . $totalJustificadas . '</td>';
        echo '<td>' . $faltasEjecutadas . '</td>'; // FALTAS EJECUTADAS CALCULADAS CORRECTAMENTE
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

// Obtener sucursales según el cargo del usuario
if ($esRH) {
    // RH puede ver todas las sucursales
    $sucursales = obtenerTodasSucursales();
    // Agregar opción "Todas" al principio
    array_unshift($sucursales, ['codigo' => 'todas', 'nombre' => 'Todas las sucursales']);
} else {
    // Líder solo ve sus sucursales
    $sucursales = obtenerSucursalesLider($_SESSION['usuario_id']);
}

// Si el líder solo tiene una sucursal, seleccionarla automáticamente
if (count($sucursales) === 1 && !isset($_GET['sucursal'])) {
    $sucursalSeleccionada = $sucursales[0]['codigo'];
} else {
    $sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursales[0]['codigo'] ?? null);
}

// Procesar formulario de registro manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_falta'])) {
    procesarRegistroFaltaManual();
}

// Obtener datos para los filtros
$sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursales[0]['codigo'] ?? null);

// Establecer rango del mes actual por defecto
$hoy = new DateTime();
$primerDiaMes = $hoy->format('Y-m-01');
$ultimoDiaMes = $hoy->format('Y-m-t');

// Si es líder y tiene múltiples sucursales, seleccionar la primera por defecto
if (!$esRH && count($sucursales) > 0 && !isset($_GET['sucursal'])) {
    $sucursalSeleccionada = $sucursales[0]['codigo'];
}

// Obtener fechas desde los parámetros GET o usar el mes actual
$fechaDesde = $_GET['desde'] ?? $primerDiaMes;
$fechaHasta = $_GET['hasta'] ?? $ultimoDiaMes;

// Validar que las fechas no estén vacías
if (empty($fechaDesde)) $fechaDesde = $primerDiaMes;
if (empty($fechaHasta)) $fechaHasta = $ultimoDiaMes;

// Obtener operario seleccionado
$operarioSeleccionado = isset($_GET['operario']) ? intval($_GET['operario']) : 0;

// Obtener operarios para el filtro
$operarios = obtenerOperariosFiltro();

// Determinar modo de vista basado en la selección de sucursal
$modoVista = ($sucursalSeleccionada === 'todas') ? 'todas' : 'sucursal';

// Obtener faltas manuales si hay sucursal y fechas seleccionadas
$faltasManuales = [];
if (($sucursalSeleccionada || $modoVista === 'todas') && $fechaDesde && $fechaHasta) {
    $faltasManuales = obtenerFaltasManuales(
        ($modoVista === 'todas') ? null : $sucursalSeleccionada, 
        $fechaDesde, 
        $fechaHasta, 
        $esRH, 
        $modoVista,
        false, 
        $operarioSeleccionado
    );
}

// Función para obtener operarios para el filtro
function obtenerOperariosFiltro() {
    global $conn;
    
    $sql = "SELECT o.CodOperario, 
                   CONCAT(
                       IFNULL(o.Nombre, ''), ' ', 
                       IFNULL(o.Nombre2, ''), ' ', 
                       IFNULL(o.Apellido, ''), ' ', 
                       IFNULL(o.Apellido2, '')
                   ) AS nombre_completo 
            FROM Operarios o
            WHERE o.Operativo = 1
            AND o.CodOperario NOT IN (
                SELECT DISTINCT anc.CodOperario 
                FROM AsignacionNivelesCargos anc
                WHERE anc.CodNivelesCargos = 27
                AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            )
            GROUP BY o.CodOperario
            ORDER BY nombre_completo";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

//Recortar texto
function recortarTexto($texto, $longitud = 50) {
    if (strlen($texto) <= $longitud) {
        return $texto;
    }
    return substr($texto, 0, $longitud) . '...';
}

// Funciones específicas para faltas manuales
function obtenerFaltasManuales($codSucursal, $fechaDesde, $fechaHasta, $esRH = false, $modoVista = 'sucursal', $excluirPendientes = false, $operarioId = 0) {
    global $conn;
    
    error_log("Intentando obtener faltas manuales para sucursal: $codSucursal, desde: $fechaDesde, hasta: $fechaHasta, operario: $operarioId");
    
    try {
        $sql = "
            SELECT fm.*, 
                o.Nombre AS operario_nombre, 
                o.Nombre2 AS operario_nombre2,
                o.Apellido AS operario_apellido,
                o.Apellido2 AS operario_apellido2,
                s.nombre AS sucursal_nombre,
                r.Nombre AS registrador_nombre,
                r.Apellido AS registrador_apellido,
                fm.observaciones_rrhh,
                fm.cod_contrato,
                fm.fecha_registro,
                fm.porcentaje_pago,
                tf.nombre as tipo_falta_nombre
            FROM faltas_manual fm
            JOIN Operarios o ON fm.cod_operario = o.CodOperario
            JOIN sucursales s ON fm.cod_sucursal = s.codigo
            JOIN Operarios r ON fm.registrado_por = r.CodOperario
            LEFT JOIN tipos_falta tf ON fm.tipo_falta = tf.codigo
            WHERE fm.fecha_falta BETWEEN ? AND ?
        ";
        
        $params = [$fechaDesde, $fechaHasta];
        
        // CORRECCIÓN: Si no es 'todas' y no está vacío, filtrar por sucursal
        if ($modoVista !== 'todas' && !empty($codSucursal) && $codSucursal !== 'todas') {
            $sql .= " AND fm.cod_sucursal = ?";
            $params[] = $codSucursal;
        }
        
        // Filtrar por operario si se seleccionó uno específico
        if ($operarioId > 0) {
            $sql .= " AND fm.cod_operario = ?";
            $params[] = $operarioId;
        }
        
        // Excluir faltas pendientes si se solicita
        if ($excluirPendientes) {
            $sql .= " AND fm.tipo_falta != 'Pendiente'";
        }
        
        $sql .= " ORDER BY fm.fecha_falta DESC, o.Nombre, o.Apellido";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Error al preparar la consulta: " . implode(" ", $conn->errorInfo()));
            return [];
        }
        
        if (!$stmt->execute($params)) {
            error_log("Error al ejecutar la consulta: " . implode(" ", $stmt->errorInfo()));
            return [];
        }
        
        $resultados = $stmt->fetchAll();
        error_log("Faltas manuales encontradas: " . count($resultados));
        return $resultados;
    } catch (PDOException $e) {
        error_log("Excepción al obtener faltas manuales: " . $e->getMessage());
        return [];
    }
}

// Modificar la función procesarRegistroFaltaManual()
function procesarRegistroFaltaManual() {
    global $conn, $esLider, $esRH;
    
    // Permitir tanto a líderes como a RH registrar faltas
    if (!$esLider && !$esRH) {
        $_SESSION['error'] = 'Solo los líderes y RH pueden registrar nuevas faltas manuales';
        header('Location: faltas_manual.php');
        exit();
    }
    
    try {
        $codOperario = (int)$_POST['cod_operario'];
        $fechaFalta = $_POST['fecha_falta'];
        $codSucursal = $_POST['cod_sucursal'];
        $observaciones = $_POST['observaciones'] ?? '';
        
        // OBTENER EL ÚLTIMO CÓDIGO DE CONTRATO - CONSULTA DIRECTA
        $codContrato = null;
        $stmt_contrato = $conn->prepare("
            SELECT CodContrato 
            FROM Contratos 
            WHERE cod_operario = ? 
            ORDER BY inicio_contrato DESC, CodContrato DESC 
            LIMIT 1
        ");
        $stmt_contrato->execute([$codOperario]);
        $contrato = $stmt_contrato->fetch();
        if ($contrato) {
            $codContrato = $contrato['CodContrato'];
        }
        
        // VALIDACIÓN: No permitir fechas futuras
        $fechaActual = date('Y-m-d');
        if ($fechaFalta > $fechaActual) {
            throw new Exception('No se pueden registrar faltas con fechas futuras');
        }
        
        // VALIDACIÓN MEJORADA: Verificar si realmente hubo falta (no hay NINGUNA marcación)
        // EXCEPCIÓN: Para sucursales 6 y 18, RRHH puede registrar sin validación de horario
        $esSucursalEspecial = in_array($codSucursal, ['6', '18']);
        $esRH = verificarAccesoCargo([13]); // Código 13 es RRHH
        
        if (!$esSucursalEspecial || !$esRH) {
            // Solo validar horario si NO es sucursal especial O NO es RRHH
            if (!verificarFaltaReal($codOperario, $codSucursal, $fechaFalta)) {
                throw new Exception('No se puede registrar falta: El colaborador tiene marcaciones registradas para esta fecha (entrada o salida) o no tenía horario programado activo');
            }
        }
        
        // VALIDACIÓN MEJORADA: Verificar si ya existe una falta para este operario en esta fecha
        $stmt = $conn->prepare("
            SELECT id, tipo_falta FROM faltas_manual 
            WHERE cod_operario = ? AND fecha_falta = ?
            LIMIT 1
        ");
        $stmt->execute([$codOperario, $fechaFalta]);
        
        if ($faltaExistente = $stmt->fetch()) {
            $tipo = $faltaExistente['tipo_falta'];
            $tipoTexto = str_replace(
                ['_', 'No_Pagado', 'Pendiente', 'Subsidio_3dias', 'Subsidio_INSS', 'Subsidio_maternidad', 'Reposo_hasta_3dias', 'Compensacion_feria', 'Compensacion_dia_trabajado', 'Cuido_materno'], 
                [' ', 'No Pagado', 'Pendiente', 'Subsidio (3 días)', 'Subsidio INSS', 'Subsidio maternidad', 'Reposo (3 días)', 'Compensación feria', 'Compensación día trabajado', 'Cuido materno'], 
                $tipo
            );
            
            $_SESSION['error'] = "Ya existe un registro de falta para este colaborador en la fecha seleccionada (Tipo: $tipoTexto).";
            
            // Conservar todos los filtros en la redirección
            $params = [];
            if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
            if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
            if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
            if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];
            
            header('Location: faltas_manual.php?' . http_build_query($params));
            exit();
        }
        
        // Validar que se haya subido una foto
        if (!isset($_FILES['foto_falta'])) {
            throw new Exception('Debe subir una foto como evidencia');
        }
        
        $foto = $_FILES['foto_falta'];
        
        // Validar el archivo subido
        if ($foto['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir la foto: ' . $foto['error']);
        }
        
        // Validar tamaño (máximo 5MB)
        if ($foto['size'] > 5 * 1024 * 1024) {
            throw new Exception('La foto no debe exceder los 5MB');
        }
        
        // Validar tipo de archivo
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($foto['type'], $allowedTypes)) {
            throw new Exception('Solo se permiten imágenes JPEG, PNG o GIF');
        }
        
        // Determinar el tipo de falta según el cargo
        if ($esRH) {
            $tipoFalta = $_POST['tipo_falta'] ?? 'No_Pagado';
        } else {
            $tipoFalta = 'Pendiente'; // Líderes registran como Pendiente
        }
        
        $porcentajePago = obtenerPorcentajePagoTipoFalta($tipoFalta);
        
        // Crear nombre único para el archivo
        $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
        $nombreFoto = 'falta_' . $codOperario . '_' . date('YmdHis') . '.' . $extension;
        
        // Ruta relativa para la base de datos
        $rutaRelativa = '/uploads/faltas_manual/' . $nombreFoto;
        
        // Ruta absoluta para guardar el archivo
        $uploadDir = __DIR__ . '/../../uploads/faltas_manual/';
        
        // Crear directorio si no existe
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('No se pudo crear el directorio de uploads');
            }
        }
        
        // Verificar que el directorio es escribible
        if (!is_writable($uploadDir)) {
            throw new Exception('El directorio de uploads no tiene permisos de escritura');
        }
        
        $rutaCompleta = $uploadDir . $nombreFoto;
        
        // Mover el archivo subido
        if (!move_uploaded_file($foto['tmp_name'], $rutaCompleta)) {
            throw new Exception('Error al guardar la foto en el servidor. Verifique permisos.');
        }
        
        // Insertar nuevo registro con la ruta relativa
        $stmt = $conn->prepare("
            INSERT INTO faltas_manual (
                cod_operario, fecha_falta, cod_sucursal, 
                tipo_falta, observaciones, foto_path, registrado_por, cod_contrato, porcentaje_pago
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $codOperario, 
            $fechaFalta, 
            $codSucursal, 
            $tipoFalta, 
            $observaciones,
            $rutaRelativa, // Usamos la ruta relativa para la BD
            $_SESSION['usuario_id'],
            $codContrato,
            $porcentajePago
        ]);
        
        $_SESSION['exito'] = 'Falta manual registrada correctamente';
    } catch (Exception $e) {
        // Eliminar la foto si hubo un error después de subirla
        if (isset($rutaCompleta) && file_exists($rutaCompleta)) {
            @unlink($rutaCompleta);
        }
        $_SESSION['error'] = 'Error al registrar la falta manual: ' . $e->getMessage();
        error_log('Error en procesarRegistroFaltaManual: ' . $e->getMessage());
    }
    
    $params = [];
    if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
    if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
    if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
    if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];
    
    header('Location: faltas_manual.php?' . http_build_query($params));
    exit();
}

// Función para obtener el total de faltas automáticas (como en faltas.php)
function obtenerTotalFaltasAutomaticas($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    // 1. Obtener todos los operarios asignados a la sucursal en el rango de fechas
    $operarios = obtenerOperariosSucursalEnRango($codSucursal, $fechaDesde, $fechaHasta);
    $totalFaltas = 0;
    
    foreach ($operarios as $operario) {
        // 2. Para cada operario, verificar días laborables en el rango
        $diasLaborables = obtenerDiasLaborablesOperario($operario['CodOperario'], $codSucursal, $fechaDesde, $fechaHasta);
        
        foreach ($diasLaborables as $dia) {
            // 3. Verificar si hay marcación de entrada para ese día
            $marcacion = obtenerMarcacionEntrada($operario['CodOperario'], $dia['fecha']);
            
            if (!$marcacion) {
                $totalFaltas++;
            }
        }
    }
    
    return $totalFaltas;
}

// Función auxiliar para obtener operarios de sucursal en rango de fechas
function obtenerOperariosSucursalEnRango($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2,
               s.nombre as sucursal_nombre
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

// Función auxiliar para obtener días laborables de un operario
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

// Función auxiliar para verificar marcación de entrada
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

// Función para obtener el total de faltas manuales registradas
function obtenerTotalFaltasManuales($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM faltas_manual 
        WHERE cod_sucursal = ? 
        AND fecha_falta BETWEEN ? AND ?
    ");
    $stmt->execute([$codSucursal, $fechaDesde, $fechaHasta]);
    $result = $stmt->fetch();
    
    return $result['total'] ?? 0;
}

// Calcular totales para la tarjeta de resumen
$totalFaltasAuto = 0;
$totalFaltasManualesRegistradas = 0;
$faltasPendientes = 0;

if ($sucursalSeleccionada || ($esRH && ($modoVista ?? 'sucursal') === 'todas')) {
    if (($modoVista ?? 'sucursal') === 'todas') {
        // Modo "todas" - sumar todas las sucursales
        $totalFaltasAuto = 0;
        $totalFaltasManualesRegistradas = 0;
        
        foreach ($sucursales as $suc) {
            $totalFaltasAuto += obtenerTotalFaltasAutomaticas($suc['codigo'], $fechaDesde, $fechaHasta);
            $totalFaltasManualesRegistradas += obtenerTotalFaltasManuales($suc['codigo'], $fechaDesde, $fechaHasta);
        }
    } else {
        // Modo sucursal específica
        $totalFaltasAuto = obtenerTotalFaltasAutomaticas($sucursalSeleccionada, $fechaDesde, $fechaHasta);
        $totalFaltasManualesRegistradas = obtenerTotalFaltasManuales($sucursalSeleccionada, $fechaDesde, $fechaHasta);
    }
    
    $faltasPendientes = $totalFaltasAuto - $totalFaltasManualesRegistradas;
    if ($faltasPendientes < 0) $faltasPendientes = 0; // Por si hay más manuales que automáticas
}

/**
 * Verifica si realmente hubo una falta (no hay NINGUNA marcación - ni entrada ni salida)
 * y el día tenía un estado de horario permitido
 */
function verificarFaltaReal($codOperario, $codSucursal, $fechaFalta) {
    global $conn;
    
    // 1. Verificar si hay CUALQUIER marcación (entrada O salida) para ese día
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_marcaciones,
               MAX(hora_ingreso) as tiene_entrada,
               MAX(hora_salida) as tiene_salida
        FROM marcaciones 
        WHERE CodOperario = ? 
        AND sucursal_codigo = ?
        AND fecha = ?
        AND (hora_ingreso IS NOT NULL OR hora_salida IS NOT NULL)
    ");
    $stmt->execute([$codOperario, $codSucursal, $fechaFalta]);
    $result = $stmt->fetch();
    
    // Si hay ALGUNA marcación (entrada O salida), NO es una falta real
    if ($result && $result['total_marcaciones'] > 0) {
        error_log("No se puede registrar falta: Operario $codOperario tiene marcaciones en $fechaFalta - Entrada: " . ($result['tiene_entrada'] ? 'SÍ' : 'NO') . ", Salida: " . ($result['tiene_salida'] ? 'SÍ' : 'NO'));
        return false;
    }
    
    // 2. Verificar si el operario tenía horario programado para ese día
    $diaSemana = date('N', strtotime($fechaFalta)); // 1=lunes, 7=domingo
    
    // Mapear a los nombres de columna
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
    
    // Obtener el horario programado para ese día
    $stmt = $conn->prepare("
        SELECT 
            {$diaColumna}_estado as estado,
            {$diaColumna}_entrada as hora_entrada,
            {$diaColumna}_salida as hora_salida
        FROM HorariosSemanalesOperaciones hso
        JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
        WHERE hso.cod_operario = ?
        AND hso.cod_sucursal = ?
        AND ? BETWEEN ss.fecha_inicio AND ss.fecha_fin
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $codSucursal, $fechaFalta]);
    $horario = $stmt->fetch();
    
    // MODIFICADO: Definir estados permitidos para registro de faltas
    $estadosPermitidos = ['Activo', 'Otra.Tienda', 'Subsidio', 'Vacaciones'];
    
    // Si no hay horario programado o el día no estaba en estados permitidos, no es una falta real
    if (!$horario || !in_array($horario['estado'], $estadosPermitidos)) {
        error_log("No se puede registrar falta: Operario $codOperario no tenía horario programado con estado permitido para $fechaFalta. Estado actual: " . ($horario['estado'] ?? 'No hay horario'));
        return false;
    }
    
    // 3. Si no hay NINGUNA marcación Y tenía horario programado con estado permitido, entonces es una falta real
    error_log("FALTA REAL CONFIRMADA: Operario $codOperario - Fecha: $fechaFalta - Sin marcaciones y con horario en estado permitido: " . $horario['estado']);
    return true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faltas Manuales</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        /* Estilos similares a faltas.php (puedes reutilizar los mismos) */
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

        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
        
        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
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
        }
        
        .btn:hover {
            background-color: #0E544C;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-primary {
            background-color: #007bff;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
        }
        
        .btn-info {
            background-color: #17a2b8;
        }
        
        .btn-info:hover {
            background-color: #138496;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            vertical-align: middle;
        }

        th {
            background-color: #0E544C;
            color: white;
            text-align: center;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .status-dias_mas_septimo {
            color: #155724;
            background-color: #d4edda;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }
        
        .status-no_pagado {
            color: #721c24;
            background-color: #f8d7da;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
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
            align-items: flex-start; /* Cambiado de center a flex-start */
            padding: 20px;
            overflow-y: auto; /* Permitir scroll en el contenedor modal */
        }
        
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 90%; /* Ancho máximo responsive */
            width: 100%;
            max-width: 600px; /* Máximo ancho para pantallas grandes */
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            margin: 20px auto; /* Centrado con margen */
            max-height: 90vh; /* Altura máxima del 90% del viewport */
            overflow-y: auto; /* Scroll interno si es necesario */
            position: relative;
        }
        
        /* Header del modal fijo */
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            border-radius: 8px 8px 0 0;
        }
        
        .modal-title {
            color: #0E544C;
            font-size: 1.2rem !important;
            font-weight: bold;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .modal-body {
            margin-bottom: 15px;
            max-height: calc(90vh - 150px); /* Altura máxima calculada */
            overflow-y: auto; /* Scroll interno */
        }
        
        /* Mejorar la visualización de los formularios dentro del modal */
        .modal-body .form-group {
            margin-bottom: 15px;
        }
        
        .modal-body .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }
        
        .modal-body .form-select, 
        .modal-body .form-textarea, 
        .modal-body .form-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box; /* Incluir padding en el ancho */
        }
        
        .modal-body .form-textarea {
            min-height: 80px;
            resize: vertical; /* Permitir redimensionamiento vertical */
        }
        
        /* Footer del modal */
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            position: sticky;
            bottom: 0;
            background: white;
            z-index: 10;
            border-radius: 0 0 8px 8px;
        }
        
        /* Mejorar el scroll */
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .modal-content::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .info-group {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #0E544C;
        }
        
        .info-value {
            margin-left: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }
        
        .form-select, .form-textarea, .form-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-textarea {
            min-height: 80px;
        }
        
        .no-results {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
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
    
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
        
        /* Estilos mejorados para los badges de estado */
        .status-badge {
            padding: 5px 10px;
            border-radius: 12px;
            text-align: center;
            font-weight: bold;
            font-size: 0.85rem;
            display: inline-block;
            min-width: 100px;
            text-transform: capitalize;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    
        .status-pendiente {
            color: #084298;
            background-color: #cfe2ff;
            border: 1px solid #9ec5fe;
        }
    
        .status-no-pagado {
            color: #58151c;
            background-color: #f8d7da;
            border: 1px solid #f1aeb5;
        }
    
        .status-vacaciones {
            color: #055160;
            background-color: #cff4fc;
            border: 1px solid #9eeaf9;
        }
    
        .status-subsidio {
            color: #664d03;
            background-color: #fff3cd;
            border: 1px solid #ffecb5;
        }
    
        .status-dia-mas-septimo {
            color: #0a3622;
            background-color: #d1e7dd;
            border: 1px solid #a3cfbb;
        }
        
        .status-subsidio-3dias {
            color: #664d03;
            background-color: #fff3cd;
            border: 1px solid #ffecb5;
        }
        
        .status-subsidio-inss {
            color: #084298;
            background-color: #cfe2ff;
            border: 1px solid #9ec5fe;
        }
        
        .status-subsidio-maternidad {
            color: #58151c;
            background-color: #f8d7da;
            border: 1px solid #f1aeb5;
        }
        
        .status-reposo-hasta-3dias {
            color: #0a3622;
            background-color: #d1e7dd;
            border: 1px solid #a3cfbb;
        }
        
        .status-compensacion-feria {
            color: #055160;
            background-color: #cff4fc;
            border: 1px solid #9eeaf9;
        }
        
        .status-compensacion-dia-trabajado {
            color: #412f04;
            background-color: #e7f1ff;
            border: 1px solid #c6d8f0;
        }
        
        .status-cuido-materno {
            color: #3d0a3d;
            background-color: #e8d6e8;
            border: 1px solid #d0a2d0;
        }
        
        .diferencia-tarde {
            color: #dc3545;
            font-weight: bold;
        }
        
        .diferencia-temprano {
            color: #28a745;
            font-weight: bold;
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
    
    .modal {
        padding: 10px;
    }
    
    .modal-content {
        max-width: 95%;
        padding: 15px;
        margin: 10px auto;
    }
}

a.btn{
    text-decoration: none;
}

/* Estilos para el filtro de colaboradores */
#operarios-sugerencias {
    width: calc(100% - 2px); /* Mismo ancho que el input */
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 5px 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    margin-top: -1px; /* Para que se pegue al input */
    position: absolute;
    top: 100%; /* Posiciona el dropdown justo debajo del input */
    left: 0;
    z-index: 1000;
    background-color: white;
    max-height: 200px;
    overflow-y: auto;
}

#operarios-sugerencias div:hover {
    background-color: #f5f5f5 !important;
}

/* Asegurar que el input tenga un z-index menor */
.filtro-group input[type="text"] {
    position: relative;
    z-index: 1;
}

/* Estilos para los filtros */
.filtros-container {
    background-color: white;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.filtros-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.filtro-group {
    display: flex;
    flex-direction: column;
    position: relative;
}

.filtro-group label {
    margin-bottom: 5px;
    text-align: left;
    font-weight: bold;
}

.filtro-group select,
.filtro-group input {
    padding: 8px;
    border-radius: 5px;
    border: 1px solid #ddd;
    width: 100%;
}

.filtro-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.filtro-buttons button {
    padding: 8px 15px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-aplicar {
    background-color: #51B8AC;
    color: white;
}

.btn-aplicar:hover {
    background-color: #0E544C;
}

.btn-limpiar {
    background-color: #f1f1f1;
    color: #333;
}

.btn-limpiar:hover {
    background-color: #ddd;
}

.btn-agregar.excel {
    background-color: transparent;
    color: #1d6f42;
    border: 1px solid #1d6f42;
}

.btn-agregar.excel:hover {
    background-color: #1d6f42;
    color: white;
}

.btn-agregar.excel-contabilidad {
    background-color: #6f42c1;
    border-color: #6f42c1;
    color: white;
}

.btn-agregar.excel-contabilidad:hover {
    background-color: #5a2d9e;
    border-color: #5a2d9e;
}

@media (max-width: 768px) {
    .filtros-form {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        max-width: 90%;
    }
}

/* Estilos para la previsualización de imagen */
#preview-image:hover {
    opacity: 0.8;
    transform: scale(1.02);
    transition: all 0.2s ease;
}

/* Estilos para el modal de ampliación */
#modalAmpliarImagen img {
    border-radius: 8px;
    transition: transform 0.3s ease;
}

#modalAmpliarImagen button:hover {
    color: #51B8AC;
}

        /* Estilos para indicadores de ordenamiento en tablas con 3 estados */
        th.sorting_asc, th.sorting_desc, th.sorting {
            background-color: #51B8AC !important;
            position: relative;
            cursor: pointer;
        }
        
        th.sorting_asc:after, th.sorting_desc:after, th.sorting:after {
            content: '';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
        }
        
        /* Estado ASC (primer click - flecha arriba) */
        th.sorting_asc {
            background-color: #0E544C !important;
        }
        
        th.sorting_asc:after {
            color: white;
        }
        
        /* Estado DESC (segundo click - flecha abajo) */
        th.sorting_desc {
            background-color: #0E544C !important;
        }
        
        th.sorting_desc:after {
            color: white;
        }
        
        /* Efecto hover para mejor usabilidad */
        th.sorting:hover {
            background-color: #0E544C !important;
            transition: background-color 0.3s;
        }
        
        /* Forzar herencia de estilos para DataTables paginación */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: inherit !important;
            background: transparent !important;
            border: 1px solid #ddd !important;
            margin-left: 2px;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background-color: #51B8AC !important;
            color: white !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background-color: #0E544C !important;
            color: white !important;
            border-color: #0E544C !important;
        }
        
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }
        
        .dataTables_wrapper .dataTables_info {
            color: inherit;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <?php if ($esAdmin || verificarAccesoCargo([8, 5, 13, 16])): ?>
                        <a href="faltas_manual.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'faltas_manual.php' ? 'activo' : '' ?>">
                            <i class="fas fa-user-times"></i> <span class="btn-text">Faltas/Ausencias</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([8, 13, 16])): ?>
                        <a href="../rh/tf_operarios.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'tf_operarios.php' ? 'activo' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Totales</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([5, 11, 16, 27, 8])): ?>
                        <a href="../operaciones/tardanzas_manual.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == '../operaciones/tardanzas_manual.php' ? 'activo' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Tardanzas</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([11, 8, 16])): ?>
                        <a href="../operaciones/horas_extras_manual.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'horas_extras_manual.php' ? 'activo' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Horas Extras</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([8, 11, 16])): ?>
                        <a href="../operaciones/feriados.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'feriados.php' ? 'activo' : '' ?>">
                            <i class="fas fa-calendar-day"></i> <span class="btn-text">Feriados</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([8, 16])): ?>
                        <a href="../operaciones/viaticos.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'viaticos.php' ? 'activo' : '' ?>">
                            <i class="fas fa-money-check-alt"></i> <span class="btn-text">Viáticos</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([5, 16])): ?>
                        <a href="programar_horarios_lider.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'programar_horarios_lider.php' ? 'activo' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Generar Horarios</span>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?= $esAdmin ? 
                            strtoupper(substr($usuario['nombre'], 0, 1)) : 
                            strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div>
                            <?= $esAdmin ? 
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
        
        <!-- Tarjeta de resumen de faltas -->
        <div class="resumen-faltas" style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; display:none;">
            <div style="display:none;" class="tarjeta" style="flex: 1; min-width: 200px; background: #f8f9fa; border-radius: 8px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h3 style="color: #0E544C; margin-bottom: 10px; font-size: 1rem;">Total Faltas Automáticas</h3>
                <p style="font-size: 1.5rem; font-weight: bold; color: #343a40;"><?= $totalFaltasAuto ?></p>
                <small style="color: #6c757d;">Faltas detectadas por el sistema</small>
            </div>
            
            <div style="display:none;" class="tarjeta" style="flex: 1; min-width: 200px; background: #f8f9fa; border-radius: 8px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h3 style="color: #0E544C; margin-bottom: 10px; font-size: 1rem;">Faltas Registradas</h3>
                <p style="font-size: 1.5rem; font-weight: bold; color: #28a745;"><?= $totalFaltasManualesRegistradas ?></p>
                <small style="color: #6c757d;">Faltas registradas manualmente</small>
            </div>
            
            <div class="tarjeta" style="flex: 1; min-width: 200px; background: #f8f9fa; border-radius: 8px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h3 style="color: #0E544C; margin-bottom: 10px; font-size: 1rem;">Faltas Pendientes</h3>
                <p style="font-size: 1.5rem; font-weight: bold; color: #dc3545;"><?= $faltasPendientes ?></p>
                <small style="color: #6c757d;">Faltas por registrar</small>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filtros-container">
            <form method="get" action="faltas_manual.php" class="filtros-form">
                <div class="filtro-group">
                    <label for="sucursal">Sucursal</label>
                    <select id="sucursal" name="sucursal">
                        <?php foreach ($sucursales as $sucursal): ?>
                            <option value="<?= $sucursal['codigo'] ?>" <?= $sucursalSeleccionada == $sucursal['codigo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sucursal['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filtro-group">
                    <label for="operario">Colaborador</label>
                    <input type="text" id="operario" name="operario" 
                           placeholder="Escriba para buscar..." 
                           value="<?php 
                               if ($operarioSeleccionado > 0) {
                                   foreach ($operarios as $op) {
                                       if ($op['CodOperario'] == $operarioSeleccionado) {
                                           echo htmlspecialchars($op['nombre_completo']);
                                           break;
                                       }
                                   }
                               } else {
                                   echo 'Todos los colaboradores';
                               }
                           ?>">
                    <input type="hidden" id="operario_id" name="operario" value="<?php echo $operarioSeleccionado; ?>">
                    <div id="operarios-sugerencias" style="display: none;"></div>
                </div>
                
                <div class="filtro-group">
                    <label for="desde">Desde</label>
                    <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($fechaDesde) ?>">
                </div>
                
                <div class="filtro-group">
                    <label for="hasta">Hasta</label>
                    <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($fechaHasta) ?>">
                </div>
                
                <div class="filtro-buttons">
                    <button type="submit" class="btn-aplicar">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a style="display:none;" href="faltas_manual.php" class="btn-limpiar">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                    
                    <!-- Botones de acción en la misma línea de filtros -->
                    <?php if ($esAdmin || verificarAccesoCargo([5, 13, 16])): ?>
                        <button type="button" onclick="mostrarModalNuevaFalta()" class="btn btn-success">
                            <i class="fas fa-plus"></i> Nuevo
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || $esRH): ?>
                        <a style="display:none;" href="faltas_manual.php?<?= http_build_query([
                            'sucursal' => $sucursalSeleccionada ?? '',
                            'desde' => $fechaDesde,
                            'hasta' => $fechaHasta,
                            'operario' => $operarioSeleccionado,
                            'exportar_excel' => 1
                        ]) ?>" class="btn-agregar excel">
                            <i class="fas fa-file-excel"></i> Exportar
                        </a>
                        
                        <!-- Nuevo botón para exportar a Excel para contabilidad -->
                        <a style="display:none;" href="faltas_manual.php?<?= http_build_query([
                            'sucursal' => $sucursalSeleccionada ?? '',
                            'desde' => $fechaDesde,
                            'hasta' => $fechaHasta,
                            'operario' => $operarioSeleccionado,
                            'exportar_contabilidad' => 1
                        ]) ?>" class="btn-agregar excel-contabilidad">
                            <i class="fas fa-file-excel"></i> Contabilidad
                        </a>
                        
                        <!-- Botones de exportación específicos -->
                        <a href="faltas_manual.php?<?= http_build_query([
                            'sucursal' => $sucursalSeleccionada ?? '',
                            'desde' => $fechaDesde,
                            'hasta' => $fechaHasta,
                            'operario' => $operarioSeleccionado,
                            'exportar_faltas_auto_septimo' => 1
                        ]) ?>" class="btn-agregar" style="background-color: #ffc107; border-color: #ffc107; color: #000;">
                            <i class="fas fa-file-excel"></i> No Reportadas + 7mo
                        </a>
                        
                        <a href="faltas_manual.php?<?= http_build_query([
                            'sucursal' => $sucursalSeleccionada ?? '',
                            'desde' => $fechaDesde,
                            'hasta' => $fechaHasta,
                            'operario' => $operarioSeleccionado,
                            'exportar_permisos' => 1
                        ]) ?>" class="btn-agregar" style="background-color: #17a2b8; border-color: #17a2b8; color: white;">
                            <i class="fas fa-file-excel"></i> Permisos
                        </a>
                        
                        <a href="faltas_manual.php?<?= http_build_query([
                            'sucursal' => $sucursalSeleccionada ?? '',
                            'desde' => $fechaDesde,
                            'hasta' => $fechaHasta,
                            'operario' => $operarioSeleccionado,
                            'exportar_vacaciones' => 1
                        ]) ?>" class="btn-agregar" style="background-color: #28a745; border-color: #28a745; color: white;">
                            <i class="fas fa-file-excel"></i> Vacaciones
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="table-container">
        <?php if (!empty($faltasManuales)): ?>
            <table id="listaFaltas">
                <thead>
                    <tr>
                        <th>Colaborador</th>
                        <th>Sucursal</th>
                        <th>Fecha Falta</th>
                        <th>Tipo Falta</th>
                        <th>Observaciones</th>
                        <th>Registrado por</th>
                        <th>Fecha Registro</th>
                        <?php if ($esAdmin || verificarAccesoCargo([13, 16])): ?>
                            <th></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($faltasManuales as $falta): ?>
                        <tr>
                            <td><?= htmlspecialchars($falta['operario_nombre'] . ' ' . $falta['operario_apellido'] . ' ' . $falta['operario_apellido2']) ?></td>
                            <td><?= htmlspecialchars($falta['sucursal_nombre']) ?></td>
                            <td><?= formatoFechaCorta($falta['fecha_falta']) ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower(str_replace(['_', ' '], '-', $falta['tipo_falta'])) ?>">
                                    <?= str_replace(
                                        ['_', 'No_Pagado', 'Pendiente', 'Subsidio_3dias', 'Subsidio_INSS', 'Subsidio_maternidad', 'Reposo_hasta_3dias', 'Compensacion_feria', 'Compensacion_dia_trabajado', 'Cuido_materno'], 
                                        [' ', 'No Pagado', 'Pendiente', 'Subsidio (3 días)', 'Subsidio INSS', 'Subsidio maternidad', 'Reposo (3 días)', 'Compensación feria', 'Compensación día trabajado', 'Cuido materno'], 
                                        $falta['tipo_falta']
                                    ) ?>
                                </span>
                            </td>
                            <td style="text-align:center;" title="<?= htmlspecialchars($falta['observaciones'] ?: '-') ?>">
                                <?= $falta['observaciones'] ? htmlspecialchars(recortarTexto($falta['observaciones'], 10)) : '-' ?>
                                <?php if ($falta['foto_path']): ?>
                                    <button type="button" onclick="mostrarFoto('<?= htmlspecialchars($falta['foto_path']) ?>')" class="btn btn-sm btn-info">
                                        <i class="fas fa-image"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($falta['registrador_nombre'] . ' ' . $falta['registrador_apellido']) ?></td>
                            <td><?= formatoFechaCorta($falta['fecha_registro']) ?></td>
                            <?php if ($esAdmin || verificarAccesoCargo([13, 16])): ?>
                                <td style="text-align: center;">
                                    <button type="button" onclick="mostrarModalEditarFalta(
                                        <?= $falta['id'] ?>, 
                                        '<?= htmlspecialchars($falta['operario_nombre'] . ' ' . $falta['operario_apellido']) ?>', 
                                        '<?= htmlspecialchars($falta['sucursal_nombre']) ?>', 
                                        '<?= $falta['fecha_falta'] ?>', 
                                        '<?= $falta['tipo_falta'] ?>', 
                                        '<?= htmlspecialchars($falta['observaciones'] ?? '') ?>',
                                        '<?= htmlspecialchars($falta['observaciones_rrhh'] ?? '') ?>', // <-- ESTE ES EL NUEVO PARÁMETRO
                                        '<?= $falta['foto_path'] ?>'
                                    )" class="btn btn-info">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button style="display:none;" type="button" onclick="consultarMarcacion(
                                        <?= $falta['cod_operario'] ?>,
                                        '<?= htmlspecialchars($falta['operario_nombre'] . ' ' . $falta['operario_apellido']) ?>',
                                        '<?= htmlspecialchars($falta['sucursal_nombre']) ?>',
                                        '<?= $falta['cod_sucursal'] ?>',
                                        '<?= $falta['fecha_falta'] ?>'
                                    )" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
                <div class="alert alert-info">
                    <?php if (($sucursalSeleccionada || $modoVista === 'todas') && $fechaDesde && $fechaHasta): ?>
                        No se encontraron faltas manuales 
                        <?php if ($modoVista === 'todas'): ?>
                            en todas las sucursales
                        <?php else: ?>
                            para <?= htmlspecialchars(obtenerNombreSucursal($sucursalSeleccionada)) ?>
                        <?php endif; ?>
                        entre <?= formatoFechaCorta($fechaDesde) ?> y <?= formatoFechaCorta($fechaHasta) ?>.
                    <?php else: ?>
                        Seleccione una sucursal y rango de fechas para buscar faltas manuales.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    
    <!-- Modal para nueva falta manual -->
    <div class="modal" id="modalNuevaFalta">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Registrar Falta Manual</h2>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <form id="formNuevaFalta" method="post" enctype="multipart/form-data">
                <input type="hidden" name="registrar_falta" value="1">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nueva_sucursal" class="form-label">Sucursal:</label>
                        <select id="nueva_sucursal" name="cod_sucursal" class="form-select" required>
                            <?php if ($esRH): ?>
                                <!-- Para RH, mostrar todas las sucursales -->
                                <?php foreach (obtenerTodasSucursales() as $sucursal): ?>
                                    <option value="<?= $sucursal['codigo'] ?>">
                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Para líderes, mostrar solo sus sucursales -->
                                <?php foreach (obtenerSucursalesLider($_SESSION['usuario_id']) as $sucursal): ?>
                                    <option value="<?= $sucursal['codigo'] ?>">
                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="nueva_fecha" class="form-label">Fecha de Falta:</label>
                        <input type="date" id="nueva_fecha" name="fecha_falta" class="form-input" required max="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="nueva_operario" class="form-label">Operario:</label>
                        <select id="nueva_operario" name="cod_operario" class="form-select" required>
                            <option value="">Seleccione un operario</option>
                            <!-- Se llenará dinámicamente con JavaScript -->
                        </select>
                    </div>
                    
                    <?php if ($_SESSION['cargo_cod'] == 13): // Solo RH puede seleccionar tipo ?>
                    <div class="form-group">
                        <label for="nueva_tipo" class="form-label">Tipo de Falta:</label>
                        <select id="nueva_tipo" name="tipo_falta" class="form-select" required onchange="actualizarPorcentaje(this.value)">
                            <option value="">Seleccione un tipo</option>
                            <?php 
                            $tiposFalta = obtenerTiposFaltaConPorcentajes();
                            foreach ($tiposFalta as $tipo): 
                                $porcentajeTexto = ($tipo['porcentaje_pago'] == -100) ? 
                                    'Deducción 100%' : 
                                    'Paga ' . $tipo['porcentaje_pago'] . '%';
                            ?>
                                <option value="<?= $tipo['codigo'] ?>" data-porcentaje="<?= $tipo['porcentaje_pago'] ?>">
                                    <?= htmlspecialchars($tipo['nombre']) ?> (<?= $porcentajeTexto ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="info-porcentaje" class="form-text text-muted" style="display: none;"></small>
                    </div>
                    <?php else: // Para líderes, tipo fijo ?>
                        <input type="hidden" name="tipo_falta" value="Pendiente">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="nueva_observaciones" class="form-label">Observaciones:</label>
                        <textarea id="nueva_observaciones" name="observaciones" class="form-textarea"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="nueva_foto" class="form-label">Foto de Evidencia (Obligatoria):</label>
                        <input type="file" id="nueva_foto" name="foto_falta" class="form-input" accept="image/*" capture="environment" required>
                        <small class="form-text text-muted">Toma una foto o selecciona una del dispositivo (máx. 5MB)</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para editar falta manual -->
    <div class="modal" id="modalEditarFalta">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Editar Falta Manual</h2>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <form id="formEditarFalta" method="post" action="editar_falta_manual.php">
                <input type="hidden" name="editar_falta" value="1">
                <input type="hidden" id="editar_id" name="id">
                <input type="hidden" id="editar_foto_path" name="foto_path_actual">
                
                <div class="modal-body">
                    <!-- Información básica -->
                    <div class="info-group">
                        <span class="info-label">Colaborador:</span>
                        <span class="info-value" id="editar_nombre"></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Sucursal:</span>
                        <span class="info-value" id="editar_sucursal"></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Fecha de Falta:</span>
                        <span class="info-value" id="editar_fecha"></span>
                    </div>
                    
                    <!-- Observaciones del líder -->
                    <div class="form-group">
                        <label class="form-label">Observaciones del Líder:</label>
                        <div id="editar_observaciones_lider" class="info-value" style="background-color: #f8f9fa; padding: 8px; border-radius: 4px; border: 1px solid #ddd; min-height: 40px;"></div>
                    </div>
                    
                    <!-- PREVISUALIZACIÓN DE IMAGEN - ESTA ES LA SECCIÓN CORRECTA -->
                    <div class="form-group" id="preview-container" style="display: none;">
                        <label class="form-label">Foto Evidencia:</label>
                        <div style="text-align: center;">
                            <img id="preview-image" src="" alt="Previsualización" 
                                 style="max-width: 150px; max-height: 150px; cursor: pointer; border: 1px solid #ddd; border-radius: 4px;"
                                 onclick="ampliarImagen(this.src)">
                            <div style="margin-top: 5px; font-size: 12px; color: #666;">
                                <i class="fas fa-search-plus"></i> Click para ampliar
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tipo de falta -->
                    <div class="form-group">
                        <label for="editar_tipo" class="form-label">Tipo de Falta:</label>
                        <select id="editar_tipo" name="tipo_falta" class="form-select" required onchange="actualizarPorcentajeEdicion(this.value)">
                            <option value="">Seleccione un tipo</option>
                            <?php 
                            $tiposFalta = obtenerTiposFaltaConPorcentajes();
                            foreach ($tiposFalta as $tipo): 
                                $porcentajeTexto = ($tipo['porcentaje_pago'] == -100) ? 
                                    'Deducción 100%' : 
                                    'Paga ' . $tipo['porcentaje_pago'] . '%';
                            ?>
                                <option value="<?= $tipo['codigo'] ?>" data-porcentaje="<?= $tipo['porcentaje_pago'] ?>">
                                    <?= htmlspecialchars($tipo['nombre']) ?> (<?= $porcentajeTexto ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="info-porcentaje-edicion" class="form-text text-muted" style="display: none;"></small>
                    </div>
                    
                    <!-- Observaciones RRHH (solo para RH) -->
                    <?php if ($esRH): ?>
                    <div class="form-group">
                        <label for="editar_observaciones_rrhh" class="form-label">Observaciones RRHH: *</label>
                        <textarea id="editar_observaciones_rrhh" name="observaciones_rrhh" class="form-textarea" required></textarea>
                    </div>
                    <?php else: ?>
                    <!-- Para no-RRHH, mostrar solo lectura -->
                    <div class="form-group">
                        <label class="form-label">Observaciones RRHH:</label>
                        <div id="editar_observaciones_rrhh_view" class="info-value" style="background-color: #f8f9fa; padding: 8px; border-radius: 4px; border: 1px solid #ddd; min-height: 40px;"></div>
                        <input type="hidden" id="editar_observaciones_rrhh" name="observaciones_rrhh">
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                    <?php if ($esRH): ?>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    <?php else: ?>
                    <button type="button" class="btn btn-secondary" disabled>Sin permisos para editar</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para consultar marcaciones relacionadas con la falta -->
    <div class="modal" id="modalConsultarMarcacion">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 class="modal-title">Detalles de Marcación</h2>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="info-group">
                    <span class="info-label">Colaborador:</span>
                    <span class="info-value" id="consulta_nombre"></span>
                </div>
                
                <div class="info-group">
                    <span class="info-label">Sucursal:</span>
                    <span class="info-value" id="consulta_sucursal"></span>
                </div>
                
                <div class="info-group">
                    <span class="info-label">Fecha de Falta:</span>
                    <span class="info-value" id="consulta_fecha"></span>
                </div>
                
                <h3 style="margin: 15px 0 10px; color: #0E544C;">Horario Programado</h3>
                <div class="info-group">
                    <span class="info-label">Hora de Entrada:</span>
                    <span class="info-value" id="consulta_hora_entrada_programada">-</span>
                </div>
                
                <div class="info-group">
                    <span class="info-label">Hora de Salida:</span>
                    <span class="info-value" id="consulta_hora_salida_programada">-</span>
                </div>
                
                <h3 style="margin: 15px 0 10px; color: #0E544C;">Marcaciones Registradas</h3>
                <div class="info-group">
                    <span class="info-label">Hora de Entrada:</span>
                    <span class="info-value" id="consulta_hora_entrada">-</span>
                </div>
                
                <div class="info-group">
                    <span class="info-label">Hora de Salida:</span>
                    <span class="info-value" id="consulta_hora_salida">-</span>
                </div>
                
                <div class="info-group">
                    <span class="info-label">Diferencia Entrada:</span>
                    <span class="info-value" id="consulta_diferencia_entrada">-</span>
                </div>
                
                <div class="info-group">
                    <span class="info-label">Diferencia Salida:</span>
                    <span class="info-value" id="consulta_diferencia_salida">-</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cerrar</button>
            </div>
        </div>
    </div>
    
    <script>
        // Datos de operarios para el autocompletado
        const operariosData = [
            {id: 0, nombre: 'Todos los colaboradores'},
            <?php foreach ($operarios as $op): ?>
            {id: <?php echo $op['CodOperario']; ?>, nombre: '<?php echo addslashes($op['nombre_completo']); ?>'},
            <?php endforeach; ?>
        ];
        
        // Función para buscar operarios
        function buscarOperarios(texto) {
            if (!texto) {
                return operariosData;
            }
            return operariosData.filter(op => 
                op.nombre.toLowerCase().includes(texto.toLowerCase())
            );
        }
        
        // Manejar el input de operario
        const operarioInput = document.getElementById('operario');
        const operarioIdInput = document.getElementById('operario_id');
        const sugerenciasDiv = document.getElementById('operarios-sugerencias');
        
        // Modificar el evento input del campo operario
        operarioInput.addEventListener('input', function() {
            const texto = this.value.trim();
            
            // Si el campo está vacío, resetear a "todos"
            if (texto === '') {
                operarioIdInput.value = '0';
                sugerenciasDiv.style.display = 'none';
                return;
            }
            
            const resultados = buscarOperarios(texto);
            
            sugerenciasDiv.innerHTML = '';
            
            if (resultados.length > 0) {
                resultados.forEach(op => {
                    const div = document.createElement('div');
                    div.textContent = op.nombre;
                    div.style.padding = '8px';
                    div.style.cursor = 'pointer';
                    div.addEventListener('click', function() {
                        operarioInput.value = op.nombre;
                        operarioIdInput.value = op.id;
                        sugerenciasDiv.style.display = 'none';
                    });
                    div.addEventListener('mouseover', function() {
                        this.style.backgroundColor = '#f5f5f5';
                    });
                    div.addEventListener('mouseout', function() {
                        this.style.backgroundColor = 'white';
                    });
                    sugerenciasDiv.appendChild(div);
                });
                sugerenciasDiv.style.display = 'block';
            } else {
                sugerenciasDiv.style.display = 'none';
            }
        });
        
        // Ocultar sugerencias al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (e.target !== operarioInput) {
                sugerenciasDiv.style.display = 'none';
            }
        });
        
        // Manejar tecla Enter en el input
        operarioInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const texto = this.value.trim();
                const resultados = buscarOperarios(texto);
                if (resultados.length > 0) {
                    this.value = resultados[0].nombre;
                    operarioIdInput.value = resultados[0].id;
                }
                sugerenciasDiv.style.display = 'none';
            }
        });
        
        // Función para mostrar foto en un modal
        function mostrarFoto(rutaFoto) {
            ampliarImagen(rutaFoto);
        }
        
        // Actualizar filtros y recargar la página
        function actualizarFiltros() {
            const desde = document.getElementById('desde').value;
            const hasta = document.getElementById('hasta').value;
            
            // Validar fechas
            if (!desde || !hasta) {
                alert('Por favor seleccione ambas fechas');
                return;
            }
            
            if (new Date(desde) > new Date(hasta)) {
                alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return;
            }
            
            // Construir URL con parámetros
            const params = new URLSearchParams();
            
            <?php if ($esRH): ?>
            const modo = document.getElementById('modo')?.value || 'sucursal';
            params.append('modo', modo);
            
            if (modo === 'sucursal') {
                const sucursal = document.getElementById('sucursal').value;
                if (sucursal) params.append('sucursal', sucursal);
            }
            <?php else: ?>
            const sucursal = document.getElementById('sucursal').value;
            if (sucursal) params.append('sucursal', sucursal);
            <?php endif; ?>
            
            params.append('desde', desde);
            params.append('hasta', hasta);
            
            window.location.href = 'faltas_manual.php?' + params.toString();
        }
        
        // Mostrar modal para nueva falta
        function mostrarModalNuevaFalta() {
            // Establecer fecha predeterminada como hoy
            document.getElementById('nueva_fecha').valueAsDate = new Date();
            
            // Limpiar selección de operario
            const selectOperario = document.getElementById('nueva_operario');
            selectOperario.innerHTML = '<option value="">Seleccione un operario</option>';
            
            // Obtener primera sucursal del select
            const selectSucursal = document.getElementById('nueva_sucursal');
            
            <?php if ($esRH): ?>
                // Para RH, cargar todas las sucursales
                selectSucursal.innerHTML = '';
                <?php 
                $todasSucursales = obtenerTodasSucursales();
                foreach ($todasSucursales as $sucursal): ?>
                    selectSucursal.innerHTML += '<option value="<?= $sucursal['codigo'] ?>"><?= htmlspecialchars($sucursal['nombre']) ?></option>';
                <?php endforeach; ?>
            <?php else: ?>
                // Para líderes, mantener el código actual
                const primeraSucursal = selectSucursal.value;
            <?php endif; ?>
            
            // Cargar operarios de la primera sucursal
            if (selectSucursal.value) {
                cargarOperariosSucursal(selectSucursal.value);
            }
            
            document.getElementById('modalNuevaFalta').style.display = 'flex';
        }
        
        // Función para cargar operarios de una sucursal
        function cargarOperariosSucursal(codSucursal) {
            const selectOperario = document.getElementById('nueva_operario');
            
            if (!codSucursal) {
                selectOperario.innerHTML = '<option value="">Seleccione un operario</option>';
                return;
            }
            
            // Mostrar carga
            selectOperario.innerHTML = '<option value="">Cargando operarios...</option>';
            
            // Hacer petición AJAX para obtener operarios de la sucursal
            fetch(`ajax.php?action=obtener_operarios_sucursal&sucursal=${codSucursal}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    let options = '<option value="">Seleccione un operario</option>';
                    
                    if (data.length > 0) {
                        data.forEach(operario => {
                            options += `<option value="${operario.CodOperario}">${operario.Nombre} ${operario.Apellido}</option>`;
                        });
                    } else {
                        options = '<option value="">No hay operarios en esta sucursal</option>';
                    }
                    
                    selectOperario.innerHTML = options;
                })
                .catch(error => {
                    console.error('Error al cargar operarios:', error);
                    selectOperario.innerHTML = '<option value="">Error al cargar operarios</option>';
                });
        }
        
        // Validar formulario antes de enviar
        document.getElementById('formNuevaFalta').addEventListener('submit', function(e) {
            const fechaInput = document.getElementById('nueva_fecha');
            const fechaSeleccionada = new Date(fechaInput.value);
            const fechaActual = new Date();
            fechaActual.setHours(0, 0, 0, 0); // Resetear hora para comparar solo fechas
            
            // Validar que no sea fecha futura
            if (fechaSeleccionada > fechaActual) {
                e.preventDefault();
                alert('No se pueden registrar faltas con fechas futuras');
                return false;
            }
            
            // Nueva validación: Verificar con AJAX si realmente hubo falta
            e.preventDefault();
            
            const codOperario = document.getElementById('nueva_operario').value;
            const codSucursal = document.getElementById('nueva_sucursal').value;
            const fechaFalta = fechaInput.value;
            
            if (!codOperario || !codSucursal || !fechaFalta) {
                alert('Complete todos los campos obligatorios');
                return false;
            }
            
            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
            submitBtn.disabled = true;
            
            // Hacer petición AJAX para verificar si realmente hubo falta
            fetch('ajax.php?action=verificar_falta_real', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    cod_operario: codOperario,
                    cod_sucursal: codSucursal,
                    fecha_falta: fechaFalta
                })
            })
            .then(response => response.json())
            .then(data => {
                // En el formulario de nueva falta, en la validación AJAX
                if (data.existe_falta) {
                    // Si realmente hubo falta, enviar el formulario
                    document.getElementById('formNuevaFalta').submit();
                } else {
                    // Si no hubo falta, mostrar mensaje de error actualizado
                    alert('No se puede registrar falta: El colaborador tiene marcaciones registradas para esta fecha o no tenía horario programado con estado Activo, Otra.Tienda, Subsidio o Vacaciones.');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al verificar la falta. Intente nuevamente.');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
            
            return false;
        });
        
        // Cargar operarios cuando cambia la sucursal en el modal de nueva falta
        document.getElementById('nueva_sucursal').addEventListener('change', function() {
            cargarOperariosSucursal(this.value);
        });
        
        // Función auxiliar al script
        function formatearFechaLocal(fechaStr) {
            const fecha = new Date(fechaStr + 'T00:00:00');
            const opciones = { day: '2-digit', month: 'short', year: '2-digit' };
            return fecha.toLocaleDateString('es-ES', opciones);
        }
        
        // Función para mostrar el tipo de falta correcto en el modal de edición
        function mostrarModalEditarFalta(id, nombre, sucursal, fecha, tipo, observaciones, observaciones_rrhh, fotoPath) {
            console.log('Datos recibidos:', {id, nombre, sucursal, fecha, tipo, observaciones, observaciones_rrhh, fotoPath});
            
            document.getElementById('editar_id').value = id;
            document.getElementById('editar_nombre').textContent = nombre;
            document.getElementById('editar_sucursal').textContent = sucursal;
            
            document.getElementById('editar_fecha').textContent = formatearFechaLocal(fecha);
            
            document.getElementById('editar_foto_path').value = fotoPath;
            
            // Mostrar observaciones del líder (solo lectura)
            document.getElementById('editar_observaciones_lider').textContent = observaciones || '(Sin observaciones)';
            
            // Convertir "Pendiente" de nuevo a "No_Pagado" para el formulario
            const tipoParaForm = tipo;
            const selectTipo = document.getElementById('editar_tipo');
            selectTipo.value = tipoParaForm;
            
            // Asegurarse de que el tipo actual esté seleccionado, incluso si no existe en las opciones
            if (!selectTipo.querySelector(`option[value="${tipoParaForm}"]`)) {
                // Si el tipo no existe en las opciones, agregarlo temporalmente
                const nuevaOpcion = new Option(tipoParaForm.replace(/_/g, ' '), tipoParaForm);
                selectTipo.add(nuevaOpcion);
                selectTipo.value = tipoParaForm;
            }
            
            // Actualizar la información del porcentaje basado en el tipo actual
            actualizarPorcentajeEdicion(tipo);
            
            // Manejar observaciones RRHH según el tipo de usuario
            <?php if ($esRH): ?>
            document.getElementById('editar_observaciones_rrhh').value = observaciones_rrhh || '';
            <?php else: ?>
            // Para no-RRHH, mostrar solo lectura
            document.getElementById('editar_observaciones_rrhh_view').textContent = observaciones_rrhh || '(Sin observaciones RRHH)';
            document.getElementById('editar_observaciones_rrhh').value = observaciones_rrhh || '';
            <?php endif; ?>
            
            // Mostrar u ocultar la previsualización de imagen
            const previewContainer = document.getElementById('preview-container');
            const previewImage = document.getElementById('preview-image');
            
            if (fotoPath && fotoPath !== '') {
                // Usar ruta absoluta para la imagen
                const rutaCompleta = '../..' + fotoPath;
                console.log('Cargando imagen:', rutaCompleta);
                previewImage.src = rutaCompleta;
                previewContainer.style.display = 'block';
                
                // Verificar si la imagen se carga correctamente
                previewImage.onload = function() {
                    console.log('Imagen cargada correctamente');
                };
                previewImage.onerror = function() {
                    console.error('Error al cargar la imagen:', rutaCompleta);
                    previewContainer.style.display = 'none';
                };
            } else {
                console.log('No hay imagen para mostrar');
                previewContainer.style.display = 'none';
            }
            
            document.getElementById('modalEditarFalta').style.display = 'flex';
        }
        
        // Validar formulario de edición
        document.getElementById('formEditarFalta').addEventListener('submit', function(e) {
            const observacionesRRHH = document.getElementById('editar_observaciones_rrhh').value.trim();
            
            if (!observacionesRRHH) {
                e.preventDefault();
                alert('El campo Observaciones RRHH es obligatorio');
                return false;
            }
            
            return true;
        });
        
        // Función para consultar marcaciones relacionadas con una falta
        function consultarMarcacion(codOperario, nombre, sucursalNombre, codSucursal, fechaFalta) {
            // Mostrar información básica
            document.getElementById('consulta_nombre').textContent = nombre;
            document.getElementById('consulta_sucursal').textContent = sucursalNombre;
            
            document.getElementById('consulta_fecha').textContent = formatearFechaLocal(fechaFalta);
            
            // Resetear valores mientras se carga
            document.getElementById('consulta_hora_entrada_programada').textContent = '-';
            document.getElementById('consulta_hora_salida_programada').textContent = '-';
            document.getElementById('consulta_hora_entrada').textContent = '-';
            document.getElementById('consulta_hora_salida').textContent = '-';
            document.getElementById('consulta_diferencia_entrada').textContent = '-';
            document.getElementById('consulta_diferencia_salida').textContent = '-';
            
            // Mostrar el modal
            document.getElementById('modalConsultarMarcacion').style.display = 'flex';
            
            // Hacer petición AJAX para obtener los datos
            fetch(`ajax.php?action=consultar_marcacion_falta&cod_operario=${codOperario}&cod_sucursal=${codSucursal}&fecha=${fechaFalta}`)
                .then(response => response.json())
                .then(data => {
                    // Mostrar horario programado
                    if (data.horario_programado) {
                        const hp = data.horario_programado;
                        document.getElementById('consulta_hora_entrada_programada').textContent = 
                            hp.hora_entrada_programada ? formatoHoraAmPm(hp.hora_entrada_programada) : '-';
                        document.getElementById('consulta_hora_salida_programada').textContent = 
                            hp.hora_salida_programada ? formatoHoraAmPm(hp.hora_salida_programada) : '-';
                    }
                    
                    // Mostrar marcaciones
                    if (data.marcaciones) {
                        const m = data.marcaciones;
                        document.getElementById('consulta_hora_entrada').textContent = 
                            m.hora_ingreso ? formatoHoraAmPm(m.hora_ingreso) : '-';
                        document.getElementById('consulta_hora_salida').textContent = 
                            m.hora_salida ? formatoHoraAmPm(m.hora_salida) : '-';
                        
                        // Calcular y mostrar diferencias si hay datos
                        if (data.horario_programado && data.marcaciones) {
                            const hp = data.horario_programado;
                            const m = data.marcaciones;
                            
                            // Diferencia entrada
                            if (hp.hora_entrada_programada && m.hora_ingreso) {
                                const difEntrada = calcularDiferenciaMinutos(
                                    hp.hora_entrada_programada, 
                                    m.hora_ingreso
                                );
                                mostrarDiferencia('consulta_diferencia_entrada', difEntrada);
                            }
                            
                            // Diferencia salida
                            if (hp.hora_salida_programada && m.hora_salida) {
                                const difSalida = calcularDiferenciaMinutos(
                                    hp.hora_salida_programada, 
                                    m.hora_salida
                                );
                                mostrarDiferencia('consulta_diferencia_salida', difSalida);
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error al consultar marcación:', error);
                    alert('Error al obtener los datos de marcación');
                });
        }

        // Función auxiliar para formatear hora en formato 12h AM/PM
        function formatoHoraAmPm(hora) {
            if (!hora || hora === '00:00:00') return '-';
            return new Date(`2000-01-01T${hora}`).toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }

        // Función para calcular diferencia en minutos entre dos horas
        function calcularDiferenciaMinutos(horaProgramada, horaReal) {
            const hp = new Date(`2000-01-01T${horaProgramada}`);
            const hr = new Date(`2000-01-01T${horaReal}`);
            
            const diffMs = hr - hp;
            return Math.round(diffMs / 60000); // Convertir a minutos
        }

        // Función para mostrar diferencia con colores
        function mostrarDiferencia(elementId, minutos) {
            const element = document.getElementById(elementId);
            
            if (minutos > 0) {
                element.innerHTML = `<span class="diferencia-tarde">+${minutos} min (Tarde)</span>`;
            } else if (minutos < 0) {
                element.innerHTML = `<span class="diferencia-temprano">${minutos} min (Temprano)</span>`;
            } else {
                element.innerHTML = `<span>${minutos} min (Exacto)</span>`;
            }
        }
        
        // Función para ampliar imagen (funciona sobre modales existentes)
        function ampliarImagen(src) {
            const modalAmpliar = document.createElement('div');
            modalAmpliar.id = 'modalAmpliarImagen';
            modalAmpliar.style.position = 'fixed';
            modalAmpliar.style.top = '0';
            modalAmpliar.style.left = '0';
            modalAmpliar.style.width = '100%';
            modalAmpliar.style.height = '100%';
            modalAmpliar.style.backgroundColor = 'rgba(0,0,0,0.9)';
            modalAmpliar.style.display = 'flex';
            modalAmpliar.style.justifyContent = 'center';
            modalAmpliar.style.alignItems = 'center';
            modalAmpliar.style.zIndex = '3000'; // Mayor z-index para que esté sobre el modal de edición
            
            const img = document.createElement('img');
            img.src = src;
            img.style.maxWidth = '90%';
            img.style.maxHeight = '90%';
            img.style.objectFit = 'contain';
            img.style.boxShadow = '0 0 20px rgba(255,255,255,0.2)';
            
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.position = 'absolute';
            closeBtn.style.top = '20px';
            closeBtn.style.right = '20px';
            closeBtn.style.fontSize = '2.5rem';
            closeBtn.style.color = 'white';
            closeBtn.style.background = 'none';
            closeBtn.style.border = 'none';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.zIndex = '3001';
            
            closeBtn.onclick = function() {
                document.body.removeChild(modalAmpliar);
            };
            
            modalAmpliar.appendChild(img);
            modalAmpliar.appendChild(closeBtn);
            document.body.appendChild(modalAmpliar);
            
            // Cerrar al hacer clic fuera de la imagen
            modalAmpliar.onclick = function(e) {
                if (e.target === modalAmpliar) {
                    document.body.removeChild(modalAmpliar);
                }
            };
            
            // Cerrar con tecla ESC
            const closeOnEsc = function(e) {
                if (e.key === 'Escape') {
                    document.body.removeChild(modalAmpliar);
                    document.removeEventListener('keydown', closeOnEsc);
                }
            };
            
            document.addEventListener('keydown', closeOnEsc);
        }
        
        // Cerrar modal
        function cerrarModal() {
            document.getElementById('modalNuevaFalta').style.display = 'none';
            document.getElementById('modalEditarFalta').style.display = 'none';
            document.getElementById('modalConsultarMarcacion').style.display = 'none';
        }
        
        // Cargar operarios cuando se selecciona una sucursal en el modal de nueva falta
        document.getElementById('nueva_sucursal').addEventListener('change', function() {
            const codSucursal = this.value;
            const selectOperario = document.getElementById('nueva_operario');
            
            if (!codSucursal) {
                selectOperario.innerHTML = '<option value="">Seleccione un operario</option>';
                return;
            }
            
            // Hacer petición AJAX para obtener operarios de la sucursal
            fetch('ajax.php?action=obtener_operarios_sucursal&sucursal=' + codSucursal)
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">Seleccione un operario</option>';
                    
                    data.forEach(operario => {
                        options += `<option value="${operario.CodOperario}">${operario.Nombre} ${operario.Apellido}</option>`;
                    });
                    
                    selectOperario.innerHTML = options;
                })
                .catch(error => {
                    console.error('Error al cargar operarios:', error);
                    selectOperario.innerHTML = '<option value="">Error al cargar operarios</option>';
                });
        });
        
        // Cerrar modal al hacer clic fuera del contenido
        window.addEventListener('click', function(event) {
            const modals = ['modalNuevaFalta', 'modalEditarFalta'];
            
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    cerrarModal();
                }
            });
        });
        
        $(document).ready(function() {
            $('#listaFaltas').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                dom: '<"top"l>rt<"bottom"ip>', // Quitamos la "f" en "top"lf (filter/search)
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                pageLength: 25,
                
                // CONFIGURACIÓN PARA 3 CLICKS
                order: [], // Sin orden inicial - respeta el orden de la consulta SQL
                ordering: true, // Habilitar ordenamiento
                orderMulti: true, // Permitir ordenamiento múltiple con Ctrl+click
                
                // Configuración específica para el ciclo de 3 clicks
                columnDefs: [{
                    orderable: true, // Todas las columnas son ordenables
                    targets: '_all' // Aplicar a todas las columnas
                }]
            });
        });
        
        // En el formulario de nueva falta, después de seleccionar sucursal
        document.getElementById('nueva_sucursal').addEventListener('change', function() {
            const sucursalEspecial = ['6', '18'].includes(this.value);
            const mensaje = document.getElementById('mensaje-especial');
            
            if (!mensaje) {
                const nuevoMensaje = document.createElement('div');
                nuevoMensaje.id = 'mensaje-especial';
                nuevoMensaje.style.padding = '10px';
                nuevoMensaje.style.margin = '10px 0';
                nuevoMensaje.style.borderRadius = '4px';
                document.querySelector('#formNuevaFalta .modal-body').prepend(nuevoMensaje);
            }
            
            // En el evento change del select de sucursal
            if (sucursalEspecial) {
                document.getElementById('mensaje-especial').innerHTML = 
                    '<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px;">' +
                    '<i class="fas fa-info-circle"></i> Sucursal especial: No se requiere validación de horario programado' +
                    '</div>';
            } else {
                document.getElementById('mensaje-especial').innerHTML = 
                    '<div style="background: #fff3cd; color: #856404; padding: 8px; border-radius: 4px; font-size: 12px;">' +
                    '<i class="fas fa-info-circle"></i> Se validará que el día tenga estado: Activo, Otra.Tienda, Subsidio o Vacaciones' +
                    '</div>';
            }
        });
        
        // Función para actualizar la información del porcentaje
        function actualizarPorcentaje(tipoFalta) {
            const select = document.getElementById('nueva_tipo');
            const option = select.querySelector(`option[value="${tipoFalta}"]`);
            const infoElement = document.getElementById('info-porcentaje');
            
            if (option && option.dataset.porcentaje) {
                const porcentaje = parseFloat(option.dataset.porcentaje);
                let texto = '';
                
                if (porcentaje === -100) {
                    texto = '⚠️ La empresa NO paga este día - se DEDUCE del salario';
                    infoElement.style.color = '#dc3545';
                } else if (porcentaje === 0) {
                    texto = 'ℹ️ La empresa NO paga este día';
                    infoElement.style.color = '#ffc107';
                } else if (porcentaje === 100) {
                    texto = '✅ La empresa paga el 100% de este día';
                    infoElement.style.color = '#28a745';
                } else {
                    texto = `📊 La empresa paga el ${porcentaje}% de este día`;
                    infoElement.style.color = '#17a2b8';
                }
                
                infoElement.textContent = texto;
                infoElement.style.display = 'block';
            } else {
                infoElement.style.display = 'none';
            }
        }
        
        // También para el modal de edición
        function actualizarPorcentajeEdicion(tipoFalta) {
            const select = document.getElementById('editar_tipo');
            const option = select.querySelector(`option[value="${tipoFalta}"]`);
            const infoElement = document.getElementById('info-porcentaje-edicion');
            
            if (option && option.dataset.porcentaje && infoElement) {
                const porcentaje = parseFloat(option.dataset.porcentaje);
                let texto = '';
                
                if (porcentaje === -100) {
                    texto = '⚠️ La empresa NO paga este día - se DEDUCE del salario';
                    infoElement.style.color = '#dc3545';
                } else if (porcentaje === 0) {
                    texto = 'ℹ️ La empresa NO paga este día';
                    infoElement.style.color = '#ffc107';
                } else if (porcentaje === 100) {
                    texto = '✅ La empresa paga el 100% de este día';
                    infoElement.style.color = '#28a745';
                } else {
                    texto = `📊 La empresa paga el ${porcentaje}% de este día`;
                    infoElement.style.color = '#17a2b8';
                }
                
                infoElement.textContent = texto;
                infoElement.style.display = 'block';
            } else if (infoElement) {
                infoElement.style.display = 'none';
            }
        }
    </script>
</body>
</html>