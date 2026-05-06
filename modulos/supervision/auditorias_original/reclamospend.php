<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
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
verificarAccesoCargo([11, 16, 42]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 49, 42]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Configuración de zona horaria
date_default_timezone_set('America/Managua');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

// Obtener reclamos pendientes de investigación (sin reporte final)
$queryReclamosPendientes = "SELECT r.id, 
                           DATE_FORMAT(r.fecha_evento, '%d-%b-%y') as fecha_evento_formatted,
                           s.nombre as sucursal, 
                           r.sucursal_codigo,
                           r.descripcion,
                           r.tipo_reclamo,
                           rg.nombre as grupo_nombre,
                           rt.nombre as tipo_nombre,
                           r.medio_compra,
                           r.fecha_evento
                           FROM reclamos r 
                           LEFT JOIN reportes_investigacion ri ON r.id = ri.reclamo_id 
                           JOIN sucursales s ON r.sucursal_codigo = s.codigo
                           LEFT JOIN reclamos_grupos rg ON r.grupo_id = rg.id
                           LEFT JOIN reclamos_tipos rt ON r.tipo_reclamo_id = rt.id
                           WHERE ri.id IS NULL 
                           ORDER BY r.fecha_evento DESC";
$reclamosPendientes = $conn->query($queryReclamosPendientes)->fetchAll();

// Verificar si hay parámetro de éxito en la URL
$reporteExitoso = isset($_GET['exito']) && $_GET['exito'] == '1';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reclamos Pendientes de Investigación</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px);
        }

        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 20px;
            color: #333;
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #0E544C;
            text-align: center;
            margin-bottom: 25px;
            font-size: 24px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
        }

        .reclamos-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .reclamos-table th,
        .reclamos-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .reclamos-table th {
            background-color: #0E544C;
            color: white;
        }

        .reclamos-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .reclamos-table tr:hover {
            background-color: #f1f1f1;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            padding: 3px;
            background-color: #51B8AC;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0E544C;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .badge {
            display: inline-block;
            padding: 3px;
            border-radius: 4px;
            font-weight: bold;
            font-size: clamp(11px, 2vw, 16px);
        }

        .badge-pendiente {
            background-color: #FFC107;
            color: #333;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #777;
            font-style: italic;
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

            .container {
                padding: 15px;
            }

            h1 {
                font-size: 20px;
            }

            .reclamos-table {
                font-size: 14px;
            }

            .reclamos-table th,
            .reclamos-table td {
                padding: 8px;
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

            .reclamos-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="header-container">
            <div class="logo-container">
                <img src="Logo.svg" alt="Batidos Pitaya" class="logo">
            </div>

            <div class="buttons-container">
                <a href="index_avisos.php"
                    class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'index_avisos.php' ? 'activo' : '' ?>">
                    <i class="fas fa-bullhorn"></i> <span class="btn-text">Nuevo Aviso</span>
                </a>
                <a href="kpi.php"
                    class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'kpi.php' ? 'activo' : '' ?>">
                    <i class="fas fa-chart-line"></i> <span class="btn-text">KPI</span>
                </a>
                <a href="reclamospend.php"
                    class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'reclamospend.php' ? 'activo' : '' ?>">
                    <i class="fas fa-search"></i> <span class="btn-text">Reclamos</span>
                </a>
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
                <a href="../../../index.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <div>
        <h1><strong>RECLAMOS PENDIENTES DE INVESTIGACIÓN FINAL</strong></h1>

        <?php if ($reporteExitoso): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> El reporte de investigación se ha guardado exitosamente.
            </div>
        <?php endif; ?>

        <?php if (count($reclamosPendientes) > 0): ?>
            <table class="reclamos-table">
                <thead>
                    <tr>
                        <th style="text-align:center;">Código</th>
                        <th style="text-align:center;">Fecha Evento</th>
                        <th style="text-align:center;">Sucursal</th>
                        <th style="text-align:center; display:none;">Categoría / Tipo</th>
                        <th style="text-align:center; display:none;">Medio de Compra</th>
                        <th style="text-align:center; display:none;">Descripción</th>
                        <th style="text-align:center;">Estado</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reclamosPendientes as $reclamo): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reclamo['id']); ?></td>
                            <td><?php echo traducirMes(htmlspecialchars($reclamo['fecha_evento_formatted'])); ?></td>
                            <td><?php echo htmlspecialchars($reclamo['sucursal']); ?></td>
                            <td style="display:none;"><?php echo htmlspecialchars($reclamo['tipo_reclamo'] ?? '--'); ?></td>
                            <td style="display:none;"><?php echo htmlspecialchars($reclamo['medio_compra'] ?? '--'); ?></td>
                            <td style="display:none;">
                                <?php echo mb_strimwidth(htmlspecialchars($reclamo['descripcion']), 0, 50, '...'); ?>
                            </td>
                            <td style="text-align:center;"><span class="badge badge-pendiente">Abierto</span></td>
                            <td style="text-align:center;">
                                <a href="ver_reclamo.php?id=<?php echo $reclamo['id']; ?>" class="btn btn-secondary"
                                    title="Ver detalles" style="display:none;">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="reportereclamo.php?reclamo_id=<?php echo $reclamo['id']; ?>" class="btn btn-primary"
                                    title="Agregar investigación final">
                                    Investigar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <p>No hay reclamos pendientes de investigación en este momento.</p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
