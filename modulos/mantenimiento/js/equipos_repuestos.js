// public_html/modulos/mantenimiento/js/equipos_repuestos.js

document.addEventListener('DOMContentLoaded', function() {
    cargarRepuestos();
    document.getElementById('formRepuesto').addEventListener('submit', guardarRepuesto);
});

// Cargar repuestos
async function cargarRepuestos() {
    try {
        const response = await fetch('ajax/equipos_repuestos_listar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderizarRepuestos(data.repuestos);
        } else {
            mostrarError(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error al cargar repuestos');
    }
}

// Renderizar tabla de repuestos
function renderizarRepuestos(repuestos) {
    const tbody = document.querySelector('#tablaRepuestos tbody');
    
    if (repuestos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="texto-centrado">No hay repuestos registrados</td></tr>';
        return;
    }
    
    let html = '';
    repuestos.forEach(rep => {
        html += `
            <tr>
                <td><strong>${rep.nombre}</strong></td>
                <td>${rep.descripcion || 'N/A'}</td>
                <td>$${parseFloat(rep.costo_base).toFixed(2)}</td>
                <td>${rep.unidad_medida || 'N/A'}</td>
                <td>
                    <span class="badge badge-${rep.activo == 1 ? 'completado' : 'peligro'}">
                        ${rep.activo == 1 ? 'Activo' : 'Inactivo'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-pequeno btn-icono" 
                            onclick="editarRepuesto(${rep.id}, '${escapeHtml(rep.nombre)}', '${escapeHtml(rep.descripcion)}', ${rep.costo_base}, '${escapeHtml(rep.unidad_medida)}')">
                        ✏️ Editar
                    </button>
                    ${rep.activo == 1 ? 
                        `<button class="btn btn-pequeno btn-peligro" 
                                onclick="desactivarRepuesto(${rep.id})">
                            🗑️ Desactivar
                        </button>` :
                        `<button class="btn btn-pequeno btn-primario" 
                                onclick="activarRepuesto(${rep.id})">
                            ✓ Activar
                        </button>`
                    }
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Abrir modal para nuevo repuesto
function abrirModalRepuesto() {
    document.getElementById('tituloModal').textContent = 'Nuevo Repuesto';
    document.getElementById('formRepuesto').reset();
    document.getElementById('repuesto_id').value = '';
    document.getElementById('modalRepuesto').classList.add('activo');
}

// Editar repuesto
function editarRepuesto(id, nombre, descripcion, costoBase, unidad) {
    document.getElementById('tituloModal').textContent = 'Editar Repuesto';
    document.getElementById('repuesto_id').value = id;
    document.getElementById('nombre_repuesto').value = nombre;
    document.getElementById('descripcion_repuesto').value = descripcion || '';
    document.getElementById('costo_base_repuesto').value = costoBase;
    document.getElementById('unidad_repuesto').value = unidad || '';
    document.getElementById('modalRepuesto').classList.add('activo');
}

// Cerrar modal
function cerrarModalRepuesto() {
    document.getElementById('modalRepuesto').classList.remove('activo');
    document.getElementById('formRepuesto').reset();
}

// Guardar repuesto
async function guardarRepuesto(event) {
    event.preventDefault();
    
    try {
        const formData = new FormData(event.target);
        
        const response = await fetch('ajax/equipos_repuestos_guardar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            cerrarModalRepuesto();
            cargarRepuestos();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar repuesto');
    }
}

// Desactivar repuesto
async function desactivarRepuesto(id) {
    if (!confirm('¿Está seguro que desea desactivar este repuesto?')) {
        return;
    }
    
    try {
        const response = await fetch('ajax/equipos_repuestos_estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                repuesto_id: id,
                activo: 0
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Repuesto desactivado');
            cargarRepuestos();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al desactivar repuesto');
    }
}

// Activar repuesto
async function activarRepuesto(id) {
    try {
        const response = await fetch('ajax/equipos_repuestos_estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                repuesto_id: id,
                activo: 1
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Repuesto activado');
            cargarRepuestos();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al activar repuesto');
    }
}

// Mostrar error
function mostrarError(mensaje) {
    const tbody = document.querySelector('#tablaRepuestos tbody');
    tbody.innerHTML = `
        <tr>
            <td colspan="6" class="texto-centrado">
                <div class="alerta alerta-error">${mensaje}</div>
            </td>
        </tr>
    `;
}

// Escapar HTML para prevenir XSS
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