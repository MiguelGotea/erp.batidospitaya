/**
 * balance_cierre_diario.js
 * Lógica del módulo Balance Cierre Diario
 */

// ── Estado global ────────────────────────────────────────────
let cierresDelDia  = [];   // todos los cierres crudos del servidor
let gruposCierres  = [];   // array de grupos: { final, precierres[] }
let cierreActivo   = null; // cierre (raw) actualmente mostrado en detalle
let datosCierre    = null; // objeto completo del cierre seleccionado

// ── Agrupa cierres por ventana de 30 min en HoraInicial ─────
// Regla: si la diferencia de HoraInicial entre dos cierres es ≤ 30 min,
// se consideran del mismo turno. El de mayor CodigoCierre es el Cierre Final
// y los demás son Precierres.
function agruparCierres(lista) {
    // Ordenar por CodigoCierre ASC para procesar en orden
    const sorted = lista.slice().sort((a, b) => a.CodigoCierre - b.CodigoCierre);

    const grupos = [];

    sorted.forEach(function (c) {
        const minC = horaAMinutos(c.HoraInicial);

        // Buscar un grupo existente cuya HoraInicial (del primer elemento) esté ≤ 30 min de distancia
        let grupoEncontrado = null;
        for (let g of grupos) {
            const minRef = horaAMinutos(g.todos[0].HoraInicial);
            if (Math.abs(minC - minRef) <= 30) {
                grupoEncontrado = g;
                break;
            }
        }

        if (grupoEncontrado) {
            grupoEncontrado.todos.push(c);
        } else {
            grupos.push({ todos: [c] });
        }
    });

    // Para cada grupo, ordenar por CodigoCierre DESC → el primero es el Final
    return grupos.map(function (g) {
        const ordenados = g.todos.slice().sort((a, b) => b.CodigoCierre - a.CodigoCierre);
        return {
            final:      ordenados[0],                    // mayor CodigoCierre
            precierres: ordenados.slice(1)               // los demás (orden desc)
        };
    }).sort((a, b) => a.final.CodigoCierre - b.final.CodigoCierre); // sidebar: cronológico
}

// Convierte "HH:MM:SS" o "HH:MM" a minutos desde medianoche
function horaAMinutos(h) {
    if (!h) return 0;
    const parts = h.split(':');
    return parseInt(parts[0] || 0) * 60 + parseInt(parts[1] || 0);
}

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

            // Agrupar en cierres finales + precierres
            gruposCierres = agruparCierres(cierresDelDia);

            const totalFinal = gruposCierres.length;
            const totalPrec  = cierresDelDia.length - totalFinal;
            let badgeText = totalFinal + ' cierre(s) final(es)';
            if (totalPrec > 0) badgeText += ' · ' + totalPrec + ' precierre(s)';

            $('#badgeResultados').text(badgeText).show();
            $('#sidebarCount').text(totalFinal);
            renderizarSidebar();
            $('#bcdLayout').show();

            // Auto-seleccionar primer grupo
            if (gruposCierres.length > 0) {
                seleccionarGrupo(0);
            }
        },
        error: function () {
            mostrarAlerta('Error de comunicación con el servidor.', 'danger');
        }
    });
}

// ── Renderiza la lista lateral (solo cierres finales) ────────
function renderizarSidebar() {
    const list = $('#listaCierres');
    list.empty();

    gruposCierres.forEach(function (g, idx) {
        const c  = g.final;
        const hi = c.HoraInicial ? formatHora(c.HoraInicial) : '--:--';
        const hf = c.HoraFinal   ? formatHora(c.HoraFinal)   : '--:--';
        const tienePrec = g.precierres.length > 0;

        list.append(`
            <div class="bcd-cierre-item" id="item-cierre-${idx}" onclick="seleccionarGrupo(${idx})">
                <span class="ci-code">
                    <i class="bi bi-receipt me-1"></i>Cierre #${c.CodigoCierre}
                    ${tienePrec ? `<span class="ci-badge-prec" title="${g.precierres.length} precierre(s)">${g.precierres.length}</span>` : ''}
                </span>
                <span class="ci-horario">
                    <i class="bi bi-clock"></i>
                    ${hi} — ${hf}
                </span>
            </div>
        `);
    });
}

// ── Selecciona un grupo (cierre final) desde el sidebar ──────
function seleccionarGrupo(idxGrupo) {
    $('.bcd-cierre-item').removeClass('active');
    $(`#item-cierre-${idxGrupo}`).addClass('active');

    const grupo = gruposCierres[idxGrupo];
    // Cargar el cierre final del grupo por defecto
    cargarCierre(grupo.final, idxGrupo);
}

// ── Renderiza las pestañas Cierre Final / Precierre ──────────
function renderizarTabsPrecierre(grupo, cierreSeleccionado) {
    // Eliminar tabs previos
    $('#bcdTabsPrecierre').remove();

    // Solo renderizar si hay al menos 1 precierre
    if (grupo.precierres.length === 0) return;

    const tabsContainer = $('<div id="bcdTabsPrecierre" class="bcd-tabs-precierre"></div>');

    // Tab del cierre final (primer tab)
    const esFinal = cierreSeleccionado.CodigoCierre === grupo.final.CodigoCierre;
    const tabFinal = $(`
        <button class="bcd-tab-item ${esFinal ? 'active' : ''}" 
                onclick="cargarCierre(gruposCierres[getIdxGrupoActivo()].final, getIdxGrupoActivo())">
            <i class="bi bi-patch-check-fill me-1"></i>
            Cierre Final <span class="bcd-tab-code">#${grupo.final.CodigoCierre}</span>
        </button>
    `);
    tabsContainer.append(tabFinal);

    // Tabs de precierres (desc por CodigoCierre)
    grupo.precierres.forEach(function (p) {
        const esActivo = cierreSeleccionado.CodigoCierre === p.CodigoCierre;
        const tabPrec = $(`
            <button class="bcd-tab-item bcd-tab-precierre ${esActivo ? 'active' : ''}" 
                    onclick="cargarCierre(gruposCierres[getIdxGrupoActivo()].precierres.find(x => x.CodigoCierre === ${p.CodigoCierre}), getIdxGrupoActivo())">
                <i class="bi bi-clock-history me-1"></i>
                Precierre <span class="bcd-tab-code">#${p.CodigoCierre}</span>
            </button>
        `);
        tabsContainer.append(tabPrec);
    });

    // Insertar antes del contenidoDetalle
    $('#contenidoDetalle').before(tabsContainer);
}

// Helper: obtiene el índice del grupo activo en el sidebar
function getIdxGrupoActivo() {
    let idx = 0;
    $('.bcd-cierre-item').each(function (i) {
        if ($(this).hasClass('active')) { idx = i; return false; }
    });
    return idx;
}

// ── Carga el detalle de un cierre concreto ───────────────────
function cargarCierre(cierre, idxGrupo) {
    cierreActivo = cierre;

    // Redibujar pestañas con la nueva selección
    renderizarTabsPrecierre(gruposCierres[idxGrupo], cierre);

    // Mostrar loading
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
            cod_cierre:     cierre.CodigoCierre,
            hora_final:     cierre.HoraFinal,
            cod_operario:   cierre.CodOperario,
            mf_cor:         cierre.MFCor,
            mf_dol:         cierre.MFDol,
            total_pos:      cierre.TotalPOS,
            total_transfer: cierre.TotalTransferencia,
            total_py:       cierre.TotalPedidosYa,
            observaciones:  cierre.Observaciones
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
    const totalDif     = totalFisico - totalSistema;
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
    const dif = fisico - sistema;
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
