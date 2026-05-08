// Variables para manejar el estado de edición de feriados
let editandoObservacionesFeriado = {};
let observacionesOriginalesFeriado = {};

/**
 * Actualiza el estado de un feriado (Pagado/Descansado)
 */
function actualizarEstadoFeriado(elementId, nuevoEstado, codOperario, fecha) {
    const confirmMessage = nuevoEstado === 'Pagado'
        ? '¿Está seguro de marcar este feriado como PAGADO? (8 horas a pagar)'
        : '¿Está seguro de marcar este feriado como DESCANSADO/COMPENSADO?';

    if (!confirm(confirmMessage)) {
        return;
    }

    // Para registros sin ID (nuevos), crear uno
    if (elementId.startsWith('temp_')) {
        crearRegistroFeriado(elementId, nuevoEstado, codOperario, fecha);
        return;
    }

    // Para registros existentes, actualizar
    actualizarRegistroFeriado(elementId, nuevoEstado);
}

/**
 * Crea un nuevo registro de feriado trabajado
 */
function crearRegistroFeriado(elementId, estado, codOperario, fecha) {
    const observaciones = document.getElementById(`obs-edit-${elementId}`)?.value || '';

    // Mostrar loading
    const actionsDiv = document.getElementById(`actions-${elementId}`);
    if (!actionsDiv) return;

    const originalHTML = actionsDiv.innerHTML;
    actionsDiv.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-spinner fa-spin"></i> Creando registro...</div>';

    // Enviar petición AJAX
    fetch('ajax/crear_feriado_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'cod_operario': codOperario,
            'fecha_feriado': fecha,
            'estado': estado,
            'observaciones': observaciones
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Recargar la página para mostrar el nuevo registro con ID real
                mostrarNotificacion('success', data.message);
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                // Restaurar HTML original en caso de error
                actionsDiv.innerHTML = originalHTML;
                mostrarNotificacion('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            actionsDiv.innerHTML = originalHTML;
            mostrarNotificacion('error', 'Error al crear el registro del feriado');
        });
}

/**
 * Cambia el estado de un feriado ya procesado
 */
function cambiarEstadoFeriado(id, estadoActual, codOperario, fecha) {
    const nuevoEstado = estadoActual === 'Pagado' ? 'Descansado' : 'Pagado';
    actualizarEstadoFeriado(id, nuevoEstado, codOperario, fecha);
}

/**
 * Activa el modo de edición de observaciones para feriados
 */
function toggleEditObservacionesFeriado(id) {
    const displayDiv = document.getElementById(`obs-display-${id}`);
    const editTextarea = document.getElementById(`obs-edit-${id}`);

    // Si ya estamos editando, no hacer nada
    if (editandoObservacionesFeriado[id]) return;

    // Guardar valor original
    observacionesOriginalesFeriado[id] = editTextarea ? editTextarea.value : '';

    // Alternar visibilidad
    if (displayDiv) displayDiv.style.display = 'none';
    if (editTextarea) {
        editTextarea.style.display = 'block';
        editTextarea.focus();
        
        // Mover cursor al final del texto
        const length = editTextarea.value.length;
        editTextarea.setSelectionRange(length, length);
    }

    // Marcar como editando
    editandoObservacionesFeriado[id] = true;
}

/**
 * Maneja las teclas presionadas en el textarea de observaciones
 */
function manejarTeclasObservaciones(event, id, codOperario, fecha) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        event.target.blur(); // Esto disparará el onblur y por lo tanto el guardar
    } else if (event.key === 'Escape') {
        cancelarEditObservacionesFeriado(id);
    }
}

/**
 * Guarda las observaciones editadas para feriados
 */
function guardarObservacionesFeriado(id, codOperario, fecha) {
    // Si no estamos editando o ya se está guardando, salir
    if (!editandoObservacionesFeriado[id] || editandoObservacionesFeriado[id] === 'guardando') return;

    const editTextarea = document.getElementById(`obs-edit-${id}`);
    const nuevasObservaciones = editTextarea ? editTextarea.value.trim() : '';

    // Si el valor no ha cambiado, simplemente finalizar edición
    if (nuevasObservaciones === observacionesOriginalesFeriado[id]) {
        finalizarEdicionObservacionesFeriado(id);
        return;
    }

    // Marcar como guardando para evitar peticiones duplicadas (blur + enter)
    editandoObservacionesFeriado[id] = 'guardando';

    // Obtener estado actual
    const badge = document.getElementById(`status-badge-${id}`);
    const estadoActual = badge ? badge.textContent.trim() : 'Pendiente';

    // Mostrar visualmente que se está guardando
    const displayDiv = document.getElementById(`obs-display-${id}`);
    if (displayDiv) {
        displayDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        displayDiv.style.display = 'block';
    }
    if (editTextarea) editTextarea.style.display = 'none';

    // Para registros existentes, actualizar observaciones
    fetch('ajax/actualizar_feriado_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'id': id,
            'estado': estadoActual,
            'observaciones': nuevasObservaciones
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar valor en el textarea (por si hubo limpieza en el server)
                if (editTextarea) editTextarea.value = nuevasObservaciones;

                // Actualizar display de observaciones
                if (displayDiv) {
                    if (nuevasObservaciones) {
                        displayDiv.innerHTML = nuevasObservaciones.replace(/\n/g, '<br>');
                    } else {
                        displayDiv.innerHTML = '<span class="text-muted">Sin observaciones</span>';
                    }
                }

                // Salir del modo edición
                finalizarEdicionObservacionesFeriado(id);
                mostrarNotificacion('success', 'Observaciones actualizadas');
            } else {
                // Si hubo error, restaurar estado de edición para permitir reintento o cancelación
                editandoObservacionesFeriado[id] = true;
                if (displayDiv) {
                    displayDiv.style.display = 'none';
                    // Restaurar HTML original para cuando se cancele la edición
                    if (observacionesOriginalesFeriado[id]) {
                        displayDiv.innerHTML = observacionesOriginalesFeriado[id].replace(/\n/g, '<br>');
                    } else {
                        displayDiv.innerHTML = '<span class="text-muted">Sin observaciones</span>';
                    }
                }
                if (editTextarea) {
                    editTextarea.style.display = 'block';
                    editTextarea.focus();
                }
                mostrarNotificacion('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            editandoObservacionesFeriado[id] = true;
            if (displayDiv) {
                displayDiv.style.display = 'none';
                if (observacionesOriginalesFeriado[id]) {
                    displayDiv.innerHTML = observacionesOriginalesFeriado[id].replace(/\n/g, '<br>');
                } else {
                    displayDiv.innerHTML = '<span class="text-muted">Sin observaciones</span>';
                }
            }
            if (editTextarea) {
                editTextarea.style.display = 'block';
                editTextarea.focus();
            }
            mostrarNotificacion('error', 'Error al guardar');
        });
}

/**
 * Cancela la edición de observaciones para feriados
 */
function cancelarEditObservacionesFeriado(id) {
    const editTextarea = document.getElementById(`obs-edit-${id}`);

    // Restaurar valor original
    if (observacionesOriginalesFeriado[id] !== undefined && editTextarea) {
        editTextarea.value = observacionesOriginalesFeriado[id];
    }

    finalizarEdicionObservacionesFeriado(id);
}

/**
 * Finaliza el modo de edición de observaciones para feriados
 */
function finalizarEdicionObservacionesFeriado(id) {
    const displayDiv = document.getElementById(`obs-display-${id}`);
    const editTextarea = document.getElementById(`obs-edit-${id}`);

    // Alternar visibilidad
    if (displayDiv) displayDiv.style.display = 'block';
    if (editTextarea) editTextarea.style.display = 'none';

    // Limpiar estado
    delete editandoObservacionesFeriado[id];
    delete observacionesOriginalesFeriado[id];
}

/**
 * Actualiza los botones de acción según el nuevo estado del feriado
 */
function actualizarBotonesAccionFeriado(id, nuevoEstado) {
    const actionsDiv = document.getElementById(`actions-${id}`);

    if (!actionsDiv) return;

    // Extraer código de operario y fecha del ID si es temporal
    let codOperario = '';
    let fecha = '';

    if (id.startsWith('temp_')) {
        const parts = id.split('_');
        if (parts.length >= 3) {
            codOperario = parts[1];
            fecha = parts[2];
        }
    }

    if (nuevoEstado === 'Pendiente' || nuevoEstado === 'Sin marcación' || nuevoEstado === 'Con Marcación') {
        actionsDiv.innerHTML = `
            <button type="button" class="btn-action btn-approve" 
                    onclick="actualizarEstadoFeriado('${id}', 'Pagado', '${codOperario}', '${fecha}')" title="Marcar como Pagado">
                <i class="fas fa-dollar-sign"></i>
            </button>
            <button type="button" class="btn-action btn-compensado" 
                    onclick="actualizarEstadoFeriado('${id}', 'Descansado', '${codOperario}', '${fecha}')" title="Marcar como Compensado/Descansado">
                <i class="fas fa-bed"></i>
            </button>
        `;
    } else {
        actionsDiv.innerHTML = `
            <button type="button" class="btn-action btn-change" 
                    onclick="cambiarEstadoFeriado('${id}', '${nuevoEstado}', '${codOperario}', '${fecha}')" title="Cambiar estado">
                <i class="fas fa-exchange-alt"></i>
            </button>
        `;
    }
}

/**
 * Muestra notificaciones toast (reutilizable)
 */
function mostrarNotificacion(tipo, mensaje) {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${tipo}`;
    notification.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${mensaje}</span>
    `;

    // Estilos inline para la notificación
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        background: ${tipo === 'success' ? 'linear-gradient(135deg, #28a745 0%, #20c997 100%)' : 'linear-gradient(135deg, #dc3545 0%, #e83e8c 100%)'};
    `;

    document.body.appendChild(notification);

    // Eliminar después de 3 segundos
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

/**
 * Actualiza un registro existente de feriado
 */
function actualizarRegistroFeriado(id, nuevoEstado) {
    const observaciones = document.getElementById(`obs-edit-${id}`)?.value || '';

    // Mostrar loading
    const actionsDiv = document.getElementById(`actions-${id}`);
    if (!actionsDiv) return;

    const originalHTML = actionsDiv.innerHTML;
    actionsDiv.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-spinner fa-spin"></i> Procesando...</div>';

    // Enviar petición AJAX
    fetch('ajax/actualizar_feriado_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'id': id,
            'estado': nuevoEstado,
            'observaciones': observaciones
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar badge de estado
                const badge = document.getElementById(`status-badge-${id}`);
                if (badge) {
                    badge.textContent = nuevoEstado;
                    badge.className = `status-badge status-${nuevoEstado.toLowerCase().replace(' ', '-')}`;
                }

                // Actualizar botones de acción
                actualizarBotonesAccionFeriado(id, nuevoEstado);

                // Mostrar mensaje de éxito
                mostrarNotificacion('success', data.message);
            } else {
                // Restaurar HTML original en caso de error
                actionsDiv.innerHTML = originalHTML;
                mostrarNotificacion('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            actionsDiv.innerHTML = originalHTML;
            mostrarNotificacion('error', 'Error al actualizar el estado del feriado');
        });
}

// Función para buscar operarios
function buscarOperarios(texto) {
    if (!texto) {
        return typeof operariosData !== 'undefined' ? operariosData : [];
    }
    return (typeof operariosData !== 'undefined' ? operariosData : []).filter(op =>
        op.nombre.toLowerCase().includes(texto.toLowerCase())
    );
}

/**
 * Inicializa los eventos del DOM cuando el contenido esté cargado
 */
document.addEventListener('DOMContentLoaded', function() {
    // Manejar el input de operario
    const operarioInput = document.getElementById('operario');
    const operarioIdInput = document.getElementById('operario_id');
    const sugerenciasDiv = document.getElementById('operarios-sugerencias');

    if (operarioInput && sugerenciasDiv) {
        // Modificar el evento input del campo operario
        operarioInput.addEventListener('input', function () {
            const texto = this.value.trim();

            // Si el campo está vacío, resetear a "todos"
            if (texto === '') {
                if (operarioIdInput) operarioIdInput.value = '0';
                sugerenciasDiv.style.display = 'none';
                return;
            }

            const resultados = buscarOperarios(texto);

            sugerenciasDiv.innerHTML = '';

            if (resultados.length > 0) {
                resultados.forEach(op => {
                    const div = document.createElement('div');
                    div.textContent = op.nombre;
                    div.style.padding = '8px';
                    div.style.cursor = 'pointer';
                    div.addEventListener('click', function () {
                        operarioInput.value = op.nombre;
                        if (operarioIdInput) operarioIdInput.value = op.id;
                        sugerenciasDiv.style.display = 'none';
                    });
                    div.addEventListener('mouseover', function () {
                        this.style.backgroundColor = '#f5f5f5';
                    });
                    div.addEventListener('mouseout', function () {
                        this.style.backgroundColor = 'white';
                    });
                    sugerenciasDiv.appendChild(div);
                });
                sugerenciasDiv.style.display = 'block';
            } else {
                sugerenciasDiv.style.display = 'none';
            }
        });

        // Ocultar sugerencias al hacer clic fuera
        document.addEventListener('click', function (e) {
            if (e.target !== operarioInput) {
                sugerenciasDiv.style.display = 'none';
            }
        });

        // Manejar tecla Enter en el input
        operarioInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const texto = this.value.trim();
                const resultados = buscarOperarios(texto);
                if (resultados.length > 0) {
                    this.value = resultados[0].nombre;
                    if (operarioIdInput) operarioIdInput.value = resultados[0].id;
                }
                sugerenciasDiv.style.display = 'none';
            }
        });
    }

    // Cerrar modal al hacer clic fuera del contenido
    window.addEventListener('click', function (event) {
        const modal = document.getElementById('modalAprobacion');
        if (event.target === modal) {
            cerrarModal();
        }
    });
});

// Actualizar filtros y recargar la página
function actualizarFiltros() {
    const sucursalEl = document.getElementById('sucursal');
    const operarioIdEl = document.getElementById('operario_id');
    const desdeEl = document.getElementById('desde');
    const hastaEl = document.getElementById('hasta');

    if (!sucursalEl || !operarioIdEl || !desdeEl || !hastaEl) return;

    const sucursal = sucursalEl.value;
    const operario = operarioIdEl.value;
    const desde = desdeEl.value;
    const hasta = hastaEl.value;

    // Validar fechas
    if (!desde || !hasta) {
        alert('Por favor seleccione ambas fechas');
        return;
    }

    // Validar que la fecha desde no sea mayor que hasta
    if (new Date(desde) > new Date(hasta)) {
        alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
        return;
    }

    // Construir URL con parámetros
    const params = new URLSearchParams();
    if (sucursal) params.append('sucursal', sucursal);
    if (operario && operario != '0') params.append('operario', operario);
    params.append('desde', desde);
    params.append('hasta', hasta);

    window.location.href = 'feriados.php?' + params.toString();
}

// Mostrar modal de aprobación
function mostrarModalAprobacion(
    idMarcacion, nombre, sucursal, fecha, horaEntrada, horaSalida,
    horasTrabajadas, feriadoNombre, feriadoTipo, departamentoNombre,
    estado, observaciones, codOperario
) {
    const idMarcacionEl = document.getElementById('id_marcacion');
    const codOperarioEl = document.getElementById('cod_operario');
    const horasTrabajadasEl = document.getElementById('horas_trabajadas');
    const modalNombreEl = document.getElementById('modal-nombre');
    const modalSucursalEl = document.getElementById('modal-sucursal');
    const modalFechaEl = document.getElementById('modal-fecha');
    const modalFeriadoEl = document.getElementById('modal-feriado');
    const modalTipoEl = document.getElementById('modal-tipo');
    const modalHoraEntradaEl = document.getElementById('modal-hora-entrada');
    const modalHoraSalidaEl = document.getElementById('modal-hora-salida');
    const modalHorasTrabajadasEl = document.getElementById('modal-horas-trabajadas');
    const estadoEl = document.getElementById('estado');
    const observacionesEl = document.getElementById('observaciones');
    const fechaFeriadoEl = document.getElementById('fecha_feriado');
    const sucursalFiltroEl = document.querySelector('input[name="sucursal_filtro"]');
    const desdeFiltroEl = document.querySelector('input[name="desde_filtro"]');
    const hastaFiltroEl = document.querySelector('input[name="hasta_filtro"]');
    const operarioFiltroEl = document.querySelector('input[name="operario_filtro"]');

    // Manejar idMarcacion null o undefined
    if (idMarcacionEl) {
        if (idMarcacion === null || idMarcacion === 'null' || idMarcacion === '' || idMarcacion === undefined) {
            idMarcacionEl.value = '';
        } else {
            idMarcacionEl.value = idMarcacion;
        }
    }

    if (codOperarioEl) codOperarioEl.value = codOperario;
    if (horasTrabajadasEl) horasTrabajadasEl.value = horasTrabajadas;

    // Formatear fecha localmente
    function formatearFechaLocal(fechaStr) {
        try {
            const fecha = new Date(fechaStr + 'T00:00:00');
            const opciones = { day: '2-digit', month: 'short', year: '2-digit' };
            return fecha.toLocaleDateString('es-ES', opciones);
        } catch (e) {
            return fechaStr; // Si hay error, devolver la fecha original
        }
    }

    // Establecer valores en el modal
    if (modalNombreEl) modalNombreEl.textContent = nombre;
    if (modalSucursalEl) modalSucursalEl.textContent = sucursal;
    if (modalFechaEl) modalFechaEl.textContent = formatearFechaLocal(fecha);
    if (modalFeriadoEl) modalFeriadoEl.textContent = feriadoNombre;
    if (modalTipoEl) modalTipoEl.textContent = feriadoTipo +
        (feriadoTipo === 'Departamental' ? ` (${departamentoNombre})` : '');

    // Manejar horas (pueden estar vacías)
    if (modalHoraEntradaEl) modalHoraEntradaEl.textContent =
        (horaEntrada && horaEntrada.trim() !== '') ? horaEntrada : 'No registrada';
    if (modalHoraSalidaEl) modalHoraSalidaEl.textContent =
        (horaSalida && horaSalida.trim() !== '') ? horaSalida : 'No registrada';

    if (modalHorasTrabajadasEl) modalHorasTrabajadasEl.textContent = horasTrabajadas.toFixed(2);
    if (estadoEl) estadoEl.value = estado;
    if (observacionesEl) observacionesEl.value = observaciones || '';

    // IMPORTANTE: Establecer la fecha del feriado en un campo oculto
    if (fechaFeriadoEl) fechaFeriadoEl.value = fecha;

    // Guardar filtros actuales
    if (sucursalFiltroEl) sucursalFiltroEl.value = document.getElementById('sucursal')?.value || '';
    if (desdeFiltroEl) desdeFiltroEl.value = document.getElementById('desde')?.value || '';
    if (hastaFiltroEl) hastaFiltroEl.value = document.getElementById('hasta')?.value || '';
    if (operarioFiltroEl) operarioFiltroEl.value = document.getElementById('operario_id')?.value || '';

    // Mostrar el modal
    const modalAprobacion = document.getElementById('modalAprobacion');
    if (modalAprobacion) modalAprobacion.style.display = 'flex';
}

// Cerrar modal
function cerrarModal() {
    const modal = document.getElementById('modalAprobacion');
    if (modal) modal.style.display = 'none';
}
