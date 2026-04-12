<?php
// historial_unidades_conversion.php
// Ubicación: /public_html/modulos/POS/historial_unidades_conversion.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';

require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('unidades_conversion_productos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Verificar permiso de creación
$puedeCrear = tienePermiso('unidades_conversion_productos', 'nuevo_registro', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Unidades y Conversión</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/historial_unidades_conversion.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Administración de Unidades y Conversión'); ?>
            
            <div class="container-fluid p-3">
                <!-- Botón para agregar nueva unidad -->
                <?php if ($puedeCrear): ?>
                <div class="mb-3">
                    <button class="btn btn-success" onclick="abrirModalNuevaUnidad()">
                        <i class="bi bi-plus-circle"></i> Nueva Unidad
                    </button>
                </div>
                <?php endif; ?>

                <!-- Tabla de unidades -->
                <div class="table-responsive">
                    <table class="table table-hover unidades-table" id="tablaUnidades">
                        <thead>
                            <tr>
                                <th data-column="nombre" data-type="text">
                                    Nombre
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="observaciones" data-type="text">
                                    Observaciones
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <?php if ($puedeCrear): ?>
                                <th style="width: 200px;">Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tablaUnidadesBody">
                            <!-- Datos cargados vía AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0">Mostrar:</label>
                        <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;" onchange="cambiarRegistrosPorPagina()">
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

    <!-- Modal para agregar/editar unidad -->
    <div class="modal fade" id="modalUnidad" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUnidadTitulo">Nueva Unidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formUnidad">
                        <input type="hidden" id="unidadId" name="id">
                        
                        <div class="mb-3">
                            <label for="nombreUnidad" class="form-label">Nombre de la Unidad *</label>
                            <input type="text" class="form-control" id="nombreUnidad" name="nombre" 
                                   required maxlength="100" placeholder="Ej: Kilogramos, Litros, Onzas">
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" 
                                      rows="3" maxlength="255" placeholder="Notas adicionales sobre esta unidad"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="guardarUnidad()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para nueva conversión -->
    <div class="modal fade" id="modalConversion" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Conversión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formConversion">
                        <input type="hidden" id="conversionUnidadInicio" name="id_unidad_producto_inicio">
                        
                        <div class="mb-3">
                            <label class="form-label">Unidad de Entrada</label>
                            <input type="text" class="form-control" id="nombreUnidadInicio" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="unidadFinal" class="form-label">Unidad de Salida *</label>
                            <select class="form-select" id="unidadFinal" name="id_unidad_producto_final" required>
                                <option value="">Seleccionar unidad...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="cantidad" class="form-label">Factor de Conversión *</label>
                            <input type="number" class="form-control" id="cantidad" name="cantidad" 
                                   required step="0.0001" min="0.0001" placeholder="Ej: 1000">
                            <small class="text-muted">
                                Cantidad de la unidad de salida equivalente a 1 unidad de entrada
                            </small>
                        </div>

                        <!-- Ejemplo visual -->
                        <div class="alert alert-info">
                            <strong>Ejemplo:</strong> Si 1 <span id="ejemploInicio">Kilogramo</span> = 
                            <input type="number" id="ejemploCantidad" class="form-control d-inline-block" 
                                   style="width: 100px;" placeholder="1000" onchange="actualizarEjemplo()"> 
                            <span id="ejemploFinal">Gramos</span>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="guardarConversion()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para historial de conversiones -->
    <div class="modal fade" id="modalHistorial" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Historial de Conversiones: <span id="historialNombreUnidad"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="historialConversiones" class="conversiones-container">
                        <!-- Datos cargados vía AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/historial_unidades_conversion.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>