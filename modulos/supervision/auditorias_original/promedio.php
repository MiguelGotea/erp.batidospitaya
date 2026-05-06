<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once '../../../includes/auth.php';
require_once '../../../core/layout/header_universal.php';
require_once '../../../core/layout/menu_lateral.php';
require_once '../../../core/permissions/permissions.php';

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo
if (!tienePermiso('desempeno_sucursales', 'vista', $cargoOperario)) {
    header('Location: ../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Al inicio del archivo promedio.php, agregar esto para detectar la página actual
$pagina_actual = basename($_SERVER['PHP_SELF']);
$es_pagina_avisos = $pagina_actual == 'index_avisos_publico.php';
$es_pagina_auditorias = $pagina_actual == 'index_auditorias_publico.php';
$es_pagina_promedio = $pagina_actual == 'promedio.php';
$es_pagina_reclamos = ($pagina_actual == 'index_reclamos_publico.php');

require_once 'conexion.php';

// Obtener el mes y año seleccionados (si existen)
$mes_seleccionado = isset($_GET['mes']) ? (int) $_GET['mes'] : date('n');
$anio_seleccionado = isset($_GET['anio']) ? (int) $_GET['anio'] : date('Y');

// Obtener lista de sucursales activas desde la base de datos
$query_sucursales = "SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal = 1 ORDER BY nombre";
$stmt_sucursales = $conn->prepare($query_sucursales);
$stmt_sucursales->execute();
$sucursales_data = $stmt_sucursales->fetchAll(PDO::FETCH_ASSOC);

// Crear array asociativo de sucursales [codigo => nombre]
$sucursales = [];
foreach ($sucursales_data as $sucursal) {
    $sucursales[$sucursal['codigo']] = $sucursal['nombre'];
}

// Obtener datos de limpieza - USANDO cod_sucursal
$query_limpieza = "SELECT cod_sucursal, AVG(promedio_general) as promedio, COUNT(*) as cantidad 
                   FROM auditoria 
                   WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio 
                   GROUP BY cod_sucursal";
$stmt_limpieza = $conn->prepare($query_limpieza);
$stmt_limpieza->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$limpieza_data = $stmt_limpieza->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Obtener promedio general de limpieza
$query_limpieza_total = "SELECT AVG(promedio_general) as promedio_total, COUNT(*) as cantidad_total 
                         FROM auditoria 
                         WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio";
$stmt_limpieza_total = $conn->prepare($query_limpieza_total);
$stmt_limpieza_total->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$limpieza_total = $stmt_limpieza_total->fetch(PDO::FETCH_ASSOC);

// Obtener datos de servicio - USANDO cod_sucursal
$query_servicio = "SELECT cod_sucursal, AVG(promedio_calificacion) as promedio, COUNT(*) as cantidad 
                   FROM auditoria_servicio 
                   WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio 
                   GROUP BY cod_sucursal";
$stmt_servicio = $conn->prepare($query_servicio);
$stmt_servicio->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$servicio_data = $stmt_servicio->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Obtener promedio general de servicio
$query_servicio_total = "SELECT AVG(promedio_calificacion) as promedio_total, COUNT(*) as cantidad_total 
                         FROM auditoria_servicio 
                         WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio";
$stmt_servicio_total = $conn->prepare($query_servicio_total);
$stmt_servicio_total->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$servicio_total = $stmt_servicio_total->fetch(PDO::FETCH_ASSOC);

// Obtener datos de personal - USANDO cod_sucursal
$query_personal = "SELECT cod_sucursal, AVG(promedio_personal) as promedio, COUNT(*) as cantidad 
                   FROM auditoria_personal 
                   WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio 
                   GROUP BY cod_sucursal";
$stmt_personal = $conn->prepare($query_personal);
$stmt_personal->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$personal_data = $stmt_personal->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Obtener promedio general de personal
$query_personal_total = "SELECT AVG(promedio_personal) as promedio_total, COUNT(*) as cantidad_total 
                         FROM auditoria_personal 
                         WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio";
$stmt_personal_total = $conn->prepare($query_personal_total);
$stmt_personal_total->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$personal_total = $stmt_personal_total->fetch(PDO::FETCH_ASSOC);

// Obtener datos de KPIs Ventas y Reclamos
$query_kpi_reclamos = "SELECT cod_sucursal, kpi_ventas, reclamos_cantidad, reclamos_totales 
                       FROM kpi_reclamos 
                       WHERE mes = :mes AND anio = :anio";
$stmt_kpi_reclamos = $conn->prepare($query_kpi_reclamos);
$stmt_kpi_reclamos->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$kpi_reclamos_data = $stmt_kpi_reclamos->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Obtener datos de Total VM (Ventas Mensuales) desde VentasGlobalesAccessCSV
$query_ventas_mensuales = "SELECT local as cod_sucursal, SUM(Precio) as total_ventas
                          FROM VentasGlobalesAccessCSV
                          WHERE MONTH(Fecha) = :mes AND YEAR(Fecha) = :anio
                          AND DATE(Fecha) < CURDATE()
                          AND (Anulado IS NULL OR Anulado = 0)
                          GROUP BY local";
$stmt_ventas_mensuales = $conn->prepare($query_ventas_mensuales);
$stmt_ventas_mensuales->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$ventas_mensuales_data = $stmt_ventas_mensuales->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Obtener datos de Total VMt (Ventas Meta) desde ventas_meta
$query_ventas_meta = "SELECT cod_sucursal, SUM(meta) as total_meta
                     FROM ventas_meta
                     WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio
                     AND DATE(fecha) < CURDATE()
                     GROUP BY cod_sucursal";
$stmt_ventas_meta = $conn->prepare($query_ventas_meta);
$stmt_ventas_meta->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$ventas_meta_data = $stmt_ventas_meta->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Obtener promedios totales de KPIs Ventas y Reclamos
$query_kpi_reclamos_total = "SELECT AVG(kpi_ventas) as kpi_ventas_total, 
                                    SUM(reclamos_cantidad) as reclamos_cantidad_total,
                                    SUM(reclamos_totales) as reclamos_totales_total
                             FROM kpi_reclamos 
                             WHERE mes = :mes AND anio = :anio";
$stmt_kpi_reclamos_total = $conn->prepare($query_kpi_reclamos_total);
$stmt_kpi_reclamos_total->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$kpi_reclamos_total = $stmt_kpi_reclamos_total->fetch(PDO::FETCH_ASSOC);

// Función para determinar el color según el promedio
function getColorClass($promedio)
{
    if ($promedio <= 4)
        return 'rojo';
    if ($promedio <= 4.5)
        return 'amarillo';
    return 'verde';
}

// Función para calcular el porcentaje de reclamos según la nueva escala
function calcularPorcentajeReclamos($cantidad)
{
    if ($cantidad <= 1)
        return 100;
    if ($cantidad == 2)
        return 80;
    if ($cantidad == 3)
        return 60;
    if ($cantidad == 4)
        return 40;
    if ($cantidad == 5)
        return 20;
    return 0; // Para 6 o más reclamos
}

// Función para generar el enlace de ver auditorías
function getVerAuditoriasLink($cod_sucursal, $tipo, $mes, $anio)
{
    return "ver_auditorias.php?sucursal=" . urlencode($cod_sucursal) .
        "&tipo=" . $tipo .
        "&mes=" . $mes .
        "&anio=" . $anio;
}

// Función para obtener el valor visual ajustado del Factor %
// Solo para visualización - los cálculos internos usan el valor real
function obtenerFactorVisual($factor_real)
{
    if ($factor_real < 85) {
        return 60;
    } elseif ($factor_real >= 130) {
        return 130;
    } else {
        return $factor_real;
    }
}

// Array para almacenar datos de todas las sucursales y ordenarlas
$datos_sucursales = [];

// Variables para totales de ventas
$total_ventas_global = 0;
$total_meta_global = 0;

foreach ($sucursales as $cod_sucursal => $nombre_sucursal) {
    $limpieza = $limpieza_data[$cod_sucursal][0] ?? ['promedio' => 0, 'cantidad' => 0];
    $personal = $personal_data[$cod_sucursal][0] ?? ['promedio' => 0, 'cantidad' => 0];
    $servicio = $servicio_data[$cod_sucursal][0] ?? ['promedio' => 0, 'cantidad' => 0];
    $kpi_reclamos = $kpi_reclamos_data[$cod_sucursal][0] ?? ['kpi_ventas' => 0, 'reclamos_cantidad' => 0];

    // Obtener datos de ventas
    $ventas_mensual = $ventas_mensuales_data[$cod_sucursal][0] ?? ['total_ventas' => 0];
    $ventas_meta = $ventas_meta_data[$cod_sucursal][0] ?? ['total_meta' => 0];

    $total_vm = $ventas_mensual['total_ventas'] ?? 0;
    $total_vmt = $ventas_meta['total_meta'] ?? 0;

    // Acumular totales globales
    $total_ventas_global += $total_vm;
    $total_meta_global += $total_vmt;

    // Calcular Factor %
    $factor_porcentaje = 0;
    if ($total_vmt > 0) {
        $factor_porcentaje = ($total_vm / $total_vmt) * 100;
    }

    // Calcular promedio general para la sucursal
    $general = 0;
    $mostrar_general = false;
    $contador_tipos = 0;

    if ($limpieza['cantidad'] > 0 || $personal['cantidad'] > 0 || $servicio['cantidad'] > 0 || $kpi_reclamos['kpi_ventas'] > 0) {
        $suma = 0;
        $contador = 0;

        if ($limpieza['cantidad'] > 0) {
            $suma += $limpieza['promedio'];
            $contador++;
        }
        if ($personal['cantidad'] > 0) {
            $suma += $personal['promedio'];
            $contador++;
        }
        if ($servicio['cantidad'] > 0) {
            $suma += $servicio['promedio'];
            $contador++;
        }
        if ($kpi_reclamos['kpi_ventas'] > 0) {
            $suma += $kpi_reclamos['kpi_ventas'];
            $contador++;
        }

        $general = $suma / $contador;
        $mostrar_general = true;
    }

    // Almacenar datos para ordenar
    $datos_sucursales[$cod_sucursal] = [
        'nombre' => $nombre_sucursal,
        'general' => $general,
        'limpieza' => $limpieza,
        'personal' => $personal,
        'servicio' => $servicio,
        'kpi_reclamos' => $kpi_reclamos,
        'mostrar_general' => $mostrar_general,
        'total_vm' => $total_vm,
        'total_vmt' => $total_vmt,
        'factor_porcentaje' => $factor_porcentaje
    ];
}

// Ordenar las sucursales por el valor general (de mayor a menor)
uasort($datos_sucursales, function ($a, $b) {
    return $b['general'] <=> $a['general'];
});

// Calcular promedios totales por tipo (solo si hay datos)
$promedios_limpieza = [];
$promedios_personal = [];
$promedios_servicio = [];
$promedios_kpi_ventas = [];
$porcentajes_reclamos = [];

foreach ($datos_sucursales as $cod_sucursal => $datos) {
    if ($datos['limpieza']['cantidad'] > 0) {
        $promedios_limpieza[] = $datos['limpieza']['promedio'];
    }
    if ($datos['personal']['cantidad'] > 0) {
        $promedios_personal[] = $datos['personal']['promedio'];
    }
    if ($datos['servicio']['cantidad'] > 0) {
        $promedios_servicio[] = $datos['servicio']['promedio'];
    }
    if ($datos['kpi_reclamos']['kpi_ventas'] > 0) {
        $promedios_kpi_ventas[] = $datos['kpi_reclamos']['kpi_ventas'];
    }
    if ($datos['kpi_reclamos']['reclamos_cantidad'] > 0) {
        $porcentajes_reclamos[] = calcularPorcentajeReclamos($datos['kpi_reclamos']['reclamos_cantidad']);
    }
}

$promedio_limpieza_total = count($promedios_limpieza) > 0 ? array_sum($promedios_limpieza) / count($promedios_limpieza) : 0;
$promedio_personal_total = count($promedios_personal) > 0 ? array_sum($promedios_personal) / count($promedios_personal) : 0;
$promedio_servicio_total = count($promedios_servicio) > 0 ? array_sum($promedios_servicio) / count($promedios_servicio) : 0;
$promedio_kpi_ventas_total = count($promedios_kpi_ventas) > 0 ? array_sum($promedios_kpi_ventas) / count($promedios_kpi_ventas) : 0;

// Calcular promedio general total (promedio de los promedios por tipo)
$suma_promedios_tipos = 0;
$contador_tipos_con_datos = 0;

if (count($promedios_limpieza) > 0) {
    $suma_promedios_tipos += $promedio_limpieza_total;
    $contador_tipos_con_datos++;
}
if (count($promedios_personal) > 0) {
    $suma_promedios_tipos += $promedio_personal_total;
    $contador_tipos_con_datos++;
}
if (count($promedios_servicio) > 0) {
    $suma_promedios_tipos += $promedio_servicio_total;
    $contador_tipos_con_datos++;
}
if (count($promedios_kpi_ventas) > 0) {
    $suma_promedios_tipos += $promedio_kpi_ventas_total;
    $contador_tipos_con_datos++;
}

$general_total = $contador_tipos_con_datos > 0 ? $suma_promedios_tipos / $contador_tipos_con_datos : 0;

// Calcular porcentaje general (solo si el promedio es >= 4)
$porcentaje_general_total = ($general_total >= 4) ? ($general_total / 5) * 100 : 0;

// Calcular porcentaje de reclamos total según la nueva escala
$reclamos_cantidad_total = $kpi_reclamos_total['reclamos_cantidad_total'] ?? 0;
$porcentaje_reclamos_total = calcularPorcentajeReclamos($reclamos_cantidad_total);

// Calcular porcentaje total (producto de General y Reclamos)
$porcentaje_total = ($porcentaje_general_total * $porcentaje_reclamos_total) / 100;

// Calcular Factor % total
$factor_porcentaje_total = 0;
if ($total_meta_global > 0) {
    $factor_porcentaje_total = ($total_ventas_global / $total_meta_global) * 100;
}

// Calcular Total % (Total * Factor %)
$total_porcentaje_final = ($porcentaje_total * $factor_porcentaje_total) / 100;

// Determinar colores para los totales
$color_limpieza_total = getColorClass($promedio_limpieza_total);
$color_personal_total = getColorClass($promedio_personal_total);
$color_servicio_total = getColorClass($promedio_servicio_total);
$color_kpi_ventas_total = getColorClass($promedio_kpi_ventas_total);
$color_general_total = getColorClass($general_total);
$color_reclamos_porcentaje_total = getColorClass($porcentaje_reclamos_total / 20); // Convertir a escala de 5 (100% = 5)
$color_porcentaje_total = getColorClass($porcentaje_total / 20); // Convertir a escala de 5 (100% = 5)
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desempeño Acumulado</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet"
        href="/assets/css/global_tools.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/global_tools.css') ?>">
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


        .contenedor-principal {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            margin-bottom: 25px;
        }

        th,
        td {
            padding: 7px;
            border: 1px solid #ddd;
        }

        th {
            background-color: #0E544C;
            color: white;
        }

        /* Estilos para los círculos de color */
        .color-circle {
            display: inline-block;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .rojo {
            background-color: #FF6F61;
        }

        .amarillo {
            background-color: #FFD166;
        }

        .verde {
            background-color: #06D6A0;
        }

        /* Estilos para los filtros */
        .filtros-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filtro {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filtro select,
        .filtro button {
            padding: 6px 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        .filtro button {
            background-color: #51B8AC;
            color: white;
            border: none;
            cursor: pointer;
        }

        .filtro button:hover {
            background-color: #0E544C;
        }

        @media (max-width: 768px) {
            * {
                font-size: 10px;
            }

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

            /* Estilos para el círculo en responsive */
            /* Reducir tamaño del círculo en móviles */
            .color-circle {
                width: 10px;
                height: 10px;
                margin: 0 auto 3px auto;
                /* Menor margen inferior */
            }

            /* Ajustar contenedor para círculos más pequeños */
            .contenedor-valor {
                flex-direction: column;
                align-items: center;
            }

            .valor-con-circulo {
                display: flex;
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            * {
                font-size: 8px;
            }

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
        }

        /* Estilo para la fila de totales */
        .total-row {
            background-color: #f0f0f0;
        }

        .cantidad-auditorias {
            font-size: 0.8em;
            opacity: 0.8;
        }

        /* Estilo para la columna General */
        .general-cell {
            background-color: #f0f0f0;
            color: black;
        }

        /* Estilo para las columnas de porcentaje */
        .porcentaje-cell {}

        /* Estilo para agrupar columnas */
        .group-header {
            background-color: #3a9a8d;
        }

        /* Clase para contener valor y círculo */
        .contenedor-valor {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Estilo para columnas de ventas */
        .ventas-cell {
            background-color: #e8f5e9;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container"> <!-- ya existe en el css de menu lateral -->
        <div class="sub-container"> <!-- ya existe en el css de menu lateral -->
            <?php echo renderHeader($usuario, $esAdmin, 'Desempeño de TIenda'); ?> <!-- Dejar vacio si Bienvenido.. -->
            <div class="contenedor-principal">
                <!-- Filtros de mes y año -->
                <div class="filtros-container">
                    <form method="get" action="promedio.php" class="filtro">
                        <label for="mes">Mes:</label>
                        <select name="mes" id="mes">
                            <?php
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

                            foreach ($meses as $num => $nombre) {
                                $selected = ($num == $mes_seleccionado) ? 'selected' : '';
                                echo "<option value='$num' $selected>$nombre</option>";
                            }
                            ?>
                        </select>

                        <label for="anio">Año:</label>
                        <select name="anio" id="anio">
                            <?php
                            $anio_actual = date('Y');
                            for ($i = $anio_actual; $i >= $anio_actual - 5; $i--) {
                                $selected = ($i == $anio_seleccionado) ? 'selected' : '';
                                echo "<option value='$i' $selected>$i</option>";
                            }
                            ?>
                        </select>

                        <button type="submit">Filtrar</button>
                    </form>
                </div>

                <!-- Tabla de resultados -->
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" style="text-align:center;">Sucursal</th>
                            <th rowspan="2" style="text-align:center;">Limpieza</th>
                            <th rowspan="2" style="text-align:center;">Personal</th>
                            <th rowspan="2" style="text-align:center;">Servicio</th>
                            <th rowspan="2" style="text-align:center;">KPIs Impulso de Ventas</th>
                            <th rowspan="2" style="text-align:center;" class="group-header">General</th>
                            <th colspan="2" style="text-align:center;">Reclamos</th>
                            <th rowspan="2" style="text-align:center; display:none;">Total</th>
                            <th rowspan="2" style="text-align:center; display:none;">Total VM</th>
                            <th rowspan="2" style="text-align:center; display:none;">Total VMt</th>
                            <th rowspan="2" style="text-align:center;">Cumplimiento de Ventas %</th>
                            <th rowspan="2" style="text-align:center;">Total %</th>
                        </tr>
                        <tr>
                            <th style="text-align:center;">Cant</th>
                            <th style="text-align:center;">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos_sucursales as $cod_sucursal => $datos):
                            $nombre_sucursal = $datos['nombre'];
                            $limpieza = $datos['limpieza'];
                            $personal = $datos['personal'];
                            $servicio = $datos['servicio'];
                            $kpi_reclamos = $datos['kpi_reclamos'];
                            $mostrar_general = $datos['mostrar_general'];
                            $general = $datos['general'];
                            $total_vm = $datos['total_vm'];
                            $total_vmt = $datos['total_vmt'];
                            $factor_porcentaje = $datos['factor_porcentaje'];

                            // Calcular porcentaje de reclamos según la nueva escala
                            $porcentaje_reclamos = calcularPorcentajeReclamos($kpi_reclamos['reclamos_cantidad']);

                            // Calcular porcentaje general (solo si el promedio es >= 4)
                            $porcentaje_general = ($general >= 4) ? ($general / 5) * 100 : 0;

                            // Calcular porcentaje total (producto de General y Reclamos)
                            $porcentaje_total = ($porcentaje_general * $porcentaje_reclamos) / 100;

                            // Calcular Total % (Total * Factor %)
                            $total_porcentaje = ($porcentaje_total * $factor_porcentaje) / 100;

                            // Determinar colores
                            $color_limpieza = getColorClass($limpieza['promedio']);
                            $color_personal = getColorClass($personal['promedio']);
                            $color_servicio = getColorClass($servicio['promedio']);
                            $color_kpi_ventas = getColorClass($kpi_reclamos['kpi_ventas']);
                            $color_general = getColorClass($general);
                            $color_reclamos_porcentaje = getColorClass($porcentaje_reclamos / 20); // Convertir a escala de 5 (100% = 5)
                            $color_porcentaje_total = getColorClass($porcentaje_total / 20); // Convertir a escala de 5 (100% = 5)
                            ?>
                            <tr>
                                <td><?php echo $nombre_sucursal; ?></td>

                                <!-- Columna Limpieza -->
                                <td style="text-align:center;">
                                    <?php if ($limpieza['cantidad'] > 0): ?>
                                        <div class="contenedor-valor">
                                            <span class="color-circle <?php echo $color_limpieza; ?>"></span>
                                            <div>
                                                <?php echo number_format($limpieza['promedio'], 1); ?>
                                                <span class="cantidad-auditorias">(<?php echo $limpieza['cantidad']; ?>)</span>
                                            </div>
                                            <a href="<?php echo getVerAuditoriasLink($cod_sucursal, 'limpieza', $mes_seleccionado, $anio_seleccionado); ?>"
                                                style="color: #51B8AC; margin-left: 5px; display:none;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        --
                                    <?php endif; ?>
                                </td>

                                <!-- Columna Personal -->
                                <td style="text-align:center;">
                                    <?php if ($personal['cantidad'] > 0): ?>
                                        <div class="contenedor-valor">
                                            <span class="color-circle <?php echo $color_personal; ?>"></span>
                                            <div>
                                                <?php echo number_format($personal['promedio'], 1); ?>
                                                <span class="cantidad-auditorias">(<?php echo $personal['cantidad']; ?>)</span>
                                            </div>
                                            <a href="<?php echo getVerAuditoriasLink($cod_sucursal, 'personal', $mes_seleccionado, $anio_seleccionado); ?>"
                                                style="color: #51B8AC; margin-left: 5px; display:none;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        --
                                    <?php endif; ?>
                                </td>

                                <!-- Columna Servicio -->
                                <td style="text-align:center;">
                                    <?php if ($servicio['cantidad'] > 0): ?>
                                        <div class="contenedor-valor">
                                            <span class="color-circle <?php echo $color_servicio; ?>"></span>
                                            <div>
                                                <?php echo number_format($servicio['promedio'], 1); ?>
                                                <span class="cantidad-auditorias">(<?php echo $servicio['cantidad']; ?>)</span>
                                            </div>
                                            <a href="<?php echo getVerAuditoriasLink($cod_sucursal, 'servicio', $mes_seleccionado, $anio_seleccionado); ?>"
                                                style="color: #51B8AC; margin-left: 5px; display:none;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        --
                                    <?php endif; ?>
                                </td>

                                <!-- Columna KPIs Ventas -->
                                <td style="text-align:center;">
                                    <?php if ($kpi_reclamos['kpi_ventas'] > 0): ?>
                                        <div class="contenedor-valor">
                                            <span class="color-circle <?php echo $color_kpi_ventas; ?>"></span>
                                            <div>
                                                <?php echo number_format($kpi_reclamos['kpi_ventas'], 2); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        --
                                    <?php endif; ?>
                                </td>

                                <!-- Columna General - Promedio -->
                                <td class="general-cell" style="text-align:center;">
                                    <?php if ($mostrar_general): ?>
                                        <div class="contenedor-valor">
                                            <span class="color-circle <?php echo $color_general; ?>"></span>
                                            <div>
                                                <?php echo number_format($general, 2); ?>
                                            </div>
                                            <a href="auditorias_combinadas.php?sucursal=<?php echo urlencode($cod_sucursal); ?>&mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>"
                                                style="color: #51B8AC; margin-left: 5px;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        --
                                    <?php endif; ?>
                                </td>

                                <!-- Columna Reclamos - Cantidad -->
                                <td style="text-align:center;">
                                    <?php if ($kpi_reclamos['reclamos_cantidad'] > 0): ?>
                                        <div class="contenedor-valor">
                                            <div>
                                                <span
                                                    style="font-size: 1.1em;"><?php echo $kpi_reclamos['reclamos_cantidad']; ?></span>
                                                <?php if (isset($kpi_reclamos['reclamos_totales']) && $kpi_reclamos['reclamos_totales'] > 0): ?>
                                                    <span
                                                        class="cantidad-auditorias">(<?php echo $kpi_reclamos['reclamos_totales']; ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="index_reclamos_publico.php?sucursal=<?php echo urlencode($cod_sucursal); ?>&mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>"
                                                style="color: #51B8AC; margin-left: 5px;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <?php if (isset($kpi_reclamos['reclamos_totales']) && $kpi_reclamos['reclamos_totales'] > 0): ?>
                                            <div class="contenedor-valor">
                                                <div>
                                                    <span style="font-size: 1.1em;">-</span>
                                                    <span
                                                        class="cantidad-auditorias">(<?php echo $kpi_reclamos['reclamos_totales']; ?>)</span>
                                                </div>
                                                <a href="index_reclamos_publico.php?sucursal=<?php echo urlencode($cod_sucursal); ?>&mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>"
                                                    style="color: #51B8AC; margin-left: 5px;">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            --
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <!-- Columna Reclamos - % -->
                                <td class="porcentaje-cell" style="text-align:center;">
                                    <?php echo number_format($porcentaje_reclamos, 0); ?>
                                </td>

                                <!-- Columna Total -->
                                <td class="porcentaje-cell" style="text-align:center; display:none;">
                                    <?php echo number_format($porcentaje_total, 2); ?>
                                </td>

                                <!-- Columna Total VM -->
                                <td class="ventas-cell" style="text-align:center; display:none;">
                                    <?php echo number_format($total_vm, 2); ?>
                                </td>

                                <!-- Columna Total VMt -->
                                <td class="ventas-cell" style="text-align:center; display:none;">
                                    <?php echo number_format($total_vmt, 2); ?>
                                </td>

                                <!-- Columna Factor % -->
                                <td style="text-align:center;">
                                    <?php echo number_format(obtenerFactorVisual($factor_porcentaje), 1); ?>%
                                </td>

                                <!-- Columna Total % -->
                                <td style="text-align:center;">
                                    <?php echo number_format($total_porcentaje, 2); ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Fila de totales -->
                        <tr class="total-row">
                            <td style="text-align:center;">Total</td>

                            <!-- Total Limpieza -->
                            <td style="text-align:center;">
                                <?php if (count($promedios_limpieza) > 0): ?>
                                    <div class="contenedor-valor">
                                        <span class="color-circle <?php echo $color_limpieza_total; ?>"></span>
                                        <div>
                                            <?php echo number_format($promedio_limpieza_total, 1); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    --
                                <?php endif; ?>
                            </td>

                            <!-- Total Personal -->
                            <td style="text-align:center;">
                                <?php if (count($promedios_personal) > 0): ?>
                                    <div class="contenedor-valor">
                                        <span class="color-circle <?php echo $color_personal_total; ?>"></span>
                                        <div>
                                            <?php echo number_format($promedio_personal_total, 1); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    --
                                <?php endif; ?>
                            </td>

                            <!-- Total Servicio -->
                            <td style="text-align:center;">
                                <?php if (count($promedios_servicio) > 0): ?>
                                    <div class="contenedor-valor">
                                        <span class="color-circle <?php echo $color_servicio_total; ?>"></span>
                                        <div>
                                            <?php echo number_format($promedio_servicio_total, 1); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    --
                                <?php endif; ?>
                            </td>

                            <!-- Total KPIs Ventas -->
                            <td style="text-align:center;">
                                <?php if (count($promedios_kpi_ventas) > 0): ?>
                                    <div class="contenedor-valor">
                                        <span class="color-circle <?php echo $color_kpi_ventas_total; ?>"></span>
                                        <div>
                                            <?php echo number_format($promedio_kpi_ventas_total, 2); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    --
                                <?php endif; ?>
                            </td>

                            <!-- Total General - Promedio -->
                            <td class="general-cell" style="text-align:center;">
                                <?php if ($contador_tipos_con_datos > 0): ?>
                                    <div class="contenedor-valor">
                                        <span class="color-circle <?php echo $color_general_total; ?>"></span>
                                        <div>
                                            <?php echo number_format($general_total, 2); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    --
                                <?php endif; ?>
                            </td>

                            <!-- Total Reclamos - Cantidad -->
                            <td style="text-align:center;">
                                <?php if ($reclamos_cantidad_total > 0): ?>
                                    <div>
                                        <span style="font-size: 1.1em;"><?php echo $reclamos_cantidad_total; ?></span>
                                        <?php if (isset($kpi_reclamos_total['reclamos_totales_total']) && $kpi_reclamos_total['reclamos_totales_total'] > 0): ?>
                                            <span
                                                class="cantidad-auditorias">(<?php echo $kpi_reclamos_total['reclamos_totales_total']; ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?php if (isset($kpi_reclamos_total['reclamos_totales_total']) && $kpi_reclamos_total['reclamos_totales_total'] > 0): ?>
                                        <div>
                                            <span style="font-size: 1.1em;">-</span>
                                            <span
                                                class="cantidad-auditorias">(<?php echo $kpi_reclamos_total['reclamos_totales_total']; ?>)</span>
                                        </div>
                                    <?php else: ?>
                                        --
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <!-- Total Reclamos - % -->
                            <td class="porcentaje-cell" style="text-align:center; visibility:hidden;">
                                <?php echo number_format($porcentaje_reclamos_total, 0); ?>
                            </td>

                            <!-- Total % Promedio -->
                            <td class="porcentaje-cell" style="text-align:center; display:none;">
                                <?php echo number_format($porcentaje_total, 1); ?>
                            </td>

                            <!-- Total VM - Total -->
                            <td class="ventas-cell" style="text-align:center; display:none;">
                                <?php echo number_format($total_ventas_global, 2); ?>
                            </td>

                            <!-- Total VMt - Total -->
                            <td class="ventas-cell" style="text-align:center; display:none;">
                                <?php echo number_format($total_meta_global, 2); ?>
                            </td>

                            <!-- Factor % - Total -->
                            <td style="text-align:center; visibility:hidden;">
                                <?php echo number_format(obtenerFactorVisual($factor_porcentaje_total), 1); ?>%
                            </td>

                            <!-- Total % Final -->
                            <td style="text-align:center; visibility:hidden;">
                                <?php echo number_format($total_porcentaje_final, 2); ?>%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
