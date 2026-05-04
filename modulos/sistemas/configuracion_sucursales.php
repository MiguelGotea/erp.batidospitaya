<?php
// configuracion_sucursales.php
$version = mt_rand(1, 10000);

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('configuracion_sucursales', 'vista', $cargoOperario)) {
    header('Location: ../../index.php?error=no_permiso');
    exit();
}

$puedeEditar = tienePermiso('configuracion_sucursales', 'edicion', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Sucursales — Batidos Pitaya</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="../../core/assets/css/indexmodulos.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="css/configuracion_sucursales.css?v=<?php echo $version; ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, false); ?>

            <div class="suc-wrap">

                <!-- Header de página -->
                <div class="suc-header">
                    <h1>
                        <i class="bi bi-building-gear"></i>
                        Configuración de Sucursales
                    </h1>
                    <?php if (!$puedeEditar): ?>
                    <span style="font-size:.75rem;background:#fef3c7;color:#92400e;padding:4px 12px;border-radius:99px;font-weight:600;border:1px solid #f59e0b;">
                        <i class="bi bi-eye"></i> Modo solo lectura
                    </span>
                    <?php endif; ?>
                </div>

                <!-- KPIs -->
                <div class="kpi-strip">
                    <div class="kpi-box total">
                        <span class="kpi-lbl">Total</span>
                        <span class="kpi-num" id="kpi-total">—</span>
                        <span class="kpi-sub">sucursales</span>
                    </div>
                    <div class="kpi-box activa">
                        <span class="kpi-lbl">Activas</span>
                        <span class="kpi-num" id="kpi-activa">—</span>
                        <span class="kpi-sub">en operación</span>
                    </div>
                    <div class="kpi-box inact">
                        <span class="kpi-lbl">Inactivas</span>
                        <span class="kpi-num" id="kpi-inact">—</span>
                        <span class="kpi-sub">cerradas</span>
                    </div>
                    <div class="kpi-box dvr">
                        <span class="kpi-lbl">Con DVR</span>
                        <span class="kpi-num" id="kpi-dvr">—</span>
                        <span class="kpi-sub">configuradas</span>
                    </div>
                </div>

                <!-- Mapa General -->
                <div class="mapa-general-wrap">
                    <div class="mapa-general-header">
                        <span><i class="bi bi-map"></i> Ubicación de sucursales</span>
                        <button class="toggle-mapa-btn" onclick="toggleMapaGeneral()">Ocultar mapa</button>
                    </div>
                    <div id="mapa-general"></div>
                </div>

                <!-- Toolbar -->
                <div class="suc-toolbar">
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" id="suc-search" placeholder="Buscar por nombre, código o departamento…">
                    </div>
                    <button class="fil-btn active" data-filtro="all">Todas</button>
                    <button class="fil-btn f-act"  data-filtro="act">✅ Activas</button>
                    <button class="fil-btn f-ina"  data-filtro="ina">🔴 Inactivas</button>
                    <button class="fil-btn f-dvr"  data-filtro="dvr">📷 Con DVR</button>
                    <button class="fil-btn f-ndvr" data-filtro="ndvr">⚠️ Sin DVR</button>
                </div>

                <!-- Grid de sucursales -->
                <div id="suc-grid">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="skeleton-card"></div>
                    <?php endfor; ?>
                </div>

            </div><!-- /suc-wrap -->
        </div>
    </div>

    <!-- Toast container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const PUEDE_EDITAR = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
    </script>
    <script src="js/configuracion_sucursales.js?v=<?php echo $version; ?>"></script>
</body>
</html>
