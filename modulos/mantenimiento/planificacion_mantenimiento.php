<?php
// planificacion_mantenimiento.php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Ticket.php';
require_once __DIR__ . '/../../core/auth/auth.php';
require_once __DIR__ . '/../../core/layout/menu_lateral.php';
require_once __DIR__ . '/../../core/layout/header_universal.php';
require_once __DIR__ . '/../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo usando sistema de permisos (como pidió el usuario)
if (!tienePermiso('planificacion_mantenimiento', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$ticketModel = new Ticket();
$tickets = $ticketModel->getTicketsForPlanning();


// Configuración de la jornada
$horas_jornada = 10;
$dias_plan = 6;

// ALGORITMO DE AGENDAMIENTO DINÁMICO (6 Días / 10 Horas)
$agenda_semanal = [];
$pool_tickets = $tickets;
$capacidad_diaria = 10;
$dias_nombres = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

for ($d = 0; $d < 6; $d++) {
    $dia_nombre = $dias_nombres[$d];
    $agenda_semanal[$dia_nombre] = [
        'visitas' => [],
        'tiempo_total' => 0,
        'tiempo_transporte' => 0,
        'tiempo_ejecucion' => 0
    ];

    $tiempo_restante = $capacidad_diaria;
    $sucursales_visitadas_hoy = [];

    // Intentar llenar el día mientras haya tiempo y tickets
    while ($tiempo_restante > 0.5 && !empty($pool_tickets)) {
        $scores_sucursales = [];
        $tickets_por_sucursal = [];

        foreach ($pool_tickets as $t) {
            $cod = $t['cod_sucursal'];
            if (!isset($tickets_por_sucursal[$cod]))
                $tickets_por_sucursal[$cod] = [];
            $tickets_por_sucursal[$cod][] = $t;
        }

        foreach ($tickets_por_sucursal as $cod => $s_tickets) {
            $is_regional = ($s_tickets[0]['departamento_sucursal'] !== 'Managua');
            $costo_viaje = (in_array($cod, $sucursales_visitadas_hoy)) ? 0 : ($is_regional ? 6 : 0);

            if ($tiempo_restante < ($costo_viaje + 0.5))
                continue;

            $score_sucursal = 0;
            $tiempo_util = $tiempo_restante - $costo_viaje;
            $tickets_seleccionados = [];
            $acum_h = 0;

            foreach ($s_tickets as $st) {
                if ($acum_h + $st['tiempo_exec'] <= $tiempo_util) {
                    // El score favorece fuertemente la urgencia alta
                    $score_sucursal += pow($st['urgencia'], 3) * 10;
                    $acum_h += $st['tiempo_exec'];
                    $tickets_seleccionados[] = $st;
                } else {
                    break;
                }
            }

            if ($score_sucursal > 0) {
                $scores_sucursales[] = [
                    'cod' => $cod,
                    'nombre' => $s_tickets[0]['nombre_sucursal'],
                    'departamento' => $s_tickets[0]['departamento_sucursal'],
                    'score' => $score_sucursal,
                    'viaje' => $costo_viaje,
                    'tickets' => $tickets_seleccionados,
                    'horas_exec' => $acum_h
                ];
            }
        }

        if (empty($scores_sucursales))
            break;

        // Elegir la sucursal con el mejor impacto para este momento del día
        usort($scores_sucursales, fn($a, $b) => $b['score'] <=> $a['score']);
        $mejor_opcion = $scores_sucursales[0];

        // Registrar visita en la agenda
        $agenda_semanal[$dia_nombre]['visitas'][] = $mejor_opcion;
        $agenda_semanal[$dia_nombre]['tiempo_ejecucion'] += $mejor_opcion['horas_exec'];
        $agenda_semanal[$dia_nombre]['tiempo_transporte'] += $mejor_opcion['viaje'];
        $agenda_semanal[$dia_nombre]['tiempo_total'] += ($mejor_opcion['horas_exec'] + $mejor_opcion['viaje']);

        // Quitar tickets del pool
        $ids_remover = array_column($mejor_opcion['tickets'], 'id');
        $pool_tickets = array_filter($pool_tickets, fn($pt) => !in_array($pt['id'], $ids_remover));

        $sucursales_visitadas_hoy[] = $mejor_opcion['cod'];
        $tiempo_restante -= ($mejor_opcion['horas_exec'] + $mejor_opcion['viaje']);
    }
}

// Función para obtener color de urgencia (consistente con el módulo)
function getColorUrgencia($nivel)
{
    switch ($nivel) {
        case 1:
            return '#28a745'; // Verde
        case 2:
            return '#ffc107'; // Amarillo
        case 3:
            return '#fd7e14'; // Naranja
        case 4:
            return '#dc3545'; // Rojo
        default:
            return '#adb5bd'; // Gris
    }
}

// Métricas de Consolidación
$agendados_count = 0;
$total_h_exec = 0;
$total_h_viaje = 0;
foreach ($agenda_semanal as $d) {
    $total_h_exec += $d['tiempo_ejecucion'];
    $total_h_viaje += $d['tiempo_transporte'];
    foreach ($d['visitas'] as $v) {
        $agendados_count += count($v['tickets']);
    }
}
$eficiencia = ($total_h_exec + $total_h_viaje) > 0 ? ($total_h_exec / ($total_h_exec + $total_h_viaje)) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planificación de Mantenimiento</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/planificacion_mantenimiento.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Planificación Semanal (10h/6d)'); ?>

            <div class="container-fluid p-4">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card stat-card h-100 shadow-sm border-0 border-start border-4 border-primary">
                            <div class="card-body">
                                <h5 class="card-title text-muted fw-bold small text-uppercase">Algoritmo de Priorización
                                </h5>
                                <p class="card-text">
                                    El sistema analiza los tickets con estado <span
                                        class="badge bg-secondary">solicitado</span> y <span
                                        class="badge bg-secondary">agendado</span>,
                                    calculando el tiempo de transporte según el departamento y priorizando por urgencia
                                    crítica.
                                </p>
                                <div class="d-flex gap-4 mt-3">
                                    <div class="stat-item">
                                        <span class="d-block text-muted small">Total Tickets</span>
                                        <span class="fs-4 fw-bold">
                                            <?php
                                            $agendados = 0;
                                            foreach ($agenda_semanal as $d)
                                                foreach ($d['visitas'] as $v)
                                                    $agendados += count($v['tickets']);
                                            echo count($pool_tickets) + $agendados;
                                            ?>
                                        </span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="d-block text-muted small">Agendados</span>
                                        <span class="fs-4 fw-bold text-success">
                                            <?php echo $agendados; ?>
                                        </span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="d-block text-muted small">Eficiencia de Ruta</span>
                                        <div class="d-flex align-items-center gap-2">
                                            <span
                                                class="fs-4 fw-bold text-primary"><?php echo number_format($eficiencia, 0); ?>%</span>
                                            <div class="progress flex-grow-1" style="height: 6px; min-width: 60px;">
                                                <div class="progress-bar bg-primary"
                                                    style="width: <?php echo $eficiencia; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="stat-item">
                                        <span class="d-block text-muted small">Sin Cupo</span>
                                        <span
                                            class="fs-4 fw-bold <?php echo count($pool_tickets) > 0 ? 'text-danger' : ''; ?>">
                                            <?php echo count($pool_tickets); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-dark text-white shadow-sm border-0 h-100">
                            <div class="card-body d-flex flex-column justify-content-center">
                                <h6 class="text-info fw-bold mb-3"><i
                                        class="bi bi-rocket-takeoff-fill me-2"></i>Sugerencia de Ruta</h6>
                                <p class="small mb-0 opacity-75">
                                    Se recomienda iniciar con las sucursales donde el <strong>Score de
                                        Prioridad</strong> es más alto, agrupando trabajos locales en Managua para
                                    liberar volumen rápidamente.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Lista de Tickets sin Agendar (Pool Restante) -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0 mb-4 h-100">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-stack me-2"></i>Tickets Descartados</h6>
                                <p class="small text-muted mb-0">No cupieron en la semana de 60h</p>
                            </div>
                            <div class="card-body p-0 overflow-auto" style="max-height: 600px;">
                                <?php if (empty($pool_tickets)): ?>
                                    <div class="p-4 text-center text-muted italic">
                                        <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                                        Todo agendado
                                    </div>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($pool_tickets as $pt): ?>
                                            <li class="list-group-item small border-start border-3"
                                                style="border-left-color: <?php echo getColorUrgencia($pt['urgencia']); ?> !important;">
                                                <div class="fw-bold"><?php echo htmlspecialchars($pt['nombre_sucursal']); ?>
                                                </div>
                                                <div class="text-muted d-flex justify-content-between">
                                                    <span>Urgencia: <?php echo $pt['urgencia']; ?></span>
                                                    <span><?php echo $pt['tiempo_exec']; ?>h</span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Vista de Agenda Dinámica (6 días) -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm border-0 bg-light-soft">
                            <div
                                class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 fw-bold text-primary">Agenda Semanal Optimizada</h6>
                                    <p class="small text-muted mb-0">Prioridad: 1. Urgencia, 2. Logística</p>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success">60h Capacidad</span>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <div class="row row-cols-1 row-cols-md-2 g-3">
                                    <?php foreach ($agenda_semanal as $dia => $data):
                                        $percent = ($data['tiempo_total'] / 10) * 100;
                                        $color_bar = $percent > 100 ? 'bg-danger' : ($percent > 85 ? 'bg-warning' : 'bg-primary');
                                        ?>
                                        <div class="col">
                                            <div class="planning-day-card h-100 border rounded-3 bg-white shadow-xs p-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span
                                                        class="fw-bold text-uppercase small text-muted"><?php echo $dia; ?></span>
                                                    <span
                                                        class="small fw-bold <?php echo $percent > 100 ? 'text-danger' : ''; ?>">
                                                        <?php echo number_format($data['tiempo_total'], 1); ?>/10h
                                                    </span>
                                                </div>

                                                <div class="progress mb-3" style="height: 4px;">
                                                    <div class="progress-bar <?php echo $color_bar; ?>"
                                                        style="width: <?php echo $percent; ?>%"></div>
                                                </div>

                                                <?php if (empty($data['visitas'])): ?>
                                                    <div class="text-center py-4 text-muted small opacity-50">Sin actividades
                                                    </div>
                                                <?php else: ?>
                                                    <div class="day-activities">
                                                        <?php foreach ($data['visitas'] as $v): ?>
                                                            <div
                                                                class="visit-item mb-2 p-2 rounded bg-light border-start border-3 <?php echo ($v['viaje'] > 0) ? 'border-danger' : 'border-info'; ?>">
                                                                <div class="d-flex justify-content-between">
                                                                    <div class="fw-bold small">
                                                                        <?php echo htmlspecialchars($v['nombre']); ?>
                                                                    </div>
                                                                    <span class="badge bg-white text-dark border-0 small px-0"
                                                                        style="font-size: 0.7rem;">
                                                                        <?php echo $v['horas_exec']; ?>h sitio +
                                                                        <?php echo $v['viaje']; ?>h viaje
                                                                    </span>
                                                                </div>
                                                                <div class="d-flex gap-1 mt-1">
                                                                    <?php foreach (array_slice($v['tickets'], 0, 5) as $tk): ?>
                                                                        <span class="dot-urgency"
                                                                            style="background-color: <?php echo getColorUrgencia($tk['urgencia']); ?>"
                                                                            title="Urgencia <?php echo $tk['urgencia']; ?>"></span>
                                                                    <?php endforeach; ?>
                                                                    <?php if (count($v['tickets']) > 5): ?>
                                                                        <span class="small text-muted"
                                                                            style="font-size: 0.6rem;">+<?php echo count($v['tickets']) - 5; ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>