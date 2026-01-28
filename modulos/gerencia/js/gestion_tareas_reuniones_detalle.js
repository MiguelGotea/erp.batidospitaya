// =====================================================
// JavaScript para Página de Detalle
// =====================================================

let modalSubtarea;
let modalVerSubtarea;
let modalFinalizarTarea;
let quillEditor;

// Inicializar
$(document).ready(function () {
    // Inicializar modales
    modalSubtarea = new bootstrap.Modal(document.getElementById('modalSubtarea'));
    modalVerSubtarea = new bootstrap.Modal(document.getElementById('modalVerSubtarea'));
    modalFinalizarTarea = new bootstrap.Modal(document.getElementById('modalFinalizarTarea'));

    // Inicializar editor Quill si es reunión y es creador
    if (tipoItem === 'reunion' && esCreador) {
        quillEditor = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'color': [] }, { 'background': [] }],
                    ['clean']
                ]
            }
        });
    }

    // Cargar datos iniciales
    cargarArchivosItem();
    cargarProgreso();

    if (tipoItem === 'tarea') {
        cargarSubtareas();
    }

    cargarComentarios();

    if (tipoItem === 'reunion') {
        cargarResumen();
    }

    if (estadoItem === 'finalizado' && tipoItem === 'tarea') {
        cargarArchivosFinalizacion();
    }
});

// Cargar archivos del item
function cargarArchivosItem() {
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_archivos.php',
        method: 'POST',
        data: { id_item: idItem, tipo_vinculo: 'item' },
        dataType: 'json',
        success: function (response) {
            if (response.success && response.archivos.length > 0) {
                let html = '<label class="fw-bold">Archivos Adjuntos:</label><div class="comentario-archivos">';
                response.archivos.forEach(archivo => {
                    html += `
                        <a href="${archivo.ruta_archivo}" target="_blank" class="archivo-adjunto">
                            <i class="bi bi-file-earmark"></i>
                            ${archivo.nombre_archivo}
                        </a>
                    `;
                });
                html += '</div>';
                $('#archivosItem').html(html);
            }
        }
    });
}

// Cargar progreso
function cargarProgreso() {
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_progreso.php',
        method: 'POST',
        data: { id: idItem },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                renderizarProgreso(response.progreso, response.label);
            }
        }
    });
}

// Renderizar progreso
function renderizarProgreso(progreso, label) {
    const porcentaje = Math.round(progreso || 0);
    const radio = 40;
    const circunferencia = 2 * Math.PI * radio;
    const progresoVal = Math.min(Math.max(progreso || 0, 0), 100);
    const offset = circunferencia - (progresoVal / 100) * circunferencia;

    const html = `
        <div class="progreso-display">
            <div class="progreso-circular">
                <svg width="100" height="100" viewBox="0 0 100 100">
                    <circle class="progreso-circular-bg" cx="50" cy="50" r="${radio}"></circle>
                    <circle class="progreso-circular-fill ${porcentaje >= 100 ? 'completo' : ''}" 
                            cx="50" cy="50" r="${radio}"
                            style="stroke-dasharray: ${circunferencia}; stroke-dashoffset: ${offset}"></circle>
                </svg>
                <div class="progreso-texto">${porcentaje}%</div>
            </div>
            <div class="progreso-label">${label || ''}</div>
        </div>
    `;

    $('#progresoContainer').html(html);
}

// ========== SUBTAREAS ==========

// Cargar subtareas
function cargarSubtareas() {
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_subtareas.php',
        method: 'POST',
        data: { id_padre: idItem },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                renderizarSubtareas(response.subtareas);
                $('#badge-subtareas').text(response.subtareas.length);
            } else {
                $('#listaSubtareas').html('<p class="text-muted text-center">No hay subtareas</p>');
                $('#badge-subtareas').text('0');
            }
        }
    });
}

// Renderizar subtareas
function renderizarSubtareas(subtareas) {
    const container = $('#listaSubtareas');
    container.empty();

    if (subtareas.length === 0) {
        container.html(`
            <div class="estado-vacio-small">
                <i class="bi bi-list-check"></i>
                <p>No hay subtareas creadas</p>
            </div>
        `);
        return;
    }

    subtareas.forEach(subtarea => {
        const item = $(`
            <div class="subtarea-item-row ${subtarea.estado === 'finalizado' ? 'finalizada' : ''}">
                <div class="subtarea-main">
                    <div class="subtarea-status-icon">
                        <i class="bi ${subtarea.estado === 'finalizado' ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted'}"></i>
                    </div>
                    <div class="subtarea-text">
                        <div class="subtarea-titulo" onclick="verDetalleSubtarea(${subtarea.id})" style="cursor:pointer; color: var(--color-primario);">
                            ${escapeHtml(subtarea.titulo)}
                            <i class="bi bi-info-circle small ms-1 text-muted"></i>
                        </div>
                    </div>
                </div>

                <div class="subtarea-meta-row">
                    <div class="subtarea-meta-col">
                        <i class="bi bi-calendar-check me-1"></i>
                        <span>${formatearFecha(subtarea.fecha_meta)}</span>
                    </div>
                    <div class="subtarea-meta-col">
                        <span class="badge-estado-small ${subtarea.estado}">${formatearEstado(subtarea.estado)}</span>
                    </div>
                </div>

                <div class="subtarea-acciones-row">
                    ${subtarea.estado !== 'finalizado' && esAsignado && estadoItem === 'en_progreso' ? `
                        <button class="btn-icon-subtarea btn-finalizar" onclick="finalizarSubtarea(${subtarea.id})" title="Finalizar subtarea">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button class="btn-icon-subtarea btn-eliminar" onclick="eliminarSubtarea(${subtarea.id})" title="Eliminar subtarea">
                            <i class="bi bi-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
        `);

        container.append(item);
    });
}

// Abrir modal subtarea
function abrirModalSubtarea() {
    $('#formSubtarea')[0].reset();
    modalSubtarea.show();
}

// Guardar subtarea
function guardarSubtarea() {
    const form = $('#formSubtarea')[0];

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData();
    formData.append('id_padre', idItem);
    formData.append('titulo', $('#tituloSubtarea').val());
    formData.append('descripcion', $('#descripcionSubtarea').val());
    formData.append('fecha_meta', $('#fechaMetaSubtarea').val());

    // Archivos
    const archivos = $('#archivosSubtarea')[0].files;
    for (let i = 0; i < archivos.length; i++) {
        if (archivos[i].size > 10 * 1024 * 1024) {
            Swal.fire('Error', 'Archivo excede 10MB', 'error');
            return;
        }
        formData.append('archivos[]', archivos[i]);
    }

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_guardar_subtarea.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                Swal.fire('Éxito', response.message, 'success');
                modalSubtarea.hide();
                cargarSubtareas();
                cargarProgreso();
                // Actualizar fecha meta si cambió
                actualizarFechaMeta();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }
    });
}

// Actualizar fecha meta en la página
function actualizarFechaMeta() {
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_item.php',
        method: 'POST',
        data: { id: idItem },
        dataType: 'json',
        success: function (response) {
            if (response.success && response.item.fecha_meta) {
                const fechaFormateada = formatearFecha(response.item.fecha_meta);
                $('#fechaMetaDisplay').text(fechaFormateada);
            }
        }
    });
}

// Finalizar subtarea
function finalizarSubtarea(id) {
    Swal.fire({
        title: 'Finalizar Subtarea',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label">Detalles de Finalización:</label>
                    <textarea id="detallesFinalizacion" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Archivos:</label>
                    <input type="file" id="archivosFinalizacion" class="form-control" 
                           multiple accept=".pdf,.jpg,.jpeg,.png">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Finalizar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('detalles', $('#detallesFinalizacion').val());

            const archivos = $('#archivosFinalizacion')[0].files;
            for (let i = 0; i < archivos.length; i++) {
                formData.append('archivos[]', archivos[i]);
            }

            return formData;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/gestion_tareas_reuniones_finalizar.php',
                method: 'POST',
                data: result.value,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire('Finalizado', response.message, 'success');
                        cargarSubtareas();
                        cargarProgreso();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }
    });
}

// Eliminar subtarea
function eliminarSubtarea(id) {
    Swal.fire({
        title: '¿Eliminar subtarea?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/gestion_tareas_reuniones_eliminar_subtarea.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire('Eliminado', response.message, 'success');
                        cargarSubtareas();
                        cargarProgreso();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }
    });
}

// ========== COMENTARIOS ==========

// Cargar comentarios
function cargarComentarios() {
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_comentarios.php',
        method: 'POST',
        data: { id_item: idItem },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                renderizarComentarios(response.comentarios);
                $('#badge-comentarios').text(response.comentarios.length);
            } else {
                $('#listaComentarios').html('<p class="text-muted text-center">No hay comentarios</p>');
                $('#badge-comentarios').text('0');
            }
        }
    });
}

// Renderizar comentarios
function renderizarComentarios(comentarios) {
    const container = $('#listaComentarios');
    container.empty();

    if (comentarios.length === 0) {
        container.html(`
            <div class="estado-vacio-small">
                <i class="bi bi-chat-left-text"></i>
                <p>No hay comentarios</p>
            </div>
        `);
        return;
    }

    comentarios.forEach(comentario => {
        let html = `
            <div class="comentario-item">
                <div class="comentario-header">
                    <span class="comentario-autor">${escapeHtml(comentario.nombre_operario)}</span>
                    <span class="comentario-fecha">${formatearFechaHora(comentario.fecha_creacion)}</span>
                </div>
                <div class="comentario-texto">${escapeHtml(comentario.comentario)}</div>
        `;

        if (comentario.archivos && comentario.archivos.length > 0) {
            html += '<div class="comentario-archivos">';
            comentario.archivos.forEach(archivo => {
                html += `
                    <a href="${archivo.ruta_archivo}" target="_blank" class="archivo-adjunto">
                        <i class="bi bi-file-earmark"></i>
                        ${archivo.nombre_archivo}
                    </a>
                `;
            });
            html += '</div>';
        }

        html += '</div>';
        container.append(html);
    });
}

// Agregar comentario
function agregarComentario() {
    const comentario = $('#nuevoComentario').val().trim();

    if (!comentario) {
        Swal.fire('Error', 'Escribe un comentario', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('id_item', idItem);
    formData.append('comentario', comentario);

    const archivos = $('#archivosComentario')[0].files;
    for (let i = 0; i < archivos.length; i++) {
        formData.append('archivos[]', archivos[i]);
    }

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_agregar_comentario.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                $('#nuevoComentario').val('');
                $('#archivosComentario').val('');
                cargarComentarios();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }
    });
}

// ========== REUNIÓN ==========

// Cargar resumen
function cargarResumen() {
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_item.php',
        method: 'POST',
        data: { id: idItem },
        dataType: 'json',
        success: function (response) {
            if (response.success && response.item.resumen_reunion) {
                if (quillEditor) {
                    quillEditor.root.innerHTML = response.item.resumen_reunion;
                } else {
                    $('#resumen-display').html(response.item.resumen_reunion);
                }
            }
        }
    });
}

// Guardar resumen
function guardarResumen() {
    const html = quillEditor.root.innerHTML;

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_guardar_resumen.php',
        method: 'POST',
        data: { id: idItem, resumen: html },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                Swal.fire('Guardado', response.message, 'success');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }
    });
}

// ========== ACCIONES ==========

// Aprobar tarea
function aprobarTarea() {
    Swal.fire({
        title: '¿Aprobar tarea?',
        text: 'La tarea pasará a estado En Progreso',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/gestion_tareas_reuniones_aprobar.php',
                method: 'POST',
                data: { id: idItem },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire('Aprobado', response.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }
    });
}

// Confirmar asistencia
function confirmarAsistencia(tipo) {
    const texto = tipo === 'asistire' ? 'Confirmar asistencia' : 'Confirmar inasistencia';

    Swal.fire({
        title: texto,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/gestion_tareas_reuniones_confirmar_asistencia.php',
                method: 'POST',
                data: { id_item: idItem, confirmacion: tipo },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire('Confirmado', response.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }
    });
}

// Cancelar item
function cancelarItem() {
    const tipo = tipoItem === 'reunion' ? 'reunión' : 'tarea';

    Swal.fire({
        title: `¿Cancelar ${tipo}?`,
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/gestion_tareas_reuniones_cancelar.php',
                method: 'POST',
                data: { id: idItem },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire('Cancelado', response.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }
    });
}

// Editar fecha meta
function editarFechaMeta() {
    Swal.fire({
        title: 'Editar Fecha Límite',
        html: '<input type="date" id="nuevaFechaMeta" class="form-control">',
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        preConfirm: () => {
            return $('#nuevaFechaMeta').val();
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            $.ajax({
                url: 'ajax/gestion_tareas_reuniones_actualizar_fecha.php',
                method: 'POST',
                data: { id: idItem, fecha_meta: result.value },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire('Guardado', response.message, 'success');
                        location.reload();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }
    });
}

// Ver detalle de subtarea
function verDetalleSubtarea(id) {
    $('#verSubtareaContenido').html('<div class="text-center p-4"><div class="spinner-border spinner-border-sm"></div></div>');
    modalVerSubtarea.show();

    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_subtarea_detalle.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                const sub = response.subtarea;
                let html = `
                    <div class="mb-3">
                        <label class="fw-bold small text-muted">Descripción:</label>
                        <p class="mb-0">${escapeHtml(sub.descripcion) || '<i class="text-muted">Sin descripción</i>'}</p>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="fw-bold small text-muted">Fecha Límite:</label>
                            <p class="mb-0">${formatearFecha(sub.fecha_meta)}</p>
                        </div>
                        <div class="col-6">
                            <label class="fw-bold small text-muted">Estado:</label>
                            <div><span class="badge-estado-small ${sub.estado}">${formatearEstado(sub.estado)}</span></div>
                        </div>
                    </div>
                `;

                if (sub.estado === 'finalizado') {
                    html += `
                        <div class="card bg-success bg-opacity-10 border-success border-opacity-25 mb-3">
                            <div class="card-body py-2">
                                <label class="fw-bold small text-success">Detalles de Finalización:</label>
                                <p class="mb-1 small">${escapeHtml(sub.detalles_finalizacion) || 'Finalizado sin detalles'}</p>
                                <div class="small text-muted">
                                    Por: ${escapeHtml(sub.nombre_finalizador)} ${escapeHtml(sub.apellido_finalizador)} el ${formatearFechaHora(sub.fecha_finalizacion)}
                                </div>
                            </div>
                        </div>
                    `;
                }

                if (response.archivos && response.archivos.length > 0) {
                    html += '<label class="fw-bold small text-muted">Archivos Adjuntos:</label><div class="comentario-archivos">';
                    response.archivos.forEach(archivo => {
                        html += `
                            <a href="${archivo.ruta_archivo}" target="_blank" class="archivo-adjunto">
                                <i class="bi bi-file-earmark"></i>
                                ${archivo.nombre_archivo}
                            </a>
                        `;
                    });
                    html += '</div>';
                }

                $('#verSubtareaTitulo').text(sub.titulo);
                $('#verSubtareaContenido').html(html);
            } else {
                $('#verSubtareaContenido').html(`<p class="text-danger">${response.message}</p>`);
            }
        }
    });
}

// Finalización manual de tarea (sin subtareas)
function finalizarManual() {
    $('#formFinalizarTarea')[0].reset();
    modalFinalizarTarea.show();
}

function confirmarFinalizarManual() {
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
                Swal.fire('Éxito', 'Tarea finalizada correctamente', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }
    });
}

// Cargar archivos de finalización para la pestaña
function cargarArchivosFinalizacion() {
    $.ajax({
        url: 'ajax/gestion_tareas_reuniones_get_archivos.php',
        method: 'POST',
        data: { id_item: idItem, tipo_vinculo: 'finalizacion' },
        dataType: 'json',
        success: function (response) {
            if (response.success && response.archivos.length > 0) {
                let html = '<label class="fw-bold small text-muted">Evidencias Adjuntas:</label><div class="comentario-archivos">';
                response.archivos.forEach(archivo => {
                    html += `
                        <a href="${archivo.ruta_archivo}" target="_blank" class="archivo-adjunto">
                            <i class="bi bi-file-earmark-check"></i>
                            ${archivo.nombre_archivo}
                        </a>
                    `;
                });
                html += '</div>';
                $('#archivosFinalizacion').html(html);
            }
        }
    });
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
