// ============================================
// GESTIÓN DE MOVIMIENTOS DE EQUIPOS
// ============================================

// Variables globales
let equiposDisponibles = [];
let sucursalesData = [];

document.addEventListener('DOMContentLoaded', function() {
    cargarDatosIniciales();
});

function cargarDatosIniciales() {
    // Cargar equipos
    fetch('ajax/equipos_datos.php?accion=equipos_disponibles')
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                equiposDisponibles = result.data;
            }
        })
        .catch(error => console.error('Error cargando equipos:', error));
    
    // Cargar sucursales
    fetch('ajax/equipos_datos.php?accion=sucursales')
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                sucursalesData = result.data;
            }
        })
        .catch(error => console.error('Error cargando sucursales:', error));
}

function abrirNuevoMovimiento() {
    console.error('se abrio movimiento limpio');
    document.getElementById('form-movimiento').reset();
    document.getElementById('mov-solicitud-id').value = '';
    document.getElementById('opcion-cambio').style.display = 'none';
    cargarEquiposEnSelect();
    openModal('modal-movimiento');
}

function abrirMovimientoConSolicitud(equipoId, sucursalOrigenId, solicitudId) {
    document.getElementById('form-movimiento').reset();
    document.getElementById('mov-solicitud-id').value = solicitudId;
    document.getElementById('opcion-cambio').style.display = 'block';
    
    cargarEquiposEnSelect(equipoId);
    document.getElementById('mov-origen').value = sucursalOrigenId;
    
    // Destino por defecto: central
    const centralOption = Array.from(document.getElementById('mov-destino').options)
        .find(opt => opt.textContent.includes('Central') || opt.textContent.includes('Almacén'));
    if (centralOption) {
        document.getElementById('mov-destino').value = centralOption.value;
    }
    
    openModal('modal-movimiento');
}

function cargarEquiposEnSelect(selectedId = null) {
    const select = document.getElementById('mov-equipo-id');
    select.innerHTML = '<option value="">Seleccione equipo...</option>';
    
    equiposDisponibles.forEach(eq => {
        const option = document.createElement('option');
        option.value = eq.id;
        option.textContent = `${eq.codigo} - ${eq.marca} ${eq.modelo}`;
        option.dataset.ubicacionId = eq.ubicacion_actual_id;
        if (selectedId && eq.id == selectedId) {
            option.selected = true;
        }
        select.appendChild(option);
    });
    
    if (selectedId) {
        actualizarOrigenDestino();
    }
}

function actualizarOrigenDestino() {
    const select = document.getElementById('mov-equipo-id');
    const option = select.options[select.selectedIndex];
    const ubicacionId = option.dataset.ubicacionId;
    
    if (ubicacionId) {
        document.getElementById('mov-origen').value = ubicacionId;
    }
}

function toggleEquipoCambio() {
    const checked = document.getElementById('enviar-cambio').checked;
    const container = document.getElementById('equipo-cambio-container');
    container.style.display = checked ? 'block' : 'none';
    
    if (!checked) {
        document.getElementById('equipo-cambio').value = '';
    }
}

function guardarMovimiento(e) {
    e.preventDefault();
    
    // Validaciones
    const equipoId = document.getElementById('mov-equipo-id').value;
    const origen = document.getElementById('mov-origen').value;
    const destino = document.getElementById('mov-destino').value;
    
    if (!equipoId || !origen || !destino) {
        showAlert('Complete todos los campos obligatorios', 'warning');
        return;
    }
    
    if (origen === destino) {
        showAlert('La sucursal origen y destino no pueden ser iguales', 'warning');
        return;
    }
    
    // Validar equipo de cambio si está seleccionado
    const enviarCambio = document.getElementById('enviar-cambio').checked;
    if (enviarCambio) {
        const equipoCambio = document.getElementById('equipo-cambio').value;
        if (!equipoCambio) {
            showAlert('Seleccione el equipo de reemplazo', 'warning');
            return;
        }
    }
    
    const formData = new FormData(e.target);
    showLoading(true);
    
    fetch('ajax/equipos_movimiento_crear.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        showLoading(false);
        if (result.success) {
            showAlert(result.message, 'success');
            closeModal('modal-movimiento');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(result.message || 'Error al crear movimiento', 'danger');
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('Error:', error);
        showAlert('Error al procesar la solicitud', 'danger');
    });
}

function finalizarMovimiento(movimientoId) {
    if (!confirm('¿Confirmar que el movimiento se ha realizado correctamente?')) {
        return;
    }
    
    showLoading(true);
    
    fetch('ajax/equipos_movimiento_finalizar.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({movimiento_id: movimientoId})
    })
    .then(response => response.json())
    .then(result => {
        showLoading(false);
        if (result.success) {
            showAlert('Movimiento finalizado exitosamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(result.message || 'Error al finalizar movimiento', 'danger');
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('Error:', error);
        showAlert('Error al procesar la solicitud', 'danger');
    });
}

function verDetalleMovimiento(movimientoId) {
    showLoading(true);
    
    fetch('ajax/equipos_datos.php?accion=detalle_movimiento&id=' + movimientoId)
        .then(response => response.json())
        .then(result => {
            showLoading(false);
            if (result.success) {
                mostrarDetalleMovimiento(result.data);
            } else {
                showAlert('Error al cargar detalle', 'danger');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Error:', error);
            showAlert('Error al cargar detalle', 'danger');
        });
}

function mostrarDetalleMovimiento(data) {
    // Crear modal dinámico con información del movimiento
    const modalHtml = `
        <div class="modal active" id="modal-detalle-movimiento">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Detalle del Movimiento #${data.id}</h2>
                    <button class="modal-close" onclick="closeModal('modal-detalle-movimiento')">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="info-row">
                        <span class="info-label">Equipo:</span>
                        <span class="info-value"><strong>${data.codigo}</strong> - ${data.marca} ${data.modelo}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Estado:</span>
                        <span class="info-value">
                            <span class="badge ${data.estado === 'agendado' ? 'badge-warning' : 'badge-success'}">
                                ${data.estado === 'agendado' ? 'Agendado' : 'Finalizado'}
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Origen:</span>
                        <span class="info-value">${data.sucursal_origen}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Destino:</span>
                        <span class="info-value">${data.sucursal_destino}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Fecha Programada:</span>
                        <span class="info-value">${formatDate(data.fecha_programada)}</span>
                    </div>
                    ${data.fecha_realizada ? `
                        <div class="info-row">
                            <span class="info-label">Fecha Realizada:</span>
                            <span class="info-value">${formatDate(data.fecha_realizada)}</span>
                        </div>
                    ` : ''}
                    ${data.observaciones ? `
                        <div class="info-row">
                            <span class="info-label">Observaciones:</span>
                            <span class="info-value">${data.observaciones}</span>
                        </div>
                    ` : ''}
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('modal-detalle-movimiento')">Cerrar</button>
                </div>
            </div>
        </div>
    `;
    
    // Insertar modal en el documento
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = modalHtml;
    document.body.appendChild(tempDiv.firstElementChild);
}

// Función para filtrar movimientos por estado
function filtrarMovimientos(estado) {
    const filas = document.querySelectorAll('tbody tr');
    
    filas.forEach(fila => {
        const badge = fila.querySelector('.badge');
        if (!badge) return;
        
        if (estado === 'todos') {
            fila.style.display = '';
        } else if (estado === 'agendado') {
            fila.style.display = badge.classList.contains('badge-warning') ? '' : 'none';
        } else if (estado === 'finalizado') {
            fila.style.display = badge.classList.contains('badge-success') ? '' : 'none';
        }
    });
}

// Función para exportar movimientos a CSV
function exportarMovimientos() {
    showLoading(true);
    
    fetch('ajax/equipos_datos.php?accion=exportar_movimientos')
        .then(response => response.blob())
        .then(blob => {
            showLoading(false);
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `movimientos_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        })
        .catch(error => {
            showLoading(false);
            console.error('Error:', error);
            showAlert('Error al exportar movimientos', 'danger');
        });
}

// Validación en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const origenSelect = document.getElementById('mov-origen');
    const destinoSelect = document.getElementById('mov-destino');
    
    if (origenSelect && destinoSelect) {
        origenSelect.addEventListener('change', validarOrigenDestino);
        destinoSelect.addEventListener('change', validarOrigenDestino);
    }
});

function validarOrigenDestino() {
    const origen = document.getElementById('mov-origen').value;
    const destino = document.getElementById('mov-destino').value;
    
    if (origen && destino && origen === destino) {
        document.getElementById('mov-destino').style.borderColor = '#dc3545';
        showAlert('Origen y destino deben ser diferentes', 'warning');
    } else {
        document.getElementById('mov-destino').style.borderColor = '#ddd';
    }
}