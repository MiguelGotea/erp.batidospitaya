<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

// Verificar conexión
if (!$conn) {
    die("Error de conexión a la base de datos");
}

// Inicialización de variables para control de sucursales
$todasSucursales = [];
$sucursales = [];


$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso a la herramienta
if (!tienePermiso('tardanzas_manual', 'vista', $cargoOperario)) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

// Permisos via sistema de permisos
$verVistaCompleta = tienePermiso('tardanzas_manual', 'vista_completa', $cargoOperario);
$puedeNuevoRegistro = tienePermiso('tardanzas_manual', 'nuevo_registro', $cargoOperario);
$puedeExportar = tienePermiso('tardanzas_manual', 'exportar', $cargoOperario);
$puedeAprobar = tienePermiso('tardanzas_manual', 'aprobar', $cargoOperario);

// Variables legacy para compatibilidad con funciones internas
$esLider = $puedeNuevoRegistro;
$esOperaciones = $verVistaCompleta;

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
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
    echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
    echo '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';

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
        $nombreCompleto = implode(' ', array_filter([
            $item['operario_nombre'],
            $item['operario_nombre2'] ?? '',
            $item['operario_apellido'],
            $item['operario_apellido2'] ?? ''
        ], fn($v) => trim($v) !== ''));

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
    echo '</body></html>';
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
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
    echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
    echo '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';

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
    echo '</body></html>';
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
            $marcacion = obtenerMarcacionEntrada($codOperario, $dia['fecha'], $codSucursal);
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

// $esLider y $esOperaciones ya definidos arriba via tienePermiso
//$esSucursales = verificarAccesoCargo([27]);

// Handler AJAX para obtener operarios → movido a ajax/tardanzas_manual_obtener_operarios.php

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
/**
 * @param int         $codOperario
 * @param string      $fechaTardanza
 * @param int|null    $codSucursal   Cuando se provee, filtra horario y marcación por sucursal
 *                                   (imprescindible cuando el colaborador tiene doble jornada en el mismo día).
 */
function obtenerInformacionHorariosTardanza($codOperario, $fechaTardanza, $codSucursal = null)
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
        // 1. Obtener horario programado (filtrando por sucursal si está disponible)
        $semana = obtenerSemanaPorFecha($fechaTardanza);
        if ($semana) {
            if ($codSucursal !== null) {
                $stmt = $conn->prepare("
                    SELECT * FROM HorariosSemanalesOperaciones
                    WHERE cod_operario = ?
                    AND id_semana_sistema = ?
                    AND cod_sucursal = ?
                    LIMIT 1
                ");
                $stmt->execute([$codOperario, $semana['id'], $codSucursal]);
            } else {
                $stmt = $conn->prepare("
                    SELECT * FROM HorariosSemanalesOperaciones
                    WHERE cod_operario = ?
                    AND id_semana_sistema = ?
                    LIMIT 1
                ");
                $stmt->execute([$codOperario, $semana['id']]);
            }
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

        // 2. Obtener marcaciones (filtrando por sucursal si está disponible para evitar
        //    tomar la marcación de otra sucursal cuando el colaborador tiene doble jornada)
        if ($codSucursal !== null) {
            $stmt = $conn->prepare("
                SELECT hora_ingreso, hora_salida
                FROM marcaciones
                WHERE CodOperario = ?
                AND fecha = ?
                AND sucursal_codigo = ?
                LIMIT 1
            ");
            $stmt->execute([$codOperario, $fechaTardanza, $codSucursal]);
        } else {
            $stmt = $conn->prepare("
                SELECT hora_ingreso, hora_salida
                FROM marcaciones
                WHERE CodOperario = ?
                AND fecha = ?
                LIMIT 1
            ");
            $stmt->execute([$codOperario, $fechaTardanza]);
        }
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
 * Obtiene marcación de entrada de un operario en una fecha específica.
 * @param int    $codOperario
 * @param string $fecha
 * @param int|null $codSucursal  Si se provee, filtra por sucursal_codigo (necesario cuando
 *                               el operario trabaja en varias sucursales el mismo día).
 */
function obtenerMarcacionEntrada($codOperario, $fecha, $codSucursal = null)
{
    global $conn;

    if ($codSucursal !== null) {
        $stmt = $conn->prepare("
            SELECT * FROM marcaciones
            WHERE CodOperario = ?
            AND fecha = ?
            AND sucursal_codigo = ?
            AND hora_ingreso IS NOT NULL
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([$codOperario, $fecha, $codSucursal]);
    } else {
        $stmt = $conn->prepare("
            SELECT * FROM marcaciones
            WHERE CodOperario = ?
            AND fecha = ?
            AND hora_ingreso IS NOT NULL
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([$codOperario, $fecha]);
    }
    return $stmt->fetch();
}

// Obtener sucursales según el cargo del usuario
if ($esOperaciones) {
    $todasSucursales = obtenerTodasSucursales();
    $sucursales = $todasSucursales;
    $mostrarTodas = true;
    //} elseif ($esSucursales || $esLider) {
} elseif ($esLider) {
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
                 CONCAT_WS(' ',
                     NULLIF(TRIM(o.Nombre),   ''),
                     NULLIF(TRIM(o.Nombre2),  ''),
                     NULLIF(TRIM(o.Apellido), ''),
                     NULLIF(TRIM(o.Apellido2),'')
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
            // 3. Verificar si hay marcación de entrada para ese día, filtrando por sucursal
            $marcacion = obtenerMarcacionEntrada($operario['CodOperario'], $dia['fecha'], $codSucursal);

            if ($marcacion) {
                // 4. Verificar si hay tardanza (solo si llegó DESPUÉS de la hora programada)
                // Nota: obtenerMarcacionEntrada ya filtra por sucursal, así que hora_ingreso
                // corresponde a la sucursal correcta.
                $horaEntradaStr = preg_replace('/^\d{4}-\d{2}-\d{2}\s+/', '', trim($dia['hora_entrada']));
                $horaIngresaStr = preg_replace('/^\d{4}-\d{2}-\d{2}\s+/', '', trim($marcacion['hora_ingreso']));

                $horaProgramada = new DateTime('2000-01-01 ' . $horaEntradaStr);
                $horaMarcada    = new DateTime('2000-01-01 ' . $horaIngresaStr);

                // Calcular diferencia en segundos (positiva = llegó tarde)
                $segundos = $horaMarcada->getTimestamp() - $horaProgramada->getTimestamp();
                $minutos  = (int) floor($segundos / 60);

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

    $horaEntradaStr = preg_replace('/^\d{4}-\d{2}-\d{2}\s+/', '', trim($horarioProgramado['hora_entrada']));
    $horaIngresaStr = preg_replace('/^\d{4}-\d{2}-\d{2}\s+/', '', trim($horaMarcada));

    $horaProgramadaObj = new DateTime('2000-01-01 ' . $horaEntradaStr);
    $horaMarcadaObj    = new DateTime('2000-01-01 ' . $horaIngresaStr);

    // Calcular diferencia en segundos totales (positiva = llegó tarde)
    $segundos = ($horaMarcadaObj->getTimestamp() - $horaProgramadaObj->getTimestamp());
    $minutos  = (int) floor($segundos / 60);

    // Solo tardanzas de más de 1 minuto DESPUÉS de la hora programada
    if ($minutos > 1) {
        return [
            'minutos' => $minutos,
            'hora_entrada_programada' => $horarioProgramado['hora_entrada'],
            'hora_entrada_marcada' => $horaMarcadaObj->format('H:i:s')
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
            // 3. Verificar si hay marcación de entrada para ese día, filtrando por sucursal
            $marcacion = obtenerMarcacionEntrada($codOperario, $dia['fecha'], $sucursalCodigo);

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
    // Pasar $codSucursal para que tome la marcación de la sucursal correcta
    // (operario puede haber marcado en múltiples sucursales el mismo día)
    $marcacion = obtenerMarcacionEntrada($codOperario, $fecha, $codSucursal);

    if (!$marcacion || !$marcacion['hora_ingreso']) {
        // No tiene marcación de entrada
        $resultado['tipo_error'] = 'sin_marcacion';
        return $resultado;
    }

    $resultado['tiene_marcacion'] = true;
    $resultado['hora_marcada'] = $marcacion['hora_ingreso'];

    // 3. Verificar si hay tardanza comparando con el horario programado
    // Normalizar ambas horas a una fecha fija para evitar problemas con valores
    // TIME de MySQL que PHP puede parsear con distintas fechas base.
    $horaEntradaStr = preg_replace('/^\d{4}-\d{2}-\d{2}\s+/', '', trim($horarioProgramado['hora_entrada']));
    $horaIngresaStr = preg_replace('/^\d{4}-\d{2}-\d{2}\s+/', '', trim($marcacion['hora_ingreso']));

    $horaProgramada = new DateTime('2000-01-01 ' . $horaEntradaStr);
    $horaMarcada    = new DateTime('2000-01-01 ' . $horaIngresaStr);

    // Calcular diferencia en segundos totales (positiva = llegó tarde, negativa = llegó antes)
    $segundos = ($horaMarcada->getTimestamp() - $horaProgramada->getTimestamp());
    $minutos  = (int) floor($segundos / 60);

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
            $marcacion = obtenerMarcacionEntrada($operario['CodOperario'], $dia['fecha'], $operario['Sucursal'] ?? $codSucursal);

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
        $marcacion = obtenerMarcacionEntrada($codOperario, $dia['fecha'], $codSucursal);

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
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/tardanzas_manual.css?v=<?= time() ?>">
    <!-- Library for HEIC support -->
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Registro de Tardanzas'); ?>

            <div class="container-fluid p-3">

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
                        <?php if ($mostrarSelectSucursal): ?>
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
                            <?php if ($puedeNuevoRegistro): ?>
                                <button type="button" onclick="mostrarModalNuevaTardanza()" class="btn btn-success">
                                    <i class="fas fa-plus"></i>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if ($puedeExportar): ?>
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
                                    <?php if ($puedeAprobar): ?>
                                        <th style="text-align: center;">Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- SECCIÓN 1: TARDANZAS YA REGISTRADAS -->
                                <?php foreach ($tardanzasManuales as $tardanza): ?>
                                    <?php include 'includes/row_tardanza_registrada.php'; ?>
                                <?php endforeach; ?>

                                <!-- SECCIÓN 2: TARDANZAS NO REPORTADAS (solo para vista completa) -->
                                <?php if ($verVistaCompleta && !empty($tardanzasNoReportadas)): ?>
                                    <tr class="separador-tardanzas">
                                        <td colspan="<?= $puedeAprobar ? 10 : 9 ?>"
                                            style="background-color: #f8f9fa; font-weight: bold; text-align: center; padding: 10px;">
                                            <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>
                                            TARDANZAS NO REPORTADAS (DETECTADAS POR SISTEMA)
                                            <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>
                                        </td>
                                    </tr>

                                    <?php foreach ($tardanzasNoReportadas as $tardanza): ?>
                                        <?php include 'includes/row_tardanza_no_reportada.php'; ?>
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

        <script>
            // Variables para manejar el estado de edición
            let editandoObservaciones = {};
            let observacionesOriginales = {};

            // Datos de operarios para el autocompletado (generado por PHP)
            const operariosData = [{
                    id: 0,
                    nombre: 'Todos los colaboradores'
                },
                <?php foreach ($operarios as $op): ?> {
                        id: <?php echo $op['CodOperario']; ?>,
                        nombre: '<?php echo addslashes($op['nombre_completo']); ?>'
                    },
                <?php endforeach; ?>
            ];
        </script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="js/tardanzas_manual.js?v=<?= time() ?>"></script>

        <!-- Modal para nueva tardanza manual -->
        <div class="modal-custom" id="modalNuevaTardanza">
            <div class="modal-custom-content">
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
                                // Usar las sucursales ya filtradas por permisos al inicio del archivo
                                foreach ($sucursales as $sucursal): ?>
                                    <option value="<?= $sucursal['codigo'] ?>" <?= ($sucursalSeleccionada == $sucursal['codigo']) ? 'selected' : '' ?>>
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

        <!-- Modal para fotos -->
        <div class="modal fade" id="modalFotos" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #0E544C; color: white;">
                        <h5 class="modal-title">Evidencia de la Tardanza</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="carouselFotos" class="carousel slide">
                            <div class="carousel-inner" id="carouselFotosInner">
                                <!-- Fotos cargadas vía JS -->
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#carouselFotos"
                                data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#carouselFotos"
                                data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para editar tardanza manual -->
        <div class="modal-custom" id="modalEditarTardanza">
            <div class="modal-custom-content">
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
        <div class="modal-custom" id="modalConsultarMarcaciones">
            <div class="modal-custom-content" style="max-width: 700px;">
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
</body>

</html>