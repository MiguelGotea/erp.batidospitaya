<?php
// promociones.php — Configurador de Promociones (Pitaya ERP)

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario      = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('promociones', 'vista', $cargoOperario)) {
    header('Location: /login.php'); exit();
}

$puedeCrear        = tienePermiso('promociones', 'nuevo', $cargoOperario);
$puedeEditar       = tienePermiso('promociones', 'edicion', $cargoOperario);
$puedeEliminar     = tienePermiso('promociones', 'eliminar', $cargoOperario);
$puedeCambiarEstado = tienePermiso('promociones', 'cambiar_estado', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurador de Promociones</title>
    <meta name="description" content="Gestión y configuración de promociones del ERP Pitaya">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1,9999); ?>">
    <link rel="stylesheet" href="css/promociones.css?v=<?php echo mt_rand(1,9999); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Configurador de Promociones'); ?>

            <div class="container-fluid p-3">

                <!-- TOOLBAR -->
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end mb-3">
                    <?php if ($puedeCrear): ?>
                    <a href="promocion_form.php" class="btn promo-btn-nuevo">
                        <i class="bi bi-plus-circle"></i> Nueva Promoción
                    </a>
                    <?php endif; ?>
                </div>

                <!-- TABLA -->
                <div class="table-responsive promo-table-wrapper">
                    <table class="table promo-table" id="tablaPromociones">
                        <thead>
                            <tr>
                                <th data-column="id" data-type="number">
                                    Código
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nombre" data-type="text">
                                    Nombre
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="estado" data-type="list">
                                    Estado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th class="text-center" data-column="num_condiciones" data-type="number">
                                    Condiciones
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="resultado_tipo" data-type="list">
                                    Resultado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_inicio" data-type="daterange">
                                    Vigencia
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th class="text-center" data-column="prioridad" data-type="number">
                                    Prioridad
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th class="text-center" style="width:160px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaPromocionesBody">
                            <tr><td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-arrow-repeat spin-icon"></i> Cargando…
                            </td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINACIÓN -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0 small">Mostrar:</label>
                        <select class="form-select form-select-sm" id="registrosPorPagina" style="width:auto;" onchange="cambiarRegistrosPorPagina()">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="mb-0 small text-muted" id="infoRegistros"></span>
                    </div>
                    <div id="paginacion" class="promo-paginacion"></div>
                </div>

            </div><!-- /container-fluid -->
        </div>
    </div>

    <!-- ===================== MODAL AYUDA ===================== -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header promo-modal-header">
                    <h5 class="modal-title"><i class="bi bi-question-circle me-2"></i>Ayuda — Configurador de Promociones</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-bold text-success">¿Qué es el Configurador de Promociones?</h6>
                    <p>Herramienta para definir reglas de descuento que el motor del POS evaluará en tiempo real al procesar una venta. <strong>No es el motor de facturación</strong> — solo configura las reglas.</p>

                    <h6 class="fw-bold text-success mt-3">Estados de una promoción</h6>
                    <ul>
                        <li><span class="badge bg-secondary">Borrador</span> — En preparación, no activa en POS.</li>
                        <li><span class="badge bg-success">Activa</span> — El POS la evalúa en cada venta.</li>
                        <li><span class="badge bg-warning text-dark">Inactiva</span> — Pausada temporalmente.</li>
                        <li><span class="badge bg-danger">Archivada</span> — Eliminación lógica, no se puede reversar.</li>
                    </ul>

                    <h6 class="fw-bold text-success mt-3">Prioridad</h6>
                    <p>Cuando dos promociones campean sobre el mismo producto, la de <strong>menor número gana</strong>. Configure prioridad 1 para la más importante.</p>

                    <h6 class="fw-bold text-success mt-3">Condiciones</h6>
                    <p>Todas las condiciones de una promoción se evalúan con <strong>AND</strong>: todas deben cumplirse simultáneamente para que la promoción se active.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.PROMO_PAGE = 'listado';
        window.PROMO_PERMISOS = {
            edicion:      <?php echo $puedeEditar ? 'true' : 'false'; ?>,
            eliminar:     <?php echo $puedeEliminar ? 'true' : 'false'; ?>,
            cambiarEstado:<?php echo $puedeCambiarEstado ? 'true' : 'false'; ?>
        };
    </script>
    <script src="js/promociones.js?v=<?php echo mt_rand(1,9999); ?>"></script>
</body>
</html>
