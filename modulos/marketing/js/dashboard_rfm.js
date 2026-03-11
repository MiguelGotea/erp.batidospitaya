// Dashboard RFM 2.0 - Intelligence & Loyalty Logic
let chartSegments = null;
let chartEvolution = null;
let chartBranchScores = null;
let chartBranchDistribution = null;
let chartHeatmap = null;

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
    // 1. KPIs
    updateKPIs(data.summary);
    
    // 2. Segmentos y Evolución
    updateSegmentsChart(data.segments);
    updateEvolutionChart(data.evolution);
    
    // 3. Tabla Individual (Paginada)
    renderPaginatedTable();
    
    // 4. Sucursales
    updateBranchCharts(data.branch_analysis);
    
    // 5. Hábitos
    updateHabitSection(data.habits);
}

function updateKPIs(summary) {
    if (!summary) return;
    animateValue('kpiTotalClub', summary.total_club);
    animateValue('kpiNuevos', summary.nuevos);
    animateValue('kpiEnRiesgo', summary.en_riesgo);
    animateValue('kpiPerdidos', summary.perdidos);
    animateValue('kpiTicket', summary.ticket_club, true);
    animateValue('kpiRetention', summary.retention_rate, false, '%');

    // Actualizar Tooltips con fórmulas
    const umbral = $('#umbral_perdido').val();
    
    $('#tipClubActivos').attr('title', `Socio con compras en los últimos ${umbral} días`);
    $('#tipNuevos').attr('title', `Socios con fecha de registro en el periodo seleccionado`);
    $('#tipEnRiesgo').attr('title', `Inactividad entre ${umbral/2} y ${umbral} días`);
    $('#tipPerdidos').attr('title', `Inactividad mayor a ${umbral} días`);
    $('#tipTicket').attr('title', `<b>Fórmula:</b> Ingresos / Pedidos Totales`);
    $('#tipRetention').attr('title', `<b>Fórmula:</b> (Regresaron H2 / Compraron H1) * 100 <br><small>H1: Primera mitad periodo | H2: Segunda mitad</small>`);

    // Reinicializar tooltips de Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        // Primero destruir el anterior para evitar duplicados
        const oldTip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
        if (oldTip) oldTip.dispose();
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// --- VISUALIZACIONES (CHART.JS) ---

function updateSegmentsChart(segments) {
    const ctx = document.getElementById('chartSegments').getContext('2d');
    const labels = Object.keys(segments);
    const valores = Object.values(segments);
    const colors = getPalette();

    if (chartSegments) chartSegments.destroy();
    chartSegments = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels.map(getSegmentName),
            datasets: [{
                data: valores,
                backgroundColor: labels.map(l => colors[l]),
                borderWidth: 0,
                cutout: '70%'
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, font: { size: 10 } } } },
            maintainAspectRatio: false
        }
    });
}

function updateEvolutionChart(evolution) {
    const ctx = document.getElementById('chartEvolution').getContext('2d');
    if (chartEvolution) chartEvolution.destroy();
    
    chartEvolution = new Chart(ctx, {
        type: 'line',
        data: {
            labels: evolution.map(e => 'Sem ' + e.Semana.split('-')[1]),
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

function updateBranchCharts(branchData) {
    const labels = Object.keys(branchData);
    const scores = labels.map(l => branchData[l].score / branchData[l].count);
    
    // Scores por sucursal
    const ctxS = document.getElementById('chartBranchScores').getContext('2d');
    if (chartBranchScores) chartBranchScores.destroy();
    chartBranchScores = new Chart(ctxS, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'RFM Score Promedio',
                data: scores,
                backgroundColor: 'rgba(52, 152, 219, 0.6)',
                borderRadius: 10
            }]
        },
        options: { responsive: true, scales: { y: { max: 15 } } }
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
                    <span class="fw-bold">${i+1}. ${p.Product}</span>
                    <span class="text-muted">${p.Count} pedidos</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-primary" style="width: ${perc}%"></div>
                </div>
            </div>
        `);
    });

    // Heatmap (Simulado con Matrix o Bubble si no hay plugin, usaremos Bubble simplificado)
    const ctxH = document.getElementById('chartHeatmap').getContext('2d');
    if (chartHeatmap) chartHeatmap.destroy();
    
    chartHeatmap = new Chart(ctxH, {
        type: 'bubble',
        data: {
            datasets: [{
                label: 'Intensidad',
                data: habits.heatmap.map(h => ({ x: h.Hour, y: h.Day, r: Math.log(h.Count + 1) * 3 })),
                backgroundColor: 'rgba(81, 184, 172, 0.5)'
            }]
        },
        options: {
            scales: {
                x: { title: { display: true, text: 'Hora del día' }, min: 6, max: 23 },
                y: { title: { display: true, text: 'Día de Semana' }, min: 1, max: 7, ticks: { callback: v => ['','Dom','Lun','Mar','Mie','Jue','Vie','Sab'][v] } }
            },
            plugins: { legend: { display: false } }
        }
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
        const rColor = c.Recency > umbral ? 'text-danger' : '';
        $body.append(`
            <tr>
                <td>
                    <div class="fw-bold text-dark">${c.ClienteNombre || 'Innominado'}</div>
                    <div class="small text-muted">Membresía: ${c.CodCliente}</div>
                </td>
                <td><span class="badge border text-dark opacity-75">${c.Sucursal || 'N/A'}</span></td>
                <td class="${rColor} fw-bold">${c.Recency}d</td>
                <td class="text-center">${c.Frequency}</td>
                <td class="fw-bold text-teal">${fmt(c.Monetary)}</td>
                <td>
                    <span class="badge-score score-${c.R_Score}">${c.R_Score}</span>
                    <span class="badge-score score-${c.F_Score}">${c.F_Score}</span>
                    <span class="badge-score score-${c.M_Score}">${c.M_Score}</span>
                </td>
                <td class="fw-bold ps-3">${c.ScoreTotal}</td>
                <td><span class="badge ${getSegmentBadge(c.Segment)} shadow-sm">${getSegmentName(c.Segment)}</span></td>
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
