<?php
/* ============================================================
   PRONÓSTICO DE ABASTECIMIENTO
   modulos/productos/pronostico_abastecimiento.php

   Vista tipo agenda cronológica de despachos futuros.
   Reutiliza: pedido_sugerido_calcular_v2.php
              pedido_sugerido_pronostico_v2.php
   ============================================================ */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario      = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pronostico_abastecimiento', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$version = mt_rand(1, 10000);

// Predeterminar semanas desde el servidor
$semActual = $semDesdeDefault = $semHastaDefault = '';
try {
    $stmtSem = $conn->query("SELECT numero_semana FROM SemanasSistema WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin LIMIT 1");
    $resSem  = $stmtSem->fetch(PDO::FETCH_ASSOC);
    if ($resSem) {
        $semActual       = (int)$resSem['numero_semana'];
        $semDesdeDefault = $semActual - 6;
        $semHastaDefault = $semActual;
    }
} catch (Exception $e) { /* silencioso */ }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pronóstico de Abastecimiento · Pitaya ERP</title>
    <meta name="description" content="Agenda cronológica de despachos futuros proyectados por sucursal e insumo.">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="css/pronostico_abastecimiento.css?v=<?php echo $version; ?>">
</head>

<body>
<?php echo renderMenuLateral($cargoOperario); ?>

<div class="main-container">
    <div class="sub-container">
        <?php echo renderHeader($usuario, 'Pronóstico de Abastecimiento'); ?>

        <div class="pa-wrapper p-2">

            <!-- ══ FILTROS (UNA SOLA LÍNEA) ══ -->
            <div class="pa-filtros-card mb-3">
                <div class="row g-2 align-items-end">

                    <div class="col-6 col-md-auto">
                        <label class="pa-label" for="pa-desde">Semana Desde</label>
                        <input type="number" id="pa-desde" class="form-control form-control-sm pa-input"
                               style="width:110px" min="1" max="9999"
                               placeholder="ej: <?php echo $semDesdeDefault; ?>"
                               value="<?php echo $semDesdeDefault; ?>">
                    </div>

                    <div class="col-6 col-md-auto">
                        <label class="pa-label" for="pa-hasta">Semana Hasta</label>
                        <input type="number" id="pa-hasta" class="form-control form-control-sm pa-input"
                               style="width:110px" min="1" max="9999"
                               placeholder="ej: <?php echo $semHastaDefault; ?>"
                               value="<?php echo $semHastaDefault; ?>">
                    </div>

                    <div class="col-6 col-md-auto">
                        <label class="pa-label" for="pa-corte"
                               title="Semana cuyo inventario real (domingo) sirve como base del pronóstico D-1">
                            Sem. Corte
                        </label>
                        <input type="number" id="pa-corte" class="form-control form-control-sm pa-input"
                               style="width:110px" min="1" max="9999"
                               placeholder="ej: <?php echo $semHastaDefault; ?>">
                    </div>

                    <div class="col-12 col-md">
                        <label class="pa-label" for="pa-sucursal">
                            Sucursal <span class="text-danger">*</span>
                        </label>
                        <select id="pa-sucursal" class="form-select form-select-sm pa-select" style="min-width:200px">
                            <option value="">— Selecciona —</option>
                        </select>
                    </div>

                    <div class="col-auto d-flex align-items-end gap-2">
                        <?php if (!empty($semActual)): ?>
                        <span class="badge rounded-pill"
                              style="background:rgba(56,189,248,0.15);color:#38bdf8;border:1px solid rgba(56,189,248,0.3);font-size:11px;padding:6px 10px;">
                            <i class="fas fa-calendar-check me-1"></i>Sem.&nbsp;<strong><?php echo $semActual; ?></strong>
                        </span>
                        <?php endif; ?>
                        <button id="pa-btn-calcular" class="btn pa-btn-calcular">
                            <i class="bi bi-calendar2-week me-1"></i>Calcular Agenda
                        </button>
                    </div>

                </div>
            </div><!-- /filtros -->

            <!-- ══ ESTADO INICIAL ══ -->
            <div id="pa-panel-inicial" class="pa-empty-state">
                <div class="pa-empty-icon"><i class="bi bi-calendar2-week"></i></div>
                <h5>Pronóstico de Abastecimiento</h5>
                <p class="text-muted" style="max-width:400px;margin:0 auto">
                    Ingresa el rango de semanas, la semana de corte y la sucursal,
                    luego haz clic en <strong>Calcular Agenda</strong>.
                </p>
            </div>

            <!-- ══ LOADER ══ -->
            <div id="pa-loader" class="pa-loader d-none">
                <div class="pa-spinner"></div>
                <div class="pa-loader-text">
                    Calculando agenda de despachos…
                    <div class="pa-loader-step" id="pa-loader-step">Analizando consumo histórico</div>
                </div>
            </div>

            <!-- ══ PANEL DE RESULTADOS ══ -->
            <div id="pa-panel-datos" class="d-none">

                <!-- Warnings de grupos sin plan -->
                <div id="pa-warnings" class="d-none"></div>

                <!-- Agenda cronológica -->
                <div id="pa-agenda"></div>

            </div><!-- /panel-datos -->

        </div><!-- /pa-wrapper -->
    </div><!-- /sub-container -->
</div><!-- /main-container -->

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="js/pronostico_abastecimiento.js?v=<?php echo $version; ?>"></script>
</body>
</html>
