<?php
/**
 * Historial y Nueva Solicitud de Reembolsos con IA
 * Ubicación: /modulos/compras/reembolsos_ia_historial.php
 */

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso (Permiso estandarizado)
if (!tienePermiso('reembolsos_ia_plantilla', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Obtener proveedores para el select (usado en el modal de nuevo, si aplica)
$stmtProv = $conn->query("SELECT id, nombre FROM proveedores WHERE vigente = 1 ORDER BY nombre ASC");
$proveedores = $stmtProv->fetchAll(PDO::FETCH_ASSOC);

// Nota: El historial ahora se carga vía AJAX para soportar filtros de encabezado
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resúmenes de Reembolso IA | Pitaya ERP</title>
    
    <!-- Librerías estándar -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Estilos Globales y Específicos -->
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css">
    <link rel="stylesheet" href="css/reembolsos_ia_historial.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>

    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Plantilla: Reembolsos con IA'); ?>
            
            <div class="container-fluid p-3">
                <div class="row mb-4 align-items-center">
                    <div class="col">
                    </div>
                </div>

                <!-- Historial -->
                <div class="card premium-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover cupones-table" id="tablaReembolsos">
                                <thead>
                                    <tr>
                                        <th data-column="fecha_solicitud" data-type="daterange">
                                            Fecha
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="proveedor_nombre" data-type="text">
                                            Proveedor
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="concepto" data-type="text">
                                            Concepto
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="ceco" data-type="list">
                                            CECO
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="total_cordobas" data-type="number">
                                            Monto
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="estado" data-type="list">
                                            Estado
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="usuario_nombre" data-type="text">
                                            Registrado por
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th style="width: 150px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaReembolsosBody">
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            Cargando registros...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Paginación -->
                <div class="d-flex justify-content-between align-items-center mt-3 mb-5">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0 registros-info">Mostrar:</label>
                        <select class="form-select form-select-sm" id="registrosPorPagina" 
                                style="width: auto;" onchange="cambiarRegistrosPorPagina()">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="mb-0 registros-info">registros</span>
                    </div>
                    <div id="paginacion"></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (tienePermiso('reembolsos_ia_plantilla', 'nuevo_registro', $cargoOperario)): ?>
    <a href="reembolsos_ia_nuevo.php" class="btn-floating-pitaya" title="Nuevo Resumen">
        <i class="fas fa-plus"></i>
    </a>
    <?php endif; ?>

    <!-- Modal de Ayuda Universal -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía: Reembolsos con IA
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-robot me-2"></i> Transcripción con IA
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Sube una foto clara de la factura para que la IA extraiga los conceptos y montos automáticamente.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-university me-2"></i> Cuentas Bancarias
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El sistema auto-completa los datos bancarios del proveedor seleccionado para facilitar el pago.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/reembolsos_ia_historial.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

</body>
</html>
