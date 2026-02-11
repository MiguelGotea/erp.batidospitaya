/* ===================================================
   Registro de Pedidos - JavaScript
   =================================================== */

let productos = [];
let pedidos = {};
let currentWeekDates = [];

// Días de la semana
const diasSemana = [
    { num: 1, nombre: 'Lun', nombreCompleto: 'Lunes' },
    { num: 2, nombre: 'Mar', nombreCompleto: 'Martes' },
    { num: 3, nombre: 'Mié', nombreCompleto: 'Miércoles' },
    { num: 4, nombre: 'Jue', nombreCompleto: 'Jueves' },
    { num: 5, nombre: 'Vie', nombreCompleto: 'Viernes' },
    { num: 6, nombre: 'Sáb', nombreCompleto: 'Sábado' },
    { num: 7, nombre: 'Dom', nombreCompleto: 'Domingo' }
];

// Inicializar
$(document).ready(function () {
    calcularDiasAlrededor();
    cargarProductos();
});

// Calcular días: 3 antes de hoy, hoy, 3 después de hoy
function calcularDiasAlrededor() {
    const hoy = new Date();

    currentWeekDates = [];

    // 3 días antes de hoy
    for (let i = 3; i > 0; i--) {
        const fecha = new Date(hoy);
        fecha.setDate(hoy.getDate() - i);
        currentWeekDates.push(fecha);
    }

    // Hoy
    currentWeekDates.push(new Date(hoy));

    // 3 días después de hoy
    for (let i = 1; i <= 3; i++) {
        const fecha = new Date(hoy);
        fecha.setDate(hoy.getDate() + i);
        currentWeekDates.push(fecha);
    }
}

// Formatear fecha para mostrar
function formatearFecha(fecha) {
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const dia = fecha.getDate();
    const mes = meses[fecha.getMonth()];
    return `${dia}-${mes}`;
}

// Formatear fecha y hora para tooltip
function formatearFechaHora(fechaHoraStr) {
    if (!fechaHoraStr) return null;

    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const fecha = new Date(fechaHoraStr);

    const dia = fecha.getDate().toString().padStart(2, '0');
    const mes = meses[fecha.getMonth()];
    const anio = fecha.getFullYear().toString().substr(-2);
    const horas = fecha.getHours().toString().padStart(2, '0');
    const minutos = fecha.getMinutes().toString().padStart(2, '0');

    return `${dia}-${mes}-${anio} ${horas}:${minutos}`;
}

// Cargar productos configurados para esta sucursal
function cargarProductos() {
    $.ajax({
        url: 'ajax/compra_local_registro_pedidos_get_productos.php',
        method: 'POST',
        data: { codigo_sucursal: codigoSucursal },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                productos = response.productos;
                cargarPedidos();
            } else {
                mostrarError('Error al cargar productos: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión al cargar productos');
        }
    });
}

// Cargar pedidos existentes
function cargarPedidos() {
    $.ajax({
        url: 'ajax/compra_local_registro_pedidos_get_pedidos.php',
        method: 'POST',
        data: { codigo_sucursal: codigoSucursal },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                pedidos = agruparPedidos(response.pedidos);
                renderizarTabla();
            } else {
                mostrarError('Error al cargar pedidos: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión al cargar pedidos');
        }
    });
}

// Agrupar pedidos por producto y día
function agruparPedidos(pedidosArray) {
    const agrupado = {};

    pedidosArray.forEach(pedido => {
        const key = `${pedido.id_producto_presentacion}_${pedido.dia_entrega}`;
        agrupado[key] = {
            id: pedido.id,
            cantidad: pedido.cantidad_pedido,
            fecha_hora_reportada: pedido.fecha_hora_reportada
        };
    });

    return agrupado;
}

// Obtener día de la semana (1-7) de una fecha
function getDiaSemana(fecha) {
    const dia = fecha.getDay();
    return dia === 0 ? 7 : dia; // Convertir Domingo (0) a 7
}

// Renderizar tabla de productos
function renderizarTabla() {
    let tableHtml = `
        <div class="table-responsive">
            <table class="table pedidos-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        ${currentWeekDates.map((fecha, index) => {
        const diaSemana = getDiaSemana(fecha);
        const diaInfo = diasSemana.find(d => d.num === diaSemana);
        const esHoy = index === 3; // Hoy está en el índice 3
        return `
                            <th ${esHoy ? 'class="today-column"' : ''}>
                                <div class="day-header">
                                    <span class="day-name">${diaInfo.nombre}${esHoy ? ' (HOY)' : ''}</span>
                                    <span class="day-date">${formatearFecha(fecha)}</span>
                                </div>
                            </th>
                        `;
    }).join('')}
                    </tr>
                </thead>
                <tbody>
    `;

    if (productos.length === 0) {
        tableHtml += `
            <tr>
                <td colspan="${currentWeekDates.length + 1}">
                    <div class="no-products-message">
                        <i class="bi bi-inbox"></i>
                        <p>No hay productos configurados para esta sucursal</p>
                    </div>
                </td>
            </tr>
        `;
    } else {
        productos.forEach(producto => {
            const isInactive = producto.status === 'inactivo';
            tableHtml += `
                <tr class="${isInactive ? 'inactive-row' : ''}">
                    <td>
                        ${producto.nombre_producto}
                        ${isInactive ? '<span class="badge-inactive">Inactivo</span>' : ''}
                    </td>
                    ${currentWeekDates.map((fecha, index) => {
                const diaSemana = getDiaSemana(fecha);
                const tieneEntrega = producto.dias_entrega.includes(diaSemana);
                const key = `${producto.id_producto}_${diaSemana}`;
                const pedido = pedidos[key];
                const cantidad = pedido ? pedido.cantidad : '';
                const fechaHora = pedido ? pedido.fecha_hora_reportada : null;
                const esHoy = index === 3;

                return `
                            <td class="day-cell ${tieneEntrega && !isInactive ? 'enabled' : 'disabled'} ${cantidad ? 'has-order' : ''} ${esHoy ? 'today-column' : ''}"
                                data-producto-id="${producto.id_producto}"
                                data-dia="${diaSemana}"
                                data-fecha-hora="${fechaHora || ''}"
                                ${tieneEntrega && !isInactive && puedeEditar ? `onclick="editarCantidad(this)"` : ''}>
                                ${tieneEntrega && !isInactive ?
                        (cantidad ?
                            `<span class="cantidad-display">${cantidad}</span>` :
                            '<span class="text-muted">-</span>')
                        : '-'}
                            </td>
                        `;
            }).join('')}
                </tr>
            `;
        });
    }

    tableHtml += `
                </tbody>
            </table>
        </div>
    `;

    $('#productos-container').html(tableHtml);

    // Inicializar tooltips para celdas con pedidos
    inicializarTooltips();
}

// Inicializar tooltips hover
function inicializarTooltips() {
    $('.day-cell.has-order').hover(
        function () {
            const fechaHora = $(this).data('fecha-hora');
            if (fechaHora) {
                const fechaFormateada = formatearFechaHora(fechaHora);
                if (fechaFormateada) {
                    mostrarTooltip(this, `Última modificación: ${fechaFormateada}`);
                }
            }
        },
        function () {
            ocultarTooltip();
        }
    );
}

// Mostrar tooltip personalizado
function mostrarTooltip(element, texto) {
    // Remover tooltip existente
    $('.custom-tooltip').remove();

    // Crear nuevo tooltip
    const tooltip = $('<div class="custom-tooltip"></div>').text(texto);
    $('body').append(tooltip);

    // Posicionar tooltip
    const rect = element.getBoundingClientRect();
    const tooltipWidth = tooltip.outerWidth();
    const left = rect.left + (rect.width / 2) - (tooltipWidth / 2);
    const top = rect.top - tooltip.outerHeight() - 10;

    tooltip.css({
        left: left + 'px',
        top: top + 'px'
    });

    // Mostrar con animación
    setTimeout(() => tooltip.addClass('show'), 10);
}

// Ocultar tooltip
function ocultarTooltip() {
    $('.custom-tooltip').removeClass('show');
    setTimeout(() => $('.custom-tooltip').remove(), 200);
}

// Editar cantidad
function editarCantidad(cell) {
    if (!puedeEditar) return;

    const $cell = $(cell);
    const productoId = $cell.data('producto-id');
    const dia = $cell.data('dia');
    const cantidadActual = $cell.find('.cantidad-display').text() || '';

    // Crear input
    const input = $(`<input type="number" class="cantidad-input" value="${cantidadActual}" min="0" step="1">`);

    // Reemplazar contenido
    $cell.html(input);
    input.focus().select();

    // Guardar al perder foco o presionar Enter
    input.on('blur keypress', function (e) {
        if (e.type === 'blur' || e.which === 13) {
            const nuevaCantidad = parseInt($(this).val()) || 0;
            guardarCantidad(productoId, dia, nuevaCantidad, cantidadActual, $cell);
        }
    });

    // Cancelar con Escape
    input.on('keydown', function (e) {
        if (e.which === 27) {
            renderizarTabla();
        }
    });
}

// Guardar cantidad
function guardarCantidad(productoId, dia, nuevaCantidad, cantidadAnterior, $cell) {
    // Si no cambió el valor, solo re-renderizar
    if (nuevaCantidad.toString() === cantidadAnterior.toString()) {
        renderizarTabla();
        return;
    }

    // Mostrar indicador de guardado
    mostrarGuardando();

    $.ajax({
        url: 'ajax/compra_local_registro_pedidos_guardar.php',
        method: 'POST',
        data: {
            codigo_sucursal: codigoSucursal,
            id_producto_presentacion: productoId,
            dia_entrega: dia,
            cantidad_pedido: nuevaCantidad
        },
        dataType: 'json',
        success: function (response) {
            ocultarGuardando();
            if (response.success) {
                cargarPedidos(); // Recargar para obtener fecha_hora_reportada actualizada
                mostrarExito('Cantidad guardada correctamente');
            } else {
                mostrarError('Error al guardar: ' + response.message);
                renderizarTabla();
            }
        },
        error: function () {
            ocultarGuardando();
            mostrarError('Error de conexión al guardar');
            renderizarTabla();
        }
    });
}

// Mostrar indicador de guardado
function mostrarGuardando() {
    if ($('.saving-indicator').length === 0) {
        $('body').append('<div class="saving-indicator"><i class="bi bi-arrow-repeat"></i> Guardando...</div>');
    }
}

// Ocultar indicador de guardado
function ocultarGuardando() {
    $('.saving-indicator').fadeOut(300, function () {
        $(this).remove();
    });
}

// Mostrar mensaje de éxito
function mostrarExito(mensaje) {
    Swal.fire({
        icon: 'success',
        title: 'Éxito',
        text: mensaje,
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
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
