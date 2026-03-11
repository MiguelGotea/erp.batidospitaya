let chartSegments = null;
let chartIngresos = null;

$(document).ready(function() {
    cargarSucursales();
    cargarDatos();
});

function cargarSucursales() {
    $.get('/modulos/marketing/ajax/get_sucursales.php', function(res) {
        if (res.success) {
            let html = '<option value="">Todas las sucursales</option>';
            res.data.forEach(s => {
                html += `<option value="${s.nombre}">${s.nombre}</option>`;
            });
            $('#filtro_sucursal').html(html);
        }
    });
}

async function cargarDatos() {
    Swal.fire({
        title: 'Cargando indicadores...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    const f_inicio = $('#fecha_inicio').val();
    const f_fin = $('#fecha_fin').val();
    const sucursal = $('#filtro_sucursal').val();

    try {
        const response = await fetch(`ajax/dashboard_rfm_get_datos.php?fecha_inicio=${f_inicio}&fecha_fin=${f_fin}&sucursal=${sucursal}`);
        const data = await response.json();

        updateDashboard(data);
        Swal.close();
    } catch (error) {
        console.error(error);
        Swal.fire('Error', 'Hubo un problema al cargar los datos', 'error');
    }
}

function updateDashboard(data) {
    if (!data.success) {
        Swal.fire('Error', data.message, 'error');
        return;
    }

    const summary = data.summary;
    const habits = data.habits;
    const ingresos = data.ingresos;

    // Actualizar KPIs
    animateValue('kpiTotalClub', summary.total_club);
    animateValue('kpiActivos', summary.activos);
    animateValue('kpiTicket', summary.ticket_promedio, true);
    animateValue('kpiAntiguedad', summary.antiguedad_promedio);
    animateValue('kpiChurn', summary.churn_rate, false, '%');

    // Gráfico de Segmentos
    updateSegmentsChart(data.segments);

    // Hábitos
    $('#habitProduct').text(habits.fav_product);
    $('#habitSize').text(habits.fav_size);
    $('#habitModalidad').text(habits.fav_modalidad);
    $('#habitPromo').text(habits.perc_promo + '%');

    // Ingresos
    const club = parseFloat(ingresos.IngresosClub || 0);
    const general = parseFloat(ingresos.IngresosGeneral || 0);
    const total = club + general;
    const perc = total > 0 ? (club / total) * 100 : 0;

    $('#ingresoClub').text(fmt(club));
    $('#ingresoGeneral').text(fmt(general));
    $('#percClub').text(perc.toFixed(1) + '%');
    $('#progressClub').css('width', perc + '%');

    // Top 10 Clientes
    renderTop10(data.top_10);
}

function renderTop10(clientes) {
    const $body = $('#tableTopClients');
    $body.empty();

    if (!clientes || clientes.length === 0) {
        $body.append('<tr><td colspan="5" class="text-center py-4 opacity-50">No hay datos disponibles</td></tr>');
        return;
    }

    clientes.forEach((c, i) => {
        const badgeClass = getSegmentBadge(c.Segment);
        $body.append(`
            <tr>
                <td class="fw-bold">#${i + 1}</td>
                <td>
                    <div class="fw-bold text-dark">${c.ClienteNombre || 'Anónimo'}</div>
                    <div class="small text-muted">ID: ${c.CodCliente}</div>
                </td>
                <td class="text-center">${c.Frequency}</td>
                <td class="fw-bold text-teal">${fmt(c.Monetary)}</td>
                <td><span class="badge ${badgeClass}">${getSegmentName(c.Segment)}</span></td>
            </tr>
        `);
    });
}

function updateSegmentsChart(segments) {
    const ctx = document.getElementById('chartSegments').getContext('2d');
    const labels = Object.keys(segments);
    const valores = Object.values(segments);

    const colors = {
        'Champions': '#198754',
        'Loyal': '#0d6efd',
        'New': '#ffc107',
        'At Risk': '#fd7e14',
        'Hibernating': '#6c757d',
        'Lost': '#dc3545'
    };

    const backgroundColors = labels.map(l => colors[l] || '#adb5bd');

    if (chartSegments) chartSegments.destroy();

    chartSegments = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels.map(getSegmentName),
            datasets: [{
                data: valores,
                backgroundColor: backgroundColors,
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: { display: false }
            }
        }
    });

    // Renderizar leyenda personalizada
    const $legend = $('#segmentLegend');
    $legend.empty();
    labels.forEach((l, i) => {
        const totalValores = valores.reduce((a, b) => a + b, 0);
        const perc = totalValores > 0 ? (valores[i] / totalValores * 100).toFixed(1) : 0;
        $legend.append(`
            <div class="segment-legend d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge" style="background: ${backgroundColors[i]}">${getSegmentName(l)}</span>
                </div>
                <div class="fw-bold">${perc}%</div>
            </div>
        `);
    });
}

function getSegmentName(key) {
    const names = {
        'Champions': 'Campeones',
        'Loyal': 'Leales',
        'New': 'Nuevos',
        'At Risk': 'En Riesgo',
        'Hibernating': 'Hibernando',
        'Lost': 'Perdidos'
    };
    return names[key] || key;
}

function getSegmentBadge(key) {
    const badges = {
        'Champions': 'bg-success',
        'Loyal': 'bg-primary',
        'New': 'bg-warning text-dark',
        'At Risk': 'bg-orange',
        'Hibernating': 'bg-secondary',
        'Lost': 'bg-danger'
    };
    return badges[key] || 'bg-light text-dark';
}

function animateValue(id, value, isCurrency = false, suffix = '') {
    const obj = document.getElementById(id);
    if(!obj) return;
    const start = 0;
    const end = parseFloat(value);
    const duration = 1000;
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const current = progress * (end - start) + start;
        obj.innerHTML = (isCurrency ? fmt(current) : Math.floor(current).toLocaleString('en-US')) + suffix;
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

function fmt(val) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
}

// Exportar
$(document).on('click', '#btnExportar', function() {
    const f_inicio = $('#fecha_inicio').val();
    const f_fin = $('#fecha_fin').val();
    const sucursal = $('#filtro_sucursal').val();
    window.location.href = `ajax/dashboard_rfm_exportar.php?fecha_inicio=${f_inicio}&fecha_fin=${f_fin}&sucursal=${sucursal}`;
});

// Listener para el formulario de filtros
$('#filterForm').on('submit', function(e) {
    e.preventDefault();
    cargarDatos();
});
