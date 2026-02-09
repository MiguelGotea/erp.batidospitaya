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

// Agrupar tickets por sucursal para el análisis de prioridad
$sucursales_prioridad = [];
foreach ($tickets as $t) {
    $cod = $t['cod_sucursal'];
    if (!isset($sucursales_prioridad[$cod])) {
        $sucursales_prioridad[$cod] = [
            'nombre' => $t['nombre_sucursal'],
            'departamento' => $t['departamento_sucursal'],
            'transporte' => $t['tiempo_transporte'],
            'tickets' => [],
            'suma_ejecucion' => 0,
            'urgencia_max' => 0
        ];
    }
    $sucursales_prioridad[$cod]['tickets'][] = $t;
    $sucursales_prioridad[$cod]['suma_ejecucion'] += $t['tiempo_exec'];
    if ($t['urgencia'] > $sucursales_prioridad[$cod]['urgencia_max']) {
        $sucursales_prioridad[$cod]['urgencia_max'] = $t['urgencia'];
    }
}

// Calcular Score de Prioridad por Sucursal
foreach ($sucursales_prioridad as &$s) {
    // Score = (Urgencia Max * 100) + Suma Horas - Penalización Transporte
    $s['score'] = ($s['urgencia_max'] * 100) + $s['suma_ejecucion'] - ($s['transporte'] > 0 ? 50 : 0);
}
unset($s);

// Ordenar sucursales por score
uasort($sucursales_prioridad, function ($a, $b) {
    return $b['score'] <=> $a['score'];
});

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
                                        <span class="d-block text-muted small">Tickets Pendientes</span>
                                        <span class="fs-4 fw-bold">
                                            <?php echo count($tickets); ?>
                                        </span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="d-block text-muted small">Sucursales con Carga</span>
                                        <span class="fs-4 fw-bold">
                                            <?php echo count($sucursales_prioridad); ?>
                                        </span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="d-block text-muted small">Capacidad Semanal</span>
                                        <span class="fs-4 fw-bold">60 hrs</span>
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
                    <!-- Lista de Sucursales Priorizadas -->
                    <div class="col-lg-7">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">Rank de Prioridad por Sucursal</h6>
                                <span class="badge bg-light text-dark border">
                                    <?php echo count($sucursales_prioridad); ?> Sucursales
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th>Sucursal</th>
                                                <th class="text-center">Tickets</th>
                                                <th class="text-center">Urgencia</th>
                                                <th class="text-center">Trabajo (h)</th>
                                                <th class="text-center">Viaje (h)</th>
                                                <th class="text-center">Score</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sucursales_prioridad as $s): ?>
                                                <tr class="align-middle">
                                                    <td>
                                                        <div class="fw-bold">
                                                            <?php echo htmlspecialchars($s['nombre']); ?>
                                                        </div>
                                                        <small class="text-muted"><i class="bi bi-geo-alt me-1"></i>
                                                            <?php echo htmlspecialchars($s['departamento']); ?>
                                                        </small>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge rounded-pill bg-primary">
                                                            <?php echo count($s['tickets']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="urgency-dot-container d-inline-flex">
                                                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                                                <span
                                                                    class="urgency-dot <?php echo $i <= $s['urgencia_max'] ? '' : 'inactive'; ?>"
                                                                    style="background-color: <?php echo getColorUrgencia($i); ?>"></span>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-center fw-medium">
                                                        <?php echo number_format($s['suma_ejecucion'], 1); ?>
                                                    </td>
                                                    <td class="text-center text-muted">
                                                        <?php echo $s['transporte']; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="score-pill">
                                                            <?php echo number_format($s['score'], 0); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vista de Agenda Propuesta (6 días) -->
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0 bg-light-soft">
                            <div class="card-header bg-transparent py-3">
                                <h6 class="mb-0 fw-bold text-primary">Propuesta de Plan Semanal</h6>
                                <p class="small text-muted mb-0">Basado en jornada de 10 horas</p>
                            </div>
                            <div class="card-body pt-0">
                                <div class="planning-timeline">
                                    <?php
                                    $top_sucursales = array_slice($sucursales_prioridad, 0, 6, true);
                                    $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                                    $idx = 0;
                                    foreach ($top_sucursales as $cod => $s):
                                        $dia = $dias[$idx] ?? "Día " . ($idx + 1);
                                        $total_dia = $s['suma_ejecucion'] + $s['transporte'];
                                        $percent = ($total_dia / 10) * 100;
                                        $color_bar = $percent > 100 ? 'bg-danger' : ($percent > 80 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="planning-day mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold fs-7">
                                                    <?php echo $dia; ?>
                                                </span>
                                                <span class="badge bg-white text-dark border small">
                                                    <?php echo number_format($total_dia, 1); ?> / 10h
                                                </span>
                                            </div>
                                            <div class="day-card p-3 rounded shadow-xs bg-white border">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <div class="fw-bold text-dark">
                                                            <?php echo htmlspecialchars($s['nombre']); ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo count($s['tickets']); ?> tickets pendientes
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="small fw-medium text-uppercase text-secondary"
                                                            style="font-size: 0.65rem;">Sugerencia</div>
                                                        <div
                                                            class="small fw-bold <?php echo $s['transporte'] > 0 ? 'text-danger' : 'text-primary'; ?>">
                                                            <?php echo $s['transporte'] > 0 ? 'GIRA REGIONAL' : 'LOCAL MANAGUA'; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="progress mt-3" style="height: 6px;">
                                                    <div class="progress-bar <?php echo $color_bar; ?>" role="progressbar"
                                                        style="width: <?php echo $percent; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                        $idx++;
                                    endforeach;
                                    ?>
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