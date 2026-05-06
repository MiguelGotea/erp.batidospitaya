<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once 'auth.php';
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditor�a
require_once 'conexion.php';

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([5, 8, 11, 13, 16, 21, 27, 43]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([5, 8, 11, 13, 16, 21, 27, 43]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Al inicio del archivo auditorias_combinadas.php, agregar esto para detectar la página actual
$pagina_actual = basename($_SERVER['PHP_SELF']);
$es_pagina_avisos = $pagina_actual == 'index_avisos_publico.php';
$es_pagina_auditorias = $pagina_actual == 'index_auditorias_publico.php';
$es_pagina_promedio = $pagina_actual == 'promedio.php';
$es_pagina_combinadas = $pagina_actual == 'auditorias_combinadas.php';

// Obtener parámetros de la URL
$sucursal_codigo = isset($_GET['sucursal']) ? $_GET['sucursal'] : null;
$mes_seleccionado = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$anio_seleccionado = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

// Obtener nombre de la sucursal desde la base de datos
$nombre_sucursal = 'Desconocida';
if ($sucursal_codigo) {
    $query_sucursal = "SELECT nombre FROM sucursales WHERE codigo = :codigo LIMIT 1";
    $stmt_sucursal = $conn->prepare($query_sucursal);
    $stmt_sucursal->execute([':codigo' => $sucursal_codigo]);
    $sucursal_data = $stmt_sucursal->fetch(PDO::FETCH_ASSOC);
    
    if ($sucursal_data) {
        $nombre_sucursal = $sucursal_data['nombre'];
    }
}

// Obtener nombre del mes (completo para el subtítulo)
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$nombre_mes = $meses[$mes_seleccionado];

// Array con meses abreviados (solo para la tabla)
$meses_abreviados = [
    1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
    5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
    9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'
];

// Consultas para obtener las auditorías de cada tipo (ACTUALIZADAS para usar cod_sucursal)
$auditorias_combinadas = [];

// 1. Auditorías de Limpieza - ACTUALIZADA para usar cod_sucursal
$query_limpieza = "SELECT a.id, a.fecha, a.cod_sucursal, a.promedio_general as promedio, 'limpieza' as tipo 
                   FROM auditoria a
                   WHERE MONTH(a.fecha) = :mes AND YEAR(a.fecha) = :anio AND a.cod_sucursal = :sucursal
                   ORDER BY a.fecha DESC";
$stmt_limpieza = $conn->prepare($query_limpieza);
$stmt_limpieza->execute([
    ':mes' => $mes_seleccionado,
    ':anio' => $anio_seleccionado,
    ':sucursal' => $sucursal_codigo
]);
$auditorias_limpieza = $stmt_limpieza->fetchAll(PDO::FETCH_ASSOC);

// 2. Auditorías de Personal - ACTUALIZADA para usar cod_sucursal
$query_personal = "SELECT ap.id, ap.fecha, ap.cod_sucursal, ap.promedio_personal as promedio, 'personal' as tipo 
                   FROM auditoria_personal ap
                   WHERE MONTH(ap.fecha) = :mes AND YEAR(ap.fecha) = :anio AND ap.cod_sucursal = :sucursal
                   ORDER BY ap.fecha DESC";
$stmt_personal = $conn->prepare($query_personal);
$stmt_personal->execute([
    ':mes' => $mes_seleccionado,
    ':anio' => $anio_seleccionado,
    ':sucursal' => $sucursal_codigo
]);
$auditorias_personal = $stmt_personal->fetchAll(PDO::FETCH_ASSOC);

// 3. Auditorías de Servicio - ACTUALIZADA para usar cod_sucursal
$query_servicio = "SELECT aserv.id, aserv.fecha, aserv.cod_sucursal, aserv.promedio_calificacion as promedio, 'servicio' as tipo 
                   FROM auditoria_servicio aserv
                   WHERE MONTH(aserv.fecha) = :mes AND YEAR(aserv.fecha) = :anio AND aserv.cod_sucursal = :sucursal
                   ORDER BY aserv.fecha DESC";
$stmt_servicio = $conn->prepare($query_servicio);
$stmt_servicio->execute([
    ':mes' => $mes_seleccionado,
    ':anio' => $anio_seleccionado,
    ':sucursal' => $sucursal_codigo
]);
$auditorias_servicio = $stmt_servicio->fetchAll(PDO::FETCH_ASSOC);

// Combinar todas las auditorías y ordenar por fecha descendente
$auditorias_combinadas = array_merge($auditorias_limpieza, $auditorias_personal, $auditorias_servicio);

// Función para ordenar por fecha
usort($auditorias_combinadas, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

// Función para determinar el color según el promedio
function getColorClass($promedio) {
    if ($promedio <= 4) return 'rojo';
    if ($promedio <= 4.5) return 'amarillo';
    return 'verde';
}

// Función para obtener el nombre del tipo de auditoría
function getTipoNombre($tipo) {
    switch ($tipo) {
        case 'limpieza': return 'Limpieza';
        case 'personal': return 'Personal';
        case 'servicio': return 'Servicio';
        default: return $tipo;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditorías Combinadas</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="icon12.png" type="image/png">
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

        .contenedor-principal {
            width: 100%;
            max-width: 1200px;
            margin: 25px auto;
            padding: 0 1px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }

        th, td {
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

        /* Header styles */
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
        
        .btn-agregar {
            background-color: transparent;
            color: #51B8AC;
            border: 1px solid #51B8AC;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
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
        
        table, th, td {
            text-align: center;
        }
        
        /* Estilo para el tipo de auditoría */
        .tipo-auditoria {
            font-weight: bold;
            color: #0E544C;
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
            
            .btn-agregar {
                padding: 6px 10px;
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
    </style>
</head>
<body>
    <!-- Header con logo y botones actualizado -->
    <div class="header-container">
        <div class="logo-container">
            <a href="index_auditorias_publico.php">
                <img src="Logo.svg" alt="Logo de la empresa" class="logo" style="max-width:75px;">
            </a>
        </div>
        <div class="buttons-container">
            <a href="index_avisos_publico.php" class="btn-agregar <?php echo $es_pagina_avisos ? 'activo' : ''; ?>">
                <i class="fas fa-bullhorn"></i> <span class="btn-text">Avisos</span>
            </a>
            <a href="index_auditorias_publico.php" class="btn-agregar <?php echo $es_pagina_auditorias ? 'activo' : ''; ?>">
                <i class="fas fa-clipboard-check"></i> <span class="btn-text">Auditorías</span>
            </a>
            <a href="promedio.php" class="btn-agregar <?php echo $es_pagina_promedio ? 'activo' : ''; ?>">
                <i class="fas fa-chart-bar"></i> <span class="btn-text">Promedios</span>
            </a>
        </div>
    </div>
    
    <div class="contenedor-principal">
        <h2 class="titulo-auditorias">Auditorías Combinadas</h2>
        <h3 class="subtitulo-auditorias">Sucursal: <?php echo htmlspecialchars($nombre_sucursal); ?> - <?php echo $nombre_mes; ?> <?php echo $anio_seleccionado; ?></h3>
        
        <a href="promedio.php?mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>" class="btn-regresar">
            <i class="fas fa-arrow-left"></i> Regresar a promedios
        </a>
        
        <?php if (count($auditorias_combinadas) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditorias_combinadas as $auditoria): 
                        $color = getColorClass($auditoria['promedio']);
                        
                        // Formatear la fecha en formato 03-abr-25
                        $fecha = new DateTime($auditoria['fecha']);
                        $dia = $fecha->format('d');
                        $mes_numero = $fecha->format('n');
                        $mes_abreviado = $meses_abreviados[$mes_numero];
                        $anio_abreviado = $fecha->format('y');
                        $fecha_formateada = "$dia-$mes_abreviado-$anio_abreviado";
                        
                        $tipo_nombre = getTipoNombre($auditoria['tipo']);
                        
                        // Determinar el enlace según el tipo de auditoría
                        $enlace_ver = '';
                        switch ($auditoria['tipo']) {
                            case 'limpieza':
                                $enlace_ver = "ver_publico.php?id={$auditoria['id']}&tipo=limpieza";
                                break;
                            case 'personal':
                                $enlace_ver = "verpersonal_publico.php?id={$auditoria['id']}&tipo=personal";
                                break;
                            case 'servicio':
                                $enlace_ver = "verservicios_publico.php?id={$auditoria['id']}&tipo=servicio";
                                break;
                        }
                    ?>
                    <tr>
                        <td><?php echo $fecha_formateada; ?></td>
                        <td class="tipo-auditoria"><?php echo $tipo_nombre; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="color-circle <?php echo $color; ?>"></span>
                                <?php echo number_format($auditoria['promedio'], 1); ?>
                                <a href="<?php echo $enlace_ver; ?>" style="margin-left:4px; color: #51B8AC;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No se encontraron auditorías para esta sucursal en el período seleccionado.</p>
        <?php endif; ?>
    </div>
</body>
</html>
