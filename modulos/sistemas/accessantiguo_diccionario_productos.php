<?php
// diccionario_productos.php
$version = mt_rand(1, 10000);

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('diccionario_productos', 'vista', $cargoOperario)) {
    header('Location: ../../index.php');
    exit();
}

$puedeEditar = tienePermiso('diccionario_productos', 'edicion', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diccionario de Productos | Sistemas</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <style>
        /* ── Layout ────────────────────────────────────────── */
        body {
            background: #f0f2f5;
        }

        .page-header {
            background: linear-gradient(135deg, #1a2a4a 0%, #2d4a80 100%);
            color: #fff;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header h1 {
            font-size: 1.4rem;
            margin: 0;
            font-weight: 700;
        }

        .page-header p {
            margin: 4px 0 0;
            font-size: .85rem;
            opacity: .8;
        }

        /* ── Stats bar ─────────────────────────────────────── */
        .stats-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
            flex: 1;
            min-width: 160px;
        }

        .stat-card .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #fff;
        }

        .stat-card .stat-icon.total {
            background: #4e73df;
        }

        .stat-card .stat-icon.mapeados {
            background: #1cc88a;
        }

        .stat-card .stat-icon.pendientes {
            background: #f6c23e;
        }

        .stat-card .stat-num {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }

        .stat-card .stat-label {
            font-size: .75rem;
            color: #888;
        }

        /* ── Progress bar ──────────────────────────────────── */
        .progress-wrap {
            background: #fff;
            border-radius: 10px;
            padding: 14px 20px;
            margin-bottom: 16px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: .8rem;
            color: #555;
            margin-bottom: 6px;
        }

        .progress {
            height: 10px;
            border-radius: 10px;
        }

        /* ── Toolbar ───────────────────────────────────────── */
        .toolbar {
            background: #fff;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 14px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .toolbar input[type="text"] {
            flex: 1;
            min-width: 200px;
        }

        /* ── Table wrapper ─────────────────────────────────── */
        .table-wrap {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .09);
            overflow: hidden;
        }

        .table thead th {
            background: #1a2a4a;
            color: #fff;
            font-size: .78rem;
            font-weight: 600;
            white-space: nowrap;
            vertical-align: middle;
            border: none;
        }

        .table tbody td {
            vertical-align: middle;
            font-size: .82rem;
            border-bottom: 1px solid #f0f2f5;
        }

        .table tbody tr:hover {
            background: #f8f9ff;
        }

        /* ── Badge estado ──────────────────────────────────── */
        .badge-mapeado {
            background: #d4ede3;
            color: #1a7a52;
        }

        .badge-pendiente {
            background: #fff3cd;
            color: #856404;
        }

        /* ── Autocomplete ──────────────────────────────────── */
        .autocomplete-wrap {
            position: relative;
        }

        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 999;
            background: #fff;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 220px;
            overflow-y: auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .12);
            display: none;
        }

        .autocomplete-item {
            padding: 8px 12px;
            cursor: pointer;
            font-size: .82rem;
            border-bottom: 1px solid #f0f2f5;
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: #e8eeff;
        }

        .autocomplete-item .sku {
            font-weight: 700;
            color: #2d4a80;
        }

        .autocomplete-item .nom {
            color: #444;
        }

        .autocomplete-item .extra {
            color: #888;
            font-size: .75rem;
        }

        /* ── Acciones por fila ─────────────────────────────── */
        .btn-guardar-mapeo {
            white-space: nowrap;
        }

        /* ── Valor mapeado ─────────────────────────────────── */
        .valor-mapeado {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .82rem;
        }

        .valor-mapeado .sku-tag {
            background: #e8f4fd;
            color: #1a6fad;
            border-radius: 4px;
            padding: 1px 6px;
            font-weight: 600;
            font-size: .75rem;
        }

        /* ── Pagination ────────────────────────────────────── */
        .paginacion {
            padding: 12px 16px;
        }

        /* ── Loading ───────────────────────────────────────── */
        .loading-row td {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        /* ── Empty ─────────────────────────────────────────── */
        .empty-row td {
            text-align: center;
            padding: 50px;
            color: #aaa;
        }

        /* ── Toast ─────────────────────────────────────────── */
        .toast-container {
            z-index: 1100;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Diccionario de Productos'); ?>

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-exchange-alt me-2"></i>Diccionario de Productos</h1>
                    <p>Mapeo de presentaciones del sistema antiguo (Access) al nuevo ERP</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-light" id="btnExportar" title="Exportar mapeos">
                        <i class="fas fa-file-export me-1"></i> Exportar
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-bar" id="statsBar">
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fas fa-boxes"></i></div>
                    <div>
                        <div class="stat-num" id="statTotal">—</div>
                        <div class="stat-label">Total presentaciones</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon mapeados"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="stat-num" id="statMapeados">—</div>
                        <div class="stat-label">Mapeadas</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pendientes"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="stat-num" id="statPendientes">—</div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="progress-wrap">
                <div class="progress-label">
                    <span>Progreso de mapeo</span>
                    <span id="progresoPct">0%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar bg-success" id="progresoBar" style="width:0%"></div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="input-group" style="flex:1;min-width:220px">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="inputBusqueda"
                        placeholder="Buscar por nombre, marca, línea…">
                </div>
                <div class="btn-group" role="group" id="filtroGroup">
                    <input type="radio" class="btn-check" name="filtro" id="filtroTodos" value="todos" checked>
                    <label class="btn btn-outline-secondary btn-sm" for="filtroTodos">Todos</label>

                    <input type="radio" class="btn-check" name="filtro" id="filtroPendientes" value="pendientes">
                    <label class="btn btn-outline-warning btn-sm" for="filtroPendientes">Pendientes</label>

                    <input type="radio" class="btn-check" name="filtro" id="filtroMapeados" value="mapeados">
                    <label class="btn btn-outline-success btn-sm" for="filtroMapeados">Mapeados</label>
                </div>
                <select class="form-select form-select-sm" id="selectPorPagina" style="width:auto">
                    <option value="25">25 / pág</option>
                    <option value="50" selected>50 / pág</option>
                    <option value="100">100 / pág</option>
                </select>
            </div>

            <!-- Table -->
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tablaProductos">
                        <thead>
                            <tr>
                                <th style="width:24px">#</th>
                                <th>Producto Antiguo</th>
                                <th style="width:110px">Tipo</th>
                                <th style="width:80px">Estado</th>
                                <?php if ($puedeEditar): ?>
                                    <th style="min-width:280px">Producto Nuevo (búsqueda)</th>
                                    <th style="width:90px">Acción</th>
                                <?php else: ?>
                                    <th>Producto Nuevo</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tbodyProductos">
                            <tr class="loading-row">
                                <td colspan="6">
                                    <div class="spinner-border text-primary spinner-border-sm me-2"></div>
                                    Cargando productos…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="paginacion d-flex justify-content-between align-items-center border-top">
                    <small class="text-muted" id="paginacionInfo">—</small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="paginacionUl"></ul>
                    </nav>
                </div>
            </div>

        </div><!-- /sub-container -->
    </div><!-- /main-container -->

    <!-- Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="toastMensaje" class="toast align-items-center border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body" id="toastBody">Guardado correctamente</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script>const PUEDE_EDITAR = <?php echo $puedeEditar ? 'true' : 'false'; ?>;</script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /* =====================================================================
           DICCIONARIO DE PRODUCTOS - JS
           ===================================================================== */

        const BASE = 'ajax/';

        // Estado global
        const state = {
            pagina: 1,
            porPagina: 50,
            filtro: 'todos',
            busqueda: '',
            totalPaginas: 1,
        };

        // Mapa: CodCotizacion → { id_producto_presentacion, sku, nombre }
        const mapaSeleccionados = {};

        // ── Bootstrap Toast ──────────────────────────────────────────────────
        const toastEl = document.getElementById('toastMensaje');
        const bsToast = new bootstrap.Toast(toastEl, { delay: 3000 });
        function mostrarToast(msg, tipo = 'success') {
            const body = document.getElementById('toastBody');
            body.textContent = msg;
            toastEl.className = 'toast align-items-center border-0 text-bg-' + tipo;
            bsToast.show();
        }

        // ── Cargar tabla ─────────────────────────────────────────────────────
        function cargarProductos() {
            const tbody = document.getElementById('tbodyProductos');
            tbody.innerHTML = `<tr class="loading-row"><td colspan="6">
        <div class="spinner-border text-primary spinner-border-sm me-2"></div>Cargando…</td></tr>`;

            const params = new URLSearchParams({
                filtro: state.filtro,
                busqueda: state.busqueda,
                pagina: state.pagina,
                por_pagina: state.porPagina,
            });

            fetch(BASE + 'accessantiguo_get_productos_antiguos.php?' + params)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) { mostrarToast(res.message || 'Error', 'danger'); return; }

                    actualizarStats(res.estadisticas);
                    state.totalPaginas = res.total_paginas;
                    renderTabla(res.data);
                    renderPaginacion(res.total, res.pagina, res.total_paginas);
                })
                .catch(() => mostrarToast('Error de red', 'danger'));
        }

        // ── Estadísticas y progreso ──────────────────────────────────────────
        function actualizarStats(est) {
            document.getElementById('statTotal').textContent = est.total_global.toLocaleString();
            document.getElementById('statMapeados').textContent = est.total_mapeados.toLocaleString();
            document.getElementById('statPendientes').textContent = est.total_pendientes.toLocaleString();

            const pct = est.total_global > 0
                ? Math.round(est.total_mapeados / est.total_global * 100)
                : 0;
            document.getElementById('progresoPct').textContent = pct + '%';
            document.getElementById('progresoBar').style.width = pct + '%';
        }

        // ── Render filas ─────────────────────────────────────────────────────
        function renderTabla(rows) {
            const tbody = document.getElementById('tbodyProductos');

            if (!rows.length) {
                tbody.innerHTML = `<tr class="empty-row"><td colspan="6">
            <i class="fas fa-search fa-2x mb-3 d-block text-muted"></i>
            No se encontraron productos con ese filtro.</td></tr>`;
                return;
            }

            const offset = (state.pagina - 1) * state.porPagina;

            tbody.innerHTML = rows.map((p, i) => {
                const mapeado = !!p.mapeo_id;
                const badge = mapeado
                    ? `<span class="badge badge-mapeado rounded-pill">✓ Mapeado</span>`
                    : `<span class="badge badge-pendiente rounded-pill">⏳ Pendiente</span>`;

                // Texto descriptivo del producto antiguo
                const partes = [p.Nombre];
                if (p.Marca) partes.push(p.Marca);
                if (p.Linea) partes.push(p.Linea);
                if (p.Unidad) partes.push(p.Unidad);
                if (p.Capacidad) partes.push(p.Capacidad);
                const productoAntiguo = partes.join(' · ');

                let colNuevo = '';
                if (PUEDE_EDITAR) {
                    const valActual = mapeado
                        ? `<div class="valor-mapeado mb-1">
                       <span class="sku-tag">${esc(p.nuevo_sku)}</span>
                       <span>${esc(p.nuevo_nombre)}</span>
                   </div>` : '';

                    colNuevo = `
                <td>
                    <div class="autocomplete-wrap" id="wrap-${p.CodCotizacion}">
                        ${valActual}
                        <input type="text"
                            class="form-control form-control-sm input-buscar-nuevo"
                            data-cod="${p.CodCotizacion}"
                            data-ingr="${esc(p.CodIngrediente)}"
                            placeholder="Buscar en nuevo ERP…"
                            autocomplete="off">
                        <div class="autocomplete-list" id="aclist-${p.CodCotizacion}"></div>
                    </div>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary btn-guardar-mapeo"
                        data-cod="${p.CodCotizacion}"
                        data-ingr="${esc(p.CodIngrediente)}"
                        title="Guardar mapeo">
                        <i class="fas fa-save"></i>
                    </button>
                    ${mapeado ? `
                    <button class="btn btn-sm btn-outline-danger btn-quitar-mapeo ms-1"
                        data-cod="${p.CodCotizacion}"
                        title="Quitar mapeo">
                        <i class="fas fa-times"></i>
                    </button>` : ''}
                </td>`;
                } else {
                    colNuevo = `<td>
                ${mapeado
                            ? `<div class="valor-mapeado">
                           <span class="sku-tag">${esc(p.nuevo_sku)}</span>
                           <span>${esc(p.nuevo_nombre)}</span>
                       </div>`
                            : '<span class="text-muted">—</span>'}
            </td>`;
                }

                return `<tr data-cod="${p.CodCotizacion}" data-mapeado="${mapeado ? '1' : '0'}">
            <td class="text-muted">${offset + i + 1}</td>
            <td>
                <div class="fw-semibold">${esc(p.Nombre)}</div>
                <small class="text-muted">${[p.Marca, p.Linea, p.Unidad, p.Capacidad].filter(Boolean).join(' · ')}</small>
                <div><small class="text-muted">#${p.CodCotizacion}</small></div>
            </td>
            <td><small class="text-muted">${esc(p.Tipo || '—')}</small></td>
            <td>${badge}</td>
            ${colNuevo}
        </tr>`;
            }).join('');

            // Restaurar selecciones previas en inputs de autocomplete
            Object.keys(mapaSeleccionados).forEach(cod => {
                const inp = document.querySelector(`.input-buscar-nuevo[data-cod="${cod}"]`);
                if (inp && mapaSeleccionados[cod]) {
                    inp.value = `${mapaSeleccionados[cod].sku} – ${mapaSeleccionados[cod].nombre}`;
                }
            });
        }

        // ── Paginación ───────────────────────────────────────────────────────
        function renderPaginacion(total, pagina, totalPags) {
            const ini = (pagina - 1) * state.porPagina + 1;
            const fin = Math.min(pagina * state.porPagina, total);
            document.getElementById('paginacionInfo').textContent =
                `Mostrando ${ini}–${fin} de ${total} presentaciones`;

            const ul = document.getElementById('paginacionUl');
            ul.innerHTML = '';

            const addBtn = (label, pg, disabled = false, active = false) => {
                const li = document.createElement('li');
                li.className = `page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#">${label}</a>`;
                if (!disabled && !active) li.querySelector('a').addEventListener('click', e => {
                    e.preventDefault(); state.pagina = pg; cargarProductos();
                });
                ul.appendChild(li);
            };

            addBtn('«', 1, pagina === 1);
            addBtn('‹', pagina - 1, pagina === 1);

            const start = Math.max(1, pagina - 2);
            const end = Math.min(totalPags, pagina + 2);
            for (let p = start; p <= end; p++) addBtn(p, p, false, p === pagina);

            addBtn('›', pagina + 1, pagina === totalPags);
            addBtn('»', totalPags, pagina === totalPags);
        }

        // ── Autocomplete ─────────────────────────────────────────────────────
        let acTimer = null;
        document.addEventListener('input', function (e) {
            if (!e.target.matches('.input-buscar-nuevo')) return;
            const inp = e.target;
            const cod = inp.dataset.cod;
            const list = document.getElementById('aclist-' + cod);
            const q = inp.value.trim();

            delete mapaSeleccionados[cod];

            clearTimeout(acTimer);
            if (q.length < 2) { list.style.display = 'none'; return; }

            acTimer = setTimeout(() => {
                fetch(BASE + 'accessantiguo_buscar_productos_nuevos.php?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(res => {
                        if (!res.success || !res.data.length) {
                            list.innerHTML = `<div class="autocomplete-item text-muted">Sin resultados</div>`;
                            list.style.display = 'block';
                            return;
                        }
                        list.innerHTML = res.data.map(p =>
                            `<div class="autocomplete-item"
                         data-id="${p.id}" data-sku="${esc(p.SKU)}" data-nom="${esc(p.Nombre)}">
                        <span class="sku">${esc(p.SKU)}</span>
                        <span class="nom"> – ${esc(p.Nombre)}</span>
                        <div class="extra">${[p.unidad, p.cantidad ? p.cantidad + ' u.' : ''].filter(Boolean).join(' | ')}</div>
                    </div>`
                        ).join('');
                        list.style.display = 'block';
                    });
            }, 280);
        });

        // Seleccionar opción del autocomplete
        document.addEventListener('click', function (e) {
            const item = e.target.closest('.autocomplete-item');
            if (item) {
                const list = item.closest('.autocomplete-list');
                const cod = list.id.replace('aclist-', '');
                const inp = document.querySelector(`.input-buscar-nuevo[data-cod="${cod}"]`);
                if (!inp) return;

                const id = item.dataset.id;
                const sku = item.dataset.sku;
                const nom = item.dataset.nom;

                mapaSeleccionados[cod] = { id, sku, nombre: nom };
                inp.value = `${sku} – ${nom}`;
                list.style.display = 'none';
                return;
            }

            // Cerrar listas abiertas al clic fuera
            if (!e.target.closest('.autocomplete-wrap')) {
                document.querySelectorAll('.autocomplete-list').forEach(l => l.style.display = 'none');
            }
        });

        // ── Guardar mapeo ────────────────────────────────────────────────────
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-guardar-mapeo');
            if (!btn) return;

            const cod = btn.dataset.cod;
            const ingr = btn.dataset.ingr;
            const sel = mapaSeleccionados[cod];

            if (!sel) {
                mostrarToast('Primero selecciona un producto del nuevo ERP', 'warning');
                return;
            }

            const fd = new FormData();
            fd.append('CodIngrediente', ingr);
            fd.append('CodCotizacion', cod);
            fd.append('id_producto_presentacion', sel.id);

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch(BASE + 'accessantiguo_guardar_mapeo.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        mostrarToast('✓ ' + res.message, 'success');
                        delete mapaSeleccionados[cod];
                        // Pequeña pausa para mostrar el toast antes de recargar
                        setTimeout(() => cargarProductos(), 800);
                    } else {
                        mostrarToast(res.message || 'Error al guardar', 'danger');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-save"></i>';
                    }
                })
                .catch(() => {
                    mostrarToast('Error de red', 'danger');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i>';
                });
        });

        // ── Quitar mapeo ─────────────────────────────────────────────────────
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-quitar-mapeo');
            if (!btn) return;

            if (!confirm('¿Quitar el mapeo de este producto?')) return;

            const cod = btn.dataset.cod;
            const fd = new FormData();
            fd.append('CodCotizacion', cod);
            fd.append('eliminar', '1');

            fetch(BASE + 'accessantiguo_guardar_mapeo.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        mostrarToast('Mapeo eliminado', 'info');
                        setTimeout(() => cargarProductos(), 600);
                    } else {
                        mostrarToast(res.message || 'Error', 'danger');
                    }
                });
        });

        // ── Exportar CSV ─────────────────────────────────────────────────────
        document.getElementById('btnExportar').addEventListener('click', () => {
            window.open(BASE + 'accessantiguo_get_productos_antiguos.php?filtro=mapeados&por_pagina=9999&formato=csv', '_blank');
        });

        // ── Filtros y búsqueda ───────────────────────────────────────────────
        document.querySelectorAll('input[name="filtro"]').forEach(r => {
            r.addEventListener('change', () => {
                state.filtro = r.value;
                state.pagina = 1;
                cargarProductos();
            });
        });

        let busTimer = null;
        document.getElementById('inputBusqueda').addEventListener('input', function () {
            clearTimeout(busTimer);
            busTimer = setTimeout(() => {
                state.busqueda = this.value.trim();
                state.pagina = 1;
                cargarProductos();
            }, 350);
        });

        document.getElementById('selectPorPagina').addEventListener('change', function () {
            state.porPagina = parseInt(this.value);
            state.pagina = 1;
            cargarProductos();
        });

        // ── Utils ────────────────────────────────────────────────────────────
        function esc(s) {
            if (!s) return '';
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // ── Init ─────────────────────────────────────────────────────────────
        cargarProductos();
    </script>
</body>

</html>