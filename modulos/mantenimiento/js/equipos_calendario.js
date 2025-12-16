// public_html/modulos/mantenimiento/js/equipos_calendario.js

let equiposDisponibles = [];
let mantenimientosAgendados = {};

document.addEventListener('DOMContentLoaded', function() {
    cargarCalendario();
    cargarEquiposDisponibles();
    
    document.getElementById('buscarEquipo').addEventListener('keyup', filtrarEquipos);
    document.getElementById('formMovimiento').addEventListener('submit', guardarMovimiento);
});

// Cambiar mes
function cambiarMes(incremento) {
    mesActual += incremento;
    if (mesActual > 12) {
        mesActual = 1;
        anioActual++;
    } else if (mesActual < 1) {
        mesActual = 12;
        anioActual--;
    }
    cargarCalendario();
    cargarEquiposDisponibles();
}

// Cargar calendario
async function cargarCalendario() {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    document.getElementById('mesAnio').textContent = `${meses[mesActual - 1]} ${anioActual}`;
    
    const primerDia = new Date(anioActual, mesActual - 1, 1);
    const ultimoDia = new Date(anioActual, mesActual, 0);
    const diasMes = ultimoDia.getDate();
    const diaSemanaInicio = primerDia.getDay();
    
    let html = `
        <div class="dia-semana">Dom</div>
        <div class="dia-semana">Lun</div>
        <div class="dia-semana">Mar</div>
        <div class="dia-semana">Mié</div>
        <div class="dia-semana">Jue</div>
        <div class="dia-semana">Vie</div>
        <div class="dia-semana">Sáb</div>
    `;
    
    // Días vacíos antes del primer día
    for (let i = 0; i < diaSemanaInicio; i++) {
        html += '<div class="dia-calendario otro-mes"></div>';
    }
    
    // Días del mes
    for (let dia = 1; dia <= diasMes; dia++) {
        const fechaCompleta = `${anioActual}-${String(mesActual).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        html += `
            <div class="dia-calendario" data-fecha="${fechaCompleta}" 
                 ondrop="soltar(event)" ondragover="permitirSoltar(event)">
                <div class="numero-dia">${dia}</div>
                <div class="equipos-dia" id="dia-${fechaCompleta}"></div>
            </div>
        `;
    }
    
    document.getElementById('calendarioGrid').innerHTML = html;
    
    // Cargar mantenimientos agendados
    await cargarMantenimientosAgendados();
}

// Cargar mantenimientos agendados
async function cargarMantenimientosAgendados() {
    try {
        const response = await fetch('ajax/equipos_calendario_agendados.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mes: mesActual, anio: anioActual })
        });
        
        const data = await response.json();
        
        if (data.success) {
            data.mantenimientos.forEach(mtto => {
                const diaDiv = document.getElementById(`dia-${mtto.fecha}`);
                if (diaDiv) {
                    diaDiv.innerHTML += `
                        <div class="equipo-calendario" draggable="true" 
                             data-equipo-id="${mtto.equipo_id}"
                             ondragstart="arrastrar(event)">
                            ${mtto.codigo} - ${mtto.nombre}
                        </div>
                    `;
                }
            });
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Cargar equipos disponibles para agendar
async function cargarEquiposDisponibles() {
    try {
        const response = await fetch('ajax/equipos_calendario_disponibles.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mes: mesActual, anio: anioActual })
        });
        
        const data = await response.json();
        
        if (data.success) {
            equiposDisponibles = data.equipos;
            renderizarEquipos();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Renderizar lista de equipos
function renderizarEquipos(filtro = '') {
    const listaDiv = document.getElementById('listaEquipos');
    const filtroLower = filtro.toLowerCase();
    
    const equiposFiltrados = equiposDisponibles.filter(eq => 
        eq.codigo.toLowerCase().includes(filtroLower) ||
        eq.nombre.toLowerCase().includes(filtroLower)
    );
    
    if (equiposFiltrados.length === 0) {
        listaDiv.innerHTML = '<p class="texto-centrado">No hay equipos para agendar</p>';
        return;
    }
    
    let html = '';
    equiposFiltrados.forEach(equipo => {
        let claseEstado = 'preventivo';
        if (equipo.retrasado) claseEstado = 'retrasado';
        else if (equipo.tipo === 'Correctivo') claseEstado = 'correctivo';
        
        html += `
            <div class="equipo-sidebar ${claseEstado}" draggable="true" 
                 data-equipo-id="${equipo.equipo_id}"
                 data-tipo="${equipo.tipo}"
                 data-ubicacion="${equipo.ubicacion}"
                 data-sucursal-id="${equipo.sucursal_id || ''}"
                 ondragstart="arrastrar(event)">
                <strong>${equipo.codigo}</strong><br>
                ${equipo.nombre}<br>
                <small>${equipo.ubicacion}</small>
                ${equipo.en_sucursal ? 
                    `<br><button class="btn btn-pequeno btn-primario" 
                        style="margin-top: 5px;" 
                        onclick="abrirModalMovimiento(${equipo.equipo_id}, ${equipo.solicitud_id}, '${equipo.codigo}', '${equipo.nombre}', '${equipo.ubicacion}', ${equipo.sucursal_id})">
                        📦 Movimiento
                    </button>` : ''}
            </div>
        `;
    });
    
    listaDiv.innerHTML = html;
}

// Filtrar equipos
function filtrarEquipos() {
    const filtro = document.getElementById('buscarEquipo').value;
    renderizarEquipos(filtro);
}

// Drag and drop
function arrastrar(event) {
    const equipoId = event.target.dataset.equipoId;
    const tipo = event.target.dataset.tipo;
    const ubicacion = event.target.dataset.ubicacion;
    
    event.dataTransfer.setData('equipoId', equipoId);
    event.dataTransfer.setData('tipo', tipo);
    event.dataTransfer.setData('ubicacion', ubicacion);
}

function permitirSoltar(event) {
    event.preventDefault();
}

async function soltar(event) {
    event.preventDefault();
    
    const equipoId = event.dataTransfer.getData('equipoId');
    const tipo = event.dataTransfer.getData('tipo');
    const ubicacion = event.dataTransfer.getData('ubicacion');
    const fecha = event.currentTarget.dataset.fecha;
    
    // Solo se pueden arrastrar equipos que están en Central
    if (ubicacion !== 'Almacén Central') {
        alert('Solo puede agendar directamente equipos que están en el Almacén Central. Para equipos en sucursales, use el botón "Movimiento".');
        return;
    }
    
    if (confirm(`¿Desea agendar el mantenimiento para el ${fecha}?`)) {
        await agendarMantenimiento(equipoId, fecha, tipo);
    }
}

// Agendar mantenimiento
async function agendarMantenimiento(equipoId, fecha, tipo) {
    try {
        const response = await fetch('ajax/equipos_calendario_agendar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                equipo_id: equipoId,
                fecha: fecha,
                tipo: tipo,
                registrado_por: usuarioId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Mantenimiento agendado exitosamente');
            cargarCalendario();
            cargarEquiposDisponibles();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al agendar mantenimiento');
    }
}

// Abrir modal de movimiento
async function abrirModalMovimiento(equipoId, solicitudId, codigo, nombre, ubicacion, sucursalId) {
    document.getElementById('solicitud_id').value = solicitudId || '';
    
    document.getElementById('infoEquipoMovimiento').innerHTML = `
        <strong>Equipo en Sucursal:</strong> ${codigo} - ${nombre}<br>
        <strong>Ubicación:</strong> ${ubicacion}
    `;
    
    // Cargar equipos disponibles en central
    const response = await fetch('ajax/equipos_central_disponibles.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ equipo_actual_id: equipoId })
    });
    
    const data = await response.json();
    
    if (data.success) {
        const select = document.getElementById('equipo_enviar_id');
        select.innerHTML = '<option value="">Seleccione un equipo del almacén central</option>';
        
        data.equipos.forEach(eq => {
            select.innerHTML += `<option value="${eq.id}">${eq.codigo} - ${eq.nombre}</option>`;
        });
    }
    
    // Establecer fecha mínima (hoy)
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('fecha_movimiento').min = hoy;
    
    document.getElementById('modalMovimiento').classList.add('activo');
}

// Cerrar modal
function cerrarModal() {
    document.getElementById('modalMovimiento').classList.remove('activo');
    document.getElementById('formMovimiento').reset();
}

// Guardar movimiento
async function guardarMovimiento(event) {
    event.preventDefault();
    
    try {
        const formData = new FormData(event.target);
        
        const response = await fetch('ajax/equipos_movimiento_guardar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Movimiento agendado exitosamente');
            cerrarModal();
            cargarEquiposDisponibles();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar movimiento');
    }
}