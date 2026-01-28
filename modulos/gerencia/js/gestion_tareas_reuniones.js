// =====================================================
// JavaScript para Panel de Gestión de Tareas y Reuniones
// =====================================================

let agrupacionActual = 'mes';
let modalNuevaTarea, modalSolicitarTarea, modalNuevaReunion, modalFinalizarTarea;
let cargosLiderazgo = [];
let calendar;

// Inicializar
$(document).ready(function () {
    // Inicializar modales
    modalNuevaTarea = new bootstrap.Modal(document.getElementById('modalNuevaTarea'));
    modalSolicitarTarea = new bootstrap.Modal(document.getElementById('modalSolicitarTarea'));
    modalNuevaReunion = new bootstrap.Modal(document.getElementById('modalNuevaReunion'));
    modalFinalizarTarea = new bootstrap.Modal(document.getElementById('modalFinalizarTarea'));

    // Cargar cargos de liderazgo
    cargarCargosLiderazgo();

    // Cargar datos iniciales
    cargarDatos();
});

// Cargar cargos del equipo de liderazgo
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

// Poblar selects de cargos
function poblarSelectsCargos() {
    const selectTarea = $('#cargoAsignadoTarea');
    const selectSolicitud = $('#cargoAsignadoTareaSolicitud');

    selectTarea.empty().append('<option value="">Seleccione un cargo...</option>');
    selectSolicitud.empty().append('<option value="">Seleccione un cargo...</option>');

    cargosLiderazgo.forEach(cargo => {
        const option = `<option value="${cargo.CodNivelesCargos}">${cargo.Nombre}</option>`;
        selectTarea.append(option);
        selectSolicitud.append(option);
    });
}

// Poblar lista de invitados para reuniones
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

        // Hacer que todo el div sea clickeable
        item.on('click', function (e) {
            if (e.target.tagName !== 'INPUT') {
                const checkbox = $(this).find('input[type="checkbox"]');
                checkbox.prop('checked', !checkbox.prop('checked'));
            }
        });

        lista.append(item);
    });
}

// Abrir modal nueva tarea
function abrirModalNuevaTarea() {
    $('#formNuevaTarea')[0].reset();
    $('#fechaMetaTarea').val(obtenerFechaManana());
    modalNuevaTarea.show();
}

// Abrir modal solicitar tarea
function abrirModalSolicitarTarea() {
    $('#formSolicitarTarea')[0].reset();
    $('#fechaMetaTareaSolicitud').val(obtenerFechaManana());
    modalSolicitarTarea.show();
}

// Abrir modal nueva reunión
function abrirModalNuevaReunion() {
    $('#formNuevaReunion')[0].reset();
    $('#fechaReunion').val(obtenerFechaHoraManana());
    // Desmarcar todos los checkboxes
    $('#listaInvitados input[type="checkbox"]').prop('checked', false);
    modalNuevaReunion.show();
}

// Obtener fecha de mañana
function obtenerFechaManana() {
    const manana = new Date();
    manana.setDate(manana.getDate() + 1);
    return manana.toISOString().split('T')[0];
}

// Obtener fecha y hora de mañana
function obtenerFechaHoraManana() {
    const manana = new Date();
    manana.setDate(manana.getDate() + 1);
    manana.setHours(9, 0, 0, 0);
    return manana.toISOString().slice(0, 16);
}

// Guardar tarea (crear o solicitar)
function guardarTarea(tipo) {
    const formId = tipo === 'crear' ? '#formNuevaTarea' : '#formSolicitarTarea';
    const form = $(formId)[0];

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    formData.append('tipo', tipo);

    // Validar archivos
    const archivosInput = tipo === 'crear' ? '#archivosTarea' : '#archivosTareaSolicitud';
    const archivos = $(archivosInput)[0].files;

    for (let i = 0; i < archivos.length; i++) {
        if (archivos[i].size > 10 * 1024 * 1024) {
            Swal.fire('Error', `El archivo ${archivos[i].name} excede el límite de 10MB`, 'error');
            return;
        }
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
                Swal.fire('Éxito', response.message, 'success');
                if (tipo === 'crear') {
                    modalNuevaTarea.hide();
                } else {
                    modalSolicitarTarea.hide();
                }
                cargarDatos();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function () {
            Swal.fire('Error', 'No se pudo guardar la tarea', 'error');
        }
    });
}

// Guardar reunión
function guardarReunion() {
    const form = $('#formNuevaReunion')[0];

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Obtener invitados seleccionados
    const invitados = [];
    $('#listaInvitados input[type="checkbox"]:checked').each(function () {
        invitados.push($(this).val());
    });

    if (invitados.length === 0) {
        Swal.fire('Error', 'Debe seleccionar al menos un invitado', 'error');
        return;
    }

    const formData = new FormData(form);
    formData.append('invitados', JSON.stringify(invitados));

    // Validar archivos
    const archivos = $('#archivosReunion')[0].files;
    for (let i = 0; i < archivos.length; i++) {
        if (archivos[i].size > 10 * 1024 * 1024) {
            Swal.fire('Error', `El archivo ${archivos[i].name} excede el límite de 10MB`, 'error');
            return;
        }
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
                Swal.fire('Éxito', response.message, 'success');
                modalNuevaReunion.hide();
                cargarDatos();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function () {
            Swal.fire('Error', 'No se pudo guardar la reunión', 'error');
        }
    });
}

// Cambiar agrupación
function cambiarAgrupacion(tipo) {
    agrupacionActual = tipo;

    // Actualizar botones
    $('.btn-group .btn').removeClass('active');
    $(`.btn-group .btn[data-agrupacion="${tipo}"]`).addClass('active');

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

// Inicializar Calendario - VERSIÓN CORREGIDA
function inicializarCalendario() {
    const calendarEl = document.getElementById('calendarioTareas');

    // Si ya existe, destruirlo antes de recrear para asegurar que tome nuevos eventos
    if (calendar) {
        calendar.destroy();
    }

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: '' // Oculto según requerimiento de diseño (solo mes)
        },
        buttonText: {
            today: 'Hoy'
        },
        firstDay: 1, // Lunes
        events: function (fetchInfo, successCallback, failureCallback) {
            $.ajax({
                url: 'ajax/gestion_tareas_reuniones_get_calendario.php',
                method: 'GET',
                data: {
                    start: fetchInfo.startStr,
                    end: fetchInfo.endStr
                },
                dataType: 'json',
                success: function (response) {
                    console.log('Eventos cargados:', response); // Para debugging
                    successCallback(response);
                },
                error: function (xhr, status, error) {
                    console.error('Error cargando eventos:', error);
                    successCallback([]); // Devolver array vacío en caso de error
                }
            });
        },
        eventClick: function (info) {
            // Extraer el ID numérico del prefijo (itemId provisto en extendedProps)
            const itemId = info.event.extendedProps.itemId;
            if (itemId) {
                verDetalle(itemId);
            }
        },
        eventDidMount: function (info) {
            const props = info.event.extendedProps;
            const eventColor = info.event.backgroundColor;

            // Inyectar color como variable CSS para el borde lateral
            info.el.style.setProperty('--event-status-color', eventColor);

            let tooltipText = `${info.event.title}`;

            if (props.descripcion) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = props.descripcion;
                const plainDesc = tempDiv.textContent || tempDiv.innerText || "";
                if (plainDesc.trim() !== "") {
                    tooltipText += `\n\n${plainDesc.substring(0, 300)}${plainDesc.length > 300 ? '...' : ''}`;
                }
            }

            info.el.setAttribute('title', tooltipText);
        },
        height: 'auto',
        navLinks: true,
        nowIndicator: true,
        editable: false,
        selectable: false,
        businessHours: {
            daysOfWeek: [1, 2, 3, 4, 5],
            startTime: '08:00',
            endTime: '18:00',
        },
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            meridiem: false
        }
    });

    calendar.render();
}

// Cargar datos
function cargarDatos() {
    if (agrupacionActual === 'calendario') {
        if (calendar) {
            calendar.refetchEvents();
        }
        return;
    }

    $('#contenedorTareasReuniones').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `);

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_datos.php',
        method: 'POST',
        data: {
            agrupacion: agrupacionActual
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
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

// Renderizar datos agrupados
function renderizarDatos(grupos) {
    const contenedor = $('#contenedorTareasReuniones');
    contenedor.empty();

    if (grupos.length === 0) {
        mostrarEstadoVacio();
        return;
    }

    grupos.forEach(grupo => {
        const grupoHtml = crearGrupoHtml(grupo);
        contenedor.append(grupoHtml);
    });
}

// Crear HTML de grupo
function crearGrupoHtml(grupo) {
    const grupoDiv = $('<div class="grupo-container"></div>');

    const header = $(`
        <div class="grupo-header">
            <span>${grupo.nombre}</span>
            <span class="grupo-badge">${grupo.items.length} ${grupo.items.length === 1 ? 'item' : 'items'}</span>
        </div>
    `);

    const body = $('<div class="grupo-body"></div>');

    if (grupo.items.length === 0) {
        body.append('<p class="text-muted text-center mb-0">No hay tareas o reuniones en este grupo</p>');
    } else {
        grupo.items.forEach(item => {
            const itemHtml = crearItemHtml(item);
            body.append(itemHtml);
        });
    }

    grupoDiv.append(header);
    grupoDiv.append(body);

    return grupoDiv;
}

// Crear HTML de item (tarea o reunión)
function crearItemHtml(item) {
    const tipoIcono = item.tipo === 'reunion' ? 'bi-calendar-event' : 'bi-file-earmark-text';
    const tipoClase = item.tipo === 'reunion' ? 'reunion' : 'tarea';
    const progreso = Math.round(item.progreso || 0);

    const card = $(`
        <div class="item-card-row" onclick="verDetalle(${item.id})">
            <div class="item-main-info">
                <div class="item-icon-small ${tipoClase}">
                    <i class="bi ${tipoIcono}"></i>
                </div>
                <div class="item-titulo-container">
                    <div class="item-titulo-text" title="${escapeHtml(item.titulo)}">${escapeHtml(item.titulo)}</div>
                </div>
            </div>
            
            <div class="item-meta-row">
                ${crearMetaHtml(item)}
            </div>

            <div class="item-responsable-container">
                ${crearAvatarHtml(item)}
            </div>

            <div class="item-progreso-row">
                ${crearProgresoHtml(item)}
            </div>

            <div class="item-acciones-row" onclick="event.stopPropagation()">
                ${crearAccionesHtml(item)}
            </div>
        </div>
    `);

    return card;
}

// Crear HTML de meta información
function crearMetaHtml(item) {
    let html = '';

    // Estado
    html += `
        <div class="item-meta-col state">
            <span class="badge-estado ${item.estado}">${formatearEstado(item.estado)}</span>
        </div>
    `;

    // Fecha
    if (item.tipo === 'reunion') {
        html += `
            <div class="item-meta-col date">
                <i class="bi bi-calendar3 me-1"></i>
                <span>${formatearFechaHora(item.fecha_reunion)}</span>
            </div>
        `;
    } else {
        html += `
            <div class="item-meta-col date">
                <i class="bi bi-calendar-check me-1"></i>
                <span>${formatearFecha(item.fecha_meta)}</span>
            </div>
        `;
    }

    return html;
}

// Crear HTML de avatar
function crearAvatarHtml(item) {
    if (item.avatar_url) {
        return `<img src="${item.avatar_url}" class="avatar-responsable-small" title="${escapeHtml(item.nombre_responsable || '')}" data-bs-toggle="tooltip">`;
    } else {
        const iniciales = obtenerIniciales(item.nombre_responsable || 'NN');
        return `<div class="avatar-placeholder-small" title="${escapeHtml(item.nombre_responsable || '')}" data-bs-toggle="tooltip">${iniciales}</div>`;
    }
}

// Crear HTML de progreso
function crearProgresoHtml(item) {
    const progreso = Math.round(item.progreso || 0);
    return `
        <div class="progreso-compacto">
            <div class="progreso-barra-small">
                <div class="progreso-fill-small ${progreso >= 100 ? 'completo' : ''}" style="width: ${progreso}%"></div>
            </div>
            <span class="progreso-porcentaje">${progreso}%</span>
        </div>
    `;
}

// Crear HTML de acciones
function crearAccionesHtml(item) {
    let html = '';

    // Botón ver (siempre visible)
    html += `
        <button class="btn-icon-only btn-ver" onclick="verDetalle(${item.id})" title="Ver detalle">
            <i class="bi bi-eye"></i>
        </button>
    `;

    // Botón finalizar (solo para tareas en progreso y asignadas)
    const esTarea = item.tipo === 'tarea';
    const esAsignado = item.cod_cargo_asignado == cargoActual;
    const mostrarFinalizar = esTarea && item.estado === 'en_progreso' && esAsignado;

    html += `
        <button class="btn-icon-only btn-success btn-finalizar-panel" 
                onclick="finalizarTareaManualPanel(${item.id}, ${item.total_subtareas})" 
                title="Finalizar tarea"
                style="color: #28a745; background-color: rgba(40, 167, 69, 0.1); ${mostrarFinalizar ? '' : 'visibility: hidden; pointer-events: none;'}">
            <i class="bi bi-check-circle"></i>
        </button>
    `;

    // Botón cancelar (solo si tiene permiso o es el creador)
    const puedeCancel = permisoCancelar || item.cod_operario_creador == cargoActual;
    const mostrarCancelar = puedeCancel && item.estado !== 'cancelado' && item.estado !== 'finalizado';

    html += `
        <button class="btn-icon-only btn-cancelar" 
                onclick="cancelarItem(${item.id}, '${item.tipo}')" 
                title="Cancelar"
                style="${mostrarCancelar ? '' : 'visibility: hidden; pointer-events: none;'}">
            <i class="bi bi-x-circle"></i>
        </button>
    `;

    return html;
}

// Ver detalle
function verDetalle(id) {
    window.location.href = `gestion_tareas_reuniones_detalle.php?id=${id}`;
}

// Cancelar item
function cancelarItem(id, tipo) {
    const tipoTexto = tipo === 'reunion' ? 'reunión' : 'tarea';

    Swal.fire({
        title: `¿Cancelar ${tipoTexto}?`,
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/gestion_tareas_reuniones_cancelar.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire('Cancelado', response.message, 'success');
                        cargarDatos();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'No se pudo cancelar', 'error');
                }
            });
        }
    });
}

// Finalización manual desde el panel
function finalizarTareaManualPanel(id, totalSubtareas) {
    if (parseInt(totalSubtareas) > 0) {
        Swal.fire({
            title: 'Subtareas pendientes',
            text: 'Esta tarea tiene subtareas. Debes finalizar todas las subtareas para que la tarea principal se complete automáticamente.',
            icon: 'info'
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

    if (!detalles) {
        Swal.fire('Error', 'Debes ingresar los detalles de finalización', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('id', idItem);
    formData.append('detalles', detalles);

    const archivos = $('#archivosFinalizacionTarea')[0].files;
    for (let i = 0; i < archivos.length; i++) {
        formData.append('archivos[]', archivos[i]);
    }

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_finalizar.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                Swal.fire('Éxito', 'Tarea finalizada correctamente', 'success');
                modalFinalizarTarea.hide();
                cargarDatos();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function () {
            Swal.fire('Error', 'No se pudo completar la finalización', 'error');
        }
    });
}

// Mostrar estado vacío
function mostrarEstadoVacio() {
    $('#contenedorTareasReuniones').html(`
        <div class="estado-vacio">
            <i class="bi bi-inbox"></i>
            <h5>No hay tareas o reuniones</h5>
            <p>Comienza creando una nueva tarea o reunión</p>
        </div>
    `);
}

// Utilidades
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const f = new Date(fecha + 'T00:00:00');
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `${f.getDate().toString().padStart(2, '0')}-${meses[f.getMonth()]}-${f.getFullYear().toString().substr(2)}`;
}

function formatearFechaHora(fechaHora) {
    if (!fechaHora) return '-';
    const f = new Date(fechaHora);
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const fecha = `${f.getDate().toString().padStart(2, '0')}-${meses[f.getMonth()]}-${f.getFullYear().toString().substr(2)}`;
    const hora = `${f.getHours().toString().padStart(2, '0')}:${f.getMinutes().toString().padStart(2, '0')}`;
    return `${fecha} ${hora}`;
}

function formatearEstado(estado) {
    const estados = {
        'solicitado': 'Solicitado',
        'en_progreso': 'En Progreso',
        'finalizado': 'Finalizado',
        'cancelado': 'Cancelado'
    };
    return estados[estado] || estado;
}

function obtenerIniciales(nombre) {
    const partes = nombre.trim().split(' ');
    if (partes.length >= 2) {
        return (partes[0][0] + partes[1][0]).toUpperCase();
    }
    return nombre.substr(0, 2).toUpperCase();
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
