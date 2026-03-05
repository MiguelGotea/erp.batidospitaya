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
                <!-- Barra de acciones -->
                <div class="d-flex justify-content-end mb-2 gap-2">
                    <?php if (tienePermiso('gestion_sorteos', 'invalidacion_masiva', $cargoOperario)): ?>
                        <button class="btn btn-outline-danger btn-sm" onclick="ejecutarInvalidacionMasiva()"
                            title="Invalida automáticamente los registros por IA o Colaboradores (lógica completa)">
                            <i class="bi bi-shield-slash me-1"></i> Invalidación Masiva
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-success btn-sm" onclick="descargarConcursantesValidos()"
                        title="Descarga todos los concursantes válidos (valido=1) sin columnas de verificación">
                        <i class="bi bi-download me-1"></i> Descargar Concursantes
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover sorteos-table" id="tablaSorteos">
                        <thead>
                            <tr>
                                <th data-column="nombre_completo" data-type="text">
                                    Nombre Completo
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="numero_cedula" data-type="text">
                                    No. Cédula
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="numero_contacto" data-type="text">
                                    No. Contacto
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="correo_electronico" data-type="text" style="max-width:130px;">
                                    Correo
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="monto_factura" data-type="number">
                                    Monto
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="numero_factura" data-type="text">
                                    No. Factura
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="puntos_factura" data-type="number">
                                    Puntos
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="puntos_globales" data-type="number"
                                    title="Suma total de puntos de todas las participaciones de este mismo nombre">
                                    Pts. Globales
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_registro" data-type="daterange">
                                    Fecha Registro
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="verificacion_ia">
                                    Verificación IA
                                    <div class="ia-filter-circles">
                                        <i class="bi bi-check-circle-fill filter-circle" data-state="verified"
                                            onclick="setIAFilter('verified')" title="Verificado"></i>
                                        <i class="bi bi-dash-circle-fill filter-circle active" data-state="all"
                                            onclick="setIAFilter('all')" title="Todos"></i>
                                        <i class="bi bi-exclamation-triangle-fill filter-circle" data-state="review"
                                            onclick="setIAFilter('review')" title="Revisar"></i>
                                    </div>
                                </th>
                                <th data-column="verificacion_colaborador">
                                    Verificación Colaborador
                                    <div class="colab-filter-circles">
                                        <i class="bi bi-check-circle-fill filter-circle" data-state="verified"
                                            onclick="setColabFilter('verified')" title="Verificado"></i>
                                        <i class="bi bi-dash-circle-fill filter-circle active" data-state="all"
                                            onclick="setColabFilter('all')" title="Todos"></i>
                                        <i class="bi bi-exclamation-triangle-fill filter-circle" data-state="review"
                                            onclick="setColabFilter('review')" title="Revisar"></i>
                                    </div>
                                </th>
                                <th data-column="valido" data-type="toggle3">
                                    Válido
                                    <div class="valido-filter-circles">
                                        <i class="bi bi-check-circle-fill filter-circle" data-state="valid"
                                            onclick="setValidoFilter('valid')" title="Válidos"></i>
                                        <i class="bi bi-dash-circle-fill filter-circle active" data-state="all"
                                            onclick="setValidoFilter('all')" title="Todos"></i>
                                        <i class="bi bi-x-circle-fill filter-circle" data-state="invalid"
                                            onclick="setValidoFilter('invalid')" title="Inválidos"></i>
                                    </div>
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
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Gestión de Sorteos - Pitaya Love
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
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
                                        Cada registro de factura es procesado automáticamente por inteligencia
                                        artificial que detecta:
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
                                        <li><span class="text-success fw-bold">✓ Válido:</span> Registro correcto y
                                            verificado</li>
                                        <li><span class="text-danger fw-bold">✗ Inválido:</span> Registro con errores o
                                            fraudulento</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="fas fa-user-secret me-2"></i> Verificación Colaborador
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El sistema compara automáticamente el nombre de cada concursante con la
                                        base de datos de <strong>colaboradores activos</strong> (contrato vigente).
                                    </p>
                                    <ul class="small text-muted mt-2 mb-0">
                                        <li><span class="text-success fw-bold">✓ Verificado:</span> Sin coincidencia con
                                            colaboradores</li>
                                        <li><span class="text-warning fw-bold">⚠ Revisar:</span> Al menos 2 palabras del
                                            nombre coinciden con un colaborador activo</li>
                                    </ul>
                                    <p class="small text-muted mt-2 mb-0">
                                        En el modal de detalle se muestra el nombre del colaborador sospechoso para
                                        facilitar la revisión.
                                    </p>
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
                                        <li><i class="bi bi-circle text-secondary"></i> <strong>Todos:</strong> Muestra
                                            todos los registros</li>
                                        <li><i class="bi bi-check-circle text-success"></i> <strong>Válidos:</strong>
                                            Solo registros verificados</li>
                                        <li><i class="bi bi-x-circle text-danger"></i> <strong>Inválidos:</strong> Solo
                                            registros rechazados</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 small">
                        <strong><i class="fas fa-info-circle me-1"></i> Nota Importante:</strong>
                        <br>
                        Los registros inválidos no se eliminan, solo se marcan para mantener un historial completo. Esto
                        permite auditorías y revisiones posteriores.
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

    <!-- Modal Resultados Invalidación Masiva -->
    <div class="modal fade" id="modalResultadoInvalidacion" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-shield-slash me-2"></i> Resultado de Invalidación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                        <h4 class="mt-2" id="msgTotalActualizados">0 Registros Actualizados</h4>
                        <p class="text-muted">La base de datos ha sido actualizada con éxito.</p>
                    </div>
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body p-3">
                            <h6 class="card-title text-uppercase small fw-bold text-muted mb-3">Desglose por lógica:
                            </h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><i class="bi bi-robot me-2 text-primary"></i> Por Verificación IA:</span>
                                <span class="badge bg-primary fs-6" id="cntIA">0</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-people me-2 text-warning"></i> Por Colaboradores:</span>
                                <span class="badge bg-warning text-dark fs-6" id="cntColab">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i> Recuerde que algunos registros pueden haber coincidido en
                        ambas lógicas simultáneamente.
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set permissions from PHP
        const cargoOperario = <?php echo json_encode($cargoOperario); ?>;
        const tienePermisoVista = <?php echo tienePermiso('gestion_sorteos', 'vista', $cargoOperario) ? 'true' : 'false'; ?>;
        const tienePermisoEdicion = <?php echo tienePermiso('gestion_sorteos', 'edicion', $cargoOperario) ? 'true' : 'false'; ?>;
        const tienePermisoInvalidacion = <?php echo tienePermiso('gestion_sorteos', 'invalidacion_masiva', $cargoOperario) ? 'true' : 'false'; ?>;

        console.log('Cargo del operario:', cargoOperario);
        console.log('Permiso de vista:', tienePermisoVista);
        console.log('Permiso de edición:', tienePermisoEdicion);
        console.log('Permiso de invalidación masiva:', tienePermisoInvalidacion);
    </script>
    <!-- SheetJS para exportar Excel (.xlsx) -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
    <script src="js/gestion_sorteos.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
    <script src="js/gestion_sorteos_patch.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>