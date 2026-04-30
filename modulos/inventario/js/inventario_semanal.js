/* ============================================================
   JS: Inventario Semanal — solo renderizado, los cálculos
       son 100% del backend (inventario_get_data.php)
   ============================================================ */
let semanaActual = 0;

$(document).ready(function () {
    cargarSucursales();
    establecerSemanasDefecto();
    $('#btnCalcular').on('click', cargarDatosInventario);
    $('#btnGuardarInventario').on('click', guardarInventario);
});

/* ── semana por defecto ───────────────────────────────────── */
function establecerSemanasDefecto() {
    $.getJSON('ajax/get_current_week.php', function (res) {
        if (!res.ok) return;
        semanaActual = parseInt(res.semana);
        $('#filtroSemanaInv').val(semanaActual);
    });
}

/* ── lista de sucursales ──────────────────────────────────── */
function cargarSucursales() {
    $.getJSON('ajax/obtener_sucursales.php', function (res) {
        if (!res.success) return;
        let html = '<option value="">-- Seleccione Sucursal --</option>';
        res.data.forEach(s => { html += `<option value="${s.codigo}">${s.nombre}</option>`; });
        $('#filtroSucursal').html(html);
    });
}

/* ── obtener y calcular ───────────────────────────────────── */
function cargarDatosInventario() {
    const sucursal = $('#filtroSucursal').val();
    const semInv = parseInt($('#filtroSemanaInv').val());

    // El rango ahora es automático: 5 semanas hacia atrás desde la semana de inventario
    const semDesde = semInv - 5;
    const semHasta = semInv - 1;

    if (!sucursal || !semInv) {
        Swal.fire('Atención', 'Seleccione sucursal y semana de inventario.', 'warning');
        return;
    }

    $('#loader').show();
    $('#tablaInventarioContainer').hide();
    $('#btnGuardarInventario').hide();

    $.ajax({
        url: 'ajax/inventario_get_data.php',
        method: 'GET',
        data: { cod_sucursal: sucursal, semana_inv: semInv, semana_desde: semDesde, semana_hasta: semHasta },
        success: function (res) {
            $('#loader').hide();
            if (!res.ok) return Swal.fire('Error', res.msg, 'error');

            renderizarTabla(res, semInv);
            $('#tablaInventarioContainer').show();

            // Solo mostramos botón guardar si la semana es actual o futura
            if (semInv >= semanaActual) {
                $('#btnGuardarInventario').show();
            } else {
                $('#btnGuardarInventario').hide();
            }
            if (res.rango_fechas_inv) {
                $('#labelRangoFechas').text(`Del ${res.rango_fechas_inv.fecha_inicio} al ${res.rango_fechas_inv.fecha_fin}`);
            }
        },
        error: function () {
            $('#loader').hide();
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        }
    });
}

/* ── formato número ───────────────────────────────────────── */
const fmt = (v, d = 2) =>
    v !== null && v !== undefined
        ? Number(v).toLocaleString('es-NI', { minimumFractionDigits: d, maximumFractionDigits: d })
        : '—';

/* ── renderizar tabla ─────────────────────────────────────── */
function renderizarTabla(res, semInv) {
    const tbody = $('#tbodyInventario');
    tbody.empty();

    const esSoloLectura = semInv < semanaActual;

    // Orden de categorías
    const orden = ['B', 'A', 'C', 'F', 'D', 'G', 'E'];
    res.productos.sort((a, b) => {
        let iA = orden.indexOf(a.categoria_insumo); if (iA < 0) iA = 99;
        let iB = orden.indexOf(b.categoria_insumo); if (iB < 0) iB = 99;
        return iA !== iB ? iA - iB : a.Nombre.localeCompare(b.Nombre);
    });

    res.productos.forEach(p => {
        const idPP = p.id;
        const cat = p.categoria_insumo ?? '—';

        // Inventario actual guardado en BD (viene del backend)
        const invPres = p._inv_pres !== null && p._inv_pres !== undefined ? p._inv_pres : '';
        const invUnid = p._inv_unidades !== null && p._inv_unidades !== undefined ? p._inv_unidades : '';

        // Stock máximo final (ajustado para B si aplica)
        const sMaxHtml = p.stock_max_final !== null
            ? fmt(p.stock_max_final) + (p.es_ajustado ? ' <span class="badge bg-info text-dark" style="font-size:.65rem">Aj.</span>' : '')
            : '<span class="text-muted small">Sin config.</span>';

        // Pedido sugerido
        const pedidoHtml = p.pedido_sugerido !== null
            ? (p.pedido_sugerido <= 0
                ? '<span class="badge bg-success">No pedir</span>'
                : `<strong class="text-primary">${fmt(p.pedido_sugerido)}</strong>`)
            : '<span class="text-muted small">—</span>';

        // Si la semana de inventario es pasada, todo es readonly
        const readonlyAttr = esSoloLectura ? 'readonly' : '';
        const disabledAttr = esSoloLectura ? 'disabled' : ''; // Para mejor feedback visual

        const despFactor = p.despacho_factor ? parseFloat(p.despacho_factor) : 0;
        const invPresNum = invPres !== '' ? parseFloat(invPres) : null;

        // Chip informativo: Presentación Despacho
        let despChipHtml = '';
        if (p.despacho_nombre) {
            const despDetalle = [p.despacho_nombre, p.despacho_unidad ? p.despacho_unidad : null, p.despacho_cant ? p.despacho_cant : null]
                .filter(Boolean).join(' · ');
            despChipHtml = `<div class="info-pres-despacho"><i class="bi bi-truck me-1"></i>${despDetalle}</div>`;
        }

        // Valor inicial en Unidades de Control (Fórmula: Unidades + Presentación * Despacho_Factor)
        const invUnidNum = invUnid !== '' ? parseFloat(invUnid) : 0;
        const totalControl = invUnidNum + (invPresNum !== null ? invPresNum * despFactor : 0);

        const calcControl = `
            <span class="despacho-val">${fmt(totalControl)}</span>
            <div class="despacho-unit-label" style="opacity:0.8; font-weight:500;">${p.unidad ?? ''}</div>
        `;

        tbody.append(`
            <tr data-id="${idPP}" data-cat="${cat}"
                data-smax="${p.stock_max_final ?? ''}"
                data-cant-pres="${p.cant_pres || 1}"
                data-despacho-factor="${despFactor}"
                data-despacho-unidad="${p.despacho_unidad ?? ''}">
                <td class="text-start small">
                    <div class="fw-bold text-pitaya">${p.Nombre}</div>
                    <div class="text-muted small">
                        ${p.presentacion ? p.presentacion : '<em class="text-muted" style="font-size:.75rem;opacity:.6;">Sin presentación</em>'}
                        <span class="ms-1">(${cat})</span>
                    </div>
                </td>
                <td><input type="number" class="form-control form-control-sm input-inv-unidades" value="${invUnid}" ${readonlyAttr} step="0.01"></td>
                <td>
                    <input type="number" class="form-control form-control-sm input-inv-pres" value="${invPres}" ${readonlyAttr} step="0.01">
                    ${despChipHtml}
                </td>
                <td class="col-inv-despacho">${calcControl}</td>
                <td class="bg-light">${fmt(p._stock_min)}</td>
                <td class="bg-light">${sMaxHtml}</td>
                <td class="col-sug">${pedidoHtml}</td>
                <td class="bg-highlight-p1">${p.p1 !== null ? fmt(p.p1) : '—'}</td>
                <td class="bg-highlight-p2">${p.p2 !== null ? fmt(p.p2) : '—'}</td>
                <td class="text-muted">—</td>
            </tr>
        `);
    });

    // Recalcular pedido en tiempo real al editar inventario
    $('#tbodyInventario').off('input').on('input', '.input-inv-pres, .input-inv-unidades', function (e) {
        recalcularFila($(this).closest('tr'), e.target, res.porcentajes);
    });
}

/* ── recalcular una fila al cambiar inventario ────────────── */
function recalcularFila(tr, target, porcentajes) {
    const sMax = parseFloat(tr.data('smax')) || 0;
    const cantPPFactor = parseFloat(tr.data('cant-pres')) || 1;
    const cat = tr.data('cat');

    let invPres = parseFloat(tr.find('.input-inv-pres').val()) || 0;

    // Las columnas son independientes, no se calcula una desde la otra.
    // Solo leemos el valor actual de la presentación para el cálculo del pedido.

    const pedido = Math.max(0, sMax - invPres);

    // Desglose P1 / P2
    const pctCong = parseFloat(porcentajes.porcentaje_congelados) || 0;
    const pctFresc = parseFloat(porcentajes.porcentaje_frescos) || 0;
    let p1 = 0, p2 = 0;
    if (['B', 'D', 'F'].includes(cat)) {
        p1 = pedido * pctCong; p2 = pedido - p1;
    } else if (['A', 'C'].includes(cat)) {
        p1 = pedido * pctFresc; p2 = pedido - p1;
    } else {
        p1 = pedido;
    }

    const pedidoHtml = pedido <= 0
        ? '<span class="badge bg-success">No pedir</span>'
        : `<strong class="text-primary">${fmt(pedido)}</strong>`;

    tr.find('.col-sug').html(pedidoHtml);
    tr.find('.bg-highlight-p1').text(fmt(p1));
    tr.find('.bg-highlight-p2').text(fmt(p2));

    // Recalcular columna En presentación Unidades de Control
    // Fórmula: En Unidades sueltas + (Presentación * Factor de Despacho)
    const invUnid = parseFloat(tr.find('.input-inv-unidades').val()) || 0;
    const despFactor = parseFloat(tr.data('despacho-factor')) || 0;
    
    // Si no hay factor de despacho, el factor es 1 (misma unidad)
    const factorEfectivo = despFactor > 0 ? despFactor : 1;
    const totalControl = invUnid + (invPres * factorEfectivo);
    
    tr.find('.despacho-val').text(fmt(totalControl));
}

/* ── guardar inventario ───────────────────────────────────── */
function guardarInventario() {
    const sucursal = $('#filtroSucursal').val();
    const semInv = $('#filtroSemanaInv').val();
    const items = [];

    $('#tbodyInventario tr').each(function () {
        const tr = $(this);
        items.push({
            id_producto_presentacion: tr.data('id'),
            cantidad_unidades: parseFloat(tr.find('.input-inv-unidades').val()) || 0,
            cantidad_presentacion: parseFloat(tr.find('.input-inv-pres').val()) || 0
        });
    });

    Swal.fire({
        title: '¿Guardar Inventario?',
        text: `Se registrará el inventario para la semana ${semInv}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.ajax({
            url: 'ajax/inventario_save.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ cod_sucursal: sucursal, semana_inv: semInv, items }),
            success: function (res) {
                if (res.ok) Swal.fire('Guardado', res.msg, 'success');
                else Swal.fire('Error', res.msg, 'error');
            }
        });
    });
}
