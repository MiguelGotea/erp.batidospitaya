<?php
// historial_informes.php

require_once 'models/Ticket.php';
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('agenda_mantenimiento', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}


$ticketModel = new Ticket();
$puedeVerTodos = tienePermiso('agenda_mantenimiento', 'todos_colaboradores', $cargoOperario);
$esAdminCaja = tienePermiso('mantenimiento', 'validar_caja_chica', $cargoOperario);

$filtros = [];
if (!$puedeVerTodos) {
    $filtros['cod_operario'] = $usuario['CodOperario'];
} else {
    if (isset($_GET['colaborador']) && !empty($_GET['colaborador'])) {
        $filtros['cod_operario'] = intval($_GET['colaborador']);
    }
}

$informes = $ticketModel->getHistorialInformes($filtros);
$colaboradores = $puedeVerTodos ? $ticketModel->getColaboradoresDisponibles() : [];

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Informes Diarios</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css">
    <link rel="stylesheet" href="../../core/assets/css/modales_premium.css">
    <style>
        .card-informe {
            transition: all 0.2s;
            border-radius: 12px;
        }

        .card-informe:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
        }

        .table-premium thead th {
            background: #f8f9fa;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #666;
            border-top: none;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: 0.2s;
        }

        .btn-action:hover {
            transform: scale(1.1);
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Control de Informes Diarios'); ?>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">Historial de Jornadas</h4>
                        <p class="text-muted small mb-0">Listado de reportes de mantenimiento y control de gastos</p>
                    </div>
                    <a href="agenda_colaborador.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                        <i class="fas fa-plus me-2"></i>Nuevo Reporte de Hoy
                    </a>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white p-3 border-0">
                        <form method="GET" class="row g-2 align-items-center">
                            <?php if ($puedeVerTodos): ?>
                                <div class="col-md-4">
                                    <select name="colaborador" class="form-select border-light bg-light">
                                        <option value="">Todos los colaboradores...</option>
                                        <?php foreach ($colaboradores as $c): ?>
                                            <option value="<?= $c['CodOperario'] ?>" <?= (isset($_GET['colaborador']) && $_GET['colaborador'] == $c['CodOperario']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['Nombre'] . ' ' . $c['Apellido']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-dark w-100 rounded-3">
                                    <i class="fas fa-filter me-2"></i>Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-premium mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Fecha</th>
                                        <th>Colaborador</th>
                                        <th>KM Recorrido</th>
                                        <th>Caja Chica</th>
                                        <th>Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($informes)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">No se encontraron reportes
                                                registrados</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($informes as $i): ?>
                                            <tr class="align-middle">
                                                <td class="ps-4">
                                                    <span class="fw-bold"><?= date('d/m/Y', strtotime($i['fecha'])) ?></span>
                                                    <br><small
                                                        class="text-muted"><?= date('h:i A', strtotime($i['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-primary bg-opacity-10 text-primary rounded-circle me-2 d-flex align-items-center justify-content-center"
                                                            style="width: 32px; height: 32px;">
                                                            <?= substr($i['Nombre'], 0, 1) ?>
                                                        </div>
                                                        <span><?= htmlspecialchars($i['Nombre'] . ' ' . $i['Apellido']) ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($i['km_final']): ?>
                                                        <span class="badge bg-light text-dark border">
                                                            <?= number_format($i['km_final'] - $i['km_inicial'], 2) ?> KM
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">En proceso...</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span
                                                            class="fw-bold text-success">$<?= number_format($i['monto_caja_chica'], 2) ?></span>
                                                        <?php if ($i['foto_caja_chica']): ?>
                                                            <i class="fas fa-file-invoice text-muted cursor-zoom"
                                                                onclick="zoomFoto('uploads/caja/<?= $i['foto_caja_chica'] ?>')"></i>
                                                        <?php endif; ?>
                                                        <?php if ($esAdminCaja && $i['estado'] === 'creado'): ?>
                                                            <button class="btn btn-link btn-sm p-0"
                                                                onclick="modalValidarCaja(<?= $i['id'] ?>, <?= $i['monto_caja_chica'] ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="status-badge bg-<?= $i['estado'] === 'finalizado' ? 'success' : 'primary' ?> bg-opacity-10 text-<?= $i['estado'] === 'finalizado' ? 'success' : 'primary' ?>">
                                                        <?= strtoupper($i['estado'] === 'creado' ? 'Abierto' : 'Finalizado') ?>
                                                    </span>
                                                </td>
                                                <td class="text-center pe-4">
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <?php if ($i['estado'] === 'creado' && $i['cod_operario'] == $usuario['CodOperario']): ?>
                                                            <a href="agenda_colaborador.php"
                                                                class="btn-action bg-primary bg-opacity-10 text-primary"
                                                                title="Continuar Reporte">
                                                                <i class="fas fa-external-link-alt"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="imprimir_informe.php?id=<?= $i['id'] ?>" target="_blank"
                                                            class="btn-action bg-dark bg-opacity-10 text-dark"
                                                            title="Ver/Imprimir">
                                                            <i class="fas fa-print"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL VALIDAR CAJA CHICA (ADMIN) -->
    <div class="modal fade" id="validarCajaModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-cash-register me-2 text-success"></i>Validar Caja
                        Chica</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formCaja">
                        <input type="hidden" name="informe_id" id="caja_informe_id">
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Monto Entregado (Caja Chica) *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" name="monto" id="caja_monto"
                                    class="form-control form-control-lg" required>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">Foto del Voucher / Comprobante *</label>
                            <input type="file" name="foto_caja" id="caja_foto_input" class="form-control"
                                accept="image/*" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success rounded-pill px-4"
                        onclick="guardarValidacionCaja()">Confirmar Entrega</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ZOOM IMAGEN -->
    <div class="modal fade" id="zoomModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-transparent border-0 shadow-none">
                <div class="modal-body text-center p-0">
                    <img id="zoomImg" src="" class="img-fluid rounded-4 shadow-lg">
                    <button type="button" class="btn btn-dark btn-sm rounded-circle position-absolute top-0 end-0 m-3"
                        data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function zoomFoto(src) {
            $('#zoomImg').attr('src', src);
            new bootstrap.Modal(document.getElementById('zoomModal')).show();
        }

        function modalValidarCaja(id, monto) {
            $('#caja_informe_id').val(id);
            $('#caja_monto').val(monto);
            new bootstrap.Modal(document.getElementById('validarCajaModal')).show();
        }

        async function guardarValidacionCaja() {
            const form = document.getElementById('formCaja');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            Swal.fire({ title: 'Procesando...', didOpen: () => Swal.showLoading() });

            try {
                const response = await fetch('ajax/validar_caja_chica.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                if (res.success) {
                    Swal.fire('Éxito', 'Entrega de caja chica validada', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
            }
        }
    </script>
</body>

</html>