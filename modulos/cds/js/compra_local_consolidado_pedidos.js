/* ===================================================
   Consolidado de Pedidos - JavaScript
   =================================================== */

let consolidado = [];
let productos = [];
let currentDates = [];
let productoActivo = null;
let semanaOffset = 0; // 0 = semana actual, -1 = semana pasada, etc.

// Días de la semana para el encabezado (Perspectiva de Pedido)
const diasConfig = [
    { num: 1, entrega: 2, nombre: 'Lun', info: 'Se Despacha Martes' },
    { num: 2, entrega: 3, nombre: 'Mar', info: 'Se Despacha Miércoles' },
    { num: 3, entrega: 4, nombre: 'Mié', info: 'Se Despacha Jueves' },
    { num: 4, entrega: 5, nombre: 'Jue', info: 'Se Despacha Viernes' },
    { num: 5, entrega: 6, nombre: 'Vie', info: 'Se Despacha Sábado' },
    { num: 6, entrega: 7, nombre: 'Sáb', info: 'Se Despacha Domingo' },
    { num: 7, entrega: 1, nombre: 'Dom', info: 'Se Despacha Lunes' }
];

// Obtener el lunes de la semana actual
function getLunesSemanaActual() {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const diaHoy = hoy.getDay();
    const diffLunes = diaHoy === 0 ? -6 : 1 - diaHoy;
    const lunes = new Date(hoy);
    lunes.setDate(hoy.getDate() + diffLunes);
    return lunes;
}

// Formatear fecha como DD/MMM
function formatFechaCorta(fecha) {
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
        'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `${fecha.getDate()}/${meses[fecha.getMonth()]}`;
}

// Cambiar semana
function cambiarSemana(delta) {
    semanaOffset += delta;
    // No permitir avanzar más allá de la semana actual
    if (semanaOffset > 0) semanaOffset = 0;
    productoActivo = null; // Resetear tab activo al cambiar semana
    cargarConsolidado();
}

// Inicializar
$(document).ready(function () {
    cargarConsolidado();
});

// Formatear cantidad para mostrar decimales de forma limpia
function formatCantidad(valor) {
    if (valor === undefined || valor === null || valor === 0) return '-';
    const num = parseFloat(valor);
    if (isNaN(num)) return '-';
    // Si es entero, mostrar sin decimales. Si tiene decimales, mostrar hasta 2.
    return Number.isInteger(num) ? num.toString() : parseFloat(num.toFixed(2)).toString();
}

// Determinar qué día es hoy (1-7)
function getDiaHoy() {
    const hoy = new Date();
    const dia = hoy.getDay();
    return dia === 0 ? 7 : dia;
}

// Cargar datos consolidados
function cargarConsolidado() {
    // Calcular el lunes de la semana activa según el offset
    const lunesSemanaActual = getLunesSemanaActual();
    const lunes = new Date(lunesSemanaActual);
    lunes.setDate(lunesSemanaActual.getDate() + semanaOffset * 7);

    // El domingo de esa semana
    const domingo = new Date(lunes);
    domingo.setDate(lunes.getDate() + 6);

    // Rango de FECHA_ENTREGA: Martes de esa semana → Lunes de la siguiente
    const fechaInicio = new Date(lunes);
    fechaInicio.setDate(lunes.getDate() + 1); // Martes

    const fechaFin = new Date(lunes);
    fechaFin.setDate(lunes.getDate() + 7); // Lunes siguiente

    // Texto del rango de la semana (Lun DD/MMM – Dom DD/MMM)
    const rangoTexto = `${formatFechaCorta(lunes)} – ${formatFechaCorta(domingo)}`;
    const esSemanaActual = semanaOffset === 0;

    // Renderizar el encabezado de navegación de semana + loader
    $('#consolidado-container').html(`
        <div class="semana-nav">
            <button class="btn-semana prev" onclick="cambiarSemana(-1)" title="Semana anterior">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div class="semana-info">
                <span class="semana-label">Semana</span>
                <span class="semana-rango">${rangoTexto}</span>
                ${esSemanaActual ? '<span class="badge-actual">Actual</span>' : ''}
            </div>
            <button class="btn-semana next ${esSemanaActual ? 'disabled' : ''}"
                    onclick="${esSemanaActual ? 'void(0)' : 'cambiarSemana(1)'}"
                    title="Semana siguiente"
                    ${esSemanaActual ? 'disabled' : ''}>
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
        <div class="loader-container">
            <div class="loader"></div>
        </div>
    `);

    $.ajax({
        url: 'ajax/compra_local_consolidado_pedidos_get_datos.php',
        method: 'POST',
        data: {
            fecha_inicio: fechaInicio.toISOString().split('T')[0],
            fecha_fin: fechaFin.toISOString().split('T')[0]
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                consolidado = response.consolidado;
                procesarDatos();
                renderizarTabs(rangoTexto, esSemanaActual);
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

            // Sumar si ya existe (por seguridad)
            const cantActual = productosMap[item.id_producto_presentacion].sucursales[detalle.codigo_sucursal].pedidos[item.dia_entrega] || 0;
            productosMap[item.id_producto_presentacion].sucursales[detalle.codigo_sucursal].pedidos[item.dia_entrega] = cantActual + detalle.cantidad;
        });
    });

    productos = Object.values(productosMap);

    // Seleccionar primer producto por defecto
    if (productos.length > 0 && !productoActivo) {
        productoActivo = productos[0].id;
    }
}

// Renderizar tabs de productos
function renderizarTabs(rangoTexto, esSemanaActual) {
    const navHtml = `
        <div class="semana-nav">
            <button class="btn-semana prev" onclick="cambiarSemana(-1)" title="Semana anterior">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div class="semana-info">
                <span class="semana-label">Semana</span>
                <span class="semana-rango">${rangoTexto}</span>
                ${esSemanaActual ? '<span class="badge-actual">Actual</span>' : ''}
            </div>
            <button class="btn-semana next ${esSemanaActual ? 'disabled' : ''}"
                    onclick="${esSemanaActual ? 'void(0)' : 'cambiarSemana(1)'}"
                    title="Semana siguiente"
                    ${esSemanaActual ? 'disabled' : ''}>
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    `;

    if (productos.length === 0) {
        $('#consolidado-container').html(`
            ${navHtml}
            <div class="no-data-message">
                <i class="bi bi-inbox"></i>
                <p>No hay productos con pedidos registrados</p>
            </div>
        `);
        return;
    }

    let tabsHtml = `
        <div class="productos-tabs">
            <ul class="nav nav-tabs" role="tablist">
    `;

    productos.forEach((producto, index) => {
        const isActive = producto.id === productoActivo;
        tabsHtml += `
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

    tabsHtml += `
            </ul>
            <div class="tab-content">
    `;

    productos.forEach((producto, index) => {
        const isActive = producto.id === productoActivo;
        tabsHtml += `
            <div class="tab-pane fade ${isActive ? 'show active' : ''}" 
                 id="content-${producto.id}" 
                 role="tabpanel">
                ${renderizarTablaProducto(producto)}
            </div>
        `;
    });

    tabsHtml += `
            </div>
        </div>
    `;

    $('#consolidado-container').html(navHtml + tabsHtml);
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
                                    <span class="day-name">
                                        ${esHoy ? '<i class="fas fa-star text-warning me-1"></i>' : ''}
                                        ${dia.nombre}${esHoy ? ' (HOY)' : ''}
                                    </span>
                                    <span class="delivery-label">${dia.info}</span>
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
                    ${formatCantidad(cantidad)}
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
                            <strong>${formatCantidad(total)}</strong>
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
