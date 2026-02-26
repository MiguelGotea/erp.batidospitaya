<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso (SIEMPRE debe existir permiso 'vista')
if (!tienePermiso('gestion_colaboradores', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Colaboradores</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/colaboradores.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Lista de Colaboradores'); ?>

            <div class="container-fluid p-3">
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
                                <th data-column="ultima_fecha_laborada" data-type="daterange">
                                    Último Día<br>Marcado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="tiempo_trabajado_dias" data-type="list">
                                    Tiempo Trabajado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="porcentaje_llenado" style="width: 120px; text-align: center;">
                                    Llenado
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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const canFinalize = <?= tienePermiso('gestion_colaboradores', 'finalizar_contrato', $cargoOperario) ? 'true' : 'false' ?>;
    </script>
    <script src="js/colaboradores.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>