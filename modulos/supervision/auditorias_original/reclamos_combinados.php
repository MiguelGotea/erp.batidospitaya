<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([11, 16, 22, 38]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 22, 38]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Configuración de zona horaria
date_default_timezone_set('America/Managua');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

// Obtener parámetros de filtro
$sucursal_seleccionada = isset($_GET['sucursal']) ? $_GET['sucursal'] : 'todas';
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
    // Consulta para obtener reclamos
    $sql = "
        SELECT 
            r.id,
            r.fecha_hora,
            r.sucursal,
            r.descripcion,
            IFNULL(ri.resolucion, 'Abierto') as resolucion,
            COUNT(rc.id) as colaboradores_involucrados
        FROM reclamos r
        LEFT JOIN reportes_investigacion ri ON r.id = ri.reclamo_id
        LEFT JOIN reportes_colaboradores rc ON ri.id = rc.reporte_id
        WHERE MONTH(r.fecha_hora) = :mes 
        AND YEAR(r.fecha_hora) = :anio
    ";
    
    if ($sucursal_seleccionada != 'todas') {
        $sql .= " AND r.sucursal = :sucursal";
    }
    
    $sql .= " GROUP BY r.id ORDER BY r.fecha_hora DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':mes', $mes_seleccionado, PDO::PARAM_INT);
    $stmt->bindValue(':anio', $anio_seleccionado, PDO::PARAM_INT);
    
    if ($sucursal_seleccionada != 'todas') {
        $stmt->bindValue(':sucursal', $sucursal_seleccionada, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $reclamos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

// Generar opciones de meses y años
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$anios = range(2020, date('Y') + 1);

// Función para formatear fecha y hora
function formatearFechaHora($fecha_hora) {
    $fecha = new DateTime($fecha_hora);
    // Restar 6 horas
    $fecha->modify('-6 hours');
    
    // Nombres de los meses en español
    $meses_espanol = [
        'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar', 
        'Apr' => 'Abr', 'May' => 'May', 'Jun' => 'Jun',
        'Jul' => 'Jul', 'Aug' => 'Ago', 'Sep' => 'Sep',
        'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'
    ];
    
    // Formatear fecha (30-Abr-25)
    $fecha_formateada = $fecha->format('d-M-y');
    
    // Reemplazar el mes en inglés con el equivalente en español
    $partes = explode('-', $fecha_formateada);
    if (isset($meses_espanol[$partes[1]])) {
        $partes[1] = $meses_espanol[$partes[1]];
        $fecha_formateada = implode('-', $partes);
    }
    
    // Formatear hora en formato 12 horas con AM/PM
    $hora_formateada = $fecha->format('h:i A');
    
    return $fecha_formateada . ' ' . $hora_formateada;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reclamos Combinados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="icon12.png" type="image/png">
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            box-sizing: border-box;
            font-size: clamp(11px, 2vw, 16px);
        }

        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
        }

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

        .logo {
            max-width: 75px;
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
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .btn-agregar:hover {
            background-color: #51B8AC;
            color: white;
        }
        
        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            flex-grow: 1;
        }
        
        .contenedor-principal {
            width: 100%;
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            margin-bottom: 20px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #51B8AC;
            color: white;
            position: sticky;
            top: 0;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
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
            padding: 8px;
            border-radius: 5px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
            min-width: 150px;
        }

        .filtro-opciones.sucursal {
            width: 220px;
        }
        
        .filtro-opciones.sucursal .sucursales-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 5px;
        }

        .filtro-opciones a {
            display: block;
            padding: 5px;
            text-decoration: none;
            color: #333;
        }

        .filtro-opciones a:hover {
            background-color: #f1f1f1;
        }

        .filtro-encabezado {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .filtro-contenedor:hover .filtro-opciones,
        .filtro-contenedor.activo .filtro-opciones {
            display: block;
        }

        .accion-ver {
            color: #51B8AC;
            text-decoration: none;
        }

        .accion-ver:hover {
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
                font-size: 13px;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Header con logo -->
    <div class="header-container">
        <div class="logo-container">
            <a href="index.php">
                <img src="Logo.svg" alt="Logo de la empresa" class="logo">
            </a>
        </div>
        <div class="buttons-container">
            <a href="promedio.php?mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>" class="btn-agregar">
                <i class="fas fa-arrow-left"></i> <span class="btn-text">Volver a Promedios</span>
            </a>
        </div>
    </div>
    
    <div class="contenedor-principal">
        <h2>Reclamos Combinados - <?php echo $meses[$mes_seleccionado] . ' ' . $anio_seleccionado; ?></h2>
        <?php if ($sucursal_seleccionada != 'todas'): ?>
            <h3>Sucursal: <?php echo $sucursal_seleccionada; ?></h3>
        <?php endif; ?>

        <!-- Tabla de reclamos -->
        <table>
            <thead>
                <tr>
                    <th style="text-align:center;">Fecha</th>
                    <th style="text-align:center;">Sucursal</th>
                    <th style="text-align:center;">Descripción</th>
                    <th style="text-align:center;">Resolución</th>
                    <th style="text-align:center;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reclamos)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No hay reclamos registrados para este período.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reclamos as $reclamo): ?>
                        <tr>
                            <td>
                                <?php echo formatearFechaHora($reclamo['fecha_hora']); ?>
                            </td>
                            <td><?php echo $reclamo['sucursal']; ?></td>
                            <td><?php echo strlen($reclamo['descripcion']) > 50 ? substr($reclamo['descripcion'], 0, 50) . '...' : $reclamo['descripcion']; ?></td>
                            <td><?php echo $reclamo['resolucion']; ?></td>
                            <td>
                                <a href="ver_reclamo.php?id=<?php echo $reclamo['id']; ?>" class="accion-ver" title="Ver detalles">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
