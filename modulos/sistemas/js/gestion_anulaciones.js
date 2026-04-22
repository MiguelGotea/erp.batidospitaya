/**
 * gestion_anulaciones.js
 * Lógica principal de la herramienta de Aprobación de Anulaciones.
 */

'use strict';

// ── Estado global ───────────────────────────────────────────
const AJAX_GET      = 'ajax/anulaciones_get.php';
const AJAX_APROBAR  = 'ajax/anulaciones_aprobar.php';
const AJAX_DETALLE  = 'ajax/anulaciones_detalle_pedido.php';
const AJAX_NUEVA    = 'ajax/anulaciones_nueva_web.php';

let currentPage        = 1;
let totalPages         = 1;
let pendingDecision    = null;   // { id, codPedido, codCambio, sucursal }
let countdownVal       = 60;
let countdownTimer     = null;
let sucursalesPopuladas = new Set();

// ── Bootstrap ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cargarStats();
    cargarDatos(1);
    iniciarAutoRefresh();
    poblarSucursalesFiltro();

    document.getElementById('filtBuscar').addEventListener('keydown', e => {
        if (e.key === 'Enter') cargarDatos(1);
    });
});

// ── Stats ────────────────────────────────────────────────────
async function cargarStats() {
    try {
        const [rAll, rPend, rApro] = await Promise.all([
            fetch(AJAX_GET + '?status=-1&limit=1').then(r => r.json()),
            fetch(AJAX_GET + '?status=0&limit=1').then(r => r.json()),
            fetch(AJAX_GET + '?status=1&limit=1').then(r => r.json()),
        ]);
        document.getElementById('statTotal').textContent      = rAll.total  ?? '—';
        document.getElementById('statPendientes').textContent  = rPend.total ?? '—';
        document.getElementById('statAprobadas').textContent   = rApro.total ?? '—';

        // Ejecutadas: aprobadas con EjecutadoEnTienda=1
        const rEje = await fetch(AJAX_GET + '?status=1&limit=500').then(r => r.json());
        const eje = (rEje.registros || []).filter(r => parseInt(r.EjecutadoEnTienda) === 1).length;
        document.getElementById('statEjecutadas').textContent = eje;
    } catch (e) {
        console.warn('Stats error', e);
    }
}

// ── Listado ──────────────────────────────────────────────────
async function cargarDatos(page = currentPage) {
    currentPage = page;
    const status   = document.getElementById('filtStatus').value;
    const sucursal = document.getElementById('filtSucursal').value;
    const buscar   = encodeURIComponent(document.getElementById('filtBuscar').value.trim());
    const limit    = document.getElementById('registrosPorPagina').value;

    const url = `${AJAX_GET}?status=${status}&sucursal=${sucursal}&buscar=${buscar}&page=${page}&limit=${limit}`;

    document.getElementById('tableBody').innerHTML =
        '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></td></tr>';
    document.getElementById('tableInfo').textContent = 'Cargando...';

    try {
        const data = await fetch(url).then(r => r.json());
        if (!data.success) throw new Error(data.error);

        totalPages = data.paginas || 1;
        document.getElementById('tableInfo').textContent =
            `${data.registros.length} de ${data.total} registros (pág. ${page}/${totalPages})`;

        renderTabla(data.registros);
        renderPaginacion(data.total, page, parseInt(limit));
        poblarSucursalesTabla(data.registros);
    } catch (e) {
        document.getElementById('tableBody').innerHTML =
            `<tr><td colspan="9" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${e.message}</td></tr>`;
    }
}

// ── Render tabla ─────────────────────────────────────────────
function renderTabla(registros) {
    const tbody = document.getElementById('tableBody');
    if (!registros.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-2 opacity-25 d-block mb-2"></i>
            No hay solicitudes con los filtros actuales.</td></tr>`;
        return;
    }

    tbody.innerHTML = registros.map(r => {
        const badge    = statusBadge(r.Status, r.EjecutadoEnTienda);
        const solicit  = r.HoraSolicitada ? r.HoraSolicitada.substring(0, 16).replace('T', ' ') : '—';
        const motivo   = r.Motivo ? escHtml(r.Motivo).substring(0, 50) : '<em class="text-muted">—</em>';
        const aprobPor = r.AprobadoPor || '—';
        const ejecut   = parseInt(r.EjecutadoEnTienda) === 1
            ? `<span class="text-success small"><i class="bi bi-check-circle-fill"></i> ${(r.HoraEjecutadaTienda || '').substring(0, 16)}</span>`
            : `<span class="text-muted small">Pendiente</span>`;

        let acciones = `
            <button class="btn-accion btn-ver me-1" title="Ver detalle / decidir"
                    onclick="abrirModalDecision(${r.CodAnulacionHost},${r.CodPedido},${r.CodPedidoCambio || 0},${r.Sucursal})">
                <i class="bi bi-eye"></i>
            </button>`;

        if (PUEDE_APROBAR && parseInt(r.Status) === 0) {
            acciones += `
            <button class="btn-accion btn-aprobar me-1" title="Aprobar"
                    onclick="accionRapida(${r.CodAnulacionHost},'aprobar')">
                <i class="bi bi-check-lg"></i>
            </button>
            <button class="btn-accion btn-rechazar" title="Rechazar"
                    onclick="accionRapida(${r.CodAnulacionHost},'rechazar')">
                <i class="bi bi-x-lg"></i>
            </button>`;
        }

        return `<tr>
            <td class="text-muted px-3" style="font-size:11px">#${r.CodAnulacionHost}</td>
            <td><strong style="color:#dc3545">${r.CodPedido}</strong>
                ${r.CodPedidoCambio ? `<br><span class="text-primary small">↔ ${r.CodPedidoCambio}</span>` : ''}
            </td>
            <td><span class="badge" style="background:#e8f5f3;color:#0E544C;font-size:11px">S${r.Sucursal}</span></td>
            <td style="font-size:12px">${solicit}</td>
            <td>${badge}</td>
            <td title="${escHtml(r.Motivo || '')}" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${motivo}</td>
            <td style="font-size:12px">${aprobPor}</td>
            <td>${ejecut}</td>
            <td class="text-center">${acciones}</td>
        </tr>`;
    }).join('');
}

function statusBadge(status, ejecutado) {
    const s = parseInt(status);
    const e = parseInt(ejecutado);
    if (s === 0) return '<span class="badge-an badge-pending">Pendiente</span>';
    if (s === 1 && e === 1) return '<span class="badge-an badge-done">Ejecutado</span>';
    if (s === 1) return '<span class="badge-an badge-approved">Aprobado</span>';
    if (s === 2) return '<span class="badge-an badge-rejected">Rechazado</span>';
    return `<span class="badge-an bg-secondary text-white">${status}</span>`;
}

// ── Paginación ───────────────────────────────────────────────
function renderPaginacion(total, page, limit) {
    const pages = Math.ceil(total / limit);
    const el    = document.getElementById('paginacion');
    if (pages <= 1) { el.innerHTML = ''; return; }

    let html = '';
    if (page > 1) html += `<button class="page-btn me-1" onclick="cargarDatos(${page-1})">‹</button>`;
    const from = Math.max(1, page - 2);
    const to   = Math.min(pages, page + 2);
    for (let i = from; i <= to; i++) {
        html += `<button class="page-btn me-1 ${i === page ? 'active' : ''}" onclick="cargarDatos(${i})">${i}</button>`;
    }
    if (page < pages) html += `<button class="page-btn" onclick="cargarDatos(${page+1})">›</button>`;
    el.innerHTML = html;
}

// ── Sucursales ───────────────────────────────────────────────
async function poblarSucursalesFiltro() {
    try {
        const data = await fetch(AJAX_GET + '?status=-1&limit=500').then(r => r.json());
        poblarSucursalesTabla(data.registros || []);
        if (typeof PUEDE_APROBAR !== 'undefined' && PUEDE_APROBAR) {
            const sel = document.getElementById('new_sucursal');
            if (sel) {
                (data.registros || []).forEach(r => {
                    if (!sucursalesPopuladas.has(r.Sucursal)) {
                        sucursalesPopuladas.add(r.Sucursal);
                        const opt = document.createElement('option');
                        opt.value = r.Sucursal;
                        opt.textContent = 'Sucursal ' + r.Sucursal;
                        sel.appendChild(opt);
                    }
                });
            }
        }
    } catch (e) { /* silencioso */ }
}

function poblarSucursalesTabla(registros) {
    const sel = document.getElementById('filtSucursal');
    registros.forEach(r => {
        if (!sucursalesPopuladas.has('f' + r.Sucursal)) {
            sucursalesPopuladas.add('f' + r.Sucursal);
            const opt = document.createElement('option');
            opt.value = r.Sucursal;
            opt.textContent = 'Sucursal ' + r.Sucursal;
            sel.appendChild(opt);
        }
    });
}

// ── Modal Decisión (Ver / Aprobar / Rechazar) ────────────────
async function abrirModalDecision(id, codPedido, codCambio, sucursal) {
    pendingDecision = { id, codPedido, codCambio, sucursal };

    document.getElementById('dec_codPedido').textContent = codPedido;
    document.getElementById('dec_codCambio').textContent = codCambio > 0 ? codCambio : '—';
    document.getElementById('dec_sucursal').textContent  = 'S' + sucursal;
    document.getElementById('dec_motivo').textContent    = '...';

    // Mostrar/ocultar tab cambio
    document.getElementById('tabCambioItem').style.display = codCambio > 0 ? '' : 'none';

    // Cargar motivo desde la fila (ya tenemos el dato en la tabla)
    const filas = document.querySelectorAll('#tableBody tr');
    filas.forEach(tr => {
        const tdPedido = tr.querySelector('td:nth-child(2) strong');
        if (tdPedido && tdPedido.textContent == codPedido) {
            const motivoEl = tr.querySelector('td:nth-child(6)');
            if (motivoEl) document.getElementById('dec_motivo').textContent = motivoEl.title || motivoEl.textContent;
        }
    });

    if (document.getElementById('dec_comentario')) {
        document.getElementById('dec_comentario').value = '';
    }

    // Activar tab pedido
    setActiveTab('pedido');
    mostrarDetallePlaceholder();

    const modal = new bootstrap.Modal(document.getElementById('modalDecision'));
    modal.show();

    // Cargar detalle del pedido principal
    await cargarDetallePedido(codPedido, sucursal, 'pedido');
}

function setActiveTab(tipo) {
    document.getElementById('tab-pedido-link').classList.toggle('active', tipo === 'pedido');
    const cambioLink = document.getElementById('tab-cambio-link');
    if (cambioLink) cambioLink.classList.toggle('active', tipo === 'cambio');
}

function mostrarDetallePlaceholder() {
    document.getElementById('detallePlaceholder').style.display = '';
    document.getElementById('detalleContenido').style.display   = 'none';
}

async function verDetallePedido(tipo) {
    setActiveTab(tipo);
    const cod = tipo === 'pedido' ? pendingDecision.codPedido : pendingDecision.codCambio;
    if (!cod || cod === 0) return;
    mostrarDetallePlaceholder();
    await cargarDetallePedido(cod, pendingDecision.sucursal, tipo);
}

async function cargarDetallePedido(codPedido, sucursal, tipo) {
    try {
        const data = await fetch(`${AJAX_DETALLE}?cod_pedido=${codPedido}&sucursal=${sucursal}`)
            .then(r => r.json());

        document.getElementById('detallePlaceholder').style.display = 'none';
        const contenido = document.getElementById('detalleContenido');
        contenido.style.display = '';

        if (!data.success || !data.items || data.items.length === 0) {
            contenido.innerHTML = `<div class="alert alert-warning py-2 small">
                <i class="bi bi-exclamation-triangle me-1"></i>
                No se encontraron líneas para el pedido <strong>${codPedido}</strong> en la sucursal <strong>S${sucursal}</strong>.
            </div>`;
            return;
        }

        const info = data.resumen;
        const anulado = parseInt(info.Anulado) === -1 || parseInt(info.Anulado) === 1;

        contenido.innerHTML = `
            <div class="detalle-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="cod">#${codPedido} ${anulado ? '<span class="badge bg-danger ms-2" style="font-size:12px">ANULADO</span>' : ''}</div>
                        <div style="font-size:12px;opacity:.85">${info.Fecha || ''} ${info.Hora || ''} · ${info.Sucursal_Nombre || 'S' + sucursal}</div>
                    </div>
                    <div class="text-end">
                        <div style="font-size:12px;opacity:.85">${info.Modalidad || ''} · ${info.aPOS || ''}</div>
                        <div style="font-size:11px;opacity:.7">${info.Caja || ''}</div>
                    </div>
                </div>
            </div>
            <div class="detalle-resumen">
                ${chip('Cliente', info.CodCliente || '—')}
                ${chip('Tipo', info.Tipo || '—')}
                ${chip('Motorizado', info.Motorizado || '—')}
                ${chip('Delivery', info.Delivery_Nombre || '—')}
                ${chip('Monto Factura', info.MontoFactura ? 'C$ ' + parseFloat(info.MontoFactura).toFixed(2) : '—')}
                ${chip('Propina', info.Propina ? 'C$ ' + parseFloat(info.Propina).toFixed(2) : '—')}
                ${anulado ? chip('Motivo Anulación', info.MotivoAnulado || '—') : ''}
            </div>
            <table class="table table-detalle table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Medida</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-end">P. Unit.</th>
                        <th class="text-end">Subtotal</th>
                        <th>Promo</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.items.map(it => {
                        const sub = (parseFloat(it.Cantidad || 0) * parseFloat(it.Precio_Unitario_Sin_Descuento || it.Precio || 0)).toFixed(2);
                        return `<tr class="${parseInt(it.Anulado) === -1 ? 'fila-anulada' : ''}">
                            <td>${escHtml(it.DBBatidos_Nombre || it.NombreGrupo || '—')}</td>
                            <td>${escHtml(it.Medida || '—')}</td>
                            <td class="text-center">${it.Cantidad}</td>
                            <td class="text-end">C$ ${parseFloat(it.Precio_Unitario_Sin_Descuento || it.Precio || 0).toFixed(2)}</td>
                            <td class="text-end">C$ ${sub}</td>
                            <td class="text-center">${it.CodigoPromocion ? `<span class="badge bg-warning text-dark" style="font-size:10px">${it.CodigoPromocion}</span>` : '—'}</td>
                        </tr>`;
                    }).join('')}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end fw-bold">Total Factura:</td>
                        <td class="text-end fw-bold text-success">C$ ${parseFloat(info.MontoFactura || 0).toFixed(2)}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        `;
    } catch (e) {
        document.getElementById('detallePlaceholder').style.display = 'none';
        document.getElementById('detalleContenido').style.display = '';
        document.getElementById('detalleContenido').innerHTML =
            `<div class="alert alert-danger py-2 small">Error al cargar detalle: ${e.message}</div>`;
    }
}

function chip(lbl, val) {
    return `<div class="det-chip"><span class="lbl">${lbl}</span><span class="val">${escHtml(String(val))}</span></div>`;
}

// ── Ejecutar decisión ────────────────────────────────────────
async function ejecutarDecision(accion) {
    if (!pendingDecision || !PUEDE_APROBAR) return;

    const comentario = document.getElementById('dec_comentario')?.value.trim() || '';
    const btnApr = document.getElementById('btnAprobar');
    const btnRec = document.getElementById('btnRechazar');
    if (btnApr) btnApr.disabled = true;
    if (btnRec) btnRec.disabled = true;

    try {
        const data = await fetch(AJAX_APROBAR, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cod_anulacion_host: pendingDecision.id,
                accion,
                comentario,
                aprobado_por: USUARIO_ACTUAL
            })
        }).then(r => r.json());

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalDecision')).hide();
            mostrarToast(data.message, 'success');
            cargarDatos(currentPage);
            cargarStats();
        } else {
            mostrarToast('Error: ' + data.error, 'danger');
        }
    } catch (e) {
        mostrarToast('Error de red: ' + e.message, 'danger');
    } finally {
        if (btnApr) btnApr.disabled = false;
        if (btnRec) btnRec.disabled = false;
    }
}

// ── Acción rápida (sin abrir modal) ─────────────────────────
async function accionRapida(id, accion) {
    if (!PUEDE_APROBAR) return;
    const msg = accion === 'aprobar' ? '¿Aprobar esta solicitud?' : '¿Rechazar esta solicitud?';
    if (!confirm(msg)) return;

    try {
        const data = await fetch(AJAX_APROBAR, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cod_anulacion_host: id, accion,
                comentario: '', aprobado_por: USUARIO_ACTUAL
            })
        }).then(r => r.json());

        if (data.success) {
            mostrarToast(data.message, 'success');
            cargarDatos(currentPage);
            cargarStats();
        } else {
            mostrarToast('Error: ' + data.error, 'danger');
        }
    } catch (e) {
        mostrarToast('Error de red: ' + e.message, 'danger');
    }
}

// ── Nueva Anulación Web ──────────────────────────────────────
function abrirModalNuevaAnulacion() {
    document.getElementById('new_codPedido').value = '';
    document.getElementById('new_motivo').value    = '';
    document.getElementById('new_sucursal').value  = '';
    document.getElementById('newPedidoPreview').style.display = 'none';
    document.getElementById('newPedidoEmpty').style.display   = 'none';
    document.getElementById('btnEnviarAnulacionWeb').disabled = true;
    new bootstrap.Modal(document.getElementById('modalNuevaAnulacion')).show();
}

async function buscarPedidoWeb() {
    const cod = parseInt(document.getElementById('new_codPedido').value);
    const suc = parseInt(document.getElementById('new_sucursal').value);
    if (!cod || !suc) return;

    document.getElementById('newPedidoPreview').style.display = 'none';
    document.getElementById('newPedidoEmpty').style.display   = 'none';
    document.getElementById('btnEnviarAnulacionWeb').disabled = true;

    try {
        const data = await fetch(`${AJAX_DETALLE}?cod_pedido=${cod}&sucursal=${suc}`).then(r => r.json());
        if (!data.success || !data.items || !data.items.length) {
            document.getElementById('newPedidoEmpty').style.display = '';
            return;
        }
        const info = data.resumen;
        document.getElementById('newPedidoDetalle').innerHTML = `
            <div class="detalle-header">
                <div class="cod">#${cod} · ${info.Sucursal_Nombre || 'S' + suc}</div>
                <div style="font-size:12px;opacity:.85">${info.Fecha || ''} ${info.Hora || ''} · ${info.Modalidad || ''} · ${info.aPOS || ''}</div>
            </div>
            <div class="detalle-resumen">
                ${chip('Tipo', info.Tipo || '—')}
                ${chip('Cliente', info.CodCliente || '—')}
                ${chip('Monto', info.MontoFactura ? 'C$ ' + parseFloat(info.MontoFactura).toFixed(2) : '—')}
            </div>
            <table class="table table-detalle table-bordered mb-0">
                <thead><tr><th>Producto</th><th>Medida</th><th class="text-center">Cant.</th><th class="text-end">Precio</th></tr></thead>
                <tbody>
                ${data.items.map(it => `<tr>
                    <td>${escHtml(it.DBBatidos_Nombre || it.NombreGrupo || '—')}</td>
                    <td>${escHtml(it.Medida || '—')}</td>
                    <td class="text-center">${it.Cantidad}</td>
                    <td class="text-end">C$ ${parseFloat(it.Precio || 0).toFixed(2)}</td>
                </tr>`).join('')}
                </tbody>
            </table>`;
        document.getElementById('newPedidoPreview').style.display = '';
        document.getElementById('btnEnviarAnulacionWeb').disabled = false;
    } catch (e) {
        document.getElementById('newPedidoEmpty').style.display = '';
    }
}

async function enviarAnulacionWeb() {
    const cod    = parseInt(document.getElementById('new_codPedido').value);
    const suc    = parseInt(document.getElementById('new_sucursal').value);
    const motivo = document.getElementById('new_motivo').value.trim();

    if (!cod || !suc || !motivo) {
        mostrarToast('Completa todos los campos antes de enviar.', 'warning');
        return;
    }

    document.getElementById('btnEnviarAnulacionWeb').disabled = true;

    try {
        const data = await fetch(AJAX_NUEVA, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cod_pedido: cod, sucursal: suc, motivo, aprobado_por: USUARIO_ACTUAL })
        }).then(r => r.json());

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalNuevaAnulacion')).hide();
            mostrarToast(data.message, 'success');
            cargarDatos(1);
            cargarStats();
        } else {
            mostrarToast('Error: ' + data.error, 'danger');
            document.getElementById('btnEnviarAnulacionWeb').disabled = false;
        }
    } catch (e) {
        mostrarToast('Error de red: ' + e.message, 'danger');
        document.getElementById('btnEnviarAnulacionWeb').disabled = false;
    }
}

// ── Auto-refresh ─────────────────────────────────────────────
function iniciarAutoRefresh() {
    countdownVal = 60;
    clearInterval(countdownTimer);
    countdownTimer = setInterval(() => {
        countdownVal--;
        const el = document.getElementById('countdown');
        if (el) el.textContent = countdownVal;
        if (countdownVal <= 0) {
            cargarDatos(currentPage);
            cargarStats();
            countdownVal = 60;
        }
    }, 1000);
}

// ── Helpers ──────────────────────────────────────────────────
function limpiarFiltros() {
    document.getElementById('filtStatus').value   = '0';
    document.getElementById('filtSucursal').value = '0';
    document.getElementById('filtBuscar').value   = '';
    cargarDatos(1);
}

function escHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function mostrarToast(msg, tipo = 'success') {
    const icons = { success: 'check-circle-fill', danger: 'x-circle-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
    const colors = { success: '#198754', danger: '#dc3545', warning: '#ffc107', info: '#0dcaf0' };
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;
        background:#fff;border-left:4px solid ${colors[tipo] || colors.info};
        box-shadow:0 4px 16px rgba(0,0,0,.15);border-radius:8px;
        padding:12px 18px;font-size:13px;font-weight:500;min-width:280px;
        display:flex;align-items:center;gap:10px;
        animation:fadeInRight .3s ease`;
    toast.innerHTML = `<i class="bi bi-${icons[tipo] || 'info-circle-fill'}" style="color:${colors[tipo]};font-size:1.2rem"></i>${escHtml(msg)}`;
    document.body.appendChild(toast);

    // Animación CSS inline
    const style = document.createElement('style');
    style.textContent = `@keyframes fadeInRight{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}`;
    document.head.appendChild(style);

    setTimeout(() => toast.remove(), 4000);
}
