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
    const fmt = (v, d = 4) => v !== null && v !== undefined ? Number(v).toLocaleString('es-NI', { minimumFractionDigits: d, maximumFractionDigits: d }) : '<span class="val-na">N/A</span>';
    const fmt2 = (v) => fmt(v, 2);

    // Stock max final con badge "Ajustado" para congelados (B)
    let stockMaxFinalHtml = '';
    if (p.stock_max_final !== null) {
        stockMaxFinalHtml = fmt2(p.stock_max_final);
        if (p.es_ajustado) {
            stockMaxFinalHtml += ' <span class="badge-ajustado">Adj</span>';
        }
    } else {
        stockMaxFinalHtml = '<span class="val-na">N/A</span>';
    }

    // (Inventario y pedido sugerido removidos)

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

        cellFecha = `<div class="text-center" style="line-height: 1.1;"><span class="fw-bold text-dark">${sem} ${fechaDespFormat}</span> <small class="text-muted" style="font-size: 0.85em;">(${diasHasta}d)</small></div>`;
    }

    // Label de unidad de despacho (aparece debajo del número)
    const despTag = p.despacho_nombre
        ? `<div class="desp-unit-label" title="${escHtml(p.despacho_nombre)}">${escHtml(p.despacho_nombre)}</div>`
        : '';

    return `
        <tr class="ps-fila-producto" data-id="${p.id_pp}">
            <td class="col-producto">
                <div class="fw-bold text-dark">${escHtml(p.nombre)}</div>
            </td>
            <td class="col-presentacion">
                <div class="text-muted" style="font-size: 0.9em;">${escHtml(p.unidad || '—')}</div>
            </td>
            
            <td class="text-end num-cell bg-light-gray">${fmt2(p.prom_consumo)}</td>
            <td class="text-end num-cell text-muted bg-light-gray" style="font-size: 0.9em;">${fmt2(p.desv_estandar)}</td>
            <td class="text-end num-cell fw-bold text-dark bg-light-gray">${fmt2(p.cons_semanal)}</td>
            <td class="text-end num-cell text-muted bg-light-gray">${fmt(p.cons_diario, 3)}</td>
            
            <td class="text-end num-cell bg-mid-gray">
                <div>${fmt2(p.stock_minimo)}</div>
                ${despTag}
            </td>
            <td class="text-end num-cell bg-mid-gray">
                <div>${fmt2(p.stock_maximo)}</div>
                ${despTag}
            </td>
            <td class="text-end num-cell fw-bold text-dark bg-mid-gray">
                <div>${stockMaxFinalHtml}</div>
                ${despTag}
            </td>

            <td class="text-center col-pronostico bg-pronostico align-middle">
                ${cellFecha}
            </td>
            <td class="text-end col-pronostico num-cell bg-pronostico align-middle">
                <div class="pron-d1" data-idpp="${p.id_pp}">—</div>
            </td>
            <td class="text-center col-pronostico num-cell bg-pronostico align-middle">
                <div class="pron-desp fw-bold" data-idpp="${p.id_pp}" style="font-size: 1.1em;">—</div>
            </td>
        </tr>
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
    const semDesde = parseInt($('#filtroSemanaDesde').val()) || semCorte;
    const semHasta = parseInt($('#filtroSemanaHasta').val()) || semCorte;

    for (let i = 0; i < productos.length; i += LOTE) {
        const batch = productos.slice(i, i + LOTE);
        await Promise.all(batch.map(prod => {
            // cons_proy_diario: usa total_consumo_rango (suma bruta de TODAS las semanas del rango,
            // sin filtro de ventana activa) dividido por los días totales del rango.
            // Esto es idéntico al _promDiario del Kardex: misma fuente, mismo denominador.
            const nSemsRango = (semHasta - semDesde) + 1;
            // Preferir total_consumo_rango (enviado por calcular_v2 con ceros incluidos)
            // para garantizar alineación exacta con el Kardex.
            const totalConsRango = (prod.total_consumo_rango ?? null) !== null
                ? prod.total_consumo_rango
                : Object.values(prod.semanas_consumo ?? {}).reduce((a, v) => a + v, 0);
            const consProyDiario = (nSemsRango > 0 && totalConsRango > 0)
                ? totalConsRango / (nSemsRango * 7)
                : prod.cons_diario;

            return $.ajax({
                url: 'ajax/pedido_sugerido_pronostico_despacho.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    id_pp: prod.id_pp,
                    cod_sucursal: codSucursalActual,
                    sem_corte: semCorte,
                    sem_hasta: semHasta,
                    fecha_despacho: prod.fecha_proximo_despacho,
                    cons_diario: prod.cons_diario,
                    cons_proy_diario: consProyDiario,   // baseline histórico Kardex-aligned
                    despacho_factor: prod.despacho_factor ?? 1,
                    stock_max_final: prod.stock_max_final ?? 0
                }
            }).then(resp => {
                const $d1   = $(`.pron-d1[data-idpp="${prod.id_pp}"]`);
                const $desp = $(`.pron-desp[data-idpp="${prod.id_pp}"]`);
                if (!resp || !resp.ok) { errores++; $d1.html('—'); $desp.html('—'); return; }

                if (resp.sin_inventario) {
                    $d1.html('<span class="text-muted small">Sin datos</span>');
                    $desp.html('<span class="text-muted">—</span>');
                    return;
                }

                const d1Val = Number(resp.stock_D1_paquetes ?? 0).toFixed(1);
                $d1.html(`${d1Val} <br><small class="text-muted" style="font-size: 9px; font-weight: 600;">PAQ</small>`);

                const dp  = resp.despacho_sugerido_pronostico ?? 0;
                const cls = dp > 0 ? 'fw-bold text-danger' : 'text-success fw-bold';
                $desp.html(`<span class="${cls}">${dp}</span>`);
            }).catch(() => { errores++; });
        }));
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
