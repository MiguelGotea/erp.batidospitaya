'use strict';
/* ===================================================
   Pedido Sugerido — JavaScript
   Módulo: Productos
   =================================================== */

// Categorías de insumo
const CAT_LABELS = {
    A: 'Frescos', B: 'Congelados', C: 'Fresas',
    D: 'Desechables', E: 'Fijos', F: 'Secos y Preparación', G: 'Productos de Mostrador'
};
const CAT_ORDER = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

// Estado global
let datosResultado = [];   // Array completo de productos devuelto por el AJAX

// ====================================================
// Inicialización
// ====================================================
$(document).ready(function () {
    cargarSucursales();
    cargarSemanaActual();

    $('#btnCalcular').on('click', calcularPedido);

    // Búsqueda en tabla
    $('#buscarProducto').on('input', function () {
        const q = $(this).val().toLowerCase();
        $('#tbodyProductos tr.ps-fila-producto').each(function () {
            const txt = $(this).text().toLowerCase();
            const idParent = $(this).data('id');
            if (txt.includes(q)) {
                $(this).show();
                $(`.ps-fila-indicadores[data-parent="${idParent}"]`).show();
            } else {
                $(this).hide();
                $(`.ps-fila-indicadores[data-parent="${idParent}"]`).hide();
            }
        });
        // Ocultar headers de grupo vacíos
        $('#tbodyProductos tr.fila-grupo-header').each(function () {
            const next = $(this).nextUntil('.fila-grupo-header');
            const alguno = next.filter(':visible').length > 0;
            $(this).toggle(alguno);
        });
    });

    if (PUEDE_EDITAR) {
        $('#btnGuardarInventario').on('click', guardarInventario);
    }
});

// ====================================================
// Cargar sucursales activas
// ====================================================
function cargarSucursales() {
    $.ajax({
        url: 'ajax/configuracion_logistica_get_sucursales.php',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.success && res.sucursales.length > 0) {
                res.sucursales.forEach(s => {
                    $('#filtroSucursal').append(
                        `<option value="${s.codigo}">${s.nombre}</option>`
                    );
                });
            }
        }
    });
}

// ====================================================
// Mostrar semana actual
// ====================================================
function cargarSemanaActual() {
    $.ajax({
        url: 'ajax/dashboard_consumo_get_filtros.php',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.ok && res.semana_actual && res.semana_actual.numero_semana) {
                $('#semanaActualNum').text(res.semana_actual.numero_semana);
                $('#badgeSemanaActual').removeClass('d-none');
            }
        },
        error: function () { /* silencioso — el badge simplemente no aparece */ }
    });
}

// ====================================================
// Calcular pedido sugerido
// ====================================================
function calcularPedido() {
    const desde = parseInt($('#filtroSemanaDesde').val());
    const hasta = parseInt($('#filtroSemanaHasta').val());
    const sucursal = $('#filtroSucursal').val();

    // Validaciones
    if (!desde || !hasta) {
        return Swal.fire({ icon: 'warning', title: 'Filtros incompletos', text: 'Ingresa el rango de semanas.', confirmButtonColor: '#51B8AC' });
    }
    if (!sucursal) {
        return Swal.fire({ icon: 'warning', title: 'Sucursal requerida', text: 'Selecciona una sucursal para calcular.', confirmButtonColor: '#51B8AC' });
    }
    if (desde > hasta) {
        return Swal.fire({ icon: 'warning', title: 'Rango inválido', text: 'La semana "Desde" debe ser menor o igual a "Hasta".', confirmButtonColor: '#51B8AC' });
    }

    $('#panelInicial').addClass('d-none');
    $('#panelDatos').addClass('d-none');
    $('#panelLoader').removeClass('d-none');

    $.ajax({
        url: 'ajax/pedido_sugerido_calcular.php',
        method: 'POST',
        data: {
            semana_desde_num: desde,
            semana_hasta_num: hasta,
            cod_sucursal: sucursal
        },
        dataType: 'json',
        success: function (res) {
            $('#panelLoader').addClass('d-none');
            if (!res.ok) {
                $('#panelInicial').removeClass('d-none');
                return Swal.fire({ icon: 'error', title: 'Error', text: res.msg, confirmButtonColor: '#51B8AC' });
            }
            datosResultado = res.productos;
            renderizarResultados(res);
        },
        error: function () {
            $('#panelLoader').addClass('d-none');
            $('#panelInicial').removeClass('d-none');
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#51B8AC' });
        }
    });
}

// ====================================================
// Renderizar KPIs y tabla
// ====================================================
function renderizarResultados(res) {
    // KPIs
    $('#kpiNSemanas').text(res.n_semanas);
    $('#kpiNProductos').text(res.productos.length);
    $('#kpiCapacidadCongelados').text(
        res.capacidad_congelados !== null
            ? Number(res.capacidad_congelados).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '—'
    );
    $('#kpiFactorCongelados').text(
        res.factor_congelados !== null
            ? Number(res.factor_congelados).toLocaleString('es-NI', { minimumFractionDigits: 4, maximumFractionDigits: 4 })
            : '—'
    );

    // Agrupar productos por categoría
    const grupos = {};
    CAT_ORDER.forEach(c => { grupos[c] = []; });
    grupos['_sin_cat'] = [];

    res.productos.forEach(p => {
        const cat = p.categoria_insumo || '_sin_cat';
        if (!grupos[cat]) grupos[cat] = [];
        grupos[cat].push(p);
    });

    let totalFilas = 0;
    let html = '';

    // Orden: categorías definidas + las sin cat al final
    [...CAT_ORDER, '_sin_cat'].forEach(cat => {
        const items = grupos[cat];
        if (!items || items.length === 0) return;

        const catLabel = cat === '_sin_cat'
            ? 'Sin Categoría Asignada'
            : `${cat} — ${CAT_LABELS[cat] || cat}`;

        html += `<tr class="fila-grupo-header"><td colspan="12">${catLabel} (${items.length})</td></tr>`;

        items.forEach(p => {
            totalFilas++;
            html += buildFila(p, cat);
        });
    });

    $('#tbodyProductos').html(html || '<tr><td colspan="12" class="text-center text-muted py-4">Sin productos con consumo en el período.</td></tr>');
    $('#labelResultados').text(`${totalFilas} producto${totalFilas !== 1 ? 's' : ''}`);

    $('#panelDatos').removeClass('d-none');
}

// ====================================================
// Construir fila de la tabla
// ====================================================
function buildFila(p, cat) {
    const catBadge = cat !== '_sin_cat'
        ? `<span class="cat-badge cat-${cat}-bg">${cat}</span>`
        : '<span class="val-na">—</span>';

    const fmt = (v, d = 4) => v !== null && v !== undefined ? Number(v).toLocaleString('es-NI', { minimumFractionDigits: d, maximumFractionDigits: d }) : '<span class="val-na">N/A</span>';
    const fmt2 = (v) => fmt(v, 2);

    // Stock max final con badge "Ajustado" para congelados (B)
    let stockMaxFinalHtml = '';
    if (p.stock_max_final !== null) {
        stockMaxFinalHtml = fmt2(p.stock_max_final);
        if (p.es_ajustado) {
            stockMaxFinalHtml += '<span class="badge-ajustado">Ajustado</span>';
        }
    } else {
        stockMaxFinalHtml = '<span class="val-na">N/A</span>';
    }

    // Input de inventario
    const inventarioVal = p.inventario_actual !== null ? p.inventario_actual : '';
    const roAttr = PUEDE_EDITAR ? '' : 'disabled';
    const inputHtml = PUEDE_EDITAR
        ? `<input type="number" min="0" step="1"
                  class="input-inventario"
                  data-id-pp="${p.id_pp}"
                  data-stock-max-final="${p.stock_max_final}"
                  value="${inventarioVal}"
                  placeholder="0"
                  ${roAttr}
                  onfocus="this.dataset.inicial = this.value"
                  oninput="recalcularPedidoFila(this)">`
        : `<span class="fw-bold">${inventarioVal !== '' ? inventarioVal : '—'}</span>`;

    // Pedido sugerido inicial
    const pedidoHtml = buildPedidoHtml(p.stock_max_final, inventarioVal !== '' ? Number(inventarioVal) : null);

    // Indicadores detallados (para verificación)
    const detalleHtml = `
        <tr class="ps-fila-indicadores cat-${cat !== '_sin_cat' ? cat : 'X'}" data-parent="${p.id_pp}">
            <td colspan="12">
                <div class="ps-indicadores-wrapper">
                    <div class="ps-indicadores-container">
                        <span class="ps-ind-item" title="Ajuste Demanda"><b>Adj:</b> ${fmt(p.ajuste_demanda * 100, 2)}%</span>
                        <span class="ps-ind-item" title="Días Ciclo"><b>Ciclo:</b> ${p.dias_ciclo}d</span>
                        <span class="ps-ind-item" title="Días Desfase"><b>Desf:</b> ${p.dias_desfase}d</span>
                        <span class="ps-ind-item" title="Días Stock Mínimo"><b>S.Mín:</b> ${p.dias_stock_min}d</span>
                        <span class="ps-ind-item" title="Consumo Diario Final"><b>C.Diario:</b> ${fmt(p.cons_diario, 4)}</span>
                    </div>
                </div>
            </td>
        </tr>
    `;

    return `
        <tr class="ps-fila-producto cat-${cat !== '_sin_cat' ? cat : 'X'}" data-id="${p.id_pp}">
            <td class="col-producto"><span class="fw-500">${escHtml(p.nombre)}</span></td>
            <td class="text-center">${catBadge}</td>
            <td>${escHtml(p.unidad || '—')}</td>
            <td class="text-end">${fmt2(p.prom_consumo)}</td>
            <td class="text-end">${fmt2(p.desv_estandar)}</td>
            <td class="text-end fw-bold">${fmt2(p.cons_semanal)}</td>
            <td class="text-end">${fmt(p.cons_diario)}</td>
            <td class="text-end">${fmt2(p.stock_minimo)}</td>
            <td class="text-end">${fmt2(p.stock_maximo)}</td>
            <td class="text-end">${stockMaxFinalHtml}</td>
            <td class="text-center col-inventario">${inputHtml}</td>
            <td class="text-center col-pedido" id="pedido-${p.id_pp}">${pedidoHtml}</td>
        </tr>
        ${detalleHtml}
    `;
}

// ====================================================
// Construir HTML del pedido sugerido
// ====================================================
function buildPedidoHtml(stockMaxFinal, inventario) {
    if (stockMaxFinal === null) {
        return '<span class="pedido-valor pedido-nd">Sin config.</span>';
    }
    if (inventario === null) {
        return '<span class="pedido-valor pedido-nd">Ingresa inv.</span>';
    }
    const pedido = stockMaxFinal - inventario;
    if (pedido <= 0) {
        return `<span class="pedido-valor pedido-ok">No ordenar</span>`;
    }
    return `<span class="pedido-valor pedido-warn">${Number(pedido).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>`;
}

// ====================================================
// Recalcular pedido en tiempo real al cambiar inventario
// ====================================================
function recalcularPedidoFila(inputEl) {
    const idPP = inputEl.dataset.idPp;
    const stockMaxFinal = parseFloat(inputEl.dataset.stockMaxFinal);
    const inventario = inputEl.value !== '' ? parseFloat(inputEl.value) : null;

    $(`#pedido-${idPP}`).html(buildPedidoHtml(
        isNaN(stockMaxFinal) ? null : stockMaxFinal,
        inventario
    ));
}

// ====================================================
// Guardar inventario en BD
// ====================================================
function guardarInventario() {
    if (!PUEDE_EDITAR) return;

    const items = [];
    $('.input-inventario').each(function () {
        const val = $(this).val().trim();
        if (val !== '') {
            items.push({
                id_producto_presentacion: $(this).data('id-pp'),
                cantidad: parseInt(val, 10),
                cod_sucursal: $('#filtroSucursal').val(),
                fecha_inventario: new Date().toISOString().slice(0, 10)
            });
        }
    });

    if (items.length === 0) {
        return Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay valores de inventario ingresados.', confirmButtonColor: '#51B8AC' });
    }

    $('#btnGuardarInventario').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando…');

    $.ajax({
        url: 'ajax/pedido_sugerido_guardar_inventario.php',
        method: 'POST',
        data: JSON.stringify({ items }),
        contentType: 'application/json',
        dataType: 'json',
        success: function (res) {
            $('#btnGuardarInventario').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Inventario');
            if (res.ok) {
                Swal.fire({
                    icon: 'success', title: 'Guardado', toast: true, position: 'top-end',
                    text: `${res.guardados} registro${res.guardados !== 1 ? 's' : ''} guardado${res.guardados !== 1 ? 's' : ''}.`,
                    timer: 2000, showConfirmButton: false
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error al guardar', text: res.msg, confirmButtonColor: '#51B8AC' });
            }
        },
        error: function () {
            $('#btnGuardarInventario').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Inventario');
            Swal.fire({ icon: 'error', title: 'Error de conexión', confirmButtonColor: '#51B8AC' });
        }
    });
}

// ====================================================
// Utilidades
// ====================================================
function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
