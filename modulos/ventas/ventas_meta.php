<?php
// ventas_meta.php
// Registro de proyecciones de ventas mensuales por sucursal

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('ventas_meta', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeEditar = tienePermiso('ventas_meta', 'edicion', $cargoOperario);
$anioActual = date('Y');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyección de Ventas (Meta)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/ventas_meta_estilos.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Proyección de Ventas (Meta)'); ?>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-end align-items-center mb-4">
                    <div class="year-navigator">
                        <button class="btn btn-outline-secondary" onclick="cambiarAnio(-1)">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span class="mx-3 fw-bold fs-5" id="valAnio"><?php echo $anioActual; ?></span>
                        <button class="btn btn-outline-secondary" onclick="cambiarAnio(1)">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover meta-table mb-0">
                                <thead>
                                    <tr>
                                        <th class="sticky-col">Sucursal</th>
                                        <th>Ene</th>
                                        <th>Feb</th>
                                        <th>Mar</th>
                                        <th>Abr</th>
                                        <th>May</th>
                                        <th>Jun</th>
                                        <th>Jul</th>
                                        <th>Ago</th>
                                        <th>Sep</th>
                                        <th>Oct</th>
                                        <th>Nov</th>
                                        <th>Dic</th>
                                        <th class="fw-bold bg-light">Anual</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaMetasBody">
                                    <!-- Cargado vía AJAX -->
                                    <tr>
                                        <td colspan="14" class="text-center py-5">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Cargando...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-muted small">
                    <i class="bi bi-info-circle"></i> Haz clic en una celda para editar. Las metas se ingresan y
                    muestran en valores brutos.
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const PUEDE_EDITAR = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
        let anioSeleccionado = <?php echo $anioActual; ?>;
    </script>
    <script src="js/ventas_meta_funciones.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>