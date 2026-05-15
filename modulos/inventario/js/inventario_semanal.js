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
        // Default: corte = semana anterior
        $('#filtroSemanaCortePronostico').val(semanaActual - 1);
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
    const semInv   = parseInt($('#filtroSemanaInv').val());
    const semCorte = parseInt($('#filtroSemanaCortePronostico').val()) || (semInv - 1);

    // El rango ahora es automático: 5 semanas hacia atrás desde la semana de inventario
    const semDesde = semInv - 5;
    const semHasta = semInv - 1;

    if (!sucursal || !semInv) {
        Swal.fire('Atención', 'Seleccione sucursal y semana de inventario.', 'warning');
        return;
    }

    // Sincronizar corte con semana_inv si el usuario no lo editó aún
    if (!$('#filtroSemanaCortePronostico').val()) {
        $('#filtroSemanaCortePronostico').val(semInv - 1);
    }

    $('#loader').show();
    $('#tablaInventarioContainer').hide();
    $('#btnGuardarInventario').hide();

    $.ajax({
        url: 'ajax/inventario_get_data.php',
        method: 'GET',
        data: { cod_sucursal: sucursal, semana_inv: semInv, semana_desde: semDesde, semana_hasta: semHasta, semana_corte_pronostico: semCorte },
        success: function (res) {
            $('#loader').hide();
            if (!res.ok) return Swal.fire('Error', res.msg, 'error');

            // Actualizar label del corte en el encabezado de la tabla
            const corteUsado = res.semana_corte_pronostico || semCorte;
            $('#labelCortePronostico').text(`Corte: S${corteUsado}`);

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

    // Orden oficial de categorías
    const CAT_ORDER = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

    res.productos.sort((a, b) => {
        let iA = CAT_ORDER.indexOf(a.categoria_insumo); if (iA < 0) iA = 99;
        let iB = CAT_ORDER.indexOf(b.categoria_insumo); if (iB < 0) iB = 99;
        if (iA !== iB) return iA - iB;
        return a.Nombre.localeCompare(b.Nombre);
    });

    let ultimoGrupo = null;

    res.productos.forEach(p => {
        const idPP = p.id;
        const cat = p.categoria_insumo ?? '—';
        const nomCat = p.categoria_nombre ?? 'Sin Categoría';
        const grupoId = cat;

        // Insertar fila de encabezado de grupo si cambia
        if (grupoId !== ultimoGrupo) {
            const labelGrupo = cat !== '—' ? `${cat} — ${nomCat}` : nomCat;
            tbody.append(`
                <tr class="table-light">
                    <td colspan="12" class="fw-bold py-2 ps-3" style="background-color: #e9ecef; color: #495057; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px;">
                        ${labelGrupo}
                    </td>
                </tr>
            `);
            ultimoGrupo = grupoId;
        }

        // Inventario actual guardado en BD
        const invPres = p._inv_pres !== null && p._inv_pres !== undefined ? p._inv_pres : '';
        const invUnid = p._inv_unidades !== null && p._inv_unidades !== undefined ? p._inv_unidades : '';

        // Stock máximo final
        const sMaxHtml = p.stock_max_final !== null
            ? fmt(p.stock_max_final) + (p.es_ajustado ? ' <span class="badge bg-info text-dark" style="font-size:.65rem">Aj.</span>' : '')
            : '<span class="text-muted small">Sin config.</span>';

        // Pedido sugerido
        const pedidoHtml = p.pedido_sugerido !== null
            ? (p.pedido_sugerido <= 0
                ? '<span class="badge bg-success">No pedir</span>'
                : `<strong class="text-primary">${fmt(p.pedido_sugerido)}</strong>`)
            : '<span class="text-muted small">—</span>';

        const readonlyAttr = esSoloLectura ? 'readonly' : '';
        const despFactor = p.despacho_factor ? parseFloat(p.despacho_factor) : 0;
        const invPresNum = invPres !== '' ? parseFloat(invPres) : null;



        // Stock pronóstico (antes de stock mínimo)
        let pronHtml = '<span class="text-muted small">—</span>';
        if (p._stock_pronostico !== null && p._stock_pronostico !== undefined) {
            const pron = parseFloat(p._stock_pronostico);
            const sMin = parseFloat(p._stock_min) || 0;
            const sMax = parseFloat(p.stock_max_final) || 0;
            let colorClass = '';
            let icon       = '';
            if (sMin > 0 && pron < sMin) {
                colorClass = 'text-danger fw-bold';
                icon = '<i class="bi bi-exclamation-triangle-fill me-1" style="font-size:.7rem"></i>';
            } else if (sMin > 0 && pron < sMin * 1.2) {
                colorClass = 'text-warning fw-bold';
                icon = '<i class="bi bi-dash-circle-fill me-1" style="font-size:.7rem"></i>';
            } else {
                colorClass = 'text-success fw-bold';
                icon = '<i class="bi bi-check-circle-fill me-1" style="font-size:.7rem"></i>';
            }
            pronHtml = `<span class="${colorClass}">${icon}${fmt(pron)}</span>`;
        }

        tbody.append(`
            <tr data-id="${idPP}" data-cat="${cat}"
                data-smax="${p.stock_max_final ?? ''}"
                data-cant-pres="${p.cant_pres || 1}"
                data-despacho-factor="${despFactor}"
                data-despacho-unidad="${p.despacho_unidad ?? ''}">
                <td class="text-start small">
                    <span class="fw-bold text-pitaya">${p.Nombre}</span>
                    <span class="text-muted ms-1">${p.presentacion || ''}</span>
                </td>
                <td><input type="number" class="form-control form-control-sm input-inv-unidades" value="${invUnid}" ${readonlyAttr} step="0.01"></td>
                <td class="small text-muted">${p.presentacion || ''}</td>
                <td>
                    <input type="number" class="form-control form-control-sm input-inv-pres" value="${invPres}" ${readonlyAttr} step="0.01">
                </td>
                <td class="small text-muted">
                    ${p.despacho_nombre ? p.despacho_nombre : ''}
                </td>
                <td class="bg-light text-center" style="min-width:80px">${pronHtml}</td>
                <td class="bg-light">${fmt(p._stock_min)}</td>
                <td class="bg-light">${sMaxHtml}</td>
                <td class="col-sug">${pedidoHtml}</td>
                <td class="bg-highlight-p1">${p.p1 !== null ? fmt(p.p1) : '—'}</td>
                <td class="bg-highlight-p2">${p.p2 !== null ? fmt(p.p2) : '—'}</td>
                <td class="text-muted">—</td>
            </tr>
        `);
    }); // end forEach productos

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
