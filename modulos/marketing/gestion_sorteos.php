<?php
// gestion_sorteos.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];


// Verificar acceso
if (!tienePermiso('gestion_sorteos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Sorteos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/gestion_sorteos.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Gestión de Sorteos - Pitaya Love'); ?>

            <div class="container-fluid p-3">
                <div class="table-responsive">
                    <table class="table table-hover sorteos-table" id="tablaSorteos">
                        <thead>
                            <tr>
                                <th data-column="fecha_registro" data-type="daterange">
                                    Fecha Registro
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nombre_completo" data-type="text">
                                    Nombre Completo
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="numero_contacto" data-type="text">
                                    No. Contacto
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="numero_cedula" data-type="text">
                                    No. Cédula
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="numero_factura" data-type="text">
                                    No. Factura
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="correo_electronico" data-type="text">
                                    Correo
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="monto_factura" data-type="number">
                                    Monto
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="puntos_factura" data-type="number">
                                    Puntos
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="valido" data-type="toggle3">
                                    Válido
                                    <button class="valido-filter-toggle" onclick="toggleValidoFilter()"
                                        title="Filtrar por estado">
                                        <i class="bi bi-circle"></i>
                                        <span>Todos</span>
                                    </button>
                                </th>
                                <th style="width: 150px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaSorteosBody">
                            <!-- Datos cargados vía AJAX -->
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0">Mostrar:</label>
                        <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;"
                            onchange="cambiarRegistrosPorPagina()">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="mb-0">registros</span>
                    </div>
                    <div id="paginacion"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para ver foto -->
    <div class="modal fade" id="modalVerFoto" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalle de Registro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Foto de Factura</h6>
                            <img id="fotoFactura" src="" alt="Factura" class="img-fluid rounded border">
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Datos del Registro</h6>
                            <div id="datosRegistro" class="card p-3">
                                <!-- Datos cargados dinámicamente -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" 
         aria-labelledby="pageHelpModalLabel" aria-hidden="true" 
         data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Gestión de Sorteos - Pitaya Love
                    </h5>
                    <button type="button" class="btn-close btn-close-white" 
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-robot me-2"></i> Sistema de Validación IA
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Cada registro de factura es procesado automáticamente por inteligencia artificial que detecta:
                                    </p>
                                    <ul class="small text-muted mt-2 mb-0">
                                        <li><strong>Código de sorteo:</strong> Número identificador del sorteo</li>
                                        <li><strong>Puntos:</strong> Puntos acumulados en la factura</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="fas fa-check-circle me-2"></i> Comparación de Datos
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Al hacer clic en <strong>"Ver"</strong>, podrás comparar lado a lado:
                                    </p>
                                    <ul class="small text-muted mt-2 mb-0">
                                        <li>Foto de la factura</li>
                                        <li>Datos guardados manualmente</li>
                                        <li>Datos detectados por la IA</li>
                                    </ul>
                                    <p class="small text-muted mt-2 mb-0">
                                        Las diferencias se resaltan automáticamente.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="fas fa-toggle-on me-2"></i> Validar/Invalidar Registros
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Dentro del modal de revisión encontrarás un toggle para marcar el registro como:
                                    </p>
                                    <ul class="small text-muted mt-2 mb-0">
                                        <li><span class="text-success fw-bold">✓ Válido:</span> Registro correcto y verificado</li>
                                        <li><span class="text-danger fw-bold">✗ Inválido:</span> Registro con errores o fraudulento</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="fas fa-filter me-2"></i> Filtros de Estado
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Usa el filtro de la columna "Válido" para ver:
                                    </p>
                                    <ul class="small text-muted mt-2 mb-0">
                                        <li><i class="bi bi-circle text-secondary"></i> <strong>Todos:</strong> Muestra todos los registros</li>
                                        <li><i class="bi bi-check-circle text-success"></i> <strong>Válidos:</strong> Solo registros verificados</li>
                                        <li><i class="bi bi-x-circle text-danger"></i> <strong>Inválidos:</strong> Solo registros rechazados</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info py-2 px-3 small">
                        <strong><i class="fas fa-info-circle me-1"></i> Nota Importante:</strong>
                        <br>
                        Los registros inválidos no se eliminan, solo se marcan para mantener un historial completo. Esto permite auditorías y revisiones posteriores.
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
    <script>
        // Set permissions from PHP
        const cargoOperario = <?php echo json_encode($cargoOperario); ?>;
        const tienePermisoVista = <?php echo tienePermiso('gestion_sorteos', 'vista', $cargoOperario) ? 'true' : 'false'; ?>;
        const tienePermisoEdicion = <?php echo tienePermiso('gestion_sorteos', 'edicion', $cargoOperario) ? 'true' : 'false'; ?>;

        console.log('Cargo del operario:', cargoOperario);
        console.log('Permiso de vista:', tienePermisoVista);
        console.log('Permiso de edición:', tienePermisoEdicion);
    </script>
    <script src="js/gestion_sorteos.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>