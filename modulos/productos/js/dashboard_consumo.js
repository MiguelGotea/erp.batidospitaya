/* ============================================================
   DASHBOARD CONSUMO DE INSUMOS — JavaScript
   modulos/productos/js/dashboard_consumo.js
   ============================================================ */

'use strict';

/* ── Referencias DOM ─────────────────────────────────────── */
const $semDesde    = $('#filtroSemanaDesde');
const $semHasta    = $('#filtroSemanaHasta');
const $sucursales  = $('#filtroSucursales');
const $insumo      = $('#filtroInsumo');
const $btnAplicar  = $('#btnAplicar');
const $btnExportar = $('#btnExportar');

const $panelInicial = $('#panelInicial');
const $panelLoader  = $('#panelLoader');
const $panelDatos   = $('#panelDatos');

/* ── Estado global ───────────────────────────────────────── */
let datosActuales  = null;   // Respuesta completa del AJAX de datos
let chartTendencia = null;   // Instancia Chart.js activa
let modoChart      = 'bar';  // 'bar' | 'line'

/* ════════════════════════════════════════════════════════════
   INICIALIZACIÓN
   ════════════════════════════════════════════════════════════ */
$(document).ready(function () {
    inicializarSelect2();
    cargarFiltros();     // carga semana actual + sucursales + insumos
    bindEventos();
});

/* ── Inicializar Select2 ──────────────────────────────────── */
function inicializarSelect2() {
    $sucursales.select2({
        placeholder: 'Todas las sucursales',
        allowClear: true,
        width: '100%',
    });
    $insumo.select2({
        placeholder: 'Todos los insumos',
        allowClear: true,
        width: '100%',
        dropdownAutoWidth: true,
    });
}

/* ── Ligar eventos ────────────────────────────────────────── */
function bindEventos() {
    $btnAplicar.on('click', cargarDatos);

    // Modo gráfico
    $('#chartModoBarra, #chartModoLinea').on('click', function () {
        modoChart = $(this).data('modo');
        $('#chartModoBarra, #chartModoLinea').removeClass('active');
        $(this).addClass('active');
        if (datosActuales) renderGrafico(datosActuales);
    });

    // Cambiar insumo en gráfico
    $('#chartInsumoFiltro').on('change', function () {
        if (datosActuales) renderGrafico(datosActuales);
    });

    // Buscar en tabla historial
    $('#buscarHistorial').on('keyup', function () {
        const q = $(this).val().toLowerCase();
        $('#tbodyHistorial tr').each(function () {
            const txt = $(this).text().toLowerCase();
            $(this).toggle(txt.includes(q));
        });
    });

    // Exportar
    $btnExportar.on('click', exportarCSV);

    // Heatmap insumo selector
    $('#heatmapInsumoSel').on('change', function () {
        if (datosActuales) renderHeatmap(datosActuales, $(this).val());
    });
}

/* ════════════════════════════════════════════════════════════
   CARGAR FILTROS — detecta semana actual + puebla sucursales e insumos
   ════════════════════════════════════════════════════════════ */
async function cargarFiltros() {
    try {
        const resp = await $.ajax({
            url: 'ajax/dashboard_consumo_get_filtros.php',
            type: 'GET',
        });

        if (!resp.ok) {
            console.error('Error filtros:', resp.msg);
            return;
        }

        // ── Semana actual ───────────────────────────────────────
        if (resp.semana_actual) {
            const sa = resp.semana_actual;
            $('#semanaActualNum').text(`Sem. ${sa.numero_semana} / ${sa.anio}`);
            $('#semanaActualRango').text(
                ` · ${formatFecha(sa.fecha_inicio)} – ${formatFecha(sa.fecha_fin)}`
            );
            $('#rowSemanaActual').show();   // ← muestra la fila separada

            // Pre-cargar rango por defecto: 4 semanas hasta la actual
            const semHasta = sa.numero_semana;
            const semDesde = Math.max(1, semHasta - 3);
            $semDesde.val(semDesde);
            $semHasta.val(semHasta);
        }

        // ── Sucursales ─────────────────────────────────────────
        $sucursales.empty();
        resp.sucursales.forEach(s => {
            $sucursales.append(`<option value="${s.codigo}">${s.nombre}</option>`);
        });

        // ── Insumos ───────────────────────────────────────────
        $insumo.empty().append('<option value="">Todos los insumos</option>');
        resp.insumos.forEach(i => {
            const tipoLabel = i.es_global == 1 ? ' [Global]' : '';
            $insumo.append(`<option value="${i.id}">${i.nombre_completo}${tipoLabel}</option>`);
        });

    } catch (err) {
        console.error('Error cargando filtros:', err);
    }
}

/* ════════════════════════════════════════════════════════════
   CARGAR DATOS (ANALIZAR)
   ════════════════════════════════════════════════════════════ */
async function cargarDatos() {
    const semDesdeNum = parseInt($semDesde.val());
    const semHastaNum = parseInt($semHasta.val());

    if (!semDesdeNum || !semHastaNum || isNaN(semDesdeNum) || isNaN(semHastaNum)) {
        Swal.fire({ icon: 'warning', title: 'Semanas requeridas', text: 'Ingresa los números de semana de inicio y fin.', confirmButtonColor: '#0E544C' });
        return;
    }

    const semD = Math.min(semDesdeNum, semHastaNum);
    const semH = Math.max(semDesdeNum, semHastaNum);

    const sucursalesSelec = $sucursales.val() || [];
    const idInsumo        = $insumo.val() || 0;

    // Mostrar loader
    mostrarEstado('loader');
    $btnAplicar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Calculando…');
    $btnExportar.prop('disabled', true);

    try {
        const formData = new FormData();
        formData.append('semana_desde_num', semD);
        formData.append('semana_hasta_num', semH);
        sucursalesSelec.forEach(s => formData.append('sucursales[]', s));
        formData.append('id_insumo', idInsumo);

        const resp = await $.ajax({
            url: 'ajax/dashboard_consumo_get_datos.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        });

        if (!resp.ok) {
            Swal.fire({ icon: 'error', title: 'Error', text: resp.msg || 'Error al calcular consumo.', confirmButtonColor: '#0E544C' });
            mostrarEstado('inicial');
            return;
        }

        if (resp.consumo.length === 0 && resp.sin_mapeo.length === 0) {
            mostrarEstado('inicial');
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay ventas válidas en el período seleccionado.', confirmButtonColor: '#0E544C' });
            return;
        }

        datosActuales = resp;
        // Guardar info de semanas para exportar
        datosActuales._semDesde = semD;
        datosActuales._semHasta = semH;

        renderDashboard(resp);
        mostrarEstado('datos');

        if (PUEDE_EXPORTAR) $btnExportar.prop('disabled', false);

    } catch (err) {
        console.error('Error cargando datos:', err);
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0E544C' });
        mostrarEstado('inicial');
    } finally {
        $btnAplicar.prop('disabled', false).html('<i class="fas fa-search me-1"></i>Analizar');
    }
}

function obtenerNumSemana(semId) {
    return $semDesde.find(`option[value="${semId}"]`).data('num') || semId;
}

/* ── Controlar visibilidad de paneles ─────────────────────── */
function mostrarEstado(estado) {
    $panelInicial.addClass('d-none');
    $panelLoader.addClass('d-none');
    $panelDatos.addClass('d-none');

    if (estado === 'inicial') $panelInicial.removeClass('d-none');
    if (estado === 'loader')  $panelLoader.removeClass('d-none');
    if (estado === 'datos')   $panelDatos.removeClass('d-none');
}

/* ════════════════════════════════════════════════════════════
   RENDER COMPLETO DEL DASHBOARD
   ════════════════════════════════════════════════════════════ */
function renderDashboard(data) {
    renderKPIs(data);
    renderGrafico(data);
    renderTablaHistorial(data);
    renderTablaProyeccion(data);
    renderHeatmapSelector(data);
    renderTabSinMapeo(data);
}

/* ── KPIs ─────────────────────────────────────────────────── */
function renderKPIs(data) {
    // Total general (suma de todos los consumos, número de insumos únicos)
    $('#kpiTotalVal').text(formatNum(data.total_general));
    $('#kpiTotalSub').text(`${data.num_insumos} insumos analizados`);

    // Semana pico
    if (data.semana_pico_global) {
        const semPico = data.semanas.find(s => s.numero_semana == data.semana_pico_global);
        $('#kpiPicoVal').text(`Sem. ${data.semana_pico_global}`);
        $('#kpiPicoSub').text(semPico ? `${formatFecha(semPico.fecha_inicio)}–${formatFecha(semPico.fecha_fin)}` : '');
    } else {
        $('#kpiPicoVal').text('—');
        $('#kpiPicoSub').text('');
    }

    // Proyección 4 semanas
    $('#kpiProyVal').text(formatNum(data.proyeccion_total));
    $('#kpiProySub').text('Basado en promedio del período');

    // Sin mapeo
    const nSinMapeo = data.num_sin_mapeo || 0;
    $('#kpiAlertasVal').text(nSinMapeo);
    $('#kpiAlertasSub').text(nSinMapeo > 0 ? 'Ingredientes no contabilizados' : 'Todo mapeado ✓');
    $('#kpiAlertas .dc-kpi-icon').css('color', nSinMapeo > 0 ? '#e74c3c' : '#27ae60');

    // Badge en tab
    if (nSinMapeo > 0) {
        $('#badgeSinMapeo').removeClass('d-none').text(nSinMapeo);
    } else {
        $('#badgeSinMapeo').addClass('d-none');
    }
}

/* ── Gráfico de Tendencia ─────────────────────────────────── */
function renderGrafico(data) {
    const modoInsumo = $('#chartInsumoFiltro').val(); // 'top5' | 'todos'

    // Labels del eje X = semanas del rango
    const labels = data.semanas.map(s => `S${s.numero_semana}/${s.anio}`);
    const semanasNros = data.semanas.map(s => s.numero_semana);

    let datasets = [];

    if (modoInsumo === 'todos' || !modoInsumo) {
        // Suma de todos los insumos por semana
        const sumas = {};
        semanasNros.forEach(n => { sumas[n] = 0; });
        data.consumo.forEach(item => {
            semanasNros.forEach(n => {
                sumas[n] += (item.por_semana[n] || 0);
            });
        });
        datasets.push({
            label: 'Consumo Total',
            data: semanasNros.map(n => round2(sumas[n])),
            backgroundColor: 'rgba(81,184,172,.4)',
            borderColor: '#0E544C',
            borderWidth: 2,
            tension: 0.3,
            fill: modoChart === 'line',
            pointRadius: 4,
            pointBackgroundColor: '#0E544C',
        });
    } else {
        // Top 5 insumos por consumo total
        const top5 = [...data.consumo].sort((a, b) => b.total - a.total).slice(0, 5);
        const colores = ['#0E544C', '#51B8AC', '#e67e22', '#3498db', '#9b59b6'];
        top5.forEach((item, i) => {
            datasets.push({
                label: item.nombre.length > 30 ? item.nombre.substring(0, 28) + '…' : item.nombre,
                data: semanasNros.map(n => round2(item.por_semana[n] || 0)),
                backgroundColor: colores[i] + '66',
                borderColor: colores[i],
                borderWidth: 2,
                tension: 0.3,
                fill: false,
                pointRadius: 3,
                pointBackgroundColor: colores[i],
            });
        });
    }

    // Destruir chart anterior
    if (chartTendencia) {
        chartTendencia.destroy();
        chartTendencia = null;
    }

    const ctx = document.getElementById('chartTendencia').getContext('2d');
    chartTendencia = new Chart(ctx, {
        type: modoChart,
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { size: 11, family: 'Calibri' }, padding: 14 },
                },
                tooltip: {
                    callbacks: {
                        label: ctx => `${ctx.dataset.label}: ${formatNum(ctx.parsed.y)}`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { color: '#e8f0f4' },
                    ticks: { font: { size: 10 }, maxRotation: 45 },
                },
                y: {
                    grid: { color: '#e8f0f4' },
                    ticks: {
                        font: { size: 10 },
                        callback: v => formatNum(v),
                    },
                    beginAtZero: true,
                },
            },
        },
    });
}

/* ── Tabla Historial ─────────────────────────────────────── */
function renderTablaHistorial(data) {
    let html = '';
    const total = data.consumo.length;

    if (total === 0) {
        html = `<tr><td colspan="8" class="text-center text-muted py-4">Sin datos de consumo en el período.</td></tr>`;
    } else {
        data.consumo.forEach(item => {
            const tipoBadge = item.es_global
                ? `<span class="badge-global"><i class="fas fa-layer-group me-1"></i>Global</span>`
                : `<span class="badge-normal"><i class="fas fa-box me-1"></i>Simple</span>`;

            const trendIcon = item.tendencia === 'up'
                ? `<span class="dc-trend-up"><i class="fas fa-arrow-up"></i></span>`
                : item.tendencia === 'down'
                    ? `<span class="dc-trend-down"><i class="fas fa-arrow-down"></i></span>`
                    : `<span class="dc-trend-flat"><i class="fas fa-minus"></i></span>`;

            html += `
            <tr>
                <td>
                    <div class="fw-bold" style="font-size:.82rem">${escHtml(item.nombre)}</div>
                    <div class="text-muted" style="font-size:.72rem">${escHtml(item.maestro)}</div>
                </td>
                <td>${escHtml(item.unidad)}</td>
                <td class="text-end fw-bold" style="color:#0E544C">${formatNum(item.total)}</td>
                <td class="text-end">${formatNum(item.prom_semana)}</td>
                <td class="text-end">
                    ${item.semana_pico_num
                        ? `<span class="dc-semana-badge">Sem ${item.semana_pico_num}</span>
                           <br><small class="text-muted">${formatNum(item.max_consumo_sem)}</small>`
                        : '—'}
                </td>
                <td class="text-center">${Object.keys(item.por_semana).length}</td>
                <td class="text-center">${tipoBadge}</td>
                <td class="text-center">
                    <button class="btn-desglose" onclick="mostrarDesglose(${item.id})">
                        <i class="fas fa-expand-arrows-alt me-1"></i>Ver
                    </button>
                </td>
            </tr>`;
        });
    }

    $('#tbodyHistorial').html(html);
    $('#labelResultados').text(total > 0 ? `${total} insumo(s) encontrado(s)` : '');
}

/* ── Tabla Proyección ─────────────────────────────────────── */
function renderTablaProyeccion(data) {
    let html = '';

    if (data.consumo.length === 0) {
        html = `<tr><td colspan="9" class="text-center text-muted py-4">Sin datos de proyección.</td></tr>`;
    } else {
        data.consumo.forEach(item => {
            const trendIcon = item.tendencia === 'up'
                ? `<span class="dc-trend-up"><i class="fas fa-arrow-up me-1"></i>Creciente</span>`
                : item.tendencia === 'down'
                    ? `<span class="dc-trend-down"><i class="fas fa-arrow-down me-1"></i>Decreciente</span>`
                    : `<span class="dc-trend-flat"><i class="fas fa-minus me-1"></i>Estable</span>`;

            html += `
            <tr>
                <td>
                    <div class="fw-bold" style="font-size:.82rem">${escHtml(item.nombre)}</div>
                    <div class="text-muted" style="font-size:.72rem">${escHtml(item.maestro)}</div>
                </td>
                <td>${escHtml(item.unidad)}</td>
                <td class="text-end">${formatNum(item.prom_semana)}</td>
                <td class="text-end fw-bold" style="color:#0E544C">${formatNum(item.proyeccion_4sem)}</td>
                <td class="text-end" style="color:#e67e22">${formatNum(item.stock_min)}</td>
                <td class="text-end" style="color:#27ae60">${formatNum(item.stock_max)}</td>
                <td class="text-end">
                    ${item.semana_pico_num ? `<span class="dc-semana-badge">Sem ${item.semana_pico_num}</span>` : '—'}
                </td>
                <td class="text-end">
                    ${item.semana_low_num ? `<span class="dc-semana-badge">Sem ${item.semana_low_num}</span>` : '—'}
                </td>
                <td class="text-center">${trendIcon}</td>
            </tr>`;
        });
    }

    $('#tbodyProyeccion').html(html);
}

/* ── Selector de insumos en heatmap ─────────────────────── */
function renderHeatmapSelector(data) {
    const $sel = $('#heatmapInsumoSel');
    $sel.empty().append('<option value="">— Selecciona un insumo —</option>');

    data.consumo.slice(0, 50).forEach(item => {
        $sel.append(`<option value="${item.id}">${escHtml(item.nombre)}</option>`);
    });

    // Auto-seleccionar el primero si hay datos
    if (data.consumo.length > 0) {
        $sel.val(data.consumo[0].id);
        renderHeatmap(data, data.consumo[0].id);
    }
}

/* ── Render Heatmap ──────────────────────────────────────── */
function renderHeatmap(data, idInsumo) {
    const $cont = $('#heatmapContainer');

    if (!idInsumo) {
        $cont.html('<div class="text-center text-muted py-4"><i class="fas fa-th fa-2x mb-2 d-block"></i>Selecciona un insumo.</div>');
        return;
    }

    const item = data.consumo.find(c => c.id == idInsumo);
    if (!item) {
        $cont.html('<div class="text-center text-muted py-4">Insumo no encontrado.</div>');
        return;
    }

    const sucursales  = data.sucursales;
    const semanas     = data.semanas;

    // Valor máximo para normalizar
    let maxVal = 0;
    semanas.forEach(s => {
        sucursales.forEach(suc => {
            const v = (item.desglose_semxsuc[s.numero_semana]?.[suc] || 0);
            if (v > maxVal) maxVal = v;
        });
    });

    // Encabezados: sucursales
    let theadHtml = '<tr><th>Semana</th>';
    sucursales.forEach(suc => {
        theadHtml += `<th>${escHtml(suc)}</th>`;
    });
    theadHtml += '<th>Total Sem.</th></tr>';

    // Filas: semanas
    let tbodyHtml = '';
    semanas.forEach(s => {
        let totalSem = 0;
        let fila = `<tr><td class="fw-bold">S${s.numero_semana}<br><small class="text-muted">${formatFecha(s.fecha_inicio)}</small></td>`;
        sucursales.forEach(suc => {
            const v = item.desglose_semxsuc[s.numero_semana]?.[suc] || 0;
            totalSem += v;
            const intensidad = maxVal > 0 ? Math.ceil((v / maxVal) * 10) : 0;
            fila += `<td class="hm-${intensidad}" title="${suc}: ${formatNum(v)} ${escHtml(item.unidad)}">${v > 0 ? formatNum(v) : ''}</td>`;
        });
        fila += `<td class="fw-bold text-end">${formatNum(totalSem)}</td></tr>`;
        tbodyHtml += fila;
    });

    const html = `
        <div style="font-size:.78rem;color:#667;margin-bottom:8px">
            <strong>${escHtml(item.nombre)}</strong> · ${escHtml(item.unidad)}
            <span class="ms-3 dc-semana-badge">Total: ${formatNum(item.total)}</span>
        </div>
        <table class="dc-heatmap-table">
            <thead>${theadHtml}</thead>
            <tbody>${tbodyHtml}</tbody>
        </table>
        <div style="margin-top:8px;font-size:.72rem;color:#99a">
            Color: 
            <span class="hm-1" style="padding:1px 6px;border-radius:3px">Bajo</span>
            <span class="mx-1">→</span>
            <span class="hm-5" style="padding:1px 6px;border-radius:3px">Medio</span>
            <span class="mx-1">→</span>
            <span class="hm-10" style="padding:1px 6px;border-radius:3px">Alto</span>
        </div>
    `;
    $cont.html(html);
}

/* ── Tab Sin Mapeo ───────────────────────────────────────── */
function renderTabSinMapeo(data) {
    let html = '';
    if (!data.sin_mapeo || data.sin_mapeo.length === 0) {
        html = `<tr><td colspan="5" class="text-center text-muted py-4">
            <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
            Todos los ingredientes tienen mapeo ERP.
        </td></tr>`;
    } else {
        data.sin_mapeo.forEach(sm => {
            html += `
            <tr>
                <td><code>${escHtml(sm.cod_ingrediente)}</code></td>
                <td>${escHtml(sm.nombre)}</td>
                <td><span class="badge-sinmapeo">${escHtml(sm.unidad_access)}</span></td>
                <td class="text-center fw-bold">${sm.num_productos}</td>
                <td class="text-end">${formatNum(sm.ventas_afectadas)}</td>
            </tr>`;
        });
    }
    $('#tbodySinMapeo').html(html);
}

/* ── Modal Desglose ──────────────────────────────────────── */
window.mostrarDesglose = function (idInsumo) {
    if (!datosActuales) return;
    const item = datosActuales.consumo.find(c => c.id == idInsumo);
    if (!item) return;

    const semanas    = datosActuales.semanas;
    const sucursales = datosActuales.sucursales;

    let theadHtml = '<tr><th style="min-width:100px">Semana</th>';
    sucursales.forEach(s => { theadHtml += `<th>${escHtml(s)}</th>`; });
    theadHtml += '<th>TOTAL</th></tr>';

    let tbodyHtml = '';
    let totalGeneral = 0;
    const totalesSuc = {};
    sucursales.forEach(s => { totalesSuc[s] = 0; });

    semanas.forEach(sem => {
        let totalSem = 0;
        let fila = `<tr><td><span class="dc-semana-badge">Sem ${sem.numero_semana}/${sem.anio}</span><br>
            <small class="text-muted">${formatFecha(sem.fecha_inicio)}–${formatFecha(sem.fecha_fin)}</small></td>`;
        sucursales.forEach(suc => {
            const v = item.desglose_semxsuc[sem.numero_semana]?.[suc] || 0;
            totalSem += v;
            totalesSuc[suc] += v;
            fila += `<td class="text-end">${v > 0 ? formatNum(v) : '<span class="text-muted">—</span>'}</td>`;
        });
        totalGeneral += totalSem;
        fila += `<td class="text-end fw-bold" style="color:#0E544C">${formatNum(totalSem)}</td></tr>`;
        tbodyHtml += fila;
    });

    // Fila total
    let filaTot = `<tr style="background:#f0f8f6;font-weight:700"><td>TOTAL PERÍODO</td>`;
    sucursales.forEach(s => { filaTot += `<td class="text-end">${formatNum(totalesSuc[s])}</td>`; });
    filaTot += `<td class="text-end" style="color:#0E544C">${formatNum(totalGeneral)}</td></tr>`;

    const contenido = `
        <div class="mb-2">
            <strong>${escHtml(item.nombre)}</strong>
            <span class="text-muted ms-2" style="font-size:.8rem">· ${escHtml(item.unidad)} · ${escHtml(item.maestro)}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover dc-tabla mb-0" style="font-size:.78rem">
                <thead>${theadHtml}</thead>
                <tbody>${tbodyHtml}${filaTot}</tbody>
            </table>
        </div>
    `;

    $('#modalDesgloseLabel').html(`<i class="fas fa-expand-arrows-alt me-2"></i>${escHtml(item.nombre)} — Detalle`);
    $('#modalDesgloseContenido').html(contenido);
    const modal = new bootstrap.Modal(document.getElementById('modalDesglose'));
    modal.show();
};

/* ── Exportar CSV ─────────────────────────────────────────── */
function exportarCSV() {
    if (!datosActuales || !PUEDE_EXPORTAR) return;

    // Determinar modo según tab activo
    const tabActivo = $('.dc-tab-btn.active').attr('id');
    let modo = 'historial';
    if (tabActivo === 'tabProyeccionBtn') modo = 'proyeccion';
    if (tabActivo === 'tabSinMapeoBtn')   modo = 'sin_mapeo';

    const payload = {
        consumo:    datosActuales.consumo,
        semanas:    datosActuales.semanas,
        sin_mapeo:  datosActuales.sin_mapeo,
        sem_desde:  datosActuales._semDesde,
        sem_hasta:  datosActuales._semHasta,
        modo,
    };

    // Crear form oculto para descargar
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'ajax/dashboard_consumo_exportar.php';
    form.style.display = 'none';

    const input = document.createElement('input');
    input.type  = 'hidden';
    input.name  = 'not_used'; // El endpoint lee php://input directamente
    form.appendChild(input);
    document.body.appendChild(form);

    // Usar fetch para descargar
    fetch('ajax/dashboard_consumo_exportar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(res => res.blob())
    .then(blob => {
        const url  = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href     = url;
        link.download = `consumo_insumos_sem${datosActuales._semDesde}_${datosActuales._semHasta}_${modo}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        document.body.removeChild(form);
    })
    .catch(err => {
        console.error('Error exportando:', err);
        Swal.fire({ icon: 'error', title: 'Error al exportar', text: 'No se pudo generar el CSV.', confirmButtonColor: '#0E544C' });
    });
}

/* ════════════════════════════════════════════════════════════
   UTILIDADES
   ════════════════════════════════════════════════════════════ */
function formatNum(n) {
    if (n === null || n === undefined || isNaN(n)) return '0';
    const num = parseFloat(n);
    if (num === 0) return '0';
    // Si tiene decimales significativos muéstralos, sino entero
    if (Math.abs(num - Math.round(num)) < 0.001) return num.toLocaleString('es-NI');
    return num.toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}

function formatFecha(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    return `${d.getDate()}/${meses[d.getMonth()]}/${String(d.getFullYear()).slice(2)}`;
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function round2(n) {
    return Math.round(parseFloat(n) * 100) / 100;
}
