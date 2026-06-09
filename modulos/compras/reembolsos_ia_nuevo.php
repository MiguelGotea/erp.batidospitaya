<?php
/**
 * Nueva Solicitud de Reembolso con IA
 * Ubicación: /modulos/compras/reembolsos_ia_nuevo.php
 */

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

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

// Obtener Centros de Costos para el select
$stmtCeco = $conn->query("SELECT Codigo, Nombre FROM CentroCostos WHERE Activo = 1 ORDER BY Codigo ASC");
$cecos = $stmtCeco->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Estilos Globales y Específicos -->
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css">
    <link rel="stylesheet" href="css/reembolsos_ia_historial.css?v=<?php echo mt_rand(1, 10000); ?>">
    <style>
        .autocomplete-suggestions {
            position: absolute;
            z-index: 1000;
            background: white;
            border: 1px solid #ddd;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .autocomplete-suggestion {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.9em;
        }
        .autocomplete-suggestion:hover {
            background-color: #f1f1f1;
            color: #51B8AC;
        }
        .position-relative { position: relative; }
        .reembolso-section {
            background: linear-gradient(135deg, rgba(81,184,172,0.06), rgba(81,184,172,0.02));
            border: 1px solid rgba(81,184,172,0.25);
            border-radius: 12px;
            padding: 12px 16px 4px;
        }
        .reembolso-section .section-label {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #51B8AC;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        /* ── Cámara Premium ── */
        #camera-viewport {
            position: relative;
            background: #000;
            min-height: 300px;
            cursor: crosshair;
            overflow: hidden;
        }
        #video {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        /* Anillo de enfoque táctil */
        #focus-ring {
            position: absolute;
            width: 70px;
            height: 70px;
            border: 2px solid #FFD700;
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(1.6);
            opacity: 0;
            pointer-events: none;
            transition: transform 0.25s ease, opacity 0.25s ease;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.4);
        }
        #focus-ring.active {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
        #focus-ring.locked {
            border-color: #00FF88;
            opacity: 0.7;
        }
        /* Rejilla de enfoque (corners) */
        #focus-ring::before, #focus-ring::after {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            border-color: inherit;
            border-style: solid;
        }
        #focus-ring::before {
            top: -1px; left: -1px;
            border-width: 2px 0 0 2px;
        }
        #focus-ring::after {
            bottom: -1px; right: -1px;
            border-width: 0 2px 2px 0;
        }
        /* Grid de ayuda visual (regla de tercios) */
        #cam-grid {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0.15;
            background-image:
                linear-gradient(to right, #fff 1px, transparent 1px),
                linear-gradient(to bottom, #fff 1px, transparent 1px);
            background-size: 33.33% 33.33%;
        }
        /* Toast de enfoque */
        #focus-toast {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.65);
            color: #fff;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            white-space: nowrap;
        }
        /* Controles inferiores de cámara */
        .cam-controls {
            background: #111;
            padding: 10px 16px 14px;
        }
        .cam-controls .zoom-label {
            color: #aaa;
            font-size: 0.72rem;
            margin-bottom: 4px;
        }
        #zoomRange {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 4px;
            background: #444;
            border-radius: 2px;
            outline: none;
        }
        #zoomRange::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: #FFD700;
            cursor: pointer;
            box-shadow: 0 0 4px rgba(255,215,0,0.6);
        }
        .btn-torch {
            background: transparent;
            border: 1.5px solid #555;
            color: #aaa;
            border-radius: 50%;
            width: 42px; height: 42px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .btn-torch.on {
            border-color: #FFD700;
            color: #FFD700;
            box-shadow: 0 0 8px rgba(255,215,0,0.5);
        }
        .btn-capture {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: #fff;
            border: 4px solid rgba(255,255,255,0.4);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            color: #333;
            transition: transform 0.1s, background 0.1s;
            cursor: pointer;
        }
        .btn-capture:active {
            transform: scale(0.92);
            background: #ddd;
        }
    </style>
</head>
<body>

    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, $tituloPagina); ?>
            
            <script>
                const editingId = <?= json_encode($editingId) ?>;
                const visitaId = <?= json_encode(isset($_GET['visita_id']) ? (int)$_GET['visita_id'] : null) ?>;
                const dataCecos = <?= json_encode($cecos) ?>;
                const dataProveedores = <?= json_encode($proveedores) ?>;
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

                                <!-- FILA 1: Proveedores lado a lado -->
                                <div class="col-md-5">
                                    <div class="reembolso-section h-100">
                                        <div class="section-label"><i class="fas fa-store me-1"></i>Proveedor de la Compra</div>
                                        <div class="position-relative">
                                            <label class="form-label small fw-bold text-secondary">Proveedor</label>
                                            <input type="text" id="proveedor_nombre" class="form-control border-0 shadow-sm" placeholder="Escribe para buscar..." oninput="filtrarProveedor(this.value)" autocomplete="off">
                                            <input type="hidden" id="id_proveedor" value="">
                                            <div id="proveedor-suggestions" class="autocomplete-suggestions"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <div class="reembolso-section h-100">
                                        <div class="section-label"><i class="fas fa-hand-holding-usd me-1"></i>Reembolsar a</div>
                                        <div class="row g-2">
                                            <div class="col-md-5 position-relative">
                                                <label class="form-label small fw-bold text-secondary">Persona / Proveedor a Reembolsar</label>
                                                <input type="text" id="reembolso_proveedor_nombre" class="form-control border-0 shadow-sm" placeholder="Por defecto: mismo proveedor" oninput="filtrarProveedorReembolso(this.value)" autocomplete="off">
                                                <input type="hidden" id="id_proveedor_reembolso" value="">
                                                <div id="reembolso-proveedor-suggestions" class="autocomplete-suggestions"></div>
                                            </div>
                                            <div class="col-md-7">
                                                <label class="form-label small fw-bold text-secondary">Cuenta Bancaria para el Reembolso</label>
                                                <select id="select_cuenta_reembolso" class="form-select border-0 shadow-sm" disabled onchange="seleccionarCuentaReembolso(this)">
                                                    <option value="">— Selecciona el proveedor a reembolsar —</option>
                                                </select>
                                                <input type="hidden" id="cuenta_bancaria" value="">
                                                <input type="hidden" id="banco_proveedor" value="">
                                                <input type="hidden" id="moneda" value="Cordobas">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- FILA 2: Fecha + Concepto + CECO (siempre juntos) -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-secondary">Fecha de Solicitud</label>
                                    <input type="date" id="fecha_solicitud" class="form-control border-0 shadow-sm" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Concepto General del Reembolso</label>
                                    <input type="text" id="concepto" class="form-control border-0 shadow-sm" placeholder="Ej: Viáticos de viaje a Occidente - Marzo 2026">
                                </div>
                                <div class="col-md-4 position-relative">
                                    <label class="form-label small fw-bold text-secondary">Centro de Costo (CECO)</label>
                                    <input type="text" id="ceco_nombre" class="form-control border-0 shadow-sm" placeholder="Escribe para buscar CECO..." oninput="filtrarCECO(this.value)" autocomplete="off">
                                    <input type="hidden" id="ceco" value="">
                                    <div id="ceco-suggestions" class="autocomplete-suggestions"></div>
                                </div>

                                <!-- FILA 3: Área Multimedia IA -->
                                <div class="col-12">
                                    <div class="reembolso-section" style="background: linear-gradient(135deg, rgba(13,110,253,0.06), rgba(13,110,253,0.02)); border-color: rgba(13,110,253,0.2);">
                                        <div class="section-label" style="color: #0d6efd;"><i class="fas fa-robot me-1"></i>Transcripción con Inteligencia Artificial</div>
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-8">
                                                <div class="input-group">
                                                    <span class="input-group-text bg-primary text-white border-0"><i class="fas fa-robot"></i></span>
                                                    <input type="file" id="foto_factura" class="form-control border-0 shadow-sm" accept="image/*,application/pdf" onchange="procesarFoto(this)">
                                                    <button type="button" class="btn btn-primary" onclick="abrirCamara()" title="Tomar Foto con Cámara">
                                                        <i class="fas fa-camera"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div id="statusIA" class="small text-muted px-1"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="table-responsive border rounded-4 bg-white shadow-sm overflow-hidden mb-4">
                                <table class="table table-bordered excel-table mb-0" id="tablaDetalles">
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">Cant.</th>
                                            <th>Detalle del Gasto</th>
                                            <th style="width: 200px;" id="thTotalSugerido">Total Sugerido (C$)</th>
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

                            <div class="row align-items-center mb-4">
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-4" onclick="agregarFilaManual()">
                                        <i class="fas fa-plus me-2"></i> Agregar Detalle Manualmente
                                    </button>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" id="chkImprimirFotos" checked>
                                        <label class="form-check-label fw-bold text-secondary" for="chkImprimirFotos">Imprimir fotos de facturas</label>
                                    </div>
                                </div>
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

    <!-- Modal para Cámara Premium -->
    <div class="modal fade" id="modalCamara" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px !important; overflow: hidden;">

                <!-- Header -->
                <div class="modal-header bg-dark text-white border-0 py-2 px-3">
                    <h6 class="modal-title mb-0"><i class="fas fa-camera me-2"></i> Capturar Factura</h6>
                    <div class="d-flex align-items-center gap-2">
                        <span id="cam-focus-status" class="badge bg-secondary" style="font-size:0.65rem;">AUTO</span>
                        <button type="button" class="btn-close btn-close-white" onclick="cerrarCamara()"></button>
                    </div>
                </div>

                <!-- Viewport con overlay -->
                <div id="camera-viewport" style="min-height: 320px;">
                    <video id="video" autoplay playsinline muted></video>
                    <!-- Rejilla de regla de tercios -->
                    <div id="cam-grid"></div>
                    <!-- Anillo de enfoque táctil -->
                    <div id="focus-ring"></div>
                    <!-- Toast de estado de enfoque -->
                    <div id="focus-toast">Toca para enfocar</div>
                    <canvas id="canvas" style="display:none;"></canvas>
                </div>

                <!-- Controles -->
                <div class="cam-controls">
                    <!-- Zoom slider -->
                    <div id="zoom-control" style="display:none;">
                        <div class="zoom-label d-flex justify-content-between">
                            <span><i class="fas fa-search-minus me-1"></i>Zoom</span>
                            <span id="zoom-value">1×</span>
                        </div>
                        <input type="range" id="zoomRange" min="1" max="5" step="0.1" value="1" oninput="aplicarZoom(this.value)">
                    </div>

                    <!-- Botones acción -->
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <!-- Cancelar -->
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 text-white border-secondary" onclick="cerrarCamara()">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>

                        <!-- Disparo -->
                        <button type="button" class="btn-capture" onclick="capturarFoto()" title="Tomar foto">
                            <i class="fas fa-circle" style="color:#e74c3c;"></i>
                        </button>

                        <!-- Linterna -->
                        <button type="button" id="btnTorch" class="btn-torch" onclick="toggleLinterna()" title="Linterna" style="display:none;">
                            <i class="fas fa-bolt"></i>
                        </button>
                        <!-- Placeholder si no hay linterna -->
                        <div id="btnTorchPlaceholder" style="width:42px;"></div>
                    </div>
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
