'use strict';
const PA_GRUPOS  = ['B','D','F','G'];
const PA_LABELS  = {B:'Congelados',D:'Desechables',F:'Secos y Preparación',G:'Productos de Mostrador'};
const PA_SEMANAS = 4;

let PA_SUCURSALES = [];

$(document).ready(() => {
    cargarSucursales();
    $('#pa-btn-calcular').on('click', calcularAgenda);
    $('#pa-agenda').on('click', '.pa-row-expandible', function () {
        const ppId = $(this).data('pp-id');
        const sk   = $(this).data('slot-key');
        $(`.pa-tienda-sub[data-slot-key="${sk}"][data-pp-id="${ppId}"]`).toggleClass('d-none');
        $(this).find('.pa-expand-icon').toggleClass('rotated');
    });
});

function cargarSucursales() {
    $.getJSON('ajax/configuracion_logistica_get_sucursales.php', res => {
        if (res.success && res.sucursales.length) {
            PA_SUCURSALES = res.sucursales;
            $('#pa-sucursal').append(`<option value="TODAS">🏪 Todas las Tiendas (Consolidado)</option>`);
            res.sucursales.forEach(s => {
                $('#pa-sucursal').append(`<option value="${s.codigo}">${s.nombre}</option>`);
            });
            $('#pa-sucursal').select2({ width:'100%', dropdownAutoWidth:true });
        }
    });
}

function addDaysStr(d, n) {
    const dt = new Date(d + 'T12:00:00'); dt.setDate(dt.getDate() + n);
    return dt.toISOString().split('T')[0];
}
function todayStr() { return new Date().toISOString().split('T')[0]; }
function limitStr()  { return addDaysStr(todayStr(), PA_SEMANAS * 7); }
function formatDateHeader(ds) {
    const DIAS  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const d = new Date(ds + 'T12:00:00');
    return { weekday:DIAS[d.getDay()], day:d.getDate(), month:MESES[d.getMonth()], year:d.getFullYear() };
}
function fmt2(v) {
    if (v === null || v === undefined) return '<span class="pa-na">N/A</span>';
    return Number(v).toLocaleString('es-NI', {minimumFractionDigits:2, maximumFractionDigits:2});
}
function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function setLoaderStep(m) { $('#pa-loader-step').text(m); }
function showLoader()  { $('#pa-panel-inicial,#pa-panel-datos').addClass('d-none'); $('#pa-loader').removeClass('d-none'); }
function hideLoader()  { $('#pa-loader').addClass('d-none'); }
function showInicial() { hideLoader(); $('#pa-panel-inicial').removeClass('d-none'); $('#pa-panel-datos').addClass('d-none'); }
function showDatos()   { hideLoader(); $('#pa-panel-datos').removeClass('d-none'); }

async function calcularAgenda() {
    const semDesde = parseInt($('#pa-desde').val());
    const semHasta = parseInt($('#pa-hasta').val());
    const semCorte = parseInt($('#pa-corte').val());
    const sucursal = $('#pa-sucursal').val();

    const errores = [];
    if (!semDesde || !semHasta) errores.push('Ingresa el rango de semanas (Desde / Hasta).');
    if (!semCorte)              errores.push('Ingresa la Semana de Corte para el pronóstico D-1.');
    if (!sucursal)              errores.push('Selecciona una sucursal.');
    if (semDesde && semHasta && semDesde > semHasta) errores.push('La semana "Desde" debe ser ≤ que "Hasta".');
    if (semCorte && semDesde && semHasta && (semCorte < semDesde || semCorte > semHasta))
        errores.push(`La semana de corte (${semCorte}) debe estar dentro del rango ${semDesde}–${semHasta}.`);

    if (errores.length) {
        Swal.fire({ icon:'warning', title:'Datos incompletos', html:errores.map(e=>`<div>• ${e}</div>`).join(''), confirmButtonColor:'#0ea5e9' });
        return;
    }

    showLoader();
    setLoaderStep('Iniciando cálculo…');

    try {
        if (sucursal === 'TODAS') {
            await calcularAgendaConsolidada(semDesde, semHasta, semCorte);
        } else {
            const datos = await calcularDatosParaSucursal(semDesde, semHasta, semCorte, sucursal);
            if (!datos) return;
            renderAgenda(datos.agendaMap, datos.fechasOrdenadas, datos.sinPlan, false);
            showDatos();
        }
    } catch (err) {
        console.error('calcularAgenda:', err);
        hideLoader(); showInicial();
        Swal.fire({ icon:'error', title:'Error de conexión', text:'No se pudo comunicar con el servidor.', confirmButtonColor:'#0ea5e9' });
    }
}

async function calcularDatosParaSucursal(semDesde, semHasta, semCorte, codSuc) {
    try {
    const fdP = new FormData();
    fdP.append('semana_desde_num', semDesde);
    fdP.append('semana_hasta_num', semHasta);
    fdP.append('cod_sucursal', codSuc);

    const resPedido = await fetch('ajax/pedido_sugerido_calcular_v2.php', { method:'POST', body:fdP }).then(r=>r.json());
    if (!resPedido.ok) return null;

    const prodFiltrados = (resPedido.productos || []).filter(p => PA_GRUPOS.includes(p.categoria_insumo));
    if (!prodFiltrados.length) return null;

    const porCat = {};
    PA_GRUPOS.forEach(c => porCat[c] = []);
    prodFiltrados.forEach(p => { porCat[p.categoria_insumo]?.push(p); });

    const sinPlan = {}, conPlan = {};
    PA_GRUPOS.forEach(cat => {
        const items = porCat[cat];
        if (!items.length) return;
        if (!items[0].fecha_proximo_despacho) sinPlan[cat] = items;
        else                                   conPlan[cat] = items;
    });

    const limit = limitStr();
    const agendaMap = {};
    Object.entries(conPlan).forEach(([cat, items]) => {
        const cycle = Math.max(1, Math.round(items[0].dias_ciclo || 7));
        let cur = items[0].fecha_proximo_despacho, round = 1;
        while (cur <= limit) {
            if (!agendaMap[cur]) agendaMap[cur] = {};
            agendaMap[cur][cat] = { items, round };
            cur = addDaysStr(cur, cycle); round++;
        }
    });

    const fechasOrdenadas = Object.keys(agendaMap).sort();
    if (!fechasOrdenadas.length) return null;

    const prodConPlan = Object.values(conPlan).flat();
    const stockRonda1 = {};
    if (prodConPlan.length) {
        const fdPron = new FormData();
        fdPron.append('semana_desde', semDesde);
        fdPron.append('semana_hasta', semHasta);
        fdPron.append('semana_corte', semCorte);
        fdPron.append('cod_sucursal', codSuc);
        prodConPlan.forEach(p => {
            fdPron.append('ids_pp[]', p.id_pp);
            fdPron.append(`fechas_d1[${p.id_pp}]`, addDaysStr(p.fecha_proximo_despacho, -1));
        });
        const resPron = await fetch('ajax/pedido_sugerido_pronostico_v2.php', { method:'POST', body:fdPron }).then(r=>r.json());
        if (resPron.ok) Object.entries(resPron.stocks || {}).forEach(([id, val]) => { stockRonda1[String(id)] = val; });
    }

    fechasOrdenadas.forEach(fecha => {
        Object.entries(agendaMap[fecha]).forEach(([cat, slot]) => {
            slot.items.forEach(p => {
                const df  = p.despacho_factor > 0 ? p.despacho_factor : 1;
                const smf = p.stock_max_final ?? 0;
                const cd  = p.cons_diario   ?? 0;
                const dc  = p.dias_ciclo    ?? 7;
                let stockD1Paq;
                if (slot.round === 1) {
                    const su = stockRonda1[String(p.id_pp)];
                    stockD1Paq = (su !== null && su !== undefined) ? Math.max(0, su / df) : null;
                } else {
                    stockD1Paq = Math.max(0, smf - (cd * dc) / df);
                }
                if (!p._porRonda) p._porRonda = {};
                p._porRonda[slot.round] = {
                    stockD1Paq,
                    despachoPron: stockD1Paq !== null ? Math.max(0, Math.ceil(smf - stockD1Paq)) : null
                };
            });
        });
    });

    return { agendaMap, fechasOrdenadas, sinPlan };
    } catch(err) {
        console.warn(`calcularDatosParaSucursal(${codSuc}):`, err);
        return null;
    }
}

async function calcularAgendaConsolidada(semDesde, semHasta, semCorte) {
    const total = PA_SUCURSALES.length;
    const storeResults = {};

    for (let i = 0; i < PA_SUCURSALES.length; i++) {
        const suc = PA_SUCURSALES[i];
        setLoaderStep(`Calculando tienda ${i + 1} de ${total}: ${suc.nombre}…`);
        const datos = await calcularDatosParaSucursal(semDesde, semHasta, semCorte, suc.codigo);
        if (datos) storeResults[suc.codigo] = { ...datos, nombre: suc.nombre, codigo: suc.codigo };
    }

    if (!Object.keys(storeResults).length) {
        hideLoader(); showInicial();
        Swal.fire({ icon:'info', title:'Sin datos', text:'No hay datos para ninguna tienda en el período seleccionado.', confirmButtonColor:'#0ea5e9' });
        return;
    }

    setLoaderStep('Consolidando resultados…');
    const cons = consolidarResultados(storeResults);
    if (!cons.fechasOrdenadas.length) {
        hideLoader(); showInicial();
        Swal.fire({ icon:'info', title:'Sin despachos próximos', text:'No hay despachos programados en las próximas 4 semanas para ninguna tienda.', confirmButtonColor:'#0ea5e9' });
        return;
    }

    renderAgenda(cons.agendaMap, cons.fechasOrdenadas, cons.sinPlan, true, Object.keys(storeResults).length);
    showDatos();
}

function consolidarResultados(storeResults) {
    const allFechas = new Set();
    Object.values(storeResults).forEach(sr => sr.fechasOrdenadas.forEach(f => allFechas.add(f)));
    const fechasOrdenadas = [...allFechas].sort();

    const agendaMap = {};
    const sinPlan   = {};

    fechasOrdenadas.forEach(fecha => {
        agendaMap[fecha] = {};
        PA_GRUPOS.forEach(cat => {
            const tiendas = {};
            Object.entries(storeResults).forEach(([cod, sr]) => {
                if (sr.agendaMap[fecha]?.[cat]) tiendas[cod] = { ...sr.agendaMap[fecha][cat], nombre: sr.nombre };
            });
            if (!Object.keys(tiendas).length) return;

            const byPP = {};
            Object.entries(tiendas).forEach(([cod, slot]) => {
                slot.items.forEach(p => {
                    if (!byPP[p.id_pp]) {
                        byPP[p.id_pp] = {
                            id_pp: p.id_pp, nombre: p.nombre, unidad: p.unidad,
                            categoria_insumo: p.categoria_insumo,
                            despacho_nombre: p.despacho_nombre,
                            despacho_factor: p.despacho_factor,
                            cons_semanal: 0, stock_minimo: 0, stock_maximo: 0, stock_max_final: 0,
                            _stockD1Total: null, _despTotal: null, _porTienda: {}
                        };
                    }
                    const item = byPP[p.id_pp];
                    item.cons_semanal   += p.cons_semanal   ?? 0;
                    item.stock_minimo   += p.stock_minimo   ?? 0;
                    item.stock_maximo   += p.stock_maximo   ?? 0;
                    item.stock_max_final += p.stock_max_final ?? p.stock_maximo ?? 0;

                    const rd = p._porRonda?.[slot.round] ?? {};
                    const sd = rd.stockD1Paq, dp = rd.despachoPron;
                    if (sd !== null && sd !== undefined) item._stockD1Total = (item._stockD1Total ?? 0) + sd;
                    if (dp !== null && dp !== undefined) item._despTotal     = (item._despTotal    ?? 0) + dp;

                    item._porTienda[cod] = {
                        nombre: slot.nombre, round: slot.round,
                        cons_semanal: p.cons_semanal, stock_minimo: p.stock_minimo,
                        stock_maximo: p.stock_maximo, stock_max_final: p.stock_max_final,
                        stockD1Paq: sd, despachoPron: dp
                    };
                });
            });
            agendaMap[fecha][cat] = { items: Object.values(byPP), tiendas, isConsolidado: true };
        });
        if (!Object.keys(agendaMap[fecha]).length) delete agendaMap[fecha];
    });

    Object.values(storeResults).forEach(sr => {
        Object.entries(sr.sinPlan || {}).forEach(([cat]) => { sinPlan[cat] = true; });
    });

    return { agendaMap, fechasOrdenadas: fechasOrdenadas.filter(f => agendaMap[f]), sinPlan };
}

function renderAgenda(agendaMap, fechasOrdenadas, sinPlan, isConsolidado = false, nTiendas = 1) {
    const $w = $('#pa-warnings').empty();
    const spk = Object.keys(sinPlan);
    if (spk.length) {
        const labels = spk.map(c => `<strong>${c} — ${PA_LABELS[c]}</strong>`).join(', ');
        $w.html(`<div class="pa-warning-banner"><i class="bi bi-exclamation-triangle-fill"></i><div>Los siguientes grupos <strong>no tienen Plan de Despacho activo</strong> configurado y no se muestran en la agenda: ${labels}. Verifica la configuración en <em>Configuración Logística</em>.</div></div>`).removeClass('d-none');
    } else {
        $w.addClass('d-none');
    }

    let html = '';
    if (isConsolidado) {
        html += `<div class="pa-consolidado-banner"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Vista Consolidada · <strong>${nTiendas} Tiendas</strong> · Haz clic en una fila para ver el detalle por tienda</div>`;
    }

    fechasOrdenadas.forEach(fecha => {
        const info = formatDateHeader(fecha);
        const cats = agendaMap[fecha];
        html += `
        <div class="pa-date-block">
            <div class="pa-date-header">
                <div class="pa-date-pill">
                    <div class="pa-date-day-num">${info.day}</div>
                    <div class="pa-date-info">
                        <div class="pa-date-weekday">${info.weekday}</div>
                        <div class="pa-date-monthyear">${info.month} ${info.year}</div>
                    </div>
                </div>
                <div class="pa-date-line"></div>
            </div>
            <div class="pa-cats-row">${buildCatsHtml(cats, isConsolidado, fecha)}</div>
        </div>`;
    });

    $('#pa-agenda').html(html || '<p class="text-muted text-center p-5">Sin datos para mostrar.</p>');
}

function buildCatsHtml(cats, isConsolidado, fecha) {
    let html = '';
    PA_GRUPOS.forEach(cat => {
        if (!cats[cat]) return;
        const slot = cats[cat];
        let badge;
        if (isConsolidado) {
            const n = Object.keys(slot.tiendas).length;
            badge = `<span class="pa-round-badge" style="background:rgba(16,185,129,0.12);color:#10b981;">${n} Tienda${n>1?'s':''}</span>`;
        } else {
            badge = slot.round === 1
                ? '<span class="pa-round-badge" style="background:rgba(56,189,248,0.15);color:#38bdf8;">Ronda 1 · Inventario Real</span>'
                : `<span class="pa-round-badge">Ronda ${slot.round} · Proyección</span>`;
        }
        const slotKey = `${fecha.replace(/-/g,'')}-${cat}`;
        html += `
        <div class="pa-cat-card pa-cat-${cat.toLowerCase()}">
            <div class="pa-cat-header">
                <div class="pa-cat-badge">${cat}</div>
                <span>${PA_LABELS[cat] || cat}</span>
                ${badge}
            </div>
            <div class="pa-table-wrap">
                ${buildTablaProductos(slot, isConsolidado, slotKey)}
            </div>
        </div>`;
    });
    return html;
}

function buildTablaProductos(slot, isConsolidado, slotKey) {
    const items = slot.items;
    const round  = slot.round;
    let rows = '';

    items.forEach(p => {
        let stockD1Paq, despPron;
        if (isConsolidado) {
            stockD1Paq = p._stockD1Total;
            despPron   = p._despTotal;
        } else {
            const rd = p._porRonda?.[round] ?? {};
            stockD1Paq = rd.stockD1Paq;
            despPron   = rd.despachoPron;
        }

        const smf = p.stock_max_final ?? 0;
        let stockHtml;
        if (stockD1Paq === null || stockD1Paq === undefined) {
            stockHtml = '<span class="pa-na">Sin datos</span>';
        } else {
            const pct = smf > 0 ? stockD1Paq / smf : 0;
            const cls = pct >= 0.5 ? 'positive' : pct >= 0.25 ? 'low' : 'critical';
            stockHtml = `<span class="pa-stock-d1 ${cls}">${stockD1Paq.toFixed(1)}</span>`;
        }

        let despHtml;
        if (despPron === null || despPron === undefined) {
            despHtml = '<span class="pa-na">—</span>';
        } else {
            despHtml = `<span class="pa-desp-val ${despPron > 0 ? 'needs' : 'ok'}">${despPron}</span>`;
        }

        const despTag = p.despacho_nombre ? `<div class="pa-prod-sub">${esc(p.despacho_nombre)}</div>` : '';

        if (isConsolidado) {
            rows += `
            <tr class="pa-row-expandible" data-pp-id="${p.id_pp}" data-slot-key="${slotKey}">
                <td><i class="bi bi-chevron-right pa-expand-icon"></i><div class="pa-prod-name">${esc(p.nombre)}</div>${despTag}</td>
                <td><span class="pa-unit">${esc(p.unidad || '—')}</span></td>
                <td>${fmt2(p.cons_semanal)}</td>
                <td>${fmt2(p.stock_minimo)}</td>
                <td>${fmt2(p.stock_maximo)}</td>
                <td>${fmt2(p.stock_max_final)}</td>
                <td class="pa-col-desp">${stockHtml}</td>
                <td class="pa-col-desp">${despHtml}</td>
            </tr>
            ${buildSubRowsTiendas(p, slotKey)}`;
        } else {
            rows += `
            <tr>
                <td><div class="pa-prod-name">${esc(p.nombre)}</div>${despTag}</td>
                <td><span class="pa-unit">${esc(p.unidad || '—')}</span></td>
                <td>${fmt2(p.cons_semanal)}</td>
                <td>${fmt2(p.stock_minimo)}</td>
                <td>${fmt2(p.stock_maximo)}</td>
                <td>${p.stock_max_final !== null ? fmt2(p.stock_max_final) : '<span class="pa-na">N/A</span>'}</td>
                <td class="pa-col-desp">${stockHtml}</td>
                <td class="pa-col-desp">${despHtml}</td>
            </tr>`;
        }
    });

    const thead = `<thead><tr>
        <th style="text-align:left">Producto</th>
        <th style="text-align:left">Presentación</th>
        <th>Cons. Semanal<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(en unidades)</small></th>
        <th>Stock Mín</th><th>Stock Máx</th><th>Stock Máx Ajustado</th>
        <th>Pronóstico Inventario</th><th>Despacho</th>
    </tr></thead>`;

    return `<table class="pa-table">${thead}<tbody>${rows || '<tr class="pa-no-data-row"><td colspan="8">Sin productos</td></tr>'}</tbody></table>`;
}

function buildSubRowsTiendas(item, slotKey) {
    let rows = '';
    Object.entries(item._porTienda || {}).forEach(([cod, td]) => {
        const smf = td.stock_max_final ?? 0;
        let sHtml;
        if (td.stockD1Paq === null || td.stockD1Paq === undefined) {
            sHtml = '<span class="pa-na">Sin datos</span>';
        } else {
            const pct = smf > 0 ? td.stockD1Paq / smf : 0;
            const cls = pct >= 0.5 ? 'positive' : pct >= 0.25 ? 'low' : 'critical';
            sHtml = `<span class="pa-stock-d1 ${cls}">${td.stockD1Paq.toFixed(1)}</span>`;
        }
        const dHtml = (td.despachoPron === null || td.despachoPron === undefined)
            ? '<span class="pa-na">—</span>'
            : `<span class="pa-desp-val ${td.despachoPron > 0 ? 'needs' : 'ok'}">${td.despachoPron}</span>`;

        rows += `
        <tr class="pa-tienda-row pa-tienda-sub d-none" data-slot-key="${slotKey}" data-pp-id="${item.id_pp}">
            <td><span class="pa-tienda-badge">${esc(td.nombre)}</span></td>
            <td></td>
            <td>${fmt2(td.cons_semanal)}</td>
            <td>${fmt2(td.stock_minimo)}</td>
            <td>${fmt2(td.stock_maximo)}</td>
            <td>${fmt2(td.stock_max_final)}</td>
            <td class="pa-col-desp">${sHtml}</td>
            <td class="pa-col-desp">${dHtml}</td>
        </tr>`;
    });
    return rows;
}

