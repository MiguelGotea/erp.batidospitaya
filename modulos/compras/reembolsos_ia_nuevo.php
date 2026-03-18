<?php
/**
 * Nueva Solicitud de Reembolso con IA
 * Ubicación: /modulos/compras/reembolsos_ia_nuevo.php
 */

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/database/conexion.php';
require_once '../../core/helpers/funciones.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('reembolsos_ia_plantilla', 'nuevo_registro', $cargoOperario)) {
    header('Location: reembolsos_ia_historial.php');
    exit();
}

// Obtener proveedores para el select
$stmtProv = $conn->query("SELECT id, nombre FROM proveedores WHERE vigente = 1 ORDER BY nombre ASC");
$proveedores = $stmtProv->fetchAll(PDO::FETCH_ASSOC);

// Detectar modo edición
$editingId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$tituloPagina = $editingId ? 'Editar Solicitud IA' : 'Nueva Solicitud: Reembolsos con IA';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Solicitud IA | Pitaya ERP</title>
    
    <!-- Librerías estándar -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Estilos Globales y Específicos -->
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css">
    <link rel="stylesheet" href="css/reembolsos_ia_historial.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>

    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, $tituloPagina); ?>
            
            <script>
                const editingId = <?= json_encode($editingId) ?>;
            </script>
            
            <div class="container-fluid p-4">

                <div class="card premium-card border-0">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0 text-primary">
                            <i class="fas fa-file-invoice-dollar me-2"></i>
                            Formato de Resumen de Gasto
                        </h5>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <form id="formReembolso">
                            <div class="row g-3 mb-4 p-3 bg-light rounded-4 shadow-sm">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Proveedor</label>
                                    <select id="id_proveedor" class="form-select border-0 shadow-sm" onchange="cargarDatosProveedor(this.value)">
                                        <option value="">Seleccione proveedor...</option>
                                        <?php foreach ($proveedores as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Cuenta Bancaria (Auto)</label>
                                    <input type="text" id="cuenta_bancaria" class="form-control border-0 shadow-sm bg-white" readonly placeholder="Esperando proveedor...">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Banco (Auto)</label>
                                    <input type="text" id="banco_proveedor" class="form-control border-0 shadow-sm bg-white" readonly placeholder="Esperando proveedor...">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Fecha de Solicitud</label>
                                    <input type="date" id="fecha_solicitud" class="form-control border-0 shadow-sm" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold text-secondary">Concepto General del Reembolso</label>
                                    <input type="text" id="concepto" class="form-control border-0 shadow-sm" placeholder="Ej: Viáticos de viaje a Occidente - Marzo 2026">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Centro de Costo (CECO)</label>
                                    <input type="text" id="ceco" class="form-control border-0 shadow-sm" placeholder="ID o Nombre del CECO">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold text-primary">
                                        <i class="fas fa-camera me-1"></i> Subir Foto de Factura para Transcribir con IA
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-primary text-white border-0"><i class="fas fa-robot"></i></span>
                                        <input type="file" id="foto_factura" class="form-control border-0 shadow-sm" accept="image/*,application/pdf" onchange="procesarFoto(this)">
                                        <button type="button" class="btn btn-primary" onclick="abrirCamara()" title="Tomar Foto">
                                            <i class="fas fa-camera"></i>
                                        </button>
                                    </div>
                                    <div id="statusIA" class="small mt-1 text-muted"></div>
                                </div>
                            </div>

                            <div class="table-responsive border rounded-4 bg-white shadow-sm overflow-hidden mb-4">
                                <table class="excel-table mb-0" id="tablaDetalles">
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">Cant.</th>
                                            <th>Detalle del Gasto</th>
                                            <th style="width: 200px;">Total Sugerido (C$)</th>
                                            <th style="width: 120px;" class="text-center">Evidencia</th>
                                            <th style="width: 60px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="bodyDetalles">
                                        <!-- Filas generadas por IA -->
                                        <tr class="empty-row">
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="fas fa-cloud-upload-alt fa-3x mb-3 d-block opacity-25"></i>
                                                Sube una foto para comenzar la extracción automática.
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-light border-top">
                                        <tr>
                                            <td colspan="2" class="text-end fw-bold py-3 text-secondary">RESUMEN TOTAL:</td>
                                            <td class="fw-bold text-primary py-3 fs-5" id="labelTotal">C$ 0.00</td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="mb-4">
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-4" onclick="agregarFilaManual()">
                                    <i class="fas fa-plus me-2"></i> Agregar Detalle Manualmente
                                </button>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12 text-end">
                                    <button type="button" class="btn btn-secondary px-5 rounded-pill me-2" onclick="location.href='reembolsos_ia_historial.php'">
                                        Cancelar
                                    </button>
                                    <button type="button" class="btn btn-pitaya px-5" onclick="guardarSolicitud()">
                                        <i class="fas fa-save me-2"></i> Finalizar y Guardar Solicitud
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda Universal -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-lightbulb me-2"></i>
                        Ayuda: Nueva Solicitud con IA
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <h6>¿Cómo funciona?</h6>
                    <ol class="small">
                        <li>Selecciona el <strong>Proveedor</strong> para cargar los datos de pago.</li>
                        <li>Ingresa los datos generales (Concepto, CECO, Fecha).</li>
                        <li>Sube una foto de la factura. La IA extraerá automáticamente la cantidad, el detalle y el monto total en Córdobas.</li>
                        <li>Revisa los datos en la tabla (puedes editarlos manualmente si es necesario).</li>
                        <li>Haz clic en <strong>Finalizar y Guardar</strong> para registrar la solicitud.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="loader">
        <div class="spinner-border text-primary" role="status" style="width: 3.5rem; height: 3.5rem; border-width: 0.3em;"></div>
        <h5 class="mt-4 fw-bold">Inteligencia Artificial Procesando...</h5>
        <div class="progress mt-2" style="width: 250px; height: 6px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%"></div>
        </div>
        <p class="text-muted mt-3">Estamos leyendo tu factura, por favor espera.</p>
    </div>

    <!-- Modal para Cámara -->
    <div class="modal fade" id="modalCamara" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 25px !important; overflow: hidden;">
                <div class="modal-header bg-dark text-white border-0">
                    <h5 class="modal-title"><i class="fas fa-camera me-2"></i> Capturar Factura</h5>
                    <button type="button" class="btn-close btn-close-white" onclick="cerrarCamara()"></button>
                </div>
                <div class="modal-body p-0 position-relative bg-black" style="min-height: 300px;">
                    <video id="video" autoplay playsinline class="w-100 h-100" style="object-fit: cover;"></video>
                    <canvas id="canvas" style="display:none;"></canvas>
                </div>
                <div class="modal-footer border-0 bg-dark d-flex justify-content-between p-3">
                    <button type="button" class="btn btn-outline-light rounded-pill px-4" onclick="cerrarCamara()">Cancelar</button>
                    <button type="button" class="btn btn-primary rounded-circle" style="width: 60px; height: 60px;" onclick="capturarFoto()">
                        <i class="fas fa-camera fa-lg"></i>
                    </button>
                    <div style="width: 80px;"></div> <!-- Espaciador -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/reembolsos_ia_nuevo.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

</body>
</html>
