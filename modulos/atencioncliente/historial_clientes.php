<?php
// historial_clientes.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('historial_clientes_club', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Clientes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/historial_clientes.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, false, 'Historial de Clientes'); ?>

            <div class="container-fluid p-3">
                <div class="d-flex justify-content-end mb-3">
                    <?php if (tienePermiso('historial_clientes_club', 'descargar', $cargoOperario)): ?>
                        <button class="btn btn-success" onclick="exportarExcel()">
                            <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                        </button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover historial-table" id="tablaClientes">
                        <thead>
                            <tr>
                                <th data-column="membresia" data-type="text">
                                    Membresía
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nombre" data-type="text">
                                    Nombre
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="apellido" data-type="text">
                                    Apellido
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="celular" data-type="text">
                                    Celular
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_nacimiento" data-type="info">
                                    Fecha Nacimiento
                                </th>
                                <th data-column="correo" data-type="text">
                                    Correo
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_registro" data-type="daterange">
                                    Fecha Inscripción
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="ultima_compra" data-type="daterange">
                                    Última Compra
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nombre_sucursal" data-type="list">
                                    Sucursal
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th style="width: 100px;">Historial</th>
                            </tr>
                        </thead>
                        <tbody id="tablaClientesBody">
                            <!-- Datos cargados vía AJAX -->
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0">Mostrar:</label>
                        <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;"
                            onchange="cambiarRegistrosPorPagina()">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="mb-0">registros</span>
                    </div>
                    <div id="paginacion"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/historial_clientes.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>