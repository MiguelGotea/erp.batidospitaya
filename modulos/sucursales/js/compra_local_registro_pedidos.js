/* ===================================================
   Registro de Pedidos - JavaScript (Simplified)
   =================================================== */

let productos = [];
let pedidos = {};
let fechasEntrega = {};
let contadorInterval = null;

// Inicializar al cargar la página
$(document).ready(function () {
    fechasEntrega = calcularSemanaActual();
    iniciarContador();
    cargarProductos();
});

// Calcular semana actual (Lunes a Domingo) con fechas de entrega
function calcularSemanaActual() {
    const hoy = new Date();
    const diaSemana = hoy.getDay(); // 0=Sunday, 1=Monday, etc.

    // Calculate Monday of current week
    const lunes = new Date(hoy);
    const diff = diaSemana === 0 ? -6 : 1 - diaSemana;
    lunes.setDate(hoy.getDate() + diff);
    lunes.setHours(0, 0, 0, 0);

    // Generate array of 7 days (Mon-Sun)
    const semana = [];
    for (let i = 0; i < 7; i++) {
        const dia = new Date(lunes);
        dia.setDate(lunes.getDate() + i);

        // Calculate delivery date (next day)
        const entrega = new Date(dia);
        entrega.setDate(dia.getDate() + 1);

        semana.push({
            pedido: dia,
            entrega: entrega,
            diaSemana: dia.getDay() === 0 ? 7 : dia.getDay(),
            diaSemanaEntrega: entrega.getDay() === 0 ? 7 : entrega.getDay()
        });
    }

    return semana;
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

// Actualizar solo el contador (sin recargar toda la tabla)
function actualizarContador() {
    const { expired, hours, minutes, seconds } = calcularTiempoRestante();

    let contadorHTML = '';
    if (expired) {
        contadorHTML = `
            <div class="countdown-banner countdown-expired-banner">
                🔒 <strong>BLOQUEADO</strong> - Ya no se pueden registrar pedidos para entrega de mañana
            </div>
        `;
    } else {
        const horasStr = String(hours).padStart(2, '0');
        const minutosStr = String(minutes).padStart(2, '0');
        const segundosStr = String(seconds).padStart(2, '0');

        let claseBanner = 'countdown-banner-normal';
        const totalMinutes = hours * 60 + minutes;
        if (totalMinutes < 30) {
            claseBanner = 'countdown-banner-critical';
        } else if (totalMinutes < 120) {
            claseBanner = 'countdown-banner-warning';
        }

        contadorHTML = `
            <div class="countdown-banner ${claseBanner}">
                ⏰ <strong>Tiempo restante para pedidos de HOY:</strong> 
                <span class="countdown-time">${horasStr}:${minutosStr}:${segundosStr}</span>
                <small>(Plazo límite: 12:00 PM)</small>
            </div>
        `;
    }

    // Actualizar solo el banner si existe
    const existingBanner = $('.countdown-banner');
    if (existingBanner.length > 0) {
        existingBanner.replaceWith(contadorHTML);
    }
}

// Iniciar contador regresivo
function iniciarContador() {
    // Actualizar cada segundo
    contadorInterval = setInterval(() => {
        actualizarContador();

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
    // Get first and last day of week
    const primerDia = fechasEntrega[0].entrega; // Tuesday (Monday's delivery)
    const ultimoDia = fechasEntrega[6].entrega; // Monday next week (Sunday's delivery)

    const fecha_inicio = formatearFechaSQL(primerDia);
    const fecha_fin = formatearFechaSQL(ultimoDia);

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

// Agrupar pedidos por producto y fecha de entrega
function agruparPedidos(pedidosArray) {
    const agrupado = {};

    pedidosArray.forEach(pedido => {
        const key = `${pedido.id_producto_presentacion}_${pedido.fecha_entrega}`;
        agrupado[key] = {
            id: pedido.id,
            cantidad: pedido.cantidad_pedido,
            fecha_hora_reportada: pedido.fecha_hora_reportada,
            fecha_entrega: pedido.fecha_entrega
        };
    });

    return agrupado;
}

// Renderizar tabla semanal (7 columnas: Lunes-Domingo)
function renderizarTabla() {
    const nombreDias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    // Filter products that have at least one delivery day configured this week
    const diasSemanaEntrega = fechasEntrega.map(d => d.diaSemanaEntrega);
    const productosFiltrados = productos.filter(producto => {
        return producto.dias_entrega.some(dia => diasSemanaEntrega.includes(dia));
    });

    if (productosFiltrados.length === 0) {
        $('#productos-container').html(`
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                No hay productos configurados para entregas esta semana.
            </div>
        `);
        return;
    }

    // Generate countdown banner for today
    const hoyIndex = fechasEntrega.findIndex(d => d.pedido.toDateString() === hoy.toDateString());
    let contadorBannerHTML = '';

    if (hoyIndex !== -1) {
        const beforeDeadline = verificarHoraLimite();

        if (!beforeDeadline) {
            contadorBannerHTML = `
                <div class="countdown-banner countdown-expired-banner">
                    🔒 <strong>BLOQUEADO</strong> - Ya no se pueden registrar pedidos para entrega de mañana
                </div>
            `;
        } else {
            const { hours, minutes, seconds } = calcularTiempoRestante();
            const horasStr = String(hours).padStart(2, '0');
            const minutosStr = String(minutes).padStart(2, '0');
            const segundosStr = String(seconds).padStart(2, '0');

            const totalMinutes = hours * 60 + minutes;
            let claseBanner = 'countdown-banner-normal';
            if (totalMinutes < 30) {
                claseBanner = 'countdown-banner-critical';
            } else if (totalMinutes < 120) {
                claseBanner = 'countdown-banner-warning';
            }

            contadorBannerHTML = `
                <div class="countdown-banner ${claseBanner}">
                    ⏰ <strong>Tiempo restante para pedidos de HOY:</strong> 
                    <span class="countdown-time">${horasStr}:${minutosStr}:${segundosStr}</span>
                    <small>(Plazo límite: 12:00 PM)</small>
                </div>
            `;
        }
    }

    // Build table header
    let html = contadorBannerHTML + `
        <div class="table-responsive">
            <table class="table table-bordered pedidos-table">
                <thead>
                    <tr>
                        <th style="width: 20%">Producto</th>
    `;

    // Generate column headers for each day
    fechasEntrega.forEach((diaInfo, index) => {
        const nombreDia = nombreDias[index];
        const nombreEntrega = nombreDias[(index + 1) % 7];
        const esHoy = diaInfo.pedido.toDateString() === hoy.toDateString();

        let claseColumna = 'day-column';
        if (esHoy) {
            claseColumna += ' current-day-column';
        }

        html += `
            <th class="text-center ${claseColumna}">
                <div class="day-name">
                    ${esHoy ? '<i class="fas fa-star text-warning me-1"></i>' : ''}
                    ${nombreDia}${esHoy ? ' (HOY)' : ''}
                </div>
                <div class="delivery-label">Pedido Llega ${nombreEntrega}</div>
            </th>
        `;
    });


    html += `
                    </tr>
                </thead>
                <tbody>
    `;

    // Generate rows for each product
    productosFiltrados.forEach(producto => {
        const isInactive = producto.status === 'inactivo';

        html += `
            <tr ${isInactive ? 'class="inactive-product"' : ''}>
                <td>
                    <strong>${producto.nombre_producto}</strong>
                    ${isInactive ? '<br><span class="badge bg-secondary">Inactivo</span>' : ''}
                </td>
        `;
        // Generate cell for each day
        fechasEntrega.forEach((diaInfo, index) => {
            const esHoy = diaInfo.pedido.toDateString() === hoy.toDateString();
            const esPasado = diaInfo.pedido < hoy; // Check if date is in the past
            const beforeDeadline = esHoy ? verificarHoraLimite() : true;
            const tieneConfig = producto.dias_entrega.includes(diaInfo.diaSemanaEntrega);

            // Get existing order for this day
            const fechaEntregaSQL = formatearFechaSQL(diaInfo.entrega);
            const key = `${producto.id_producto}_${fechaEntregaSQL}`;
            const pedido = pedidos[key];
            const cantidad = pedido ? pedido.cantidad : '';
            const fechaHora = pedido ? pedido.fecha_hora_reportada : null;

            // Past dates are always disabled for editing but show as completed
            const habilitado = !esPasado && tieneConfig && beforeDeadline && !isInactive;

            // Calculate Suggested Reorder Point for editable cells
            let sugeridoHTML = '';
            if (habilitado && !esPasado && tieneConfig) {
                // Constants
                const operatingHours = 14;
                const fixedCountHour = 9;
                const closingHour = 21;
                const dailyWaste = 0;

                const leadTime = parseInt(producto.lead_time_days) || 0;
                const shelfLife = parseInt(producto.shelf_life_days) || 7;

                // 1. Demanda restante para HOY (9 AM a 9 PM)
                const diaHoy = diaInfo.diaSemana;
                const configHoy = producto.config_diaria[diaHoy] || { base_consumption: 0 };
                const DHoyTotal = configHoy.base_consumption + dailyWaste;
                const Hr = closingHour - fixedCountHour;
                const F = Hr / operatingHours;
                const DhoyRemanente = F * DHoyTotal;

                // 2. Determinar periodo de cobertura (Días hasta la siguiente entrega)
                const diasEntrega = producto.dias_entrega;
                let diasHastaSiguiente = 0;
                if (diasEntrega.length > 0) {
                    const diaActualEntrega = diaInfo.diaSemanaEntrega;
                    let siguienteEntrega = diasEntrega.find(d => d > diaActualEntrega);
                    if (!siguienteEntrega) siguienteEntrega = diasEntrega[0];

                    if (siguienteEntrega > diaActualEntrega) {
                        diasHastaSiguiente = siguienteEntrega - diaActualEntrega;
                    } else {
                        diasHastaSiguiente = (7 - diaActualEntrega) + siguienteEntrega;
                    }
                }

                // 3. Sumar demanda de los días de cobertura
                // Empezamos desde el día que llega el pedido (diaInfo.entrega)
                let Dfutura = 0;
                const diasASumar = Math.min(diasHastaSiguiente + leadTime, shelfLife);

                for (let i = 0; i < diasASumar; i++) {
                    // Calcular el día de la semana correspondiente a (Entrega + i días)
                    let diaSemanaSumar = (diaInfo.diaSemanaEntrega + i);
                    if (diaSemanaSumar > 7) diaSemanaSumar = ((diaSemanaSumar - 1) % 7) + 1;

                    const configSumar = producto.config_diaria[diaSemanaSumar] || { base_consumption: 0 };
                    const demandai = configSumar.base_consumption + dailyWaste;
                    Dfutura += demandai;
                }

                const ReorderPoint = Math.ceil(DhoyRemanente + Dfutura);
                sugeridoHTML = `<div class="reorder-suggested" title="Stock sugerido al conteo de 9:00 AM">Stock Mín: ${ReorderPoint}</div>`;
            }

            // Check if should show urgent icon (today's column, empty, deadline approaching)
            let mostrarAlerta = false;
            if (esHoy && !cantidad && habilitado && beforeDeadline) {
                const { hours, minutes } = calcularTiempoRestante();
                const totalMinutes = hours * 60 + minutes;
                mostrarAlerta = totalMinutes < 120; // Show alert if less than 2 hours
            }

            // Determine cell content
            let cellContent = '';

            // 1. Mostrar Cantidad si existe (independiente de si está bloqueado o no)
            let cantidadHTML = '';
            if (cantidad) {
                const checkmark = esPasado ? '✓ ' : '';
                cantidadHTML = `<span class="${esPasado ? 'cantidad-completada' : 'cantidad-display'}">${checkmark}${cantidad}</span>`;
            } else {
                cantidadHTML = '<span class="text-muted">-</span>';
            }

            // 2. Mostrar icono de bloqueo si aplica
            let lockHTML = (tieneConfig && !beforeDeadline && !esPasado) ? '<span class="lock-indicator" title="Plazo vencido">🔒</span>' : '';

            // 3. Montar contenido principal
            if (mostrarAlerta && !cantidad) {
                cellContent = `<span class="urgent-badge">🚨</span>` + cantidadHTML;
            } else {
                cellContent = lockHTML + cantidadHTML;
            }

            // 4. Agregar siempre el Stock Mínimo si existe config
            cellContent += sugeridoHTML;

            html += `
                <td class="day-cell ${esPasado ? 'past-date' : ''} ${habilitado ? 'enabled' : 'disabled'} ${cantidad ? 'has-order' : ''} ${mostrarAlerta ? 'alert-cell' : ''}"\r
                    data-producto-id="${producto.id_producto}"\r
                    data-dia-index="${index}"\r
                    data-fecha-entrega="${fechaEntregaSQL}"\r
                    data-fecha-hora="${fechaHora || ''}"\r
                    ${habilitado && puedeEditar ? `onclick="editarCantidad(this)"` : ''}>\r
                    ${cellContent}\r
                </td>\r
            `;
        });

        html += `
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    $('#productos-container').html(html);

    // Add tooltips
    $('.day-cell[data-fecha-hora]').each(function () {
        const fechaHora = $(this).data('fecha-hora');
        if (fechaHora) {
            $(this).attr('title', `Registrado: ${formatearFechaHora(fechaHora)}`);
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
    const fechaEntrega = $cell.data('fecha-entrega');
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
            guardarCantidad(productoId, fechaEntrega, nuevaCantidad, cantidadActual, $cell);
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
function guardarCantidad(productoId, fechaEntrega, nuevaCantidad, cantidadAnterior, $cell) {
    // Si no cambió el valor, solo re-renderizar
    if (nuevaCantidad.toString() === cantidadAnterior.toString()) {
        renderizarTabla();
        return;
    }

    // Validar hora límite para pedidos de hoy
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const fechaEntregaDate = new Date(fechaEntrega + 'T00:00:00');
    const manana = new Date(hoy);
    manana.setDate(hoy.getDate() + 1);

    // Si la entrega es mañana (pedido de hoy), validar hora límite
    if (fechaEntregaDate.toDateString() === manana.toDateString()) {
        const beforeDeadline = verificarHoraLimite();
        if (!beforeDeadline) {
            mostrarError('Plazo vencido. No se pueden registrar pedidos para entrega de mañana después de las 12:00 PM');
            renderizarTabla();
            return;
        }
    }

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
