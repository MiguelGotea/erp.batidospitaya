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
                                <input type="radio" class="btn-check" name="estadoBatido" id="estadoTodos" value="todos"
                                    checked>
                                <label class="btn btn-outline-secondary" for="estadoTodos">Todos</label>
                                <input type="radio" class="btn-check" name="estadoBatido" id="estadoActivos"
                                    value="activos">
                                <label class="btn btn-outline-success" for="estadoActivos">Activos</label>
                                <input type="radio" class="btn-check" name="estadoBatido" id="estadoInactivos"
                                    value="inactivos">
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
                            <!-- ── Fila de segmentos ── -->
                            <tr>
                                <th colspan="5" class="text-center"
                                    style="background:#1b4332;border-right:3px solid #40916c;letter-spacing:.05em;font-size:.7rem;padding:6px 10px">
                                    <i class="fas fa-database me-1"></i> Estructura Access
                                </th>
                                <th colspan="4" class="text-center"
                                    style="background:#1a237e;border-right:3px solid #5c7aff;letter-spacing:.05em;font-size:.7rem;padding:6px 10px">
                                    <i class="fas fa-receipt me-1"></i> Comanda Access
                                </th>
                                <th colspan="3" class="text-center"
                                    style="background:#4a148c;letter-spacing:.05em;font-size:.7rem;padding:6px 10px">
                                    <i class="fas fa-layer-group me-1"></i> Nuevo Sistema
                                </th>
                            </tr>
                            <!-- ── Columnas individuales ── -->
                            <tr>
                                <th>Nombre</th>
                                <th style="width:85px">Unidad Base</th>
                                <th style="width:75px">Cantidad</th>
                                <th style="width:75px">Porción</th>
                                <th style="width:150px;border-right:3px solid #40916c">Cotización</th>
                                <th style="width:45px">Orden</th>
                                <th style="width:50px">Tipo</th>
                                <th>Nombre</th>
                                <th style="width:75px;border-right:3px solid #5c7aff">Cantidad</th>
                                <th>Insumo Receta</th>
                                <th style="width:80px">Cantidad</th>
                                <th>Presentación Uso</th>
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
        let allBatidos = [];   // todos los productos del grupo actual
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
            if (estadoFiltro === 'activos') return allBatidos.filter(b => esActivo(b));
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
            selNom.disabled = true;
            selVer.innerHTML = '<option value="">— Elige producto primero —</option>';
            selVer.disabled = true;
            btnVer.disabled = true;
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
                const nombreActual = document.getElementById('selectNombre').value;
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
            const total = ingredientes.length;
            const conCot = ingredientes.filter(i => i.cotizacion).length;
            const traducidos = ingredientes.filter(i => i.nuevo_producto).length;
            const sinMapeo = conCot - traducidos;
            const sinCot = total - conCot;

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
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-5">
            <i class="fas fa-exclamation-circle me-2"></i>Esta receta no tiene ingredientes registrados.</td></tr>`;
                show('panelReceta');
                return;
            }


            tbody.innerHTML = ingredientes.map((ingr, idx) => {
                const tipo = ingr.Tipo || '—';
                const filaClass = `fila-${tipo}`;

                // Ingrediente: nombre + unidad
                const vigente = parseInt(ingr.VigenteIngrediente) !== 0;
                const nomClass = vigente ? '' : 'ingr-inactivo';
                const nomBadge = vigente ? '' : `<span style="font-size:.65rem;background:#fdd;color:#c0392b;border-radius:3px;padding:1px 5px;margin-left:4px">INACTIVO</span>`;
                const insumoChip = ingr.InsumoClave ? `<span class="chip-clave ms-1">Clave</span>` : '';
                const unidad = ingr.UnidadIngrediente ? `<small class="text-muted"> ${esc(ingr.UnidadIngrediente)}</small>` : '';

                // Cotización
                const cot = ingr.cotizacion;
                const metodoCot = ingr.metodo_cotizacion;
                let cotBadge = '';
                if (metodoCot === 'directa') {
                    cotBadge = '<span style="font-size:.65rem;background:#e3f2fd;color:#1565c0;border-radius:3px;padding:1px 5px;display:inline-block;margin-top:3px">porción</span>';
                } else if (metodoCot === 'conversion1') {
                    cotBadge = '<span style="font-size:.65rem;background:#f3e5f5;color:#6a1b9a;border-radius:3px;padding:1px 5px;display:inline-block;margin-top:3px">Conversión=1</span>';
                } else if (metodoCot === 'prioritaria') {
                    cotBadge = '<span style="font-size:.65rem;background:#fff8e1;color:#e65100;border-radius:3px;padding:1px 5px;display:inline-block;margin-top:3px">Prioritaria</span>';
                }
                // ── Cotización: texto con NombreIngrediente al inicio ───────────
                const ingrNombre = ingr.NombreIngrediente || ingr.CodIngrediente;
                const infoTexto = cot
                    ? [ingrNombre, cot.Marca, cot.Linea, cot.Capacidad].filter(Boolean).join(' · ')
                    : '';
                const cotHTML = cot
                    ? `<div style="font-size:.75rem;color:#333">${infoTexto || '<span style="color:#bbb;font-style:italic">Sin detalle</span>'}</div>${cotBadge}`
                    : `<span class="sin-cot">Sin cotización</span>`;

                // ── Comanda Access: Nombre ───────────────────────────────────────────
                let comandaNombre;
                if (metodoCot === 'directa') {
                    // Prioridad 1: Nombre (Marca, Linea, Capacidad)
                    const extras = [cot?.Marca, cot?.Linea, cot?.Capacidad].filter(Boolean).join(', ');
                    comandaNombre = ingrNombre + (extras ? ` (${extras})` : '');
                } else {
                    // Prioridad 2+: si tiene campos de preparación usar presentacionpreparacion, si no UnidadBase
                    const tienePrep = ingr.presentacionpreparacion != null && ingr.conversionpreparacion != null;
                    const etiqueta = tienePrep ? ingr.presentacionpreparacion : (ingr.UnidadIngrediente || '');
                    comandaNombre = ingrNombre + (etiqueta ? ` (${etiqueta})` : '');
                }

                // ── Comanda Access: Cantidad ─────────────────────────────────────────
                const conv = cot ? parseFloat(cot.Conversion) : NaN;
                const cant = parseFloat(ingr.Cantidad);
                let comandaCantidad;
                if (metodoCot === 'directa') {
                    // Prioridad 1: SubReceta.Cantidad / Cotizacion.Conversion
                    comandaCantidad = (!isNaN(conv) && !isNaN(cant) && conv !== 0)
                        ? (cant / conv).toFixed(4).replace(/\.?0+$/, '')
                        : '—';
                } else {
                    // Prioridad 2+: si hay ajuste de preparación → Cantidad / conversionpreparacion
                    const convPrep = parseFloat(ingr.conversionpreparacion);
                    const tienePrep = ingr.presentacionpreparacion != null && !isNaN(convPrep) && convPrep !== 0;
                    comandaCantidad = (tienePrep && !isNaN(cant))
                        ? (cant / convPrep).toFixed(4).replace(/\.?0+$/, '')
                        : (ingr.Cantidad ?? '—');
                }

                // ── Nuevo Sistema: 3 columnas ────────────────────────────────────
                const np        = ingr.nuevo_producto;
                const ir        = ingr.insumo_receta;
                const escenario = ingr.escenario_erp;

                // Insumo Receta: la presentación que coincide con la unidad del ingrediente
                let celInsumoReceta;
                if (ir) {
                    const irActivo = ir.activoNuevo === 'NO'
                        ? `<span style="font-size:.65rem;background:#fdd;color:#c0392b;border-radius:3px;padding:1px 5px">INACTIVO</span>` : '';
                    celInsumoReceta = `<div class="nom-nuevo">${esc(ir.NombreNuevo)}${irActivo}</div>
                        <div class="uni-nuevo">${esc(ir.unidadNueva || '')}${ir.cantidad ? ' · ' + ir.cantidad : ''}</div>`;
                } else if (np) {
                    celInsumoReceta = `<span class="traduccion-na"><i class="fas fa-search me-1 text-warning"></i>Sin equiv. de unidad</span>`;
                } else if (cot) {
                    celInsumoReceta = `<span class="traduccion-na"><i class="fas fa-exclamation-triangle me-1 text-warning"></i>Sin mapeo</span>`;
                } else {
                    celInsumoReceta = `<span class="traduccion-na text-danger"><i class="fas fa-times-circle me-1"></i>No resuelto</span>`;
                }

                // Cantidad ERP: SubReceta.Cantidad / insumo_receta.cantidad
                let celCantERP = '—';
                if (ir && ir.cantidad != null) {
                    const ppCant = parseFloat(ir.cantidad);
                    const srCant = parseFloat(ingr.Cantidad);
                    if (ppCant > 0 && !isNaN(srCant)) {
                        celCantERP = (srCant / ppCant).toFixed(4).replace(/\.?0+$/, '');
                    }
                }

                // Presentación Uso: el producto que actualmente sirve al consumo
                let celPresentacionUso;
                if (np) {
                    const activoTag = np.activoNuevo === 'NO'
                        ? `<span style="font-size:.65rem;background:#fdd;color:#c0392b;border-radius:3px;padding:1px 5px;margin-left:4px">INACTIVO</span>` : '';
                    const autoTag = ingr.metodo_resolucion === 'maestro'
                        ? `<span style="font-size:.65rem;background:#e8f5e9;color:#2e7d32;border-radius:3px;padding:1px 5px;margin-left:4px" title="Resuelto automáticamente por maestro + unidad">AUTO</span>` : '';
                    let variedadesHTML = '';
                    if (np.variedades && np.variedades.length > 0) {
                        variedadesHTML = `<select class="form-select form-select-sm mt-1" style="font-size:.75rem;padding:2px 5px;height:auto">
                            ${np.variedades.map(v => `<option value="${v.id}" ${v.es_principal == 1 ? 'selected' : ''}>${esc(v.nombre)} ${v.es_principal == 1 ? '(Principal)' : ''}</option>`).join('')}
                        </select>`;
                    }
                    celPresentacionUso = `<div class="traduccion-ok">
                        <div class="d-flex align-items-center gap-1 mb-1">${activoTag}${autoTag}</div>
                        <div class="nom-nuevo">${esc(np.NombreNuevo)}</div>
                        ${variedadesHTML}
                        <div class="uni-nuevo mt-1">${esc(np.unidadNueva || '')}${np.cantidad ? ' · ' + np.cantidad : ''} ${esc(np.productoMaestro || '')}</div>
                    </div>`;
                } else if (cot) {
                    celPresentacionUso = `<span class="traduccion-na"><i class="fas fa-exclamation-triangle me-1 text-warning"></i>Sin mapeo</span>`;
                } else {
                    celPresentacionUso = `<span class="traduccion-na text-danger"><i class="fas fa-times-circle me-1"></i>No resuelto</span>`;
                }

                return `<tr class="${filaClass}">
                    <!-- Estructura Access -->
                    <td>
                        <div class="${nomClass}">${esc(ingrNombre)}${nomBadge}${insumoChip}</div>
                        <small class="text-muted">${esc(ingr.CodIngrediente)}</small>
                    </td>
                    <td class="text-center text-muted" style="font-size:.8rem">${esc(ingr.UnidadIngrediente || '—')}</td>
                    <td class="text-center fw-semibold">${ingr.Cantidad ?? '—'}</td>
                    <td class="text-center text-muted">${ingr.codporcion || '—'}</td>
                    <td style="border-right:3px solid #40916c">${cotHTML}</td>
                    <!-- Comanda Access -->
                    <td class="text-center text-muted">${ingr.ordenreceta ?? idx + 1}</td>
                    <td class="text-center"><span class="badge bg-secondary">${esc(tipo)}</span></td>
                    <td style="font-size:.8rem">${esc(comandaNombre)}</td>
                    <td class="text-center fw-semibold" style="border-right:3px solid #5c7aff;font-size:.8rem">${comandaCantidad}</td>
                    <!-- Nuevo Sistema -->
                    <td>${celInsumoReceta}</td>
                    <td class="text-center fw-semibold">${celCantERP}</td>
                    <td>${celPresentacionUso}</td>
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
    <!-- ══════════════════════════════════════════════════════════════════
         MODAL DE AYUDA — Visor de Recetas
         Abierto por openPageHelp() del header universal
    ═══════════════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header" style="background:linear-gradient(135deg,#1a3a2a,#2d7a50);color:#fff">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-blender me-2"></i> Guía — Visor de Recetas (Access → ERP)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" style="font-size:.88rem;line-height:1.65">

                    <!-- ── SECCIÓN 1: ¿Qué hace esta página? ─────────────────────── -->
                    <h6 class="fw-bold text-success mb-2"><i class="fas fa-info-circle me-1"></i> ¿Qué hace esta página?
                    </h6>
                    <p>
                        Permite consultar las recetas del <strong>sistema Access antiguo</strong> y ver, para cada
                        ingrediente, cómo se resuelve su presentación comercial (cotización) y cómo se traduce
                        al nuevo ERP (Pitaya). Cada receta se consulta por Grupo → Producto → Versión/Tamaño.
                    </p>

                    <hr>

                    <!-- ── SECCIÓN 2: Sistema de Prioridades para Cotización ──────── -->
                    <h6 class="fw-bold text-success mb-2"><i class="fas fa-layer-group me-1"></i> Sistema de Prioridades
                        — Resolución de Cotización</h6>
                    <p>Para cada ingrediente de la receta, el sistema busca su presentación comercial (cotización)
                        siguiendo este orden de prioridad:</p>

                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width:100px">Prioridad</th>
                                    <th>Condición de Búsqueda</th>
                                    <th style="width:130px">Badge en tabla</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-bold text-primary">🔵 1 — Porción</td>
                                    <td>
                                        El campo <code>SubReceta.codporcion</code> tiene valor (≠ NULL y &gt; 0).<br>
                                        Se usa directamente como <code>CodCotizacion</code> en la tabla
                                        <code>Cotizaciones</code>. Indica que la receta especifica
                                        una presentación exacta ("porción mapeada").
                                    </td>
                                    <td><span
                                            style="font-size:.72rem;background:#e3f2fd;color:#1565c0;border-radius:3px;padding:2px 7px">porción</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-purple" style="color:#6a1b9a">🟣 2 — Base</td>
                                    <td>
                                        <code>codporcion</code> es NULL. Se busca en <code>Cotizaciones</code> el
                                        registro base del ingrediente donde:
                                        <ul class="mb-0 mt-1">
                                            <li><code>Conversion = 1</code></li>
                                            <li><code>Prioridad = 1</code></li>
                                            <li><code>Subproducto IS NULL OR Subproducto ≠ 1</code> (no es subproducto)
                                            </li>
                                            <li><code>Marca IS NULL OR Marca ≠ 'Almacen Global'</code></li>
                                        </ul>
                                    </td>
                                    <td><span
                                            style="font-size:.72rem;background:#f3e5f5;color:#6a1b9a;border-radius:3px;padding:2px 7px">Conversión=1</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold" style="color:#e65100">🟠 3 — Prioritaria</td>
                                    <td>
                                        Si no se encontró nada en Prioridad 2, se busca cualquier cotización del
                                        ingrediente que cumpla:
                                        <ul class="mb-0 mt-1">
                                            <li><code>Subproducto IS NULL OR Subproducto ≠ 1</code> (no es subproducto)
                                            </li>
                                            <li><code>Marca ≠ 'Almacen Global'</code></li>
                                            <li><code>Prioridad = 1</code></li>
                                        </ul>
                                        Se toma el primer resultado encontrado.
                                    </td>
                                    <td><span
                                            style="font-size:.72rem;background:#fff8e1;color:#e65100;border-radius:3px;padding:2px 7px">Prioritaria</span>
                                    </td>
                                </tr>
                                <tr class="table-danger">
                                    <td class="fw-bold text-danger">🔴 Sin cotización</td>
                                    <td>Ninguna de las 3 prioridades encontró un resultado.</td>
                                    <td><span style="font-size:.72rem;color:#e57373;font-style:italic">Sin
                                            cotización</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr>

                    <!-- ── SECCIÓN 3: Estructura de la tabla ─────────────────────── -->
                    <h6 class="fw-bold text-success mb-2"><i class="fas fa-table me-1"></i> Estructura de la Tabla — 3
                        Segmentos</h6>
                    <p>La tabla de ingredientes está dividida en <strong>3 segmentos visuales</strong>:</p>

                    <!-- Segmento 1 -->
                    <div class="p-2 mb-2 rounded" style="background:#e8f5e9;border-left:4px solid #40916c">
                        <strong><i class="fas fa-database me-1 text-success"></i> Estructura Access</strong>
                        <p class="mb-1 mt-1" style="font-size:.82rem">Datos tal como están en el sistema Access
                            original.</p>
                        <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                            <thead class="table-success">
                                <tr>
                                    <th>Columna</th>
                                    <th>Contenido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Nombre</strong></td>
                                    <td>Nombre del ingrediente (<code>DBIngredientes.Nombre</code>) + código debajo.
                                        Tachado si está inactivo.</td>
                                </tr>
                                <tr>
                                    <td><strong>Unidad Base</strong></td>
                                    <td>Unidad de medida del ingrediente (<code>DBIngredientes.Unidad</code>).</td>
                                </tr>
                                <tr>
                                    <td><strong>Cantidad</strong></td>
                                    <td>Cantidad usada en la receta (<code>SubReceta.Cantidad</code>).</td>
                                </tr>
                                <tr>
                                    <td><strong>Porción</strong></td>
                                    <td>Código de porción asignado (<code>SubReceta.codporcion</code>). Muestra "—" si
                                        no tiene.</td>
                                </tr>
                                <tr>
                                    <td><strong>Cotización</strong></td>
                                    <td>
                                        Muestra: <code>NombreIngrediente · Marca · Linea · Capacidad</code>
                                        (campos de <code>Cotizaciones</code>).<br>
                                        Si alguno de los campos está vacío en la BD, se omite del texto.
                                        Si no hay cotización resuelta, muestra "Sin cotización" en rojo.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Segmento 2 -->
                    <div class="p-2 mb-2 rounded" style="background:#e8eaf6;border-left:4px solid #5c7aff">
                        <strong><i class="fas fa-receipt me-1" style="color:#3949ab"></i> Comanda Access</strong>
                        <p class="mb-1 mt-1" style="font-size:.82rem">Vista orientada a cómo se preparaba la comanda en
                            Access.</p>
                        <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                            <thead style="background:#c5cae9">
                                <tr>
                                    <th>Columna</th>
                                    <th>Prioridad 1 (porción)</th>
                                    <th>Prioridad 2 y 3</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Orden</strong></td>
                                    <td colspan="2"><code>SubReceta.ordenreceta</code> (número de orden en la receta).
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Tipo</strong></td>
                                    <td colspan="2">Tipo de ingrediente: B (Base), L (Líquido/Hielo), P
                                        (Empaque), etc.</td>
                                </tr>
                                <tr>
                                    <td><strong>Nombre</strong></td>
                                    <td><code>DBIngredientes.Nombre (Marca, Linea, Capacidad)</code><br><small>Los datos
                                            entre paréntesis vienen de la cotización.</small></td>
                                    <td>
                                        <strong>Con ajuste de preparación</strong> (si
                                        <code>DBIngredientes.presentacionpreparacion</code>
                                        y <code>conversionpreparacion</code> tienen valor):<br>
                                        <code>DBIngredientes.Nombre (presentacionpreparacion)</code><br>
                                        <strong>Sin ajuste:</strong><br>
                                        <code>DBIngredientes.Nombre (DBIngredientes.Unidad)</code>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Cantidad</strong></td>
                                    <td>
                                        <code>SubReceta.Cantidad ÷ Cotizacion.Conversion</code><br>
                                        <small>Expresa qué fracción de la presentación se consume por receta.</small>
                                    </td>
                                    <td>
                                        <strong>Con ajuste de preparación:</strong><br>
                                        <code>SubReceta.Cantidad ÷ DBIngredientes.conversionpreparacion</code><br>
                                        <small>Ejemplo: Maní Horneado 24gr ÷ conversionpreparacion = cantidad en unidad
                                            de preparación.</small><br>
                                        <strong>Sin ajuste:</strong><br>
                                        <code>SubReceta.Cantidad</code> (igual a Estructura Access).
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-2 p-2 rounded"
                            style="background:#fff3e0;font-size:.78rem;border-left:3px solid #ff9800">
                            <i class="fas fa-info-circle me-1 text-warning"></i>
                            <strong>Ajuste de preparación:</strong> aplica únicamente en Prioridad 2 y 3.
                            Ambos campos (<code>presentacionpreparacion</code> y <code>conversionpreparacion</code>)
                            deben tener valor en <code>DBIngredientes</code>; si cualquiera es NULL se usa el
                            comportamiento estándar (UnidadBase / Cantidad sin transformar).
                        </div>
                    </div>

                    <!-- Segmento 3 -->
                    <div class="p-2 mb-3 rounded" style="background:#f3e5f5;border-left:4px solid #9c27b0">
                        <strong><i class="fas fa-layer-group me-1" style="color:#7b1fa2"></i> Nuevo Sistema
                            (ERP)</strong>
                        <p class="mb-1 mt-1" style="font-size:.82rem">Traducción del ingrediente al catálogo del nuevo
                            ERP Pitaya.</p>
                        <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                            <thead style="background:#e1bee7">
                                <tr>
                                    <th>Estado</th>
                                    <th>¿Qué muestra?</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge" style="background:#c8e6c9;color:#1b5e20">✓ Traducido</span>
                                    </td>
                                    <td>Nombre del producto en el nuevo ERP, unidad, cantidad y producto maestro.
                                        Si fue resuelto automáticamente por maestro + unidad, muestra badge
                                        <span
                                            style="background:#e8f5e9;color:#2e7d32;font-size:.72rem;border-radius:3px;padding:1px 5px">AUTO</span>.
                                    </td>
                                </tr>
                                <tr>
                                    <td><span style="color:#888;font-style:italic;font-size:.82rem">⚠ Sin mapeo</span>
                                    </td>
                                    <td>Hay cotización resuelta pero el ingrediente no tiene mapeo en el diccionario del
                                        ERP.</td>
                                </tr>
                                <tr>
                                    <td><span class="text-danger" style="font-style:italic;font-size:.82rem">✕ No
                                            resuelto</span></td>
                                    <td>Sin cotización y sin traducción. El ingrediente no pudo resolverse en ningún
                                        paso.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr>

                    <!-- ── SECCIÓN 4: Resumen estadístico ────────────────────────── -->
                    <h6 class="fw-bold text-success mb-2"><i class="fas fa-chart-bar me-1"></i> Barra de Resumen</h6>
                    <p>Encima de la tabla se muestra un resumen con los conteos de:</p>
                    <ul class="mb-0">
                        <li><strong>Total de ingredientes</strong> en la receta.</li>
                        <li class="text-success"><strong>Traducidos al nuevo ERP</strong> — tienen producto mapeado.
                        </li>
                        <li class="text-warning"><strong>Con cotización pero sin mapeo</strong> — cotización encontrada
                            pero sin entrada en el diccionario ERP.</li>
                        <li class="text-danger"><strong>Sin cotización resuelta</strong> — ninguna de las 3 prioridades
                            encontró resultado.</li>
                    </ul>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>

</body>

</html>