// js/programacion_solicitudes.js - Archivo completo

let draggedTicket = null;
let resizing = null;

// ==================== RENDERIZADO Y EMPAQUETADO ====================

// También modifica la función renderizarCronograma para ser más robusta
function renderizarCronograma() {
    // Procesar cada equipo de trabajo
    Object.keys(ticketsPorEquipo).forEach(equipo => {
        const tickets = ticketsPorEquipo[equipo];
        if (!tickets || tickets.length === 0) return;
        
        // Inicializar matriz de ocupación para este equipo
        const matrizOcupacion = [];
        const posiciones = [];
        
        // Procesar cada ticket
        tickets.forEach(ticket => {
            const posicion = calcularPosicion(ticket, matrizOcupacion);
            if (posicion) {
                posiciones.push({
                    ticket: ticket,
                    fila: posicion.fila,
                    diaInicio: posicion.diaInicio,
                    numDias: posicion.numDias
                });
            }
        });
        
        // Renderizar tickets en el DOM
        posiciones.forEach(pos => {
            renderizarTicket(pos.ticket, pos.fila, pos.diaInicio, pos.numDias, equipo);
        });
        
        // Ajustar altura de las celdas del equipo
        ajustarAlturaCeldas(equipo, matrizOcupacion.length);
    });
}

function calcularPosicion(ticket, matrizOcupacion) {
    // Calcular índice de día de inicio
    let diaInicio = fechasSemana.indexOf(ticket.fecha_inicio);
    
    if (diaInicio === -1) {
        // El ticket empieza antes de esta semana
        const fechaInicioSemana = new Date(fechasSemana[0]);
        const fechaInicioTicket = new Date(ticket.fecha_inicio);
        if (fechaInicioTicket < fechaInicioSemana) {
            diaInicio = 0; // Empezar desde lunes
        } else {
            return null;
        }
    }
    
    return calcularPosicionDesdeInicio(ticket, diaInicio, matrizOcupacion);
}

function calcularPosicionDesdeInicio(ticket, diaInicio, matrizOcupacion) {
    // Calcular cuántos días abarca el ticket en esta semana
    const fechaInicio = new Date(ticket.fecha_inicio);
    const fechaFinal = new Date(ticket.fecha_final);
    const fechaFinSemana = new Date(fechasSemana[fechasSemana.length - 1]);
    
    // Limitar al fin de semana si se extiende más allá
    const fechaFinalVisible = fechaFinal > fechaFinSemana ? fechaFinSemana : fechaFinal;
    
    // Calcular días desde el inicio visible
    const inicioVisible = diaInicio === 0 && fechaInicio < new Date(fechasSemana[0]) 
        ? new Date(fechasSemana[0]) 
        : new Date(fechasSemana[diaInicio]);
    
    const numDias = Math.ceil((fechaFinalVisible - inicioVisible) / (1000 * 60 * 60 * 24)) + 1;
    
    // Limitar a los días disponibles en la semana
    const diasDisponibles = fechasSemana.length - diaInicio;
    const diasReales = Math.min(numDias, diasDisponibles);
    
    // Buscar la primera fila donde cabe el ticket
    let filaEncontrada = -1;
    
    for (let fila = 0; fila < matrizOcupacion.length; fila++) {
        if (cabEnFila(matrizOcupacion[fila], diaInicio, diasReales)) {
            filaEncontrada = fila;
            break;
        }
    }
    
    // Si no cabe en ninguna fila, crear una nueva
    if (filaEncontrada === -1) {
        matrizOcupacion.push(new Array(fechasSemana.length).fill(false));
        filaEncontrada = matrizOcupacion.length - 1;
    }
    
    // Marcar los días como ocupados
    for (let i = 0; i < diasReales; i++) {
        matrizOcupacion[filaEncontrada][diaInicio + i] = true;
    }
    
    return {
        fila: filaEncontrada,
        diaInicio: diaInicio,
        numDias: diasReales
    };
}

function cabEnFila(fila, diaInicio, numDias) {
    for (let i = 0; i < numDias; i++) {
        if (fila[diaInicio + i]) {
            return false;
        }
    }
    return true;
}

function renderizarTicket(ticket, fila, diaInicio, numDias, equipo) {
    // Obtener la celda correspondiente
    const row = document.querySelector(`tr[data-equipo="${equipo}"]`);
    if (!row) return;
    
    const celdas = row.querySelectorAll('.calendar-cell');
    const celdaInicio = celdas[diaInicio];
    if (!celdaInicio) return;
    
    // Calcular ancho
    const anchoCelda = celdaInicio.offsetWidth;
    const anchoCard = (anchoCelda * numDias) + (1 * (numDias - 1)) - 10;
    
    // Calcular posición vertical
    const top = (fila * 60) + 5;
    
    // Color de urgencia
    const coloresUrgencia = {
        1: '#28a745',
        2: '#ffc107',
        3: '#fd7e14',
        4: '#dc3545'
    };
    const colorUrgencia = coloresUrgencia[ticket.nivel_urgencia] || '#8b8b8bff';
    
    // Crear elemento
    const card = document.createElement('div');
    card.className = 'ticket-card';
    card.draggable = true;
    card.dataset.ticketId = ticket.id;
    card.dataset.fechaInicio = ticket.fecha_inicio;
    card.dataset.fechaFinal = ticket.fecha_final;
    card.dataset.tipoFormulario = ticket.tipo_formulario;
    
    card.style.position = 'absolute';
    card.style.top = top + 'px';
    card.style.left = '5px';
    card.style.width = anchoCard + 'px';
    card.style.height = '55px';
    card.style.backgroundColor = 'white';
    card.style.border = '1px solid #ddd';
    card.style.borderRadius = '4px';
    card.style.padding = '0.4rem 0.5rem';
    card.style.cursor = 'move';
    card.style.boxSizing = 'border-box';
    card.style.overflow = 'hidden';
    
    card.innerHTML = `
        <div style="position: relative; height: 100%;">
            <div style="font-size: 0.8rem; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 25px;">
                ${ticket.titulo}
            </div>
            <div style="font-size: 0.7rem; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 25px;">
                ${ticket.nombre_sucursal}
            </div>
            
            <button class="btn-desprogramar" onclick="desprogramarTicket(${ticket.id}, event)" title="Desprogramar">
                <i class="bi bi-x"></i>
            </button>
            
            <button class="btn-colaboradores" onclick="mostrarColaboradores(${ticket.id}, event)" title="Asignar colaboradores">
                <i class="bi bi-plus"></i>
            </button>
            
            ${ticket.nivel_urgencia ? `
                <span class="badge-urgencia-card" style="background-color: ${colorUrgencia};">
                    ${ticket.nivel_urgencia}
                </span>
            ` : ''}
            
            <div class="resize-handle" 
                 onmousedown="startResize(event, ${ticket.id}, '${ticket.fecha_inicio}', '${ticket.fecha_final}')">
            </div>
        </div>
    `;
    
    // Event listeners
    card.addEventListener('dragstart', handleDragStart);
    card.addEventListener('click', (e) => {
        if (!e.target.closest('.btn-desprogramar') && 
            !e.target.closest('.btn-colaboradores') &&
            !e.target.closest('.resize-handle')) {
            mostrarDetallesTicket(ticket.id);
        }
    });
    
    celdaInicio.appendChild(card);
}

function ajustarAlturaCeldas(equipo, numFilas) {
    const row = document.querySelector(`tr[data-equipo="${equipo}"]`);
    if (!row) {
        console.warn(`No se encontró fila para equipo: ${equipo}`);
        return;
    }
    
    // Altura mínima + altura por fila (con margen)
    const alturaMinima = Math.max(80, (numFilas * 60) + 30);
    const celdas = row.querySelectorAll('.calendar-cell, .equipo-label');
    
    console.log(`Ajustando ${celdas.length} celdas a altura: ${alturaMinima}px`);
    
    celdas.forEach((celda, index) => {
        celda.style.minHeight = alturaMinima + 'px';
        celda.style.height = alturaMinima + 'px';
        celda.style.position = 'relative';
    });
    
    // Forzar reflow
    row.offsetHeight;
}

// ==================== DRAG & DROP ====================

function handleDragStart(e) {
    if (resizing) {
        e.preventDefault();
        return;
    }
    
    draggedTicket = {
        id: e.target.dataset.ticketId,
        fechaInicio: e.target.dataset.fechaInicio,
        fechaFinal: e.target.dataset.fechaFinal,
        tipoFormulario: e.target.dataset.tipoFormulario,
        duracion: e.target.dataset.fechaInicio && e.target.dataset.fechaInicio !== 'null' 
            ? calcularDias(e.target.dataset.fechaInicio, e.target.dataset.fechaFinal) 
            : 1
    };
    
    e.target.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    
    const cell = e.target.closest('.calendar-cell');
    if (cell) {
        cell.classList.add('drag-over');
    }
    
    return false;
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    // Limpiar estados
    document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
    document.querySelectorAll('.dragging').forEach(el => el.classList.remove('dragging'));
    
    const cell = e.target.closest('.calendar-cell');
    if (!cell) return false;
    
    const equipoTrabajo = cell.dataset.equipoTrabajo;
    const fecha = cell.dataset.fecha;
    const esGrupoCambioEquipos = equipoTrabajo === 'Cambio de Equipos';
    
    // Validar tipo_formulario
    if (draggedTicket.tipoFormulario === 'cambio_equipos' && !esGrupoCambioEquipos) {
        alert('Las solicitudes de cambio de equipos solo pueden programarse en el grupo "Cambio de Equipos"');
        return false;
    }
    
    if (draggedTicket.tipoFormulario === 'mantenimiento_general' && esGrupoCambioEquipos) {
        alert('Las solicitudes de mantenimiento general no pueden programarse en el grupo "Cambio de Equipos"');
        return false;
    }
    
    const nuevaFechaInicio = fecha;
    const nuevaFechaFinal = sumarDias(fecha, draggedTicket.duracion - 1);
    
    // Extraer tipos de usuario del equipo de trabajo
    const tiposUsuario = equipoTrabajo === 'Cambio de Equipos' ? [] : equipoTrabajo.split(' + ');
    
    // Si viene del sidebar, usar asignar, si no, usar mover
    const esDesdeSidebar = !draggedTicket.fechaInicio || draggedTicket.fechaInicio === 'null';
    const url = esDesdeSidebar ? 'ajax/agenda_asignar_ticket.php' : 'ajax/agenda_mover_ticket.php';
    
    $.ajax({
        url: url,
        method: 'POST',
        data: {
            ticket_id: draggedTicket.id,
            fecha_inicio: nuevaFechaInicio,
            fecha_final: nuevaFechaFinal,
            tipos_usuario: JSON.stringify(tiposUsuario)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error al mover la solicitud');
        }
    });
    
    draggedTicket = null;
    return false;
}

// ==================== RESIZE ====================

function startResize(e, ticketId, fechaInicio, fechaFinal) {
    e.stopPropagation();
    e.preventDefault();
    
    const card = e.target.closest('.ticket-card');
    card.draggable = false;
    
    resizing = {
        card: card,
        ticketId: ticketId,
        fechaInicio: fechaInicio,
        startX: e.clientX,
        originalWidth: card.offsetWidth,
        cellWidth: card.closest('.calendar-cell').offsetWidth
    };
    
    document.body.classList.add('resizing');
    document.addEventListener('mousemove', handleResize);
    document.addEventListener('mouseup', stopResize);
}

function handleResize(e) {
    if (!resizing) return;
    
    const deltaX = e.clientX - resizing.startX;
    const newWidth = Math.max(resizing.cellWidth - 10, resizing.originalWidth + deltaX);
    
    // Calcular número de días
    const numDias = Math.max(1, Math.round((newWidth + 10) / resizing.cellWidth));
    const exactWidth = (resizing.cellWidth * numDias) + (1 * (numDias - 1)) - 10;
    
    resizing.card.style.width = exactWidth + 'px';
    resizing.nuevaFechaFinal = sumarDias(resizing.fechaInicio, numDias - 1);
}

function stopResize(e) {
    if (!resizing) return;
    
    document.removeEventListener('mousemove', handleResize);
    document.removeEventListener('mouseup', stopResize);
    document.body.classList.remove('resizing');
    
    const card = resizing.card;
    const ticketId = resizing.ticketId;
    const fechaInicio = resizing.fechaInicio;
    const fechaFinal = resizing.nuevaFechaFinal;
    
    card.draggable = true;
    
    // Actualizar fechas
    $.ajax({
        url: 'ajax/agenda_actualizar_fechas.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            fecha_inicio: fechaInicio,
            fecha_final: fechaFinal
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
    
    resizing = null;
}

// ==================== COLABORADORES ====================

function mostrarColaboradores(ticketId, event) {
    event.stopPropagation();
    
    $.ajax({
        url: 'ajax/agenda_get_colaboradores.php',
        method: 'GET',
        data: { ticket_id: ticketId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                cargarOperariosYModal(ticketId, response.colaboradores);
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function cargarOperariosYModal(ticketId, colaboradores) {
    $.ajax({
        url: 'ajax/agenda_get_operarios.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderModalColaboradores(ticketId, colaboradores, response.operarios);
            }
        }
    });
}

function renderModalColaboradores(ticketId, colaboradores, operarios) {
    let html = `
        <div class="modal fade" id="modalColaboradores" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #0E544C; color: white;">
                        <h5 class="modal-title">Asignar Colaboradores</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Colaborador</th>
                                    <th>Tipo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="listaColaboradores">`;
    
    colaboradores.forEach(col => {
        html += `
            <tr data-id="${col.id}">
                <td>
                    <select class="form-select form-select-sm colaborador-select" data-id="${col.id}">
                        <option value="">Seleccionar...</option>`;
        
        operarios.forEach(op => {
            const selected = op.CodOperario == col.cod_operario ? 'selected' : '';
            html += `<option value="${op.CodOperario}" ${selected}>${op.nombre_completo}</option>`;
        });
        
        html += `
                    </select>
                </td>
                <td><small>${col.tipo_usuario}</small></td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="eliminarColaborador(${col.id})">
                        <i class="bi bi-x"></i>
                    </button>
                </td>
            </tr>`;
    });
    
    html += `
                                <tr id="nuevaFila" style="display: none;">
                                    <td>
                                        <select class="form-select form-select-sm" id="nuevoColaborador">
                                            <option value="">Seleccionar...</option>`;
    
    operarios.forEach(op => {
        html += `<option value="${op.CodOperario}">${op.nombre_completo}</option>`;
    });
    
    html += `
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" id="nuevoTipo">
                                            <option value="">Seleccionar tipo...</option>
                                            <option value="Jefe de Manteniento">Jefe de Mantenimiento</option>
                                            <option value="Lider de Infraestructura">Líder de Infraestructura</option>
                                            <option value="Conductor">Conductor</option>
                                            <option value="Auxiliar de Mantenimiento">Auxiliar de Mantenimiento</option>
                                        </select>
                                    </td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                        <button class="btn btn-sm btn-success" id="btnAgregarNuevo" style="background-color: #51B8AC; border: none;">
                            <i class="bi bi-plus"></i> Agregar
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>`;
    
    $('body').append(html);
    const modal = new bootstrap.Modal(document.getElementById('modalColaboradores'));
    modal.show();
    
    // Event listeners
    $('#btnAgregarNuevo').click(function() {
        $('#nuevaFila').show();
        $(this).hide();
    });
    
    $('#nuevoTipo').change(function() {
        const tipo = $(this).val();
        if (tipo) {
            agregarColaborador(ticketId, null, tipo);
        }
    });
    
    $('.colaborador-select').change(function() {
        const colaboradorId = $(this).data('id');
        const codOperario = $(this).val();
        actualizarColaborador(colaboradorId, codOperario);
    });
    
    $('#modalColaboradores').on('hidden.bs.modal', function() {
        $(this).remove();
        location.reload();
    });
}

function agregarColaborador(ticketId, codOperario, tipoUsuario) {
    $.ajax({
        url: 'ajax/agenda_save_colaborador.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            cod_operario: codOperario,
            tipo_usuario: tipoUsuario
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#modalColaboradores').modal('hide');
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function actualizarColaborador(colaboradorId, codOperario) {
    $.ajax({
        url: 'ajax/agenda_update_colaborador.php',
        method: 'POST',
        data: {
            colaborador_id: colaboradorId,
            cod_operario: codOperario
        },
        dataType: 'json'
    });
}

function eliminarColaborador(colaboradorId) {
    if (!confirm('¿Eliminar este colaborador?')) return;
    
    $.ajax({
        url: 'ajax/agenda_delete_colaborador.php',
        method: 'POST',
        data: { colaborador_id: colaboradorId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $(`tr[data-id="${colaboradorId}"]`).remove();
            }
        }
    });
}

// ==================== DESPROGRAMAR ====================

function desprogramarTicket(ticketId, event) {
    event.stopPropagation();
    
    if (!confirm('¿Está seguro que desea desprogramar esta solicitud?')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/agenda_desprogramar_ticket.php',
        method: 'POST',
        data: { ticket_id: ticketId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

// ==================== SIDEBAR ====================

function toggleSidebar() {
    const sidebar = document.getElementById('sidebarPendientes');
    sidebar.classList.toggle('open');
}

function filtrarPendientes() {
    const sucursal = document.getElementById('filtroSucursal').value;
    const tickets = document.querySelectorAll('.ticket-pendiente');
    
    tickets.forEach(ticket => {
        const ticketSucursal = ticket.dataset.sucursal;
        if (!sucursal || ticketSucursal === sucursal) {
            ticket.style.display = 'flex';
        } else {
            ticket.style.display = 'none';
        }
    });
}

// ==================== UTILIDADES ====================

function calcularDias(fechaInicio, fechaFinal) {
    const inicio = new Date(fechaInicio);
    const final = new Date(fechaFinal);
    const diff = Math.ceil((final - inicio) / (1000 * 60 * 60 * 24));
    return diff + 1;
}

function sumarDias(fecha, dias) {
    const date = new Date(fecha);
    date.setDate(date.getDate() + dias);
    return date.toISOString().split('T')[0];
}

// ==================== MODAL DE DETALLES ====================

function mostrarDetallesTicket(ticketId) {
    $.ajax({
        url: 'ajax/get_ticket_details.php',
        method: 'GET',
        data: { id: ticketId },
        success: function(response) {
            const modal = $('<div class="modal fade"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">' + response + '</div></div></div></div>');
            $('body').append(modal);
            
            setTimeout(function() {
                const hiddenInput = document.getElementById('edit_nivel_urgencia');
                if (hiddenInput && typeof window.currentUrgency !== 'undefined') {
                    const newUrgency = hiddenInput.value ? parseInt(hiddenInput.value) : null;
                    window.currentUrgency = newUrgency;
                }
                
                if (typeof initUrgencyControls === 'function') {
                    initUrgencyControls();
                }
            }, 0);
            
            modal.modal('show');
            modal.on('hidden.bs.modal', function() { 
                modal.remove(); 
            });
        }
    });
}