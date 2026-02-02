<?php
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/includes/header_universal.php';
require_once '../../core/includes/menu_lateral.php';
require_once __DIR__ . '/models/Equipo.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_equipos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: equipos_lista.php');
    exit;
}

$equipoModel = new Equipo();
$equipo = $equipoModel->obtenerPorId($_GET['id']);

if (!$equipo) {
    header('Location: equipos_lista.php');
    exit;
}

// Obtener datos del dashboard
$estadisticas = $equipoModel->obtenerEstadisticas($equipo['id']);
$historialMantenimientos = $equipoModel->obtenerHistorialMantenimientos($equipo['id']);
$historialMovimientos = $equipoModel->obtenerHistorialMovimientos($equipo['id']);
$planAnual = $equipoModel->obtenerPlanMantenimientoAnual($equipo['id']);

// Calcular fechas de mantenimiento preventivo para el año
$fechasPreventivo = [];
if (!empty($historialMantenimientos)) {
    $ultimoPreventivo = null;
    foreach ($historialMantenimientos as $mant) {
        if ($mant['tipo'] == 'preventivo') {
            $ultimoPreventivo = $mant;
            break;
        }
    }

    if ($ultimoPreventivo) {
        $fechaBase = new DateTime($ultimoPreventivo['fecha_finalizacion']);
    } else {
        $fechaBase = new DateTime($equipo['fecha_compra'] ?? 'now');
    }

    $anioActual = date('Y');
    for ($mes = 1; $mes <= 12; $mes++) {
        $fechaRecomendada = clone $fechaBase;
        $fechaRecomendada->modify('+' . ($equipo['frecuencia_mantenimiento_meses'] * $mes) . ' months');

        if ($fechaRecomendada->format('Y') == $anioActual) {
            $fechasPreventivo[] = $fechaRecomendada->format('Y-m-d');
        }
    }
}

// Obtener mantenimientos en curso
global $db;
$mantenimientosEnCurso = $db->fetchAll(
    "SELECT mp.*, mm.id as mantenimiento_id
     FROM mtto_equipos_mantenimientos_programados mp
     LEFT JOIN mtto_equipos_mantenimientos mm ON mp.id = mm.mantenimiento_programado_id
     WHERE mp.equipo_id = ? AND mp.estado = 'agendado'
     ORDER BY mp.fecha_programada ASC",
    [$equipo['id']]
);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($equipo['codigo']) ?></title>
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/equipos_general.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: clamp(24px, 4vw, 36px) !important;
            font-weight: bold;
            color: #0E544C;
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: clamp(12px, 2vw, 14px) !important;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #333;
        }

        .info-value {
            color: #666;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #ddd;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #51B8AC;
            border: 3px solid white;
        }

        .timeline-date {
            font-weight: 600;
            color: #0E544C;
            margin-bottom: 5px;
        }

        .plan-preventivo {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .fecha-preventivo {
            padding: 8px 15px;
            background: #e8f5f3;
            border: 2px solid #51B8AC;
            border-radius: 6px;
            font-weight: 600;
            color: #0E544C;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Dashboard del Equipo'); ?>
            <div class="page-header">
                <div>
                    <h1 class="page-title">📊 Dashboard del Equipo</h1>
                    <p style="color: #666; margin-top: 5px;">Código:
                        <strong><?= htmlspecialchars($equipo['codigo']) ?></strong>
                    </p>
                </div>
                <a href="equipos_lista.php" class="btn btn-secondary">← Volver</a>
            </div>

            <!-- Información General -->
            <div class="info-grid">
                <div class="info-card">
                    <h3 style="margin-bottom: 15px; color: #0E544C;">📝 Información General</h3>
                    <div class="info-row">
                        <span class="info-label">Tipo:</span>
                        <span class="info-value"><?= htmlspecialchars($equipo['tipo_nombre']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Marca:</span>
                        <span class="info-value"><?= htmlspecialchars($equipo['marca']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Modelo:</span>
                        <span class="info-value"><?= htmlspecialchars($equipo['modelo']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Serial:</span>
                        <span class="info-value"><?= htmlspecialchars($equipo['serial']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ubicación Actual:</span>
                        <span
                            class="info-value badge badge-info"><?= htmlspecialchars($equipo['ubicacion_actual'] ?? 'Sin ubicación') ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <h3 style="margin-bottom: 15px; color: #0E544C;">🔧 Información de Compra</h3>
                    <div class="info-row">
                        <span class="info-label">Fecha Compra:</span>
                        <span
                            class="info-value"><?= $equipo['fecha_compra'] ? date('d/m/Y', strtotime($equipo['fecha_compra'])) : '-' ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Proveedor:</span>
                        <span class="info-value"><?= htmlspecialchars($equipo['proveedor_nombre'] ?? '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Garantía:</span>
                        <span class="info-value"><?= $equipo['garantia_meses'] ?> meses</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Frecuencia Mtto:</span>
                        <span class="info-value">Cada <?= $equipo['frecuencia_mantenimiento_meses'] ?> meses</span>
                    </div>
                </div>
            </div>

            <!-- Estadísticas -->
            <h3 style="margin-bottom: 15px; color: #0E544C;">📈 Estadísticas</h3>
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Mantenimientos</div>
                    <div class="stat-value"><?= $estadisticas['total_mantenimientos'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Preventivos</div>
                    <div class="stat-value" style="color: #28a745;"><?= $estadisticas['mantenimientos_preventivos'] ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Correctivos</div>
                    <div class="stat-value" style="color: #ffc107;"><?= $estadisticas['mantenimientos_correctivos'] ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Costo Total Repuestos</div>
                    <div class="stat-value" style="color: #dc3545;">C$
                        <?= number_format($estadisticas['costo_total_repuestos'], 2) ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Días Fuera de Servicio</div>
                    <div class="stat-value"><?= $estadisticas['dias_fuera_servicio'] ?></div>
                </div>
            </div>

            <!-- Plan de Mantenimiento Anual -->
            <div class="info-card" style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px; color: #0E544C;">📅 Plan de Mantenimiento Preventivo <?= date('Y') ?>
                </h3>
                <div class="plan-preventivo">
                    <?php if (!empty($fechasPreventivo)): ?>
                        <?php foreach ($fechasPreventivo as $fecha): ?>
                            <div class="fecha-preventivo">
                                <?= date('F Y', strtotime($fecha)) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #666;">No hay plan de mantenimiento definido para este año</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mantenimientos en Curso -->
            <?php if (!empty($mantenimientosEnCurso)): ?>
                <div class="info-card" style="margin-bottom: 30px;">
                    <h3 style="margin-bottom: 15px; color: #0E544C;">⚙️ Mantenimientos en Curso</h3>
                    <?php foreach ($mantenimientosEnCurso as $mant): ?>
                        <div class="alert alert-warning">
                            <strong>Tipo:</strong> <?= ucfirst($mant['tipo']) ?> |
                            <strong>Programado:</strong> <?= date('d/m/Y', strtotime($mant['fecha_programada'])) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Historial de Mantenimientos -->
            <div class="info-card" style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px; color: #0E544C;">🔧 Historial de Mantenimientos</h3>
                <?php if (!empty($historialMantenimientos)): ?>
                    <div class="timeline">
                        <?php foreach ($historialMantenimientos as $mant): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <?= date('d/m/Y', strtotime($mant['fecha_inicio'])) ?>
                                    <?php if ($mant['fecha_finalizacion']): ?>
                                        - <?= date('d/m/Y', strtotime($mant['fecha_finalizacion'])) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span
                                        class="badge <?= $mant['tipo'] == 'preventivo' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($mant['tipo']) ?>
                                    </span>
                                </div>
                                <div style="margin-top: 10px;">
                                    <strong>Trabajo Realizado:</strong> <?= htmlspecialchars($mant['trabajo_realizado']) ?>
                                </div>
                                <?php if ($mant['proveedor_nombre']): ?>
                                    <div style="margin-top: 5px; color: #666;">
                                        <strong>Proveedor:</strong> <?= htmlspecialchars($mant['proveedor_nombre']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($mant['costo_total_repuestos'] > 0): ?>
                                    <div style="margin-top: 5px; color: #dc3545; font-weight: 600;">
                                        Costo: C$ <?= number_format($mant['costo_total_repuestos'], 2) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #666;">No hay mantenimientos registrados</p>
                <?php endif; ?>
            </div>

            <!-- Historial de Movimientos -->
            <div class="info-card">
                <h3 style="margin-bottom: 15px; color: #0E544C;">🚚 Historial de Movimientos</h3>
                <?php if (!empty($historialMovimientos)): ?>
                    <div class="timeline">
                        <?php foreach ($historialMovimientos as $mov): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <?= date('d/m/Y', strtotime($mov['fecha_programada'])) ?>
                                    <?php if ($mov['fecha_realizada']): ?>
                                        (Realizado: <?= date('d/m/Y', strtotime($mov['fecha_realizada'])) ?>)
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span
                                        class="badge <?= $mov['estado'] == 'finalizado' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($mov['estado']) ?>
                                    </span>
                                </div>
                                <div style="margin-top: 10px;">
                                    <strong>Origen:</strong> <?= htmlspecialchars($mov['sucursal_origen']) ?> →
                                    <strong>Destino:</strong> <?= htmlspecialchars($mov['sucursal_destino']) ?>
                                </div>
                                <?php if ($mov['observaciones']): ?>
                                    <div style="margin-top: 5px; color: #666;">
                                        <?= htmlspecialchars($mov['observaciones']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #666;">No hay movimientos registrados</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/equipos_funciones.js"></script>
</body></html>