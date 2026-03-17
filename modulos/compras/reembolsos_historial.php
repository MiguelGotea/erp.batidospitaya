<?php
/**
 * Historial y Nueva Solicitud de Reembolsos
 * Ubicación: /modulos/compras/reembolsos_historial.php
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
if (!tienePermiso('reembolsos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Obtener proveedores para el select
$stmtProv = $conn->query("SELECT id, nombre FROM proveedores WHERE vigente = 1 ORDER BY nombre ASC");
$proveedores = $stmtProv->fetchAll(PDO::FETCH_ASSOC);

// Obtener historial
$stmtHist = $conn->prepare("
    SELECT s.*, p.nombre as proveedor_nombre, cp.banco, cp.numero_cuenta, o.Nombre as usuario_nombre
    FROM reembolsos_solicitudes s
    LEFT JOIN proveedores p ON s.id_proveedor = p.id
    LEFT JOIN cuenta_proveedor cp ON s.id_cuenta_proveedor = cp.id
    LEFT JOIN Operarios o ON s.usuario_registro = o.CodOperario
    ORDER BY s.created_at DESC
");
$stmtHist->execute();
$historial = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Reembolsos | Pitaya ERP</title>
    
    <!-- Librerías estándar -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Estilos Globales y Específicos -->
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css">
    <link rel="stylesheet" href="css/reembolsos_historial.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>

    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Gestión de Reembolsos'); ?>
            
            <div class="container-fluid p-3">
                <div class="row mb-4 align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-0">Historial de Solicitudes</h4>
                        <p class="text-muted small">Registro de gastos y reembolsos procesados con IA</p>
                    </div>
                    <div class="col-auto">
                        <?php if (tienePermiso('reembolsos', 'nuevo_registro', $cargoOperario)): ?>
                        <button class="btn btn-pitaya" data-bs-toggle="modal" data-bs-target="#modalNuevoReembolso">
                            <i class="fas fa-plus me-2"></i> Nueva Solicitud
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Historial -->
                <div class="card premium-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Proveedor</th>
                                        <th>Concepto</th>
                                        <th>CECO</th>
                                        <th>Monto (C$)</th>
                                        <th>Estado</th>
                                        <th>Registrado por</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($historial)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">No hay solicitudes registradas.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($historial as $reg): ?>
                                    <tr>
                                        <td><?= formatoFechaCorta($reg['fecha_solicitud']) ?></td>
                                        <td><?= $reg['proveedor_nombre'] ?? '<span class="text-muted">N/A</span>' ?></td>
                                        <td><?= $reg['concepto'] ?></td>
                                        <td><span class="badge bg-light text-dark"><?= $reg['ceco'] ?></span></td>
                                        <td class="fw-bold text-primary">C$ <?= number_format($reg['total_cordobas'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $reg['estado'] == 'pendiente' ? 'warning text-dark' : 'success' ?>">
                                                <?= strtoupper($reg['estado']) ?>
                                            </span>
                                        </td>
                                        <td><?= $reg['usuario_nombre'] ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary" onclick="verDetalle(<?= $reg['id'] ?>)" title="Ver Detalle">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nueva Solicitud (Premium Style) -->
    <div class="modal fade" id="modalNuevoReembolso" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-file-invoice-dollar text-primary me-2"></i>
                        Nuevo Resumen de Reembolso
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formReembolso">
                        <div class="row g-3 mb-4 p-3 bg-light rounded-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Proveedor</label>
                                <select id="id_proveedor" class="form-select border-0 shadow-sm" onchange="cargarDatosProveedor(this.value)">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($proveedores as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">N° Cuenta (Auto)</label>
                                <input type="text" id="cuenta_bancaria" class="form-control border-0 shadow-sm bg-white" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Banco (Auto)</label>
                                <input type="text" id="banco_proveedor" class="form-control border-0 shadow-sm bg-white" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Fecha</label>
                                <input type="date" id="fecha_solicitud" class="form-control border-0 shadow-sm" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Concepto General</label>
                                <input type="text" id="concepto" class="form-control border-0 shadow-sm" placeholder="Ej: Gastos de combustible Marzo 2026">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">CECO</label>
                                <input type="text" id="ceco" class="form-control border-0 shadow-sm" placeholder="Centro de Costo">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-primary">
                                    <i class="fas fa-robot me-1"></i> Transcribir Factura
                                </label>
                                <input type="file" id="foto_factura" class="form-control border-0 shadow-sm" accept="image/*" onchange="procesarFoto(this)">
                            </div>
                        </div>

                        <div class="table-responsive border rounded-3 bg-white">
                            <table class="excel-table mb-0" id="tablaDetalles">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Cant.</th>
                                        <th>Detalle del Gasto</th>
                                        <th style="width: 150px;">Total (C$)</th>
                                        <th style="width: 100px;" class="text-center">Evidencia</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="bodyDetalles">
                                    <!-- Filas generadas por IA -->
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <td colspan="2" class="text-end fw-bold py-3">MONTO TOTAL A REEMBOLSAR:</td>
                                        <td class="fw-bold text-primary py-3" id="labelTotal">C$ 0.00</td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top-0 p-4">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-pitaya px-4" onclick="guardarSolicitud()">
                        <i class="fas fa-check-circle me-1"></i> Confirmar y Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda Universal (OBLIGATORIO) -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Uso: Gestión de Reembolsos
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
                                        Al subir una foto de factura, el sistema utiliza modelos de visión (Gemini/OpenAI) 
                                        para extraer automáticamente la cantidad, el detalle y el monto total en Córdobas.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-university me-2"></i> Datos de Proveedor
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Al seleccionar un proveedor, el sistema recupera automáticamente su cuenta principal y banco 
                                        registrados en el sistema para facilitar la transferencia posterior.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="alert alert-info py-2 px-3 small rounded-3">
                                <strong><i class="fas fa-exclamation-circle me-1"></i> Nota:</strong>
                                <br>
                                Si la factura está en dólares, la IA intentará convertirla a Córdobas basándose en los datos visibles o indicará la moneda original en el detalle.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="loader">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
        <h5 class="mt-3 fw-bold">Procesando con IA...</h5>
        <p class="text-muted">Analizando imagen y extrayendo datos</p>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/reembolsos_historial.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

</body>
</html>
