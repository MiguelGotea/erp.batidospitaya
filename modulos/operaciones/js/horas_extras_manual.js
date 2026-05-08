/* modulos/operaciones/js/horas_extras_manual.js */

// ─── Estado global ────────────────────────────────────────────────
let todosLosRegistros = [];  // cache completo de la última carga
let paginaActual = 1;
let registrosPorPag = 25;

// ─── Init ─────────────────────────────────────────────────────────
$(document).ready(function () {

    cargarDatos();

    // Autocompletado colaborador — barra de filtros
    $('#operario_search').on('input', function () {
        const q = $(this).val();
        if (q.length < 3) { $('#operarios-sugerencias').hide(); return; }
        $.get('/includes/buscar_operario.php', { nombre: q, sucursal: $('#sucursal').val() }, function (data) {
            let html = '';
            data.forEach(op => {
                html += `<button type="button" class="list-group-item list-group-item-action"
                    onclick="seleccionarOperario('${op.CodOperario}','${op.Nombre} ${op.Apellido}')">
                    ${op.Nombre} ${op.Apellido} <small class="text-muted">(${op.sucursal_nombre})</small>
                </button>`;
            });
            $('#operarios-sugerencias').html(html).show();
        }, 'json');
    });

    // Autocompletado colaborador — modal solicitud
    $('#sol_operario_search').on('input', function () {
        const q = $(this).val();
        if (q.length < 3) { $('#sol-sugerencias').hide(); return; }
        $.get('/includes/buscar_operario.php', { nombre: q, sucursal: $('#sucursal').val() }, function (data) {
            let html = '';
            data.forEach(op => {
                html += `<button type="button" class="list-group-item list-group-item-action"
                    onclick="seleccionarOperarioModal('${op.CodOperario}','${op.Nombre} ${op.Apellido}','${op.sucursal_codigo || ''}')">
                    ${op.Nombre} ${op.Apellido} <small class="text-muted">(${op.sucursal_nombre})</small>
                </button>`;
            });
            $('#sol-sugerencias').html(html).show();
        }, 'json');
    });

    // Ocultar sugerencias al clic fuera
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.position-relative').length) {
            $('#operarios-sugerencias').hide();
            $('#sol-sugerencias').hide();
        }
    });

    // Guardar solicitud
    $(document).on('submit', '#formSolicitud', function (e) {
        e.preventDefault();

        const codOp = $('#sol_cod_operario').val();
        if (!codOp) {
            alert('Por favor seleccione un colaborador.');
            $('#sol_operario_search').focus();
            return;
        }

        const formData = $(this).serialize() + '&action=guardar';
        $.post('ajax/horas_extras_manual_guardar.php', formData, function (res) {
            if (res.success) {
                bootstrap.Modal.getInstance($('#modalSolicitud')[0])?.hide();
                cargarDatos();
            } else {
                alert('Error: ' + res.message);
            }
        }, 'json');
    });

    // Procesar (aprobar/denegar)
    $(document).on('submit', '#formProcesar', function (e) {
        e.preventDefault();
        $.post('ajax/horas_extras_manual_guardar.php', $(this).serialize(), function (res) {
            if (res.success) {
                bootstrap.Modal.getInstance($('#modalProcesar')[0])?.hide();
                cargarDatos();
            } else {
                alert('Error: ' + res.message);
            }
        }, 'json');
    });
});

// ─── Selección de colaborador (filtros) ───────────────────────────
window.seleccionarOperario = function (id, nombre) {
    $('#operario_id').val(id);
    $('#operario_search').val(nombre);
    $('#operarios-sugerencias').hide();
    cargarDatos();
};

// ─── Selección colaborador en modal ──────────────────────────────
window.seleccionarOperarioModal = function (id, nombre, sucursal) {
    $('#sol_cod_operario').val(id);
    $('#sol_cod_sucursal').val(sucursal);
    $('#sol_operario_search').val('').hide();
    $('#sol-sugerencias').hide();
    $('#sol_operario_badge').text(nombre);
    $('#sol_operario_seleccionado').show();
};

window.limpiarOperarioModal = function () {
    $('#sol_cod_operario').val('');
    $('#sol_cod_sucursal').val('');
    $('#sol_operario_search').val('').show().focus();
    $('#sol_operario_seleccionado').hide();
    $('#sol_operario_badge').text('');
};

// ─── Lógica de restricción de fechas para líderes ────────────────
function getRestrictedDateRange() {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth(); // 0-indexed
    const day = now.getDate();

    let minDate, maxDate;

    if (day >= 13 && day <= 26) {
        // Rango 13-26 del mes actual
        minDate = new Date(year, month, 13);
        maxDate = new Date(year, month, 26);
    } else {
        // Rango 27-12 (cruza mes)
        if (day >= 27) {
            // Desde el 27 del mes actual al 12 del mes siguiente
            minDate = new Date(year, month, 27);
            maxDate = new Date(year, month + 1, 12);
        } else {
            // day <= 12: Desde el 27 del mes anterior al 12 del mes actual
            minDate = new Date(year, month - 1, 27);
            maxDate = new Date(year, month, 12);
        }
    }

    const formatDate = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const dayNum = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${dayNum}`;
    };

    return {
        min: formatDate(minDate),
        max: formatDate(maxDate)
    };
}

// ─── Abrir modal nueva solicitud ──────────────────────────────────
window.abrirNuevaSolicitud = function () {
    $('#formSolicitud')[0].reset();
    $('#sol_id').val('');
    $('#sol_cod_operario').val('');
    $('#sol_cod_sucursal').val('');
    $('#sol_operario_search').val('').show();
    $('#sol_operario_seleccionado').hide();
    $('#sol_operario_badge').text('');
    const localNow = new Date();
    const dateString = localNow.getFullYear() + '-' + String(localNow.getMonth() + 1).padStart(2, '0') + '-' + String(localNow.getDate()).padStart(2, '0');
    $('#sol_fecha').val(dateString);

    // Aplicar restricciones para usuarios restringidos (antes líderes)
    if (window.esRestringido) {
        const range = getRestrictedDateRange();
        $('#sol_fecha').attr('min', range.min).attr('max', range.max);
        
        // Ajustar fecha inicial si está fuera del rango
        const currentVal = $('#sol_fecha').val();
        if (currentVal < range.min) $('#sol_fecha').val(range.min);
        if (currentVal > range.max) $('#sol_fecha').val(range.max);
    } else {
        $('#sol_fecha').removeAttr('min').removeAttr('max');
    }

    $('#modalSolicitudTitulo').text('Solicitar Horas Extras');
    new bootstrap.Modal($('#modalSolicitud')[0]).show();
};

// ─── Cargar datos ─────────────────────────────────────────────────
window.cargarDatos = function () {
    const filters = {
        sucursal: $('#sucursal').val(),
        desde: $('#desde').val(),
        hasta: $('#hasta').val(),
        operario: $('#operario_id').val(),
        estado: $('#filtroEstado').val()
    };

    // Actualizar link de exportación
    const params = new URLSearchParams(filters);
    params.append('exportar_excel', '1');
    $('#linkExport').attr('href', '?' + params.toString());

    $('#historialBody').html(`<tr><td colspan="10" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</td></tr>`);

    $.get('ajax/horas_extras_manual_get_datos.php', filters, function (res) {
        if (res.success) {
            todosLosRegistros = res.data;
            paginaActual = 1;
            renderTabla();
        } else {
            $('#historialBody').html(`<tr><td colspan="10" class="text-center text-danger py-3">Error: ${res.message}</td></tr>`);
        }
    }, 'json');
};

// ─── Render tabla con paginación ──────────────────────────────────
function renderTabla() {
    registrosPorPag = parseInt($('#registrosPorPagina').val()) || 25;
    const total = todosLosRegistros.length;
    const totalPags = Math.max(1, Math.ceil(total / registrosPorPag));
    paginaActual = Math.min(paginaActual, totalPags);

    const inicio = (paginaActual - 1) * registrosPorPag;
    const fin = Math.min(inicio + registrosPorPag, total);
    const pagina = todosLosRegistros.slice(inicio, fin);

    const canApprove = window.canApprove;
    const canReject = window.canReject;
    const canEdit = window.canEdit;
    const puedeVerObs = window.puedeVerObs;

    let html = '';

    if (pagina.length === 0) {
        html = `<tr><td colspan="10" class="text-center py-5 text-muted">
            <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>No hay registros con los filtros seleccionados.
        </td></tr>`;
    } else {
        pagina.forEach(row => {
            const statusClass = (row.estado || 'pendiente').toLowerCase();
            const estadoBadge = `<span class="status-badge status-${statusClass}">${row.estado || 'Pendiente'}</span>`;

            // Horario programado
            const hEntProg = row.hora_entrada_programada
                ? `<span class="hora-entrada">${row.hora_entrada_programada}</span>`
                : `<span class="hora-vacia">—</span>`;
            const hSalProg = row.hora_salida_programada
                ? `<span class="hora-salida">${row.hora_salida_programada}</span>`
                : `<span class="hora-vacia">—</span>`;

            // Hora marcada
            const hEntMar = row.hora_entrada_marcada
                ? `<span class="hora-entrada">${row.hora_entrada_marcada}</span>`
                : `<span class="hora-vacia">—</span>`;
            const hSalMar = row.hora_salida_marcada
                ? `<span class="hora-salida">${row.hora_salida_marcada}</span>`
                : `<span class="hora-vacia">—</span>`;

            // Acciones
            let acciones = '';
            if ((row.estado || 'Pendiente') === 'Pendiente') {
                if (canApprove) {
                    acciones += `<button class="btn-action btn-approve" onclick="procesarSolicitud(${row.id},'Aprobado')" title="Aprobar"><i class="fas fa-check"></i></button>`;
                }
                if (canReject) {
                    acciones += `<button class="btn-action btn-deny"    onclick="procesarSolicitud(${row.id},'Denegado')" title="Denegar"><i class="fas fa-times"></i></button>`;
                }
            }
            
            if (canEdit) {
                const dataStr = encodeURIComponent(JSON.stringify(row));
                acciones += `<button class="btn-action btn-edit"   onclick="editarRegistro('${dataStr}')" title="Editar"><i class="fas fa-edit"></i></button>`;
            }

            const obsCell = puedeVerObs
                ? `<td>${row.observaciones || '<span class="text-muted">—</span>'}</td>`
                : '';

            html += `<tr>
                <td><strong>${row.operario_nombre || ''}</strong></td>
                <td>${row.sucursal_nombre || ''}</td>
                <td>${row.fecha || ''}</td>
                <td class="col-horario">
                    <div class="turno-prog">${hEntProg} <span class="hora-sep">·</span> ${hSalProg} <small class="text-muted">(P)</small></div>
                    <div class="turno-real" style="border-top: 1px solid #eee; margin-top: 2px; padding-top: 2px;">
                        ${hEntMar} <span class="hora-sep">·</span> ${hSalMar} <small class="text-muted">(R)</small>
                    </div>
                </td>
                <td class="text-center"><strong>${row.horas_extras || '—'}</strong></td>
                <td>${estadoBadge}</td>
                <td>${row.motivo_solicitud || '<span class="text-muted">—</span>'}</td>
                ${obsCell}
                <td class="text-nowrap">${acciones || '<span class="text-muted small">—</span>'}</td>
            </tr>`;
        });
    }

    $('#historialBody').html(html);

    // Info de registros
    if (total > 0) {
        $('#infoRegistros').text(`Mostrando ${inicio + 1}–${fin} de ${total} registros`);
    } else {
        $('#infoRegistros').text('');
    }

    // Paginación
    renderPaginacion(totalPags);
}

function renderPaginacion(totalPags) {
    let html = '';
    html += `<button class="pagination-btn" onclick="cambiarPagina(${paginaActual - 1})" ${paginaActual <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;

    // Números de página con ventana deslizante
    const ventana = 2;
    for (let p = 1; p <= totalPags; p++) {
        if (p === 1 || p === totalPags || (p >= paginaActual - ventana && p <= paginaActual + ventana)) {
            html += `<button class="pagination-btn ${p === paginaActual ? 'active' : ''}" onclick="cambiarPagina(${p})">${p}</button>`;
        } else if (p === paginaActual - ventana - 1 || p === paginaActual + ventana + 1) {
            html += `<span class="px-1 text-muted">…</span>`;
        }
    }

    html += `<button class="pagination-btn" onclick="cambiarPagina(${paginaActual + 1})" ${paginaActual >= totalPags ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
    $('#paginacion').html(html);
}

window.cambiarPagina = function (p) {
    const totalPags = Math.max(1, Math.ceil(todosLosRegistros.length / (parseInt($('#registrosPorPagina').val()) || 25)));
    if (p < 1 || p > totalPags) return;
    paginaActual = p;
    renderTabla();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// ─── Editar registro ──────────────────────────────────────────────
window.editarRegistro = function (dataStr) {
    const data = JSON.parse(decodeURIComponent(dataStr));
    $('#formSolicitud')[0].reset();
    $('#sol_id').val(data.id);
    $('#sol_cod_operario').val(data.cod_operario);
    $('#sol_cod_sucursal').val(data.cod_sucursal);
    // Mostrar nombre como badge (ya seleccionado)
    $('#sol_operario_search').val('').hide();
    $('#sol_operario_badge').text(data.operario_nombre);
    $('#sol_operario_seleccionado').show();
    $('#sol_fecha').val(data.fecha);

    // Aplicar restricciones si es necesario
    if (window.esRestringido) {
        const range = getRestrictedDateRange();
        $('#sol_fecha').attr('min', range.min).attr('max', range.max);
    } else {
        $('#sol_fecha').removeAttr('min').removeAttr('max');
    }

    $('#sol_horas').val(data.horas_extras);
    $('#sol_motivo').val(data.motivo_solicitud);
    if (window.puedeVerObs) {
        $('#sol_observaciones').val(data.observaciones);
    }
    $('#modalSolicitudTitulo').text('Editar Registro de Horas Extras');
    new bootstrap.Modal($('#modalSolicitud')[0]).show();
};

// ─── Procesar (Aprobar / Denegar) ─────────────────────────────────
window.procesarSolicitud = function (id, estado) {
    $('#proc_id').val(id);
    $('#proc_estado').val(estado);
    $('#proc_obs').val('');
    $('#modalProcesarTitulo').text(estado === 'Aprobado' ? '✅ Aprobar Solicitud' : '❌ Denegar Solicitud');
    $('#btnProcesarConfirmar')
        .removeClass('btn-primary btn-danger')
        .addClass(estado === 'Aprobado' ? 'btn-success' : 'btn-danger')
        .text(estado === 'Aprobado' ? 'Aprobar' : 'Denegar');
    new bootstrap.Modal($('#modalProcesar')[0]).show();
};

// ─── Eliminar registro ────────────────────────────────────────────
window.eliminarRegistro = function (id) {
    if (!confirm('¿Está seguro de eliminar este registro?')) return;
    $.post('ajax/horas_extras_manual_eliminar.php', { id }, function (res) {
        if (res.success) {
            cargarDatos();
        } else {
            alert('Error: ' + res.message);
        }
    }, 'json');
};
