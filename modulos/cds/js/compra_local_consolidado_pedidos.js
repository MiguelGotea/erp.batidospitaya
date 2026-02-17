/* ===================================================
   Consolidado de Pedidos - JavaScript
   =================================================== */

let consolidado = [];
let productos = [];
let currentDates = [];
let productoActivo = null;

// Días de la semana para el encabezado (Perspectiva de Pedido)
const diasConfig = [
    { num: 1, entrega: 2, nombre: 'Lun', info: 'Llega Mar' },
    { num: 2, entrega: 3, nombre: 'Mar', info: 'Llega Mié' },
    { num: 3, entrega: 4, nombre: 'Mié', info: 'Llega Jue' },
    { num: 4, entrega: 5, nombre: 'Jue', info: 'Llega Vie' },
    { num: 5, entrega: 6, nombre: 'Vie', info: 'Llega Sáb' },
    { num: 6, entrega: 7, nombre: 'Sáb', info: 'Llega Dom' },
    { num: 7, entrega: 1, nombre: 'Dom', info: 'Llega Lun' }
];

// Inicializar
$(document).ready(function () {
    cargarConsolidado();
});

// Determinar qué día es hoy (1-7)
function getDiaHoy() {
    const hoy = new Date();
    const dia = hoy.getDay();
    return dia === 0 ? 7 : dia;
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

    const diaHoy = getDiaHoy();
    let html = `
        <div class="table-responsive mt-2">
            <table class="table consolidado-table">
                <thead>
                    <tr>
                        <th>Sucursal</th>
                        ${diasConfig.map(dia => {
        const esHoy = dia.num === diaHoy;
        return `
                            <th class="${esHoy ? 'today-column' : ''}">
                                <div class="day-header">
                                    <span class="day-name">${dia.nombre}</span>
                                    <span class="day-info">${dia.info}</span>
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

        diasConfig.forEach((dia, index) => {
            const cantidad = sucursal.pedidos[dia.entrega] || 0;
            const esHoy = dia.num === diaHoy;

            if (cantidad > 0) {
                totalesPorDia[index] += cantidad;
            }

            html += `
                <td class="data-cell ${cantidad > 0 ? 'has-value' : 'no-value'} ${esHoy ? 'today-column' : ''}">
                    ${cantidad > 0 ? cantidad : '-'}
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
                        <td class="${diasConfig[index].num === diaHoy ? 'today-column' : ''}">
                            <strong>${total}</strong>
                        </td>
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
