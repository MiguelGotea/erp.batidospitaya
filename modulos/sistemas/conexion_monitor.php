<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Conexión — Sistemas Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../core/assets/css/indexmodulos.css?v=<?php echo mt_rand(1,9999); ?>">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <style>
        :root {
            --online:  #10b981;
            --alerta:  #f59e0b;
            --offline: #ef4444;
            --bg:      #0f172a;
            --surface: #1e293b;
            --card:    #263146;
            --border:  #334155;
            --text:    #e2e8f0;
            --muted:   #94a3b8;
            --accent:  #51B8AC;
            --accent2: #0E544C;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', 'Calibri', sans-serif; }

        body { background: #F6F6F6; color: #333; }

        /* ── Panel wrapper ── */
        .monitor-wrap {
            padding: 18px 22px 30px;
        }

        .monitor-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .monitor-header h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--accent2);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .monitor-header h1 i { color: var(--accent); }

        .live-badge {
            display: flex;
            align-items: center;
            gap: 7px;
            background: #0E544C15;
            border: 1px solid var(--accent);
            color: var(--accent2);
            font-size: .78rem;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 99px;
        }
        .pulse-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--online);
            animation: pulse 1.6s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { opacity:1; transform:scale(1); }
            50%      { opacity:.4; transform:scale(1.4); }
        }

        /* ── KPI Cards ── */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .kpi-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            border-left: 4px solid #ddd;
            display: flex;
            flex-direction: column;
            gap: 4px;
            transition: transform .2s, box-shadow .2s;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
        .kpi-card.online  { border-color: var(--online); }
        .kpi-card.alerta  { border-color: var(--alerta); }
        .kpi-card.offline { border-color: var(--offline); }
        .kpi-card.total   { border-color: var(--accent); }

        .kpi-label { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
        .kpi-num   { font-size: 2.2rem; font-weight: 700; line-height: 1; }
        .kpi-card.online  .kpi-num { color: var(--online); }
        .kpi-card.alerta  .kpi-num { color: var(--alerta); }
        .kpi-card.offline .kpi-num { color: var(--offline); }
        .kpi-card.total   .kpi-num { color: var(--accent2); }
        .kpi-sub { font-size: .72rem; color: #94a3b8; }

        /* ── Main grid ── */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 900px) { .main-grid { grid-template-columns: 1fr; } }

        /* ── PC Cards grid ── */
        .section-title-sm {
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #64748b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
        }

        .pc-card {
            background: #fff;
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            border-top: 3px solid #ddd;
            position: relative;
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
            cursor: default;
        }
        .pc-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.13); }
        .pc-card.online  { border-top-color: var(--online); }
        .pc-card.alerta  { border-top-color: var(--alerta); }
        .pc-card.offline { border-top-color: var(--offline); }

        .pc-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 60px;
            opacity: .05;
        }
        .pc-card.online::before  { background: var(--online); }
        .pc-card.alerta::before  { background: var(--alerta); }
        .pc-card.offline::before { background: var(--offline); }

        .pc-status-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .pc-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .online  .pc-icon { background: #d1fae5; color: var(--online); }
        .alerta  .pc-icon { background: #fef3c7; color: var(--alerta); }
        .offline .pc-icon { background: #fee2e2; color: var(--offline); }

        .pc-badge {
            font-size: .66rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 99px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .online  .pc-badge { background: #d1fae5; color: #065f46; }
        .alerta  .pc-badge { background: #fef3c7; color: #92400e; }
        .offline .pc-badge { background: #fee2e2; color: #991b1b; }

        .pc-nombre    { font-size: .95rem; font-weight: 700; color: #1e293b; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pc-sucursal  { font-size: .72rem; color: var(--accent2); font-weight: 600; margin-bottom: 8px; }
        .pc-detail    { font-size: .7rem; color: #64748b; display: flex; align-items: center; gap: 5px; margin-bottom: 3px; }
        .pc-detail i  { width: 12px; text-align: center; color: #94a3b8; }
        .pc-time      { font-size: .68rem; font-weight: 600; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f5f9; }
        .online  .pc-time { color: var(--online); }
        .alerta  .pc-time { color: var(--alerta); }
        .offline .pc-time { color: var(--offline); }

        /* ── Sidebar actividad ── */
        .sidebar-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
        }
        .activity-list { list-style: none; display: flex; flex-direction: column; gap: 0; }
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 9px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .activity-item:last-child { border-bottom: none; }
        .act-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--online);
            margin-top: 4px;
            flex-shrink: 0;
        }
        .act-info { flex: 1; min-width: 0; }
        .act-pc   { font-size: .75rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .act-suc  { font-size: .68rem; color: var(--accent2); }
        .act-time { font-size: .65rem; color: #94a3b8; margin-top: 1px; }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 14px; display: block; opacity: .4; }
        .empty-state p { font-size: .85rem; }

        /* ── Toolbar ── */
        .toolbar {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .search-box {
            flex: 1;
            min-width: 180px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 8px 12px 8px 34px;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            font-size: .8rem;
            color: #334155;
            outline: none;
            transition: border-color .2s;
        }
        .search-box input:focus { border-color: var(--accent); }
        .search-box i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: .78rem; }

        .filter-btn {
            padding: 7px 14px;
            border-radius: 9px;
            font-size: .75rem;
            font-weight: 600;
            border: 1.5px solid #e2e8f0;
            background: #fff;
            cursor: pointer;
            transition: all .2s;
            color: #64748b;
        }
        .filter-btn:hover, .filter-btn.active { background: var(--accent2); color: #fff; border-color: var(--accent2); }
        .filter-btn.f-online:hover,  .filter-btn.f-online.active  { background: var(--online);  border-color: var(--online); }
        .filter-btn.f-alerta:hover,  .filter-btn.f-alerta.active  { background: var(--alerta);  border-color: var(--alerta); }
        .filter-btn.f-offline:hover, .filter-btn.f-offline.active { background: var(--offline); border-color: var(--offline); }

        .refresh-info { font-size: .72rem; color: #94a3b8; margin-left: auto; white-space: nowrap; }

        /* ── Loader ── */
        .skeleton-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
        }
        .skeleton-card {
            background: #fff;
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            height: 160px;
            animation: shimmer 1.4s ease-in-out infinite;
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 200% 100%;
        }
        @keyframes shimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
<?php echo renderMenuLateral($cargoOperario); ?>
<div class="main-container">
    <div class="contenedor-principal">
        <?php echo renderHeader($usuario, false); ?>

        <div class="monitor-wrap">

            <!-- Header -->
            <div class="monitor-header">
                <h1>
                    <i class="fas fa-satellite-dish"></i>
                    Monitor de Conexión — Sistemas Access
                </h1>
                <div class="live-badge">
                    <div class="pulse-dot"></div>
                    EN VIVO &bull; Actualiza cada 15s
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="kpi-row" id="kpi-row">
                <div class="kpi-card total">
                    <span class="kpi-label">Total PCs</span>
                    <span class="kpi-num" id="kpi-total">—</span>
                    <span class="kpi-sub">equipos registrados</span>
                </div>
                <div class="kpi-card online">
                    <span class="kpi-label">En línea</span>
                    <span class="kpi-num" id="kpi-online">—</span>
                    <span class="kpi-sub">ping &lt; 90 seg</span>
                </div>
                <div class="kpi-card alerta">
                    <span class="kpi-label">Alerta</span>
                    <span class="kpi-num" id="kpi-alerta">—</span>
                    <span class="kpi-sub">ping 90–300 seg</span>
                </div>
                <div class="kpi-card offline">
                    <span class="kpi-label">Sin conexión</span>
                    <span class="kpi-num" id="kpi-offline">—</span>
                    <span class="kpi-sub">ping &gt; 5 min</span>
                </div>
            </div>

            <!-- Main grid -->
            <div class="main-grid">

                <!-- LEFT: PC Grid -->
                <div>
                    <div class="section-title-sm">
                        <i class="fas fa-desktop"></i>
                        Estado de equipos
                    </div>

                    <!-- Toolbar -->
                    <div class="toolbar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="search-input" placeholder="Buscar PC o sucursal…">
                        </div>
                        <button class="filter-btn active" data-filter="all" id="btn-all">Todos</button>
                        <button class="filter-btn f-online"  data-filter="online"  id="btn-online">🟢 Online</button>
                        <button class="filter-btn f-alerta"  data-filter="alerta"  id="btn-alerta">🟡 Alerta</button>
                        <button class="filter-btn f-offline" data-filter="offline" id="btn-offline">🔴 Offline</button>
                        <span class="refresh-info" id="last-update">—</span>
                    </div>

                    <!-- PC Cards -->
                    <div id="pc-container">
                        <!-- Skeleton loader inicial -->
                        <div class="skeleton-grid" id="skeleton">
                            <?php for($i=0;$i<6;$i++): ?>
                            <div class="skeleton-card"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Actividad reciente -->
                <div>
                    <div class="section-title-sm">
                        <i class="fas fa-history"></i>
                        Actividad reciente
                    </div>
                    <div class="sidebar-card">
                        <ul class="activity-list" id="activity-list">
                            <li style="color:#94a3b8;font-size:.78rem;text-align:center;padding:20px 0;">Cargando…</li>
                        </ul>
                    </div>
                </div>

            </div><!-- /main-grid -->
        </div><!-- /monitor-wrap -->
    </div>
</div>

<script>
// ══════════════════════════════════════════════════════════
//  Monitor de Conexión — Pitaya Systems
// ══════════════════════════════════════════════════════════
const POLL_INTERVAL = 15000; // 15 segundos
const AJAX_URL      = 'ajax/conexion_monitor_get.php';

let allPCs        = [];
let activeFilter  = 'all';
let searchTerm    = '';

// ── Utilidades ────────────────────────────────────────────
function formatSegundos(seg) {
    seg = parseInt(seg);
    if (seg < 60)  return seg + ' seg';
    if (seg < 3600) return Math.floor(seg/60) + ' min ' + (seg%60) + ' seg';
    return Math.floor(seg/3600) + 'h ' + Math.floor((seg%3600)/60) + 'min';
}

function formatHora(dt) {
    if (!dt) return '—';
    const d = new Date(dt.replace(' ', 'T'));
    return d.toLocaleTimeString('es-NI', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
}

function estadoLabel(e) {
    if (e === 'online')  return 'En línea';
    if (e === 'alerta')  return 'Alerta';
    return 'Sin conexión';
}

function estadoIcon(e) {
    if (e === 'online')  return 'fa-circle-check';
    if (e === 'alerta')  return 'fa-triangle-exclamation';
    return 'fa-circle-xmark';
}

// ── Render KPIs ───────────────────────────────────────────
function renderKPIs(r) {
    document.getElementById('kpi-total').textContent   = r.total   ?? 0;
    document.getElementById('kpi-online').textContent  = r.online  ?? 0;
    document.getElementById('kpi-alerta').textContent  = r.alerta  ?? 0;
    document.getElementById('kpi-offline').textContent = r.offline ?? 0;
}

// ── Render PC Cards ───────────────────────────────────────
function renderPCs() {
    const container = document.getElementById('pc-container');
    document.getElementById('skeleton')?.remove();

    let filtered = allPCs.filter(pc => {
        const matchFilter = activeFilter === 'all' || pc.estado === activeFilter;
        const q = searchTerm.toLowerCase();
        const matchSearch = !q ||
            (pc.pc_nombre || '').toLowerCase().includes(q) ||
            (pc.nombre_sucursal || '').toLowerCase().includes(q) ||
            (pc.sucursal_codigo || '').toLowerCase().includes(q);
        return matchFilter && matchSearch;
    });

    if (filtered.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-satellite-dish"></i>
                <p>No hay equipos que coincidan con los filtros.</p>
            </div>`;
        return;
    }

    const html = filtered.map(pc => {
        const seg = parseInt(pc.segundos_sin_ping) || 0;
        let tiempoTxt = '';
        if (pc.estado === 'online')  tiempoTxt = '✓ Hace ' + formatSegundos(seg);
        else if (pc.estado === 'alerta') tiempoTxt = '⚠ Sin ping hace ' + formatSegundos(seg);
        else tiempoTxt = '✗ Desconectado hace ' + formatSegundos(seg);

        return `
        <div class="pc-card ${pc.estado}">
            <div class="pc-status-row">
                <div class="pc-icon"><i class="fas ${estadoIcon(pc.estado)}"></i></div>
                <span class="pc-badge">${estadoLabel(pc.estado)}</span>
            </div>
            <div class="pc-nombre" title="${pc.pc_nombre || '—'}">${pc.pc_nombre || '(Sin nombre)'}</div>
            <div class="pc-sucursal"><i class="fas fa-store" style="margin-right:4px"></i>${pc.nombre_sucursal || pc.sucursal_codigo}</div>
            ${pc.pc_usuario ? `<div class="pc-detail"><i class="fas fa-user"></i>${pc.pc_usuario}</div>` : ''}
            ${pc.ip_local   ? `<div class="pc-detail"><i class="fas fa-network-wired"></i>${pc.ip_local}</div>` : ''}
            ${pc.ip_publica ? `<div class="pc-detail"><i class="fas fa-globe"></i>${pc.ip_publica}</div>` : ''}
            ${pc.modulo_activo ? `<div class="pc-detail"><i class="fas fa-th-large"></i>${pc.modulo_activo}</div>` : ''}
            <div class="pc-time">${tiempoTxt}</div>
        </div>`;
    }).join('');

    container.innerHTML = `<div class="pc-grid">${html}</div>`;
}

// ── Render Actividad ──────────────────────────────────────
function renderActividad(items) {
    const ul = document.getElementById('activity-list');
    if (!items || items.length === 0) {
        ul.innerHTML = '<li style="color:#94a3b8;font-size:.78rem;text-align:center;padding:20px 0;">Sin actividad aún</li>';
        return;
    }
    ul.innerHTML = items.map(a => `
        <li class="activity-item">
            <div class="act-dot"></div>
            <div class="act-info">
                <div class="act-pc">${a.pc_nombre || '(Sin nombre)'}</div>
                <div class="act-suc">${a.nombre_sucursal || a.sucursal_codigo}</div>
                <div class="act-time">${formatHora(a.ping_at)}</div>
            </div>
        </li>`).join('');
}

// ── Fetch datos ───────────────────────────────────────────
async function fetchData() {
    try {
        const res  = await fetch(AJAX_URL + '?t=' + Date.now());
        const data = await res.json();

        if (!data.success) throw new Error(data.message || 'Error desconocido');

        allPCs = data.pcs || [];
        renderKPIs(data.resumen || {});
        renderPCs();
        renderActividad(data.actividad || []);

        const now = new Date();
        document.getElementById('last-update').textContent =
            'Actualizado: ' + now.toLocaleTimeString('es-NI');

    } catch (err) {
        console.error('[Monitor]', err);
        document.getElementById('last-update').textContent = '⚠ Error al cargar datos';
    }
}

// ── Filtros ───────────────────────────────────────────────
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeFilter = btn.dataset.filter;
        renderPCs();
    });
});

// ── Búsqueda ──────────────────────────────────────────────
document.getElementById('search-input').addEventListener('input', e => {
    searchTerm = e.target.value;
    renderPCs();
});

// ── Iniciar ───────────────────────────────────────────────
fetchData();
setInterval(fetchData, POLL_INTERVAL);
</script>
</body>
</html>
