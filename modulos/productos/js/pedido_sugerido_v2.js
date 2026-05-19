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
let datosResultado = [];      // Array completo de productos devuelto por el AJAX
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
            if (txt.includes(q)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        // Ocultar headers de grupo vacíos
        $('#tbodyProductos tr.fila-grupo-header').each(function () {
            const next = $(this).nextUntil('.fila-grupo-header');
            const alguno = next.filter(':visible').length > 0;
            $(this).toggle(alguno);
        });
    });
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
        error: function () { /* silencioso */ }
    });
}

// ====================================================
// Calcular pedido sugerido
// ====================================================
function calcularPedido() {
    const desde    = parseInt($('#filtroSemanaDesde').val());
    const hasta    = parseInt($('#filtroSemanaHasta').val());
    const sucursal = $('#filtroSucursal').val();

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
            datosResultado    = res.productos;
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
    // KPIs
    $('#kpiNSemanas').text(res.n_semanas);
    $('#kpiNProductos').text(res.productos.length);

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

    const usaPlan  = res.usa_plan_despacho;
    const planBadge = usaPlan
        ? '<span class="badge bg-success ms-2" title="Usando ciclo real del Plan de Despacho">Plan Activo</span>'
        : '<span class="badge bg-secondary ms-2" title="Usando configuración logística fija">Config. Fija</span>';
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

    [...CAT_ORDER, '_sin_cat'].forEach(cat => {
        const items = grupos[cat];
        if (!items || items.length === 0) return;

        const catLabel = cat === '_sin_cat'
            ? 'Sin Categoría Asignada'
            : `${cat} — ${CAT_LABELS[cat] || cat}`;

        const fmtLocal = (v, d = 4) => v !== null && v !== undefined ? Number(v).toLocaleString('es-NI', { minimumFractionDigits: d, maximumFractionDigits: d }) : 'N/A';

        let headerInfo = '';
        if (cat !== '_sin_cat' && items.length > 0) {
            const p0 = items[0];
            headerInfo = ` <span class="ms-3 small fw-normal text-white" style="font-size: 0.85em;">
                <b title="Ajuste Demanda">Adj:</b> ${fmtLocal(p0.ajuste_demanda * 100, 2)}% <span class="mx-1">|</span>
                <b title="Días Ciclo">Ciclo:</b> ${p0.dias_ciclo}d <span class="mx-1">|</span>
                <b title="Días Desfase">Desf:</b> ${p0.dias_desfase}d <span class="mx-1">|</span>
                <b title="Días Stock Mínimo">S.Mín:</b> ${p0.dias_stock_min}d
            </span>`;
        }

        html += `<tr class="fila-grupo-header"><td colspan="12">${catLabel} (${items.length})${headerInfo}</td></tr>`;

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
    const fmt  = (v, d = 4) => v !== null && v !== undefined ? Number(v).toLocaleString('es-NI', { minimumFractionDigits: d, maximumFractionDigits: d }) : '<span class="val-na">N/A</span>';
    const fmt2 = (v) => fmt(v, 2);

    let stockMaxFinalHtml = '';
    if (p.stock_max_final !== null) {
        stockMaxFinalHtml = fmt2(p.stock_max_final);
        if (p.es_ajustado) {
            stockMaxFinalHtml += '<br><span class="badge-ajustado">Ajustado</span>';
        }
    } else {
        stockMaxFinalHtml = '<span class="val-na">N/A</span>';
    }

    // Semáforo de fecha de próximo despacho
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

        cellFecha = `<div class="d-flex flex-column align-items-center" style="line-height: 1.2;"><span class="fw-bold text-dark" style="font-size: 13px;">${sem} ${fechaDespFormat}</span><small class="text-muted" style="font-size: 10px;">en ${diasHasta}d</small></div>`;
    }

    const despTag = p.despacho_nombre
        ? `<div class="desp-unit-label" title="${escHtml(p.despacho_nombre)}">${escHtml(p.despacho_nombre)}</div>`
        : '';

    return `
        <tr class="ps-fila-producto" data-id="${p.id_pp}">
            <td class="col-producto">
                <div class="fw-bold text-dark" style="font-size: 13px;">${escHtml(p.nombre)}</div>
            </td>
            <td class="col-presentacion">
                <div class="text-muted" style="font-size: 11px;">${escHtml(p.unidad || '—')}</div>
            </td>

            <td class="text-end num-cell bg-light-gray" style="font-size: 13px;">${fmt2(p.prom_consumo)}</td>
            <td class="text-end num-cell text-muted bg-light-gray" style="font-size: 12px;">${fmt2(p.desv_estandar)}</td>
            <td class="text-end num-cell fw-bold text-dark bg-light-gray" style="font-size: 13px;">${fmt2(p.cons_semanal)}</td>
            <td class="text-end num-cell text-muted bg-light-gray" style="font-size: 13px;">${fmt(p.cons_diario, 3)}</td>

            <td class="text-end num-cell bg-mid-gray">
                <div style="font-size: 13px;">${fmt2(p.stock_minimo)}</div>
                ${despTag}
            </td>
            <td class="text-end num-cell bg-mid-gray">
                <div style="font-size: 13px;">${fmt2(p.stock_maximo)}</div>
                ${despTag}
            </td>
            <td class="text-end num-cell fw-bold text-dark bg-mid-gray">
                <div style="font-size: 13px;">${stockMaxFinalHtml}</div>
                ${despTag}
            </td>

            <td class="text-center col-pronostico bg-pronostico align-middle">
                ${cellFecha}
            </td>
            <td class="text-end col-pronostico num-cell bg-pronostico align-middle">
                <div class="pron-d1" data-idpp="${p.id_pp}" style="font-size: 13px;">—</div>
            </td>
            <td class="text-center col-pronostico num-cell bg-pronostico align-middle">
                <div class="pron-desp" data-idpp="${p.id_pp}" style="font-size: 14px;">—</div>
            </td>
        </tr>
    `;
}

// ====================================================
// Calcular pronóstico D-1 — llamada bulk al servidor
// Matemática idéntica a la línea morada del dashboard_consumo:
//   · Kardex como stock base
//   · Balance diario (movimientos reales + consumo teórico) hasta fin del rango
//   · Proyección DOW-ponderada: 0.65×pDow + 0.35×promDiario
// ====================================================
async function calcularPronosticoMasivo() {
    const semCorte = parseInt($('#semCortePron').val());
    const semDesde = parseInt($('#filtroSemanaDesde').val());
    const semHasta = parseInt($('#filtroSemanaHasta').val());

    if (!semCorte || !datosResultado.length || !codSucursalActual) {
        return Swal.fire({
            icon: 'warning', title: 'Datos incompletos',
            text: 'Primero calcula el pedido sugerido e ingresa la semana de corte.',
            confirmButtonColor: '#51B8AC'
        });
    }

    // semCorte debe estar dentro del rango de análisis
    if (semCorte < semDesde || semCorte > semHasta) {
        return Swal.fire({
            icon: 'warning', title: 'Sem. Corte fuera de rango',
            html: `La semana de corte debe estar entre <strong>${semDesde}</strong> y <strong>${semHasta}</strong>.`,
            confirmButtonColor: '#51B8AC'
        });
    }

    const productos = datosResultado.filter(p => p.fecha_proximo_despacho);
    if (!productos.length) {
        return Swal.fire({
            icon: 'info', title: 'Sin despachos',
            text: 'No hay productos con fecha de próximo despacho calculada.',
            confirmButtonColor: '#51B8AC'
        });
    }

    const $btn = $('#btnCalcularPronostico');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Calculando…');

    // Indicador visual en cada fila
    $('.pron-d1').html('<span class="text-muted small">…</span>');
    $('.pron-desp').html('<span class="text-muted small">…</span>');

    try {
        const fd = new FormData();
        fd.append('semana_desde',  semDesde);
        fd.append('semana_hasta',  semHasta);
        fd.append('semana_corte',  semCorte);
        fd.append('cod_sucursal',  codSucursalActual);

        // Mapa local: id_pp → { despFactor, stockMaxFinal }
        const metaMap = {};

        productos.forEach(p => {
            fd.append('ids_pp[]', p.id_pp);

            // fecha_D1 = fecha_proximo_despacho − 1 día
            const dObj = new Date(p.fecha_proximo_despacho + 'T12:00:00');
            dObj.setDate(dObj.getDate() - 1);
            const fechaD1 = dObj.toISOString().split('T')[0];
            fd.append(`fechas_d1[${p.id_pp}]`, fechaD1);

            metaMap[String(p.id_pp)] = {
                despFactor:    p.despacho_factor > 0 ? p.despacho_factor : 1,
                stockMaxFinal: p.stock_max_final ?? 0
            };
        });

        // Una sola llamada al servidor para todos los productos
        const resp = await fetch('ajax/pedido_sugerido_pronostico_v2.php', {
            method: 'POST',
            body: fd
        }).then(r => r.json());

        if (!resp.ok) {
            Swal.fire({ icon: 'error', title: 'Error', text: resp.msg || 'Error al calcular pronóstico.', confirmButtonColor: '#51B8AC' });
            $('.pron-d1, .pron-desp').html('<span class="text-muted">—</span>');
            return;
        }

        const stocks = resp.stocks || {};

        // Calcular paquetes y despacho sugerido en el cliente y actualizar DOM
        productos.forEach(p => {
            const key  = String(p.id_pp);
            const $d1  = $(`.pron-d1[data-idpp="${p.id_pp}"]`);
            const $dep = $(`.pron-desp[data-idpp="${p.id_pp}"]`);

            const stockUso = stocks[key];   // unidades de uso (null = sin datos Kardex)

            if (stockUso === null || stockUso === undefined) {
                $d1.html('<span class="text-muted small">Sin datos</span>');
                $dep.html('<span class="text-muted">—</span>');
                return;
            }

            const meta     = metaMap[key] || { despFactor: 1, stockMaxFinal: 0 };
            const dfSafe   = meta.despFactor > 0 ? meta.despFactor : 1;
            const stockPaq = stockUso / dfSafe;
            const despSug  = Math.max(0, Math.ceil(meta.stockMaxFinal - stockPaq));

            $d1.html(`${stockPaq.toFixed(1)}<br><small class="text-muted" style="font-size:9px;font-weight:600">PAQ</small>`);

            const cls = despSug > 0 ? 'fw-bold text-danger' : 'text-success fw-bold';
            $dep.html(`<span class="${cls}">${despSug}</span>`);
        });

    } catch (err) {
        console.error('Error calculando pronóstico masivo:', err);
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo calcular el pronóstico.', confirmButtonColor: '#51B8AC' });
        $('.pron-d1, .pron-desp').html('<span class="text-muted">—</span>');
    } finally {
        $btn.prop('disabled', false).html('<i class="bi bi-graph-up-arrow me-1"></i> Recalcular');
    }
}

// ====================================================
// Utilidades
// ====================================================
function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
