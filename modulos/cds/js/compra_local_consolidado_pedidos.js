/* ===================================================
   Consolidado de Pedidos - JavaScript
   =================================================== */

let consolidado = [];
let sucursales = [];
let currentDates = [];
let filtros = {
    sucursal: ''
};

// Días de la semana
const diasSemana = {
    1: 'Lunes',
    2: 'Martes',
    3: 'Miércoles',
    4: 'Jueves',
    5: 'Viernes',
    6: 'Sábado',
    7: 'Domingo'
};

const diasSemanaCorto = {
    1: 'Lun',
    2: 'Mar',
    3: 'Mié',
    4: 'Jue',
    5: 'Vie',
    6: 'Sáb',
    7: 'Dom'
};

// Inicializar
$(document).ready(function () {
    calcularDiasAlrededor();
    cargarSucursales();
    cargarConsolidado();
});

// Calcular días: 3 antes de hoy, hoy, 3 después de hoy
function calcularDiasAlrededor() {
    const hoy = new Date();

    currentDates = [];

    // 3 días antes de hoy
    for (let i = 3; i > 0; i--) {
        const fecha = new Date(hoy);
        fecha.setDate(hoy.getDate() - i);
        currentDates.push(fecha);
    }

    // Hoy
    currentDates.push(new Date(hoy));

    // 3 días después de hoy
    for (let i = 1; i <= 3; i++) {
        const fecha = new Date(hoy);
        fecha.setDate(hoy.getDate() + i);
        currentDates.push(fecha);
    }
}

// Obtener día de la semana (1-7) de una fecha
function getDiaSemana(fecha) {
    const dia = fecha.getDay();
    return dia === 0 ? 7 : dia; // Convertir Domingo (0) a 7
}

// Formatear fecha para mostrar
function formatearFecha(fecha) {
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const dia = fecha.getDate();
    const mes = meses[fecha.getMonth()];
    return `${dia}-${mes}`;
}

// Cargar lista de sucursales para el filtro
function cargarSucursales() {
    $.ajax({
        url: 'ajax/compra_local_consolidado_pedidos_get_sucursales.php',
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                sucursales = response.sucursales;
                renderizarFiltroSucursales();
            }
        },
        error: function () {
            console.error('Error al cargar sucursales');
        }
    });
}

// Renderizar opciones del filtro de sucursales
function renderizarFiltroSucursales() {
    let options = '<option value="">Todas las sucursales</option>';
    sucursales.forEach(sucursal => {
        options += `<option value="${sucursal.codigo}">${sucursal.nombre}</option>`;
    });
    $('#filtro-sucursal').html(options);
}

// Aplicar filtros
function aplicarFiltros() {
    filtros.sucursal = $('#filtro-sucursal').val();
    cargarConsolidado();
}

// Cargar datos consolidados
function cargarConsolidado() {
    $('#consolidado-container').html(`
        <div class="loader-container">
            <div class="loader"></div>
        </div>
    `);

    $.ajax({
        url: 'ajax/compra_local_consolidado_pedidos_get_datos.php',
        method: 'POST',
        data: filtros,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                consolidado = response.consolidado;
                renderizarTabla();
            } else {
                mostrarError('Error al cargar datos: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión al cargar datos');
        }
    });
}

// Renderizar tabla consolidada
function renderizarTabla() {
    if (consolidado.length === 0) {
        $('#consolidado-container').html(`
            <div class="no-data-message">
                <i class="bi bi-inbox"></i>
                <p>No hay pedidos registrados</p>
            </div>
        `);
        return;
    }

    // Agrupar por producto
    const productosPedidos = agruparPorProducto();

    let html = `
        <div class="table-responsive">
            <table class="table consolidado-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        ${currentDates.map((fecha, index) => {
        const diaSemana = getDiaSemana(fecha);
        const diaInfo = diasSemanaCorto[diaSemana];
        const esHoy = index === 3;
        return `
                            <th ${esHoy ? 'class="today-column"' : ''}>
                                <div class="day-header">
                                    <span class="day-name">${diaInfo}${esHoy ? ' (HOY)' : ''}</span>
                                    <span class="day-date">${formatearFecha(fecha)}</span>
                                </div>
                            </th>
                        `;
    }).join('')}
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
    `;

    let totalGeneral = 0;
    const totalesPorDia = new Array(7).fill(0);

    productosPedidos.forEach(producto => {
        let totalProducto = 0;

        html += `
            <tr>
                <td class="producto-name">${producto.nombre}</td>
        `;

        currentDates.forEach((fecha, index) => {
            const diaSemana = getDiaSemana(fecha);
            const cantidad = producto.cantidadesPorDia[diaSemana] || 0;
            const esHoy = index === 3;

            totalProducto += cantidad;
            totalesPorDia[index] += cantidad;

            html += `
                <td class="data-cell ${cantidad > 0 ? 'has-value' : 'no-value'} ${esHoy ? 'today-column' : ''}"
                    ${cantidad > 0 ? `onclick="verDetalle('${producto.id}', ${diaSemana})"` : ''}>
                    ${cantidad > 0 ? cantidad : '-'}
                </td>
            `;
        });

        totalGeneral += totalProducto;

        html += `
                <td class="total-cell">${totalProducto}</td>
            </tr>
        `;
    });

    // Fila de totales
    html += `
                <tr class="totals-row">
                    <td><strong>TOTAL</strong></td>
                    ${totalesPorDia.map((total, index) => `
                        <td class="${index === 3 ? 'today-column' : ''}"><strong>${total}</strong></td>
                    `).join('')}
                    <td><strong>${totalGeneral}</strong></td>
                </tr>
    `;

    html += `
                </tbody>
            </table>
        </div>
    `;

    $('#consolidado-container').html(html);
}

// Agrupar consolidado por producto
function agruparPorProducto() {
    const productos = {};

    consolidado.forEach(item => {
        if (!productos[item.id_producto_presentacion]) {
            productos[item.id_producto_presentacion] = {
                id: item.id_producto_presentacion,
                nombre: item.nombre_producto,
                cantidadesPorDia: {}
            };
        }

        productos[item.id_producto_presentacion].cantidadesPorDia[item.dia_entrega] =
            parseInt(item.total_cantidad);
    });

    return Object.values(productos);
}

// Ver detalle de pedidos por sucursal
function verDetalle(productoId, diaSemana) {
    const item = consolidado.find(c =>
        c.id_producto_presentacion == productoId && c.dia_entrega == diaSemana
    );

    if (!item || !item.detalles || item.detalles.length === 0) {
        return;
    }

    let detalleHtml = '<div class="detalle-sucursales">';
    item.detalles.forEach(detalle => {
        detalleHtml += `
            <div class="sucursal-item">
                <span class="sucursal-nombre">${detalle.nombre_sucursal}</span>
                <span class="sucursal-cantidad">${detalle.cantidad} unidades</span>
            </div>
        `;
    });
    detalleHtml += '</div>';

    Swal.fire({
        title: `${item.nombre_producto} - ${diasSemana[diaSemana]}`,
        html: detalleHtml,
        icon: 'info',
        confirmButtonColor: '#51B8AC',
        width: '500px'
    });
}

// Exportar a Excel (simulado)
function exportarExcel() {
    Swal.fire({
        icon: 'info',
        title: 'Exportar a Excel',
        text: 'Funcionalidad de exportación en desarrollo',
        confirmButtonColor: '#51B8AC'
    });
}

// Mostrar mensaje de error
function mostrarError(mensaje) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: mensaje,
        confirmButtonColor: '#51B8AC'
    });
}
