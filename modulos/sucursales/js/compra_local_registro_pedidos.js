/* ===================================================
   Registro de Pedidos - JavaScript (Simplified)
   =================================================== */

let productos = [];
let pedidos = {};
let fechasEntrega = {};
let contadorInterval = null;

// Inicializar al cargar la página
$(document).ready(function () {
    calcularFechasEntrega();
    iniciarContador();
    cargarProductos();
});

// Calcular fechas de entrega (mañana y pasado mañana)
function calcularFechasEntrega() {
    const hoy = new Date();

    // Mañana (entrega para pedido de hoy)
    const manana = new Date(hoy);
    manana.setDate(manana.getDate() + 1);

    // Pasado mañana (entrega para pedido de mañana)
    const pasadoManana = new Date(hoy);
    pasadoManana.setDate(pasadoManana.getDate() + 2);

    fechasEntrega = {
        hoy: manana,
        manana: pasadoManana
    };
}

// Verificar si estamos antes de las 12:00 PM
function verificarHoraLimite() {
    const now = new Date();
    const currentHour = now.getHours();
    const currentMinute = now.getMinutes();

    return (currentHour < 12) || (currentHour === 12 && currentMinute === 0);
}

// Calcular tiempo restante hasta las 12:00 PM
function calcularTiempoRestante() {
    const now = new Date();
    const deadline = new Date(now);
    deadline.setHours(12, 0, 0, 0);

    if (now >= deadline) {
        return { expired: true, hours: 0, minutes: 0, seconds: 0 };
    }

    const diff = deadline - now;
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    return { expired: false, hours, minutes, seconds };
}

// Obtener estado de la columna HOY
function obtenerEstadoColumnaHoy() {
    const { expired, hours, minutes } = calcularTiempoRestante();

    if (expired) {
        return { estado: 'bloqueado', icono: '🔒', clase: 'deadline-expired' };
    }

    const totalMinutes = hours * 60 + minutes;

    if (totalMinutes < 30) {
        return { estado: 'critico', icono: '⚠️', clase: 'deadline-critical' };
    }

    if (totalMinutes < 120) {
        return { estado: 'advertencia', icono: '⏰', clase: 'deadline-warning' };
    }

    return { estado: 'normal', icono: '✅', clase: 'deadline-normal' };
}

// Iniciar contador regresivo
function iniciarContador() {
    // Actualizar cada segundo
    contadorInterval = setInterval(() => {
        // Si el plazo expiró, recargar la tabla para bloquear la columna
        const { expired } = calcularTiempoRestante();
        if (expired && contadorInterval) {
            clearInterval(contadorInterval);
            renderizarTabla();
        }
    }, 1000);
}

// Formatear fecha para SQL (YYYY-MM-DD)
function formatearFechaSQL(fecha) {
    const year = fecha.getFullYear();
    const month = String(fecha.getMonth() + 1).padStart(2, '0');
    const day = String(fecha.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Formatear fecha y hora para mostrar
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
    const fecha_inicio = formatearFechaSQL(fechasEntrega.hoy);
    const fecha_fin = formatearFechaSQL(fechasEntrega.manana);

    $.ajax({
        url: 'ajax/compra_local_registro_pedidos_get_pedidos.php',
        method: 'POST',
        data: {
            codigo_sucursal: codigoSucursal,
            fecha_inicio: fecha_inicio,
            fecha_fin: fecha_fin
        },
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

// Agrupar pedidos por producto y columna (hoy/mañana)
function agruparPedidos(pedidosArray) {
    const agrupado = {};

    const fechaHoySQL = formatearFechaSQL(fechasEntrega.hoy);
    const fechaMananaSQL = formatearFechaSQL(fechasEntrega.manana);

    pedidosArray.forEach(pedido => {
        let columna = '';
        if (pedido.fecha_entrega === fechaHoySQL) {
            columna = 'hoy';
        } else if (pedido.fecha_entrega === fechaMananaSQL) {
            columna = 'manana';
        }

        if (columna) {
            const key = `${pedido.id_producto_presentacion}_${columna}`;
            agrupado[key] = {
                id: pedido.id,
                cantidad: pedido.cantidad_pedido,
                fecha_hora_reportada: pedido.fecha_hora_reportada,
                fecha_entrega: pedido.fecha_entrega
            };
        }
    });

    return agrupado;
}

// Renderizar tabla simplificada (2 columnas)
function renderizarTabla() {
    const beforeDeadline = verificarHoraLimite();
    const estadoHoy = obtenerEstadoColumnaHoy();
    const { expired, hours, minutes, seconds } = calcularTiempoRestante();

    // Calcular día de la semana de mañana y pasado mañana
    const diaSemanaManana = fechasEntrega.hoy.getDay() === 0 ? 7 : fechasEntrega.hoy.getDay();
    const diaSemanaMananaManana = fechasEntrega.manana.getDay() === 0 ? 7 : fechasEntrega.manana.getDay();

    // Filtrar productos que tienen configuración para hoy o mañana
    const productosFiltrados = productos.filter(producto => {
        return producto.dias_entrega.includes(diaSemanaManana) ||
            producto.dias_entrega.includes(diaSemanaMananaManana);
    });

    if (productosFiltrados.length === 0) {
        $('#productos-container').html(`
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                No hay productos configurados para pedidos de hoy o mañana.
            </div>
        `);
        return;
    }

    // Generar contador para el encabezado
    let contadorEncabezado = '';
    if (expired) {
        contadorEncabezado = '<span class="countdown-expired">🔒 BLOQUEADO</span>';
    } else {
        const horasStr = String(hours).padStart(2, '0');
        const minutosStr = String(minutes).padStart(2, '0');
        const segundosStr = String(seconds).padStart(2, '0');

        let claseContador = 'countdown-normal';
        const totalMinutes = hours * 60 + minutes;
        if (totalMinutes < 30) {
            claseContador = 'countdown-critical';
        } else if (totalMinutes < 120) {
            claseContador = 'countdown-warning';
        }

        contadorEncabezado = `<span class="${claseContador}">⏰ ${horasStr}:${minutosStr}:${segundosStr}</span>`;
    }

    let html = `
        <div class="table-responsive">
            <table class="table table-bordered pedidos-table">
                <thead>
                    <tr>
                        <th style="width: 40%">Producto</th>
                        <th class="text-center column-hoy ${estadoHoy.clase}" style="width: 30%">
                            Pedido HOY<br>
                            <small>(Llega Mañana)</small><br>
                            <span class="countdown-header">${contadorEncabezado}</span>
                        </th>
                        <th class="text-center column-manana" style="width: 30%">
                            Pedido MAÑANA<br>
                            <small>(Llega Pasado Mañana)</small>
                        </th>
                    </tr>
                </thead>
                <tbody>
    `;

    productosFiltrados.forEach(producto => {
        const isInactive = producto.status === 'inactivo';
        const tieneConfigHoy = producto.dias_entrega.includes(diaSemanaManana);
        const tieneConfigManana = producto.dias_entrega.includes(diaSemanaMananaManana);

        // Pedido de HOY
        const keyHoy = `${producto.id_producto}_hoy`;
        const pedidoHoy = pedidos[keyHoy];
        const cantidadHoy = pedidoHoy ? pedidoHoy.cantidad : '';
        const fechaHoraHoy = pedidoHoy ? pedidoHoy.fecha_hora_reportada : null;
        const habilitadoHoy = tieneConfigHoy && beforeDeadline && !isInactive;
        const alertaHoy = !cantidadHoy && habilitadoHoy && estadoHoy.estado === 'advertencia';

        // Pedido de MAÑANA
        const keyManana = `${producto.id_producto}_manana`;
        const pedidoManana = pedidos[keyManana];
        const cantidadManana = pedidoManana ? pedidoManana.cantidad : '';
        const fechaHoraManana = pedidoManana ? pedidoManana.fecha_hora_reportada : null;
        const habilitadoManana = tieneConfigManana && !isInactive;

        html += `
            <tr ${isInactive ? 'class="inactive-product"' : ''}>
                <td>
                    <strong>${producto.nombre_producto}</strong>
                    ${isInactive ? '<br><span class="badge bg-secondary">Inactivo</span>' : ''}
                </td>
                <td class="day-cell ${habilitadoHoy ? 'enabled' : 'disabled'} ${cantidadHoy ? 'has-order' : ''} ${alertaHoy ? 'alert-cell' : ''} ${estadoHoy.clase}"
                    data-producto-id="${producto.id_producto}"
                    data-columna="hoy"
                    data-fecha-hora="${fechaHoraHoy || ''}"
                    ${habilitadoHoy && puedeEditar ? `onclick="editarCantidad(this)"` : ''}>
                    ${!tieneConfigHoy || !beforeDeadline || isInactive ?
                (tieneConfigHoy && !beforeDeadline ? '🔒' : '-') :
                (cantidadHoy ?
                    `<span class="cantidad-display">${cantidadHoy}</span>` :
                    (alertaHoy ? '<span class="urgent-icon">🚨</span>' : '<span class="text-muted">-</span>')
                )
            }      </td>
                <td class="day-cell ${habilitadoManana ? 'enabled' : 'disabled'} ${cantidadManana ? 'has-order' : ''}"
                    data-producto-id="${producto.id_producto}"
                    data-columna="manana"
                    data-fecha-hora="${fechaHoraManana || ''}"
                    ${habilitadoManana && puedeEditar ? `onclick="editarCantidad(this)"` : ''}>
                    ${!tieneConfigManana || isInactive ?
                '-' :
                (cantidadManana ?
                    `<span class="cantidad-display">${cantidadManana}</span>` :
                    '<span class="text-muted">-</span>'
                )
            }
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    $('#productos-container').html(html);

    // Agregar tooltips
    $('.day-cell[data-fecha-hora]').each(function () {
        const fechaHora = $(this).data('fecha-hora');
        if (fechaHora) {
            $(this).on('mouseenter', function (e) {
                mostrarTooltip(e, fechaHora);
            }).on('mouseleave', ocultarTooltip);
        }
    });
}

// Mostrar tooltip
function mostrarTooltip(e, fechaHora) {
    const fechaFormateada = formatearFechaHora(fechaHora);
    const tooltip = $(`
        <div class="custom-tooltip">
            <strong>Última modificación:</strong><br>
            ${fechaFormateada}
        </div>
    `);

    $('body').append(tooltip);

    setTimeout(() => {
        tooltip.css({
            top: e.pageY + 10,
            left: e.pageX + 10
        }).addClass('show');
    }, 10);
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
    const columna = $cell.data('columna');
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
            guardarCantidad(productoId, columna, nuevaCantidad, cantidadActual, $cell);
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
function guardarCantidad(productoId, columna, nuevaCantidad, cantidadAnterior, $cell) {
    // Si no cambió el valor, solo re-renderizar
    if (nuevaCantidad.toString() === cantidadAnterior.toString()) {
        renderizarTabla();
        return;
    }

    // Validar hora límite para columna HOY
    if (columna === 'hoy') {
        const beforeDeadline = verificarHoraLimite();
        if (!beforeDeadline) {
            mostrarError('Plazo vencido. No se pueden registrar pedidos para entrega de mañana después de las 12:00 PM');
            renderizarTabla();
            return;
        }
    }

    // Calcular fecha específica de entrega
    const fechaEntrega = formatearFechaSQL(fechasEntrega[columna]);

    // Mostrar indicador de guardado
    mostrarGuardando();

    $.ajax({
        url: 'ajax/compra_local_registro_pedidos_guardar.php',
        method: 'POST',
        data: {
            codigo_sucursal: codigoSucursal,
            id_producto_presentacion: productoId,
            fecha_entrega: fechaEntrega,
            cantidad_pedido: nuevaCantidad
        },
        dataType: 'json',
        success: function (response) {
            ocultarGuardando();
            if (response.success) {
                cargarPedidos();
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
    Swal.fire({
        title: 'Guardando...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

// Ocultar indicador de guardado
function ocultarGuardando() {
    Swal.close();
}

// Mostrar mensaje de éxito
function mostrarExito(mensaje) {
    Swal.fire({
        icon: 'success',
        title: 'Éxito',
        text: mensaje,
        timer: 2000,
        showConfirmButton: false
    });
}

// Mostrar mensaje de error
function mostrarError(mensaje) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: mensaje
    });
}
