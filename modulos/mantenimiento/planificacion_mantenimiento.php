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
$horas_jornada = isset($_POST['horas_jornada']) ? floatval($_POST['horas_jornada']) : 10;
$dias_plan = isset($_POST['dias_plan']) ? intval($_POST['dias_plan']) : 6;
$alpha = 0.7; // Peso a Carga Relativa
$beta = 0.3;  // Peso a Eficiencia Geográfica

// Constantes logísticas
define('VELOCIDAD_PROMEDIO_KMH', 50.0);
define('BUFFER_TIEMPO', 1.15);
define('BASE_LAT', 12.1328); // Managua
define('BASE_LNG', -86.2504);

// Función Haversine para tiempos de viaje
if (!function_exists('obtenerHorasViaje')) {
    function obtenerHorasViaje($lat1, $lon1, $lat2, $lon2) {
        if (empty($lat1) || empty($lon1) || empty($lat2) || empty($lon2)) {
            return 2.5; // Fallback promedio si no hay lat/lng
        }
        $earth_radius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * asin(sqrt($a));
        $distancia_km = $earth_radius * $c;
        return $distancia_km / VELOCIDAD_PROMEDIO_KMH;
    }
}

// ALGORITMO DE AGENDAMIENTO DINÁMICO: OLEADAS DE URGENCIA v2
$agenda_semanal = [];

// 1. Preparación del Pool
$pool_tickets_raw = [];
foreach ($tickets as $t) {
    // T_real con holgura
    $t['T_real'] = max(0.2, floatval($t['tiempo_exec']) * BUFFER_TIEMPO);
    
    // Urgencia Efectiva (escala 1 pt cada 3 semanas)
    $semanas = intval($t['semanas_antiguedad'] ?? 0);
    $urg_base = intval($t['urgencia'] ?? 1);
    $t['U_ef'] = min(4, $urg_base + floor($semanas / 3));
    
    // Fallback Coords
    $lat = floatval($t['Latitude'] ?? 0);
    $lng = floatval($t['Longitude'] ?? 0);
    $is_regional = ($t['departamento_sucursal'] !== 'Managua');
    
    if ($lat == 0 || $lng == 0) {
        $t['Latitude'] = BASE_LAT + ($is_regional ? 0.8 : 0.05); // Offset mock geográfico
        $t['Longitude'] = BASE_LNG + ($is_regional ? 0.8 : 0.05);
    }
    
    $pool_tickets_raw[$t['id']] = $t;
}

for ($d = 1; $d <= $dias_plan; $d++) {
    $dia_nombre = "Día " . $d;
    $agenda_semanal[$dia_nombre] = [
        'visitas' => [],
        'tiempo_total' => 0,
        'tiempo_transporte' => 0,
        'tiempo_ejecucion' => 0
    ];

    $h_libres = $horas_jornada;
    $sucursales_visitadas_hoy = [];
    $ultima_lat = BASE_LAT;
    $ultima_lng = BASE_LNG;

    // Procesar oleadas 4 a 1
    for ($oleada = 4; $oleada >= 1; $oleada--) {
        
        while ($h_libres > 0.5) {
            // Agrupar tickets de esta oleada por sucursal
            $grupos = [];
            foreach ($pool_tickets_raw as $id => $t) {
                if ($t['U_ef'] == $oleada) {
                    $cod = $t['cod_sucursal'];
                    if (!isset($grupos[$cod])) {
                        $grupos[$cod] = [
                            'cod' => $cod,
                            'nombre' => $t['nombre_sucursal'],
                            'lat' => $t['Latitude'],
                            'lng' => $t['Longitude'],
                            'tickets' => [],
                            'C' => 0
                        ];
                    }
                    $grupos[$cod]['tickets'][] = $t;
                    $grupos[$cod]['C'] += $t['T_real'];
                }
            }

            if (empty($grupos)) break; // Pasa a la siguiente oleada

            $max_C = max(array_column($grupos, 'C'));
            $max_eta = 0;
            
            foreach ($grupos as &$g) {
                if (empty($sucursales_visitadas_hoy)) {
                    $g['costo_viaje'] = obtenerHorasViaje(BASE_LAT, BASE_LNG, $g['lat'], $g['lng']) * 2;
                } else {
                    $t_u_n = obtenerHorasViaje($ultima_lat, $ultima_lng, $g['lat'], $g['lng']);
                    $t_n_b = obtenerHorasViaje($g['lat'], $g['lng'], BASE_LAT, BASE_LNG);
                    $t_u_b = obtenerHorasViaje($ultima_lat, $ultima_lng, BASE_LAT, BASE_LNG);
                    $g['costo_viaje'] = max(0, $t_u_n + $t_n_b - $t_u_b); // Evita flotantes minúsculos negativos
                }
                
                $viaje_total = obtenerHorasViaje(BASE_LAT, BASE_LNG, $g['lat'], $g['lng']) * 2;
                $g['eta'] = $g['C'] / max(0.2, $viaje_total);
                if ($g['eta'] > $max_eta) $max_eta = $g['eta'];
            }
            unset($g);

            $mejor_grupo = null;
            $mejor_score = -1;

            foreach ($grupos as $g) {
                $min_task = min(array_column($g['tickets'], 'T_real'));
                if ($h_libres < ($g['costo_viaje'] + $min_task)) continue;

                $norm_C = $max_C > 0 ? $g['C'] / $max_C : 0;
                $norm_eta = $max_eta > 0 ? $g['eta'] / $max_eta : 0;
                
                $score = ($alpha * $norm_C) + ($beta * $norm_eta);
                
                if ($score > $mejor_score) {
                    $mejor_score = $score;
                    $mejor_grupo = $g;
                }
            }

            if (!$mejor_grupo) break; // Ningún grupo cabe, intenta oleada menor o pasa al sgte dia

            $h_libres -= $mejor_grupo['costo_viaje'];
            $horas_exec_hoy = 0;
            $tickets_ejecutados = [];

            // RELLENO OPORTUNISTA (Mochila Voraz en Sucursal Elegida)
            $tkts_sucursal = array_filter($pool_tickets_raw, fn($t) => $t['cod_sucursal'] === $mejor_grupo['cod']);
            usort($tkts_sucursal, fn($a, $b) => $b['U_ef'] <=> $a['U_ef']);

            foreach ($tkts_sucursal as $t) {
                if ($h_libres >= $t['T_real']) {
                    $h_libres -= $t['T_real'];
                    $horas_exec_hoy += $t['T_real'];
                    // Ajuste UI: Reflejar T_real en el panel formateando a visual
                    $t['tiempo_exec'] = round($t['T_real'], 2);
                    $tickets_ejecutados[] = $t;
                    unset($pool_tickets_raw[$t['id']]);
                }
            }

            if (!empty($tickets_ejecutados)) {
                $agenda_semanal[$dia_nombre]['visitas'][] = [
                    'cod' => $mejor_grupo['cod'],
                    'nombre' => $mejor_grupo['nombre'],
                    'departamento' => $tickets_ejecutados[0]['departamento_sucursal'],
                    'viaje' => round($mejor_grupo['costo_viaje'], 2),
                    'horas_exec' => round($horas_exec_hoy, 2),
                    'tickets' => $tickets_ejecutados
                ];

                $agenda_semanal[$dia_nombre]['tiempo_ejecucion'] += $horas_exec_hoy;
                $agenda_semanal[$dia_nombre]['tiempo_transporte'] += $mejor_grupo['costo_viaje'];
                $agenda_semanal[$dia_nombre]['tiempo_total'] += ($horas_exec_hoy + $mejor_grupo['costo_viaje']);
                
                $sucursales_visitadas_hoy[] = $mejor_grupo['cod'];
                $ultima_lat = $mejor_grupo['lat'];
                $ultima_lng = $mejor_grupo['lng'];
            }
        }
    }
}

// Convertimos el mapa remanente a arreglo normal para UI
$pool_tickets = array_values($pool_tickets_raw);

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
                                            <label class="small text-muted mb-0">Cant. de Días</label>
                                            <input type="number" min="1" step="1" name="dias_plan"
                                                class="form-control form-control-sm"
                                                value="<?php echo $dias_plan; ?>">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end align-items-center mt-3 mb-1">
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
                    <div class="col-lg-3">
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
                    <div class="col-lg-9">
                        <div class="card shadow-sm border-0 bg-light-soft">
                            <div
                                class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
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
                                                            <div class="visit-item mb-2 bg-white rounded-2 p-2 px-3 border-start border-4 shadow-sm"
                                                                style="border-color: <?php echo getColorUrgencia($v['tickets'][0]['urgencia']); ?>; cursor: pointer;"
                                                                title="Clic para ver detalle térmico"
                                                                data-bs-toggle="modal" data-bs-target="#visitDetailsModal"
                                                                data-visit-json='<?php echo htmlspecialchars(json_encode([
                                                                    "nombre" => $v["nombre"],
                                                                    "departamento" => $v["departamento"],
                                                                    "horas_exec" => $v["horas_exec"],
                                                                    "viaje" => $v["viaje"],
                                                                    "tickets" => $v["tickets"]
                                                                ]), ENT_QUOTES, "UTF-8"); ?>'>
                                                                <div
                                                                    class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                                                    <div class="fw-bold small">
                                                                        <?php echo htmlspecialchars($v['nombre']); ?>
                                                                    </div>
                                                                    <span class="badge bg-white text-dark border-0 small px-1"
                                                                        style="font-size: 0.65rem; white-space: normal;">
                                                                        <?php echo $v['horas_exec']; ?>h S +
                                                                        <?php echo $v['viaje']; ?>h V
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

    <!-- Modal de Detalles de Visita -->
    <div class="modal fade" id="visitDetailsModal" tabindex="-1" aria-labelledby="visitDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header text-white border-0 py-3" style="background-color: #0E544C;">
                    <h5 class="modal-title fw-bold" id="visitDetailsModalLabel">
                        <i class="bi bi-geo-alt-fill me-2"></i>Detalles de Visita
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-dark" id="modalVisitBranch">Nombre de la Sucursal</h6>
                        <span class="badge bg-secondary" id="modalVisitStats">0h Ejecución | 0h Viaje</span>
                    </div>
                    
                    <div class="list-group list-group-flush border rounded-3 overflow-hidden shadow-sm" id="modalTicketsList">
                        <!-- Tickets se inyectarán aquí vía JS -->
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
                        <h6 class="fw-bold text-info"><i class="bi bi-cpu-fill me-2"></i>Algoritmo: Oleadas de Urgencia v2</h6>
                        <p class="text-muted small mb-2">
                            El sistema evalúa todos los tickets de <strong>Mantenimiento General</strong> y los agrupa en <strong>Oleadas (O4 a O1)</strong> basado en su Urgencia Efectiva. 
                        </p>
                        <p class="text-muted small">
                            Aplica la <strong>Fórmula de Haversine</strong> para medir distancias exactas y optimizar los viajes, resolviendo un problema logístico avanzado con relleno oportunista para aprovechar cada minuto del día.
                        </p>
                    </section>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h7 class="fw-bold d-block mb-2 text-dark">1. Escalado de Urgencia</h7>
                                <p class="small text-muted mb-0">Cada 3 semanas que un ticket pasa sin ser atendido, su urgencia sube 1 nivel automáticamente. ¡Un ticket Normal puede volverse Crítico por antigüedad!</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h7 class="fw-bold d-block mb-2 text-dark">2. Score de Eficiencia</h7>
                                <p class="small text-muted mb-0">El sistema balancea el impacto (volumen de horas de trabajo en la sucursal) contra la distancia geográfica. Privilegia viajes donde se resuelvan muchas tareas de golpe.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h7 class="fw-bold d-block mb-2 text-dark">3. Agrupamiento Triangular</h7>
                                <p class="small text-muted mb-0">Si el equipo ya está en la Sucursal A, el algoritmo evalúa si vale la pena saltar a la B midiendo el "Costo Incremental" del triángulo (A -> B -> Base) vs (A -> Base).</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h7 class="fw-bold d-block mb-2 text-dark">4. Relleno Oportunista</h7>
                                <p class="small text-muted mb-0">Si el sistema te envía a un departamento por una Urgencia 4 y sobran horas en el día, rellenará ese tiempo resolviendo tickets menores de <strong>esa misma sucursal</strong>.</p>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-4 mb-0 border-0 shadow-sm">
                        <i class="bi bi-lightbulb-fill me-2 text-warning"></i>
                        <strong>Importante:</strong> Los tiempos estimados se multiplican por un factor de holgura (1.15x) para representar el tiempo real operativo y evitar retrasos.
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

        // Lógica del Modal de Detalles de Visita
        document.addEventListener('DOMContentLoaded', () => {
            const visitItems = document.querySelectorAll('.visit-item');
            const modalBranch = document.getElementById('modalVisitBranch');
            const modalStats = document.getElementById('modalVisitStats');
            const modalTicketsList = document.getElementById('modalTicketsList');

            visitItems.forEach(item => {
                item.addEventListener('click', function() {
                    let rawData = this.getAttribute('data-visit-json');
                    if (!rawData) return;
                    
                    const data = JSON.parse(rawData);
                    
                    // Actualizar cabeceras
                    modalBranch.textContent = data.nombre + ' (' + data.departamento + ')';
                    modalStats.textContent = `${data.horas_exec}h Ejecución | ${data.viaje}h Viaje`;
                    
                    // Limpiar y poblar lista de tickets
                    modalTicketsList.innerHTML = '';
                    
                    if (data.tickets && data.tickets.length > 0) {
                        data.tickets.forEach(tk => {
                            // Función Helper JS para colores de urgencia (replicando PHP)
                            let badgeColor = '';
                            let badgeLabel = '';
                            switch(parseInt(tk.urgencia)) {
                                case 4: badgeColor = 'bg-danger'; badgeLabel = 'Crítico'; break;
                                case 3: badgeColor = 'bg-warning text-dark'; badgeLabel = 'Alta'; break;
                                case 2: badgeColor = 'bg-info text-white'; badgeLabel = 'Media'; break;
                                case 1: badgeColor = 'bg-success'; badgeLabel = 'Baja'; break;
                                default: badgeColor = 'bg-secondary'; badgeLabel = 'N/A';
                            }

                            const imgPath = tk.primera_foto ? `/uploads/${tk.primera_foto}` : null;
                            const imageHtml = imgPath 
                                ? `<div class="me-3 flex-shrink-0">
                                     <img src="${imgPath}" class="rounded border" style="width: 60px; height: 60px; object-fit: cover;" alt="Miniatura" onerror="this.style.display='none'">
                                   </div>` 
                                : '';

                            const tktHtml = `
                                <div class="list-group-item list-group-item-action p-3">
                                    <div class="d-flex w-100 align-items-start">
                                        ${imageHtml}
                                        <div class="flex-grow-1 min-width-0">
                                            <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                                <h6 class="mb-0 fw-bold text-dark text-truncate pe-2">${tk.titulo_formulario || 'Ticket de Mantenimiento'}</h6>
                                                <span class="badge ${badgeColor} flex-shrink-0">${badgeLabel}</span>
                                            </div>
                                            <div class="d-flex justify-content-start align-items-center mb-1">
                                                <small class="text-muted"><i class="bi bi-clock-history me-1"></i>${tk.tiempo_exec}h estimadas</small>
                                            </div>
                                            ${tk.descripcion ? `<p class="mb-0 small text-muted text-wrap" style="line-height: 1.3;">${tk.descripcion}</p>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                            modalTicketsList.insertAdjacentHTML('beforeend', tktHtml);
                        });
                    } else {
                        modalTicketsList.innerHTML = '<div class="p-4 text-center text-muted">No hay tickets asignados a esta visita.</div>';
                    }
                });
            });
        });
    </script>
</body>

</html>