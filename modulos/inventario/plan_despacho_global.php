<?php
/* ============================================================
   PÁGINA: Plan de Despacho Global
   Ruta: modulos/inventario/plan_despacho_global.php
   ============================================================ */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('plan_despacho_global', 'vista', $cargoOperario)) {
    header('Location: ../../index.php'); exit();
}
$puedeEditar = tienePermiso('plan_despacho_global', 'edicion', $cargoOperario);
$version = mt_rand(1, 10000);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan de Despacho Global · Pitaya ERP</title>
    <meta name="description" content="Configuración del plan de despacho por sucursal y categoría de insumo para el ERP Batidos Pitaya.">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo $version;?>">
    <link rel="stylesheet" href="css/plan_despacho_global.css?v=<?php echo $version;?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Plan de Despacho Global'); ?>

            <div class="container-fluid p-3">



                <!-- Loader de sucursales -->
                <div id="loaderSucursales" class="pdg-loader-main">
                    <div class="spinner-border pdg-spinner" role="status"></div>
                    <p class="mt-3 text-muted small">Cargando sucursales…</p>
                </div>

                <!-- Contenedor principal: Lista lateral + Formulario -->
                <div id="mainLayout" style="display:none;" class="row g-3">
                    <!-- Panel lateral para sucursales -->
                    <div class="col-md-3 col-lg-2">
                        <div class="w-100 h-100 position-relative" style="min-height: 250px;">
                            <div class="pdg-sidebar-wrapper pdg-tab-content d-flex flex-column" style="padding: 1.25rem;">
                                <h6 class="mb-3 fw-bold" style="color: var(--pdg-green);"><i class="bi bi-shop me-2"></i>Tiendas</h6>
                                <div class="list-group pdg-store-list flex-grow-1" id="sucursalesList" style="overflow-y: auto; overflow-x: hidden; padding-right: 5px;">
                                    <!-- Generado por JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista de categorias (Formulario) -->
                    <div class="col-md-9 col-lg-10 d-flex flex-column">
                        <div class="pdg-tab-content flex-grow-1" id="contentSelectedStore">
                            <!-- Generado por JS -->
                        </div>
                    </div>
                </div>

                <!-- Calendario Global full width -->
                <div id="calendarContainer" class="mt-4" style="display:none;">
                    <div class="pdg-tab-content" style="overflow-x: auto; padding: 1.25rem;">
                        <div id="globalCalendar"></div>
                    </div>
                </div>

                <!-- Estado vacío si no hay sucursales -->
                <div id="sinSucursales" style="display:none;" class="pdg-empty-state">
                    <i class="bi bi-building-x"></i>
                    <h5>No hay sucursales activas</h5>
                    <p class="text-muted small">No se encontraron sucursales configuradas como activas en el sistema.</p>
                </div>

            </div><!-- /container-fluid -->
        </div>
    </div>

    <!-- Tooltip container (Bootstrap) -->
    <div id="tooltipAncla" class="d-none">
        Ingresa el número de semana de un despacho real ya ocurrido. Ej: si la semana 540 fue el último despacho, escribe 540. El sistema usa esto para calcular si la semana actual "toca" según el intervalo configurado.
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>const PUEDE_EDITAR = <?php echo $puedeEditar ? 'true' : 'false'; ?>;</script>
    <script src="js/plan_despacho_global.js?v=<?php echo $version;?>"></script>
</body>
</html>
