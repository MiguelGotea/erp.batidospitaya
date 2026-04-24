// ══════════════════════════════════════════════════════════
//  Monitor de Conexión — Pitaya Systems
// ══════════════════════════════════════════════════════════
const POLL_INTERVAL = 60000; // 1 minuto
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
    const totalEl = document.getElementById('kpi-total');
    const onlineEl = document.getElementById('kpi-online');
    const alertaEl = document.getElementById('kpi-alerta');
    const offlineEl = document.getElementById('kpi-offline');

    if (totalEl) totalEl.textContent = r.total ?? 0;
    if (onlineEl) onlineEl.textContent = r.online ?? 0;
    if (alertaEl) alertaEl.textContent = r.alerta ?? 0;
    if (offlineEl) offlineEl.textContent = r.offline ?? 0;
}

// ── Render PC Cards ───────────────────────────────────────
function renderPCs() {
    const container = document.getElementById('pc-container');
    if (!container) return;

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
            <div class="pc-sucursal" title="${pc.nombre_sucursal || pc.sucursal_codigo}"><i class="fas fa-store" style="margin-right:4px"></i>${pc.nombre_sucursal || pc.sucursal_codigo}</div>
            <div class="pc-nombre">${pc.pc_nombre || '(Sin nombre)'}</div>
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
    if (!ul) return;

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
        const lastUpdateEl = document.getElementById('last-update');
        if (lastUpdateEl) {
            lastUpdateEl.textContent = 'Actualizado: ' + now.toLocaleTimeString('es-NI');
        }

    } catch (err) {
        console.error('[Monitor]', err);
        const lastUpdateEl = document.getElementById('last-update');
        if (lastUpdateEl) {
            lastUpdateEl.textContent = '⚠ Error al cargar datos';
        }
    }
}

// ── Inicializar Eventos ────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Filtros
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeFilter = btn.dataset.filter;
            renderPCs();
        });
    });

    // Búsqueda
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', e => {
            searchTerm = e.target.value;
            renderPCs();
        });
    }

    // Iniciar polling
    fetchData();
    setInterval(fetchData, POLL_INTERVAL);
});
