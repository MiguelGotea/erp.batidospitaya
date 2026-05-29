<?php
/**
 * config_cargos_reclamos.php
 * Administración de la tabla reclamos_cargos_responsables
 * Define qué cargos pueden investigar qué grupos/tipos de reclamos.
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/layout/menu_lateral.php';
require_once '../../../core/layout/header_universal.php';
require_once '../../../core/permissions/permissions.php';

$usuario        = obtenerUsuarioActual();
$cargoOperario  = $usuario['CodNivelesCargos'] ?? null;
$esAdmin        = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso
verificarPermisoORedireccionar('config_cargos_reclamos', 'vista', $cargoOperario);

$puedeCrear    = $esAdmin || tienePermiso('config_cargos_reclamos', 'crear',    $cargoOperario);
$puedeEliminar = $esAdmin || tienePermiso('config_cargos_reclamos', 'eliminar', $cargoOperario);

date_default_timezone_set('America/Managua');

$errores = [];
$exito   = '';

// -------------------------------------------------------------------------
// POST: Crear nuevo registro
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    if (!$puedeCrear) {
        $errores[] = 'No tienes permiso para crear registros.';
    } else {
        $codCargo = intval($_POST['cod_niveles_cargos'] ?? 0);
        $grupoId  = !empty($_POST['grupo_id'])  ? intval($_POST['grupo_id'])  : null;
        $tipoId   = !empty($_POST['tipo_id'])   ? intval($_POST['tipo_id'])   : null;

        if (!$codCargo) {
            $errores[] = 'Debes seleccionar un cargo.';
        }
        if (!$grupoId && !$tipoId) {
            $errores[] = 'Debes seleccionar al menos un Grupo o un Tipo de reclamo.';
        }

        if (empty($errores)) {
            // Verificar duplicado
            $stmtDup = $conn->prepare("
                SELECT COUNT(*) FROM reclamos_cargos_responsables
                WHERE cod_niveles_cargos = :cargo
                  AND (grupo_id <=> :grupo)
                  AND (tipo_id  <=> :tipo)
            ");
            $stmtDup->execute([':cargo' => $codCargo, ':grupo' => $grupoId, ':tipo' => $tipoId]);
            if ($stmtDup->fetchColumn() > 0) {
                $errores[] = 'Ya existe una asignación idéntica para ese cargo, grupo y tipo.';
            } else {
                $stmtIns = $conn->prepare("
                    INSERT INTO reclamos_cargos_responsables
                        (cod_niveles_cargos, grupo_id, tipo_id, usuario_creador, fecha_creacion)
                    VALUES (:cargo, :grupo, :tipo, :creador, NOW())
                ");
                $stmtIns->execute([
                    ':cargo'   => $codCargo,
                    ':grupo'   => $grupoId,
                    ':tipo'    => $tipoId,
                    ':creador' => $usuario['CodOperario'],
                ]);
                $exito = 'Asignación creada correctamente.';
            }
        }
    }
}

// -------------------------------------------------------------------------
// Datos para la tabla y los selects
// -------------------------------------------------------------------------

// Listado de asignaciones actuales
$registros = $conn->query("
    SELECT
        rcr.id,
        nc.Nombre                                    AS cargo_nombre,
        rcr.cod_niveles_cargos,
        rg.nombre                                    AS grupo_nombre,
        rcr.grupo_id,
        rt.nombre                                    AS tipo_nombre,
        rcr.tipo_id,
        CONCAT_WS(' ', o.Nombre, NULLIF(o.Nombre2,''), o.Apellido, NULLIF(o.Apellido2,'')) AS creador_nombre,
        DATE_FORMAT(rcr.fecha_creacion, '%d-%b-%y %H:%i') AS fecha_creacion_fmt,
        CONCAT_WS(' ', om.Nombre, NULLIF(om.Nombre2,''), om.Apellido, NULLIF(om.Apellido2,'')) AS modificador_nombre,
        DATE_FORMAT(rcr.fecha_modifica, '%d-%b-%y %H:%i') AS fecha_modifica_fmt
    FROM reclamos_cargos_responsables rcr
    LEFT JOIN NivelesCargos  nc ON rcr.cod_niveles_cargos = nc.CodNivelesCargos
    LEFT JOIN reclamos_grupos rg ON rcr.grupo_id  = rg.id
    LEFT JOIN reclamos_tipos  rt ON rcr.tipo_id   = rt.id
    LEFT JOIN Operarios        o ON rcr.usuario_creador   = o.CodOperario
    LEFT JOIN Operarios       om ON rcr.usuario_modifica  = om.CodOperario
    ORDER BY nc.Nombre, rg.nombre, rt.nombre
")->fetchAll();

// Cargos disponibles
$cargos = $conn->query("SELECT CodNivelesCargos, Nombre FROM NivelesCargos ORDER BY Nombre")->fetchAll();

// Grupos disponibles
$grupos = $conn->query("SELECT id, nombre FROM reclamos_grupos ORDER BY nombre")->fetchAll();

// Todos los tipos (para el select inicial; se filtrará por JS al elegir grupo)
$tipos = $conn->query("SELECT id, grupo_id, nombre FROM reclamos_tipos ORDER BY nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargos Responsables de Reclamos | Batidos Pitaya</title>
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1,10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1,10000); ?>">
    <style>
        .badge-cargo  { background: #e8f4fd; color: #1565c0; border: 1px solid #90caf9; }
        .badge-grupo  { background: #f3e5f5; color: #6a1b9a; border: 1px solid #ce93d8; }
        .badge-tipo   { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .badge-pill   { border-radius: 20px; padding: 4px 12px; font-size: .78rem; font-weight: 600; display:inline-block; }
        .audit-text   { font-size: .75rem; color: #9e9e9e; }
        .form-card    { border-radius: 16px; border: 0; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .table-card   { border-radius: 16px; border: 0; box-shadow: 0 2px 12px rgba(0,0,0,.08); overflow: hidden; }
        th            { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: #757575; font-weight: 600; }
        .btn-delete   { width: 32px; height: 32px; display:inline-flex; align-items:center; justify-content:center; border-radius: 8px; }
    </style>
</head>
<body>
<?php echo renderMenuLateral($cargoOperario); ?>
<div class="main-container">
    <div class="sub-container">
        <?php echo renderHeader($usuario, 'Cargos Responsables de Reclamos'); ?>

        <div class="container-fluid p-4">

            <?php if ($exito): ?>
            <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-center" style="border-radius:12px;">
                <i class="fas fa-check-circle fs-5 me-3"></i>
                <div><?php echo htmlspecialchars($exito); ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius:12px;">
                <ul class="mb-0">
                    <?php foreach ($errores as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- ========================================================
                 FORMULARIO NUEVA ASIGNACIÓN
            ========================================================= -->
            <?php if ($puedeCrear): ?>
            <div class="card form-card mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="fas fa-plus-circle me-2 text-primary"></i>Nueva Asignación</h5>
                    <p class="text-muted small mt-1 mb-0">Asigna qué cargo puede investigar un grupo o tipo específico de reclamo.</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="formNueva">
                        <input type="hidden" name="action" value="crear">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted text-uppercase small">Cargo <span class="text-danger">*</span></label>
                                <select name="cod_niveles_cargos" class="form-select" id="selectCargo" required>
                                    <option value="">— Seleccionar cargo —</option>
                                    <?php foreach ($cargos as $c): ?>
                                    <option value="<?php echo $c['CodNivelesCargos']; ?>">
                                        <?php echo htmlspecialchars($c['Nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted text-uppercase small">Grupo de Reclamo</label>
                                <select name="grupo_id" class="form-select" id="selectGrupo">
                                    <option value="">— Todos los grupos —</option>
                                    <?php foreach ($grupos as $g): ?>
                                    <option value="<?php echo $g['id']; ?>">
                                        <?php echo htmlspecialchars($g['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Dejar vacío para aplicar a todos los grupos.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted text-uppercase small">Tipo de Reclamo</label>
                                <select name="tipo_id" class="form-select" id="selectTipo">
                                    <option value="">— Todos los tipos —</option>
                                    <?php foreach ($tipos as $t): ?>
                                    <option value="<?php echo $t['id']; ?>" data-grupo="<?php echo $t['grupo_id']; ?>">
                                        <?php echo htmlspecialchars($t['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Se filtra al elegir un grupo.</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                <i class="fas fa-save me-2"></i>Guardar Asignación
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- ========================================================
                 TABLA DE ASIGNACIONES ACTUALES
            ========================================================= -->
            <div class="card table-card">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-list-alt me-2 text-secondary"></i>Asignaciones Registradas</h5>
                        <p class="text-muted small mt-1 mb-0"><?php echo count($registros); ?> asignación(es) configurada(s).</p>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($registros)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-folder-open fs-1 mb-3 d-block"></i>
                        No hay asignaciones configuradas aún.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="px-4 py-3">Cargo</th>
                                    <th class="py-3">Grupo</th>
                                    <th class="py-3">Tipo</th>
                                    <th class="py-3">Creado por</th>
                                    <th class="py-3">Modificado por</th>
                                    <?php if ($puedeEliminar): ?>
                                    <th class="py-3 text-center" style="width:60px;"></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($registros as $r): ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="badge-pill badge-cargo">
                                            <?php echo htmlspecialchars($r['cargo_nombre'] ?? 'ID '.$r['cod_niveles_cargos']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($r['grupo_id']): ?>
                                            <span class="badge-pill badge-grupo">
                                                <?php echo htmlspecialchars($r['grupo_nombre']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">Todos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($r['tipo_id']): ?>
                                            <span class="badge-pill badge-tipo">
                                                <?php echo htmlspecialchars($r['tipo_nombre']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">Todos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <div class="fw-semibold small"><?php echo htmlspecialchars($r['creador_nombre'] ?? '—'); ?></div>
                                        <div class="audit-text"><?php echo htmlspecialchars($r['fecha_creacion_fmt'] ?? '—'); ?></div>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($r['modificador_nombre']): ?>
                                            <div class="fw-semibold small"><?php echo htmlspecialchars($r['modificador_nombre']); ?></div>
                                            <div class="audit-text"><?php echo htmlspecialchars($r['fecha_modifica_fmt']); ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($puedeEliminar): ?>
                                    <td class="py-3 text-center">
                                        <button class="btn btn-outline-danger btn-delete border-0 btn-eliminar"
                                                data-id="<?php echo $r['id']; ?>"
                                                data-cargo="<?php echo htmlspecialchars($r['cargo_nombre'] ?? ''); ?>"
                                                title="Eliminar asignación">
                                            <i class="fas fa-trash-alt" style="font-size:.85rem;"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /container-fluid -->
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-0">¿Eliminar la asignación del cargo <strong id="modalCargoNombre"></strong>?<br>
                <span class="text-muted small">Esta acción no se puede deshacer.</span></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger px-4" id="btnConfirmarEliminar">
                    <i class="fas fa-trash-alt me-2"></i>Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Datos de tipos desde PHP para filtrado local
const todosTipos = <?php echo json_encode($tipos); ?>;

// Filtrar tipos al cambiar grupo
document.getElementById('selectGrupo').addEventListener('change', function () {
    const grupoId = this.value;
    const selectTipo = document.getElementById('selectTipo');
    selectTipo.innerHTML = '<option value="">— Todos los tipos —</option>';

    const filtrados = grupoId
        ? todosTipos.filter(t => String(t.grupo_id) === String(grupoId))
        : todosTipos;

    filtrados.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = t.nombre;
        selectTipo.appendChild(opt);
    });
});

// Eliminar con confirmación
let eliminarId = null;
const modalEliminar = new bootstrap.Modal(document.getElementById('modalEliminar'));

document.querySelectorAll('.btn-eliminar').forEach(btn => {
    btn.addEventListener('click', function () {
        eliminarId = this.dataset.id;
        document.getElementById('modalCargoNombre').textContent = this.dataset.cargo;
        modalEliminar.show();
    });
});

document.getElementById('btnConfirmarEliminar').addEventListener('click', function () {
    if (!eliminarId) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Eliminando...';

    fetch('ajax/config_cargos_reclamos_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=eliminar&id=' + eliminarId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            modalEliminar.hide();
            location.reload();
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash-alt me-2"></i>Eliminar';
        }
    })
    .catch(() => {
        alert('Error de conexión.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash-alt me-2"></i>Eliminar';
    });
});
</script>
</body>
</html>
