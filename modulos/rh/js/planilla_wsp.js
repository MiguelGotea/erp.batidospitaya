'use strict';
/* =============================================================
   planilla_wsp.js — Notificaciones WhatsApp de Planilla
   ============================================================= */

// Estado global
let resetEnCurso = false;
let editandoProgId = 0;       // 0 = nuevo, > 0 = edición
let editandoFecha = '';

// ─────────────────────────────────────────────
// Inicialización
// ─────────────────────────────────────────────
$(document).ready(function () {
    cargarPlanillas();
    verificarEstadoVPS();
    setInterval(verificarEstadoVPS, 15_000); // Actualiza badge cada 15s
});

// ─────────────────────────────────────────────
// Badge de estado VPS
// ─────────────────────────────────────────────
function verificarEstadoVPS() {
    $.get('ajax/planilla_wsp_get_status.php', function (resp) {
        if (resp.success) {
            actualizarBadgeVPS(resp.estado, resp.numero);
        }
    }, 'json').fail(function () {
        actualizarBadgeVPS('desconectado');
    });
}

function actualizarBadgeVPS(estado, numero) {
    const dot = document.getElementById('vpsDot');
    const texto = document.getElementById('vpsStatusTexto');
    if (!dot || !texto) return;

    dot.className = 'wsp-dot ' + estado;

    const mapa = {
        conectado: `✅ WSP Planilla conectado${numero ? ' — ' + numero : ''}`,
        qr_pendiente: '📷 QR Pendiente — Haz clic para escanear',
        desconectado: '🔴 Servicio desconectado',
        inicializando: '⏳ Iniciando servicio...',
        reset_pendiente: '🔄 Cambiando número...'
    };
    texto.textContent = mapa[estado] || estado;
}

function verificarQR() {
    $.get('ajax/planilla_wsp_get_status.php', function (resp) {
        if (!resp.success) return;
        if (resp.estado === 'qr_pendiente' && resp.qr) {
            document.getElementById('qrImage').src = resp.qr;
            document.getElementById('qrImage').style.display = 'block';
            document.getElementById('qrLoading').style.display = 'none';
            new bootstrap.Modal(document.getElementById('modalQR')).show();
        } else if (resp.estado === 'conectado') {
            Swal.fire({ icon: 'success', title: 'WhatsApp Conectado', text: 'El servicio está listo para enviar.', timer: 3000 });
        } else {
            Swal.fire({ icon: 'info', title: resp.estado, text: 'El QR aún no está disponible. Espere unos segundos.', timer: 3000 });
        }
    }, 'json');
}

function confirmarResetSesion() {
    Swal.fire({
        title: '¿Cambiar número de WhatsApp (Planilla)?',
        html: 'Esto <strong>cerrará la sesión actual</strong> y generará un QR nuevo para vincular un número diferente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cambiar número',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;
        Swal.fire({ title: 'Solicitando reset...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        $.post('ajax/planilla_wsp_reset_sesion.php', {}, function (resp) {
            if (resp.success) {
                resetEnCurso = true;
                actualizarBadgeVPS('reset_pendiente');
                Swal.fire({ icon: 'info', title: 'Cambio solicitado', text: 'El servicio cerrará la sesión en los próximos 60 segundos.', timer: 5000, timerProgressBar: true });
                setTimeout(() => { resetEnCurso = false; verificarQR(); }, 65_000);
            } else {
                Swal.fire('Error', resp.error || 'No se pudo solicitar el reset', 'error');
            }
        }, 'json').fail(() => Swal.fire('Error', 'No se pudo contactar el servidor', 'error'));
    });
}

// ─────────────────────────────────────────────
// Tabla principal: planillas disponibles
// ─────────────────────────────────────────────
function cargarPlanillas() {
    const tbody = document.getElementById('cuerpoTablaPlanillas');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando planillas...</td></tr>';

    $.get('ajax/planilla_wsp_get_planillas.php', function (resp) {
        if (!resp.success) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">${resp.error}</td></tr>`;
            return;
        }
        if (!resp.planillas || resp.planillas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">No hay planillas registradas en el sistema.</td></tr>';
            return;
        }

        let html = '';
        resp.planillas.forEach(p => {
            const progBadge = badgeEstado(p.prog_estado);
            const hayProg = !!p.prog_id;
            const puedeEdit = hayProg && (p.prog_estado === 'programada');
            const puedeElim = hayProg && (p.prog_estado === 'programada');

            // Barra de progreso si hay programación
            let progHtml = '—';
            if (hayProg) {
                const pct = p.prog_total > 0 ? Math.round((p.prog_enviados / p.prog_total) * 100) : 0;
                progHtml = `
                    <div class="prog-bar-container">
                        <div class="d-flex justify-content-between mb-1">
                            <span>${p.prog_enviados}/${p.prog_total}</span>
                            <span>${pct}%</span>
                        </div>
                        <div style="height:6px;background:#dee2e6;border-radius:3px">
                            <div class="prog-bar-inner" style="width:${pct}%"></div>
                        </div>
                    </div>`;
            }

            html += `
            <tr class="planilla-row-header" onclick="toggleDetalle('${p.fecha_planilla}')">
                <td>
                    <i class="bi bi-chevron-right me-1 chevron-${p.fecha_planilla}" style="transition:transform .2s"></i>
                    <strong>${p.fecha_planilla_fmt}</strong>
                </td>
                <td class="text-center">${p.total_boletas}</td>
                <td class="text-center">${progBadge}</td>
                <td>${hayProg ? p.prog_fecha_envio : '—'}</td>
                <td>${progHtml}</td>
                <td class="text-center" onclick="event.stopPropagation()">
                    ${!hayProg && PUEDE_CREAR ? `<button class="btn-accion btn-programar" onclick="abrirModalProgramar('${p.fecha_planilla}', ${p.total_boletas})" title="Programar envío"><i class="bi bi-send-plus"></i></button>` : ''}
                    ${puedeEdit && PUEDE_CREAR ? `<button class="btn-accion btn-editar" onclick="abrirModalEditar('${p.fecha_planilla}', ${p.prog_id})" title="Editar programación"><i class="bi bi-pencil"></i></button>` : ''}
                    ${puedeElim && PUEDE_ELIMINAR ? `<button class="btn-accion btn-eliminar" onclick="eliminarProgramacion(${p.prog_id})" title="Eliminar programación"><i class="bi bi-trash"></i></button>` : ''}
                </td>
            </tr>
            <tr id="detalle-${p.fecha_planilla}" class="d-none">
                <td colspan="6" class="planilla-detail-row">
                    <div class="planilla-detail-inner" id="inner-${p.fecha_planilla}">
                        <span class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Cargando colaboradores...</span>
                    </div>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }, 'json').fail(() => {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Error al conectar con el servidor</td></tr>';
    });
}

function badgeEstado(estado) {
    if (!estado) return '<span class="badge bg-secondary">Sin programar</span>';
    const cls = { programada: 'badge-programada', enviando: 'badge-enviando', completada: 'badge-completada', cancelada: 'badge-cancelada' };
    const lbl = { programada: 'Programada', enviando: 'Enviando...', completada: 'Completada', cancelada: 'Cancelada' };
    return `<span class="badge ${cls[estado] || 'bg-secondary'}">${lbl[estado] || estado}</span>`;
}

// ─────────────────────────────────────────────
// Toggle detalle de planilla (tabla de colaboradores)
// ─────────────────────────────────────────────
let detallesCargados = {};

function toggleDetalle(fecha) {
    const fila = document.getElementById(`detalle-${fecha}`);
    const inner = document.getElementById(`inner-${fecha}`);
    const chevron = document.querySelector(`.chevron-${fecha}`);

    if (!fila) return;

    const visible = !fila.classList.contains('d-none');
    fila.classList.toggle('d-none', visible);
    if (chevron) chevron.style.transform = visible ? '' : 'rotate(90deg)';

    if (!visible && !detallesCargados[fecha]) {
        detallesCargados[fecha] = true;
        $.get('ajax/planilla_wsp_get_destinatarios.php', { fecha_planilla: fecha }, function (resp) {
            if (!resp.success) { inner.innerHTML = `<span class="text-danger">${resp.error}</span>`; return; }
            let aviso = '';
            if (resp.sin_telefono > 0) {
                aviso = `<div class="alert alert-warning py-1 px-2 small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>${resp.sin_telefono} colaborador(es) sin teléfono registrado — no recibirán el mensaje.</div>`;
            }
            let rows = resp.destinatarios.map(d =>
                `<tr><td>${d.cod_operario}</td><td>${d.nombre_completo || d.empleado_nombre}</td><td>${d.telefono}</td></tr>`
            ).join('');
            inner.innerHTML = `${aviso}
            <p class="small text-muted mb-2"><strong>${resp.total}</strong> colaboradores con teléfono registrado para esta planilla.</p>
            <div class="tabla-destinatarios">
                <table class="table table-sm table-bordered mb-0">
                    <thead><tr><th>Cód.</th><th>Nombre</th><th>Teléfono</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
        }, 'json').fail(() => { inner.innerHTML = '<span class="text-danger">Error al cargar colaboradores</span>'; });
    }
}

// ─────────────────────────────────────────────
// Modal: Programar / Editar
// ─────────────────────────────────────────────
function abrirModalProgramar(fecha, totalBoletas) {
    editandoProgId = 0;
    editandoFecha = fecha;
    document.getElementById('modalTitulo').textContent = 'Programar envío — ' + fecha;
    document.getElementById('campMensaje').value = '';
    document.getElementById('campFechaEnvio').value = '';
    document.getElementById('campImagen').value = '';
    document.getElementById('previewImagenContainer').classList.add('d-none');
    document.getElementById('resumenDestinatarios').textContent = totalBoletas + ' colaboradores';
    document.getElementById('resumenFecha').textContent = fecha;
    actualizarPreview();
    new bootstrap.Modal(document.getElementById('modalProgramar')).show();
}

function abrirModalEditar(fecha, progId) {
    editandoProgId = progId;
    editandoFecha = fecha;
    document.getElementById('modalTitulo').textContent = 'Editar programación — ' + fecha;

    // Cargar datos de la programación actual
    $.get('ajax/planilla_wsp_get_planillas.php', function (resp) {
        if (!resp.success) return;
        const prog = resp.planillas.find(p => p.prog_id == progId);
        if (!prog) return;
        document.getElementById('campMensaje').value = prog.prog_mensaje || '';
        document.getElementById('campFechaEnvio').value = prog.prog_fecha_envio_iso || '';
        actualizarPreview();
    }, 'json');

    // Contar destinatarios
    $.get('ajax/planilla_wsp_get_destinatarios.php', { fecha_planilla: fecha }, function (resp) {
        if (resp.success) {
            document.getElementById('resumenDestinatarios').textContent = resp.total + ' colaboradores';
        }
    }, 'json');

    document.getElementById('resumenFecha').textContent = fecha;
    new bootstrap.Modal(document.getElementById('modalProgramar')).show();
}

function insertarVariable(variable) {
    const ta = document.getElementById('campMensaje');
    const ini = ta.selectionStart;
    const fin = ta.selectionEnd;
    ta.value = ta.value.substring(0, ini) + variable + ta.value.substring(fin);
    ta.selectionStart = ta.selectionEnd = ini + variable.length;
    ta.focus();
    actualizarPreview();
}

function actualizarPreview() {
    const msg = document.getElementById('campMensaje').value;
    const bubble = document.getElementById('previewBubble');
    const preview = msg
        .replace(/\{\{nombre\}\}/gi, '<strong>Juan Pérez</strong>')
        .replace(/\{\{fecha_planilla\}\}/gi, '<strong>15-Feb-2026</strong>')
        .replace(/\n/g, '<br>');
    bubble.innerHTML = preview || '<em class="text-muted">El mensaje aparecerá aquí...</em>';
    document.getElementById('contadorCaracteres').textContent = msg.length;
}

function previsualizarImagen(input) {
    const file = input.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire('Error', 'La imagen supera el límite de 5MB', 'error');
        input.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('previewImagen').src = e.target.result;
        document.getElementById('previewImagenContainer').classList.remove('d-none');
    };
    reader.readAsDataURL(file);
}

// ─────────────────────────────────────────────
// Guardar programación
// ─────────────────────────────────────────────
async function guardarProgramacion() {
    const mensaje = document.getElementById('campMensaje').value.trim();
    const fechaEnvio = document.getElementById('campFechaEnvio').value;
    const imagenFile = document.getElementById('campImagen').files[0];

    if (!mensaje) { Swal.fire('Atención', 'El mensaje es requerido', 'warning'); return; }
    if (!fechaEnvio) { Swal.fire('Atención', 'La fecha y hora de envío es requerida', 'warning'); return; }

    let imagenBase64 = null;
    if (imagenFile) {
        imagenBase64 = await new Promise(resolve => {
            const reader = new FileReader();
            reader.onload = e => resolve(e.target.result);
            reader.readAsDataURL(imagenFile);
        });
    }

    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    $.ajax({
        url: 'ajax/planilla_wsp_guardar.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            fecha_planilla: editandoFecha,
            fecha_envio: fechaEnvio,
            mensaje: mensaje,
            imagen_base64: imagenBase64,
            prog_id: editandoProgId
        }),
        success: function (resp) {
            if (resp.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Programado!',
                    text: `El mensaje se enviará a ${resp.total} colaboradores en la fecha programada.`,
                    timer: 3000
                });
                bootstrap.Modal.getInstance(document.getElementById('modalProgramar')).hide();
                detallesCargados = {};   // Forzar recarga de detalles
                cargarPlanillas();
            } else {
                Swal.fire('Error', resp.error || 'No se pudo guardar', 'error');
            }
        },
        error: () => Swal.fire('Error', 'Error de conexión con el servidor', 'error'),
        dataType: 'json'
    });
}

// ─────────────────────────────────────────────
// Eliminar programación
// ─────────────────────────────────────────────
function eliminarProgramacion(progId) {
    Swal.fire({
        title: '¿Eliminar programación?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.ajax({
            url: 'ajax/planilla_wsp_eliminar.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ prog_id: progId }),
            success: function (resp) {
                if (resp.success) {
                    Swal.fire({ icon: 'success', title: 'Eliminada', timer: 2000, showConfirmButton: false });
                    detallesCargados = {};
                    cargarPlanillas();
                } else {
                    Swal.fire('Error', resp.error || 'No se pudo eliminar', 'error');
                }
            },
            error: () => Swal.fire('Error', 'Error de conexión', 'error'),
            dataType: 'json'
        });
    });
}
