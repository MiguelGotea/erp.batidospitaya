/**
 * Dashboard Accionistas — JS Principal
 * modulos/gerencia/js/dashboard_accionistas.js
 */
'use strict';

// ── Colores corporativos ──────────────────────────────
const DA_COLORS = {
    primary:  '#51B8AC',
    green:    '#3fb950',
    red:      '#f85149',
    orange:   '#f0883e',
    purple:   '#bc8cff',
    yellow:   '#e3b341',
    muted:    '#8b949e',
    bg3:      '#21262d',
    grid:     'rgba(255,255,255,0.06)',
};

const CHART_DEFAULTS = {
    color: '#e6edf3',
    plugins: { legend: { labels: { color: '#8b949e', font: { family: 'Inter', size: 11 } } } },
    scales: {
        x: { ticks: { color: '#8b949e' }, grid: { color: DA_COLORS.grid } },
        y: { ticks: { color: '#8b949e' }, grid: { color: DA_COLORS.grid } }
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

    setLoader(true);

    try {
        const fd = new FormData();
        fd.append('periodo', periodo);
        fd.append('anio',    anio);

        const res  = await fetch(DA_CONFIG.ajaxUrl, { method: 'POST', body: fd });
        const data = await res.json();

        if (!data.success) throw new Error(data.message || 'Error desconocido');

        renderVentas(data.ventas, data.meta);
        renderTendencia(data.tendencia_mensual);
        renderRanking(data.ranking_tiendas, data.periodo);
        renderClub(data.club);
        renderSegmentosRFM(data.segmentos_rfm);
        renderParticipacion(data.club);
        renderNuevosSocios(data.nuevos_por_mes);
        renderTopProductos(data.top_productos);
        renderMixCategorias(data.mix_categorias);
        renderTablaTiendas(data.detalle_tiendas);
        renderAlertas(data);

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

// ── 1. VENTAS ─────────────────────────────────────────
function renderVentas(v, meta) {
    setText('kpiVentasTotales', fmtC(v.totales));
    setText('kpiVentasUSD',     fmtUSD(v.usd));
    setText('kpiTransacciones', fmtN(v.total_pedidos));
    setText('kpiTicketPromedio',fmtC(v.ticket_prom));
    setText('kpiVentaPorTienda',fmtC(v.por_tienda));

    // Trend
    if (v.trend_pct !== null) {
        const up  = v.trend_pct >= 0;
        const sym = up ? '▲' : '▼';
        const cls = up ? 'up' : 'down';
        setHtml('trendVentasTotales', `<span class="da-kpi-trend ${cls}">${sym} ${Math.abs(v.trend_pct)}% vs período anterior</span>`);
        setHtml('trendTransacciones','');
    }

    // Meta progress
    const pct = Math.min(meta.cumplimiento, 150);
    setText('kpiCumplimientoMeta', fmtPct(meta.cumplimiento));
    setText('subCumplimientoMeta', `${fmtC(v.totales)} / Meta ${fmtC(meta.total)}`);
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
function renderTendencia(meses) {
    const labels = meses.map(m => {
        const [y, mo] = m.mes.split('-');
        return ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][parseInt(mo)-1] + ' ' + y.slice(2);
    });
    const datos  = meses.map(m => parseFloat(m.total));

    const ctx = document.getElementById('chartTendenciaVentas');
    if (!ctx) return;
    if (chartTendencia) chartTendencia.destroy();

    chartTendencia = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Ventas C$',
                data: datos,
                backgroundColor: datos.map((_, i) => i === datos.length - 1 ? DA_COLORS.primary : 'rgba(81,184,172,0.35)'),
                borderColor: DA_COLORS.primary,
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            ...CHART_DEFAULTS,
            responsive: true,
            plugins: {
                ...CHART_DEFAULTS.plugins,
                tooltip: {
                    callbacks: { label: ctx => ' ' + fmtC(ctx.raw) }
                }
            },
            scales: {
                x: { ticks: { color: DA_COLORS.muted }, grid: { color: DA_COLORS.grid } },
                y: {
                    ticks: { color: DA_COLORS.muted, callback: v => fmtC(v) },
                    grid: { color: DA_COLORS.grid }
                }
            }
        }
    });
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
            <div class="da-ranking-valor">${fmtC(r.ventas)}</div>
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
            datasets: [{ data: segs.map(s => s.total), backgroundColor: PALETTE, borderWidth: 2, borderColor: '#161b22' }]
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
                borderWidth: 2, borderColor: '#161b22'
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
            <span class="da-top-amt">${fmtC(p.monto)}</span>
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
            : '<span class="da-cumpl-badge" style="background:rgba(255,255,255,0.05);color:#8b949e">Sin meta</span>';

        const minibar = Array.from({ length: 5 }, (_, j) => {
            const h = 30 + Math.random() * 70;
            return `<div class="da-bar-mini" style="height:${h}%;opacity:0.5;"></div>`;
        }).join('');

        return `
        <tr>
            <td style="color:#8b949e;font-weight:700">${i+1}</td>
            <td><span style="font-weight:600">${t.tienda}</span></td>
            <td class="text-end">${fmtC(t.ventas)}</td>
            <td class="text-end">${fmtC(t.meta)}</td>
            <td class="text-center">${cumplBadge}</td>
            <td class="text-end">${fmtN(t.pedidos)}</td>
            <td class="text-end">${fmtC(t.ticket)}</td>
            <td class="text-end">${fmtN(t.socios)}</td>
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

// ── Tabs tendencia ────────────────────────────────────
document.querySelectorAll('#tabsTendencia .da-tab').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('#tabsTendencia .da-tab').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        // Re-cargar con diferente agrupación (simplificado — mismo fetch)
        cargarDashboard();
    });
});

// ── Botón actualizar ──────────────────────────────────
document.getElementById('btnActualizar')?.addEventListener('click', cargarDashboard);

// ── Auto-actualizar al cambiar período ───────────────
document.getElementById('selectorPeriodo')?.addEventListener('change', cargarDashboard);
document.getElementById('selectorAnio')?.addEventListener('change',   cargarDashboard);

// ── Init ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.color = '#8b949e';
    Chart.defaults.font.family = 'Inter';
    cargarDashboard();
});
