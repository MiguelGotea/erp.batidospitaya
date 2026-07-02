/* ============================================================
   JS: Inventario Semanal — solo renderizado, los cálculos
       son 100% del backend (inventario_get_data.php)
   ============================================================ */
let semanaActual = 0;
let filasModificadas = new Set();

$(document).ready(function () {
    cargarSucursales();
    establecerSemanasDefecto();

    // Botón Cargar: deshabilitado por defecto, se habilita al cambiar filtros
    $('#filtroSucursal, #filtroSemanaInv').on('change input', function () {
        $('#btnCalcular').prop('disabled', false);
    });

    $('#btnCalcular').on('click', cargarDatosInventario);
    $('#btnGuardarInventario').on('click', guardarInventario);
});

/* ── semana por defecto ───────────────────────────────────── */
function establecerSemanasDefecto() {
    $.getJSON('ajax/get_current_week.php', function (res) {
        if (!res.ok) return;
        semanaActual = parseInt(res.semana);
        $('#filtroSemanaInv').val(semanaActual);
        // El cambio de valor por JS no dispara 'input', así que habilitamos manualmente
        $('#btnCalcular').prop('disabled', false);
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

/* ── cargar datos ─────────────────────────────────────────── */
function cargarDatosInventario() {
    const sucursal = $('#filtroSucursal').val();
    const semInv = parseInt($('#filtroSemanaInv').val());

    const semDesde = semInv - 5;
    const semHasta = semInv - 1;

    if (!sucursal || !semInv) {
        Swal.fire('Atención', 'Seleccione sucursal y semana de inventario.', 'warning');
        return;
    }

    // Deshabilitar el botón mientras carga (indica que ya se aplicó este filtro)
    $('#btnCalcular').prop('disabled', true);
    $('#loader').show();
    $('#tablaInventarioContainer').hide();
    $('#btnGuardarInventario').hide();

    $.ajax({
        url: 'ajax/inventario_get_data.php',
        method: 'GET',
        data: { cod_sucursal: sucursal, semana_inv: semInv, semana_desde: semDesde, semana_hasta: semHasta },
        success: function (res) {
            $('#loader').hide();
            if (!res.ok) {
                // Re-habilitar para que pueda reintentar
                $('#btnCalcular').prop('disabled', false);
                return Swal.fire('Error', res.msg, 'error');
            }

            // Resetear seguimiento de modificaciones
            filasModificadas = new Set();

            renderizarTabla(res, semInv);
            $('#tablaInventarioContainer').show();

            // Solo mostramos botón guardar si la semana es actual o futura
            if (semInv >= semanaActual) {
                $('#btnGuardarInventario').show().prop('disabled', true);
            } else {
                $('#btnGuardarInventario').hide();
            }
            if (res.rango_fechas_inv) {
                $('#labelRangoFechas').text(`Del ${res.rango_fechas_inv.fecha_inicio} al ${res.rango_fechas_inv.fecha_fin}`);
            }
        },
        error: function () {
            $('#loader').hide();
            // Re-habilitar para que pueda reintentar
            $('#btnCalcular').prop('disabled', false);
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
                    <td colspan="5" class="fw-bold py-2 ps-3" style="background-color: #e9ecef; color: #495057; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px;">
                        ${labelGrupo}
                    </td>
                </tr>
            `);
            ultimoGrupo = grupoId;
        }

        // Inventario actual guardado en BD
        const invPres = p._inv_pres !== null && p._inv_pres !== undefined ? p._inv_pres : '';
        const invUnid = p._inv_unidades !== null && p._inv_unidades !== undefined ? p._inv_unidades : '';

        const readonlyAttr = esSoloLectura ? 'readonly' : '';
        const despFactor = p.despacho_factor ? parseFloat(p.despacho_factor) : 0;

        tbody.append(`
            <tr data-id="${idPP}" data-cat="${cat}"
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
            </tr>
        `);
    }); // end forEach productos

    // Rastrear modificaciones para habilitar el botón Guardar
    $('#tbodyInventario').off('input').on('input', '.input-inv-pres, .input-inv-unidades', function () {
        const idFila = $(this).closest('tr').data('id');
        if (idFila !== undefined) filasModificadas.add(idFila);
        actualizarEstadoGuardar();
    });
}

/* ── habilitar / deshabilitar botón guardar ───────────────── */
function actualizarEstadoGuardar() {
    const hayModificaciones = filasModificadas.size > 0;
    $('#btnGuardarInventario').prop('disabled', !hayModificaciones);
}

/* ── guardar inventario ───────────────────────────────────── */
function guardarInventario() {
    const sucursal = $('#filtroSucursal').val();
    const semInv = $('#filtroSemanaInv').val();
    const items = [];

    $('#tbodyInventario tr').each(function () {
        const tr = $(this);
        const idPP = tr.data('id');
        if (!idPP || !filasModificadas.has(idPP)) return; // solo modificados!
        items.push({
            id_producto_presentacion: idPP,
            cantidad_unidades: parseFloat(tr.find('.input-inv-unidades').val()) || 0,
            cantidad_presentacion: parseFloat(tr.find('.input-inv-pres').val()) || 0
        });
    });

    if (items.length === 0) {
        Swal.fire('Atención', 'No hay modificaciones para guardar.', 'info');
        return;
    }

    Swal.fire({
        title: '¿Guardar Inventario?',
        text: `Se registrará el inventario para la semana ${semInv}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0E544C',
        cancelButtonColor: '#6c757d'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.ajax({
            url: 'ajax/inventario_save.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ cod_sucursal: sucursal, semana_inv: semInv, items }),
            success: function (res) {
                if (res.ok) {
                    // Resetear modificaciones y deshabilitar el botón guardar
                    filasModificadas = new Set();
                    actualizarEstadoGuardar();
                    Swal.fire('Guardado', res.msg, 'success');
                } else {
                    Swal.fire('Error', res.msg, 'error');
                }
            }
        });
    });
}
