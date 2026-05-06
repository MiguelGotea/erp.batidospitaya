<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php';
require_once '../../core/helpers/funciones.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';

// Verificar conexión
if (!$conn) {
    die("Error de conexión a la base de datos");
}

// Inicialización de variables para control de sucursales
$todasSucursales = [];
$sucursales = [];

verificarAutenticacion();

$usuario = obtenerUsuarioActual();

if (!verificarAccesoCargo([5, 46, 43, 16, 8, 13, 28, 39, 30, 37, 49])) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
$cargoOperario = $usuario['CodNivelesCargos'];

// Definir qué cargos ven la vista completa
$cargosVistaCompleta = [13, 28, 39, 30, 37, 5, 43, 46, 49];
$verVistaCompleta = verificarAccesoCargo($cargosVistaCompleta);

// Agrega al inicio del archivo (antes de cualquier output)
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 minutos

// Verificar si se solicitó la exportación a Excel
if (isset($_GET['exportar_excel'])) {
    $sucursalSeleccionada = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 month'));
    $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');

    // Obtener todos los datos SOLO con estado "Justificado"
    $datosCompletos = obtenerTodasTardanzasConOperarios(
        !empty($sucursalSeleccionada) ? $sucursalSeleccionada : null,
        $fechaDesde,
        $fechaHasta
    );

    // Filtrar solo los registros con estado "Justificado"
    $datosJustificados = array_filter($datosCompletos, function ($item) {
        return isset($item['estado']) && $item['estado'] === 'Justificado';
    });

    // Obtener conteo de tardanzas justificadas por operario
    $tardanzasJustificadasPorOperario = contarTardanzasJustificadasPorOperario(
        !empty($sucursalSeleccionada) ? $sucursalSeleccionada : null,
        $fechaDesde,
        $fechaHasta
    );

    // Configurar headers para descarga con rango de fechas
    $nombreArchivo = "tardanzas_justificadas_{$fechaDesde}_a_{$fechaHasta}.xls";
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Código</th>';
    echo '<th>Código Contrato</th>';  // NUEVA COLUMNA
    echo '<th>Colaborador</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha Tardanza</th>';
    // ELIMINAR: <th>Minutos</th>';
    echo '<th>Tipo Justificación</th>';
    echo '<th>Estado</th>';
    echo '<th>Observaciones</th>';
    // ELIMINAR: <th>Registrado por</th>';
    // ELIMINAR: <th>Fecha Registro</th>';
    echo '<th>Total Tardanzas (Sistema)</th>';
    echo '<th>Tardanzas Reportadas</th>';
    echo '<th>Tardanzas Totales</th>'; // NUEVA COLUMNA
    echo '<th>Tardanzas Justificadas</th>';
    echo '<th>Tardanzas Ejecutadas</th>';
    echo '</tr>';

    foreach ($datosJustificados as $item) {
        $nombreCompleto = trim(
            $item['operario_nombre'] . ' ' .
            ($item['operario_nombre2'] ?? '') . ' ' .
            $item['operario_apellido'] . ' ' .
            ($item['operario_apellido2'] ?? '')
        );

        $codOperario = $item['cod_operario'];
        $totalJustificadas = $tardanzasJustificadasPorOperario[$codOperario] ?? 0;
        $tardanzasTotales = ($item['total_sistema'] ?? 0) + ($item['total_reportadas'] ?? 0);
        $diferencia = $tardanzasTotales - $totalJustificadas;

        // Si la diferencia es negativa, establecerla en 0
        if ($diferencia < 0) {
            $diferencia = 0;
        }

        echo '<tr>';
        echo '<td>' . $item['cod_operario'] . '</td>';
        echo '<td>' . ($item['cod_contrato'] ?? '') . '</td>';  // NUEVA COLUMNA
        echo '<td>' . htmlspecialchars($nombreCompleto) . '</td>';
        echo '<td>' . htmlspecialchars($item['sucursal_nombre']) . '</td>';
        echo '<td>' . ($item['fecha_tardanza'] ? formatoFechaCorta($item['fecha_tardanza']) : '-') . '</td>';
        // ELIMINAR: echo '<td>' . ($item['minutos_tardanza'] ?? '-') . '</td>';
        echo '<td>' . ($item['tipo_justificacion'] ? ucfirst(str_replace('_', ' ', $item['tipo_justificacion'])) : '-') . '</td>';
        echo '<td>' . ($item['estado'] ?? '-') . '</td>';
        echo '<td>' . ($item['observaciones'] ? htmlspecialchars($item['observaciones']) : '-') . '</td>';
        // ELIMINAR: echo '<td>' . ($item['registrador_nombre'] ? htmlspecialchars($item['registrador_nombre'] . ' ' . $item['registrador_apellido']) : '-') . '</td>';
        // ELIMINAR: echo '<td>' . ($item['fecha_registro'] ? formatoFechaCorta($item['fecha_registro']) : '-') . '</td>';
        echo '<td>' . ($item['total_sistema'] ?? 0) . '</td>';
        echo '<td>' . ($item['total_reportadas'] ?? 0) . '</td>';
        echo '<td>' . $tardanzasTotales . '</td>'; // NUEVA COLUMNA
        echo '<td>' . $totalJustificadas . '</td>';
        echo '<td>' . $diferencia . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    exit;
}

// Verificar si se solicitó la exportación a Excel para contabilidad
if (isset($_GET['exportar_contabilidad'])) {
    $sucursalSeleccionada = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 month'));
    $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');

    // Obtener todos los datos agrupados por operario
    $tardanzasPorOperario = obtenerTardanzasAgrupadasParaContabilidad(
        !empty($sucursalSeleccionada) ? $sucursalSeleccionada : null,
        $fechaDesde,
        $fechaHasta
    );

    // Configurar headers para descarga con rango de fechas
    $nombreArchivo = "tardanzas_contabilidad_{$fechaDesde}_a_{$fechaHasta}.xls";
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Nombre</th>';
    echo '<th>Código</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha Pago</th>';
    echo '<th>1era quincena</th>';
    echo '<th>2da quincena</th>';
    echo '<th>Tardanzas Ejecutadas</th>';
    echo '<th>Total Tardanzas (Sistema)</th>';
    echo '<th>Tardanzas Justificadas</th>';
    echo '</tr>';

    foreach ($tardanzasPorOperario as $operario) {
        // Calcular tardanzas ejecutadas
        $tardanzasEjecutadas = $operario['total_sistema'] - $operario['total_justificadas'];
        if ($tardanzasEjecutadas < 0)
            $tardanzasEjecutadas = 0;

        // Determinar valor para 2da quincena (mismo que tardanzas ejecutadas)
        $segundaQuincena = $tardanzasEjecutadas;

        echo '<tr>';
        echo '<td>' . htmlspecialchars($operario['nombre_completo']) . '</td>';
        echo '<td>' . $operario['cod_operario'] . '</td>';
        echo '<td>' . htmlspecialchars($operario['sucursal_principal']) . '</td>';
        echo '<td></td>'; // Fecha Pago (vacío)
        echo '<td></td>'; // 1era quincena (vacío)
        echo '<td>' . $segundaQuincena . '</td>';
        echo '<td>' . $tardanzasEjecutadas . '</td>';
        echo '<td>' . $operario['total_sistema'] . '</td>';
        echo '<td>' . $operario['total_justificadas'] . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    exit;
}

/**
 * @param string|int|null $codSucursal
 * @param string $fechaDesde
 * @param string $fechaHasta
 * @return array
 */
function obtenerConteoTardanzasPorOperario($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $conteo = [];

    // 1. Obtener conteo de tardanzas automáticas (calculadas)
    // Primero obtenemos todos los operarios en el rango
    $sqlOperarios = "SELECT DISTINCT o.CodOperario 
                    FROM Operarios o
                    JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                    -- WHERE o.Operativo = 1
                    AND (anc.Fin IS NULL OR anc.Fin >= ?)
                    AND anc.Fecha <= ?";

    $params = [$fechaDesde, $fechaHasta];

    if (!empty($codSucursal)) {
        $sqlOperarios .= " AND anc.Sucursal = ?";
        $params[] = $codSucursal;
    }

    $stmt = $conn->prepare($sqlOperarios);
    $stmt->execute($params);
    $operarios = $stmt->fetchAll();

    foreach ($operarios as $operario) {
        $codOperario = $operario['CodOperario'];

        // Obtener días laborables del operario
        $diasLaborables = obtenerDiasLaborablesOperario(
            $codOperario,
            $codSucursal,
            $fechaDesde,
            $fechaHasta
        );

        $tardanzasAuto = 0;

        foreach ($diasLaborables as $dia) {
            $marcacion = obtenerMarcacionEntrada($codOperario, $dia['fecha']);
            if ($marcacion) {
                $tardanza = verificarTardanza(
                    $codOperario,
                    $codSucursal,
                    $dia['fecha'],
                    $marcacion['hora_ingreso']
                );
                if ($tardanza) {
                    $tardanzasAuto++;
                }
            }
        }

        if ($tardanzasAuto > 0) {
            $conteo[$codOperario]['sistema'] = $tardanzasAuto;
        }
    }

    // 2. Obtener tardanzas manuales por operario
    $sqlManuales = "SELECT cod_operario, COUNT(*) as total 
                   FROM TardanzasManuales 
                   WHERE fecha_tardanza BETWEEN ? AND ?";
    $params = [$fechaDesde, $fechaHasta];

    if (!empty($codSucursal)) {
        $sqlManuales .= " AND cod_sucursal = ?";
        $params[] = $codSucursal;
    }

    $sqlManuales .= " GROUP BY cod_operario";

    $stmt = $conn->prepare($sqlManuales);
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        $conteo[$row['cod_operario']]['reportadas'] = $row['total'];
    }

    return $conteo;
}

$esLider = verificarAccesoCargo([5, 43, 46]);
$esOperaciones = verificarAccesoCargo([11, 8, 28, 39, 30, 37, 13, 49]);
$esSucursales = verificarAccesoCargo([27]);

// AJAX Handler para obtener operarios dinámicamente
if (isset($_GET['action']) && $_GET['action'] == 'obtener_operarios' && isset($_GET['sucursal'])) {
    // CRÍTICO: Limpiar cualquier output previo
    ob_clean(); // Limpia el buffer de salida

    header('Content-Type: application/json');

    $codSucursal = $_GET['sucursal'];
    $fechaTardanza = $_GET['fecha_tardanza'] ?? null;

    // Si NO se proporciona fecha, usar fecha de ayer por defecto
    if (!$fechaTardanza) {
        $fechaTardanza = date('Y-m-d', strtotime('-1 day'));
    }

    // NUEVA LÓGICA: Obtener operarios considerando fecha de liquidación
    $operarios = obtenerOperariosSucursalParaTardanzasPorFecha($codSucursal, $fechaTardanza);

    // Agregar información de contrato para validaciones en frontend
    foreach ($operarios as &$operario) {
        $estadoContrato = obtenerMensajeEstadoContrato($operario['CodOperario']);
        $operario['tiene_contrato'] = operarioTieneContrato($operario['CodOperario']);
        $operario['estado_contrato'] = $estadoContrato['tipo'];
        $operario['mensaje_contrato'] = $estadoContrato['mensaje'];
    }

    echo json_encode($operarios);
    exit(); // IMPORTANTE: Salir inmediatamente
}

/**
 * Obtiene operarios de una sucursal para registrar tardanzas manuales
 * NUEVA FUNCIÓN: Considera fecha específica y fecha de liquidación
 * @param string|int $codSucursal
 * @param string $fechaReferencia
 * @return array
 */
function obtenerOperariosSucursalParaTardanzasPorFecha($codSucursal, $fechaReferencia)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT DISTINCT 
            o.CodOperario, 
            o.Nombre, 
            o.Nombre2,
            o.Apellido, 
            o.Apellido2,
            c.fecha_liquidacion,
            c.CodContrato
        FROM Operarios o
        INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        INNER JOIN marcaciones m ON o.CodOperario = m.CodOperario
        LEFT JOIN (
            -- Subquery para obtener el último contrato de cada operario
            SELECT c1.cod_operario, c1.CodContrato, c1.fecha_liquidacion
            FROM Contratos c1
            INNER JOIN (
                SELECT cod_operario, MAX(CodContrato) as max_contrato
                FROM Contratos
                GROUP BY cod_operario
            ) c2 ON c1.cod_operario = c2.cod_operario AND c1.CodContrato = c2.max_contrato
        ) c ON o.CodOperario = c.cod_operario
        WHERE anc.Sucursal = ?
        AND m.sucursal_codigo = ?
        -- Operarios con marcaciones en los últimos 30 días
        AND m.fecha BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND ?
        AND m.hora_ingreso IS NOT NULL
        -- Verificar que estaba asignado a la sucursal en esa fecha
        AND anc.Fecha <= ?
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        -- Excluir cargo 27
        AND o.CodOperario NOT IN (
            SELECT DISTINCT anc2.CodOperario 
            FROM AsignacionNivelesCargos anc2
            WHERE anc2.CodNivelesCargos = 27
            AND anc2.Fecha <= ?
            AND (anc2.Fin IS NULL OR anc2.Fin >= ?)
        )
        -- FILTRO NUEVO: Solo operarios activos según fecha de liquidación
        AND (
            c.fecha_liquidacion IS NULL 
            OR c.fecha_liquidacion = '0000-00-00'
            OR c.fecha_liquidacion >= ?
        )
        GROUP BY o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2, o.Sucursal
        ORDER BY o.Nombre, o.Apellido, o.Apellido2
    ");

    $stmt->execute([
        $codSucursal,           // Para anc.Sucursal
        $codSucursal,           // Para m.sucursal_codigo
        $fechaReferencia,       // Para DATE_SUB
        $fechaReferencia,       // Para rango de marcaciones
        $fechaReferencia,       // Para anc.Fecha
        $fechaReferencia,       // Para anc.Fin
        $fechaReferencia,       // Para cargo 27 fecha
        $fechaReferencia,       // Para cargo 27 fin
        $fechaReferencia        // Para fecha_liquidacion
    ]);

    return $stmt->fetchAll();
}

/**
 * Obtiene operarios de una sucursal en un rango de fechas
 * MODIFICADA: Filtra por fecha de liquidación
 * @param string|int $codSucursal
 * @param string $fechaDesde
 * @param string $fechaHasta
 * @return array
 */
function obtenerOperariosSucursalEnRango($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, s.nombre as sucursal_nombre,
               c.fecha_liquidacion
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        JOIN sucursales s ON anc.Sucursal = s.codigo
        LEFT JOIN (
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
        -- FILTRO NUEVO: Solo operarios activos según fecha de liquidación
        AND (
            c.fecha_liquidacion IS NULL 
            OR c.fecha_liquidacion = '0000-00-00'
            OR c.fecha_liquidacion > CURDATE()
        )
        ORDER BY o.Nombre, o.Apellido
    ");
    $stmt->execute([$codSucursal, $fechaDesde, $fechaHasta]);
    return $stmt->fetchAll();
}

/**
 * Obtiene información de horarios programados y marcados para una tardanza específica
 */
function obtenerInformacionHorariosTardanza($codOperario, $fechaTardanza)
{
    global $conn;

    // Valores por defecto
    $resultado = [
        'entrada_programada' => 'No',
        'salida_programada' => 'No',
        'entrada_marcada' => 'No marco',
        'salida_marcada' => 'No marco'
    ];

    try {
        // 1. Obtener horario programado
        $semana = obtenerSemanaPorFecha($fechaTardanza);
        if ($semana) {
            $stmt = $conn->prepare("
                SELECT * FROM HorariosSemanalesOperaciones
                WHERE cod_operario = ? 
                AND id_semana_sistema = ?
                LIMIT 1
            ");
            $stmt->execute([$codOperario, $semana['id']]);
            $horario = $stmt->fetch();

            if ($horario) {
                // Obtener día de la semana (1=lunes, 7=domingo)
                $diaSemana = date('N', strtotime($fechaTardanza));
                $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
                $diaNombre = $dias[$diaSemana - 1];

                $columnaEntrada = $diaNombre . '_entrada';
                $columnaSalida = $diaNombre . '_salida';
                $columnaEstado = $diaNombre . '_estado';

                if ($horario[$columnaEstado] === 'Activo' && $horario[$columnaEntrada]) {
                    $resultado['entrada_programada'] = formatoHoraCorta($horario[$columnaEntrada]);
                    $resultado['salida_programada'] = $horario[$columnaSalida] ? formatoHoraCorta($horario[$columnaSalida]) : 'No';
                }
            }
        }

        // 2. Obtener marcaciones
        $stmt = $conn->prepare("
            SELECT hora_ingreso, hora_salida 
            FROM marcaciones 
            WHERE CodOperario = ? 
            AND fecha = ?
            LIMIT 1
        ");
        $stmt->execute([$codOperario, $fechaTardanza]);
        $marcacion = $stmt->fetch();

        if ($marcacion) {
            $resultado['entrada_marcada'] = $marcacion['hora_ingreso'] ? formatoHoraCorta($marcacion['hora_ingreso']) : 'No marco';
            $resultado['salida_marcada'] = $marcacion['hora_salida'] ? formatoHoraCorta($marcacion['hora_salida']) : 'No marco';
        }

    } catch (PDOException $e) {
        error_log("Error al obtener horarios para tardanza: " . $e->getMessage());
    }

    return $resultado;
}

/**
 * Obtiene días laborables de un operario en un rango de fechas
 */
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

                // Solo considerar días con estado "Activo" y con hora de entrada definida
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

function esDiaLaborable($codOperario, $codSucursal, $fecha)
{
    global $conn;

    // Obtener la semana
    $semana = obtenerSemanaPorFecha($fecha);
    if (!$semana)
        return false;

    // Obtener día de la semana (1=lunes, 7=domingo)
    $diaSemana = date('N', strtotime($fecha));
    $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $diaNombre = $dias[$diaSemana - 1];

    // Verificar si está programado para trabajar ese día
    $stmt = $conn->prepare("
        SELECT {$diaNombre}_estado as estado, {$diaNombre}_entrada as entrada
        FROM HorariosSemanalesOperaciones
        WHERE cod_operario = ? 
        AND cod_sucursal = ?
        AND id_semana_sistema = ?
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $codSucursal, $semana['id']]);
    $result = $stmt->fetch();

    return ($result && $result['estado'] === 'Activo' && $result['entrada'] !== null);
}

/**
 * Obtiene marcación de entrada de un operario en una fecha específica
 */
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

// Obtener sucursales según el cargo del usuario
if ($esOperaciones) {
    $todasSucursales = obtenerTodasSucursales();
    $sucursales = $todasSucursales;
    $mostrarTodas = true;
} elseif ($esSucursales || $esLider) {
    // Líder o usuario con cargo 27 solo ve sus sucursales
    $sucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);
    $mostrarTodas = false;

    // Si solo tiene una sucursal, seleccionarla automáticamente
    if (count($sucursales) === 1 && !isset($_GET['sucursal'])) {
        $sucursalSeleccionada = $sucursales[0]['codigo'];
    }
} else {
    // Para otros usuarios (cargo 2, etc.)
    $sucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);
    $mostrarTodas = false;

    // Si solo tiene una sucursal, seleccionarla automáticamente
    if (count($sucursales) === 1 && !isset($_GET['sucursal'])) {
        $sucursalSeleccionada = $sucursales[0]['codigo'];
    }
}

// Obtener todos los operarios para el filtro
$sql_operarios = "SELECT o.CodOperario, 
                 CONCAT(
                     IFNULL(o.Nombre, ''), ' ', 
                     IFNULL(o.Nombre2, ''), ' ', 
                     IFNULL(o.Apellido, ''), ' ', 
                     IFNULL(o.Apellido2, '')
                 ) AS nombre_completo 
                 FROM Operarios o
                 LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                 WHERE (anc.CodNivelesCargos IS NULL OR anc.CodNivelesCargos != 27)
                 -- AND o.Operativo = 1
                 GROUP BY o.CodOperario
                 ORDER BY nombre_completo";
$operarios = $conn->query($sql_operarios)->fetchAll(PDO::FETCH_ASSOC);

// Obtener parámetro de filtro de operario
$operarioSeleccionado = $_GET['operario'] ?? 0;

// Procesar formulario de registro manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_tardanza'])) {
    procesarRegistroTardanzaManual();
}

// Obtener datos para los filtros
$sucursalSeleccionada = $_GET['sucursal'] ?? null;

// Determinar si se debe mostrar el select de sucursal
$mostrarSelectSucursal = $esOperaciones || count($sucursales) > 1;

// Si el usuario NO debe ver el select de sucursal Y no tiene una seleccionada,
// establecer automáticamente su primera sucursal asignada
if (!$mostrarSelectSucursal && empty($sucursalSeleccionada) && !empty($sucursales)) {
    $sucursalSeleccionada = $sucursales[0]['codigo'];
}

// Establecer rango del mes actual por defecto
$hoy = new DateTime();
$primerDiaMes = $hoy->format('Y-m-01');
$ultimoDiaMes = $hoy->format('Y-m-t');

// Solo establecer sucursal por defecto si no es operaciones o si no se ha seleccionado "Todas"
if (!$esOperaciones && empty($sucursalSeleccionada) && count($sucursales) > 0) {
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

// Obtener tardanzas manuales si hay fechas seleccionadas
$tardanzasManuales = [];
$tardanzasNoReportadas = [];

if ($fechaDesde && $fechaHasta) {
    // Para el jefe de operaciones, si la sucursal está vacía o es "todas", pasamos null
    $sucursalParam = (!empty($sucursalSeleccionada) && $sucursalSeleccionada !== "todas") ? $sucursalSeleccionada : null;
    $operarioParam = ($operarioSeleccionado > 0) ? $operarioSeleccionado : null;

    // Obtener tardanzas manuales (ya registradas)
    $tardanzasManuales = obtenerTardanzasManuales($sucursalParam, $fechaDesde, $fechaHasta, $operarioParam);

    // Si tiene acceso a vista completa, obtener tardanzas no reportadas
    if ($verVistaCompleta) {
        $tardanzasNoReportadas = obtenerTardanzasNoReportadas(
            $sucursalParam,
            $fechaDesde,
            $fechaHasta,
            $operarioParam
        );
    }
}

/**
 * Obtiene las tardanzas automáticas (detectadas por sistema) que aún no han sido reportadas manualmente
 * MODIFICADA: Filtra por fecha de liquidación
 */
function obtenerTardanzasNoReportadas($codSucursal = null, $fechaDesde, $fechaHasta, $codOperario = null)
{
    global $conn;

    try {
        // 1. Obtener todos los operarios con asignación en el rango de fechas
        $sqlOperarios = "
            SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Nombre2, o.Apellido2,
                   s.nombre as sucursal_nombre, s.codigo as sucursal_codigo,
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
            WHERE (anc.Fin IS NULL OR anc.Fin >= ?)
            AND anc.Fecha <= ?
        ";

        $params = [$fechaDesde, $fechaHasta];

        // Solo filtrar por sucursal si se especificó y NO es "todas"
        if (!empty($codSucursal) && $codSucursal !== "todas") {
            $sqlOperarios .= " AND anc.Sucursal = ?";
            $params[] = $codSucursal;
        }

        if (!empty($codOperario) && $codOperario > 0) {
            $sqlOperarios .= " AND o.CodOperario = ?";
            $params[] = $codOperario;
        }

        $sqlOperarios .= " ORDER BY o.Nombre, o.Apellido";

        $stmt = $conn->prepare($sqlOperarios);
        $stmt->execute($params);
        $operarios = $stmt->fetchAll();

        $tardanzasNoReportadas = [];

        foreach ($operarios as $operario) {
            $codOperario = $operario['CodOperario'];
            $codSucursalOp = $operario['sucursal_codigo'];

            // NUEVA LÓGICA: Determinar hasta qué fecha buscar tardanzas
            $fechaHastaOperario = $fechaHasta;
            $fechaLiquidacion = $operario['fecha_liquidacion'];

            if (!empty($fechaLiquidacion) && $fechaLiquidacion != '0000-00-00') {
                $fechaLiq = new DateTime($fechaLiquidacion);
                $fechaHastaObj = new DateTime($fechaHasta);

                // Si la fecha de liquidación es anterior al fin del rango, usar liquidación
                if ($fechaLiq < $fechaHastaObj) {
                    $fechaHastaOperario = $fechaLiq->format('Y-m-d');
                }

                // Si la fecha de liquidación es antes del inicio del rango, saltar este operario
                $fechaDesdeObj = new DateTime($fechaDesde);
                if ($fechaLiq < $fechaDesdeObj) {
                    continue; // Este operario ya estaba liquidado en el período
                }
            }

            // 2. Obtener días laborables del operario en el rango (ajustado por liquidación)
            $diasLaborables = obtenerDiasLaborablesOperario(
                $codOperario,
                $codSucursalOp,
                $fechaDesde,
                $fechaHastaOperario
            );

            foreach ($diasLaborables as $dia) {
                // 3. Verificar si hay tardanza real
                $tardanzaReal = verificarTardanzaReal($codOperario, $codSucursalOp, $dia['fecha']);

                if ($tardanzaReal['hubo_tardanza'] && $tardanzaReal['minutos_tardanza'] > 0) {
                    // 4. Verificar si ya existe una tardanza manual registrada para esta fecha
                    $tardanzaRegistrada = verificarTardanzaRegistrada($codOperario, $dia['fecha']);

                    if (!$tardanzaRegistrada) {
                        // 5. Obtener información de horarios para mostrar
                        $horariosInfo = obtenerInformacionHorariosTardanza($codOperario, $dia['fecha']);

                        $tardanzasNoReportadas[] = [
                            'id' => null,
                            'cod_operario' => $codOperario,
                            'operario_nombre' => $operario['Nombre'],
                            'operario_nombre2' => $operario['Nombre2'] ?? '',
                            'operario_apellido' => $operario['Apellido'],
                            'operario_apellido2' => $operario['Apellido2'] ?? '',
                            'sucursal_nombre' => $operario['sucursal_nombre'],
                            'fecha_tardanza' => $dia['fecha'],
                            'minutos_tardanza' => $tardanzaReal['minutos_tardanza'],
                            'tipo_justificacion' => null,
                            'estado' => 'No Reportada',
                            'observaciones' => null,
                            'registrador_nombre' => null,
                            'registrador_apellido' => null,
                            'fecha_registro' => null,
                            'foto_path' => null,
                            'horario_entrada_programada' => $horariosInfo['entrada_programada'],
                            'horario_salida_programada' => $horariosInfo['salida_programada'],
                            'horario_entrada_marcada' => $horariosInfo['entrada_marcada'],
                            'horario_salida_marcada' => $horariosInfo['salida_marcada'],
                            'es_no_reportada' => true,
                            'fecha_liquidacion' => $fechaLiquidacion
                        ];
                    }
                }
            }
        }

        return $tardanzasNoReportadas;

    } catch (PDOException $e) {
        error_log("Error al obtener tardanzas no reportadas: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica si ya existe una tardanza manual registrada para un operario en una fecha
 */
function verificarTardanzaRegistrada($codOperario, $fecha)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT id FROM TardanzasManuales 
        WHERE cod_operario = ? AND fecha_tardanza = ?
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $fecha]);

    return $stmt->fetch() !== false;
}

// Calcular totales para los indicadores
$totalTardanzasAuto = 0;
$totalTardanzasManualesRegistradas = 0;
$tardanzasPendientes = 0;

if ($sucursalSeleccionada || ($esOperaciones && empty($sucursalSeleccionada))) {
    if ($esOperaciones && empty($sucursalSeleccionada)) {
        // Modo "todas" - sumar todas las sucursales
        $totalTardanzasAuto = 0;
        $totalTardanzasManualesRegistradas = 0;

        foreach ($todasSucursales as $suc) {
            $totalTardanzasAuto += obtenerTotalTardanzasAutomaticas($suc['codigo'], $fechaDesde, $fechaHasta);
            $totalTardanzasManualesRegistradas += obtenerTotalTardanzasManuales($suc['codigo'], $fechaDesde, $fechaHasta);
        }
    } else {
        // Modo sucursal específica
        $totalTardanzasAuto = obtenerTotalTardanzasAutomaticas($sucursalSeleccionada, $fechaDesde, $fechaHasta);
        $totalTardanzasManualesRegistradas = obtenerTotalTardanzasManuales($sucursalSeleccionada, $fechaDesde, $fechaHasta);
    }

    $tardanzasPendientes = $totalTardanzasAuto - $totalTardanzasManualesRegistradas;
    if ($tardanzasPendientes < 0)
        $tardanzasPendientes = 0; // Por si hay más manuales que automáticas
}

/**
 * Obtiene el total de tardanzas automáticas
 * MODIFICADA: Filtra por fecha de liquidación
 */
function obtenerTotalTardanzasAutomaticas($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    // 1. Obtener todos los operarios asignados a la sucursal en el rango de fechas
    $sqlOperarios = "
        SELECT DISTINCT o.CodOperario,
               c.fecha_liquidacion
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        LEFT JOIN (
            SELECT c1.cod_operario, c1.fecha_liquidacion
            FROM Contratos c1
            INNER JOIN (
                SELECT cod_operario, MAX(CodContrato) as max_contrato
                FROM Contratos
                GROUP BY cod_operario
            ) c2 ON c1.cod_operario = c2.cod_operario AND c1.CodContrato = c2.max_contrato
        ) c ON o.CodOperario = c.cod_operario
        WHERE (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
    ";

    $params = [$fechaDesde, $fechaHasta];

    if (!empty($codSucursal)) {
        $sqlOperarios .= " AND anc.Sucursal = ?";
        $params[] = $codSucursal;
    }

    $stmt = $conn->prepare($sqlOperarios);
    $stmt->execute($params);
    $operarios = $stmt->fetchAll();

    $totalTardanzas = 0;

    foreach ($operarios as $operario) {
        // NUEVA LÓGICA: Determinar hasta qué fecha contar tardanzas
        $fechaHastaOperario = $fechaHasta;
        $fechaLiquidacion = $operario['fecha_liquidacion'];

        if (!empty($fechaLiquidacion) && $fechaLiquidacion != '0000-00-00') {
            $fechaLiq = new DateTime($fechaLiquidacion);
            $fechaHastaObj = new DateTime($fechaHasta);

            if ($fechaLiq < $fechaHastaObj) {
                $fechaHastaOperario = $fechaLiq->format('Y-m-d');
            }

            $fechaDesdeObj = new DateTime($fechaDesde);
            if ($fechaLiq < $fechaDesdeObj) {
                continue;
            }
        }

        // 2. Para cada operario, verificar días laborables en el rango (ajustado)
        $diasLaborables = obtenerDiasLaborablesOperario(
            $operario['CodOperario'],
            $codSucursal,
            $fechaDesde,
            $fechaHastaOperario
        );

        foreach ($diasLaborables as $dia) {
            // 3. Verificar si hay marcación de entrada para ese día
            $marcacion = obtenerMarcacionEntrada($operario['CodOperario'], $dia['fecha']);

            if ($marcacion) {
                // 4. Verificar si hay tardanza (solo si llegó DESPUÉS de la hora programada)
                $horaProgramada = new DateTime($dia['hora_entrada']);
                $horaMarcada = new DateTime($marcacion['hora_ingreso']);

                // Calcular diferencia (positiva si llegó después, negativa si llegó antes)
                $diferencia = $horaMarcada->diff($horaProgramada);
                $minutos = ($diferencia->h * 60 + $diferencia->i) * ($horaMarcada > $horaProgramada ? 1 : -1);

                // Solo contar si llegó más de 1 minuto DESPUÉS
                if ($minutos > 1) {
                    $totalTardanzas++;
                }
            }
        }
    }

    return $totalTardanzas;
}

/**
 * Verifica si hay tardanza en una marcación específica
 * MODIFICADA: Solo tardanza si llegó DESPUÉS de la hora programada + 1 minuto
 */
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

    // Si no hay horario programado, no es día laborable → no hay tardanza
    if (!$horarioProgramado || !$horarioProgramado['hora_entrada'] || $horarioProgramado['estado'] !== 'Activo') {
        return false;
    }

    $horaProgramada = new DateTime($horarioProgramado['hora_entrada']);
    $horaMarcada = new DateTime($horaMarcada);

    // Calcular diferencia en minutos (positiva si llegó después, negativa si llegó antes)
    $diferencia = $horaMarcada->diff($horaProgramada);
    $minutos = ($diferencia->h * 60 + $diferencia->i) * ($horaMarcada > $horaProgramada ? 1 : -1);

    // Solo tardanzas de más de 1 minuto DESPUÉS de la hora programada
    if ($minutos > 1) {
        return [
            'minutos' => $minutos,
            'hora_entrada_programada' => $horarioProgramado['hora_entrada'],
            'hora_entrada_marcada' => $horaMarcada->format('H:i:s')
        ];
    }

    return false;
}

// Función para obtener el total de tardanzas manuales registradas
function obtenerTotalTardanzasManuales($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "SELECT COUNT(*) as total FROM TardanzasManuales WHERE fecha_tardanza BETWEEN ? AND ?";
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

function obtenerTodasTardanzasConOperarios($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    try {
        // 1. Obtener todos los operarios (activos e inactivos) que tuvieron asignación en el rango de fechas
        $sqlOperarios = "
            SELECT DISTINCT o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2, 
                   s.nombre AS sucursal_nombre
            FROM Operarios o
            JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            JOIN sucursales s ON anc.Sucursal = s.codigo
            WHERE (anc.Fin IS NULL OR anc.Fin >= ?)
            AND anc.Fecha <= ?
        ";

        $params = [$fechaDesde, $fechaHasta];

        if (!empty($codSucursal)) {
            $sqlOperarios .= " AND anc.Sucursal = ?";
            $params[] = $codSucursal;
        }

        $sqlOperarios .= " ORDER BY o.Nombre, o.Apellido";

        $stmt = $conn->prepare($sqlOperarios);
        $stmt->execute($params);
        $operarios = $stmt->fetchAll();

        // 2. Obtener tardanzas manuales
        $tardanzasManuales = obtenerTardanzasManuales($codSucursal, $fechaDesde, $fechaHasta);

        // 3. Obtener conteo de tardanzas automáticas
        $conteoTardanzas = obtenerConteoTardanzasPorOperario($codSucursal, $fechaDesde, $fechaHasta);

        // 4. Combinar la información
        $resultado = [];

        foreach ($operarios as $operario) {
            $codOperario = $operario['CodOperario'];
            $nombreCompleto = trim(
                $operario['Nombre'] . ' ' .
                ($operario['Nombre2'] ?? '') . ' ' .
                $operario['Apellido'] . ' ' .
                ($operario['Apellido2'] ?? '')
            );

            // Buscar tardanzas manuales para este operario
            $tardanzasOperario = array_filter($tardanzasManuales, function ($tm) use ($codOperario) {
                return $tm['cod_operario'] == $codOperario;
            });

            // Si no tiene tardanzas manuales, crear registro base
            if (empty($tardanzasOperario)) {
                $resultado[] = [
                    'cod_operario' => $codOperario,
                    'operario_nombre' => $operario['Nombre'],
                    'operario_nombre2' => $operario['Nombre2'] ?? '',
                    'operario_apellido' => $operario['Apellido'],
                    'operario_apellido2' => $operario['Apellido2'] ?? '',
                    'sucursal_nombre' => $operario['sucursal_nombre'],
                    'fecha_tardanza' => null,
                    'minutos_tardanza' => null,
                    'tipo_justificacion' => null,
                    'estado' => null,
                    'observaciones' => null,
                    'registrador_nombre' => null,
                    'registrador_apellido' => null,
                    'fecha_registro' => null,
                    'total_sistema' => $conteoTardanzas[$codOperario]['sistema'] ?? 0,
                    'total_reportadas' => $conteoTardanzas[$codOperario]['reportadas'] ?? 0
                ];
            } else {
                // Si tiene tardanzas manuales, agregar cada una
                foreach ($tardanzasOperario as $tm) {
                    $resultado[] = [
                        'cod_operario' => $codOperario,
                        'operario_nombre' => $tm['operario_nombre'],
                        'operario_nombre2' => $tm['operario_nombre2'] ?? '',
                        'operario_apellido' => $tm['operario_apellido'],
                        'operario_apellido2' => $tm['operario_apellido2'] ?? '',
                        'sucursal_nombre' => $tm['sucursal_nombre'],
                        'fecha_tardanza' => $tm['fecha_tardanza'],
                        'minutos_tardanza' => $tm['minutos_tardanza'],
                        'tipo_justificacion' => $tm['tipo_justificacion'],
                        'estado' => $tm['estado'],
                        'observaciones' => $tm['observaciones'] ?? null,
                        'registrador_nombre' => $tm['registrador_nombre'],
                        'registrador_apellido' => $tm['registrador_apellido'],
                        'fecha_registro' => $tm['fecha_registro'],
                        'total_sistema' => $conteoTardanzas[$codOperario]['sistema'] ?? 0,
                        'total_reportadas' => $conteoTardanzas[$codOperario]['reportadas'] ?? 0
                    ];
                }
            }
        }

        return $resultado;
    } catch (PDOException $e) {
        error_log("Excepción al obtener tardanzas completas: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene las tardanzas manuales registradas - CORREGIDA para manejar "todas"
 */
function obtenerTardanzasManuales($codSucursal = null, $fechaDesde, $fechaHasta, $codOperario = null)
{
    global $conn, $esOperaciones;

    try {
        $sql = "
            SELECT tm.*, 
                   o.Nombre AS operario_nombre, 
                   o.Nombre2 AS operario_nombre2,
                   o.Apellido AS operario_apellido,
                   o.Apellido2 AS operario_apellido2,
                   s.nombre AS sucursal_nombre,
                   r.Nombre AS registrador_nombre,
                   r.Apellido AS registrador_apellido,
                   tm.cod_contrato
            FROM TardanzasManuales tm
            JOIN Operarios o ON tm.cod_operario = o.CodOperario
            JOIN sucursales s ON tm.cod_sucursal = s.codigo
            JOIN Operarios r ON tm.registrado_por = r.CodOperario
            WHERE tm.fecha_tardanza BETWEEN ? AND ?
        ";

        $params = [$fechaDesde, $fechaHasta];

        // Solo agregar filtro por sucursal si se especificó una Y NO es vacía/cadena vacía
        if (!empty($codSucursal) && $codSucursal !== "" && $codSucursal !== "todas") {
            $sql .= " AND tm.cod_sucursal = ?";
            $params[] = $codSucursal;
        }

        // Agregar filtro por operario si se especificó
        if (!empty($codOperario) && $codOperario > 0) {
            $sql .= " AND tm.cod_operario = ?";
            $params[] = $codOperario;
        }

        $sql .= " ORDER BY tm.fecha_tardanza DESC, o.Nombre, o.Apellido, o.Apellido2";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            error_log("Error al preparar la consulta: " . implode(" ", $conn->errorInfo()));
            return [];
        }

        if (!$stmt->execute($params)) {
            error_log("Error al ejecutar la consulta: " . implode(" ", $stmt->errorInfo()));
            return [];
        }

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Excepción al obtener tardanzas manuales: " . $e->getMessage());
        return [];
    }
}

function obtenerTodasTardanzasParaContabilidad($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    // 1. Obtener todos los operarios (activos e inactivos) que tuvieron asignación en el rango
    $sqlOperarios = "
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2,
               s.nombre as sucursal_nombre, s.codigo as sucursal_codigo
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        JOIN sucursales s ON anc.Sucursal = s.codigo
        WHERE (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
    ";

    $params = [$fechaDesde, $fechaHasta];

    if (!empty($codSucursal)) {
        $sqlOperarios .= " AND anc.Sucursal = ?";
        $params[] = $codSucursal;
    }

    $sqlOperarios .= " ORDER BY o.Nombre, o.Apellido, o.Apellido2";

    $stmt = $conn->prepare($sqlOperarios);
    $stmt->execute($params);
    $operarios = $stmt->fetchAll();

    $resultado = [];

    foreach ($operarios as $operario) {
        $codOperario = $operario['CodOperario'];
        $sucursalCodigo = $operario['sucursal_codigo'];

        // 2. Obtener días laborables en el rango
        $diasLaborables = obtenerDiasLaborablesOperario(
            $codOperario,
            $sucursalCodigo,
            $fechaDesde,
            $fechaHasta
        );

        $tardanzasOperario = [];

        foreach ($diasLaborables as $dia) {
            // 3. Verificar si hay marcación de entrada para ese día
            $marcacion = obtenerMarcacionEntrada($codOperario, $dia['fecha']);

            if ($marcacion) {
                // 4. Verificar si hay tardanza
                $tardanza = verificarTardanza(
                    $codOperario,
                    $sucursalCodigo,
                    $dia['fecha'],
                    $marcacion['hora_ingreso']
                );

                if ($tardanza) {
                    // Calcular minutos de tardanza
                    $horaProgramada = new DateTime($tardanza['hora_entrada_programada']);
                    $horaMarcada = new DateTime($marcacion['hora_ingreso']);
                    $diferencia = $horaMarcada->diff($horaProgramada);
                    $minutosTardanza = $diferencia->h * 60 + $diferencia->i;

                    $tardanzasOperario[] = [
                        'fecha' => $dia['fecha'],
                        'minutos' => $minutosTardanza,
                        'hora_entrada_programada' => $tardanza['hora_entrada_programada'],
                        'hora_entrada_marcada' => $marcacion['hora_ingreso']
                    ];
                }
            }
        }

        // 5. Obtener tardanzas manuales "No Válido" para este operario
        $tardanzasNoValidas = obtenerTardanzasManualesNoValidasOperario(
            $codOperario,
            $sucursalCodigo,
            $fechaDesde,
            $fechaHasta
        );

        // 6. Calcular tardanzas pendientes (automáticas - no válidas)
        $tardanzasPendientes = count($tardanzasOperario) - count($tardanzasNoValidas);
        if ($tardanzasPendientes < 0)
            $tardanzasPendientes = 0;

        // 7. Agregar al resultado solo si hay tardanzas pendientes
        if ($tardanzasPendientes > 0 || !empty($tardanzasOperario) || !empty($tardanzasNoValidas)) {
            $resultado[] = [
                'cod_operario' => $codOperario,
                'nombre_completo' => trim(
                    $operario['Nombre'] . ' ' .
                    ($operario['Nombre2'] ?? '') . ' ' .
                    $operario['Apellido'] . ' ' .
                    ($operario['Apellido2'] ?? '')
                ),
                'sucursal' => $operario['sucursal_nombre'],
                'total_tardanzas' => $tardanzasPendientes,
                'total_sistema' => count($tardanzasOperario),
                'total_no_validas' => count($tardanzasNoValidas),
                'detalles' => $tardanzasOperario
            ];
        }
    }

    return $resultado;
}

// Nueva función auxiliar
function obtenerTardanzasManualesNoValidasOperario($codOperario, $codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "
        SELECT * FROM TardanzasManuales
        WHERE cod_operario = ?
        AND estado = 'No Válido'
        AND fecha_tardanza BETWEEN ? AND ?
    ";

    $params = [$codOperario, $fechaDesde, $fechaHasta];

    if (!empty($codSucursal)) {
        $sql .= " AND cod_sucursal = ?";
        $params[] = $codSucursal;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function procesarRegistroTardanzaManual()
{
    global $conn, $esLider;

    // Validar fecha no sea futura ni actual
    $fechaTardanza = $_POST['fecha_tardanza'];
    $fechaMaximaPermitida = date('Y-m-d', strtotime('-1 day'));

    if ($fechaTardanza > $fechaMaximaPermitida) {
        $_SESSION['error'] = 'No se pueden registrar tardanzas para fechas futuras ni para el día actual. Solo se permiten fechas hasta: ' . formatoFechaCorta($fechaMaximaPermitida);
        header('Location: tardanzas_manual.php?' . http_build_query([
            'sucursal' => $_GET['sucursal'] ?? '',
            'desde' => $_GET['desde'] ?? '',
            'hasta' => $_GET['hasta'] ?? ''
        ]));
        exit();
    }

    // Solo líderes pueden registrar nuevas tardanzas
    if (!$esLider) {
        $_SESSION['error'] = 'Solo los líderes pueden registrar nuevas tardanzas manuales';
        header('Location: tardanzas_manual.php');
        exit();
    }

    $codOperario = (int) $_POST['cod_operario'];
    $codSucursal = $_POST['cod_sucursal'];

    // NUEVA VALIDACIÓN: Verificar que la fecha no sea posterior a liquidación
    if (fechaPosteriorLiquidacion($codOperario, $fechaTardanza)) {
        $_SESSION['error'] = 'No se puede registrar tardanza: El colaborador fue liquidado antes de esta fecha';
        header('Location: tardanzas_manual.php?' . http_build_query([
            'sucursal' => $_GET['sucursal'] ?? '',
            'desde' => $_GET['desde'] ?? '',
            'hasta' => $_GET['hasta'] ?? ''
        ]));
        exit();
    }

    // NUEVA VALIDACIÓN: Verificar que el operario tenga contrato
    if (!operarioTieneContrato($codOperario)) {
        $_SESSION['error'] = 'Este colaborador no tiene registro de contrato. Por favor contactar con el área de RH.';
        header('Location: tardanzas_manual.php?' . http_build_query([
            'sucursal' => $_GET['sucursal'] ?? '',
            'desde' => $_GET['desde'] ?? '',
            'hasta' => $_GET['hasta'] ?? ''
        ]));
        exit();
    }

    // VALIDACIÓN MEJORADA: Verificar si ya existe una tardanza para este operario en esta fecha
    $stmt = $conn->prepare("
        SELECT id, estado FROM TardanzasManuales 
        WHERE cod_operario = ? AND fecha_tardanza = ?
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $fechaTardanza]);

    if ($tardanzaExistente = $stmt->fetch()) {
        $estado = $tardanzaExistente['estado'];
        $_SESSION['error'] = "Ya existe un registro de tardanza para este colaborador en la fecha seleccionada (Estado: $estado).";

        header('Location: tardanzas_manual.php?' . http_build_query([
            'sucursal' => $_GET['sucursal'] ?? '',
            'desde' => $_GET['desde'] ?? '',
            'hasta' => $_GET['hasta'] ?? ''
        ]));
        exit();
    }

    // NUEVA VALIDACIÓN: Verificar si realmente hubo una tardanza
    $tardanzaReal = verificarTardanzaReal($codOperario, $codSucursal, $fechaTardanza);

    if (!$tardanzaReal['hubo_tardanza']) {
        $mensajeError = '';

        switch ($tardanzaReal['tipo_error']) {
            case 'sin_horario':
                $mensajeError = 'No se puede registrar una tardanza manual porque el colaborador no tenía horario programado para esta fecha.';
                break;
            case 'sin_marcacion':
                $mensajeError = 'No se puede registrar una tardanza manual porque no hay marcaciones de entrada para esta fecha.';
                break;
            case 'a_tiempo':
                $mensajeError = 'No se puede registrar una tardanza manual porque el colaborador llegó a tiempo o antes de la hora programada.';
                break;
            case 'minuto_gracia':
                $mensajeError = 'No se puede registrar una tardanza manual porque el colaborador llegó dentro del minuto de gracia permitido.';
                break;
            default:
                $mensajeError = 'No se puede registrar una tardanza manual porque no se detectó una tardanza real.';
        }

        $_SESSION['error'] = $mensajeError;

        header('Location: tardanzas_manual.php?' . http_build_query([
            'sucursal' => $_GET['sucursal'] ?? '',
            'desde' => $_GET['desde'] ?? '',
            'hasta' => $_GET['hasta'] ?? ''
        ]));
        exit();
    }

    // Modifica la validación de la foto en procesarRegistroTardanzaManual()
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Debe subir una foto como evidencia de la tardanza';
        header('Location: tardanzas_manual.php?' . http_build_query([
            'sucursal' => $_GET['sucursal'] ?? '',
            'desde' => $_GET['desde'] ?? '',
            'hasta' => $_GET['hasta'] ?? ''
        ]));
        exit();
    }

    try {
        $codOperario = (int) $_POST['cod_operario'];
        $fechaTardanza = $_POST['fecha_tardanza'];
        $codSucursal = $_POST['cod_sucursal'];
        $tipoJustificacion = $_POST['tipo_justificacion'];
        $observaciones = $_POST['observaciones'] ?? null;

        // OBTENER EL ÚLTIMO CÓDIGO DE CONTRATO
        $codContrato = obtenerUltimoCodigoContrato($codOperario);

        // Procesar la foto si se subió
        $fotoPath = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/tardanzas/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileExt = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $fileName = 'tardanza_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $filePath)) {
                $fotoPath = $fileName;
            }
        }

        // Insertar nuevo registro (sin minutos_tardanza)
        $stmt = $conn->prepare("
            INSERT INTO TardanzasManuales (
                cod_operario, fecha_tardanza, cod_sucursal, 
                tipo_justificacion, observaciones,
                foto_path, registrado_por, cod_contrato
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $codOperario,
            $fechaTardanza,
            $codSucursal,
            $tipoJustificacion,
            $observaciones,
            $fotoPath,
            $_SESSION['usuario_id'],
            $codContrato
        ]);

        $_SESSION['exito'] = 'Tardanza manual registrada correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al registrar la tardanza manual: ' . $e->getMessage();
    }

    header('Location: tardanzas_manual.php?' . http_build_query([
        'sucursal' => $_GET['sucursal'] ?? '',
        'desde' => $_GET['desde'] ?? '',
        'hasta' => $_GET['hasta'] ?? ''
    ]));
    exit();
}

/**
 * Verifica si realmente hubo una tardanza
 * MODIFICADA: Solo considera tardanza si llegó DESPUÉS de la hora programada + 1 minuto de gracia
 *             Y si la fecha NO es posterior a la fecha de liquidación
 */
function verificarTardanzaReal($codOperario, $codSucursal, $fecha)
{
    global $conn;

    $resultado = [
        'hubo_tardanza' => false,
        'tiene_marcacion' => false,
        'minutos_tardanza' => 0,
        'tipo_error' => 'sin_marcacion',
        'hora_programada' => null,
        'hora_marcada' => null,
        'operario_activo' => true
    ];

    // 0. Primero verificar si el operario está activo según fecha de liquidación
    if (fechaPosteriorLiquidacion($codOperario, $fecha)) {
        $resultado['tipo_error'] = 'operario_liquidado';
        $resultado['operario_activo'] = false;
        $resultado['mensaje_error'] = 'El colaborador fue liquidado antes de esta fecha. No se puede registrar tardanza.';
        return $resultado;
    }

    // 1. Verificar si el operario tenía horario programado para ese día
    $semana = obtenerSemanaPorFecha($fecha);
    if (!$semana) {
        $resultado['tipo_error'] = 'sin_horario';
        return $resultado;
    }

    // Obtener el horario programado para ese día específico
    $horarioProgramado = obtenerHorarioOperacionesPorDia(
        $codOperario,
        $semana['id'],
        $codSucursal,
        $fecha
    );

    // Si no hay horario programado, no es día laborable → no hay tardanza
    if (!$horarioProgramado || !$horarioProgramado['hora_entrada'] || $horarioProgramado['estado'] !== 'Activo') {
        $resultado['tipo_error'] = 'sin_horario';
        return $resultado;
    }

    $resultado['hora_programada'] = $horarioProgramado['hora_entrada'];

    // 2. Obtener marcaciones del operario para esa fecha
    $marcacion = obtenerMarcacionEntrada($codOperario, $fecha);

    if (!$marcacion || !$marcacion['hora_ingreso']) {
        // No tiene marcación de entrada
        $resultado['tipo_error'] = 'sin_marcacion';
        return $resultado;
    }

    $resultado['tiene_marcacion'] = true;
    $resultado['hora_marcada'] = $marcacion['hora_ingreso'];

    // 3. Verificar si hay tardanza comparando con el horario programado
    $horaProgramada = new DateTime($horarioProgramado['hora_entrada']);
    $horaMarcada = new DateTime($marcacion['hora_ingreso']);

    // Calcular diferencia en minutos (positiva si llegó después, negativa si llegó antes)
    $diferencia = $horaMarcada->diff($horaProgramada);
    $minutos = ($diferencia->h * 60 + $diferencia->i) * ($horaMarcada > $horaProgramada ? 1 : -1);

    // Si llegó antes o exactamente a tiempo
    if ($minutos <= 0) {
        $resultado['tipo_error'] = 'a_tiempo';
        $resultado['minutos_tardanza'] = 0;
        return $resultado;
    }

    // Si está en el minuto de gracia (1 minuto)
    if ($minutos == 1) {
        $resultado['tipo_error'] = 'minuto_gracia';
        $resultado['minutos_tardanza'] = 0;
        return $resultado;
    }

    // Considerar tardanza solo si es mayor a 1 minuto DESPUÉS de la hora programada
    if ($minutos > 1) {
        $resultado['hubo_tardanza'] = true;
        $resultado['minutos_tardanza'] = $minutos;
    }

    return $resultado;
}

/**
 * Obtiene operarios de una sucursal para registrar tardanzas manuales
 * MODIFICADA: Considera solo operarios ACTIVOS según fecha de liquidación
 */
function obtenerOperariosSucursalParaTardanzas($codSucursal)
{
    global $conn;

    // Obtener la fecha de hoy y hace 30 días para buscar marcaciones recientes
    $hoy = date('Y-m-d');
    $hace30Dias = date('Y-m-d', strtotime('-30 days'));

    $stmt = $conn->prepare("
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2, o.Sucursal
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        JOIN marcaciones m ON o.CodOperario = m.CodOperario
        WHERE anc.Sucursal = ?
        AND m.sucursal_codigo = ?
        AND m.fecha BETWEEN ? AND ?
        AND m.hora_ingreso IS NOT NULL
        AND (
            anc.Fin IS NULL 
            OR anc.Fin >= ?  -- Si la fecha de fin es mayor o igual a hoy
        )
        AND o.CodOperario NOT IN (
            SELECT DISTINCT CodOperario 
            FROM AsignacionNivelesCargos 
            WHERE CodNivelesCargos = 27
            AND (Fin IS NULL OR Fin >= ?) -- Excluir operarios con cargo 27 (inactivos)
        )
        -- FILTRO NUEVO: Solo operarios activos según fecha de liquidación
        AND o.CodOperario IN (
            SELECT c.cod_operario 
            FROM Contratos c
            WHERE (c.fecha_liquidacion IS NULL 
                   OR c.fecha_liquidacion = '0000-00-00'
                   OR c.fecha_liquidacion > CURDATE())
            AND c.cod_operario = o.CodOperario
        )
        GROUP BY o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2, o.Sucursal
        ORDER BY o.Nombre, o.Apellido, o.Apellido2
    ");

    // Ejecutar con los parámetros: sucursal, sucursal_marcaciones, fecha_inicio, fecha_fin, fecha_fin_activos
    $stmt->execute([$codSucursal, $codSucursal, $hace30Dias, $hoy, $hoy, $hoy]);

    return $stmt->fetchAll();
}

/**
 * Obtiene todas las tardanzas automáticas (detectadas por el sistema) para el reporte de contabilidad
 */
function obtenerTardanzasAutomaticasParaContabilidad($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    // 1. Obtener todos los operarios asignados a la sucursal en el rango de fechas
    $sqlOperarios = "
        SELECT DISTINCT o.CodOperario, o.Nombre as operario_nombre, o.Nombre2 as operario_nombre2, 
               o.Apellido as operario_apellido, o.Apellido2 as operario_apellido2,
               s.nombre as sucursal_nombre
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        JOIN sucursales s ON anc.Sucursal = s.codigo
        -- WHERE o.Operativo = 1
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
    ";

    $params = [$fechaDesde, $fechaHasta];

    if (!empty($codSucursal)) {
        $sqlOperarios .= " AND anc.Sucursal = ?";
        $params[] = $codSucursal;
    }

    $sqlOperarios .= " ORDER BY o.Nombre, o.Apellido, o.Apellido2";

    $stmt = $conn->prepare($sqlOperarios);
    $stmt->execute($params);
    $operarios = $stmt->fetchAll();

    $tardanzas = [];

    foreach ($operarios as $operario) {
        // 2. Para cada operario, verificar días laborables en el rango
        $diasLaborables = obtenerDiasLaborablesOperario(
            $operario['CodOperario'],
            $operario['Sucursal'] ?? $codSucursal,
            $fechaDesde,
            $fechaHasta
        );

        foreach ($diasLaborables as $dia) {
            // 3. Verificar si hay marcación de entrada para ese día
            $marcacion = obtenerMarcacionEntrada($operario['CodOperario'], $dia['fecha']);

            if ($marcacion) {
                // 4. Verificar si hay tardanza comparando con el horario programado
                $tardanza = verificarTardanza(
                    $operario['CodOperario'],
                    $operario['Sucursal'] ?? $codSucursal,
                    $dia['fecha'],
                    $marcacion['hora_ingreso']
                );

                if ($tardanza) {
                    // Calcular minutos de tardanza
                    $horaProgramada = new DateTime($tardanza['hora_entrada_programada']);
                    $horaMarcada = new DateTime($marcacion['hora_ingreso']);
                    $diferencia = $horaMarcada->diff($horaProgramada);
                    $minutosTardanza = $diferencia->h * 60 + $diferencia->i;

                    $tardanzas[] = [
                        'cod_operario' => $operario['CodOperario'],
                        'operario_nombre' => $operario['operario_nombre'],
                        'operario_nombre2' => $operario['operario_nombre2'],
                        'operario_apellido' => $operario['operario_apellido'],
                        'operario_apellido2' => $operario['operario_apellido2'],
                        'sucursal_nombre' => $operario['sucursal_nombre'],
                        'fecha_tardanza' => $dia['fecha'],
                        'minutos_tardanza' => $minutosTardanza,
                        'hora_entrada_programada' => $tardanza['hora_entrada_programada'],
                        'hora_entrada_marcada' => $marcacion['hora_ingreso']
                    ];
                }
            }
        }
    }

    return $tardanzas;
}

/**
 * Obtiene las tardanzas manuales con estado "No Válido" para restar de las automáticas
 */
function obtenerTardanzasManualesNoValidas($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "
        SELECT tm.cod_operario, tm.fecha_tardanza, tm.minutos_tardanza,
               o.Nombre as operario_nombre, o.Nombre2 as operario_nombre2,
               o.Apellido as operario_apellido, o.Apellido2 as operario_apellido2,
               s.nombre as sucursal_nombre
        FROM TardanzasManuales tm
        JOIN Operarios o ON tm.cod_operario = o.CodOperario
        JOIN sucursales s ON tm.cod_sucursal = s.codigo
        WHERE tm.estado = 'No Válido'
        AND tm.fecha_tardanza BETWEEN ? AND ?
    ";

    $params = [$fechaDesde, $fechaHasta];

    if (!empty($codSucursal)) {
        $sql .= " AND tm.cod_sucursal = ?";
        $params[] = $codSucursal;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Contar tardanzas justificadas por operario
 */
function contarTardanzasJustificadasPorOperario($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "
        SELECT cod_operario, COUNT(*) as total_justificadas
        FROM TardanzasManuales
        WHERE estado = 'Justificado'
        AND fecha_tardanza BETWEEN ? AND ?
    ";

    $params = [$fechaDesde, $fechaHasta];

    if (!empty($codSucursal)) {
        $sql .= " AND cod_sucursal = ?";
        $params[] = $codSucursal;
    }

    $sql .= " GROUP BY cod_operario";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $resultados = [];
    while ($row = $stmt->fetch()) {
        $resultados[$row['cod_operario']] = $row['total_justificadas'];
    }

    return $resultados;
}

/**
 * Contar tardanzas por estado específico para cada operario
 */
function contarTardanzasPorEstadoOperario($codSucursal, $fechaDesde, $fechaHasta, $estado)
{
    global $conn;

    $sql = "
        SELECT cod_operario, COUNT(*) as total
        FROM TardanzasManuales
        WHERE estado = ?
        AND fecha_tardanza BETWEEN ? AND ?
    ";

    $params = [$estado, $fechaDesde, $fechaHasta];

    if (!empty($codSucursal)) {
        $sql .= " AND cod_sucursal = ?";
        $params[] = $codSucursal;
    }

    $sql .= " GROUP BY cod_operario";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $resultados = [];
    while ($row = $stmt->fetch()) {
        $resultados[$row['cod_operario']] = $row['total'];
    }

    return $resultados;
}

/**
 * Contar todas las tardanzas reportadas (sin importar estado) para cada operario
 */
function contarTardanzasReportadasOperario($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "
        SELECT cod_operario, COUNT(*) as total
        FROM TardanzasManuales
        WHERE fecha_tardanza BETWEEN ? AND ?
    ";

    $params = [$fechaDesde, $fechaHasta];

    if (!empty($codSucursal)) {
        $sql .= " AND cod_sucursal = ?";
        $params[] = $codSucursal;
    }

    $sql .= " GROUP BY cod_operario";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $resultados = [];
    while ($row = $stmt->fetch()) {
        $resultados[$row['cod_operario']] = $row['total'];
    }

    return $resultados;
}

/**
 * Obtiene tardanzas agrupadas por operario para contabilidad
 */
function obtenerTardanzasAgrupadasParaContabilidad($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    try {
        // 1. Obtener todos los operarios con asignaciones en el rango
        $sqlOperarios = "
            SELECT DISTINCT o.CodOperario, 
                   CONCAT(o.Nombre, ' ', 
                          IFNULL(o.Nombre2, ''), ' ', 
                          o.Apellido, ' ', 
                          IFNULL(o.Apellido2, '')) as nombre_completo
            FROM Operarios o
            JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            -- WHERE o.Operativo = 1
            AND (anc.Fin IS NULL OR anc.Fin >= ?)
            AND anc.Fecha <= ?
        ";

        $params = [$fechaDesde, $fechaHasta];

        if (!empty($codSucursal)) {
            $sqlOperarios .= " AND anc.Sucursal = ?";
            $params[] = $codSucursal;
        }

        $sqlOperarios .= " ORDER BY o.CodOperario";

        $stmt = $conn->prepare($sqlOperarios);
        $stmt->execute($params);
        $operarios = $stmt->fetchAll();

        $resultado = [];

        foreach ($operarios as $operario) {
            $codOperario = $operario['CodOperario'];

            // 2. Obtener todas las sucursales donde trabajó este operario
            $sucursalesOperario = obtenerSucursalesOperario($codOperario, $fechaDesde, $fechaHasta);

            // 3. Encontrar la sucursal con más marcaciones
            $sucursalPrincipal = encontrarSucursalPrincipal($codOperario, $sucursalesOperario, $fechaDesde, $fechaHasta);

            // 4. Obtener totales combinados de todas las sucursales
            $totales = obtenerTotalesTardanzasOperario($codOperario, $sucursalesOperario, $fechaDesde, $fechaHasta);

            $resultado[] = [
                'cod_operario' => $codOperario,
                'nombre_completo' => $operario['nombre_completo'],
                'sucursal_principal' => $sucursalPrincipal,
                'total_sistema' => $totales['sistema'],
                'total_justificadas' => $totales['justificadas'],
                'total_reportadas' => $totales['reportadas']
            ];
        }

        return $resultado;

    } catch (PDOException $e) {
        error_log("Error al obtener tardanzas agrupadas: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene las sucursales donde trabajó un operario en un rango de fechas
 */
function obtenerSucursalesOperario($codOperario, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "
        SELECT DISTINCT anc.Sucursal as codigo, s.nombre
        FROM AsignacionNivelesCargos anc
        JOIN sucursales s ON anc.Sucursal = s.codigo
        WHERE anc.CodOperario = ?
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
        ORDER BY s.nombre
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario, $fechaDesde, $fechaHasta]);

    return $stmt->fetchAll();
}

/**
 * Encuentra la sucursal principal (con más marcaciones) de un operario
 */
function encontrarSucursalPrincipal($codOperario, $sucursales, $fechaDesde, $fechaHasta)
{
    $maxMarcaciones = 0;
    $sucursalPrincipal = '';

    foreach ($sucursales as $sucursal) {
        $codSucursal = $sucursal['codigo'];

        // Contar marcaciones en esta sucursal
        $marcaciones = contarMarcacionesSucursal($codOperario, $codSucursal, $fechaDesde, $fechaHasta);

        if ($marcaciones > $maxMarcaciones) {
            $maxMarcaciones = $marcaciones;
            $sucursalPrincipal = $sucursal['nombre'];
        }
    }

    return $sucursalPrincipal ?: ($sucursales[0]['nombre'] ?? 'Desconocida');
}

/**
 * Cuenta las marcaciones de un operario en una sucursal específica
 */
function contarMarcacionesSucursal($codOperario, $codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "
        SELECT COUNT(*) as total
        FROM marcaciones 
        WHERE CodOperario = ?
        AND sucursal_codigo = ?
        AND fecha BETWEEN ? AND ?
        AND hora_ingreso IS NOT NULL
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario, $codSucursal, $fechaDesde, $fechaHasta]);
    $result = $stmt->fetch();

    return $result['total'] ?? 0;
}

/**
 * Obtiene los totales de tardanzas de un operario en todas sus sucursales
 */
function obtenerTotalesTardanzasOperario($codOperario, $sucursales, $fechaDesde, $fechaHasta)
{
    $totalSistema = 0;
    $totalJustificadas = 0;
    $totalReportadas = 0;

    foreach ($sucursales as $sucursal) {
        $codSucursal = $sucursal['codigo'];

        // Tardanzas del sistema
        $tardanzasSistema = contarTardanzasSistema($codOperario, $codSucursal, $fechaDesde, $fechaHasta);
        $totalSistema += $tardanzasSistema;

        // Tardanzas justificadas
        $tardanzasJustificadas = contarTardanzasPorEstado($codOperario, $codSucursal, $fechaDesde, $fechaHasta, 'Justificado');
        $totalJustificadas += $tardanzasJustificadas;

        // Tardanzas reportadas (todas)
        $tardanzasReportadas = contarTardanzasReportadas($codOperario, $codSucursal, $fechaDesde, $fechaHasta);
        $totalReportadas += $tardanzasReportadas;
    }

    return [
        'sistema' => $totalSistema,
        'justificadas' => $totalJustificadas,
        'reportadas' => $totalReportadas
    ];
}

/**
 * Cuenta las tardanzas del sistema para un operario en una sucursal
 * MODIFICADA: Considera fecha de liquidación
 */
function contarTardanzasSistema($codOperario, $codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    // NUEVA LÓGICA: Obtener fecha de liquidación del operario
    $contrato = obtenerUltimoContratoOperario($codOperario);
    $fechaHastaOperario = $fechaHasta;

    if ($contrato && !empty($contrato['fecha_liquidacion']) && $contrato['fecha_liquidacion'] != '0000-00-00') {
        $fechaLiq = new DateTime($contrato['fecha_liquidacion']);
        $fechaHastaObj = new DateTime($fechaHasta);

        if ($fechaLiq < $fechaHastaObj) {
            $fechaHastaOperario = $fechaLiq->format('Y-m-d');
        }

        $fechaDesdeObj = new DateTime($fechaDesde);
        if ($fechaLiq < $fechaDesdeObj) {
            return 0; // Ya estaba liquidado en el período
        }
    }

    // Obtener días laborables del operario (solo días con horario programado)
    $diasLaborables = obtenerDiasLaborablesOperario(
        $codOperario,
        $codSucursal,
        $fechaDesde,
        $fechaHastaOperario
    );
    $tardanzas = 0;

    foreach ($diasLaborables as $dia) {
        // Verificar si hay marcación de entrada para ese día
        $marcacion = obtenerMarcacionEntrada($codOperario, $dia['fecha']);

        if ($marcacion && $marcacion['hora_ingreso']) {
            // Verificar si hay tardanza (considerando 1 minuto de gracia)
            $tardanza = verificarTardanza($codOperario, $codSucursal, $dia['fecha'], $marcacion['hora_ingreso']);
            if ($tardanza) {
                $tardanzas++;
            }
        }
        // Si no hay marcación, NO se cuenta como tardanza (es ausencia)
    }

    return $tardanzas;
}

/**
 * Cuenta las tardanzas por estado para un operario en una sucursal
 */
function contarTardanzasPorEstado($codOperario, $codSucursal, $fechaDesde, $fechaHasta, $estado)
{
    global $conn;

    $sql = "
        SELECT COUNT(*) as total
        FROM TardanzasManuales
        WHERE cod_operario = ?
        AND cod_sucursal = ?
        AND estado = ?
        AND fecha_tardanza BETWEEN ? AND ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario, $codSucursal, $estado, $fechaDesde, $fechaHasta]);
    $result = $stmt->fetch();

    return $result['total'] ?? 0;
}

/**
 * Cuenta todas las tardanzas reportadas para un operario en una sucursal
 */
function contarTardanzasReportadas($codOperario, $codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "
        SELECT COUNT(*) as total
        FROM TardanzasManuales
        WHERE cod_operario = ?
        AND cod_sucursal = ?
        AND fecha_tardanza BETWEEN ? AND ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario, $codSucursal, $fechaDesde, $fechaHasta]);
    $result = $stmt->fetch();

    return $result['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tardanzas Manuales</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
    <link rel="stylesheet" href="css/tardanzas_manual.css">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, false, 'Registro de Tardanzas'); ?>

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

            <!-- Tarjeta de resumen de tardanzas -->
            <div class="resumen-tardanzas">
                <div style="display:none;" class="tarjeta">
                    <h3>Total Tardanzas Automáticas</h3>
                    <p class="tardanzas-auto"><?= $totalTardanzasAuto ?></p>
                    <small>Tardanzas detectadas por el sistema</small>
                </div>

                <div style="display:none;" class="tarjeta">
                    <h3>Tardanzas Registradas</h3>
                    <p class="tardanzas-registradas"><?= $totalTardanzasManualesRegistradas ?></p>
                    <small>Tardanzas registradas manualmente</small>
                </div>

                <div style="display:none;" class="tarjeta">
                    <h3>Tardanzas Pendientes</h3>
                    <p class="tardanzas-pendientes"><?= $tardanzasPendientes ?></p>
                    <small>Tardanzas por registrar</small>
                </div>
            </div>

            <div class="filters-container">
                <div class="filters-form">
                    <?php if (!verificarAccesoCargo([5, 43, 2, 46])): ?>
                        <div class="filter-group">
                            <label for="sucursal">Sucursal</label>
                            <select id="sucursal" name="sucursal" onchange="actualizarFiltros()">
                                <?php if ($esOperaciones): ?>
                                    <option value="todas" <?= (empty($sucursalSeleccionada) || $sucursalSeleccionada === 'todas') ? 'selected' : '' ?>>
                                        Todas las sucursales
                                    </option>
                                <?php endif; ?>
                                <?php foreach ($sucursales as $sucursal): ?>
                                    <option value="<?= $sucursal['codigo'] ?>" <?= $sucursalSeleccionada == $sucursal['codigo'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <!-- Para usuarios con cargo 2, 5, 43, mantener un campo oculto con su sucursal -->
                        <?php if (!empty($sucursalSeleccionada)): ?>
                            <input type="hidden" id="sucursal" name="sucursal" value="<?= $sucursalSeleccionada ?>">
                            <div class="filter-group" style="display:none;">
                                <label>Sucursal</label>
                                <div style="padding: 8px; background-color: #f5f5f5; border-radius: 4px;">
                                    <?= htmlspecialchars(obtenerNombreSucursal($sucursalSeleccionada)) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="filter-group">
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
                        <!-- Este div debe estar dentro del filter-group -->
                    </div>

                    <div class="filter-group">
                        <label for="desde">Desde</label>
                        <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($fechaDesde) ?>"
                            onchange="actualizarFiltros()">
                    </div>

                    <div class="filter-group">
                        <label for="hasta">Hasta</label>
                        <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($fechaHasta) ?>"
                            onchange="actualizarFiltros()">
                    </div>

                    <div class="filter-group">
                        <button type="button" onclick="actualizarFiltros()" class="btn">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>

                    <div class="action-buttons">
                        <?php if ($esLider): ?>
                            <button type="button" onclick="mostrarModalNuevaTardanza()" class="btn btn-success">
                                <i class="fas fa-plus"></i> Nuevo
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (verificarAccesoCargo([8, 16])): ?>
                        <div class="action-buttons">
                            <a style="display:none;" href="tardanzas_manual.php?<?= http_build_query([
                                'sucursal' => $sucursalSeleccionada ?? '',
                                'operario' => $operarioSeleccionado,
                                'desde' => $fechaDesde,
                                'hasta' => $fechaHasta,
                                'exportar_excel' => 1
                            ]) ?>" class="btn btn-primary">
                                <i class="fas fa-file-excel"></i> Exportar
                            </a>

                            <a style="display:none;" href="tardanzas_manual.php?<?= http_build_query([
                                'sucursal' => $sucursalSeleccionada ?? '',
                                'operario' => $operarioSeleccionado,
                                'desde' => $fechaDesde,
                                'hasta' => $fechaHasta,
                                'exportar_contabilidad' => 1
                            ]) ?>" class="btn btn-contabilidad">
                                <i class="fas fa-file-excel"></i> Contabilidad
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-container">
                <?php if (!empty($tardanzasManuales) || ($verVistaCompleta && !empty($tardanzasNoReportadas))): ?>
                    <table id="listaTardanzasMan">
                        <thead>
                            <tr>
                                <th>Colaborador</th>
                                <th>Sucursal</th>
                                <th>Fecha Tardanza</th>
                                <th>Horarios</th>
                                <th>Tipo Justificación</th>
                                <th>Estado</th>
                                <th>Observaciones</th>
                                <th>Registrado por</th>
                                <th>Foto</th>
                                <th style="text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- SECCIÓN 1: TARDANZAS YA REGISTRADAS -->
                            <?php foreach ($tardanzasManuales as $tardanza): ?>
                                <?php include 'row_tardanza_registrada.php'; ?>
                            <?php endforeach; ?>

                            <!-- SECCIÓN 2: TARDANZAS NO REPORTADAS (solo para vista completa) -->
                            <?php if ($verVistaCompleta && !empty($tardanzasNoReportadas)): ?>
                                <tr class="separador-tardanzas">
                                    <td colspan="10"
                                        style="background-color: #f8f9fa; font-weight: bold; text-align: center; padding: 10px;">
                                        <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>
                                        TARDANZAS NO REPORTADAS (DETECTADAS POR SISTEMA)
                                        <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>
                                    </td>
                                </tr>

                                <?php foreach ($tardanzasNoReportadas as $tardanza): ?>
                                    <?php include 'row_tardanza_no_reportada.php'; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">
                        <?php if ($fechaDesde && $fechaHasta): ?>
                            <?php if (empty($sucursalSeleccionada)): ?>
                                No se encontraron tardanzas entre <?= formatoFechaCorta($fechaDesde) ?> y
                                <?= formatoFechaCorta($fechaHasta) ?>.
                            <?php else: ?>
                                No se encontraron tardanzas para
                                <?= htmlspecialchars(obtenerNombreSucursal($sucursalSeleccionada)) ?>
                                entre <?= formatoFechaCorta($fechaDesde) ?> y <?= formatoFechaCorta($fechaHasta) ?>.
                            <?php endif; ?>
                        <?php else: ?>
                            Seleccione un rango de fechas para buscar tardanzas.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para nueva tardanza manual -->
    <div class="modal" id="modalNuevaTardanza">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Registrar Tardanza Manual</h2>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <form id="formNuevaTardanza" method="post" enctype="multipart/form-data">
                <input type="hidden" name="registrar_tardanza" value="1">

                <div class="modal-body">
                    <!-- NUEVO: Mensaje de advertencia para operarios sin contrato -->
                    <div id="mensaje-advertencia-contrato-tardanza" style="display: none; 
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
                            <?php
                            // Mostrar solo sucursales donde el usuario es líder
                            $sucursalesLider = obtenerSucursalesLider($_SESSION['usuario_id']);
                            foreach ($sucursalesLider as $sucursal): ?>
                                <option value="<?= $sucursal['codigo'] ?>">
                                    <?= htmlspecialchars($sucursal['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nueva_fecha" class="form-label">Fecha de Tardanza:</label>
                        <input type="date" id="nueva_fecha" name="fecha_tardanza" class="form-input" required
                            max="<?= date('Y-m-d', strtotime('-1 day')) ?>">
                    </div>

                    <div class="form-group">
                        <label for="nueva_operario" class="form-label">Colaborador:</label>
                        <select id="nueva_operario" name="cod_operario" class="form-select" required>
                            <option value="">Seleccione un colaborador</option>
                            <!-- Se llenará dinámicamente con JavaScript -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nueva_tipo" class="form-label">Tipo de Justificación:</label>
                        <select id="nueva_tipo" name="tipo_justificacion" class="form-select" required>
                            <option value="llave">Problema con llave</option>
                            <option value="error_sistema">Error del sistema</option>
                            <option value="accidente">Accidente/tráfico</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nueva_foto" class="form-label">Foto (obligatorio):</label>
                        <input type="file" id="nueva_foto" name="foto" class="form-input" accept="image/*" required>
                        <img id="nueva_foto_preview" class="photo-preview" src="#" alt="Vista previa de la foto">
                    </div>

                    <div class="form-group">
                        <label for="nueva_observaciones" class="form-label">Observaciones:</label>
                        <textarea id="nueva_observaciones" name="observaciones" class="form-textarea"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para ver foto ampliada - VERSIÓN CORREGIDA -->
    <div class="modal" id="modalVerFoto">
        <div class="modal-content-foto">
            <div class="modal-header-foto">
                <button class="modal-close-foto" onclick="cerrarModalFoto()">&times;</button>
                <div class="zoom-controls">
                    <button class="btn-zoom" onclick="zoomIn()" title="Acercar">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button class="btn-zoom" onclick="zoomOut()" title="Alejar">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <button class="btn-zoom" onclick="resetZoom()" title="Tamaño original">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="image-container">
                <img id="fotoAmpliada" src="" alt="Foto ampliada">
            </div>
        </div>
    </div>

    <!-- Modal para editar tardanza manual -->
    <div class="modal" id="modalEditarTardanza">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Editar Tardanza Manual</h2>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <form id="formEditarTardanza" method="post" action="editar_tardanza_manual.php">
                <input type="hidden" name="editar_tardanza" value="1">
                <input type="hidden" id="editar_id" name="id">
                <input type="hidden" id="editar_cod_operario" name="cod_operario">

                <!-- Campos ocultos para mantener los filtros -->
                <input type="hidden" name="sucursal" value="<?= htmlspecialchars($_GET['sucursal'] ?? '') ?>">
                <input type="hidden" name="desde" value="<?= htmlspecialchars($_GET['desde'] ?? '') ?>">
                <input type="hidden" name="hasta" value="<?= htmlspecialchars($_GET['hasta'] ?? '') ?>">

                <div class="modal-body">
                    <div class="info-group">
                        <span class="info-label">Colaborador:</span>
                        <span class="info-value" id="editar_nombre"></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Sucursal:</span>
                        <span class="info-value" id="editar_sucursal"></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Fecha de Tardanza:</span>
                        <span class="info-value" id="editar_fecha"></span>
                    </div>

                    <!-- INFORMACIÓN DE HORARIOS (MANTENER) -->
                    <div class="info-group">
                        <span class="info-label">Horario Programado:</span>
                        <span id="editar_entrada_programada">Cargando...</span> - <span
                            id="editar_salida_programada">Cargando...</span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Horario Marcado:</span>
                        <span id="editar_entrada_marcada">Cargando...</span> - <span
                            id="editar_salida_marcada">Cargando...</span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Tipo de Justificación:</span>
                        <span class="info-value" id="editar_tipo_justificacion"></span>
                    </div>

                    <div class="form-group">
                        <label for="editar_estado" class="form-label">Estado:</label>
                        <select id="editar_estado" name="estado" class="form-select" required>
                            <option value="Justificado">Justificado</option>
                            <option value="No Válido">No Válido</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editar_observaciones" class="form-label">Observaciones:</label>
                        <textarea id="editar_observaciones" name="observaciones" class="form-textarea"></textarea>
                    </div>

                    <div class="form-group" id="foto-container">
                        <label class="form-label">Foto:</label>
                        <img id="editar_foto_preview" class="photo-preview" src="#" alt="Foto de la tardanza"
                            style="max-width: 100%; max-height: 200px; cursor: zoom-in;"
                            onclick="mostrarFotoAmpliada(this.src)">
                        <a href="#" id="editar_foto_link" style="display: none;"
                            onclick="event.preventDefault(); mostrarFotoAmpliada(this.href);"></a>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Popup para consultar marcaciones -->
    <div class="modal" id="modalConsultarMarcaciones">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 class="modal-title">Información de Marcaciones</h2>
                <button class="modal-close" onclick="cerrarModalConsultar()">&times;</button>
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
                    <span class="info-label">Fecha de la Tardanza registrada por Líder:</span>
                    <span class="info-value" id="consulta_fecha_tardanza"></span>
                </div>

                <div style="display:none;" class="info-group">
                    <span class="info-label">Fecha utilizada en consulta:</span>
                    <span class="info-value" id="consulta_fecha_utilizada"></span>
                </div>

                <div class="info-group">
                    <span class="info-label">Hora Entrada Programada:</span>
                    <span class="info-value" id="consulta_entrada_programada"></span>
                    <small id="consulta_fecha_entrada_programada" style="color: #666; display: block;"></small>
                </div>

                <div class="info-group">
                    <span class="info-label">Hora Entrada Marcada:</span>
                    <span class="info-value" id="consulta_entrada_marcada"></span>
                    <small id="consulta_fecha_entrada_marcada" style="color: #666; display: block;"></small>
                </div>

                <div style="display:none;" class="info-group">
                    <span class="info-label">Hora Salida Programada:</span>
                    <span class="info-value" id="consulta_salida_programada"></span>
                    <small id="consulta_fecha_salida_programada" style="color: #666; display: block;"></small>
                </div>

                <div style="display:none;" class="info-group">
                    <span class="info-label">Hora Salida Marcada:</span>
                    <span class="info-value" id="consulta_salida_marcada"></span>
                    <small id="consulta_fecha_salida_marcada" style="color: #666; display: block;"></small>
                </div>

                <div style="display:none;" class="info-group">
                    <span class="info-label">Minutos de Tardanza:</span>
                    <span class="info-value" id="consulta_minutos_tardanza"></span>
                </div>

                <div style="display:none;" class="info-group">
                    <span class="info-label">Información de Depuración:</span>
                    <pre id="consulta_debug_info" style="background: #f5f5f5; padding: 10px; border-radius: 4px;"></pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModalConsultar()" class="btn btn-primary">Cerrar</button>
            </div>
        </div>
    </div>

    <script>
        // =============================================
        // VARIABLES GLOBALES PARA EL VISOR DE FOTOS
        // =============================================
        let currentZoomLevel = 1;
        const maxZoomLevel = 5;
        const minZoomLevel = 1;
        const zoomStep = 0.5;
        // =============================================

        // Variables para manejar el estado de edición
        let editandoObservaciones = {};
        let observacionesOriginales = {};

        // Datos de operarios para el autocompletado
        const operariosData = [
            { id: 0, nombre: 'Todos los colaboradores' },
            <?php foreach ($operarios as $op): ?>
                                                    { id: <?php echo $op['CodOperario']; ?>, nombre: '<?php echo addslashes($op['nombre_completo']); ?>' },
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
        operarioInput.addEventListener('input', function () {
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
                    div.addEventListener('click', function () {
                        operarioInput.value = op.nombre;
                        operarioIdInput.value = op.id;
                        sugerenciasDiv.style.display = 'none';
                    });
                    div.addEventListener('mouseover', function () {
                        this.style.backgroundColor = '#f5f5f5';
                    });
                    div.addEventListener('mouseout', function () {
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
        document.addEventListener('click', function (e) {
            if (e.target !== operarioInput) {
                sugerenciasDiv.style.display = 'none';
            }
        });

        // Manejar tecla Enter en el input
        operarioInput.addEventListener('keydown', function (e) {
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

        // Actualizar función actualizarFiltros para incluir el operario
        function actualizarFiltros() {
            const sucursal = document.getElementById('sucursal').value;
            const desde = document.getElementById('desde').value;
            const hasta = document.getElementById('hasta').value;
            const operario = document.getElementById('operario_id').value;

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

            // IMPORTANTE: Incluir sucursal incluso si está vacía para "Todas"
            if (sucursal !== undefined) {
                params.append('sucursal', sucursal);
            }

            params.append('desde', desde);
            params.append('hasta', hasta);

            if (operario > 0) {
                params.append('operario', operario);
            }

            window.location.href = 'tardanzas_manual.php?' + params.toString();
        }

        // Mostrar modal para nueva tardanza
        function mostrarModalNuevaTardanza() {
            // Establecer fecha predeterminada como ayer y establecer máximo como ayer
            const ayer = new Date();
            ayer.setDate(ayer.getDate() - 1);
            document.getElementById('nueva_fecha').valueAsDate = ayer;
            document.getElementById('nueva_fecha').max = ayer.toISOString().split('T')[0];

            // Limpiar selección de operario
            const selectOperario = document.getElementById('nueva_operario');
            selectOperario.innerHTML = '<option value="">Seleccione un colaborador</option>';

            // Obtener primera sucursal del select
            const selectSucursal = document.getElementById('nueva_sucursal');
            const primeraSucursal = selectSucursal.value;

            // Cargar operarios de la primera sucursal
            if (primeraSucursal) {
                cargarOperariosSucursal(primeraSucursal);
            }

            document.getElementById('modalNuevaTardanza').style.display = 'flex';
        }

        // FUNCIÓN MODIFICADA: Cargar operarios considerando fecha de liquidación
        function cargarOperariosSucursal(codSucursal, fechaTardanza) {
            const selectOperario = document.getElementById('nueva_operario');
            const mensajeAdvertencia = document.getElementById('mensaje-advertencia-contrato-tardanza');

            if (!codSucursal) {
                selectOperario.innerHTML = '<option value="">Primero seleccione una sucursal</option>';
                selectOperario.disabled = true;
                return;
            }

            if (!fechaTardanza) {
                selectOperario.innerHTML = '<option value="">Primero seleccione una fecha</option>';
                selectOperario.disabled = true;
                return;
            }

            // Indicar estado de carga
            selectOperario.innerHTML = '<option value="">⏳ Cargando operarios para ' + fechaTardanza + '...</option>';
            selectOperario.disabled = true;

            // NUEVA URL: Incluye fecha_tardanza para filtrar por liquidación
            let url = `tardanzas_manual.php?action=obtener_operarios&sucursal=${codSucursal}&fecha_tardanza=${fechaTardanza}`;

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Operarios recibidos:', data);

                    selectOperario.disabled = false;

                    if (!data || data.length === 0) {
                        selectOperario.innerHTML = '<option value="">No hay operarios activos para esta fecha</option>';
                        if (mensajeAdvertencia) {
                            mensajeAdvertencia.style.display = 'none';
                        }
                        return;
                    }

                    let options = '<option value="">Seleccione un colaborador</option>';
                    let hayOperariosSinContrato = false;

                    data.forEach(operario => {
                        const nombre = operario.Nombre || '';
                        const nombre2 = operario.Nombre2 || '';
                        const apellido = operario.Apellido || '';
                        const apellido2 = operario.Apellido2 || '';
                        const nombreCompleto = `${nombre} ${nombre2} ${apellido} ${apellido2}`.trim();

                        // NUEVA VALIDACIÓN: Verificar si tiene contrato
                        if (!operario.tiene_contrato) {
                            hayOperariosSinContrato = true;
                            options += `<option value="${operario.CodOperario}" data-sin-contrato="true">⚠️ ${nombreCompleto} (Sin contrato)</option>`;
                        } else {
                            options += `<option value="${operario.CodOperario}">${nombreCompleto}</option>`;
                        }
                    });

                    selectOperario.innerHTML = options;

                    // MOSTRAR ADVERTENCIA si hay operarios sin contrato
                    if (hayOperariosSinContrato && mensajeAdvertencia) {
                        mensajeAdvertencia.style.display = 'block';
                        mensajeAdvertencia.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Algunos colaboradores no tienen contrato registrado. Contactar con RH.';
                    } else if (mensajeAdvertencia) {
                        mensajeAdvertencia.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error cargando operarios:', error);
                    selectOperario.disabled = false;
                    selectOperario.innerHTML = '<option value="">❌ Error al cargar. Intente de nuevo</option>';
                    if (mensajeAdvertencia) {
                        mensajeAdvertencia.style.display = 'none';
                    }
                });
        }

        // Mostrar vista previa de la foto al seleccionarla
        document.getElementById('nueva_foto').addEventListener('change', function (e) {
            const preview = document.getElementById('nueva_foto_preview');
            const file = e.target.files[0];

            if (file) {
                const reader = new FileReader();

                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }

                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });

        // Función para mostrar los detalles en el modal de edición
        function mostrarModalEditarTardanza(id, codOperario, nombre, sucursal, fecha, tipoJustificacion, estado, observaciones, fotoPath) {
            document.getElementById('editar_id').value = id;
            // Agregar código de operario al campo oculto
            document.getElementById('editar_cod_operario').value = codOperario;
            document.getElementById('editar_nombre').textContent = nombre;
            document.getElementById('editar_sucursal').textContent = sucursal;

            document.getElementById('editar_fecha').textContent = formatearFechaLocal(fecha);

            document.getElementById('editar_tipo_justificacion').textContent = tipoJustificacion.replace('_', ' ');

            // Estado
            document.getElementById('editar_estado').value = estado;

            // Observaciones
            document.getElementById('editar_observaciones').value = observaciones || '';

            // Foto
            const fotoPreview = document.getElementById('editar_foto_preview');
            const fotoLink = document.getElementById('editar_foto_link');
            const fotoContainer = document.getElementById('foto-container');

            if (fotoPath) {
                const fotoUrl = `uploads/tardanzas/${fotoPath}`;
                fotoPreview.src = fotoUrl;
                fotoPreview.style.display = 'block';
                fotoLink.href = fotoUrl;
                fotoLink.style.display = 'inline-block';
                fotoContainer.style.display = 'block';
            } else {
                fotoPreview.style.display = 'none';
                fotoLink.style.display = 'none';
                fotoContainer.style.display = 'none';
            }

            // Obtener información del horario programado y marcaciones (MANTENER ESTA FUNCIONALIDAD)
            Promise.all([
                fetch(`obtener_horario_programado.php?cod_operario=${codOperario}&fecha=${fecha}`).then(r => r.json()),
                fetch(`obtener_marcaciones.php?cod_operario=${codOperario}&fecha=${fecha}`).then(r => r.json())
            ])
                .then(([horario, marcaciones]) => {
                    // Mostrar horario programado
                    const entradaProgramada = horario.hora_entrada ? formatoHoraAmPm(horario.hora_entrada) : 'No';
                    const salidaProgramada = horario.hora_salida ? formatoHoraAmPm(horario.hora_salida) : 'No';

                    document.getElementById('editar_entrada_programada').textContent = entradaProgramada;
                    document.getElementById('editar_salida_programada').textContent = salidaProgramada;

                    // Mostrar horario marcado
                    const entradaMarcada = marcaciones.hora_ingreso ? formatoHoraAmPm(marcaciones.hora_ingreso) : 'No marco';
                    const salidaMarcada = marcaciones.hora_salida ? formatoHoraAmPm(marcaciones.hora_salida) : 'No marco';

                    document.getElementById('editar_entrada_marcada').textContent = entradaMarcada;
                    document.getElementById('editar_salida_marcada').textContent = salidaMarcada;
                })
                .catch(error => {
                    console.error('Error al obtener datos:', error);
                    document.getElementById('editar_entrada_programada').textContent = 'Error';
                    document.getElementById('editar_salida_programada').textContent = 'Error';
                    document.getElementById('editar_entrada_marcada').textContent = 'Error';
                    document.getElementById('editar_salida_marcada').textContent = 'Error';
                });

            // Agregar parámetros de filtro al formulario
            const urlParams = new URLSearchParams(window.location.search);
            document.querySelector('#formEditarTardanza input[name="sucursal"]').value = urlParams.get('sucursal') || '';
            document.querySelector('#formEditarTardanza input[name="desde"]').value = urlParams.get('desde') || '';
            document.querySelector('#formEditarTardanza input[name="hasta"]').value = urlParams.get('hasta') || '';

            document.getElementById('modalEditarTardanza').style.display = 'flex';

            // Asegurarse de que el modal se muestre desde arriba
            document.querySelector('#modalEditarTardanza .modal-content').scrollTop = 0;
        }

        // Cerrar modal
        function cerrarModal() {
            document.getElementById('modalNuevaTardanza').style.display = 'none';
            document.getElementById('modalEditarTardanza').style.display = 'none';
        }

        // Función para habilitar/deshabilitar el botón de consulta en nueva tardanza
        document.getElementById('nueva_operario').addEventListener('change', function () {
            const btnConsultar = document.getElementById('btnConsultarMarcacionesNueva');
            btnConsultar.disabled = !this.value || !document.getElementById('nueva_fecha').value;
        });

        document.getElementById('nueva_fecha').addEventListener('change', function () {
            const btnConsultar = document.getElementById('btnConsultarMarcacionesNueva');
            btnConsultar.disabled = !this.value || !document.getElementById('nueva_operario').value;
        });

        // EVENTO NUEVO: Cargar operarios cuando cambia la fecha
        document.getElementById('nueva_fecha').addEventListener('change', function () {
            const sucursalSelect = document.getElementById('nueva_sucursal');
            if (sucursalSelect.value && this.value) {
                console.log('Fecha de tardanza cambiada a:', this.value);
                cargarOperariosSucursal(sucursalSelect.value, this.value);
            }
        });

        // Evento para el botón de consulta en nueva tardanza
        document.getElementById('btnConsultarMarcacionesNueva').addEventListener('click', function () {
            const codOperario = document.getElementById('nueva_operario').value;
            const fecha = document.getElementById('nueva_fecha').value;
            const nombre = document.getElementById('nueva_operario').options[document.getElementById('nueva_operario').selectedIndex].text;
            const sucursal = document.getElementById('nueva_sucursal').options[document.getElementById('nueva_sucursal').selectedIndex].text;

            if (!codOperario || !fecha) {
                alert('Seleccione un colaborador y una fecha para consultar las marcaciones');
                return;
            }

            mostrarModalConsultarMarcaciones(codOperario, nombre, sucursal, fecha, 0);
        });

        // Evento para el botón de consulta en editar tardanza
        document.getElementById('btnConsultarMarcacionesEditar').addEventListener('click', function () {
            const idTardanza = document.getElementById('editar_id').value;
            const nombre = document.getElementById('editar_nombre').textContent;
            const sucursal = document.getElementById('editar_sucursal').textContent;
            const fecha = document.getElementById('editar_fecha').textContent;
            const minutos = parseInt(document.getElementById('editar_minutos').textContent);

            // Obtener el código de operario del formulario de edición (necesitarás incluirlo como campo oculto)
            const codOperario = document.getElementById('editar_cod_operario').value;

            mostrarModalConsultarMarcaciones(codOperario, nombre, sucursal, fecha, minutos);
        });

        // Función para mostrar el modal de consulta de marcaciones
        function mostrarModalConsultarMarcaciones(codOperario, nombre, sucursal, fechaTardanza, minutosTardanza) {
            // Mostrar información básica
            document.getElementById('consulta_nombre').textContent = nombre;
            document.getElementById('consulta_sucursal').textContent = sucursal;

            document.getElementById('consulta_fecha_tardanza').textContent = formatoFechaCompleta(fechaTardanza);

            document.getElementById('consulta_minutos_tardanza').textContent = minutosTardanza + ' minutos';

            // Preparar información de depuración
            let debugInfo = `Iniciando consulta para:\n`;
            debugInfo += `- Colaborador: ${codOperario}\n`;
            debugInfo += `- Fecha de tardanza original: ${fechaTardanza}\n`;

            // Convertir la fecha al formato YYYY-MM-DD si no está en ese formato
            let fechaConsulta;
            try {
                if (fechaTardanza.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    fechaConsulta = fechaTardanza;
                } else {
                    // Intentar parsear otros formatos
                    const fechaObj = new Date(fechaTardanza);
                    if (isNaN(fechaObj.getTime())) {
                        throw new Error('Formato de fecha no reconocido');
                    }
                    fechaConsulta = fechaObj.toISOString().split('T')[0];
                }
            } catch (e) {
                fechaConsulta = fechaTardanza; // Usar el valor original si hay error
                debugInfo += `- Error al formatear fecha: ${e.message}\n`;
            }

            debugInfo += `- Fecha enviada al servidor: ${fechaConsulta}\n`;
            document.getElementById('consulta_fecha_utilizada').textContent = formatoFechaCompleta(fechaConsulta);

            // Obtener información de marcaciones del servidor
            fetch(`obtener_marcaciones.php?cod_operario=${codOperario}&fecha=${fechaConsulta}&debug=1`)
                .then(response => response.json())
                .then(data => {
                    debugInfo += `Respuesta del servidor:\n${JSON.stringify(data, null, 2)}\n`;

                    // Mostrar información de marcaciones con fechas utilizadas
                    const mostrarHoraConFecha = (hora, elementoHora, elementoFecha, tipo) => {
                        if (hora) {
                            document.getElementById(elementoHora).textContent = formatoHoraAmPm(hora);
                            document.getElementById(elementoFecha).textContent =
                                `(Consultado para ${tipo} en fecha: ${formatoFechaCompleta(fechaConsulta)})`;
                        } else {
                            document.getElementById(elementoHora).textContent = 'No registrado';
                            document.getElementById(elementoFecha).textContent =
                                `(Consultado para ${tipo} en fecha: ${formatoFechaCompleta(fechaConsulta)})`;
                        }
                    };

                    mostrarHoraConFecha(
                        data.hora_entrada_programada,
                        'consulta_entrada_programada',
                        'consulta_fecha_entrada_programada',
                        'entrada programada'
                    );

                    mostrarHoraConFecha(
                        data.hora_ingreso,
                        'consulta_entrada_marcada',
                        'consulta_fecha_entrada_marcada',
                        'entrada marcada'
                    );

                    mostrarHoraConFecha(
                        data.hora_salida_programada,
                        'consulta_salida_programada',
                        'consulta_fecha_salida_programada',
                        'salida programada'
                    );

                    mostrarHoraConFecha(
                        data.hora_salida,
                        'consulta_salida_marcada',
                        'consulta_fecha_salida_marcada',
                        'salida marcada'
                    );

                    // Mostrar semana utilizada para horario
                    if (data.semana_horario) {
                        debugInfo += `Semana de horario utilizada: ${data.semana_horario.id} (${data.semana_horario.fecha_inicio} a ${data.semana_horario.fecha_fin})\n`;
                    }

                    document.getElementById('consulta_debug_info').textContent = debugInfo;

                    // Mostrar el modal
                    document.getElementById('modalConsultarMarcaciones').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Error al obtener marcaciones:', error);
                    debugInfo += `Error en la consulta: ${error.message}\n`;
                    document.getElementById('consulta_debug_info').textContent = debugInfo;
                    document.getElementById('modalConsultarMarcaciones').style.display = 'flex';
                });
        }

        function formatearFechaLocal(fechaStr) {
            const fecha = new Date(fechaStr + 'T00:00:00');
            const opciones = { day: '2-digit', month: 'short', year: '2-digit' };
            return fecha.toLocaleDateString('es-ES', opciones);
        }

        // Función auxiliar para formatear fechas completas
        function formatoFechaCompleta(fechaStr) {
            try {
                const fecha = new Date(fechaStr + 'T00:00:00');
                const opciones = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    timeZone: 'UTC'
                };
                return fecha.toLocaleDateString('es-ES', opciones) +
                    ` (${fecha.toISOString().split('T')[0]})`;
            } catch (e) {
                return fechaStr; // Si hay error, devolver el valor original
            }
        }

        // Función para cerrar el modal de consulta
        function cerrarModalConsultar() {
            document.getElementById('modalConsultarMarcaciones').style.display = 'none';
        }

        // Función auxiliar para formatear horas
        function formatoHoraAmPm(hora) {
            if (!hora) return '-';
            return new Date(`2000-01-01T${hora}`).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        }

        // Cargar operarios cuando se selecciona una sucursal en el modal de nueva tardanza
        document.getElementById('nueva_sucursal').addEventListener('change', function () {
            const fechaInput = document.getElementById('nueva_fecha');
            if (fechaInput.value) {
                cargarOperariosSucursal(this.value, fechaInput.value);
            } else {
                const selectOperario = document.getElementById('nueva_operario');
                selectOperario.innerHTML = '<option value="">Primero seleccione una fecha</option>';
            }
        });

        document.getElementById('formNuevaTardanza').addEventListener('submit', function (e) {
            const fotoInput = document.getElementById('nueva_foto');
            if (!fotoInput.files || fotoInput.files.length === 0) {
                alert('Debe seleccionar una foto como evidencia');
                e.preventDefault();
                return false;
            }

            // Validar tipo de archivo
            const file = fotoInput.files[0];
            if (!file.type.match('image.*')) {
                alert('El archivo debe ser una imagen');
                e.preventDefault();
                return false;
            }

            // NUEVA VALIDACIÓN: Verificar si el operario seleccionado tiene contrato
            const selectOperario = document.getElementById('nueva_operario');
            const optionSeleccionada = selectOperario.options[selectOperario.selectedIndex];

            if (optionSeleccionada && optionSeleccionada.dataset.sinContrato === 'true') {
                e.preventDefault();
                alert('Este colaborador no tiene registro de contrato. Por favor contactar con el área de RH antes de registrar una tardanza.');
                return false;
            }

            return true;
        });

        // =============================================
        // FUNCIONES DEL VISOR DE FOTOS - CORREGIDAS
        // =============================================

        // Función para mostrar foto ampliada desde la tabla
        function mostrarFotoAmpliadaDesdeTabla(fotoPath) {
            if (!fotoPath) {
                alert('No hay foto disponible');
                return;
            }

            const fotoUrl = `uploads/tardanzas/${fotoPath}`;
            const fotoAmpliada = document.getElementById('fotoAmpliada');

            // Resetear zoom al abrir nueva foto
            currentZoomLevel = 1;
            fotoAmpliada.style.transform = `scale(${currentZoomLevel})`;
            fotoAmpliada.style.cursor = 'zoom-in';

            // Cargar la imagen
            fotoAmpliada.src = fotoUrl;

            // Mostrar el modal
            document.getElementById('modalVerFoto').style.display = 'flex';
        }

        // Función para mostrar foto ampliada desde el modal de edición
        function mostrarFotoAmpliada(src) {
            const fotoAmpliada = document.getElementById('fotoAmpliada');

            // Resetear zoom al abrir nueva foto
            currentZoomLevel = 1;
            fotoAmpliada.style.transform = `scale(${currentZoomLevel})`;
            fotoAmpliada.style.cursor = 'zoom-in';

            // Cargar la imagen
            fotoAmpliada.src = src;

            // Mostrar el modal
            document.getElementById('modalVerFoto').style.display = 'flex';
        }

        // Función para cerrar el modal de foto ampliada
        function cerrarModalFoto() {
            document.getElementById('modalVerFoto').style.display = 'none';
            // Resetear zoom al cerrar
            currentZoomLevel = 1;
            const fotoAmpliada = document.getElementById('fotoAmpliada');
            if (fotoAmpliada) {
                fotoAmpliada.style.transform = 'scale(1)';
                fotoAmpliada.style.cursor = 'zoom-in';
            }
        }

        // Funciones de zoom
        function zoomIn() {
            if (currentZoomLevel < maxZoomLevel) {
                currentZoomLevel += zoomStep;
                applyZoom();
            }
        }

        function zoomOut() {
            if (currentZoomLevel > minZoomLevel) {
                currentZoomLevel -= zoomStep;
                applyZoom();
            }
        }

        function resetZoom() {
            currentZoomLevel = 1;
            applyZoom();
        }

        function applyZoom() {
            const fotoAmpliada = document.getElementById('fotoAmpliada');
            if (fotoAmpliada) {
                fotoAmpliada.style.transform = `scale(${currentZoomLevel})`;

                // Actualizar cursor según el nivel de zoom
                if (currentZoomLevel > 1) {
                    fotoAmpliada.style.cursor = 'zoom-out';
                } else {
                    fotoAmpliada.style.cursor = 'zoom-in';
                }
            }
        }

        // =============================================
        // EVENT LISTENERS PARA EL VISOR DE FOTOS
        // =============================================

        // Inicializar event listeners cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function () {
            const modalFoto = document.getElementById('modalVerFoto');
            const imageContainer = document.getElementById('imageContainer');
            const fotoAmpliada = document.getElementById('fotoAmpliada');

            // Cerrar modal al hacer clic en el fondo (modal mismo)
            if (modalFoto) {
                modalFoto.addEventListener('click', function (e) {
                    // Solo cerrar si se hace clic directamente en el modal (fondo)
                    if (e.target === modalFoto) {
                        cerrarModalFoto();
                    }
                });
            }

            // Prevenir que el clic en el contenido cierre el modal
            if (imageContainer) {
                imageContainer.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            }

            // Zoom con la rueda del mouse en la imagen
            if (fotoAmpliada) {
                fotoAmpliada.addEventListener('wheel', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.deltaY < 0) {
                        zoomIn();
                    } else {
                        zoomOut();
                    }
                });

                // Alternar zoom al hacer clic en la imagen
                fotoAmpliada.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (currentZoomLevel === 1) {
                        zoomIn();
                    } else {
                        resetZoom();
                    }
                });
            }

            // Cerrar modal con tecla Escape
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && document.getElementById('modalVerFoto').style.display === 'flex') {
                    cerrarModalFoto();
                }
            });
        });

        // =============================================
        // FUNCIONES ADICIONALES
        // =============================================

        // Cerrar modal al hacer clic fuera del contenido
        window.addEventListener('click', function (event) {
            const modals = ['modalNuevaTardanza', 'modalEditarTardanza'];

            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    cerrarModal();
                }
            });
        });

        // Ajustar el posicionamiento del dropdown cuando se muestre
        function ajustarPosicionDropdown() {
            const input = document.getElementById('operario');
            const dropdown = document.getElementById('operarios-sugerencias');

            if (input && dropdown) {
                // Obtener la posición del input
                const rect = input.getBoundingClientRect();

                // Posicionar el dropdown justo debajo del input
                dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
                dropdown.style.left = rect.left + 'px';
                dropdown.style.width = rect.width + 'px';
            }
        }

        // Modificar el evento input para ajustar la posición
        operarioInput.addEventListener('input', function () {
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
                    div.addEventListener('click', function () {
                        operarioInput.value = op.nombre;
                        operarioIdInput.value = op.id;
                        sugerenciasDiv.style.display = 'none';
                    });
                    div.addEventListener('mouseover', function () {
                        this.style.backgroundColor = '#f5f5f5';
                    });
                    div.addEventListener('mouseout', function () {
                        this.style.backgroundColor = 'white';
                    });
                    sugerenciasDiv.appendChild(div);
                });

                // Ajustar posición antes de mostrar
                ajustarPosicionDropdown();
                sugerenciasDiv.style.display = 'block';
            } else {
                sugerenciasDiv.style.display = 'none';
            }
        });

        // Ajustar posición cuando se redimensiona la ventana
        window.addEventListener('resize', function () {
            if (sugerenciasDiv.style.display === 'block') {
                ajustarPosicionDropdown();
            }
        });

        // Ajustar posición cuando se hace scroll
        window.addEventListener('scroll', function () {
            if (sugerenciasDiv.style.display === 'block') {
                ajustarPosicionDropdown();
            }
        });


        /** Actualiza el estado de una tardanza (Aprobar/Rechazar)
        **/
        function actualizarEstado(id, nuevoEstado) {
            if (!confirm(`¿Está seguro de ${nuevoEstado === 'Justificado' ? 'aprobar' : 'rechazar'} esta tardanza?`)) {
                return;
            }

            const observaciones = document.getElementById(`obs-edit-${id}`).value;

            // Mostrar loading
            const actionsDiv = document.getElementById(`actions-${id}`);
            const originalHTML = actionsDiv.innerHTML;
            actionsDiv.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-spinner fa-spin"></i> Procesando...</div>';

            // Enviar petición AJAX
            fetch('actualizar_estado_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'id': id,
                    'estado': nuevoEstado,
                    'observaciones': observaciones
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Actualizar badge de estado
                        const badge = document.getElementById(`status-badge-${id}`);
                        badge.textContent = nuevoEstado;
                        badge.className = `status-badge status-${nuevoEstado.toLowerCase().replace(' ', '-')}`;

                        // Actualizar botones de acción
                        actualizarBotonesAccion(id, nuevoEstado);

                        // Mostrar mensaje de éxito
                        mostrarNotificacion('success', data.message);
                    } else {
                        // Restaurar HTML original en caso de error
                        actionsDiv.innerHTML = originalHTML;
                        mostrarNotificacion('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    actionsDiv.innerHTML = originalHTML;
                    mostrarNotificacion('error', 'Error al actualizar el estado');
                });
        }

        /**
         * Cambia el estado de una tardanza ya procesada
         */
        function cambiarEstado(id, estadoActual) {
            const nuevoEstado = estadoActual === 'Justificado' ? 'No Válido' : 'Justificado';
            const accion = nuevoEstado === 'Justificado' ? 'aprobar' : 'rechazar';

            actualizarEstado(id, nuevoEstado);
        }

        /**
         * Activa el modo de edición de observaciones
         */
        function toggleEditObservaciones(id) {
            const displayDiv = document.getElementById(`obs-display-${id}`);
            const editTextarea = document.getElementById(`obs-edit-${id}`);
            const actionsDiv = document.getElementById(`actions-${id}`);
            const saveCancelDiv = document.getElementById(`save-cancel-${id}`);

            // Guardar valor original
            if (!editandoObservaciones[id]) {
                observacionesOriginales[id] = editTextarea.value;
            }

            // Alternar visibilidad
            displayDiv.style.display = 'none';
            editTextarea.style.display = 'block';
            actionsDiv.style.display = 'none';
            saveCancelDiv.style.display = 'flex';

            // Marcar como editando
            editandoObservaciones[id] = true;

            // Focus en el textarea
            editTextarea.focus();
        }

        /**
         * Guarda las observaciones editadas
         */
        function guardarObservaciones(id) {
            const editTextarea = document.getElementById(`obs-edit-${id}`);
            const nuevasObservaciones = editTextarea.value.trim();

            // Obtener estado actual
            const badge = document.getElementById(`status-badge-${id}`);
            const estadoActual = badge.textContent.trim();

            // Mostrar loading
            const saveCancelDiv = document.getElementById(`save-cancel-${id}`);
            const originalHTML = saveCancelDiv.innerHTML;
            saveCancelDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            // Enviar petición AJAX
            fetch('actualizar_estado_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'id': id,
                    'estado': estadoActual,
                    'observaciones': nuevasObservaciones
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Actualizar display de observaciones
                        const displayDiv = document.getElementById(`obs-display-${id}`);
                        if (nuevasObservaciones) {
                            displayDiv.innerHTML = nuevasObservaciones.replace(/\n/g, '<br>');
                        } else {
                            displayDiv.innerHTML = '<span class="text-muted">Sin observaciones</span>';
                        }

                        // Salir del modo edición
                        finalizarEdicionObservaciones(id);

                        mostrarNotificacion('success', 'Observaciones actualizadas correctamente');
                    } else {
                        saveCancelDiv.innerHTML = originalHTML;
                        mostrarNotificacion('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    saveCancelDiv.innerHTML = originalHTML;
                    mostrarNotificacion('error', 'Error al guardar las observaciones');
                });
        }

        /**
         * Cancela la edición de observaciones
         */
        function cancelarEditObservaciones(id) {
            const editTextarea = document.getElementById(`obs-edit-${id}`);

            // Restaurar valor original
            if (observacionesOriginales[id] !== undefined) {
                editTextarea.value = observacionesOriginales[id];
            }

            finalizarEdicionObservaciones(id);
        }

        /**
         * Finaliza el modo de edición de observaciones
         */
        function finalizarEdicionObservaciones(id) {
            const displayDiv = document.getElementById(`obs-display-${id}`);
            const editTextarea = document.getElementById(`obs-edit-${id}`);
            const actionsDiv = document.getElementById(`actions-${id}`);
            const saveCancelDiv = document.getElementById(`save-cancel-${id}`);

            // Alternar visibilidad
            displayDiv.style.display = 'block';
            editTextarea.style.display = 'none';
            actionsDiv.style.display = 'flex';
            saveCancelDiv.style.display = 'none';

            // Limpiar estado
            delete editandoObservaciones[id];
            delete observacionesOriginales[id];
        }

        /**
         * Actualiza los botones de acción según el nuevo estado
         */
        function actualizarBotonesAccion(id, nuevoEstado) {
            const actionsDiv = document.getElementById(`actions-${id}`);

            if (nuevoEstado === 'Pendiente') {
                actionsDiv.innerHTML = `
            <button type="button" class="btn-action btn-approve" 
                    onclick="actualizarEstado(${id}, 'Justificado')" title="Aprobar">
                <i class="fas fa-check"></i>
            </button>
            <button type="button" class="btn-action btn-reject" 
                    onclick="actualizarEstado(${id}, 'No Válido')" title="Rechazar">
                <i class="fas fa-times"></i>
            </button>
            <button type="button" class="btn-action btn-edit" 
                    onclick="toggleEditObservaciones(${id})" title="Editar observaciones">
                <i class="fas fa-edit"></i>
            </button>
        `;
            } else {
                actionsDiv.innerHTML = `
            <button type="button" class="btn-action btn-change" 
                    onclick="cambiarEstado(${id}, '${nuevoEstado}')" title="Cambiar estado">
                <i class="fas fa-exchange-alt"></i>
            </button>
            <button type="button" class="btn-action btn-edit" 
                    onclick="toggleEditObservaciones(${id})" title="Editar observaciones">
                <i class="fas fa-edit"></i>
            </button>
        `;
            }
        }

        /**
         * Muestra notificaciones toast
         */
        function mostrarNotificacion(tipo, mensaje) {
            // Crear elemento de notificación
            const notification = document.createElement('div');
            notification.className = `notification notification-${tipo}`;
            notification.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${mensaje}</span>
    `;

            // Estilos inline para la notificación
            notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        background: ${tipo === 'success' ? 'linear-gradient(135deg, #28a745 0%, #20c997 100%)' : 'linear-gradient(135deg, #dc3545 0%, #e83e8c 100%)'};
    `;

            document.body.appendChild(notification);

            // Eliminar después de 3 segundos
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Agregar estilos de animación para las notificaciones
        const style = document.createElement('style');
        style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
        document.head.appendChild(style);

        // Función para registrar una tardanza no reportada
        function registrarTardanzaNoReportada(codOperario, fecha, sucursalNombre, minutos, codSucursal) {
            // Validar que tengamos el código de sucursal
            if (!codSucursal) {
                alert('Error: No se pudo determinar la sucursal. Intente seleccionar una sucursal en los filtros.');
                return;
            }

            // Mostrar modal de confirmación
            if (confirm(`¿Desea registrar la tardanza del colaborador en fecha ${formatearFechaLocal(fecha)}?\n\nTardanza: ${minutos} minutos\nSucursal: ${sucursalNombre}`)) {
                // Mostrar modal de registro rápido
                mostrarModalRegistroRapido(codOperario, fecha, codSucursal, minutos, sucursalNombre);
            }
        }

        // Función para mostrar modal de registro rápido
        function mostrarModalRegistroRapido(codOperario, fecha, codSucursal, minutos, sucursalNombre) {
            // Crear modal dinámico
            const modalHTML = `
        <div class="modal" id="modalRegistroRapido">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h2 class="modal-title">Registrar Tardanza</h2>
                    <button class="modal-close" onclick="cerrarModalRegistroRapido()">&times;</button>
                </div>
                <form id="formRegistroRapido" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="registrar_tardanza" value="1">
                    <input type="hidden" name="cod_operario" value="${codOperario}">
                    <input type="hidden" name="fecha_tardanza" value="${fecha}">
                    <input type="hidden" name="cod_sucursal" value="${codSucursal}">
                    
                    <div class="modal-body">
                        <div class="info-group">
                            <span class="info-label">Colaborador:</span>
                            <span class="info-value">${document.querySelector(`#tardanza-nr-${codOperario}-${fecha} td:first-child`).textContent}</span>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Sucursal:</span>
                            <span class="info-value">${sucursalNombre}</span>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Fecha:</span>
                            <span class="info-value">${formatearFechaLocal(fecha)}</span>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Minutos de tardanza:</span>
                            <span class="info-value">${minutos} minutos</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="rapido_tipo" class="form-label">Tipo de Justificación:</label>
                            <select id="rapido_tipo" name="tipo_justificacion" class="form-select" required>
                                <option value="llave">Problema con llave</option>
                                <option value="error_sistema">Error del sistema</option>
                                <option value="accidente">Accidente/tráfico</option>
                                <option value="transporte">Problema de transporte</option>
                                <option value="personal">Asunto personal</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="rapido_foto" class="form-label">Foto (obligatorio):</label>
                            <input type="file" id="rapido_foto" name="foto" class="form-input" accept="image/*" required>
                            <img id="rapido_foto_preview" class="photo-preview" src="#" alt="Vista previa de la foto">
                        </div>
                        
                        <div class="form-group">
                            <label for="rapido_observaciones" class="form-label">Observaciones:</label>
                            <textarea id="rapido_observaciones" name="observaciones" class="form-textarea" placeholder="Opcional"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" onclick="cerrarModalRegistroRapido()" class="btn btn-secondary">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    `;

            // Agregar modal al DOM
            document.body.insertAdjacentHTML('beforeend', modalHTML);

            // Mostrar modal
            document.getElementById('modalRegistroRapido').style.display = 'flex';

            // Configurar vista previa de foto
            document.getElementById('rapido_foto').addEventListener('change', function (e) {
                const preview = document.getElementById('rapido_foto_preview');
                const file = e.target.files[0];

                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.style.display = 'none';
                }
            });

            // Configurar envío del formulario
            document.getElementById('formRegistroRapido').addEventListener('submit', function (e) {
                if (!validarFormularioRapido()) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });
        }

        // Función para cerrar modal de registro rápido
        function cerrarModalRegistroRapido() {
            const modal = document.getElementById('modalRegistroRapido');
            if (modal) {
                modal.remove();
            }
        }

        // Función para validar formulario rápido
        function validarFormularioRapido() {
            const fotoInput = document.getElementById('rapido_foto');

            if (!fotoInput.files || fotoInput.files.length === 0) {
                alert('Debe seleccionar una foto como evidencia');
                return false;
            }

            const file = fotoInput.files[0];
            if (!file.type.match('image.*')) {
                alert('El archivo debe ser una imagen');
                return false;
            }

            return true;
        }

        // Función para ver detalles de tardanza no reportada
        function verDetallesTardanzaNoReportada(codOperario, fecha, sucursalNombre, minutos) {
            const detallesHTML = `
        <div class="modal" id="modalDetallesNR">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Detalles de Tardanza Detectada</h2>
                    <button class="modal-close" onclick="cerrarModalDetallesNR()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="info-group">
                        <span class="info-label">Colaborador:</span>
                        <span class="info-value">${document.querySelector(`#tardanza-nr-${codOperario}-${fecha} td:first-child`).textContent}</span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Sucursal:</span>
                        <span class="info-value">${sucursalNombre}</span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Fecha:</span>
                        <span class="info-value">${formatearFechaLocal(fecha)}</span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Minutos de tardanza:</span>
                        <span class="info-value">${minutos} minutos</span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Estado:</span>
                        <span class="info-value"><span class="status-badge status-no-reportada">No Reportada</span></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Descripción:</span>
                        <span class="info-value">Esta tardanza fue detectada automáticamente por el sistema al comparar el horario programado con las marcaciones reales. Aún no ha sido reportada manualmente por un líder.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="cerrarModalDetallesNR()" class="btn btn-primary">Cerrar</button>
                </div>
            </div>
        </div>
    `;

            document.body.insertAdjacentHTML('beforeend', detallesHTML);
            document.getElementById('modalDetallesNR').style.display = 'flex';
        }

        // Función para cerrar modal de detalles
        function cerrarModalDetallesNR() {
            const modal = document.getElementById('modalDetallesNR');
            if (modal) {
                modal.remove();
            }
        }

        // Función auxiliar para mostrar minutos de tardanza correctamente
        function mostrarMinutosTardanza(minutos) {
            if (minutos <= 0) {
                return 'A tiempo';
            } else if (minutos == 1) {
                return '1 minuto (gracia)';
            } else {
                return minutos + ' minutos';
            }
        }
    </script>

    <script>
        // Verificar carga de librerías
        $(document).ready(function () {
            $('#listaTardanzasMan').DataTable({
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
    </script>
</body>

</html>