<?php
// compra_local_registro_pedidos.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/helpers/funciones.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];


// Verificar acceso
if (!tienePermiso('compra_local_registro_pedidos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Verificar permiso de edición
$puedeEditar = tienePermiso('compra_local_registro_pedidos', 'edicion', $cargoOperario);

// Obtener sucursal del usuario
$sucursales = obtenerSucursalesUsuario($usuario['CodOperario']);
$codigoSucursal = !empty($sucursales) ? $sucursales[0]['codigo'] : null;

if (!$codigoSucursal) {
    die('Error: No se pudo determinar la sucursal del usuario.');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Pedidos de Insumos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/compra_local_registro_pedidos.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>


<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Registro de Pedidos de Insumos'); ?>

            <div class="container-fluid p-3">
                <!-- Tabla de Productos -->
                <div id="productos-container">
                    <div class="loader-container">
                        <div class="loader"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Registro de Pedidos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold small">
                                        <i class="fas fa-calendar-day me-2"></i> Pedido de HOY
                                    </h6>
                                    <p class="x-small text-muted mb-0">
                                        Los pedidos registrados HOY llegan MAÑANA. Plazo límite: 12:00 PM.
                                        Después de esta hora, la columna se bloquea (🔒).
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-success border-bottom pb-2 fw-bold small">
                                        <i class="fas fa-calendar-plus me-2"></i> Pedido de MAÑANA
                                    </h6>
                                    <p class="x-small text-muted mb-0">
                                        Vea y registre pedidos para el día siguiente sin límite de tiempo.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lógica de Cálculo -->
                    <div class="bg-white border rounded p-3 mb-3">
                        <h6 class="fw-bold text-dark border-bottom pb-2">
                            <i class="fas fa-calculator me-2 text-secondary"></i> Lógica del Stock Mínimo
                        </h6>
                        <p class="small mb-2">Se calcula sumando la demanda proyectada desde el conteo actual hasta que
                            llegue el <u>siguiente</u> despacho.</p>

                        <div class="row g-2">
                            <div class="col-md-5">
                                <div class="p-2 border rounded bg-light h-100">
                                    <span class="badge bg-secondary mb-2">Componentes</span>
                                    <ul class="list-unstyled x-small mb-0">
                                        <li>• <strong>1. Día en curso:</strong> Demanda de hoy (~85% del día).</li>
                                        <li>• <strong>2. Días de Despacho:</strong> Demanda entre entregas.</li>
                                        <li>• <strong>3. Contingencia:</strong> Días extra de seguridad.</li>
                                        <li>• <strong>Restricción:</strong> Vida Útil limita la suma total.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="p-2 border rounded bg-info bg-opacity-10 h-100">
                                    <span class="badge bg-info text-dark mb-2">Ejemplo Práctico</span>
                                    <div class="x-small">
                                        <strong>Producto:</strong> Galonera de Leche (Consumo Base: 10)<br>
                                        <strong>Escenario:</strong> Pedido Lunes (llega Mar). Siguiente: Jue.
                                        Contingencia: 1.<br>
                                        <div class="mt-1 p-1 bg-white rounded border">
                                            1. <strong>Hoy (Lun):</strong> 8.5 gal. (Remanente 9AM-9PM)<br>
                                            2. <strong>Mar (Factor 1.2):</strong> + 12 gal.<br>
                                            3. <strong>Mie (Factor 1.0):</strong> + 10 gal.<br>
                                            4. <strong>Contingencia (1 día):</strong> + 10 gal.<br>
                                            <strong>Stock Mín:</strong> 8.5 + 12 + 10 + 10 = 40.5 → <strong>41</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 small mb-0">
                        <strong><i class="fas fa-info-circle me-1"></i> Nota:</strong>
                        Los pedidos se guardan automáticamente al ingresar la cantidad.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Ajuste de z-index para evitar que el backdrop cubra el modal */
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
        const codigoSucursal = '<?php echo $codigoSucursal; ?>';
    </script>
    <script src="js/compra_local_registro_pedidos.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>