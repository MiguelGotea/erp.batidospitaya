<?php
/**
 * celulares_asignados.php — Administración de celulares asignados
 * Módulo: sistemas
 */
$version = mt_rand(1, 10000);

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('celulares_asignados', 'vista', $cargoOperario)) {
    header('Location: ../../index.php?error=no_permiso');
    exit();
}

$puedeCrear   = tienePermiso('celulares_asignados', 'crear',   $cargoOperario);
$puedeEditar  = tienePermiso('celulares_asignados', 'editar',  $cargoOperario);
$puedeEliminar= tienePermiso('celulares_asignados', 'eliminar',$cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Celulares Asignados — Batidos Pitaya</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <style>
        :root {
            --color-principal: #51B8AC;
            --color-header-tabla: #0E544C;
            --color-hover-btn: #3fa195;
            --bg-glass: rgba(255,255,255,0.85);
        }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .main-card { border: none; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); background: var(--bg-glass); backdrop-filter: blur(10px); margin-bottom: 30px; }
        .card-header-custom { background-color: var(--color-header-tabla); color: #fff; border-top-left-radius: 16px !important; border-top-right-radius: 16px !important; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .table-container { overflow-x: auto; border-radius: 12px; border: 1px solid #e2e8f0; }
        .custom-table { width: 100%; margin-bottom: 0; background-color: #fff; }
        .custom-table th { background-color: #f1f5f9; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; padding: 12px 16px; border-bottom: 2px solid #e2e8f0; }
        .custom-table td { padding: 13px 16px; vertical-align: middle; font-size: 0.875rem; color: #334155; border-bottom: 1px solid #e2e8f0; }
        .custom-table tbody tr:hover { background-color: #f8fafc; }
        .btn-custom-primary { background-color: var(--color-principal); color: white; border: none; font-weight: 600; transition: all 0.2s ease; }
        .btn-custom-primary:hover { background-color: var(--color-hover-btn); color: white; transform: translateY(-1px); }
        .filter-panel { background-color: #fff; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .filter-title { font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px; }
        .audit-info { font-size: 0.7rem; color: #94a3b8; display: block; margin-top: 2px; }
        .device-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #0E544C, #51B8AC); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.1rem; flex-shrink: 0; }
        .tech-badge { font-family: monospace; font-size: 0.75rem; background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 5px; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<?php echo renderMenuLateral($cargoOperario); ?>
<div class="main-container">
    <div class="sub-container">
        <?php echo renderHeader($usuario, 'Celulares Asignados'); ?>

        <div class="container-fluid p-4">

            <!-- Filtros -->
            <div class="filter-panel">
                <div class="filter-title"><i class="bi bi-funnel-fill"></i> Filtros de Búsqueda</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label text-muted small">Buscar</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="filtroBuscar" class="form-control bg-light border-start-0" placeholder="Nombre, modelo, serie, IMEI..." oninput="filtrarCelulares()">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Sucursal</label>
                        <select id="filtroSucursal" class="form-select bg-light" onchange="filtrarCelulares()">
                            <option value="">Todas las sucursales</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Departamento</label>
                        <select id="filtroDepartamento" class="form-select bg-light" onchange="filtrarCelulares()">
                            <option value="">Todos los departamentos</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-outline-secondary w-100" onclick="limpiarFiltros()" title="Limpiar Filtros">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tarjeta principal -->
            <div class="card main-card">
                <div class="card-header-custom">
                    <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-phone-fill"></i> Listado de Dispositivos Asignados
                    </h5>
                    <?php if ($puedeCrear): ?>
                        <button class="btn btn-custom-primary btn-sm rounded-pill px-3" onclick="abrirModalCrear()">
                            <i class="bi bi-plus-circle me-1"></i> Nuevo Dispositivo
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-3">
                    <div class="table-container">
                        <table class="table custom-table align-middle">
                            <thead>
                                <tr>
                                    <th>Dispositivo</th>
                                    <th>Serie / IMEI</th>
                                    <th>No. SIM</th>
                                    <th>Sucursal</th>
                                    <th>Cargo / Área</th>
                                    <th>Colaborador</th>
                                    <th class="text-center" style="width:90px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyCelulares">
                                <tr><td colspan="7" class="text-center py-5 text-muted">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>Cargando...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Guardar / Editar -->
<div class="modal fade" id="modalCelular" tabindex="-1" aria-labelledby="modalCelularTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header text-white" style="background-color:var(--color-header-tabla);border-top-left-radius:16px;border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold" id="modalCelularTitle">Nuevo Dispositivo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formCelular">
                    <input type="hidden" id="modalId" name="id">
                    <div class="row g-3">
                        <!-- Nombre / Alias -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nombre / Alias <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modalNombre" name="nombre" required placeholder="Ej: Celular Tienda Granada">
                        </div>
                        <!-- Modelo -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Modelo</label>
                            <input type="text" class="form-control" id="modalModelo" name="modelo" placeholder="Ej: Samsung Galaxy A15">
                        </div>
                        <!-- Serie -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Número de Serie</label>
                            <input type="text" class="form-control" id="modalSerie" name="serie" placeholder="S/N del dispositivo">
                        </div>
                        <!-- No. SIM -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Número SIM / Chip</label>
                            <input type="number" class="form-control" id="modalNoSim" name="no_sim" placeholder="Número del chip">
                        </div>
                        <!-- IMEI -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">IMEI</label>
                            <input type="number" class="form-control" id="modalIMEI" name="IMEI" placeholder="15 dígitos">
                        </div>
                        <!-- IMSI -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">IMSI</label>
                            <input type="number" class="form-control" id="modalIMSI" name="IMSI" placeholder="Número IMSI del chip">
                        </div>
                        <!-- Sucursal -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sucursal <span class="text-danger">*</span></label>
                            <select class="form-select" id="modalSucursal" name="cod_sucursal" required>
                                <option value="">-- Seleccione Sucursal --</option>
                            </select>
                        </div>
                        <!-- Cargo -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cargo Asignado <span class="text-danger">*</span></label>
                            <select class="form-select" id="modalCargo" name="cargo_asignado" required>
                                <option value="">-- Seleccione Cargo --</option>
                            </select>
                        </div>
                        <!-- Departamento -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Departamento / Área <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modalDepartamento" name="departamento" required placeholder="Ej: Operaciones">
                            <small class="text-muted">Se pre-completa al elegir el cargo.</small>
                        </div>
                        <!-- Colaborador -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Colaborador Asignado</label>
                            <select class="form-select" id="modalUsuarioUso" name="usuario_uso">
                                <option value="">-- Sin asignar --</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-custom-primary rounded-pill px-4" onclick="guardarCelular()">
                    <i class="bi bi-save me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const PUEDE_CREAR   = <?php echo $puedeCrear    ? 'true' : 'false'; ?>;
    const PUEDE_EDITAR  = <?php echo $puedeEditar   ? 'true' : 'false'; ?>;
    const PUEDE_ELIMINAR= <?php echo $puedeEliminar ? 'true' : 'false'; ?>;

    let todosCelulares = [];
    let catalogos = {};
    let bsModal = null;

    $(document).ready(async () => {
        bsModal = new bootstrap.Modal(document.getElementById('modalCelular'));
        await cargarCatalogos();
        await cargarCelulares();

        $('#modalCargo').on('change', function() {
            const cid = parseInt($(this).val());
            const cargo = (catalogos.cargos || []).find(c => c.CodNivelesCargos === cid);
            if (cargo) $('#modalDepartamento').val(cargo.Area);
        });
    });

    async function cargarCatalogos() {
        try {
            const res = await (await fetch('ajax/celulares_get_catalogos.php')).json();
            if (!res.success) return;
            catalogos = res;

            // Sucursales
            let sucOpts = '<option value="">-- Seleccione Sucursal --</option>';
            res.sucursales.forEach(s => {
                sucOpts += `<option value="${s.codigo}">${s.nombre}</option>`;
                $('#filtroSucursal').append(`<option value="${s.codigo}">${s.nombre}</option>`);
            });
            $('#modalSucursal').html(sucOpts);

            // Cargos
            let cgOpts = '<option value="">-- Seleccione Cargo --</option>';
            const areas = new Set();
            res.cargos.forEach(c => {
                cgOpts += `<option value="${c.CodNivelesCargos}">${c.Nombre} (${c.Area})</option>`;
                if (c.Area) areas.add(c.Area);
            });
            $('#modalCargo').html(cgOpts);
            areas.forEach(a => $('#filtroDepartamento').append(`<option value="${a}">${a}</option>`));

            // Operarios
            let opOpts = '<option value="">-- Sin asignar --</option>';
            res.operarios.forEach(o => {
                const nom = [o.Nombre, o.Nombre2, o.Apellido, o.Apellido2].filter(Boolean).join(' ');
                opOpts += `<option value="${o.CodOperario}">${nom}</option>`;
            });
            $('#modalUsuarioUso').html(opOpts);
        } catch(e) { console.error('Error catálogos', e); }
    }

    async function cargarCelulares() {
        $('#tbodyCelulares').html('<tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Cargando...</td></tr>');
        try {
            const res = await (await fetch('ajax/celulares_get.php')).json();
            if (res.success) { todosCelulares = res.data || []; filtrarCelulares(); }
            else $('#tbodyCelulares').html(`<tr><td colspan="7" class="text-center text-danger py-4">${res.error}</td></tr>`);
        } catch(e) {
            $('#tbodyCelulares').html('<tr><td colspan="7" class="text-center text-danger py-4">Error de conexión.</td></tr>');
        }
    }

    function filtrarCelulares() {
        const q    = $('#filtroBuscar').val().toLowerCase();
        const suc  = $('#filtroSucursal').val();
        const depto= $('#filtroDepartamento').val();

        const f = todosCelulares.filter(c =>
            (!q   || [c.nombre, c.modelo, c.serie, c.IMEI, c.usuario_uso_nombre].some(v => (v||'').toLowerCase().includes(q))) &&
            (!suc  || c.cod_sucursal === suc) &&
            (!depto|| c.departamento === depto)
        );
        renderCelulares(f);
    }

    function renderCelulares(list) {
        if (!list.length) {
            $('#tbodyCelulares').html('<tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-phone-x fs-2 d-block mb-2"></i>No se encontraron dispositivos.</td></tr>');
            return;
        }
        let html = '';
        list.forEach(c => {
            let audit = '';
            if (c.creador_nombre)     audit += `Creó: ${esc(c.creador_nombre)}`;
            if (c.modificador_nombre) audit += `${audit ? ' | ' : ''}Modificó: ${esc(c.modificador_nombre)}`;

            const btnEdit = PUEDE_EDITAR   ? `<button class="btn btn-sm btn-outline-secondary" onclick="abrirModalEditar(${c.id})" title="Editar"><i class="bi bi-pencil"></i></button>` : '';
            const btnDel  = PUEDE_ELIMINAR ? `<button class="btn btn-sm btn-outline-danger" onclick="eliminarCelular(${c.id})" title="Eliminar"><i class="bi bi-trash"></i></button>` : '';

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="device-icon"><i class="bi bi-phone-fill"></i></div>
                            <div>
                                <strong class="d-block">${esc(c.nombre)}</strong>
                                <span class="text-muted small">${esc(c.modelo || 'Sin modelo')}</span>
                                ${audit ? `<span class="audit-info">${audit}</span>` : ''}
                            </div>
                        </div>
                    </td>
                    <td>
                        ${c.serie  ? `<span class="tech-badge d-block mb-1">S/N: ${esc(c.serie)}</span>` : ''}
                        ${c.IMEI   ? `<span class="tech-badge d-block">IMEI: ${esc(c.IMEI)}</span>` : '<em class="text-muted small">Sin datos</em>'}
                    </td>
                    <td>${c.no_sim ? `<span class="tech-badge">${esc(c.no_sim)}</span>` : '<em class="text-muted small">—</em>'}</td>
                    <td><strong>${esc(c.sucursal_nombre || c.cod_sucursal)}</strong></td>
                    <td>
                        <strong class="d-block">${esc(c.cargo_nombre || '—')}</strong>
                        <span class="text-muted small">${esc(c.departamento || '')}</span>
                    </td>
                    <td>${c.usuario_uso ? `<strong>${esc(c.usuario_uso_nombre)}</strong>` : '<em class="text-muted small">No asignado</em>'}</td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">${btnEdit}${btnDel}</div>
                    </td>
                </tr>`;
        });
        $('#tbodyCelulares').html(html);
    }

    function limpiarFiltros() {
        $('#filtroBuscar, #filtroSucursal, #filtroDepartamento').val('');
        filtrarCelulares();
    }

    function abrirModalCrear() {
        $('#modalCelularTitle').text('Nuevo Dispositivo');
        $('#formCelular')[0].reset();
        $('#modalId').val('');
        bsModal.show();
    }

    async function abrirModalEditar(id) {
        try {
            const res = await (await fetch(`ajax/celulares_get.php?id=${id}`)).json();
            if (!res.success) { Swal.fire('Error', res.error, 'error'); return; }
            const c = res.data;
            $('#modalCelularTitle').text('Editar Dispositivo');
            $('#modalId').val(c.id);
            $('#modalNombre').val(c.nombre);
            $('#modalModelo').val(c.modelo || '');
            $('#modalSerie').val(c.serie || '');
            $('#modalNoSim').val(c.no_sim || '');
            $('#modalIMEI').val(c.IMEI || '');
            $('#modalIMSI').val(c.IMSI || '');
            $('#modalSucursal').val(c.cod_sucursal);
            $('#modalCargo').val(c.cargo_asignado);
            $('#modalDepartamento').val(c.departamento || '');
            $('#modalUsuarioUso').val(c.usuario_uso || '');
            bsModal.show();
        } catch(e) { Swal.fire('Error', 'Error de red al cargar el registro.', 'error'); }
    }

    async function guardarCelular() {
        const form = document.getElementById('formCelular');
        if (!form.checkValidity()) { form.reportValidity(); return; }
        try {
            const res = await (await fetch('ajax/celulares_guardar.php', { method: 'POST', body: new FormData(form) })).json();
            if (res.success) {
                bsModal.hide();
                Swal.fire({ icon: 'success', title: '¡Guardado!', text: res.message, timer: 2000, showConfirmButton: false });
                cargarCelulares();
            } else { Swal.fire('Error', res.error, 'error'); }
        } catch(e) { Swal.fire('Error', 'Error de red al guardar.', 'error'); }
    }

    async function eliminarCelular(id) {
        const r = await Swal.fire({
            title: '¿Eliminar este dispositivo?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
        });
        if (!r.isConfirmed) return;
        try {
            const res = await (await fetch('ajax/celulares_eliminar.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id })
            })).json();
            if (res.success) {
                Swal.fire({ icon: 'success', title: '¡Eliminado!', text: res.message, timer: 1500, showConfirmButton: false });
                cargarCelulares();
            } else { Swal.fire('Error', res.error, 'error'); }
        } catch(e) { Swal.fire('Error', 'Error de red al eliminar.', 'error'); }
    }

    function esc(t) {
        if (!t) return '';
        return t.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
</script>
</body>
</html>
