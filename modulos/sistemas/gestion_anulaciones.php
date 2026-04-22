<?php
/**
 * erp/modulos/sistemas/gestion_anulaciones.php
 * Herramienta web para gestionar solicitudes de anulación de pedidos.
 */
require_once __DIR__ . '/../../../api.batidospitaya.com/core/database/conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Anulaciones · Pitaya ERP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #0d0f14;
  --surface: #161a23;
  --surface2: #1e2330;
  --border: #2a3045;
  --accent: #f97316;
  --accent-dim: rgba(249,115,22,.15);
  --green: #22c55e;
  --green-dim: rgba(34,197,94,.15);
  --red: #ef4444;
  --red-dim: rgba(239,68,68,.15);
  --yellow: #eab308;
  --yellow-dim: rgba(234,179,8,.15);
  --blue: #3b82f6;
  --blue-dim: rgba(59,130,246,.15);
  --text: #e2e8f0;
  --text-muted: #64748b;
  --radius: 10px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* ── Layout ── */
.header{background:var(--surface);border-bottom:1px solid var(--border);padding:16px 28px;
  display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:100}
.header-icon{width:40px;height:40px;background:var(--accent-dim);border-radius:10px;
  display:flex;align-items:center;justify-content:center;font-size:20px}
.header h1{font-size:18px;font-weight:600;color:var(--text)}
.header p{font-size:13px;color:var(--text-muted)}
.header-right{margin-left:auto;display:flex;gap:10px;align-items:center}

.container{padding:24px 28px;max-width:1400px;margin:0 auto}

/* ── Stats ── */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
  padding:18px 20px;transition:border-color .2s}
.stat-card:hover{border-color:var(--accent)}
.stat-val{font-size:28px;font-weight:700;margin-bottom:4px}
.stat-lbl{font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em}

/* ── Filters ── */
.filters{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
  padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.filter-group{display:flex;flex-direction:column;gap:6px}
.filter-group label{font-size:12px;color:var(--text-muted);font-weight:500}
.filter-group select, .filter-group input{background:var(--surface2);border:1px solid var(--border);
  color:var(--text);border-radius:7px;padding:8px 12px;font-size:13px;font-family:inherit;outline:none}
.filter-group select:focus,.filter-group input:focus{border-color:var(--accent)}
.btn{padding:8px 18px;border-radius:7px;border:none;font-family:inherit;font-size:13px;
  font-weight:500;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:6px}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{background:#ea6c0a;transform:translateY(-1px)}
.btn-ghost{background:var(--surface2);color:var(--text);border:1px solid var(--border)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-sm{padding:5px 12px;font-size:12px}
.btn-approve{background:var(--green-dim);color:var(--green);border:1px solid rgba(34,197,94,.3)}
.btn-approve:hover{background:var(--green);color:#fff}
.btn-reject{background:var(--red-dim);color:var(--red);border:1px solid rgba(239,68,68,.3)}
.btn-reject:hover{background:var(--red);color:#fff}

/* ── Table ── */
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.table-header{padding:14px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between}
.table-header span{font-size:13px;color:var(--text-muted)}
table{width:100%;border-collapse:collapse}
thead tr{background:var(--surface2)}
th{padding:11px 14px;text-align:left;font-size:11px;font-weight:600;
  text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);
  border-bottom:1px solid var(--border)}
td{padding:12px 14px;font-size:13px;border-bottom:1px solid rgba(42,48,69,.5)}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.02)}
.cod-pedido{font-weight:600;color:var(--accent)}
.suc-badge{background:var(--blue-dim);color:var(--blue);border-radius:5px;
  padding:3px 8px;font-size:11px;font-weight:600}

/* ── Status badges ── */
.badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:5px}
.badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}
.badge-pending{background:var(--yellow-dim);color:var(--yellow)}
.badge-approved{background:var(--green-dim);color:var(--green)}
.badge-rejected{background:var(--red-dim);color:var(--red)}
.badge-done{background:var(--blue-dim);color:var(--blue)}

/* ── Modal ── */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);
  z-index:200;display:none;align-items:center;justify-content:center}
.overlay.active{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:14px;
  padding:28px;width:100%;max-width:500px;animation:slideUp .2s ease}
@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.modal h2{font-size:17px;font-weight:600;margin-bottom:6px}
.modal p{font-size:13px;color:var(--text-muted);margin-bottom:20px}
.modal label{display:block;font-size:12px;color:var(--text-muted);font-weight:500;margin-bottom:6px}
.modal textarea{width:100%;background:var(--surface2);border:1px solid var(--border);
  color:var(--text);border-radius:8px;padding:10px 14px;font-family:inherit;font-size:13px;
  resize:vertical;min-height:90px;outline:none;margin-bottom:18px}
.modal textarea:focus{border-color:var(--accent)}
.modal-actions{display:flex;gap:10px;justify-content:flex-end}
.modal-info{background:var(--surface2);border-radius:8px;padding:12px 14px;margin-bottom:18px;font-size:13px}
.modal-info strong{color:var(--accent)}

/* ── Pagination ── */
.pagination{display:flex;gap:6px;align-items:center;justify-content:center;margin-top:20px}
.page-btn{padding:6px 12px;border-radius:6px;border:1px solid var(--border);background:var(--surface);
  color:var(--text);cursor:pointer;font-size:13px;transition:all .2s}
.page-btn:hover,.page-btn.active{background:var(--accent);border-color:var(--accent);color:#fff}
.page-btn:disabled{opacity:.4;cursor:default}

/* ── Empty ── */
.empty{padding:60px 20px;text-align:center;color:var(--text-muted)}
.empty-icon{font-size:48px;margin-bottom:14px;opacity:.4}

/* ── Toast ── */
.toast-container{position:fixed;bottom:24px;right:24px;display:flex;flex-direction:column;gap:8px;z-index:999}
.toast{padding:12px 18px;border-radius:8px;font-size:13px;font-weight:500;
  animation:slideIn .3s ease;display:flex;align-items:center;gap:8px;min-width:260px}
@keyframes slideIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
.toast-ok{background:#166534;color:#bbf7d0;border:1px solid #15803d}
.toast-err{background:#7f1d1d;color:#fecaca;border:1px solid #b91c1c}

/* ── Loading ── */
.spinner{width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--accent);
  border-radius:50%;animation:spin .7s linear infinite;display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}
.loading-row td{text-align:center;padding:40px;color:var(--text-muted)}
</style>
</head>
<body>

<div class="header">
  <div class="header-icon">🚫</div>
  <div>
    <h1>Gestión de Anulaciones</h1>
    <p>Solicitudes de anulación de pedidos por sucursal</p>
  </div>
  <div class="header-right">
    <div id="autoRefreshStatus" style="font-size:12px;color:var(--text-muted)">⟳ Auto-refresh: <span id="countdown">60</span>s</div>
    <button class="btn btn-ghost" onclick="cargarDatos()">⟳ Actualizar</button>
  </div>
</div>

<div class="container">
  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-val" id="statTotal" style="color:var(--text)">—</div>
      <div class="stat-lbl">Total solicitudes</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" id="statPendientes" style="color:var(--yellow)">—</div>
      <div class="stat-lbl">Pendientes</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" id="statAprobadas" style="color:var(--green)">—</div>
      <div class="stat-lbl">Aprobadas</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" id="statEjecutadas" style="color:var(--blue)">—</div>
      <div class="stat-lbl">Ejecutadas en tienda</div>
    </div>
  </div>

  <!-- Filters -->
  <div class="filters">
    <div class="filter-group">
      <label>Status</label>
      <select id="filtStatus">
        <option value="-1">Todos</option>
        <option value="0" selected>Pendientes</option>
        <option value="1">Aprobados</option>
        <option value="2">Rechazados</option>
      </select>
    </div>
    <div class="filter-group">
      <label>Sucursal</label>
      <select id="filtSucursal">
        <option value="0">Todas</option>
      </select>
    </div>
    <div class="filter-group">
      <label>Buscar</label>
      <input type="text" id="filtBuscar" placeholder="CodPedido o motivo..." style="width:220px">
    </div>
    <button class="btn btn-primary" onclick="cargarDatos(1)">Buscar</button>
    <button class="btn btn-ghost" onclick="limpiarFiltros()">Limpiar</button>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <div class="table-header">
      <span id="tableInfo">Cargando...</span>
    </div>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Pedido</th>
          <th>Sucursal</th>
          <th>Solicitado</th>
          <th>Status</th>
          <th>Modalidad</th>
          <th>Motivo</th>
          <th>Comentario aprobación</th>
          <th>Aprobado por</th>
          <th>Tienda</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <tr class="loading-row"><td colspan="11"><div class="spinner"></div></td></tr>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div class="pagination" id="pagination"></div>
</div>

<!-- Modal Aprobación / Rechazo -->
<div class="overlay" id="modalOverlay">
  <div class="modal">
    <h2 id="modalTitle">Aprobar Solicitud</h2>
    <p id="modalSubtitle">Confirma la acción y agrega un comentario.</p>
    <div class="modal-info" id="modalInfo"></div>
    <label>Comentario <span style="color:var(--text-muted)">(opcional)</span></label>
    <textarea id="modalComentario" placeholder="Motivo de la decisión..."></textarea>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
      <button class="btn btn-primary" id="modalConfirm" onclick="ejecutarAccion()">Confirmar</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const AJAX_GET     = 'ajax/anulaciones_get.php';
const AJAX_APROBAR = 'ajax/anulaciones_aprobar.php';

let currentPage    = 1;
let totalPages     = 1;
let pendingAction  = null; // { id, accion, codPedido, sucursal }
let countdownVal   = 60;
let countdownTimer = null;

// ── Bootstrap ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  cargarStats();
  cargarDatos(1);
  iniciarAutoRefresh();

  document.getElementById('filtBuscar').addEventListener('keydown', e => {
    if (e.key === 'Enter') cargarDatos(1);
  });
});

// ── Stats ──────────────────────────────────────────────────
async function cargarStats() {
  try {
    const r = await fetch(AJAX_GET + '?status=-1&limit=1');
    const d = await r.json();
    document.getElementById('statTotal').textContent = d.total ?? '—';

    const rP = await fetch(AJAX_GET + '?status=0&limit=1');
    const dP = await rP.json();
    document.getElementById('statPendientes').textContent = dP.total ?? '—';

    const rA = await fetch(AJAX_GET + '?status=1&limit=1');
    const dA = await rA.json();
    document.getElementById('statAprobadas').textContent = dA.total ?? '—';

    // Ejecutadas: aprobadas con EjecutadoEnTienda=1 (filtro aproximado)
    // Para simplicidad contamos desde la data ya cargada
    const rE = await fetch(AJAX_GET + '?status=1&limit=500');
    const dE = await rE.json();
    const ejecutadas = (dE.registros || []).filter(r => parseInt(r.EjecutadoEnTienda) === 1).length;
    document.getElementById('statEjecutadas').textContent = ejecutadas;
  } catch(e) { console.warn('Stats error', e); }
}

// ── Listado ────────────────────────────────────────────────
async function cargarDatos(page = currentPage) {
  currentPage = page;
  const status   = document.getElementById('filtStatus').value;
  const sucursal = document.getElementById('filtSucursal').value;
  const buscar   = encodeURIComponent(document.getElementById('filtBuscar').value.trim());

  const url = `${AJAX_GET}?status=${status}&sucursal=${sucursal}&buscar=${buscar}&page=${page}&limit=30`;

  document.getElementById('tableBody').innerHTML =
    '<tr class="loading-row"><td colspan="11"><div class="spinner"></div></td></tr>';
  document.getElementById('tableInfo').textContent = 'Cargando...';

  try {
    const resp = await fetch(url);
    const data = await resp.json();

    if (!data.success) throw new Error(data.error);

    totalPages = data.paginas || 1;
    document.getElementById('tableInfo').textContent =
      `Mostrando ${data.registros.length} de ${data.total} registros (página ${page}/${totalPages})`;

    renderTabla(data.registros);
    renderPaginacion(data.total, page, 30);
    poblarSucursales(data.registros);

  } catch(e) {
    document.getElementById('tableBody').innerHTML =
      `<tr class="loading-row"><td colspan="11">❌ Error: ${e.message}</td></tr>`;
    toast('Error al cargar datos: ' + e.message, 'err');
  }
}

// ── Render tabla ───────────────────────────────────────────
function renderTabla(registros) {
  const tbody = document.getElementById('tableBody');
  if (!registros.length) {
    tbody.innerHTML = `<tr><td colspan="11" class="empty">
      <div class="empty-icon">📋</div>
      <div>No hay solicitudes con los filtros actuales</div></td></tr>`;
    return;
  }

  const modalidades = { 1: 'Anulado en tienda', 2: 'Por Telegram/Web', 0: 'Desconocida' };

  tbody.innerHTML = registros.map(r => {
    const badge   = statusBadge(r.Status, r.EjecutadoEnTienda);
    const modal   = modalidades[r.Modalidad] || r.Modalidad;
    const motivo  = r.Motivo ? escapeHtml(r.Motivo).substring(0, 60) : '<em style="color:var(--text-muted)">—</em>';
    const coment  = r.ComentarioAprobacion ? escapeHtml(r.ComentarioAprobacion).substring(0, 50) : '—';
    const aprobPor = r.AprobadoPor || '—';
    const solicit = r.HoraSolicitada ? r.HoraSolicitada.substring(0, 16).replace('T',' ') : '—';
    const ejecut  = parseInt(r.EjecutadoEnTienda) === 1
      ? `<span style="color:var(--green);font-size:11px">✔ ${(r.HoraEjecutadaTienda||'').substring(0,16)}</span>`
      : `<span style="color:var(--text-muted);font-size:11px">Pendiente</span>`;

    let acciones = '—';
    if (parseInt(r.Status) === 0) {
      acciones = `
        <button class="btn btn-sm btn-approve" onclick="abrirModal(${r.CodAnulacionHost},'aprobar',${r.CodPedido},${r.Sucursal})">✔ Aprobar</button>
        <button class="btn btn-sm btn-reject" onclick="abrirModal(${r.CodAnulacionHost},'rechazar',${r.CodPedido},${r.Sucursal})" style="margin-top:4px">✖ Rechazar</button>
      `;
    }

    return `<tr>
      <td style="color:var(--text-muted);font-size:11px">#${r.CodAnulacionHost}</td>
      <td><span class="cod-pedido">${r.CodPedido}</span></td>
      <td><span class="suc-badge">S${r.Sucursal}</span></td>
      <td style="font-size:12px">${solicit}</td>
      <td>${badge}</td>
      <td style="font-size:12px">${modal}</td>
      <td title="${escapeHtml(r.Motivo||'')}">${motivo}</td>
      <td style="font-size:12px">${coment}</td>
      <td style="font-size:12px">${aprobPor}</td>
      <td>${ejecut}</td>
      <td style="display:flex;flex-direction:column;gap:4px">${acciones}</td>
    </tr>`;
  }).join('');
}

function statusBadge(status, ejecutado) {
  const s = parseInt(status);
  const e = parseInt(ejecutado);
  if (s === 0) return '<span class="badge badge-pending">Pendiente</span>';
  if (s === 1 && e === 1) return '<span class="badge badge-done">Ejecutado</span>';
  if (s === 1) return '<span class="badge badge-approved">Aprobado</span>';
  if (s === 2) return '<span class="badge badge-rejected">Rechazado</span>';
  return `<span class="badge">${status}</span>`;
}

// ── Paginación ─────────────────────────────────────────────
function renderPaginacion(total, page, limit) {
  const pages = Math.ceil(total / limit);
  const el    = document.getElementById('pagination');
  if (pages <= 1) { el.innerHTML = ''; return; }

  let html = '';
  if (page > 1)  html += `<button class="page-btn" onclick="cargarDatos(${page-1})">‹ Anterior</button>`;

  const start = Math.max(1, page - 2);
  const end   = Math.min(pages, page + 2);
  for (let i = start; i <= end; i++) {
    html += `<button class="page-btn ${i===page?'active':''}" onclick="cargarDatos(${i})">${i}</button>`;
  }

  if (page < pages) html += `<button class="page-btn" onclick="cargarDatos(${page+1})">Siguiente ›</button>`;
  el.innerHTML = html;
}

// ── Sucursales ─────────────────────────────────────────────
const sucursalesVistas = new Set();
function poblarSucursales(registros) {
  const sel = document.getElementById('filtSucursal');
  registros.forEach(r => {
    if (!sucursalesVistas.has(r.Sucursal)) {
      sucursalesVistas.add(r.Sucursal);
      const opt = document.createElement('option');
      opt.value = r.Sucursal;
      opt.textContent = 'Sucursal ' + r.Sucursal;
      sel.appendChild(opt);
    }
  });
}

// ── Modal ──────────────────────────────────────────────────
function abrirModal(id, accion, codPedido, sucursal) {
  pendingAction = { id, accion, codPedido, sucursal };
  const isAprobar = accion === 'aprobar';
  document.getElementById('modalTitle').textContent    = isAprobar ? '✔ Aprobar Solicitud' : '✖ Rechazar Solicitud';
  document.getElementById('modalSubtitle').textContent = isAprobar
    ? 'La tienda ejecutará la anulación automáticamente al detectar esta aprobación.'
    : 'El pedido no será anulado. La tienda recibirá el rechazo en su próximo ciclo.';
  document.getElementById('modalInfo').innerHTML =
    `Pedido: <strong>#${codPedido}</strong> &nbsp;|&nbsp; Sucursal: <strong>${sucursal}</strong> &nbsp;|&nbsp; ID: <strong>#${id}</strong>`;
  document.getElementById('modalComentario').value = '';
  const btn = document.getElementById('modalConfirm');
  btn.textContent = isAprobar ? '✔ Confirmar Aprobación' : '✖ Confirmar Rechazo';
  btn.style.background = isAprobar ? 'var(--green)' : 'var(--red)';
  document.getElementById('modalOverlay').classList.add('active');
  document.getElementById('modalComentario').focus();
}

function cerrarModal() {
  document.getElementById('modalOverlay').classList.remove('active');
  pendingAction = null;
}

async function ejecutarAccion() {
  if (!pendingAction) return;
  const comentario = document.getElementById('modalComentario').value.trim();
  const btn = document.getElementById('modalConfirm');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner"></div>';

  try {
    const resp = await fetch(AJAX_APROBAR, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        cod_anulacion_host: pendingAction.id,
        accion: pendingAction.accion,
        comentario,
        aprobado_por: 'ERP Web'
      })
    });
    const data = await resp.json();

    if (data.success) {
      toast(data.message, 'ok');
      cerrarModal();
      cargarDatos(currentPage);
      cargarStats();
    } else {
      toast('Error: ' + data.error, 'err');
    }
  } catch(e) {
    toast('Error de red: ' + e.message, 'err');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Confirmar';
  }
}

// Cerrar modal al hacer click fuera
document.getElementById('modalOverlay').addEventListener('click', e => {
  if (e.target === e.currentTarget) cerrarModal();
});

// ── Auto-refresh ───────────────────────────────────────────
function iniciarAutoRefresh() {
  countdownVal = 60;
  clearInterval(countdownTimer);
  countdownTimer = setInterval(() => {
    countdownVal--;
    document.getElementById('countdown').textContent = countdownVal;
    if (countdownVal <= 0) {
      cargarDatos(currentPage);
      cargarStats();
      countdownVal = 60;
    }
  }, 1000);
}

// ── Helpers ────────────────────────────────────────────────
function limpiarFiltros() {
  document.getElementById('filtStatus').value   = '0';
  document.getElementById('filtSucursal').value = '0';
  document.getElementById('filtBuscar').value   = '';
  cargarDatos(1);
}

function escapeHtml(str) {
  return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toast(msg, tipo = 'ok') {
  const el = document.createElement('div');
  el.className = `toast toast-${tipo}`;
  el.textContent = (tipo === 'ok' ? '✔ ' : '✖ ') + msg;
  document.getElementById('toastContainer').appendChild(el);
  setTimeout(() => el.remove(), 4000);
}
</script>
</body>
</html>
