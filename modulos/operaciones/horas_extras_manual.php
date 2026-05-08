<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';


$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('horas_extras_manual', 'vista', $cargoOperario)) {
    header('Location: /index.php');
    exit();
}

// Permisos específicos consolidados
$puedeGestionar  = tienePermiso('horas_extras_manual', 'gestionar', $cargoOperario);
$puedeSolicitar  = tienePermiso('horas_extras_manual', 'solicitar', $cargoOperario);
$puedeExportar   = tienePermiso('horas_extras_manual', 'exportar', $cargoOperario);
$puedeVerTodo    = tienePermiso('horas_extras_manual', 'ver_todo', $cargoOperario);
$puedeFiltroAll  = tienePermiso('horas_extras_manual', 'filtro_todas_tiendas', $cargoOperario);
$puedeVerObs     = tienePermiso('horas_extras_manual', 'vista', $cargoOperario); // O según se defina

// MIGRACIÓN AUTOMÁTICA (Si falla el CLI)
try {
    $res = $conn->query("SHOW COLUMNS FROM horas_extras_manual LIKE 'motivo_solicitud'");
    if ($res->rowCount() == 0) {
        $conn->exec("ALTER TABLE horas_extras_manual ADD COLUMN motivo_solicitud TEXT AFTER observaciones");
    }
} catch (Exception $e) {
}

// EXPORTACIÓN EXCEL (Aprobados)
if (isset($_GET['exportar_excel']) && $puedeExportar) {
    $sucursal = $_GET['sucursal'] ?? '';
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-t');
    $operario = $_GET['operario'] ?? '';
    $estado = $_GET['estado'] ?? '';

    $sql = "
        SELECT 
            hem.*,
            o.Nombre,
            o.Nombre2,
            o.Apellido,
            o.Apellido2,
            s.nombre  AS sucursal_nombre,
            c.CodContrato
        FROM horas_extras_manual hem
        JOIN Operarios o       ON hem.cod_operario  = o.CodOperario
        LEFT JOIN sucursales s ON hem.cod_sucursal  = s.codigo
        LEFT JOIN Contratos c  ON hem.cod_contrato  = c.CodContrato
        WHERE hem.fecha BETWEEN ? AND ?
    ";
    $params = [$desde, $hasta];

    // Restricción de sucursal si no tiene permiso de ver todo
    if (!$puedeVerTodo) {
        $misSucursales = obtenerSucursalesLider($_SESSION['usuario_id']);
        $sucursal = $misSucursales[0]['codigo'] ?? 'NINGUNA';
    }
    if (!empty($sucursal)) {
        $sql .= " AND hem.cod_sucursal = ?";
        $params[] = $sucursal;
    }
    if (!empty($operario)) {
        $sql .= " AND hem.cod_operario = ?";
        $params[] = $operario;
    }
    if (!empty($estado)) {
        $sql .= " AND hem.estado = ?";
        $params[] = $estado;
    }

    $sql .= " ORDER BY hem.fecha DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    $filename = 'horas_extras_' . date('Y-m-d') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF"; // BOM UTF-8

    echo '<table border="1"><tr>
        <th>CODIGO</th>
        <th>SUCURSAL</th>
        <th>PERSONA</th>
        <th>FECHA</th>
        <th>HORAS</th>
        <th>ESTADO</th>
        <th>MOTIVO</th>
        <th>OBSERVACIONES</th>
    </tr>';

    foreach ($data as $r) {
        // Nombre completo sin espacios dobles por campos vacíos/nulos
        $partes = array_filter([
            $r['Nombre'] ?? '',
            $r['Nombre2'] ?? '',
            $r['Apellido'] ?? '',
            $r['Apellido2'] ?? '',
        ], fn($p) => trim($p) !== '');
        $nombreCompleto = htmlspecialchars(implode(' ', $partes));

        $sucursalNombre = htmlspecialchars($r['sucursal_nombre'] ?? '');
        $motivo = htmlspecialchars($r['motivo_solicitud'] ?? '');
        $obs = htmlspecialchars($r['observaciones'] ?? '');

        echo "<tr>
            <td>{$r['CodContrato']}</td>
            <td>{$sucursalNombre}</td>
            <td>{$nombreCompleto}</td>
            <td>{$r['fecha']}</td>
            <td>{$r['horas_extras']}</td>
            <td>{$r['estado']}</td>
            <td>{$motivo}</td>
            <td>{$obs}</td>
        </tr>";
    }
    echo '</table>';
    exit();
}

// Obtener sucursales según permiso
if ($puedeVerTodo || $puedeFiltroAll) {
    $sucursales = obtenerTodasSucursales();
} else {
    $sucursales = obtenerSucursalesLider($_SESSION['usuario_id']);
}

// Determinar si se comporta como líder (restringido a su tienda)
$esRestringido = !$puedeVerTodo && !$puedeFiltroAll;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horas Extras Manuales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/horas_extras_manual.css?v=<?= time() ?>">
</head>

<body>
    <?= renderMenuLateral($cargoOperario) ?>

    <div class="main-container">
        <div class="container-fluid">
            <?= renderHeader($usuario, false, 'Gestión de Horas Extras') ?>

            <!-- Filtros -->
            <div class="card card-body mb-3 filters-container">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label fw-semibold text-muted small">Sucursal</label>
                        <select id="sucursal" class="form-select form-select-sm" <?= $esRestringido ? 'disabled' : '' ?>>
                            <?php if (!$esRestringido): ?>
                                <option value="">Todas</option>
                            <?php endif; ?>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?= $s['codigo'] ?>" <?= ($esRestringido && count($sucursales) > 0) ? 'selected' : '' ?>><?= $s['nombre'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted small">Colaborador</label>
                        <div class="position-relative">
                            <input type="text" id="operario_search" class="form-control form-control-sm"
                                placeholder="Buscar colaborador...">
                            <input type="hidden" id="operario_id">
                            <div id="operarios-sugerencias" class="list-group position-absolute w-100 shadow-sm"
                                style="display:none;z-index:1000;"></div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold text-muted small">Desde</label>
                        <input type="date" id="desde" class="form-control form-control-sm"
                            value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold text-muted small">Hasta</label>
                        <input type="date" id="hasta" class="form-control form-control-sm" value="<?= date('Y-m-t') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label fw-semibold text-muted small">Estado</label>
                        <select id="filtroEstado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="Pendiente">Pendiente</option>
                            <option value="Aprobado">Aprobado</option>
                            <option value="Denegado">Denegado</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="button" onclick="cargarDatos()" class="btn btn-sm btn-primary"><i
                                class="fas fa-search"></i></button>
                        <?php if ($puedeSolicitar || $puedeGestionar): ?>
                            <button type="button" onclick="abrirNuevaSolicitud()" class="btn btn-sm btn-warning"><i
                                    class="fas fa-plus"></i> Solicitar</button>
                        <?php endif; ?>
                        <?php if ($puedeExportar): ?>
                            <a href="?exportar_excel=1" id="linkExport" class="btn btn-sm btn-success"><i
                                    class="fas fa-file-excel"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tabla Historial Unificado -->
            <div class="table-responsive hem-table-wrapper">
                <table class="table hem-table" id="tableHistorial">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Sucursal</th>
                            <th>Fecha</th>
                            <th class="text-center" title="Horario Programado (arriba) vs Marcación Real (abajo)">
                                Turno<br><small class="fw-normal opacity-75">Prog. / Real</small>
                            </th>
                            <th class="text-center">Horas Ext.</th>
                            <th>Estado</th>
                            <th>Motivo Solicitud</th>
                            <?php if ($puedeVerObs): ?>
                                <th>Observaciones</th>
                            <?php endif; ?>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="historialBody">
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="d-flex justify-content-between align-items-center mt-3 px-1">
                <div class="d-flex align-items-center gap-2">
                    <label class="mb-0 small text-muted">Mostrar:</label>
                    <select class="form-select form-select-sm" id="registrosPorPagina" style="width:auto;"
                        onchange="cambiarPagina(1)">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span class="small text-muted">registros</span>
                    <span class="small text-muted ms-3" id="infoRegistros"></span>
                </div>
                <div id="paginacion" class="d-flex gap-1"></div>
            </div>
        </div>
    </div>

    <!-- Modal Solicitud Directa -->
    <div class="modal fade" id="modalSolicitud" tabindex="-1">
        <div class="modal-dialog">
            <form id="formSolicitud" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clock me-2 text-warning"></i><span
                            id="modalSolicitudTitulo">Solicitar Horas Extras</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="sol_id">
                    <input type="hidden" name="cod_operario" id="sol_cod_operario">
                    <input type="hidden" name="cod_sucursal" id="sol_cod_sucursal">

                    <!-- Colaborador con búsqueda -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Colaborador <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input type="text" id="sol_operario_search" class="form-control"
                                placeholder="Buscar colaborador..." autocomplete="off">
                            <div id="sol-sugerencias" class="list-group position-absolute w-100 shadow-sm"
                                style="display:none;z-index:2000;max-height:200px;overflow-y:auto;"></div>
                        </div>
                        <div id="sol_operario_seleccionado" class="mt-1" style="display:none;">
                            <span class="badge bg-success fs-6" id="sol_operario_badge"></span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1"
                                onclick="limpiarOperarioModal()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Fecha -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" id="sol_fecha" class="form-control" value="<?= date('Y-m-d') ?>"
                            required>
                    </div>

                    <!-- Horas Extras -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Horas Extras <span class="text-danger">*</span></label>
                        <input type="number" step="0.5" min="0.5" name="horas" id="sol_horas" class="form-control"
                            required>
                    </div>

                    <!-- Motivo -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Motivo de la Solicitud <span
                                class="text-danger">*</span></label>
                        <textarea name="motivo_solicitud" id="sol_motivo" class="form-control" rows="3" required
                            placeholder="Explique por qué se realizaron las horas extras..."></textarea>
                    </div>

                    <!-- Observaciones (solo cargo 11 / aprobadores) -->
                    <?php if ($puedeVerObs): ?>
                        <div class="mb-3" id="campoObservaciones">
                            <label class="form-label fw-semibold">Observaciones <small
                                    class="text-muted fw-normal">(opcional — visión del superior)</small></label>
                            <textarea name="observaciones" id="sol_observaciones" class="form-control" rows="2"
                                placeholder="Observaciones del líder / RRHH..."></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Enviar
                        Solicitud</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Aprobar/Denegar (para responsables) -->
    <div class="modal fade" id="modalProcesar" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <form id="formProcesar" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProcesarTitulo">Procesar Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="proc_id">
                    <input type="hidden" name="estado" id="proc_estado">
                    <input type="hidden" name="action" value="cambiar_estado">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea name="observaciones" id="proc_obs" class="form-control" rows="3"
                            placeholder="Ingrese observaciones..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnProcesarConfirmar">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.canApprove = <?= $puedeGestionar ? 'true' : 'false' ?>;
        window.canReject = <?= $puedeGestionar ? 'true' : 'false' ?>;
        window.canEdit = <?= $puedeGestionar ? 'true' : 'false' ?>;
        window.canSolicit = <?= ($puedeSolicitar || $puedeGestionar) ? 'true' : 'false' ?>;
        window.cargoOperario = <?= intval($cargoOperario) ?>;
        window.puedeVerObs = <?= $puedeVerObs ? 'true' : 'false' ?>;
        window.usuarioId = <?= $_SESSION['usuario_id'] ?? 0 ?>;
        window.esRestringido = <?= $esRestringido ? 'true' : 'false' ?>;
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/horas_extras_manual.js?v=<?= time() ?>"></script>
</body>

</html>