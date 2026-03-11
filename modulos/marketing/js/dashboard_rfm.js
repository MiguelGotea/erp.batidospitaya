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

        if (data.success) {
            actualizarKPIs(data.summary);
            actualizarSegmentosChart(data.segments);
            actualizarHabitos(data.habits);
            actualizarIngresosChart(data.ingresos);
            Swal.close();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        console.error(error);
        Swal.fire('Error', 'Hubo un problema al cargar los datos', 'error');
    }
}

function actualizarKPIs(summary) {
    if (!summary) return;
    $('#kpi_total_club').text(summary.total_club.toLocaleString());
    $('#kpi_activos').text(summary.activos.toLocaleString());
    $('#kpi_churn').text(`${summary.churn_rate}%`);
    $('#kpi_ticket').text(`C$ ${summary.ticket_promedio.toFixed(2)}`);
}

function actualizarSegmentosChart(segments) {
    const ctx = document.getElementById('chart_segmentos').getContext('2d');
    
    if (chartSegments) chartSegments.destroy();

    const labels = Object.keys(segments);
    const valores = Object.values(segments);

    chartSegments = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: valores,
                backgroundColor: [
                    '#198754', // Champions - Verde
                    '#0d6efd', // Loyal - Azul
                    '#ffc107', // New - Amarillo/Dorado
                    '#fd7e14', // At Risk - Naranja
                    '#6c757d', // Hibernating - Gris
                    '#dc3545'  // Lost - Rojo
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            },
            cutout: '70%'
        }
    });
}

function actualizarHabitos(habits) {
    $('#habit_fav_product').text(habits.fav_product);
    $('#habit_fav_size').text(habits.fav_size);
    $('#habit_fav_modalidad').text(habits.fav_modalidad);
    $('#habit_perc_promo').text(`${habits.perc_promo}%`);
    $('#habit_redenciones').text(habits.redenciones.toLocaleString());
}

function actualizarIngresosChart(ingresos) {
    const ctx = document.getElementById('chart_ingresos').getContext('2d');
    
    if (chartIngresos) chartIngresos.destroy();

    const labels = ['Club', 'General'];
    const valores = [ingresos.IngresosClub, ingresos.IngresosGeneral];

    chartIngresos = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Monto C$',
                data: valores,
                backgroundColor: ['#51B8AC', '#cbd5e0'],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}
