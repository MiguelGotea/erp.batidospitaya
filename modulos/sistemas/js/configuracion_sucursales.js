'use strict';

// ── State ────────────────────────────────────────────────────
const S = {
    sucursales: [],
    departamentos: [],
    filtro: 'all',
    busqueda: '',
    openDrawerId: null,
    mapaGeneral: null,
    mapaMini: null,
    makerGeneral: {},
    markerMini: null,
    detalleCache: {}
};

const BASE = 'ajax/';

// ── Toast ────────────────────────────────────────────────────
function toast(msg, tipo='ok', dur=2500) {
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = `toast ${tipo}`;
    t.innerHTML = `<i class="bi bi-${tipo==='ok'?'check-circle-fill':tipo==='err'?'x-circle-fill':'info-circle-fill'}"></i>${msg}`;
    c.appendChild(t);
    setTimeout(() => t.remove(), dur);
}

// ── KPIs ─────────────────────────────────────────────────────
function renderKPIs(lista) {
    const total  = lista.length;
    const activa = lista.filter(s=>s.activa==1).length;
    const inact  = total - activa;
    const dvr    = lista.filter(s=>s.tiene_dvr==1).length;
    document.getElementById('kpi-total').textContent  = total;
    document.getElementById('kpi-activa').textContent = activa;
    document.getElementById('kpi-inact').textContent  = inact;
    document.getElementById('kpi-dvr').textContent    = dvr;
}

// ── Filtrar ──────────────────────────────────────────────────
function getListaFiltrada() {
    let lista = S.sucursales;
    const q = S.busqueda.toLowerCase();
    if (q) lista = lista.filter(s =>
        s.nombre.toLowerCase().includes(q) ||
        s.codigo.toLowerCase().includes(q) ||
        (s.departamento||'').toLowerCase().includes(q)
    );
    if (S.filtro === 'act')  lista = lista.filter(s=>s.activa==1);
    if (S.filtro === 'ina')  lista = lista.filter(s=>s.activa==0);
    if (S.filtro === 'dvr')  lista = lista.filter(s=>s.tiene_dvr==1);
    if (S.filtro === 'ndvr') lista = lista.filter(s=>s.tiene_dvr==0);
    return lista;
}

// ── Render Cards ─────────────────────────────────────────────
function renderGrid() {
    const grid = document.getElementById('suc-grid');
    const lista = getListaFiltrada();
    if (!lista.length) {
        grid.innerHTML = `<div class="empty-state"><i class="bi bi-shop-window"></i><p>No se encontraron tiendas</p></div>`;
        return;
    }
    grid.innerHTML = lista.map(s => cardHTML(s)).join('');
    lista.forEach(s => {
        const h = document.getElementById(`card-${s.id}`);
        if (h) h.addEventListener('click', () => openDrawer(s.id));
    });
}

function cardHTML(s) {
    const actBadge = s.activa==1
        ? `<span class="badge badge-activa">Activa</span>`
        : `<span class="badge badge-inactiva">Inactiva</span>`;
    const dvrBadge = s.tiene_dvr==1
        ? `<span class="badge badge-dvr"><i class="bi bi-camera-video-fill"></i> DVR</span>`
        : `<span class="badge badge-nodvr">Sin DVR</span>`;
    const vmtap = s.VMTAP==1 ? `<span class="badge badge-vmtap">VMTAP</span>` : '';
    const tunel = s.dvr_tunel_activo==1 ? `<span class="badge badge-tunel"><i class="bi bi-shield-check"></i> Túnel</span>` : '';
    const inact = s.activa==0 ? ' inactiva' : '';

    return `<div class="suc-card${inact}" data-id="${s.id}" id="card-${s.id}">
        <div class="suc-card-head">
            <div class="suc-card-left">
                <div><span class="suc-codigo">${s.codigo}</span></div>
                <div class="suc-nombre">${s.nombre}</div>
                <div class="suc-depto"><i class="bi bi-geo-alt"></i>${s.departamento||'—'}</div>
                <div class="suc-badges">${actBadge}${dvrBadge}${vmtap}${tunel}</div>
            </div>
            <i class="bi bi-chevron-right suc-chevron"></i>
        </div>
    </div>`;
}

// ── Drawer Management ────────────────────────────────────────
function openDrawer(id) {
    const s = S.sucursales.find(x => x.id == id);
    if (!s) return;

    S.openDrawerId = id;
    document.getElementById('drawer-title').textContent = s.nombre;
    document.getElementById('drawer-subtitle').textContent = `Código: ${s.codigo}`;
    
    const overlay = document.getElementById('drawer-overlay');
    const drawer = document.getElementById('suc-drawer');
    overlay.classList.add('active');
    drawer.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent scroll

    // Reset tabs
    document.querySelectorAll('.suc-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('.suc-tab-btn[data-tab="general"]').classList.add('active');
    document.querySelectorAll('.suc-tab-content').forEach(c => {
        c.classList.remove('active');
        c.innerHTML = `<div class="text-center p-5"><div class="spinner-border text-secondary"></div><p class="mt-2 text-muted">Cargando datos...</p></div>`;
    });
    document.getElementById('dtab-general').classList.add('active');

    cargarDetalle(id);
}

function closeDrawer() {
    S.openDrawerId = null;
    document.getElementById('drawer-overlay').classList.remove('active');
    document.getElementById('suc-drawer').classList.remove('active');
    document.body.style.overflow = '';
}

function switchDrawerTab(tab, btnEl) {
    const drawer = document.getElementById('suc-drawer');
    drawer.querySelectorAll('.suc-tab-btn').forEach(b => b.classList.remove('active'));
    drawer.querySelectorAll('.suc-tab-content').forEach(c => c.classList.remove('active'));
    
    btnEl.classList.add('active');
    const tc = document.getElementById(`dtab-${tab}`);
    if (tc) tc.classList.add('active');

    if (tab === 'mapa' && !S.mapaMiniInit?.[S.openDrawerId]) {
        const suc = S.detalleCache[S.openDrawerId]?.sucursal || S.sucursales.find(s=>s.id==S.openDrawerId) || {};
        setTimeout(() => initMapaMini(S.openDrawerId, suc), 100);
        S.mapaMiniInit = S.mapaMiniInit || {};
        S.mapaMiniInit[S.openDrawerId] = true;
    }
}

// ── Cargar detalle ───────────────────────────────────────────
function cargarDetalle(id) {
    if (S.detalleCache[id]) { renderTabs(id, S.detalleCache[id]); return; }
    fetch(`${BASE}suc_get_detalle.php?id=${id}`)
        .then(r=>r.json())
        .then(res => {
            if (!res.success) { toast(res.message,'err'); return; }
            S.detalleCache[id] = res;
            renderTabs(id, res);
        }).catch(() => toast('Error de conexión','err'));
}

function renderTabs(id, res) {
    const {sucursal: s, dvr, tiene_dvr} = res;
    renderTabGeneral(id, s);
    renderTabEstado(id, s);
    renderTabDVR(id, s, dvr, tiene_dvr);
    renderTabMapa(id, s);
}

// ── Tab: General ─────────────────────────────────────────────
function renderTabGeneral(id, s) {
    const ro = !PUEDE_EDITAR ? 'readonly' : '';
    const deptoOpts = S.departamentos.map(d=>
        `<option value="${d.codigo}" ${d.codigo==s.cod_departamento?'selected':''}>${d.nombre}</option>`
    ).join('');

    document.getElementById('dtab-general').innerHTML = `
    <div class="field-group">
        <div class="field-item">
            <label class="field-label"><i class="bi bi-hash"></i>Código</label>
            <div class="field-input-wrap"><input class="field-input" value="${esc(s.codigo)}" readonly title="El código no es editable"></div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-shop"></i>Nombre</label>
            <div class="field-input-wrap">
                <input class="field-input autosave-suc" data-id="${id}" data-campo="nombre" value="${esc(s.nombre)}" ${ro}>
                <span class="save-indicator"><i class="bi bi-check-circle-fill"></i></span>
            </div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-telephone"></i>Teléfono</label>
            <div class="field-input-wrap">
                <input class="field-input autosave-suc" data-id="${id}" data-campo="telefono" value="${esc(s.telefono)}" ${ro}>
                <span class="save-indicator"></span>
            </div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-whatsapp"></i>WhatsApp</label>
            <div>
                <div class="field-input-wrap">
                    <input class="field-input autosave-suc" data-id="${id}" data-campo="whatsapp" value="${esc(s.whatsapp)}" ${ro}>
                    <span class="save-indicator"></span>
                </div>
                ${s.whatsapp ? `<a class="wsp-link" href="https://wa.me/${s.whatsapp.replace(/\D/g,'')}" target="_blank"><i class="bi bi-whatsapp"></i>Abrir chat</a>` : ''}
            </div>
        </div>
        <div class="field-item full">
            <label class="field-label"><i class="bi bi-envelope"></i>Email</label>
            <div class="field-input-wrap">
                <input type="email" class="field-input autosave-suc" data-id="${id}" data-campo="email" value="${esc(s.email)}" ${ro}>
                <span class="save-indicator"></span>
            </div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-hdd-network"></i>IP Dirección</label>
            <div class="field-input-wrap">
                <input class="field-input autosave-suc" data-id="${id}" data-campo="ip_direccion" value="${esc(s.ip_direccion)}" ${ro}>
                <span class="save-indicator"></span>
            </div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-geo-alt"></i>Departamento</label>
            ${PUEDE_EDITAR
                ? `<select class="field-input autosave-suc" data-id="${id}" data-campo="cod_departamento"><option value="">—</option>${deptoOpts}</select>`
                : `<input class="field-input" value="${esc(s.departamento)}" readonly>`}
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-calendar-check"></i>Fecha Apertura</label>
            <div class="field-input-wrap">
                <input type="date" class="field-input autosave-suc" data-id="${id}" data-campo="Fecha_Apertura" value="${s.Fecha_Apertura||''}" ${ro}>
                <span class="save-indicator"></span>
            </div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-calendar-x"></i>Fecha Cierre</label>
            <div class="field-input-wrap">
                <input type="date" class="field-input autosave-suc" data-id="${id}" data-campo="Fecha_Cierre" value="${s.Fecha_Cierre||''}" ${ro}>
                <span class="save-indicator"></span>
            </div>
        </div>
    </div>`;
    bindAutoSave(id, 'suc');
}

// ── Tab: Estado ──────────────────────────────────────────────
function renderTabEstado(id, s) {
    const dis = !PUEDE_EDITAR ? 'disabled' : '';
    const ro  = !PUEDE_EDITAR ? 'readonly' : '';

    const gbizLink = s.cod_googlebusiness
        ? `<a class="gbiz-link" href="https://maps.google.com/?cid=${encodeURIComponent(s.cod_googlebusiness)}" target="_blank"><i class="bi bi-google"></i>Ver en Google Maps</a>` : '';

    document.getElementById('dtab-estado').innerHTML = `
    <div class="toggle-row">
        <div class="toggle-label-text"><i class="bi bi-power"></i>Tienda Activa<span class="toggle-saving" id="tsav-activa-${id}">guardando…</span></div>
        <label class="toggle-switch"><input type="checkbox" class="toggle-suc" data-id="${id}" data-campo="activa" ${s.activa==1?'checked':''} ${dis}><span class="toggle-slider"></span></label>
    </div>
    <div class="toggle-row">
        <div class="toggle-label-text"><i class="bi bi-shop"></i>¿Es Tienda?<span class="toggle-saving" id="tsav-sucursal-${id}">guardando…</span></div>
        <label class="toggle-switch"><input type="checkbox" class="toggle-suc" data-id="${id}" data-campo="sucursal" ${s.sucursal==1?'checked':''} ${dis}><span class="toggle-slider"></span></label>
    </div>
    <div class="toggle-row">
        <div class="toggle-label-text"><i class="bi bi-graph-up-arrow"></i>Proyección de ventas (VMTAP)<span class="toggle-saving" id="tsav-VMTAP-${id}">guardando…</span></div>
        <label class="toggle-switch"><input type="checkbox" class="toggle-suc" data-id="${id}" data-campo="VMTAP" ${s.VMTAP==1?'checked':''} ${dis}><span class="toggle-slider"></span></label>
    </div>
    <div class="field-group" style="margin-top:14px">
        <div class="field-item">
            <label class="field-label"><i class="bi bi-moon-stars"></i>Viático Nocturno (C$)</label>
            <div class="field-input-wrap">
                <input type="number" class="field-input autosave-suc" data-id="${id}" data-campo="viatico_nocturno" value="${s.viatico_nocturno||0}" min="0" ${ro}>
                <span class="save-indicator"></span>
            </div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-google"></i>Google Business ID</label>
            <div class="field-input-wrap">
                <input class="field-input autosave-suc" data-id="${id}" data-campo="cod_googlebusiness" value="${esc(s.cod_googlebusiness)}" ${ro}>
                <span class="save-indicator"></span>
            </div>
            ${gbizLink}
        </div>
        ${PUEDE_EDITAR ? `
        <div class="field-item">
            <label class="field-label"><i class="bi bi-key"></i>Cookie Token (ERP)</label>
            <div class="field-input-wrap">
                <input class="field-input autosave-suc" data-id="${id}" data-campo="cookie_token" value="${esc(s.cookie_token)}" ${ro}>
                <span class="save-indicator"></span>
            </div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-key-fill"></i>Cookie Token (POS)</label>
            <div class="field-input-wrap">
                <input class="field-input autosave-suc" data-id="${id}" data-campo="pos_cookie_token" value="${esc(s.pos_cookie_token)}" ${ro}>
                <span class="save-indicator"></span>
            </div>
        </div>` : ''}
    </div>`;
    bindAutoSave(id, 'suc');
    bindToggles(id, 'suc');
}

// ── Tab: DVR ─────────────────────────────────────────────────
function renderTabDVR(id, suc, dvr, tiene_dvr) {
    const el = document.getElementById('dtab-dvr');
    if (!tiene_dvr) {
        el.innerHTML = `<div class="dvr-empty">
            <i class="bi bi-camera-video-off"></i>
            <p>Esta tienda no tiene configuración DVR</p>
            ${PUEDE_EDITAR ? `<button class="btn-crear-dvr" id="btn-dvr-${id}" onclick="crearDVR(${id})"><i class="bi bi-plus-circle"></i>Agregar configuración DVR</button>` : ''}
        </div>`;
        return;
    }
    const ro  = !PUEDE_EDITAR ? 'readonly' : '';
    const dis = !PUEDE_EDITAR ? 'disabled' : '';
    el.innerHTML = `
    <div class="dvr-section-title"><i class="bi bi-info-circle"></i>Dispositivo</div>
    <div class="field-group">
        <div class="field-item">
            <label class="field-label"><i class="bi bi-cpu"></i>Modelo</label>
            <div class="field-input-wrap"><input class="field-input autosave-dvr" data-id="${id}" data-campo="modelo" value="${esc(dvr.modelo)}" ${ro}><span class="save-indicator"></span></div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-tag"></i>Marca</label>
            <div class="field-input-wrap"><input class="field-input autosave-dvr" data-id="${id}" data-campo="marca" value="${esc(dvr.marca)}" ${ro}><span class="save-indicator"></span></div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-upc-scan"></i>Serial</label>
            <div class="field-input-wrap"><input class="field-input autosave-dvr" data-id="${id}" data-campo="serial" value="${esc(dvr.serial)}" ${ro}><span class="save-indicator"></span></div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-hdd"></i>Capacidad</label>
            <div class="field-input-wrap"><input class="field-input autosave-dvr" data-id="${id}" data-campo="capacidad" value="${esc(dvr.capacidad)}" ${ro}><span class="save-indicator"></span></div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-lock"></i>Clave Dispositivo</label>
            <div class="pwd-wrap">
                <input type="password" class="field-input autosave-dvr" id="clave-disp-${id}" data-id="${id}" data-campo="clave_dispositivo" value="${esc(dvr.clave_dispositivo)}" ${ro}>
                <button class="pwd-eye" onclick="togglePwd('clave-disp-${id}',this)" type="button"><i class="bi bi-eye"></i></button>
                <span class="save-indicator"></span>
            </div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-camera"></i>Canal de Caja</label>
            <div class="field-input-wrap"><input type="number" class="field-input autosave-dvr" data-id="${id}" data-campo="canal_caja" value="${dvr.canal_caja||0}" min="0" ${ro}><span class="save-indicator"></span></div>
        </div>
    </div>
    <div class="dvr-section-title" style="margin-top:12px"><i class="bi bi-hdd-network"></i>Portal / Acceso</div>
    <div class="field-group">
        <div class="field-item">
            <label class="field-label"><i class="bi bi-router"></i>IP Local Portal</label>
            <div class="field-input-wrap"><input class="field-input autosave-dvr" data-id="${id}" data-campo="portal_ip_local" value="${esc(dvr.portal_ip_local)}" ${ro}><span class="save-indicator"></span></div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-person"></i>Usuario Portal</label>
            <div class="field-input-wrap"><input class="field-input autosave-dvr" data-id="${id}" data-campo="portal_usuario" value="${esc(dvr.portal_usuario)}" ${ro}><span class="save-indicator"></span></div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-key"></i>Clave Portal</label>
            <div class="field-input-wrap"><input class="field-input autosave-dvr" data-id="${id}" data-campo="portal_clave" value="${esc(dvr.portal_clave)}" ${ro}><span class="save-indicator"></span></div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-link-45deg"></i>URL Imagen</label>
            <div class="field-input-wrap"><input class="field-input autosave-dvr" data-id="${id}" data-campo="url_imagen" value="${esc(dvr.url_imagen)}" ${ro}><span class="save-indicator"></span></div>
        </div>
    </div>
    <div class="dvr-section-title" style="margin-top:12px"><i class="bi bi-shield-lock"></i>Túnel SSH (VPS)</div>
    <div class="field-group">
        <div class="field-item">
            <label class="field-label"><i class="bi bi-ethernet"></i>Puerto RTSP en VPS</label>
            <div class="field-input-wrap"><input type="number" class="field-input autosave-dvr" data-id="${id}" data-campo="puerto_rtsp_vps" value="${dvr.puerto_rtsp_vps||''}" ${ro}><span class="save-indicator"></span></div>
        </div>
        <div class="field-item" style="align-self:end;padding-bottom:4px">
            <div class="toggle-row">
                <div class="toggle-label-text"><i class="bi bi-shield-check"></i>Túnel SSH Activo<span class="toggle-saving" id="tsav-tunel-${id}">guardando…</span></div>
                <label class="toggle-switch"><input type="checkbox" class="toggle-dvr" data-id="${id}" data-campo="tunel_activo" ${dvr.tunel_activo==1?'checked':''} ${dis}><span class="toggle-slider"></span></label>
            </div>
        </div>
    </div>`;
    bindAutoSave(id, 'dvr');
    bindToggles(id, 'dvr');
}

// ── Tab: Mapa ────────────────────────────────────────────────
function renderTabMapa(id, s) {
    const ro = !PUEDE_EDITAR ? 'readonly' : '';
    document.getElementById('dtab-mapa').innerHTML = `
    <div class="mapa-mini-wrap"><div id="mapa-mini-${id}" style="height:280px"></div></div>
    <div class="coords-inputs">
        <div class="field-item">
            <label class="field-label"><i class="bi bi-arrow-up-down"></i>Latitud</label>
            <div class="field-input-wrap">
                <input type="number" step="any" class="field-input autosave-suc" id="lat-${id}" data-id="${id}" data-campo="Latitude" value="${s.Latitude||''}" ${ro}>
                <span class="save-indicator"></span>
            </div>
        </div>
        <div class="field-item">
            <label class="field-label"><i class="bi bi-arrow-left-right"></i>Longitud</label>
            <div class="field-input-wrap">
                <input type="number" step="any" class="field-input autosave-suc" id="lng-${id}" data-id="${id}" data-campo="Longitude" value="${s.Longitude||''}" ${ro}>
                <span class="save-indicator"></span>
            </div>
        </div>
    </div>`;
    bindAutoSave(id, 'suc');
    setTimeout(() => initMapaMini(id, s), 100);
}

// ── Auto-save: texto/número/fecha/select ─────────────────────
const _timers = {};
function bindAutoSave(id, tipo) {
    const cls = tipo === 'suc' ? '.autosave-suc' : '.autosave-dvr';
    const panel = document.getElementById('suc-drawer');
    if (!panel) return;
    panel.querySelectorAll(cls).forEach(el => {
        if (el._bound) return;
        el._bound = true;
        const evts = (el.tagName === 'SELECT' || el.type === 'date' || el.type === 'checkbox') ? ['change'] : ['blur'];
        evts.forEach(evt => el.addEventListener(evt, () => {
            const campo = el.dataset.campo;
            const valor = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
            const key = `${tipo}-${id}-${campo}`;
            clearTimeout(_timers[key]);
            const ind = el.parentElement.querySelector('.save-indicator');
            if (ind) { ind.className = 'save-indicator saving'; ind.innerHTML = '<i class="bi bi-arrow-repeat spin"></i>'; }
            _timers[key] = setTimeout(() => guardarCampo(id, campo, valor, tipo, ind, el), 800);
        }));
    });
}

function guardarCampo(id, campo, valor, tipo, indicator, el) {
    const url = tipo === 'suc' ? `${BASE}suc_guardar_campo.php` : `${BASE}suc_dvr_guardar_campo.php`;
    const body = new FormData();
    body.append(tipo === 'suc' ? 'id_sucursal' : 'cod_sucursal', id);
    body.append('campo', campo);
    body.append('valor', valor ?? '');

    fetch(url, { method: 'POST', body })
        .then(r => r.json())
        .then(res => {
            if (indicator) {
                if (res.success) {
                    indicator.className = 'save-indicator saved';
                    indicator.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
                    setTimeout(() => { indicator.className = 'save-indicator'; indicator.innerHTML = ''; }, 2000);
                    // Actualizar cache y card badges si cambió estado importante
                    if (S.detalleCache[id]) {
                        if (tipo === 'suc') S.detalleCache[id].sucursal[campo] = valor;
                        else if (S.detalleCache[id].dvr) S.detalleCache[id].dvr[campo] = valor;
                    }
                    actualizarCardBadges(id, campo, valor);
                } else {
                    indicator.className = 'save-indicator error';
                    indicator.innerHTML = '<i class="bi bi-x-circle-fill"></i>';
                    setTimeout(() => { indicator.className = 'save-indicator'; indicator.innerHTML = ''; }, 3000);
                    toast(res.message, 'err', 4000);
                    if (el && res.message.includes('fecha')) el.focus();
                }
            }
        }).catch(() => { if (indicator) { indicator.className = 'save-indicator error'; indicator.innerHTML = '<i class="bi bi-x-circle-fill"></i>'; } toast('Error de conexión','err'); });
}

function actualizarCardBadges(id, campo, valor) {
    const card = document.getElementById(`card-${id}`);
    if (!card) return;
    const suc = S.sucursales.find(s => s.id == id);
    if (!suc) return;
    if (campo === 'activa') { suc.activa = parseInt(valor); card.classList.toggle('inactiva', suc.activa == 0); }
    if (campo === 'VMTAP')  suc.VMTAP = parseInt(valor);
    if (campo === 'tunel_activo') suc.dvr_tunel_activo = parseInt(valor);
    // Re-render badges only
    const badgesEl = card.querySelector('.suc-badges');
    if (badgesEl) {
        const actBadge = suc.activa==1 ? `<span class="badge badge-activa">Activa</span>` : `<span class="badge badge-inactiva">Inactiva</span>`;
        const dvrBadge = suc.tiene_dvr==1 ? `<span class="badge badge-dvr"><i class="bi bi-camera-video-fill"></i> DVR</span>` : `<span class="badge badge-nodvr">Sin DVR</span>`;
        const vmtap   = suc.VMTAP==1 ? `<span class="badge badge-vmtap">VMTAP</span>` : '';
        const tunel   = suc.dvr_tunel_activo==1 ? `<span class="badge badge-tunel"><i class="bi bi-shield-check"></i> Túnel</span>` : '';
        badgesEl.innerHTML = actBadge + dvrBadge + vmtap + tunel;
    }
    renderKPIs(S.sucursales);
}

// ── Auto-save: toggles ────────────────────────────────────────
function bindToggles(id, tipo) {
    const cls = tipo === 'suc' ? '.toggle-suc' : '.toggle-dvr';
    const panel = document.getElementById('suc-drawer');
    if (!panel) return;
    panel.querySelectorAll(cls).forEach(el => {
        if (el._togbound) return;
        el._togbound = true;
        el.addEventListener('change', () => {
            const campo = el.dataset.campo;
            const valor = el.checked ? 1 : 0;
            const savEl = document.getElementById(`tsav-${campo}-${id}`);
            if (savEl) { savEl.textContent = 'guardando…'; savEl.classList.add('show'); }
            guardarCampoDirecto(id, campo, valor, tipo, () => {
                if (savEl) { savEl.textContent = '✓'; setTimeout(() => { savEl.textContent = ''; savEl.classList.remove('show'); }, 1500); }
            }, () => {
                el.checked = !el.checked; // revert
                if (savEl) { savEl.textContent = 'error'; setTimeout(() => { savEl.textContent = ''; savEl.classList.remove('show'); }, 2000); }
            });
        });
    });
}

function guardarCampoDirecto(id, campo, valor, tipo, onOk, onErr) {
    const url  = tipo === 'suc' ? `${BASE}suc_guardar_campo.php` : `${BASE}suc_dvr_guardar_campo.php`;
    const body = new FormData();
    body.append(tipo === 'suc' ? 'id_sucursal' : 'cod_sucursal', id);
    body.append('campo', campo);
    body.append('valor', valor);
    fetch(url, { method: 'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (S.detalleCache[id]) {
                    if (tipo === 'suc') S.detalleCache[id].sucursal[campo] = valor;
                    else if (S.detalleCache[id].dvr) S.detalleCache[id].dvr[campo] = valor;
                }
                actualizarCardBadges(id, campo, valor);
                onOk && onOk();
            } else { toast(res.message, 'err', 4000); onErr && onErr(); }
        }).catch(() => { toast('Error de conexión','err'); onErr && onErr(); });
}

// ── Crear DVR ─────────────────────────────────────────────────
function crearDVR(id) {
    const btn = document.getElementById(`btn-dvr-${id}`);
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Creando…'; }
    const body = new FormData();
    body.append('cod_sucursal', id);
    fetch(`${BASE}suc_dvr_crear.php`, { method: 'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                toast('Configuración DVR creada', 'ok');
                const suc = S.sucursales.find(s => s.id == id);
                if (suc) suc.tiene_dvr = 1;
                if (S.detalleCache[id]) {
                    S.detalleCache[id].dvr = res.dvr;
                    S.detalleCache[id].tiene_dvr = true;
                }
                renderTabDVR(id, S.detalleCache[id]?.sucursal || {}, res.dvr, true);
                actualizarCardBadges(id, 'tiene_dvr_force', 1);
                // Force DVR badge update
                const suc2 = S.sucursales.find(s => s.id == id);
                if (suc2) { suc2.tiene_dvr = 1; }
                actualizarCardBadges(id, '__noop__', null);
            } else {
                toast(res.message, 'err');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-plus-circle"></i>Agregar configuración DVR'; }
            }
        }).catch(() => { toast('Error de conexión','err'); if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-plus-circle"></i>Agregar configuración DVR'; } });
}

// ── Tabs switch ───────────────────────────────────────────────


// ── Mapa General (Leaflet) ────────────────────────────────────
function initMapaGeneral(lista) {
    if (S.mapaGeneral) { S.mapaGeneral.remove(); S.mapaGeneral = null; }
    S.mapaGeneral = L.map('mapa-general', { zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap', maxZoom: 18
    }).addTo(S.mapaGeneral);

    const conCoords = lista.filter(s => s.Latitude && s.Longitude);
    if (!conCoords.length) { S.mapaGeneral.setView([12.8654, -85.2072], 7); return; }

    const bounds = [];
    const iconV = (activa) => L.divIcon({
        className: '',
        html: `<div style="width:14px;height:14px;border-radius:50%;background:${activa?'#10b981':'#94a3b8'};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>`,
        iconSize: [14,14], iconAnchor: [7,7]
    });

    conCoords.forEach(s => {
        const m = L.marker([s.Latitude, s.Longitude], { icon: iconV(s.activa==1) })
            .addTo(S.mapaGeneral)
            .bindPopup(`<b>${s.nombre}</b><br><small>${s.codigo} · ${s.departamento||''}</small><br>
                <button onclick="openDrawer(${s.id})" style="margin-top:5px;padding:3px 8px;background:#0E544C;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:.7rem">Ver detalle</button>`);
        S.makerGeneral[s.id] = m;
        bounds.push([s.Latitude, s.Longitude]);
    });
    if (bounds.length > 1) S.mapaGeneral.fitBounds(bounds, { padding: [30,30] });
    else S.mapaGeneral.setView(bounds[0], 14);
}

function scrollToCard(id) {
    const card = document.getElementById(`card-${id}`);
    if (!card) return;
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    openDrawer(id);
    S.mapaGeneral?.closePopup();
}

// ── Mapa Mini (Leaflet) ───────────────────────────────────────
function initMapaMini(id, s) {
    const el = document.getElementById(`mapa-mini-${id}`);
    if (!el) return;
    const lat = s.Latitude  || 12.8654;
    const lng = s.Longitude || -85.2072;
    const zoom = s.Latitude ? 15 : 7;
    const mini = L.map(`mapa-mini-${id}`, { zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OSM', maxZoom: 18 }).addTo(mini);
    mini.setView([lat, lng], zoom);

    const marker = L.marker([lat, lng], { draggable: PUEDE_EDITAR }).addTo(mini);
    marker.bindPopup(s.nombre || 'Tienda').openPopup();

    if (PUEDE_EDITAR) {
        marker.on('dragend', e => {
            const pos = e.target.getLatLng();
            const latEl = document.getElementById(`lat-${id}`);
            const lngEl = document.getElementById(`lng-${id}`);
            if (latEl) { latEl.value = pos.lat.toFixed(6); latEl.dispatchEvent(new Event('blur')); }
            if (lngEl) { lngEl.value = pos.lng.toFixed(6); lngEl.dispatchEvent(new Event('blur')); }
        });
        // Click en mapa mueve el marker
        mini.on('click', e => {
            marker.setLatLng(e.latlng);
            const latEl = document.getElementById(`lat-${id}`);
            const lngEl = document.getElementById(`lng-${id}`);
            if (latEl) { latEl.value = e.latlng.lat.toFixed(6); latEl.dispatchEvent(new Event('blur')); }
            if (lngEl) { lngEl.value = e.latlng.lng.toFixed(6); lngEl.dispatchEvent(new Event('blur')); }
        });
    }
}

// ── Toggle mapa general ───────────────────────────────────────
function toggleMapaGeneral() {
    const wrap = document.getElementById('mapa-general');
    const btn  = document.querySelector('.toggle-mapa-btn');
    if (!wrap) return;
    const visible = wrap.style.display !== 'none';
    wrap.style.display = visible ? 'none' : 'block';
    btn.textContent = visible ? 'Mostrar mapa' : 'Ocultar mapa';
    if (!visible && S.mapaGeneral) S.mapaGeneral.invalidateSize();
}

// ── Toggle password ───────────────────────────────────────────
function togglePwd(inputId, btn) {
    const el = document.getElementById(inputId);
    if (!el) return;
    if (el.type === 'password') { el.type = 'text'; btn.innerHTML = '<i class="bi bi-eye-slash"></i>'; }
    else { el.type = 'password'; btn.innerHTML = '<i class="bi bi-eye"></i>'; }
}

// ── Escape helper ─────────────────────────────────────────────
function esc(v) {
    if (v === null || v === undefined) return '';
    return String(v).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Spin CSS ──────────────────────────────────────────────────
(function() {
    const st = document.createElement('style');
    st.textContent = '@keyframes spin{to{transform:rotate(360deg)}}.spin{animation:spin .7s linear infinite;display:inline-block}';
    document.head.appendChild(st);
})();

// ── Carga inicial ─────────────────────────────────────────────
function cargarLista() {
    const grid = document.getElementById('suc-grid');
    grid.innerHTML = Array(6).fill('<div class="skeleton-card"></div>').join('');
    fetch(`${BASE}suc_get_lista.php`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) { toast(res.message,'err'); grid.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-circle"></i><p>Error al cargar</p></div>'; return; }
            S.sucursales = res.data;
            renderKPIs(S.sucursales);
            renderGrid();
            initMapaGeneral(S.sucursales);
        }).catch(() => { toast('Error de conexión','err'); grid.innerHTML = ''; });
}

// ── Carga de departamentos ───────────────────────────────────
function cargarDepartamentos() {
    return fetch(`${BASE}suc_get_departamentos.php`)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                S.departamentos = res.data;
            }
        }).catch(err => console.error('Error cargando departamentos:', err));
}

// ── Filtros y búsqueda ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    await cargarDepartamentos();
    cargarLista();

    document.getElementById('suc-search').addEventListener('input', function() {
        S.busqueda = this.value;
        renderGrid();
    });

    document.querySelectorAll('.fil-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.fil-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            S.filtro = this.dataset.filtro;
            renderGrid();
        });
    });
});
