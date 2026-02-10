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
$weekly_stats = $ticketModel->getWeeklyReportStats();
$equipment_stats = $ticketModel->getEquipmentChangeStats();

// Preparar datos para el gráfico de barras (12 semanas)
$labels_semanas = [];
$data_criticos = [];
$data_normales = [];

foreach (array_reverse($weekly_stats) as $ws) {
    $labels_semanas[] = $ws['numero_semana'];
    $data_criticos[] = $ws['tickets_criticos'];
    $data_normales[] = $ws['tickets_normales'];
}

// Preparar datos para el gráfico de cambio de equipos (8 semanas)
$labels_equipos = [];
$data_equipos = [];

foreach (array_reverse($equipment_stats) as $es) {
    $labels_equipos[] = $es['numero_semana'];
    $data_equipos[] = $es['total_cambios'];
}


// Configuración del Algoritmo (Valores por defecto o personalizados)
$peso_u1 = isset($_POST['peso_u1']) ? floatval($_POST['peso_u1']) : 1;
$peso_u2 = isset($_POST['peso_u2']) ? floatval($_POST['peso_u2']) : 5;
$peso_u3 = isset($_POST['peso_u3']) ? floatval($_POST['peso_u3']) : 25;
$peso_u4 = isset($_POST['peso_u4']) ? floatval($_POST['peso_u4']) : 150;
$horas_jornada = isset($_POST['horas_jornada']) ? floatval($_POST['horas_jornada']) : 10;
$dias_plan = isset($_POST['dias_plan']) ? intval($_POST['dias_plan']) : 6;
$v_viaje_reg = isset($_POST['v_viaje_reg']) ? floatval($_POST['v_viaje_reg']) : 6;

// ALGORITMO DE AGENDAMIENTO DINÁMICO
$agenda_semanal = [];
$pool_tickets = $tickets;
$dias_nombres = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

for ($d = 0; $d < $dias_plan; $d++) {
    $dia_nombre = $dias_nombres[$d];
    $agenda_semanal[$dia_nombre] = [
        'visitas' => [],
        'tiempo_total' => 0,
        'tiempo_transporte' => 0,
        'tiempo_ejecucion' => 0
    ];

    $tiempo_restante = $horas_jornada;
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
            $costo_viaje = (in_array($cod, $sucursales_visitadas_hoy)) ? 0 : ($is_regional ? $v_viaje_reg : 0);

            if ($tiempo_restante < ($costo_viaje + 0.5))
                continue;

            $score_sucursal = 0;
            $tiempo_util = $tiempo_restante - $costo_viaje;
            $tickets_seleccionados = [];
            $acum_h = 0;

            foreach ($s_tickets as $st) {
                if ($acum_h + $st['tiempo_exec'] <= $tiempo_util) {
                    // El score favorece la urgencia según pesos configurados
                    $curr_weight = 1;
                    if ($st['urgencia'] == 2)
                        $curr_weight = $peso_u2;
                    if ($st['urgencia'] == 3)
                        $curr_weight = $peso_u3;
                    if ($st['urgencia'] == 4)
                        $curr_weight = $peso_u4;
                    if ($st['urgencia'] == 1)
                        $curr_weight = $peso_u1;

                    $score_sucursal += $curr_weight * 10;
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

// Solicitudes Críticas (Urgencia 4) - Todas para seguimiento
$solicitudes_criticas = array_filter($tickets, function ($t) {
    return $t['urgencia'] == 4;
});
// Debug: Total de críticos encontrados en DB: <?php echo count($solicitudes_criticas); ?>
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planificación de Mantenimiento</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/planificacion_mantenimiento.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Planificación Semanal'); ?>

            <div class="container-fluid p-4">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card stat-card h-100 shadow-sm border-0 border-start border-4 border-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="card-title text-muted fw-bold small text-uppercase mb-0">Rendimiento
                                        Operativo</h5>
                                    <i class="bi bi-speedometer2 text-primary"></i>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6 col-lg-3">
                                        <div
                                            class="stat-item bg-light p-3 rounded-3 h-100 border-bottom border-3 border-dark text-center">
                                            <span class="d-block text-muted small fw-bold">TOTAL</span>
                                            <span class="fs-2 fw-bold text-dark">
                                                <?php
                                                $agendados = 0;
                                                foreach ($agenda_semanal as $d)
                                                    foreach ($d['visitas'] as $v)
                                                        $agendados += count($v['tickets']);
                                                echo count($pool_tickets) + $agendados;
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div
                                            class="stat-item bg-light p-3 rounded-3 h-100 border-bottom border-3 border-success text-center">
                                            <span class="d-block text-muted small fw-bold">AGENDADOS</span>
                                            <span class="fs-2 fw-bold text-success">
                                                <?php echo $agendados; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div
                                            class="stat-item bg-light p-3 rounded-3 h-100 border-bottom border-3 border-danger text-center">
                                            <span class="d-block text-muted small fw-bold">SIN CUPO</span>
                                            <span class="fs-2 fw-bold text-danger">
                                                <?php echo count($pool_tickets); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div
                                            class="stat-item bg-light p-3 rounded-3 h-100 border-bottom border-3 border-primary text-center">
                                            <span class="d-block text-muted small fw-bold">EFICIENCIA</span>
                                            <div class="d-flex justify-content-center align-items-baseline gap-1">
                                                <span class="fs-2 fw-bold"
                                                    style="color: #0E544C;"><?php echo number_format($eficiencia, 0); ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-white shadow-sm border-0 h-100">
                            <form method="POST" action="">
                                <div class="card-header bg-primary bg-opacity-10 py-2">
                                    <h6 class="mb-0 fw-bold text-primary small"><i
                                            class="bi bi-gear-fill me-2"></i>Configuración del Algoritmo</h6>
                                </div>
                                <div class="card-body py-2">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="small text-muted mb-0">Horas/Día</label>
                                            <input type="number" step="0.5" name="horas_jornada"
                                                class="form-control form-control-sm"
                                                value="<?php echo $horas_jornada; ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted mb-0">Días/Semana</label>
                                            <select name="dias_plan" class="form-select form-select-sm">
                                                <?php for ($i = 1; $i <= 7; $i++): ?>
                                                    <option value="<?php echo $i; ?>" <?php echo ($dias_plan == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted mb-0">Peso U4 (Críticos)</label>
                                            <input type="number" name="peso_u4" class="form-control form-control-sm"
                                                value="<?php echo $peso_u4; ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted mb-0">Viaje Regional (h)</label>
                                            <input type="number" step="0.5" name="v_viaje_reg"
                                                class="form-control form-control-sm"
                                                value="<?php echo $v_viaje_reg; ?>">
                                        </div>
                                    </div>
                                    <div class="collapse mt-2" id="moreSettings">
                                        <div class="row g-2">
                                            <div class="col-4">
                                                <label class="small text-muted mb-0">Peso U3</label>
                                                <input type="number" name="peso_u3" class="form-control form-control-sm"
                                                    value="<?php echo $peso_u3; ?>">
                                            </div>
                                            <div class="col-4">
                                                <label class="small text-muted mb-0">Peso U2</label>
                                                <input type="number" name="peso_u2" class="form-control form-control-sm"
                                                    value="<?php echo $peso_u2; ?>">
                                            </div>
                                            <div class="col-4">
                                                <label class="small text-muted mb-0">Peso U1</label>
                                                <input type="number" name="peso_u1" class="form-control form-control-sm"
                                                    value="<?php echo $peso_u1; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <a href="javascript:void(0)" class="small text-decoration-none"
                                            data-bs-toggle="collapse" data-bs-target="#moreSettings">Ver Pesos</a>
                                        <button type="submit" class="btn btn-primary btn-sm px-3">
                                            <i class="bi bi-arrow-clockwise me-1"></i> Recalcular
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <!-- Gráficos de Reportes Semanales -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold" style="color: #0E544C;"><i
                                    class="bi bi-bar-chart-line-fill me-2"></i>Reportes por Semana</h6>
                        </div>
                        <div class="card-body" style="height: 350px;">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold text-warning"><i class="bi bi-tools me-2"></i>Cambios de Equipo
                            </h6>
                        </div>
                        <div class="card-body" style="height: 350px;">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Lista de Tickets sin Agendar (Pool Restante) -->
                <div class="col-lg-4">
                    <!-- Panel de Solicitudes Críticas (Prioridad 1) -->
                    <div class="card shadow-sm border-0 border-danger border-2 mb-3">
                        <div class="card-header bg-danger bg-opacity-10 py-3">
                            <h6 class="mb-0 fw-bold text-danger"><i
                                    class="bi bi-exclamation-triangle-fill me-2"></i>Solicitudes Críticas</h6>
                        </div>
                        <div class="card-body p-0 overflow-auto" style="max-height: 280px;">
                            <?php if (empty($solicitudes_criticas)): ?>
                                <div class="p-4 text-center text-muted italic">
                                    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                                    Sin solicitudes críticas
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($solicitudes_criticas as $sc):
                                        // Verificar si está en el pool (sin cupo) o fue agendado
                                        $agendado = true;
                                        foreach ($pool_tickets as $pt) {
                                            if ($pt['id'] == $sc['id']) {
                                                $agendado = false;
                                                break;
                                            }
                                        }
                                        ?>
                                        <li class="list-group-item small border-start border-3 border-danger p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div class="fw-bold text-danger">
                                                    <?php echo htmlspecialchars($sc['nombre_sucursal']); ?>
                                                    (<?php echo $sc['tiempo_exec']; ?>h)
                                                </div>
                                                <span
                                                    class="badge <?php echo $agendado ? 'bg-success' : 'bg-warning text-dark'; ?>"
                                                    style="font-size: 0.6rem;">
                                                    <?php echo $agendado ? 'AGENDADO' : 'SIN CUPO'; ?>
                                                </span>
                                            </div>
                                            <div class="fw-bold text-dark mb-1">
                                                <?php echo htmlspecialchars($sc['titulo']); ?>
                                            </div>
                                            <div class="text-muted mb-0"
                                                style="font-size: 0.75rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                <?php echo htmlspecialchars($sc['descripcion']); ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Lista de Tickets sin Agendar (Pool Restante) -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-stack me-2"></i>Resto Descartados</h6>
                        </div>
                        <div class="card-body p-0 overflow-auto" style="max-height: 280px;">
                            <?php
                            // Filtrar pool para no repetir los críticos que ya se muestran arriba
                            $pool_filtrado = array_filter($pool_tickets, function ($pt) {
                                return $pt['urgencia'] < 4;
                            });
                            ?>
                            <?php if (empty($pool_filtrado)): ?>
                                <div class="p-4 text-center text-muted italic">
                                    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                                    Todo agendado
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($pool_filtrado as $pt): ?>
                                        <li class="list-group-item small border-start border-3 p-3"
                                            style="border-left-color: <?php echo getColorUrgencia($pt['urgencia']); ?> !important;">
                                            <div class="fw-bold fs-7">
                                                <?php echo htmlspecialchars($pt['nombre_sucursal']); ?>
                                                (<?php echo $pt['tiempo_exec']; ?>h)
                                            </div>
                                            <div class="text-dark small opacity-75">
                                                <?php echo htmlspecialchars($pt['titulo']); ?>
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
                        <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold" style="color: #0E544C;">Agenda Semanal Optimizada</h6>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success"><?php echo $horas_jornada * $dias_plan; ?>h
                                    Capacidad</span>
                            </div>
                        </div>
                        <div class="card-body pt-0 px-1 overflow-hidden">
                            <div class="row g-2 m-0">
                                <?php foreach ($agenda_semanal as $dia => $data):
                                    $percent = ($data['tiempo_total'] / $horas_jornada) * 100;
                                    $color_bar = $percent > 100 ? 'bg-danger' : ($percent > 85 ? 'bg-warning' : 'bg-primary');
                                    ?>
                                    <div class="col">
                                        <div class="planning-day-card h-100 border rounded-3 bg-white shadow-xs p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span
                                                    class="fw-bold text-uppercase small text-muted"><?php echo $dia; ?></span>
                                                <span
                                                    class="small fw-bold <?php echo $percent > 100 ? 'text-danger' : ''; ?>">
                                                    <?php echo number_format($data['tiempo_total'], 1); ?>/<?php echo $horas_jornada; ?>h
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

    <!-- Modal de Ayuda: Guía del Algoritmo -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-info text-white border-0 py-3">
                    <h5 class="modal-title fw-bold" id="helpModalLabel">
                        <i class="bi bi-info-circle-fill me-2"></i>Guía de Planificación Inteligente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <section class="mb-4">
                        <h6 class="fw-bold text-info"><i class="bi bi-cpu-fill me-2"></i>¿Cómo funciona el cerebro del
                            sistema?</h6>
                        <p class="text-muted small mb-2">
                            El sistema analiza los tickets con estado <span class="badge bg-secondary">solicitado</span>
                            y <span class="badge bg-secondary">agendado</span>, calculando el tiempo de transporte según
                            el departamento y priorizando por urgencia crítica.
                        </p>
                        <p class="text-muted small">
                            El algoritmo utiliza una técnica de <strong>Optimización Heurística</strong>. No solo busca
                            atender lo más urgente, sino hacerlo de la manera más eficiente logísticamente.
                        </p>
                    </section>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h7 class="fw-bold d-block mb-2 text-dark">1. Score de Prioridad</h7>
                                <p class="small text-muted mb-0"> Cada sucursal recibe un puntaje basado en sus tickets.
                                    Los tickets <strong>Críticos (U4)</strong> tienen un "peso" exponencialmente mayor.
                                    Si aumentas el peso de U4, el sistema moverá cielo y tierra para agendarlos primero.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h7 class="fw-bold d-block mb-2 text-dark">2. Agrupamiento Logístico</h7>
                                <p class="small text-muted mb-0">El sistema prefiere visitar una sucursal y resolver
                                    <strong>todos sus tickets pendientes</strong> de una vez para "ahorrar" el tiempo de
                                    transporte, en lugar de saltar entre múltiples ubicaciones.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h7 class="fw-bold d-block mb-2 text-dark">3. Gestión de Tiempos</h7>
                                <p class="small text-muted mb-0">Cada día tiene un límite (ej. 10h). El sistema
                                    descuenta el viaje regional (6h ida/vuelta) de la jornada. Si un ticket requiere más
                                    tiempo del sobrante, se descarta para ese día.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h7 class="fw-bold d-block mb-2 text-dark">4. Pool de Descartes</h7>
                                <p class="small text-muted mb-0">Los tickets que ves como "Descartados" son aquellos
                                    que, por su urgencia o tiempo, no pudieron ser encajados en la ventana de 60h
                                    semanales bajo las reglas actuales.</p>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-4 mb-0 border-0 shadow-sm">
                        <i class="bi bi-lightbulb-fill me-2 text-warning"></i>
                        <strong>Tip:</strong> Puedes "forzar" la agenda reduciendo el tiempo de viaje regional o
                        aumentando las horas laborables en el panel de configuración.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-info text-white px-4 fw-bold"
                        data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Gráfico de barras apiladas con línea de tendencia de críticos
        const ctx = document.getElementById('weeklyChart').getContext('2d');
        const criticosData = <?php echo json_encode($data_criticos); ?>.map(Number);

        console.log('Datos críticos:', criticosData);

        // Calcular línea de tendencia para críticos (regresión lineal)
        const n = criticosData.length;
        const sumX = criticosData.reduce((sum, _, i) => sum + i, 0);
        const sumY = criticosData.reduce((sum, val) => sum + val, 0);
        const sumXY = criticosData.reduce((sum, val, i) => sum + (i * val), 0);
        const sumX2 = criticosData.reduce((sum, _, i) => sum + (i * i), 0);

        const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
        const intercept = (sumY - slope * sumX) / n;
        const trendLineCriticos = criticosData.map((_, i) => slope * i + intercept);

        console.log('Línea de tendencia:', trendLineCriticos);

        const weeklyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels_semanas); ?>,
                datasets: [
                    {
                        label: 'Críticos',
                        data: <?php echo json_encode($data_criticos); ?>,
                        backgroundColor: '#dc3545',
                        borderRadius: 4,
                        order: 2
                    },
                    {
                        label: 'Normales',
                        data: <?php echo json_encode($data_normales); ?>,
                        backgroundColor: '#0E544C',
                        borderRadius: 4,
                        order: 2
                    },
                    {
                        label: 'Tendencia Críticos',
                        data: trendLineCriticos,
                        type: 'line',
                        borderColor: '#28a745',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        order: 1,
                        displayInLegend: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { color: '#f0f0f0' },
                        ticks: { precision: 0 }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 10,
                            filter: function (item, chart) {
                                return !item.text.includes('Tendencia');
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });

        // Gráfico de cambios de equipo con tendencia
        const ctxEquip = document.getElementById('trendChart').getContext('2d');
        const equipData = <?php echo json_encode($data_equipos); ?>;

        // Calcular línea de tendencia para equipos
        const nEquip = equipData.length;
        const sumXEquip = equipData.reduce((sum, _, i) => sum + i, 0);
        const sumYEquip = equipData.reduce((sum, val) => sum + val, 0);
        const sumXYEquip = equipData.reduce((sum, val, i) => sum + (i * val), 0);
        const sumX2Equip = equipData.reduce((sum, _, i) => sum + (i * i), 0);

        const slopeEquip = (nEquip * sumXYEquip - sumXEquip * sumYEquip) / (nEquip * sumX2Equip - sumXEquip * sumXEquip);
        const interceptEquip = (sumYEquip - slopeEquip * sumXEquip) / nEquip;
        const trendLineEquip = equipData.map((_, i) => slopeEquip * i + interceptEquip);

        const equipChart = new Chart(ctxEquip, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels_equipos); ?>,
                datasets: [
                    {
                        label: 'Cambios',
                        data: equipData,
                        backgroundColor: '#0E544C',
                        borderRadius: 4,
                        order: 2
                    },
                    {
                        label: 'Tendencia',
                        data: trendLineEquip,
                        type: 'line',
                        borderColor: '#28a745',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        order: 1,
                        displayInLegend: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f0f0f0' },
                        ticks: { precision: 0 }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 10,
                            filter: function (item, chart) {
                                return !item.text.includes('Tendencia');
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
    </script>
</body>

</html>