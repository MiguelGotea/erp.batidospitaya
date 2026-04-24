<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso de vista para esta herramienta
if (!tienePermiso('conexion_monitor', 'vista', $cargoOperario)) {
    header('Location: ../../index.php?error=no_permiso');
    exit();
}

$version = mt_rand(1, 9999);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Conexión — Sistemas Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../core/assets/css/indexmodulos.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="css/conexion_monitor.css?v=<?php echo $version; ?>">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, false); ?>

            <div class="monitor-wrap">

                <!-- Header -->
                <div class="monitor-header">
                    <h1>
                        <i class="fas fa-satellite-dish"></i>
                        Monitor de Conexión — Sistemas Access
                    </h1>
                    <div class="live-badge">
                        <div class="pulse-dot"></div>
                        EN VIVO &bull; Actualiza cada 1 min
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="kpi-row" id="kpi-row">
                    <div class="kpi-card total">
                        <span class="kpi-label">Total PCs</span>
                        <span class="kpi-num" id="kpi-total">—</span>
                        <span class="kpi-sub">equipos registrados</span>
                    </div>
                    <div class="kpi-card online">
                        <span class="kpi-label">En línea</span>
                        <span class="kpi-num" id="kpi-online">—</span>
                        <span class="kpi-sub">ping &lt; 5 min</span>
                    </div>
                    <div class="kpi-card alerta">
                        <span class="kpi-label">Alerta</span>
                        <span class="kpi-num" id="kpi-alerta">—</span>
                        <span class="kpi-sub">ping 5–30 min</span>
                    </div>
                    <div class="kpi-card offline">
                        <span class="kpi-label">Sin conexión</span>
                        <span class="kpi-num" id="kpi-offline">—</span>
                        <span class="kpi-sub">ping &gt; 30 min</span>
                    </div>
                </div>


                <!-- Main grid -->
                <div class="main-grid">

                    <!-- LEFT: PC Grid -->
                    <div>
                        <div class="section-title-sm">
                            <i class="fas fa-desktop"></i>
                            Estado de equipos
                        </div>

                        <!-- Toolbar -->
                        <div class="toolbar">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="search-input" placeholder="Buscar PC o sucursal…">
                            </div>
                            <button class="filter-btn active" data-filter="all" id="btn-all">Todos</button>
                            <button class="filter-btn f-online" data-filter="online" id="btn-online">🟢 Online</button>
                            <button class="filter-btn f-alerta" data-filter="alerta" id="btn-alerta">🟡 Alerta</button>
                            <button class="filter-btn f-offline" data-filter="offline" id="btn-offline">🔴
                                Offline</button>
                            <span class="refresh-info" id="last-update">—</span>
                        </div>

                        <!-- PC Cards -->
                        <div id="pc-container">
                            <!-- Skeleton loader inicial -->
                            <div class="skeleton-grid" id="skeleton">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                    <div class="skeleton-card"></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: Actividad reciente -->
                    <div>
                        <div class="section-title-sm">
                            <i class="fas fa-history"></i>
                            Actividad reciente
                        </div>
                        <div class="sidebar-card">
                            <ul class="activity-list" id="activity-list">
                                <li style="color:#94a3b8;font-size:.78rem;text-align:center;padding:20px 0;">Cargando…
                                </li>
                            </ul>
                        </div>
                    </div>

                </div><!-- /main-grid -->
            </div><!-- /monitor-wrap -->
        </div>
    </div>

    <script src="js/conexion_monitor.js?v=<?php echo $version; ?>"></script>
</body>

</html>