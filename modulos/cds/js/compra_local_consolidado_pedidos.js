/* ===================================================
   Consolidado de Pedidos - JavaScript
   =================================================== */

let consolidado = [];
let productos = [];
let currentDates = [];
let productoActivo = null;
let semanaOffset = 0; // 0 = semana actual, -1 = semana pasada, etc.

let currentView = 'producto'; // 'producto' o 'semana'
let diaActivo = null;

// Días de la semana para el encabezado (Perspectiva de Pedido)
const diasConfig = [
    { num: 0, entrega: 1, nombre: 'Dom (Pasado)', info: 'Se Despacha Lunes', isPrev: true },
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
    diaActivo = null;
    cargarConsolidado();
}

// Cambiar vista
function cambiarVista(vista) {
    if (currentView === vista) return;
    currentView = vista;
    renderizarTabs();
}

// Inicializar
$(document).ready(function () {
    cargarConsolidado();
});

// Formatear cantidad para mostrar decimales de forma limpia
function formatCantidad(valor) {
    if (valor === undefined || valor === null) return '-';
    const num = parseFloat(valor);
    if (isNaN(num)) return '-';
    // Si es entero, mostrar sin decimales. Si tiene decimales, mostrar hasta 2.
    return Number.isInteger(num) ? num.toString() : parseFloat(num.toFixed(2)).toString();
}

// Determinar qué día es hoy (0-7 según diasConfig)
function getDiaHoy() {
    const hoy = new Date();
    const dia = hoy.getDay();
    // En diasConfig: 0=Dom(Pasado), 1=Lun, ..., 6=Sab, 7=Dom
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
            <div class="nav-left">
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

            <div class="nav-right">
                <div class="view-selector">
                    <button class="btn-view ${currentView === 'producto' ? 'active' : ''}" onclick="cambiarVista('producto')">
                        <i class="fas fa-box"></i> Por Producto
                    </button>
                    <button class="btn-view ${currentView === 'semana' ? 'active' : ''}" onclick="cambiarVista('semana')">
                        <i class="fas fa-calendar-week"></i> Por Semana
                    </button>
                </div>
            </div>
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

            // Registrar el pedido (puede ser 0 si es pedido faltante)
            productosMap[item.id_producto_presentacion].sucursales[detalle.codigo_sucursal].pedidos[item.dia_entrega] = detalle.cantidad;
        });
    });

    productos = Object.values(productosMap);

    // Seleccionar primer producto por defecto si no hay uno activo
    if (productos.length > 0 && !productoActivo) {
        productoActivo = productos[0].id;
    }

    // Seleccionar hoy como día activo por defecto en vista semana
    if (!diaActivo) {
        diaActivo = getDiaHoy();
    }
}

// Renderizar tabs (de productos o de días)
function renderizarTabs() {
    const lunesSemanaActual = getLunesSemanaActual();
    const lunes = new Date(lunesSemanaActual);
    lunes.setDate(lunesSemanaActual.getDate() + semanaOffset * 7);
    const domingo = new Date(lunes);
    domingo.setDate(lunes.getDate() + 6);
    const rangoTexto = `${formatFechaCorta(lunes)} – ${formatFechaCorta(domingo)}`;
    const esSemanaActual = semanaOffset === 0;

    const navHtml = `
        <div class="semana-nav">
            <div class="nav-left">
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

            <div class="nav-right">
                <div class="view-selector">
                    <button class="btn-view ${currentView === 'producto' ? 'active' : ''}" onclick="cambiarVista('producto')">
                        <i class="fas fa-box"></i> Por Producto
                    </button>
                    <button class="btn-view ${currentView === 'semana' ? 'active' : ''}" onclick="cambiarVista('semana')">
                        <i class="fas fa-calendar-week"></i> Por Semana
                    </button>
                </div>
            </div>
        </div>
    `;

    if (consolidado.length === 0) {
        $('#consolidado-container').html(`
            ${navHtml}
            <div class="no-data-message">
                <i class="bi bi-inbox"></i>
                <p>No hay datos registrados para esta semana</p>
            </div>
        `);
        return;
    }

    let tabsHtml = `
        <div class="productos-tabs">
            <ul class="nav nav-tabs" role="tablist">
    `;

    if (currentView === 'producto') {
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
    } else {
        diasConfig.forEach((dia, index) => {
            const isActive = dia.num === diaActivo;
            const esHoy = esSemanaActual && dia.num === getDiaHoy();
            tabsHtml += `
                <li class="nav-item" role="presentation">
                    <button class="nav-link ${isActive ? 'active' : ''} d-flex flex-column align-items-center" 
                            id="tab-dia-${dia.num}" 
                            data-bs-toggle="tab" 
                            data-bs-target="#content-dia-${dia.num}" 
                            type="button" 
                            role="tab"
                            onclick="cambiarDia(${dia.num})"
                            style="min-width: 120px;">
                        <span class="day-tab-name">
                            ${esHoy ? '<i class="fas fa-star text-warning me-1"></i>' : ''}
                            ${dia.nombre}${esHoy ? ' (HOY)' : ''}
                        </span>
                        <span class="day-tab-info" style="font-size: 10px; font-weight: normal; opacity: 0.8; margin-top: 2px;">
                            ${dia.info}
                        </span>
                    </button>
                </li>
            `;
        });
    }

    tabsHtml += `
            </ul>
            <div class="tab-content">
    `;

    if (currentView === 'producto') {
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
    } else {
        diasConfig.forEach((dia, index) => {
            const isActive = dia.num === diaActivo;
            tabsHtml += `
                <div class="tab-pane fade ${isActive ? 'show active' : ''}" 
                     id="content-dia-${dia.num}" 
                     role="tabpanel">
                    ${renderizarTablaSemana(dia.num)}
                </div>
            `;
        });
    }

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

// Cambiar día activo
function cambiarDia(diaNum) {
    diaActivo = diaNum;
}

// Renderizar tabla de un producto (Vista Por Producto)
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

    // Determinar índice de hoy
    const todayIdx = getDiaHoy();
    
    let html = `
        <div class="table-responsive mt-2">
            <table class="table consolidado-table">
                <thead>
                    <tr>
                        <th>Sucursal</th>
                        ${diasConfig.map((dia, index) => {
                            const esHoy = semanaOffset === 0 && dia.num === todayIdx;
                            return `
                                <th class="${esHoy ? 'today-column' : ''} ${dia.isPrev ? 'prev-week-column' : ''}">
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

    const totalesPorDia = new Array(8).fill(0);
    
    sucursalesProducto.sort((a, b) => a.nombre.localeCompare(b.nombre));

    sucursalesProducto.forEach(sucursal => {
        html += `
            <tr>
                <td class="sucursal-name">${sucursal.nombre}</td>
        `;

        diasConfig.forEach((dia, index) => {
            const cantidad = sucursal.pedidos[dia.num];
            const esHoy = semanaOffset === 0 && dia.num === todayIdx;
            const esConfigurado = cantidad !== undefined;
            
            const esPasadoOHoy = semanaOffset < 0 || (semanaOffset === 0 && dia.num <= todayIdx);
            const esFaltante = esConfigurado && (cantidad === null || cantidad === undefined) && esPasadoOHoy;

            if (cantidad > 0) {
                totalesPorDia[index] += cantidad;
            }

            let cellContent = "";
            let cellClass = "";

            if (esFaltante) {
                cellClass = "missing-order";
                cellContent = `<i class="fas fa-exclamation-triangle missing-order-icon"></i> -`;
            } else if (cantidad !== null && cantidad !== undefined) {
                cellClass = "has-value data-cell";
                cellContent = formatCantidad(cantidad);
            } else {
                cellClass = "no-value data-cell";
                cellContent = "-";
            }

            html += `
                <td class="${cellClass} ${esHoy ? 'today-column' : ''}">
                    ${cellContent}
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
                    ${totalesPorDia.map((total, index) => {
                        const esHoy = semanaOffset === 0 && diasConfig[index].num === todayIdx;
                        return `
                            <td class="${esHoy ? 'today-column' : ''}">
                                <strong>${formatCantidad(total)}</strong>
                            </td>
                        `;
                    }).join('')}
                </tr>
    `;

    html += `
                </tbody>
            </table>
        </div>
    `;

    return html;
}

// Renderizar tabla de un día (Vista Por Semana)
function renderizarTablaSemana(diaNum) {
    // Extraer todas las sucursales y productos únicos que tienen pedidos este día
    const sucursalesMap = {};
    const productosMap = {};

    consolidado.filter(item => item.dia_entrega === diaNum).forEach(item => {
        if (!productosMap[item.id_producto_presentacion]) {
            productosMap[item.id_producto_presentacion] = {
                id: item.id_producto_presentacion,
                nombre: item.nombre_producto,
                sku: item.SKU
            };
        }

        item.detalles.forEach(detalle => {
            if (!sucursalesMap[detalle.codigo_sucursal]) {
                sucursalesMap[detalle.codigo_sucursal] = {
                    codigo: detalle.codigo_sucursal,
                    nombre: detalle.nombre_sucursal,
                    pedidos: {}
                };
            }
            // Registrar el pedido de este producto para esta sucursal
            sucursalesMap[detalle.codigo_sucursal].pedidos[item.id_producto_presentacion] = detalle.cantidad;
        });
    });

    const sucursales = Object.values(sucursalesMap).sort((a, b) => a.nombre.localeCompare(b.nombre));
    const productos = Object.values(productosMap).sort((a, b) => a.nombre.localeCompare(b.nombre));

    if (sucursales.length === 0) {
        return `
            <div class="no-data-message">
                <i class="bi bi-inbox"></i>
                <p>No hay pedidos registrados para este día</p>
            </div>
        `;
    }

    let html = `
        <div class="table-responsive mt-2">
            <table class="table consolidado-table">
                <thead>
                    <tr>
                        <th style="min-width: 250px;">Sucursal</th>
                        ${productos.map(prod => `
                            <th>
                                <div class="day-header">
                                    <span class="day-name">${prod.nombre}</span>
                                    <span class="delivery-label">${prod.sku || ''}</span>
                                </div>
                            </th>
                        `).join('')}
                    </tr>
                </thead>
                <tbody>
    `;

    const totalesPorProducto = new Array(productos.length).fill(0);

    sucursales.forEach(sucursal => {
        html += `
            <tr>
                <td class="sucursal-name">${sucursal.nombre}</td>
        `;

        productos.forEach((prod, pIdx) => {
            const cantidad = sucursal.pedidos[prod.id];
            const esConfigurado = cantidad !== undefined;
            const esPasadoOHoy = semanaOffset < 0 || (semanaOffset === 0 && diaNum <= getDiaHoy());
            const esFaltante = esConfigurado && (cantidad === null || cantidad === undefined) && esPasadoOHoy;

            if (cantidad > 0) {
                totalesPorProducto[pIdx] += cantidad;
            }

            let cellContent = "";
            let cellClass = "";

            if (esFaltante) {
                cellClass = "missing-order";
                cellContent = `<i class="fas fa-exclamation-triangle missing-order-icon"></i> -`;
            } else if (cantidad !== null && cantidad !== undefined) {
                cellClass = "has-value data-cell";
                cellContent = formatCantidad(cantidad);
            } else {
                cellClass = "no-value data-cell";
                cellContent = "-";
            }

            html += `
                <td class="${cellClass}">
                    ${cellContent}
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
                    ${totalesPorProducto.map(total => `
                        <td>
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
