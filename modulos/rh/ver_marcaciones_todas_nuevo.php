<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
if (!tienePermiso('historial_marcaciones_globales', 'vista', $usuario['CodNivelesCargos'])) {
    header('Location: /login.php');
    exit();
}

$esLider = tienePermiso('historial_marcaciones_globales', 'permisoslider', $usuario['CodNivelesCargos']);
$esOperaciones = tienePermiso('historial_marcaciones_globales', 'permisosoperaciones', $usuario['CodNivelesCargos']);
$esCDS = tienePermiso('historial_marcaciones_globales', 'permisoscds', $usuario['CodNivelesCargos']);
$esContabilidad = tienePermiso('historial_marcaciones_globales', 'permisoscontabilidad', $usuario['CodNivelesCargos']);
$esFotoMarcacion = tienePermiso('historial_marcaciones_globales', 'foto_marcacion', $usuario['CodNivelesCargos']);
$esCambiarFotoMarcacion = tienePermiso('historial_marcaciones_globales', 'cambiar_foto_marcacion', $usuario['CodNivelesCargos']);
$canExportMarcacionesBd = tienePermiso('historial_marcaciones_globales', 'exportar_marcaciones_bd', $usuario['CodNivelesCargos']);

// Obtener operarios según el tipo de usuario
if ($esLider) {
    // Para líderes: operarios de TODAS sus sucursales asignadas
    $sucursalesLider = obtenerSucursalesLider($_SESSION['usuario_id']);
    $operariosFiltro = [];
    if (!empty($sucursalesLider)) {
        foreach ($sucursalesLider as $suc) {
            $opsSuc = obtenerOperariosSucursalLider($suc['codigo'], $_SESSION['usuario_id']);
            foreach ($opsSuc as $op) {
                $operariosFiltro[$op['CodOperario']] = $op; // evitar duplicados
            }
        }
        $operariosFiltro = array_values($operariosFiltro);
    }
} else {
    // Para otros usuarios (admin, RH, etc.): todos los operarios
    global $conn;
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
GROUP BY o.CodOperario
ORDER BY nombre_completo";
    $operariosFiltro = $conn->query($sql_operarios)->fetchAll();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Obtener sucursales según el tipo de usuario
if ($esLider) { // Si es líder de sucursal
    $sucursalesLider = obtenerSucursalesLider($usuario['CodOperario']);
    $modoVista = 'sucursal'; // Forzar modo sucursal para líderes

    // Si no hay sucursales asignadas al líder, mostrar mensaje
    if (empty($sucursalesLider)) {
        $_SESSION['error'] = "No tienes sucursales asignadas como líder.";
        header('Location: /index.php');
        exit();
    }

    // Para _nuevo.php: siempre cargar TODAS las sucursales del líder de una vez;
    // el filtro de columna "Sucursal" de la tabla maneja el filtrado en el cliente.
    $sucursalLider = $sucursalesLider[0]['codigo'];
    $sucursalSeleccionada = null; // null = cargar todas las sucursales del líder
    $sucursales = $sucursalesLider;
    $mostrarSelectSucursalLider = false; // El selector superior NO es necesario en _nuevo.php

    // Guardar la sucursal del líder en una variable global para usar en las consultas
    $sucursalAsignacionLider = $sucursalLider;
} elseif ($esOperaciones) { // Si es de operaciones
    $sucursales = obtenerSucursalesFisicas(); // Solo sucursales físicas (sucursal = 1)
    $modoVista = 'sucursal'; // Forzar modo sucursal para operaciones
} elseif ($esCDS) { // Si es jefe de CDS (cargo 19)
// Solo puede ver la sucursal 6
    $sucursales = [['codigo' => '6', 'nombre' => obtenerNombreSucursal('6')]];
    $modoVista = 'sucursal'; // Forzar modo sucursal
    $sucursalSeleccionada = '6'; // Forzar sucursal 6
} else { // Para otros cargos (contabilidad, RH, etc.)
    $sucursales = obtenerTodasSucursales();
    $modoVista = $_GET['modo'] ?? 'todas'; // 'sucursal' o 'todas'
}

// Si no hay sucursales
//if (empty($sucursales)) {
// $_SESSION['error'] = "No hay sucursales registradas en el sistema.";
// header('Location: /index.php');
// exit();
//}

// Obtener parámetros de filtro
if ($esLider) {
    $modoVista = 'sucursal';
    $sucursalSeleccionada = null; // null = cargar todas las sucursales del líder
} else {
    $sucursalParam = $_GET['sucursal'] ?? 'todas';
    // Priorizar el parámetro 'modo' explícito (lo pasa el botón exportar cuando sucursal está vacío)
    if (isset($_GET['modo']) && $_GET['modo'] === 'todas') {
        $modoVista = 'todas';
        $sucursalSeleccionada = null;
    } else {
        $modoVista = ($sucursalParam !== '' && $sucursalParam !== 'todas') ? 'sucursal' : 'todas';
        $sucursalSeleccionada = ($sucursalParam !== '' && $sucursalParam !== 'todas') ? $sucursalParam : ($sucursales[0]['codigo'] ?? null);
        if ($modoVista === 'todas') {
            $sucursalSeleccionada = null;
        }
    }
}

// LIMITAR FECHAS MÁXIMO AL DÍA ACTUAL
$fechaHoy = date('Y-m-d');
$fechaDesde = $_GET['desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['hasta'] ?? $fechaHoy;

// Asegurar que la fecha hasta no sea mayor a hoy
if ($fechaHasta > $fechaHoy) {
    $fechaHasta = $fechaHoy;
}

$filtroActivo = $_GET['activo'] ?? 'todos'; // 'activos', 'inactivos', 'todos'
$busqueda = $_GET['busqueda'] ?? '';
$operario_id = isset($_GET['operario_id']) ? intval($_GET['operario_id']) : 0;

// Manejar filtro por número de semana (solo para líderes)
if ($esLider && isset($_GET['numero_semana']) && !empty($_GET['numero_semana'])) {
    $numeroSemana = intval($_GET['numero_semana']);
    $semanaSeleccionada = obtenerSemanaPorNumero($numeroSemana);

    if ($semanaSeleccionada) {
        // Sobrescribir las fechas con las de la semana seleccionada
        $fechaDesde = $semanaSeleccionada['fecha_inicio'];
        $fechaHasta = $semanaSeleccionada['fecha_fin'];

        // Guardar el número de semana para mostrarlo en el select
        $numeroSemanaSeleccionado = $numeroSemana;
    }
}

// Si es líder de sucursal (cargo 5) y no se ha seleccionado un operario específico, cargar su propio ID
// Solo si no viene vacío desde el formulario Y no es la opción "Todos"
//if ($esLider && $operario_id === 0 && !isset($_GET['operario_vacio'])) {
if ($esLider && empty($operario_id) && $operario_id !== '0' && !isset($_GET['operario_vacio'])) {
    // NO forzar el ID del usuario cuando es 0
// Dejar que $operario_id = 0 se mantenga para "Todos los colaboradores"
// Solo cambiar si es realmente vacío y el usuario no ha hecho una selección
//$operario_id = intval($_SESSION['usuario_id']);
}

// Validar fechas
if (strtotime($fechaDesde) > strtotime($fechaHasta)) {
    $_SESSION['error'] = "La fecha 'Desde' no puede ser mayor a la fecha 'Hasta'";
    header('Location: ver_marcaciones_todas.php');
    exit();
}

// Validar que no se seleccionen fechas futuras
if (strtotime($fechaDesde) > strtotime($fechaHoy)) {
    $fechaDesde = $fechaHoy;
    $_SESSION['info'] = "La fecha 'Desde' se ajustó al día actual porque no se pueden consultar fechas futuras.";
}

if (strtotime($fechaHasta) > strtotime($fechaHoy)) {
    $fechaHasta = $fechaHoy;
    $_SESSION['info'] = "La fecha 'Hasta' se ajustó al día actual porque no se pueden consultar fechas futuras.";
}

// Obtener lista de operarios para el filtro
global $conn;
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
GROUP BY o.CodOperario
ORDER BY nombre_completo";
$operarios = $conn->query($sql_operarios)->fetchAll();

// Obtener marcaciones con horarios programados
$marcaciones = [];
// Para líderes en _nuevo.php: $sucursalSeleccionada = null → cargar todas sus sucursales
if ($esLider) {
    $marcaciones = obtenerMarcacionesConHorariosProgramados(
        null, // Cargar todas las sucursales del líder (el filtro de columna se encarga en cliente)
        $fechaDesde,
        $fechaHasta,
        $filtroActivo,
        $busqueda,
        $operario_id,
        'sucursal'
    );
} elseif ($modoVista === 'sucursal' && $sucursalSeleccionada) {
    $marcaciones = obtenerMarcacionesConHorariosProgramados(
        $sucursalSeleccionada,
        $fechaDesde,
        $fechaHasta,
        $filtroActivo,
        $busqueda,
        $operario_id,
        'sucursal'
    );
} elseif ($modoVista === 'todas') {
    $marcaciones = obtenerMarcacionesConHorariosProgramados(
        null,
        $fechaDesde,
        $fechaHasta,
        $filtroActivo,
        $busqueda,
        $operario_id,
        'todas'
    );
}

// Si es líder y no hay marcaciones, pero tiene su ID cargado, mostrar mensaje informativo
if ($esLider && $operario_id === intval($_SESSION['usuario_id']) && empty($marcaciones)) {
    $_SESSION['info'] = "Se están mostrando tus propias marcaciones. Puedes usar el filtro de 'Colaborador' para ver otros
operarios de tu sucursal.";
}

// Ordenar marcaciones por fecha (más reciente primero)
usort($marcaciones, function ($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

// CALCULAR TOTALES POR OPERARIO - SOLO PARA DÍAS CON MARCACIÓN
$totalesOperarios = [];
foreach ($marcaciones as $marcacion) {
    // Solo calcular horas para días que tienen marcación
    if ($marcacion['tiene_marcacion'] && $marcacion['hora_ingreso'] && $marcacion['hora_salida']) {
        $entrada = new DateTime($marcacion['hora_ingreso']);
        $salida = new DateTime($marcacion['hora_salida']);

        if ($salida < $entrada) {
            $salida->add(new DateInterval('P1D'));
        }

        $diferencia = $salida->diff($entrada);
        $horasTrabajadasDia = $diferencia->h + ($diferencia->i / 60);

        $codOperario = $marcacion['CodOperario'];
        if (!isset($totalesOperarios[$codOperario])) {
            $totalesOperarios[$codOperario] = 0;
        }
        $totalesOperarios[$codOperario] += $horasTrabajadasDia;
    }
}

// Validación adicional para líderes: el modo siempre es 'sucursal' pero $sucursalSeleccionada = null
// para que la función cargue TODAS sus sucursales asignadas.
if ($esLider) {
    $modoVista = 'sucursal'; // Mantener modo sucursal
    // sucursalSeleccionada ya fue establecida como null arriba para cargar todas las sucursales del líder
}

/**
 * Obtiene las marcaciones Y horarios programados (incluso cuando no hay marcación)
 * MODIFICADA: Permite múltiples marcaciones del mismo operario en la misma fecha
 * MODIFICADA: Identifica mejor las faltas potenciales
 */
function obtenerMarcacionesConHorariosProgramados(
    $codSucursal,
    $fechaDesde,
    $fechaHasta,
    $filtroActivo = 'todos',
    $busqueda = '',
    $operario_id = 0,
    $modoVista = 'sucursal'
) {
    global $conn;

    // LIMITAR FECHA HASTA AL DÍA ACTUAL SI ES FUTURO
    $fechaHoy = date('Y-m-d');
    if ($fechaHasta > $fechaHoy) {
        $fechaHasta = $fechaHoy;
    }

    // Verificar si el usuario actual es líder de sucursal
    global $esLider;
    $usuarioId = $_SESSION['usuario_id'] ?? 0;

    // PRIMERO: Obtener todos los horarios programados en el rango de fechas
    $sqlHorarios = "
    SELECT
    hso.cod_operario,
    hso.cod_sucursal,
    ss.fecha_inicio,
    ss.fecha_fin,
    ss.numero_semana,
    -- Campos para cada día de la semana
    hso.lunes_estado, hso.lunes_entrada, hso.lunes_salida,
    hso.martes_estado, hso.martes_entrada, hso.martes_salida,
    hso.miercoles_estado, hso.miercoles_entrada, hso.miercoles_salida,
    hso.jueves_estado, hso.jueves_entrada, hso.jueves_salida,
    hso.viernes_estado, hso.viernes_entrada, hso.viernes_salida,
    hso.sabado_estado, hso.sabado_entrada, hso.sabado_salida,
    hso.domingo_estado, hso.domingo_entrada, hso.domingo_salida,
    s.nombre as nombre_sucursal,
    o.Nombre, o.Apellido, o.Apellido2,
    -- Verificar estado actual del operario
    (SELECT CASE
    WHEN MAX(anc.Fin) IS NULL THEN 1
    WHEN MAX(anc.Fin) >= CURDATE() THEN 1
    ELSE 0
    END
    FROM AsignacionNivelesCargos anc
    WHERE anc.CodOperario = hso.cod_operario AND
    (anc.Fin IS NULL OR anc.Fin >= CURDATE())) as estado_actual,
    -- Obtener el cargo actual
    (SELECT nc.Nombre
    FROM AsignacionNivelesCargos anc
    LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
    WHERE anc.CodOperario = hso.cod_operario AND
    (anc.Fin IS NULL OR anc.Fin >= CURDATE())
    ORDER BY
    CASE WHEN anc.CodNivelesCargos != 2 THEN 0 ELSE 1 END,
    anc.Fecha DESC
    LIMIT 1) as nombre_cargo,
    (SELECT anc.CodNivelesCargos
    FROM AsignacionNivelesCargos anc
    WHERE anc.CodOperario = hso.cod_operario AND
    (anc.Fin IS NULL OR anc.Fin >= CURDATE())
    ORDER BY
    CASE WHEN anc.CodNivelesCargos != 2 THEN 0 ELSE 1 END,
    anc.Fecha DESC
    LIMIT 1) as codigo_cargo
    FROM HorariosSemanalesOperaciones hso
    JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
    JOIN sucursales s ON hso.cod_sucursal = s.codigo
    JOIN Operarios o ON hso.cod_operario = o.CodOperario
    WHERE ss.fecha_inicio <= ? AND ss.fecha_fin>= ?
        ";

    $paramsHorarios = [$fechaHasta, $fechaDesde]; // Nota: invertido para cubrir el rango

    // Aplicar filtro de sucursal
    if ($modoVista === 'sucursal' && $codSucursal && $codSucursal !== 'todas') {
        $sqlHorarios .= " AND hso.cod_sucursal = ?";
        $paramsHorarios[] = $codSucursal;
    }

    // Si es líder de sucursal, filtrar por colaboradores asignados a CUALQUIERA de sus sucursales
    if ($esLider) {
        $sucursalesLider = obtenerSucursalesLider($usuarioId);
        $codigosSucursalesLider = array_filter(array_column($sucursalesLider, 'codigo'));

        if (!empty($codigosSucursalesLider)) {
            $placeholders = implode(',', array_fill(0, count($codigosSucursalesLider), '?'));
            // Filtrar por colaboradores asignados a CUALQUIERA de las sucursales del líder
            $sqlHorarios .= " AND EXISTS (
        SELECT 1 FROM AsignacionNivelesCargos anc_asig
        WHERE anc_asig.CodOperario = hso.cod_operario
        AND anc_asig.Sucursal IN ($placeholders)
        AND (anc_asig.Fin IS NULL OR anc_asig.Fin >= CURDATE())
        AND anc_asig.CodNivelesCargos != 27 -- Excluir cargo 27
        )";
            foreach ($codigosSucursalesLider as $cod) {
                $paramsHorarios[] = $cod;
            }
        }
    }

    // Si es jefe de CDS (cargo 19), filtrar solo sucursal 6 y cargos específicos
    global $esCDS;
    $esJefeCDS = $esCDS;
    if ($esJefeCDS) {
        // Filtrar por sucursal 6
        $sqlHorarios .= " AND hso.cod_sucursal = ?";
        $paramsHorarios[] = '6';

        // Y filtrar por los cargos específicos (20, 23, 34, 58, 59, 60) en cualquier sucursal
        $sqlHorarios .= " AND EXISTS (
        SELECT 1 FROM AsignacionNivelesCargos anc
        WHERE anc.CodOperario = hso.cod_operario
        AND anc.CodNivelesCargos IN (20, 23, 34, 58, 59, 60)
        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        )";
    }

    // Aplicar filtro de operario específico
    if ($operario_id > 0) {
        $sqlHorarios .= " AND hso.cod_operario = ?";
        $paramsHorarios[] = $operario_id;
    }

    // Aplicar filtro de activos/inactivos
    if ($filtroActivo === 'activos') {
        $sqlHorarios .= " AND EXISTS (
        SELECT 1 FROM AsignacionNivelesCargos anc
        WHERE anc.CodOperario = hso.cod_operario AND
        (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        )";
    } elseif ($filtroActivo === 'inactivos') {
        $sqlHorarios .= " AND NOT EXISTS (
        SELECT 1 FROM AsignacionNivelesCargos anc
        WHERE anc.CodOperario = hso.cod_operario AND
        (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        )";
    }

    // Aplicar filtro de búsqueda
    if (!empty($busqueda)) {
        $sqlHorarios .= " AND (CONCAT(o.Nombre, ' ', o.Apellido) LIKE ? OR hso.cod_operario = ?)";
        $paramsHorarios[] = "%$busqueda%";
        $paramsHorarios[] = $busqueda;
    }

    $stmtHorarios = $conn->prepare($sqlHorarios);
    $stmtHorarios->execute($paramsHorarios);
    $horariosProgramados = $stmtHorarios->fetchAll();

    // SEGUNDO: Obtener TODAS las marcaciones en el rango de fechas (sin limitar a 1 por día)
    $sqlMarcaciones = "
        SELECT
        m.id,
        m.fecha,
        m.hora_ingreso,
        m.hora_salida,
        m.CodOperario,
        m.sucursal_codigo,
        s.nombre as nombre_sucursal,
        o.Nombre, o.Apellido, o.Apellido2,
        (SELECT ss.numero_semana
        FROM SemanasSistema ss
        WHERE m.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
        LIMIT 1) as numero_semana,
        -- Verificar estado actual
        (SELECT CASE
        WHEN MAX(anc.Fin) IS NULL THEN 1
        WHEN MAX(anc.Fin) >= CURDATE() THEN 1
        ELSE 0
        END
        FROM AsignacionNivelesCargos anc
        WHERE anc.CodOperario = m.CodOperario AND
        (anc.Fin IS NULL OR anc.Fin >= CURDATE())) as estado_actual,
        -- Obtener el cargo actual
        (SELECT nc.Nombre
        FROM AsignacionNivelesCargos anc
        LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
        WHERE anc.CodOperario = m.CodOperario AND
        (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        ORDER BY
        CASE WHEN anc.CodNivelesCargos != 2 THEN 0 ELSE 1 END,
        anc.Fecha DESC
        LIMIT 1) as nombre_cargo,
        (SELECT anc.CodNivelesCargos
        FROM AsignacionNivelesCargos anc
        WHERE anc.CodOperario = m.CodOperario AND
        (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        ORDER BY
        CASE WHEN anc.CodNivelesCargos != 2 THEN 0 ELSE 1 END,
        anc.Fecha DESC
        LIMIT 1) as codigo_cargo
        FROM marcaciones m
        JOIN Operarios o ON m.CodOperario = o.CodOperario
        JOIN sucursales s ON m.sucursal_codigo = s.codigo
        WHERE m.fecha BETWEEN ? AND ?
        ";

    $paramsMarcaciones = [$fechaDesde, $fechaHasta];

    // Aplicar filtro de sucursal para marcaciones
    if ($modoVista === 'sucursal' && $codSucursal && $codSucursal !== 'todas') {
        $sqlMarcaciones .= " AND m.sucursal_codigo = ?";
        $paramsMarcaciones[] = $codSucursal;
    }

    // Si es líder de sucursal, filtrar marcaciones por colaboradores asignados a CUALQUIERA de sus sucursales
    if ($esLider) {
        $sucursalesLider = obtenerSucursalesLider($usuarioId);
        $codigosSucursalesLider = array_filter(array_column($sucursalesLider, 'codigo'));

        if (!empty($codigosSucursalesLider)) {
            $placeholders = implode(',', array_fill(0, count($codigosSucursalesLider), '?'));
            // Filtrar por colaboradores asignados a CUALQUIERA de las sucursales del líder
            $sqlMarcaciones .= " AND EXISTS (
        SELECT 1 FROM AsignacionNivelesCargos anc_asig
        WHERE anc_asig.CodOperario = m.CodOperario
        AND anc_asig.Sucursal IN ($placeholders)
        AND (anc_asig.Fin IS NULL OR anc_asig.Fin >= CURDATE())
        AND anc_asig.CodNivelesCargos != 27 -- Excluir cargo 27
        )";
            foreach ($codigosSucursalesLider as $cod) {
                $paramsMarcaciones[] = $cod;
            }
        }
    }

    // Si es jefe de CDS (cargo 19), filtrar marcaciones también
    if ($esJefeCDS) {
        // Filtrar por sucursal 6 O por los cargos específicos en cualquier sucursal
        $sqlMarcaciones .= " AND (m.sucursal_codigo = ? OR EXISTS (
        SELECT 1 FROM AsignacionNivelesCargos anc
        WHERE anc.CodOperario = m.CodOperario
        AND anc.CodNivelesCargos IN (20, 23, 34, 58, 59, 60)
        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        ))";
        $paramsMarcaciones[] = '6';
    }

    // Aplicar filtro de operario específico para marcaciones
    if ($operario_id > 0) {
        $sqlMarcaciones .= " AND m.CodOperario = ?";
        $paramsMarcaciones[] = $operario_id;
    }

    // Aplicar filtro de activos/inactivos para marcaciones
    if ($filtroActivo === 'activos') {
        $sqlMarcaciones .= " AND EXISTS (
        SELECT 1 FROM AsignacionNivelesCargos anc
        WHERE anc.CodOperario = m.CodOperario AND
        (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        )";
    } elseif ($filtroActivo === 'inactivos') {
        $sqlMarcaciones .= " AND NOT EXISTS (
        SELECT 1 FROM AsignacionNivelesCargos anc
        WHERE anc.CodOperario = m.CodOperario AND
        (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        )";
    }

    // Aplicar filtro de búsqueda para marcaciones
    if (!empty($busqueda)) {
        $sqlMarcaciones .= " AND (CONCAT(o.Nombre, ' ', o.Apellido) LIKE ? OR m.CodOperario = ?)";
        $paramsMarcaciones[] = "%$busqueda%";
        $paramsMarcaciones[] = $busqueda;
    }

    $stmtMarcaciones = $conn->prepare($sqlMarcaciones);
    $stmtMarcaciones->execute($paramsMarcaciones);
    $marcaciones = $stmtMarcaciones->fetchAll();

    // TERCERO: Combinar horarios programados y marcaciones
    $resultado = [];

    // Procesar horarios programados (generar un registro por cada día con horario)
    foreach ($horariosProgramados as $horario) {
        $fechaInicio = new DateTime($horario['fecha_inicio']);
        $fechaFin = new DateTime($horario['fecha_fin']);

        // Generar registros para cada día de la semana del horario
        for ($fecha = clone $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 day')) {
            $fechaStr = $fecha->format('Y-m-d');

            // Solo incluir fechas dentro del rango solicitado Y NO FUTURAS
            if ($fechaStr < $fechaDesde || $fechaStr > $fechaHasta || $fechaStr > $fechaHoy) {
                continue;
            }

            $diaSemana = $fecha->format('N'); // 1=lunes, 7=domingo

            // Obtener datos del día específico según el día de la semana
            $estadoDia = '';
            $horaEntradaProgramada = '';
            $horaSalidaProgramada = '';

            switch ($diaSemana) {
                case 1: // lunes
                    $estadoDia = $horario['lunes_estado'];
                    $horaEntradaProgramada = $horario['lunes_entrada'];
                    $horaSalidaProgramada = $horario['lunes_salida'];
                    break;
                case 2: // martes
                    $estadoDia = $horario['martes_estado'];
                    $horaEntradaProgramada = $horario['martes_entrada'];
                    $horaSalidaProgramada = $horario['martes_salida'];
                    break;
                case 3: // miércoles
                    $estadoDia = $horario['miercoles_estado'];
                    $horaEntradaProgramada = $horario['miercoles_entrada'];
                    $horaSalidaProgramada = $horario['miercoles_salida'];
                    break;
                case 4: // jueves
                    $estadoDia = $horario['jueves_estado'];
                    $horaEntradaProgramada = $horario['jueves_entrada'];
                    $horaSalidaProgramada = $horario['jueves_salida'];
                    break;
                case 5: // viernes
                    $estadoDia = $horario['viernes_estado'];
                    $horaEntradaProgramada = $horario['viernes_entrada'];
                    $horaSalidaProgramada = $horario['viernes_salida'];
                    break;
                case 6: // sábado
                    $estadoDia = $horario['sabado_estado'];
                    $horaEntradaProgramada = $horario['sabado_entrada'];
                    $horaSalidaProgramada = $horario['sabado_salida'];
                    break;
                case 7: // domingo
                    $estadoDia = $horario['domingo_estado'];
                    $horaEntradaProgramada = $horario['domingo_entrada'];
                    $horaSalidaProgramada = $horario['domingo_salida'];
                    break;
            }

            // Solo incluir días que tienen horario programado (estado no vacío)
            if (!empty($estadoDia) && $estadoDia !== 'Inactivo') {
                // Buscar TODAS las marcaciones para este día y operario (no solo una)
                // En modo 'todas' no filtramos por sucursal porque el colaborador puede
                // marcar en una sucursal diferente a la que tiene asignada en el horario
                $marcacionesExistentes = array_filter($marcaciones, function ($marcacion) use ($horario, $fechaStr, $modoVista) {
                    $match = $marcacion['CodOperario'] == $horario['cod_operario'] &&
                        $marcacion['fecha'] == $fechaStr;
                    if ($modoVista !== 'todas') {
                        $match = $match && $marcacion['sucursal_codigo'] == $horario['cod_sucursal'];
                    }
                    return $match;
                });

                // NUEVO: Verificar si ya existe falta registrada para este día
                $faltaYaRegistrada = verificarFaltaYaRegistrada(
                    $horario['cod_operario'],
                    $horario['cod_sucursal'],
                    $fechaStr
                );

                if (count($marcacionesExistentes) > 0) {
                    // Crear un registro por cada marcación encontrada
                    foreach ($marcacionesExistentes as $marcacionExistente) {
                        $key = $horario['cod_operario'] . '_' . $fechaStr . '_' . $horario['cod_sucursal'] . '_' .
                            $marcacionExistente['id'];

                        $registro = [
                            'id' => $marcacionExistente['id'],
                            'fecha' => $fechaStr,
                            'hora_ingreso' => $marcacionExistente['hora_ingreso'],
                            'hora_salida' => $marcacionExistente['hora_salida'],
                            'CodOperario' => $horario['cod_operario'],
                            'sucursal_codigo' => $horario['cod_sucursal'],
                            'nombre_sucursal' => $horario['nombre_sucursal'],
                            'Nombre' => $horario['Nombre'],
                            'Apellido' => $horario['Apellido'],
                            'Apellido2' => $horario['Apellido2'],
                            'numero_semana' => $horario['numero_semana'],
                            'estado_actual' => $horario['estado_actual'],
                            'nombre_cargo' => $horario['nombre_cargo'],
                            'codigo_cargo' => $horario['codigo_cargo'],
                            'hora_entrada_programada' => $horaEntradaProgramada,
                            'hora_salida_programada' => $horaSalidaProgramada,
                            'estado_dia' => $estadoDia,
                            'tiene_horario' => true,
                            'tiene_marcacion' => true,
                            'falta_ya_registrada' => $faltaYaRegistrada, // NUEVO
                            'es_falta_potencial' => false // NUEVO
                        ];

                        $resultado[$key] = $registro;
                    }
                } else {
                    // No hay marcaciones, crear registro solo con horario programado
                    // Esto es una FALTA POTENCIAL
                    $key = $horario['cod_operario'] . '_' . $fechaStr . '_' . $horario['cod_sucursal'] . '_0';

                    $registro = [
                        'id' => null,
                        'fecha' => $fechaStr,
                        'hora_ingreso' => null,
                        'hora_salida' => null,
                        'CodOperario' => $horario['cod_operario'],
                        'sucursal_codigo' => $horario['cod_sucursal'],
                        'nombre_sucursal' => $horario['nombre_sucursal'],
                        'Nombre' => $horario['Nombre'],
                        'Apellido' => $horario['Apellido'],
                        'Apellido2' => $horario['Apellido2'],
                        'numero_semana' => $horario['numero_semana'],
                        'estado_actual' => $horario['estado_actual'],
                        'nombre_cargo' => $horario['nombre_cargo'],
                        'codigo_cargo' => $horario['codigo_cargo'],
                        'hora_entrada_programada' => $horaEntradaProgramada,
                        'hora_salida_programada' => $horaSalidaProgramada,
                        'estado_dia' => $estadoDia,
                        'tiene_horario' => true,
                        'tiene_marcacion' => false,
                        'falta_ya_registrada' => $faltaYaRegistrada, // NUEVO
                        'es_falta_potencial' => true // NUEVO: Esta es una falta potencial
                    ];

                    $resultado[$key] = $registro;
                }
            }
        }
    }

    // CUARTO: Agregar marcaciones que no tengan horario programado (casos especiales)
    foreach ($marcaciones as $marcacion) {
        $key = $marcacion['CodOperario'] . '_' . $marcacion['fecha'] . '_' . $marcacion['sucursal_codigo'] . '_'
            . $marcacion['id'];

        if (!isset($resultado[$key])) {
            // Esta marcación no tiene horario programado correspondiente
            $registro = [
                'id' => $marcacion['id'],
                'fecha' => $marcacion['fecha'],
                'hora_ingreso' => $marcacion['hora_ingreso'],
                'hora_salida' => $marcacion['hora_salida'],
                'CodOperario' => $marcacion['CodOperario'],
                'sucursal_codigo' => $marcacion['sucursal_codigo'],
                'nombre_sucursal' => $marcacion['nombre_sucursal'],
                'Nombre' => $marcacion['Nombre'],
                'Apellido' => $marcacion['Apellido'],
                'Apellido2' => $marcacion['Apellido2'],
                'numero_semana' => $marcacion['numero_semana'],
                'estado_actual' => $marcacion['estado_actual'],
                'nombre_cargo' => $marcacion['nombre_cargo'],
                'codigo_cargo' => $marcacion['codigo_cargo'],
                'hora_entrada_programada' => null,
                'hora_salida_programada' => null,
                'estado_dia' => 'Sin horario programado',
                'tiene_horario' => false,
                'tiene_marcacion' => true
            ];

            $resultado[$key] = $registro;
        }
    }

    // Convertir el array asociativo a numérico
    $resultado = array_values($resultado);

    // Ordenar por fecha (más reciente primero) y luego por operario
    usort($resultado, function ($a, $b) {
        if ($a['fecha'] == $b['fecha']) {
            if ($a['CodOperario'] == $b['CodOperario']) {
                // Si es el mismo operario y misma fecha, ordenar por hora de ingreso
                return strtotime($a['hora_ingreso'] ?? '00:00:00') - strtotime($b['hora_ingreso'] ?? '00:00:00');
            }
            return strcmp($a['Nombre'] . $a['Apellido'], $b['Nombre'] . $b['Apellido']);
        }
        return strtotime($b['fecha']) - strtotime($a['fecha']);
    });

    return $resultado;
}

// Obtener nombre de sucursal seleccionada
$nombreSucursal = obtenerNombreSucursal($sucursalSeleccionada);

// Verificar si se solicitó la exportación a Excel, Faltas o Tardanzas
if (
    isset($_GET['exportar_excel']) || isset($_GET['exportar_faltas']) ||
    isset($_GET['exportar_tardanzas'])
) {
    // Limpiar cualquier buffer de salida
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Aplicar filtro de sucursal del panel de columna JS si viene en GET
    // El parámetro sucursal_filtro[] contiene los CÓDIGOS de sucursal seleccionados
    if (!empty($_GET['sucursal_filtro']) && is_array($_GET['sucursal_filtro'])) {
        $sucursalesFiltroExport = array_map('strval', $_GET['sucursal_filtro']);
        $marcaciones = array_filter($marcaciones, function($m) use ($sucursalesFiltroExport) {
            return in_array(strval($m['sucursal_codigo']), $sucursalesFiltroExport);
        });
        $marcaciones = array_values($marcaciones);
    }

    // Aplicar filtro de semana si viene en GET
    if (isset($_GET['semana_min']) && $_GET['semana_min'] !== '') {
        $semanaMin = intval($_GET['semana_min']);
        $marcaciones = array_filter($marcaciones, function($m) use ($semanaMin) {
            return intval($m['numero_semana']) >= $semanaMin;
        });
        $marcaciones = array_values($marcaciones);
    }
    if (isset($_GET['semana_max']) && $_GET['semana_max'] !== '') {
        $semanaMax = intval($_GET['semana_max']);
        $marcaciones = array_filter($marcaciones, function($m) use ($semanaMax) {
            return intval($m['numero_semana']) <= $semanaMax;
        });
        $marcaciones = array_values($marcaciones);
    }

    // Aplicar filtro de colaborador si viene en GET
    if (!empty($_GET['colaborador_filtro']) && is_array($_GET['colaborador_filtro'])) {
        $colaboradoresFiltroExport = array_map('strval', $_GET['colaborador_filtro']);
        $marcaciones = array_filter($marcaciones, function($m) use ($colaboradoresFiltroExport) {
            return in_array(strval($m['CodOperario']), $colaboradoresFiltroExport);
        });
        $marcaciones = array_values($marcaciones);
    }

    // Aplicar filtro de cargo si viene en GET
    if (!empty($_GET['cargo_filtro']) && is_array($_GET['cargo_filtro'])) {
        $cargosFiltroExport = array_map('strval', $_GET['cargo_filtro']);
        $marcaciones = array_filter($marcaciones, function($m) use ($cargosFiltroExport) {
            return in_array(strval($m['codigo_cargo']), $cargosFiltroExport);
        });
        $marcaciones = array_values($marcaciones);
    }

    // Aplicar filtro de estado_dia (Turno Programado) si viene en GET
    if (!empty($_GET['estado_dia_filtro']) && is_array($_GET['estado_dia_filtro'])) {
        $estadosFiltroExport = array_map('strval', $_GET['estado_dia_filtro']);
        $marcaciones = array_filter($marcaciones, function($m) use ($estadosFiltroExport) {
            return in_array(strval($m['estado_dia']), $estadosFiltroExport);
        });
        $marcaciones = array_values($marcaciones);
    }

    // Verificar si se solicitó la exportación a Excel
    if (isset($_GET['exportar_excel'])) {
        if (!$canExportMarcacionesBd) {
            header('Location: /login.php');
            exit();
        }
        // Configurar headers para descarga de archivo Excel con rango de fechas
        $labelSucursal = (!empty($_GET['sucursal_filtro']) && is_array($_GET['sucursal_filtro']))
            ? implode('_', array_map(function($s) { return preg_replace('/[^a-zA-Z0-9]/', '', $s); }, $_GET['sucursal_filtro']))
            : ($modoVista === 'todas' ? 'todas_sucursales' : preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nombreSucursal ?? 'sucursal'));
        $nombreArchivo = "marcaciones_{$labelSucursal}_{$fechaDesde}_a_{$fechaHasta}.xls";
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
        echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
        echo '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';

        // Iniciar salida
        echo '<table border="1">';
        echo '<tr>';
        if ($modoVista === 'todas') {
            echo '<th>Sucursal</th>';
        }
        echo '<th>Colaborador/a (Código)</th>';
        echo '<th>Fecha</th>';
        echo '<th>Estado Día</th>';
        echo '<th>Horario Programado</th>';
        echo '<th>Horas Programadas</th>';
        echo '<th>Horario Marcado</th>';
        echo '<th>Horas Trabajadas</th>';
        echo '<th>Diferencia Entrada</th>';
        echo '<th>Diferencia Salida</th>';
        echo '</tr>';

        foreach ($marcaciones as $marcacion) {
            $codOperario = $marcacion['CodOperario'];
            $totalOperario = $totalesOperarios[$codOperario] ?? 0;

            // Calcular diferencia en minutos para entrada
            $diferenciaEntrada = null;
            if ($marcacion['hora_entrada_programada'] && $marcacion['hora_ingreso']) {
                $horaProgramada = new DateTime($marcacion['hora_entrada_programada']);
                $horaReal = new DateTime($marcacion['hora_ingreso']);
                $diferencia = $horaReal->diff($horaProgramada);
                $diferenciaEntrada = ($diferencia->invert ? -1 : 1) * ($diferencia->h * 60 + $diferencia->i);
            }

            // Calcular diferencia en minutos para salida
            $diferenciaSalida = null;
            if ($marcacion['hora_salida_programada'] && $marcacion['hora_salida']) {
                $horaProgramada = new DateTime($marcacion['hora_salida_programada']);
                $horaReal = new DateTime($marcacion['hora_salida']);
                $diferencia = $horaReal->diff($horaProgramada);
                $diferenciaSalida = ($diferencia->invert ? -1 : 1) * ($diferencia->h * 60 + $diferencia->i);
            }

            // Formatear horario programado
            $horarioProgramado = '-';
            if ($marcacion['hora_entrada_programada'] || $marcacion['hora_salida_programada']) {
                $entrada = $marcacion['hora_entrada_programada'] ?
                    formatoHoraAmPm($marcacion['hora_entrada_programada']) : '-';
                $salida = $marcacion['hora_salida_programada'] ?
                    formatoHoraAmPm($marcacion['hora_salida_programada']) : '-';
                $horarioProgramado = $entrada . ' - ' . $salida;
            }

            // Formatear horario marcado
            $horarioMarcado = '-';
            if ($marcacion['hora_ingreso'] || $marcacion['hora_salida']) {
                $entrada = $marcacion['hora_ingreso'] ? formatoHoraAmPm($marcacion['hora_ingreso']) : '-';
                $salida = $marcacion['hora_salida'] ? formatoHoraAmPm($marcacion['hora_salida']) : '-';
                $horarioMarcado = $entrada . ' - ' . $salida;
            }

            // Calcular horas programadas
            $horasProgramadas = '-';
            if ($marcacion['hora_entrada_programada'] && $marcacion['hora_salida_programada']) {
                $entradaProg = new DateTime($marcacion['hora_entrada_programada']);
                $salidaProg = new DateTime($marcacion['hora_salida_programada']);

                if ($salidaProg < $entradaProg) {
                    $salidaProg->add(new DateInterval('P1D'));
                }

                $diferenciaProg = $salidaProg->diff($entradaProg);
                $horasProg = $diferenciaProg->h + ($diferenciaProg->i / 60);
                $horasProgramadas = number_format($horasProg, 2) . ' hrs';
            }

            // Calcular horas trabajadas
            $horasTrabajadas = '-';
            if ($marcacion['hora_ingreso'] && $marcacion['hora_salida']) {
                $entrada = new DateTime($marcacion['hora_ingreso']);
                $salida = new DateTime($marcacion['hora_salida']);

                if ($salida < $entrada) {
                    $salida->add(new DateInterval('P1D'));
                }

                $diferencia = $salida->diff($entrada);
                $horasTrabajadas = $diferencia->h + ($diferencia->i / 60);
                $horasTrabajadas = number_format($horasTrabajadas, 2) . ' hrs';
            }

            // Formatear diferencia entrada
            $diferenciaEntradaTexto = '-';
            if ($diferenciaEntrada !== null) {
                $diferenciaEntradaTexto = $diferenciaEntrada > 0 ? '+' . $diferenciaEntrada :
                    $diferenciaEntrada;
                $diferenciaEntradaTexto .= ' min';
            }

            // Formatear diferencia salida
            $diferenciaSalidaTexto = '-';
            if ($diferenciaSalida !== null) {
                $diferenciaSalidaTexto = $diferenciaSalida > 0 ? '+' . $diferenciaSalida :
                    $diferenciaSalida;
                $diferenciaSalidaTexto .= ' min';
            }

            echo '<tr>';
            echo '<td>' . ($marcacion['numero_semana'] ?? 'N/A') . '</td>';
            if ($modoVista === 'todas') {
                echo '<td>' . htmlspecialchars($marcacion['nombre_sucursal'] ?? '') . '</td>';
            }
            echo '<td>' . htmlspecialchars(obtenerNombreCompletoOperario($marcacion) ?? '') . ' (' .
                ($marcacion['CodOperario'] ?? '') . ')</td>';
            echo '<td>' . formatoFecha($marcacion['fecha'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($marcacion['estado_dia'] ?? 'Activo') . '</td>';
            echo '<td>' . $horarioProgramado . '</td>';
            echo '<td>' . $horasProgramadas . '</td>';
            echo '<td>' . $horarioMarcado . '</td>';
            echo '<td>' . $horasTrabajadas . '</td>';
            echo '<td>' . $diferenciaEntradaTexto . '</td>';
            echo '<td>' . $diferenciaSalidaTexto . '</td>';
            echo '<td>' . number_format($totalOperario, 2) . ' hrs</td>'; // USA EL TOTAL PRECALCULADO
            echo '</tr>';
        }

        echo '</table>';
        echo '</body></html>';
        exit;
    } elseif (isset($_GET['exportar_faltas'])) {
        // Exportar FALTAS con rango de fechas
        $nombreArchivo = "faltas_{$fechaDesde}_a_{$fechaHasta}.xls";
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
        echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
        echo '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';

        // Obtener datos para el reporte de faltas
        $reporteFaltas = generarReporteFaltas(
            $modoVista,
            $sucursalSeleccionada,
            $fechaDesde,
            $fechaHasta,
            $filtroActivo,
            $operario_id
        );

        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Activo</th>';
        echo '<th>Persona</th>';
        echo '<th>Codigo</th>';
        echo '<th>Sucursal</th>';
        echo '<th>Faltas Automáticas</th>';
        echo '<th>Faltas Reportadas</th>';
        echo '<th>Faltas Justificadas</th>';
        echo '<th>Faltas Ejecutadas</th>';
        echo '</tr>';

        foreach ($reporteFaltas as $fila) {
            echo '<tr>';
            echo '<td>' . ($fila['operativo'] == 1 ? 'SI' : 'NO') . '</td>';
            echo '<td>' . htmlspecialchars($fila['nombre_completo'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($fila['CodOperario'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($fila['nombre_sucursal'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($fila['faltas_automaticas'] ?? '0') . '</td>';
            echo '<td>' . htmlspecialchars($fila['faltas_reportadas'] ?? '0') . '</td>';
            echo '<td>' . htmlspecialchars($fila['faltas_justificadas'] ?? '0') . '</td>';
            echo '<td>' . htmlspecialchars($fila['faltas_ejecutadas'] ?? '0') . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</body></html>';
        exit;

    } elseif (isset($_GET['exportar_tardanzas'])) {
        // Exportar TARDANZAS con rango de fechas - VERSIÓN MODIFICADA
        $nombreArchivo = "tardanzas_{$fechaDesde}_a_{$fechaHasta}.xls";
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
        echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
        echo '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';

        // Obtener datos para el reporte de tardanzas (nueva versión)
        $reporteTardanzas = generarReporteTardanzas(
            $modoVista,
            $sucursalSeleccionada,
            $fechaDesde,
            $fechaHasta,
            $filtroActivo,
            $operario_id
        );

        // DEBUG: Ver qué estamos obteniendo
        error_log("Reporte Tardanzas (por contrato): " . print_r($reporteTardanzas, true));

        echo '<table border="1">';
        echo '<tr>';
        // echo '<th>Activo</th>';
        // echo '<th>Codigo</th>'; // Código de operario
        echo '<th>Código</th>'; // Código de contrato
        echo '<th>Persona</th>';
        // echo '<th>Sucursal</th>';
        echo '<th>Fecha Pago</th>';
        // echo '<th>1er quincena</th>';
        // echo '<th>2da quincena</th>';
        echo '<th>Tardanzas</th>'; // Tardanzas Ejecutadas
        // echo '<th>Total Tardanzas</th>';
        echo '<th>Tardanzas Justificadas</th>';
        // echo '<th>Tardanzas Reportadas</th>';
        // echo '<th>Fecha Registro</th>'; // NUEVA COLUMNA
        echo '</tr>';

        if (!empty($reporteTardanzas)) {
            foreach ($reporteTardanzas as $fila) {
                echo '<tr>';
                // echo '<td>' . ($fila['operativo'] == 1 ? 'SI' : 'NO') . '</td>';
                // echo '<td>' . htmlspecialchars($fila['CodOperario'] ?? '') . '</td>';
                // echo '<td>' . htmlspecialchars($fila['codigos_contrato_justificadas'] ??
                //     $fila['codigos_contrato'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($fila['cod_contrato'] ?? '') . '</td>';
                // echo '<td>' . htmlspecialchars($fila['nombre_completo'] ?? '') . '</td>';
                // USAR EL NUEVO CAMPO COMBINADO
                echo '<td>' . ($fila['cod_contrato'] ?? '') . ' ' . htmlspecialchars($fila['persona_completa']
                    ?? $fila['nombre_completo']) . '</td>';
                // echo '<td>' . htmlspecialchars($fila['nombre_sucursal'] ?? '') . '</td>';
                echo '<td></td>'; // Fecha Pago (vacío)
                // echo '<td></td>'; // 1er quincena (vacío)
                // echo '<td></td>'; // 2da quincena (vacío)
                echo '<td>' . htmlspecialchars($fila['tardanzas_ejecutadas'] ?? '0') . '</td>';
                // echo '<td>' . htmlspecialchars($fila['total_tardanzas'] ?? '0') . '</td>';
                echo '<td>' . htmlspecialchars($fila['tardanzas_justificadas'] ?? '0') . '</td>';
                // echo '<td>' . htmlspecialchars($fila['tardanzas_reportadas'] ?? '0') . '</td>';
                // echo '<td>' . htmlspecialchars($fila['fechas_registro_justificadas'] ??
                //     $fila['fechas_registro'] ?? '') . '</td>'; // NUEVA COLUMNA
                echo '</tr>';
            }

            // Agregar fila de totales
            // $totalTardanzas = array_sum(array_column($reporteTardanzas, 'total_tardanzas'));
            // $totalJustificadas = array_sum(array_column($reporteTardanzas, 'tardanzas_justificadas'));
            // $totalEjecutadas = array_sum(array_column($reporteTardanzas, 'tardanzas_ejecutadas'));

            // echo '<tr style="font-weight: bold; background-color: #f0f0f0;">';
            // echo '<td colspan="6" style="text-align: right;">TOTALES:</td>';
            // echo '<td>' . $totalTardanzas . '</td>';
            // echo '<td>' . $totalJustificadas . '</td>';
            // echo '<td>' . $totalEjecutadas . '</td>';
            // echo '<td></td>';
            // echo '</tr>';
        } else {
            echo '<tr>
                        <td colspan="12" style="text-align: center;">No se encontraron datos</td>
                    </tr>';
        }

        echo '</table>';
        echo '</body></html>';
        exit;
    }
}

// Función para determinar si una hora es nocturna (entre 8:00 PM y 11:59 PM)
function esHoraNocturna($hora)
{
    if (empty($hora))
        return false;

    $horaObj = DateTime::createFromFormat('H:i:s', $hora);
    if (!$horaObj)
        return false;

    $horaNum = (int) $horaObj->format('H');
    return $horaNum >= 20 && $horaNum < 24; // 8:00 PM a 11:59 PM
}

function obtenerTipoHorario($hora)
{
    if (empty($hora))
        return 'normal';
    $horaObj = DateTime::createFromFormat('H:i:s', $hora);
    if (!$horaObj)
        return 'normal';
    $horaNum = (int) $horaObj->format('H');

    if ($horaNum >= 20 && $horaNum < 24) { // 8:00 PM a 11:59 PM
        return 'nocturno';
    } elseif ($horaNum >= 12 && $horaNum < 20) { // 12:00 PM a 7:59 PM
        return 'tarde';
    } else { // Antes de las 12:00 PM
        return 'normal';
    }
}
/**
 * Genera reporte de faltas - VERSIÓN CORREGIDA
 */
function generarReporteFaltas($modoVista, $codSucursal, $fechaDesde, $fechaHasta, $filtroActivo, $operario_id)
{
    global $conn;
    try {
        // Obtenemos todos los operarios con su última asignación de sucursal
        $sqlOperarios = "SELECT
            o.CodOperario,
            o.Operativo,
            CONCAT_WS(' ',
                NULLIF(TRIM(o.Nombre),   ''),
                NULLIF(TRIM(o.Nombre2),  ''),
                NULLIF(TRIM(o.Apellido), ''),
                NULLIF(TRIM(o.Apellido2),'')
            ) AS nombre_completo,
            COALESCE(
                (SELECT s.nombre
                 FROM AsignacionNivelesCargos anc
                 JOIN sucursales s ON anc.Sucursal = s.codigo
                 WHERE anc.CodOperario = o.CodOperario
                 AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                 ORDER BY anc.Fecha DESC
                 LIMIT 1),
                s.nombre,
                'Sin asignar'
            ) AS nombre_sucursal,
            COALESCE(
                (SELECT anc.Sucursal
                 FROM AsignacionNivelesCargos anc
                 WHERE anc.CodOperario = o.CodOperario
                 AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                 ORDER BY anc.Fecha DESC
                 LIMIT 1),
                o.Sucursal
            ) AS codigo_sucursal
        FROM Operarios o
        LEFT JOIN sucursales s ON o.Sucursal = s.codigo
        WHERE (o.CodOperario NOT IN (
            SELECT CodOperario FROM AsignacionNivelesCargos
            WHERE CodNivelesCargos = 27 AND (Fin IS NULL OR Fin >= CURDATE())
        ) OR o.CodOperario NOT IN (
            SELECT CodOperario FROM AsignacionNivelesCargos
            WHERE CodNivelesCargos = 27
                    ))";
        $params = [];
        // Aplicar filtros según modo de vista
        if ($modoVista === 'sucursal' && $codSucursal && $codSucursal !== 'todas') {
            $sqlOperarios .= " AND (COALESCE(
                (SELECT anc.Sucursal
                 FROM AsignacionNivelesCargos anc
                 WHERE anc.CodOperario = o.CodOperario
                 AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                 ORDER BY anc.Fecha DESC
                 LIMIT 1),
                o.Sucursal
                            ) = ?)";
            $params[] = $codSucursal;
        }
        // Aplicar filtro de operario específico
        if ($operario_id > 0) {
            $sqlOperarios .= " AND o.CodOperario = ?";
            $params[] = $operario_id;
        }

        $sqlOperarios .= " GROUP BY o.CodOperario ORDER BY nombre_completo";

        $stmt = $conn->prepare($sqlOperarios);
        $stmt->execute($params);
        $operarios = $stmt->fetchAll();

        $resultado = [];

        foreach ($operarios as $operario) {
            // FALTAS AUTOMÁTICAS: Días con horario programado pero sin marcaciones
            $sqlFaltasAutomaticas = "SELECT COUNT(DISTINCT h.fecha) as total
                            FROM (
                            SELECT DATE(?) + INTERVAL (a.a + (10 * b.a)) DAY as fecha
                            FROM
                            (SELECT 0 a UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                            (SELECT 0 a UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                            WHERE DATE(?) + INTERVAL (a.a + (10 * b.a)) DAY <= ? ) h WHERE h.fecha BETWEEN ? AND ? AND h.fecha < CURDATE() AND
                                EXISTS ( SELECT 1 FROM HorariosSemanalesOperaciones hso JOIN SemanasSistema ss ON
                                hso.id_semana_sistema=ss.id WHERE hso.cod_operario=? AND hso.cod_sucursal=? AND h.fecha
                                BETWEEN ss.fecha_inicio AND ss.fecha_fin AND ( (DAYOFWEEK(h.fecha)=2 AND
                                hso.lunes_estado='Activo' ) OR (DAYOFWEEK(h.fecha)=3 AND hso.martes_estado='Activo' ) OR
                                (DAYOFWEEK(h.fecha)=4 AND hso.miercoles_estado='Activo' ) OR (DAYOFWEEK(h.fecha)=5 AND
                                hso.jueves_estado='Activo' ) OR (DAYOFWEEK(h.fecha)=6 AND hso.viernes_estado='Activo' )
                                OR (DAYOFWEEK(h.fecha)=7 AND hso.sabado_estado='Activo' ) OR (DAYOFWEEK(h.fecha)=1 AND
                                hso.domingo_estado='Activo' ) ) ) AND NOT EXISTS ( SELECT 1 FROM marcaciones m WHERE
                                m.CodOperario=? AND m.sucursal_codigo=? AND m.fecha=h.fecha AND (m.hora_ingreso IS NOT
                                NULL OR m.hora_salida IS NOT NULL) )";
            $stmtFaltas = $conn->
                prepare($sqlFaltasAutomaticas);
            $stmtFaltas->execute([
                $fechaDesde,
                $fechaDesde,
                $fechaHasta,
                $fechaDesde,
                $fechaHasta,
                $operario['CodOperario'],
                $operario['codigo_sucursal'],
                $operario['CodOperario'],
                $operario['codigo_sucursal']
            ]);
            $faltasAutomaticas = $stmtFaltas->fetch()['total'];

            // FALTAS REPORTADAS: Total de faltas en faltas_manual
            $sqlFaltasReportadas = "SELECT COUNT(*) as total
                                FROM faltas_manual
                                WHERE cod_operario = ?
                                AND fecha_falta BETWEEN ? AND ?
                                AND fecha_falta < CURDATE()";

            $stmtReportadas = $conn->prepare($sqlFaltasReportadas);
            $stmtReportadas->execute([$operario['CodOperario'], $fechaDesde, $fechaHasta]);
            $faltasReportadas = $stmtReportadas->fetch()['total'];

            // FALTAS JUSTIFICADAS: Faltas manuales que no son "No_Pagado" o "Pendiente"
            $sqlFaltasJustificadas = "SELECT COUNT(*) as total
                                FROM faltas_manual
                                WHERE cod_operario = ?
                                AND fecha_falta BETWEEN ? AND ?
                                AND fecha_falta < CURDATE()
                                AND tipo_falta NOT IN ('No_Pagado', 'Pendiente')";

            $stmtJustificadas = $conn->prepare($sqlFaltasJustificadas);
            $stmtJustificadas->execute([$operario['CodOperario'], $fechaDesde, $fechaHasta]);
            $faltasJustificadas = $stmtJustificadas->fetch()['total'];

            // FALTAS EJECUTADAS: Automáticas - Justificadas
            $faltasEjecutadas = max(0, $faltasAutomaticas - $faltasJustificadas);

            $resultado[] = [
                'nombre_completo' => $operario['nombre_completo'],
                'CodOperario' => $operario['CodOperario'],
                'nombre_sucursal' => $operario['nombre_sucursal'],
                'operativo' => $operario['Operativo'],
                'faltas_automaticas' => $faltasAutomaticas,
                'faltas_reportadas' => $faltasReportadas,
                'faltas_justificadas' => $faltasJustificadas,
                'faltas_ejecutadas' => $faltasEjecutadas
            ];
        }

        return $resultado;
    } catch (Exception $e) {
        error_log("Error en generarReporteFaltas: " . $e->getMessage());
        return [];
    }
}

/**
* Genera reporte de tardanzas - VERSIÓN MODIFICADA para combinar código de contrato y
nombre
*/
function generarReporteTardanzas(
    $modoVista,
    $codSucursal,
    $fechaDesde,
    $fechaHasta,
    $filtroActivo,
    $operario_id
) {
    global $conn;

    try {
        // Obtener todos los códigos de contrato únicos con sus operarios
        $sqlContratos = "SELECT DISTINCT
                                tm.cod_contrato,
                                tm.cod_operario,
                                o.Operativo,
                                CONCAT_WS(' ',
                                    NULLIF(TRIM(o.Nombre),   ''),
                                    NULLIF(TRIM(o.Nombre2),  ''),
                                    NULLIF(TRIM(o.Apellido), ''),
                                    NULLIF(TRIM(o.Apellido2),'')
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
                                AND tm.fecha_tardanza < CURDATE()
                                AND (o.CodOperario NOT IN (
                                SELECT CodOperario FROM AsignacionNivelesCargos
                                WHERE CodNivelesCargos = 27 AND (Fin IS NULL OR Fin >= CURDATE())
                                ) OR o.CodOperario NOT IN (
                                SELECT CodOperario FROM AsignacionNivelesCargos
                                WHERE CodNivelesCargos = 27
                                ))";

        $params = [$fechaDesde, $fechaHasta];

        // Aplicar filtros según modo de vista
        if ($modoVista === 'sucursal' && $codSucursal && $codSucursal !== 'todas') {
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

        // Aplicar filtro de operario específico
        if ($operario_id > 0) {
            $sqlContratos .= " AND tm.cod_operario = ?";
            $params[] = $operario_id;
        }

        $sqlContratos .= " GROUP BY tm.cod_contrato, tm.cod_operario
                                ORDER BY nombre_completo, tm.cod_contrato";

        $stmt = $conn->prepare($sqlContratos);
        $stmt->execute($params);
        $contratos = $stmt->fetchAll();

        $resultado = [];

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
                                AND m.fecha < CURDATE()
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

            // TARDANZAS REPORTADAS: Total de tardanzas en TardanzasManuales para este contrato
            $sqlTardanzasReportadas = "SELECT COUNT(*) as total,
                                GROUP_CONCAT(DISTINCT fecha_registro) as fechas_registro
                                FROM TardanzasManuales
                                WHERE cod_operario = ?
                                AND cod_contrato = ?
                                AND fecha_tardanza BETWEEN ? AND ?
                                AND fecha_tardanza < CURDATE()";

            $stmtReportadas = $conn->prepare($sqlTardanzasReportadas);
            $stmtReportadas->execute([$codOperario, $codContrato, $fechaDesde, $fechaHasta]);
            $resultReportadas = $stmtReportadas->fetch();
            $tardanzasReportadas = $resultReportadas['total'] ?? 0;
            $fechasRegistro = $resultReportadas['fechas_registro'] ?? '';

            // TARDANZAS JUSTIFICADAS: Tardanzas manuales con estado "Justificado" para este contrato
            $sqlTardanzasJustificadas = "SELECT COUNT(*) as total,
                                GROUP_CONCAT(DISTINCT fecha_registro) as fechas_registro
                                FROM TardanzasManuales
                                WHERE cod_operario = ?
                                AND cod_contrato = ?
                                AND fecha_tardanza BETWEEN ? AND ?
                                AND fecha_tardanza < CURDATE()
                                AND estado = 'Justificado'";

            $stmtJustificadas = $conn->prepare($sqlTardanzasJustificadas);
            $stmtJustificadas->execute([$codOperario, $codContrato, $fechaDesde, $fechaHasta]);
            $resultJustificadas = $stmtJustificadas->fetch();
            $tardanzasJustificadas = $resultJustificadas['total'] ?? 0;
            $fechasRegistroJustificadas = $resultJustificadas['fechas_registro'] ?? '';

            // TARDANZAS EJECUTADAS: Total - Justificadas
            $tardanzasEjecutadas = max(0, $totalTardanzas - $tardanzasJustificadas);

            // MODIFICACIÓN: Combinar código de contrato y nombre en una sola columna
            //$persona_completa = $codContrato . $contrato['nombre_completo'];
            $persona_completa = $contrato['nombre_completo'];

            $resultado[] = [
                'nombre_completo' => $contrato['nombre_completo'],
                'persona_completa' => $persona_completa, // NUEVO CAMPO COMBINADO
                'CodOperario' => $codOperario,
                'cod_contrato' => $codContrato,
                'nombre_sucursal' => $contrato['nombre_sucursal'],
                'operativo' => $contrato['Operativo'],
                'total_tardanzas' => $totalTardanzas,
                'tardanzas_reportadas' => $tardanzasReportadas,
                'tardanzas_justificadas' => $tardanzasJustificadas,
                'tardanzas_ejecutadas' => $tardanzasEjecutadas,
                'fechas_registro' => $fechasRegistro,
                'fechas_registro_justificadas' => $fechasRegistroJustificadas
            ];
        }

        return $resultado;

    } catch (Exception $e) {
        error_log("Error en generarReporteTardanzas: " . $e->getMessage());
        return [];
    }
}

/**
* Verifica si ya existe una falta manual registrada para un operario en una fecha
específica
*/
function verificarFaltaYaRegistrada($codOperario, $codSucursal, $fecha)
{
    global $conn;

    $stmt = $conn->prepare("
                                SELECT id FROM faltas_manual
                                WHERE cod_operario = ?
                                AND cod_sucursal = ?
                                AND fecha_falta = ?
                                LIMIT 1
                                ");
    $stmt->execute([$codOperario, $codSucursal, $fecha]);

    return $stmt->fetch() ? true : false;
}

/**
* Verifica si ya existe una tardanza manual registrada para un operario en una fecha
específica
*/
function verificarTardanzaYaRegistrada(
    $codOperario,
    $codSucursal,
    $fecha,
    $codContrato
    = null
) {
    global $conn;

    $sql = "SELECT id FROM TardanzasManuales
                                WHERE cod_operario = ?
                                AND cod_sucursal = ?
                                AND fecha_tardanza = ?";

    $params = [$codOperario, $codSucursal, $fecha];

    if ($codContrato !== null) {
        $sql .= " AND cod_contrato = ?";
        $params[] = $codContrato;
    }

    $sql .= " LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetch() ? true : false;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualización de Marcaciones</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/ver_marcaciones_todas.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($usuario['CodNivelesCargos']); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Historial de Marcaciones'); ?>

            <div class="container-fluid p-3">
                <?php if (isset($_SESSION['exito'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['exito'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['exito']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- En _nuevo.php el filtro de la columna "Sucursal" de la tabla maneja la selección; el selector superior no es necesario -->

                <!-- Toolbar de exportación (solo para perfiles con permisos de exportación) -->
                <?php if ($esContabilidad || $esOperaciones || $canExportMarcacionesBd): ?>
                    <div class="toolbar-container"
                        style="margin-bottom: 10px; display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
                        <?php if ($canExportMarcacionesBd): ?>
                            <a id="btn-exportar-excel"
                               href="ver_marcaciones_todas_nuevo.php?<?= http_build_query([
                                'modo' => $modoVista,
                                'sucursal' => $modoVista === 'sucursal' ? $sucursalSeleccionada : '',
                                'desde' => $fechaDesde,
                                'hasta' => $fechaHasta,
                                'activo' => $filtroActivo,
                                'operario_id' => $operario_id,
                                'exportar_excel' => 1
                            ]) ?>" class="btn btn-sm btn-success" title="Exportar Todo (aplica filtros activos)">
                                <i class="fas fa-file-excel"></i> Todo
                            </a>
                        <?php endif; ?>
                        <?php if ($esContabilidad): ?>
                            <a id="btn-exportar-faltas"
                               href="ver_marcaciones_todas_nuevo.php?<?= http_build_query([
                                'modo' => $modoVista,
                                'sucursal' => $modoVista === 'sucursal' ? $sucursalSeleccionada : '',
                                'desde' => $fechaDesde,
                                'hasta' => $fechaHasta,
                                'activo' => $filtroActivo,
                                'operario_id' => $operario_id,
                                'exportar_faltas' => 1
                            ]) ?>" class="btn btn-sm btn-danger" title="Exportar Faltas">
                                <i class="fas fa-user-slash"></i> Faltas
                            </a>
                            <a id="btn-exportar-tardanzas"
                               href="ver_marcaciones_todas_nuevo.php?<?= http_build_query([
                                'modo' => $modoVista,
                                'sucursal' => $modoVista === 'sucursal' ? $sucursalSeleccionada : '',
                                'desde' => $fechaDesde,
                                'hasta' => $fechaHasta,
                                'activo' => $filtroActivo,
                                'operario_id' => $operario_id,
                                'exportar_tardanzas' => 1
                            ]) ?>" class="btn btn-sm btn-warning" style="color: #000;" title="Exportar Tardanzas">
                                <i class="fas fa-clock"></i> Tardanzas
                            </a>
                            <a href="exportar_tardanzas_detalle.php?<?= http_build_query([
                                'modo' => $modoVista,
                                'sucursal' => $modoVista === 'sucursal' ? $sucursalSeleccionada : '',
                                'desde' => $fechaDesde,
                                'hasta' => $fechaHasta,
                                'activo' => $filtroActivo,
                                'operario_id' => $operario_id
                            ]) ?>" class="btn btn-sm btn-info" style="color: white;" title="Tardanzas Detalle">
                                <i class="fas fa-list"></i> Detalle
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="table-container" style="margin-top: 0;">
                    <?php if (empty($marcaciones)): ?>
                        <div class="no-results">
                            No se encontraron marcaciones para los filtros seleccionados.
                        </div>
                    <?php else: ?>
                        <table id="tabla-marcaciones">
                            <thead>
                                <tr>
                                    <th data-column="numero_semana" data-type="number" style="text-align: center;">
                                        Semana
                                        <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                    </th>
                                    <th data-column="nombre_sucursal" data-type="list" style="text-align: center;">
                                        Sucursal
                                        <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                    </th>
                                    <th data-column="nombre_completo" data-type="list" style="text-align: center;">
                                        Colaborador
                                        <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                    </th>
                                    <th data-column="nombre_cargo" data-type="list" style="text-align: center;">
                                        Cargo
                                        <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                    </th>
                                    <th data-column="fecha" data-type="daterange" style="text-align: center;">
                                        Fecha
                                        <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                    </th>
                                    <th data-column="estado_dia" data-type="list" style="text-align: center;">
                                        Turno Programado
                                        <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                    </th>
                                    <th style="text-align: center;">Horario Programado</th>
                                    <?php if ($esLider): ?>
                                        <th style="text-align: center;">Horas Programadas</th>
                                    <?php endif; ?>
                                    <th style="text-align: center;">Horario Marcado</th>
                                    <?php if ($esFotoMarcacion): ?>
                                        <th style="text-align: center; min-width: 100px;">
                                            <i class="bi bi-camera-video"></i> Foto
                                        </th>
                                    <?php endif; ?>
                                    <?php if ($esLider): ?>
                                        <th style="text-align: center;">Horas Trabajadas</th>
                                    <?php endif; ?>
                                    <th style="display:none;">Diferencia Entrada</th>
                                    <th style="display:none;">Diferencia Salida</th>
                                    <?php if ($esOperaciones): ?>
                                        <th style="text-align: center;">Total Horas<br>Semana</th>
                                    <?php endif; ?>
                                    <th style="text-align: center;">
                                        <div class="header-actions-container">
                                            <div style="margin-bottom: 5px;">Acciones</div>
                                            <div class="tri-state-filter-group">
                                                <span class="tri-btn neutral active" onclick="setFiltroIncidencias('todos')"
                                                    title="Ver Todo">
                                                    <i class="fas fa-minus-circle"></i>
                                                </span>
                                                <span class="tri-btn warning" onclick="setFiltroIncidencias('tardanzas')"
                                                    title="Ver solo TARDANZAS">
                                                    <i class="fas fa-clock"></i>
                                                </span>
                                                <span class="tri-btn danger" onclick="setFiltroIncidencias('faltas')"
                                                    title="Ver solo FALTAS">
                                                    <i class="fas fa-user-slash"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tablaMarcacionesBody">
                                <!-- Datos cargados vía AJAX -->
                            </tbody>
                        </table>

                        <!-- Controles de paginación -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="d-flex align-items-center gap-2">
                                <label class="mb-0">Mostrar:</label>
                                <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;"
                                    onchange="cambiarRegistrosPorPagina()">
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span class="mb-0">registros</span>
                            </div>
                            <div id="paginacion"></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal para registrar tardanza manual -->
            <div class="modal" id="modalTardanzaManual" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h2 class="modal-title">Registrar Justificación de Tardanza</h2>
                        <button class="modal-close" onclick="cerrarModalTardanza()">&times;</button>
                    </div>
                    <form id="formTardanzaManual" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="registrar_tardanza_marcacion" value="1">
                        <input type="hidden" id="tm_cod_operario" name="cod_operario">
                        <input type="hidden" id="tm_cod_sucursal" name="cod_sucursal">
                        <input type="hidden" id="tm_fecha_tardanza" name="fecha_tardanza">
                        <input type="hidden" id="tm_cod_contrato" name="cod_contrato">

                        <div class="modal-body">
                            <div class="info-group" style="display:none;">
                                <span class="info-label">Colaborador:</span>
                                <span class="info-value" id="tm_nombre_operario"></span>
                            </div>

                            <div class="info-group" style="display:none;">
                                <span class="info-label">Sucursal:</span>
                                <span class="info-value" id="tm_nombre_sucursal"></span>
                            </div>

                            <div class="info-group" style="display:none;">
                                <span class="info-label">Fecha:</span>
                                <span class="info-value" id="tm_fecha_formateada"></span>
                            </div>

                            <div class="info-group" style="display:none;">
                                <span class="info-label">Horario Programado:</span>
                                <span class="info-value" id="tm_hora_programada"></span>
                            </div>

                            <div class="info-group" style="display:none;">
                                <span class="info-label">Horario Marcado:</span>
                                <span class="info-value" id="tm_hora_marcada"></span>
                            </div>

                            <div class="form-group">
                                <label for="tm_tipo_justificacion" class="form-label">Tipo de Justificación:</label>
                                <select id="tm_tipo_justificacion" name="tipo_justificacion" class="form-select"
                                    required>
                                    <option value="llave">Problema con llave</option>
                                    <option value="error_sistema">Error del sistema
                                    </option>
                                    <option value="accidente">Accidente/tráfico</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="tm_foto" class="form-label">Foto
                                    (obligatorio):</label>
                                <input type="file" id="tm_foto" name="foto" class="form-input" accept="image/*"
                                    required>
                                <img id="tm_foto_preview" class="photo-preview" src="#" alt="Vista previa"
                                    style="display: none;">
                            </div>

                            <div class="form-group">
                                <label for="tm_observaciones" class="form-label">Observaciones:</label>
                                <textarea id="tm_observaciones" name="observaciones" class="form-textarea"
                                    placeholder="Describa la razón de la tardanza..."></textarea>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="cerrarModalTardanza()"
                                class="btn btn-secondary">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Enviar
                                Solicitud</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para registrar falta manual -->
            <div class="modal" id="modalFaltaManual" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h2 class="modal-title">Registrar Justificacion de Falta</h2>
                        <button class="modal-close" onclick="cerrarModalFalta()">&times;</button>
                    </div>
                    <form id="formFaltaManual" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="registrar_falta_marcacion" value="1">
                        <input type="hidden" id="fm_cod_operario" name="cod_operario">
                        <input type="hidden" id="fm_cod_sucursal" name="cod_sucursal">
                        <input type="hidden" id="fm_fecha_falta" name="fecha_falta">

                        <div class="modal-body">
                            <div class="info-group" style="display:none;">
                                <span class="info-label">Colaborador:</span>
                                <span class="info-value" id="fm_nombre_operario"></span>
                            </div>

                            <div class="info-group" style="display:none;">
                                <span class="info-label">Sucursal:</span>
                                <span class="info-value" id="fm_nombre_sucursal"></span>
                            </div>

                            <div class="info-group" style="display:none;">
                                <span class="info-label">Fecha:</span>
                                <span class="info-value" id="fm_fecha_formateada"></span>
                            </div>

                            <div class="info-group" style="display:none;">
                                <span class="info-label">Horario Programado:</span>
                                <span class="info-value" id="fm_hora_programada"></span>
                            </div>

                            <div class="form-group">
                                <label for="fm_observaciones" class="form-label">Observaciones:</label>
                                <textarea id="fm_observaciones" name="observaciones" class="form-textarea"
                                    placeholder="Describa la razón de la falta... (Ej: No se presentó, enfermedad, etc.)"
                                    required></textarea>
                            </div>

                            <div class="form-group">
                                <label for="fm_foto" class="form-label">Foto
                                    (obligatorio):</label>
                                <input type="file" id="fm_foto" name="foto" class="form-input" accept="image/*"
                                    required>
                                <small class="form-text text-muted">Toma una foto de la
                                    evidencia (máx. 5MB)</small>
                                <img id="fm_foto_preview" class="photo-preview" src="#" alt="Vista previa"
                                    style="display: none;">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="cerrarModalFalta()"
                                class="btn btn-secondary">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Registrar
                                Falta</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                // Las funciones aplicarFiltros, cambiarFiltroActivo y lógica de autocompletado han sido eliminadas por redundancia con el nuevo sistema AJAX.

                // Función para mostrar el modal de tardanza
                function mostrarModalTardanza(codOperario, nombre, codSucursal, nombreSucursal, fecha, horaProgramada, horaMarcada, codContrato, esTardanza) {
                    // Llenar los campos ocultos
                    const elOperario = document.getElementById('tm_cod_operario');
                    const elSucursal = document.getElementById('tm_cod_sucursal');
                    const elFecha = document.getElementById('tm_fecha_tardanza');
                    const elContrato = document.getElementById('tm_cod_contrato');

                    if (elOperario) elOperario.value = codOperario;
                    if (elSucursal) elSucursal.value = codSucursal;
                    if (elFecha) elFecha.value = fecha;
                    if (elContrato) elContrato.value = codContrato || '';

                    // Llenar la información visible
                    const elNombre = document.getElementById('tm_nombre_operario');
                    const elNomSucursal = document.getElementById('tm_nombre_sucursal');
                    const elFechaForm = document.getElementById('tm_fecha_formateada');

                    if (elNombre) elNombre.textContent = nombre;
                    if (elNomSucursal) elNomSucursal.textContent = nombreSucursal;
                    if (elFechaForm) elFechaForm.textContent = formatoFechaCorta(fecha);

                    // Formatear horas
                    const elHoraProg = document.getElementById('tm_hora_programada');
                    const elHoraMarc = document.getElementById('tm_hora_marcada');

                    if (elHoraProg) elHoraProg.textContent = formatoHoraAmPm(horaProgramada);
                    if (elHoraMarc) elHoraMarc.textContent = formatoHoraAmPm(horaMarcada);

                    // Calcular minutos de diferencia
                    const minutos = calcularMinutosDiferencia(horaProgramada, horaMarcada);
                    if (elHoraMarc && minutos > 0) {
                        elHoraMarc.textContent += ` (${minutos} min ${esTardanza ? 'tarde' : 'en minuto de gracia'})`;
                    }

                    // Mostrar el modal
                    const elModal = document.getElementById('modalTardanzaManual');
                    if (elModal) elModal.style.display = 'flex';
                }

                // Función para formatear fecha corta
                function formatoFechaCorta(fechaStr) {
                    const fecha = new Date(fechaStr + 'T00:00:00');
                    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                    const dia = fecha.getDate().toString().padStart(2, '0');
                    const mes = meses[fecha.getMonth()];
                    const año = fecha.getFullYear().toString().slice(-2);
                    return `${dia}-${mes}-${año}`;
                }

                // Función para formatear hora AM/PM
                function formatoHoraAmPm(horaStr) {
                    if (!horaStr) return '-';
                    const [hora, minuto] = horaStr.split(':');
                    const horaNum = parseInt(hora);
                    const periodo = horaNum >= 12 ? 'PM' : 'AM';
                    const hora12 = horaNum % 12 || 12;
                    return `${hora12}:${minuto} ${periodo}`;
                }

                // Función para calcular minutos de diferencia
                function calcularMinutosDiferencia(horaInicio, horaFin) {
                    const inicio = new Date(`2000-01-01T${horaInicio}`);
                    const fin = new Date(`2000-01-01T${horaFin}`);
                    const diferencia = fin - inicio;
                    return Math.round(diferencia / 60000); // Convertir a minutos
                }

                // Función para cerrar el modal
                function cerrarModalTardanza() {
                    document.getElementById('modalTardanzaManual').style.display = 'none';
                    // Limpiar el formulario
                    document.getElementById('formTardanzaManual').reset();
                    document.getElementById('tm_foto_preview').style.display = 'none';
                }

                // Vista previa de la foto
                const tmFoto = document.getElementById('tm_foto');
                if (tmFoto) {
                    tmFoto.addEventListener('change', function (e) {
                        const preview = document.getElementById('tm_foto_preview');
                        const file = e.target.files[0];

                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function (e) {
                                if (preview) {
                                    preview.src = e.target.result;
                                    preview.style.display = 'block';
                                }
                            }
                            reader.readAsDataURL(file);
                        } else {
                            if (preview) preview.style.display = 'none';
                        }
                    });
                }

                // Procesar el formulario
                const formTardanzaManual = document.getElementById('formTardanzaManual');
                if (formTardanzaManual) {
                    formTardanzaManual.addEventListener('submit', function (e) {
                        e.preventDefault();

                        const formData = new FormData(this);

                        // Validar foto
                        const fotoInput = document.getElementById('tm_foto');
                        if (fotoInput && (!fotoInput.files || fotoInput.files.length === 0)) {
                            alert('Debe seleccionar una foto como evidencia');
                            return false;
                        }

                        // Mostrar loading
                        const submitBtn = this.querySelector('button[type="submit"]');
                        let originalText = '';
                        if (submitBtn) {
                            originalText = submitBtn.innerHTML;
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                            submitBtn.disabled = true;
                        }

                        // Enviar la solicitud
                        fetch('ajax/procesar_tardanza_marcacion.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert(data.message);
                                    cerrarModalTardanza();
                                    // Actualizar la tabla dinámicamente vía AJAX
                                    if (typeof cargarDatos === 'function') {
                                        cargarDatos();
                                    } else {
                                        setTimeout(() => location.reload(), 1000);
                                    }
                                } else {
                                    alert('Error: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error al enviar la solicitud');
                            })
                            .finally(() => {
                                // Restaurar botón
                                if (submitBtn) {
                                    submitBtn.innerHTML = originalText;
                                    submitBtn.disabled = false;
                                }
                            });
                    });
                }

                // Función para mostrar el modal de falta
                function mostrarModalFalta(codOperario, nombre, codSucursal, nombreSucursal, fecha, horaEntradaProgramada, horaSalidaProgramada) {
                    // Llenar los campos ocultos
                    const elOperario = document.getElementById('fm_cod_operario');
                    const elSucursal = document.getElementById('fm_cod_sucursal');
                    const elFecha = document.getElementById('fm_fecha_falta');

                    if (elOperario) elOperario.value = codOperario;
                    if (elSucursal) elSucursal.value = codSucursal;
                    if (elFecha) elFecha.value = fecha;

                    // Llenar la información visible
                    const elNombre = document.getElementById('fm_nombre_operario');
                    const elNomSucursal = document.getElementById('fm_nombre_sucursal');
                    const elFechaForm = document.getElementById('fm_fecha_formateada');

                    if (elNombre) elNombre.textContent = nombre;
                    if (elNomSucursal) elNomSucursal.textContent = nombreSucursal;
                    if (elFechaForm) elFechaForm.textContent = formatoFechaCorta(fecha);

                    // Formatear horas programadas
                    const horaEntrada = horaEntradaProgramada ? formatoHoraAmPm(horaEntradaProgramada) : '-';
                    const horaSalida = horaSalidaProgramada ? formatoHoraAmPm(horaSalidaProgramada) : '-';
                    const elHoraProg = document.getElementById('fm_hora_programada');
                    if (elHoraProg) elHoraProg.textContent = `${horaEntrada} - ${horaSalida}`;

                    // Mostrar el modal
                    const elModal = document.getElementById('modalFaltaManual');
                    if (elModal) elModal.style.display = 'flex';
                }

                // Función para cerrar el modal de falta
                function cerrarModalFalta() {
                    document.getElementById('modalFaltaManual').style.display = 'none';
                    // Limpiar el formulario
                    document.getElementById('formFaltaManual').reset();
                    document.getElementById('fm_foto_preview').style.display = 'none';
                }

                // Vista previa de la foto para falta
                const fmFoto = document.getElementById('fm_foto');
                if (fmFoto) {
                    fmFoto.addEventListener('change', function (e) {
                        const preview = document.getElementById('fm_foto_preview');
                        const file = e.target.files[0];

                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function (e) {
                                if (preview) {
                                    preview.src = e.target.result;
                                    preview.style.display = 'block';
                                }
                            }
                            reader.readAsDataURL(file);
                        } else {
                            if (preview) preview.style.display = 'none';
                        }
                    });
                }

                // Procesar el formulario de falta
                const formFaltaManual = document.getElementById('formFaltaManual');
                if (formFaltaManual) {
                    formFaltaManual.addEventListener('submit', function (e) {
                        e.preventDefault();

                        const formData = new FormData(this);

                        // Validar foto
                        const fotoInput = document.getElementById('fm_foto');
                        if (fotoInput && (!fotoInput.files || fotoInput.files.length === 0)) {
                            alert('Debe seleccionar una foto como evidencia');
                            return false;
                        }

                        // Validar observaciones
                        const observationsInput = document.getElementById('fm_observaciones');
                        const observaciones = observationsInput ? observationsInput.value.trim() : '';
                        if (!observaciones) {
                            alert('Debe ingresar las observaciones de la falta');
                            return false;
                        }

                        // Mostrar loading
                        const submitBtn = this.querySelector('button[type="submit"]');
                        let originalText = '';
                        if (submitBtn) {
                            originalText = submitBtn.innerHTML;
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
                            submitBtn.disabled = true;
                        }

                        // Enviar la solicitud
                        fetch('ajax/procesar_falta_marcacion.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert(data.message);
                                    cerrarModalFalta();
                                    // Actualizar la tabla dinámicamente vía AJAX
                                    if (typeof cargarDatos === 'function') {
                                        cargarDatos();
                                    } else {
                                        setTimeout(() => location.reload(), 1000);
                                    }
                                } else {
                                    alert('Error: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error al enviar la solicitud');
                            })
                            .finally(() => {
                                // Restaurar botón
                                if (submitBtn) {
                                    submitBtn.innerHTML = originalText;
                                    submitBtn.disabled = false;
                                }
                            });
                    });
                }

                // Función para aplicar filtros específica para líderes
                function aplicarFiltrosLider() {
                    let sucursal, modo;

                // Para líderes: usar el selector si existe (multi-sucursal), sino la asignada
                const selectSucursal = document.getElementById('sucursal_lider_select');
                sucursal = selectSucursal ? selectSucursal.value : '<?= $sucursalSeleccionada ?? ($sucursales[0]['codigo'] ?? '') ?>';
                modo = 'sucursal';

                    const operario_id = document.getElementById('operario_id') ? document.getElementById('operario_id').value : '';
                    const numeroSemana = document.getElementById('numero_semana') ? document.getElementById('numero_semana').value : '';

                    // Aplicar filtros al sistema AJAX unificado
                    if (typeof filtrosActivos !== 'undefined') {
                        // Mapear los filtros manuales al sistema global de filtros
                        if (operario_id) filtrosActivos['operario_id'] = operario_id;
                        if (numeroSemana) {
                            filtrosActivos['numero_semana'] = numeroSemana;
                            delete filtrosActivos['fecha']; // Semana y fecha son mutuamente excluyentes
                        }

                        // Reiniciar a la primera página y cargar
                        paginaActual = 1;
                        cargarDatos();
                    }
                }

                // Función para seleccionar una semana (solo para líderes)
                function seleccionarSemana(numeroSemana) {
                    if (!numeroSemana) return; // Si selecciona la opción vacía

                    const selectElement = document.getElementById('numero_semana');
                    if (!selectElement) return;
                    const selectedOption = selectElement.options[selectElement.selectedIndex];

                    // Obtener fechas de inicio y fin de la semana seleccionada
                    const fechaInicio = selectedOption.getAttribute('data-fecha-inicio');
                    const fechaFin = selectedOption.getAttribute('data-fecha-fin');

                    // Establecer las fechas en los filtros AJAX
                    if (typeof filtrosActivos !== 'undefined') {
                        filtrosActivos['fecha'] = { desde: fechaInicio, hasta: fechaFin };
                        delete filtrosActivos['numero_semana']; // Mutuamente excluyentes

                        paginaActual = 1;
                        cargarDatos();
                    }
                }

                // Función para limpiar todos los filtros
                function limpiarTodosFiltros() {
                    if (typeof filtrosActivos !== 'undefined') {
                        filtrosActivos = {};
                    }

                    <?php if ($esLider): ?>
                        // Para líderes
                        if (document.getElementById('numero_semana')) document.getElementById('numero_semana').value = '';
                        if (document.getElementById('operario_id')) document.getElementById('operario_id').value = '<?= $_SESSION['usuario_id'] ?>';

                        // Establecer fecha actual
                        const hoy = '<?= $fechaHoy ?>';
                        const primerDiaMes = hoy.substring(0, 8) + '01';

                        if (typeof filtrosActivos !== 'undefined') {
                            filtrosActivos['fecha'] = { desde: primerDiaMes, hasta: hoy };
                            filtrosActivos['operario_id'] = '<?= $_SESSION['usuario_id'] ?>';
                            paginaActual = 1;
                            cargarDatos();
                            if (typeof actualizarIndicadoresFiltros === 'function') actualizarIndicadoresFiltros();
                        }
                    <?php else: ?>
                        // Para otros usuarios
                        if (document.getElementById('sucursal')) document.getElementById('sucursal').value = 'todas';
                        if (document.getElementById('operario')) document.getElementById('operario').value = 'Todos los colaboradores';
                        if (document.getElementById('operario_id')) document.getElementById('operario_id').value = '0';

                        // Establecer fecha actual
                        const hoy = '<?= $fechaHoy ?>';
                        const primerDiaMes = hoy.substring(0, 8) + '01';

                        if (typeof filtrosActivos !== 'undefined') {
                            filtrosActivos['fecha'] = { desde: primerDiaMes, hasta: hoy };
                            paginaActual = 1;
                            cargarDatos();
                            if (typeof actualizarIndicadoresFiltros === 'function') actualizarIndicadoresFiltros();
                        }
                    <?php endif; ?>
                }
            </script>

            <!-- jQuery (required for filter system) -->
            <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

            <!-- Bootstrap 5 Bundle with Popper (required for tooltips) -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

            <!-- Permisos del usuario -->
            <script>
                const PERMISOS_USUARIO = {
                    esLider: <?= $esLider ? 'true' : 'false' ?>,
                    esOperaciones: <?= $esOperaciones ? 'true' : 'false' ?>,
                    esCDS: <?= $esCDS ? 'true' : 'false' ?>,
                    esContabilidad: <?= $esContabilidad ? 'true' : 'false' ?>,
                    esFotoMarcacion: <?= $esFotoMarcacion ? 'true' : 'false' ?>,
                    esCambiarFotoMarcacion: <?= $esCambiarFotoMarcacion ? 'true' : 'false' ?>,
                    fechaHoy: '<?= $fechaHoy ?>',
                    sucursalLider: '<?= htmlspecialchars($sucursalAsignacionLider ?? '') ?>'
                };

                // Función para cambiar sucursal del líder (multi-sucursal)
                function cambiarSucursalLider(codSucursal) {
                    const params = new URLSearchParams(window.location.search);
                    params.set('sucursal', codSucursal);
                    window.location.href = 'ver_marcaciones_todas_nuevo.php?' + params.toString();
                }
            </script>



            <!-- Custom filter system -->
            <script src="js/ver_marcaciones_todas.js?v=<?php echo time(); ?>"></script>
        </div>
        <!-- sub-container -->
    </div> <!-- main-container -->


    <!-- ── Modal Visor Foto Marcación DVR (Premium) ── -->
    <div class="modal fade" id="modalFotoMarcacion" tabindex="-1" data-bs-backdrop="false"
         aria-labelledby="fotoModalTituloLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:720px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">

                <!-- Header premium verde -->
                <div class="modal-header border-0 py-3 px-4" style="background:#0E544C; color:#fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                             style="width:40px; height:40px; flex-shrink:0;">
                            <i class="bi bi-camera-fill fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0 text-white" id="fotoModalTituloLabel" style="color: #ffffff !important;">
                                <span id="fotoModalTitulo" class="text-white" style="color: #ffffff !important;">Foto DVR &mdash; Marcación</span>
                            </h5>
                            <p class="small mb-0 opacity-75 text-white-50" id="fotoModalSubtitulo" style="color: rgba(255, 255, 255, 0.75) !important;"></p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white ms-auto"
                            data-bs-dismiss="modal" onclick="cerrarModalFoto()" aria-label="Cerrar"></button>
                </div>

                <!-- Body -->
                <div class="modal-body p-4 pb-0" style="background:#ffffff;">

                    <!-- Contenedor imagen -->
                    <div id="fotoModalContenedor"
                         style="background:#f8f9fa; border:1px solid #dee2e6; border-radius:12px;
                                min-height:260px; display:flex; align-items:center;
                                justify-content:center; overflow:hidden;">
                        <span style="color:#adb5bd; font-size:.88rem; text-align:center;">
                            <i class="bi bi-camera" style="font-size:2.2rem; display:block; margin-bottom:8px; opacity:.35;"></i>
                            Iniciando captura&hellip;
                        </span>
                    </div>

                    <!-- Controles de tiempo -->
                    <div id="fotoOffsetControls"
                         style="margin-top:16px; <?= !$esCambiarFotoMarcacion ? 'display:none;' : '' ?>">

                        <!-- Label estado actual -->
                        <div class="text-center mb-3" style="font-size:.8rem; color:#6c757d;">
                            <i class="bi bi-clock-history" style="color:#0E544C;"></i>
                            &nbsp;Capturando en:&nbsp;<span id="fotoOffsetLabel"><strong>hora exacta</strong></span>
                        </div>

                        <!-- Controles en una línea -->
                        <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">

                            <!-- Toggle Antes / Después -->
                            <div class="btn-group btn-group-sm" role="group" aria-label="Dirección del offset">
                                <input type="radio" class="btn-check" name="fotoOffsetDir" id="dirAntes" value="-1" checked>
                                <label class="btn btn-outline-secondary" for="dirAntes" style="font-size:.8rem; padding:5px 14px;">
                                    <i class="bi bi-skip-backward-fill"></i> Antes
                                </label>
                                <input type="radio" class="btn-check" name="fotoOffsetDir" id="dirDespues" value="1">
                                <label class="btn btn-outline-secondary" for="dirDespues" style="font-size:.8rem; padding:5px 14px;">
                                    Después <i class="bi bi-skip-forward-fill"></i>
                                </label>
                            </div>

                            <!-- Input segundos -->
                            <div class="input-group input-group-sm" style="max-width:130px;">
                                <input type="number" id="fotoOffsetInput"
                                       class="form-control text-center"
                                       value="0" min="0" max="3600" placeholder="0"
                                       onkeydown="if(event.key==='Enter') aplicarOffsetFoto()"
                                       style="font-weight:700; font-size:.9rem;">
                                <span class="input-group-text" style="font-size:.8rem; color:#6c757d;">seg</span>
                            </div>

                            <!-- Botón capturar -->
                            <button type="button" class="btn-modern btn-modern-primary"
                                    onclick="aplicarOffsetFoto()"
                                    style="padding:7px 20px; font-size:.82rem;">
                                <i class="bi bi-camera-fill"></i> Capturar
                            </button>

                        </div>
                    </div>

                    <!-- Meta info (KB, timestamp) -->
                    <div id="fotoModalMeta" style="display:flex; flex-wrap:wrap; gap:8px;"></div>
                </div>

                <!-- Footer -->
                <div class="modal-footer border-0 px-4 pt-2 pb-4 bg-white d-flex justify-content-end">
                    <button type="button" class="btn-modern btn-modern-secondary"
                            data-bs-dismiss="modal" onclick="cerrarModalFoto()">
                        Cerrar
                    </button>
                </div>

            </div>
        </div>
    </div>
    <!-- /Modal Foto Marcación -->


    <style>
        /* Ajuste de z-index para evitar que el backdrop cubra el modal */
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }

        /* Modal Foto DVR: backdrop propio integrado para evitar conflictos de z-index */
        #modalFotoMarcacion {
            background-color: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
    </style>

    <!-- Modal Guía de Reglas -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalGuiaReglasLabel">
                        <i class="fas fa-book-reader me-2"></i> Guía de Reglas y Lógica del Sistema
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-clock me-2"></i> Tardanzas
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Se reporta como <strong>tardanza</strong> cuando la marcación de entrada ocurre
                                        <strong>más de 1 minuto</strong> después de la hora programada.
                                        <br><br>
                                        Un día en curso (hoy) no muestra incidencias hasta que el día termina. prueba
                                        <br><br>
                                        La verificacion de tardanzas se hara con los horarios programados y los
                                        ejecutados tanto en casos de horario programado "Otra.Sucursal" y en "Activo"
                                        con las marcaciones realizadas en cualquier sucursal.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-danger border-bottom pb-2 fw-bold">
                                        <i class="fas fa-user-slash me-2"></i> Faltas
                                    </h6>
                                    <p class="small text-muted mb-2">
                                        El sistema detecta una <strong>falta</strong> si no hay marcación en un día
                                        programado
                                        cuyo estado esté configurado como <strong>"Con Marcación"</strong> (ej: Activo).
                                        <br><br>
                                        Estados como "Libre" o "Subsidio" no generan faltas.
                                    </p>
                                    <div class="alert alert-success py-2 px-3 mb-0 small">
                                        <strong><i class="fas fa-info-circle me-1"></i> Inasistencias
                                            Programadas:</strong>
                                        <br>
                                        Algunos días sin marcación (ej: Vacaciones, Permisos) requieren justificación
                                        aunque estén programados.
                                        Estos se muestran con <strong>ícono verde</strong> en lugar de rojo para
                                        diferenciarlos de faltas normales.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="fas fa-users-cog me-2"></i> Equipo de Trabajo
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Los líderes ven a los colaboradores asignados a su sucursal de equipo.
                                        <br><br>
                                        Las marcaciones son válidas sin importar en qué sucursal física se realicen,
                                        siempre que coincidan con el día programado.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-secondary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-map-marker-alt me-2"></i> Sucursal Externa
                                    </h6>
                                    <p class="small text-muted mb-2">
                                        <strong>Marcación en sucursal diferente:</strong><br>
                                        Si un colaborador marca en una sucursal distinta a la programada, se mostrará un
                                        <strong>tag gris</strong> bajo el horario marcado indicando el nombre del lugar
                                        exacto.
                                    </p>
                                    <p class="small text-muted mb-0">
                                        <strong>Horario programado en otra tienda:</strong><br>
                                        Cuando el estado del día es <strong>"Otra.Tienda"</strong>, se muestra un
                                        <strong>tag gris</strong> bajo el turno programado con el nombre de la sucursal
                                        donde está asignado ese día.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-dark border-bottom pb-2 fw-bold">
                                        <i class="fas fa-stream me-2"></i> Semáforo (Status Tracker)
                                    </h6>
                                    <p class="small text-muted mb-2">
                                        El sistema muestra un semáforo de 3 pasos para el seguimiento de incidencias:
                                    </p>
                                    <div class="d-flex justify-content-between mt-2 mb-3">
                                        <div class="text-center">
                                            <span class="badge bg-secondary mb-1">Gris</span>
                                            <div class="small text-muted">Pendiente de Revisión</div>
                                        </div>
                                        <div class="text-center">
                                            <span class="badge bg-success mb-1">Verde</span>
                                            <div class="small text-muted">Aprobado / Justificado</div>
                                        </div>
                                        <div class="text-center">
                                            <span class="badge bg-danger mb-1">Rojo</span>
                                            <div class="small text-muted">Rechazado / No Válido</div>
                                        </div>
                                    </div>
                                    <div class="alert alert-info py-2 px-3 mb-0 small">
                                        <strong><i class="fas fa-info-circle me-1"></i> Ícono Verde Inicial:</strong>
                                        <br>
                                        Las inasistencias programadas (ej: Vacaciones, Permisos) que requieren
                                        justificación
                                        se muestran con <strong>ícono verde</strong> desde el inicio para diferenciarlas
                                        de faltas normales (rojas). Esto indica que es una ausencia planificada pero que
                                        aún requiere documentación de respaldo.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Entendido</button>
                </div>
</body>

</html>