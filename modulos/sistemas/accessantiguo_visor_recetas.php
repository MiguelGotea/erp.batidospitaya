<?php
// visor_recetas.php
$version = mt_rand(1, 10000);

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('visor_recetas', 'vista', $cargoOperario)) {
    header('Location: ../../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visor de Recetas | Sistemas</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <style>
        body {
            background: #f0f2f5;
        }

        /* ── Header ─────────────────────────────────────────── */
        .page-header {
            background: linear-gradient(135deg, #1a3a2a 0%, #2d7a50 100%);
            color: #fff;
            border-radius: 12px;
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .page-header h1 {
            font-size: 1.35rem;
            margin: 0;
            font-weight: 700;
        }

        .page-header p {
            margin: 3px 0 0;
            font-size: .82rem;
            opacity: .8;
        }

        /* ── Panel selectores ───────────────────────────────── */
        .panel-selector {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 18px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, .08);
        }

        .panel-selector label {
            font-size: .8rem;
            font-weight: 600;
            color: #555;
            display: block;
            margin-bottom: 4px;
        }

        .panel-selector select {
            font-size: .85rem;
        }

        /* ── Tarjeta del producto seleccionado ──────────────── */
        .card-batido {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
            border: 1.5px solid #a5d6a7;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 18px;
        }

        .card-batido .badge-inactivo {
            background: #ffdde0;
            color: #c0392b;
            border-radius: 6px;
            padding: 2px 10px;
            font-size: .75rem;
            font-weight: 700;
        }

        .card-batido .badge-activo {
            background: #d4ede3;
            color: #1a7a52;
            border-radius: 6px;
            padding: 2px 10px;
            font-size: .75rem;
            font-weight: 700;
        }

        .card-batido .dato-label {
            font-size: .72rem;
            color: #666;
        }

        .card-batido .dato-val {
            font-size: .9rem;
            font-weight: 600;
        }

        /* ── Leyenda de tipo ────────────────────────────────── */
        .leyenda-tipos {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .leyenda-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: .75rem;
            color: #555;
        }

        .tipo-dot {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        .tipo-B {
            background: #4e73df;
        }

        /* Batido base */
        .tipo-L {
            background: #f6c23e;
        }

        /* Líquido / extra */
        .tipo-P {
            background: #858796;
        }

        /* Porcionado */
        .tipo-otro {
            background: #e0e0e0;
        }

        /* ── Tabla receta ────────────────────────────────────── */
        .table-receta {
            font-size: .82rem;
        }

        .table-receta thead th {
            background: #1a3a2a;
            color: #fff;
            font-size: .75rem;
            font-weight: 600;
            white-space: nowrap;
            vertical-align: middle;
            border: none;
        }

        .table-receta tbody td {
            vertical-align: middle;
            border-bottom: 1px solid #f0f2f5;
        }

        .table-receta tbody tr:hover {
            background: #f6fff8;
        }

        /* Stripe por tipo */
        .fila-B {
            border-left: 3px solid #4e73df;
        }

        .fila-L {
            border-left: 3px solid #f6c23e;
        }

        .fila-P {
            border-left: 3px solid #858796;
        }

        /* ── Chip insumo clave ─────────────────────────────── */
        .chip-clave {
            background: #fff3cd;
            color: #856404;
            border-radius: 4px;
            padding: 1px 7px;
            font-size: .7rem;
            font-weight: 700;
        }

        /* ── Columna traducción ─────────────────────────────── */
        .col-traduccion {
            min-width: 200px;
        }

        .traduccion-ok {
            background: #e8f5e9;
            border-radius: 8px;
            padding: 6px 10px;
        }

        .traduccion-ok .sku-tag {
            font-size: .72rem;
            background: #c8e6c9;
            color: #1b5e20;
            border-radius: 4px;
            padding: 1px 6px;
            font-weight: 700;
        }

        .traduccion-ok .nom-nuevo {
            font-size: .8rem;
            color: #2e7d32;
            font-weight: 600;
        }

        .traduccion-ok .uni-nuevo {
            font-size: .72rem;
            color: #555;
        }

        .traduccion-na {
            font-size: .75rem;
            color: #bbb;
            font-style: italic;
        }

        .sin-cot {
            font-size: .75rem;
            color: #e57373;
            font-style: italic;
        }

        /* ── Ingrediente sin vigencia ───────────────────────── */
        .ingr-inactivo {
            text-decoration: line-through;
            color: #aaa;
        }

        /* ── Panel vacío ────────────────────────────────────── */
        .panel-empty {
            text-align: center;
            padding: 60px 20px;
            color: #bbb;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, .07);
        }

        .panel-empty i {
            font-size: 3rem;
            display: block;
            margin-bottom: 14px;
        }

        /* ── Spinner ────────────────────────────────────────── */
        .spinner-overlay {
            display: none;
            text-align: center;
            padding: 50px;
            color: #888;
            background: #fff;
            border-radius: 12px;
        }

        /* ── Resumen traducción ─────────────────────────────── */
        .resumen-bar {
            background: #fff;
            border-radius: 10px;
            padding: 10px 18px;
            margin-bottom: 12px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .07);
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .resumen-item {
            font-size: .8rem;
        }

        .resumen-item .num {
            font-size: 1.1rem;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Visor de Recetas'); ?>

            <!-- Header -->
            <div class="page-header">
                <div style="font-size:2rem; opacity:.85"><i class="fas fa-blender"></i></div>
                <div>
                    <h1>Visor de Recetas</h1>
                    <p>Consulta de recetas del sistema antiguo con traducción al nuevo ERP de productos</p>
                </div>
            </div>

            <!-- Selectores -->
            <div class="panel-selector">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label><i class="fas fa-layer-group me-1"></i> Grupo</label>
                        <select class="form-select" id="selectGrupo">
                            <option value="">— Selecciona un grupo —</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label><i class="fas fa-box me-1"></i> Producto</label>
                        <select class="form-select" id="selectNombre" disabled>
                            <option value="">— Elige grupo primero —</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label><i class="fas fa-tag me-1"></i> Versión / Tamaño</label>
                        <select class="form-select" id="selectVersion" disabled>
                            <option value="">— Elige producto primero —</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex flex-column gap-2">
                        <label><i class="fas fa-filter me-1"></i> Estado</label>
                        <div class="d-flex gap-2 align-items-center">
                            <div class="btn-group btn-group-sm flex-grow-1" role="group" id="toggleEstado">
                                <input type="radio" class="btn-check" name="estadoBatido" id="estadoTodos" value="todos" checked>
                                <label class="btn btn-outline-secondary" for="estadoTodos">Todos</label>
                                <input type="radio" class="btn-check" name="estadoBatido" id="estadoActivos" value="activos">
                                <label class="btn btn-outline-success" for="estadoActivos">Activos</label>
                                <input type="radio" class="btn-check" name="estadoBatido" id="estadoInactivos" value="inactivos">
                                <label class="btn btn-outline-danger" for="estadoInactivos">Inactivos</label>
                            </div>
                        </div>
                        <button class="btn btn-success" id="btnVerReceta" disabled>
                            <i class="fas fa-eye me-2"></i>Ver receta
                        </button>
                    </div>
                </div>
            </div>

            <!-- Datos del batido seleccionado -->
            <div class="card-batido d-none" id="cardBatido"></div>

            <!-- Spinner -->
            <div class="spinner-overlay" id="spinnerReceta">
                <div class="spinner-border text-success mb-3"></div>
                <div>Cargando receta…</div>
            </div>

            <!-- Resumen -->
            <div class="resumen-bar d-none" id="resumenBar"></div>

            <!-- Panel vacío -->
            <div class="panel-empty" id="panelEmpty">
                <i class="fas fa-blender"></i>
                Selecciona un grupo y un producto para ver su receta
            </div>

            <!-- Tabla receta -->
            <div class="d-none" id="panelReceta">
                <div class="table-responsive"
                    style="border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,.09); overflow:hidden;">
                    <table class="table table-hover mb-0 table-receta" id="tablaReceta">
                        <thead>
                            <tr>
                                <th style="width:40px">Orden</th>
                                <th>Ingrediente</th>
                                <th style="width:80px">Cantidad</th>
                                <th style="width:55px">Tipo</th>
                                <th style="width:80px">Porción</th>
                                <th style="width:160px">Cotización</th>
                                <th class="col-traduccion">Producto Nuevo (ERP)</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyReceta"></tbody>
                    </table>
                </div>
            </div>

        </div><!-- /sub-container -->
    </div><!-- /main-container -->

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /* =====================================================================
           VISOR DE RECETAS — JS
           ===================================================================== */

        const BASE = 'ajax/';

        // ── Utils ─────────────────────────────────────────────────────────────
        function esc(s) {
            if (s === null || s === undefined) return '';
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function hide(id) { document.getElementById(id).classList.add('d-none'); }
        function show(id) { document.getElementById(id).classList.remove('d-none'); }

        // ── Estado global ─────────────────────────────────────────────────────
        let allBatidos  = [];   // todos los productos del grupo actual
        let estadoFiltro = 'todos'; // 'todos' | 'activos' | 'inactivos'

        // ── Cargar grupos ─────────────────────────────────────────────────────
        function cargarGrupos() {
            fetch(BASE + 'accessantiguo_get_grupos_batidos.php')
                .then(r => r.json())
                .then(res => {
                    if (!res.success) return;
                    const sel = document.getElementById('selectGrupo');
                    res.data.forEach(g => {
                        const opt = document.createElement('option');
                        opt.value = g.CodGrupo;
                        opt.textContent = g.NombreGrupo + (g.alias ? ` (${g.alias})` : '');
                        sel.appendChild(opt);
                    });
                });
        }

        // ── Helpers cascade ───────────────────────────────────────────────────
        function esActivo(b) { return parseInt(b.Vigencia) === 1; }

        function batidosFiltrados() {
            if (estadoFiltro === 'activos')   return allBatidos.filter(b =>  esActivo(b));
            if (estadoFiltro === 'inactivos') return allBatidos.filter(b => !esActivo(b));
            return allBatidos;
        }

        function poblaNombres() {
            const selNom = document.getElementById('selectNombre');
            const selVer = document.getElementById('selectVersion');
            const btnVer = document.getElementById('btnVerReceta');

            // Nombres únicos del subconjunto filtrado
            const nombres = [...new Set(batidosFiltrados().map(b => b.Nombre))].sort();

            selNom.innerHTML = '<option value="">— Selecciona un producto —</option>';
            nombres.forEach(n => {
                const opt = document.createElement('option');
                opt.value = n;
                opt.textContent = n;
                selNom.appendChild(opt);
            });
            selNom.disabled = !nombres.length;

            selVer.innerHTML = '<option value="">— Elige producto primero —</option>';
            selVer.disabled = true;
            btnVer.disabled = true;
        }

        function poblaVersiones(nombre) {
            const selVer = document.getElementById('selectVersion');
            const btnVer = document.getElementById('btnVerReceta');

            const versiones = batidosFiltrados().filter(b => b.Nombre === nombre);

            selVer.innerHTML = '<option value="">— Selecciona versión —</option>';
            versiones.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.CodBatido;
                const inactivo = !esActivo(b);
                const prefijo = inactivo ? '⛔ ' : '';
                opt.textContent = `${prefijo}${b.CodBatido}${b.Medida ? ' · ' + b.Medida : ''}`;
                if (inactivo) opt.style.color = '#c0392b';
                selVer.appendChild(opt);
            });
            selVer.disabled = !versiones.length;
            btnVer.disabled = true;
        }

        // ── Evento: cambio de grupo ──────────────────────────────────────────
        document.getElementById('selectGrupo').addEventListener('change', function () {
            const selNom = document.getElementById('selectNombre');
            const selVer = document.getElementById('selectVersion');
            const btnVer = document.getElementById('btnVerReceta');

            selNom.innerHTML = '<option value="">Cargando…</option>';
            selNom.disabled  = true;
            selVer.innerHTML = '<option value="">— Elige producto primero —</option>';
            selVer.disabled  = true;
            btnVer.disabled  = true;
            allBatidos = [];
            resetVista();

            const cod = this.value;
            if (!cod) {
                selNom.innerHTML = '<option value="">— Elige grupo primero —</option>';
                return;
            }

            fetch(BASE + 'accessantiguo_get_batidos_por_grupo.php?cod_grupo=' + encodeURIComponent(cod))
                .then(r => r.json())
                .then(res => {
                    if (!res.success || !res.data.length) {
                        selNom.innerHTML = '<option value="">Sin productos en este grupo</option>';
                        return;
                    }
                    allBatidos = res.data;
                    poblaNombres();
                });
        });

        // ── Evento: cambio de nombre ──────────────────────────────────────────
        document.getElementById('selectNombre').addEventListener('change', function () {
            resetVista();
            document.getElementById('btnVerReceta').disabled = true;
            if (!this.value) {
                const selVer = document.getElementById('selectVersion');
                selVer.innerHTML = '<option value="">— Elige producto primero —</option>';
                selVer.disabled = true;
                return;
            }
            poblaVersiones(this.value);
        });

        // ── Evento: cambio de versión ─────────────────────────────────────────
        document.getElementById('selectVersion').addEventListener('change', function () {
            document.getElementById('btnVerReceta').disabled = !this.value;
            resetVista();
        });

        // ── Evento: toggle estado ─────────────────────────────────────────────
        document.querySelectorAll('input[name="estadoBatido"]').forEach(r => {
            r.addEventListener('change', function () {
                estadoFiltro = this.value;
                // Repoblar nombres manteniendo selección actual si sigue disponible
                const nombreActual  = document.getElementById('selectNombre').value;
                const versionActual = document.getElementById('selectVersion').value;
                poblaNombres();
                // Restaurar nombre si todavía está disponible
                const selNom = document.getElementById('selectNombre');
                if (nombreActual) {
                    selNom.value = nombreActual;
                    if (selNom.value === nombreActual) {
                        poblaVersiones(nombreActual);
                        // Restaurar versión si todavía está disponible
                        const selVer = document.getElementById('selectVersion');
                        selVer.value = versionActual;
                        document.getElementById('btnVerReceta').disabled = !selVer.value;
                    }
                }
                resetVista();
            });
        });

        // ── Ver receta ────────────────────────────────────────────────────────
        document.getElementById('btnVerReceta').addEventListener('click', function () {
            const codBatido = document.getElementById('selectVersion').value;
            if (!codBatido) return;

            resetVista();
            hide('panelEmpty');
            document.getElementById('spinnerReceta').style.display = 'block';

            fetch(BASE + 'accessantiguo_get_detalle_receta.php?cod_batido=' + encodeURIComponent(codBatido))
                .then(r => r.json())
                .then(res => {
                    document.getElementById('spinnerReceta').style.display = 'none';
                    if (!res.success) { show('panelEmpty'); return; }
                    renderCardBatido(res.batido);
                    renderResumen(res.ingredientes);
                    renderTabla(res.ingredientes);
                })
                .catch(() => {
                    document.getElementById('spinnerReceta').style.display = 'none';
                    show('panelEmpty');
                });
        });

        // ── Card producto ─────────────────────────────────────────────────────
        function renderCardBatido(b) {
            const activo = parseInt(b.Vigencia) === 1;
            const badgeHTML = activo
                ? `<span class="badge-activo">✓ Activo</span>`
                : `<span class="badge-inactivo">⛔ Inactivo</span>`;

            document.getElementById('cardBatido').innerHTML = `
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span style="font-size:1.1rem;font-weight:700">${esc(b.Nombre)}</span>
                    ${badgeHTML}
                </div>
                <div class="d-flex gap-4 flex-wrap">
                    <div><div class="dato-label">Código</div><div class="dato-val">${esc(b.CodBatido)}</div></div>
                    <div><div class="dato-label">Grupo</div><div class="dato-val">${esc(b.NombreGrupo)}</div></div>
                    <div><div class="dato-label">Medida</div><div class="dato-val">${esc(b.Medida || '—')}</div></div>
                    <div><div class="dato-label">Precio</div><div class="dato-val">${b.Precio ? 'C$ ' + b.Precio : '—'}</div></div>
                    <div><div class="dato-label">Marca</div><div class="dato-val">${esc(b.Marca || '—')}</div></div>
                    ${b.CodigoBarras ? `<div><div class="dato-label">Barras</div><div class="dato-val">${esc(b.CodigoBarras)}</div></div>` : ''}
                </div>
            </div>
        </div>
    `;
            show('cardBatido');
        }

        // ── Resumen de traducción ────────────────────────────────────────────
        function renderResumen(ingredientes) {
            const total      = ingredientes.length;
            const conCot     = ingredientes.filter(i => i.cotizacion).length;
            const traducidos = ingredientes.filter(i => i.nuevo_producto).length;
            const sinMapeo   = conCot - traducidos;
            const sinCot     = total - conCot;

            document.getElementById('resumenBar').innerHTML = `
        <div class="resumen-item"><span class="num">${total}</span><span class="text-muted ms-1">ingredientes</span></div>
        <div class="vr"></div>
        <div class="resumen-item text-success"><span class="num">${traducidos}</span><span class="ms-1">traducidos al nuevo ERP</span></div>
        <div class="resumen-item text-warning"><span class="num">${sinMapeo}</span><span class="ms-1">con cotización pero sin mapeo</span></div>
        <div class="resumen-item text-danger"><span class="num">${sinCot}</span><span class="ms-1">sin cotización resuelta</span></div>
    `;
            show('resumenBar');
        }

        // ── Tabla de ingredientes ────────────────────────────────────────────
        function renderTabla(ingredientes) {
            const tbody = document.getElementById('tbodyReceta');

            if (!ingredientes.length) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-5">
            <i class="fas fa-exclamation-circle me-2"></i>Esta receta no tiene ingredientes registrados.</td></tr>`;
                show('panelReceta');
                return;
            }

            tbody.innerHTML = ingredientes.map((ingr, idx) => {
                const tipo      = ingr.Tipo || '—';
                const filaClass = `fila-${tipo}`;

                // Ingrediente: nombre + unidad
                const vigente    = parseInt(ingr.VigenteIngrediente) !== 0;
                const nomClass   = vigente ? '' : 'ingr-inactivo';
                const nomBadge   = vigente ? '' : `<span style="font-size:.65rem;background:#fdd;color:#c0392b;border-radius:3px;padding:1px 5px;margin-left:4px">INACTIVO</span>`;
                const insumoChip = ingr.InsumoClave ? `<span class="chip-clave ms-1">Clave</span>` : '';
                const unidad     = ingr.UnidadIngrediente ? `<small class="text-muted"> ${esc(ingr.UnidadIngrediente)}</small>` : '';

                // Cotización (sin número)
                const cot = ingr.cotizacion;
                const cotHTML = cot
                    ? `<div style="font-size:.75rem;color:#333">${[cot.Marca, cot.Linea, cot.Capacidad].filter(Boolean).join(' · ')}</div>
                       ${ingr.codporcion
                           ? '<span style="font-size:.65rem;background:#e3f2fd;color:#1565c0;border-radius:3px;padding:1px 5px">por porción</span>'
                           : '<span style="font-size:.65rem;background:#f3e5f5;color:#6a1b9a;border-radius:3px;padding:1px 5px">Conversión=1</span>'}`
                    : `<span class="sin-cot">Sin cotización</span>`;

                // Traducción nuevo ERP
                let tradHTML;
                const np = ingr.nuevo_producto;
                if (np) {
                    const activoTag = np.activoNuevo === 'NO'
                        ? `<span style="font-size:.65rem;background:#fdd;color:#c0392b;border-radius:3px;padding:1px 5px;margin-left:4px">INACTIVO</span>` : '';
                    tradHTML = `<div class="traduccion-ok">
                        <div class="d-flex align-items-center gap-1 mb-1"><span class="sku-tag">${esc(np.SKU)}</span>${activoTag}</div>
                        <div class="nom-nuevo">${esc(np.NombreNuevo)}</div>
                        <div class="uni-nuevo">${esc(np.unidadNueva || '')}${np.cantidad ? ' · ' + np.cantidad : ''} ${esc(np.productoMaestro || '')}</div>
                    </div>`;
                } else if (cot) {
                    tradHTML = `<span class="traduccion-na"><i class="fas fa-exclamation-triangle me-1 text-warning"></i>Sin mapeo</span>`;
                } else {
                    tradHTML = `<span class="traduccion-na text-danger"><i class="fas fa-times-circle me-1"></i>No resuelto</span>`;
                }

                return `<tr class="${filaClass}">
                    <td class="text-center text-muted">${ingr.ordenreceta ?? idx + 1}</td>
                    <td>
                        <div class="${nomClass}">${esc(ingr.NombreIngrediente || ingr.CodIngrediente)}${unidad}${nomBadge}${insumoChip}</div>
                        <small class="text-muted">${esc(ingr.CodIngrediente)}</small>
                    </td>
                    <td class="text-center fw-semibold">${ingr.Cantidad ?? '—'}</td>
                    <td class="text-center"><span class="badge bg-secondary">${esc(tipo)}</span></td>
                    <td class="text-center text-muted">${ingr.codporcion ? '#' + ingr.codporcion : '—'}</td>
                    <td>${cotHTML}</td>
                    <td class="col-traduccion">${tradHTML}</td>
                </tr>`;
            }).join('');

            show('panelReceta');
        }

        // ── Reset vista ───────────────────────────────────────────────────────
        function resetVista() {
            hide('cardBatido');
            hide('panelReceta');
            hide('resumenBar');
            show('panelEmpty');
            document.getElementById('spinnerReceta').style.display = 'none';
            document.getElementById('tbodyReceta').innerHTML = '';
        }

        // ── Init ──────────────────────────────────────────────────────────────
        cargarGrupos();
    </script>
</body>

</html>