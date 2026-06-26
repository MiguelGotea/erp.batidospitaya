/* ============================================================
   PRONÓSTICO DE ABASTECIMIENTO — CHART LOGIC
   js/pronostico_charts.js
   Reutiliza la lógica gráfica de dashboard_consumo.js
   ============================================================ */

'use strict';

const SUCURSAL_COLORS = [
    { bg: 'rgba(52, 152, 219, 0.2)', border: '#2980b9' },
    { bg: 'rgba(231, 76, 60, 0.2)', border: '#c0392b' },
    { bg: 'rgba(46, 204, 113, 0.2)', border: '#27ae60' },
    { bg: 'rgba(155, 89, 182, 0.2)', border: '#8e44ad' },
    { bg: 'rgba(241, 196, 15, 0.2)', border: '#f39c12' },
    { bg: 'rgba(26, 188, 156, 0.2)', border: '#16a085' },
    { bg: 'rgba(230, 126, 34, 0.2)', border: '#d35400' },
    { bg: 'rgba(52, 73, 94, 0.2)', border: '#2c3e50' },
    { bg: 'rgba(236, 240, 241, 0.2)', border: '#bdc3c7' },
    { bg: 'rgba(149, 165, 166, 0.2)', border: '#7f8c8d' }
];

let globalDatosConsumo = null; // caché del ajax/dashboard_consumo_get_datos.php
let instanciasCharts = {}; // { chartId: ChartInstance }

function formatNum(n) {
    if (n === null || n === undefined || isNaN(n)) return '0';
    const num = parseFloat(n);
    if (num === 0) return '0';
    if (Math.abs(num - Math.round(num)) < 0.001) return num.toLocaleString('es-NI');
    return num.toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}

function round2(n) {
    return Math.round(parseFloat(n) * 100) / 100;
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

window.cargarGraficasParaFila = async function (ppId, sk, sucursal, semDesde, semHasta, semCorte, fechaDespacho, cicloSlot) {
    const canvasTend = document.getElementById(`chart-tend-${sk}-${ppId}`);
    const canvasKard = document.getElementById(`chart-kardex-${sk}-${ppId}`);
    if (!canvasTend || !canvasKard) return;

    // Calcular fecha pronóstico: fecha despacho + cicloSlot
    const d = new Date(fechaDespacho + 'T12:00:00');
    d.setDate(d.getDate() + Math.round(cicloSlot));
    let fechaPronostico = d.toISOString().split('T')[0];
    if (sk && sk.endsWith('-HOY')) {
        const ayer = new Date();
        ayer.setDate(ayer.getDate() - 1);
        fechaPronostico = ayer.toISOString().split('T')[0];
    }

    // Mostrar loaders o placeholders opcionalmente
    // 1. Cargar datos de consumo si no están en caché
    if (!globalDatosConsumo || globalDatosConsumo._semDesde !== semDesde || globalDatosConsumo._semHasta !== semHasta || globalDatosConsumo._sucursal !== sucursal) {
        const fd = new FormData();
        fd.append('semana_desde_num', semDesde);
        fd.append('semana_hasta_num', semHasta);
        fd.append('sucursales[]', sucursal);

        try {
            const res = await fetch('ajax/dashboard_consumo_get_datos.php', { method: 'POST', body: fd }).then(r => r.json());
            if (res.ok) {
                globalDatosConsumo = res;
                globalDatosConsumo._semDesde = semDesde;
                globalDatosConsumo._semHasta = semHasta;
                globalDatosConsumo._sucursal = sucursal;
            }
        } catch (e) { console.error('Error fetching consumo', e); }
    }

    if (globalDatosConsumo) {
        renderChartTendencia(canvasTend, globalDatosConsumo, ppId, sk);
    }

    // 2. Cargar Kardex
    cargarChartKardex(canvasKard, ppId, semDesde, semHasta, semCorte, sucursal, fechaPronostico, sk);
};

function renderChartTendencia(canvas, data, idInsumoSel, sk) {
    const item = data.consumo.find(c => c.id == idInsumoSel);
    if (!item) return;

    const labels = data.semanas.map(s => `${s.numero_semana}`);
    const semanasNros = data.semanas.map(s => s.numero_semana);
    const prom = item.prom_semana || 0;

    // ── Excluir semana en curso del promedio y proyeccin ────────────────────
    const semanaActual = parseInt($('.pa-badge-current-week strong').text()) || 0;
    const esSemActualEnRango = semanaActual > 0 && semanasNros.includes(semanaActual);

    // Semanas completas: excluir la semana en curso si est dentro del rango
    const semanasCalc = esSemActualEnRango
        ? semanasNros.filter(n => n !== semanaActual)
        : semanasNros;

    // Promedio recalculado sin semana en curso
    let promCalc = prom;
    const valsCalc = semanasCalc.map(n => item.por_semana[n] || 0);
    if (semanasCalc.length > 0) {
        const valsPos = valsCalc.filter(v => v > 0);
        promCalc = valsPos.length > 0 ? valsPos.reduce((a, b) => a + b, 0) / valsPos.length : prom;
    }

    const ultimaSem = semanasNros[semanasNros.length - 1];
    let proyW1 = round2(promCalc), proyW2 = round2(promCalc), proyW3 = round2(promCalc);
    let regSlope = 0, regIntercept = promCalc;
    let proyActual = null;

    if (item.wls_n !== undefined && item.wls_n > 0) {
        regSlope = item.wls_m !== undefined ? item.wls_m : 0;
        regIntercept = item.wls_b !== undefined ? item.wls_b : promCalc;
        let n_activa = item.wls_n;
        let ultima_semana_activa = semanasNros[item.wls_first_idx + n_activa - 1];

        let calcWLS = (sem) => Math.max(0, round2(regSlope * (n_activa + (sem - ultima_semana_activa)) + regIntercept));

        proyW1 = calcWLS(ultimaSem + 1);
        proyW2 = calcWLS(ultimaSem + 2);
        proyW3 = calcWLS(ultimaSem + 3);

        if (esSemActualEnRango) {
            proyActual = calcWLS(semanaActual);
        }
    } else {
        if (semanasCalc.length >= 2) {
            const xV = semanasCalc;
            const yV = semanasCalc.map(n => item.por_semana[n] || 0);
            const n_ = xV.length;
            const sumX = xV.reduce((a, b) => a + b, 0);
            const sumY = yV.reduce((a, b) => a + b, 0);
            const sumXY = xV.reduce((acc, x, i) => acc + x * yV[i], 0);
            const sumX2 = xV.reduce((acc, x) => acc + x * x, 0);
            const denom = n_ * sumX2 - sumX * sumX;
            if (Math.abs(denom) > 0.001) {
                regSlope = (n_ * sumXY - sumX * sumY) / denom;
                regIntercept = (sumY - regSlope * sumX) / n_;

                if (regSlope < 0) {
                    let ultimas2 = yV.slice(-2);
                    let maxUlt2 = ultimas2.length > 0 ? Math.max(...ultimas2) : 0;
                    regSlope = 0;
                    regIntercept = maxUlt2;
                }

                proyW1 = Math.max(0, round2(regSlope * (ultimaSem + 1) + regIntercept));
                proyW2 = Math.max(0, round2(regSlope * (ultimaSem + 2) + regIntercept));
                proyW3 = Math.max(0, round2(regSlope * (ultimaSem + 3) + regIntercept));
            }
        }
        if (esSemActualEnRango) {
            proyActual = Math.max(0, round2(regSlope * semanaActual + regIntercept));
        }
    }

    const labelsExtended = [
        ...labels,
        `${ultimaSem + 1}`,
        `${ultimaSem + 2}`,
        `${ultimaSem + 3}`,
    ];

    const valores = semanasNros.map(n => round2(item.por_semana[n] || 0));
    let datasets = [
        {
            label: 'Consumo Real',
            data: [...valores, null, null, null],
            backgroundColor: 'rgba(81,184,172,.35)',
            borderColor: '#0E544C',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            pointRadius: [...valores.map(() => 4), 0, 0, 0],
            pointBackgroundColor: '#0E544C',
        },
        {
            label: `Promedio Semanal: ${formatNum(round2(promCalc))}`,
            data: [...semanasNros.map(n => n === semanaActual ? null : round2(promCalc)), null, null, null],
            borderColor: '#e67e22',
            borderWidth: 1.5,
            borderDash: [5, 4],
            pointRadius: 0,
            fill: false,
            tension: 0,
            type: 'line',
        },
    ];

    // Detección para la leyenda visual
    let isAjustado = false;
    let sum_w=0, sum_wx=0, sum_wy=0, sum_wxx=0, sum_wxy=0;
    valsCalc.forEach((y, i) => {
        let x = i + 1; let w = x;
        sum_w += w; sum_wx += w*x; sum_wy += w*y; sum_wxx += w*x*x; sum_wxy += w*x*y;
    });
    let denomWLS = (sum_w * sum_wxx) - (sum_wx * sum_wx);
    if (Math.abs(denomWLS) > 0.001) {
        let rawSlope = ((sum_w * sum_wxy) - (sum_wx * sum_wy)) / denomWLS;
        if (rawSlope < -0.001) isAjustado = true;
    }
    let proyLabel = isAjustado ? 'Proyección (Ajuste p/Caída: Máx 2 sem)' : 'Proyección';

    const proyData = [...semanasNros.map(n => n === semanaActual ? proyActual : null), proyW1, proyW2, proyW3];
    datasets.push({
        label: proyLabel,
        data: proyData,
        borderColor: '#f39c12',
        backgroundColor: 'rgba(243,156,18,.12)',
        borderWidth: 2.5,
        borderDash: [6, 4],
        pointRadius: [...semanasNros.map(n => n === semanaActual ? 6 : 0), 6, 6, 6],
        pointStyle: 'triangle',
        pointBackgroundColor: '#f39c12',
        fill: false,
        tension: 0,
        type: 'line',
        spanGaps: true,
        order: 0,
    });

    const chartId = `tend-${sk}-${idInsumoSel}`;
    let existingTend = Chart.getChart(canvas);
    if (existingTend) existingTend.destroy();
    if (instanciasCharts[chartId]) { instanciasCharts[chartId].destroy(); delete instanciasCharts[chartId]; }

    const ctx = canvas.getContext('2d');
    instanciasCharts[chartId] = new Chart(ctx, {
        type: 'line',
        data: { labels: labelsExtended, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, font: { size: 10 } } }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#e8f0f4' }, ticks: { font: { size: 10 }, callback: v => formatNum(v) } },
                x: { grid: { color: '#e8f0f4' }, ticks: { font: { size: 10 }, maxRotation: 45 } }
            }
        }
    });
}

async function cargarChartKardex(canvas, idPP, semDesde, semHasta, semCorte, codSuc, fechaPronostico, sk) {
    const fd = new FormData();
    fd.append('id_pp', idPP);
    fd.append('semana_desde', semDesde);
    fd.append('semana_hasta', semHasta);
    fd.append('semana_corte', semCorte);
    fd.append('sucursales[]', codSuc);

    try {
        const res = await fetch('ajax/balance_inventario_get_detalle.php', { method: 'POST', body: fd }).then(r => r.json());
        if (!res.ok) {
            console.error('Error kardex', res.msg);
            return;
        }

        renderKardexCore(canvas, res, fechaPronostico, sk, semDesde, semHasta, semCorte, codSuc);
    } catch (e) { console.error('Error fetching kardex', e); }
}

function renderKardexCore(canvas, res, fechaObjetivoPronostico, sk, semDesde, semHasta, semCorte, codSuc) {
    const regs = res.registros || [];
    const t = res.totales_tipo || {};
    const invCorte = t.inv_inicial || 0;
    const invFin = t.inv_final || 0;
    const pivotDate = res.fecha_inicio_corte;
    const invIniRango = res.inv_inicial_rango ?? null;
    const semAntRango = res.semana_ant_rango || 0;
    const consTeoDiario = res.consumo_teorico_diario || {};
    const puntosDomingo = res.puntos_domingo || {};
    const fmtKardex = (v, d = 4) => v === null || v === undefined ? '—' : parseFloat(v).toLocaleString('es', { minimumFractionDigits: d, maximumFractionDigits: d });

    const start = new Date(res.fecha_inicio + 'T12:00:00');
    const end = new Date(res.fecha_fin + 'T12:00:00');
    const allDays = [];
    let curr = new Date(start);
    while (curr <= end) {
        allDays.push(curr.toISOString().split('T')[0]);
        curr.setDate(curr.getDate() + 1);
    }

    const hoy = new Date();
    hoy.setHours(12, 0, 0, 0);
    const ayerDate = new Date(hoy);
    ayerDate.setDate(ayerDate.getDate() - 1);
    const ayerStr = ayerDate.toISOString().split('T')[0];

    const isHoyAudit = typeof sk === 'string' && sk.endsWith('-HOY');
    if (isHoyAudit) {
        while (allDays.length > 0 && allDays[allDays.length - 1] > ayerStr) {
            allDays.pop();
        }
    }

    const semanaActualIncompleta = allDays.length > 0 && allDays[allDays.length - 1] > ayerStr;

    let originalRangeLen;
    if (semanaActualIncompleta) {
        const lastRealIdx = allDays.reduce((last, day, i) => day <= ayerStr ? i : last, -1);
        originalRangeLen = lastRealIdx >= 0 ? lastRealIdx + 1 : 0;
    } else {
        originalRangeLen = allDays.length;
    }

    if (fechaObjetivoPronostico && fechaObjetivoPronostico > allDays[allDays.length - 1]) {
        const endExt = new Date(fechaObjetivoPronostico + 'T12:00:00');
        let extCurr = new Date(allDays[allDays.length - 1] + 'T12:00:00');
        extCurr.setDate(extCurr.getDate() + 1);
        while (extCurr <= endExt) {
            allDays.push(extCurr.toISOString().split('T')[0]);
            extCurr.setDate(extCurr.getDate() + 1);
        }
    }

    const movsPorFecha = {};
    regs.forEach(r => {
        if (r.tipo === 'inv_inicial' || r.tipo === 'inv_final') return;
        if (!movsPorFecha[r.fecha]) movsPorFecha[r.fecha] = 0;
        let val = r.qty_base;
        if (r.tipo === 'merma') val = -val;
        movsPorFecha[r.fecha] += val;
    });

    const pivotIdx = pivotDate ? allDays.indexOf(pivotDate) : 0;
    const pIdx = pivotIdx >= 0 ? pivotIdx : 0;

    const stockTeoData = new Array(allDays.length).fill(null);

    let balFwd = invCorte;
    for (let i = pIdx; i < originalRangeLen; i++) {
        const mov = movsPorFecha[allDays[i]] || 0;
        const cTeo = consTeoDiario[allDays[i]] || 0;
        balFwd = balFwd + mov - cTeo;
        stockTeoData[i] = balFwd;
    }

    if (pIdx > 0) {
        let balBwd = invCorte;
        stockTeoData[pIdx - 1] = balBwd;
        for (let i = pIdx - 2; i >= 0; i--) {
            const mov = movsPorFecha[allDays[i + 1]] || 0;
            const cTeo = consTeoDiario[allDays[i + 1]] || 0;
            balBwd = balBwd - mov + cTeo;
            stockTeoData[i] = balBwd;
        }
    }

    const labels = allDays.map(day => {
        const dObj = new Date(day + 'T12:00:00');
        return dObj.toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short' });
    });

    const corteMarkerIdx = pIdx > 0 ? pIdx - 1 : pIdx;
    const corteMarker = new Array(labels.length).fill(null);
    corteMarker[corteMarkerIdx] = invCorte;

    const domingoData = allDays.map(day => {
        const v = puntosDomingo[day];
        return (v !== undefined && v !== null) ? v : null;
    });

    const realFinalPoint = new Array(allDays.length).fill(null);
    if (!semanaActualIncompleta) {
        realFinalPoint[originalRangeLen - 1] = invFin;
    }

    const invIniRangoData = new Array(allDays.length).fill(null);
    if (invIniRango !== null) invIniRangoData[0] = invIniRango;

    const datasets = [
        {
            label: 'Stock Teórico',
            data: stockTeoData,
            borderColor: '#51B8AC',
            backgroundColor: 'rgba(81,184,172,0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.3,
            pointRadius: 2,
        },
        {
            label: `Corte S${semCorte}`,
            data: corteMarker,
            borderColor: '#f39c12',
            backgroundColor: '#f39c12',
            pointRadius: 8,
            pointStyle: 'triangle',
            showLine: false,
        },
        {
            label: 'Inv. Físico',
            data: domingoData,
            borderColor: '#e74c3c',
            backgroundColor: '#e74c3c',
            pointRadius: 5,
            pointStyle: 'rectRot',
            showLine: false,
        }
    ];

    const chartId = `kardex-${sk}-${res.id_pp}`;
    let existingKard = Chart.getChart(canvas);
    if (existingKard) existingKard.destroy();
    if (instanciasCharts[chartId]) { instanciasCharts[chartId].destroy(); delete instanciasCharts[chartId]; }

    const ctx = canvas.getContext('2d');

    if (fechaObjetivoPronostico) {
        const _anchorIdx = originalRangeLen > 0 ? originalRangeLen - 1 : pIdx;
        const _anchorVal = stockTeoData[_anchorIdx] ?? (originalRangeLen === 0 ? invCorte : null);

        if (_anchorVal !== null && _anchorVal !== undefined && allDays[_anchorIdx] && allDays[_anchorIdx] < fechaObjetivoPronostico) {

            const _cntDow = [0, 0, 0, 0, 0, 0, 0];
            const _sumDow = [0, 0, 0, 0, 0, 0, 0];
            let _totalCons = 0;
            const allDates = Object.keys(consTeoDiario);
            allDates.forEach(f => {
                const v = consTeoDiario[f] ?? 0;
                if (v > 0) {
                    const dow = new Date(f + 'T12:00:00').getDay();
                    _sumDow[dow] += v;
                    _cntDow[dow]++;
                }
                _totalCons += v;
            });
            const _totalDias = allDates.length > 0 ? allDates.length : 1;
            const _promDiario = _totalCons / _totalDias;
            const _promDow = _sumDow.map((s, i) => _cntDow[i] > 0 ? s / _cntDow[i] : _promDiario);

            const _getConsProy = (fechaStr) => {
                const dow = new Date(fechaStr + 'T12:00:00').getDay();
                const pDow = _promDow[dow] > 0 ? _promDow[dow] : _promDiario;
                return 0.65 * pDow + 0.35 * _promDiario;
            };

            // Call the async function to add the forecast with dispatch
            calcularPronosticoAbastKardex(
                res, _anchorVal, _anchorIdx, allDays, fechaObjetivoPronostico, _getConsProy, datasets, ctx, fmtKardex, chartId, labels, semDesde, semHasta, semCorte, codSuc, sk
            );
            return; // We return here because _finalizarChartKardex will render the chart
        }
    }

    instanciasCharts[chartId] = new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'bottom', labels: { boxWidth: 10, font: { size: 9 } } },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function (context) {
                            if (context.raw === null) return null;
                            let label = context.dataset.label || '';
                            let extra = '';
                            if (context.dataset.despachoAmounts && context.dataset.despachoAmounts[context.dataIndex] !== null && context.dataset.despachoAmounts[context.dataIndex] !== undefined) {
                                let qty = context.dataset.despachoAmounts[context.dataIndex];
                                let tpe = (context.dataset.despachoTypes && context.dataset.despachoTypes[context.dataIndex] === 'curso') ? 'en curso' : 'proyectados';
                                extra = ` (+${qty} paq ${tpe})`;
                            }
                            if (label) label += ': ';
                            label += fmtKardex(context.raw, 2) + extra;
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 45 } }
            }
        }
    });

    instanciasCharts[chartId]._allDays = allDays;
    addStockLines(res.id_pp, sk, chartId, allDays);
}

async function calcularPronosticoAbastKardex(
    res, anchorVal, anchorIdx, allDays, fechaObj, getConsProy, datasets, ctx, fmtKardex, chartId, labels, semDesde, semHasta, semCorte, codSuc, sk
) {
    try {
        const idPP = res.id_pp;

        if (!idPP || !codSuc) {
            _buildSimpleForecast(anchorVal, anchorIdx, allDays, fechaObj, getConsProy, datasets, fmtKardex);
            _finalizarChartKardex(datasets, ctx, chartId, labels);
            return;
        }

        const fdP = new FormData();
        fdP.append('semana_desde_num', semDesde);
        fdP.append('semana_hasta_num', semHasta);
        fdP.append('cod_sucursal', codSuc);

        const resPedido = await fetch('ajax/pedido_sugerido_calcular_v2.php', { method: 'POST', body: fdP }).then(r => r.json());

        if (!resPedido.ok) {
            _buildSimpleForecast(anchorVal, anchorIdx, allDays, fechaObj, getConsProy, datasets, fmtKardex);
            _finalizarChartKardex(datasets, ctx, chartId, labels);
            return;
        }

        const prod = (resPedido.productos || []).find(p => String(p.id_pp) === String(idPP));

        if (!prod || !prod.fecha_proximo_despacho) {
            _buildSimpleForecast(anchorVal, anchorIdx, allDays, fechaObj, getConsProy, datasets, fmtKardex);
            _finalizarChartKardex(datasets, ctx, chartId, labels);
            return;
        }

        const addDays = (d, n) => {
            const dt = new Date(d + 'T12:00:00'); dt.setDate(dt.getDate() + n);
            return dt.toISOString().split('T')[0];
        };
        const calcularCicloSlot = (p, fechaStr) => {
            const tipo = p.plan_tipo_frecuencia;
            if (tipo === 'n_semanas') return (p.plan_intervalo_semanas || 1) * 7;
            if (tipo === 'dias_semana') {
                let dias = Array.isArray(p.plan_dias_semana) ? p.plan_dias_semana.map(Number) : [];
                dias.sort((a, b) => a - b);
                const n = dias.length;
                if (n === 0) return 7;
                if (n === 1) return 7;
                const dt = new Date(fechaStr + 'T12:00:00');
                const dowJS = dt.getDay();
                const dowDispatch = (dowJS + 6) % 7;
                for (let d = 1; d <= 7; d++) {
                    const next = (dowDispatch + d) % 7;
                    if (dias.includes(next)) return d;
                }
                return 7 / n;
            }
            return p.dias_ciclo || 7;
        };

        const calcularStockMaxSlot = (p, cicloSlot, cd_dinamico) => {
            if (p.plan_tipo_frecuencia !== 'dias_semana') {
                return p.stock_max_final ?? 0;
            }
            const cd = cd_dinamico ?? 0;
            const dSM = p.dias_stock_min ?? 0;
            const df_ = p.despacho_factor > 0 ? p.despacho_factor : 1;

            const sMinUso = cd * dSM;
            const sMaxUso = (cd * cicloSlot) + sMinUso;

            let ratio = 1;
            if (p.es_ajustado && p.stock_maximo > 0 && p.stock_max_final !== null) {
                ratio = (p.stock_max_final * df_) / (p.stock_maximo * df_);
            }
            return (sMaxUso * ratio) / df_;
        };

        const df = prod.despacho_factor > 0 ? prod.despacho_factor : 1;

        const getDynamicCd = (fecha_str) => {
            let wls_x = prod.wls_n ?? 0;
            if (resPedido.wls_last_fecha_fin) {
                const dF = new Date(resPedido.wls_last_fecha_fin + 'T23:59:59');
                const dD = new Date(fecha_str + 'T12:00:00');
                const diffDays = Math.round((dD - dF) / (1000 * 60 * 60 * 24));
                const x_offset = Math.ceil(diffDays / 7);
                wls_x += x_offset;
            } else {
                wls_x += 1;
            }
            const semC = Math.max(0, ((prod.wls_m ?? 0) * wls_x) + (prod.wls_b ?? 0));
            return semC / 7;
        };

        const hoyDBase = new Date();
        hoyDBase.setHours(12, 0, 0, 0);
        const hoyStrBase = hoyDBase.toISOString().split('T')[0];
        const baseCd = getDynamicCd(hoyStrBase || allDays[0]);

        const primeraFechaAgenda = prod.hoy_es_despacho ? hoyStrBase : prod.fecha_proximo_despacho;

        const rondas = [];
        let cur = primeraFechaAgenda;
        let round = 1;
        let prevCycle = 0;
        let prevCdRonda = baseCd;
        while (cur <= fechaObj && round <= 52) {
            const cd_ronda = getDynamicCd(cur);
            const cicloReal = calcularCicloSlot(prod, cur);
            const smfSlot = calcularStockMaxSlot(prod, cicloReal, cd_ronda);
            if (cur > allDays[anchorIdx]) {
                rondas.push({ fecha: cur, round, cycle: cicloReal, prevCycle: prevCycle, smfSlot: smfSlot, cd_ronda: cd_ronda, prevCdRonda: prevCdRonda });
            }
            prevCycle = cicloReal;
            prevCdRonda = cd_ronda;
            cur = addDays(cur, cicloReal);
            round++;
        }

        let stockD1R1 = null;
        let preHoyPaq = 0;
        let despachosReales = {};
        let allDespachosReales = {};
        const groupProds = (resPedido.productos || []).filter(p => p.categoria_insumo === prod.categoria_insumo);

        if (rondas.length > 0) {
            const fechaD1R1 = addDays(primeraFechaAgenda, -1);
            const fdPron = new FormData();
            fdPron.append('semana_desde', semDesde);
            fdPron.append('semana_hasta', semHasta);
            fdPron.append('semana_corte', semCorte);
            fdPron.append('cod_sucursal', codSuc);
            
            groupProds.forEach(p => {
                const fProxima = p.hoy_es_despacho ? hoyStrBase : p.fecha_proximo_despacho;
                const fD1 = addDays(fProxima, -1);
                fdPron.append('ids_pp[]', p.id_pp);
                fdPron.append(`fechas_d1[${p.id_pp}]`, fD1);
            });

            try {
                const resPron = await fetch('ajax/pedido_sugerido_pronostico_v2.php', { method: 'POST', body: fdPron }).then(r => r.json());
                if (resPron.ok) {
                    allDespachosReales = resPron.despachos_reales || {};
                    despachosReales = allDespachosReales[String(idPP)] || {};
                    if (rondas[0].round === 1) {
                        const su = resPron.stocks[String(idPP)];
                        const dP = resPron.dias_proy[String(idPP)] || 0;
                        if (su !== null && su !== undefined) {
                            let proyD1 = su;
                            if (dP > 0) {
                                for (let k = 0; k < dP; k++) {
                                    const dStr = addDays(fechaD1R1, -k);
                                    proyD1 -= getDynamicCd(dStr);
                                }
                            }
                            stockD1R1 = Math.max(0, proyD1 / df);
                        }
                    }
                    const ph = resPron.preingresos_hoy[String(idPP)];
                    preHoyPaq = 0; // Forced to 0 just like in pronostico_abastecimiento.js
                }
            } catch (e) { }
        }

        const despachosPorRonda = {};
        let prevRoundPostDespachoPaq = null;
        const kardexDespCursoEnabled = window.pa_include_preingreso;

        rondas.forEach(r => {
            let hayDespachoGrupo = false;
            groupProds.forEach(p => {
                const drGroup = allDespachosReales[String(p.id_pp)]?.[r.fecha];
                if (drGroup !== undefined && drGroup !== null) {
                    hayDespachoGrupo = true;
                }
            });

            let stockD1Paq;

            if (r.round === 1) {
                stockD1Paq = stockD1R1;
            } else {
                if (prevRoundPostDespachoPaq !== null) {
                    const prevConsPaq = (r.prevCdRonda * r.prevCycle) / df;
                    stockD1Paq = Math.max(0, prevRoundPostDespachoPaq - prevConsPaq);
                } else {
                    stockD1Paq = Math.max(0, r.smfSlot - (r.cd_ronda * r.cycle) / df);
                }
            }

            const invBeforePaq = (stockD1Paq ?? 0) + (r.round === 1 && kardexDespCursoEnabled ? preHoyPaq : 0);
            const despSugeridoPaq = Math.max(0, Math.ceil(r.smfSlot - invBeforePaq));

            let despachoAUsarPaq = despSugeridoPaq;
            let isReal = false;
            
            if (kardexDespCursoEnabled) {
                const dr = despachosReales[r.fecha];
                if (dr !== undefined && dr !== null) {
                    despachoAUsarPaq = dr / df;
                    isReal = true;
                } else if (hayDespachoGrupo) {
                    despachoAUsarPaq = 0;
                    isReal = true;
                }
            }

            prevRoundPostDespachoPaq = invBeforePaq + despachoAUsarPaq;

            despachosPorRonda[r.fecha] = { despacho: despachoAUsarPaq, stockD1Paq, round: r.round, stockPostDespachoPaq: prevRoundPostDespachoPaq, isReal };
        });

        const forecastData = new Array(allDays.length).fill(null);
        forecastData[anchorIdx] = anchorVal;
        let balFc = anchorVal;
        const dispatchMarkers = [];

        const hoyD = new Date();
        hoyD.setHours(12, 0, 0, 0);
        const hoyStrLocal = hoyD.toISOString().split('T')[0];

        const getConsProyAligned = (day) => getDynamicCd(day);

        for (let i = anchorIdx + 1; i < allDays.length; i++) {
            const day = allDays[i];
            if (day > fechaObj) break;

            balFc = balFc - getConsProyAligned(day);

            if (day === hoyStrLocal && kardexDespCursoEnabled && preHoyPaq > 0) {
                balFc = balFc + preHoyPaq * df;
                dispatchMarkers.push({ idx: i, val: balFc, rnd: 'Curso', despacho: preHoyPaq, isPreingreso: true });
            }

            if (despachosPorRonda[day]) {
                const { despacho, round: rnd, isReal } = despachosPorRonda[day];
                balFc = balFc + despacho * df;
                dispatchMarkers.push({ idx: i, val: balFc, rnd, despacho, isPreingreso: isReal });
            }

            forecastData[i] = balFc;
        }

        const _idxObj = allDays.indexOf(fechaObj);
        const _valObj = _idxObj >= 0 ? forecastData[_idxObj] : null;
        const _finalPoint = new Array(allDays.length).fill(null);
        if (_idxObj >= 0 && _valObj !== null) _finalPoint[_idxObj] = _valObj;

        const pointRadii = forecastData.map((v, i) => i === _idxObj ? 11 : 2);
        const pointHoverRadii = forecastData.map((v, i) => i === _idxObj ? 13 : 4);
        const pointStyles = forecastData.map((v, i) => i === _idxObj ? 'crossRot' : 'circle');

        const pronLabel = `Pronóstico de Inventario`;
        datasets.push({
            label: pronLabel,
            data: forecastData,
            borderColor: '#8e44ad',
            backgroundColor: 'rgba(142,68,173,0.06)',
            borderWidth: 2.5,
            borderDash: [10, 5],
            fill: false,
            tension: 0.2,
            pointRadius: pointRadii,
            pointHoverRadius: pointHoverRadii,
            pointStyle: pointStyles,
            pointBackgroundColor: '#8e44ad',
            spanGaps: true,
        });

        if (dispatchMarkers.length > 0) {
            const dispData = new Array(allDays.length).fill(null);
            const dispRadius = new Array(allDays.length).fill(0);
            const dispAmounts = new Array(allDays.length).fill(null);
            const dispTypes = new Array(allDays.length).fill('proy');
            const pStyles = new Array(allDays.length).fill('triangle');
            const bgColors = new Array(allDays.length).fill('#27ae60');

            dispatchMarkers.forEach(m => {
                dispData[m.idx] = m.val;
                dispRadius[m.idx] = 10;
                dispAmounts[m.idx] = m.despacho;
                if (m.isPreingreso) {
                    dispTypes[m.idx] = 'curso';
                    pStyles[m.idx] = 'circle';
                    bgColors[m.idx] = '#2980b9';
                }
            });
            datasets.push({
                label: `🚧 Despacho(s) programado/curso`,
                data: dispData,
                despachoAmounts: dispAmounts,
                despachoTypes: dispTypes,
                borderColor: bgColors,
                backgroundColor: bgColors,
                pointRadius: dispRadius,
                pointHoverRadius: 13,
                pointStyle: pStyles,
                showLine: false,
            });
        }

        _finalizarChartKardex(datasets, ctx, chartId, labels);
        addStockLines(idPP, sk, chartId, allDays);

    } catch (err) {
        console.warn('calcularPronosticoAbastKardex error:', err);
        _buildSimpleForecast(anchorVal, anchorIdx, allDays, fechaObj, getConsProy, datasets, fmtKardex);
        _finalizarChartKardex(datasets, ctx, chartId, labels);
        addStockLines(res.id_pp, sk, chartId, allDays);
    }
}

function _buildSimpleForecast(anchorVal, anchorIdx, allDays, fechaObj, getConsProy, datasets, fmtKardex) {
    const forecastData = new Array(allDays.length).fill(null);
    forecastData[anchorIdx] = anchorVal;
    let balFc = anchorVal;
    for (let i = anchorIdx + 1; i < allDays.length; i++) {
        if (allDays[i] > fechaObj) break;
        balFc -= getConsProy(allDays[i]);
        forecastData[i] = balFc;
    }
    const _idxObj = allDays.indexOf(fechaObj);
    const _valObj = _idxObj >= 0 ? forecastData[_idxObj] : null;
    const _finalPoint = new Array(allDays.length).fill(null);
    if (_idxObj >= 0 && _valObj !== null) _finalPoint[_idxObj] = _valObj;

    const pointRadii = forecastData.map((v, i) => i === _idxObj ? 11 : 2);
    const pointHoverRadii = forecastData.map((v, i) => i === _idxObj ? 13 : 4);
    const pointStyles = forecastData.map((v, i) => i === _idxObj ? 'crossRot' : 'circle');

    datasets.push({
        label: `Pronóstico de Inventario`,
        data: forecastData,
        borderColor: '#8e44ad',
        backgroundColor: 'rgba(142,68,173,0.06)',
        borderWidth: 2.5,
        borderDash: [10, 5],
        fill: false,
        tension: 0.2,
        pointRadius: pointRadii,
        pointHoverRadius: pointHoverRadii,
        pointStyle: pointStyles,
        pointBackgroundColor: '#8e44ad',
        spanGaps: false,
    });
}

function _finalizarChartKardex(datasets, ctx, chartId, labels) {
    instanciasCharts[chartId] = new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'bottom', labels: { boxWidth: 10, font: { size: 9 } } },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function (context) {
                            if (context.raw === null) return null;
                            let label = context.dataset.label || '';
                            let extra = '';
                            if (context.dataset.despachoAmounts && context.dataset.despachoAmounts[context.dataIndex] !== null && context.dataset.despachoAmounts[context.dataIndex] !== undefined) {
                                let qty = context.dataset.despachoAmounts[context.dataIndex];
                                let tpe = (context.dataset.despachoTypes && context.dataset.despachoTypes[context.dataIndex] === 'curso') ? 'en curso' : 'proyectados';
                                extra = ` (+${qty} paq ${tpe})`;
                            }
                            if (label) label += ': ';
                            label += (typeof fmtKardex === 'function' ? fmtKardex(context.raw, 2) : context.raw.toFixed(2)) + extra;
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 45 } }
            }
        }
    });
}

function addStockLines(idPP, sk, chartId, allDays) {
    try {
        const row = $(`.pa-row-expandible-charts[data-pp-id="${idPP}"][data-slot-key="${sk}"]`);
        if (!row.length) return;

        const wls_m = parseFloat(row.attr('data-wls-m')) || 0;
        const wls_b = parseFloat(row.attr('data-wls-b')) || 0;
        const wls_n = parseInt(row.attr('data-wls-n')) || 0;
        const wls_lff = row.attr('data-wls-lff');
        const dSM = parseFloat(row.attr('data-dsm')) || 0;
        const cicloSlotFijo = parseFloat(row.attr('data-ciclo')) || 0;
        const ratio = parseFloat(row.attr('data-ratio')) || 1;

        const planTipo = row.attr('data-plan-tipo');
        let planDias = [];
        try { planDias = JSON.parse(row.attr('data-plan-dias') || '[]'); } catch(e) {}
        const planSemanas = parseInt(row.attr('data-plan-semanas')) || 1;
        const diasCiclo = parseInt(row.attr('data-dias-ciclo')) || 7;

        const calcularCicloSlot = (fechaStr) => {
            if (!planTipo) return cicloSlotFijo;
            if (planTipo === 'n_semanas') return planSemanas * 7;
            if (planTipo === 'dias_semana') {
                let dias = Array.isArray(planDias) ? planDias.map(Number) : [];
                dias.sort((a, b) => a - b);
                const n = dias.length;
                if (n === 0 || n === 1) return 7;
                const dt = new Date(fechaStr + 'T12:00:00');
                const dowJS = dt.getDay();
                const dowDispatch = (dowJS + 6) % 7;
                for (let d = 1; d <= 7; d++) {
                    const next = (dowDispatch + d) % 7;
                    if (dias.includes(next)) return d;
                }
                return 7 / n;
            }
            return diasCiclo;
        };

        const getMostRecentDispatchDay = (fechaStr) => {
            if (planTipo !== 'dias_semana') return fechaStr;
            let dias = Array.isArray(planDias) ? planDias.map(Number) : [];
            if (dias.length === 0 || dias.length === 7) return fechaStr;
            
            let curDt = new Date(fechaStr + 'T12:00:00');
            for(let i=0; i<7; i++) {
                const dowJS = curDt.getDay();
                const dowDispatch = (dowJS + 6) % 7;
                if (dias.includes(dowDispatch)) {
                    return curDt.toISOString().split('T')[0];
                }
                curDt.setDate(curDt.getDate() - 1);
            }
            return fechaStr;
        };

        const chart = instanciasCharts[chartId];
        if (!chart) return;

        const getDynamicCd = (fecha_str) => {
            let wls_x = wls_n;
            if (wls_lff) {
                const dF = new Date(wls_lff + 'T23:59:59');
                const dD = new Date(fecha_str + 'T12:00:00');
                const diffDays = Math.round((dD - dF) / (1000 * 60 * 60 * 24));
                const x_offset = Math.ceil(diffDays / 7);
                wls_x += x_offset;
            } else {
                wls_x += 1;
            }
            const semC = Math.max(0, (wls_m * wls_x) + wls_b);
            // No aplicamos 'adj' aquí porque el cálculo de la tabla (en pronostico_abastecimiento.js) no lo hace
            return semC / 7;
        };

        const minData = [];
        const maxData = [];
        
        for (let i = 0; i < allDays.length; i++) {
            const day = allDays[i];
            if (!day) {
                minData.push(null);
                maxData.push(null);
                continue;
            }
            
            // Stock Mínimo cambia semanalmente basado en la fecha actual (no se ata a despachos)
            const cdSemanal = getDynamicCd(day);
            const sMinHoy = cdSemanal * dSM;
            
            // Requerido Total cambia por bloques de despacho
            const dispatchDay = getMostRecentDispatchDay(day);
            const cdDispatch = getDynamicCd(dispatchDay);
            const ciclo = calcularCicloSlot(dispatchDay);
            const sMinDispatch = cdDispatch * dSM;
            
            const sMaxUso = (cdDispatch * ciclo) + sMinDispatch;
            const sMaxFinal = sMaxUso * ratio;
            
            minData.push(sMinHoy);
            maxData.push(sMaxFinal);
        }

        chart.data.datasets.push({
            label: 'Stock Mínimo *',
            data: minData,
            borderColor: '#f9a825',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 0,
            fill: false,
            tension: 0,
            stepped: true
        });

        chart.data.datasets.push({
            label: 'Requerido Total *',
            data: maxData,
            borderColor: '#6d597a',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 0,
            fill: false,
            tension: 0,
            stepped: true
        });
        
        chart.update();

    } catch (e) {
        console.warn('Error en addStockLines', e);
    }
}

