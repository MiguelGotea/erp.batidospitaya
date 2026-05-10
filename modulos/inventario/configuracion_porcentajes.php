<?php
/* ============================================================
   PÁGINA: Configuración de Porcentajes de Inventario
   Ruta: modulos/inventario/configuracion_porcentajes.php
   ============================================================ */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargo = $usuario['CodNivelesCargos'];

// Permisos: 27, 16, 49, 55 para vista
if (!tienePermiso('porcentajes_inventario', 'vista', $cargo)) {
    header('Location: /index.php');
    exit();
}

$puedeEditar = tienePermiso('porcentajes_inventario', 'edicion', $cargo);
$version = mt_rand(1, 10000);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Porcentajes · Pitaya ERP</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <style>
        .input-pct { width: 100px; text-align: center; }
        .table-middle td { vertical-align: middle; }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargo); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Configuración de Porcentajes por Sucursal'); ?>

            <div class="p-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40%;">Sucursal</th>
                                        <th class="text-center" style="width: 25%;">Porcentaje Congelados (B, D, F)</th>
                                        <th class="text-center" style="width: 25%;">Porcentaje Frescos (A, C)</th>
                                        <th class="text-center" style="width: 10%;">Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyPorcentajes">
                                    <!-- Cargado por AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Información -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i> Información de Porcentajes de Despacho</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>¿Para qué sirven estos porcentajes?</h6>
                    <p>Estos valores definen cómo se divide el <strong>Pedido Sugerido</strong> total de la semana en los diferentes despachos (Pedido 1 y Pedido 2).</p>
                    
                    <hr>
                    
                    <h6>Distribución por Categorías:</h6>
                    <ul>
                        <li><strong>Categorías B, D, F:</strong> Utilizan el <strong>Porcentaje de Congelados</strong>.</li>
                        <li><strong>Categorías A, C:</strong> Utilizan el <strong>Porcentaje de Frescos</strong>.</li>
                        <li><strong>Categorías E, G:</strong> Siempre se envían al 100% en el Pedido 1 (no se dividen).</li>
                    </ul>
                    
                    <hr>
                    
                    <h6>Cálculo del Desglose:</h6>
                    <div class="bg-light p-3 rounded">
                        <p class="mb-1"><code>PEDIDO 1 = Pedido Sugerido × (Porcentaje / 100)</code></p>
                        <p class="mb-0"><code>PEDIDO 2 = Pedido Sugerido - PEDIDO 1</code></p>
                    </div>
                    <p class="mt-2 small text-muted">Ejemplo: Si el sugerido es 10 unidades y el porcentaje es 60%, el Pedido 1 será de 6 unidades y el Pedido 2 de 4 unidades.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>;

        $(document).ready(function() {
            cargarLista();
        });

        function cargarLista() {
            $.ajax({
                url: 'ajax/porcentajes_operaciones.php',
                data: { accion: 'get_lista' },
                dataType: 'json',
                success: function(res) {
                    if (res.ok) {
                        const tbody = $('#tbodyPorcentajes');
                        tbody.empty();
                        res.data.forEach(s => {
                            const readonly = puedeEditar ? '' : 'disabled';
                            tbody.append(`
                                <tr id="row-${s.codigo}">
                                    <td class="fw-bold">${s.nombre}</td>
                                    <td class="text-center">
                                        <div class="input-group input-group-sm mx-auto" style="width:120px;">
                                            <input type="number" step="1" class="form-control input-pct p-cong" 
                                                   value="${(parseFloat(s.porcentaje_congelados) * 100).toFixed(0)}" ${readonly}
                                                   onchange="guardarFila('${s.codigo}')">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="input-group input-group-sm mx-auto" style="width:120px;">
                                            <input type="number" step="1" class="form-control input-pct p-fres" 
                                                   value="${(parseFloat(s.porcentaje_frescos) * 100).toFixed(0)}" ${readonly}
                                                   onchange="guardarFila('${s.codigo}')">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                    <td class="text-center" style="min-width:100px;">
                                        <span class="badge bg-success d-none" id="status-${s.codigo}"><i class="bi bi-check-all"></i> OK</span>
                                    </td>
                                </tr>
                            `);
                        });
                    }
                }
            });
        }

        function guardarFila(codigo) {
            const row = $(`#row-${codigo}`);
            const pCong = parseFloat(row.find('.p-cong').val()) / 100;
            const pFres = parseFloat(row.find('.p-fres').val()) / 100;
            const badge = $(`#status-${codigo}`);

            $.ajax({
                url: 'ajax/porcentajes_operaciones.php',
                method: 'POST',
                data: {
                    accion: 'save',
                    cod_sucursal: codigo,
                    porcentaje_congelados: pCong,
                    porcentaje_frescos: pFres
                },
                dataType: 'json',
                success: function(res) {
                    if (res.ok) {
                        badge.removeClass('d-none').fadeIn();
                        setTimeout(() => badge.fadeOut(() => badge.addClass('d-none')), 3000);
                    } else {
                        Swal.fire('Error', res.msg, 'error');
                    }
                }
            });
        }
    </script>
</body>
</html>
