<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

//******************************Estándar para header******************************
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo Operaciones (Código 11 para Jefe de Operaciones)

if (!verificarAccesoCargo([21, 11, 5, 43, 27, 8, 13, 39, 30, 37, 28, 42, 54, 42]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Obtener sucursales - lógica mejorada
if ($esAdmin || verificarAccesoCargo([21, 11, 8, 13, 39, 30, 37, 28, 42, 54, 42])) {
    // Admin y supervisores ven todas las sucursales
    $sucursales = obtenerSucursalesFisicas();
} elseif (verificarAccesoCargo([5, 43, 27])) {
    // Cargos 5 y 27 solo ven sus sucursales asignadas (sin selector)
    $sucursales = obtenerSucursalesUsuario($usuario['CodOperario']);
    // Para estos cargos, forzar la primera sucursal asignada
    if (!empty($sucursales) && !isset($_GET['sucursal'])) {
        $sucursalSeleccionada = $sucursales[0]['codigo'];
        $mostrarTodas = false;
    }
} else {
    // Otros cargos ven sus sucursales asignadas pero con selector
    $sucursales = obtenerSucursalesUsuario($usuario['CodOperario']);
}

// Si no hay sucursales asignadas
if (empty($sucursales)) {
    $_SESSION['error'] = "No tienes sucursales asignadas.";
    header('Location: index.php');
    exit();
}

// Obtener semanas disponibles según cargo
$semanaActual = obtenerSemanaActual();
$semanasDisponibles = [];
$semanaSiguiente = null; // Mantener para compatibilidad con botones existentes si fuera necesario

if ($semanaActual) {
    // 1. Agregar semana actual
    $semanasDisponibles[$semanaActual['numero_semana']] = array_merge($semanaActual, ['tipo' => 'Actual']);

    // 2. Determinar rango según cargo
    $rangoAnterior = 1; // Por defecto 1 para colaboradores/líderes
    if (verificarAccesoCargo([21, 8, 13, 11, 39, 30, 37, 28, 42])) {
        $rangoAnterior = 10; // Supervisores ven hasta 10 atrás
    }

    // 3. Buscar semanas anteriores
    for ($i = 1; $i <= $rangoAnterior; $i++) {
        $numSemana = $semanaActual['numero_semana'] - $i;
        $stmt = $conn->prepare("SELECT * FROM SemanasSistema WHERE numero_semana = ? LIMIT 1");
        $stmt->execute([$numSemana]);
        $sem = $stmt->fetch();
        if ($sem) {
            $semanasDisponibles[$numSemana] = array_merge($sem, ['tipo' => 'Anterior']);
        } else {
            break;
        }
    }

    // 4. Buscar semana siguiente (si aplica según cargo)
    if (verificarAccesoCargo([21, 8, 27, 5, 43, 13, 11, 39, 30, 37, 28, 42])) {
        $numSiguiente = $semanaActual['numero_semana'] + 1;
        $stmt = $conn->prepare("SELECT * FROM SemanasSistema WHERE numero_semana = ? LIMIT 1");
        $stmt->execute([$numSiguiente]);
        $sem = $stmt->fetch();
        if ($sem) {
            $semanasDisponibles[$numSiguiente] = array_merge($sem, ['tipo' => 'Siguiente']);
            $semanaSiguiente = $sem; // Mantener para compatibilidad
        }
    }

    // 5. Ordenar por número de semana (cronológico)
    ksort($semanasDisponibles);
}

// Obtener datos para la vista
$semanaSeleccionada = $_GET['semana'] ?? $semanaActual['numero_semana'];
$sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursales[0]['codigo'] ?? null);
$mostrarTodas = ($sucursalSeleccionada === 'todas');

// Obtener operarios con horarios programados y horarios si hay sucursal y semana seleccionada
$operarios = [];
$semana = null;
$horariosOperaciones = [];
$horariosLideres = [];
$horariosPorSucursal = [];
$usarHorariosLideres = false;

if ($semanaSeleccionada) {
    $semana = obtenerSemanaPorNumero($semanaSeleccionada);
    if ($semana) {
        if ($mostrarTodas) {
            // Obtener horarios para todas las sucursales
            foreach ($sucursales as $sucursal) {
                $usarLiderLocal = false;
                // Primero intentamos con HorariosSemanalesOperaciones
                $ops = obtenerTodosOperariosEnSucursal($sucursal['codigo'], $semana['id'], false);

                // Si no hay registros, intentamos con HorariosSemanales
                if (empty($ops)) {
                    $ops = obtenerTodosOperariosEnSucursal($sucursal['codigo'], $semana['id'], true);
                    $usarLiderLocal = true;
                }

                // Obtener horarios filtrados para cada operario
                $horariosFiltrados = [];
                foreach ($ops as &$operario) {
                    $codOperario = $operario['CodOperario'];
                    $horario = obtenerHorarioFiltradoPorSucursal(
                        $codOperario,
                        $semana['id'],
                        $sucursal['codigo'],
                        $usarLiderLocal
                    );

                    if ($horario) {
                        $horariosFiltrados[$codOperario] = $horario;
                    }
                }
                unset($operario);

                // Obtener categorías para estos operarios
                if (!empty($ops)) {
                    $codigosOperarios = array_column($ops, 'CodOperario');
                    $categorias = obtenerCategoriasOperarios($codigosOperarios);

                    // Asignar categorías a los operarios
                    foreach ($ops as &$operario) {
                        $codOperario = $operario['CodOperario'];
                        if (isset($categorias[$codOperario])) {
                            $operario['categoria'] = $categorias[$codOperario];
                        } else {
                            $operario['categoria'] = [
                                'NombreCategoria' => 'Sin categoría',
                                'Peso' => '-',
                                'idCategoria' => 0
                            ];
                        }
                    }
                    unset($operario);

                    $horariosPorSucursal[$sucursal['codigo']] = [
                        'nombre' => $sucursal['nombre'],
                        'operarios' => $ops,
                        'horarios' => $horariosFiltrados,
                        'esDeLider' => $usarLiderLocal
                    ];
                }
            }
        } elseif ($sucursalSeleccionada) {
            // Modo sucursal individual
            // Primero intentamos con HorariosSemanalesOperaciones
            $operarios = obtenerTodosOperariosEnSucursal($sucursalSeleccionada, $semana['id'], false);

            // Si no hay registros, intentamos con HorariosSemanales
            if (empty($operarios)) {
                $operarios = obtenerTodosOperariosEnSucursal($sucursalSeleccionada, $semana['id'], true);
                $usarHorariosLideres = true;
            }

            // Obtener horarios filtrados para cada operario
            $horariosOperaciones = [];
            foreach ($operarios as &$operario) {
                $codOperario = $operario['CodOperario'];
                $horario = obtenerHorarioFiltradoPorSucursal(
                    $codOperario,
                    $semana['id'],
                    $sucursalSeleccionada,
                    $usarHorariosLideres
                );

                if ($horario) {
                    $horariosOperaciones[$codOperario] = $horario;
                }
            }
            unset($operario);

            // Obtener categorías para estos operarios
            if (!empty($operarios)) {
                $codigosOperarios = array_column($operarios, 'CodOperario');
                $categorias = obtenerCategoriasOperarios($codigosOperarios);

                // Asignar categorías a los operarios
                foreach ($operarios as &$operario) {
                    $codOperario = $operario['CodOperario'];
                    if (isset($categorias[$codOperario])) {
                        $operario['categoria'] = $categorias[$codOperario];
                    } else {
                        $operario['categoria'] = [
                            'NombreCategoria' => 'Sin categoría',
                            'Peso' => '-',
                            'idCategoria' => 0
                        ];
                    }
                }
                unset($operario);
            }
        }
    }
}

// Obtener estado del horario (solo aplica cuando se selecciona una sucursal específica)
$estadoHorario = 'sin-registros'; // Valor por defecto
$totalOperarios = 0;
$operariosConfirmados = 0;
$hayRegistrosOperaciones = !empty($horariosOperaciones);

if (!$mostrarTodas && $sucursalSeleccionada && $semanaSeleccionada && $semana) {
    if ($hayRegistrosOperaciones) {
        // Contar operarios con horarios confirmados
        $stmt = $conn->prepare("SELECT 
            COUNT(*) as total_operarios,
            SUM(CASE WHEN confirmado = 1 THEN 1 ELSE 0 END) as operarios_confirmados
            FROM HorariosSemanalesOperaciones 
            WHERE id_semana_sistema = ? AND cod_sucursal = ?");
        $stmt->execute([$semana['id'], $sucursalSeleccionada]);
        $result = $stmt->fetch();

        $totalOperarios = $result['total_operarios'] ?? 0;
        $operariosConfirmados = $result['operarios_confirmados'] ?? 0;

        if ($totalOperarios > 0) {
            if ($operariosConfirmados == $totalOperarios) {
                $estadoHorario = 'publicado';
            } elseif ($operariosConfirmados > 0) {
                $estadoHorario = 'parcial';
            } else {
                $estadoHorario = 'pendiente';
            }
        }
    }
}

// Mapear estados a clases y mensajes
$estados = [
    'publicado' => [
        'clase' => 'status-published',
        'mensaje' => 'Horario actual ya fue publicado'
    ],
    'parcial' => [
        'clase' => 'status-partial',
        'mensaje' => 'Horario parcialmente aprobado (' . $operariosConfirmados . '/' . $totalOperarios . ' operarios)'
    ],
    'pendiente' => [
        'clase' => 'status-pending',
        'mensaje' => 'Horario actual pendiente de aprobación'
    ],
    'sin-registros' => [
        'clase' => 'status-warning',
        'mensaje' => 'No hay registros de horarios para esta semana'
    ]
];

/**
 * Obtiene los operarios que tienen horario registrado en HorariosSemanalesOperaciones
 * para una sucursal y semana específica
 */
function obtenerOperariosConHorario($codSucursal, $idSemana)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2 
        FROM Operarios o
        JOIN HorariosSemanalesOperaciones hso ON o.CodOperario = hso.cod_operario
        WHERE hso.cod_sucursal = ?
        AND hso.id_semana_sistema = ?
        AND o.Operativo = 1
        ORDER BY o.Nombre, o.Apellido, o.Apellido2
    ");
    $stmt->execute([$codSucursal, $idSemana]);
    return $stmt->fetchAll();
}

/**
 * Obtiene los horarios de operaciones para una semana y sucursal específica,
 * organizados por código de operario
 */
function obtenerHorariosOperacionesPorSemanaYSucursal($idSemana, $codSucursal)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            cod_operario,
            lunes_estado, lunes_entrada, lunes_salida, lunes_horas, lunes_comentario, lunes_sucursal_externa,
            martes_estado, martes_entrada, martes_salida, martes_horas, martes_comentario, martes_sucursal_externa,
            miercoles_estado, miercoles_entrada, miercoles_salida, miercoles_horas, miercoles_comentario, miercoles_sucursal_externa,
            jueves_estado, jueves_entrada, jueves_salida, jueves_horas, jueves_comentario, jueves_sucursal_externa,
            viernes_estado, viernes_entrada, viernes_salida, viernes_horas, viernes_comentario, viernes_sucursal_externa,
            sabado_estado, sabado_entrada, sabado_salida, sabado_horas, sabado_comentario, sabado_sucursal_externa,
            domingo_estado, domingo_entrada, domingo_salida, domingo_horas, domingo_comentario, domingo_sucursal_externa,
            confirmado
        FROM HorariosSemanalesOperaciones
        WHERE id_semana_sistema = ?
        AND cod_sucursal = ?
    ");
    $stmt->execute([$idSemana, $codSucursal]);

    $horarios = [];
    while ($row = $stmt->fetch()) {
        $horarios[$row['cod_operario']] = $row;
    }

    return $horarios;
}

/**
 * Obtiene los operarios que tienen horario registrado en HorariosSemanales (de líderes)
 */
function obtenerOperariosConHorarioLider($codSucursal, $idSemana)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2 
        FROM Operarios o
        JOIN HorariosSemanales hs ON o.CodOperario = hs.cod_operario
        WHERE hs.cod_sucursal = ?
        AND hs.id_semana_sistema = ?
        AND o.Operativo = 1
        ORDER BY o.Nombre, o.Apellido, o.Apellido2
    ");
    $stmt->execute([$codSucursal, $idSemana]);
    return $stmt->fetchAll();
}

/**
 * Obtiene los horarios de líderes para una semana y sucursal específica
 */
function obtenerHorariosLiderPorSemanaYSucursal($idSemana, $codSucursal)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            cod_operario,
            lunes_estado, lunes_entrada, lunes_salida, lunes_horas, lunes_comentario, lunes_sucursal_externa,
            martes_estado, martes_entrada, martes_salida, martes_horas, martes_comentario, martes_sucursal_externa,
            miercoles_estado, miercoles_entrada, miercoles_salida, miercoles_horas, miercoles_comentario, miercoles_sucursal_externa,
            jueves_estado, jueves_entrada, jueves_salida, jueves_horas, jueves_comentario, jueves_sucursal_externa,
            viernes_estado, viernes_entrada, viernes_salida, viernes_horas, viernes_comentario, viernes_sucursal_externa,
            sabado_estado, sabado_entrada, sabado_salida, sabado_horas, sabado_comentario, sabado_sucursal_externa,
            domingo_estado, domingo_entrada, domingo_salida, domingo_horas, domingo_comentario, domingo_sucursal_externa,
            0 as confirmado
        FROM HorariosSemanales
        WHERE id_semana_sistema = ?
        AND cod_sucursal = ?
    ");
    $stmt->execute([$idSemana, $codSucursal]);

    $horarios = [];
    while ($row = $stmt->fetch()) {
        $horarios[$row['cod_operario']] = $row;
    }

    return $horarios;
}

/**
 * Obtiene el nombre de la sucursal externa si existe
 */
function obtenerNombreSucursalExterna($codSucursalExterna)
{
    global $conn;

    if (empty($codSucursalExterna)) {
        return null;
    }

    $stmt = $conn->prepare("SELECT nombre FROM sucursales WHERE codigo = ? LIMIT 1");
    $stmt->execute([$codSucursalExterna]);
    $result = $stmt->fetch();

    return $result['nombre'] ?? null;
}

// Obtener categorías de los operarios
function obtenerCategoriasOperarios($codigosOperarios)
{
    global $conn;

    if (empty($codigosOperarios)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($codigosOperarios), '?'));
    $stmt = $conn->prepare("
        SELECT anc.CodOperario, nc.Nombre as NombreCategoria, nc.Peso, nc.CodNivelesCargos as idCategoria, nc.color
        FROM AsignacionNivelesCargos anc
        JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
        WHERE anc.CodOperario IN ($placeholders)
        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        AND anc.Fecha <= CURDATE()
        ORDER BY anc.Fecha DESC
    ");

    $stmt->execute($codigosOperarios);
    $categorias = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    $resultados = [];
    foreach ($categorias as $codOperario => $categoriasOp) {
        $resultados[$codOperario] = $categoriasOp[0]; // Tomar la más reciente
    }

    return $resultados;
}

function obtenerColorCategoria($idCategoria)
{
    $colores = [
        5 => '#E8F5E9',  // Lider de Tienda - Verde muy claro
        43 => '#E3F2FD', // Líder Interino - Azul muy claro  
        46 => '#E3F2FD', // Vendedor Asistente de Líder
        47 => '#FFF3E0', // Vendedor Experto - Naranja muy claro
        45 => '#F1F8E9', // Vendedor Junior - Verde claro suave
        44 => '#F5F5F5', // Vendedor Training - Gris claro
        0 => '#FFFFFF'   // Sin categoría - Blanco
    ];

    return $colores[$idCategoria] ?? '#FFFFFF';
}

function obtenerClaseCategoria($nombreCategoria)
{
    $clases = [
        'Lider de Tienda' => 'tr-categoria-lider',
        'Vendedor Asistente de Líder' => 'tr-categoria-asistente',
        'Líder Interino' => 'tr-categoria-asistente',
        'Vendedor Experto' => 'tr-categoria-experto',
        'Vendedor Junior' => 'tr-categoria-junior',
        'Vendedor Training' => 'tr-categoria-training',
        'Sin categoría' => 'tr-categoria-sin-categoria'
    ];

    return $clases[$nombreCategoria] ?? 'tr-categoria-sin-categoria';
}

function obtenerNombreCategoria($idCategoria)
{
    global $conn;

    if ($idCategoria == 0) {
        return 'Sin categoría';
    }

    $stmt = $conn->prepare("SELECT Nombre, Peso FROM NivelesCargos WHERE CodNivelesCargos = ?");
    $stmt->execute([$idCategoria]);
    $categoria = $stmt->fetch();

    if ($categoria) {
        return $categoria['Nombre'] . ' (' . $categoria['Peso'] . ')';
    }

    return 'Sin categoría';
}

function obtenerCategoriasDesdeBD()
{
    global $conn;

    $stmt = $conn->prepare("SELECT CodNivelesCargos as idCategoria, Nombre as NombreCategoria, Peso FROM NivelesCargos WHERE CodNivelesCargos IN (5, 43, 46, 47, 45, 44) ORDER BY CodNivelesCargos");
    $stmt->execute();
    $categorias = $stmt->fetchAll();

    // Agregar la categoría "Sin categoría"
    $categorias[] = [
        'idCategoria' => 0,
        'NombreCategoria' => 'Sin categoría',
        'Peso' => '-'
    ];

    return $categorias;
}

/**
 * Obtiene colores mejorados para categorías con mejor contraste
 */
function obtenerColorCategoriaMejorado($idCategoria)
{
    $colores = [
        5 => '#FFE4E1',  // Lider de Tienda - Rosa muy claro (mejor contraste)
        43 => '#E6E6FA', // Líder Interino - Lavanda  
        46 => '#E6E6FA', // Vendedor Asistente de Líder
        47 => '#FFFACD', // Vendedor Experto - Amarillo claro
        45 => '#F0FFF0', // Vendedor Junior - Miel verde claro
        44 => '#F5F5DC', // Vendedor Training - Beige claro
        0 => '#FFFFFF'   // Sin categoría - Blanco
    ];

    return $colores[$idCategoria] ?? '#FFFFFF';
}

/**
 * Obtiene clase CSS mejorada para categorías
 */
function obtenerClaseCategoriaMejorada($nombreCategoria)
{
    $clases = [
        'Lider de Tienda' => 'tr-categoria-lider-mejorado',
        'Vendedor Asistente de Líder' => 'tr-categoria-asistente-mejorado',
        'Líder Interino' => 'tr-categoria-asistente-mejorado',
        'Vendedor Experto' => 'tr-categoria-experto-mejorado',
        'Vendedor Junior' => 'tr-categoria-junior-mejorado',
        'Vendedor Training' => 'tr-categoria-training-mejorado',
        'Sin categoría' => 'tr-categoria-sin-categoria'
    ];

    return $clases[$nombreCategoria] ?? 'tr-categoria-sin-categoria';
}

// Determinar si mostrar botones de semana (para cargos 5 y 27)
$mostrarBotonesSemana = false;
if (verificarAccesoCargo([5, 43, 27])) {
    $mostrarBotonesSemana = true;
    // Si también tiene otros permisos de supervisión, no mostrar botones
    if (verificarAccesoCargo([21, 8, 13, 11, 39, 30, 37, 28, 42])) {
        $mostrarBotonesSemana = false;
    }
}

/**
 * Obtiene TODOS los operarios que trabajan en una sucursal (principal + externos)
 * para una semana específica - VERSIÓN MEJORADA
 */
function obtenerTodosOperariosEnSucursal($codSucursal, $idSemana, $buscarEnLideres = false)
{
    global $conn;

    if ($buscarEnLideres) {
        $tabla = 'HorariosSemanales';
    } else {
        $tabla = 'HorariosSemanalesOperaciones';
    }

    $stmt = $conn->prepare("
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2
        FROM Operarios o
        JOIN $tabla h ON o.CodOperario = h.cod_operario
        WHERE o.Operativo = 1
        AND h.id_semana_sistema = ?
        AND (
            -- Sucursal principal del horario
            h.cod_sucursal = ?
            OR 
            -- O es Otra.Tienda para esta sucursal en algún día
            (h.lunes_estado = 'Otra.Tienda' AND h.lunes_sucursal_externa = ?)
            OR (h.martes_estado = 'Otra.Tienda' AND h.martes_sucursal_externa = ?)
            OR (h.miercoles_estado = 'Otra.Tienda' AND h.miercoles_sucursal_externa = ?)
            OR (h.jueves_estado = 'Otra.Tienda' AND h.jueves_sucursal_externa = ?)
            OR (h.viernes_estado = 'Otra.Tienda' AND h.viernes_sucursal_externa = ?)
            OR (h.sabado_estado = 'Otra.Tienda' AND h.sabado_sucursal_externa = ?)
            OR (h.domingo_estado = 'Otra.Tienda' AND h.domingo_sucursal_externa = ?)
        )
        ORDER BY o.Nombre, o.Apellido, o.Apellido2
    ");

    $stmt->execute([
        $idSemana,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal
    ]);

    $result = $stmt->fetchAll();

    // Deduplicar por CodOperario (puede aparecer dos veces si el mismo operario matchea
    // tanto cod_sucursal = ? como alguna condición de Otra.Tienda en la misma semana)
    $vistos = [];
    $deduplicados = [];
    foreach ($result as $row) {
        $cod = $row['CodOperario'];
        if (!isset($vistos[$cod])) {
            $vistos[$cod] = true;
            $deduplicados[] = $row;
        }
    }
    return $deduplicados;
}

/**
 * Obtiene el horario FILTRADO para una sucursal específica
 * VERSIÓN CORREGIDA: Maneja correctamente Otra.Tienda en ambas sucursales
 */
function obtenerHorarioFiltradoPorSucursal($codOperario, $idSemana, $codSucursal, $buscarEnLideres = false)
{
    global $conn;

    if ($buscarEnLideres) {
        $tabla = 'HorariosSemanales';
    } else {
        $tabla = 'HorariosSemanalesOperaciones';
    }

    $stmt = $conn->prepare("
        SELECT * FROM $tabla 
        WHERE cod_operario = ? 
        AND id_semana_sistema = ?
        AND (
            -- Sucursal principal
            cod_sucursal = ?
            OR 
            -- O es Otra.Tienda para esta sucursal
            (lunes_estado = 'Otra.Tienda' AND lunes_sucursal_externa = ?)
            OR (martes_estado = 'Otra.Tienda' AND martes_sucursal_externa = ?)
            OR (miercoles_estado = 'Otra.Tienda' AND miercoles_sucursal_externa = ?)
            OR (jueves_estado = 'Otra.Tienda' AND jueves_sucursal_externa = ?)
            OR (viernes_estado = 'Otra.Tienda' AND viernes_sucursal_externa = ?)
            OR (sabado_estado = 'Otra.Tienda' AND sabado_sucursal_externa = ?)
            OR (domingo_estado = 'Otra.Tienda' AND domingo_sucursal_externa = ?)
        )
        ORDER BY CASE WHEN cod_sucursal = ? THEN 0 ELSE 1 END
        LIMIT 1
    ");

    $stmt->execute([
        $codOperario,
        $idSemana,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal  // para el ORDER BY CASE WHEN cod_sucursal = ?
    ]);

    $horario = $stmt->fetch();

    if (!$horario) {
        return null;
    }

    // Filtrar solo los días que aplican para esta sucursal
    return filtrarHorarioPorSucursal($horario, $codSucursal);
}

/**
 * Filtra un horario completo para mostrar solo días que aplican a una sucursal
 * VERSIÓN CORREGIDA: Mantiene datos originales para Otra.Tienda en sucursal de origen
 */
function filtrarHorarioPorSucursal($horario, $codSucursal)
{
    $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $horarioFiltrado = $horario;
    $sucursalPrincipal = (string)($horario['cod_sucursal'] ?? '');
    $esSucursalPrincipal = ($sucursalPrincipal === (string)$codSucursal);

    foreach ($dias as $dia) {
        $estadoDia = $horario["{$dia}_estado"] ?? '';
        $sucursalExternaDia = (string)($horario["{$dia}_sucursal_externa"] ?? '');
        $codSucursalStr = (string)$codSucursal;

        if ($esSucursalPrincipal) {
            // Estamos viendo la sucursal PRINCIPAL del operario:
            // Mostrar todos sus días (incluyendo Otra.Tienda a otras sucursales)
            // No se filtra nada - todo aplica para la sucursal principal
        } else {
            // Estamos viendo una sucursal EXTERNA (el operario fue prestado aquí):
            // Solo mostrar los días donde está como Otra.Tienda para ESTA sucursal
            $aplica = ($estadoDia === 'Otra.Tienda' && $sucursalExternaDia === $codSucursalStr);
            if (!$aplica) {
                $horarioFiltrado["{$dia}_estado"] = '';
                $horarioFiltrado["{$dia}_entrada"] = null;
                $horarioFiltrado["{$dia}_salida"] = null;
                $horarioFiltrado["{$dia}_horas"] = 0;
                $horarioFiltrado["{$dia}_comentario"] = null;
                $horarioFiltrado["{$dia}_sucursal_externa"] = null;
            }
        }
    }

    return $horarioFiltrado;
}

/**
 * Versión CORREGIDA que determina si un día aplica para una sucursal
 * Ahora maneja correctamente Otra.Tienda mostrándolo en ambas sucursales
 */
function diaAplicaParaSucursalCompleto($horario, $dia, $codSucursal)
{
    if (!$horario || !$codSucursal) {
        return false;
    }

    $codSucursalBuscar = (string)$codSucursal;
    $sucursalPrincipal = (string)($horario['cod_sucursal'] ?? '');
    $estadoDia = $horario["{$dia}_estado"] ?? '';
    $sucursalExterna = (string)($horario["{$dia}_sucursal_externa"] ?? '');

    // Caso 1: Si está en la sucursal principal
    if ($sucursalPrincipal === $codSucursalBuscar) {
        // Si está en sucursal principal, mostrar SIEMPRE (incluyendo Otra.Tienda)
        return true;
    }

    // Caso 2: Si es Otra.Tienda para esta sucursal externa
    if ($estadoDia === 'Otra.Tienda' && $sucursalExterna === $codSucursalBuscar) {
        return true;
    }

    return false;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualización de Horarios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
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

        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px;
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

        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
        }

        .subtitle {
            color: #51B8AC;
            font-size: 1.2rem !important;
            margin-bottom: 20px;
        }

        .current-week {
            font-size: 0.9rem !important;
            color: #666;
            margin-bottom: 5px;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
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

        select,
        input,
        button {
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

        /* BOTONES PARA CARGOS 5 Y 27 */
        .week-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .week-btn {
            padding: 10px 20px;
            background-color: #51B8AC;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            min-width: 150px;
        }

        .week-btn:hover {
            background-color: #0E544C;
            transform: translateY(-2px);
        }

        .week-btn.active {
            background-color: #0E544C;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
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

        th,
        td {
            padding: 8px;
            text-align: center;
            border: 1px solid #ddd;
            vertical-align: middle;
        }

        th {
            background-color: #0E544C;
            color: white;
        }

        th.fixed-column {
            text-align: center;
            background-color: #0E544C;
            color: white;
            position: sticky;
            left: 0;
            z-index: 2;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .day-header {
            font-weight: bold;
        }

        .day-date {
            font-size: 0.8rem !important;
            color: #ffffff;
        }

        .fixed-column {
            position: sticky;
            left: 0;
            background-color: white;
            z-index: 1;
            text-align: left;
            min-width: 150px;
        }

        /* ESTADOS DE HORARIO (estos se mantienen igual) */
        .status-activo {
            background-color: #d4edda;
            color: #155724;
            border-radius: 4px;
            padding: 3px;
            margin: 2px 0;
        }

        .extended-hours {
            background-color: #fff3cd;
            color: #856404;
            border-radius: 4px;
            padding: 3px;
            margin: 2px 0;
        }

        .inactive-hours {
            background-color: #53a1fa;
            color: white;
            border-radius: 4px;
            padding: 3px;
            margin: 2px 0;
        }

        .total-hours {
            font-weight: bold;
        }

        .total-dia {
            font-weight: bold;
            background-color: #d1ecf1;
            color: #0c5460;
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

        .status-indicator {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            margin-left: 15px;
            font-weight: bold;
        }

        .status-published {
            background-color: #d4edda;
            color: #155724;
        }

        .status-partial {
            background-color: #ffeeba;
            color: #856404;
        }

        .status-pending {
            background-color: #f8d7da;
            color: #721c24;
        }

        .confirmed-icon {
            color: #28a745;
            margin-left: 5px;
        }

        .status-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .compact-time {
            white-space: nowrap;
        }

        .horas-dia {
            font-size: 0.8em;
            margin-top: 3px;
            color: inherit;
            opacity: 0.9;
        }

        .sucursal-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: bold;
        }

        /* NUEVOS ESTILOS MEJORADOS PARA CATEGORÍAS */
        .tr-categoria-lider-mejorado {
            background-color: #FFE4E1 !important;
            /* Rosa muy claro */
        }

        .tr-categoria-asistente-mejorado {
            background-color: #E6E6FA !important;
            /* Lavanda */
        }

        .tr-categoria-experto-mejorado {
            background-color: #FFFACD !important;
            /* Amarillo claro */
        }

        .tr-categoria-junior-mejorado {
            background-color: #F0FFF0 !important;
            /* Miel verde claro */
        }

        .tr-categoria-training-mejorado {
            background-color: #F5F5DC !important;
            /* Beige claro */
        }

        .tr-categoria-sin-categoria {
            background-color: #FFFFFF !important;
        }

        /* Leyenda de categorías mejorada */
        .categoria-leyenda {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }

        .leyenda-item {
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 0.85rem !important;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .leyenda-item::before {
            content: "";
            width: 15px;
            height: 15px;
            border-radius: 3px;
            background-color: inherit;
            border: 1px solid rgba(0, 0, 0, 0.2);
        }

        .operario-cell {
            font-weight: bold;
            font-size: 14px !important;
            text-align: center !important;
            position: relative;
            text-align: left;
            padding-bottom: 25px !important;
        }

        .categoria-indicator {
            position: absolute;
            bottom: 3px;
            left: 3px;
            font-size: 0.6rem !important;
            padding: 3px 8px;
            border-radius: 4px;
            opacity: 0.95;
            width: calc(100% - 6px);
            text-align: center;
            font-weight: bold;
            /*border: 1px solid rgba(0,0,0,0.1); */
            border: none;
        }

        /* Tooltip styles */
        .tooltip-container {
            position: relative;
            display: inline-block;
            width: 100%;
            height: 100%;
        }

        .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            z-index: 100;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px !important;
            font-weight: normal !important;
        }

        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        .tooltip-container:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        td:not(.fixed-column) {
            position: relative;
            padding: 10px !important;
        }

        .day-cell-content {
            padding: 12px;
            height: 100%;
            width: 100%;
        }

        /* RESPONSIVE MEJORADO */
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                width: 100%;
                position: relative;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: white;
            }

            table {
                width: auto;
                min-width: 100%;
                table-layout: auto;
                border-collapse: collapse;
            }

            thead th:not(.fixed-column) {
                text-align: center;
                height: 120px;
                width: 110px;
                min-width: 109px;
                max-width: 40px;
                padding: 0;
                position: relative;
                vertical-align: bottom;
            }

            thead th:not(.fixed-column)>div {
                transform-origin: left top;
                position: absolute;
                left: 20px;
                bottom: 15px;
                width: 100px;
                text-align: left;
                white-space: nowrap;
            }

            thead th:last-child {
                height: 120px;
                width: 40px;
            }

            thead th:last-child>div {
                transform: rotate(270deg);
                transform-origin: left top;
                position: absolute;
                left: 10px;
                bottom: 15px;
                width: 70px;
                text-align: left;
            }

            th.fixed-column,
            td.fixed-column {
                position: sticky;
                left: 0;
                z-index: 2;
                width: 120px;
                min-width: 120px;
                background-color: #0E544C;
                color: white;
            }

            td.fixed-column {
                background-color: white;
                color: #333;
                border-right: 2px solid #ddd;
            }

            td:not(.fixed-column) {
                width: 40px;
                min-width: 40px;
                max-width: 40px;
                padding: 3px;
                text-align: center;
                border: 1px solid #e0e0e0;
            }

            .compact-time,
            .inactive-hours {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2px;
                min-height: 40px;
                font-size: 0.8em;
                line-height: 1.2;
            }

            .horas-dia {
                font-size: 0.7em;
                margin-top: 2px;
            }

            td>div {
                width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .tooltip-text {
                width: 120px;
                font-size: 10px !important;
            }

            .day-cell-content {
                padding: 10px;
                min-height: 40px;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .week-buttons {
                flex-direction: column;
                gap: 5px;
            }

            .week-btn {
                min-width: 100%;
                padding: 12px;
            }
        }

        @media (min-width: 769px) {
            .fixed-column {
                position: sticky;
                left: 0;
                z-index: 1;
                min-width: 150px;
            }

            th.fixed-column {
                background-color: #0E544C;
                color: white;
                z-index: 2;
            }

            td {
                padding: 8px;
            }
        }

        /* Estilos para DataTables */
        th.sorting_asc,
        th.sorting_desc,
        th.sorting {
            background-color: #51B8AC !important;
            position: relative;
            cursor: pointer;
        }

        th.sorting_asc:after,
        th.sorting_desc:after,
        th.sorting:after {
            content: '';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
        }

        th.sorting_asc {
            background-color: #0E544C !important;
        }

        th.sorting_desc {
            background-color: #0E544C !important;
        }

        th.sorting:hover {
            background-color: #0E544C !important;
            transition: background-color 0.3s;
        }

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

        /* Efecto hover para filas */
        tr:hover {
            background-color: rgba(81, 184, 172, 0.1) !important;
            transition: background-color 0.3s ease;
        }

        .tr-categoria-especialista {
            background-color: #FFE4E1 !important;
            /* Rosa muy claro */
        }

        /* Efecto al pasar el mouse sobre las filas */
        tr.tr-categoria-especialista:hover {
            background-color: #E6E6FA !important;
            /* O el color que prefieras */
        }

        /* Estilo para Otra.Tienda */
        .otra-tienda-cell {
            background-color: #e8f4f8 !important;
            border: 1px solid #51B8AC;
        }

        .otra-tienda-icon {
            color: #0E544C;
            margin-right: 3px;
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
                    <!-- Aquí revisar bien que sea el mismo nombre de archivo tanto en el herf como en el PHP_SELF -->
                    <a href="../supervision/ver_horarios_compactos.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'ver_horarios_compactos.php' ? 'activo' : '' ?>">
                        <i class="fas fa-clock"></i> <span class="btn-text">Horarios Programados</span>
                    </a>

                    <?php if ($esAdmin || verificarAccesoCargo([13, 5, 43, 8, 11, 21, 22, 39, 30, 37, 28])): ?>
                        <a href="../rh/ver_marcaciones_todas.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'ver_marcaciones_todas.php' ? 'activo' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Marcaciones</span>
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
                                htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?>
                        </div>
                        <small>
                            <?= htmlspecialchars($cargoUsuario) ?>
                        </small>
                    </div>
                    <a href="index.php" class="btn-logout">
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

        <?php if (($mostrarTodas && !empty($horariosPorSucursal)) || (!$mostrarTodas && !empty($operarios))): ?>
            <div class="categoria-leyenda">
                <strong>Leyenda de categorías:</strong>
                <?php
                $categoriasMostradas = [];

                if ($mostrarTodas) {
                    foreach ($horariosPorSucursal as $data) {
                        foreach ($data['operarios'] as $operario) {
                            $catId = $operario['categoria']['idCategoria'];
                            if (!in_array($catId, $categoriasMostradas)) {
                                $categoriasMostradas[] = $catId;
                            }
                        }
                    }
                } else {
                    foreach ($operarios as $operario) {
                        $catId = $operario['categoria']['idCategoria'];
                        if (!in_array($catId, $categoriasMostradas)) {
                            $categoriasMostradas[] = $catId;
                        }
                    }
                }

                // Obtener todas las categorías desde la BD
                $todasCategorias = obtenerCategoriasDesdeBD();

                // Mostrar solo las categorías que están presentes
                foreach ($todasCategorias as $categoria):
                    if (in_array($categoria['idCategoria'], $categoriasMostradas)):
                        $colorFondo = obtenerColorCategoria($categoria['idCategoria']);
                ?>
                        <span class="leyenda-item" style="background-color: <?= obtenerColorCategoriaMejorado($categoria['idCategoria']) ?>;">
                            <?= htmlspecialchars($categoria['NombreCategoria']) ?> (<?= $categoria['Peso'] ?>)
                        </span>
                <?php
                    endif;
                endforeach;
                ?>
            </div>
        <?php endif; ?>

        <div class="filters">
            <div class="filter-group">
                <label style="display:none;" for="semana">Semana</label>

                <?php if ($mostrarBotonesSemana): ?>
                    <!-- BOTONES PARA CARGOS 5 Y 27 -->
                    <div class="week-buttons">
                        <?php foreach ($semanasDisponibles as $num => $sem): ?>
                            <button class="week-btn <?= $semanaSeleccionada == $num ? 'active' : '' ?>"
                                onclick="cambiarSemanaConBoton(<?= $num ?>, '<?= $sucursalSeleccionada ?>')">
                                <?php 
                                    $etiqueta = "Semana $num";
                                    if ($sem['tipo'] == 'Actual') $etiqueta = "Semana Actual";
                                    if ($sem['tipo'] == 'Siguiente') $etiqueta = "Semana Siguiente";
                                    echo $etiqueta;
                                ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- SELECTOR NORMAL PARA OTROS CARGOS -->
                    <select id="semana" name="semana" onchange="cambiarSemana()">
                        <?php foreach ($semanasDisponibles as $num => $sem): ?>
                            <option value="<?= $num ?>" <?= $semanaSeleccionada == $num ? 'selected' : '' ?>>
                                Semana <?= $num ?> 
                                (<?= ($sem['tipo'] == 'Actual' ? 'Actual, ' : ($sem['tipo'] == 'Siguiente' ? 'Siguiente, ' : '')) . formatoFecha($sem['fecha_inicio']) ?> al <?= formatoFecha($sem['fecha_fin']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

            </div>

            <?php if (!verificarAccesoCargo([5, 43, 27])): ?>
                <div class="filter-group">
                    <label style="display:none;" for="sucursal">Sucursal</label>
                    <select id="sucursal" name="sucursal" onchange="cambiarSucursal()">
                        <?php if ($esAdmin || verificarAccesoCargo([21, 11, 8, 13, 39, 30, 37, 28, 42])): ?>
                            <option value="todas" <?= $mostrarTodas ? 'selected' : '' ?>>Todas las sucursales</option>
                        <?php endif; ?>
                        <?php foreach ($sucursales as $sucursal): ?>
                            <option value="<?= $sucursal['codigo'] ?>" <?= $sucursalSeleccionada == $sucursal['codigo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sucursal['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="filter-group">
                <?php if (!$mostrarTodas): ?>
                    <span class="status-indicator <?= $estados[$estadoHorario]['clase'] ?>">
                        <?= $estados[$estadoHorario]['mensaje'] ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($esAdmin || verificarAccesoCargo([8, 16, 41])): ?>
                <div class="filter-group" style="flex-direction: row; align-items: flex-end;">
                    <a href="exportar_horarios_compactos.php?semana=<?= $semanaSeleccionada ?>&sucursal=<?= $sucursalSeleccionada ?>"
                        class="btn btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-download"></i> Descargar Horarios (Excel)
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($semanaSeleccionada && $semana): ?>
            <?php if ($mostrarTodas): ?>
                <?php if (empty($horariosPorSucursal)): ?>
                    <div class="alert alert-info">
                        No hay registros de horarios para ninguna sucursal en la semana seleccionada.
                    </div>
                <?php else: ?>
                    <div style="font-weight:bold; display:none;" class="subtitle">
                        Visualizando horarios para la semana <?= $semanaSeleccionada ?>
                        (<?= formatoFecha($semana['fecha_inicio']) ?> al <?= formatoFecha($semana['fecha_fin']) ?>)
                        | Todas las sucursales
                        <?php if ($usarHorariosLideres): ?>
                            <span class="status-indicator status-info">Mostrando horarios programados por líderes</span>
                        <?php endif; ?>
                    </div>

                    <?php foreach ($horariosPorSucursal as $codSucursal => $data): ?>
                        <div class="sucursal-section">
                            <h3 class="sucursal-title"><?= htmlspecialchars($data['nombre']) ?></h3>

                            <?php if ($data['esDeLider']): ?>
                                <div class="alert alert-info" style="margin-bottom: 15px;">
                                    <i class="fas fa-info-circle"></i> Horarios programados por líderes (no confirmados aún por operaciones)
                                </div>
                            <?php endif; ?>

                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th class="fixed-column">Colaborador</th>
                                            <?php
                                            $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                                            $fechasSemana = [];
                                            $fechaActual = new DateTime($semana['fecha_inicio']);

                                            foreach ($diasSemana as $dia) {
                                                echo '<th><div>' . $dia . '<br><span class="day-date">' . formatoFecha($fechaActual->format('Y-m-d')) . '</span></div></th>';
                                                $fechasSemana[] = $fechaActual->format('Y-m-d');
                                                $fechaActual->modify('+1 day');
                                            }
                                            ?>
                                            <th>Total Horas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['operarios'] as $operario):
                                            $horario = $data['horarios'][$operario['CodOperario']] ?? null;

                                            if (!$horario) continue;

                                            // Calcular total de horas solo para días que NO están vacíos
                                            $totalHoras = 0;
                                            $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
                                            foreach ($dias as $dia) {
                                                $estado = $horario["{$dia}_estado"] ?? '';
                                                if (!empty($estado)) {  // Solo sumar si el día no está vacío
                                                    $totalHoras += $horario["{$dia}_horas"] ?? 0;
                                                }
                                            }
                                        ?>
                                            <tr class="<?= obtenerClaseCategoriaMejorada($operario['categoria']['NombreCategoria']) ?>"
                                                data-operario="<?= $operario['CodOperario'] ?>"
                                                style="background-color: <?= obtenerColorCategoriaMejorado($operario['categoria']['idCategoria']) ?>;">

                                                <td class="fixed-column operario-cell" style="font-weight:bold; background-color: <?= obtenerColorCategoriaMejorado($operario['categoria']['idCategoria']) ?>; color: #333;">
                                                    <?= htmlspecialchars($operario['Nombre'] . ' ' . $operario['Apellido'] . ' ' . $operario['Apellido2']) ?>
                                                    <div class="categoria-indicator" style="background-color: <?= obtenerColorCategoriaMejorado($operario['categoria']['idCategoria']) ?>; color: #333; display:none;">
                                                        <?= htmlspecialchars($operario['categoria']['NombreCategoria']) ?>
                                                    </div>
                                                </td>
                                                <?php
                                                foreach ($dias as $dia):
                                                    // Obtener datos del día
                                                    $estado = $horario["{$dia}_estado"] ?? '';
                                                    $entrada = $horario["{$dia}_entrada"] ?? null;
                                                    $salida = $horario["{$dia}_salida"] ?? null;
                                                    $horasDia = $horario["{$dia}_horas"] ?? 0;
                                                    $comentario = $horario["{$dia}_comentario"] ?? null;
                                                    $sucursalExterna = $horario["{$dia}_sucursal_externa"] ?? null;
                                                    $esNocturno = $salida && substr($salida, 0, 2) >= 20;

                                                    // Determinar si es día vacío (no aplica para esta sucursal)
                                                    $esDiaVacio = empty($estado);

                                                    // Si es día vacío, mostrar "-"
                                                    if ($esDiaVacio): ?>
                                                        <td style="font-weight:bold; font-size:10px !important;" data-label="<?= $dia ?>">
                                                            <div class="tooltip-container">
                                                                <div style="font-weight:bold; font-size:11px !important;" class="day-cell-content empty-cell">
                                                                    <span style="color: #ccc;">-</span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    <?php else:
                                                        // Obtener nombre de sucursal externa si es Otra.Tienda
                                                        $nombreSucursalExterna = null;
                                                        $esOtraTienda = ($estado === 'Otra.Tienda');
                                                        if ($esOtraTienda && !empty($sucursalExterna)) {
                                                            $nombreSucursalExterna = obtenerNombreSucursalExterna($sucursalExterna);
                                                        }
                                                    ?>
                                                        <td style="font-weight:bold; font-size:10px !important;" data-label="<?= $dia ?>">
                                                            <div class="tooltip-container">
                                                                <div style="font-weight:bold; font-size:11px !important;"
                                                                    class="day-cell-content 
                            <?= $estado == 'Activo' && $entrada && $salida ? ($esNocturno ? 'extended-hours' : 'status-activo') : ($esOtraTienda ? 'otra-tienda-cell' : 'inactive-hours') ?>">

                                                                    <?php if ($entrada && $salida): ?>
                                                                        <?= formatoHoraAmPm($entrada) ?> - <?= formatoHoraAmPm($salida) ?>
                                                                        <?php if ($esOtraTienda && $nombreSucursalExterna): ?>
                                                                            <div style="font-size: 8px !important; color: #0E544C; margin-top: 1px; font-weight:bold;">
                                                                                <?= htmlspecialchars($nombreSucursalExterna) ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <div style="font-weight:bold; font-size:10px !important;" class="horas-dia">
                                                                            <?= number_format($horasDia, 1) ?> hrs
                                                                        </div>

                                                                    <?php elseif ($esOtraTienda && $nombreSucursalExterna): ?>
                                                                        <div style="font-size: 9px !important; color: #0E544C; font-weight: bold;">
                                                                            <?= htmlspecialchars($nombreSucursalExterna) ?>
                                                                        </div>
                                                                        <?php if (floatval($horasDia) > 0): ?>
                                                                            <div style="font-weight:bold; font-size:10px !important;" class="horas-dia">
                                                                                <?= number_format($horasDia, 1) ?> hrs
                                                                            </div>
                                                                        <?php endif; ?>

                                                                    <?php else: ?>
                                                                        <?= htmlspecialchars($estado) ?>
                                                                        <?php if (floatval($horasDia) > 0): ?>
                                                                            <div style="font-weight:bold; font-size:10px !important;" class="horas-dia">
                                                                                <?= number_format($horasDia, 1) ?> hrs
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <?php if (!empty($comentario)): ?>
                                                                    <span class="tooltip-text"><?= htmlspecialchars($comentario) ?></span>
                                                                <?php endif; ?>

                                                                <?php if ($esOtraTienda && $nombreSucursalExterna && !empty($comentario)): ?>
                                                                    <span class="tooltip-text">
                                                                        <strong>Otra Tienda:</strong> <?= htmlspecialchars($nombreSucursalExterna) ?><br>
                                                                        <?= htmlspecialchars($comentario) ?>
                                                                    </span>
                                                                <?php elseif ($esOtraTienda && $nombreSucursalExterna): ?>
                                                                    <span class="tooltip-text">
                                                                        <strong>Otra Tienda:</strong> <?= htmlspecialchars($nombreSucursalExterna) ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>

                                                <td class="total-hours">
                                                    <?= number_format($totalHoras, 1) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php elseif ($sucursalSeleccionada): ?>
                <?php if (empty($operarios) && empty($horariosLideres)): ?>
                    <div class="alert alert-info">
                        No hay registros de horarios para la sucursal "<?= htmlspecialchars(array_column($sucursales, 'nombre', 'codigo')[$sucursalSeleccionada]) ?>" en la semana seleccionada.
                    </div>
                <?php else: ?>
                    <div style="font-weight:bold; display:none;" class="subtitle">
                        Visualizando horarios para la semana <?= $semanaSeleccionada ?>
                        (<?= formatoFecha($semana['fecha_inicio']) ?> al <?= formatoFecha($semana['fecha_fin']) ?>)
                        | Sucursal: <?= htmlspecialchars(array_column($sucursales, 'nombre', 'codigo')[$sucursalSeleccionada]) ?>
                        <?php if ($usarHorariosLideres): ?>
                            <span class="status-indicator status-info">Mostrando horarios programados por líderes</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($usarHorariosLideres): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Horarios programados por líderes (no confirmados aún por operaciones)
                        </div>
                    <?php endif; ?>

                    <div class="table-container">
                        <table id="horariosProgramados">
                            <thead>
                                <tr>
                                    <th class="fixed-column">Colaborador</th>
                                    <?php
                                    $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                                    $fechasSemana = [];
                                    $fechaActual = new DateTime($semana['fecha_inicio']);

                                    foreach ($diasSemana as $dia) {
                                        echo '<th><div>' . $dia . '<br><span class="day-date">' . formatoFecha($fechaActual->format('Y-m-d')) . '</span></div></th>';
                                        $fechasSemana[] = $fechaActual->format('Y-m-d');
                                        $fechaActual->modify('+1 day');
                                    }
                                    ?>
                                    <th>Total Horas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Usamos $operarios que ahora puede venir de HorariosSemanalesOperaciones o HorariosSemanales
                                $horariosMostrar = $horariosOperaciones;

                                foreach ($operarios as $operario):
                                    $horario = $horariosMostrar[$operario['CodOperario']] ?? null;

                                    if (!$horario) continue;

                                    // Calcular total de horas solo para días que NO están vacíos
                                    $totalHoras = 0;
                                    $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
                                    foreach ($dias as $dia) {
                                        $estado = $horario["{$dia}_estado"] ?? '';
                                        if (!empty($estado)) {  // Solo sumar si el día no está vacío
                                            $totalHoras += $horario["{$dia}_horas"] ?? 0;
                                        }
                                    }
                                ?>
                                    <tr class="<?= obtenerClaseCategoriaMejorada($operario['categoria']['NombreCategoria']) ?>"
                                        data-operario="<?= $operario['CodOperario'] ?>"
                                        style="background-color: <?= obtenerColorCategoriaMejorado($operario['categoria']['idCategoria']) ?>;">

                                        <td class="fixed-column operario-cell" style="font-weight:bold; background-color: <?= obtenerColorCategoriaMejorado($operario['categoria']['idCategoria']) ?>; color: #333;">
                                            <?= htmlspecialchars($operario['Nombre'] . ' ' . $operario['Apellido'] . ' ' . $operario['Apellido2']) ?>
                                            <div class="categoria-indicator" style="background-color: <?= obtenerColorCategoriaMejorado($operario['categoria']['idCategoria']) ?>; color: #333; display:none;">
                                                <?= htmlspecialchars($operario['categoria']['NombreCategoria']) ?>
                                            </div>
                                        </td>
                                        <?php
                                        foreach ($dias as $dia):
                                            // Obtener datos del día
                                            $estado = $horario["{$dia}_estado"] ?? '';
                                            $entrada = $horario["{$dia}_entrada"] ?? null;
                                            $salida = $horario["{$dia}_salida"] ?? null;
                                            $horasDia = $horario["{$dia}_horas"] ?? 0;
                                            $comentario = $horario["{$dia}_comentario"] ?? null;
                                            $sucursalExterna = $horario["{$dia}_sucursal_externa"] ?? null;
                                            $esNocturno = $salida && substr($salida, 0, 2) >= 20;

                                            // Determinar si es día vacío (no aplica para esta sucursal)
                                            $esDiaVacio = empty($estado);

                                            // Si es día vacío, mostrar "-"
                                            if ($esDiaVacio): ?>
                                                <td style="font-weight:bold; font-size:10px !important;" data-label="<?= $dia ?>">
                                                    <div class="tooltip-container">
                                                        <div style="font-weight:bold; font-size:11px !important;" class="day-cell-content empty-cell">
                                                            <span style="color: #ccc;">-</span>
                                                        </div>
                                                    </div>
                                                </td>
                                            <?php else:
                                                // Obtener nombre de sucursal externa si es Otra.Tienda
                                                $nombreSucursalExterna = null;
                                                $esOtraTienda = ($estado === 'Otra.Tienda');
                                                if ($esOtraTienda && !empty($sucursalExterna)) {
                                                    $nombreSucursalExterna = obtenerNombreSucursalExterna($sucursalExterna);
                                                }
                                            ?>
                                                <td style="font-weight:bold; font-size:10px !important;" data-label="<?= $dia ?>">
                                                    <div class="tooltip-container">
                                                        <div style="font-weight:bold; font-size:11px !important;"
                                                            class="day-cell-content 
                            <?= $estado == 'Activo' && $entrada && $salida ? ($esNocturno ? 'extended-hours' : 'status-activo') : ($esOtraTienda ? 'otra-tienda-cell' : 'inactive-hours') ?>">

                                                            <?php if ($entrada && $salida): ?>
                                                                <?= formatoHoraAmPm($entrada) ?> - <?= formatoHoraAmPm($salida) ?>
                                                                <?php if ($esOtraTienda && $nombreSucursalExterna): ?>
                                                                    <div style="font-size: 8px !important; color: #0E544C; margin-top: 1px; font-weight:bold;">
                                                                        <?= htmlspecialchars($nombreSucursalExterna) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div style="font-weight:bold; font-size:10px !important;" class="horas-dia">
                                                                    <?= number_format($horasDia, 1) ?> hrs
                                                                </div>

                                                            <?php elseif ($esOtraTienda && $nombreSucursalExterna): ?>
                                                                <div style="font-size: 9px !important; color: #0E544C; font-weight: bold;">
                                                                    <?= htmlspecialchars($nombreSucursalExterna) ?>
                                                                </div>
                                                                <?php if (floatval($horasDia) > 0): ?>
                                                                    <div style="font-weight:bold; font-size:10px !important;" class="horas-dia">
                                                                        <?= number_format($horasDia, 1) ?> hrs
                                                                    </div>
                                                                <?php endif; ?>

                                                            <?php else: ?>
                                                                <?= htmlspecialchars($estado) ?>
                                                                <?php if (floatval($horasDia) > 0): ?>
                                                                    <div style="font-weight:bold; font-size:10px !important;" class="horas-dia">
                                                                        <?= number_format($horasDia, 1) ?> hrs
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>

                                                        <?php if (!empty($comentario)): ?>
                                                            <span class="tooltip-text"><?= htmlspecialchars($comentario) ?></span>
                                                        <?php endif; ?>

                                                        <?php if ($esOtraTienda && $nombreSucursalExterna && !empty($comentario)): ?>
                                                            <span class="tooltip-text">
                                                                <strong>Otra Tienda:</strong> <?= htmlspecialchars($nombreSucursalExterna) ?><br>
                                                                <?= htmlspecialchars($comentario) ?>
                                                            </span>
                                                        <?php elseif ($esOtraTienda && $nombreSucursalExterna): ?>
                                                            <span class="tooltip-text">
                                                                <strong>Otra Tienda:</strong> <?= htmlspecialchars($nombreSucursalExterna) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>

                                        <td class="total-hours">
                                            <?= number_format($totalHoras, 1) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php elseif ($sucursalSeleccionada && !$semanaSeleccionada): ?>
            <div style="text-align: center; padding: 20px; color: #666;">
                Ingrese un número de semana para ver los horarios
            </div>
        <?php elseif ($semanaSeleccionada && !$semana): ?>
            <div style="text-align: center; padding: 20px; color: #dc3545;">
                La semana ingresada no existe en el sistema
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Función para cambiar semana con selector (para otros cargos)
        function cambiarSemana() {
            var semana = document.getElementById('semana').value;
            var sucursal = document.getElementById('sucursal') ? document.getElementById('sucursal').value : '<?= $sucursalSeleccionada ?>';

            if (semana) {
                window.location.href = 'ver_horarios_compactos.php?semana=' + semana + '&sucursal=' + sucursal;
            } else {
                alert('Por favor ingrese un número de semana');
            }
        }

        // Función para cambiar sucursal (solo para cargos que tienen selector)
        function cambiarSucursal() {
            var semana = document.getElementById('semana').value;
            var sucursal = document.getElementById('sucursal').value;

            if (semana && sucursal) {
                window.location.href = 'ver_horarios_compactos.php?semana=' + semana + '&sucursal=' + sucursal;
            }
        }

        $(document).ready(function() {
            $('#horariosProgramados').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                dom: 't', // Solo muestra la tabla, sin paginación más que todo
                order: [],
                ordering: true
            });
        });

        // Función para cambiar semana con botones (para cargos 5 y 27)
        function cambiarSemanaConBoton(numeroSemana, sucursal) {
            window.location.href = 'ver_horarios_compactos.php?semana=' + numeroSemana + '&sucursal=' + sucursal;
        }
    </script>
</body>

</html>