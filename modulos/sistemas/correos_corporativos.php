<?php
/**
 * correos_corporativos.php — Administración de correos corporativos
 * Módulo: sistemas
 */
$version = mt_rand(1, 10000);

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('correos_corporativos', 'vista', $cargoOperario)) {
    header('Location: ../../index.php?error=no_permiso');
    exit();
}

$puedeCrear = tienePermiso('correos_corporativos', 'crear', $cargoOperario);
$puedeEditar = tienePermiso('correos_corporativos', 'editar', $cargoOperario);
$puedeEliminar = tienePermiso('correos_corporativos', 'eliminar', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correos Corporativos — Batidos Pitaya</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap & FontAwesome & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo $version; ?>">
    
    <style>
        :root {
            --color-principal: #51B8AC;
            --color-header-tabla: #0E544C;
            --color-hover-btn: #3fa195;
            --bg-glass: rgba(255, 255, 255, 0.85);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .main-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            background: var(--bg-glass);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .card-header-custom {
            background-color: var(--color-header-tabla);
            color: #ffffff;
            border-top-left-radius: 16px !important;
            border-top-right-radius: 16px !important;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .custom-table {
            width: 100%;
            margin-bottom: 0;
            background-color: #fff;
        }

        .custom-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .custom-table td {
            padding: 14px 16px;
            vertical-align: middle;
            font-size: 0.875rem;
            color: #334155;
            border-bottom: 1px solid #e2e8f0;
        }

        .custom-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .badge-provider {
            font-weight: 600;
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .provider-gmail {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
        }

        .provider-outlook {
            background-color: #eff6ff;
            color: #2563eb;
            border: 1px solid #dbeafe;
        }

        .badge-status {
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 99px;
        }

        .status-activo {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-inactivo {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-suspendido {
            background-color: #fef3c7;
            color: #92400e;
        }

        .btn-custom-primary {
            background-color: var(--color-principal);
            color: white;
            border: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-custom-primary:hover {
            background-color: var(--color-hover-btn);
            color: white;
            transform: translateY(-1px);
        }

        .password-field {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: monospace;
            background-color: #f8fafc;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            max-width: fit-content;
        }

        .password-toggle-btn, .copy-btn {
            background: none;
            border: none;
            color: #64748b;
            padding: 2px;
            cursor: pointer;
            transition: color 0.15s;
        }

        .password-toggle-btn:hover, .copy-btn:hover {
            color: var(--color-principal);
        }

        .filter-panel {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .filter-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .audit-info {
            font-size: 0.7rem;
            color: #94a3b8;
            display: block;
            margin-top: 2px;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Correos Corporativos'); ?>

            <div class="container-fluid p-4">
                
                <!-- Barra de filtros -->
                <div class="filter-panel">
                    <div class="filter-title">
                        <i class="bi bi-funnel-fill"></i>
                        <span>Filtros de Búsqueda</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label text-muted small">Buscar por cuenta o nombre</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" id="filtroBuscar" class="form-control bg-light border-start-0" placeholder="Buscar correo, usuario, cargo..." oninput="filtrarCorreos()">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Proveedor</label>
                            <select id="filtroProveedor" class="form-select bg-light" onchange="filtrarCorreos()">
                                <option value="">Todos los proveedores</option>
                                <option value="gmail">Gmail</option>
                                <option value="outlook">Outlook</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Estado</label>
                            <select id="filtroEstado" class="form-select bg-light" onchange="filtrarCorreos()">
                                <option value="">Todos los estados</option>
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                                <option value="2">Suspendido</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button class="btn btn-outline-secondary w-100" onclick="limpiarFiltros()" title="Limpiar Filtros">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta Principal -->
                <div class="card main-card">
                    <div class="card-header-custom">
                        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i class="bi bi-envelope-at-fill"></i>
                            Listado de Cuentas Corporativas
                        </h5>
                        <?php if ($puedeCrear): ?>
                            <button class="btn btn-custom-primary btn-sm rounded-pill px-3" onclick="abrirModalCrear()">
                                <i class="bi bi-plus-circle me-1"></i> Nueva Cuenta
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body p-3">
                        <div class="table-container">
                            <table class="table custom-table align-middle">
                                <thead>
                                    <tr>
                                        <th>Correo</th>
                                        <th>Proveedor</th>
                                        <th>Usuario / Depto.</th>
                                        <th>Contraseña</th>
                                        <th>Asignación</th>
                                        <th>Estado</th>
                                        <th class="text-center" style="width: 100px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyCorreos">
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <div class="spinner-border text-teal spinner-border-sm me-2" role="status"></div>
                                            Cargando cuentas...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div><!-- /container-fluid -->
        </div>
    </div>

    <!-- Modal de Guardar / Editar -->
    <div class="modal fade" id="modalCorreo" tabindex="-1" aria-labelledby="modalCorreoTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header text-white" style="background-color: var(--color-header-tabla); border-top-left-radius: 16px; border-top-right-radius: 16px;">
                    <h5 class="modal-title fw-bold" id="modalCorreoTitle">Nueva Cuenta Corporativa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formCorreo">
                        <input type="hidden" id="modalId" name="id" value="">
                        
                        <div class="row g-3">
                            <!-- Correo -->
                            <div class="col-md-8">
                                <label for="modalCorreoInput" class="form-label fw-bold">Correo Electrónico <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="modalCorreoInput" name="correo" required placeholder="nombre@batidospitaya.com">
                            </div>
                            
                            <!-- Proveedor -->
                            <div class="col-md-4">
                                <label for="modalProveedor" class="form-label fw-bold">Proveedor <span class="text-danger">*</span></label>
                                <select class="form-select" id="modalProveedor" name="proveedor" required>
                                    <option value="outlook">Outlook</option>
                                    <option value="gmail">Gmail</option>
                                </select>
                            </div>

                            <!-- Contraseña -->
                            <div class="col-md-6">
                                <label for="modalPassword" class="form-label fw-bold">Contraseña del Correo</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="modalPassword" name="password_correo" placeholder="Contraseña de la cuenta">
                                    <button class="btn btn-outline-secondary" type="button" onclick="generarPassword()" title="Generar Contraseña">
                                        <i class="bi bi-key-fill"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Cargo -->
                            <div class="col-md-6">
                                <label for="modalCargoAsignado" class="form-label fw-bold">Cargo Asignado <span class="text-danger">*</span></label>
                                <select class="form-select" id="modalCargoAsignado" name="cargo_asignado" required>
                                    <!-- Dinámico -->
                                </select>
                            </div>

                            <!-- Usuario/Etiqueta -->
                            <div class="col-md-6">
                                <label for="modalNombreUsuario" class="form-label fw-bold">Nombre del Usuario / Rol</label>
                                <input type="text" class="form-control" id="modalNombreUsuario" name="nombre_usuario" placeholder="Ej: Líder Tienda Granada">
                                <small class="text-muted">Se pre-completa según el cargo, pero puede personalizarse.</small>
                            </div>

                            <!-- Departamento -->
                            <div class="col-md-6">
                                <label for="modalDepartamento" class="form-label fw-bold">Departamento / Área</label>
                                <input type="text" class="form-control" id="modalDepartamento" name="departamento" placeholder="Ej: Operaciones">
                            </div>

                            <!-- Asignado a (Operario) -->
                            <div class="col-md-6">
                                <label for="modalAsignadoA" class="form-label fw-bold">Colaborador Asignado</label>
                                <select class="form-select" id="modalAsignadoA" name="asignado_a">
                                    <!-- Dinámico -->
                                </select>
                            </div>

                            <!-- Fecha Asignación -->
                            <div class="col-md-3">
                                <label for="modalFechaAsignacion" class="form-label fw-bold">Fecha Asignación</label>
                                <input type="date" class="form-control" id="modalFechaAsignacion" name="fecha_asignacion">
                            </div>

                            <!-- Estado -->
                            <div class="col-md-3">
                                <label for="modalEstado" class="form-label fw-bold">Estado <span class="text-danger">*</span></label>
                                <select class="form-select" id="modalEstado" name="estado" required>
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                    <option value="2">Suspendido</option>
                                </select>
                            </div>

                            <!-- Observaciones -->
                            <div class="col-12">
                                <label for="modalObservaciones" class="form-label fw-bold">Observaciones</label>
                                <textarea class="form-control" id="modalObservaciones" name="observaciones" rows="3" placeholder="Detalles adicionales, número de teléfono asignado, etc."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-custom-primary rounded-pill px-4" onclick="guardarCorreo()">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        const PUEDE_CREAR = <?php echo $puedeCrear ? 'true' : 'false'; ?>;
        const PUEDE_EDITAR = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
        const PUEDE_ELIMINAR = <?php echo $puedeEliminar ? 'true' : 'false'; ?>;
        
        let todosLosCorreos = [];
        let catalogos = { operarios: [], cargos: [] };
        let bootstrapModal = null;

        $(document).ready(async function() {
            bootstrapModal = new bootstrap.Modal(document.getElementById('modalCorreo'));
            await cargarCatalogos();
            await cargarCorreos();
            
            // Listener de cambio de cargo para pre-llenar Campos
            $('#modalCargoAsignado').on('change', function() {
                const cargoId = parseInt($(this).value || $(this).val());
                const cargo = catalogos.cargos.find(c => c.CodNivelesCargos === cargoId);
                if (cargo) {
                    $('#modalNombreUsuario').val(cargo.Nombre);
                    $('#modalDepartamento').val(cargo.Area);
                }
            });
        });

        // Cargar operadores y cargos
        async function cargarCatalogos() {
            try {
                const resp = await fetch('ajax/correos_get_catalogos.php');
                const res = await resp.json();
                if (res.success) {
                    catalogos = res;
                    
                    // nombre_completo viene pre-construido desde PHP con CONCAT_WS,
                    // garantizando que operarios sin Nombre2 o Apellido2 se muestren correctamente.
                    let opHtml = '<option value="">-- Sin asignar / Ninguno --</option>';
                    res.operarios.forEach(o => {
                        opHtml += `<option value="${o.CodOperario}">${o.nombre_completo}</option>`;
                    });
                    $('#modalAsignadoA').html(opHtml);

                    // Llenar select de cargos
                    let cgHtml = '<option value="">-- Seleccione Cargo --</option>';
                    res.cargos.forEach(c => {
                        cgHtml += `<option value="${c.CodNivelesCargos}">${c.Nombre} (${c.Area})</option>`;
                    });
                    $('#modalCargoAsignado').html(cgHtml);
                }
            } catch (e) {
                console.error("Error cargando catálogos", e);
            }
        }

        // Cargar correos desde base de datos
        async function cargarCorreos() {
            try {
                $('#tbodyCorreos').html(`
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <div class="spinner-border text-teal spinner-border-sm me-2" role="status"></div>
                            Cargando cuentas...
                        </td>
                    </tr>
                `);
                
                const resp = await fetch('ajax/correos_get.php');
                const res = await resp.json();
                if (res.success) {
                    todosLosCorreos = res.data || [];
                    filtrarCorreos();
                } else {
                    $('#tbodyCorreos').html(`<tr><td colspan="7" class="text-center text-danger py-4">${res.error}</td></tr>`);
                }
            } catch (e) {
                console.error("Error cargando correos", e);
                $('#tbodyCorreos').html('<tr><td colspan="7" class="text-center text-danger py-4">Error de conexión al cargar datos.</td></tr>');
            }
        }

        // Filtrado en el cliente
        function filtrarCorreos() {
            const q = $('#filtroBuscar').val().toLowerCase();
            const prov = $('#filtroProveedor').val();
            const est = $('#filtroEstado').val();

            const filtrados = todosLosCorreos.filter(c => {
                const matchQ = !q || 
                              c.correo.toLowerCase().includes(q) || 
                              (c.nombre_usuario || '').toLowerCase().includes(q) || 
                              (c.cargo_nombre || '').toLowerCase().includes(q) || 
                              (c.asignado_a_nombre || '').toLowerCase().includes(q) || 
                              (c.departamento || '').toLowerCase().includes(q);
                              
                const matchProv = !prov || c.proveedor === prov;
                const matchEst = est === '' || String(c.estado) === est;

                return matchQ && matchProv && matchEst;
            });

            renderCorreos(filtrados);
        }

        // Renderizar filas de tabla
        function renderCorreos(list) {
            const tbody = $('#tbodyCorreos');
            if (list.length === 0) {
                tbody.html('<tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-envelope-x fs-2 d-block mb-2"></i>No se encontraron cuentas que coincidan con los filtros.</td></tr>');
                return;
            }

            let html = '';
            list.forEach(c => {
                const provBadge = c.proveedor === 'gmail' 
                    ? '<span class="badge-provider provider-gmail"><i class="bi bi-google"></i> Gmail</span>' 
                    : '<span class="badge-provider provider-outlook"><i class="bi bi-microsoft"></i> Outlook</span>';

                let estBadge = '';
                if (c.estado === 1) estBadge = '<span class="badge badge-status status-activo">Activo</span>';
                else if (c.estado === 0) estBadge = '<span class="badge badge-status status-inactivo">Inactivo</span>';
                else if (c.estado === 2) estBadge = '<span class="badge badge-status status-suspendido">Suspendido</span>';

                const usuarioYDepto = `
                    <strong class="d-block">${escapeHtml(c.nombre_usuario || 'Sin nombre')}</strong>
                    <span class="text-muted small">${escapeHtml(c.departamento || 'Sin área')}</span>
                `;

                const asignado = c.asignado_a 
                    ? `<strong>${escapeHtml(c.asignado_a_nombre)}</strong><br><span class="text-secondary small">Cargo: ${escapeHtml(c.cargo_nombre)}</span>` 
                    : '<em class="text-muted">No asignado</em>';

                // Campos de auditoría creador/modificador
                let auditoria = '';
                if (c.creador_nombre) auditoria += `Creador: ${escapeHtml(c.creador_nombre)}`;
                if (c.modificador_nombre) auditoria += `${auditoria ? ' | ' : ''}Modificó: ${escapeHtml(c.modificador_nombre)}`;
                const auditHtml = auditoria ? `<span class="audit-info" title="${auditoria}">${auditoria}</span>` : '';

                // Contraseña masked y toggle
                const passwordId = `pw-${c.id}`;
                const passField = c.password_correo 
                    ? `
                        <div class="password-field">
                            <span id="${passwordId}" data-pass="${escapeHtml(c.password_correo)}">••••••••</span>
                            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('${passwordId}')" title="Ver contraseña">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button type="button" class="copy-btn" onclick="copiarAlPortapapeles('${passwordId}')" title="Copiar contraseña">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    ` 
                    : '<em class="text-muted small">Sin contraseña</em>';

                let btnEditar = PUEDE_EDITAR 
                    ? `<button class="btn btn-sm btn-outline-secondary" onclick="abrirModalEditar(${c.id})" title="Editar"><i class="bi bi-pencil"></i></button>` 
                    : '';
                let btnEliminar = PUEDE_ELIMINAR 
                    ? `<button class="btn btn-sm btn-outline-danger" onclick="eliminarCorreo(${c.id})" title="Eliminar"><i class="bi bi-trash"></i></button>` 
                    : '';

                html += `
                    <tr>
                        <td>
                            <span class="fw-bold">${escapeHtml(c.correo)}</span>
                            ${auditHtml}
                        </td>
                        <td>${provBadge}</td>
                        <td>${usuarioYDepto}</td>
                        <td>${passField}</td>
                        <td>${asignado}</td>
                        <td>${estBadge}</td>
                        <td class="text-center">
                            <div class="btn-group gap-1">
                                ${btnEditar}
                                ${btnEliminar}
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbody.html(html);
        }

        // Toggles y Portapapeles
        function togglePasswordVisibility(id) {
            const el = document.getElementById(id);
            const icon = el.nextElementSibling.querySelector('i');
            const actualPass = el.getAttribute('data-pass');

            if (el.textContent === '••••••••') {
                el.textContent = actualPass;
                icon.className = 'bi bi-eye-slash';
            } else {
                el.textContent = '••••••••';
                icon.className = 'bi bi-eye';
            }
        }

        async function copiarAlPortapapeles(id) {
            const el = document.getElementById(id);
            const pass = el.getAttribute('data-pass');
            try {
                await navigator.clipboard.writeText(pass);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: '¡Contraseña copiada!',
                    showConfirmButton: false,
                    timer: 1500
                });
            } catch (err) {
                console.error('Error al copiar', err);
            }
        }

        // Limpiar Filtros
        function limpiarFiltros() {
            $('#filtroBuscar').val('');
            $('#filtroProveedor').val('');
            $('#filtroEstado').val('');
            filtrarCorreos();
        }

        // Abrir Modal de Creación
        function abrirModalCrear() {
            $('#modalCorreoTitle').text('Nueva Cuenta Corporativa');
            $('#formCorreo')[0].reset();
            $('#modalId').val('');
            
            // Establecer valores predeterminados
            $('#modalProveedor').val('outlook');
            $('#modalEstado').val('1');
            
            bootstrapModal.show();
        }

        // Abrir Modal de Edición
        async function abrirModalEditar(id) {
            try {
                const resp = await fetch(`ajax/correos_get.php?id=${id}`);
                const res = await resp.json();
                if (res.success && res.data) {
                    const c = res.data;
                    $('#modalCorreoTitle').text('Editar Cuenta Corporativa');
                    $('#modalId').val(c.id);
                    $('#modalCorreoInput').val(c.correo);
                    $('#modalProveedor').val(c.proveedor);
                    $('#modalPassword').val(c.password_correo || '');
                    $('#modalCargoAsignado').val(c.cargo_asignado);
                    $('#modalNombreUsuario').val(c.nombre_usuario || '');
                    $('#modalDepartamento').val(c.departamento || '');
                    $('#modalAsignadoA').val(c.asignado_a || '');
                    $('#modalFechaAsignacion').val(c.fecha_asignacion || '');
                    $('#modalEstado').val(c.estado);
                    $('#modalObservaciones').val(c.observaciones || '');
                    
                    bootstrapModal.show();
                } else {
                    Swal.fire('Error', res.error || 'No se pudo obtener el detalle de la cuenta.', 'error');
                }
            } catch (e) {
                console.error("Error cargando detalle", e);
                Swal.fire('Error', 'Error de red al cargar el registro.', 'error');
            }
        }

        // Guardar Datos
        async function guardarCorreo() {
            const form = document.getElementById('formCorreo');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            
            try {
                const resp = await fetch('ajax/correos_guardar.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await resp.json();

                if (res.success) {
                    bootstrapModal.hide();
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    cargarCorreos();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch (e) {
                console.error("Error guardando correo", e);
                Swal.fire('Error', 'Error de red al guardar los cambios.', 'error');
            }
        }

        // Eliminar Registro
        async function eliminarCorreo(id) {
            const result = await Swal.fire({
                title: '¿Está seguro de eliminar esta cuenta?',
                text: "Esta acción no se puede deshacer y eliminará permanentemente el registro.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                try {
                    const resp = await fetch('ajax/correos_eliminar.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ id })
                    });
                    const res = await resp.json();

                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Eliminado!',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        cargarCorreos();
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }
                } catch (e) {
                    console.error("Error eliminando cuenta", e);
                    Swal.fire('Error', 'Error de red al intentar eliminar la cuenta.', 'error');
                }
            }
        }

        // Generar Contraseña Aleatoria Sencilla y Segura
        function generarPassword() {
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*";
            let pass = "";
            for (let i = 0; i < 10; i++) {
                pass += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            $('#modalPassword').val(pass);
        }

        // Escapar HTML para evitar XSS
        function escapeHtml(text) {
            if (!text) return '';
            return text
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>

</html>
