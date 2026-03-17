<?php
/**
 * Historial y Nueva Solicitud de Reembolsos
 * Ubicación: /modulos/compras/reembolsos_historial.php
 */

@session_start();
require_once '../../core/database/conexion.php';

// Verificar permisos (asumiendo sistema de permisos existente)
// if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit(); }

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
    
    <!-- Estilos base del ERP -->
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .premium-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            background: #fff;
            transition: transform 0.3s ease;
        }
        .btn-pitaya {
            background: linear-gradient(45deg, #ff0080, #ff8c00);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: bold;
        }
        .btn-pitaya:hover {
            opacity: 0.9;
            color: white;
            transform: scale(1.05);
        }
        .excel-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .excel-table th, .excel-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .excel-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .excel-input {
            width: 100%;
            border: none;
            background: transparent;
        }
        .excel-input:focus {
            outline: 2px solid #ff0080;
            background: #fff;
        }
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .preview-img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="fw-bold">
                <i class="fas fa-file-invoice-dollar text-primary"></i> 
                Gestión de Reembolsos
            </h2>
            <p class="text-muted">Historial y Transcripción de Gastos con IA</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-pitaya" data-bs-toggle="modal" data-bs-target="#modalNuevoReembolso">
                <i class="fas fa-plus"></i> Nueva Solicitud
            </button>
        </div>
    </div>

    <!-- Historial -->
    <div class="card premium-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Concepto</th>
                            <th>CECO</th>
                            <th>Monto (C$)</th>
                            <th>Estado</th>
                            <th>Usuario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $reg): ?>
                        <tr>
                            <td><?= $reg['fecha_solicitud'] ?></td>
                            <td><?= $reg['proveedor_nombre'] ?? 'N/A' ?></td>
                            <td><?= $reg['concepto'] ?></td>
                            <td><?= $reg['ceco'] ?></td>
                            <td class="fw-bold">C$ <?= number_format($reg['total_cordobas'], 2) ?></td>
                            <td>
                                <span class="badge bg-<?= $reg['estado'] == 'pendiente' ? 'warning' : 'success' ?>">
                                    <?= strtoupper($reg['estado']) ?>
                                </span>
                            </td>
                            <td><?= $reg['usuario_nombre'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="verDetalle(<?= $reg['id'] ?>)">
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

<!-- Modal Nueva Solicitud -->
<div class="modal fade" id="modalNuevoReembolso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Formato de Solicitud de Reembolso</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formReembolso">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Proveedor</label>
                            <select id="id_proveedor" class="form-select select2" onchange="cargarDatosProveedor(this.value)">
                                <option value="">Seleccione...</option>
                                <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Cuenta</label>
                            <input type="text" id="cuenta_bancaria" class="form-control" readonly placeholder="Auto-completado">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Banco</label>
                            <input type="text" id="banco_proveedor" class="form-control" readonly placeholder="Auto-completado">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Fecha</label>
                            <input type="date" id="fecha_solicitud" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Concepto</label>
                            <input type="text" id="concepto" class="form-control" placeholder="Ej: Gastos de combustible marzo">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">CECO</label>
                            <input type="text" id="ceco" class="form-control" placeholder="Centro de Costo">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Subir Factura (IA)</label>
                            <input type="file" id="foto_factura" class="form-control" accept="image/*" onchange="procesarFoto(this)">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="excel-table" id="tablaDetalles">
                            <thead>
                                <tr>
                                    <th style="width: 100px;">Cantidad</th>
                                    <th>Detalle</th>
                                    <th style="width: 150px;">Total (C$)</th>
                                    <th style="width: 100px;">Imagen</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="bodyDetalles">
                                <!-- Filas dinámicas -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="text-end fw-bold">TOTAL:</td>
                                    <td class="fw-bold" id="labelTotal">C$ 0.00</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-pitaya" onclick="guardarSolicitud()">
                    <i class="fas fa-save"></i> Guardar Solicitud
                </button>
            </div>
        </div>
    </div>
</div>

<div class="loading-overlay" id="loader">
    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
    <h5 class="mt-3">Procesando con IA...</h5>
    <p class="text-muted">Extrayendo datos de la factura</p>
</div>

<script src="../../js/jquery.min.js"></script>
<script src="../../js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let itemsActuales = [];
let id_cuenta_proveedor = null;

function cargarDatosProveedor(id) {
    if (!id) return;
    $.get('ajax/reembolsos_get_proveedor_data.php', { id_proveedor: id }, function(res) {
        if (res.success && res.data) {
            $('#cuenta_bancaria').val(res.data.numero_cuenta);
            $('#banco_proveedor').val(res.data.banco);
            id_cuenta_proveedor = res.data.id;
        } else {
            $('#cuenta_bancaria').val('');
            $('#banco_proveedor').val('');
            id_cuenta_proveedor = null;
        }
    });
}

function procesarFoto(input) {
    if (!input.files || !input.files[0]) return;

    let formData = new FormData();
    formData.append('foto', input.files[0]);

    $('#loader').css('display', 'flex');

    $.ajax({
        url: 'ajax/reembolsos_procesar_foto.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            $('#loader').hide();
            if (res.success) {
                Swal.fire('Éxito', 'IA transcribió la factura correctamente usando ' + res.proveedor, 'success');
                agregarAFilas(res.items, res.foto_path);
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        },
        error: function() {
            $('#loader').hide();
            Swal.fire('Error', 'Error de conexión', 'error');
        }
    });
}

function agregarAFilas(items, fotoPath) {
    items.forEach(item => {
        item.foto_path = fotoPath;
        itemsActuales.push(item);
        
        let row = `
            <tr data-index="${itemsActuales.length - 1}">
                <td><input type="number" class="excel-input" value="${item.cantidad}" onchange="actualizarDato(${itemsActuales.length - 1}, 'cantidad', this.value)"></td>
                <td><input type="text" class="excel-input" value="${item.detalle}" onchange="actualizarDato(${itemsActuales.length - 1}, 'detalle', this.value)"></td>
                <td><input type="number" class="excel-input" value="${item.total_cordobas}" onchange="actualizarDato(${itemsActuales.length - 1}, 'total_cordobas', this.value)"></td>
                <td><img src="../../${fotoPath}" class="preview-img" onclick="window.open('../../${fotoPath}')"></td>
                <td><button class="btn btn-sm btn-danger" onclick="eliminarFila(${itemsActuales.length - 1})"><i class="fas fa-trash"></i></button></td>
            </tr>
        `;
        $('#bodyDetalles').append(row);
    });
    calcularTotal();
}

function actualizarDato(index, campo, valor) {
    itemsActuales[index][campo] = valor;
    calcularTotal();
}

function eliminarFila(index) {
    itemsActuales.splice(index, 1);
    renderTable();
}

function renderTable() {
    $('#bodyDetalles').empty();
    itemsActuales.forEach((item, i) => {
        let row = `
            <tr data-index="${i}">
                <td><input type="number" class="excel-input" value="${item.cantidad}" onchange="actualizarDato(${i}, 'cantidad', this.value)"></td>
                <td><input type="text" class="excel-input" value="${item.detalle}" onchange="actualizarDato(${i}, 'detalle', this.value)"></td>
                <td><input type="number" class="excel-input" value="${item.total_cordobas}" onchange="actualizarDato(${i}, 'total_cordobas', this.value)"></td>
                <td><img src="../../${item.foto_path}" class="preview-img" onclick="window.open('../../${item.foto_path}')"></td>
                <td><button class="btn btn-sm btn-danger" onclick="eliminarFila(${i})"><i class="fas fa-trash"></i></button></td>
            </tr>
        `;
        $('#bodyDetalles').append(row);
    });
    calcularTotal();
}

function calcularTotal() {
    let total = 0;
    itemsActuales.forEach(item => {
        total += parseFloat(item.total_cordobas) || 0;
    });
    $('#labelTotal').text('C$ ' + total.toLocaleString('en-US', { minimumFractionDigits: 2 }));
}

function guardarSolicitud() {
    let data = {
        id_proveedor: $('#id_proveedor').val(),
        id_cuenta_proveedor: id_cuenta_proveedor,
        concepto: $('#concepto').val(),
        ceco: $('#ceco').val(),
        fecha_solicitud: $('#fecha_solicitud').val(),
        total_cordobas: itemsActuales.reduce((acc, curr) => acc + (parseFloat(curr.total_cordobas) || 0), 0),
        items: itemsActuales
    };

    if (!data.concepto) {
        Swal.fire('Error', 'Debe ingresar un concepto', 'warning');
        return;
    }

    if (itemsActuales.length === 0) {
        Swal.fire('Error', 'Debe agregar al menos un gasto (suba una factura)', 'warning');
        return;
    }

    $.ajax({
        url: 'ajax/reembolsos_guardar.php',
        type: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        success: function(res) {
            if (res.success) {
                Swal.fire('Guardado', res.message, 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }
    });
}

function verDetalle(id) {
    Swal.fire('Info', 'Funcionalidad de ver detalle del ID ' + id + ' en construcción.', 'info');
}
</script>

</body>
</html>
