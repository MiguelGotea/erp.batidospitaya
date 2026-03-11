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

$(document).ready(function() {
    cargarSucursales();
    cargarDatos();
    
    // Event Listeners
    $('#filterForm').on('submit', (e) => { e.preventDefault(); cargarDatos(); });
    $('#tableSearch').on('keyup', debounce(handleSearch, 300));
    $('#btnExportFull').on('click', () => exportData('full'));
});

// --- CARGA DE DATOS ---

function cargarSucursales() {
    $.get('/modulos/marketing/ajax/get_sucursales.php', function(res) {
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
        tipo_cliente: $('#tipo_cliente').val(),
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

// --- ACTUALIZACIÓN DE UI ---

function updateDashboard(data) {
    updateKPIs(data.summary);
    updateSegmentsChart(data.segments, data.segment_revenue);
    updateEvolutionChart(data.evolution);
    renderPaginatedTable();
    updateBranchCharts(data.branch_analysis, data.summary.ticket_club);
    updateHabitSection(data.habits);
}
function updateKPIs(summary) {
    if (!summary) return;
    animateValue('kpiTotalClub', summary.activos);
    animateValue('kpiNuevos', summary.nuevos);
    animateValue('kpiEnRiesgo', summary.en_riesgo);
    animateValue('kpiPerdidos', summary.perdidos);
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

    $('#tipClubActivos').attr('title', `<div class="tooltip-data-row"><span>Criterio:</span> <span><= ${umbral} días</span></div><div class="tooltip-data-row"><span>Total Activos:</span> <span>${summary.activos}</span></div><div class="tooltip-formula">Socios con al menos una compra en los últimos ${umbral} días.</div>`);
    $('#tipNuevos').attr('title', `<div class="tooltip-data-row"><span>Registros:</span> <span>${summary.nuevos}</span></div><div class="tooltip-data-row"><span>Previo:</span> <span>${summary.prev_nuevos}</span></div><div class="tooltip-formula">Comparado contra el periodo anterior equivalente.</div>`);
    $('#tipEnRiesgo').attr('title', `<div class="tooltip-data-row"><span>Criterio:</span> <span>${Math.floor(umbral/2)}-${umbral} días</span></div><div class="tooltip-formula">Socios enfriándose.</div>`);
    $('#tipPerdidos').attr('title', `<div class="tooltip-data-row"><span>Criterio:</span> <span>> ${umbral} días</span></div><div class="tooltip-formula">Inactivos totales.</div>`);
    $('#tipTicket').attr('title', `<div class="tooltip-data-row"><span>Ventas:</span> <span>${fmt(summary.raw.total_ingresos)}</span></div><div class="tooltip-data-row"><span>Pedidos:</span> <span>${summary.raw.total_pedidos}</span></div>`);
    $('#tipRetention').attr('title', `<div class="tooltip-data-row"><span>H1 → H2:</span> <span>${summary.retention_metrics.h2} de ${summary.retention_metrics.h1}</span></div>`);
    $('#tipParticipation').attr('title', `<div class="tooltip-data-row"><span>Venta Club:</span> <span>${fmt(summary.participacion.club)}</span></div><div class="tooltip-data-row"><span>Venta Gen:</span> <span>${fmt(summary.participacion.general)}</span></div>`);
    $('#tipChurnTotal').attr('title', `<div class="tooltip-data-row"><span>Perdidos:</span> <span>${summary.perdidos}</span></div><div class="tooltip-formula">Porcentaje de pérdida sobre base filtrada.</div>`);

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
    
    chartEvolution = new Chart(ctx, {
        type: 'line',
        data: {
            labels: evolution.map(e => 'Sem ' + e.Semana),
            datasets: [{
                label: 'Pedidos por Semana',
                data: evolution.map(e => e.Pedidos),
                borderColor: '#51B8AC',
                backgroundColor: 'rgba(81, 184, 172, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } }
        }
    });
}

function updateBranchCharts(branchData, globalTicket = 0) {
    const labels = Object.keys(branchData);
    const scores = labels.map(l => branchData[l].score / branchData[l].count);
    const tickets = labels.map(l => branchData[l].monto / branchData[l].count);
    
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
    const $ltvRow = $('#branchTopLTV').empty();
    labels.forEach(bn => {
        $ltvRow.append(`
            <div class="mb-3 border-bottom pb-2">
                <div class="fw-bold mb-1"><i class="fas fa-store me-2"></i>${bn}</div>
                <div class="small text-muted">Próximamente: Integración con historial extendido</div>
            </div>
        `);
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
                    <span class="fw-bold text-nowrap">${i+1}. ${p.Product}</span>
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
                data: habits.heatmap.map(h => ({ x: h.Hour, y: h.Day, r: Math.sqrt(h.Count) * 2 + 2 })),
                backgroundColor: habits.heatmap.map(h => {
                    const i = h.Count / maxVal;
                    return i > 0.8 ? 'rgba(239, 68, 68, 0.8)' : (i > 0.4 ? 'rgba(249, 115, 22, 0.7)' : 'rgba(59, 130, 246, 0.6)');
                })
            }]
        },
        options: {
            aspectRatio: 2.2,
            maintainAspectRatio: true,
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
    const labels = Object.keys(data);
    const values = Object.values(data);
    if (chartRef) chartRef.destroy();
    return new Chart(ctx, {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: values, backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#f97316', '#ef4444', '#64748b'], borderWidth: 0 }] },
        options: { cutout: '60%', plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 9 } } } }, maintainAspectRatio: false }
    });
}

// --- TABLA Y PAGINACIÓN ---

function handleSearch() {
    const term = $('#tableSearch').val().toLowerCase();
    filteredData = fullClientData.filter(c => 
        (c.ClienteNombre && c.ClienteNombre.toLowerCase().includes(term)) || 
        c.CodCliente.toString().includes(term)
    );
    currentPage = 1; // Reset to page 1 on search
    renderPaginatedTable();
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
                    <div class="fw-bold text-dark">${c.ClienteNombre || 'Innominado'}</div>
                    <div class="small text-muted">ID: ${c.CodCliente}</div>
                </td>
                <td><span class="badge border text-dark opacity-75">${c.Sucursal || 'N/A'}</span></td>
                <td class="${rColor} fw-bold">${c.Recency}d</td>
                <td class="text-center">${c.Frequency}</td>
                <td class="fw-bold">${fmt(c.Monetary)}</td>
                <td class="text-teal">${fmt(c.TicketPromedio)}</td>
                <td>
                    <div class="d-flex gap-1 mb-1">
                        <span class="badge-score score-${c.R_Score}">${c.R_Score}</span>
                        <span class="badge-score score-${c.F_Score}">${c.F_Score}</span>
                        <span class="badge-score score-${c.M_Score}">${c.M_Score}</span>
                    </div>
                    <div class="small fw-bold text-center border rounded">Total: ${c.ScoreTotal}</div>
                </td>
                <td><span class="badge ${getSegmentBadge(c.Segment)} shadow-sm">${getSegmentName(c.Segment)}</span></td>
                <td class="small">${c.Antiguedad}d</td>
                <td class="small" title="${c.UltimoProducto || ''}">${(c.UltimoProducto || '--').substring(0,12)}...</td>
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
    const obj = document.getElementById(id); if(!obj) return;
    const end = parseFloat(value);
    $({ val: 0 }).animate({ val: end }, {
        duration: 1000,
        easing: 'swing',
        step: function() { obj.innerHTML = (isCurrency ? fmt(this.val) : Math.floor(this.val).toLocaleString()) + suffix; },
        complete: function() { obj.innerHTML = (isCurrency ? fmt(end) : Math.floor(end).toLocaleString()) + suffix; }
    });
}

function fmt(val) { return 'C$ ' + new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val); }

function debounce(func, wait) {
    let timeout;
    return function() {
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
    Swal.fire({ title: 'Perfil del Socio', text: 'Detalle del socio ID: ' + id, icon: 'info' });
}
