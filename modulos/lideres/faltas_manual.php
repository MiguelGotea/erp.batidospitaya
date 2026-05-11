<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

// Verificar conexión
if (!$conn) {
    die("Error de conexión a la base de datos");
}

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo
if (!tienePermiso('faltas_manual', 'vista', $cargoOperario)) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

$tieneTodasSucursales = tienePermiso('faltas_manual', 'todas_sucursales', $cargoOperario);
$puedeAprobar = tienePermiso('faltas_manual', 'aprobar', $cargoOperario);
$puedeNuevo = tienePermiso('faltas_manual', 'nuevo', $cargoOperario);
$puedeExportar = tienePermiso('faltas_manual', 'exportar', $cargoOperario);

/**
 * Obtiene los tipos de falta con sus porcentajes
 */
function obtenerTiposFaltaConPorcentajes()
{
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
function obtenerPorcentajePagoTipoFalta($tipoFalta)
{
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
function obtenerTodasFaltasManuales($codSucursal, $fechaDesde, $fechaHasta)
{
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
    usort($faltasPorOperario, function ($a, $b) {
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

// Establecer rango del mes actual por defecto
$hoy = new DateTime();
$primerDiaMes = $hoy->format('Y-m-01');
$ultimoDiaMes = $hoy->format('Y-m-t');

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
    $fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 month'));
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
 * MODIFICADA: Filtra por fecha de liquidación
 */
function obtenerFaltasAutomaticasParaContabilidad($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sqlOperarios = "
        SELECT DISTINCT o.CodOperario, 
               o.Nombre as operario_nombre, 
               o.Nombre2 as operario_nombre2,
               o.Apellido as operario_apellido,
               o.Apellido2 as operario_apellido2, 
               s.nombre as sucursal_nombre,
               anc.Sucursal as cod_sucursal,
               c.fecha_liquidacion,
               c.CodContrato
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        JOIN sucursales s ON anc.Sucursal = s.codigo
        LEFT JOIN (
            SELECT c1.cod_operario, c1.CodContrato, c1.fecha_liquidacion
            FROM Contratos c1
            INNER JOIN (
                SELECT cod_operario, MAX(CodContrato) as max_contrato
                FROM Contratos
                GROUP BY cod_operario
            ) c2 ON c1.cod_operario = c2.cod_operario AND c1.CodContrato = c2.max_contrato
        ) c ON o.CodOperario = c.cod_operario
        WHERE (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
        AND o.CodOperario NOT IN (
            SELECT DISTINCT anc2.CodOperario 
            FROM AsignacionNivelesCargos anc2
            WHERE anc2.CodNivelesCargos = 27
            AND (anc2.Fin IS NULL OR anc2.Fin >= ?)
        )
        -- FILTRO NUEVO: Solo operarios activos según fecha de liquidación
        AND (
            c.fecha_liquidacion IS NULL 
            OR c.fecha_liquidacion = '0000-00-00'
            OR c.fecha_liquidacion > CURDATE()
        )
    ";

    $params = [$fechaDesde, $fechaHasta, $fechaDesde];

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
        // NUEVA LÓGICA: Determinar hasta qué fecha buscar faltas
        $fechaHastaOperario = $fechaHasta;

        if (
            !empty($operario['fecha_liquidacion']) &&
            $operario['fecha_liquidacion'] != '0000-00-00'
        ) {
            $fechaLiquidacion = new DateTime($operario['fecha_liquidacion']);
            $fechaHastaObj = new DateTime($fechaHasta);

            if ($fechaLiquidacion < $fechaHastaObj) {
                $fechaHastaOperario = $fechaLiquidacion->format('Y-m-d');
            }

            $fechaDesdeObj = new DateTime($fechaDesde);
            if ($fechaLiquidacion < $fechaDesdeObj) {
                continue;
            }
        }

        $diasLaborables = obtenerDiasLaborablesOperario(
            $operario['CodOperario'],
            $operario['cod_sucursal'],
            $fechaDesde,
            $fechaHastaOperario
        );

        foreach ($diasLaborables as $dia) {
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
                    'cod_contrato' => $operario['CodContrato'],
                    'fecha_registro' => $dia['fecha']
                ];
            }
        }
    }

    return $faltas;
}

/**
 * Obtiene las faltas manuales reportadas como "No_Pagado" para restar de las automáticas
 */
function obtenerFaltasManualesReportadas($codSucursal, $fechaDesde, $fechaHasta)
{
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
function exportarFaltasAutoSeptimo($codSucursal, $fechaDesde, $fechaHasta)
{
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
function exportarPermisos($codSucursal, $fechaDesde, $fechaHasta)
{
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
        $obsDisplay = !empty($permiso['observaciones_rrhh']) ? $permiso['observaciones_rrhh'] : $permiso['observaciones'];
        echo '<td>' . ($obsDisplay ? htmlspecialchars($obsDisplay) : 'Sin comentarios') . '</td>';
        echo '<td>' . $fechaRegistro . '</td>'; // NUEVA COLUMNA
        echo '</tr>';
    }

    echo '</table>';
    exit;
}

/**
 * Exportar vacaciones
 */
function exportarVacaciones($codSucursal, $fechaDesde, $fechaHasta)
{
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
        $obsDisplay = !empty($vacacion['observaciones_rrhh']) ? $vacacion['observaciones_rrhh'] : $vacacion['observaciones'];
        echo '<td>' . ($obsDisplay ? htmlspecialchars($obsDisplay) : 'Sin comentarios') . '</td>';
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

// Obtener sucursales según los permisos del usuario
if ($tieneTodasSucursales) {
    $sucursales = obtenerTodasSucursales();
    // Agregar opción "Todas" al principio
    array_unshift($sucursales, ['codigo' => 'todas', 'nombre' => 'Todas las sucursales']);
} else {
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

// Si no tiene todas las sucursales y tiene múltiples asignadas, seleccionar la primera por defecto
if (!$tieneTodasSucursales && count($sucursales) > 0 && !isset($_GET['sucursal'])) {
    $sucursalSeleccionada = $sucursales[0]['codigo'];
}

// Obtener fechas desde los parámetros GET o usar el mes actual
$fechaDesde = $_GET['desde'] ?? $primerDiaMes;
$fechaHasta = $_GET['hasta'] ?? $ultimoDiaMes;

// Validar que las fechas no estén vacías
if (empty($fechaDesde))
    $fechaDesde = $primerDiaMes;
if (empty($fechaHasta))
    $fechaHasta = $ultimoDiaMes;

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
        $modoVista,
        false,
        $operarioSeleccionado
    );
}

/**
 * Función para obtener operarios para el filtro
 * MODIFICADA: Filtra por fecha de liquidación en lugar de Operativo
 */
function obtenerOperariosFiltro()
{
    global $conn;

    $sql = "SELECT o.CodOperario, 
                   CONCAT(
                       IFNULL(o.Nombre, ''), ' ', 
                       IFNULL(o.Nombre2, ''), ' ', 
                       IFNULL(o.Apellido, ''), ' ', 
                       IFNULL(o.Apellido2, '')
                   ) AS nombre_completo 
            FROM Operarios o
            LEFT JOIN (
                SELECT c1.cod_operario, c1.fecha_liquidacion
                FROM Contratos c1
                INNER JOIN (
                    SELECT cod_operario, MAX(CodContrato) as max_contrato
                    FROM Contratos
                    GROUP BY cod_operario
                ) c2 ON c1.cod_operario = c2.cod_operario AND c1.CodContrato = c2.max_contrato
            ) c ON o.CodOperario = c.cod_operario
            WHERE o.CodOperario NOT IN (
                SELECT DISTINCT anc.CodOperario 
                FROM AsignacionNivelesCargos anc
                WHERE anc.CodNivelesCargos = 27
                AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            )
            -- FILTRO NUEVO: Solo operarios activos según fecha de liquidación
            AND (
                c.fecha_liquidacion IS NULL 
                OR c.fecha_liquidacion = '0000-00-00'
                OR c.fecha_liquidacion > CURDATE()
            )
            GROUP BY o.CodOperario
            ORDER BY nombre_completo";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll();
}

//Recortar texto
function recortarTexto($texto, $longitud = 50)
{
    if (strlen($texto) <= $longitud) {
        return $texto;
    }
    return substr($texto, 0, $longitud) . '...';
}

// Funciones específicas para faltas manuales
function obtenerFaltasManuales($codSucursal, $fechaDesde, $fechaHasta, $modoVista = 'sucursal', $excluirPendientes = false, $operarioId = 0)
{
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
                tf.nombre as tipo_falta_nombre,
                c.fecha_liquidacion
            FROM faltas_manual fm
            JOIN Operarios o ON fm.cod_operario = o.CodOperario
            JOIN sucursales s ON fm.cod_sucursal = s.codigo
            JOIN Operarios r ON fm.registrado_por = r.CodOperario
            LEFT JOIN tipos_falta tf ON fm.tipo_falta = tf.codigo
            LEFT JOIN (
                -- Subquery para obtener el último contrato de cada operario
                SELECT c1.cod_operario, c1.fecha_liquidacion
                FROM Contratos c1
                INNER JOIN (
                    SELECT cod_operario, MAX(CodContrato) as max_contrato
                    FROM Contratos
                    GROUP BY cod_operario
                ) c2 ON c1.cod_operario = c2.cod_operario AND c1.CodContrato = c2.max_contrato
            ) c ON fm.cod_operario = c.cod_operario
            WHERE fm.fecha_falta BETWEEN ? AND ?
        ";

        $params = [$fechaDesde, $fechaHasta];

        if ($modoVista !== 'todas' && !empty($codSucursal) && $codSucursal !== 'todas') {
            $sql .= " AND fm.cod_sucursal = ?";
            $params[] = $codSucursal;
        }

        if ($operarioId > 0) {
            $sql .= " AND fm.cod_operario = ?";
            $params[] = $operarioId;
        }

        if ($excluirPendientes) {
            $sql .= " AND fm.tipo_falta != 'Pendiente'";
        }

        // NUEVO FILTRO: Excluir faltas posteriores a fecha de liquidación
        $sql .= " AND (
            c.fecha_liquidacion IS NULL 
            OR c.fecha_liquidacion = '0000-00-00'
            OR fm.fecha_falta <= c.fecha_liquidacion
        )";

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

        foreach ($resultados as &$falta) {
            $horarios = obtenerHorarioProgramadoMarcado(
                $falta['cod_operario'],
                $falta['cod_sucursal'],
                $falta['fecha_falta']
            );
            $falta['horarios'] = $horarios;
        }

        error_log("Faltas manuales encontradas: " . count($resultados));
        return $resultados;
    } catch (PDOException $e) {
        error_log("Excepción al obtener faltas manuales: " . $e->getMessage());
        return [];
    }
}

function procesarRegistroFaltaManual()
{
    global $conn;
    $cargoOperario = $_SESSION['cargo_cod'] ?? obtenerUsuarioActual()['CodNivelesCargos'];

    // Verificar permiso para registrar faltas
    if (!tienePermiso('faltas_manual', 'nuevo', $cargoOperario)) {
        $_SESSION['error'] = 'No tiene permisos para registrar nuevas faltas manuales';
        header('Location: faltas_manual.php');
        exit();
    }

    $puedeAprobar = tienePermiso('faltas_manual', 'aprobar', $cargoOperario);

    try {
        $codOperario = (int) $_POST['cod_operario'];
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

        // VALIDACIÓN: No permitir fechas futuras ni la fecha actual
        $fechaActual = date('Y-m-d');
        $fechaMaximaPermitida = date('Y-m-d', strtotime('-1 day'));

        if ($fechaFalta > $fechaMaximaPermitida) {
            throw new Exception('No se pueden registrar faltas para fechas futuras ni para el día actual. Solo se permiten fechas hasta: ' . formatoFechaCorta($fechaMaximaPermitida));
        }

        // VALIDACIÓN MEJORADA: Verificar si realmente hubo falta (no hay NINGUNA marcación)
        // EXCEPCIÓN: Para sucursales 6 y 18, quienes aprueban pueden registrar sin validación de horario
        $esSucursalEspecial = in_array($codSucursal, ['6', '18']);

        if (!$esSucursalEspecial || !$puedeAprobar) {
            // Solo validar horario si NO es sucursal especial O NO puede aprobar
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
            if (isset($_GET['sucursal']))
                $params['sucursal'] = $_GET['sucursal'];
            if (isset($_GET['desde']))
                $params['desde'] = $_GET['desde'];
            if (isset($_GET['hasta']))
                $params['hasta'] = $_GET['hasta'];
            if (isset($_GET['operario']) && $_GET['operario'] != 0)
                $params['operario'] = $_GET['operario'];

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

        // Determinar el tipo de falta según permisos
        if ($puedeAprobar) {
            $tipoFalta = $_POST['tipo_falta'] ?? 'No_Pagado';
        } else {
            $tipoFalta = 'Pendiente'; // Los que no aprueban registran como Pendiente
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
    if (isset($_GET['sucursal']))
        $params['sucursal'] = $_GET['sucursal'];
    if (isset($_GET['desde']))
        $params['desde'] = $_GET['desde'];
    if (isset($_GET['hasta']))
        $params['hasta'] = $_GET['hasta'];
    if (isset($_GET['operario']) && $_GET['operario'] != 0)
        $params['operario'] = $_GET['operario'];

    header('Location: faltas_manual.php?' . http_build_query($params));
    exit();
}

// Función para obtener el total de faltas automáticas
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
            // 3. Verificar si hay marcación de entrada para ese día
            $marcacion = obtenerMarcacionEntrada($operario['CodOperario'], $dia['fecha']);

            if (!$marcacion) {
                $totalFaltas++;
            }
        }
    }

    return $totalFaltas;
}

/**
 * Función auxiliar para obtener operarios de sucursal en rango de fechas
 * MODIFICADA: Filtra por fecha de liquidación
 */
function obtenerOperariosSucursalEnRango($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2,
               s.nombre as sucursal_nombre,
               c.fecha_liquidacion
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        JOIN sucursales s ON anc.Sucursal = s.codigo
        LEFT JOIN (
            -- Subquery para obtener el último contrato de cada operario
            SELECT c1.cod_operario, c1.fecha_liquidacion
            FROM Contratos c1
            INNER JOIN (
                SELECT cod_operario, MAX(CodContrato) as max_contrato
                FROM Contratos
                GROUP BY cod_operario
            ) c2 ON c1.cod_operario = c2.cod_operario AND c1.CodContrato = c2.max_contrato
        ) c ON o.CodOperario = c.cod_operario
        WHERE anc.Sucursal = ?
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
        AND o.CodOperario NOT IN (
            SELECT DISTINCT anc2.CodOperario 
            FROM AsignacionNivelesCargos anc2
            WHERE anc2.CodNivelesCargos = 27
            AND (anc2.Fin IS NULL OR anc2.Fin >= ?)
        )
        -- FILTRO NUEVO: Solo operarios activos según fecha de liquidación
        AND (
            c.fecha_liquidacion IS NULL 
            OR c.fecha_liquidacion = '0000-00-00'
            OR c.fecha_liquidacion > CURDATE()
        )
        ORDER BY o.Nombre, o.Apellido
    ");

    // CORRECCIÓN: Pasar todos los parámetros necesarios
    $stmt->execute([
        $codSucursal,    // Para anc.Sucursal = ?
        $fechaDesde,     // Para anc.Fin >= ?
        $fechaHasta,     // Para anc.Fecha <= ?
        $fechaDesde      // Para cargo 27 anc.Fin >= ?
    ]);

    return $stmt->fetchAll();
}

// Función auxiliar para obtener días laborables de un operario
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

// Función para obtener el total de faltas manuales registradas
function obtenerTotalFaltasManuales($codSucursal, $fechaDesde, $fechaHasta)
{
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

if ($sucursalSeleccionada || ($tieneTodasSucursales && ($modoVista ?? 'sucursal') === 'todas')) {
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
    if ($faltasPendientes < 0)
        $faltasPendientes = 0; // Por si hay más manuales que automáticas
}

/**
 * Verifica si realmente hubo una falta (no hay NINGUNA marcación - ni entrada ni salida)
 * y el día tenía un estado de horario permitido
 */
function verificarFaltaReal($codOperario, $codSucursal, $fechaFalta)
{
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Faltas Manuales</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <link rel="stylesheet" href="css/faltas_manual.css">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, 'Registro de Faltas/Ausencias'); ?>

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
            <div class="resumen-faltas"
                style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; display:none;">
                <div style="display:none;" class="tarjeta"
                    style="flex: 1; min-width: 200px; background: #f8f9fa; border-radius: 8px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <h3 style="color: #0E544C; margin-bottom: 10px; font-size: 1rem;">Total Faltas Automáticas</h3>
                    <p style="font-size: 1.5rem; font-weight: bold; color: #343a40;"><?= $totalFaltasAuto ?></p>
                    <small style="color: #6c757d;">Faltas detectadas por el sistema</small>
                </div>

                <div style="display:none;" class="tarjeta"
                    style="flex: 1; min-width: 200px; background: #f8f9fa; border-radius: 8px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <h3 style="color: #0E544C; margin-bottom: 10px; font-size: 1rem;">Faltas Registradas</h3>
                    <p style="font-size: 1.5rem; font-weight: bold; color: #28a745;">
                        <?= $totalFaltasManualesRegistradas ?>
                    </p>
                    <small style="color: #6c757d;">Faltas registradas manualmente</small>
                </div>

                <div class="tarjeta"
                    style="flex: 1; min-width: 200px; background: #f8f9fa; border-radius: 8px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <h3 style="color: #0E544C; margin-bottom: 10px; font-size: 1rem;">Faltas Pendientes</h3>
                    <p style="font-size: 1.5rem; font-weight: bold; color: #dc3545;"><?= $faltasPendientes ?></p>
                    <small style="color: #6c757d;">Faltas por registrar</small>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filtros-container">
                <form method="get" action="faltas_manual.php" class="filtros-form">
                    <?php if ($tieneTodasSucursales || count($sucursales) > 1): ?>
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
                    <?php else: ?>
                        <input type="hidden" name="sucursal" value="<?= htmlspecialchars($sucursales[0]['codigo'] ?? '') ?>">
                    <?php endif; ?>

                    <div class="filtro-group">
                        <label for="operario">Colaborador</label>
                        <input type="text" id="operario" name="operario" placeholder="Escriba para buscar..." value="<?php
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
                        <input type="hidden" id="operario_id" name="operario"
                            value="<?php echo $operarioSeleccionado; ?>">
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

                        <?php if ($puedeExportar): ?>
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
                                <th>Horarios</th>
                                <th>Tipo Falta</th>
                                <th>Observaciones</th>
                                <th>Registrado por</th>
                                <th>Fecha Registro</th>
                                <th>Foto</th>
                                <?php if ($puedeAprobar): ?>
                                    <th></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faltasManuales as $falta): ?>
                                <tr>
                                    <td><?= htmlspecialchars($falta['operario_nombre'] . ' ' . $falta['operario_apellido'] . ' ' . $falta['operario_apellido2']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($falta['sucursal_nombre']) ?></td>
                                    <td><?= formatoFechaCorta($falta['fecha_falta']) ?></td>
                                    <td style="min-width: 200px;">
                                        <?php
                                        $horarios = $falta['horarios'];
                                        if ($horarios):
                                            ?>
                                            <!-- Horario Programado -->
                                            <div style="margin-bottom: 5px;">
                                                <small style="color: #666; font-weight: bold;">Programado:</small>
                                                <?php if ($horarios['programado']): ?>
                                                    <?php
                                                    // Verificamos si entrada o salida están vacías
                                                    $entrada = $horarios['programado']['entrada'] ? formatoHoraCorta($horarios['programado']['entrada']) : '';
                                                    $salida = $horarios['programado']['salida'] ? formatoHoraCorta($horarios['programado']['salida']) : '';
                                                    ?>

                                                    <?php if ($entrada || $salida): ?>
                                                        <span style="font-size: 0.9em;">
                                                            <?= $entrada ?> - <?= $salida ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="font-size: 0.9em;">
                                                            <?= $horarios['programado']['estado'] ?>
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if ($horarios['programado']['estado']): ?>
                                                        <br><small
                                                            style="color: <?= $horarios['programado']['estado'] == 'Activo' ? '#28a745' : '#dc3545' ?>;">
                                                            <?//= $horarios['programado']['estado'] ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color: #999; font-size: 0.9em;">Sin horario programado</span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Horario Marcado -->
                                            <div>
                                                <small style="color: #666; font-weight: bold;">Marcado:</small>
                                                <?php if ($horarios['marcado'] && ($horarios['marcado']['entrada'] || $horarios['marcado']['salida'])): ?>
                                                    <span style="font-size: 0.9em;">
                                                        <?= $horarios['marcado']['entrada'] ? formatoHoraCorta($horarios['marcado']['entrada']) : '--:--' ?>
                                                        -
                                                        <?= $horarios['marcado']['salida'] ? formatoHoraCorta($horarios['marcado']['salida']) : '--:--' ?>
                                                    </span>

                                                    <!-- Mostrar diferencias si existen -->
                                                    <?php if ($horarios['diferencia_entrada'] !== null || $horarios['diferencia_salida'] !== null): ?>
                                                        <br>
                                                        <?php if ($horarios['diferencia_entrada'] !== null): ?>
                                                            <small
                                                                style="color: <?= $horarios['diferencia_entrada'] > 0 ? '#dc3545' : ($horarios['diferencia_entrada'] < 0 ? '#28a745' : '#17a2b8') ?>;">
                                                                Entrada:
                                                                <?= $horarios['diferencia_entrada'] > 0 ? '+' : '' ?>
                                                                <?= $horarios['diferencia_entrada'] ?>
                                                                min
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if ($horarios['diferencia_salida'] !== null): ?>
                                                            <br>
                                                            <small
                                                                style="color: <?= $horarios['diferencia_salida'] > 0 ? '#28a745' : ($horarios['diferencia_salida'] < 0 ? '#dc3545' : '#17a2b8') ?>;">
                                                                Salida:
                                                                <?= $horarios['diferencia_salida'] > 0 ? '+' : '' ?>
                                                                <?= $horarios['diferencia_salida'] ?>
                                                                min
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color: #dc3545; font-size: 0.9em;">Sin marcaciones</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999;">Sin información de horarios</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span
                                            class="status-badge status-<?= strtolower(str_replace(['_', ' '], '-', $falta['tipo_falta'])) ?>">
                                            <?= str_replace(
                                                ['_', 'No_Pagado', 'Pendiente', 'Subsidio_3dias', 'Subsidio_INSS', 'Subsidio_maternidad', 'Reposo_hasta_3dias', 'Compensacion_feria', 'Compensacion_dia_trabajado', 'Cuido_materno'],
                                                [' ', 'No Pagado', 'Pendiente', 'Subsidio (3 días)', 'Subsidio INSS', 'Subsidio maternidad', 'Reposo (3 días)', 'Compensación feria', 'Compensación día trabajado', 'Cuido materno'],
                                                $falta['tipo_falta']
                                            ) ?>
                                        </span>
                                    </td>
                                    <td title="<?= htmlspecialchars($falta['observaciones'] ?: '-') ?>">
                                        <?= $falta['observaciones'] ? htmlspecialchars(recortarTexto($falta['observaciones'], 20)) : '-' ?>
                                    </td>
                                    <td><?= htmlspecialchars($falta['registrador_nombre'] . ' ' . $falta['registrador_apellido']) ?>
                                    </td>
                                    <td><?= formatoFechaCorta($falta['fecha_registro']) ?></td>
                                    <td style="text-align:center;">
                                        <?php if ($falta['foto_path']): ?>
                                            <button type="button"
                                                onclick="mostrarFoto('<?= htmlspecialchars($falta['foto_path']) ?>')"
                                                class="btn btn-sm btn-foto">
                                                <i class="fas fa-camera" style="color: #51B8AC; font-size: 18px;"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($puedeAprobar): ?>
                                        <td style="text-align: center;">
                                            <button type="button" class="btn btn-info btn-editar-falta"
                                                data-id="<?= $falta['id'] ?>"
                                                data-nombre="<?= htmlspecialchars($falta['operario_nombre'] . ' ' . $falta['operario_apellido'], ENT_QUOTES) ?>"
                                                data-sucursal="<?= htmlspecialchars($falta['sucursal_nombre'], ENT_QUOTES) ?>"
                                                data-fecha="<?= $falta['fecha_falta'] ?>" data-tipo="<?= $falta['tipo_falta'] ?>"
                                                data-observaciones="<?= htmlspecialchars($falta['observaciones'] ?? '', ENT_QUOTES) ?>"
                                                data-observaciones-rrhh="<?= htmlspecialchars($falta['observaciones_rrhh'] ?? '', ENT_QUOTES) ?>"
                                                data-foto="<?= htmlspecialchars($falta['foto_path'], ENT_QUOTES) ?>">
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
        </div>
    </div>

    <!-- Modal para nueva falta manual -->
    <div class="modal" id="modalNuevaFalta" style="backdrop-filter: blur(10px); background-color: rgba(0, 0, 0, 0.45);">
        <div class="modal-content" style="padding: 0; overflow: hidden; max-width: 600px;">
            <div class="modal-header" style="background: #0E544C; color: #fff; padding: 20px; border-bottom: none; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center;">
                    <div style="background: rgba(255,255,255,0.25); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                        <i class="fas fa-file-alt" style="font-size: 1.2rem;"></i>
                    </div>
                    <div>
                        <h2 class="modal-title" style="margin: 0; font-size: 1.25rem !important; font-weight: bold; color: #fff;">Registrar Falta Manual</h2>
                        <p style="margin: 0; font-size: 0.85rem; opacity: 0.75;">Configura los detalles de la falta</p>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="cerrarModal()" style="color: #fff; opacity: 0.8;">&times;</button>
            </div>
            <form id="formNuevaFalta" method="post" enctype="multipart/form-data">
                <input type="hidden" name="registrar_falta" value="1">

                <div class="modal-body" style="padding: 20px; background: #f8f9fa;">
                    <!-- NUEVO: Mensaje de advertencia para operarios sin contrato -->
                    <div id="mensaje-advertencia-contrato" style="display: none; 
                                background-color: #fff3cd; 
                                border: 1px solid #ffc107; 
                                color: #856404; 
                                padding: 10px; 
                                border-radius: 4px; 
                                margin-bottom: 15px;">
                        <!-- El mensaje se llenará dinámicamente con JavaScript -->
                    </div>

                    <div class="form-group">
                        <label for="nueva_sucursal" class="form-label">Sucursal:</label>
                        <select id="nueva_sucursal" name="cod_sucursal" class="form-select" required>
                            <?php if ($tieneTodasSucursales): ?>
                                <!-- Para usuarios con acceso a todas las sucursales -->
                                <?php foreach (obtenerTodasSucursales() as $sucursal): ?>
                                    <option value="<?= $sucursal['codigo'] ?>">
                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Para usuarios limitados a sus sucursales -->
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
                        <input type="date" id="nueva_fecha" name="fecha_falta" class="form-input" required
                            max="<?= date('Y-m-d', strtotime('-1 day')) ?>">
                    </div>

                    <div class="form-group">
                        <label for="nueva_operario" class="form-label">Operario:</label>
                        <select id="nueva_operario" name="cod_operario" class="form-select" required>
                            <option value="">Seleccione un operario</option>
                            <!-- Se llenará dinámicamente con JavaScript -->
                        </select>
                    </div>

                    <?php if ($puedeAprobar): ?>
                        <div class="form-group">
                            <label for="nueva_tipo" class="form-label">Tipo de Falta:</label>
                            <select id="nueva_tipo" name="tipo_falta" class="form-select" required
                                onchange="actualizarPorcentaje(this.value)">
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
                        <input type="file" id="nueva_foto" name="foto_falta" class="form-input" accept="image/*"
                            required>
                        <small class="form-text text-muted">Toma una foto o selecciona una del dispositivo (máx.
                            5MB)</small>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 20px; background: white; border-top: none; display: flex; justify-content: space-between;">
                    <button type="button" onclick="cerrarModal()" class="btn-modern btn-modern-secondary">Cancelar</button>
                    <button type="submit" class="btn-modern btn-modern-primary"><i class="fas fa-save" style="margin-right: 8px;"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para editar falta manual -->
    <div class="modal" id="modalEditarFalta" style="backdrop-filter: blur(10px); background-color: rgba(0, 0, 0, 0.45);">
        <div class="modal-content" style="padding: 0; overflow: hidden; max-width: 600px;">
            <div class="modal-header" style="background: #0E544C; color: #fff; padding: 20px; border-bottom: none; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center;">
                    <div style="background: rgba(255,255,255,0.25); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                        <i class="fas fa-edit" style="font-size: 1.2rem;"></i>
                    </div>
                    <div>
                        <h2 class="modal-title" style="margin: 0; font-size: 1.25rem !important; font-weight: bold; color: #fff;">Editar Falta Manual</h2>
                        <p style="margin: 0; font-size: 0.85rem; opacity: 0.75;">Actualiza los detalles de la falta</p>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="cerrarModal()" style="color: #fff; opacity: 0.8;">&times;</button>
            </div>
            <form id="formEditarFalta" method="post" action="ajax/editar_falta_manual.php">
                <input type="hidden" name="editar_falta" value="1">
                <input type="hidden" id="editar_id" name="id">
                <input type="hidden" id="editar_foto_path" name="foto_path_actual">

                <div class="modal-body" style="padding: 20px; background: #f8f9fa;">
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
                        <div id="editar_observaciones_lider" class="info-value"
                            style="background-color: #f8f9fa; padding: 8px; border-radius: 4px; border: 1px solid #ddd; min-height: 40px;">
                        </div>
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
                        <select id="editar_tipo" name="tipo_falta" class="form-select" required
                            onchange="actualizarPorcentajeEdicion(this.value)">
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

                    <!-- Observaciones RRHH (solo para quienes pueden aprobar) -->
                    <?php if ($puedeAprobar): ?>
                        <div class="form-group">
                            <label for="editar_observaciones_rrhh" class="form-label">Observaciones RRHH: *</label>
                            <textarea id="editar_observaciones_rrhh" name="observaciones_rrhh" class="form-textarea"
                                required></textarea>
                        </div>
                    <?php else: ?>
                        <!-- Para sin permisos de aprobar, mostrar solo lectura -->
                        <div class="form-group">
                            <label class="form-label">Observaciones RRHH:</label>
                            <div id="editar_observaciones_rrhh_view" class="info-value"
                                style="background-color: #f8f9fa; padding: 8px; border-radius: 4px; border: 1px solid #ddd; min-height: 40px;">
                            </div>
                            <input type="hidden" id="editar_observaciones_rrhh" name="observaciones_rrhh">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer" style="padding: 20px; background: white; border-top: none; display: flex; justify-content: space-between;">
                    <button type="button" onclick="cerrarModal()" class="btn-modern btn-modern-secondary">Cancelar</button>
                    <?php if ($puedeAprobar): ?>
                        <button type="submit" class="btn-modern btn-modern-primary"><i class="fas fa-save" style="margin-right: 8px;"></i> Guardar Cambios</button>
                    <?php else: ?>
                        <button type="button" class="btn-modern btn-modern-secondary" disabled>Sin permisos para editar</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para consultar marcaciones relacionadas con la falta -->
    <div class="modal" id="modalConsultarMarcacion" style="backdrop-filter: blur(10px); background-color: rgba(0, 0, 0, 0.45);">
        <div class="modal-content" style="max-width: 600px; padding: 0; overflow: hidden;">
            <div class="modal-header" style="background: #0E544C; color: #fff; padding: 20px; border-bottom: none; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center;">
                    <div style="background: rgba(255,255,255,0.25); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                        <i class="fas fa-clock" style="font-size: 1.2rem;"></i>
                    </div>
                    <div>
                        <h2 class="modal-title" style="margin: 0; font-size: 1.25rem !important; font-weight: bold; color: #fff;">Detalles de Marcación</h2>
                        <p style="margin: 0; font-size: 0.85rem; opacity: 0.75;">Consulta el registro de horario</p>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="cerrarModal()" style="color: #fff; opacity: 0.8;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px; background: #f8f9fa;">
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
            <div class="modal-footer" style="padding: 20px; background: white; border-top: none; display: flex; justify-content: flex-end;">
                <button type="button" onclick="cerrarModal()" class="btn-modern btn-modern-secondary">Cerrar</button>
            </div>
        </div>
    </div>

    <script>
        const CONFIG_FALTAS = {
            puedeAprobar: <?= $puedeAprobar ? 'true' : 'false' ?>,
            todasSucursales: <?= $tieneTodasSucursales ? 'true' : 'false' ?>,
            operariosData: [
                { id: 0, nombre: 'Todos los colaboradores' },
                <?php foreach ($operarios as $op): ?>
                                { id: <?php echo $op['CodOperario']; ?>, nombre: '<?php echo addslashes($op['nombre_completo']); ?>' },
                <?php endforeach; ?>
            ]
        };
    </script>
    <script src="js/faltas_manual.js?v=<?= time() ?>"></script>

    <!-- Botón Flotante con opciones -->
    <?php if ($puedeNuevo || $puedeExportar): ?>
        <div class="fab-container">
            <div class="fab-options">
                <?php if ($puedeNuevo): ?>
                    <div class="fab-option" onclick="mostrarModalNuevaFalta()">
                        <span class="fab-label">Nuevo</span>
                        <div class="fab-icon-holder"><i class="fas fa-plus"></i></div>
                    </div>
                <?php endif; ?>

                <?php if ($puedeExportar): ?>
                    <a href="faltas_manual.php?<?= http_build_query([
                        'sucursal' => $sucursalSeleccionada ?? '',
                        'desde' => $fechaDesde,
                        'hasta' => $fechaHasta,
                        'operario' => $operarioSeleccionado,
                        'exportar_vacaciones' => 1
                    ]) ?>" class="fab-option">
                        <span class="fab-label">Descargar Vacaciones</span>
                        <div class="fab-icon-holder"><i class="fas fa-file-excel"></i></div>
                    </a>

                    <a href="faltas_manual.php?<?= http_build_query([
                        'sucursal' => $sucursalSeleccionada ?? '',
                        'desde' => $fechaDesde,
                        'hasta' => $fechaHasta,
                        'operario' => $operarioSeleccionado,
                        'exportar_permisos' => 1
                    ]) ?>" class="fab-option">
                        <span class="fab-label">Descargar Permisos</span>
                        <div class="fab-icon-holder"><i class="fas fa-file-excel"></i></div>
                    </a>

                    <a href="faltas_manual.php?<?= http_build_query([
                        'sucursal' => $sucursalSeleccionada ?? '',
                        'desde' => $fechaDesde,
                        'hasta' => $fechaHasta,
                        'operario' => $operarioSeleccionado,
                        'exportar_faltas_auto_septimo' => 1
                    ]) ?>" class="fab-option">
                        <span class="fab-label">Descargar No reportados + 7mo</span>
                        <div class="fab-icon-holder"><i class="fas fa-file-excel"></i></div>
                    </a>
                <?php endif; ?>
            </div>
            <div class="btn-floating-pitaya" title="Herramientas">
                <i class="fas fa-wrench"></i>
            </div>
        </div>
    <?php endif; ?>
</body>

</html>