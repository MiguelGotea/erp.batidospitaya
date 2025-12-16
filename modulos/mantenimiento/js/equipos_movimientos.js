// public_html/modulos/mantenimiento/js/equipos_movimientos.js

document.addEventListener('DOMContentLoaded', function() {
    cargarMovimientos();
    document.getElementById('formEjecutar').addEventListener('submit', ejecutarMovimiento);
});

async function cargarMovimientos() {
    try {
        const response = await fetch('ajax/equipos_movimientos_listar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderizarMovimientos(data.movimientos);
        } else {
            mostrarError(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error al cargar movimientos');
    }
}

function renderizarMovimientos(movimientos) {
    const tbody = document.querySelector('#tablaMovimientos tbody');
    
    if (movimientos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="texto-centrado">No hay movimientos pendientes</td></tr>';
        return;
    }
    
    let html = '';
    movimientos.forEach(mov => {
        html += `
            <tr>
                <td><strong>${mov.equipo_codigo}</strong><br>${mov.equipo_nombre}</td>
                <td>${mov.tipo_movimiento}</td>
                <td>${mov.origen}</td>
                <td>${mov.destino}</td>
                <td>${mov.fecha_planificada}</td>
                <td><span class="badge badge-${getBadgeClass(mov.estado)}">${mov.estado}</span></td>
                <td>
                    ${mov.estado === 'Planificado' ? 
                        `<button class="btn btn-pequeno btn-primario" 
                            onclick="abrirModalEjecutar(${mov.id}, '${mov.equipo_codigo}', '${mov.tipo_movimiento}', '${mov.origen}', '${mov.destino}')">
                            ✓ Ejecutar
                        </button>` : 
                        `<span style="color: #28a745;">✓ Completado</span>`
                    }
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function getBadgeClass(estado) {
    const badges = {
        'Planificado': 'programado',
        'En Tránsito': 'proceso',
        'Completado': 'completado'
    };
    return badges[estado] || 'programado';
}

function abrirModalEjecutar(id, codigo, tipo, origen, destino) {
    document.getElementById('movimiento_id').value = id;
    
    document.getElementById('infoMovimiento').innerHTML = `
        <strong>Equipo:</strong> ${codigo}<br>
        <strong>Movimiento:</strong> ${tipo}<br>
        <strong>De:</strong> ${origen}<br>
        <strong>A:</strong> ${destino}
    `;
    
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('fecha_ejecutada').value = hoy;
    
    document.getElementById('modalEjecutar').classList.add('activo');
}

function cerrarModalEjecutar() {
    document.getElementById('modalEjecutar').classList.remove('activo');
    document.getElementById('formEjecutar').reset();
}

async function ejecutarMovimiento(event) {
    event.preventDefault();
    
    try {
        const formData = new FormData(event.target);
        
        const response = await fetch('ajax/equipos_movimiento_ejecutar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Movimiento ejecutado exitosamente');
            cerrarModalEjecutar();
            cargarMovimientos();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al ejecutar movimiento');
    }
}

function mostrarError(mensaje) {
    const tbody = document.querySelector('#tablaMovimientos tbody');
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="texto-centrado">
                <div class="alerta alerta-error">${mensaje}</div>
            </td>
        </tr>
    `;
}