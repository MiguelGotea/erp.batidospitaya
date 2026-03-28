// =====================================================
// Gestión de Tareas y Reuniones — Minimal Premium v3
// Popover inline para reagendar — sin modal extra
// =====================================================

'use strict';

let agrupacionActual = 'mes';
let modalNuevaTarea, modalSolicitarTarea, modalNuevaReunion, modalFinalizarTarea;
let cargosLiderazgo = [];
let calendar;
let itemDragId = null;
let itemDragFechaOrigen = null;
let popoverActivo = null; // referencia al popover abierto


// ── Inicializar ──────────────────────────────────────
$(document).ready(function () {
    modalNuevaTarea = new bootstrap.Modal(document.getElementById('modalNuevaTarea'));
    modalSolicitarTarea = new bootstrap.Modal(document.getElementById('modalSolicitarTarea'));
    modalNuevaReunion = new bootstrap.Modal(document.getElementById('modalNuevaReunion'));
    modalFinalizarTarea = new bootstrap.Modal(document.getElementById('modalFinalizarTarea'));

    cargarCargosLiderazgo();
    cargarDatos();

    // Cerrar popover al hacer click fuera
    document.addEventListener('click', function (e) {
        if (popoverActivo && !popoverActivo.contains(e.target) &&
            !e.target.classList.contains('btn-posponer') &&
            !e.target.closest('.btn-posponer')) {
            cerrarPopover();
        }
    });
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

// ── Modales de formulario ────────────────────────────
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
    grupos.forEach(g => cont.append(crearGrupoHtml(g)));
}

function crearGrupoHtml(grupo) {
    const hoy = obtenerFechaHoy();
    let claseHeader = '';
    if (agrupacionActual === 'mes') {
        const n = (grupo.nombre || '').toLowerCase();
        if (n.includes('vencid') || n.includes('pasad') || n.includes('anterior')) claseHeader = 'vencido';
        else if (n.includes('hoy') || n.includes('today')) claseHeader = 'hoy';
    }

    const grupoDiv = $('<div class="grupo-container"></div>');
    grupoDiv.append($(`
        <div class="grupo-header ${claseHeader}">
            <span>${grupo.nombre}</span>
            <span class="grupo-badge">${grupo.items.length} ${grupo.items.length === 1 ? 'item' : 'items'}</span>
        </div>
    `));

    const body = $('<div class="grupo-body"></div>');
    if (grupo.items.length === 0) {
        body.append('<p class="text-muted text-center mb-0" style="padding:14px;font-size:13px;">No hay tareas o reuniones en este grupo</p>');
    } else {
        grupo.items.forEach(item => body.append(crearItemHtml(item, hoy)));
    }

    body[0].dataset.fechaGrupo = grupo.fecha_referencia || '';
    habilitarDropZone(body[0]);
    grupoDiv.append(body);
    return grupoDiv;
}

// ── Item Card ────────────────────────────────────────
function crearItemHtml(item, hoy) {
    const tipoIcono = item.tipo === 'reunion' ? 'bi-calendar-event' : 'bi-file-earmark-text';
    const tipoClase = item.tipo === 'reunion' ? 'reunion' : 'tarea';
    const progreso = Math.round(item.progreso || 0);

    const fechaRef = item.tipo === 'reunion'
        ? (item.fecha_reunion || '').substring(0, 10)
        : (item.fecha_meta || '');
    let claseCard = '', claseFecha = '';
    if (fechaRef && item.estado !== 'finalizado' && item.estado !== 'cancelado') {
        if (fechaRef < hoy) { claseCard = 'vencida'; claseFecha = 'fecha-vencida'; }
        else if (fechaRef === hoy) { claseCard = 'hoy'; claseFecha = 'fecha-hoy'; }
        else if (diasDiff(hoy, fechaRef) <= 3) claseFecha = 'fecha-proxima';
    }

    const card = $(`
        <div class="item-card-row ${claseCard}"
             data-id="${item.id}"
             data-tipo="${item.tipo}"
             data-fecha="${fechaRef}"
             data-estado="${item.estado}">

            <div class="drag-handle" title="Arrastra para reagendar">
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

// ── Acciones ─────────────────────────────────────────
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

    const puedePosponer = item.tipo === 'tarea'
        && item.estado !== 'finalizado'
        && item.estado !== 'cancelado';

    html += `
        <button class="btn-icon-only btn-posponer"
                onclick="abrirPopoverReagendar(this, ${item.id}, '${item.fecha_meta}')"
                data-tip="Reagendar"
                ${puedePosponer ? '' : 'style="visibility:hidden;pointer-events:none;"'}>
            <i class="bi bi-calendar-plus"></i>
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

// ════════════════════════════════════════════════════
// POPOVER INLINE DE REAGENDA
// ════════════════════════════════════════════════════
function abrirPopoverReagendar(btnEl, itemId, fechaActual) {
    // Si ya hay uno abierto para el mismo item, cerrarlo (toggle)
    if (popoverActivo) {
        const mismoItem = popoverActivo.dataset.itemId == itemId;
        cerrarPopover();
        if (mismoItem) return;
    }

    const hoy = obtenerFechaHoy();
    // Si la fecha actual ya pasó, sugerir mañana
    const sugerida = (!fechaActual || fechaActual < hoy) ? obtenerFechaManana() : fechaActual;

    // Pre-calcular chips
    const chips = [
        { label: 'Hoy', fecha: hoy },
        { label: 'Mañana', fecha: offsetFecha(hoy, 1) },
        { label: '+2 días', fecha: offsetFecha(hoy, 2) },
        { label: '+1 semana', fecha: offsetFecha(hoy, 7) },
    ];

    const chipsHtml = chips.map(c => `
        <button type="button" class="date-chip" data-fecha="${c.fecha}"
                onclick="seleccionarChip(this, '${c.fecha}', ${itemId})">
            <span class="chip-label">${c.label}</span>
            <span class="chip-date">${formatearFecha(c.fecha)}</span>
        </button>
    `).join('');

    const popover = document.createElement('div');
    popover.className = 'reschedule-popover';
    popover.dataset.itemId = itemId;
    popover.innerHTML = `
        <h6><i class="bi bi-calendar-plus" style="color:#51B8AC;"></i> Reagendar tarea</h6>
        <div class="date-chips">${chipsHtml}</div>
        <div class="reschedule-divider">o elige una fecha</div>
        <input type="date" id="rschDateInput_${itemId}" min="${hoy}" value="${sugerida}">
        <div class="reschedule-actions">
            <button type="button" class="btn-rsch-cancel" onclick="cerrarPopover()">Cancelar</button>
            <button type="button" class="btn-rsch-confirm"
                    onclick="confirmarReagendaManual(${itemId})">
                <i class="bi bi-check2"></i> Confirmar
            </button>
        </div>
    `;

    // Posicionar relativo al contenedor de acciones (padre del botón)
    const accRow = btnEl.closest('.item-acciones-row');
    accRow.style.position = 'relative';
    accRow.appendChild(popover);
    popoverActivo = popover;

    // Marcar chip si coincide con la fecha sugerida
    const chipMatch = popover.querySelector(`.date-chip[data-fecha="${sugerida}"]`);
    if (chipMatch) chipMatch.classList.add('selected');
}

function seleccionarChip(chipEl, fecha, itemId) {
    // Marcar seleccionado
    chipEl.closest('.date-chips').querySelectorAll('.date-chip').forEach(c => c.classList.remove('selected'));
    chipEl.classList.add('selected');
    // Sincronizar el input de fecha
    const input = document.getElementById(`rschDateInput_${itemId}`);
    if (input) input.value = fecha;
}

function confirmarReagendaManual(itemId) {
    const input = document.getElementById(`rschDateInput_${itemId}`);
    const fecha = input ? input.value : '';
    const hoy = obtenerFechaHoy();

    if (!fecha) { Swal.fire('Atención', 'Selecciona una nueva fecha', 'warning'); return; }
    if (fecha < hoy) { Swal.fire('No permitido', 'Solo puedes reagendar a hoy o días futuros.', 'warning'); return; }

    cerrarPopover();
    posponerTareaAjax(itemId, fecha);
}

function cerrarPopover() {
    if (popoverActivo) {
        popoverActivo.remove();
        popoverActivo = null;
    }
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

// ── Drag & Drop ──────────────────────────────────────
function habilitarDrag(cardEl, item) {
    cardEl.setAttribute('draggable', 'true');
    cardEl.addEventListener('dragstart', function (e) {
        itemDragId = item.id;
        itemDragFechaOrigen = item.fecha_meta;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(item.id));
        cardEl.classList.add('dragging');
        cerrarPopover();
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

        const fechaDestino = bodyEl.dataset.fechaGrupo || '';
        const hoy = obtenerFechaHoy();

        if (!fechaDestino) {
            // Sin fecha de referencia: abrir popover del elemento
            const cardEl = document.querySelector(`.item-card-row[data-id="${itemDragId}"]`);
            const btnPos = cardEl ? cardEl.querySelector('.btn-posponer') : null;
            if (btnPos) abrirPopoverReagendar(btnPos, itemDragId, itemDragFechaOrigen);
            itemDragId = null;
            return;
        }
        if (fechaDestino < hoy) {
            Swal.fire('No permitido', 'No puedes mover tareas a fechas pasadas.', 'warning');
            itemDragId = null;
            return;
        }
        posponerTareaAjax(itemDragId, fechaDestino);
        itemDragId = null;
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
    const idItem = $('#finalizarIdItem').val();
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
        events: function (info, ok, fail) {
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

// ── Ver detalle ──────────────────────────────────────
function verDetalle(id) {
    window.location.href = `gestion_tareas_reuniones_detalle.php?id=${id}`;
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