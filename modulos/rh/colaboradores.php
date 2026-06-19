<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
// Verificar acceso (SIEMPRE debe existir permiso 'vista')
if (!tienePermiso('gestion_colaboradores', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
$puedeExportar = tienePermiso('gestion_colaboradores', 'exportar', $cargoOperario);
$puedeCrearColaborador = tienePermiso('gestion_colaboradores', 'nuevo_colaborador', $cargoOperario);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Colaboradores</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/colaboradores.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>


<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Maestro de Colaboradores'); ?>

            <div class="container-fluid p-3">

                <!-- Botón Flotante Herramientas -->
                <?php if ($puedeCrearColaborador): ?>
                <div class="fab-container">
                    <div class="fab-options">
                        <a href="nuevo_colaborador.php" class="fab-option">
                            <span class="fab-label">Nuevo Colaborador</span>
                            <div class="fab-icon-holder"><i class="fas fa-user-plus"></i></div>
                        </a>
                    </div>
                    <div class="btn-floating-pitaya" title="Herramientas">
                        <i class="fas fa-wrench"></i>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['exito'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['exito'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['exito']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="export-toolbar">
                    <?php if ($puedeExportar): ?>
                        <button id="btnExportarExcel" class="btn-exportar-excel" onclick="exportarExcel()"
                            title="Exportar colaboradores (con filtros activos) a Excel">
                            <i class="fas fa-file-excel"></i>
                            Exportar a Excel
                            <span id="exportarSpinner" class="exportar-spinner" style="display:none;"></span>
                        </button>
                        <span id="exportarFiltrosLabel" class="exportar-filtros-label" style="display:none;">
                            <i class="bi bi-funnel-fill"></i> Con filtros aplicados
                        </span>
                    <?php endif; ?>

                    <button id="btnLimpiarTodo" class="btn-limpiar-todo" onclick="limpiarTodosLosFiltros()"
                        style="display:none;" title="Limpiar todos los filtros y restaurar tabla original">
                        <i class="fas fa-broom"></i> Limpiar Filtros
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover colaboradores-table" id="tablaColaboradores">
                        <thead>
                            <tr>
                                <th data-column="CodOperario" data-type="text">
                                    Código
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nombre_completo" data-type="text">
                                    Nombre Completo
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Cedula" data-type="text">
                                    Cédula
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="codigo_inss" data-type="text">
                                    Seguro INSS
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="cargo_nombre" data-type="list">
                                    Cargo
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="telefonos" data-type="text">
                                    Teléfonos
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Operativo">
                                    <div class="status-header-content">
                                        <span class="status-label">Estado / Contrato</span>
                                        <div class="estado-filter-circles">
                                            <i class="bi bi-person-check-fill filter-circle active" data-state="1"
                                                onclick="setEstadoFilter('1')" title="Activos"></i>
                                            <i class="bi bi-people-fill filter-circle" data-state="all"
                                                onclick="setEstadoFilter('all')" title="Todos"></i>
                                            <i class="bi bi-person-x-fill filter-circle" data-state="0"
                                                onclick="setEstadoFilter('0')" title="Inactivos"></i>
                                        </div>
                                    </div>
                                </th>
                                <th data-column="nombre_sucursal" data-type="list" class="col-tienda">
                                    Tienda/Área<br>Contrato
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="sucursal_actual_nombre" data-type="list" class="col-tienda">
                                    Tienda/Area<br>Actual
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_inicio_ultimo_contrato" data-type="daterange">
                                    Inicio Contrato
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="mes_contrato" data-type="list" style="width: 100px; text-align: center;">
                                    Mes Contrato
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="ultima_fecha_laborada" data-type="daterange">
                                    Último Día<br>Marcado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_salida_ultimo" data-type="daterange" class="col-fecha-salida"
                                    style="white-space: nowrap;">
                                    Fecha de Salida
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="tiempo_trabajado_dias" data-type="list">
                                    Tiempo Trabajado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="cantidad_hijos" data-type="list"
                                    style="width: 80px; text-align: center;">
                                    Hijos
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="talla_camisa" data-type="list"
                                    style="width: 100px; text-align: center;">
                                    Talla Camisa
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_vencimiento_salud" data-type="daterange" style="white-space: nowrap;">
                                    Venc. Cert. Salud
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="porcentaje_llenado" data-type="numrange"
                                    style="width: 100px; text-align: center;">
                                    % de avance
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th style="width: 120px; text-align: center;">
                                    Documentos completos
                                </th>
                                <th style="width: 80px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaColaboradoresBody">
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

    <!-- Modal para terminación de contrato (Traído de editar_colaborador.php) -->
    <div id="modalTerminacion" class="modal-backdrop" style="display: none;">
        <div class="modal-content">
            <h3 style="color: #dc3545; margin-bottom: 20px;">Terminar Contrato</h3>

            <form id="formTerminacion">
                <input type="hidden" name="id_contrato" id="idContratoTerminar" value="">
                <input type="hidden" name="cod_operario" id="codOperarioTerminar" value="">

                <div class="form-group mb-3">
                    <label class="form-label fw-bold">N° Contrato</label>
                    <input type="text" id="codigoManualTerminar" class="form-control" readonly
                        style="background-color: #f8f9fa;">
                </div>

                <div class="form-group mb-3">
                    <label class="form-label fw-bold">Colaborador</label>
                    <input type="text" id="nombreColaboradorTerminar" class="form-control" readonly
                        style="background-color: #f8f9fa;">
                </div>

                <!-- Fecha Fin de Contrato - OCULTO -->
                <div class="form-group mb-3" style="display: none;">
                    <label for="fecha_fin_contrato" class="form-label fw-bold">Fecha Fin de Contrato</label>
                    <input type="date" id="fecha_fin_contrato" name="fecha_fin_contrato" class="form-control" readonly
                        style="background-color: #f8f9fa;">
                </div>

                <div class="form-group mb-3">
                    <label for="fecha_terminacion" class="form-label fw-bold">Fecha de Salida/Terminación *</label>
                    <input type="date" id="fecha_terminacion" name="fecha_terminacion" class="form-control"
                        value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <!-- Fecha de Liquidación - OCULTO -->
                <div class="form-group mb-3" style="display: none;">
                    <label for="fecha_liquidacion" class="form-label fw-bold">Fecha de Liquidación</label>
                    <input type="date" id="fecha_liquidacion" name="fecha_liquidacion" class="form-control">
                </div>

                <div class="form-group mb-3">
                    <label for="fecha_carta" class="form-label fw-bold">Fecha de la Carta</label>
                    <input type="date" id="fecha_carta" name="fecha_carta" class="form-control"
                        value="<?php echo date('Y-m-d'); ?>">
                    <small style="color: #6c757d;">Fecha formal que aparece en la carta de terminación</small>
                </div>

                <div class="form-group mb-3">
                    <label for="tipo_salida" class="form-label fw-bold">Tipo de Salida *</label>
                    <select id="tipo_salida" name="tipo_salida" class="form-control" required>
                        <option value="">Seleccionar tipo de salida...</option>
                        <?php
                        require_once 'editar_colaborador_componentes/logic/funciones_colaborador.php';
                        $tiposSalida = obtenerTiposSalida();
                        foreach ($tiposSalida as $tipo): ?>
                            <option value="<?= $tipo['CodTipoSalida'] ?>">
                                <?= htmlspecialchars($tipo['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label for="motivo_salida" class="form-label fw-bold">Motivo de Salida *</label>
                    <textarea id="motivo_salida" name="motivo_salida" class="form-control" rows="3" required></textarea>
                </div>

                <div style="display:none;" class="form-group mb-3">
                    <label for="devolucion_herramientas" class="form-label fw-bold">Devolución de Herramientas</label>
                    <select id="devolucion_herramientas" name="devolucion_herramientas" class="form-control"
                        onchange="togglePersonaHerramientasList(this.value)">
                        <option value="0">No aplica</option>
                        <option value="1">Sí aplica</option>
                    </select>
                </div>

                <div class="form-group mb-3" id="grupoPersonaHerramientas" style="display: none;">
                    <label for="persona_recibe_herramientas" class="form-label fw-bold">Persona que Recibe
                        Herramientas</label>
                    <input type="text" id="persona_recibe_herramientas" name="persona_recibe_herramientas"
                        class="form-control">
                </div>

                <div style="display:none;" class="form-group mb-3">
                    <label for="dias_trabajados" class="form-label fw-bold">Días Trabajados *</label>
                    <input type="number" id="dias_trabajados" name="dias_trabajados" class="form-control" min="1"
                        required>
                </div>

                <div style="display:none;" class="form-group mb-3">
                    <label for="monto_indemnizacion" class="form-label fw-bold">Indemnización</label>
                    <input type="number" id="monto_indemnizacion" name="monto_indemnizacion" class="form-control"
                        step="0.01" min="0" value="0">
                    <small style="color: #6c757d;">Monto en córdobas (opcional)</small>
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 30px;">
                    <button type="button" class="btn-modern btn-modern-secondary" onclick="cerrarModalTerminacion()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-modern btn-modern-danger">
                        <i class="fas fa-check"></i> Confirmar Terminación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Ayuda Universal -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header text-white" style="background-color: #0E544C;">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i> Guía del Maestro de Colaboradores
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <!-- Sección 1 -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="border-bottom pb-2 fw-bold" style="color: #0E544C;">
                                        <i class="fas fa-users me-2"></i> Gestión de Colaboradores
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Permite administrar el listado general de personal de <strong>Batidos
                                            Pitaya</strong>.
                                        Aquí puedes consultar datos personales, INSS, cédulas, contactos, cargos, estado
                                        y tallas de camisa.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <!-- Sección 2 -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="border-bottom pb-2 fw-bold" style="color: #51B8AC;">
                                        <i class="fas fa-filter me-2"></i> Filtros de Cabecera
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Cada columna posee un embudo <i class="bi bi-funnel"></i> para filtrados
                                        personalizados de forma simultánea.
                                        Si deseas restaurar la vista original rápidamente, haz clic en el botón naranja
                                        <strong>Limpiar Filtros</strong>.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <!-- Sección 3 -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="border-bottom pb-2 fw-bold" style="color: #ca6f1e;">
                                        <i class="fas fa-store me-2"></i> Historial e Inactivos
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Para colaboradores inactivos, el sistema rescata automáticamente su última
                                        sucursal de operaciones bajo el sufijo <strong>(última tienda)</strong>,
                                        manteniendo el contexto histórico de auditorías.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <!-- Sección 4 -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="border-bottom pb-2 fw-bold" style="color: #28a745;">
                                        <i class="fas fa-file-excel me-2"></i> Exportar Reportes
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El botón <strong>Exportar a Excel</strong> genera un reporte asíncrono
                                        respetando fielmente el ordenamiento, orden por nulos de salida y filtros que
                                        tengas aplicados en pantalla.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 px-3 small mt-2 mb-0">
                        <strong><i class="fas fa-user-slash me-1"></i> Terminación de Contrato:</strong>
                        Los usuarios con permisos asignados pueden finalizar la relación laboral directamente desde el
                        botón "Terminar" de la columna *Estado/Contrato*, registrando el motivo y fecha formal de
                        salida.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
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
        const canFinalize = <?= tienePermiso('gestion_colaboradores', 'finalizar_contrato', $cargoOperario) ? 'true' : 'false' ?>;
        const canExport = <?= $puedeExportar ? 'true' : 'false' ?>;
        const canCreateColaborador = <?= $puedeCrearColaborador ? 'true' : 'false' ?>;
    </script>
    <script src="js/colaboradores.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
    <!-- FAB Draggable: permite mover el botón flotante libremente en el viewport -->
    <script src="/core/assets/js/fab_button.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>