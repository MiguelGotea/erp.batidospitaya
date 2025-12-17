// js/programacion_solicitudes.js - Archivo completo

let draggedTicket = null;
let resizing = null;

// ==================== RENDERIZADO Y EMPAQUETADO ====================

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
    const esFinalizado = ticket.status === 'finalizado';
    
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
    if (esFinalizado) {
        card.classList.add('finalizado');
    }
    
    card.dataset.ticketId = ticket.id;
    card.dataset.fechaInicio = ticket.fecha_inicio;
    card.dataset.fechaFinal = ticket.fecha_final;
    card.dataset.tipoFormulario = ticket.tipo_formulario;
    
    // Solo hacer arrastrable si NO está finalizado
    if (!esFinalizado) {
        card.draggable = true;
    }
    
    card.style.position = 'absolute';
    card.style.top = top + 'px';
    card.style.left = '5px';
    card.style.width = anchoCard + 'px';
    card.style.height = '55px';
    card.style.boxSizing = 'border-box';
    card.style.zIndex = '50';
    card.style.cursor = esFinalizado ? 'pointer' : 'move';
    
    // Construir HTML interno
    let innerHTML = `
        <div style="position: relative; height: 100%;">
            <div class="ticket-title" style="font-size: 0.8rem; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 30px;">
                ${ticket.titulo}
                ${esFinalizado ? ' <small class="text-muted">(Finalizado)</small>' : ''}
            </div>
            <div style="display: flex; align-items: center; gap: 0.25rem; margin-top: 0.25rem;">`;
    
    // Solo mostrar botones si NO está finalizado
    if (!esFinalizado) {
        innerHTML += `
                <button class="btn-desprogramar" onclick="desprogramarTicket(${ticket.id}, event)" title="Desprogramar">
                    <i class="bi bi-x-lg"></i>
                </button>
                
                <button class="btn-colaboradores" onclick="mostrarColaboradores(${ticket.id}, event)" title="Asignar colaboradores">
                    <i class="bi bi-plus-lg"></i>
                </button>`;
    }
    
    innerHTML += `
                <div style="font-size: 0.7rem; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; ${!esFinalizado ? 'padding-left: 0.25rem;' : ''}">
                    ${ticket.nombre_sucursal}
                </div>
            </div>`;
    
    // Badge de urgencia - siempre visible
    if (ticket.nivel_urgencia) {
        innerHTML += `
            <span class="badge-urgencia-card" style="background-color: ${colorUrgencia};">
                ${ticket.nivel_urgencia}
            </span>`;
    }
    
    // Resize handle solo si NO está finalizado
    if (!esFinalizado) {
        innerHTML += `
            <div class="resize-handle" 
                 onmousedown="startResize(event, ${ticket.id}, '${ticket.fecha_inicio}', '${ticket.fecha_final}')">
            </div>`;
    }
    
    innerHTML += `
        </div>
    `;
    
    card.innerHTML = innerHTML;
    
    // Variables para detectar drag vs click
    let isDragging = false;
    let isResizing = false;
    let mouseDownTime = 0;
    let mouseDownX = 0;
    let mouseDownY = 0;
    
    // Event listeners solo si NO está finalizado
    if (!esFinalizado) {
        card.addEventListener('mousedown', (e) => {
            if (e.target.closest('.btn-desprogramar') || 
                e.target.closest('.btn-colaboradores') ||
                e.target.closest('.resize-handle')) {
                return;
            }
            isDragging = false;
            mouseDownTime = Date.now();
            mouseDownX = e.clientX;
            mouseDownY = e.clientY;
        });
        
        card.addEventListener('mousemove', (e) => {
            const deltaX = Math.abs(e.clientX - mouseDownX);
            const deltaY = Math.abs(e.clientY - mouseDownY);
            if (deltaX > 5 || deltaY > 5) {
                isDragging = true;
            }
        });
        
        card.addEventListener('dragstart', (e) => {
            isDragging = true;
            handleDragStart.call(card, e);
        });
    }
    
    // Click event para todos los tickets
    card.addEventListener('click', (e) => {
        const clickDuration = Date.now() - mouseDownTime;
        
        // Para tickets finalizados, permitir siempre el click
        if (esFinalizado) {
            mostrarDetallesTicket(ticket.id);
            return;
        }
        
        // Para tickets no finalizados, verificar que no sea un drag
        if (!e.target.closest('.btn-desprogramar') && 
            !e.target.closest('.btn-colaboradores') &&
            !e.target.closest('.resize-handle') &&
            !isDragging && 
            !isResizing &&
            clickDuration < 300) {
            mostrarDetallesTicket(ticket.id);
        }
        isDragging = false;
        isResizing = false;
    });
    
    // Agregar hover effect solo para tickets no finalizados
    if (!esFinalizado) {
        card.addEventListener('mouseenter', () => {
            card.style.borderColor = '#0E544C';
            card.style.boxShadow = '0 4px 12px rgba(14, 84, 76, 0.25)';
            card.style.transform = 'translateY(-1px)';
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.borderColor = '#51B8AC';
            card.style.boxShadow = '0 2px 4px rgba(14, 84, 76, 0.1)';
            card.style.transform = '';
        });
    }
    
    celdaInicio.appendChild(card);
    
    // Crear overlays para las celdas que ocupa el ticket
    for (let i = 1; i < numDias; i++) {
        const celdaSiguiente = celdas[diaInicio + i];
        if (celdaSiguiente) {
            const overlay = document.createElement('div');
            overlay.style.position = 'absolute';
            overlay.style.top = top + 'px';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '55px';
            overlay.style.pointerEvents = 'auto';
            overlay.style.zIndex = '49';
            overlay.style.cursor = esFinalizado ? 'pointer' : 'move';
            
            if (esFinalizado) {
                overlay.classList.add('ticket-card-overlay', 'finalizado');
            } else {
                overlay.classList.add('ticket-card-overlay');
            }
            
            let overlayDragging = false;
            let overlayMouseDownTime = 0;
            let overlayMouseDownX = 0;
            let overlayMouseDownY = 0;
            
            // Event listeners solo si NO está finalizado
            if (!esFinalizado) {
                overlay.draggable = true;
                
                overlay.addEventListener('mousedown', (e) => {
                    overlayDragging = false;
                    overlayMouseDownTime = Date.now();
                    overlayMouseDownX = e.clientX;
                    overlayMouseDownY = e.clientY;
                });
                
                overlay.addEventListener('mousemove', (e) => {
                    const deltaX = Math.abs(e.clientX - overlayMouseDownX);
                    const deltaY = Math.abs(e.clientY - overlayMouseDownY);
                    if (deltaX > 5 || deltaY > 5) {
                        overlayDragging = true;
                    }
                });
                
                overlay.addEventListener('dragstart', (e) => {
                    overlayDragging = true;
                    handleDragStart.call(card, e);
                });
            }
            
            // Click event para overlay
            overlay.addEventListener('click', (e) => {
                if (esFinalizado) {
                    mostrarDetallesTicket(ticket.id);
                } else {
                    const clickDuration = Date.now() - overlayMouseDownTime;
                    if (!overlayDragging && clickDuration < 300) {
                        mostrarDetallesTicket(ticket.id);
                    }
                    overlayDragging = false;
                }
            });
            
            celdaSiguiente.appendChild(overlay);
        }
    }
}

function ajustarAlturaCeldas(equipo, numFilas) {
    const row = document.querySelector(`tr[data-equipo="${equipo}"]`);
    if (!row) {
        return;
    }
    
    // Altura mínima + altura por fila (con margen)
    const alturaMinima = Math.max(80, (numFilas * 60) + 30);
    const celdas = row.querySelectorAll('.calendar-cell, .equipo-label');
    
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
    
    const card = e.target.closest('.ticket-card');
    const status = card ? card.dataset.status : null;
    
    // No permitir arrastrar tickets finalizados
    if (status === 'finalizado') {
        e.preventDefault();
        return false;
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
    const status = card ? card.dataset.status : null;
    
    // No permitir redimensionar tickets finalizados
    if (status === 'finalizado') {
        return;
    }
    
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
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Cargo</th>
                                    <th>Colaborador</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="listaColaboradores">`;
    
    colaboradores.forEach(col => {
        html += `
            <tr data-id="${col.id}">
                <td><small>${col.tipo_usuario}</small></td>
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
                                        <select class="form-select form-select-sm" id="nuevoTipo">
                                            <option value="">Seleccionar tipo...</option>
                                            <option value="Jefe de Manteniento">Jefe de Mantenimiento</option>
                                            <option value="Lider de Infraestructura">Líder de Infraestructura</option>
                                            <option value="Conductor">Conductor</option>
                                            <option value="Auxiliar de Mantenimiento">Auxiliar de Mantenimiento</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" id="nuevoColaborador">
                                            <option value="">Seleccionar...</option>`;
    
    operarios.forEach(op => {
        html += `<option value="${op.CodOperario}">${op.nombre_completo}</option>`;
    });
    
    html += `
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
    // Validar que se haya seleccionado un colaborador cuando viene de la nueva fila
    const selectColaborador = document.getElementById('nuevoColaborador');
    if (selectColaborador && !codOperario) {
        codOperario = selectColaborador.value;
    }
    
    $.ajax({
        url: 'ajax/agenda_save_colaborador.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            cod_operario: codOperario || null, // Permitir NULL
            tipo_usuario: tipoUsuario
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Cerrar modal y recargar (necesario para recalcular equipos)
                $('#modalColaboradores').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr) {
            console.error('Error completo:', xhr.responseText);
            alert('Error al agregar colaborador. Ver consola para detalles.');
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
                // Mostrar mensaje temporal
                const mensaje = $('<div class="alert alert-success" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">Colaborador eliminado</div>');
                $('body').append(mensaje);
                setTimeout(() => mensaje.fadeOut(() => mensaje.remove()), 2000);
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
            // Crear modal sin estilos adicionales - el contenido ya tiene sus propios estilos
            const modalHtml = `
                <div class="modal fade" id="modalDetallesTicket" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            ${response}
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            const modalElement = document.getElementById('modalDetallesTicket');
            const modal = new bootstrap.Modal(modalElement);
            
            setTimeout(function() {
                const hiddenInput = document.getElementById('edit_nivel_urgencia');
                if (hiddenInput && typeof window.currentUrgency !== 'undefined') {
                    const newUrgency = hiddenInput.value ? parseInt(hiddenInput.value) : null;
                    window.currentUrgency = newUrgency;
                }
                
                if (typeof initUrgencyControls === 'function') {
                    initUrgencyControls();
                }
            }, 100);
            
            modal.show();
            
            modalElement.addEventListener('hidden.bs.modal', function() {
                modalElement.remove(); 
            });
        },
        error: function() {
            alert('Error al cargar los detalles de la solicitud');
        }
    });
}