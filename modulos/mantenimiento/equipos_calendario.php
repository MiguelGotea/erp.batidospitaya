<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once __DIR__ . '/config/database.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Solo líder de infraestructura
if ($cargoOperario != 35) {
    header('Location: equipos_lista.php');
    exit;
}

// Obtener mes y año actual o del parámetro
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');

// Validar mes y año
if ($mes < 1) { $mes = 12; $anio--; }
if ($mes > 12) { $mes = 1; $anio++; }

$primerDia = new DateTime("$anio-$mes-01");
$ultimoDia = clone $primerDia;
$ultimoDia->modify('last day of this month');

// Equipos con preventivo recomendado este mes o vencidos
$equiposPreventivo = $db->fetchAll("
    SELECT 
        e.id, e.codigo, e.marca, e.modelo,
        (SELECT s.nombre 
         FROM mtto_equipos_movimientos m 
         INNER JOIN sucursales s ON m.sucursal_destino_id = s.codigo 
         WHERE m.equipo_id = e.id AND m.estado = 'finalizado' 
         ORDER BY m.fecha_realizada DESC LIMIT 1) as ubicacion_actual,
        CASE 
            WHEN (SELECT MAX(mm.fecha_finalizacion) FROM mtto_equipos_mantenimientos mm WHERE mm.equipo_id = e.id) IS NOT NULL
            THEN DATE_ADD((SELECT MAX(mm.fecha_finalizacion) FROM mtto_equipos_mantenimientos mm WHERE mm.equipo_id = e.id), INTERVAL e.frecuencia_mantenimiento_meses MONTH)
            ELSE DATE_ADD(e.fecha_compra, INTERVAL e.frecuencia_mantenimiento_meses MONTH)
        END as proxima_fecha,
        CASE 
            WHEN MONTH(CASE 
                WHEN (SELECT MAX(mm.fecha_finalizacion) FROM mtto_equipos_mantenimientos mm WHERE mm.equipo_id = e.id) IS NOT NULL
                THEN DATE_ADD((SELECT MAX(mm.fecha_finalizacion) FROM mtto_equipos_mantenimientos mm WHERE mm.equipo_id = e.id), INTERVAL e.frecuencia_mantenimiento_meses MONTH)
                ELSE DATE_ADD(e.fecha_compra, INTERVAL e.frecuencia_mantenimiento_meses MONTH)
            END) = ? AND YEAR(CASE 
                WHEN (SELECT MAX(mm.fecha_finalizacion) FROM mtto_equipos_mantenimientos mm WHERE mm.equipo_id = e.id) IS NOT NULL
                THEN DATE_ADD((SELECT MAX(mm.fecha_finalizacion) FROM mtto_equipos_mantenimientos mm WHERE mm.equipo_id = e.id), INTERVAL e.frecuencia_mantenimiento_meses MONTH)
                ELSE DATE_ADD(e.fecha_compra, INTERVAL e.frecuencia_mantenimiento_meses MONTH)
            END) = ? THEN 'verde'
            WHEN CASE 
                WHEN (SELECT MAX(mm.fecha_finalizacion) FROM mtto_equipos_mantenimientos mm WHERE mm.equipo_id = e.id) IS NOT NULL
                THEN DATE_ADD((SELECT MAX(mm.fecha_finalizacion) FROM mtto_equipos_mantenimientos mm WHERE mm.equipo_id = e.id), INTERVAL e.frecuencia_mantenimiento_meses MONTH)
                ELSE DATE_ADD(e.fecha_compra, INTERVAL e.frecuencia_mantenimiento_meses MONTH)
            END < CURDATE() THEN 'rojo'
            ELSE NULL
        END as indicador
    FROM mtto_equipos e
    WHERE e.activo = 1
    AND NOT EXISTS (
        SELECT 1 FROM mtto_equipos_mantenimientos_programados mmpp 
        WHERE mmpp.equipo_id = e.id AND mmpp.estado = 'agendado'
    )
    HAVING indicador IS NOT NULL
", [$mes, $anio]);

// Separar en verdes y rojos
$equiposPreventivo = array_filter($equiposPreventivo, function($eq) {
    return $eq['indicador'] !== null;
});

// Equipos con solicitud pendiente sin movimiento agendado
$equiposSolicitud = $db->fetchAll("
    SELECT 
        e.id, e.codigo, e.marca, e.modelo,
        s.id as solicitud_id,
        (SELECT su.nombre 
         FROM mtto_equipos_movimientos m 
         INNER JOIN sucursales su ON m.sucursal_destino_id = su.codigo 
         WHERE m.equipo_id = e.id AND m.estado = 'finalizado' 
         ORDER BY m.fecha_realizada DESC LIMIT 1) as ubicacion_actual
    FROM mtto_equipos e
    INNER JOIN mtto_equipos_solicitudes s ON e.id = s.equipo_id
    AND NOT EXISTS (
        SELECT 1 FROM mtto_equipos_mantenimientos mm 
        WHERE mm.solicitud_id = s.id
    )
    AND NOT EXISTS (
        SELECT 1 FROM mtto_equipos_mantenimientos_programados mmpp 
        WHERE mmpp.equipo_id = e.id AND mmpp.estado = 'agendado'
    )
");

// Mantenimientos programados del mes
$mantenimientosProgramados = $db->fetchAll("
    SELECT 
        mp.*, e.codigo, e.marca, e.modelo,
        m.id as mantenimiento_realizado_id,
        (SELECT s.nombre 
         FROM mtto_equipos_movimientos mov 
         INNER JOIN sucursales s ON mov.sucursal_destino_id = s.codigo 
         WHERE mov.equipo_id = mp.equipo_id AND mov.estado = 'finalizado' 
         ORDER BY mov.fecha_realizada DESC LIMIT 1) as ubicacion_actual
    FROM mtto_equipos_mantenimientos_programados mp
    INNER JOIN mtto_equipos e ON mp.equipo_id = e.id
    LEFT JOIN mtto_equipos_mantenimientos m ON mp.id = m.mantenimiento_programado_id
    WHERE MONTH(mp.fecha_programada) = ? AND YEAR(mp.fecha_programada) = ?
    ORDER BY mp.fecha_programada, mp.id
", [$mes, $anio]);

// Organizar mantenimientos por fecha
$mantenimientosPorFecha = [];
foreach ($mantenimientosProgramados as $mant) {
    $fecha = $mant['fecha_programada'];
    if (!isset($mantenimientosPorFecha[$fecha])) {
        $mantenimientosPorFecha[$fecha] = [];
    }
    $mantenimientosPorFecha[$fecha][] = $mant;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Mantenimientos</title>
    <link rel="stylesheet" href="css/equipos_general.css">
    <style>
        .calendario-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            height: calc(100vh - 150px);
        }

        .sidebar {
            background: white;
            border-radius: 8px;
            padding: 15px;
            overflow-y: auto;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .sidebar-section {
            margin-bottom: 25px;
        }

        .sidebar-title {
            font-weight: bold;
            color: #0E544C;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #0E544C;
        }

        .equipo-card {
            background: #f9f9f9;
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: grab;
            transition: all 0.2s;
        }

        .equipo-card:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .equipo-card.dragging {
            opacity: 0.5;
            cursor: grabbing;
        }

        .equipo-card.preventivo { border-color: #28a745; }
        .equipo-card.vencido { border-color: #dc3545; }
        .equipo-card.solicitud { border-color: #ffc107; }

        .equipo-codigo {
            font-weight: bold;
            color: #0E544C;
            margin-bottom: 3px;
        }

        .equipo-info {
            font-size: clamp(10px, 1.5vw, 12px) !important;
            color: #666;
        }

        .equipo-ubicacion {
            margin-top: 5px;
            padding: 3px 6px;
            background: #e8f5f3;
            border-radius: 3px;
            font-size: clamp(9px, 1.3vw, 11px) !important;
            display: inline-block;
        }

        .calendario {
            background: white;
            border-radius: 8px;
            padding: 20px;
            overflow-y: auto;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .calendario-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .mes-actual {
            font-size: clamp(16px, 3vw, 24px) !important;
            font-weight: bold;
            color: #0E544C;
        }

        .nav-mes {
            display: flex;
            gap: 10px;
        }

        .calendario-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        .dia-header {
            text-align: center;
            font-weight: bold;
            color: #0E544C;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 4px;
        }

        .dia-cell {
            min-height: 120px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            padding: 8px;
            background: white;
            position: relative;
        }

        .dia-cell.drop-zone {
            background: #e8f5f3;
            border-color: #51B8AC;
        }

        .dia-cell.otro-mes {
            background: #f9f9f9;
            opacity: 0.5;
        }

        .dia-numero {
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        .dia-hoy {
            background: #fff3cd;
            border-color: #ffc107;
        }

        .mantenimiento-card {
            background: #51B8AC;
            color: white;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 5px;
            font-size: clamp(10px, 1.5vw, 12px) !important;
            cursor: move;
            position: relative;
        }

        .mantenimiento-card.finalizado {
            background: #6c757d;
            opacity: 0.7;
        }

        .mantenimiento-remove {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 3px;
            width: 18px;
            height: 18px;
            cursor: pointer;
            font-size: 12px !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mantenimiento-check {
            margin-top: 5px;
            width: 100%;
            padding: 3px;
            font-size: clamp(9px, 1.3vw, 11px) !important;
        }

        .search-container {
            margin-bottom: 15px;
        }

        .search-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container-main">
        <div class="page-header">
            <h1 class="page-title">📅 Calendario de Mantenimientos</h1>
            <a href="equipos_lista.php" class="btn btn-secondary">← Volver</a>
        </div>

        <div class="calendario-container">
            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Equipos con preventivo este mes -->
                <div class="sidebar-section">
                    <div class="sidebar-title">Mantenimiento Preventivo</div>
                    <?php 
                    $tieneEquipos = false;
                    foreach ($equiposPreventivo as $eq): 
                        $tieneEquipos = true;
                        $indicador = $eq['indicador'] == 'verde' ? '🟢' : '🔴';
                        $claseBorde = $eq['indicador'] == 'verde' ? 'preventivo' : 'vencido';
                        $mensaje = $eq['indicador'] == 'verde' ? 'Le toca este mes' : 'Vencido - Requiere atención';
                    ?>
                    <div class="equipo-card <?= $claseBorde ?>" draggable="true" 
                         data-equipo-id="<?= $eq['id'] ?>" 
                         data-tipo="preventivo"
                         title="<?= $mensaje ?>">
                        <div class="equipo-codigo"><?= $indicador ?> <?= htmlspecialchars($eq['codigo']) ?></div>
                        <div class="equipo-info"><?= htmlspecialchars($eq['marca'] . ' ' . $eq['modelo']) ?></div>
                        <div class="equipo-info" style="font-size: 10px; color: #999;">
                            <?= $mensaje ?>
                        </div>
                        <div class="equipo-ubicacion">📍 <?= htmlspecialchars($eq['ubicacion_actual'] ?? 'Sin ubicación') ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$tieneEquipos): ?>
                        <p style="color: #666; font-size: 12px; padding: 10px;">No hay equipos pendientes</p>
                    <?php endif; ?>
                </div>

                <!-- Equipos con solicitud -->
                <?php if (!empty($equiposSolicitud)): ?>
                <div class="sidebar-section">
                    <div class="sidebar-title">⚠️ Con Solicitud Pendiente</div>
                    <?php foreach ($equiposSolicitud as $eq): ?>
                    <div class="equipo-card solicitud" draggable="true" 
                         data-equipo-id="<?= $eq['id'] ?>" 
                         data-solicitud-id="<?= $eq['solicitud_id'] ?>"
                         data-tipo="correctivo">
                        <div class="equipo-codigo"><?= htmlspecialchars($eq['codigo']) ?></div>
                        <div class="equipo-info"><?= htmlspecialchars($eq['marca'] . ' ' . $eq['modelo']) ?></div>
                        <div class="equipo-ubicacion">📍 <?= htmlspecialchars($eq['ubicacion_actual'] ?? 'Sin ubicación') ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Buscador general -->
                <div class="sidebar-section">
                    <div class="sidebar-title">🔍 Buscar Equipo</div>
                    <div class="search-container">
                        <input type="text" class="search-input" id="buscar-equipo" 
                               placeholder="Buscar por código..." onkeyup="buscarEquipo()">
                    </div>
                    <div id="resultados-busqueda"></div>
                </div>
            </div>

            <!-- Calendario -->
            <div class="calendario">
                <div class="calendario-header">
                    <div class="mes-actual">
                        <?= ucfirst(strftime('%B %Y', $primerDia->getTimestamp())) ?>
                    </div>
                    <div class="nav-mes">
                        <a href="?mes=<?= $mes-1 ?>&anio=<?= $anio ?>" class="btn btn-secondary">← Anterior</a>
                        <a href="?mes=<?= date('n') ?>&anio=<?= date('Y') ?>" class="btn btn-primary">Hoy</a>
                        <a href="?mes=<?= $mes+1 ?>&anio=<?= $anio ?>" class="btn btn-secondary">Siguiente →</a>
                    </div>
                </div>

                <div class="calendario-grid">
                    <!-- Headers días -->
                    <div class="dia-header">Dom</div>
                    <div class="dia-header">Lun</div>
                    <div class="dia-header">Mar</div>
                    <div class="dia-header">Mié</div>
                    <div class="dia-header">Jue</div>
                    <div class="dia-header">Vie</div>
                    <div class="dia-header">Sáb</div>

                    <?php
                    // Días del mes
                    $diaInicio = $primerDia->format('w'); // 0=domingo
                    $diasMes = $ultimoDia->format('j');
                    $hoy = date('Y-m-d');
                    
                    // Días del mes anterior
                    $mesAnterior = clone $primerDia;
                    $mesAnterior->modify('-1 day');
                    $diasMesAnterior = $mesAnterior->format('j');
                    
                    for ($i = $diaInicio - 1; $i >= 0; $i--) {
                        $dia = $diasMesAnterior - $i;
                        echo "<div class='dia-cell otro-mes'><div class='dia-numero'>$dia</div></div>";
                    }
                    
                    // Días del mes actual
                    for ($dia = 1; $dia <= $diasMes; $dia++) {
                        $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
                        $esHoy = $fecha == $hoy ? 'dia-hoy' : '';
                        ?>
                        <div class="dia-cell <?= $esHoy ?>" data-fecha="<?= $fecha ?>">
                            <div class="dia-numero"><?= $dia ?></div>
                            <?php if (isset($mantenimientosPorFecha[$fecha])): ?>
                                <?php foreach ($mantenimientosPorFecha[$fecha] as $mant): ?>
                                <div class="mantenimiento-card <?= $mant['mantenimiento_realizado_id'] ? 'finalizado' : '' ?>" 
                                     draggable="<?= $mant['mantenimiento_realizado_id'] ? 'false' : 'true' ?>"
                                     data-programado-id="<?= $mant['id'] ?>">
                                    <div class="mantenimiento-header">
                                        <span class="mantenimiento-codigo"><?= htmlspecialchars($mant['codigo']) ?></span>
                                        <?php if (!$mant['mantenimiento_realizado_id']): ?>
                                        <button class="mantenimiento-remove" onclick="desprogramar(<?= $mant['id'] ?>)" title="Desprogramar">×</button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$mant['mantenimiento_realizado_id']): ?>
                                    <button class="mantenimiento-check" onclick="abrirReporte(<?= $mant['id'] ?>, <?= $mant['equipo_id'] ?>)" title="Completar">✓</button>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/equipos_funciones.js"></script>
    <script src="js/equipos_calendario.js"></script>
</body>
</html>