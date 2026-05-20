<?php

/**
 * reclamospend.php
 * Reclamos pendientes de investigación final (procesamiento)
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/layout/menu_lateral.php';
require_once '../../../core/layout/header_universal.php';
require_once '../../../core/permissions/permissions.php';

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar si el usuario tiene el permiso de ver el historial completo (vista_total)
$verHistorialCompleto = tienePermiso('investigacion_reclamos', 'vista_total', $cargoOperario);

// Si no tiene vista_total, verificar si al menos tiene el permiso vista para pendientes
if (!$verHistorialCompleto) {
    verificarPermisoORedireccionar('investigacion_reclamos', 'vista', $cargoOperario);
}

// Configuración de zona horaria
date_default_timezone_set('America/Managua');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

// Obtener reclamos según permisos (pendientes si es vista, todos si es vista_total)
try {
    $whereClause = "";
    if (!$verHistorialCompleto) {
        $whereClause = "WHERE ri.id IS NULL";
    }

    $queryReclamos = "SELECT r.id, 
                             DATE_FORMAT(r.fecha_evento, '%d-%b-%y') as fecha_evento_formatted,
                             DATE_FORMAT(r.fecha_reclamo, '%d-%b-%y') as fecha_reclamo_formatted,
                             r.hora_evento,
                             s.nombre as sucursal, 
                             r.sucursal_codigo,
                             r.descripcion,
                             r.tipo_reclamo,
                             rg.nombre as grupo_nombre,
                             rt.nombre as tipo_nombre,
                             r.medio_compra,
                             r.fecha_evento,
                             r.fecha_reclamo,
                             ri.id as reporte_id
                      FROM reclamos r 
                      LEFT JOIN reportes_investigacion ri ON r.id = ri.reclamo_id 
                      JOIN sucursales s ON r.sucursal_codigo = s.codigo
                      LEFT JOIN reclamos_grupos rg ON r.grupo_id = rg.id
                      LEFT JOIN reclamos_tipos rt ON r.tipo_reclamo_id = rt.id
                      $whereClause
                      ORDER BY r.fecha_evento DESC";
    $reclamosPendientes = $conn->query($queryReclamos)->fetchAll();
} catch (PDOException $e) {
    $reclamosPendientes = [];
    error_log("Error al obtener reclamos: " . $e->getMessage());
}

// Definir título dinámico para la página y encabezado
$tituloPagina = $verHistorialCompleto ? 'Historial Completo de Reclamos' : 'Reclamos Pendientes de Investigación';

// Verificar si hay parámetro de éxito en la URL
$reporteExitoso = isset($_GET['exito']) && $_GET['exito'] == '1';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $tituloPagina; ?> | Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <style>
        .reclamos-table {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .reclamos-table thead th {
            background-color: #0E544C;
            color: white;
            border-bottom: none;
            padding: 15px;
            font-weight: 600;
        }

        .reclamos-table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        .badge-pendiente {
            background-color: #FFF3CD;
            color: #856404;
            border: 1px solid #FFEEBA;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .badge-resuelto {
            background-color: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .btn-action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            color: white !important;
            text-decoration: none !important;
        }

        .btn-investigar-icon {
            background-color: #51B8AC;
        }

        .btn-investigar-icon:hover {
            background-color: #0E544C;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(81, 184, 172, 0.4);
        }

        .btn-ver-icon {
            background-color: #2196F3;
        }

        .btn-ver-icon:hover {
            background-color: #1565C0;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(33, 150, 243, 0.4);
        }

        .no-data-card {
            background-color: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .no-data-icon {
            font-size: 4rem;
            color: #e0e0e0;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, $tituloPagina); ?>

            <div class="container-fluid p-4">
                <?php if ($reporteExitoso): ?>
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert"
                        style="border-radius: 12px; border-left: 5px solid #28a745 !important;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle fs-4 me-3"></i>
                            <div>
                                <h6 class="alert-heading fw-bold mb-1">¡Operación Exitosa!</h6>
                                <p class="mb-0 small">El reporte de investigación se ha guardado correctamente.</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-body p-0">
                        <?php if (count($reclamosPendientes) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 reclamos-table">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 80px;">Codigo</th>
                                            <th>Fecha Reclamo</th>
                                            <th>Fecha Evento</th>
                                            <th>Sucursal</th>
                                            <th>Medio</th>
                                            <th class="text-center">Estado</th>
                                            <th class="text-center" style="width: 100px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reclamosPendientes as $reclamo): ?>
                                            <?php $esPendiente = empty($reclamo['reporte_id']); ?>
                                            <tr>
                                                <td class="text-center fw-bold text-muted">
                                                    <?php echo htmlspecialchars($reclamo['id']); ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="far fa-calendar-check me-2 text-success"></i>
                                                        <?php echo traducirMes(htmlspecialchars($reclamo['fecha_reclamo_formatted'] ?? '--')); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="far fa-calendar-alt me-2 text-primary"></i>
                                                        <?php echo traducirMes(htmlspecialchars($reclamo['fecha_evento_formatted'])); ?>
                                                    </div>
                                                    <?php if (!empty($reclamo['hora_evento'])): ?>
                                                        <div class="text-muted small mt-1" style="padding-left: 24px;">
                                                            <i class="far fa-clock me-1"></i>
                                                            <?php echo htmlspecialchars(date('h:i A', strtotime($reclamo['hora_evento']))); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark">
                                                        <?php echo htmlspecialchars($reclamo['sucursal']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-light text-dark border"><?php echo htmlspecialchars($reclamo['medio_compra'] ?? '--'); ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($esPendiente): ?>
                                                        <span class="badge-pendiente">Abierto</span>
                                                    <?php else: ?>
                                                        <span class="badge-resuelto">Cerrado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($esPendiente): ?>
                                                        <a href="reportereclamo.php?reclamo_id=<?php echo $reclamo['id']; ?>"
                                                            class="btn-action-icon btn-investigar-icon" title="Investigar Reclamo">
                                                            <i class="fas fa-search"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="ver_reclamo.php?id=<?php echo $reclamo['id']; ?>"
                                                            class="btn-action-icon btn-ver-icon" title="Ver Detalle del Reclamo">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-data-card">
                                <div class="no-data-icon">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <h4 class="fw-bold text-dark">¡Todo al día!</h4>
                                <p class="text-muted">No se encontraron reclamos pendientes de investigación en este
                                    momento.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>