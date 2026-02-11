/* ===================================================
   Consolidado de Pedidos - JavaScript
   =================================================== */

let consolidado = [];
let productos = [];
let currentDates = [];
let productoActivo = null;

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

// Verificar si una fecha está a 2 días o menos de hoy
function estaProximoAHoy(fecha) {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    fecha.setHours(0, 0, 0, 0);

    const diffTime = fecha - hoy;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    return diffDays >= 0 && diffDays <= 2;
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
        data: {},
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                consolidado = response.consolidado;
                procesarDatos();
                renderizarTabs();
            } else {
                mostrarError('Error al cargar datos: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión al cargar datos');
        }
    });
}

// Procesar datos para agrupar por producto
function procesarDatos() {
    const productosMap = {};

    consolidado.forEach(item => {
        if (!productosMap[item.id_producto_presentacion]) {
            productosMap[item.id_producto_presentacion] = {
                id: item.id_producto_presentacion,
                nombre: item.nombre_producto,
                sucursales: {}
            };
        }

        // Agrupar por sucursal y día
        item.detalles.forEach(detalle => {
            if (!productosMap[item.id_producto_presentacion].sucursales[detalle.codigo_sucursal]) {
                productosMap[item.id_producto_presentacion].sucursales[detalle.codigo_sucursal] = {
                    codigo: detalle.codigo_sucursal,
                    nombre: detalle.nombre_sucursal,
                    pedidos: {}
                };
            }

            productosMap[item.id_producto_presentacion].sucursales[detalle.codigo_sucursal].pedidos[item.dia_entrega] = detalle.cantidad;
        });
    });

    productos = Object.values(productosMap);

    // Seleccionar primer producto por defecto
    if (productos.length > 0 && !productoActivo) {
        productoActivo = productos[0].id;
    }
}

// Renderizar tabs de productos
function renderizarTabs() {
    if (productos.length === 0) {
        $('#consolidado-container').html(`
            <div class="no-data-message">
                <i class="bi bi-inbox"></i>
                <p>No hay productos con pedidos registrados</p>
            </div>
        `);
        return;
    }

    let html = `
        <div class="productos-tabs">
            <ul class="nav nav-tabs" role="tablist">
    `;

    productos.forEach((producto, index) => {
        const isActive = producto.id === productoActivo;
        html += `
            <li class="nav-item" role="presentation">
                <button class="nav-link ${isActive ? 'active' : ''}" 
                        id="tab-${producto.id}" 
                        data-bs-toggle="tab" 
                        data-bs-target="#content-${producto.id}" 
                        type="button" 
                        role="tab"
                        onclick="cambiarProducto(${producto.id})">
                    ${producto.nombre}
                </button>
            </li>
        `;
    });

    html += `
            </ul>
            <div class="tab-content">
    `;

    productos.forEach((producto, index) => {
        const isActive = producto.id === productoActivo;
        html += `
            <div class="tab-pane fade ${isActive ? 'show active' : ''}" 
                 id="content-${producto.id}" 
                 role="tabpanel">
                ${renderizarTablaProducto(producto)}
            </div>
        `;
    });

    html += `
            </div>
        </div>
    `;

    $('#consolidado-container').html(html);
}

// Cambiar producto activo
function cambiarProducto(productoId) {
    productoActivo = productoId;
}

// Renderizar tabla de un producto
function renderizarTablaProducto(producto) {
    const sucursalesProducto = Object.values(producto.sucursales);

    if (sucursalesProducto.length === 0) {
        return `
            <div class="no-data-message">
                <i class="bi bi-inbox"></i>
                <p>No hay sucursales con pedidos para este producto</p>
            </div>
        `;
    }

    let html = `
        <div class="table-responsive mt-3">
            <table class="table consolidado-table">
                <thead>
                    <tr>
                        <th>Sucursal</th>
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
                    </tr>
                </thead>
                <tbody>
    `;

    const totalesPorDia = new Array(7).fill(0);

    sucursalesProducto.forEach(sucursal => {
        html += `
            <tr>
                <td class="sucursal-name">${sucursal.nombre}</td>
        `;

        currentDates.forEach((fecha, index) => {
            const diaSemana = getDiaSemana(fecha);
            const cantidad = sucursal.pedidos[diaSemana] || 0;
            const esHoy = index === 3;
            const esProximo = estaProximoAHoy(fecha);
            const sinPedido = cantidad === 0 && esProximo;

            if (cantidad > 0) {
                totalesPorDia[index] += cantidad;
            }

            html += `
                <td class="data-cell ${cantidad > 0 ? 'has-value' : 'no-value'} ${sinPedido ? 'alert-cell' : ''} ${esHoy ? 'today-column' : ''}">
                    ${cantidad > 0 ? cantidad : (sinPedido ? '⚠️' : '-')}
                </td>
            `;
        });

        html += `
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
                </tr>
    `;

    html += `
                </tbody>
            </table>
        </div>
    `;

    return html;
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
