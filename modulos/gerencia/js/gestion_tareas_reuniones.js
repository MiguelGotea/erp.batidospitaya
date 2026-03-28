// =====================================================
// Gestión de Tareas y Reuniones — PREMIUM JS v2
// Drag & Drop para posponer tareas, acciones mejoradas
// =====================================================

'use strict';

let agrupacionActual = 'mes';
let modalNuevaTarea, modalSolicitarTarea, modalNuevaReunion, modalFinalizarTarea, modalPosponerFecha;
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
    modalPosponerFecha  = new bootstrap.Modal(document.getElementById('modalPosponerFecha'));

    cargarCargosLiderazgo();
    cargarDatos();
});

// ── Cargos de liderazgo ──────────────────────────────
function cargarCargosLiderazgo() {
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_cargos_liderazgo.php',
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargosLiderazgo = response.cargos;
                poblarSelectsCargos();
                poblarListaInvitados();
            }
        },
        error: function () {
            Swal.fire('Error', 'No se pudieron cargar los cargos', 'error');
        }
    });
}

function poblarSelectsCargos() {
    const selectTarea    = $('#cargoAsignadoTarea');
    const selectSolicitud= $('#cargoAsignadoTareaSolicitud');

    selectTarea.empty().append('<option value="">Seleccione un cargo...</option>');
    selectSolicitud.empty().append('<option value="">Seleccione un cargo...</option>');

    cargosLiderazgo.forEach(cargo => {
        const opt = `<option value="${cargo.CodNivelesCargos}">${cargo.Nombre}</option>`;
        selectTarea.append(opt);
        selectSolicitud.append(opt);
    });
}

function poblarListaInvitados() {
    const lista = $('#listaInvitados');
    lista.empty();

    cargosLiderazgo.forEach(cargo => {
        const item = $(`
            <div class="invitado-item">
                <input type="checkbox" id="invitado_${cargo.CodNivelesCargos}" value="${cargo.CodNivelesCargos}">
                <label for="invitado_${cargo.CodNivelesCargos}">${cargo.Nombre}</label>
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

// ── Abrir modales ────────────────────────────────────
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

// ── Cambiar agrupación ───────────────────────────────
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
            <span>Cargando tareas y reuniones...</span>
        </div>
    `);

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_datos.php',
        method: 'POST',
        data: { agrupacion: agrupacionActual },
        dataType: 'json',
        success: function (response) {
            if (response.success && response.grupos.length > 0) {
                renderizarDatos(response.grupos);
            } else {
                mostrarEstadoVacio();
            }
        },
        error: function () {
            Swal.fire('Error', 'No se pudieron cargar los datos', 'error');
        }
    });
}

// ── Renderizar grupos ────────────────────────────────
function renderizarDatos(grupos) {
    const contenedor = $('#contenedorTareasReuniones');
    contenedor.empty();
    grupos.forEach(grupo => contenedor.append(crearGrupoHtml(grupo)));
}

function crearGrupoHtml(grupo) {
    const hoy      = obtenerFechaHoy();
    const esMes    = agrupacionActual === 'mes';

    // Determinar si el grupo es "vencido" o "hoy" según fecha
    let claseHeader = '';
    if (esMes) {
        // grup.nombre puede ser "Marzo 2026", "Pasadas" etc.
        const nLower = (grupo.nombre || '').toLowerCase();
        if (nLower.includes('vencid') || nLower.includes('pasad') || nLower.includes('anterior')) claseHeader = 'vencido';
        else if (nLower.includes('hoy') || nLower.includes('today')) claseHeader = 'hoy';
    }

    const grupoDiv = $('<div class="grupo-container"></div>');

    const header = $(`
        <div class="grupo-header ${claseHeader}">
            <span>${grupo.nombre}</span>
            <span class="grupo-badge">${grupo.items.length} ${grupo.items.length === 1 ? 'item' : 'items'}</span>
        </div>
    `);

    const body = $('<div class="grupo-body"></div>');

    if (grupo.items.length === 0) {
        body.append('<p class="text-muted text-center mb-0" style="padding:14px;font-size:13px;">No hay tareas o reuniones en este grupo</p>');
    } else {
        grupo.items.forEach(item => {
            const itemEl = crearItemHtml(item, hoy);
            body.append(itemEl);
        });
    }

    // Zona de drop para este grupo
    body[0].dataset.fechaGrupo = grupo.fecha_referencia || '';
    habilitarDropZone(body[0]);

    grupoDiv.append(header);
    grupoDiv.append(body);
    return grupoDiv;
}

// ── Item Card ────────────────────────────────────────
function crearItemHtml(item, hoy) {
    const tipoIcono = item.tipo === 'reunion' ? 'bi-calendar-event' : 'bi-file-earmark-text';
    const tipoClase = item.tipo === 'reunion' ? 'reunion' : 'tarea';
    const progreso  = Math.round(item.progreso || 0);

    // Color de fecha
    const fechaRef  = item.tipo === 'reunion' ? (item.fecha_reunion || '').substring(0,10) : (item.fecha_meta || '');
    let claseCard = '';
    let claseFecha = '';
    if (fechaRef && item.estado !== 'finalizado' && item.estado !== 'cancelado') {
        if (fechaRef < hoy)       { claseCard = 'vencida'; claseFecha = 'fecha-vencida'; }
        else if (fechaRef === hoy) { claseCard = 'hoy';    claseFecha = 'fecha-hoy'; }
        else {
            const diff = diasDiff(hoy, fechaRef);
            if (diff <= 3) claseFecha = 'fecha-proxima';
        }
    }

    // Acciones
    const accionesHtml = crearAccionesHtml(item);

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
                    <span>${item.tipo === 'reunion' ? formatearFechaHora(item.fecha_reunion) : formatearFecha(item.fecha_meta)}</span>
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
                ${accionesHtml}
            </div>
        </div>
    `);

    // Drag & Drop — solo tareas no finalizadas/canceladas
    if (item.tipo === 'tarea' && item.estado !== 'finalizado' && item.estado !== 'cancelado') {
        habilitarDrag(card[0], item);
    }

    return card;
}

// ── Acciones ─────────────────────────────────────────
function crearAccionesHtml(item) {
    let html = '';

    // Ver siempre
    html += `
        <button class="btn-icon-only btn-ver" onclick="verDetalle(${item.id})" data-tip="Ver detalle">
            <i class="bi bi-eye"></i>
        </button>
    `;

    // Finalizar — solo tareas en_progreso asignadas a mí
    const esTarea       = item.tipo === 'tarea';
    const esAsignado    = item.cod_cargo_asignado == cargoActual;
    const puedeFinalizar= esTarea && item.estado === 'en_progreso' && esAsignado;

    html += `
        <button class="btn-icon-only btn-finalizar"
                onclick="finalizarTareaManualPanel(${item.id}, ${item.total_subtareas})"
                data-tip="Finalizar tarea"
                ${puedeFinalizar ? '' : 'style="visibility:hidden;pointer-events:none;"'}>
            <i class="bi bi-check-circle-fill"></i>
        </button>
    `;

    // Posponer — solo tareas activas
    const puedePosponer = esTarea && item.estado !== 'finalizado' && item.estado !== 'cancelado';
    html += `
        <button class="btn-icon-only btn-posponer"
                onclick="abrirPosponerFecha(${item.id}, '${item.fecha_meta}')"
                data-tip="Posponer fecha"
                ${puedePosponer ? '' : 'style="visibility:hidden;pointer-events:none;"'}>
            <i class="bi bi-calendar-plus"></i>
        </button>
    `;

    // Cancelar
    const puedeCancel   = permisoCancelar || item.cod_operario_creador == cargoActual;
    const mostrarCancel = puedeCancel && item.estado !== 'cancelado' && item.estado !== 'finalizado';
    html += `
        <button class="btn-icon-only btn-cancelar"
                onclick="cancelarItem(${item.id}, '${item.tipo}')"
                data-tip="Cancelar"
                ${mostrarCancel ? '' : 'style="visibility:hidden;pointer-events:none;"'}>
            <i class="bi bi-x-circle-fill"></i>
        </button>
    `;

    return html;
}

// ── Drag & Drop ──────────────────────────────────────
function habilitarDrag(cardEl, item) {
    cardEl.setAttribute('draggable', 'true');

    cardEl.addEventListener('dragstart', function (e) {
        itemDragId         = item.id;
        itemDragFechaOrigen = item.fecha_meta;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(item.id));
        cardEl.classList.add('dragging');
    });

    cardEl.addEventListener('dragend', function () {
        cardEl.classList.remove('dragging');
        document.querySelectorAll('.grupo-body.drop-active').forEach(z => z.classList.remove('drop-active'));
    });
}

function habilitarDropZone(bodyEl) {
    bodyEl.addEventListener('dragover', function (e) {
        if (!itemDragId) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        document.querySelectorAll('.grupo-body.drop-active').forEach(z => z.classList.remove('drop-active'));
        bodyEl.classList.add('drop-active');
    });

    bodyEl.addEventListener('dragleave', function (e) {
        if (!bodyEl.contains(e.relatedTarget)) {
            bodyEl.classList.remove('drop-active');
        }
    });

    bodyEl.addEventListener('drop', function (e) {
        e.preventDefault();
        bodyEl.classList.remove('drop-active');

        if (!itemDragId) return;

        const fechaDestino = bodyEl.dataset.fechaGrupo || '';
        if (!fechaDestino) {
            abrirPosponerFecha(itemDragId, itemDragFechaOrigen);
            itemDragId = null;
            return;
        }

        // Validar que la fecha destino no sea pasada
        const hoy = obtenerFechaHoy();
        if (fechaDestino < hoy) {
            Swal.fire('No permitido', 'No puedes mover tareas a fechas pasadas. Solo puedes reagendar a hoy o días futuros.', 'warning');
            itemDragId = null;
            return;
        }

        posponerTareaAjax(itemDragId, fechaDestino);
        itemDragId = null;
    });
}

// ── Posponer fecha ───────────────────────────────────
function abrirPosponerFecha(id, fechaActual) {
    $('#posponerItemId').val(id);

    const hoy  = obtenerFechaHoy();
    const min  = hoy; // no permite pasadas

    const inputFecha = $('#posponerFechaInput');
    inputFecha.attr('min', min);

    // Sugerir mañana si la fecha actual ya pasó
    const sugerida = fechaActual >= hoy ? fechaActual : obtenerFechaManana();
    inputFecha.val(sugerida);

    // Botones rápidos
    const manana    = offsetFecha(hoy, 1);
    const en2dias   = offsetFecha(hoy, 2);
    const enSemana  = offsetFecha(hoy, 7);
    const en15dias  = offsetFecha(hoy, 15);

    $('#btnPosponerManana').text('Mañana — ' + formatearFecha(manana)).off('click').on('click', () => seleccionarFechaRapida(manana));
    $('#btnPosponer2dias' ).text('+2 días — '  + formatearFecha(en2dias )).off('click').on('click', () => seleccionarFechaRapida(en2dias));
    $('#btnPosponerSemana').text('+1 semana — '+ formatearFecha(enSemana )).off('click').on('click', () => seleccionarFechaRapida(enSemana));
    $('#btnPosponer15dias').text('+15 días — ' + formatearFecha(en15dias)).off('click').on('click', () => seleccionarFechaRapida(en15dias));

    // Limpiar selección previa
    $('.fecha-quick-btn').removeClass('selected');

    modalPosponerFecha.show();
}

function seleccionarFechaRapida(fecha) {
    $('#posponerFechaInput').val(fecha);
    $('.fecha-quick-btn').removeClass('selected');
    event.currentTarget.classList.add('selected');
}

function confirmarPosponerFecha() {
    const id    = $('#posponerItemId').val();
    const fecha = $('#posponerFechaInput').val();
    const hoy   = obtenerFechaHoy();

    if (!fecha) { Swal.fire('Error', 'Selecciona una fecha', 'error'); return; }
    if (fecha < hoy) { Swal.fire('No permitido', 'No puedes posponer a una fecha pasada', 'warning'); return; }

    posponerTareaAjax(id, fecha);
    modalPosponerFecha.hide();
}

function posponerTareaAjax(id, nuevaFecha) {
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_posponer.php',
        method: 'POST',
        data: { id: id, nueva_fecha: nuevaFecha },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Fecha actualizada',
                    text: `Tarea reagendada para el ${formatearFecha(nuevaFecha)}`,
                    timer: 2000,
                    showConfirmButton: false
                });
                cargarDatos();
            } else {
                Swal.fire('Error', response.message || 'No se pudo reagendar', 'error');
            }
        },
        error: function () {
            Swal.fire('Error', 'No se pudo reagendar la tarea', 'error');
        }
    });
}

// ── Ver detalle ──────────────────────────────────────
function verDetalle(id) {
    window.location.href = `gestion_tareas_reuniones_detalle.php?id=${id}`;
}

// ── Cancelar ─────────────────────────────────────────
function cancelarItem(id, tipo) {
    const tipoTexto = tipo === 'reunion' ? 'reunión' : 'tarea';
    Swal.fire({
        title: `¿Cancelar ${tipoTexto}?`,
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#78909c',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No'
    }).then(result => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/gestion_tareas_reuniones_cancelar.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire({ icon:'success', title:'Cancelado', text: response.message, timer:1800, showConfirmButton:false });
                        cargarDatos();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function () { Swal.fire('Error', 'No se pudo cancelar', 'error'); }
            });
        }
    });
}

// ── Finalizar ────────────────────────────────────────
function finalizarTareaManualPanel(id, totalSubtareas) {
    if (parseInt(totalSubtareas) > 0) {
        Swal.fire({
            icon: 'info',
            title: 'Subtareas pendientes',
            text: 'Esta tarea tiene subtareas. Finaliza todas las subtareas para completar la tarea principal automáticamente.',
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

    if (!detalles) { Swal.fire('Error', 'Debes ingresar los detalles de finalización', 'error'); return; }

    const formData = new FormData();
    formData.append('id', idItem);
    formData.append('detalles', detalles);

    const archivos = $('#archivosFinalizacionTarea')[0].files;
    for (let i = 0; i < archivos.length; i++) { formData.append('archivos[]', archivos[i]); }

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_finalizar.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                Swal.fire({ icon:'success', title:'¡Completado!', text:'Tarea finalizada correctamente', timer:2000, showConfirmButton:false });
                modalFinalizarTarea.hide();
                cargarDatos();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function () { Swal.fire('Error', 'No se pudo completar la finalización', 'error'); }
    });
}

// ── Guardar tarea ────────────────────────────────────
function guardarTarea(tipo) {
    const formId = tipo === 'crear' ? '#formNuevaTarea' : '#formSolicitarTarea';
    const form   = $(formId)[0];
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const formData = new FormData(form);
    formData.append('tipo', tipo);

    const archivosInput = tipo === 'crear' ? '#archivosTarea' : '#archivosTareaSolicitud';
    const archivos = $(archivosInput)[0].files;
    for (let i = 0; i < archivos.length; i++) {
        if (archivos[i].size > 10 * 1024 * 1024) { Swal.fire('Error', `El archivo ${archivos[i].name} excede el límite de 10MB`, 'error'); return; }
    }

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_guardar.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                Swal.fire({ icon:'success', title:'Éxito', text:response.message, timer:2000, showConfirmButton:false });
                tipo === 'crear' ? modalNuevaTarea.hide() : modalSolicitarTarea.hide();
                cargarDatos();
            } else {
                Swal.fire('Error', response.message, 'error');
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
    if (invitados.length === 0) { Swal.fire('Error', 'Debe seleccionar al menos un invitado', 'error'); return; }

    const formData = new FormData(form);
    formData.append('invitados', JSON.stringify(invitados));

    const archivos = $('#archivosReunion')[0].files;
    for (let i = 0; i < archivos.length; i++) {
        if (archivos[i].size > 10 * 1024 * 1024) { Swal.fire('Error', `El archivo ${archivos[i].name} excede el límite de 10MB`, 'error'); return; }
    }

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_guardar_reunion.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                Swal.fire({ icon:'success', title:'Éxito', text:response.message, timer:2000, showConfirmButton:false });
                modalNuevaReunion.hide();
                cargarDatos();
            } else {
                Swal.fire('Error', response.message, 'error');
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
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },
        buttonText: { today: 'Hoy' },
        firstDay: 1,
        editable: true,           // habilita drag dentro del calendario
        droppable: true,          // acepta drops externos
        eventDrop: function (info) {
            const hoy = obtenerFechaHoy();
            const nuevaFecha = info.event.startStr.substring(0, 10);
            if (nuevaFecha < hoy) {
                Swal.fire('No permitido', 'No puedes mover tareas a fechas pasadas', 'warning');
                info.revert();
                return;
            }
            const itemId = info.event.extendedProps.itemId;
            posponerTareaAjax(itemId, nuevaFecha);
        },
        events: function (fetchInfo, successCallback, failureCallback) {
            $.ajax({
                url: 'ajax/gestion_tareas_reuniones_get_calendario.php',
                method: 'GET',
                data: { start: fetchInfo.startStr, end: fetchInfo.endStr },
                dataType: 'json',
                success: function (response) { successCallback(response); },
                error: function ()            { successCallback([]); }
            });
        },
        eventClick: function (info) {
            const itemId = info.event.extendedProps.itemId;
            if (itemId) verDetalle(itemId);
        },
        eventDidMount: function (info) {
            const props = info.event.extendedProps;
            info.el.style.setProperty('--event-status-color', info.event.backgroundColor);
            let tip = info.event.title;
            if (props.descripcion) {
                const tmp = document.createElement('div');
                tmp.innerHTML = props.descripcion;
                const plain = (tmp.textContent || tmp.innerText || '').trim();
                if (plain) tip += `\n\n${plain.substring(0, 200)}${plain.length > 200 ? '…' : ''}`;
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

// ── Estado vacío ──────────────────────────────────────
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
function obtenerFechaHoy() {
    return new Date().toISOString().split('T')[0];
}
function obtenerFechaManana() {
    const d = new Date(); d.setDate(d.getDate() + 1);
    return d.toISOString().split('T')[0];
}
function obtenerFechaHoraManana() {
    const d = new Date(); d.setDate(d.getDate() + 1); d.setHours(9,0,0,0);
    return d.toISOString().slice(0, 16);
}
function offsetFecha(base, dias) {
    const d = new Date(base + 'T00:00:00');
    d.setDate(d.getDate() + dias);
    return d.toISOString().split('T')[0];
}
function diasDiff(desde, hasta) {
    const a = new Date(desde + 'T00:00:00');
    const b = new Date(hasta + 'T00:00:00');
    return Math.round((b - a) / 86400000);
}
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const f = new Date(fecha + 'T00:00:00');
    const m = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    return `${f.getDate().toString().padStart(2,'0')}-${m[f.getMonth()]}-${f.getFullYear().toString().substr(2)}`;
}
function formatearFechaHora(fechaHora) {
    if (!fechaHora) return '-';
    const f = new Date(fechaHora);
    const m = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    return `${f.getDate().toString().padStart(2,'0')}-${m[f.getMonth()]}-${f.getFullYear().toString().substr(2)} ${f.getHours().toString().padStart(2,'0')}:${f.getMinutes().toString().padStart(2,'0')}`;
}
function formatearEstado(estado) {
    return { solicitado:'Solicitado', en_progreso:'En Progreso', finalizado:'Finalizado', cancelado:'Cancelado' }[estado] || estado;
}
function crearAvatarHtml(item) {
    if (item.avatar_url) {
        return `<img src="${item.avatar_url}" class="avatar-responsable-small" title="${escapeHtml(item.nombre_responsable || '')}">`;
    }
    const ini = obtenerIniciales(item.nombre_responsable || 'NN');
    return `<div class="avatar-placeholder-small" title="${escapeHtml(item.nombre_responsable || '')}">${ini}</div>`;
}
function obtenerIniciales(nombre) {
    const p = nombre.trim().split(' ');
    return p.length >= 2 ? (p[0][0] + p[1][0]).toUpperCase() : nombre.substr(0,2).toUpperCase();
}
function stripHtml(html) {
    if (!html) return '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}
function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, function(m) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
    });
}