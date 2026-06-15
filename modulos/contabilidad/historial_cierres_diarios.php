<?php
// historial_cierres_diarios.php
// Historial de todos los cierres finales diarios con filtros por columna.
// Usa el mismo permiso que balance_cierre_diario.

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Mismo permiso que la herramienta original
if (!tienePermiso('balance_cierre_diario', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Cierres Diarios - Batidos Pitaya</title>
    <meta name="description" content="Historial completo de cierres finales de caja diarios por sucursal">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/historial_cierres_diarios.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Historial Cierres Diarios'); ?>

            <div class="container-fluid p-3">

                <!-- Barra superior: contador + registros por página -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-success fs-5"></i>
                        <span class="fw-semibold text-muted" id="hcdContador">Cargando...</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0 small text-muted">Mostrar:</label>
                        <select class="form-select form-select-sm" id="hcdPorPagina"
                                style="width:auto;" onchange="cambiarRegistrosPorPagina()">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="small text-muted">registros</span>
                    </div>
                </div>

                <!-- Tabla principal -->
                <div class="table-responsive">
                    <table class="table table-hover hcd-table" id="tablaHistorial">
                        <thead>
                            <tr>
                                <th data-column="nombre_sucursal" data-type="list">
                                    Sucursal
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="CodigoCierre" data-type="text">
                                    Cierre Final
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="cajero" data-type="text">
                                    Cajero
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Faltante" data-type="number">
                                    Sobrante / Faltante
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="HoraInicial" data-type="text">
                                    Hora Inicial
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="HoraFinal" data-type="text">
                                    Hora Final
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Fecha" data-type="daterange">
                                    Fecha
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Observaciones" data-type="text">
                                    Observaciones
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th style="width:90px; text-align:center;">Ver</th>
                            </tr>
                        </thead>
                        <tbody id="hcdTbody">
                            <!-- Cargado vía AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="d-flex justify-content-end align-items-center mt-3">
                    <div id="hcdPaginacion"></div>
                </div>

            </div><!-- /container-fluid -->
        </div><!-- /sub-container -->
    </div><!-- /main-container -->

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/historial_cierres_diarios.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>
