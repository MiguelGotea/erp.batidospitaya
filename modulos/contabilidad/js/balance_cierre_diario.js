/**
 * balance_cierre_diario.js
 * Lógica del módulo Balance Cierre Diario
 */

// ── Estado global ────────────────────────────────────────────
let cierresDelDia = [];
let cierreActivo  = null;
let datosCierre   = null; // objeto completo del cierre seleccionado

// ── Inicialización ───────────────────────────────────────────
$(document).ready(function () {
    cargarSucursales();

    // Tecla Enter en filtros → buscar
    $('#filtroFecha, #filtroSucursal').on('keydown', function (e) {
        if (e.key === 'Enter') buscarCierres();
    });
});

// ── Carga el selector de sucursales ─────────────────────────
function cargarSucursales() {
    $.ajax({
        url: 'ajax/bcd_get_sucursales.php',
        type: 'GET',
        success: function (resp) {
            if (resp.success && resp.datos.length > 0) {
                const sel = $('#filtroSucursal');
                resp.datos.forEach(function (s) {
                    sel.append(`<option value="${s.codigo}">${s.nombre}</option>`);
                });
            }
        }
    });
}

// ── Busca los cierres del día ────────────────────────────────
function buscarCierres() {
    const fecha    = $('#filtroFecha').val();
    const sucursal = $('#filtroSucursal').val();

    if (!fecha || !sucursal) {
        mostrarAlerta('Seleccioná una fecha y una sucursal antes de buscar.', 'warning');
        return;
    }

    // Reset UI
    $('#bcdEmptyState').hide();
    $('#bcdNoResults').hide();
    $('#bcdLayout').hide();
    $('#bcdDetail').find('#contenidoDetalle').hide();
    $('#bcdDetail').find('#placeholderDetalle').show();
    $('#listaCierres').html(spinnerHTML('Cargando cierres...'));

    $.ajax({
        url: 'ajax/bcd_get_cierres_dia.php',
        type: 'POST',
        dataType: 'json',
        data: { fecha, sucursal },
        success: function (resp) {
            if (!resp.success) {
                mostrarAlerta(resp.message || 'Error al consultar cierres.', 'danger');
                return;
            }

            cierresDelDia = resp.datos;

            if (cierresDelDia.length === 0) {
                $('#bcdNoResults').show();
                return;
            }

            $('#badgeResultados').text(cierresDelDia.length + ' cierre(s)').show();
            $('#sidebarCount').text(cierresDelDia.length);
            renderizarSidebar();
            $('#bcdLayout').show();

            // Auto-seleccionar primer cierre
            if (cierresDelDia.length > 0) {
                seleccionarCierre(0);
            }
        },
        error: function () {
            mostrarAlerta('Error de comunicación con el servidor.', 'danger');
        }
    });
}

// ── Renderiza la lista lateral ───────────────────────────────
function renderizarSidebar() {
    const list = $('#listaCierres');
    list.empty();

    cierresDelDia.forEach(function (c, idx) {
        const hi = c.HoraInicial ? formatHora(c.HoraInicial) : '--:--';
        const hf = c.HoraFinal   ? formatHora(c.HoraFinal)   : '--:--';

        list.append(`
            <div class="bcd-cierre-item" id="item-cierre-${idx}" onclick="seleccionarCierre(${idx})">
                <span class="ci-code"><i class="bi bi-receipt me-1"></i>Cierre #${c.CodigoCierre}</span>
                <span class="ci-horario">
                    <i class="bi bi-clock"></i>
                    ${hi} — ${hf}
                </span>
            </div>
        `);
    });
}

// ── Carga el detalle de un cierre ────────────────────────────
function seleccionarCierre(idx) {
    // Marcar activo en sidebar
    $('.bcd-cierre-item').removeClass('active');
    $(`#item-cierre-${idx}`).addClass('active');

    cierreActivo = cierresDelDia[idx];

    // Mostrar loading en detalle
    $('#placeholderDetalle').hide();
    $('#contenidoDetalle').hide();
    $('#bcdDetail').prepend(spinnerHTML('Calculando balance...', 'bcd-loading-overlay'));

    const fecha    = $('#filtroFecha').val();
    const sucursal = $('#filtroSucursal').val();

    $.ajax({
        url: 'ajax/bcd_get_detalle_cierre.php',
        type: 'POST',
        dataType: 'json',
        data: {
            fecha,
            sucursal,
            cod_cierre:     cierreActivo.CodigoCierre,
            hora_final:     cierreActivo.HoraFinal,
            cod_operario:   cierreActivo.CodOperario,
            mf_cor:         cierreActivo.MFCor,
            mf_dol:         cierreActivo.MFDol,
            total_pos:      cierreActivo.TotalPOS,
            total_transfer: cierreActivo.TotalTransferencia,
            total_py:       cierreActivo.TotalPedidosYa,
            observaciones:  cierreActivo.Observaciones
        },
        success: function (resp) {
            $('.bcd-loading-overlay').remove();
            if (!resp.success) {
                mostrarAlerta(resp.message || 'Error al cargar detalle.', 'danger');
                return;
            }
            datosCierre = resp.datos;
            renderizarDetalle();
        },
        error: function () {
            $('.bcd-loading-overlay').remove();
            mostrarAlerta('Error de comunicación al cargar el detalle.', 'danger');
        }
    });
}

// ── Renderiza el panel de detalle ────────────────────────────
function renderizarDetalle() {
    const d = datosCierre;

    // Encabezado
    $('#detCodigoCierre').text(d.cod_cierre);
    $('#detCajero').text(d.cajero);
    $('#detFecha').text(formatFecha(d.fecha));
    $('#detTurno').text(`${formatHora(d.hora_inicial)} — ${formatHora(d.hora_final)}`);

    // ── Balance de Ventas ────
    // POS
    setVentaFila('Pos', d.total_pos_fisico, d.pos_sistema);
    // Transferencia
    setVentaFila('Trans', d.total_transfer_fisico, d.transfer_sistema);
    // Pedidos Ya
    setVentaFila('PY', d.total_py_fisico, d.py_sistema);
    // Efectivo
    setVentaFila('Efec', d.efectivo_fisico, d.efectivo_sistema);

    // Totales ventas
    const totalFisico  = d.total_pos_fisico + d.total_transfer_fisico + d.total_py_fisico + d.efectivo_fisico;
    const totalSistema = d.pos_sistema + d.transfer_sistema + d.py_sistema + d.efectivo_sistema;
    const totalDif     = totalSistema - totalFisico;
    $('#vfTotalFisico').text(fmt(totalFisico));
    $('#vfTotalSistema').text(fmt(totalSistema));
    setDiferencia('#vfTotalDif', totalDif);

    // ── Balance de Efectivo ──
    $('#efCajaInicial').text(fmt(d.caja_inicial));
    $('#efVentasEfectivo').text(fmt(d.efectivo_sistema));
    $('#efAligeramientos').text(fmt(d.aligeramientos));
    $('#efCompras').text(fmt(d.compras_caja));

    const efectivoAEntregar = d.caja_inicial + d.efectivo_sistema - d.aligeramientos - d.compras_caja;
    const totalEntregado    = d.conteo_caja;
    const resultadoEfectivo = totalEntregado - efectivoAEntregar;

    $('#efConteoCaja').text(fmt(d.conteo_caja));
    $('#efAEntregar').text(fmt(efectivoAEntregar));
    $('#efTotalEntregado').text(fmt(totalEntregado));

    // Resultado final
    const rb = $('#bcdResultBox');
    rb.removeClass('sobrante faltante cero');
    if (resultadoEfectivo > 0.005) {
        rb.addClass('sobrante');
        $('#bcdResultLabel').text('EFECTIVO SOBRANTE');
        $('#bcdResultValue').text(fmt(resultadoEfectivo));
    } else if (resultadoEfectivo < -0.005) {
        rb.addClass('faltante');
        $('#bcdResultLabel').text('EFECTIVO FALTANTE');
        $('#bcdResultValue').text(fmt(Math.abs(resultadoEfectivo)));
    } else {
        rb.addClass('cero');
        $('#bcdResultLabel').text('BALANCE EXACTO');
        $('#bcdResultValue').text('C$ 0.00');
    }

    // Observaciones
    if (d.observaciones && d.observaciones.trim() !== '') {
        $('#bcdObsText').text(d.observaciones);
        $('#bcdObsBlock').show();
    } else {
        $('#bcdObsBlock').hide();
    }

    $('#contenidoDetalle').show();
}

// ── Helper: renderiza una fila de ventas ─────────────────────
function setVentaFila(prefix, fisico, sistema) {
    const dif = sistema - fisico;
    $(`#vf${prefix}Fisico`).text(fmt(fisico));
    $(`#vf${prefix}Sistema`).text(fmt(sistema));
    setDiferencia(`#vf${prefix}Dif`, dif);
}

function setDiferencia(sel, dif) {
    const el = $(sel);
    if (Math.abs(dif) < 0.005) {
        el.html('<span class="bcd-dif-cero">0.0</span>');
    } else if (dif > 0) {
        el.html(`<span class="bcd-dif-sobrante"><i class="bi bi-arrow-up-circle-fill me-1"></i>SOBRANTE ${fmt(dif)}</span>`);
    } else {
        el.html(`<span class="bcd-dif-faltante"><i class="bi bi-arrow-down-circle-fill me-1"></i>FALTANTE ${fmt(Math.abs(dif))}</span>`);
    }
}

// ── Modal: detalle de ventas por modalidad ───────────────────
function abrirDetalleVentas(modalidad) {
    if (!cierreActivo) return;

    const labels = {
        'POS': 'POS Banco',
        'TRANSFERENCIA': 'Transferencias',
        'PEDIDOSYA': 'Pedidos Ya',
        'EFECTIVO': 'Efectivo'
    };

    $('#modalDetalleVentasLabel').text(`Detalle de Ventas — ${labels[modalidad] || modalidad}`);
    $('#modalDetalleVentasSubtitle').text(`Modalidad: ${modalidad}`);
    $('#tbodyDetalleVentas').html('<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success"></div> Cargando...</td></tr>');
    $('#modalTotalTx').text('...');
    $('#modalTotalMonto').text('...');

    const modal = new bootstrap.Modal(document.getElementById('modalDetalleVentas'));
    modal.show();

    $.ajax({
        url: 'ajax/bcd_get_detalle_ventas.php',
        type: 'POST',
        dataType: 'json',
        data: {
            fecha:      $('#filtroFecha').val(),
            sucursal:   $('#filtroSucursal').val(),
            modalidad:  modalidad,
            hora_final: cierreActivo.HoraFinal
        },
        success: function (resp) {
            if (!resp.success) {
                $('#tbodyDetalleVentas').html(`<tr><td colspan="6" class="text-center text-danger py-3">${resp.message}</td></tr>`);
                return;
            }

            const rows = resp.datos;
            let html = '';

            rows.forEach(function (r) {
                const anulado = r.Anulado == 1
                    ? '<span class="badge bg-danger">Anulado</span>'
                    : '<span class="badge bg-success">OK</span>';

                html += `
                    <tr class="${r.Anulado == 1 ? 'table-danger' : ''}">
                        <td>${r.Hora || ''}</td>
                        <td>${r.CodPedido || ''}</td>
                        <td>${r.DBBatidos_Nombre || ''}</td>
                        <td>${r.NombreGrupo || ''}</td>
                        <td class="text-end fw-semibold">${fmt(parseFloat(r.Precio) || 0)}</td>
                        <td class="text-center">${anulado}</td>
                    </tr>
                `;
            });

            if (rows.length === 0) {
                html = '<tr><td colspan="6" class="text-center text-muted py-4">Sin registros para esta modalidad</td></tr>';
            }

            $('#tbodyDetalleVentas').html(html);
            // Total correcto: suma de MontoFactura deduplicado por CodPedido (Anulado=0)
            $('#modalTotalTx').text(resp.total_pedidos + ' pedido(s)');
            $('#modalTotalMonto').text(fmt(resp.total_factura));
        },
        error: function () {
            $('#tbodyDetalleVentas').html('<tr><td colspan="6" class="text-center text-danger py-3">Error de conexión</td></tr>');
        }
    });
}

// ── Modal: detalle de compras de caja ────────────────────────
function abrirDetalleCompras() {
    if (!cierreActivo) return;

    $('#modalComprasSubtitle').text(`Fecha: ${formatFecha($('#filtroFecha').val())}`);
    $('#tbodyDetalleCompras').html('<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success"></div> Cargando...</td></tr>');
    $('#modalTotalCompras').text('...');
    $('#modalTotalCostoCompras').text('...');

    const modal = new bootstrap.Modal(document.getElementById('modalDetalleCompras'));
    modal.show();

    $.ajax({
        url: 'ajax/bcd_get_detalle_compras.php',
        type: 'POST',
        dataType: 'json',
        data: {
            fecha:    $('#filtroFecha').val(),
            sucursal: $('#filtroSucursal').val()
        },
        success: function (resp) {
            if (!resp.success) {
                $('#tbodyDetalleCompras').html(`<tr><td colspan="6" class="text-center text-danger py-3">${resp.message}</td></tr>`);
                return;
            }

            const rows = resp.datos;
            let totalCosto = 0;
            let html = '';

            rows.forEach(function (r) {
                html += `
                    <tr>
                        <td>${r.NumeroFactura || '—'}</td>
                        <td>${r.CodProveedor || '—'}</td>
                        <td>${r.Destino || '—'}</td>
                        <td>${r.Cantidad || '—'}</td>
                        <td class="text-end fw-semibold">${fmt(parseFloat(r.CostoTotal) || 0)}</td>
                        <td>${r.Observaciones || ''}</td>
                    </tr>
                `;
                totalCosto += parseFloat(r.CostoTotal) || 0;
            });

            if (rows.length === 0) {
                html = '<tr><td colspan="6" class="text-center text-muted py-4">Sin compras de caja en esta fecha</td></tr>';
            }

            $('#tbodyDetalleCompras').html(html);
            $('#modalTotalCompras').text(rows.length);
            $('#modalTotalCostoCompras').text(fmt(totalCosto));
        },
        error: function () {
            $('#tbodyDetalleCompras').html('<tr><td colspan="6" class="text-center text-danger py-3">Error de conexión</td></tr>');
        }
    });
}

// ── Utilidades ───────────────────────────────────────────────
function fmt(val) {
    const n = parseFloat(val) || 0;
    return 'C$ ' + n.toLocaleString('es-NI', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

function formatHora(h) {
    if (!h) return '--:--';
    return h.substring(0, 5); // "HH:MM"
}

function formatFecha(f) {
    if (!f) return '—';
    const [y, m, d] = f.split('-');
    return `${d}/${m}/${y}`;
}

function spinnerHTML(msg, cls) {
    cls = cls || '';
    return `<div class="bcd-loading ${cls}"><div class="spinner-border"></div><span>${msg}</span></div>`;
}

function mostrarAlerta(msg, tipo) {
    const id = 'bcd-alert-' + Date.now();
    const html = `
        <div id="${id}" class="alert alert-${tipo} alert-dismissible fade show py-2 px-3 small" role="alert">
            ${msg}
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>`;
    $('.container-fluid.p-3').prepend(html);
    setTimeout(() => $(`#${id}`).alert('close'), 5000);
}
