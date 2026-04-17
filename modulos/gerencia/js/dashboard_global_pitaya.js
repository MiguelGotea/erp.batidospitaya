/**
 * Dashboard Global Pitaya — JS Principal
 * modulos/gerencia/js/dashboard_global_pitaya.js
 */
'use strict';

// ── Estado de moneda ──────────────────────────────────
let DA_MONEDA  = 'COR'; // 'COR' | 'USD'
let DA_TC      = 36.5;  // tipo de cambio C$ por 1 US$
let DA_LAST    = null;  // última respuesta del servidor

function convertir(monto) {
    return DA_MONEDA === 'USD' ? monto / DA_TC : monto;
}
function fmtMoney(n) {
    if (n === null || n === undefined) return '—';
    const v = convertir(n);
    if (DA_MONEDA === 'USD') return fmtUSD(v);
    return fmtC(v);
}
function simbolo() {
    return DA_MONEDA === 'USD' ? 'US$' : 'C$';
}

// ── Colores corporativos — paleta cálida clara ───────
const DA_COLORS = {
    primary:  '#51B8AC',
    primaryD: '#0E544C',
    green:    '#3aaa82',
    red:      '#d9534f',
    orange:   '#e07b39',
    purple:   '#7c6bc9',
    yellow:   '#c9a227',
    muted:    '#a8b4ae',
    bg3:      '#e0ddd8',
    grid:     'rgba(0,0,0,0.06)',
    text:     '#2d3a35',
};

const CHART_DEFAULTS = {
    color: '#2d3a35',
    plugins: { legend: { labels: { color: '#7a8a84', font: { family: 'Inter', size: 11 } } } },
    scales: {
        x: { ticks: { color: '#7a8a84' }, grid: { color: DA_COLORS.grid } },
        y: { ticks: { color: '#7a8a84' }, grid: { color: DA_COLORS.grid } }
    }
};

// ── Referencias ──────────────────────────────────────
let chartTendencia = null;
let chartRFM       = null;
let chartPart      = null;
let chartNuevos    = null;
let chartMix       = null;

// ── Helper: formato moneda ───────────────────────────
function fmtC(n) {
    if (n === null || n === undefined) return '—';
    if (n >= 1_000_000) return 'C$ ' + (n / 1_000_000).toFixed(1) + 'M';
    if (n >= 1_000)     return 'C$ ' + (n / 1_000).toFixed(1) + 'K';
    return 'C$ ' + parseFloat(n).toLocaleString('es-NI', { minimumFractionDigits: 0 });
}
function fmtN(n, dec = 0) {
    if (n === null || n === undefined) return '—';
    return parseFloat(n).toLocaleString('es-NI', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}
function fmtPct(n) {
    if (n === null || n === undefined) return '—';
    return parseFloat(n).toFixed(1) + '%';
}
function fmtUSD(n) {
    if (n === null || n === undefined) return '—';
    if (n >= 1_000_000) return '$ ' + (n / 1_000_000).toFixed(2) + 'M';
    if (n >= 1_000)     return '$ ' + (n / 1_000).toFixed(1) + 'K';
    return '$ ' + parseFloat(n).toLocaleString('en-US', { minimumFractionDigits: 0 });
}

// ── Carga principal ──────────────────────────────────
async function cargarDashboard() {
    const periodo = document.getElementById('selectorPeriodo').value;
    const anio    = document.getElementById('selectorAnio').value;
    DA_TC = parseFloat(document.getElementById('inputTipoCambio')?.value) || 36.5;

    setLoader(true);

    try {
        const fd = new FormData();
        fd.append('periodo', periodo);
        fd.append('anio',    anio);

        const res  = await fetch(DA_CONFIG.ajaxUrl, { method: 'POST', body: fd });
        const data = await res.json();
        DA_LAST = data;

        if (!data.success) throw new Error(data.message || 'Error desconocido');

        renderTodo(data);

    } catch (err) {
        console.error(err);
        Swal.fire({ icon: 'error', title: 'Error', text: err.message, background: '#161b22', color: '#e6edf3' });
    } finally {
        setLoader(false);
    }
}

function setLoader(on) {
    document.getElementById('daLoader').classList.toggle('active', on);
}

function renderTodo(data) {
    renderVentas(data.ventas, data.meta);
    // Gráfica tendencia según tab activo
    const tabActivo = document.querySelector('#tabsTendencia .da-tab.active')?.dataset.tab ?? 'mensual';
    if (tabActivo === 'anual' && data.expansion?.ventas_por_anio) {
        renderTendenciaAnual(data.expansion.ventas_por_anio);
    } else {
        renderTendenciaMensual(data.tendencia_mensual);
    }
    renderRanking(data.ranking_tiendas, data.periodo);
    renderClub(data.club);
    renderSegmentosRFM(data.segmentos_rfm);
    renderParticipacion(data.club);
    renderNuevosSocios(data.nuevos_por_mes);
    renderTopProductos(data.top_productos);
    renderMixCategorias(data.mix_categorias);
    renderTablaTiendas(data.detalle_tiendas);
    if (data.expansion) renderExpansion(data.expansion);
    renderAlertas(data);
}

// ── 1. VENTAS ─────────────────────────────────────────
function renderVentas(v, meta) {
    // Label dinámico según moneda
    setText('labelVentasTotales', `Ventas Totales (${simbolo()})`);
    setText('kpiVentasTotales', fmtMoney(v.totales));
    setText('kpiTransacciones', fmtN(v.total_pedidos));
    setText('kpiTicketPromedio',fmtMoney(v.ticket_prom));
    setText('kpiVentaPorTienda',fmtMoney(v.por_tienda));

    // Trend
    if (v.trend_pct !== null) {
        const up  = v.trend_pct >= 0;
        const sym = up ? '▲' : '▼';
        const cls = up ? 'up' : 'down';
        setHtml('trendVentasTotales', `<span class="da-kpi-trend ${cls}">${sym} ${Math.abs(v.trend_pct)}% vs período anterior</span>`);
    }

    // Meta progress
    const pct = Math.min(meta.cumplimiento, 150);
    setText('kpiCumplimientoMeta', fmtPct(meta.cumplimiento));
    setText('subCumplimientoMeta', `${fmtMoney(v.totales)} / Meta ${fmtMoney(meta.total)}`);
    const bar = document.getElementById('progressMeta');
    if (bar) {
        setTimeout(() => { bar.style.width = pct + '%'; }, 100);
        bar.style.background = meta.cumplimiento >= 100
            ? 'linear-gradient(90deg,#3fb950,#51B8AC)'
            : meta.cumplimiento >= 80
                ? 'linear-gradient(90deg,#e3b341,#f0883e)'
                : 'linear-gradient(90deg,#f85149,#bc8cff)';
    }
}

// ── 2. TENDENCIA VENTAS ───────────────────────────────
function buildTendenciaChart(labels, datos, labelStr) {
    const ctx = document.getElementById('chartTendenciaVentas');
    if (!ctx) return;
    if (chartTendencia) chartTendencia.destroy();
    const dataConv = datos.map(d => convertir(d));
    chartTendencia = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: labelStr,
                data: dataConv,
                backgroundColor: dataConv.map((_, i) => i === dataConv.length - 1 ? DA_COLORS.primary : 'rgba(81,184,172,0.35)'),
                borderColor: DA_COLORS.primary,
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ' ' + fmtMoney(ctx.raw * DA_TC) } }
            },
            scales: {
                x: { ticks: { color: DA_COLORS.muted }, grid: { color: DA_COLORS.grid } },
                y: {
                    ticks: { color: DA_COLORS.muted, callback: v => simbolo() + ' ' + fmtN(v) },
                    grid: { color: DA_COLORS.grid }
                }
            }
        }
    });
}

function renderTendenciaMensual(meses) {
    const labels = meses.map(m => {
        const [y, mo] = m.mes.split('-');
        return ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][parseInt(mo)-1] + " '" + y.slice(2);
    });
    buildTendenciaChart(labels, meses.map(m => parseFloat(m.total)), 'Ventas Mensual');
}

function renderTendenciaAnual(anios) {
    const labels = anios.map(a => String(a.anio));
    buildTendenciaChart(labels, anios.map(a => parseFloat(a.ventas)), 'Ventas Anual');
}

// ── 3. RANKING TIENDAS ────────────────────────────────
function renderRanking(ranking, periodo) {
    const cont = document.getElementById('rankingTiendas');
    if (!cont) return;

    const max = ranking.length ? Math.max(...ranking.map(r => r.ventas)) : 1;
    const badgePer = document.getElementById('badgePeriodoRanking');
    if (badgePer && periodo) badgePer.textContent = `${periodo.ini} → ${periodo.fin}`;

    cont.innerHTML = ranking.slice(0, 12).map((r, i) => {
        const pct    = max > 0 ? (r.ventas / max * 100) : 0;
        const posClass = i === 0 ? 'gold' : i === 1 ? 'silver' : i === 2 ? 'bronze' : 'normal';
        const cumpl  = r.cumplimiento !== null ? `<span style="font-size:.72rem;color:${r.cumplimiento>=100?DA_COLORS.green:r.cumplimiento>=80?DA_COLORS.orange:DA_COLORS.red}">${fmtPct(r.cumplimiento)}</span>` : '';
        return `
        <div class="da-ranking-item">
            <div class="da-ranking-pos ${posClass}">${i+1}</div>
            <div class="da-ranking-name">${r.tienda} ${cumpl}</div>
            <div class="da-ranking-bar-wrap"><div class="da-ranking-bar" style="width:${pct}%"></div></div>
            <div class="da-ranking-valor">${fmtMoney(r.ventas)}</div>
        </div>`;
    }).join('');
}

// ── 4. CLUB PITAYA ────────────────────────────────────
function renderClub(club) {
    setText('kpiTotalMembresias', fmtN(club.total_membresias));
    setText('kpiSociosActivos',   fmtN(club.socios_activos));
    setText('subSociosActivos',   `${fmtPct(club.socios_activos / club.universo * 100)} del universo`);
    setText('kpiNuevosSocios',    fmtN(club.nuevos));
    setText('kpiChurn',           fmtPct(club.churn_rate));
    setText('kpiLTVPromedio',     fmtC(club.ltv_promedio));
}

// ── 5. RFM SEGMENTOS ──────────────────────────────────
function renderSegmentosRFM(segs) {
    const ctx = document.getElementById('chartRFMSegmentos');
    if (!ctx) return;
    if (chartRFM) chartRFM.destroy();

    const PALETTE = [DA_COLORS.primary, DA_COLORS.green, DA_COLORS.orange, DA_COLORS.red, DA_COLORS.purple];
    chartRFM = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: segs.map(s => s.segmento),
            datasets: [{ data: segs.map(s => s.total), backgroundColor: PALETTE, borderWidth: 2, borderColor: '#f0ede8' }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { color: DA_COLORS.muted, font: { size: 11 } } },
                tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${fmtN(ctx.raw)}` } }
            }
        }
    });
}

// ── 6. PARTICIPACIÓN CLUB ─────────────────────────────
function renderParticipacion(club) {
    const ctx = document.getElementById('chartParticipacionClub');
    if (!ctx) return;
    if (chartPart) chartPart.destroy();

    const club_pct  = club.participacion;
    const otros_pct = Math.max(0, 100 - club_pct);
    chartPart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Club Pitaya', 'Clientes Generales'],
            datasets: [{
                data: [club_pct, otros_pct],
                backgroundColor: [DA_COLORS.primary, DA_COLORS.bg3],
                borderWidth: 2, borderColor: '#f0ede8'
            }]
        },
        options: {
            responsive: true, cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { color: DA_COLORS.muted, font: { size: 11 } } },
                tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${fmtPct(ctx.raw)}` } }
            }
        }
    });
}

// ── 7. NUEVOS SOCIOS / MES ────────────────────────────
function renderNuevosSocios(datos) {
    const ctx = document.getElementById('chartNuevosSocios');
    if (!ctx) return;
    if (chartNuevos) chartNuevos.destroy();

    const labels = datos.map(d => {
        const [y, m] = d.mes.split('-');
        return ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][parseInt(m)-1];
    });

    chartNuevos = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Nuevos Socios',
                data: datos.map(d => parseInt(d.total)),
                borderColor: DA_COLORS.green,
                backgroundColor: 'rgba(63,185,80,0.1)',
                fill: true, tension: 0.4, pointRadius: 4,
                pointBackgroundColor: DA_COLORS.green,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${fmtN(ctx.raw)} socios` } } },
            scales: {
                x: { ticks: { color: DA_COLORS.muted }, grid: { color: DA_COLORS.grid } },
                y: { ticks: { color: DA_COLORS.muted }, grid: { color: DA_COLORS.grid } }
            }
        }
    });
}

// ── 8. TOP PRODUCTOS ──────────────────────────────────
function renderTopProductos(productos) {
    const cont = document.getElementById('topProductos');
    if (!cont || !productos.length) return;
    const max = Math.max(...productos.map(p => parseFloat(p.monto)));
    cont.innerHTML = productos.map((p, i) => {
        const pct = max > 0 ? (parseFloat(p.monto) / max * 100) : 0;
        return `
        <div class="da-top-item">
            <span class="da-top-rank">${i+1}</span>
            <span class="da-top-name" title="${p.producto}">${p.producto.length > 28 ? p.producto.slice(0,25)+'…' : p.producto}</span>
            <span class="da-top-qty">${fmtN(p.cantidad)} uds</span>
            <div class="da-top-bar-wrap"><div class="da-top-bar" style="width:${pct}%"></div></div>
            <span class="da-top-amt">${fmtMoney(p.monto)}</span>
        </div>`;
    }).join('');
}

// ── 9. MIX POR CATEGORÍA ─────────────────────────────
function renderMixCategorias(cats) {
    const ctx = document.getElementById('chartMixCategorias');
    if (!ctx) return;
    if (chartMix) chartMix.destroy();

    const PALETTE = [DA_COLORS.primary, DA_COLORS.green, DA_COLORS.orange, DA_COLORS.purple,
                     DA_COLORS.yellow, DA_COLORS.red, '#20b2aa', '#6495ed'];
    chartMix = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: cats.map(c => c.categoria),
            datasets: [{
                label: 'Ventas C$',
                data: cats.map(c => parseFloat(c.monto)),
                backgroundColor: cats.map((_, i) => PALETTE[i % PALETTE.length]),
                borderRadius: 6, borderWidth: 0,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ' ' + fmtC(ctx.raw) } }
            },
            scales: {
                x: { ticks: { color: DA_COLORS.muted, callback: v => fmtC(v) }, grid: { color: DA_COLORS.grid } },
                y: { ticks: { color: DA_COLORS.muted }, grid: { color: DA_COLORS.grid } }
            }
        }
    });
}

// ── 10. TABLA TIENDAS ─────────────────────────────────
function renderTablaTiendas(tiendas) {
    const tbody = document.getElementById('tbodyTiendas');
    if (!tbody) return;

    tbody.innerHTML = tiendas.map((t, i) => {
        const cumplBadge = t.cumplimiento !== null
            ? `<span class="da-cumpl-badge ${t.cumplimiento >= 100 ? 'da-cumpl-high' : t.cumplimiento >= 80 ? 'da-cumpl-mid' : 'da-cumpl-low'}">${fmtPct(t.cumplimiento)}</span>`
            : '<span class="da-cumpl-badge" style="color:#a8b4ae;">Sin meta</span>';
        const minibar = Array.from({ length: 5 }, () => {
            const h = 30 + Math.random() * 70;
            return `<div class="da-bar-mini" style="height:${h}%;opacity:0.55;"></div>`;
        }).join('');
        return `
        <tr>
            <td style="color:#a8b4ae;font-weight:700">${i+1}</td>
            <td><span style="font-weight:600">${t.tienda}</span></td>
            <td class="text-end">${fmtMoney(t.ventas)}</td>
            <td class="text-end">${fmtMoney(t.meta)}</td>
            <td class="text-center">${cumplBadge}</td>
            <td class="text-end">${fmtN(t.pedidos)}</td>
            <td class="text-end">${fmtMoney(t.ticket)}</td>
            <td class="text-end">${fmtN(t.miembros_club ?? t.socios ?? 0)}</td>
            <td class="text-center"><div class="da-tendencia-mini">${minibar}</div></td>
        </tr>`;
    }).join('');

    // Buscador
    document.getElementById('buscadorTiendas')?.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        tbody.querySelectorAll('tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

// ── 11. ALERTAS ───────────────────────────────────────
function renderAlertas(data) {
    const cont = document.getElementById('panelAlertas');
    if (!cont) return;

    const alertas = [];
    const v = data.ventas, m = data.meta, club = data.club;

    // Cumplimiento general
    if (m.cumplimiento < 80 && m.total > 0)
        alertas.push({ tipo: 'danger', icon: 'fas fa-exclamation-circle',
            titulo: `Cumplimiento de meta bajo: ${fmtPct(m.cumplimiento)}`,
            desc: `Las ventas del período (${fmtC(v.totales)}) están por debajo del 80% de la meta (${fmtC(m.total)}). Se requiere acción inmediata.` });
    else if (m.cumplimiento >= 100 && m.total > 0)
        alertas.push({ tipo: 'success', icon: 'fas fa-check-circle',
            titulo: `¡Meta cumplida! ${fmtPct(m.cumplimiento)}`,
            desc: `Excelente desempeño. Las ventas superan la meta del período por ${fmtC(v.totales - m.total)}.` });
    else if (m.cumplimiento >= 80 && m.total > 0)
        alertas.push({ tipo: 'warning', icon: 'fas fa-clock',
            titulo: `Meta al ${fmtPct(m.cumplimiento)} — Falta ${fmtC(m.total - v.totales)}`,
            desc: `Se está en trayectoria aceptable pero aún se requiere un esfuerzo adicional para alcanzar la meta.` });

    // Churn
    if (club.churn_rate > 35)
        alertas.push({ tipo: 'danger', icon: 'fas fa-user-slash',
            titulo: `Churn Rate crítico: ${fmtPct(club.churn_rate)}`,
            desc: `Más de un tercio de los socios no han comprado en los últimos 60 días. Se recomienda campaña de reactivación.` });
    else if (club.churn_rate > 20)
        alertas.push({ tipo: 'warning', icon: 'fas fa-exclamation-triangle',
            titulo: `Churn Rate elevado: ${fmtPct(club.churn_rate)}`,
            desc: `${fmtPct(club.churn_rate)} de socios están inactivos. Considera acciones de fidelización y cupones de retorno.` });

    // Tendencia ventas
    if (v.trend_pct !== null && v.trend_pct < -10)
        alertas.push({ tipo: 'danger', icon: 'fas fa-arrow-down',
            titulo: `Ventas cayeron ${Math.abs(v.trend_pct)}% vs período anterior`,
            desc: `Las ventas totales bajaron de ${fmtC(v.prev_total)} a ${fmtC(v.totales)}. Revisar tiendas con bajo desempeño.` });
    else if (v.trend_pct !== null && v.trend_pct > 10)
        alertas.push({ tipo: 'success', icon: 'fas fa-arrow-up',
            titulo: `Ventas crecieron ${v.trend_pct}% vs período anterior`,
            desc: `Crecimiento positivo de ${fmtC(v.totales - v.prev_total)} adicionales respecto al período anterior.` });

    // Expansión
    alertas.push({ tipo: 'info', icon: 'fas fa-rocket',
        titulo: 'Plan Expansión 2028 — 14 / 40 Tiendas',
        desc: 'Se necesitan ~9 aperturas por año para alcanzar las 40 tiendas en 2028. El potencial de incremento en ventas es del +186%.' });

    // Participación club
    if (club.participacion < 30)
        alertas.push({ tipo: 'warning', icon: 'fas fa-id-card',
            titulo: `Club representa solo ${fmtPct(club.participacion)} de ventas`,
            desc: 'La participación del Club Pitaya en ventas es baja. Reforzar captación de socios en tienda.' });

    if (!alertas.length) {
        cont.innerHTML = '<div class="da-loading-row" style="color:#3fb950"><i class="fas fa-check-circle me-2"></i>Todos los indicadores dentro de parámetros normales.</div>';
        return;
    }
    cont.innerHTML = alertas.map(a => `
    <div class="da-alerta alerta-${a.tipo}">
        <i class="da-alerta-icon ${a.icon}"></i>
        <div>
            <div class="da-alerta-title">${a.titulo}</div>
            <div class="da-alerta-desc">${a.desc}</div>
        </div>
    </div>`).join('');
}

// ── Helpers DOM ───────────────────────────────────────
function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}
function setHtml(id, val) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = val;
}

// ── EXPANSIÓN ─────────────────────────────────────────
let chartExpTiendas = null;
let chartVentasAnio  = null;

function renderExpansion(exp) {
    // KPIs
    setText('expTiendasActivas',   fmtN(exp.tiendas_actuales));
    setText('expAvancePct',        fmtPct(exp.avance_pct));
    setText('expApertNecesarias',  exp.aperturas_necesarias + '/año');
    const primera = exp.sucursales[0];
    if (primera) setText('expPrimeraApertura', primera.fecha_apertura?.slice(0,7) ?? '—');
    const progressEl = document.getElementById('progressExpansion');
    if (progressEl) setTimeout(() => { progressEl.style.width = exp.avance_pct + '%'; }, 100);

    // Crecimiento ventas histórico
    const vAnios = exp.ventas_por_anio;
    if (vAnios.length >= 2) {
        const v0 = parseFloat(vAnios[0].ventas), vN = parseFloat(vAnios[vAnios.length-1].ventas);
        const crecPct = v0 > 0 ? Math.round((vN - v0)/v0*100) : 0;
        setText('expCrecimientoVentas', '+' + fmtN(crecPct) + '%');
    }

    // Chart tiendas vs proyección
    const ctxT = document.getElementById('chartExpansionTiendas');
    if (ctxT) {
        if (chartExpTiendas) chartExpTiendas.destroy();
        // Combinar histórico + proyección
        const histAnios  = exp.acumulado_por_anio.map(r => r.anio);
        const histAcum   = exp.acumulado_por_anio.map(r => r.acumulado);
        const histNuevas = exp.acumulado_por_anio.map(r => r.nuevas);
        const proyAnios  = exp.proyeccion.map(r => r.anio);
        const proyVals   = exp.proyeccion.map(r => r.proyectado);
        // Unir todos los años
        const todosAnios = [...new Set([...histAnios, ...proyAnios])].sort();
        const acumData   = todosAnios.map(a => { const r = exp.acumulado_por_anio.find(x=>x.anio===a); return r ? r.acumulado : null; });
        const proyData   = todosAnios.map(a => { const r = exp.proyeccion.find(x=>x.anio===a); return r ? r.proyectado : null; });
        const nuevasData = todosAnios.map(a => { const r = exp.acumulado_por_anio.find(x=>x.anio===a); return r ? r.nuevas : 0; });

        chartExpTiendas = new Chart(ctxT, {
            data: {
                labels: todosAnios,
                datasets: [
                    { type:'bar',  label:'Nuevas ese año', data: nuevasData, backgroundColor:'rgba(81,184,172,0.4)', borderRadius:4, yAxisID:'y1' },
                    { type:'line', label:'Acumulado real', data: acumData,   borderColor: DA_COLORS.primary, backgroundColor:'rgba(81,184,172,0.1)', fill:true, tension:0.3, pointRadius:5 },
                    { type:'line', label:'Proyección → 40', data: proyData,  borderColor: DA_COLORS.orange, borderDash:[6,4], pointRadius:3, fill:false, tension:0.2 },
                ]
            },
            options: {
                responsive: true,
                interaction: { mode:'index', intersect:false },
                plugins: { legend:{ labels:{ color: DA_COLORS.muted, font:{size:11} } }, tooltip:{callbacks:{label:ctx=>` ${ctx.dataset.label}: ${ctx.raw}`}} },
                scales: {
                    x:  { ticks:{color:DA_COLORS.muted}, grid:{color:DA_COLORS.grid} },
                    y:  { ticks:{color:DA_COLORS.muted, stepSize:2}, grid:{color:DA_COLORS.grid}, title:{display:true,text:'Tiendas acum.',color:DA_COLORS.muted} },
                    y1: { position:'right', ticks:{color:DA_COLORS.muted}, grid:{display:false}, title:{display:true,text:'Nuevas/año',color:DA_COLORS.muted} },
                }
            }
        });
    }

    // Chart ventas por año
    const ctxV = document.getElementById('chartVentasAnio');
    if (ctxV && exp.ventas_por_anio.length) {
        if (chartVentasAnio) chartVentasAnio.destroy();
        chartVentasAnio = new Chart(ctxV, {
            type:'bar',
            data: {
                labels: exp.ventas_por_anio.map(r=>r.anio),
                datasets: [{ label:'Ventas', data: exp.ventas_por_anio.map(r=>convertir(parseFloat(r.ventas))),
                    backgroundColor: exp.ventas_por_anio.map((_,i)=> i===exp.ventas_por_anio.length-1?DA_COLORS.primary:'rgba(81,184,172,0.35)'),
                    borderRadius:6 }]
            },
            options: {
                responsive:true,
                plugins:{ legend:{display:false}, tooltip:{callbacks:{label:ctx=>` ${fmtMoney(convertir(ctx.raw) === ctx.raw ? ctx.raw * DA_TC : ctx.raw)}`}} },
                scales: {
                    x:{ticks:{color:DA_COLORS.muted},grid:{color:DA_COLORS.grid}},
                    y:{ticks:{color:DA_COLORS.muted, callback:v=>simbolo()+' '+fmtN(v)},grid:{color:DA_COLORS.grid}}
                }
            }
        });
    }

    // Tabla de aperturas
    const tbA = document.getElementById('tbodyAperturas');
    if (!tbA) return;
    const totalVentas = exp.sucursales.reduce((s,r)=>s+r.ventas_historico, 0) || 1;
    const hoyTs = Date.now();
    tbA.innerHTML = exp.sucursales.map((s,i) => {
        const anosOp = s.fecha_apertura ? Math.floor((hoyTs - new Date(s.fecha_apertura).getTime())/31536000000) : '?';
        const pctVentas = (s.ventas_historico/totalVentas*100).toFixed(1);
        return `<tr>
            <td style="color:#a8b4ae;font-weight:700">${i+1}</td>
            <td style="font-weight:600">${s.nombre}</td>
            <td>${s.fecha_apertura ?? '—'}</td>
            <td class="text-end">${fmtMoney(s.ventas_historico)}</td>
            <td class="text-center">${anosOp} años</td>
            <td class="text-center">
                <div style="display:flex;align-items:center;gap:6px;justify-content:center">
                    <div style="width:80px;height:7px;background:#cac7c2;border-radius:50px;overflow:hidden;box-shadow:inset 2px 2px 4px #b5b2ad,inset -2px -2px 4px #fff">
                        <div style="width:${pctVentas}%;height:100%;background:linear-gradient(90deg,#51B8AC,#0E544C);border-radius:50px"></div>
                    </div>
                    <span style="font-size:.78rem;color:#7a8a84">${pctVentas}%</span>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── Toggle Moneda ─────────────────────────────────────
document.querySelectorAll('.da-cur-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        DA_MONEDA = this.dataset.moneda;
        DA_TC = parseFloat(document.getElementById('inputTipoCambio')?.value) || 36.5;
        document.querySelectorAll('.da-cur-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        if (DA_LAST) renderTodo(DA_LAST);
    });
});
document.getElementById('inputTipoCambio')?.addEventListener('change', function() {
    DA_TC = parseFloat(this.value) || 36.5;
    if (DA_LAST) renderTodo(DA_LAST);
});

// ── Tabs tendencia ────────────────────────────────────
document.querySelectorAll('#tabsTendencia .da-tab').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('#tabsTendencia .da-tab').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        if (!DA_LAST) return;
        // Cambiar sin recargar servidor
        if (this.dataset.tab === 'anual' && DA_LAST.expansion?.ventas_por_anio) {
            renderTendenciaAnual(DA_LAST.expansion.ventas_por_anio);
        } else {
            renderTendenciaMensual(DA_LAST.tendencia_mensual);
        }
    });
});

// ── Botón actualizar ──────────────────────────────────
document.getElementById('btnActualizar')?.addEventListener('click', cargarDashboard);
document.getElementById('selectorPeriodo')?.addEventListener('change', cargarDashboard);
document.getElementById('selectorAnio')?.addEventListener('change',   cargarDashboard);

// ── Init ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.color = '#7a8a84';
    Chart.defaults.font.family = 'Inter';
    cargarDashboard();
});
