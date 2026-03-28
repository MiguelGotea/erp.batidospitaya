// =====================================================
// Gestión de Tareas y Reuniones — v4 Day-View
// Reagendar SOLO por drag & drop — sin popover ni modal
// =====================================================

'use strict';

let agrupacionActual = 'mes';
let modalNuevaTarea, modalSolicitarTarea, modalNuevaReunion, modalFinalizarTarea;
let cargosLiderazgo = [];
let calendar;
let itemDragId = null;
let itemDragFechaOrigen = null;

// ── Inicializar ──────────────────────────────────────
$(document).ready(function () {
    modalNuevaTarea     = new bootstrap.Modal(document.getElementById('modalNuevaTarea'));
    modalSolicitarTarea = new bootstrap.Modal(document.getElementById('modalSolicitarTarea'));
    modalNuevaReunion   = new bootstrap.Modal(document.getElementById('modalNuevaReunion'));
    modalFinalizarTarea = new bootstrap.Modal(document.getElementById('modalFinalizarTarea'));

    cargarCargosLiderazgo();
    cargarDatos();
});

// ── Cargos ───────────────────────────────────────────
function cargarCargosLiderazgo() {
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_cargos_liderazgo.php',
        method: 'GET',
        dataType: 'json',
        success: function (r) {
            if (r.success) {
                cargosLiderazgo = r.cargos;
                poblarSelectsCargos();
                poblarListaInvitados();
            }
        },
        error: function () { Swal.fire('Error', 'No se pudieron cargar los cargos', 'error'); }
    });
}

function poblarSelectsCargos() {
    ['#cargoAsignadoTarea', '#cargoAsignadoTareaSolicitud'].forEach(sel => {
        $(sel).empty().append('<option value="">Seleccione un cargo...</option>');
        cargosLiderazgo.forEach(c =>
            $(sel).append(`<option value="${c.CodNivelesCargos}">${c.Nombre}</option>`)
        );
    });
}

function poblarListaInvitados() {
    const lista = $('#listaInvitados').empty();
    cargosLiderazgo.forEach(cargo => {
        const item = $(`
            <div class="invitado-item">
                <input type="checkbox" id="inv_${cargo.CodNivelesCargos}" value="${cargo.CodNivelesCargos}">
                <label for="inv_${cargo.CodNivelesCargos}">${cargo.Nombre}</label>
            </div>
        `);
        item.on('click', function (e) {
            if (e.target.tagName !== 'INPUT') {
                const cb = $(this).find('input[type="checkbox"]');
                cb.prop('checked', !cb.prop('checked'));
            }
        });
        lista.append(item);
    });
}

// ── Modales ──────────────────────────────────────────
function abrirModalNuevaTarea() {
    $('#formNuevaTarea')[0].reset();
    $('#fechaMetaTarea').val(obtenerFechaManana());
    modalNuevaTarea.show();
}
function abrirModalSolicitarTarea() {
    $('#formSolicitarTarea')[0].reset();
    $('#fechaMetaTareaSolicitud').val(obtenerFechaManana());
    modalSolicitarTarea.show();
}
function abrirModalNuevaReunion() {
    $('#formNuevaReunion')[0].reset();
    $('#fechaReunion').val(obtenerFechaHoraManana());
    $('#listaInvitados input[type="checkbox"]').prop('checked', false);
    modalNuevaReunion.show();
}

// ── Agrupación ───────────────────────────────────────
function cambiarAgrupacion(tipo) {
    agrupacionActual = tipo;
    $('.view-toggle .vt-btn').removeClass('active');
    $(`.view-toggle .vt-btn[data-agrupacion="${tipo}"]`).addClass('active');

    if (tipo === 'calendario') {
        $('#contenedorTareasReuniones').hide();
        $('#contenedorCalendario').show();
        inicializarCalendario();
    } else {
        $('#contenedorCalendario').hide();
        $('#contenedorTareasReuniones').show();
        cargarDatos();
    }
}

// ── Cargar datos ─────────────────────────────────────
function cargarDatos() {
    if (agrupacionActual === 'calendario') {
        if (calendar) calendar.refetchEvents();
        return;
    }
    $('#contenedorTareasReuniones').html(`
        <div class="spinner-prem">
            <div class="spinner-border" role="status"></div>
            <span>Cargando...</span>
        </div>
    `);
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_datos.php',
        method: 'POST',
        data: { agrupacion: agrupacionActual },
        dataType: 'json',
        success: function (r) {
            (r.success && r.grupos.length > 0) ? renderizarDatos(r.grupos) : mostrarEstadoVacio();
        },
        error: function () { Swal.fire('Error', 'No se pudieron cargar los datos', 'error'); }
    });
}

// ── Renderizar ───────────────────────────────────────
function renderizarDatos(grupos) {
    const cont = $('#contenedorTareasReuniones').empty();

    // En vista mes: detectar cambio de mes para insertar separador
    let mesAnterior = null;

    grupos.forEach(grupo => {
        if (agrupacionActual === 'mes') {
            // Separador de mes cuando cambia el mes
            const mesFecha = (grupo.fecha_referencia || '').substring(0, 7); // YYYY-MM
            if (mesFecha && mesFecha !== mesAnterior) {
                mesAnterior = mesFecha;
                const separador = crearSeparadorMes(grupo.fecha_referencia);
                cont.append(separador);
            }
        }
        cont.append(crearGrupoHtml(grupo));
    });
}

function crearSeparadorMes(fecha) {
    if (!fecha) return $('');
    const d = new Date(fecha + 'T00:00:00');
    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const nombre = meses[d.getMonth()] + ' ' + d.getFullYear();
    return $(`<div class="mes-separador"><span>${nombre}</span></div>`);
}

function crearGrupoHtml(grupo) {
    // clase_header viene del backend o se deriva del nombre (semana, cargo, estado)
    const claseHeader = grupo.clase_header || derivarClaseHeader(grupo.nombre);
    const esDia       = agrupacionActual === 'mes'; // grupos diarios
    const esVacio     = grupo.items.length === 0;

    const grupoDiv = $(`<div class="grupo-container${esDia ? ' grupo-dia' : ''}"></div>`);

    grupoDiv.append($(`
        <div class="grupo-header ${claseHeader}">
            <span>${grupo.nombre}</span>
            <span class="grupo-badge">${grupo.items.length === 0
                ? '—'
                : grupo.items.length + ' ' + (grupo.items.length === 1 ? 'item' : 'items')
            }</span>
        </div>
    `));

    const body = $(`<div class="grupo-body${esVacio && esDia ? ' dia-empty' : ''}"></div>`);

    if (esVacio && esDia) {
        body.append('<span class="drag-hint"><i class="bi bi-arrow-down-circle"></i> Arrastra aquí para reagendar</span>');
    } else if (grupo.items.length === 0) {
        body.append('<p class="text-muted text-center mb-0" style="padding:14px;font-size:13px;">Sin items</p>');
    } else {
        const hoy = obtenerFechaHoy();
        grupo.items.forEach(item => body.append(crearItemHtml(item, hoy)));
    }

    body[0].dataset.fechaGrupo = grupo.fecha_referencia || '';
    habilitarDropZone(body[0]);
    grupoDiv.append(body);
    return grupoDiv;
}

function derivarClaseHeader(nombre) {
    const n = (nombre || '').toLowerCase();
    if (n.includes('vencid') || n.includes('pasad') || n.includes('anterior')) return 'vencido';
    if (n.startsWith('hoy'))  return 'hoy';
    return '';
}

// ── Item Card ────────────────────────────────────────
function crearItemHtml(item, hoy) {
    const tipoIcono = item.tipo === 'reunion' ? 'bi-calendar-event' : 'bi-file-earmark-text';
    const tipoClase = item.tipo === 'reunion' ? 'reunion' : 'tarea';
    const progreso  = Math.round(item.progreso || 0);

    const fechaRef = item.tipo === 'reunion'
        ? (item.fecha_reunion || '').substring(0, 10)
        : (item.fecha_meta || '');
    let claseCard = '', claseFecha = '';
    if (fechaRef && item.estado !== 'finalizado' && item.estado !== 'cancelado') {
        if      (fechaRef < hoy)   { claseCard = 'vencida'; claseFecha = 'fecha-vencida'; }
        else if (fechaRef === hoy) { claseCard = 'hoy';     claseFecha = 'fecha-hoy'; }
        else if (diasDiff(hoy, fechaRef) <= 3) claseFecha = 'fecha-proxima';
    }

    const card = $(`
        <div class="item-card-row ${claseCard}"
             data-id="${item.id}"
             data-tipo="${item.tipo}"
             data-fecha="${fechaRef}"
             data-estado="${item.estado}">

            <div class="drag-handle" title="Arrastra para reagendar a otro día">
                <i class="bi bi-grip-vertical"></i>
            </div>

            <div class="item-icon-small ${tipoClase}">
                <i class="bi ${tipoIcono}"></i>
            </div>

            <div class="item-main-info" style="cursor:pointer;" onclick="verDetalle(${item.id})">
                <div class="item-title-block">
                    <div class="item-titulo-text">${escapeHtml(item.titulo)}</div>
                    ${item.descripcion
                        ? `<div class="item-descripcion-preview">${escapeHtml(stripHtml(item.descripcion))}</div>`
                        : ''}
                </div>
            </div>

            <div class="item-meta-row">
                <div class="item-meta-col">
                    <span class="badge-estado ${item.estado}">${formatearEstado(item.estado)}</span>
                </div>
                <div class="item-meta-col ${claseFecha}">
                    <i class="bi bi-calendar3 me-1"></i>
                    <span>${item.tipo === 'reunion'
                        ? formatearFechaHora(item.fecha_reunion)
                        : formatearFecha(item.fecha_meta)}</span>
                </div>
                <div class="item-meta-col">
                    ${crearAvatarHtml(item)}
                </div>
            </div>

            <div class="item-progreso-row">
                <div class="progreso-compacto">
                    <div class="progreso-barra-small">
                        <div class="progreso-fill-small ${progreso >= 100 ? 'completo' : ''}" style="width:${progreso}%"></div>
                    </div>
                    <span class="progreso-porcentaje">${progreso}%</span>
                </div>
            </div>

            <div class="item-acciones-row" onclick="event.stopPropagation()">
                ${crearAccionesHtml(item)}
            </div>
        </div>
    `);

    // Drag solo para tareas activas
    if (item.tipo === 'tarea' && item.estado !== 'finalizado' && item.estado !== 'cancelado') {
        habilitarDrag(card[0], item);
    }
    return card;
}

// ── Acciones (sin botón de posponer) ─────────────────
function crearAccionesHtml(item) {
    let html = `
        <button class="btn-icon-only btn-ver" onclick="verDetalle(${item.id})" data-tip="Ver detalle">
            <i class="bi bi-eye"></i>
        </button>
    `;

    const puedeFinalizar = item.tipo === 'tarea'
        && item.estado === 'en_progreso'
        && item.cod_cargo_asignado == cargoActual;

    html += `
        <button class="btn-icon-only btn-finalizar"
                onclick="finalizarTareaManualPanel(${item.id}, ${item.total_subtareas})"
                data-tip="Finalizar"
                ${puedeFinalizar ? '' : 'style="visibility:hidden;pointer-events:none;"'}>
            <i class="bi bi-check-circle-fill"></i>
        </button>
    `;

    const mostrarCancelar = (permisoCancelar || item.cod_operario_creador == cargoActual)
        && item.estado !== 'cancelado'
        && item.estado !== 'finalizado';

    html += `
        <button class="btn-icon-only btn-cancelar"
                onclick="cancelarItem(${item.id}, '${item.tipo}')"
                data-tip="Cancelar"
                ${mostrarCancelar ? '' : 'style="visibility:hidden;pointer-events:none;"'}>
            <i class="bi bi-x-circle-fill"></i>
        </button>
    `;
    return html;
}

// ── Drag & Drop ──────────────────────────────────────
function habilitarDrag(cardEl, item) {
    cardEl.setAttribute('draggable', 'true');
    cardEl.addEventListener('dragstart', function (e) {
        itemDragId = item.id;
        itemDragFechaOrigen = item.fecha_meta;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(item.id));
        cardEl.classList.add('dragging');
    });
    cardEl.addEventListener('dragend', function () {
        cardEl.classList.remove('dragging');
        document.querySelectorAll('.grupo-body.drop-active')
            .forEach(z => z.classList.remove('drop-active'));
    });
}

function habilitarDropZone(bodyEl) {
    bodyEl.addEventListener('dragover', function (e) {
        if (!itemDragId) return;
        const fechaDest = bodyEl.dataset.fechaGrupo;
        const hoy = obtenerFechaHoy();
        // Solo permitir drop en hoy o futuro
        if (!fechaDest || fechaDest < hoy) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        document.querySelectorAll('.grupo-body.drop-active')
            .forEach(z => z.classList.remove('drop-active'));
        bodyEl.classList.add('drop-active');
    });

    bodyEl.addEventListener('dragleave', function (e) {
        if (!bodyEl.contains(e.relatedTarget)) bodyEl.classList.remove('drop-active');
    });

    bodyEl.addEventListener('drop', function (e) {
        e.preventDefault();
        bodyEl.classList.remove('drop-active');
        if (!itemDragId) return;

        const fechaDestino = bodyEl.dataset.fechaGrupo;
        const hoy = obtenerFechaHoy();

        if (!fechaDestino || fechaDestino < hoy) {
            Swal.fire('No permitido', 'Solo puedes reagendar tareas a hoy o días futuros.', 'warning');
            itemDragId = null;
            return;
        }

        // No hacer nada si es la misma fecha
        if (fechaDestino === itemDragFechaOrigen) {
            itemDragId = null;
            return;
        }

        posponerTareaAjax(itemDragId, fechaDestino);
        itemDragId = null;
    });
}

// ── Posponer AJAX ────────────────────────────────────
function posponerTareaAjax(id, nuevaFecha) {
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_posponer.php',
        method: 'POST',
        data: { id: id, nueva_fecha: nuevaFecha },
        dataType: 'json',
        success: function (r) {
            if (r.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Reagendada',
                    text: `Nueva fecha: ${formatearFecha(nuevaFecha)}`,
                    timer: 1800,
                    showConfirmButton: false
                });
                cargarDatos();
            } else {
                Swal.fire('Error', r.message || 'No se pudo reagendar', 'error');
            }
        },
        error: function () { Swal.fire('Error', 'No se pudo reagendar la tarea', 'error'); }
    });
}

// ── Finalizar ────────────────────────────────────────
function finalizarTareaManualPanel(id, totalSubtareas) {
    if (parseInt(totalSubtareas) > 0) {
        Swal.fire({
            icon: 'info',
            title: 'Subtareas pendientes',
            text: 'Finaliza todas las subtareas para completar la tarea principal automáticamente.',
            confirmButtonColor: '#0E544C'
        });
        return;
    }
    $('#formFinalizarTarea')[0].reset();
    $('#finalizarIdItem').val(id);
    modalFinalizarTarea.show();
}

function confirmarFinalizarManual() {
    const idItem   = $('#finalizarIdItem').val();
    const detalles = $('#detallesFinalizacionTarea').val().trim();
    if (!detalles) { Swal.fire('Error', 'Ingresa los detalles de finalización', 'error'); return; }

    const formData = new FormData();
    formData.append('id', idItem);
    formData.append('detalles', detalles);
    const archivos = $('#archivosFinalizacionTarea')[0].files;
    for (let i = 0; i < archivos.length; i++) formData.append('archivos[]', archivos[i]);

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_finalizar.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (r) {
            if (r.success) {
                Swal.fire({ icon: 'success', title: '¡Completado!', text: 'Tarea finalizada correctamente', timer: 2000, showConfirmButton: false });
                modalFinalizarTarea.hide();
                cargarDatos();
            } else {
                Swal.fire('Error', r.message, 'error');
            }
        },
        error: function () { Swal.fire('Error', 'No se pudo completar la finalización', 'error'); }
    });
}

// ── Cancelar ─────────────────────────────────────────
function cancelarItem(id, tipo) {
    Swal.fire({
        title: `¿Cancelar ${tipo === 'reunion' ? 'reunión' : 'tarea'}?`,
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c62828',
        cancelButtonColor: '#78909c',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.ajax({
            url: 'ajax/gestion_tareas_reuniones_cancelar.php',
            method: 'POST',
            data: { id },
            dataType: 'json',
            success: function (r) {
                if (r.success) {
                    Swal.fire({ icon: 'success', title: 'Cancelado', text: r.message, timer: 1800, showConfirmButton: false });
                    cargarDatos();
                } else {
                    Swal.fire('Error', r.message, 'error');
                }
            },
            error: function () { Swal.fire('Error', 'No se pudo cancelar', 'error'); }
        });
    });
}

// ── Guardar tarea ────────────────────────────────────
function guardarTarea(tipo) {
    const formId = tipo === 'crear' ? '#formNuevaTarea' : '#formSolicitarTarea';
    const form = $(formId)[0];
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const formData = new FormData(form);
    formData.append('tipo', tipo);

    const archivosInput = tipo === 'crear' ? '#archivosTarea' : '#archivosTareaSolicitud';
    const archivos = $(archivosInput)[0].files;
    for (let i = 0; i < archivos.length; i++) {
        if (archivos[i].size > 10 * 1024 * 1024) {
            Swal.fire('Error', `${archivos[i].name} excede el límite de 10MB`, 'error'); return;
        }
    }

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_guardar.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (r) {
            if (r.success) {
                Swal.fire({ icon: 'success', title: 'Éxito', text: r.message, timer: 2000, showConfirmButton: false });
                tipo === 'crear' ? modalNuevaTarea.hide() : modalSolicitarTarea.hide();
                cargarDatos();
            } else {
                Swal.fire('Error', r.message, 'error');
            }
        },
        error: function () { Swal.fire('Error', 'No se pudo guardar la tarea', 'error'); }
    });
}

// ── Guardar reunión ──────────────────────────────────
function guardarReunion() {
    const form = $('#formNuevaReunion')[0];
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const invitados = [];
    $('#listaInvitados input[type="checkbox"]:checked').each(function () { invitados.push($(this).val()); });
    if (invitados.length === 0) { Swal.fire('Error', 'Selecciona al menos un invitado', 'error'); return; }

    const formData = new FormData(form);
    formData.append('invitados', JSON.stringify(invitados));

    const archivos = $('#archivosReunion')[0].files;
    for (let i = 0; i < archivos.length; i++) {
        if (archivos[i].size > 10 * 1024 * 1024) {
            Swal.fire('Error', `${archivos[i].name} excede el límite de 10MB`, 'error'); return;
        }
    }

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_guardar_reunion.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (r) {
            if (r.success) {
                Swal.fire({ icon: 'success', title: 'Éxito', text: r.message, timer: 2000, showConfirmButton: false });
                modalNuevaReunion.hide();
                cargarDatos();
            } else {
                Swal.fire('Error', r.message, 'error');
            }
        },
        error: function () { Swal.fire('Error', 'No se pudo guardar la reunión', 'error'); }
    });
}

// ── Ver detalle ──────────────────────────────────────
function verDetalle(id) {
    window.location.href = `gestion_tareas_reuniones_detalle.php?id=${id}`;
}

// ── Calendario ───────────────────────────────────────
function inicializarCalendario() {
    const calendarEl = document.getElementById('calendarioTareas');
    if (calendar) calendar.destroy();

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
        buttonText: { today: 'Hoy' },
        firstDay: 1,
        editable: true,
        droppable: true,
        eventDrop: function (info) {
            const hoy = obtenerFechaHoy();
            const nuevaFecha = info.event.startStr.substring(0, 10);
            if (nuevaFecha < hoy) {
                Swal.fire('No permitido', 'No puedes mover tareas a fechas pasadas', 'warning');
                info.revert();
                return;
            }
            posponerTareaAjax(info.event.extendedProps.itemId, nuevaFecha);
        },
        events: function (info, ok) {
            $.ajax({
                url: 'ajax/gestion_tareas_reuniones_get_calendario.php',
                method: 'GET',
                data: { start: info.startStr, end: info.endStr },
                dataType: 'json',
                success: ok,
                error: function () { ok([]); }
            });
        },
        eventClick: function (info) {
            const id = info.event.extendedProps.itemId;
            if (id) verDetalle(id);
        },
        eventDidMount: function (info) {
            info.el.style.setProperty('--event-status-color', info.event.backgroundColor);
            let tip = info.event.title;
            if (info.event.extendedProps.descripcion) {
                const tmp = document.createElement('div');
                tmp.innerHTML = info.event.extendedProps.descripcion;
                const plain = (tmp.textContent || tmp.innerText || '').trim();
                if (plain) tip += `\n\n${plain.substring(0, 200)}`;
            }
            info.el.setAttribute('title', tip);
        },
        height: 'auto',
        navLinks: true,
        nowIndicator: true,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false }
    });
    calendar.render();
}

// ── Empty state ──────────────────────────────────────
function mostrarEstadoVacio() {
    $('#contenedorTareasReuniones').html(`
        <div class="estado-vacio">
            <i class="bi bi-inbox"></i>
            <h5>Sin tareas ni reuniones</h5>
            <p>Crea una nueva tarea o solicita una reunión para comenzar</p>
        </div>
    `);
}

// ── Utilidades ───────────────────────────────────────
function obtenerFechaHoy() { return new Date().toISOString().split('T')[0]; }
function obtenerFechaManana() {
    const d = new Date(); d.setDate(d.getDate() + 1);
    return d.toISOString().split('T')[0];
}
function obtenerFechaHoraManana() {
    const d = new Date(); d.setDate(d.getDate() + 1); d.setHours(9, 0, 0, 0);
    return d.toISOString().slice(0, 16);
}
function offsetFecha(base, dias) {
    const d = new Date(base + 'T00:00:00'); d.setDate(d.getDate() + dias);
    return d.toISOString().split('T')[0];
}
function diasDiff(desde, hasta) {
    return Math.round((new Date(hasta + 'T00:00:00') - new Date(desde + 'T00:00:00')) / 86400000);
}
function formatearFecha(f) {
    if (!f) return '-';
    const d = new Date(f + 'T00:00:00');
    const m = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `${d.getDate().toString().padStart(2, '0')}-${m[d.getMonth()]}-${d.getFullYear().toString().substr(2)}`;
}
function formatearFechaHora(fh) {
    if (!fh) return '-';
    const d = new Date(fh);
    const m = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `${d.getDate().toString().padStart(2, '0')}-${m[d.getMonth()]}-${d.getFullYear().toString().substr(2)} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
}
function formatearEstado(e) {
    return { solicitado: 'Solicitado', en_progreso: 'En Progreso', finalizado: 'Finalizado', cancelado: 'Cancelado' }[e] || e;
}
function crearAvatarHtml(item) {
    if (item.avatar_url)
        return `<img src="${item.avatar_url}" class="avatar-responsable-small" title="${escapeHtml(item.nombre_responsable || '')}">`;
    const ini = obtenerIniciales(item.nombre_responsable || 'NN');
    return `<div class="avatar-placeholder-small" title="${escapeHtml(item.nombre_responsable || '')}">${ini}</div>`;
}
function obtenerIniciales(n) {
    const p = n.trim().split(' ');
    return p.length >= 2 ? (p[0][0] + p[1][0]).toUpperCase() : n.substr(0, 2).toUpperCase();
}
function stripHtml(h) {
    if (!h) return '';
    const t = document.createElement('div'); t.innerHTML = h;
    return t.textContent || t.innerText || '';
}
function escapeHtml(t) {
    if (!t) return '';
    return String(t).replace(/[&<>"']/g, m =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m])
    );
}