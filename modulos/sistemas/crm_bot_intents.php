<?php
/**
 * crm_bot_intents.php — CRUD de intenciones del bot
 * Módulo: sistemas
 */
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/database/conexion.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('crm_bot', 'gestionar_intents', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intenciones CRM Bot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?= mt_rand(1, 9999) ?>">
    <style>
        :root {
            --color-principal: #51B8AC;
            --color-header-tabla: #0E544C;
        }

        .btn-nuevo {
            background: #218838;
            color: #fff;
            border: none;
        }

        .btn-nuevo:hover {
            background: #1d6f42;
            color: #fff;
        }

        .badge-activo {
            background: #28a745;
        }

        .badge-inactivo {
            background: #dc3545;
        }

        .card-intent {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .card-intent-header {
            background: var(--color-header-tabla);
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .keyword-tag {
            display: inline-block;
            background: #e6f7f5;
            color: #0E544C;
            border-radius: 12px;
            padding: 2px 10px;
            font-size: 12px;
            margin: 2px;
            border: 1px solid #51B8AC;
        }

        .template-item {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 6px;
            font-size: 13px;
            position: relative;
        }
    </style>
</head>

<body>
    <?= renderMenuLateral($cargoOperario) ?>
    <div class="main-container">
        <div class="sub-container">
            <?= renderHeader($usuario, false, 'Intenciones CRM Bot') ?>

            <div class="container-fluid p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <a href="crm_bot.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Volver al CRM
                    </a>
                    <button class="btn btn-nuevo btn-sm" onclick="abrirModalNuevo()">
                        <i class="bi bi-plus-circle me-1"></i> Nueva Intención
                    </button>
                </div>

                <!-- Filtros rápidos -->
                <div class="mb-3 d-flex gap-2">
                    <input type="text" id="filtroBuscar" class="form-control form-control-sm" style="max-width:250px"
                        placeholder="🔍 Buscar intención..." oninput="filtrarIntents()">
                    <select id="filtroActivo" class="form-select form-select-sm" style="max-width:150px"
                        onchange="filtrarIntents()">
                        <option value="">Todos</option>
                        <option value="1">✅ Activos</option>
                        <option value="0">❌ Inactivos</option>
                    </select>
                </div>

                <div id="listaIntents">
                    <div class="text-center py-5 text-muted"><i class="bi bi-arrow-repeat fs-3 spin"></i><br>Cargando...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal crear/editar intención -->
    <div class="modal fade" id="modalIntent" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background:var(--color-header-tabla);color:#fff">
                    <h6 class="modal-title mb-0" id="modalIntentTitle">Nueva Intención</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="intentId" value="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nombre interno <span class="text-danger">*</span></label>
                            <input type="text" id="intentNombre" class="form-control"
                                placeholder="ej: saludo, horario, queja">
                            <small class="text-muted">Sin espacios, en minúsculas</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Prioridad</label>
                            <input type="number" id="intentPrioridad" class="form-control" value="5" min="0" max="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Estado</label>
                            <select id="intentActivo" class="form-select">
                                <option value="1">✅ Activo</option>
                                <option value="0">❌ Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Keywords (separadas por coma)</label>
                            <textarea id="intentKeywords" class="form-control" rows="2"
                                placeholder="hola, buenos días, buenas tardes, hey..."></textarea>
                            <small class="text-muted">El bot busca estas palabras en el mensaje del cliente</small>
                        </div>
                        <div class="col-12 mb-2">
                            <label class="form-label fw-bold">Templates de respuesta</label>
                            <small class="text-muted d-block mb-2">
                                Escribe una variante por línea. Usa <code>{{nombre}}</code> para insertar el nombre del
                                cliente.
                                El bot elegirá aleatoriamente entre las variantes.
                            </small>
                            <div id="templatesList"></div>
                            <button class="btn btn-sm btn-outline-secondary mt-1" onclick="agregarTemplate()">
                                <i class="bi bi-plus me-1"></i> Agregar variante
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-nuevo" onclick="guardarIntent()">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let todosLosIntents = [];

        $(document).ready(() => cargarIntents());

        async function cargarIntents() {
            const resp = await fetch('ajax/crm_bot_get_intents.php');
            const data = await resp.json();
            todosLosIntents = data.intents || [];
            filtrarIntents();
        }

        function filtrarIntents() {
            const q = $('#filtroBuscar').val().toLowerCase();
            const activo = $('#filtroActivo').val();
            const filtrados = todosLosIntents.filter(i => {
                const matchQ = !q || i.intent_name.includes(q) || (i.keywords || '').toLowerCase().includes(q);
                const matchA = activo === '' || String(i.is_active) === activo;
                return matchQ && matchA;
            });
            renderIntents(filtrados);
        }

        function renderIntents(intents) {
            const c = document.getElementById('listaIntents');
            if (!intents.length) {
                c.innerHTML = '<div class="text-center text-muted py-4">Sin intenciones</div>'; return;
            }
            c.innerHTML = intents.map(i => {
                const kws = (i.keywords || '').split(',').filter(k => k.trim()).map(k => `<span class="keyword-tag">${k.trim()}</span>`).join('');
                const tmpl = JSON.parse(i.response_templates || '[]');
                const templHtml = tmpl.slice(0, 2).map(t => `<div class="template-item">${escapeHtml(t)}</div>`).join('');
                return `
            <div class="card-intent">
                <div class="card-intent-header">
                    <span><i class="bi bi-robot me-2"></i>${escapeHtml(i.intent_name)}
                        <span class="badge ${i.is_active ? 'badge-activo' : 'badge-inactivo'} ms-2" style="font-size:10px">
                            ${i.is_active ? 'Activo' : 'Inactivo'}
                        </span>
                        <span class="badge bg-secondary ms-1" style="font-size:10px">Prioridad: ${i.priority}</span>
                    </span>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-light" onclick='editarIntent(${JSON.stringify(i)})'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarIntent(${i.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="p-3">
                    <div class="mb-2"><strong>Keywords:</strong> ${kws || '<span class="text-muted small">Ninguna</span>'}</div>
                    <div><strong>Templates:</strong><br>${templHtml}
                        ${tmpl.length > 2 ? `<small class="text-muted">...y ${tmpl.length - 2} más</small>` : ''}
                    </div>
                </div>
            </div>`;
            }).join('');
        }

        function escapeHtml(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function abrirModalNuevo() {
            document.getElementById('intentId').value = '';
            document.getElementById('intentNombre').value = '';
            document.getElementById('intentPrioridad').value = 5;
            document.getElementById('intentActivo').value = 1;
            document.getElementById('intentKeywords').value = '';
            document.getElementById('templatesList').innerHTML = '';
            document.getElementById('modalIntentTitle').textContent = 'Nueva Intención';
            agregarTemplate(); agregarTemplate();
            new bootstrap.Modal(document.getElementById('modalIntent')).show();
        }

        function editarIntent(i) {
            document.getElementById('intentId').value = i.id;
            document.getElementById('intentNombre').value = i.intent_name;
            document.getElementById('intentPrioridad').value = i.priority;
            document.getElementById('intentActivo').value = i.is_active;
            document.getElementById('intentKeywords').value = i.keywords || '';
            document.getElementById('modalIntentTitle').textContent = 'Editar: ' + i.intent_name;
            const tmpl = JSON.parse(i.response_templates || '[]');
            document.getElementById('templatesList').innerHTML = '';
            tmpl.forEach(t => agregarTemplate(t));
            if (!tmpl.length) agregarTemplate();
            new bootstrap.Modal(document.getElementById('modalIntent')).show();
        }

        function agregarTemplate(val = '') {
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `<textarea class="form-control template-txt" rows="2" placeholder="Variante de respuesta... usa {{nombre}}">${escapeHtml(val)}</textarea>
            <button class="btn btn-outline-danger" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>`;
            document.getElementById('templatesList').appendChild(div);
        }

        async function guardarIntent() {
            const id = document.getElementById('intentId').value;
            const nombre = document.getElementById('intentNombre').value.trim();
            const prioridad = parseInt(document.getElementById('intentPrioridad').value) || 5;
            const activo = parseInt(document.getElementById('intentActivo').value);
            const keywords = document.getElementById('intentKeywords').value.trim();
            const templates = [...document.querySelectorAll('.template-txt')]
                .map(t => t.value.trim()).filter(Boolean);

            if (!nombre) return Swal.fire('Error', 'El nombre es requerido', 'error');
            if (!templates.length) return Swal.fire('Error', 'Agrega al menos un template de respuesta', 'error');

            const resp = await fetch('ajax/crm_bot_guardar_intent.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id || null, intent_name: nombre, keywords, response_templates: templates, priority: prioridad, is_active: activo })
            });
            const data = await resp.json();
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalIntent'))?.hide();
                Swal.fire({ icon: 'success', title: 'Guardado', timer: 1500, showConfirmButton: false });
                cargarIntents();
            } else {
                Swal.fire('Error', data.error, 'error');
            }
        }

        async function eliminarIntent(id) {
            const conf = await Swal.fire({ title: '¿Eliminar intención?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545' });
            if (!conf.isConfirmed) return;
            const resp = await fetch('ajax/crm_bot_eliminar_intent.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
            const data = await resp.json();
            if (data.success) { Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1200, showConfirmButton: false }); cargarIntents(); }
            else Swal.fire('Error', data.error, 'error');
        }
    </script>
</body>

</html>