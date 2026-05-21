<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([11, 16, 21, 49, 52]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 21, 49, 52]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Obtener parámetros de la URL
$sucursal_seleccionada = isset($_GET['sucursal']) ? urldecode($_GET['sucursal']) : null;
$tipo_auditoria = isset($_GET['tipo']) ? $_GET['tipo'] : null;
$mes_seleccionado = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$anio_seleccionado = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

// Validar tipo de auditoría
$tipos_validos = ['limpieza', 'personal', 'servicio'];
if (!in_array($tipo_auditoria, $tipos_validos)) {
    die("Tipo de auditoría no válido");
}

// Obtener nombre del mes
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
$nombre_mes = $meses[$mes_seleccionado];

// Determinar la tabla y campos según el tipo de auditoría
switch ($tipo_auditoria) {
    case 'limpieza':
        $tabla = 'auditoria';
        $campo_promedio = 'promedio_general';
        $titulo = 'Auditorías de Limpieza';
        break;
    case 'personal':
        $tabla = 'auditoria_personal';
        $campo_promedio = 'promedio_personal';
        $titulo = 'Auditorías de Personal';
        break;
    case 'servicio':
        $tabla = 'auditoria_servicio';
        $campo_promedio = 'promedio_calificacion';
        $titulo = 'Auditorías de Servicio';
        break;
}

// Construir consulta SQL
$query = "SELECT * FROM $tabla 
          WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio";
$params = [':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado];

// Si se especificó una sucursal, agregar filtro
if ($sucursal_seleccionada) {
    $query .= " AND sucursal = :sucursal";
    $params[':sucursal'] = $sucursal_seleccionada;
}

$query .= " ORDER BY fecha DESC";

// Ejecutar consulta
$stmt = $conn->prepare($query);
$stmt->execute($params);
$auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para determinar el color según el promedio
function getColorClass($promedio)
{
    if ($promedio <= 4) return 'rojo';
    if ($promedio <= 4.5) return 'amarillo';
    return 'verde';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            text-align: center;
            align-content: center;
            align-items: center;
            justify-content: center;
            font-size: clamp(11px, 2vw, 16px) !important;
        }

        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 99%;
        }

        header {
            margin: 20px;
        }

        .logo {
            max-width: 75px;
        }

        .btn-agregar {
            background-color: #51B8AC;
            color: white;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            margin: 5px;
            display: inline-block;
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
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
        }

        th {
            background-color: #51B8AC;
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

        /* Header responsive */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1200px;
            padding: 0 10px;
            box-sizing: border-box;
            margin: 20px auto;
            flex-wrap: wrap;
        }

        .logo-container {
            margin-right: 20px;
            flex-shrink: 0;
        }

        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            flex-grow: 1;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .buttons-container {
                width: 100%;
                justify-content: flex-start;
                gap: 8px;
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
        }

        /* Estilo para el título */
        .titulo-auditorias {
            margin-bottom: 10px;
            color: #333;
        }

        /* Estilo para el subtítulo */
        .subtitulo-auditorias {
            margin-bottom: 20px;
            color: #666;
            font-weight: normal;
        }

        /* Estilo para el botón de regresar */
        .btn-regresar {
            background-color: #0E544C;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: inline-block;
        }

        .btn-regresar:hover {
            background-color: #5a6268;
        }

        table,
        th,
        td {
            text-align: center;
        }
    </style>
</head>

<body>
    <!-- Header con logo -->
    <div class="header-container">
        <div class="logo-container">
            <a href="index_auditorias_publico.php">
                <img src="/core/assets/img/Logo.svg" alt="Logo de la empresa" class="logo">
            </a>
        </div>
        <div class="buttons-container">
            <a href="index_avisos_publico.php" class="btn-agregar">
                <i class="fas fa-bullhorn"></i> <span class="btn-text">Avisos</span>
            </a>
            <a href="index_auditorias_publico.php" class="btn-agregar">
                <i class="fas fa-clipboard-check"></i> <span class="btn-text">Auditorías</span>
            </a>
        </div>
    </div>

    <div class="contenedor-principal">
        <h2 class="titulo-auditorias"><?php echo $titulo; ?></h2>

        <?php if ($sucursal_seleccionada): ?>
            <h3 class="subtitulo-auditorias">Sucursal: <?php echo $sucursal_seleccionada; ?> - <?php echo $nombre_mes; ?> <?php echo $anio_seleccionado; ?></h3>
        <?php else: ?>
            <h3 class="subtitulo-auditorias">Todas las sucursales - <?php echo $nombre_mes; ?> <?php echo $anio_seleccionado; ?></h3>
        <?php endif; ?>

        <a href="promedio.php?mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>" class="btn-regresar">
            <i class="fas fa-arrow-left"></i> Regresar a promedios
        </a>

        <?php if (count($auditorias) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Sucursal</th>
                        <th>Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditorias as $auditoria):
                        $color = getColorClass($auditoria[$campo_promedio]);
                        $fecha_formateada = date('d/m/Y', strtotime($auditoria['fecha']));
                    ?>
                        <tr>
                            <td><?php echo $fecha_formateada; ?></td>
                            <td><?php echo $auditoria['sucursal']; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; justify-content: center;">
                                    <span class="color-circle <?php echo $color; ?>"></span>
                                    <?php echo number_format($auditoria[$campo_promedio], 1); ?>
                                    <?php if ($tipo_auditoria == 'limpieza'): ?>
                                        <a href="ver_publico.php?id=<?php echo $auditoria['id']; ?>&tipo=limpieza" style="color: #51B8AC; margin-left: 5px;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php elseif ($tipo_auditoria == 'personal'): ?>
                                        <a href="verpersonal_publico.php?id=<?php echo $auditoria['id']; ?>&tipo=personal" style="color: #51B8AC; margin-left: 5px;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php elseif ($tipo_auditoria == 'servicio'): ?>
                                        <a href="verservicios_publico.php?id=<?php echo $auditoria['id']; ?>&tipo=servicio" style="color: #51B8AC; margin-left: 5px;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No se encontraron auditorías para los criterios seleccionados.</p>
        <?php endif; ?>
    </div>
</body>

</html>