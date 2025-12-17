// ============================================
// CALENDARIO DE MANTENIMIENTOS - DRAG & DROP
// ============================================

let draggedElement = null;
let draggedType = null;

document.addEventListener('DOMContentLoaded', function() {
    initDragAndDrop();
});

function initDragAndDrop() {
    // Equipos en sidebar
    const equipoCards = document.querySelectorAll('.equipo-card');
    equipoCards.forEach(card => {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });
    
    // Mantenimientos en calendario
    const mantenimientoCards = document.querySelectorAll('.mantenimiento-card:not(.finalizado)');
    mantenimientoCards.forEach(card => {
        card.addEventListener('dragstart', handleMantenimientoDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });
    
    // Celdas del calendario
    const diaCells = document.querySelectorAll('.dia-cell:not(.otro-mes)');
    diaCells.forEach(cell => {
        cell.addEventListener('dragover', handleDragOver);
        cell.addEventListener('dragleave', handleDragLeave);
        cell.addEventListener('drop', handleDrop);
    });
}

function handleDragStart(e) {
    draggedElement = this;
    draggedType = 'equipo';
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.innerHTML);
}

function handleMantenimientoDragStart(e) {
    draggedElement = this;
    draggedType = 'mantenimiento';
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.innerHTML);
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    
    // Remover clase de todas las celdas
    document.querySelectorAll('.dia-cell').forEach(cell => {
        cell.classList.remove('drop-zone');
    });
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    
    this.classList.add('drop-zone');
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDragLeave(e) {
    this.classList.remove('drop-zone');
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    this.classList.remove('drop-zone');
    
    const fecha = this.dataset.fecha;
    
    if (draggedType === 'equipo') {
        // Programar mantenimiento desde sidebar
        const equipoId = draggedElement.dataset.equipoId;
        const tipo = draggedElement.dataset.tipo;
        const solicitudId = draggedElement.dataset.solicitudId || null;
        
        programarMantenimiento(equipoId, fecha, tipo, solicitudId);
    } else if (draggedType === 'mantenimiento') {
        // Mover mantenimiento existente
        const programadoId = draggedElement.dataset.programadoId;
        moverMantenimiento(programadoId, fecha);
    }
    
    return false;
}

function programarMantenimiento(equipoId, fecha, tipo, solicitudId) {
    showLoading(true);
    
    fetch('ajax/equipos_calendario_actualizar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            accion: 'programar',
            equipo_id: equipoId,
            fecha: fecha,
            tipo: tipo,
            solicitud_id: solicitudId
        })
    })
    .then(response => response.json())
    .then(result => {
        showLoading(false);
        if (result.success) {
            showAlert('Mantenimiento programado exitosamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(result.message || 'Error al programar mantenimiento', 'danger');
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('Error:', error);
        showAlert('Error al procesar la solicitud', 'danger');
    });
}

function moverMantenimiento(programadoId, nuevaFecha) {
    showLoading(true);
    
    fetch('ajax/equipos_calendario_actualizar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            accion: 'mover',
            programado_id: programadoId,
            nueva_fecha: nuevaFecha
        })
    })
    .then(response => response.json())
    .then(result => {
        showLoading(false);
        if (result.success) {
            showAlert('Mantenimiento movido exitosamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(result.message || 'Error al mover mantenimiento', 'danger');
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('Error:', error);
        showAlert('Error al procesar la solicitud', 'danger');
    });
}

function desprogramar(programadoId) {
    if (!confirm('¿Está seguro de desprogramar este mantenimiento?')) {
        return;
    }
    
    showLoading(true);
    
    fetch('ajax/equipos_calendario_actualizar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            accion: 'desprogramar',
            programado_id: programadoId
        })
    })
    .then(response => response.json())
    .then(result => {
        showLoading(false);
        if (result.success) {
            showAlert('Mantenimiento desprogramado', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(result.message || 'Error al desprogramar', 'danger');
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('Error:', error);
        showAlert('Error al procesar la solicitud', 'danger');
    });
}

function abrirReporte(programadoId, equipoId) {
    window.location.href = `equipos_reporte_mantenimiento.php?programado_id=${programadoId}&equipo_id=${equipoId}`;
}

function buscarEquipo() {
    const termino = document.getElementById('buscar-equipo').value;
    const container = document.getElementById('resultados-busqueda');
    
    if (termino.length < 2) {
        container.innerHTML = '';
        return;
    }
    
    showLoading(true);
    
    fetch('ajax/equipos_datos.php?accion=buscar&termino=' + encodeURIComponent(termino))
        .then(response => response.json())
        .then(result => {
            showLoading(false);
            if (result.success) {
                mostrarResultados(result.data);
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Error:', error);
        });
}

function mostrarResultados(equipos) {
    const container = document.getElementById('resultados-busqueda');
    container.innerHTML = '';
    
    if (equipos.length === 0) {
        container.innerHTML = '<p style="color: #666; padding: 10px;">No se encontraron equipos</p>';
        return;
    }
    
    equipos.forEach(eq => {
        const card = document.createElement('div');
        card.className = 'equipo-card';
        card.draggable = true;
        card.dataset.equipoId = eq.id;
        card.dataset.tipo = 'preventivo';
        
        card.innerHTML = `
            <div class="equipo-codigo">${eq.codigo}</div>
            <div class="equipo-info">${eq.marca} ${eq.modelo}</div>
            <div class="equipo-ubicacion">📍 ${eq.ubicacion_actual || 'Sin ubicación'}</div>
        `;
        
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
        
        container.appendChild(card);
    });
}