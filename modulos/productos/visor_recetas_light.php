<?php
// visor_recetas_light.php
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
    <title>Consulta de Recetas | Productos</title>
    <meta name="description" content="Visor compacto de recetas del sistema ERP Pitaya. Consulta rápida sin edición.">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo $version; ?>">

    <style>
        /* ── Base ──────────────────────────────────────────────── */
        body {
            background: #f0f2f5;
            font-family: 'Outfit', sans-serif;
        }

        /* ── Layout principal: dos columnas ─────────────────────── */
        .vrl-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 16px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .vrl-layout {
                grid-template-columns: 1fr;
            }
        }

        /* ── Panel izquierdo — Menú de productos ─────────────────── */
        .vrl-menu {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            overflow: hidden;
            position: sticky;
            top: 16px;
            max-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }

        .vrl-menu-header {
            background: linear-gradient(135deg, #1a3a2a 0%, #2d6a4f 100%);
            padding: 16px 20px 14px;
            color: #fff;
            flex-shrink: 0;
        }

        .vrl-menu-header h6 {
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin: 0 0 10px;
            color: #95d5b2;
        }

        .vrl-search-wrap {
            position: relative;
        }

        .vrl-search {
            width: 100%;
            background: rgba(255,255,255,.12);
            border: 1.5px solid rgba(255,255,255,.25);
            border-radius: 10px;
            padding: 8px 14px 8px 36px;
            color: #fff;
            font-size: .85rem;
            font-family: 'Outfit', sans-serif;
            outline: none;
            transition: border-color .2s, background .2s;
        }

        .vrl-search::placeholder { color: rgba(255,255,255,.5); }
        .vrl-search:focus {
            border-color: rgba(255,255,255,.6);
            background: rgba(255,255,255,.18);
        }

        .vrl-search-icon {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,.5);
            font-size: .8rem;
        }

        .vrl-menu-body {
            overflow-y: auto;
            flex: 1;
        }

        .vrl-menu-body::-webkit-scrollbar { width: 4px; }
        .vrl-menu-body::-webkit-scrollbar-track { background: #f5f5f5; }
        .vrl-menu-body::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }

        /* Grupo accordion */
        .vrl-grupo-btn {
            width: 100%;
            background: none;
            border: none;
            text-align: left;
            padding: 11px 18px;
            font-family: 'Outfit', sans-serif;
            font-size: .83rem;
            font-weight: 700;
            color: #1a3a2a;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border-bottom: 1px solid #f0f2f5;
            transition: background .15s;
        }

        .vrl-grupo-btn:hover { background: #f6fff8; }
        .vrl-grupo-btn.active { background: #e8f5e9; color: #1b4332; }

        .vrl-grupo-btn .grupo-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            color: #fff;
            flex-shrink: 0;
        }

        .vrl-grupo-btn .grupo-count {
            margin-left: auto;
            font-size: .72rem;
            font-weight: 600;
            background: #e8f5e9;
            color: #2d6a4f;
            border-radius: 20px;
            padding: 1px 8px;
        }

        .vrl-grupo-btn .chevron {
            margin-left: 4px;
            font-size: .65rem;
            color: #aaa;
            transition: transform .2s;
        }

        .vrl-grupo-btn.active .chevron { transform: rotate(180deg); }

        /* Lista de productos dentro del grupo */
        .vrl-productos-list {
            display: none;
            background: #fafafa;
            border-bottom: 1px solid #eee;
        }

        .vrl-productos-list.open { display: block; }

        .vrl-prod-item {
            padding: 9px 18px 9px 54px;
            font-size: .82rem;
            color: #333;
            cursor: pointer;
            transition: background .12s;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
        }

        .vrl-prod-item:last-child { border-bottom: none; }
        .vrl-prod-item:hover { background: #e8f5e9; color: #1b4332; }
        .vrl-prod-item.selected {
            background: linear-gradient(90deg, #d4ede3, #e8f5e9);
            color: #1b4332;
            font-weight: 600;
            border-left: 3px solid #40916c;
        }

        .vrl-prod-item .prod-versiones-count {
            font-size: .7rem;
            color: #888;
            background: #ececec;
            border-radius: 10px;
            padding: 1px 7px;
            flex-shrink: 0;
        }

        .vrl-prod-item.selected .prod-versiones-count {
            background: #b7e4c7;
            color: #1b4332;
        }

        /* ── Panel derecho — Contenido ───────────────────────────── */
        .vrl-content {
            min-height: 400px;
        }

        /* ── Panel versiones ─────────────────────────────────────── */
        .vrl-versiones-panel {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            padding: 18px 20px;
            margin-bottom: 14px;
        }

        .vrl-versiones-title {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 12px;
        }

        .vrl-chips-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        /* Chip de versión */
        .vrl-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 14px;
            border-radius: 50px;
            background: #f0f2f5;
            border: 2px solid transparent;
            cursor: pointer;
            font-size: .82rem;
            font-weight: 600;
            color: #444;
            transition: all .18s;
            font-family: 'Outfit', sans-serif;
        }

        .vrl-chip:hover {
            background: #e8f5e9;
            border-color: #74c69d;
            color: #1b4332;
        }

        .vrl-chip.selected {
            background: linear-gradient(135deg, #1b4332, #2d6a4f);
            border-color: #1b4332;
            color: #fff;
            box-shadow: 0 4px 12px rgba(27,67,50,.3);
        }

        .vrl-chip .chip-code {
            font-size: .78rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        .vrl-chip .chip-medida {
            font-size: .73rem;
            opacity: .75;
        }

        /* Badge PedidosYa */
        .badge-pedidosya {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: linear-gradient(135deg, #ff3008, #ff6b35);
            color: #fff;
            font-size: .63rem;
            font-weight: 700;
            border-radius: 20px;
            padding: 2px 7px;
            letter-spacing: .03em;
            box-shadow: 0 2px 6px rgba(255,48,8,.35);
            flex-shrink: 0;
        }

        .badge-pedidosya i { font-size: .6rem; }

        /* ── Card batido seleccionado ─────────────────────────────── */
        .vrl-card-producto {
            background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 50%, #40916c 100%);
            border-radius: 14px;
            padding: 16px 22px;
            margin-bottom: 14px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .vrl-card-producto .prod-nombre {
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .vrl-card-producto .prod-codigo {
            font-family: 'Courier New', monospace;
            font-size: .8rem;
            background: rgba(255,255,255,.15);
            border-radius: 6px;
            padding: 2px 10px;
            display: inline-block;
            margin-top: 4px;
        }

        .vrl-card-producto .prod-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .vrl-card-producto .prod-meta {
            font-size: .78rem;
            color: rgba(255,255,255,.75);
        }

        .vrl-card-producto .prod-meta span {
            color: #fff;
            font-weight: 600;
        }

        /* ── Barra resumen ────────────────────────────────────────── */
        .vrl-resumen {
            background: #fff;
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 14px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 1px 6px rgba(0,0,0,.06);
        }

        .vrl-resumen-item {
            font-size: .8rem;
            color: #555;
        }

        .vrl-resumen-item .num {
            font-size: 1.15rem;
            font-weight: 800;
            color: #1b4332;
        }

        .vrl-resumen-item.warn .num { color: #e65100; }
        .vrl-resumen-item.danger .num { color: #c62828; }

        /* ── Tabla receta ─────────────────────────────────────────── */
        .vrl-table-wrap {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            overflow: hidden;
        }

        .table-receta-light {
            font-size: .82rem;
            margin: 0;
        }

        .table-receta-light thead {
            position: sticky;
            top: 0;
            z-index: 2;
        }

        /* Fila segmento */
        .table-receta-light .seg-comanda th {
            background: #1a237e;
            color: #c5cae9;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            padding: 5px 10px;
            border: none;
        }

        .table-receta-light .seg-nuevo th {
            background: #4a148c;
            color: #ce93d8;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            padding: 5px 10px;
            border: none;
        }

        /* Fila de columnas */
        .table-receta-light .cols-row th {
            background: #1a3a2a;
            color: #fff;
            font-size: .74rem;
            font-weight: 600;
            white-space: nowrap;
            vertical-align: middle;
            padding: 8px 12px;
            border: none;
        }

        .table-receta-light tbody td {
            vertical-align: middle;
            border-bottom: 1px solid #f0f2f5;
            padding: 9px 12px;
        }

        .table-receta-light tbody tr:hover { background: #f8fffe; }
        .table-receta-light tbody tr:last-child td { border-bottom: none; }

        /* Stripe tipo */
        .fila-B { border-left: 3px solid #4e73df; }
        .fila-L { border-left: 3px solid #f6c23e; }
        .fila-P { border-left: 3px solid #858796; }

        /* Separador visual comanda → nuevo */
        .td-sep-left {
            border-left: 3px solid #7c4dff !important;
        }

        /* ── Badges de tipo ─────────────────────────────────────── */
        .badge-tipo {
            font-size: .68rem;
            font-weight: 700;
            border-radius: 6px;
            padding: 2px 8px;
            white-space: nowrap;
        }

        .badge-tipo-B { background: #e8eaf6; color: #3949ab; }
        .badge-tipo-L { background: #fff8e1; color: #f57f17; }
        .badge-tipo-P { background: #f3e5f5; color: #7b1fa2; }
        .badge-tipo-X { background: #eeeeee; color: #555; }

        /* ── Celdas Nuevo Sistema ───────────────────────────────── */
        .cel-insumo {
            line-height: 1.3;
        }

        .cel-insumo .sku {
            font-size: .68rem;
            background: #e8f5e9;
            color: #1b5e20;
            border-radius: 4px;
            padding: 1px 6px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            margin-right: 4px;
        }

        .cel-insumo .nom-erp {
            font-size: .84rem;
            color: #1b4332;
            font-weight: 600;
        }

        .cel-insumo .uni-erp {
            font-size: .72rem;
            color: #777;
        }

        .cel-insumo .tag-receta {
            font-size: .62rem;
            background: #e8eaf6;
            color: #3949ab;
            border-radius: 3px;
            padding: 1px 5px;
            margin-left: 4px;
        }

        .cel-insumo .tag-inactivo {
            font-size: .62rem;
            background: #fdd;
            color: #c0392b;
            border-radius: 3px;
            padding: 1px 5px;
        }

        .cel-na {
            font-size: .75rem;
            color: #bbb;
            font-style: italic;
        }

        .cel-error {
            font-size: .75rem;
            color: #e57373;
            font-style: italic;
        }

        /* ── Panel vacío ────────────────────────────────────────── */
        .vrl-empty {
            text-align: center;
            padding: 80px 30px;
            color: #bbb;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
        }

        .vrl-empty .empty-icon {
            font-size: 3.5rem;
            margin-bottom: 16px;
            display: block;
            opacity: .35;
        }

        .vrl-empty p {
            font-size: .9rem;
            margin: 0;
        }

        /* ── Spinner ─────────────────────────────────────────────── */
        .vrl-spinner {
            display: none;
            text-align: center;
            padding: 80px 30px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            color: #555;
        }

        /* ── Loading overlay menu ────────────────────────────────── */
        .menu-loading {
            padding: 30px;
            text-align: center;
            color: #aaa;
            font-size: .85rem;
        }

        /* ── Divider ─────────────────────────────────────────────── */
        .vrl-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #ddd, transparent);
            margin: 2px 0;
        }

        /* ── Contador receta ─────────────────────────────────────── */
        .orden-num {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #e8f5e9;
            color: #1b4332;
            font-size: .72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Consulta de Recetas'); ?>

            <div class="vrl-layout">

                <!-- ══ Columna izquierda — Menú de productos ══ -->
                <aside class="vrl-menu" id="vrlMenu">
                    <div class="vrl-menu-header">
                        <h6><i class="fas fa-blender me-1"></i> Menú de Productos</h6>
                        <div class="vrl-search-wrap">
                            <i class="fas fa-search vrl-search-icon"></i>
                            <input type="text" class="vrl-search" id="inputBuscar"
                                placeholder="Buscar producto…" autocomplete="off">
                        </div>
                    </div>
                    <div class="vrl-menu-body" id="menuBody">
                        <div class="menu-loading">
                            <div class="spinner-border spinner-border-sm text-success mb-2"></div><br>
                            Cargando productos…
                        </div>
                    </div>
                </aside>

                <!-- ══ Columna derecha — Contenido ══ -->
                <main class="vrl-content" id="vrlContent">

                    <!-- Panel versiones (visible cuando se elige un producto) -->
                    <div class="vrl-versiones-panel d-none" id="panelVersiones">
                        <div class="vrl-versiones-title">
                            <i class="fas fa-tag me-1"></i>
                            Selecciona una versión / tamaño
                        </div>
                        <div class="vrl-chips-wrap" id="chipsVersiones"></div>
                    </div>

                    <!-- Card producto seleccionado -->
                    <div class="vrl-card-producto d-none" id="cardProducto"></div>

                    <!-- Resumen -->
                    <div class="vrl-resumen d-none" id="barraResumen"></div>

                    <!-- Spinner -->
                    <div class="vrl-spinner" id="spinnerReceta">
                        <div class="spinner-border text-success mb-3" style="width:2.5rem;height:2.5rem"></div>
                        <div style="font-size:.9rem">Cargando receta…</div>
                    </div>

                    <!-- Empty state -->
                    <div class="vrl-empty" id="panelVacio">
                        <i class="fas fa-blender empty-icon"></i>
                        <p>Elige un grupo y un producto<br>del menú para ver su receta</p>
                    </div>

                    <!-- Tabla receta -->
                    <div class="vrl-table-wrap d-none" id="panelTabla">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 table-receta-light" id="tablaReceta">
                                <thead>
                                    <!-- Segmentos -->
                                    <tr>
                                        <th colspan="2" class="seg-comanda text-center"
                                            style="background:#1a237e;color:#c5cae9;font-size:.68rem;font-weight:600;letter-spacing:.06em;padding:5px 10px;border:none;">
                                            <i class="fas fa-receipt me-1"></i> Comanda Access
                                        </th>
                                        <th colspan="3" class="seg-nuevo text-center"
                                            style="background:#4a148c;color:#ce93d8;font-size:.68rem;font-weight:600;letter-spacing:.06em;padding:5px 10px;border:none;">
                                            <i class="fas fa-layer-group me-1"></i> Nuevo Sistema ERP
                                        </th>
                                    </tr>
                                    <!-- Columnas -->
                                    <tr class="cols-row">
                                        <th style="width:50px">Orden</th>
                                        <th style="width:55px">Tipo</th>
                                        <th class="td-sep-left">Insumo Receta</th>
                                        <th style="width:80px;text-align:center">Cantidad</th>
                                        <th style="min-width:180px">Presentación Uso</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyReceta"></tbody>
                            </table>
                        </div>
                    </div>

                </main>
            </div><!-- /vrl-layout -->

        </div><!-- /sub-container -->
    </div><!-- /main-container -->

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    /* =====================================================================
       VISOR DE RECETAS LIGHT — JS
       ===================================================================== */

    const AJAX_PRODUCTOS  = 'ajax/visor_recetas_light_get_productos.php';
    const AJAX_RECETA     = '../sistemas/ajax/accessantiguo_get_detalle_receta.php';

    // Paleta de colores para grupos (cicla sobre los grupos)
    const GRUPO_COLORES = [
        '#2d6a4f','#1565c0','#6a1b9a','#c62828','#e65100',
        '#00838f','#4527a0','#2e7d32','#4e342e','#ad1457',
    ];

    // ── Utilidades ─────────────────────────────────────────────────────
    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function show(id) { document.getElementById(id).classList.remove('d-none'); }
    function hide(id) { document.getElementById(id).classList.add('d-none'); }
    function esPedidosYa(cod) {
        return typeof cod === 'string' && cod.toLowerCase().endsWith('d');
    }

    // ── Estado ─────────────────────────────────────────────────────────
    let todosGrupos = [];          // árbol completo cargado
    let productoActual = null;     // { Nombre, versiones: [] }
    let versionActual  = null;     // { CodBatido, Medida, Precio }

    // ── Cargar árbol de productos ───────────────────────────────────────
    async function iniciarMenu() {
        try {
            const res = await fetch(AJAX_PRODUCTOS);
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            todosGrupos = data.grupos;
            renderMenu(todosGrupos);
        } catch(e) {
            document.getElementById('menuBody').innerHTML =
                `<div class="menu-loading text-danger"><i class="fas fa-exclamation-circle me-1"></i>Error al cargar</div>`;
        }
    }

    // ── Renderizar menú ────────────────────────────────────────────────
    function renderMenu(grupos) {
        const body = document.getElementById('menuBody');
        if (!grupos.length) {
            body.innerHTML = `<div class="menu-loading">Sin productos activos</div>`;
            return;
        }

        let html = '';
        grupos.forEach((g, idx) => {
            const color  = GRUPO_COLORES[idx % GRUPO_COLORES.length];
            const totalProds = g.productos.length;
            const gid    = `grupo-${g.CodGrupo}`;
            const nombre = g.alias || g.NombreGrupo;

            html += `
            <button class="vrl-grupo-btn" id="btn-${gid}"
                    onclick="toggleGrupo('${g.CodGrupo}')">
                <span class="grupo-icon" style="background:${color}">
                    <i class="fas fa-box-open"></i>
                </span>
                <span class="flex-grow-1 text-start" style="font-size:.81rem">${esc(nombre)}</span>
                <span class="grupo-count">${totalProds}</span>
                <i class="fas fa-chevron-down chevron"></i>
            </button>
            <div class="vrl-productos-list" id="${gid}">`;

            g.productos.forEach(p => {
                const pid = CSS.escape(`prod-${g.CodGrupo}-${p.Nombre}`);
                html += `
                <div class="vrl-prod-item" id="prod-${esc(g.CodGrupo)}-${esc(p.Nombre)}"
                     onclick="seleccionarProducto(${g.CodGrupo}, ${JSON.stringify(p.Nombre)})">
                    <span class="prod-nombre">${esc(p.Nombre)}</span>
                    <span class="prod-versiones-count">${p.versiones.length}v</span>
                </div>`;
            });

            html += `</div>`;
        });

        body.innerHTML = html;
    }

    // ── Toggle grupo ──────────────────────────────────────────────────
    function toggleGrupo(codGrupo) {
        const lista = document.getElementById(`grupo-${codGrupo}`);
        const btn   = document.getElementById(`btn-grupo-${codGrupo}`);
        if (!lista) return;
        const isOpen = lista.classList.contains('open');
        // Cerrar todos
        document.querySelectorAll('.vrl-productos-list.open').forEach(el => el.classList.remove('open'));
        document.querySelectorAll('.vrl-grupo-btn.active').forEach(el => el.classList.remove('active'));
        // Abrir el clickeado (si no estaba abierto)
        if (!isOpen) {
            lista.classList.add('open');
            const btnEl = document.querySelector(`[onclick="toggleGrupo('${codGrupo}')"]`);
            if (btnEl) btnEl.classList.add('active');
        }
    }

    // ── Seleccionar producto ──────────────────────────────────────────
    function seleccionarProducto(codGrupo, nombre) {
        // Marcar activo
        document.querySelectorAll('.vrl-prod-item.selected').forEach(el => el.classList.remove('selected'));
        const id = `prod-${codGrupo}-${nombre}`;
        const el = document.getElementById(id);
        if (el) el.classList.add('selected');

        // Buscar producto en árbol
        const grupo = todosGrupos.find(g => g.CodGrupo == codGrupo);
        if (!grupo) return;
        const prod = grupo.productos.find(p => p.Nombre === nombre);
        if (!prod) return;
        productoActual = prod;
        versionActual  = null;

        resetContenido();
        renderVersiones(prod);
    }

    // ── Renderizar chips de versión ────────────────────────────────────
    function renderVersiones(prod) {
        const chips  = document.getElementById('chipsVersiones');
        const panel  = document.getElementById('panelVersiones');

        let html = '';
        prod.versiones.forEach(v => {
            const esPY  = esPedidosYa(v.CodBatido);
            const pyBadge = esPY
                ? `<span class="badge-pedidosya"><i class="fas fa-motorcycle"></i> PedidosYa</span>`
                : '';
            const medida = v.Medida ? `<span class="chip-medida">· ${esc(v.Medida)}</span>` : '';
            html += `
            <div class="vrl-chip" onclick="seleccionarVersion('${esc(v.CodBatido)}')"
                 id="chip-${esc(v.CodBatido)}">
                <span class="chip-code">${esc(v.CodBatido)}</span>
                ${medida}
                ${pyBadge}
            </div>`;
        });

        chips.innerHTML = html;
        panel.classList.remove('d-none');

        // Si solo hay una versión, seleccionarla automáticamente
        if (prod.versiones.length === 1) {
            seleccionarVersion(prod.versiones[0].CodBatido);
        }
    }

    // ── Seleccionar versión ───────────────────────────────────────────
    function seleccionarVersion(codBatido) {
        // Highlight chip
        document.querySelectorAll('.vrl-chip.selected').forEach(el => el.classList.remove('selected'));
        const chipEl = document.getElementById(`chip-${codBatido}`);
        if (chipEl) chipEl.classList.add('selected');

        // Buscar versión
        if (!productoActual) return;
        versionActual = productoActual.versiones.find(v => v.CodBatido === codBatido);

        // Ocultar tabla anterior
        hide('panelTabla');
        hide('cardProducto');
        hide('barraResumen');
        hide('panelVacio');
        document.getElementById('spinnerReceta').style.display = 'block';
        document.getElementById('tbodyReceta').innerHTML = '';

        // Cargar receta
        fetch(`${AJAX_RECETA}?cod_batido=${encodeURIComponent(codBatido)}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('spinnerReceta').style.display = 'none';
                if (!data.success) { show('panelVacio'); return; }
                renderCardProducto(data.batido);
                renderResumen(data.ingredientes);
                renderTabla(data.ingredientes);
            })
            .catch(() => {
                document.getElementById('spinnerReceta').style.display = 'none';
                show('panelVacio');
            });
    }

    // ── Card producto ─────────────────────────────────────────────────
    function renderCardProducto(b) {
        const esPY = esPedidosYa(b.CodBatido);
        const pyBadge = esPY
            ? `<span class="badge-pedidosya"><i class="fas fa-motorcycle me-1"></i>PedidosYa</span>` : '';
        const precioHTML = b.Precio
            ? `<div class="prod-meta mt-1"><span>C$ ${b.Precio}</span></div>` : '';

        document.getElementById('cardProducto').innerHTML = `
            <div>
                <div class="prod-nombre">${esc(b.Nombre)}</div>
                <div class="prod-codigo">${esc(b.CodBatido)}${b.Medida ? ' · ' + esc(b.Medida) : ''}</div>
                ${precioHTML}
            </div>
            <div class="prod-badges">
                ${pyBadge}
                <span style="font-size:.75rem;background:rgba(255,255,255,.15);border-radius:6px;padding:4px 12px;color:rgba(255,255,255,.85)">
                    <i class="fas fa-layer-group me-1"></i>${esc(b.NombreGrupo)}
                </span>
            </div>`;
        show('cardProducto');
    }

    // ── Resumen ───────────────────────────────────────────────────────
    function renderResumen(ingredientes) {
        const total      = ingredientes.length;
        const traducidos = ingredientes.filter(i => i.nuevo_producto).length;
        const pendientes = total - traducidos;

        document.getElementById('barraResumen').innerHTML = `
            <div class="vrl-resumen-item">
                <span class="num">${total}</span>
                <span class="text-muted ms-1">ingredientes</span>
            </div>
            <div class="vr"></div>
            <div class="vrl-resumen-item">
                <span class="num">${traducidos}</span>
                <span class="ms-1 text-success">resueltos en ERP</span>
            </div>
            ${pendientes > 0
                ? `<div class="vr"></div>
                   <div class="vrl-resumen-item danger">
                       <span class="num">${pendientes}</span>
                       <span class="ms-1 text-muted">sin resolver</span>
                   </div>`
                : ''}`;
        show('barraResumen');
    }

    // ── Tabla de ingredientes (solo Nuevo Sistema + Orden + Tipo) ──────
    function renderTabla(ingredientes) {
        const tbody = document.getElementById('tbodyReceta');

        if (!ingredientes.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-5">
                <i class="fas fa-exclamation-circle me-2"></i>Receta sin ingredientes registrados.</td></tr>`;
            show('panelTabla');
            return;
        }

        tbody.innerHTML = ingredientes.map((ingr, idx) => {
            const tipo      = ingr.Tipo || '—';
            const orden     = ingr.ordenreceta ?? (idx + 1);
            const filaClass = `fila-${tipo}`;

            // Badge de tipo
            const tipoClasses = { B:'badge-tipo-B', L:'badge-tipo-L', P:'badge-tipo-P' };
            const tipoCls = tipoClasses[tipo] || 'badge-tipo-X';
            const tipoBadge = `<span class="badge-tipo ${tipoCls}">${esc(tipo)}</span>`;

            // ── Insumo Receta ────────────────────────────────────────
            const ir        = ingr.insumo_receta;
            const np        = ingr.nuevo_producto;
            const escenario = ingr.escenario_erp;
            const recetaTag = `<span class="tag-receta">📋 Receta</span>`;

            let celInsumo;
            if (escenario === 'receta_global' && ir) {
                celInsumo = `<div class="cel-insumo">
                    <div class="nom-erp">${esc(ir.NombreNuevo)}${recetaTag}</div>
                    <div class="uni-erp">Unidades · 1</div>
                </div>`;
            } else if (ir) {
                const inacTag = ir.activoNuevo === 'NO'
                    ? `<span class="tag-inactivo ms-1">INACTIVO</span>` : '';
                celInsumo = `<div class="cel-insumo">
                    <div class="nom-erp">${esc(ir.NombreNuevo)}${inacTag}</div>
                    <div class="uni-erp">${esc(ir.unidadNueva || '')}${ir.cantidad ? ' · ' + ir.cantidad : ''}</div>
                </div>`;
            } else if (np) {
                celInsumo = `<span class="cel-na"><i class="fas fa-search me-1 text-warning"></i>Sin equiv. de unidad</span>`;
            } else if (ingr.cotizacion) {
                celInsumo = `<span class="cel-na"><i class="fas fa-exclamation-triangle me-1 text-warning"></i>Sin mapeo ERP</span>`;
            } else {
                celInsumo = `<span class="cel-error"><i class="fas fa-times-circle me-1"></i>No resuelto</span>`;
            }

            // ── Cantidad ERP ─────────────────────────────────────────
            let celCantidad = '—';
            if (escenario === 'receta_global') {
                const srCant = parseFloat(ingr.Cantidad);
                celCantidad = isNaN(srCant) ? '—'
                    : (srCant % 1 === 0 ? srCant.toString() : srCant.toFixed(4).replace(/\.?0+$/, ''));
            } else if (ir && ir.cantidad != null) {
                const ppCant = parseFloat(ir.cantidad);
                const srCant = parseFloat(ingr.Cantidad);
                const factor = ir.factor_conversion != null ? parseFloat(ir.factor_conversion) : 1;
                const esDirP1 = ingr.metodo_cotizacion === 'directa';
                if (ppCant > 0 && !isNaN(srCant)) {
                    const resultado = (srCant * factor) / ppCant;
                    if (esDirP1) {
                        const r = Math.round(resultado * 2) / 2;
                        celCantidad = r % 1 === 0 ? r.toString() : r.toFixed(1);
                    } else {
                        celCantidad = resultado % 1 === 0
                            ? resultado.toString()
                            : parseFloat(resultado.toFixed(4)).toString();
                    }
                    if (factor !== 1 && ir.factor_conversion != null) {
                        const exacto = parseFloat(resultado.toFixed(4));
                        celCantidad = `<span title="${ingr.Cantidad} × ${factor} ÷ ${ppCant} = ${exacto}">${celCantidad}</span>`;
                    }
                }
            }

            // ── Presentación Uso ─────────────────────────────────────
            let celPresentacion;
            if (np) {
                const inacTag = np.activoNuevo === 'NO'
                    ? `<span class="tag-inactivo ms-1">INACTIVO</span>` : '';
                const autoTag = ingr.metodo_resolucion === 'maestro'
                    ? `<span style="font-size:.62rem;background:#e8f5e9;color:#2e7d32;border-radius:3px;padding:1px 5px;margin-left:4px">AUTO</span>` : '';
                const npRecetaTag = escenario === 'receta_global' ? recetaTag : '';
                let varHTML = '';
                if (np.variedades && np.variedades.length > 0) {
                    varHTML = `<div style="font-size:.72rem;color:#aaa;margin-top:2px">
                        ${np.variedades.map(v => `<span style="margin-right:5px">${esc(v.nombre)}${v.es_principal==1?' ★':''}</span>`).join('')}
                    </div>`;
                }
                celPresentacion = `<div class="cel-insumo">
                    <div class="nom-erp">${esc(np.NombreNuevo)}${inacTag}${autoTag}${npRecetaTag}</div>
                    <div class="uni-erp">${esc(np.unidadNueva || '')}${np.cantidad ? ' · ' + np.cantidad : ''}${np.productoMaestro ? ' — ' + esc(np.productoMaestro) : ''}</div>
                    ${varHTML}
                </div>`;
            } else if (ingr.cotizacion) {
                celPresentacion = `<span class="cel-na"><i class="fas fa-exclamation-triangle me-1 text-warning"></i>Sin mapeo</span>`;
            } else {
                celPresentacion = `<span class="cel-error"><i class="fas fa-times-circle me-1"></i>No resuelto</span>`;
            }

            return `<tr class="${filaClass}">
                <td class="text-center">
                    <span class="orden-num">${orden}</span>
                </td>
                <td class="text-center">${tipoBadge}</td>
                <td class="td-sep-left">${celInsumo}</td>
                <td class="text-center fw-bold" style="font-size:.9rem">${celCantidad}</td>
                <td>${celPresentacion}</td>
            </tr>`;
        }).join('');

        show('panelTabla');
    }

    // ── Reset contenido ───────────────────────────────────────────────
    function resetContenido() {
        hide('cardProducto');
        hide('barraResumen');
        hide('panelTabla');
        hide('panelVacio');
        document.getElementById('spinnerReceta').style.display = 'none';
        document.getElementById('tbodyReceta').innerHTML = '';
        // No ocultar versiones aquí, se gestiona en seleccionarProducto
    }

    // ── Búsqueda en menú ─────────────────────────────────────────────
    document.getElementById('inputBuscar').addEventListener('input', function() {
        const q = this.value.trim().toLowerCase();
        if (!q) { renderMenu(todosGrupos); return; }

        // Filtrar árbol
        const filtrado = todosGrupos.map(g => ({
            ...g,
            productos: g.productos.filter(p => p.Nombre.toLowerCase().includes(q))
        })).filter(g => g.productos.length > 0);

        renderMenu(filtrado);

        // Abrir todos los grupos con resultados
        filtrado.forEach(g => {
            const lista = document.getElementById(`grupo-${g.CodGrupo}`);
            const btn   = document.querySelector(`[onclick="toggleGrupo('${g.CodGrupo}')"]`);
            if (lista) lista.classList.add('open');
            if (btn)   btn.classList.add('active');
        });
    });

    // ── Init ──────────────────────────────────────────────────────────
    iniciarMenu();

    </script>
</body>
</html>
