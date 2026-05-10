<?php
$version = "1.0.17";
require_once '../../../core/auth/auth.php';
require_once '../../../core/layout/header_universal.php';
require_once '../../../core/layout/menu_lateral.php';
require_once '../../../core/permissions/permissions.php';

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
//false = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';


if (!tienePermiso('auditorias', 'vista', $cargoOperario)) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Al inicio del archivo, detecta si esta es la página de auditorías
$pagina_actual = basename($_SERVER['PHP_SELF']);
$es_pagina_auditorias = $pagina_actual == 'index_auditorias_publico.php';
$es_pagina_avisos = $pagina_actual == 'index_avisos_publico.php';
$es_pagina_promedios = ($pagina_actual == 'promedio.php');
$es_pagina_reclamos = ($pagina_actual == 'index_reclamos_publico.php');

// Obtener las selecciones del usuario (si existen)
$tipo_seleccionado = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
$sucursal_seleccionada = isset($_GET['sucursal']) ? $_GET['sucursal'] : 'todas';

// Obtener mes y año (por defecto mes y año actual)
$mes_seleccionado = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$anio_seleccionado = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');

// Validar las selecciones para evitar inyecciones SQL
$tipos_permitidos = ['todos', 'limpieza', 'personal', 'servicio'];
if (!in_array($tipo_seleccionado, $tipos_permitidos)) {
    $tipo_seleccionado = 'todos'; // Valor por defecto
}

$sucursales_permitidas = ['todas', 'Altamira', 'Villa Fontana', 'Las Colinas', 'Natura', 'Estelí', 'Granada', 'León', 'Masaya', 'Matagalpa'];
if (!in_array($sucursal_seleccionada, $sucursales_permitidas)) {
    $sucursal_seleccionada = 'todas'; // Valor por defecto
}

// Validar mes (1-12) y año (2020-2030)
if ($mes_seleccionado < 1 || $mes_seleccionado > 12) {
    $mes_seleccionado = date('n');
}
if ($anio_seleccionado < 2020 || $anio_seleccionado > 2030) {
    $anio_seleccionado = date('Y');
}

try {
    // Subconsulta para combinar las 3 tablas
    $sql = "
            SELECT * FROM (
                SELECT a.id, a.fecha_hora, s.nombre as sucursal, a.persona, a.promedio_general AS promedio, 'limpieza' AS tipo_auditoria 
                FROM auditoria a 
                JOIN sucursales s ON a.cod_sucursal = s.codigo
                UNION ALL
                SELECT ap.id, ap.fecha_hora, s.nombre as sucursal, ap.persona, ap.promedio_personal AS promedio, 'personal' AS tipo_auditoria 
                FROM auditoria_personal ap 
                JOIN sucursales s ON ap.cod_sucursal = s.codigo
                UNION ALL
                SELECT asv.id, asv.fecha_hora, s.nombre as sucursal, asv.persona, asv.promedio_calificacion AS promedio, 'servicio' AS tipo_auditoria 
                FROM auditoria_servicio asv 
                JOIN sucursales s ON asv.cod_sucursal = s.codigo
            ) AS combined_tables
            WHERE MONTH(fecha_hora) = :mes AND YEAR(fecha_hora) = :anio
        ";

    // Aplicar filtros adicionales si no son "todos/todas"
    if ($tipo_seleccionado != 'todos') {
        $sql .= " AND tipo_auditoria = :tipo";
    }
    if ($sucursal_seleccionada != 'todas') {
        $sql .= " AND sucursal = :sucursal";
    }

    // Ordenar por fecha de manera descendente
    $sql .= " ORDER BY fecha_hora DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':mes', $mes_seleccionado, PDO::PARAM_INT);
    $stmt->bindValue(':anio', $anio_seleccionado, PDO::PARAM_INT);

    if ($tipo_seleccionado != 'todos') {
        $stmt->bindValue(':tipo', $tipo_seleccionado, PDO::PARAM_STR);
    }
    if ($sucursal_seleccionada != 'todas') {
        $stmt->bindValue(':sucursal', $sucursal_seleccionada, PDO::PARAM_STR);
    }

    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Manejo de errores
    die("Error en la consulta: " . $e->getMessage());
}

// Generar opciones de meses y años para los selectores
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


$anios = range(2020, date('Y') + 1); // Desde 2020 hasta el próximo año

// Construir URL base para los filtros
$url_base = 'index_auditorias_publico.php?';
$params = [];
if (isset($_GET['tipo']) && $_GET['tipo'] != 'todos') {
    $params[] = 'tipo=' . urlencode($_GET['tipo']);
}
if (isset($_GET['sucursal']) && $_GET['sucursal'] != 'todas') {
    $params[] = 'sucursal=' . urlencode($_GET['sucursal']);
}
$url_filtros = $url_base . implode('&', $params);

// Obtener sucursales activas para los filtros
$query_sucursales_filtro = "SELECT nombre FROM sucursales WHERE activa = 1 AND sucursal = 1 ORDER BY nombre";
$stmt_sucursales_filtro = $conn->prepare($query_sucursales_filtro);
$stmt_sucursales_filtro->execute();
$sucursales_filtro = $stmt_sucursales_filtro->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registros de Auditoría</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <!-- contiene main, sub container * y body -->
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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

        .columna-promedio {
            width: 60px;
        }

        .promedio-contenedor {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-contenido {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 300px;
            text-align: center;
        }

        .modal-contenido h3 {
            margin-bottom: 20px;
        }

        .modal-contenido button {
            padding: 10px 20px;
            margin: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .modal-contenido #confirmar-btn {
            background-color: #FF6F61;
            color: white;
        }

        .modal-contenido #confirmar-btn:hover {
            background-color: #E55C4B;
        }

        .modal-contenido #cancelar-btn {
            background-color: #51B8AC;
            color: white;
        }

        .modal-contenido #cancelar-btn:hover {
            background-color: #0E544C;
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
                left: 50%;
                transform: translateX(-85%);
            }

            .filtro-opciones.sucursal .sucursales-grid {
                grid-template-columns: 1fr;
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
                right: -95px;
            }
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container"> <!-- ya existe en el css de menu lateral -->
        <div class="sub-container"> <!-- ya existe en el css de menu lateral -->
            <?php echo renderHeader($usuario, false, 'Auditorias'); ?> <!-- Dejar vacio si Bienvenido.. -->

            <div style="background: #fff; padding: 2px; display:none;">
                <p>Desempeño Acumulado</p>
                <a href="promedio.php" class="btn-agregar"><i class="fas fa-chart-line"></i> RESULTADOS</a>
                <br><br>
            </div>
            <br>

            <!-- Mostrar registros de la tabla seleccionada -->
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; width: 100%; display:none;">
                <div class="encabezado-historial">
                    <h3 class="titulo-historial">
                        <i class="fas fa-history"></i> Historial de Auditorías -
                        <?php echo $meses[$mes_seleccionado] . ' ' . $anio_seleccionado; ?>
                    </h3>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="columna-numero">No. Auditoría</th>
                        <th style="text-align:center;">Fecha
                            <!-- Filtro de mes/año como popup -->
                            <div class="filtro-contenedor" id="filtro-mes-anio">
                                <span class="filtro-encabezado" onclick="toggleFiltroMesAnio()">
                                    <i class="fas fa-calendar-alt" style="display:none;"></i> <i
                                        class="fas fa-caret-down"></i>
                                </span>
                                <div class="filtro-opciones mes-anio">
                                    <form method="get" action="index_auditorias_publico.php">
                                        <!-- Mantener los filtros existentes en los parámetros GET -->
                                        <?php if (isset($_GET['tipo']) && $_GET['tipo'] != 'todos'): ?>
                                            <input type="hidden" name="tipo"
                                                value="<?php echo htmlspecialchars($_GET['tipo']); ?>">
                                        <?php endif; ?>
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
                        <th style="text-align:center;">Hora</th>
                        <th style="text-align:center;">
                            <div class="filtro-contenedor">
                                <span class="filtro-encabezado">
                                    Sucursal <i class="fas fa-caret-down"></i>
                                </span>
                                <div class="filtro-opciones sucursal">
                                    <div class="sucursales-grid">
                                        <a href="<?php echo $url_filtros; ?>&sucursal=todas">Todas</a>
                                        <?php
                                        foreach ($sucursales_filtro as $sucursal) {
                                            echo '<a href="' . $url_filtros . '&sucursal=' . urlencode($sucursal['nombre']) . '">' .
                                                htmlspecialchars($sucursal['nombre']) . '</a>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </th>
                        <th style="text-align:center; display:none;">Persona</th>
                        <th style="text-align:center;">
                            <div class="filtro-contenedor">
                                <span class="filtro-encabezado">
                                    Tipo <i class="fas fa-caret-down"></i>
                                </span>
                                <div class="filtro-opciones">
                                    <a
                                        href="<?php echo $url_base; ?>sucursal=<?php echo urlencode($sucursal_seleccionada); ?>&mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>&tipo=todos">Todos</a>
                                    <a
                                        href="<?php echo $url_base; ?>sucursal=<?php echo urlencode($sucursal_seleccionada); ?>&mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>&tipo=limpieza">Limpieza</a>
                                    <a
                                        href="<?php echo $url_base; ?>sucursal=<?php echo urlencode($sucursal_seleccionada); ?>&mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>&tipo=personal">Personal</a>
                                    <a
                                        href="<?php echo $url_base; ?>sucursal=<?php echo urlencode($sucursal_seleccionada); ?>&mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>&tipo=servicio">Servicios</a>
                                </div>
                            </div>
                        </th>
                        <th style="text-align:center;" class="columna-promedio">Puntaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; background-color:#fff;">Sin registros actualmente.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($registros as $registro): ?>
                            <tr>
                                <td class="columna-numero"><?php echo $registro['id']; ?></td>
                                <td style="text-align:center;">
                                    <?php
                                    $meses_cortos = [
                                        1 => 'ene',
                                        2 => 'feb',
                                        3 => 'mar',
                                        4 => 'abr',
                                        5 => 'may',
                                        6 => 'jun',
                                        7 => 'jul',
                                        8 => 'ago',
                                        9 => 'sep',
                                        10 => 'oct',
                                        11 => 'nov',
                                        12 => 'dic'
                                    ];

                                    $fecha = new DateTime($registro['fecha_hora']);
                                    $dia = $fecha->format('d');
                                    $mes = $meses_cortos[(int) $fecha->format('m')];
                                    $anio = $fecha->format('y');

                                    echo "$dia-$mes-$anio";
                                    ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php
                                    $horafecha = new DateTime($registro['fecha_hora']);
                                    $hora = $horafecha->format('H:i');
                                    $hora_formateada = ($hora == '00:00') ? '12:00 am' :
                                        (($horafecha->format('H') < 12) ? $horafecha->format('g:i a') :
                                            (($horafecha->format('H') == 12) ? $horafecha->format('g:i') . ' pm' :
                                                ($horafecha->format('g:i')) . ' pm'));

                                    echo "$hora_formateada";
                                    ?>
                                </td>
                                <td style="text-align:center;"><?php echo $registro['sucursal']; ?></td>
                                <td style="display:none; text-align:center;"><?php echo $registro['persona']; ?></td>
                                <td style="text-align:center;"><?php echo ucfirst($registro['tipo_auditoria']); ?></td>
                                <td style="text-align:center;" class="columna-promedio">
                                    <div style="text-align:center;" class="promedio-contenedor">
                                        <?php echo number_format($registro['promedio'], 2); ?>
                                        <a href="<?php echo ($registro['tipo_auditoria'] == 'limpieza') ? 'ver_publico.php' : ($registro['tipo_auditoria'] == 'personal' ? 'verpersonal_publico.php' : 'verservicios_publico.php'); ?>?id=<?php echo $registro['id']; ?>"
                                            style="color:#51B8AC;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

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