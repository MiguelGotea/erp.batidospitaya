'use strict';
/* ===================================================
   Pedido Sugerido v2 — JavaScript
   Módulo: Productos (Con Plan de Despacho)
   =================================================== */

// Categorías de insumo
const CAT_LABELS = {
    A: 'Frescos', B: 'Congelados', C: 'Fresas',
    D: 'Desechables', E: 'Fijos', F: 'Secos y Preparación', G: 'Productos de Mostrador'
};
const CAT_ORDER = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

// Estado global
let datosResultado = [];     // Array completo de productos devuelto por el AJAX
let codSucursalActual = null; // Sucursal activa al momento del último cálculo

// ====================================================
// Inicialización
// ====================================================
$(document).ready(function () {
    cargarSucursales();
    cargarSemanaActual();

    $('#btnCalcular').on('click', calcularPedido);
    $('#btnCalcularPronostico').on('click', calcularPronosticoMasivo);

    // Habilitar btnCalcularPronostico cuando semCortePron tenga valor y haya cálculo previo
    $('#semCortePron').on('input', function () {
        const tieneCorte = parseInt($(this).val()) > 0;
        const tieneDatos = datosResultado.length > 0;
        $('#btnCalcularPronostico').prop('disabled', !(tieneCorte && tieneDatos));
    });

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

    // El botón de guardado ahora es flotante y se maneja por su selector directo o función onclick

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
                // Ya se maneja desde el servidor en el render inicial
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
        url: 'ajax/pedido_sugerido_calcular_v2.php',
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
            codSucursalActual = $('#filtroSucursal').val();
            // Habilitar pronóstico si ya hay semana de corte ingresada
            const _sc = parseInt($('#semCortePron').val());
            $('#btnCalcularPronostico').prop('disabled', !(_sc > 0));
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
    // (Lógica de inventario removida)
    // KPIs
    $('#kpiNSemanas').text(res.n_semanas);
    $('#kpiNProductos').text(res.productos.length);
    // KPI: Capacidad congelados (priorizar paquetes si está disponible)
    const capDisplay = res.capacidad_paquetes !== null && res.capacidad_paquetes !== undefined
        ? Number(res.capacidad_paquetes).toLocaleString('es-NI', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' paq.'
        : (res.capacidad_congelados !== null
            ? Number(res.capacidad_congelados).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (legacy)'
            : '—');
    $('#kpiCapacidadCongelados').text(capDisplay);
    $('#kpiFactorCongelados').text(
        res.factor_congelados !== null
            ? Number(res.factor_congelados).toLocaleString('es-NI', { minimumFractionDigits: 4, maximumFractionDigits: 4 })
            : '—'
    );
    // Badge de plan de despacho (se agrega junto al label de semanas)
    const usaPlan = res.usa_plan_despacho;
    const planBadge = usaPlan
        ? '<span class="badge bg-success ms-2" title="Usando ciclo real del Plan de Despacho">Plan Activo</span>'
        : '<span class="badge bg-secondary ms-2" title="Usando configuración logística fija">Config. Fija</span>';
    // Limpiar badge anterior y agregar el nuevo
    $('#kpiNSemanas').parent().find('.ps-kpi-label .badge').remove();
    $('#kpiNSemanas').parent().find('.ps-kpi-label').append(planBadge);

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

        html += `<tr class="fila-grupo-header"><td colspan="13">${catLabel} (${items.length})</td></tr>`;

        items.forEach(p => {
            totalFilas++;
            html += buildFila(p, cat);
        });
    });

    $('#tbodyProductos').html(html || '<tr><td colspan="13" class="text-center text-muted py-4">Sin productos con consumo en el período.</td></tr>');
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

    // (Inventario y pedido sugerido removidos)

    const detalleHtml = `
        <tr class="ps-fila-indicadores cat-${cat !== '_sin_cat' ? cat : 'X'}" data-parent="${p.id_pp}">
            <td colspan="13">
                <div class="ps-indicadores-wrapper">
                    <div class="ps-indicadores-container">
                        <span class="ps-ind-item" title="Ajuste Demanda"><b>Adj:</b> ${fmt(p.ajuste_demanda * 100, 2)}%</span>
                        <span class="ps-ind-item" title="Días Ciclo"><b>Ciclo:</b> ${p.dias_ciclo}d</span>
                        <span class="ps-ind-item" title="Días Desfase"><b>Desf:</b> ${p.dias_desfase}d</span>
                        <span class="ps-ind-item" title="Días Stock Mínimo"><b>S.Mín:</b> ${p.dias_stock_min}d</span>
                    </div>
                </div>
            </td>
        </tr>
    `;

    // Semáforo: fecha del próximo despacho
    const fechaDesp = p.fecha_proximo_despacho;
    const diasHasta = p.dias_hasta_despacho;
    let cellFecha = '—';
    if (fechaDesp) {
        let sem = '';
        const dp = p.dias_desfase ?? 1;
        if (diasHasta <= dp) sem = '🔴';
        else if (diasHasta <= (p.dias_ciclo ?? 14) / 2) sem = '🟡';
        else sem = '🟢';

        const mesesCortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const partesFecha = fechaDesp.split('-');
        let fechaDespFormat = fechaDesp;
        if (partesFecha.length === 3) {
            const año = partesFecha[0].substring(2, 4);
            const mes = mesesCortos[parseInt(partesFecha[1], 10) - 1];
            const dia = partesFecha[2];
            fechaDespFormat = `${dia}/${mes}/${año}`;
        }

        cellFecha = `${sem} ${fechaDespFormat}<br><small class="text-muted">en ${diasHasta}d</small>`;
    }

    // Label de unidad de despacho (aparece encima del número en columnas de stock/pedido)
    const despTag = p.despacho_nombre
        ? `<div class="desp-unit-label" title="${escHtml(p.despacho_nombre)}">${escHtml(p.despacho_nombre)}</div>`
        : '';

    return `
        <tr class="ps-fila-producto cat-${cat !== '_sin_cat' ? cat : 'X'}" data-id="${p.id_pp}">
            <td class="col-producto"><span class="fw-500">${escHtml(p.nombre)}</span></td>
            <td class="text-center">${catBadge}</td>
            <td>${escHtml(p.unidad || '—')}</td>
            <td class="text-end">${fmt2(p.prom_consumo)}</td>
            <td class="text-end">${fmt2(p.desv_estandar)}</td>
            <td class="text-end fw-bold">${fmt2(p.cons_semanal)}</td>
            <td class="text-end">${fmt(p.cons_diario)}</td>
            <td class="text-end">${despTag}${fmt2(p.stock_minimo)}</td>
            <td class="text-end">${despTag}${fmt2(p.stock_maximo)}</td>
            <td class="text-end">${despTag}${stockMaxFinalHtml}</td>

            <td class="text-center col-pronostico">${cellFecha}</td>
            <td class="text-end col-pronostico"><span class="pron-d1" data-idpp="${p.id_pp}">—</span></td>
            <td class="text-center col-pronostico"><span class="pron-desp" data-idpp="${p.id_pp}">—</span></td>
        </tr>
        ${detalleHtml}
    `;
}


// ====================================================
// Calcular pronóstico D-1 para todos los productos
// ====================================================
async function calcularPronosticoMasivo() {
    const semCorte = parseInt($('#semCortePron').val());
    if (!semCorte || !datosResultado.length || !codSucursalActual) {
        return Swal.fire({
            icon: 'warning', title: 'Datos incompletos',
            text: 'Primero calcula el pedido sugerido e ingresa la semana de corte.',
            confirmButtonColor: '#51B8AC'
        });
    }

    const $btn = $('#btnCalcularPronostico');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Calculando…');

    // Limpiar resultados anteriores
    $('.pron-d1').html('<span class="text-muted small">…</span>');
    $('.pron-desp').html('<span class="text-muted small">…</span>');


    const productos = datosResultado.filter(p => p.fecha_proximo_despacho);
    const LOTE = 5;
    let errores = 0;

    for (let i = 0; i < productos.length; i += LOTE) {
        const batch = productos.slice(i, i + LOTE);
        await Promise.all(batch.map(prod => $.ajax({
            url: 'ajax/pedido_sugerido_pronostico_despacho.php',
            method: 'POST',
            dataType: 'json',
            data: {
                id_pp: prod.id_pp,
                cod_sucursal: codSucursalActual,
                sem_corte: semCorte,
                fecha_despacho: prod.fecha_proximo_despacho,
                cons_diario: prod.cons_diario,
                despacho_factor: prod.despacho_factor ?? 1,
                stock_max_final: prod.stock_max_final ?? 0
            }
        }).then(resp => {
            const $d1 = $(`.pron-d1[data-idpp="${prod.id_pp}"]`);
            const $desp = $(`.pron-desp[data-idpp="${prod.id_pp}"]`);
            if (!resp || !resp.ok) { errores++; $d1.html('—'); $desp.html('—'); return; }

            if (resp.sin_inventario) {
                $d1.html('<span class="text-muted small">Sin datos</span>');
                $desp.html('<span class="text-muted">—</span>');
                return;
            }

            const d1Val = Number(resp.stock_D1_paquetes ?? 0).toFixed(1);
            $d1.html(`${d1Val} <small class="text-muted">paq</small>`);

            const dp = resp.despacho_sugerido_pronostico ?? 0;
            const cls = dp > 0 ? 'fw-bold text-danger' : 'text-success';
            $desp.html(`<span class="${cls}">${dp}</span>`);
        }).catch(() => { errores++; })));
    }

    const label = errores > 0
        ? `<i class="bi bi-graph-up-arrow me-1"></i> Recalcular (${errores} errores)`
        : '<i class="bi bi-graph-up-arrow me-1"></i> Recalcular';
    $btn.prop('disabled', false).html(label);
}

// ====================================================
// Utilidades
// ====================================================
function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
