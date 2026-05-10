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
    <title>Visor de Recetas</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo $version; ?>">
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
            <?php echo renderHeader($usuario, 'Visor de Recetas'); ?>

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
                                <input type="radio" class="btn-check" name="estadoBatido" id="estadoTodos" value="todos">
                                <label class="btn btn-outline-secondary" for="estadoTodos">Todos</label>
                                <input type="radio" class="btn-check" name="estadoBatido" id="estadoActivos"
                                    value="activos" checked>
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
                                <th colspan="4" class="text-center"
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
                                <th>Presentación Despacho</th>
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
        let estadoFiltro = 'activos'; // 'todos' | 'activos' | 'inactivos'

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
                const esArtilugio = parseInt(ingr.es_artilugio) === 1;
                const nomClass = (vigente && !esArtilugio) ? '' : 'ingr-inactivo';
                
                let nomBadge = vigente ? '' : `<span style="font-size:.65rem;background:#fdd;color:#c0392b;border-radius:3px;padding:1px 5px;margin-left:4px">INACTIVO</span>`;
                if (esArtilugio) {
                    nomBadge += `<span style="font-size:.65rem;background:#eee;color:#777;border-radius:3px;padding:1px 5px;margin-left:4px" title="Componente de Artilugio — No se mapea">ARTILUGIO</span>`;
                }

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
                    : (esArtilugio ? `<span class="sin-cot text-muted">Excluido por Artilugio</span>` : `<span class="sin-cot">Sin cotización</span>`);

                // ── Comanda Access: Nombre ───────────────────────────────────────────
                const comandaClass = esArtilugio ? 'ingr-inactivo' : '';
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
                const np = ingr.nuevo_producto;
                const ir = ingr.insumo_receta;
                const escenario = ingr.escenario_erp;

                // Insumo Receta
                const recetaTag = `<span style="font-size:.65rem;background:#e8eaf6;color:#3949ab;border-radius:3px;padding:1px 5px;margin-left:4px" title="Producto compuesto — receta_producto_global">&#128203; Receta</span>`;

                let celInsumoReceta;
                if (escenario === 'receta_global' && ir) {
                    // Es un compuesto: unidad=Unidades, cantidad=1, sin conversión
                    celInsumoReceta = `<div class="nom-nuevo">${esc(ir.NombreNuevo)}${recetaTag}</div>
                        <div class="uni-nuevo">Unidades &middot; 1</div>`;
                } else if (ir) {
                    const irActivo = ir.activoNuevo === 'NO'
                        ? `<span style="font-size:.65rem;background:#fdd;color:#c0392b;border-radius:3px;padding:1px 5px">INACTIVO</span>` : '';
                    celInsumoReceta = `<div class="nom-nuevo">${esc(ir.NombreNuevo)}${irActivo}</div>
                        <div class="uni-nuevo">${esc(ir.unidadNueva || '')}${ir.cantidad ? ' &middot; ' + ir.cantidad : ''}</div>`;
                } else if (np) {
                    celInsumoReceta = `<span class="traduccion-na"><i class="fas fa-search me-1 text-warning"></i>Sin equiv. de unidad</span>`;
                } else if (cot) {
                    celInsumoReceta = `<span class="traduccion-na"><i class="fas fa-exclamation-triangle me-1 text-warning"></i>Sin mapeo</span>`;
                } else {
                    celInsumoReceta = `<span class="traduccion-na text-danger"><i class="fas fa-times-circle me-1"></i>No resuelto</span>`;
                }

                // Cantidad ERP
                let celCantERP = '—';
                if (escenario === 'receta_global') {
                    // Compuesto: ppCant=1, factor=1 → Cantidad = srCant tal cual
                    const srCantR = parseFloat(ingr.Cantidad);
                    celCantERP = isNaN(srCantR) ? '—' : (srCantR % 1 === 0 ? srCantR.toString() : srCantR.toFixed(4).replace(/\.?0+$/, ''));
                } else if (ir && ir.cantidad != null) {
                    const ppCant = parseFloat(ir.cantidad);
                    const srCant = parseFloat(ingr.Cantidad);
                    const factor = (ir.factor_conversion != null) ? parseFloat(ir.factor_conversion) : 1;
                    const esDirP1 = ingr.metodo_cotizacion === 'directa';

                    if (ppCant > 0 && !isNaN(srCant)) {
                        const resultado = (srCant * factor) / ppCant;

                        let display;
                        if (esDirP1) {
                            const redondeado = Math.round(resultado * 2) / 2;
                            display = redondeado % 1 === 0
                                ? redondeado.toString()
                                : redondeado.toFixed(1);
                        } else {
                            display = resultado % 1 === 0
                                ? resultado.toString()
                                : parseFloat(resultado.toFixed(4)).toString();
                        }

                        if (factor !== 1 && ir.factor_conversion != null) {
                            const exacto = parseFloat(resultado.toFixed(4));
                            celCantERP = `<span title="${srCant} ${esc(ingr.UnidadIngrediente)} × ${factor} ÷ ${ppCant} ${esc(ir.unidadNueva)} = ${exacto}">${display}</span>`;
                        } else {
                            celCantERP = display;
                        }
                    }
                }

                // Presentación Uso
                let celPresentacionUso;
                if (np) {
                    const activoTag = np.activoNuevo === 'NO'
                        ? `<span style="font-size:.65rem;background:#fdd;color:#c0392b;border-radius:3px;padding:1px 5px;margin-left:4px">INACTIVO</span>` : '';
                    const autoTag = ingr.metodo_resolucion === 'maestro'
                        ? `<span style="font-size:.65rem;background:#e8f5e9;color:#2e7d32;border-radius:3px;padding:1px 5px;margin-left:4px" title="Resuelto automáticamente por maestro + unidad">AUTO</span>` : '';
                    const npRecipeTag = escenario === 'receta_global' ? recetaTag : '';
                    let variedadesHTML = '';
                    if (np.variedades && np.variedades.length > 0) {
                        variedadesHTML = `<select class="form-select form-select-sm mt-1" style="font-size:.75rem;padding:2px 5px;height:auto">
                            ${np.variedades.map(v => `<option value="${v.id}" ${v.es_principal == 1 ? 'selected' : ''}>${esc(v.nombre)} ${v.es_principal == 1 ? '(Principal)' : ''}</option>`).join('')}
                        </select>`;
                    }
                    celPresentacionUso = `<div class="traduccion-ok">
                        <div class="d-flex align-items-center gap-1 mb-1">${activoTag}${autoTag}${npRecipeTag}</div>
                        <div class="nom-nuevo">${esc(np.NombreNuevo)}</div>
                        ${variedadesHTML}
                        <div class="uni-nuevo mt-1">${esc(np.unidadNueva || '')}${np.cantidad ? ' &middot; ' + np.cantidad : ''} ${esc(np.productoMaestro || '')}</div>
                    </div>`;
                } else if (cot) {
                    celPresentacionUso = `<span class="traduccion-na"><i class="fas fa-exclamation-triangle me-1 text-warning"></i>Sin mapeo</span>`;
                } else {
                    celPresentacionUso = `<span class="traduccion-na text-danger"><i class="fas fa-times-circle me-1"></i>No resuelto</span>`;
                }

                // Presentación Despacho
                const pd = ingr.presentacion_despacho;
                let celPresentacionDespacho;
                if (pd) {
                    const activoTag = pd.activoNuevo === 'NO'
                        ? `<span style="font-size:.65rem;background:#fdd;color:#c0392b;border-radius:3px;padding:1px 5px;margin-left:4px">INACTIVO</span>` : '';
                    const npRecipeTag = escenario === 'receta_global' ? recetaTag : '';
                    
                    celPresentacionDespacho = `<div class="traduccion-ok" style="background:#fff8e1; border-color:#ffe082">
                        <div class="d-flex align-items-center gap-1 mb-1">${activoTag}${npRecipeTag}</div>
                        <div class="nom-nuevo" style="color:#e65100">${esc(pd.NombreNuevo)}</div>
                        <div class="uni-nuevo mt-1">${esc(pd.unidadNueva || '')}${pd.cantidad ? ' &middot; ' + pd.cantidad : ''} ${esc(pd.productoMaestro || '')}</div>
                    </div>`;
                } else if (cot) {
                    celPresentacionDespacho = `<span class="traduccion-na"><i class="fas fa-exclamation-triangle me-1 text-warning"></i>Sin despacho</span>`;
                } else {
                    celPresentacionDespacho = `<span class="traduccion-na text-danger"><i class="fas fa-times-circle me-1"></i>No resuelto</span>`;
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
                    <td style="font-size:.8rem" class="${comandaClass}">${esc(comandaNombre)}</td>
                    <td class="text-center fw-semibold ${comandaClass}" style="border-right:3px solid #5c7aff;font-size:.8rem">${comandaCantidad}</td>
                    <!-- Nuevo Sistema -->
                    <td>${celInsumoReceta}</td>
                    <td class="text-center fw-semibold">${celCantERP}</td>
                    <td>${celPresentacionUso}</td>
                    <td>${celPresentacionDespacho}</td>
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

                    <!-- Segmento 3 — NUEVO SISTEMA (ERP) — Guía completa -->
                    <div class="p-3 mb-3 rounded" style="background:#f3e5f5;border-left:4px solid #9c27b0">
                        <strong><i class="fas fa-layer-group me-1" style="color:#7b1fa2"></i> Nuevo Sistema (ERP)</strong>
                        <p class="mb-2 mt-1" style="font-size:.82rem">
                            El segmento <em>Nuevo Sistema</em> traduce cada ingrediente del catálogo antiguo (Access) al
                            catálogo del nuevo ERP Pitaya. Se compone de <strong>4 columnas</strong>:
                            <span class="badge" style="background:#9c27b0;color:#fff">Insumo Receta</span>
                            <span class="badge" style="background:#7b1fa2;color:#fff">Cantidad</span>
                            <span class="badge" style="background:#6a1b9a;color:#fff">Presentación Uso</span>
                            <span class="badge" style="background:#e65100;color:#fff">Presentación Despacho</span>
                        </p>

                        <!-- ── 3.1 Ruta de resolución de cotización ──────────── -->
                        <div class="mb-3">
                            <h6 style="font-size:.82rem;color:#6a1b9a;font-weight:700">
                                <i class="fas fa-route me-1"></i> Prioridades de resolución de cotización
                            </h6>
                            <p style="font-size:.79rem;margin-bottom:6px">
                                El sistema sigue 3 prioridades para encontrar la cotización y el producto ERP
                                correspondiente a cada ingrediente:
                            </p>
                            <table class="table table-sm table-bordered mb-2" style="font-size:.79rem">
                                <thead style="background:#e1bee7">
                                    <tr><th style="width:110px">Prioridad</th><th>Origen</th><th>Condición</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>P1 — Porción directa</strong></td>
                                        <td>El ingrediente tiene un <code>codporcion</code> asignado que apunta directamente a una cotización mapeada en el <em>diccionario ERP</em>.</td>
                                        <td><code>SubReceta.codporcion</code> no nulo</td>
                                    </tr>
                                    <tr>
                                        <td><strong>P2 — Cotización base</strong></td>
                                        <td>Se busca la cotización con <code>Conversion=1</code> y <code>Prioridad=1</code> para el ingrediente, excluyendo subproductos y "Almacen Global".</td>
                                        <td><code>codporcion</code> es nulo y existe cotiz. base</td>
                                    </tr>
                                    <tr>
                                        <td><strong>P3 — Prioritaria</strong></td>
                                        <td>Cualquier cotización activa del ingrediente (sin filtro de conversión/prioridad), como fallback final.</td>
                                        <td>No hubo P1 ni P2</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p style="font-size:.78rem;margin-bottom:6px">
                                Una vez localizada la cotización, se busca su mapeo en
                                <code>diccionario_productos_legado → producto_presentacion</code>.
                                Si no existe mapeo directo, se intenta resolución automática por
                                <strong>Maestro + Unidad</strong> (muestra badge <span style="background:#e8f5e9;color:#2e7d32;font-size:.72rem;border-radius:3px;padding:1px 5px">AUTO</span>).
                            </p>
                            <div class="p-2 rounded" style="background:#ede7f6;font-size:.77rem;border-left:3px solid #7c4dff">
                                <i class="fas fa-puzzle-piece me-1" style="color:#6a1b9a"></i>
                                <strong>Pre-verificación: Receta Global.</strong>
                                Antes de ejecutar P1/P2/P3 de resolución de unidades, el sistema comprueba si
                                <code>producto_presentacion.Id_receta_producto IS NOT NULL</code>.
                                Si es así, se trata de un <strong>producto compuesto</strong> cuyos campos
                                <code>cantidad</code>, <code>id_unidad_producto</code> e <code>id_producto_maestro</code>
                                son NULL en la base de datos. En ese caso se asignan valores fijos
                                (<em>cantidad=1, unidad=Unidades</em>) y se omite toda la búsqueda de
                                equivalencias de unidad — la columna Cantidad mostrará directamente la
                                cantidad del ingrediente en Access. Se identifica con el badge
                                <span style="font-size:.72rem;background:#e8eaf6;color:#3949ab;border-radius:3px;padding:1px 5px">📋 Receta</span>.
                            </div>
                        </div>

                        <hr class="my-2">

                        <!-- ── 3.2 Columna: Presentación Uso ────────────────── -->
                        <div class="mb-3">
                            <h6 style="font-size:.82rem;color:#6a1b9a;font-weight:700">
                                <i class="fas fa-box-open me-1"></i> Columna: Presentación Uso
                            </h6>
                            <p style="font-size:.79rem;margin-bottom:4px">
                                Muestra el producto del nuevo ERP que actualmente se usa como cotización para
                                ese ingrediente. Es el resultado directo del mapeo P1/P2/P3 en el diccionario.
                            </p>
                            <ul style="font-size:.79rem;margin-bottom:0">
                                <li><strong>Condición obligatoria</strong>: El producto debe tener marcada la casilla <code>presentacion_basica_inventario = 1</code> en el ERP. Si no la tiene, se muestra como "Sin mapeo".</li>
                                <li><strong>Nombre</strong>: <code>producto_presentacion.Nombre</code> del ERP.</li>
                                <li><strong>Unidad · Cantidad</strong>: unidad de medida y cantidad por presentación (ej: <em>Litros · 1.00</em>).</li>
                                <li><strong>Variedades</strong>: permite elegir sabores o tamaños si el producto los tiene registrados.</li>
                                <li><strong>Badge AUTO</strong>: fue resuelto automáticamente por Maestro + Unidad (no por diccionario directo).</li>
                                <li><strong>Badge 📋 Receta</strong>: el mapeo apunta a un <strong>producto compuesto</strong>.</li>
                            </ul>
                        </div>

                        <hr class="my-2">

                        <!-- ── 3.3 Columna: Insumo Receta ───────────────────── -->
                        <div class="mb-3">
                            <h6 style="font-size:.82rem;color:#6a1b9a;font-weight:700">
                                <i class="fas fa-list-check me-1"></i> Columna: Insumo Receta
                            </h6>
                            <p style="font-size:.79rem;margin-bottom:6px">
                                Es la presentación del ERP <strong>cuya unidad coincide (o se convierte) con la unidad del ingrediente en Access</strong>.
                                Se calcula independientemente de Presentación Uso.
                            </p>
                            <p style="font-size:.79rem;margin-bottom:6px">
                                <strong>Condición obligatoria</strong>: El producto debe tener marcada la casilla <code>presentacion_receta = 1</code> en el ERP.
                            </p>

                            <p style="font-size:.79rem;font-weight:600;margin-bottom:4px">Algoritmo de resolución en 3 niveles:</p>
                            <table class="table table-sm table-bordered mb-2" style="font-size:.78rem">
                                <thead style="background:#e1bee7">
                                    <tr><th style="width:100px">Nivel</th><th>Qué hace</th><th>Ejemplo</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Nivel 1</strong><br><small>multi_directos</small></td>
                                        <td>
                                            Resuelve la unidad Access a su(s) equivalente(s) ERP buscando en:
                                            <ol class="mb-0" style="padding-left:16px">
                                                <li><code>unidad_producto.abreviado</code> exacto</li>
                                                <li><code>unidad_producto.nombre</code> exacto</li>
                                                <li>Token en <code>nombres_opcionales</code> (FIND_IN_SET)</li>
                                            </ol>
                                            Además de la coincidencia principal, incluye <em>coincidencias secundarias</em>:
                                            otras unidades ERP que también contienen ese string.
                                            Busca presentaciones del mismo maestro con cualquiera de esas unidades,
                                            dando prioridad a las de <code>cantidad = 1</code>.
                                        </td>
                                        <td>
                                            <code>"oz"</code> → Onzas Peso (principal) + Onzas Liquidas (secundaria)<br>
                                            Busca en ambas unidades → elige <em>Leche Entera oz</em> (1 Onzas Liquidas) ✓
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nivel 2</strong><br><small>convertibles (transitivo)</small></td>
                                        <td>
                                            Si Nivel 1 no encontró presentación, busca unidades <em>relacionadas por conversión</em>
                                            usando el grafo de <code>conversion_unidad_producto</code> con cierre transitivo
                                            <strong>Floyd-Warshall</strong>.
                                            Esto permite cadenas multi-salto: <code>oz → gr → kg</code> sin necesidad de una
                                            fila directa <em>oz→kg</em> en la BD. Si encuentra, guarda el <code>factor_conversion</code>
                                            acumulado para el cálculo de cantidad.
                                        </td>
                                        <td>
                                            <code>"oz"</code> → Onzas Peso → (via Gramos) → Kilogramos<br>
                                            Sin presentación directa en oz, ni en gr, <em>pero sí en kg</em>.<br>
                                            Factor = 1/28.35 × 1/1000 = 0.0000352<br>
                                            Busca en Kilogramos → <em>Miel kg</em> ✓
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nivel 3</strong><br><small>fallback ERP</small></td>
                                        <td>
                                            Si aún no encontró, busca directamente con la unidad de <em>Presentación Uso</em>
                                            (la unidad ya resuelta del producto mapeado).
                                            También intenta recuperar el factor de conversión automáticamente.
                                        </td>
                                        <td>
                                            Cuando Niveles 1 y 2 fallan, se usa la unidad de la Presentación Uso
                                            (ej: Litros) y se busca la conversión gr→Litros en la tabla.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="p-2 rounded mb-2" style="background:#fff3e0;font-size:.77rem;border-left:3px solid #ff9800">
                                <i class="fas fa-wrench me-1 text-warning"></i>
                                <strong>Para mejorar la resolución:</strong> registra conversiones entre unidades en
                                <em>Historial de Conversiones</em> (módulo Productos). El sistema aplica <strong>Floyd-Warshall</strong>
                                automáticamente, por lo que no necesitas tener todas las combinaciones directas — basta con los
                                eslabones intermedios (ej: oz→gr y gr→kg). Si una unidad Access no se reconoce, verifica que exista
                                en <code>unidad_producto</code> con el <strong>abreviado</strong> o en
                                <strong>nombres_opcionales</strong> correcto.
                            </div>
                            <div class="p-2 rounded mb-2" style="background:#fefce8;font-size:.77rem;border-left:3px solid #ca8a04">
                                <i class="fas fa-exclamation-triangle me-1" style="color:#ca8a04"></i>
                                <strong>Insumos con rendimiento variable (ej: Naranja):</strong> cuando un ingrediente se procesa
                                en tienda y su rendimiento varía (2–3 oz por unidad), el estándar de la industria es fijar un
                                <strong>yield factor conservador</strong> (ej: 1 naranja = 2.0 oz). La variabilidad queda
                                absorbida por la desviación estándar del consumo histórico en el Pedido Sugerido.
                                Agrega la conversión en <code>conversion_unidad_producto</code> con el factor conservador elegido.
                            </div>

                            <p style="font-size:.79rem;margin-bottom:4px"><strong>Estados posibles del Insumo Receta:</strong></p>
                            <table class="table table-sm table-bordered mb-0" style="font-size:.78rem">
                                <tbody>
                                    <tr style="background:#ede7f6">
                                        <td style="width:160px"><span style="color:#3949ab;font-style:italic">📋 Nombre ERP <span style="font-size:.7rem;background:#e8eaf6;border-radius:3px;padding:1px 4px">Receta</span></span></td>
                                        <td>El producto mapeado es un <strong>compuesto / receta global</strong> (<code>Id_receta_producto IS NOT NULL</code>). Los campos <code>cantidad</code>, <code>id_unidad_producto</code> y <code>id_producto_maestro</code> son NULL en BD. El sistema asigna automáticamente <em>cantidad=1, unidad=Unidades</em> y muestra la cantidad Access sin conversión. No se ejecutan los 3 niveles de búsqueda de unidad.</td>
                                    </tr>
                                    <tr>
                                        <td><em>Nombre del producto ERP</em></td>
                                        <td>Insumo resuelto correctamente con unidad equivalente encontrada.</td>
                                    </tr>
                                    <tr>
                                        <td><span style="color:#888;font-style:italic">⚠ Sin equiv. de unidad</span></td>
                                        <td>Hay Presentación Uso pero ninguno de los 3 niveles encontró una presentación en la unidad del ingrediente. Revisar conversiones y unidades en el ERP.</td>
                                    </tr>
                                    <tr>
                                        <td><span style="color:#888;font-style:italic">⚠ Sin mapeo</span></td>
                                        <td>Hay cotización resuelta pero no existe mapeo en el diccionario ERP.</td>
                                    </tr>
                                    <tr>
                                        <td><span class="text-danger" style="font-style:italic">✕ No resuelto</span></td>
                                        <td>Sin cotización y sin traducción posible por ninguna prioridad.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <hr class="my-2">

                        <!-- ── 3.4 Columna: Presentación Despacho ────────────── -->
                        <div class="mb-3">
                            <h6 style="font-size:.82rem;color:#e65100;font-weight:700">
                                <i class="fas fa-truck me-1"></i> Columna: Presentación Despacho
                            </h6>
                            <p style="font-size:.79rem;margin-bottom:6px">
                                Presenta la unidad de embalaje para logística y traslados
                                (<code>presentacion_despacho = 1</code>). La búsqueda usa una
                                <strong>lógica bifurcada según si el ingrediente es una porción</strong>
                                para evitar que presentaciones de inventario físico (ej: bandejas en gramos)
                                sean asignadas incorrectamente como despacho de una porción empacada:
                            </p>


                            <!-- Tabla comparativa por tipo de ingrediente -->
                            <div class="p-2 rounded mb-2" style="background:#e3f2fd;border-left:3px solid #1565c0;font-size:.77rem">
                                <i class="fas fa-code-branch me-1" style="color:#1565c0"></i>
                                <strong style="color:#1565c0">Orden de resolución según tipo de ingrediente</strong>
                                <table class="table table-sm table-bordered mt-2 mb-0" style="font-size:.76rem;background:#fff">
                                    <thead style="background:#bbdefb">
                                        <tr>
                                            <th style="width:120px">Tipo</th>
                                            <th>Orden de pasos</th>
                                            <th>Ejemplo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span style="background:#e3f2fd;color:#1565c0;border-radius:3px;padding:1px 5px;font-size:.72rem">porción</span><br><small>(<code>codporcion &gt; 0</code>)</small></td>
                                            <td>
                                                <strong>① Paso A</strong> — Busca primero la <em>receta-paquete</em>: presentación con
                                                <code>Id_receta_producto IS NOT NULL</code>, <code>despacho=1</code>, con exactamente
                                                1 componente = Presentación Uso exacta.<br>
                                                <strong>② Paso B</strong> — Si no existe paquete exacto: Nivel 1 &rarr; Nivel 2 &rarr; Fallback 1 (flujo normal por unidad).<br>
                                                <strong>③ Paso D</strong> — Si aún no encuentra: receta-paquete cuyo componente sea cualquier presentación del mismo maestro.
                                            </td>
                                            <td>
                                                <strong>Con paquete exacto:</strong><br>Fresa Congelada 2oz &rarr; <em>Fresa paquete 10 unid</em> ✓<br><br>
                                                <strong>Sin paquete exacto:</strong><br>Banano congelado &rarr; sin receta-paquete &rarr; Fallback 1 (mismo maestro) &rarr; <em>Banano Cajilla 100u</em> ✓
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><em style="color:#555">no porción</em></td>
                                            <td>
                                                <strong>① Paso B</strong> — Flujo normal: Nivel 1 &rarr; Nivel 2 &rarr; Fallback 1.<br>
                                                <strong>② Paso C</strong> — Fallback 2: receta-paquete con Presentación Uso exacta como componente.<br>
                                                <strong>③ Paso D</strong> — Fallback 3: receta-paquete cuyo componente sea cualquier presentación del mismo maestro.
                                            </td>
                                            <td>
                                                Naranja oz &rarr; busca despacho por unidad &rarr; falla<br>
                                                &rarr; Paso C falla (componente no es "Naranja oz")<br>
                                                &rarr; Paso D: cajilla contiene "Naranja Unidad" (mismo maestro) &rarr; <em>Naranja Dulce Cajilla 100u</em> ✓
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="row g-1 mb-2">
                                <div class="col-md-6">
                                    <div class="p-2 rounded h-100" style="background:#fff8f2;border-left:3px solid #e65100;font-size:.77rem">
                                        <strong style="color:#e65100">Nivel 1 — Unidad directa</strong><br>
                                        Busca en el mismo maestro una presentación con <code>despacho=1</code>
                                        cuya unidad ERP coincide directamente con la unidad Access del ingrediente.<br>
                                        <em>Ej: ingrediente en "Unid" &rarr; busca despacho con unidad "Unidades".</em>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-2 rounded h-100" style="background:#fff8f2;border-left:3px solid #bf360c;font-size:.77rem">
                                        <strong style="color:#bf360c">Nivel 2 — Unidad convertible (transitivo)</strong><br>
                                        Si el Nivel 1 falla, repite la búsqueda con las unidades del grafo de conversiones
                                        expandido por <strong>Floyd-Warshall</strong>. Resuelve cadenas multi-salto
                                        <code>oz → gr → kg</code> sin necesidad de filas directas en la BD.<br>
                                        <em>Ej: ingrediente en "oz" &rarr; busca despacho en "Libras" o "Kilogramos" (vía Gramos).</em>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-2 rounded h-100" style="background:#fce4ec;border-left:3px solid #c62828;font-size:.77rem">
                                        <strong style="color:#c62828">Fallback 1 — Cualquier despacho del mismo maestro</strong><br>
                                        Si los niveles 1 y 2 fallan, acepta cualquier presentación del mismo
                                        <code>id_producto_maestro</code> con <code>despacho=1</code>, sin restricción de unidad.<br>
                                        <em>Ej: Banano congelado (porción sin paquete) &rarr; <strong>Banano Cajilla 100u</strong> ✓</em><br>
                                        <small style="color:#888">La cajilla comparte maestro con "Banano congelado unid" y tiene <code>despacho=1</code>.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-2 rounded h-100" style="background:#e8eaf6;border-left:3px solid #283593;font-size:.77rem">
                                        <strong style="color:#283593">Fallback 2 (Paso C) — Receta-paquete de 1 solo componente = Presentación Uso</strong><br>
                                        <span class="badge" style="background:#1565c0;color:#fff;font-size:.6rem">1º para porciones (Paso A)</span>
                                        <span class="badge ms-1" style="background:#283593;color:#fff;font-size:.6rem">2º recurso para no-porciones</span><br>
                                        Busca una presentación con <code>Id_receta_producto IS NOT NULL</code>,
                                        <code>despacho=1</code>, cuya receta tenga <strong>exactamente 1 componente</strong> = Presentación Uso exacta.<br>
                                        <small style="color:#c62828">⚠ La restricción <code>COUNT(componentes) = 1</code> es crítica: evita que recetas complejas sean identificadas erróneamente como paquetes.</small><br>
                                        <em>Ej: Fresa Congelada 2oz &rarr; Fresa paquete 10 unid ✓</em>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-2 rounded h-100" style="background:#e8f5e9;border-left:3px solid #2e7d32;font-size:.77rem">
                                        <strong style="color:#2e7d32">Fallback 3 (Paso D) — Receta-paquete con componente del mismo maestro <span class="badge ms-1" style="background:#43a047;color:#fff;font-size:.6rem">Nuevo</span></strong><br>
                                        Último recurso: busca una receta de despacho cuyo componente sea <strong>cualquier presentación del mismo <code>id_producto_maestro</code></strong>, sin exigir que sea exactamente la Presentación Uso.<br>
                                        <small style="color:#1b5e20">Cubre el caso donde la cajilla tiene como componente una presentación diferente pero del mismo ingrediente maestro.</small><br>
                                        <em>Ej: Naranja oz &rarr; Cajilla 100u (receta: 100 × "Naranja Unidad", mismo maestro) ✓</em>
                                    </div>
                                </div>
                            </div>
                            <div class="p-2 rounded" style="background:#fff3e0;border-left:3px solid #ff9800;font-size:.77rem">
                                <i class="fas fa-exclamation-triangle me-1 text-warning"></i>
                                <strong>Si muestra "Sin despacho":</strong> ninguno de los 4 pasos encontró una presentación válida.
                                Verifica que exista un producto con <code>presentacion_despacho = 1</code> configurado en el ERP,
                                y que si es una receta-paquete, alguno de sus componentes pertenezca al mismo <code>id_producto_maestro</code>
                                del ingrediente (el Paso D lo detectará automáticamente).
                            </div>
                        </div>


                        <hr class="my-2">

                        <!-- ── 3.5 Columna: Cantidad ─────────────────────────── -->
                        <div class="mb-1">
                            <h6 style="font-size:.82rem;color:#6a1b9a;font-weight:700">
                                <i class="fas fa-calculator me-1"></i> Columna: Cantidad
                            </h6>
                            <p style="font-size:.79rem;margin-bottom:6px">
                                Indica <strong>cuántas unidades de la presentación del Insumo Receta</strong>
                                se necesitan para cumplir con la cantidad de la SubReceta.
                            </p>
                            <table class="table table-sm table-bordered mb-2" style="font-size:.78rem">
                                <thead style="background:#e1bee7">
                                    <tr><th>Caso</th><th>Fórmula</th><th>Ejemplo</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Misma unidad</strong></td>
                                        <td><code>Cantidad_Access ÷ ppCant</code></td>
                                        <td>Leche 4 oz ÷ 1 oz/und = <strong>4</strong></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Unidad diferente con conversión</strong></td>
                                        <td><code>(Cantidad_Access × factor) ÷ ppCant</code><br><small>factor = cantidad en tabla de conversiones (ej: 1 gr = 0.035 oz)</small></td>
                                        <td>Mani 24 gr × 0.035 ÷ 1 oz/und = <strong>0.84</strong><br>
                                            <small>Pasa el cursor sobre el número para ver la fórmula exacta.</small></td>
                                    </tr>
                                    <tr>
                                        <td><strong>P1 (porción directa)</strong></td>
                                        <td>Igual que los casos anteriores pero <strong>redondeado al 0.5 más cercano</strong>:<br>
                                            <code>Math.round(resultado × 2) / 2</code></td>
                                        <td>
                                            Piña 120 gr × 0.035 ÷ 4 oz/und = 1.05 → <strong>1.0</strong><br>
                                            1.26 → <strong>1.5</strong> &nbsp;|&nbsp; 1.75 → <strong>2.0</strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="p-2 rounded" style="background:#e8f5e9;font-size:.77rem;border-left:3px solid #4caf50">
                                <i class="fas fa-lightbulb me-1 text-success"></i>
                                <strong>¿Por qué el redondeo a 0.5 solo en P1?</strong> Las porciones directas son cantidades
                                de preparación manejadas por el personal (½ porción, 1 porción, 1½ porción). Expresarlas
                                con más de 1 decimal no aporta precisión operativa; el resto de ingredientes (P2/P3) son
                                insumos a granel donde sí importa la precisión decimal.
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- ── SECCIÓN 3.6 Casos Especiales: Artilugios ─────────────── -->
                    <div class="p-2 mb-3 rounded" style="background:#fff3e0;border-left:4px solid #ff9800">
                        <strong><i class="fas fa-exclamation-triangle me-1 text-warning"></i> Casos Especiales: Componentes de Artilugio</strong>
                        <p class="mb-1 mt-1" style="font-size:.82rem">
                            Existen ingredientes que en Access se registran como parte de una mezcla o "artilugio" (registrados en <code>MezclaPorcionesAccess</code>).
                        </p>
                        <ul style="font-size:.79rem;margin-bottom:0">
                            <li><strong>Identificación</strong>: Se muestran tachados y con el badge <span style="font-size:.65rem;background:#eee;color:#777;border-radius:3px;padding:1px 5px">ARTILUGIO</span>.</li>
                            <li><strong>Regla de Mapeo</strong>: Estos componentes NO se mapean al ERP individualmente, ya que su consumo se descuenta a través del producto maestro de la mezcla principal.</li>
                            <li><strong>Visualización</strong>: Toda la fila (Comanda Access y Nuevo Sistema) aparece tachada para indicar su exclusión del proceso de homologación.</li>
                        </ul>
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