// Dashboard RFM 2.0 - Intelligence & Loyalty Logic
let chartSegments = null;
let chartEvolution = null;
let chartSegmentRevenue = null;
let chartBranchScores = null;
let chartBranchDistribution = null;
let chartBranchTicket = null;
let chartHeatmap = null;
let chartHabitMeasure = null;
let chartHabitModality = null;
let chartHabitPromo = null;

// State Management
let fullClientData = [];
let filteredData = [];
let currentPage = 1;
const itemsPerPage = 20;
let filtrosActivos = {};
let panelFiltroAbierto = null;
let ordenActivo = { columna: null, direccion: null };

$(document).ready(function () {
    cargarSucursales();
    cargarDatos();

    // Event Listeners
    $('#filterForm').on('submit', (e) => { e.preventDefault(); cargarDatos(); });
    $('#tableSearch').on('keyup', debounce(handleSearch, 300));
    $('#btnExportFull').on('click', () => exportData('full'));

    // Cerrar filtros al hacer clic fuera
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });

    // Reactividad para cambio de umbral (Colores en tabla y re-segmentación local rápida o recarga)
    $('#umbral_perdido').on('change input', debounce(() => {
        renderPaginatedTable();
        actualizarKPIsLocales();
    }, 500));
});

// --- CARGA DE DATOS ---

function cargarSucursales() {
    $.get('/modulos/marketing/ajax/get_sucursales.php', function (res) {
        if (res.success) {
            let html = '<option value="todas">Todas las Sucursales</option>';
            res.data.forEach(s => {
                html += `<option value="${s.nombre}">${s.nombre}</option>`;
            });
            $('#filtro_sucursal').html(html);
        }
    });
}

async function cargarDatos() {
    const btn = $('#filterForm button[type="submit"]');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Analizando...');

    const params = {
        fecha_inicio: $('#fecha_inicio').val(),
        fecha_fin: $('#fecha_fin').val(),
        sucursal: $('#filtro_sucursal').val(),
        umbral_perdido: $('#umbral_perdido').val()
    };

    try {
        const query = $.param(params);
        const response = await fetch(`ajax/dashboard_rfm_get_datos.php?${query}`);
        const data = await response.json();

        if (data.success) {
            fullClientData = data.individual || [];
            filteredData = [...fullClientData];
            currentPage = 1;
            updateDashboard(data);
        } else {
            Swal.fire('Atención', data.message, 'warning');
        }
    } catch (error) {
        console.error(error);
        Swal.fire('Error', 'No se pudo conectar con el servicio de datos.', 'error');
    } finally {
        btn.prop('disabled', false).html('<i class="fas fa-sync-alt me-2"></i>Actualizar Inteligencia');
    }
}

function actualizarKPIsLocales() {
    const umbral = parseInt($('#umbral_perdido').val()) || 60;
    
    // Actualizar tooltips dinámicamente
    $('.kpi-card-new').each(function() {
        const id = $(this).attr('id');
        if (id === 'tipClubActivos') {
            $(this).attr('data-bs-original-title', `Socios con última compra hace &le; ${umbral} días.`);
        } else if (id === 'tipEnRiesgo') {
            $(this).attr('data-bs-original-title', `Socios con inactividad entre ${Math.floor(umbral/2)} y ${umbral} días.`);
        } else if (id === 'tipPerdidos') {
            $(this).attr('data-bs-original-title', `Socios con inactividad > ${umbral} días.`);
        }
    });
    
    // Si queremos que los segmentos también cambien visualmente rápido sin ir al server:
    // (O podemos simplemente avisar que para re-segmentar haga clic en Actualizar)
}

// --- ACTUALIZACIÓN DE UI ---

function updateDashboard(data) {
    updateKPIs(data.summary);
    updateSegmentsChart(data.segments, data.segment_revenue);
    updateEvolutionChart(data.evolution);
    actualizarIndicadoresFiltros();
    applyAllFilters();
    updateBranchCharts(data.branch_analysis, data.summary.ticket_club);
    updateHabitSection(data.habits);
}
function updateKPIs(summary) {
    if (!summary) return;
    animateValue('kpiTotalClub', summary.activos);
    animateValue('kpiNuevos', summary.nuevos);
    animateValue('kpiEnRiesgo', summary.en_riesgo);
    animateValue('kpiPerdidos', summary.perdidos);
    animateValue('kpiAvgLTV', summary.avg_ltv, true);

    // Porcentajes de Salud (Relativos al Universo con Compra / Activos)
    if (summary.total_club > 0) {
        const pActivos = (summary.activos / summary.total_club) * 100;
        const pPerdidos = (summary.perdidos / summary.total_club) * 100;
        const pRiesgo = summary.activos > 0 ? (summary.en_riesgo / summary.activos) * 100 : 0;

        $('#kpiTotalClubPerc').text(`${pActivos.toFixed(1)}% de la base`);
        $('#kpiEnRiesgoPerc').text(`${pRiesgo.toFixed(1)}% de activos`);
        $('#kpiPerdidosPerc').text(`${pPerdidos.toFixed(1)}% de la base`);
    }

    animateValue('kpiTicket', summary.ticket_club, true);
    animateValue('kpiRetention', summary.retention_metrics.rate, false, '%');

    // New KPIs
    if (summary.participacion) {
        const partPerc = (summary.participacion.club / Math.max(1, summary.participacion.total)) * 100;
        animateValue('kpiParticipation', partPerc, false, '%');
    }
    animateValue('kpiChurn', summary.churn_rate, false, '%');

    // Trends
    if (summary.prev_nuevos !== undefined) {
        const trend = summary.nuevos - summary.prev_nuevos;
        const trendPerc = summary.prev_nuevos > 0 ? (trend / summary.prev_nuevos) * 100 : 100;
        const color = trend >= 0 ? 'text-success' : 'text-danger';
        const icon = trend >= 0 ? 'fa-caret-up' : 'fa-caret-down';
        $('#kpiNuevosTrend').html(`<span class="${color}"><i class="fas ${icon}"></i> ${Math.abs(trendPerc).toFixed(1)}%</span>`).attr('title', `Ant: ${summary.prev_nuevos}`);
    }    // Tooltips - Re-inicialización forzada para evitar caché de Bootstrap
    const umbral = $('#umbral_perdido').val();
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));

    tooltipTriggerList.forEach(el => {
        const instance = bootstrap.Tooltip.getInstance(el);
        if (instance) instance.dispose();
        // Limpiamos atributos que Bootstrap usa para cachear
        el.removeAttribute('data-bs-original-title');
    });

    $('#tipAvgLTV').attr('title', `<div class="tooltip-data-row"><span>Promedio Global:</span> <span>${fmt(summary.avg_ltv)}</span></div><div class="tooltip-formula">Valor de vida promedio de los socios en toda la marca.</div>`);
    $('#tipClubActivos').attr('title', `<div class="tooltip-data-row"><span>Criterio:</span> <span><= ${umbral} días</span></div><div class="tooltip-data-row"><span>Total c/Compra:</span> <span>${summary.total_club}</span></div><div class="tooltip-formula">Socios con al menos una compra en los últimos ${umbral} días.</div>`);
    $('#tipNuevos').attr('title', `<div class="tooltip-data-row"><span>Registros:</span> <span>${summary.nuevos}</span></div><div class="tooltip-data-row"><span>Previo:</span> <span>${summary.prev_nuevos}</span></div><div class="tooltip-formula">Comparado contra el periodo anterior equivalente.</div>`);
    $('#tipEnRiesgo').attr('title', `<div class="tooltip-data-row"><span>Criterio:</span> <span>${Math.floor(umbral / 2)}-${umbral} días</span></div><div class="tooltip-formula">Socios enfriándose. El % es sobre el total de socios ACTVOS.</div>`);
    $('#tipPerdidos').attr('title', `<div class="tooltip-data-row"><span>Criterio:</span> <span>> ${umbral} días</span></div><div class="tooltip-data-row"><span>Total c/Compra:</span> <span>${summary.total_club}</span></div><div class="tooltip-formula">Inactivos totales. El % es sobre el total de socios con historial.</div>`);
    $('#tipTicket').attr('title', `<div class="tooltip-data-row"><span>Ventas:</span> <span>${fmt(summary.raw.total_ingresos)}</span></div><div class="tooltip-data-row"><span>Pedidos:</span> <span>${summary.raw.total_pedidos}</span></div>`);
    $('#tipRetention').attr('title', `<div class="tooltip-data-row"><span>Cohorte (Previo):</span> <span>${summary.retention_metrics.h1}</span></div><div class="tooltip-data-row"><span>Retornaron:</span> <span>${summary.retention_metrics.h2}</span></div><div class="tooltip-formula">Clientes del periodo anterior que volvieron en este periodo.</div>`);
    $('#tipParticipation').attr('title', `<div class="tooltip-data-row"><span>Venta Club:</span> <span>${fmt(summary.participacion.club)}</span></div><div class="tooltip-data-row"><span>Venta Gen:</span> <span>${fmt(summary.participacion.general)}</span></div>`);
    $('#tipChurnTotal').attr('title', `<div class="tooltip-data-row"><span>Perdidos:</span> <span>${summary.perdidos}</span></div><div class="tooltip-data-row"><span>Univ. c/Compra:</span> <span>${summary.total_club}</span></div><div class="tooltip-formula">Tasa Churn = (Perdidos / Socios con al menos 1 compra) * 100.</div>`);

    tooltipTriggerList.forEach(el => {
        new bootstrap.Tooltip(el, { sanitize: false });
    });
}

// --- VISUALIZACIONES (CHART.JS) ---

function updateSegmentsChart(segments, revenue) {
    const ctx = document.getElementById('chartSegments').getContext('2d');
    const labels = Object.keys(segments);
    const valores = Object.values(segments);
    const colors = getPalette();
    if (chartSegments) chartSegments.destroy();
    chartSegments = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels.map(getSegmentName),
            datasets: [{ data: valores, backgroundColor: labels.map(l => colors[l]), borderWidth: 0, cutout: '70%' }]
        },
        options: { plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, font: { size: 10 } } } }, maintainAspectRatio: false }
    });

    // Ingresos por Segmento
    const ctxR = document.getElementById('chartSegmentRevenue').getContext('2d');
    if (chartSegmentRevenue) chartSegmentRevenue.destroy();
    chartSegmentRevenue = new Chart(ctxR, {
        type: 'bar',
        data: {
            labels: labels.map(getSegmentName),
            datasets: [{ data: labels.map(l => revenue[l] || 0), backgroundColor: labels.map(l => colors[l]), borderRadius: 5 }]
        },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, maintainAspectRatio: false }
    });
}

function updateEvolutionChart(evolution) {
    const ctx = document.getElementById('chartEvolution').getContext('2d');
    if (chartEvolution) chartEvolution.destroy();

    const segmentLabels = ['Champions', 'Loyal', 'New', 'At Risk', 'Hibernating', 'Lost'];
    const palette = getPalette();

    const datasets = segmentLabels.map(seg => ({
        label: getSegmentName(seg),
        data: evolution.map(e => e[seg] || 0),
        backgroundColor: palette[seg],
        borderRadius: 2
    }));

    chartEvolution = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: evolution.map(e => e.Semana),
            datasets: datasets
        },
        options: {
            plugins: { 
                legend: { display: false },
                tooltip: { stacked: true }
            },
            maintainAspectRatio: false,
            scales: { 
                y: { stacked: true, beginAtZero: true, grid: { display: false } }, 
                x: { stacked: true, grid: { display: false } } 
            }
        }
    });
}

function updateBranchCharts(branchData, globalTicket = 0) {
    const labels = Object.keys(branchData);
    const scores = labels.map(l => branchData[l].score / branchData[l].count);
    const tickets = labels.map(l => branchData[l].period_pedidos > 0 ? branchData[l].period_monto / branchData[l].period_pedidos : 0);

    // 1. Scores
    const ctxS = document.getElementById('chartBranchScores').getContext('2d');
    if (chartBranchScores) chartBranchScores.destroy();
    chartBranchScores = new Chart(ctxS, {
        type: 'bar',
        data: { labels: labels, datasets: [{ label: 'RFM Score', data: scores, backgroundColor: 'rgba(52, 152, 219, 0.6)', borderRadius: 10 }] },
        options: { scales: { y: { max: 15 } } }
    });

    // 2. Distribución Normalizada 100%
    const ctxD = document.getElementById('chartBranchDistribution').getContext('2d');
    if (chartBranchDistribution) chartBranchDistribution.destroy();
    const segmentLabels = ['Champions', 'Loyal', 'New', 'At Risk', 'Hibernating', 'Lost'];
    const palette = getPalette();
    const datasets = segmentLabels.map(seg => ({
        label: getSegmentName(seg),
        data: labels.map(bn => {
            const total = branchData[bn].count;
            return ((branchData[bn].segments[seg] || 0) / total) * 100;
        }),
        backgroundColor: palette[seg]
    }));
    chartBranchDistribution = new Chart(ctxD, {
        type: 'bar',
        data: { labels: labels, datasets: datasets },
        options: { indexAxis: 'y', scales: { x: { stacked: true, max: 100, ticks: { callback: v => v + '%' } }, y: { stacked: true } } }
    });

    // 3. Ticket Benchmarking
    const ctxT = document.getElementById('chartBranchTicket').getContext('2d');
    if (chartBranchTicket) chartBranchTicket.destroy();
    chartBranchTicket = new Chart(ctxT, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Ticket Sucursal', data: tickets, backgroundColor: '#51B8AC', borderRadius: 5 },
                { label: 'Promedio Global', data: Array(labels.length).fill(globalTicket), type: 'line', borderColor: '#ef4444', borderDash: [5, 5], pointRadius: 0 }
            ]
        }
    });

    // 4. TOP 5 LTV Mini Tables
    const $ltvContainer = $('#branchTopLTV').empty();
    labels.forEach(bn => {
        const top5 = branchData[bn].top_5_ltv || [];
        let html = `
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-1">
                    <span class="fw-bold text-primary small"><i class="fas fa-store me-1"></i>${bn}</span>
                </div>
                <div class="list-group list-group-flush shadow-sm rounded">`;

        if (top5.length === 0) {
            html += `<div class="list-group-item small text-muted text-center py-3">Sin datos</div>`;
        } else {
            top5.forEach((c, idx) => {
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0" style="background: rgba(255,255,255,0.05); margin-bottom: 2px;">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-light text-dark rounded-circle me-2" style="width: 20px; height: 20px; font-size: 10px; display: flex; align-items: center; justify-content: center;">${idx + 1}</span>
                            <span class="small text-truncate" style="max-width: 150px;" title="${c.name}">${c.name}</span>
                        </div>
                        <span class="badge bg-soft-success text-success fw-bold">C$ ${parseFloat(c.ltv).toLocaleString('es-NI', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}</span>
                    </div>`;
            });
        }

        html += `</div></div>`;
        $ltvContainer.append(html);
    });
}

function updateHabitSection(habits) {
    // Top Productos
    const $container = $('#topProductsList');
    $container.empty();
    habits.top_products.forEach((p, i) => {
        const perc = (p.Count / habits.top_products[0].Count) * 100;
        $container.append(`
            <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="fw-bold text-nowrap">${i + 1}. ${p.Product}</span>
                    <span class="text-muted">${p.Count}</span>
                </div>
                <div class="progress" style="height: 6px;"><div class="progress-bar bg-primary" style="width: ${perc}%"></div></div>
            </div>
        `);
    });

    // Heatmap (Bubble)
    const ctxH = document.getElementById('chartHeatmap').getContext('2d');
    if (chartHeatmap) chartHeatmap.destroy();
    const maxVal = Math.max(...habits.heatmap.map(h => h.Count));
    chartHeatmap = new Chart(ctxH, {
        type: 'bubble',
        data: {
            datasets: [{
                data: habits.heatmap.map(h => ({ x: h.Hour, y: h.Day, r: 8 })),
                backgroundColor: habits.heatmap.map(h => {
                    const i = h.Count / maxVal;
                    if (i > 0.85) return 'rgba(220, 38, 38, 0.9)'; // Rojo intenso
                    if (i > 0.70) return 'rgba(239, 68, 68, 0.8)'; // Rojo suave
                    if (i > 0.55) return 'rgba(249, 115, 22, 0.8)'; // Naranja fuerte
                    if (i > 0.40) return 'rgba(251, 146, 60, 0.7)'; // Naranja suave
                    if (i > 0.25) return 'rgba(59, 130, 246, 0.6)'; // Azul medio
                    if (i > 0.10) return 'rgba(96, 165, 250, 0.5)'; // Azul suave
                    return 'rgba(219, 234, 254, 0.4)'; // Azul muy claro
                }),
                borderWidth: 1,
                borderColor: 'rgba(255,255,255,0.3)'
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                x: { min: 6, max: 23, title: { display: true, text: 'Hora del Día', font: { size: 10 } } },
                y: { min: 1, max: 7, ticks: { padding: 10, callback: v => ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][v] }, grid: { display: true, drawTicks: false } }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `Ventas: ${ctx.raw.r > 0 ? habits.heatmap[ctx.dataIndex].Count : 0}`
                    }
                }
            }
        }
    });

    // Donuts: Medida, Modalidad, Promo
    chartHabitMeasure = renderDonut('chartHabitMeasure', habits.medida, chartHabitMeasure);
    chartHabitModality = renderDonut('chartHabitModality', habits.modalidad, chartHabitModality);
    chartHabitPromo = renderDonut('chartHabitPromo', { 'Con Promo': habits.promo.si, 'Sin Promo': habits.promo.no }, chartHabitPromo);
}

function renderDonut(id, data, chartRef) {
    const ctx = document.getElementById(id).getContext('2d');
    const $canvas = $(`#${id}`);
    const $parent = $canvas.parent();

    // Eliminar mensaje previo if exists
    $parent.find('.no-data-overlay').remove();

    const labels = Object.keys(data || {});
    const values = Object.values(data || {});
    const total = values.reduce((a, b) => a + b, 0);

    if (chartRef) chartRef.destroy();

    if (total === 0) {
        $canvas.hide();
        $parent.append('<div class="no-data-overlay text-muted small py-5 text-center"><i class="fas fa-chart-pie d-block mb-2 opacity-25" style="font-size: 2rem;"></i>Sin datos suficientes para este periodo</div>');
        return null;
    }

    $canvas.show();
    return new Chart(ctx, {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: values, backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#f97316', '#ef4444', '#64748b'], borderWidth: 0 }] },
        options: { cutout: '60%', plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 8 } } } }, maintainAspectRatio: false }
    });
}

// --- TABLA Y PAGINACIÓN ---

function handleSearch() {
    currentPage = 1;
    applyAllFilters();
}

function applyAllFilters() {
    const searchTerm = $('#tableSearch').val().toLowerCase();

    filteredData = fullClientData.filter(c => {
        // 1. Buscador Global
        const globalMatch = !searchTerm ||
            (c.ClienteNombre && c.ClienteNombre.toLowerCase().includes(searchTerm)) ||
            (c.CodCliente && c.CodCliente.toString().includes(searchTerm));

        if (!globalMatch) return false;

        // 2. Filtros Estándar (filtrosActivos)
        for (const [col, filterVal] of Object.entries(filtrosActivos)) {
            const cellVal = c[col];
            if (cellVal === undefined || cellVal === null) return false;

            const type = $(`th[data-column="${col}"]`).data('type');

            if (type === 'number') {
                const val = parseFloat(cellVal);
                const min = filterVal.min !== undefined && filterVal.min !== '' ? parseFloat(filterVal.min) : -Infinity;
                const max = filterVal.max !== undefined && filterVal.max !== '' ? parseFloat(filterVal.max) : Infinity;
                if (val < min || val > max) return false;
            } else if (type === 'list') {
                if (filterVal.length > 0 && !filterVal.includes(cellVal.toString())) return false;
            } else {
                // Text default
                if (!cellVal.toString().toLowerCase().includes(filterVal.toLowerCase())) return false;
            }
        }
        return true;
    });

    // 3. Ordenamiento
    if (ordenActivo.columna) {
        const col = ordenActivo.columna;
        const dir = ordenActivo.direccion === 'asc' ? 1 : -1;
        filteredData.sort((a, b) => {
            let valA = a[col];
            let valB = b[col];

            // Manejo de nulos
            if (valA === null || valA === undefined) return 1;
            if (valB === null || valB === undefined) return -1;

            if (typeof valA === 'string') {
                return valA.localeCompare(valB) * dir;
            }
            return (valA - valB) * dir;
        });
    }

    renderPaginatedTable();
}

// --- SISTEMA DE FILTROS ESTÁNDAR ---

function toggleFilter(icon, event) {
    if (event) event.stopPropagation();
    
    const th = $(icon).closest('th');
    const columna = th.data('column');
    const tipo = th.data('type');

    if (panelFiltroAbierto === columna) {
        cerrarTodosFiltros();
        return;
    }

    cerrarTodosFiltros();
    crearPanelFiltro(th, columna, tipo, icon);
    panelFiltroAbierto = columna;
    $(icon).addClass('active');
}

function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    $('.filter-icon').removeClass('active');
    panelFiltroAbierto = null;
}

function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel shadow-lg"></div>');

    // Sección de Ordenamiento
    panel.append(`
        <div class="filter-section">
            <span class="filter-section-title">Ordenar por ${th.text().trim()}</span>
            <div class="filter-sort-buttons">
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'asc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'asc')">
                    <i class="bi bi-sort-numeric-down"></i> Menor/A-Z
                </button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'desc')">
                    <i class="bi bi-sort-numeric-up-alt"></i> Mayor/Z-A
                </button>
            </div>
        </div>
    `);

    // Sección de Filtro según Tipo
    if (tipo === 'text') {
        const valorActual = filtrosActivos[columna] || '';
        panel.append(`
            <div class="filter-section">
                <span class="filter-section-title">Contiene:</span>
                <input type="text" class="filter-search" placeholder="Escribir texto..." 
                       value="${valorActual}"
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `);
    } else if (tipo === 'number') {
        const valorMin = filtrosActivos[columna]?.min || '';
        const valorMax = filtrosActivos[columna]?.max || '';
        panel.append(`
            <div class="filter-section">
                <span class="filter-section-title">Rango de Valores:</span>
                <div class="numeric-inputs">
                    <input type="number" class="filter-search" placeholder="Mín" 
                           value="${valorMin}"
                           oninput="filtrarNumerico('${columna}', 'min', this.value)">
                    <input type="number" class="filter-search" placeholder="Máx" 
                           value="${valorMax}"
                           oninput="filtrarNumerico('${columna}', 'max', this.value)">
                </div>
            </div>
        `);
    } else if (tipo === 'list') {
        cargarOpcionesFiltroLocal(panel, columna);
    }

    // Botones de Acción
    panel.append(`
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-eraser-fill me-1"></i> Borrar Filtro
            </button>
        </div>
    `);

    $('body').append(panel);
    posicionarPanelFiltro(panel, icon);
}

function filtrarBusqueda(col, val) {
    if (!val) delete filtrosActivos[col];
    else filtrosActivos[col] = val;
    actualizarIndicadoresFiltros();
    currentPage = 1;
    applyAllFilters();
}

function filtrarNumerico(col, sub, val) {
    if (!filtrosActivos[col]) filtrosActivos[col] = { min: '', max: '' };
    filtrosActivos[col][sub] = val;

    if (!filtrosActivos[col].min && !filtrosActivos[col].max) delete filtrosActivos[col];

    actualizarIndicadoresFiltros();
    currentPage = 1;
    applyAllFilters();
}

function cargarOpcionesFiltroLocal(panel, columna) {
    // Extraer valores únicos de fullClientData
    let opciones = [...new Set(fullClientData.map(c => c[columna]))]
        .filter(v => v !== null && v !== undefined && v !== '')
        .sort((a, b) => a.toString().localeCompare(b.toString()));

    let html = `
        <div class="filter-section">
            <span class="filter-section-title">Seleccionar de la lista:</span>
            <input type="text" class="filter-search mb-2" placeholder="Buscar en lista..." onkeyup="buscarEnOpciones(this)">
            <div class="filter-options">
    `;

    opciones.forEach(opt => {
        const isChecked = (filtrosActivos[columna] || []).includes(opt.toString());
        const display = (columna === 'Segment') ? getSegmentName(opt) : opt;
        html += `
            <label class="filter-option">
                <input type="checkbox" value="${opt}" ${isChecked ? 'checked' : ''} 
                       onchange="toggleOpcionFiltro('${columna}', this.value, this.checked)">
                <span class="text-truncate">${display}</span>
            </label>
        `;
    });

    html += `</div></div>`;
    panel.append(html);
}

function toggleOpcionFiltro(col, val, checked) {
    if (!filtrosActivos[col]) filtrosActivos[col] = [];
    if (checked) {
        if (!filtrosActivos[col].includes(val)) filtrosActivos[col].push(val);
    } else {
        filtrosActivos[col] = filtrosActivos[col].filter(v => v !== val);
    }
    if (filtrosActivos[col].length === 0) delete filtrosActivos[col];

    actualizarIndicadoresFiltros();
    currentPage = 1;
    applyAllFilters();
}

function buscarEnOpciones(input) {
    const val = input.value.toLowerCase();
    $(input).siblings('.filter-options').find('.filter-option').each(function () {
        const text = $(this).text().toLowerCase();
        $(this).toggle(text.includes(val));
    });
}

function aplicarOrden(columna, direccion) {
    ordenActivo = { columna, direccion };
    $('.filter-sort-btn').removeClass('active');
    $(`.filter-sort-btn[onclick*="'${columna}', '${direccion}'"]`).addClass('active');
    currentPage = 1;
    applyAllFilters();
    // Cerramos el panel después de ordenar
    cerrarTodosFiltros();
}

function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    actualizarIndicadoresFiltros();
    currentPage = 1;
    applyAllFilters();
    cerrarTodosFiltros();
}

function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(col => {
        $(`th[data-column="${col}"] .filter-icon`).addClass('has-filter');
    });
}

function posicionarPanelFiltro(panel, icon) {
    const offset = $(icon).offset();
    const iconH = $(icon).outerHeight();
    const panelW = panel.outerWidth();
    const winW = $(window).width();

    let left = offset.left - panelW + 20;
    if (left < 10) left = 10;
    if (left + panelW > winW) left = winW - panelW - 10;

    panel.css({
        top: (offset.top + iconH + 8) + 'px',
        left: left + 'px'
    });
}

function renderPaginatedTable() {
    const $body = $('#rfmTableBody');
    $body.empty();

    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const paginatedItems = filteredData.slice(start, end);
    const umbral = $('#umbral_perdido').val();

    if (paginatedItems.length === 0) {
        $body.append('<tr><td colspan="9" class="text-center py-5 text-muted">No se encontraron socios con este criterio.</td></tr>');
    }

    paginatedItems.forEach(c => {
        const rColor = c.Recency >= umbral ? 'text-danger' :
            (c.Recency >= (umbral / 2) ? 'text-warning' : '');
        $body.append(`
            <tr>
                <td>
                    <div class="fw-bold text-dark small">${c.CodCliente}: ${c.ClienteNombre || 'Innominado'}</div>
                </td>
                <td><span class="badge border text-dark opacity-75">${c.Sucursal || 'N/A'}</span></td>
                <td class="${rColor} fw-bold">${c.Recency}d</td>
                <td class="text-center">${c.Frequency}</td>
                <td class="fw-bold">${fmt(c.Monetary)}</td>
                <td class="text-teal">${fmt(c.TicketPromedio)}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="x-small text-muted">(${c.R_Score}, ${c.F_Score}, ${c.M_Score})</span>
                        <span class="x-small fw-bold border rounded px-1">Total: ${c.ScoreTotal}</span>
                    </div>
                </td>
                <td><span class="badge ${getSegmentBadge(c.Segment)} shadow-sm">${getSegmentName(c.Segment)}</span></td>
                <td class="small">${c.Antiguedad}d</td>
                <td class="small" title="${c.UltimoProducto || ''}">${(c.UltimoProducto || '--').substring(0, 12)}...</td>
                <td>
                    <button class="btn btn-sm btn-light border" onclick="verDetalle(${c.CodCliente})"><i class="fas fa-eye"></i></button>
                </td>
            </tr>
        `);
    });

    updatePaginationControls();
}

function updatePaginationControls() {
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    const $controls = $('#paginationControls');
    const $info = $('#paginationInfo');

    $controls.empty();

    // Info
    const startCount = filteredData.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0;
    const endCount = Math.min(currentPage * itemsPerPage, filteredData.length);
    $info.text(`Mostrando ${startCount} a ${endCount} de ${filteredData.length} socios`);

    if (totalPages <= 1) return;

    // Previous
    $controls.append(`
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="changePage(${currentPage - 1})"><i class="fas fa-chevron-left"></i></a>
        </li>
    `);

    // Numbered Pages (Limited logic to not show 100 buttons)
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

    for (let i = startPage; i <= endPage; i++) {
        $controls.append(`
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="javascript:void(0)" onclick="changePage(${i})">${i}</a>
            </li>
        `);
    }

    // Next
    $controls.append(`
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="changePage(${currentPage + 1})"><i class="fas fa-chevron-right"></i></a>
        </li>
    `);
}

function changePage(p) {
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    if (p < 1 || p > totalPages) return;
    currentPage = p;
    renderPaginatedTable();

    // Smooth scroll to table top
    document.getElementById('rfmTableMaster').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// --- UTILS ---

function getPalette() {
    return {
        'Champions': '#10b981',
        'Loyal': '#3b82f6',
        'New': '#f59e0b',
        'At Risk': '#f97316',
        'Hibernating': '#64748b',
        'Lost': '#ef4444'
    };
}

function getSegmentName(key) {
    const names = { 'Champions': 'Campeones', 'Loyal': 'Leales', 'New': 'Nuevos', 'At Risk': 'En Riesgo', 'Hibernating': 'Hibernando', 'Lost': 'Perdidos' };
    return names[key] || key;
}

function getSegmentBadge(key) {
    const badges = { 'Champions': 'bg-success', 'Loyal': 'bg-primary', 'New': 'bg-warning text-dark', 'At Risk': 'bg-orange', 'Hibernating': 'bg-secondary', 'Lost': 'bg-danger' };
    return badges[key] || 'bg-light text-dark';
}

function animateValue(id, value, isCurrency = false, suffix = '') {
    const obj = document.getElementById(id); if (!obj) return;
    const end = parseFloat(value);
    $({ val: 0 }).animate({ val: end }, {
        duration: 1000,
        easing: 'swing',
        step: function () { obj.innerHTML = (isCurrency ? fmt(this.val) : Math.floor(this.val).toLocaleString()) + suffix; },
        complete: function () { obj.innerHTML = (isCurrency ? fmt(end) : Math.floor(end).toLocaleString()) + suffix; }
    });
}

function fmt(val) { return 'C$ ' + new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val); }

function debounce(func, wait) {
    let timeout;
    return function () {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

function exportData(type) {
    const params = {
        fecha_inicio: $('#fecha_inicio').val(),
        fecha_fin: $('#fecha_fin').val(),
        sucursal: $('#filtro_sucursal').val(),
        type: type
    };
    window.location.href = `ajax/dashboard_rfm_exportar.php?${$.param(params)}`;
}

function verDetalle(id) {
    if (!id) return;
    window.open(`/modulos/atencioncliente/historial_productos.php?membresia=${id}`, '_blank');
}

