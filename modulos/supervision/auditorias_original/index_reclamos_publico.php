<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/layout/header_universal.php';
require_once '../../../core/layout/menu_lateral.php';
require_once '../../../core/permissions/permissions.php';

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo
if (!tienePermiso('historial_reclamos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

$sucursales_fisicas = obtenerTodasSucursales();

// Identificar la página actual para resaltar el botón correspondiente
$pagina_actual = basename($_SERVER['PHP_SELF']);
$es_pagina_avisos = ($pagina_actual == 'index_avisos_publico.php');
$es_pagina_auditorias = ($pagina_actual == 'index_auditorias_publico.php');
$es_pagina_promedios = ($pagina_actual == 'promedio.php');
$es_pagina_reclamos = ($pagina_actual == 'index_reclamos_publico.php');

// Configuración de zona horaria
date_default_timezone_set('America/Managua');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

// Obtener las selecciones del usuario
$sucursal_seleccionada = isset($_GET['sucursal']) ? $_GET['sucursal'] : 'todas';
$sucursal_codigo_seleccionada = isset($_GET['sucursal_codigo']) ? $_GET['sucursal_codigo'] : 'todas';
$mes_seleccionado = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$anio_seleccionado = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');

// Validar selecciones
$sucursales_permitidas = ['todas', 'Altamira', 'Villa Fontana', 'Las Colinas', 'Natura', 'Estelí', 'Granada', 'León', 'Masaya', 'Matagalpa'];
if (!in_array($sucursal_seleccionada, $sucursales_permitidas)) {
    $sucursal_seleccionada = 'todas';
}

if ($mes_seleccionado < 1 || $mes_seleccionado > 12) {
    $mes_seleccionado = date('n');
}
if ($anio_seleccionado < 2020 || $anio_seleccionado > 2030) {
    $anio_seleccionado = date('Y');
}

try {
    // Consulta para obtener reclamos con información de investigación
    $sql = "
        SELECT 
            r.id,
            r.fecha_hora,
            r.sucursal,
            r.sucursal_codigo,
            r.tipo_reclamo,
            r.descripcion,
            IFNULL(ri.resolucion, 'Abierto') as resolucion,
            COUNT(rc.id) as colaboradores_involucrados
        FROM reclamos r
        LEFT JOIN reportes_investigacion ri ON r.id = ri.reclamo_id
        LEFT JOIN reportes_colaboradores rc ON ri.id = rc.reporte_id
        WHERE MONTH(r.fecha_hora) = :mes 
        AND YEAR(r.fecha_hora) = :anio
    ";

    if ($sucursal_codigo_seleccionada != 'todas') {
        $sql .= " AND r.sucursal_codigo = :sucursal_codigo";
    }

    $sql .= " GROUP BY r.id ORDER BY r.fecha_hora DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':mes', $mes_seleccionado, PDO::PARAM_INT);
    $stmt->bindValue(':anio', $anio_seleccionado, PDO::PARAM_INT);

    if ($sucursal_codigo_seleccionada != 'todas') {
        $stmt->bindValue(':sucursal_codigo', $sucursal_codigo_seleccionada, PDO::PARAM_STR);
    }

    $stmt->execute();
    $reclamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

// Generar opciones de meses y años
$meses = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];

$anios = range(2020, date('Y') + 1);

// Construir URL base para los filtros manteniendo los parámetros existentes
$url_base = 'index_reclamos_publico.php?';
$params = [];

// Siempre incluir mes y año en los parámetros
$params[] = 'mes=' . urlencode($mes_seleccionado);
$params[] = 'anio=' . urlencode($anio_seleccionado);

// Si hay filtro de sucursal, agregarlo
if (isset($_GET['sucursal']) && $_GET['sucursal'] != 'todas') {
    $params[] = 'sucursal=' . urlencode($_GET['sucursal']);
}

$url_filtros = $url_base . implode('&', $params);

// Función para formatear fecha y hora
function formatearFechaHora($fecha_hora)
{
    $fecha = new DateTime($fecha_hora);
    // Restar 6 horas
    $fecha->modify('-6 hours');

    // Obtener el número del mes (1-12)
    $mes_numero = (int) $fecha->format('n');

    // Array de meses abreviados en español
    $meses_abreviados = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic'
    ];

    // Formatear fecha (ejemplo: 30-Abr-25)
    $fecha_formateada = $fecha->format('d') . '-' . $meses_abreviados[$mes_numero] . '-' . $fecha->format('y');

    return $fecha_formateada;
}

// Función para determinar el badge de resolución
function getBadgeResolucion($resolucion)
{
    $resolucion = strtolower(trim($resolucion));

    if ($resolucion == 'abierto') {
        return [
            'texto' => 'Abierto',
            'clase' => 'badge-abierto'
        ];
    } elseif ($resolucion == 'equipo de tienda') {
        return [
            'texto' => 'Equipo de Tienda',
            'clase' => 'badge-equipo-tienda'
        ];
    } else {
        return [
            'texto' => $resolucion,
            'clase' => 'badge-cerrado'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registros de Reclamos</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <!-- contiene main, sub container * y body -->
    <style>
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


        .btn-agregar:hover {
            background-color: #51B8AC;
            color: white;
        }

        .contenedor-principal {
            width: 100%;
            margin: auto;
            padding: 0 1px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
        }

        th {
            background-color: #0E544C;
            color: white;
        }

        .columna-numero {
            width: 30px;
            display: none;
        }

        .filtro-contenedor {
            position: relative;
            display: inline-block;
        }

        .filtro-opciones {
            display: none;
            position: absolute;
            background-color: white;
            border: 1px solid #ccc;
            z-index: 1;
            padding: 5px;
            border-radius: 5px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
            min-width: 120px;
            width: auto;
        }

        .filtro-opciones.sucursal {
            width: 220px;
            padding: 8px;
        }

        .filtro-opciones.sucursal .sucursales-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 3px;
        }

        .filtro-opciones.sucursal a {
            text-align: left;
            padding: 4px 6px;
            white-space: nowrap;
        }

        /* Estilos para el filtro de mes/año como popup */
        .filtro-opciones.mes-anio {
            width: 200px;
            padding: 15px;
            right: 0;
        }

        .filtro-opciones.mes-anio form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .filtro-opciones.mes-anio select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ddd;
            width: 100%;
        }

        .filtro-opciones.mes-anio .botones-filtro {
            display: flex;
            justify-content: space-between;
            gap: 5px;
        }

        .filtro-opciones.mes-anio button {
            padding: 8px;
            background-color: #51B8AC;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            flex: 1;
        }

        .filtro-opciones.mes-anio button.cancelar {
            background-color: #f1f1f1;
            color: #333;
        }

        .filtro-opciones.mes-anio button:hover {
            background-color: #0E544C;
        }

        .filtro-opciones.mes-anio button.cancelar:hover {
            background-color: #ddd;
        }

        .filtro-opciones a {
            display: block;
            padding: 5px;
            text-decoration: none;
            color: black;
        }

        .filtro-opciones a:hover {
            background-color: #f1f1f1;
        }

        /* Mostrar el filtro cuando está activo o con hover */
        .filtro-contenedor:hover .filtro-opciones,
        .filtro-contenedor.activo .filtro-opciones {
            display: block;
        }

        .filtro-encabezado {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Estilos tipo badge para la resolución */
        .badge-resolucion {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: clamp(9px, 1.3vw, 11px) !important;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
            white-space: nowrap;
        }

        .badge-abierto {
            background-color: #FF5252;
            color: white;
            border: 1px solid #FF1744;
        }

        .badge-equipo-tienda {
            background-color: #4CAF50;
            color: white;
            border: 1px solid #2E7D32;
        }

        .badge-cerrado {
            background-color: #2196F3;
            color: white;
            border: 1px solid #1565C0;
        }

        .accion-ver {
            color: #51B8AC;
            margin-left: 8px;
            transition: color 0.3s;
        }

        .accion-ver:hover {
            color: #0E544C;
        }

        /* Estilos para el encabezado del historial */
        .encabezado-historial {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            width: 100%;
        }

        .titulo-historial {
            margin: 0;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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

            .filtro-opciones.sucursal {
                width: 160px;
                left: 0;
                transform: translateX(0);
            }

            .filtro-opciones.sucursal .sucursales-grid {
                grid-template-columns: 1fr;
            }

            .filtro-opciones.mes-anio {
                width: 160px;
                right: auto;
                left: 0;
                transform: translateX(0);
            }
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

            .btn-agregar i {
                margin-right: 4px;
            }

            .filtro-opciones.sucursal {
                width: 130px;
            }

            .filtro-opciones.sucursal .sucursales-grid {
                grid-template-columns: 1fr;
            }

            .filtro-opciones.mes-anio {
                width: 160px;
                right: auto;
                left: 0;
            }

            /* Ajustar tabla para móviles */
            table {
                font-size: 12px;
            }

            th,
            td {
                padding: 6px 3px;
            }
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container"> <!-- ya existe en el css de menu lateral -->
        <div class="sub-container"> <!-- ya existe en el css de menu lateral -->
            <?php echo renderHeader($usuario, 'Historial de Reclamos'); ?>
            <!-- Dejar vacio si Bienvenido.. -->
            <div class="contenedor-principal">
                <!-- Mostrar registros de reclamos -->
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; width: 100%; display:none;">
                    <div class="encabezado-historial">
                        <h3 class="titulo-historial">
                            <i class="fas fa-history"></i> Historial de Reclamos -
                            <?php echo $meses[$mes_seleccionado] . ' ' . $anio_seleccionado; ?>
                        </h3>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th class="columna-numero">No.</th>
                            <th style="text-align:center;">Fecha
                                <!-- Filtro de mes/año como popup -->
                                <div class="filtro-contenedor" id="filtro-mes-anio">
                                    <span class="filtro-encabezado" onclick="toggleFiltroMesAnio()">
                                        <i class="fas fa-calendar-alt" style="display:none;"></i> <i
                                            class="fas fa-caret-down"></i>
                                    </span>
                                    <div class="filtro-opciones mes-anio">
                                        <form method="get" action="index_reclamos_publico.php">
                                            <!-- Mantener el filtro de sucursal si existe -->
                                            <?php if (isset($_GET['sucursal']) && $_GET['sucursal'] != 'todas'): ?>
                                                <input type="hidden" name="sucursal"
                                                    value="<?php echo htmlspecialchars($_GET['sucursal']); ?>">
                                            <?php endif; ?>

                                            <select name="mes" id="mes">
                                                <?php foreach ($meses as $num => $nombre): ?>
                                                    <option value="<?php echo $num; ?>" <?php echo $num == $mes_seleccionado ? 'selected' : ''; ?>>
                                                        <?php echo $nombre; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <select name="anio" id="anio">
                                                <?php foreach ($anios as $anio): ?>
                                                    <option value="<?php echo $anio; ?>" <?php echo $anio == $anio_seleccionado ? 'selected' : ''; ?>>
                                                        <?php echo $anio; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <div class="botones-filtro">
                                                <button type="submit">Aplicar</button>
                                                <button type="button" class="cancelar"
                                                    onclick="cerrarFiltroMesAnio()">Cancelar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </th>
                            <th style="text-align:center;">
                                <div class="filtro-contenedor">
                                    <span class="filtro-encabezado">
                                        Sucursal <i class="fas fa-caret-down"></i>
                                    </span>
                                    <div class="filtro-opciones sucursal">
                                        <div class="sucursales-grid">
                                            <a
                                                href="<?php echo $url_base; ?>mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>&sucursal_codigo=todas">Todas</a>
                                            <?php foreach ($sucursales_fisicas as $sucursal): ?>
                                                <a
                                                    href="<?php echo $url_base; ?>mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>&sucursal_codigo=<?php echo urlencode($sucursal['codigo']); ?>">
                                                    <?php echo htmlspecialchars($sucursal['nombre']); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </th>
                            <th style="text-align:center;">Tipo</th>
                            <th style="text-align:center;">Resolución</th>
                            <th style="text-align:center;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reclamos)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; background-color:#fff;">Sin reclamos actualmente.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reclamos as $reclamo): ?>
                                <?php $badge = getBadgeResolucion($reclamo['resolucion']); ?>
                                <tr>
                                    <td class="columna-numero"><?php echo $reclamo['id']; ?></td>
                                    <td style="text-align:center;"><?php echo formatearFechaHora($reclamo['fecha_hora']); ?>
                                    </td>
                                    <td style="text-align:center;"><?php echo $reclamo['sucursal']; ?></td>
                                    <td style="text-align:center;"><?php echo $reclamo['tipo_reclamo']; ?></td>
                                    <td style="text-align:center;">
                                        <span class="badge-resolucion <?php echo $badge['clase']; ?>">
                                            <?php echo $badge['texto']; ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="ver_reclamo.php?id=<?php echo $reclamo['id']; ?>" class="accion-ver"
                                            title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Función para alternar la visibilidad del filtro de mes/año
        function toggleFiltroMesAnio() {
            const filtro = document.getElementById('filtro-mes-anio');
            filtro.classList.toggle('activo');
        }

        // Función para cerrar el filtro de mes/año
        function cerrarFiltroMesAnio() {
            const filtro = document.getElementById('filtro-mes-anio');
            filtro.classList.remove('activo');
        }

        // Cerrar el filtro si se hace clic fuera de él
        document.addEventListener('click', function (event) {
            const filtro = document.getElementById('filtro-mes-anio');
            const target = event.target;

            // Si el clic no fue dentro del filtro ni en el botón que lo activa
            if (!filtro.contains(target) && !target.closest('.filtro-encabezado')) {
                filtro.classList.remove('activo');
            }
        });
    </script>
</body>

</html>